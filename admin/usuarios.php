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
            $stmt = $pdo->prepare("INSERT INTO usuarios_admin (username, password_admin, nombre_completo, rol) VALUES (:username, :password, :nombre, :rol)");
            $stmt->execute([
                'username' => trim($_POST['username']),
                'password' => trim($_POST['password_admin']),
                'nombre' => trim($_POST['nombre_completo']),
                'rol' => $_POST['rol']
            ]);
            $mensaje = 'Usuario creado exitosamente.';
            $tipo_mensaje = 'success';
        } elseif ($accion === 'editar') {
            $campos = "username = :username, nombre_completo = :nombre, rol = :rol";
            $params = [
                'id' => $_POST['id_usuario'],
                'username' => trim($_POST['username']),
                'nombre' => trim($_POST['nombre_completo']),
                'rol' => $_POST['rol']
            ];
            // Solo actualizar password si se proporciona
            if (!empty(trim($_POST['password_admin']))) {
                $campos .= ", password_admin = :password";
                $params['password'] = trim($_POST['password_admin']);
            }
            $stmt = $pdo->prepare("UPDATE usuarios_admin SET $campos WHERE id_usuario = :id");
            $stmt->execute($params);
            $mensaje = 'Usuario actualizado exitosamente.';
            $tipo_mensaje = 'success';
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
        $mensaje = 'Error: ' . $e->getMessage();
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Usuarios - Panel Admin SGA COBAEV</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .font-serif-elegant { font-family: 'Playfair Display', serif; }
        .font-sans-clean { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-crema { background-color: #f7f3eb; }
        .text-vino { color: #5c1931; }
        .bg-vino { background-color: #5c1931; }
        .border-vino { border-color: #5c1931; }
        .text-dorado { color: #a48253; }
        .bg-dorado { background-color: #a48253; }
    </style>
</head>
<body class="bg-crema font-sans-clean min-h-screen flex">

    <!-- Sidebar -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 bg-vino text-white transform -translate-x-full md:translate-x-0 transition-transform duration-200 ease-in-out flex flex-col">
        <div class="p-6 border-b border-white/10">
            <h1 class="font-serif-elegant text-xl font-bold tracking-wide">SGA COBAEV</h1>
            <p class="text-xs text-white/60 mt-1">Panel Administrativo</p>
        </div>
        <nav class="flex-1 p-4 space-y-1">
            <a href="index.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                Dashboard
            </a>
            <a href="alumnos.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                Alumnos
            </a>
            <a href="tutores.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Tutores
            </a>
            <a href="asistencias.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Asistencias
            </a>
            <a href="usuarios.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium bg-white/10 text-white">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Usuarios
            </a>
            <a href="dispositivos.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Dispositivos
            </a>
        </nav>
        <div class="p-4 border-t border-white/10">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-xs font-bold">
                    <?= strtoupper(substr($_SESSION['usuario_nombre'] ?? 'A', 0, 1)) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate"><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Admin') ?></p>
                    <p class="text-xs text-white/50"><?= htmlspecialchars($_SESSION['usuario_rol'] ?? 'Admin') ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Overlay mobile -->
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/50 z-20 hidden md:hidden" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <main class="flex-1 md:ml-64 min-h-screen">
        <!-- Header -->
        <header class="bg-white border-b border-zinc-200 px-4 md:px-8 py-4 flex items-center justify-between sticky top-0 z-10">
            <div class="flex items-center space-x-4">
                <button onclick="toggleSidebar()" class="md:hidden text-zinc-600 hover:text-vino">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h2 class="font-serif-elegant text-xl font-bold text-vino">Usuarios del Sistema</h2>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-zinc-500 hidden sm:inline"><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '') ?></span>
                <a href="../logout.php" class="text-xs font-bold text-white bg-vino hover:bg-opacity-90 px-4 py-2 rounded-lg transition-colors">Cerrar Sesion</a>
            </div>
        </header>

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
    </main>

    <script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }
    </script>
</body>
</html>
