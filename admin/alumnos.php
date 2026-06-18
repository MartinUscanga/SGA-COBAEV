<?php
/**
 * Gestion de Alumnos - CRUD Completo
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
            $matricula = strtoupper(trim($_POST['matricula']));
            $nombre = trim($_POST['nombre']);
            $apellido_paterno = trim($_POST['apellido_paterno']);

            // Validacion de entrada
            if (!preg_match('/^[A-Z]\d{7}$/', $matricula)) {
                $mensaje = 'La matricula debe ser una letra seguida de 7 digitos (ej: B2024001).';
                $tipo_mensaje = 'error';
            } elseif (empty($nombre) || mb_strlen($nombre) > 50) {
                $mensaje = 'El nombre es obligatorio y no debe exceder 50 caracteres.';
                $tipo_mensaje = 'error';
            } elseif (empty($apellido_paterno) || mb_strlen($apellido_paterno) > 50) {
                $mensaje = 'El apellido paterno es obligatorio y no debe exceder 50 caracteres.';
                $tipo_mensaje = 'error';
            } else {
                $stmt = $pdo->prepare("INSERT INTO alumnos (matricula, nombre, apellido_paterno, apellido_materno, grupo, fecha_nacimiento, email, telefono, direccion) VALUES (:matricula, :nombre, :ap, :am, :grupo, :fecha_nacimiento, :email, :telefono, :direccion)");
                $stmt->execute([
                    'matricula' => $matricula,
                    'nombre' => $nombre,
                    'ap' => $apellido_paterno,
                    'am' => trim($_POST['apellido_materno'] ?? ''),
                    'grupo' => trim($_POST['grupo'] ?? ''),
                    'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? null,
                    'email' => trim($_POST['email'] ?? ''),
                    'telefono' => trim($_POST['telefono'] ?? ''),
                    'direccion' => trim($_POST['direccion'] ?? '')
                ]);
                $mensaje = 'Alumno registrado exitosamente.';
                $tipo_mensaje = 'success';
            }
        } elseif ($accion === 'editar') {
            $nombre = trim($_POST['nombre']);
            $apellido_paterno = trim($_POST['apellido_paterno']);

            if (empty($nombre) || mb_strlen($nombre) > 50) {
                $mensaje = 'El nombre es obligatorio y no debe exceder 50 caracteres.';
                $tipo_mensaje = 'error';
            } elseif (empty($apellido_paterno) || mb_strlen($apellido_paterno) > 50) {
                $mensaje = 'El apellido paterno es obligatorio y no debe exceder 50 caracteres.';
                $tipo_mensaje = 'error';
            } else {
                $stmt = $pdo->prepare("UPDATE alumnos SET nombre = :nombre, apellido_paterno = :ap, apellido_materno = :am, grupo = :grupo, fecha_nacimiento = :fecha_nacimiento, email = :email, telefono = :telefono, direccion = :direccion WHERE matricula = :matricula");
                $stmt->execute([
                    'matricula' => $_POST['matricula'],
                    'nombre' => $nombre,
                    'ap' => $apellido_paterno,
                    'am' => trim($_POST['apellido_materno'] ?? ''),
                    'grupo' => trim($_POST['grupo'] ?? ''),
                    'fecha_nacimiento' => $_POST['fecha_nacimiento'] ?? null,
                    'email' => trim($_POST['email'] ?? ''),
                    'telefono' => trim($_POST['telefono'] ?? ''),
                    'direccion' => trim($_POST['direccion'] ?? '')
                ]);
                $mensaje = 'Alumno actualizado exitosamente.';
                $tipo_mensaje = 'success';
            }
        } elseif ($accion === 'eliminar') {
            $stmt = $pdo->prepare("DELETE FROM alumnos WHERE matricula = :matricula");
            $stmt->execute(['matricula' => $_POST['matricula']]);
            $mensaje = 'Alumno eliminado exitosamente.';
            $tipo_mensaje = 'success';
        }
    } catch (PDOException $e) {
        error_log('SGA Error [alumnos]: ' . $e->getMessage());
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
    $where .= " AND (matricula LIKE :buscar OR nombre LIKE :buscar2 OR apellido_paterno LIKE :buscar3)";
    $params['buscar'] = "%$busqueda%";
    $params['buscar2'] = "%$busqueda%";
    $params['buscar3'] = "%$busqueda%";
}

// Total registros
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM alumnos $where");
$stmt->execute($params);
$total = $stmt->fetch()['total'];
$total_paginas = ceil($total / $por_pagina);

// Obtener alumnos
// intval() ensures $por_pagina and $offset are safe integers for LIMIT/OFFSET
$por_pagina_int = intval($por_pagina);
$offset_int = intval($offset);
$stmt = $pdo->prepare("SELECT * FROM alumnos $where ORDER BY apellido_paterno, nombre LIMIT $por_pagina_int OFFSET $offset_int");
$stmt->execute($params);
$alumnos = $stmt->fetchAll();

// Obtener estado actual de cada alumno usando una sola consulta (evita N+1)
$hoy = date('Y-m-d');
$estados = [];
if (!empty($alumnos)) {
    $matriculas = array_column($alumnos, 'matricula');
    $placeholders = implode(',', array_fill(0, count($matriculas), '?'));
    $stmt2 = $pdo->prepare("
        SELECT a.matricula_alumno, a.tipo
        FROM asistencias a
        INNER JOIN (
            SELECT matricula_alumno, MAX(hora) as max_hora
            FROM asistencias
            WHERE fecha = ? AND matricula_alumno IN ($placeholders)
            GROUP BY matricula_alumno
        ) ultimo ON a.matricula_alumno = ultimo.matricula_alumno AND a.hora = ultimo.max_hora AND a.fecha = ?
    ");
    $bind_params = array_merge([$hoy], $matriculas, [$hoy]);
    $stmt2->execute($bind_params);
    $resultados_estado = $stmt2->fetchAll();
    foreach ($resultados_estado as $row) {
        $estados[$row['matricula_alumno']] = $row['tipo'];
    }
}

// Alumno para editar
$alumno_editar = null;
if (isset($_GET['editar'])) {
    $stmt = $pdo->prepare("SELECT * FROM alumnos WHERE matricula = :mat");
    $stmt->execute(['mat' => $_GET['editar']]);
    $alumno_editar = $stmt->fetch();
}

$pagina_actual = 'alumnos';
$page_title = 'Alumnos - Panel Admin SGA COBAEV';
$page_header = 'Gestion de Alumnos';

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
                    <?= $alumno_editar ? 'Editar Alumno' : 'Registrar Nuevo Alumno' ?>
                </h3>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="<?= $alumno_editar ? 'editar' : 'crear' ?>">
                    
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Matricula</label>
                        <input type="text" name="matricula" required maxlength="8" 
                               value="<?= htmlspecialchars($alumno_editar['matricula'] ?? '') ?>"
                               <?= $alumno_editar ? 'readonly class="w-full bg-zinc-100 border border-zinc-200 rounded p-2.5 text-sm font-mono uppercase"' : 'class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm font-mono uppercase focus:outline-none focus:border-vino"' ?>>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Nombre</label>
                        <input type="text" name="nombre" required value="<?= htmlspecialchars($alumno_editar['nombre'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Apellido Paterno</label>
                        <input type="text" name="apellido_paterno" required value="<?= htmlspecialchars($alumno_editar['apellido_paterno'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Apellido Materno</label>
                        <input type="text" name="apellido_materno" value="<?= htmlspecialchars($alumno_editar['apellido_materno'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Grupo</label>
                        <input type="text" name="grupo" value="<?= htmlspecialchars($alumno_editar['grupo'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Fecha Nacimiento</label>
                        <input type="date" name="fecha_nacimiento" value="<?= htmlspecialchars($alumno_editar['fecha_nacimiento'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($alumno_editar['email'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Telefono</label>
                        <input type="text" name="telefono" value="<?= htmlspecialchars($alumno_editar['telefono'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Direccion</label>
                        <input type="text" name="direccion" value="<?= htmlspecialchars($alumno_editar['direccion'] ?? '') ?>"
                               class="w-full bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="submit" class="bg-vino hover:bg-opacity-90 text-white font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors">
                            <?= $alumno_editar ? 'Actualizar' : 'Registrar' ?>
                        </button>
                        <?php if ($alumno_editar): ?>
                        <a href="alumnos.php" class="bg-zinc-200 hover:bg-zinc-300 text-zinc-700 font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors">Cancelar</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Busqueda -->
            <div class="bg-white rounded-xl border border-zinc-200 p-4">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="buscar" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar por nombre o matricula..."
                           class="flex-1 bg-zinc-50 border border-zinc-200 rounded p-2.5 text-sm focus:outline-none focus:border-vino">
                    <button type="submit" class="bg-dorado hover:bg-opacity-90 text-white font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors">Buscar</button>
                    <?php if ($busqueda): ?>
                    <a href="alumnos.php" class="bg-zinc-200 hover:bg-zinc-300 text-zinc-700 font-bold text-xs uppercase tracking-wider px-6 py-2.5 rounded-lg transition-colors text-center">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Tabla de Alumnos -->
            <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 text-xs uppercase text-zinc-500 tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Matricula</th>
                                <th class="px-4 py-3 text-left">Nombre Completo</th>
                                <th class="px-4 py-3 text-left">Grupo</th>
                                <th class="px-4 py-3 text-center">Estado</th>
                                <th class="px-4 py-3 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100">
                            <?php if (empty($alumnos)): ?>
                            <tr><td colspan="5" class="px-4 py-8 text-center text-zinc-400">No se encontraron alumnos</td></tr>
                            <?php else: ?>
                            <?php foreach ($alumnos as $al): ?>
                            <tr class="hover:bg-zinc-50/50">
                                <td class="px-4 py-3 font-mono text-xs font-bold text-vino"><?= htmlspecialchars($al['matricula']) ?></td>
                                <td class="px-4 py-3 font-medium text-zinc-700"><?= htmlspecialchars($al['nombre'] . ' ' . $al['apellido_paterno'] . ' ' . $al['apellido_materno']) ?></td>
                                <td class="px-4 py-3 text-zinc-500"><?= htmlspecialchars($al['grupo'] ?? '-') ?></td>
                                <td class="px-4 py-3 text-center">
                                    <?php
                                    $estado = $estados[$al['matricula']] ?? null;
                                    if ($estado === 'Entrada'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-green-100 text-green-700">En Plantel</span>
                                    <?php elseif ($estado === 'Salida'): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-orange-100 text-orange-700">Fuera</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-zinc-100 text-zinc-500">Sin registro</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-center space-x-1">
                                    <a href="?editar=<?= urlencode($al['matricula']) ?>" class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100">Editar</a>
                                    <form method="POST" class="inline" onsubmit="return confirm('Desactivar este alumno?')">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="matricula" value="<?= htmlspecialchars($al['matricula']) ?>">
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
