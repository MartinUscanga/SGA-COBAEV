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

// Generar token CSRF si no existe (para consistencia entre paginas)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

// Alumnos actualmente en el plantel
// Lógica: obtener el último registro del día de cada alumno, contar los que son "Entrada"
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM (
        SELECT a1.matricula_alumno, a1.tipo
        FROM asistencias a1
        WHERE a1.fecha = :fecha
        AND a1.id_asistencia = (
            SELECT MAX(a2.id_asistencia) 
            FROM asistencias a2 
            WHERE a2.matricula_alumno = a1.matricula_alumno 
            AND a2.fecha = :fecha2
        )
    ) sub WHERE sub.tipo = 'Entrada'
");
$stmt->execute(['fecha' => $hoy, 'fecha2' => $hoy]);
$en_plantel = $stmt->fetch()['total'];

// Total alumnos registrados
$stmt = $pdo->query("SELECT COUNT(*) as total FROM alumnos");
$total_alumnos = $stmt->fetch()['total'];

// Alumnos fuera
$fuera_plantel = $total_alumnos - $en_plantel;
if ($fuera_plantel < 0) $fuera_plantel = 0;

// Ultimos 10 movimientos
$stmt = $pdo->prepare("
    SELECT a.matricula_alumno, a.tipo, a.hora, a.fecha,
           al.nombre, al.apellido_paterno, al.apellido_materno
    FROM asistencias a
    INNER JOIN alumnos al ON a.matricula_alumno COLLATE utf8mb4_unicode_ci = al.matricula COLLATE utf8mb4_unicode_ci
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
$page_title = 'Dashboard - Panel Admin SGA COBAEV';
$page_header = 'Dashboard';

require_once 'includes/head.php';
require_once 'includes/sidebar.php';
require_once 'includes/header.php';
?>

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

<?php require_once 'includes/footer.php'; ?>
