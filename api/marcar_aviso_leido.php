<?php
/**
 * API - Marcar aviso como leido (sistema mejorado)
 * SGA COBAEV
 * 
 * Usa tabla avisos_leidos para tracking individual por tutor
 * Endpoint POST: recibe JSON { id_aviso: X }
 */

require_once '../includes/session_padres.php';
header('Content-Type: application/json; charset=utf-8');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

// Verificar autenticacion de tutor
if (!hayTutorAutenticado()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../conexion.php';

// Obtener id_aviso del body JSON
$input = json_decode(file_get_contents('php://input'), true);
$id_aviso = intval($input['id_aviso'] ?? 0);

if ($id_aviso <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'id_aviso invalido']);
    exit;
}

$matricula_tutor = $_SESSION['tutor_matricula'] ?? $_SESSION['alumno_matricula'] ?? '';
$matricula = $_SESSION['alumno_matricula'] ?? '';

if (empty($matricula_tutor)) {
    http_response_code(400);
    echo json_encode(['error' => 'Matricula de tutor no disponible']);
    exit;
}

try {
    // INSERT IGNORE para prevenir duplicados
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO avisos_leidos (id_aviso, matricula_tutor) 
        VALUES (:id_aviso, :matricula_tutor)
    ");
    $stmt->execute([
        'id_aviso' => $id_aviso,
        'matricula_tutor' => $matricula_tutor
    ]);

    // Obtener nuevo contador de no leidos
    $stmt_count = $pdo->prepare("
        SELECT COUNT(*) as no_leidos
        FROM avisos a
        LEFT JOIN avisos_leidos al ON al.id_aviso = a.id_aviso AND al.matricula_tutor = :matricula_tutor
        WHERE a.activo = 1 AND a.fecha_publicacion <= NOW()
        AND (a.destinatario = 'todos' OR a.destinatario = :matricula)
        AND al.id IS NULL
    ");
    $stmt_count->execute([
        'matricula_tutor' => $matricula_tutor,
        'matricula' => $matricula
    ]);
    $no_leidos = intval($stmt_count->fetch()['no_leidos']);

    echo json_encode([
        'success' => true,
        'no_leidos' => $no_leidos
    ]);

} catch (PDOException $e) {
    error_log('SGA Error marcar_aviso_leido: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor']);
}
