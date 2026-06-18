<?php
/**
 * Gestion de Tutores - CRUD Completo
 * SGA COBAEV - Panel Administrativo
 */
require_once 'includes/auth.php';

require_once '../conexion.php';

// Generar token CSRF si no existe
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje = '';
$tipo_mensaje = '';

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $mensaje = 'Error: token de seguridad invalido. Recargue la pagina e intente de nuevo.';
        $tipo_mensaje = 'error';
    } else {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear') {
            $password_raw = trim($_POST['password_tutor']);
            if (strlen($password_raw) < 6) {
                $mensaje = 'La contrasena debe tener al menos 6 caracteres.';
                $tipo_mensaje = 'error';
            } else {
                $stmt = $pdo->prepare("INSERT INTO tutores (matricula_alumno, nombre_tutor, password_tutor, telefono_tutor, email_tutor, relacion_parentesco) VALUES (:matricula, :nombre, :password, :telefono, :email, :parentesco)");
                $stmt->execute([
                    'matricula' => strtoupper(trim($_POST['matricula_alumno'])),
                    'nombre' => trim($_POST['nombre_tutor']),
                    'password' => password_hash($password_raw, PASSWORD_DEFAULT),
                    'telefono' => trim($_POST['telefono_tutor'] ?? ''),
                    'email' => trim($_POST['email_tutor'] ?? ''),
                    'parentesco' => $_POST['relacion_parentesco'] ?? ''
                ]);
                $mensaje = 'Tutor registrado exitosamente.';
                $tipo_mensaje = 'success';
            }
        } elseif ($accion === 'editar') {
            $password_raw = trim($_POST['password_tutor'] ?? '');
            if (!empty($password_raw) && strlen($password_raw) < 6) {
                $mensaje = 'La contrasena debe tener al menos 6 caracteres.';
                $tipo_mensaje = 'error';
            } else {
                $campos = "nombre_tutor = :nombre, telefono_tutor = :telefono, email_tutor = :email, relacion_parentesco = :parentesco, matricula_alumno = :matricula";
                $params = [
                    'nombre' => trim($_POST['nombre_tutor']),
                    'telefono' => trim($_POST['telefono_tutor'] ?? ''),
                    'email' => trim($_POST['email_tutor'] ?? ''),
                    'parentesco' => $_POST['relacion_parentesco'] ?? '',
                    'matricula' => strtoupper(trim($_POST['matricula_alumno'])),
                    'id' => $_POST['id_tutor']
                ];
                if (!empty($password_raw)) {
                    $campos .= ", password_tutor = :password";
                    $params['password'] = password_hash($password_raw, PASSWORD_DEFAULT);
                }
                $stmt = $pdo->prepare("UPDATE tutores SET $campos WHERE id_tutor = :id");
                $stmt->execute($params);
                $mensaje = 'Tutor actualizado exitosamente.';
                $tipo_mensaje = 'success';
            }
        } elseif ($accion === 'eliminar') {
            $stmt = $pdo->prepare("DELETE FROM tutores WHERE id_tutor = :id");
            $stmt->execute(['id' => $_POST['id_tutor']]);
            $mensaje = 'Tutor eliminado exitosamente.';
            $tipo_mensaje = 'success';
        }
    } catch (PDOException $e) {
        error_log('SGA Error [tutores]: ' . $e->getMessage());
        $mensaje = 'Error interno del servidor. Intente de nuevo mas tarde.';
        $tipo_mensaje = 'error';
    }
    } // end CSRF validation
}

// Busqueda y paginacion
$busqueda = trim($_GET['buscar'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 15;
$offset = ($pagina - 1) * $por_pagina;

$where = "WHERE 1=1";
$params = [];
if ($busqueda !== '') {
    $where .= " AND (t.nombre_tutor LIKE :buscar OR t.matricula_alumno LIKE :buscar2 OR a.nombre LIKE :buscar3)";
    $params['buscar'] = "%$busqueda%";
    $params['buscar2'] = "%$busqueda%";
    $params['buscar3'] = "%$busqueda%";
}

// Total registros
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM tutores t LEFT JOIN alumnos a ON t.matricula_alumno = a.matricula $where");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_paginas = ceil($total / $por_pagina);

// Obtener tutores con info del alumno
// intval() ensures $por_pagina and $offset are safe integers for LIMIT/OFFSET
$por_pagina_int = intval($por_pagina);
$offset_int = intval($offset);
$stmt = $pdo->prepare("
    SELECT t.*, a.nombre AS alumno_nombre, a.apellido_paterno AS alumno_ap, a.apellido_materno AS alumno_am
    FROM tutores t
    LEFT JOIN alumnos a ON t.matricula_alumno = a.matricula
    $where
    ORDER BY t.nombre_tutor
    LIMIT $por_pagina_int OFFSET $offset_int
");
$stmt->execute($params);
$tutores = $stmt->fetchAll();

// Tutor para editar
$tutor_editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM tutores WHERE id_tutor = :id");
    $stmt->execute(['id' => $_GET['editar']]);
    $tutor_editar = $stmt->fetch();
}

// Lista de alumnos para select
$alumnos_lista = $pdo->query("SELECT matricula, nombre, apellido_paterno FROM alumnos ORDER BY apellido_paterno, nombre")->fetchAll();

$pagina_actual = 'tutores';
$page_title = 'Tutores - Panel Admin SGA COBAEV';
$page_header = 'Gestion de Tutores';

require_once 'includes/head.php';
require_once 'includes/sidebar.php';
require_once 'includes/header.php';
?>

        <div class="p-4 md:p-8 space-y-6">
            <!-- Mensajes -->
            <?php if ($mensaje): ?>
            <div class="rounded-lg p-4 text-sm font-medium <?= $tipo_mensaje === 'success' ? 'bg-green-50 border border-green-200 text-green-700' : 'bg-red-50 border border-red-200 text-red-700' ?>">
                <?= htmlspecialchars($mensaje) ?>
            </div>
            <?php endif; ?>

            <!-- Formulario Crear/Editar -->
            <div class="bg-white rounded-xl border border-zinc-200 p-6">
                <h3 class="font-serif-elegant text-lg font-bold text-vino mb-4">
                    <?= $tutor_editar ? 'Editar Tutor' : 'Registrar Nuevo Tutor' ?>
                </h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="<?= $tutor_editar ? 'editar' : 'crear' ?>">
                    <?php if ($tutor_editar): ?>
                    <input type="hidden" name="id_tutor" value="<?= $tutor_editar['id_tutor'] ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Alumno Vinculado</label>
                        <select name="matricula_alumno" required class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                            <option value="">Seleccionar alumno...</option>
                            <?php foreach ($alumnos_lista as $al): ?>
                            <option value="<?= htmlspecialchars($al['matricula']) ?>" <?= ($tutor_editar && $tutor_editar['matricula_alumno'] === $al['matricula']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($al['matricula'] . ' - ' . $al['nombre'] . ' ' . $al['apellido_paterno']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Nombre del Tutor</label>
                        <input type="text" name="nombre_tutor" required value="<?= htmlspecialchars($tutor_editar['nombre_tutor'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Telefono</label>
                        <input type="text" name="telefono_tutor" value="<?= htmlspecialchars($tutor_editar['telefono_tutor'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Email</label>
                        <input type="email" name="email_tutor" value="<?= htmlspecialchars($tutor_editar['email_tutor'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Parentesco</label>
                        <select name="relacion_parentesco" class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                            <option value="">Seleccionar...</option>
                            <option value="Padre" <?= ($tutor_editar && ($tutor_editar['relacion_parentesco'] ?? '') === 'Padre') ? 'selected' : '' ?>>Padre</option>
                            <option value="Madre" <?= ($tutor_editar && ($tutor_editar['relacion_parentesco'] ?? '') === 'Madre') ? 'selected' : '' ?>>Madre</option>
                            <option value="Tutor" <?= ($tutor_editar && ($tutor_editar['relacion_parentesco'] ?? '') === 'Tutor') ? 'selected' : '' ?>>Tutor</option>
                            <option value="Otro" <?= ($tutor_editar && ($tutor_editar['relacion_parentesco'] ?? '') === 'Otro') ? 'selected' : '' ?>>Otro</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">
                            Contrasena <?= $tutor_editar ? '(dejar vacio para no cambiar)' : '' ?>
                        </label>
                        <input type="password" name="password_tutor" <?= $tutor_editar ? '' : 'required' ?>
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino"
                               placeholder="<?= $tutor_editar ? 'Sin cambios...' : 'Contrasena de acceso' ?>">
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-vino hover:bg-opacity-90 text-white font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors">
                            <?= $tutor_editar ? 'Actualizar' : 'Registrar' ?>
                        </button>
                        <?php if ($tutor_editar): ?>
                        <a href="tutores.php" class="bg-zinc-200 hover:bg-zinc-300 text-zinc-700 font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Busqueda -->
            <div class="bg-white rounded-xl border border-zinc-200 p-4">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar por nombre de tutor, alumno o matricula..."
                           class="flex-1 bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    <button type="submit" class="bg-dorado hover:bg-opacity-90 text-white font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors">Buscar</button>
                    <?php if ($busqueda): ?>
                    <a href="tutores.php" class="bg-zinc-200 hover:bg-zinc-300 text-zinc-700 font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors text-center">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Tutores -->
            <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Tutor</th>
                                <th class="px-4 py-3 text-left">Alumno Vinculado</th>
                                <th class="px-4 py-3 text-left">Matricula</th>
                                <th class="px-4 py-3 text-left">Telefono</th>
                                <th class="px-4 py-3 text-left">Parentesco</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            <?php if (empty($tutores)): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-400">No se encontraron tutores</td></tr>
                            <?php else: ?>
                            <?php foreach ($tutores as $t): ?>
                            <tr class="hover:bg-zinc-50/50">
                                <td class="px-4 py-3 font-medium text-zinc-700"><?= htmlspecialchars($t['nombre_tutor']) ?></td>
                                <td class="px-4 py-3 text-zinc-600"><?= htmlspecialchars(($t['alumno_nombre'] ?? '') . ' ' . ($t['alumno_ap'] ?? '')) ?></td>
                                <td class="px-4 py-3 font-mono text-xs text-vino font-bold"><?= htmlspecialchars($t['matricula_alumno']) ?></td>
                                <td class="px-4 py-3 text-zinc-500"><?= htmlspecialchars($t['telefono_tutor'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-zinc-500"><?= htmlspecialchars($t['relacion_parentesco'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-center space-x-1">
                                    <a href="?editar=<?= $t['id_tutor'] ?>" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100">Editar</a>
                                    <form method="POST" class="inline" onsubmit="return confirm('Eliminar este tutor?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_tutor" value="<?= $t['id_tutor'] ?>">
                                        <button type="submit" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginacion -->
                <?php if ($total_paginas > 1): ?>
                <div class="px-4 py-3 border-t border-zinc-100 flex items-center justify-between">
                    <p class="text-xs text-zinc-500">Mostrando <?= $offset + 1 ?> - <?= min($offset + $por_pagina, $total) ?> de <?= $total ?></p>
                    <div class="flex space-x-1">
                        <?php if ($pagina > 1): ?>
                        <a href="?pagina=<?= $pagina - 1 ?>&buscar=<?= urlencode($busqueda) ?>" class="px-3 py-1 rounded text-xs font-medium bg-zinc-100 hover:bg-zinc-200 text-zinc-700">Anterior</a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                        <a href="?pagina=<?= $i ?>&buscar=<?= urlencode($busqueda) ?>" class="px-3 py-1 rounded text-xs font-medium <?= $i === $pagina ? 'bg-vino text-white' : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-700' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($pagina < $total_paginas): ?>
                        <a href="?pagina=<?= $pagina + 1 ?>&buscar=<?= urlencode($busqueda) ?>" class="px-3 py-1 rounded text-xs font-medium bg-zinc-100 hover:bg-zinc-200 text-zinc-700">Siguiente</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

<?php require_once 'includes/footer.php'; ?>
