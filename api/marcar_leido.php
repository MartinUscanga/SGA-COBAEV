<?php
/**
 * API - Marcar aviso como leido
 * SGA COBAEV
 * 
 * Endpoint POST que recibe id_aviso y actualiza leido = 1
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

try {
    // Verificar que el aviso pertenece al usuario (destinatario = todos o su matricula)
    $stmt = $pdo->prepare("
        UPDATE avisos 
        SET leido = 1 
        WHERE id_aviso = :id_aviso 
        AND (destinatario = 'todos' OR destinatario = :matricula)
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
