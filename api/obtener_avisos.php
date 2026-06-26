<?php
/**
 * API - Obtener avisos para el tutor autenticado
 * SGA COBAEV - Sistema de Avisos Mejorado
 * 
 * Retrocompatible: funciona tanto con la tabla original (sin migracion)
 * como con la tabla migrada (columnas nuevas: contenido, categoria, prioridad, activo, fecha_publicacion).
 * 
 * Soporta filtros: categoria, no_leidos, pagina, limite
 * Incluye estado de lectura individual por tutor (tabla avisos_leidos si existe)
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

// Obtener TODAS las matriculas vinculadas al tutor (para multi-alumno)
$todas_matriculas = [];
if (!empty($matricula)) {
    $todas_matriculas[] = $matricula;
}
if (isset($_SESSION['alumnos']) && is_array($_SESSION['alumnos'])) {
    foreach ($_SESSION['alumnos'] as $alumno) {
        if (!empty($alumno['matricula']) && !in_array($alumno['matricula'], $todas_matriculas)) {
            $todas_matriculas[] = $alumno['matricula'];
        }
    }
}

if (empty($matricula) && empty($todas_matriculas)) {
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

/**
 * Verificar si las columnas nuevas existen en la tabla avisos
 */
function columnasNuevasExisten($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS 
                               WHERE TABLE_SCHEMA = DATABASE() 
                               AND TABLE_NAME = 'avisos' 
                               AND COLUMN_NAME = 'contenido'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['cnt']) > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Verificar si la tabla avisos_leidos existe
 */
function tablaAvisosLeidosExiste($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLES 
                               WHERE TABLE_SCHEMA = DATABASE() 
                               AND TABLE_NAME = 'avisos_leidos'");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return intval($result['cnt']) > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Calcular fecha relativa en espanol
 */
function calcularFechaRelativa($fecha_str) {
    try {
        $fecha = new DateTime($fecha_str);
        $ahora = new DateTime();
        $diff = $ahora->diff($fecha);

        if ($diff->days == 0) {
            return 'Hoy';
        } elseif ($diff->days == 1) {
            return 'Ayer';
        } else {
            return 'Hace ' . $diff->days . ' dias';
        }
    } catch (Exception $e) {
        return '';
    }
}

try {
    $tiene_columnas_nuevas = columnasNuevasExisten($pdo);
    $tiene_avisos_leidos = tablaAvisosLeidosExiste($pdo);

    // Iconos por categoria
    $iconos = [
        'institucional' => "\xF0\x9F\x8F\xAB",
        'academico' => "\xF0\x9F\x93\x9A",
        'emergencia' => "\xF0\x9F\x9A\xA8",
        'pagos' => "\xF0\x9F\x92\xB0",
        'cultural' => "\xF0\x9F\x8E\xAD"
    ];

    if (!$tiene_columnas_nuevas) {
        // === MODO LEGACY: tabla sin migracion ===
        // Estructura original: id_aviso, titulo, mensaje, fecha_envio, destinatario, leido
        
        // Crear placeholders para todas las matrículas
        $matricula_placeholders = [];
        $matricula_params = [];
        foreach ($todas_matriculas as $i => $mat) {
            $key = "mat_$i";
            $matricula_placeholders[] = ":$key";
            $matricula_params[$key] = $mat;
        }
        $in_clause = implode(',', $matricula_placeholders);

        $sql = "SELECT id_aviso, titulo, mensaje, fecha_envio, destinatario 
                FROM avisos 
                WHERE leido = 0 
                AND (destinatario = 'todos' OR destinatario IN ($in_clause)) 
                ORDER BY fecha_envio DESC 
                LIMIT " . intval($limite);

        $stmt = $pdo->prepare($sql);
        $stmt->execute($matricula_params);
        $avisos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Contar total
        $sql_count = "SELECT COUNT(*) as total FROM avisos 
                      WHERE leido = 0 
                      AND (destinatario = 'todos' OR destinatario IN ($in_clause))";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute($matricula_params);
        $total = intval($stmt_count->fetch(PDO::FETCH_ASSOC)['total']);

        // Formatear avisos con estructura compatible con la API nueva
        $avisos = [];
        foreach ($avisos_raw as $aviso) {
            $preview = '';
            if (!empty($aviso['mensaje'])) {
                $preview = mb_strlen($aviso['mensaje']) > 150 
                    ? mb_substr($aviso['mensaje'], 0, 150) . '...' 
                    : $aviso['mensaje'];
            }

            $avisos[] = [
                'id' => intval($aviso['id_aviso']),
                'titulo' => $aviso['titulo'],
                'preview' => $preview,
                'contenido' => $aviso['mensaje'] ?? '',
                'categoria' => 'institucional',
                'prioridad' => 'normal',
                'icono' => $iconos['institucional'],
                'fecha' => $aviso['fecha_envio'],
                'fecha_relativa' => calcularFechaRelativa($aviso['fecha_envio']),
                'leido' => false,
                'archivo_adjunto' => null
            ];
        }

        echo json_encode([
            'success' => true,
            'avisos' => $avisos,
            'estadisticas' => [
                'total' => $total,
                'no_leidos' => $total
            ],
            'paginacion' => [
                'pagina_actual' => $pagina,
                'por_pagina' => $limite,
                'total' => $total,
                'total_paginas' => max(1, ceil($total / $limite))
            ]
        ]);

    } else {
        // === MODO MIGRADO: tabla con columnas nuevas ===

        // Construir condiciones WHERE
        // Crear placeholders para todas las matrículas
        $matricula_placeholders = [];
        $matricula_params = [];
        foreach ($todas_matriculas as $i => $mat) {
            $key = "mat_$i";
            $matricula_placeholders[] = ":$key";
            $matricula_params[$key] = $mat;
        }
        $in_clause = implode(',', $matricula_placeholders);

        $where = "WHERE a.activo = 1 
                  AND (a.fecha_publicacion IS NULL OR a.fecha_publicacion <= NOW()) 
                  AND (a.destinatario = 'todos' OR a.destinatario IN ($in_clause))";
        $params = $matricula_params;

        if (!empty($categoria) && in_array($categoria, ['institucional','academico','emergencia','pagos','cultural'])) {
            $where .= " AND a.categoria = :categoria";
            $params['categoria'] = $categoria;
        }

        if ($tiene_avisos_leidos) {
            // Con tabla avisos_leidos: JOIN para estado de lectura
            if ($no_leidos) {
                $where .= " AND al.id IS NULL";
            }

            $sql = "SELECT a.id_aviso, a.titulo, a.contenido, a.mensaje, a.categoria, a.prioridad, 
                           a.archivo_adjunto, a.fecha_publicacion, a.fecha_envio, a.destinatario,
                           CASE WHEN al.id IS NOT NULL THEN 1 ELSE 0 END as leido
                    FROM avisos a
                    LEFT JOIN avisos_leidos al ON al.id_aviso = a.id_aviso AND al.matricula_tutor = :matricula_tutor
                    $where
                    ORDER BY COALESCE(a.fecha_publicacion, a.fecha_envio) DESC
                    LIMIT " . intval($limite) . " OFFSET " . intval($offset);

            $params['matricula_tutor'] = $matricula_tutor;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $avisos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Contar total
            $sql_count = "SELECT COUNT(*) as total
                          FROM avisos a
                          LEFT JOIN avisos_leidos al ON al.id_aviso = a.id_aviso AND al.matricula_tutor = :matricula_tutor
                          $where";
            $stmt_count = $pdo->prepare($sql_count);
            $stmt_count->execute($params);
            $total = intval($stmt_count->fetch(PDO::FETCH_ASSOC)['total']);

            // Contar no leidos
            $sql_no_leidos = "SELECT COUNT(*) as no_leidos
                              FROM avisos a
                              LEFT JOIN avisos_leidos al ON al.id_aviso = a.id_aviso AND al.matricula_tutor = :matricula_tutor
                              WHERE a.activo = 1 
                              AND (a.fecha_publicacion IS NULL OR a.fecha_publicacion <= NOW()) 
                              AND (a.destinatario = 'todos' OR a.destinatario IN ($in_clause))
                              AND al.id IS NULL";
            $params_nl = $matricula_params;
            $params_nl['matricula_tutor'] = $matricula_tutor;
            $stmt_nl = $pdo->prepare($sql_no_leidos);
            $stmt_nl->execute($params_nl);
            $total_no_leidos = intval($stmt_nl->fetch(PDO::FETCH_ASSOC)['no_leidos']);

        } else {
            // Sin tabla avisos_leidos: no se puede trackear lectura individual
            $sql = "SELECT a.id_aviso, a.titulo, a.contenido, a.mensaje, a.categoria, a.prioridad, 
                           a.archivo_adjunto, a.fecha_publicacion, a.fecha_envio, a.destinatario,
                           0 as leido
                    FROM avisos a
                    $where
                    ORDER BY COALESCE(a.fecha_publicacion, a.fecha_envio) DESC
                    LIMIT " . intval($limite) . " OFFSET " . intval($offset);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $avisos_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Contar total
            $sql_count = "SELECT COUNT(*) as total FROM avisos a $where";
            $stmt_count = $pdo->prepare($sql_count);
            $stmt_count->execute($params);
            $total = intval($stmt_count->fetch(PDO::FETCH_ASSOC)['total']);

            $total_no_leidos = $total;
        }

        // Formatear avisos
        $avisos = [];
        foreach ($avisos_raw as $aviso) {
            $fecha_mostrar = $aviso['fecha_publicacion'] ?? $aviso['fecha_envio'] ?? null;

            // Usar contenido, con fallback a mensaje si contenido es NULL/vacío
            $contenido = !empty($aviso['contenido']) ? $aviso['contenido'] : ($aviso['mensaje'] ?? '');
            $preview = mb_strlen($contenido) > 150 ? mb_substr($contenido, 0, 150) . '...' : $contenido;

            $cat = $aviso['categoria'] ?? 'institucional';
            $icono = $iconos[$cat] ?? "\xF0\x9F\x93\xA2";

            $avisos[] = [
                'id' => intval($aviso['id_aviso']),
                'titulo' => $aviso['titulo'],
                'preview' => $preview,
                'contenido' => $contenido,
                'categoria' => $cat,
                'prioridad' => $aviso['prioridad'] ?? 'normal',
                'icono' => $icono,
                'fecha' => $fecha_mostrar,
                'fecha_relativa' => $fecha_mostrar ? calcularFechaRelativa($fecha_mostrar) : '',
                'leido' => (bool)$aviso['leido'],
                'archivo_adjunto' => $aviso['archivo_adjunto'] ?? null
            ];
        }

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
                'total_paginas' => max(1, ceil($total / $limite))
            ]
        ]);
    }

} catch (PDOException $e) {
    error_log('SGA Error obtener_avisos: ' . $e->getMessage());
    echo json_encode([
        'success' => true,
        'avisos' => [],
        'estadisticas' => [
            'total' => 0,
            'no_leidos' => 0
        ],
        'paginacion' => [
            'pagina_actual' => 1,
            'por_pagina' => $limite,
            'total' => 0,
            'total_paginas' => 1
        ]
    ]);
} catch (Exception $e) {
    error_log('SGA Error obtener_avisos: ' . $e->getMessage());
    echo json_encode([
        'success' => true,
        'avisos' => [],
        'estadisticas' => [
            'total' => 0,
            'no_leidos' => 0
        ],
        'paginacion' => [
            'pagina_actual' => 1,
            'por_pagina' => $limite,
            'total' => 0,
            'total_paginas' => 1
        ]
    ]);
}
