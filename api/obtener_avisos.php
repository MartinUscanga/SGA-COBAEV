<?php
/**
 * API - Obtener avisos no leidos para el tutor autenticado
 * SGA COBAEV
 * 
 * Devuelve JSON con avisos donde destinatario = 'todos' OR destinatario = matricula_alumno
 * OR destinatario = 'grupo:' + grupo del alumno, filtrados por avisos_leidos.
 * Requiere sesion de tutor autenticada
 */

// Configuración centralizada de sesiones
require_once '../includes/session_padres.php';
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticacion de tutor
if (!hayTutorAutenticado()) {
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
    // Obtener avisos no leidos para esta matricula, su grupo, o para todos
    // Usa avisos_leidos para determinar estado de lectura por usuario
    $stmt = $pdo->prepare("
        SELECT a.id_aviso, a.titulo, a.mensaje, a.fecha_envio, a.destinatario
        FROM avisos a
        INNER JOIN alumnos al ON al.matricula = :matricula
        LEFT JOIN avisos_leidos ar ON ar.id_aviso = a.id_aviso AND ar.matricula_alumno = :matricula2
        WHERE ar.id IS NULL
        AND (a.destinatario = 'todos' OR a.destinatario = :matricula3 OR a.destinatario = CONCAT('grupo:', al.grupo))
        ORDER BY a.fecha_envio DESC
        LIMIT 20
    ");
    $stmt->execute([
        'matricula' => $matricula,
        'matricula2' => $matricula,
        'matricula3' => $matricula
    ]);
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
