<?php
/**
 * Gestion de Usuarios del Sistema - CRUD
 * SGA COBAEV - Panel Administrativo
 * Solo accesible para usuarios con rol Admin
 */
session_start();
if (!isset($_SESSION['usuario_autenticado']) || $_SESSION['usuario_autenticado'] !== true) {
    header("Location: ../login_admin.php");
    exit;
}

// Solo los roles Admin y Prefecto pueden gestionar usuarios
if (!isset($_SESSION['usuario_rol']) || !in_array($_SESSION['usuario_rol'], ['Admin', 'Prefecto'])) {
    header("Location: index.php");
    exit;
}

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
            $username = trim($_POST['username']);
            $password_raw = trim($_POST['password_admin']);
            $nombre = trim($_POST['nombre_completo']);
            $rol = $_POST['rol'];

            // Validacion de entrada
            if (!preg_match('/^[a-zA-Z0-9]{3,50}$/', $username)) {
                $mensaje = 'El username debe ser alfanumerico y tener entre 3 y 50 caracteres.';
                $tipo_mensaje = 'error';
            } elseif (strlen($password_raw) < 6) {
                $mensaje = 'La contrasena debe tener al menos 6 caracteres.';
                $tipo_mensaje = 'error';
            } else {
                $stmt = $pdo->prepare("INSERT INTO usuarios_admin (username, password_admin, nombre_completo, rol) VALUES (:username, :password, :nombre, :rol)");
                $stmt->execute([
                    'username' => $username,
                    'password' => password_hash($password_raw, PASSWORD_DEFAULT),
                    'nombre' => $nombre,
                    'rol' => $rol
                ]);
                $mensaje = 'Usuario creado exitosamente.';
                $tipo_mensaje = 'success';
            }
        } elseif ($accion === 'editar') {
            $username = trim($_POST['username']);
            $password_raw = trim($_POST['password_admin']);
            $nombre = trim($_POST['nombre_completo']);
            $rol = $_POST['rol'];

            // Validacion de entrada
            if (!preg_match('/^[a-zA-Z0-9]{3,50}$/', $username)) {
                $mensaje = 'El username debe ser alfanumerico y tener entre 3 y 50 caracteres.';
                $tipo_mensaje = 'error';
            } elseif (!empty($password_raw) && strlen($password_raw) < 6) {
                $mensaje = 'La contrasena debe tener al menos 6 caracteres.';
                $tipo_mensaje = 'error';
            } else {
                $campos = "username = :username, nombre_completo = :nombre, rol = :rol";
                $params = [
                    'id' => $_POST['id_usuario'],
                    'username' => $username,
                    'nombre' => $nombre,
                    'rol' => $rol
                ];
                // Solo actualizar password si se proporciona
                if (!empty($password_raw)) {
                    $campos .= ", password_admin = :password";
                    $params['password'] = password_hash($password_raw, PASSWORD_DEFAULT);
                }
                $stmt = $pdo->prepare("UPDATE usuarios_admin SET $campos WHERE id_usuario = :id");
                $stmt->execute($params);
                $mensaje = 'Usuario actualizado exitosamente.';
                $tipo_mensaje = 'success';
            }
        } elseif ($accion === 'eliminar') {
            $id_eliminar = (int)$_POST['id_usuario'];
            // Prevenir auto-eliminacion
            if ($id_eliminar === (int)$_SESSION['usuario_id']) {
                $mensaje = 'No puedes eliminar tu propia cuenta.';
                $tipo_mensaje = 'error';
            } else {
                $stmt = $pdo->prepare("DELETE FROM usuarios_admin WHERE id_usuario = :id");
                $stmt->execute(['id' => $id_eliminar]);
                $mensaje = 'Usuario eliminado exitosamente.';
                $tipo_mensaje = 'success';
            }
        }
    } catch (PDOException $e) {
        error_log('SGA Error [usuarios]: ' . $e->getMessage());
        $mensaje = 'Error interno del servidor. Intente de nuevo mas tarde.';
        $tipo_mensaje = 'error';
    }
    } // end CSRF validation
}

// Obtener todos los usuarios
$stmt = $pdo->query("SELECT * FROM usuarios_admin ORDER BY nombre_completo");
$usuarios = $stmt->fetchAll();

// Usuario para editar
$usuario_editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM usuarios_admin WHERE id_usuario = :id");
    $stmt->execute(['id' => $_GET['editar']]);
    $usuario_editar = $stmt->fetch();
}

$pagina_actual = 'usuarios';
$page_title = 'Usuarios - Panel Admin SGA COBAEV';
$page_header = 'Usuarios del Sistema';

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
                    <?= $usuario_editar ? 'Editar Usuario' : 'Crear Nuevo Usuario' ?>
                </h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="<?= $usuario_editar ? 'editar' : 'crear' ?>">
                    <?php if ($usuario_editar): ?>
                    <input type="hidden" name="id_usuario" value="<?= $usuario_editar['id_usuario'] ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Username</label>
                        <input type="text" name="username" required value="<?= htmlspecialchars($usuario_editar['username'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Nombre Completo</label>
                        <input type="text" name="nombre_completo" required value="<?= htmlspecialchars($usuario_editar['nombre_completo'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">
                            Contrasena <?= $usuario_editar ? '(vacio = sin cambio)' : '' ?>
                        </label>
                        <input type="password" name="password_admin" <?= $usuario_editar ? '' : 'required' ?>
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino"
                               placeholder="<?= $usuario_editar ? 'Sin cambios...' : 'Contrasena' ?>">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Rol</label>
                        <select name="rol" required class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                            <option value="Admin" <?= ($usuario_editar && $usuario_editar['rol'] === 'Admin') ? 'selected' : '' ?>>Admin</option>
                            <option value="Prefecto" <?= ($usuario_editar && $usuario_editar['rol'] === 'Prefecto') ? 'selected' : '' ?>>Prefecto</option>
                            <option value="Vigilante" <?= ($usuario_editar && $usuario_editar['rol'] === 'Vigilante') ? 'selected' : '' ?>>Vigilante</option>
                        </select>
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-vino hover:bg-opacity-90 text-white font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors">
                            <?= $usuario_editar ? 'Actualizar' : 'Crear' ?>
                        </button>
                        <?php if ($usuario_editar): ?>
                        <a href="usuarios.php" class="bg-zinc-200 hover:bg-zinc-300 text-zinc-700 font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Tabla de Usuarios -->
            <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">ID</th>
                                <th class="px-4 py-3 text-left">Username</th>
                                <th class="px-4 py-3 text-left">Nombre Completo</th>
                                <th class="px-4 py-3 text-center">Rol</th>
                                <th class="px-4 py-3 text-left">Ultimo Acceso</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            <?php if (empty($usuarios)): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-400">No hay usuarios registrados</td></tr>
                            <?php else: ?>
                            <?php foreach ($usuarios as $u): ?>
                            <tr class="hover:bg-zinc-50/50">
                                <td class="px-4 py-3 text-zinc-400 text-xs">#<?= $u['id_usuario'] ?></td>
                                <td class="px-4 py-3 font-mono text-sm font-bold text-vino"><?= htmlspecialchars($u['username']) ?></td>
                                <td class="px-4 py-3 font-medium text-zinc-700"><?= htmlspecialchars($u['nombre_completo']) ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php
                                    // PHP 7.x compatible role color mapping
                                    switch ($u['rol']) {
                                        case 'Admin':
                                            $rol_color = 'bg-purple-100 text-purple-700';
                                            break;
                                        case 'Prefecto':
                                            $rol_color = 'bg-blue-100 text-blue-700';
                                            break;
                                        case 'Vigilante':
                                            $rol_color = 'bg-amber-100 text-amber-700';
                                            break;
                                        default:
                                            $rol_color = 'bg-zinc-100 text-zinc-700';
                                    }
                                    ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold <?= $rol_color ?>"><?= htmlspecialchars($u['rol']) ?></span>
                                </td>
                                <td class="px-4 py-3 text-zinc-500 text-xs"><?= $u['ultimo_acceso'] ? htmlspecialchars($u['ultimo_acceso']) : 'Nunca' ?></td>
                                <td class="px-4 py-3 text-center space-x-1">
                                    <a href="?editar=<?= $u['id_usuario'] ?>" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100">Editar</a>
                                    <?php if ((int)$u['id_usuario'] !== (int)$_SESSION['usuario_id']): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Eliminar este usuario?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                        <button type="submit" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100">Eliminar</button>
                                    </form>
                                    <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-zinc-100 text-zinc-400">Tu cuenta</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

<?php require_once 'includes/footer.php'; ?>
