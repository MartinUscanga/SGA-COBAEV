<?php
/**
 * API - Marcar aviso como leido
 * SGA COBAEV
 * 
 * Endpoint POST que recibe id_aviso e inserta en avisos_leidos.
 * Requiere sesion de tutor autenticada
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Metodo no permitido']);
    exit;
}

// Verificar autenticacion de tutor
if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../conexion.php';

// Obtener id_aviso del body (JSON o form data)
$input = json_decode(file_get_contents('php://input'), true);
$id_aviso = intval($input['id_aviso'] ?? $_POST['id_aviso'] ?? 0);

if ($id_aviso <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'id_aviso invalido']);
    exit;
}

$matricula = $_SESSION['alumno_matricula'] ?? '';

if (empty($matricula)) {
    http_response_code(400);
    echo json_encode(['error' => 'Matricula no disponible']);
    exit;
}

try {
    // Verificar que el aviso pertenece al usuario (destinatario = todos, su matricula, o su grupo)
    $stmt_check = $pdo->prepare("
        SELECT a.id_aviso
        FROM avisos a
        INNER JOIN alumnos al ON al.matricula = :matricula
        WHERE a.id_aviso = :id_aviso
        AND (a.destinatario = 'todos' OR a.destinatario = :matricula2 OR a.destinatario = CONCAT('grupo:', al.grupo))
    ");
    $stmt_check->execute([
        'matricula' => $matricula,
        'id_aviso' => $id_aviso,
        'matricula2' => $matricula
    ]);

    if (!$stmt_check->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'No tienes acceso a este aviso']);
        exit;
    }

    // Insertar en avisos_leidos (IGNORE maneja duplicados)
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO avisos_leidos (id_aviso, matricula_alumno)
        VALUES (:id_aviso, :matricula)
    ");
    $stmt->execute([
        'id_aviso' => $id_aviso,
        'matricula' => $matricula
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Aviso marcado como leido'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor']);
}
