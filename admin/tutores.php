<?php
/**
 * Gestion de Tutores - CRUD Completo
 * SGA COBAEV - Panel Administrativo
 */
session_start();
if (!isset($_SESSION['usuario_autenticado']) || $_SESSION['usuario_autenticado'] !== true) {
    header("Location: ../login_admin.php");
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
            $stmt = $pdo->prepare("INSERT INTO tutores (matricula_alumno, nombre_tutor, password_tutor, telefono, creado_el) VALUES (:matricula, :nombre, :password, :telefono, NOW())");
            $stmt->execute([
                'matricula' => strtoupper(trim($_POST['matricula_alumno'])),
                'nombre' => trim($_POST['nombre_tutor']),
                'password' => trim($_POST['password_tutor']),
                'telefono' => trim($_POST['telefono'] ?? '')
            ]);
            $mensaje = 'Tutor registrado exitosamente.';
            $tipo_mensaje = 'success';
        } elseif ($accion === 'editar') {
            $stmt = $pdo->prepare("UPDATE tutores SET nombre_tutor = :nombre, telefono = :telefono, matricula_alumno = :matricula WHERE id_tutor = :id");
            $stmt->execute([
                'nombre' => trim($_POST['nombre_tutor']),
                'telefono' => trim($_POST['telefono'] ?? ''),
                'matricula' => strtoupper(trim($_POST['matricula_alumno'])),
                'id' => $_POST['id_tutor']
            ]);
            $mensaje = 'Tutor actualizado exitosamente.';
            $tipo_mensaje = 'success';
        } elseif ($accion === 'eliminar') {
            $stmt = $pdo->prepare("DELETE FROM tutores WHERE id_tutor = :id");
            $stmt->execute(['id' => $_POST['id_tutor']]);
            $mensaje = 'Tutor eliminado exitosamente.';
            $tipo_mensaje = 'success';
        }
    } catch (PDOException $e) {
        $mensaje = 'Error: ' . $e->getMessage();
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Tutores - Panel Admin SGA COBAEV</title>
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
            <a href="tutores.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium bg-white/10 text-white">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                Tutores
            </a>
            <a href="asistencias.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Asistencias
            </a>
            <a href="usuarios.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
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
                <h2 class="font-serif-elegant text-xl font-bold text-vino">Gestion de Tutores</h2>
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
