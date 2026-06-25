<?php
/**
 * Reportes de Asistencia por Grupo
 * SGA COBAEV - Panel Administrativo
 * 
 * Muestra porcentaje de asistencia por grupo con filtros de fecha.
 */
require_once 'includes/auth.php';
require_once '../conexion.php';

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Filtros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$grupo_filtro = $_GET['grupo'] ?? '';

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio)) $fecha_inicio = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) $fecha_fin = date('Y-m-d');

// Detectar filtro de alumnos activos (compatible con distintas estructuras de BD)
$filtro_activo = "";
try {
    // Intentar con 'estado' (BD de producción: columna estado con valor 'Activo')
    $test = $pdo->query("SELECT 1 FROM alumnos WHERE estado = 'Activo' LIMIT 1");
    $filtro_activo = "AND a.estado = 'Activo'";
} catch (PDOException $e) {
    try {
        // Intentar con 'activo' (BD de desarrollo: columna activo con valor 1)
        $test = $pdo->query("SELECT 1 FROM alumnos WHERE activo = 1 LIMIT 1");
        $filtro_activo = "AND a.activo = 1";
    } catch (PDOException $e2) {
        // Ninguna columna existe, no filtrar por estado
        $filtro_activo = "";
    }
}

// Obtener lista de grupos disponibles
try {
    $sql_grupos = "SELECT DISTINCT grupo FROM alumnos a WHERE grupo IS NOT NULL AND grupo != '' $filtro_activo ORDER BY grupo ASC";
    $stmt = $pdo->query($sql_grupos);
    $grupos_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Fallback: sin filtro de activo
    try {
        $stmt = $pdo->query("SELECT DISTINCT grupo FROM alumnos WHERE grupo IS NOT NULL AND grupo != '' ORDER BY grupo ASC");
        $grupos_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e2) {
        $grupos_disponibles = [];
    }
}

// Calcular días hábiles en el rango (Lunes a Viernes)
$dias_habiles = 0;
$fecha_temp = new DateTime($fecha_inicio);
$fecha_limite = new DateTime($fecha_fin);
while ($fecha_temp <= $fecha_limite) {
    $dia_semana = (int)$fecha_temp->format('N'); // 1=Lun, 7=Dom
    if ($dia_semana <= 5) {
        $dias_habiles++;
    }
    $fecha_temp->modify('+1 day');
}
if ($dias_habiles == 0) $dias_habiles = 1; // Evitar división por cero

// Obtener reporte por grupo
$where_grupo = "";
$params = ['fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin];

if (!empty($grupo_filtro)) {
    $where_grupo = "AND a.grupo = :grupo";
    $params['grupo'] = $grupo_filtro;
}

// Query principal: Alumnos por grupo con sus asistencias en el rango
$resultados = [];
$fechas_asistencia = []; // matricula => [fecha1, fecha2, ...]
try {
    $sql = "
        SELECT 
            a.grupo,
            a.matricula,
            CONCAT(a.nombre, ' ', a.apellido_paterno, ' ', IFNULL(a.apellido_materno, '')) AS nombre_completo,
            COUNT(DISTINCT CASE WHEN asist.tipo = 'Entrada' THEN asist.fecha END) AS dias_asistidos
        FROM alumnos a
        LEFT JOIN asistencias asist 
            ON a.matricula = asist.matricula_alumno 
            AND asist.fecha >= :fecha_inicio 
            AND asist.fecha <= :fecha_fin
            AND asist.tipo = 'Entrada'
        WHERE a.grupo IS NOT NULL 
        AND a.grupo != ''
        $filtro_activo
        $where_grupo
        GROUP BY a.grupo, a.matricula, a.nombre, a.apellido_paterno, a.apellido_materno
        ORDER BY a.grupo ASC, a.apellido_paterno ASC, a.nombre ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener fechas específicas de asistencia por alumno
    $sql_fechas = "
        SELECT matricula_alumno, fecha
        FROM asistencias
        WHERE fecha >= :fecha_inicio 
        AND fecha <= :fecha_fin
        AND tipo = 'Entrada'
        GROUP BY matricula_alumno, fecha
    ";
    $stmt_fechas = $pdo->prepare($sql_fechas);
    $stmt_fechas->execute(['fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin]);
    $rows_fechas = $stmt_fechas->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows_fechas as $rf) {
        $fechas_asistencia[$rf['matricula_alumno']][] = $rf['fecha'];
    }

} catch (PDOException $e) {
    error_log("Error en reportes.php: " . $e->getMessage());
    $resultados = [];
}

// Generar array de días hábiles en el rango
$dias_habiles_lista = [];
$fecha_iter = new DateTime($fecha_inicio);
$fecha_fin_dt = new DateTime($fecha_fin);
while ($fecha_iter <= $fecha_fin_dt) {
    $dia_sem = (int)$fecha_iter->format('N');
    if ($dia_sem <= 5) {
        $dias_habiles_lista[] = $fecha_iter->format('Y-m-d');
    }
    $fecha_iter->modify('+1 day');
}

// Agrupar resultados por grupo
$reporte_por_grupo = [];
foreach ($resultados as $row) {
    $grupo = $row['grupo'];
    if (!isset($reporte_por_grupo[$grupo])) {
        $reporte_por_grupo[$grupo] = [
            'alumnos' => [],
            'total_alumnos' => 0,
            'suma_asistencias' => 0
        ];
    }
    $porcentaje_alumno = round(($row['dias_asistidos'] / $dias_habiles) * 100, 1);
    if ($porcentaje_alumno > 100) $porcentaje_alumno = 100;
    
    $reporte_por_grupo[$grupo]['alumnos'][] = [
        'matricula' => $row['matricula'],
        'nombre' => trim($row['nombre_completo']),
        'dias_asistidos' => (int)$row['dias_asistidos'],
        'porcentaje' => $porcentaje_alumno,
        'fechas_asistio' => $fechas_asistencia[$row['matricula']] ?? []
    ];
    $reporte_por_grupo[$grupo]['total_alumnos']++;
    $reporte_por_grupo[$grupo]['suma_asistencias'] += (int)$row['dias_asistidos'];
}

// Calcular porcentaje general por grupo
foreach ($reporte_por_grupo as $grupo => &$data) {
    $max_posible = $data['total_alumnos'] * $dias_habiles;
    $data['porcentaje_grupo'] = ($max_posible > 0) ? round(($data['suma_asistencias'] / $max_posible) * 100, 1) : 0;
}
unset($data);

$pagina_actual = 'reportes';
$page_title = 'Reportes de Asistencia - Panel Admin SGA COBAEV';
$page_header = 'Reportes';

require_once 'includes/head.php';
require_once 'includes/sidebar.php';
require_once 'includes/header.php';
?>

        <div class="p-4 md:p-8 space-y-6">
            <!-- Encabezado -->
            <div>
                <h2 class="text-lg font-bold text-zinc-700">Reporte de Asistencia por Grupo</h2>
                <p class="text-xs text-zinc-400 mt-0.5">Porcentaje de asistencia basado en registros de entrada</p>
            </div>

            <!-- Filtros -->
            <form method="GET" class="bg-white rounded-xl border border-zinc-200 p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <!-- Fecha inicio -->
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                    </div>
                    <!-- Fecha fin -->
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Fecha Fin</label>
                        <input type="date" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                    </div>
                    <!-- Grupo -->
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Grupo</label>
                        <select name="grupo" class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                            <option value="">Todos los grupos</option>
                            <?php foreach ($grupos_disponibles as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>" <?= $grupo_filtro === $g ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Botón -->
                    <div>
                        <button type="submit" class="w-full bg-vino hover:bg-opacity-90 text-white font-bold text-xs tracking-wider uppercase py-2.5 px-4 rounded-lg shadow-sm active:scale-[0.98] transition-all">
                            Generar Reporte
                        </button>
                    </div>
                </div>
                <div class="mt-3 flex items-center space-x-4 text-xs text-zinc-400">
                    <span>Periodo: <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?></span>
                    <span>|</span>
                    <span>Dias habiles: <strong class="text-zinc-600"><?= $dias_habiles ?></strong></span>
                </div>
            </form>

            <?php if (empty($reporte_por_grupo)): ?>
                <!-- Sin datos -->
                <div class="bg-white rounded-xl border border-zinc-200 p-8 text-center">
                    <div class="w-16 h-16 mx-auto bg-zinc-50 rounded-2xl flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-zinc-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-zinc-500">Sin datos para el periodo seleccionado</p>
                    <p class="text-xs text-zinc-400 mt-1">Intenta cambiar las fechas o el grupo</p>
                </div>
            <?php else: ?>

                <!-- Resumen general -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <?php 
                    $total_grupos = count($reporte_por_grupo);
                    $total_alumnos_reporte = array_sum(array_column($reporte_por_grupo, 'total_alumnos'));
                    $promedio_general = ($total_grupos > 0) ? round(array_sum(array_column($reporte_por_grupo, 'porcentaje_grupo')) / $total_grupos, 1) : 0;
                    
                    // Grupo con mejor y peor asistencia
                    $mejor_grupo = '';
                    $peor_grupo = '';
                    $mejor_pct = 0;
                    $peor_pct = 100;
                    foreach ($reporte_por_grupo as $g => $d) {
                        if ($d['porcentaje_grupo'] >= $mejor_pct) { $mejor_pct = $d['porcentaje_grupo']; $mejor_grupo = $g; }
                        if ($d['porcentaje_grupo'] <= $peor_pct) { $peor_pct = $d['porcentaje_grupo']; $peor_grupo = $g; }
                    }
                    ?>
                    <div class="bg-white rounded-xl border border-zinc-200 p-5">
                        <p class="text-xs font-bold text-zinc-400 uppercase tracking-wide">Promedio General</p>
                        <p class="text-3xl font-bold text-vino mt-1"><?= $promedio_general ?>%</p>
                    </div>
                    <div class="bg-white rounded-xl border border-zinc-200 p-5">
                        <p class="text-xs font-bold text-zinc-400 uppercase tracking-wide">Total Alumnos</p>
                        <p class="text-3xl font-bold text-vino mt-1"><?= $total_alumnos_reporte ?></p>
                    </div>
                    <div class="bg-white rounded-xl border border-zinc-200 p-5">
                        <p class="text-xs font-bold text-zinc-400 uppercase tracking-wide">Mejor Grupo</p>
                        <p class="text-2xl font-bold text-emerald-600 mt-1"><?= htmlspecialchars($mejor_grupo) ?> <span class="text-sm text-zinc-400">(<?= $mejor_pct ?>%)</span></p>
                    </div>
                    <div class="bg-white rounded-xl border border-zinc-200 p-5">
                        <p class="text-xs font-bold text-zinc-400 uppercase tracking-wide">Menor Asistencia</p>
                        <p class="text-2xl font-bold text-red-600 mt-1"><?= htmlspecialchars($peor_grupo) ?> <span class="text-sm text-zinc-400">(<?= $peor_pct ?>%)</span></p>
                    </div>
                </div>

                <!-- Reporte por grupo -->
                <?php foreach ($reporte_por_grupo as $grupo => $data): ?>
                <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
                    <!-- Header del grupo -->
                    <div class="px-6 py-4 border-b border-zinc-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-serif-elegant text-lg font-bold text-vino">Grupo <?= htmlspecialchars($grupo) ?></h3>
                            <p class="text-xs text-zinc-400 mt-0.5"><?= $data['total_alumnos'] ?> alumnos</p>
                        </div>
                        <div class="text-right">
                            <?php
                                $pct = $data['porcentaje_grupo'];
                                $color_badge = 'bg-zinc-100 text-zinc-600';
                                if ($pct >= 90) $color_badge = 'bg-emerald-100 text-emerald-700';
                                elseif ($pct >= 75) $color_badge = 'bg-green-100 text-green-700';
                                elseif ($pct >= 60) $color_badge = 'bg-yellow-100 text-yellow-700';
                                elseif ($pct >= 40) $color_badge = 'bg-orange-100 text-orange-700';
                                elseif ($pct > 0) $color_badge = 'bg-red-100 text-red-700';
                            ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold <?= $color_badge ?>">
                                <?= $pct ?>%
                            </span>
                            <p class="text-[10px] text-zinc-400 mt-1">asistencia promedio</p>
                        </div>
                    </div>

                    <!-- Barra de progreso del grupo -->
                    <div class="px-6 py-3 bg-zinc-50 border-b border-zinc-100">
                        <div class="relative w-full h-3 bg-zinc-200 rounded-full overflow-hidden">
                            <?php
                                $bar_color = 'bg-zinc-400';
                                if ($pct >= 90) $bar_color = 'bg-emerald-500';
                                elseif ($pct >= 75) $bar_color = 'bg-green-500';
                                elseif ($pct >= 60) $bar_color = 'bg-yellow-500';
                                elseif ($pct >= 40) $bar_color = 'bg-orange-500';
                                elseif ($pct > 0) $bar_color = 'bg-red-500';
                            ?>
                            <div class="h-full <?= $bar_color ?> rounded-full transition-all duration-500" style="width: <?= $pct ?>%"></div>
                        </div>
                    </div>

                    <!-- Tabla de alumnos -->
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 tracking-wide">
                                <tr>
                                    <th class="px-4 py-3 text-left">Alumno</th>
                                    <th class="px-2 py-3 text-left">Matricula</th>
                                    <th class="px-2 py-3 text-center">Asistencia</th>
                                    <th class="px-2 py-3 text-center">%</th>
                                    <th class="px-2 py-3 text-center">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                <?php foreach ($data['alumnos'] as $alumno): ?>
                                <tr class="hover:bg-zinc-50/50">
                                    <td class="px-4 py-3 font-medium text-zinc-700 text-xs">
                                        <?= htmlspecialchars($alumno['nombre']) ?>
                                    </td>
                                    <td class="px-2 py-3 text-zinc-500 font-mono text-[10px]">
                                        <?= htmlspecialchars($alumno['matricula']) ?>
                                    </td>
                                    <td class="px-2 py-2 text-center">
                                        <!-- Lista de asistencia estilo profesor -->
                                        <div class="flex items-center justify-center flex-wrap gap-[3px]">
                                            <?php foreach ($dias_habiles_lista as $dia_habil): ?>
                                                <?php $asistio = in_array($dia_habil, $alumno['fechas_asistio']); ?>
                                                <div title="<?= date('d/m', strtotime($dia_habil)) ?>" 
                                                     class="w-4 h-4 rounded-sm flex items-center justify-center text-[8px] font-bold cursor-default
                                                     <?= $asistio ? 'bg-emerald-100 text-emerald-600' : 'bg-red-50 text-red-300' ?>">
                                                    <?= $asistio ? '&#10003;' : '&bull;' ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <p class="text-[9px] text-zinc-400 mt-1"><?= $alumno['dias_asistidos'] ?> / <?= $dias_habiles ?> dias</p>
                                    </td>
                                    <td class="px-2 py-3 text-center">
                                        <span class="text-xs font-bold text-zinc-600"><?= $alumno['porcentaje'] ?>%</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-center">
                                        <?php if ($alumno['porcentaje'] >= 80): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">Regular</span>
                                        <?php elseif ($alumno['porcentaje'] >= 60): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-yellow-100 text-yellow-700">Alerta</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">Critico</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>

<?php require_once 'includes/footer.php'; ?>
