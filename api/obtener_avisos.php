<?php
/**
 * API - Obtener avisos no leidos para el tutor autenticado
 * SGA COBAEV
 * 
 * Devuelve JSON con avisos donde destinatario = 'todos' OR destinatario = matricula_alumno
 * Requiere sesion de tutor autenticada
 */
require_once '../iniciar_sesion_padres.php';
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticacion de tutor
if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado', 'avisos' => []]);
    exit;
}

require_once '../conexion.php';

$matricula = $_SESSION['alumno_matricula'] ?? '';

if (empty($matricula)) {
    http_response_code(400);
    echo json_encode(['error' => 'Matricula no disponible', 'avisos' => []]);
    exit;
}

try {
    // Obtener avisos no leidos para esta matricula o para todos
    $stmt = $pdo->prepare("
        SELECT id_aviso, titulo, mensaje, fecha_envio, destinatario
        FROM avisos
        WHERE leido = 0 
        AND (destinatario = 'todos' OR destinatario = :matricula)
        ORDER BY fecha_envio DESC
        LIMIT 20
    ");
    $stmt->execute(['matricula' => $matricula]);
    $avisos = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'avisos' => $avisos,
        'total' => count($avisos)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor', 'avisos' => []]);
}
