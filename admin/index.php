<?php
/**
 * Panel de Administracion - Dashboard Principal
 * SGA COBAEV
 */
session_start();
if (!isset($_SESSION['usuario_autenticado']) || $_SESSION['usuario_autenticado'] !== true) {
    header("Location: ../login_admin.php");
    exit;
}

require_once '../conexion.php';

// Obtener estadisticas del dia
$hoy = date('Y-m-d');

// Total entradas hoy
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM asistencias WHERE fecha = :fecha AND tipo = 'Entrada'");
$stmt->execute(['fecha' => $hoy]);
$entradas_hoy = $stmt->fetch()['total'];

// Total salidas hoy
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM asistencias WHERE fecha = :fecha AND tipo = 'Salida'");
$stmt->execute(['fecha' => $hoy]);
$salidas_hoy = $stmt->fetch()['total'];

// Alumnos actualmente en el plantel (tienen entrada sin salida posterior)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT a1.matricula_alumno) as total
    FROM asistencias a1
    WHERE a1.fecha = :fecha
    AND a1.tipo = 'Entrada'
    AND NOT EXISTS (
        SELECT 1 FROM asistencias a2
        WHERE a2.matricula_alumno = a1.matricula_alumno
        AND a2.fecha = a1.fecha
        AND a2.tipo = 'Salida'
        AND a2.hora > a1.hora
    )
");
$stmt->execute(['fecha' => $hoy]);
$en_plantel = $stmt->fetch()['total'];

// Total alumnos registrados
$stmt = $pdo->query("SELECT COUNT(*) as total FROM alumnos WHERE activo = 1");
$total_alumnos = $stmt->fetch()['total'];

// Alumnos fuera
$fuera_plantel = $total_alumnos - $en_plantel;
if ($fuera_plantel < 0) $fuera_plantel = 0;

// Ultimos 10 movimientos
$stmt = $pdo->prepare("
    SELECT a.matricula_alumno, a.tipo, a.hora, a.fecha,
           al.nombre, al.apellido_paterno, al.apellido_materno
    FROM asistencias a
    INNER JOIN alumnos al ON a.matricula_alumno = al.matricula
    WHERE a.fecha = :fecha
    ORDER BY a.hora DESC
    LIMIT 10
");
$stmt->execute(['fecha' => $hoy]);
$ultimos_movimientos = $stmt->fetchAll();

// Datos para grafica semanal (ultimos 7 dias)
$datos_semana = [];
for ($i = 6; $i >= 0; $i--) {
    $dia = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("SELECT 
        SUM(CASE WHEN tipo = 'Entrada' THEN 1 ELSE 0 END) as entradas,
        SUM(CASE WHEN tipo = 'Salida' THEN 1 ELSE 0 END) as salidas
        FROM asistencias WHERE fecha = :fecha");
    $stmt->execute(['fecha' => $dia]);
    $row = $stmt->fetch();
    $datos_semana[] = [
        'dia' => date('D', strtotime($dia)),
        'fecha' => $dia,
        'entradas' => (int)($row['entradas'] ?? 0),
        'salidas' => (int)($row['salidas'] ?? 0)
    ];
}

$max_val = 1;
foreach ($datos_semana as $d) {
    if ($d['entradas'] > $max_val) $max_val = $d['entradas'];
    if ($d['salidas'] > $max_val) $max_val = $d['salidas'];
}

$pagina_actual = 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Dashboard - Panel Admin SGA COBAEV</title>
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
            <a href="index.php" class="flex items-center px-4 py-2.5 rounded-lg text-sm font-medium bg-white/10 text-white">
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
                <h2 class="font-serif-elegant text-xl font-bold text-vino">Dashboard</h2>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-sm text-zinc-500 hidden sm:inline"><?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '') ?></span>
                <a href="../logout.php" class="text-xs font-bold text-white bg-vino hover:bg-opacity-90 px-4 py-2 rounded-lg transition-colors">Cerrar Sesion</a>
            </div>
        </header>

        <div class="p-4 md:p-8 space-y-6">
            <!-- Stat Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Entradas hoy -->
                <div class="bg-white rounded-xl border border-zinc-200 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-zinc-400 uppercase tracking-wide">Entradas Hoy</p>
                            <p class="text-3xl font-bold text-vino mt-1"><?= $entradas_hoy ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                        </div>
                    </div>
                </div>
                <!-- Salidas hoy -->
                <div class="bg-white rounded-xl border border-zinc-200 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-zinc-400 uppercase tracking-wide">Salidas Hoy</p>
                            <p class="text-3xl font-bold text-vino mt-1"><?= $salidas_hoy ?></p>
                        </div>
                        <div class="w-12 h-12 bg-orange-50 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </div>
                    </div>
                </div>
                <!-- En plantel -->
                <div class="bg-white rounded-xl border border-zinc-200 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-zinc-400 uppercase tracking-wide">En Plantel</p>
                            <p class="text-3xl font-bold text-green-700 mt-1"><?= $en_plantel ?></p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-50 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        </div>
                    </div>
                </div>
                <!-- Fuera -->
                <div class="bg-white rounded-xl border border-zinc-200 p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-zinc-400 uppercase tracking-wide">Fuera / Total</p>
                            <p class="text-3xl font-bold text-zinc-600 mt-1"><?= $fuera_plantel ?><span class="text-lg text-zinc-400">/<?= $total_alumnos ?></span></p>
                        </div>
                        <div class="w-12 h-12 bg-zinc-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-zinc-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Ultimos movimientos -->
                <div class="lg:col-span-2 bg-white rounded-xl border border-zinc-200 overflow-hidden">
                    <div class="px-6 py-4 border-b border-zinc-100">
                        <h3 class="font-serif-elegant text-lg font-bold text-vino">Ultimos Movimientos</h3>
                        <p class="text-xs text-zinc-400 mt-0.5">Registros de hoy en tiempo real</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 tracking-wide">
                                <tr>
                                    <th class="px-6 py-3 text-left">Alumno</th>
                                    <th class="px-6 py-3 text-left">Matricula</th>
                                    <th class="px-6 py-3 text-center">Tipo</th>
                                    <th class="px-6 py-3 text-right">Hora</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <?php if (empty($ultimos_movimientos)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-zinc-400">Sin movimientos registrados hoy</td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($ultimos_movimientos as $mov): ?>
                                <tr class="hover:bg-zinc-50/50">
                                    <td class="px-6 py-3 font-medium text-zinc-700">
                                        <?= htmlspecialchars($mov['nombre'] . ' ' . $mov['apellido_paterno']) ?>
                                    </td>
                                    <td class="px-6 py-3 text-zinc-500 font-mono text-xs"><?= htmlspecialchars($mov['matricula_alumno']) ?></td>
                                    <td class="px-6 py-3 text-center">
                                        <?php if ($mov['tipo'] === 'Entrada'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">Entrada</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-700">Salida</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-3 text-right text-zinc-500"><?= htmlspecialchars($mov['hora']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Grafica semanal -->
                <div class="bg-white rounded-xl border border-zinc-200 p-6">
                    <h3 class="font-serif-elegant text-lg font-bold text-vino mb-1">Asistencia Semanal</h3>
                    <p class="text-xs text-zinc-400 mb-4">Ultimos 7 dias</p>
                    <div class="space-y-3">
                        <?php foreach ($datos_semana as $d): ?>
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-xs font-medium text-zinc-600"><?= $d['dia'] ?></span>
                                <span class="text-xs text-zinc-400"><?= $d['entradas'] ?>E / <?= $d['salidas'] ?>S</span>
                            </div>
                            <div class="flex space-x-1">
                                <div class="h-4 rounded bg-green-400" style="width: <?= ($d['entradas'] / $max_val) * 100 ?>%"></div>
                                <div class="h-4 rounded bg-orange-400" style="width: <?= ($d['salidas'] / $max_val) * 100 ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="flex items-center space-x-4 mt-4 pt-4 border-t border-zinc-100">
                        <div class="flex items-center space-x-1">
                            <div class="w-3 h-3 rounded bg-green-400"></div>
                            <span class="text-xs text-zinc-500">Entradas</span>
                        </div>
                        <div class="flex items-center space-x-1">
                            <div class="w-3 h-3 rounded bg-orange-400"></div>
                            <span class="text-xs text-zinc-500">Salidas</span>
                        </div>
                    </div>
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
