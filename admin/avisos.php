<?php
/**
 * Panel de Administracion - Avisos a Padres
 * SGA COBAEV
 * 
 * SQL para crear la tabla:
 * CREATE TABLE avisos (
 *   id_aviso INT AUTO_INCREMENT PRIMARY KEY,
 *   titulo VARCHAR(255) NOT NULL,
 *   mensaje TEXT NOT NULL,
 *   destinatario VARCHAR(50) NOT NULL DEFAULT 'todos',
 *   creado_por VARCHAR(100) NOT NULL,
 *   fecha_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *   leido TINYINT NOT NULL DEFAULT 0
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 */
session_start();
if (!isset($_SESSION['usuario_autenticado']) || $_SESSION['usuario_autenticado'] !== true) {
    header("Location: ../login_admin.php");
    exit;
}

require_once '../conexion.php';

// Obtener grupos disponibles para el select
$grupos = [];
try {
    $stmt_grupos = $pdo->query("SELECT DISTINCT grupo FROM alumnos WHERE grupo IS NOT NULL AND grupo != '' ORDER BY grupo");
    $grupos = $stmt_grupos->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('SGA Error [avisos grupos]: ' . $e->getMessage());
}

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje_exito = '';
$mensaje_error = '';

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
            $destinatario = trim($_POST['destinatario'] ?? 'todos');

            if (empty($titulo) || empty($mensaje)) {
                $mensaje_error = 'El titulo y mensaje son obligatorios.';
            } elseif ($destinatario !== 'todos' && !preg_match('/^[A-Z]\d{7}$/i', $destinatario) && !str_starts_with($destinatario, 'grupo:')) {
                $mensaje_error = 'El destinatario debe ser "todos", una matricula valida o un grupo valido.';
            } else {
                // Validar que la matricula existe si es una matricula
                if ($destinatario !== 'todos' && !str_starts_with($destinatario, 'grupo:')) {
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) as existe FROM alumnos WHERE matricula = :mat");
                    $stmt_check->execute(['mat' => strtoupper($destinatario)]);
                    if ($stmt_check->fetch()['existe'] == 0) {
                        $mensaje_error = 'La matricula especificada no existe en el sistema.';
                    }
                    $destinatario = strtoupper($destinatario);
                }

                // Validar que el grupo existe si es grupo
                if (str_starts_with($destinatario, 'grupo:')) {
                    $grupo_nombre = substr($destinatario, 6);
                    $stmt_check = $pdo->prepare("SELECT COUNT(*) as existe FROM alumnos WHERE grupo = :grupo");
                    $stmt_check->execute(['grupo' => $grupo_nombre]);
                    if ($stmt_check->fetch()['existe'] == 0) {
                        $mensaje_error = 'El grupo especificado no existe en el sistema.';
                    }
                }

                if (empty($mensaje_error)) {
                try {
                    // Guardar aviso en BD
                    $stmt = $pdo->prepare("INSERT INTO avisos (titulo, mensaje, destinatario, creado_por, fecha_envio) VALUES (:titulo, :mensaje, :destinatario, :creado_por, NOW())");
                    $stmt->execute([
                        'titulo' => $titulo,
                        'mensaje' => $mensaje,
                        'destinatario' => $destinatario,
                        'creado_por' => $_SESSION['username'] ?? $_SESSION['usuario_nombre'] ?? 'admin'
                    ]);

                    // Enviar notificacion push a los padres afectados
                    require_once '../enviar_notificacion.php';
                    
                    $tokens_enviados = 0;
                    
                    if ($destinatario === 'todos') {
                        // Obtener TODOS los tokens activos
                        $stmt_tokens = $pdo->query("SELECT DISTINCT token_fcm FROM dispositivos_padres");
                        $tokens = $stmt_tokens->fetchAll();
                    } elseif (str_starts_with($destinatario, 'grupo:')) {
                        // Obtener tokens de los alumnos del grupo
                        $grupo_nombre = substr($destinatario, 6);
                        $stmt_tokens = $pdo->prepare("SELECT DISTINCT dp.token_fcm FROM dispositivos_padres dp INNER JOIN alumnos a ON dp.matricula_alumno = a.matricula WHERE a.grupo = :grupo");
                        $stmt_tokens->execute(['grupo' => $grupo_nombre]);
                        $tokens = $stmt_tokens->fetchAll();
                    } else {
                        // Obtener token de la matricula especifica
                        $stmt_tokens = $pdo->prepare("SELECT token_fcm FROM dispositivos_padres WHERE matricula_alumno = :matricula");
                        $stmt_tokens->execute(['matricula' => $destinatario]);
                        $tokens = $stmt_tokens->fetchAll();
                    }

                    foreach ($tokens as $row) {
                        if (!empty($row['token_fcm'])) {
                            enviarAlertaFirebase($row['token_fcm'], $titulo);
                            $tokens_enviados++;
                        }
                    }

                    $mensaje_exito = "Aviso enviado correctamente. Notificación push enviada a {$tokens_enviados} dispositivo(s).";
                } catch (PDOException $e) {
                    error_log('SGA Error [avisos crear]: ' . $e->getMessage());
                    $mensaje_error = 'Error interno del servidor. Intente de nuevo mas tarde.';
                }
                } // end empty($mensaje_error)
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
                    <p class="text-xs text-zinc-400 mt-0.5">Los avisos se mostraran en el portal de padres</p>
                </div>
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="crear_aviso">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Titulo del aviso</label>
                            <input type="text" name="titulo" required maxlength="255" placeholder="Ej: Junta de padres" class="w-full border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1">Destinatario</label>
                            <div class="flex items-center space-x-3">
                                <select name="destinatario" id="select-destinatario" onchange="toggleDestinatario()" class="flex-1 border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                                    <option value="todos">Todos los padres</option>
                                    <option value="matricula">Matricula especifica</option>
                                    <option value="grupo">Por grupo</option>
                                </select>
                                <input type="text" name="matricula_especifica" id="input-matricula" placeholder="Ej: B2024001" maxlength="50" class="hidden flex-1 border border-zinc-200 rounded-lg px-4 py-2.5 text-sm uppercase focus:outline-none focus:border-vino transition-colors">
                                <select name="grupo_especifico" id="select-grupo" class="hidden flex-1 border border-zinc-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                                    <option value="">Selecciona un grupo</option>
                                    <?php foreach ($grupos as $grupo): ?>
                                        <option value="<?= htmlspecialchars($grupo) ?>"><?= htmlspecialchars($grupo) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

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
                                            <?php elseif (str_starts_with($aviso['destinatario'], 'grupo:')): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-50 text-purple-700"><?= htmlspecialchars(substr($aviso['destinatario'], 6)) ?></span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 font-mono"><?= htmlspecialchars($aviso['destinatario']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-center">
                                            <?php if ($aviso['leido']): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Leido</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-zinc-100 text-zinc-600">No leido</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-3 text-xs text-zinc-500"><?= htmlspecialchars($aviso['creado_por']) ?></td>
                                        <td class="px-6 py-3 text-xs text-zinc-500"><?= date('d/m/Y H:i', strtotime($aviso['fecha_envio'])) ?></td>
                                        <td class="px-6 py-3 text-center">
                                            <form method="POST" class="inline" onsubmit="return confirm('¿Eliminar este aviso?')">
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
        // Toggle campos de destinatario
        function toggleDestinatario() {
            const select = document.getElementById('select-destinatario');
            const input = document.getElementById('input-matricula');
            const selectGrupo = document.getElementById('select-grupo');
            if (select.value === 'matricula') {
                input.classList.remove('hidden');
                input.required = true;
                selectGrupo.classList.add('hidden');
                selectGrupo.required = false;
            } else if (select.value === 'grupo') {
                selectGrupo.classList.remove('hidden');
                selectGrupo.required = true;
                input.classList.add('hidden');
                input.required = false;
                input.value = '';
            } else {
                input.classList.add('hidden');
                input.required = false;
                input.value = '';
                selectGrupo.classList.add('hidden');
                selectGrupo.required = false;
            }
        }

        // Antes de enviar, poner el valor real del destinatario
        document.querySelector('form[method="POST"]').addEventListener('submit', function(e) {
            const select = document.getElementById('select-destinatario');
            const input = document.getElementById('input-matricula');
            const selectGrupo = document.getElementById('select-grupo');
            if (select.value === 'matricula' && input.value.trim()) {
                // Crear un input hidden con el valor real de la matricula
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'destinatario';
                hidden.value = input.value.trim().toUpperCase();
                this.appendChild(hidden);
                // Deshabilitar el select para que no envie "matricula" literal
                select.disabled = true;
            } else if (select.value === 'grupo' && selectGrupo.value) {
                // Crear un input hidden con el valor del grupo
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'destinatario';
                hidden.value = 'grupo:' + selectGrupo.value;
                this.appendChild(hidden);
                // Deshabilitar el select para que no envie "grupo" literal
                select.disabled = true;
            }
        });
        </script>

<?php require_once 'includes/footer.php'; ?>
