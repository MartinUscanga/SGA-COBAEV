<?php
/**
 * Gestion de Dispositivos FCM - Tokens de Notificacion
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
        if ($accion === 'eliminar') {
            $stmt = $pdo->prepare("DELETE FROM dispositivos_padres WHERE id = :id");
            $stmt->execute(['id' => $_POST['id_dispositivo']]);
            $mensaje = 'Dispositivo eliminado exitosamente.';
            $tipo_mensaje = 'success';
        } elseif ($accion === 'test_notificacion') {
            // Enviar notificacion de prueba
            $id_dispositivo = $_POST['id_dispositivo'];
            $stmt = $pdo->prepare("SELECT dp.token_fcm, dp.matricula_alumno, a.nombre, a.apellido_paterno FROM dispositivos_padres dp LEFT JOIN alumnos a ON dp.matricula_alumno = a.matricula WHERE dp.id = :id");
            $stmt->execute(['id' => $id_dispositivo]);
            $dispositivo = $stmt->fetch();

            if ($dispositivo && !empty($dispositivo['token_fcm'])) {
                $token = $dispositivo['token_fcm'];
                $nombre_alumno = trim(($dispositivo['nombre'] ?? '') . ' ' . ($dispositivo['apellido_paterno'] ?? ''));

                // Verificar si existe el archivo de credenciales
                $rutaCredenciales = __DIR__ . '/../config/service-account.json';
                if (file_exists($rutaCredenciales) && file_exists(__DIR__ . '/../vendor/autoload.php')) {
                    require_once __DIR__ . '/../vendor/autoload.php';

                    $jsonKey = json_decode(file_get_contents($rutaCredenciales), true);
                    if ($jsonKey) {
                        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
                        $credentials = new \Google\Auth\Credentials\ServiceAccountCredentials($scopes, $jsonKey);
                        $accessToken = $credentials->fetchAuthToken(\Google\Auth\HttpHandler\HttpHandlerFactory::build())['access_token'];

                        $projectId = $jsonKey['project_id'];
                        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

                        $payload = [
                            'message' => [
                                'token' => $token,
                                'notification' => [
                                    'title' => 'Prueba - SGA COBAEV',
                                    'body' => 'Notificacion de prueba enviada desde el panel admin para ' . $nombre_alumno
                                ],
                                'data' => [
                                    'tipo' => 'test',
                                    'timestamp' => date('Y-m-d H:i:s')
                                ]
                            ]
                        ];

                        $ch = curl_init($url);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Authorization: Bearer ' . $accessToken,
                            'Content-Type: application/json'
                        ]);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

                        $respuesta = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($httpCode === 200) {
                            $mensaje = 'Notificacion de prueba enviada exitosamente a ' . htmlspecialchars($nombre_alumno);
                            $tipo_mensaje = 'success';
                        } else {
                            $mensaje = 'Error al enviar notificacion (HTTP ' . $httpCode . '). Verifique el token.';
                            $tipo_mensaje = 'error';
                        }
                    } else {
                        $mensaje = 'Error: No se pudo leer el archivo de credenciales.';
                        $tipo_mensaje = 'error';
                    }
                } else {
                    $mensaje = 'No se puede enviar: falta service-account.json o vendor/autoload.php. Configure Firebase primero.';
                    $tipo_mensaje = 'error';
                }
            } else {
                $mensaje = 'Dispositivo no encontrado o sin token.';
                $tipo_mensaje = 'error';
            }
        }
    } catch (PDOException $e) {
        $mensaje = 'Error: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    } catch (Exception $e) {
        $mensaje = 'Error al enviar notificacion: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
    } // end CSRF validation
}

// Busqueda y paginacion
$busqueda = trim($_GET['buscar'] ?? '');
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

$where = "WHERE 1=1";
$params = [];
if ($busqueda !== '') {
    $where .= " AND (dp.matricula_alumno LIKE :buscar OR a.nombre LIKE :buscar2 OR a.apellido_paterno LIKE :buscar3)";
    $params['buscar'] = "%$busqueda%";
    $params['buscar2'] = "%$busqueda%";
    $params['buscar3'] = "%$busqueda%";
}

// Total registros
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM dispositivos_padres dp LEFT JOIN alumnos a ON dp.matricula_alumno = a.matricula $where");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_paginas = ceil($total / $por_pagina);

// Obtener dispositivos con info del alumno
// intval() ensures $por_pagina and $offset are safe integers for LIMIT/OFFSET
$por_pagina_int = intval($por_pagina);
$offset_int = intval($offset);
$stmt = $pdo->prepare("
    SELECT dp.*, a.nombre AS alumno_nombre, a.apellido_paterno AS alumno_ap, a.apellido_materno AS alumno_am
    FROM dispositivos_padres dp
    LEFT JOIN alumnos a ON dp.matricula_alumno = a.matricula
    $where
    ORDER BY dp.fecha_registro DESC
    LIMIT $por_pagina_int OFFSET $offset_int
");
$stmt->execute($params);
$dispositivos = $stmt->fetchAll();

$pagina_actual = 'dispositivos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dispositivos - Panel Admin SGA COBAEV</title>
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
            <a href="usuarios.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium text-white/70 hover:bg-white/10 hover:text-white transition-colors">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Usuarios
            </a>
            <a href="dispositivos.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium bg-white/10 text-white">
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
                <h2 class="font-serif-elegant text-xl font-bold text-vino">Dispositivos Registrados</h2>
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

            <!-- Info -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <div class="flex items-start space-x-3">
                    <svg class="w-5 h-5 text-blue-500 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div>
                        <p class="text-sm font-medium text-blue-800">Tokens FCM (Firebase Cloud Messaging)</p>
                        <p class="text-xs text-blue-600 mt-1">Estos son los dispositivos registrados para recibir notificaciones push. Cada token corresponde a un navegador/dispositivo de un tutor.</p>
                    </div>
                </div>
            </div>

            <!-- Busqueda -->
            <div class="bg-white rounded-xl border border-zinc-200 p-4">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar por alumno o matricula..."
                           class="flex-1 bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    <button type="submit" class="bg-dorado hover:bg-opacity-90 text-white font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors">Buscar</button>
                    <?php if ($busqueda): ?>
                    <a href="dispositivos.php" class="bg-zinc-200 hover:bg-zinc-300 text-zinc-700 font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors text-center">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Dispositivos -->
            <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-zinc-100 flex items-center justify-between">
                    <div>
                        <h3 class="font-serif-elegant text-lg font-bold text-vino">Tokens Registrados</h3>
                        <p class="text-xs text-zinc-400 mt-0.5"><?= $total ?> dispositivo(s) encontrado(s)</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">ID</th>
                                <th class="px-4 py-3 text-left">Alumno</th>
                                <th class="px-4 py-3 text-left">Matricula</th>
                                <th class="px-4 py-3 text-left">Token (preview)</th>
                                <th class="px-4 py-3 text-left">Fecha Registro</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            <?php if (empty($dispositivos)): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-400">No hay dispositivos registrados</td></tr>
                            <?php else: ?>
                            <?php foreach ($dispositivos as $d): ?>
                            <tr class="hover:bg-zinc-50/50">
                                <td class="px-4 py-3 text-zinc-400 text-xs">#<?= $d['id'] ?></td>
                                <td class="px-4 py-3 font-medium text-zinc-700"><?= htmlspecialchars(trim(($d['alumno_nombre'] ?? '') . ' ' . ($d['alumno_ap'] ?? ''))) ?></td>
                                <td class="px-4 py-3 font-mono text-xs text-vino font-bold"><?= htmlspecialchars($d['matricula_alumno']) ?></td>
                                <td class="px-4 py-3 text-zinc-500 font-mono text-xs">
                                    <?= htmlspecialchars(substr($d['token_fcm'] ?? '', 0, 30)) ?>...
                                </td>
                                <td class="px-4 py-3 text-zinc-500 text-xs"><?= htmlspecialchars($d['fecha_registro'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center space-x-1">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="accion" value="test_notificacion">
                                            <input type="hidden" name="id_dispositivo" value="<?= $d['id'] ?>">
                                            <button type="submit" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-50 text-green-700 hover:bg-green-100" title="Enviar notificacion de prueba">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                                Test
                                            </button>
                                        </form>
                                        <form method="POST" class="inline" onsubmit="return confirm('Eliminar este dispositivo?')">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id_dispositivo" value="<?= $d['id'] ?>">
                                            <button type="submit" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100">
                                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                Eliminar
                                            </button>
                                        </form>
                                    </div>
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
