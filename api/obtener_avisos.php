<?php
/**
 * API - Obtener avisos para el tutor autenticado
 * SGA COBAEV - Sistema de Avisos Mejorado
 * 
 * Soporta filtros: categoria, no_leidos, pagina, limite
 * Incluye estado de lectura individual por tutor (tabla avisos_leidos)
 */

require_once '../includes/session_padres.php';
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticacion de tutor
if (!hayTutorAutenticado()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../conexion.php';

$matricula = $_SESSION['alumno_matricula'] ?? '';
$matricula_tutor = $_SESSION['tutor_matricula'] ?? $_SESSION['alumno_matricula'] ?? '';

if (empty($matricula)) {
    http_response_code(400);
    echo json_encode(['error' => 'Matricula no disponible']);
    exit;
}

// Parametros GET
$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
$no_leidos = isset($_GET['no_leidos']) && $_GET['no_leidos'] == '1';
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$limite = max(1, min(50, intval($_GET['limite'] ?? 10)));
$offset = ($pagina - 1) * $limite;

try {
    // Construir condiciones WHERE
    $where = "WHERE a.activo = 1 AND a.fecha_publicacion <= NOW() AND (a.destinatario = 'todos' OR a.destinatario = :matricula)";
    $params = ['matricula' => $matricula];

    if (!empty($categoria) && in_array($categoria, ['institucional','academico','emergencia','pagos','cultural'])) {
        $where .= " AND a.categoria = :categoria";
        $params['categoria'] = $categoria;
    }

    if ($no_leidos) {
        $where .= " AND al.id IS NULL";
    }

    // Query principal con LEFT JOIN para estado de lectura
    $sql = "SELECT a.id_aviso, a.titulo, a.contenido, a.categoria, a.prioridad, 
                   a.archivo_adjunto, a.fecha_publicacion, a.destinatario,
                   CASE WHEN al.id IS NOT NULL THEN 1 ELSE 0 END as leido
            FROM avisos a
            LEFT JOIN avisos_leidos al ON al.id_aviso = a.id_aviso AND al.matricula_tutor = :matricula_tutor
            $where
            ORDER BY a.fecha_publicacion DESC
            LIMIT " . intval($limite) . " OFFSET " . intval($offset);

    $params['matricula_tutor'] = $matricula_tutor;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $avisos_raw = $stmt->fetchAll();

    // Query para contar total
    $sql_count = "SELECT COUNT(*) as total
                  FROM avisos a
                  LEFT JOIN avisos_leidos al ON al.id_aviso = a.id_aviso AND al.matricula_tutor = :matricula_tutor
                  $where";
    $stmt_count = $pdo->prepare($sql_count);
    $stmt_count->execute($params);
    $total = intval($stmt_count->fetch()['total']);

    // Query para contar no leidos
    $sql_no_leidos = "SELECT COUNT(*) as no_leidos
                      FROM avisos a
                      LEFT JOIN avisos_leidos al ON al.id_aviso = a.id_aviso AND al.matricula_tutor = :matricula_tutor
                      WHERE a.activo = 1 AND a.fecha_publicacion <= NOW() 
                      AND (a.destinatario = 'todos' OR a.destinatario = :matricula)
                      AND al.id IS NULL";
    $stmt_nl = $pdo->prepare($sql_no_leidos);
    $stmt_nl->execute(['matricula_tutor' => $matricula_tutor, 'matricula' => $matricula]);
    $total_no_leidos = intval($stmt_nl->fetch()['no_leidos']);

    // Formatear avisos
    $avisos = [];
    foreach ($avisos_raw as $aviso) {
        $fecha_pub = new DateTime($aviso['fecha_publicacion']);
        $ahora = new DateTime();
        $diff = $ahora->diff($fecha_pub);

        // Calcular fecha relativa
        if ($diff->days == 0) {
            $fecha_relativa = 'Hoy';
        } elseif ($diff->days == 1) {
            $fecha_relativa = 'Ayer';
        } else {
            $fecha_relativa = 'Hace ' . $diff->days . ' dias';
        }

        // Icono por categoria
        $iconos = [
            'institucional' => "\xF0\x9F\x8F\xAB",
            'academico' => "\xF0\x9F\x93\x9A",
            'emergencia' => "\xF0\x9F\x9A\xA8",
            'pagos' => "\xF0\x9F\x92\xB0",
            'cultural' => "\xF0\x9F\x8E\xAD"
        ];
        $icono = $iconos[$aviso['categoria']] ?? "\xF0\x9F\x93\xA2";

        // Preview (primeros 150 chars)
        $contenido = $aviso['contenido'] ?? '';
        $preview = mb_strlen($contenido) > 150 ? mb_substr($contenido, 0, 150) . '...' : $contenido;

        $avisos[] = [
            'id' => intval($aviso['id_aviso']),
            'titulo' => $aviso['titulo'],
            'preview' => $preview,
            'contenido' => $contenido,
            'categoria' => $aviso['categoria'],
            'prioridad' => $aviso['prioridad'],
            'icono' => $icono,
            'fecha' => $aviso['fecha_publicacion'],
            'fecha_relativa' => $fecha_relativa,
            'leido' => (bool)$aviso['leido'],
            'archivo_adjunto' => $aviso['archivo_adjunto']
        ];
    }

    // Respuesta
    echo json_encode([
        'success' => true,
        'avisos' => $avisos,
        'estadisticas' => [
            'total' => $total,
            'no_leidos' => $total_no_leidos
        ],
        'paginacion' => [
            'pagina_actual' => $pagina,
            'por_pagina' => $limite,
            'total' => $total,
            'total_paginas' => ceil($total / $limite)
        ]
    ]);

} catch (PDOException $e) {
    error_log('SGA Error obtener_avisos: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor']);
}
