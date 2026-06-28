<?php
/**
 * Panel de Administracion - Avisos a Padres (Mejorado)
 * SGA COBAEV
 * 
 * Soporta:
 * - Categorias: institucional, academico, emergencia, pagos, cultural
 * - Prioridades: normal, importante, urgente
 * - Destinatarios: todos (masivo), por grupo, individual (matricula)
 * - Notificaciones push ricas via FCM
 */
require_once 'includes/auth.php';

require_once '../conexion.php';

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje_exito = '';
$mensaje_error = '';

// Obtener grupos disponibles para el selector
$grupos_disponibles = [];
try {
    // Intentar con filtro activo primero
    $stmt_grupos = $pdo->query("SELECT DISTINCT grupo FROM alumnos WHERE activo = 1 AND grupo IS NOT NULL AND grupo != '' ORDER BY grupo");
    $grupos_disponibles = $stmt_grupos->fetchAll(PDO::FETCH_COLUMN);
    
    // Si no hay resultados, intentar sin filtro activo
    if (empty($grupos_disponibles)) {
        $stmt_grupos = $pdo->query("SELECT DISTINCT grupo FROM alumnos WHERE grupo IS NOT NULL AND grupo != '' ORDER BY grupo");
        $grupos_disponibles = $stmt_grupos->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    // Si la columna activo no existe, intentar sin ella
    try {
        $stmt_grupos = $pdo->query("SELECT DISTINCT grupo FROM alumnos WHERE grupo IS NOT NULL AND grupo != '' ORDER BY grupo");
        $grupos_disponibles = $stmt_grupos->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e2) {
        error_log('SGA Error [avisos grupos]: ' . $e2->getMessage());
    }
}

// Procesar envio de aviso
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    // Validar CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje_error = 'Token de seguridad invalido. Recarga la pagina.';
    } else {
        $accion = $_POST['accion'];

        if ($accion === 'crear_aviso') {
            $titulo = trim($_POST['titulo'] ?? '');
            $mensaje = trim($_POST['mensaje'] ?? '');
            $categoria = trim($_POST['categoria'] ?? 'institucional');
            $prioridad = trim($_POST['prioridad'] ?? 'normal');
            $tipo_destinatario = trim($_POST['tipo_destinatario'] ?? 'todos');
            $grupo_seleccionado = trim($_POST['grupo_seleccionado'] ?? '');
            $matricula_especifica = trim($_POST['matricula_especifica'] ?? '');

            // Validar categoria
            $categorias_validas = ['institucional', 'academico', 'emergencia', 'pagos', 'cultural'];
            if (!in_array($categoria, $categorias_validas)) {
                $categoria = 'institucional';
            }

            // Validar prioridad
            $prioridades_validas = ['normal', 'importante', 'urgente'];
            if (!in_array($prioridad, $prioridades_validas)) {
                $prioridad = 'normal';
            }

            // Determinar destinatario final
            $destinatario = 'todos';
            if ($tipo_destinatario === 'grupo') {
                if (empty($grupo_seleccionado)) {
                    $mensaje_error = 'Debe seleccionar un grupo.';
                } else {
                    $destinatario = 'grupo:' . $grupo_seleccionado;
                }
            } elseif ($tipo_destinatario === 'individual') {
                if (empty($matricula_especifica)) {
                    $mensaje_error = 'Debe ingresar una matricula.';
                } elseif (!preg_match('/^\d{9}$/', $matricula_especifica)) {
                    $mensaje_error = 'La matricula debe tener 9 digitos numericos.';
                } else {
                    $destinatario = trim($matricula_especifica);
                }
            }

            if (empty($titulo) || empty($mensaje)) {
                $mensaje_error = 'El titulo y mensaje son obligatorios.';
            }

            // Validar matricula individual existe
            if (empty($mensaje_error) && $tipo_destinatario === 'individual') {
                $stmt_check = $pdo->prepare("SELECT COUNT(*) as existe FROM alumnos WHERE matricula = :mat");
                $stmt_check->execute(['mat' => $destinatario]);
                if ($stmt_check->fetch()['existe'] == 0) {
                    $mensaje_error = 'La matricula especificada no existe en el sistema.';
                }
            }

            if (empty($mensaje_error)) {
                try {
                    // Guardar aviso en BD con campos mejorados
                    $stmt = $pdo->prepare("INSERT INTO avisos (titulo, mensaje, contenido, categoria, prioridad, destinatario, creado_por, fecha_envio, fecha_publicacion) VALUES (:titulo, :mensaje, :contenido, :categoria, :prioridad, :destinatario, :creado_por, NOW(), NOW())");
                    $stmt->execute([
                        'titulo' => $titulo,
                        'mensaje' => $mensaje,
                        'contenido' => $mensaje,
                        'categoria' => $categoria,
                        'prioridad' => $prioridad,
                        'destinatario' => $destinatario,
                        'creado_por' => $_SESSION['username'] ?? $_SESSION['usuario_nombre'] ?? 'admin'
                    ]);

                    $id_aviso = $pdo->lastInsertId();

                    // Enviar notificacion push a los padres afectados
                    require_once '../enviar_notificacion.php';
                    
                    $tokens_enviados = 0;
                    $preview = mb_substr($mensaje, 0, 100);
                    
                    if ($tipo_destinatario === 'todos') {
                        // Obtener TODOS los tokens activos
                        $stmt_tokens = $pdo->query("SELECT DISTINCT token_fcm FROM dispositivos_padres");
                        $tokens = $stmt_tokens->fetchAll();
                    } elseif ($tipo_destinatario === 'grupo') {
                        // Obtener matriculas del grupo y luego sus tokens
                        $stmt_matriculas = $pdo->prepare("SELECT matricula FROM alumnos WHERE grupo = :grupo AND activo = 1");
                        $stmt_matriculas->execute(['grupo' => $grupo_seleccionado]);
                        $matriculas_grupo = $stmt_matriculas->fetchAll(PDO::FETCH_COLUMN);
                        
                        $tokens = [];
                        if (!empty($matriculas_grupo)) {
                            $placeholders = implode(',', array_fill(0, count($matriculas_grupo), '?'));
                            $stmt_tokens = $pdo->prepare("SELECT DISTINCT token_fcm FROM dispositivos_padres WHERE matricula_alumno IN ($placeholders)");
                            $stmt_tokens->execute($matriculas_grupo);
                            $tokens = $stmt_tokens->fetchAll();
                        }
                    } else {
                        // Individual - obtener token de la matricula especifica
                        $stmt_tokens = $pdo->prepare("SELECT token_fcm FROM dispositivos_padres WHERE matricula_alumno = :matricula");
                        $stmt_tokens->execute(['matricula' => $destinatario]);
                        $tokens = $stmt_tokens->fetchAll();
                    }

                    foreach ($tokens as $row) {
                        if (!empty($row['token_fcm'])) {
                            if (function_exists('enviarAvisoRicoFirebase')) {
                                enviarAvisoRicoFirebase($row['token_fcm'], $titulo, $preview, $id_aviso, $categoria, $prioridad);
                            } else {
                                enviarAlertaFirebase($row['token_fcm'], "Nuevo aviso: " . $titulo);
                            }
                            $tokens_enviados++;
                        }
                    }

                    $mensaje_exito = "Aviso enviado correctamente. Notificacion push enviada a {$tokens_enviados} dispositivo(s).";
                } catch (PDOException $e) {
                    error_log('SGA Error [avisos crear]: ' . $e->getMessage());
                    $mensaje_error = 'Error interno del servidor. Intente de nuevo mas tarde.';
                }
            }
        } elseif ($accion === 'eliminar_aviso') {
            $id_aviso = intval($_POST['id_aviso'] ?? 0);
            if ($id_aviso > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM avisos WHERE id_aviso = :id");
                    $stmt->execute(['id' => $id_aviso]);
                    $mensaje_exito = 'Aviso eliminado correctamente.';
                } catch (PDOException $e) {
                    error_log('SGA Error [avisos eliminar]: ' . $e->getMessage());
                    $mensaje_error = 'Error interno del servidor. Intente de nuevo mas tarde.';
                }
            }
        }
    }
}

// Obtener avisos enviados (paginados)
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM avisos");
    $total_avisos = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT * FROM avisos ORDER BY fecha_envio DESC LIMIT " . intval($por_pagina) . " OFFSET " . intval($offset));
    $stmt->execute();
    $avisos = $stmt->fetchAll();
} catch (PDOException $e) {
    $avisos = [];
    $total_avisos = 0;
    error_log('SGA Error [avisos listar]: ' . $e->getMessage());
    $mensaje_error = 'Error interno del servidor. Intente de nuevo mas tarde.';
}

$total_paginas = ceil($total_avisos / $por_pagina);
$pagina_actual = 'avisos';
$page_title = 'Avisos - Panel Admin SGA COBAEV';
$page_header = 'Avisos a Padres';

require_once 'includes/head.php';
require_once 'includes/sidebar.php';
require_once 'includes/header.php';
?>

        <div class="p-4 md:p-8 space-y-6">

            <!-- Mensajes de estado -->
            <?php if ($mensaje_exito): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 rounded-lg p-4 text-sm font-medium">
                    <?= htmlspecialchars($mensaje_exito) ?>
                </div>
            <?php endif; ?>
            <?php if ($mensaje_error): ?>
                <div class="bg-rose-50 border border-rose-200 text-rose-700 rounded-lg p-4 text-sm font-medium">
                    <?= htmlspecialchars($mensaje_error) ?>
                </div>
            <?php endif; ?>

            <!-- Formulario para crear aviso -->
            <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-100">
                    <h3 class="font-serif-elegant text-lg font-bold text-vino">Enviar Nuevo Aviso</h3>
                    <p class="text-xs text-zinc-400 mt-0.5">Los avisos se mostraran en el portal de padres con notificacion push</p>
                </div>
                <form method="POST" id="form-aviso" class="p-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="crear_aviso">

                    <!-- Fila 1: Titulo y Destinatario -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Titulo del aviso</label>
                            <input type="text" name="titulo" required maxlength="255" placeholder="Ej: Junta de padres" class="w-full border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Destinatario</label>
                            <select name="tipo_destinatario" id="select-tipo-destinatario" onchange="toggleDestinatario()" class="w-full border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                                <option value="todos">Todos los padres (masivo)</option>
                                <option value="grupo">Por grupo</option>
                                <option value="individual">Matricula individual</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 2: Selector de grupo o matricula (condicional) -->
                    <div id="campo-grupo" class="hidden">
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Seleccionar Grupo</label>
                        <select name="grupo_seleccionado" id="select-grupo" class="w-full md:w-1/2 border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                            <option value="">-- Seleccione un grupo --</option>
                            <?php foreach ($grupos_disponibles as $grupo): ?>
                                <option value="<?= htmlspecialchars($grupo) ?>"><?= htmlspecialchars($grupo) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div id="campo-matricula" class="hidden">
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Matricula del alumno</label>
                        <input type="text" name="matricula_especifica" id="input-matricula" placeholder="Ej: 122310104" maxlength="9" class="w-full md:w-1/2 border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                    </div>

                    <!-- Fila 3: Categoria y Prioridad -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Categoria</label>
                            <select name="categoria" class="w-full border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                                <option value="institucional">&#127963; Institucional</option>
                                <option value="academico">&#128218; Academico</option>
                                <option value="emergencia">&#128680; Emergencia</option>
                                <option value="pagos">&#128176; Pagos</option>
                                <option value="cultural">&#127917; Cultural</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Prioridad</label>
                            <select name="prioridad" class="w-full border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                                <option value="normal">Normal</option>
                                <option value="importante">&#9888;&#65039; Importante</option>
                                <option value="urgente">&#128308; Urgente</option>
                            </select>
                        </div>
                    </div>

                    <!-- Fila 4: Mensaje -->
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Mensaje</label>
                        <textarea name="mensaje" required rows="4" maxlength="2000" placeholder="Escribe el contenido del aviso..." class="w-full border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors resize-none"></textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="bg-vino hover:bg-opacity-90 text-white font-bold text-xs uppercase tracking-wider px-6 py-3 rounded-lg transition-colors flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <span>Enviar Aviso</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Lista de avisos enviados -->
            <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-serif-elegant text-lg font-bold text-vino">Avisos Enviados</h3>
                        <p class="text-xs text-zinc-400 mt-0.5"><?= $total_avisos ?> aviso(s) en total</p>
                    </div>
                </div>

                <?php if (empty($avisos)): ?>
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 mx-auto bg-zinc-50 rounded-2xl flex items-center justify-center mb-4">
                            <svg class="w-8 h-8 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                        </div>
                        <p class="text-sm font-semibold text-zinc-500">No hay avisos enviados</p>
                        <p class="text-xs text-zinc-400 mt-1">Usa el formulario de arriba para enviar el primer aviso</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 tracking-wide">
                                <tr>
                                    <th class="px-6 py-3 text-left">Titulo</th>
                                    <th class="px-6 py-3 text-left">Destinatario</th>
                                    <th class="px-6 py-3 text-center">Categoria</th>
                                    <th class="px-6 py-3 text-center">Prioridad</th>
                                    <th class="px-6 py-3 text-center">Estado</th>
                                    <th class="px-6 py-3 text-left">Enviado por</th>
                                    <th class="px-6 py-3 text-left">Fecha</th>
                                    <th class="px-6 py-3 text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-50">
                                <?php foreach ($avisos as $aviso): ?>
                                    <tr class="hover:bg-zinc-50 transition-colors">
                                        <td class="px-6 py-3">
                                            <p class="font-semibold text-zinc-700"><?= htmlspecialchars($aviso['titulo']) ?></p>
                                            <p class="text-xs text-zinc-400 mt-0.5 truncate max-w-[200px]"><?= htmlspecialchars(mb_substr($aviso['mensaje'], 0, 60)) ?><?= mb_strlen($aviso['mensaje']) > 60 ? '...' : '' ?></p>
                                        </td>
                                        <td class="px-6 py-3">
                                            <?php if ($aviso['destinatario'] === 'todos'): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700">Todos</span>
                                            <?php elseif (strpos($aviso['destinatario'], 'grupo:') === 0): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-50 text-purple-700">Grupo <?= htmlspecialchars(substr($aviso['destinatario'], 6)) ?></span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 font-mono"><?= htmlspecialchars($aviso['destinatario']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <?php
                                            $cat = $aviso['categoria'] ?? 'institucional';
                                            $cat_colors = [
                                                'institucional' => 'bg-slate-100 text-slate-700',
                                                'academico' => 'bg-blue-50 text-blue-700',
                                                'emergencia' => 'bg-red-50 text-red-700',
                                                'pagos' => 'bg-green-50 text-green-700',
                                                'cultural' => 'bg-violet-50 text-violet-700'
                                            ];
                                            $cat_class = $cat_colors[$cat] ?? 'bg-slate-100 text-slate-700';
                                            ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= $cat_class ?>"><?= htmlspecialchars(ucfirst($cat)) ?></span>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <?php
                                            $pri = $aviso['prioridad'] ?? 'normal';
                                            $pri_colors = [
                                                'normal' => 'bg-zinc-100 text-zinc-600',
                                                'importante' => 'bg-amber-50 text-amber-700',
                                                'urgente' => 'bg-red-50 text-red-700'
                                            ];
                                            $pri_class = $pri_colors[$pri] ?? 'bg-zinc-100 text-zinc-600';
                                            ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= $pri_class ?>"><?= htmlspecialchars(ucfirst($pri)) ?></span>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <?php if (!empty($aviso['leido']) && $aviso['leido']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Leido</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-zinc-100 text-zinc-600">No leido</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-xs text-zinc-500"><?= htmlspecialchars($aviso['creado_por']) ?></td>
                                        <td class="px-6 py-3 text-xs text-zinc-500"><?= date('d/m/Y H:i', strtotime($aviso['fecha_envio'])) ?></td>
                                        <td class="px-6 py-3 text-center">
                                            <form method="POST" class="inline" onsubmit="return confirm('Eliminar este aviso?')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="accion" value="eliminar_aviso">
                                                <input type="hidden" name="id_aviso" value="<?= $aviso['id_aviso'] ?>">
                                                <button type="submit" class="text-rose-500 hover:text-rose-700 transition-colors" title="Eliminar">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginacion -->
                    <?php if ($total_paginas > 1): ?>
                        <div class="px-6 py-4 border-t border-zinc-100 flex items-center justify-between">
                            <p class="text-xs text-zinc-400">Pagina <?= $pagina ?> de <?= $total_paginas ?></p>
                            <div class="flex items-center space-x-2">
                                <?php if ($pagina > 1): ?>
                                    <a href="?pagina=<?= $pagina - 1 ?>" class="px-3 py-1.5 text-xs font-medium border border-zinc-200 rounded-lg hover:bg-zinc-50 transition-colors">Anterior</a>
                                <?php endif; ?>
                                <?php if ($pagina < $total_paginas): ?>
                                    <a href="?pagina=<?= $pagina + 1 ?>" class="px-3 py-1.5 text-xs font-medium border border-zinc-200 rounded-lg hover:bg-zinc-50 transition-colors">Siguiente</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <script>
        // Toggle campos de destinatario segun tipo seleccionado
        function toggleDestinatario() {
            const tipo = document.getElementById('select-tipo-destinatario').value;
            const campoGrupo = document.getElementById('campo-grupo');
            const campoMatricula = document.getElementById('campo-matricula');
            const selectGrupo = document.getElementById('select-grupo');
            const inputMatricula = document.getElementById('input-matricula');

            // Ocultar ambos por defecto
            campoGrupo.classList.add('hidden');
            campoMatricula.classList.add('hidden');
            selectGrupo.required = false;
            inputMatricula.required = false;

            if (tipo === 'grupo') {
                campoGrupo.classList.remove('hidden');
                selectGrupo.required = true;
            } else if (tipo === 'individual') {
                campoMatricula.classList.remove('hidden');
                inputMatricula.required = true;
            }
        }
        </script>

<?php require_once 'includes/footer.php'; ?>
