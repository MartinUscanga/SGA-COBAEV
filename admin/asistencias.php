<?php
/**
 * Historial de Asistencias - Filtros y Paginacion
 * SGA COBAEV - Panel Administrativo
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

// Filtros
$busqueda = trim($_GET['buscar'] ?? '');
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$tipo_filtro = $_GET['tipo'] ?? '';
$pagina = max(1, (int)($_GET['pagina'] ?? 1));
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

// Construir consulta con filtros
$where = "WHERE 1=1";
$params = [];

if ($busqueda !== '') {
    $where .= " AND (a.matricula_alumno LIKE :buscar OR al.nombre LIKE :buscar2 OR al.apellido_paterno LIKE :buscar3)";
    $params['buscar'] = "%$busqueda%";
    $params['buscar2'] = "%$busqueda%";
    $params['buscar3'] = "%$busqueda%";
}
if ($fecha_inicio !== '') {
    $where .= " AND a.fecha >= :fecha_inicio";
    $params['fecha_inicio'] = $fecha_inicio;
}
if ($fecha_fin !== '') {
    $where .= " AND a.fecha <= :fecha_fin";
    $params['fecha_fin'] = $fecha_fin;
}
if ($tipo_filtro !== '' && in_array($tipo_filtro, ['Entrada', 'Salida'])) {
    $where .= " AND a.tipo = :tipo";
    $params['tipo'] = $tipo_filtro;
}

// Total registros
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM asistencias a INNER JOIN alumnos al ON a.matricula_alumno = al.matricula $where");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_paginas = ceil($total / $por_pagina);

// Obtener registros
// intval() ensures $por_pagina and $offset are safe integers for LIMIT/OFFSET
$por_pagina_int = intval($por_pagina);
$offset_int = intval($offset);
$stmt = $pdo->prepare("
    SELECT a.*, al.nombre, al.apellido_paterno, al.apellido_materno
    FROM asistencias a
    INNER JOIN alumnos al ON a.matricula_alumno = al.matricula
    $where
    ORDER BY a.fecha DESC, a.hora DESC
    LIMIT $por_pagina_int OFFSET $offset_int
");
$stmt->execute($params);
$asistencias = $stmt->fetchAll();

// Estadisticas resumen
$hoy = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM asistencias WHERE fecha = :fecha AND tipo = 'Entrada'");
$stmt->execute(['fecha' => $hoy]);
$entradas_hoy = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM asistencias WHERE fecha = :fecha AND tipo = 'Salida'");
$stmt->execute(['fecha' => $hoy]);
$salidas_hoy = $stmt->fetch()['total'];

$pagina_actual = 'asistencias';
$page_title = 'Asistencias - Panel Admin SGA COBAEV';
$page_header = 'Historial de Asistencias';

require_once 'includes/head.php';
require_once 'includes/sidebar.php';
require_once 'includes/header.php';
?>

        <div class="p-4 md:p-8 space-y-6">
            <!-- Estadisticas resumen -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border border-zinc-200 p-4 flex items-center space-x-4">
                    <div class="w-10 h-10 bg-blue-50 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-400 font-bold uppercase">Total Registros</p>
                        <p class="text-2xl font-bold text-vino"><?= number_format($total) ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-zinc-200 p-4 flex items-center space-x-4">
                    <div class="w-10 h-10 bg-green-50 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-400 font-bold uppercase">Entradas Hoy</p>
                        <p class="text-2xl font-bold text-green-700"><?= $entradas_hoy ?></p>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-zinc-200 p-4 flex items-center space-x-4">
                    <div class="w-10 h-10 bg-orange-50 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7"/></svg>
                    </div>
                    <div>
                        <p class="text-xs text-zinc-400 font-bold uppercase">Salidas Hoy</p>
                        <p class="text-2xl font-bold text-orange-700"><?= $salidas_hoy ?></p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="bg-white rounded-xl border border-zinc-200 p-4">
                <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar alumno o matricula..."
                           class="bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>"
                           class="bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    <input type="date" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>"
                           class="bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    <select name="tipo" class="bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                        <option value="">Todos los tipos</option>
                        <option value="Entrada" <?= $tipo_filtro === 'Entrada' ? 'selected' : '' ?>>Entrada</option>
                        <option value="Salida" <?= $tipo_filtro === 'Salida' ? 'selected' : '' ?>>Salida</option>
                    </select>
                    <div class="flex space-x-2">
                        <button type="submit" class="flex-1 bg-dorado hover:bg-opacity-90 text-white font-bold text-xs uppercase tracking-wider px-4 py-2.5 rounded-lg transition-colors">Filtrar</button>
                        <a href="asistencias.php" class="bg-zinc-200 hover:bg-zinc-300 text-zinc-700 font-bold text-xs uppercase tracking-wider px-4 py-2.5 rounded-lg transition-colors flex items-center justify-center">Limpiar</a>
                    </div>
                </form>
            </div>

            <!-- Tabla de Asistencias -->
            <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Fecha</th>
                                <th class="px-4 py-3 text-left">Hora</th>
                                <th class="px-4 py-3 text-left">Alumno</th>
                                <th class="px-4 py-3 text-left">Matricula</th>
                                <th class="px-4 py-3 text-center">Tipo</th>
                                <th class="px-4 py-3 text-left">Observaciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            <?php if (empty($asistencias)): ?>
                            <tr><td colspan="6" class="px-4 py-8 text-center text-zinc-400">No se encontraron registros</td></tr>
                            <?php else: ?>
                            <?php foreach ($asistencias as $reg): ?>
                            <tr class="hover:bg-zinc-50/50">
                                <td class="px-4 py-3 text-zinc-600"><?= htmlspecialchars($reg['fecha']) ?></td>
                                <td class="px-4 py-3 text-zinc-600 font-mono text-xs"><?= htmlspecialchars($reg['hora']) ?></td>
                                <td class="px-4 py-3 font-medium text-zinc-700"><?= htmlspecialchars($reg['nombre'] . ' ' . $reg['apellido_paterno'] . ' ' . $reg['apellido_materno']) ?></td>
                                <td class="px-4 py-3 font-mono text-xs text-vino font-bold"><?= htmlspecialchars($reg['matricula_alumno']) ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php if ($reg['tipo'] === 'Entrada'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">Entrada</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-700">Salida</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-zinc-400 text-xs"><?= htmlspecialchars($reg['observaciones'] ?? '-') ?></td>
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
                        <a href="?pagina=<?= $pagina - 1 ?>&buscar=<?= urlencode($busqueda) ?>&fecha_inicio=<?= urlencode($fecha_inicio) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>&tipo=<?= urlencode($tipo_filtro) ?>" class="px-3 py-1 rounded text-xs font-medium bg-zinc-100 hover:bg-zinc-200 text-zinc-700">Anterior</a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $pagina - 2); $i <= min($total_paginas, $pagina + 2); $i++): ?>
                        <a href="?pagina=<?= $i ?>&buscar=<?= urlencode($busqueda) ?>&fecha_inicio=<?= urlencode($fecha_inicio) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>&tipo=<?= urlencode($tipo_filtro) ?>" class="px-3 py-1 rounded text-xs font-medium <?= $i === $pagina ? 'bg-vino text-white' : 'bg-zinc-100 hover:bg-zinc-200 text-zinc-700' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($pagina < $total_paginas): ?>
                        <a href="?pagina=<?= $pagina + 1 ?>&buscar=<?= urlencode($busqueda) ?>&fecha_inicio=<?= urlencode($fecha_inicio) ?>&fecha_fin=<?= urlencode($fecha_fin) ?>&tipo=<?= urlencode($tipo_filtro) ?>" class="px-3 py-1 rounded text-xs font-medium bg-zinc-100 hover:bg-zinc-200 text-zinc-700">Siguiente</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

<?php require_once 'includes/footer.php'; ?>
