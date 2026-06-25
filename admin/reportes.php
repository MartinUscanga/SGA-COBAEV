<?php
/**
 * Reportes de Asistencia por Grupo
 * SGA COBAEV - Panel Administrativo
 * 
 * Lista de asistencia estilo profesor con días como columnas.
 * Incluye botón para generar PDF.
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

// Detectar filtro de alumnos activos
$filtro_activo = "";
try {
    $test = $pdo->query("SELECT 1 FROM alumnos WHERE estado = 'Activo' LIMIT 1");
    $filtro_activo = "AND a.estado = 'Activo'";
} catch (PDOException $e) {
    try {
        $test = $pdo->query("SELECT 1 FROM alumnos WHERE activo = 1 LIMIT 1");
        $filtro_activo = "AND a.activo = 1";
    } catch (PDOException $e2) {
        $filtro_activo = "";
    }
}

// Obtener lista de grupos disponibles
try {
    $stmt = $pdo->query("SELECT DISTINCT grupo FROM alumnos a WHERE grupo IS NOT NULL AND grupo != '' $filtro_activo ORDER BY grupo ASC");
    $grupos_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->query("SELECT DISTINCT grupo FROM alumnos WHERE grupo IS NOT NULL AND grupo != '' ORDER BY grupo ASC");
        $grupos_disponibles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e2) {
        $grupos_disponibles = [];
    }
}

// Generar array de días hábiles en el rango (Lunes a Viernes)
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
$dias_habiles = count($dias_habiles_lista);
if ($dias_habiles == 0) $dias_habiles = 1;

// Obtener reporte por grupo
$where_grupo = "";
$params = ['fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin];

if (!empty($grupo_filtro)) {
    $where_grupo = "AND a.grupo = :grupo";
    $params['grupo'] = $grupo_filtro;
}

// Query principal
$resultados = [];
$fechas_asistencia = [];
try {
    $sql = "
        SELECT 
            a.grupo,
            a.matricula,
            CONCAT(a.apellido_paterno, ' ', IFNULL(a.apellido_materno, ''), ' ', a.nombre) AS nombre_completo,
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
        ORDER BY a.grupo ASC, a.apellido_paterno ASC, a.apellido_materno ASC, a.nombre ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener fechas específicas de asistencia por alumno
    $sql_fechas = "
        SELECT DISTINCT matricula_alumno, fecha
        FROM asistencias
        WHERE fecha >= :fecha_inicio 
        AND fecha <= :fecha_fin
        AND tipo = 'Entrada'
    ";
    $stmt_fechas = $pdo->prepare($sql_fechas);
    $stmt_fechas->execute(['fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin]);
    foreach ($stmt_fechas->fetchAll(PDO::FETCH_ASSOC) as $rf) {
        $fechas_asistencia[$rf['matricula_alumno']][] = $rf['fecha'];
    }
} catch (PDOException $e) {
    error_log("Error en reportes.php: " . $e->getMessage());
    $resultados = [];
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
            <!-- Encabezado con botón PDF -->
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-zinc-700">Reporte de Asistencia por Grupo</h2>
                    <p class="text-xs text-zinc-400 mt-0.5">Lista de asistencia con detalle por dia</p>
                </div>
                <?php if (!empty($reporte_por_grupo)): ?>
                <button onclick="generarPDF()" class="inline-flex items-center space-x-2 bg-red-700 hover:bg-red-800 text-white font-bold text-xs tracking-wider uppercase px-4 py-2.5 rounded-lg shadow-sm active:scale-[0.98] transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span>Generar PDF</span>
                </button>
                <?php endif; ?>
            </div>

            <!-- Filtros -->
            <form method="GET" class="bg-white rounded-xl border border-zinc-200 p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Fecha Inicio</label>
                        <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Fecha Fin</label>
                        <input type="date" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase tracking-wide mb-1.5">Grupo</label>
                        <select name="grupo" class="w-full bg-zinc-50 border border-zinc-200 rounded-lg p-2.5 text-sm focus:outline-none focus:border-vino transition-colors">
                            <option value="">Todos los grupos</option>
                            <?php foreach ($grupos_disponibles as $g): ?>
                                <option value="<?= htmlspecialchars($g) ?>" <?= $grupo_filtro === $g ? 'selected' : '' ?>><?= htmlspecialchars($g) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
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

                <!-- Reporte por grupo - Estilo lista de asistencia -->
                <?php foreach ($reporte_por_grupo as $grupo => $data): ?>
                <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden" id="grupo-<?= htmlspecialchars($grupo) ?>">
                    <!-- Header del grupo -->
                    <div class="px-6 py-4 border-b border-zinc-100 flex items-center justify-between">
                        <div>
                            <h3 class="font-serif-elegant text-lg font-bold text-vino">Grupo <?= htmlspecialchars($grupo) ?></h3>
                            <p class="text-xs text-zinc-400 mt-0.5"><?= $data['total_alumnos'] ?> alumnos &bull; <?= $dias_habiles ?> dias habiles</p>
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
                                <?= $pct ?>% asistencia
                            </span>
                        </div>
                    </div>

                    <!-- Tabla estilo lista de asistencia del profesor -->
                    <div class="overflow-x-auto">
                        <table class="text-xs border-collapse">
                            <thead>
                                <tr class="bg-zinc-50">
                                    <th class="px-3 py-2 text-left font-bold text-zinc-600 uppercase tracking-wide border-b border-zinc-200 sticky left-0 bg-zinc-50 z-10 whitespace-nowrap">Alumno</th>
                                    <?php foreach ($dias_habiles_lista as $dia): ?>
                                        <th class="px-1 py-2 text-center font-bold text-zinc-400 border-b border-zinc-200 w-7">
                                            <div class="text-[9px] leading-tight"><?= date('D', strtotime($dia)) ?></div>
                                            <div class="text-[10px] text-zinc-600 font-bold"><?= date('d', strtotime($dia)) ?></div>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="px-2 py-2 text-center font-bold text-zinc-600 uppercase tracking-wide border-b border-zinc-200 whitespace-nowrap">Total</th>
                                    <th class="px-2 py-2 text-center font-bold text-zinc-600 uppercase tracking-wide border-b border-zinc-200">%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['alumnos'] as $idx => $alumno): ?>
                                <tr class="<?= $idx % 2 === 0 ? 'bg-white' : 'bg-zinc-50/50' ?> hover:bg-blue-50/30">
                                    <td class="px-3 py-2 font-medium text-zinc-700 border-b border-zinc-100 sticky left-0 <?= $idx % 2 === 0 ? 'bg-white' : 'bg-zinc-50' ?> z-10 whitespace-nowrap">
                                        <?= htmlspecialchars(mb_substr($alumno['nombre'], 0, 25)) ?><?= mb_strlen($alumno['nombre']) > 25 ? '...' : '' ?>
                                    </td>
                                    <?php foreach ($dias_habiles_lista as $dia): ?>
                                        <?php $asistio = in_array($dia, $alumno['fechas_asistio']); ?>
                                        <td class="px-0 py-2 text-center border-b border-zinc-100">
                                            <?php if ($asistio): ?>
                                                <span class="inline-block w-5 h-5 leading-5 rounded-full bg-emerald-100 text-emerald-600 text-[10px] font-bold text-center">&#10003;</span>
                                            <?php else: ?>
                                                <span class="inline-block w-5 h-5 leading-5 rounded-full bg-red-50 text-red-300 text-[10px] text-center">&bull;</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="px-2 py-2 text-center border-b border-zinc-100 font-bold text-zinc-600 whitespace-nowrap">
                                        <?= $alumno['dias_asistidos'] ?>/<?= $dias_habiles ?>
                                    </td>
                                    <td class="px-2 py-2 text-center border-b border-zinc-100 whitespace-nowrap">
                                        <?php
                                            $pct_al = $alumno['porcentaje'];
                                            $color_pct = 'text-zinc-500';
                                            if ($pct_al >= 90) $color_pct = 'text-emerald-600';
                                            elseif ($pct_al >= 75) $color_pct = 'text-green-600';
                                            elseif ($pct_al >= 60) $color_pct = 'text-yellow-600';
                                            elseif ($pct_al >= 40) $color_pct = 'text-orange-600';
                                            elseif ($pct_al > 0) $color_pct = 'text-red-600';
                                        ?>
                                        <span class="font-bold <?= $color_pct ?>"><?= $pct_al ?>%</span>
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

        <!-- Script para generar PDF -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <script>
        function generarPDF() {
            const btn = event.target.closest('button');
            const textoOriginal = btn.innerHTML;
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><span>Generando...</span>';
            btn.disabled = true;

            const ALUMNOS_POR_PAGINA = 18;
            const container = document.createElement('div');
            container.style.padding = '15px';
            container.style.background = 'white';
            container.style.fontFamily = 'Arial, sans-serif';

            // Procesar cada grupo
            const grupos = document.querySelectorAll('[id^="grupo-"]');
            grupos.forEach(grupo => {
                const table = grupo.querySelector('table');
                if (!table) return;

                const thead = table.querySelector('thead');
                const rows = Array.from(table.querySelectorAll('tbody tr'));
                const header = grupo.querySelector('.px-6.py-4'); // Header del grupo
                const theadHTML = thead ? thead.outerHTML : '';
                
                // Dividir alumnos en bloques
                const totalPaginas = Math.ceil(rows.length / ALUMNOS_POR_PAGINA);
                
                for (let pag = 0; pag < totalPaginas; pag++) {
                    const bloque = document.createElement('div');
                    bloque.style.marginBottom = '10px';
                    bloque.style.border = '1px solid #e4e4e7';
                    bloque.style.borderRadius = '6px';
                    bloque.style.overflow = 'hidden';
                    
                    // Si es nueva página (no la primera), agregar page-break
                    if (pag > 0) {
                        bloque.style.pageBreakBefore = 'always';
                    }

                    // Header del grupo (repetir en cada página)
                    const headerClon = header ? header.cloneNode(true) : null;
                    if (headerClon) {
                        headerClon.style.padding = '10px 15px';
                        headerClon.style.borderBottom = '1px solid #e4e4e7';
                        headerClon.style.background = '#fafafa';
                        if (totalPaginas > 1) {
                            const paginaInfo = document.createElement('span');
                            paginaInfo.style.fontSize = '9px';
                            paginaInfo.style.color = '#999';
                            paginaInfo.style.marginLeft = '10px';
                            paginaInfo.textContent = `(Pag ${pag + 1}/${totalPaginas})`;
                            headerClon.querySelector('div')?.appendChild(paginaInfo);
                        }
                        bloque.appendChild(headerClon);
                    }

                    // Tabla con encabezado + filas del bloque
                    const tablaPag = document.createElement('table');
                    tablaPag.style.width = '100%';
                    tablaPag.style.borderCollapse = 'collapse';
                    tablaPag.style.fontSize = '8px';
                    tablaPag.innerHTML = theadHTML;
                    
                    // Estilos al thead
                    tablaPag.querySelectorAll('thead th').forEach(th => {
                        th.style.background = '#f4f4f5';
                        th.style.borderBottom = '1px solid #e4e4e7';
                        th.style.padding = '4px 2px';
                        th.style.fontSize = '7px';
                        th.style.fontWeight = 'bold';
                        th.style.textAlign = 'center';
                    });
                    const firstTh = tablaPag.querySelector('thead th');
                    if (firstTh) firstTh.style.textAlign = 'left';

                    // Body con filas de esta página
                    const tbody = document.createElement('tbody');
                    const inicio = pag * ALUMNOS_POR_PAGINA;
                    const fin = Math.min(inicio + ALUMNOS_POR_PAGINA, rows.length);
                    
                    for (let i = inicio; i < fin; i++) {
                        const fila = rows[i].cloneNode(true);
                        fila.querySelectorAll('td').forEach(td => {
                            td.style.borderBottom = '1px solid #f4f4f5';
                            td.style.padding = '3px 1px';
                            td.style.textAlign = 'center';
                            td.style.fontSize = '8px';
                            td.style.position = 'static';
                        });
                        const firstTd = fila.querySelector('td');
                        if (firstTd) {
                            firstTd.style.textAlign = 'left';
                            firstTd.style.paddingLeft = '4px';
                            firstTd.style.fontWeight = '500';
                        }
                        tbody.appendChild(fila);
                    }
                    tablaPag.appendChild(tbody);
                    bloque.appendChild(tablaPag);
                    container.appendChild(bloque);
                }
            });

            // Agregar título al inicio
            const titulo = document.createElement('div');
            titulo.style.textAlign = 'center';
            titulo.style.marginBottom = '15px';
            titulo.style.borderBottom = '2px solid #5c1931';
            titulo.style.paddingBottom = '10px';
            titulo.innerHTML = `
                <h1 style="font-size:16px;font-weight:bold;color:#5c1931;margin:0;">SGA COBAEV - Reporte de Asistencia</h1>
                <p style="font-size:10px;color:#666;margin-top:4px;">
                    Periodo: <?= date('d/m/Y', strtotime($fecha_inicio)) ?> al <?= date('d/m/Y', strtotime($fecha_fin)) ?> 
                    &bull; Dias habiles: <?= $dias_habiles ?>
                    &bull; Generado: ${new Date().toLocaleDateString('es-MX')}
                </p>
            `;
            container.insertBefore(titulo, container.firstChild);

            document.body.appendChild(container);

            const opt = {
                margin: [8, 4, 8, 4],
                filename: 'Reporte_Asistencia_<?= htmlspecialchars($grupo_filtro ?: "Todos") ?>_<?= date("Y-m-d") ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, scrollY: 0 },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' },
                pagebreak: { mode: ['css', 'legacy'] }
            };

            html2pdf().set(opt).from(container).save().then(() => {
                document.body.removeChild(container);
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
            }).catch(err => {
                console.error('Error al generar PDF:', err);
                document.body.removeChild(container);
                btn.innerHTML = textoOriginal;
                btn.disabled = false;
                alert('Error al generar el PDF. Intente de nuevo.');
            });
        }
        </script>

<?php require_once 'includes/footer.php'; ?>
