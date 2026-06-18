<?php
/**
 * API Endpoint: Bitacora del Dia
 * SGA COBAEV - Checador v2
 * 
 * Devuelve JSON con los ultimos 20 registros de asistencia del dia actual.
 */

session_start();

// Verificar autenticacion
if (!isset($_SESSION['usuario_autenticado'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once 'conexion.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $sql = "SELECT a.nombre, a.apellido_paterno, a.apellido_materno, a.grupo,
                   asis.matricula_alumno, asis.tipo, asis.hora
            FROM asistencias asis
            JOIN alumnos a ON asis.matricula_alumno = a.matricula
            WHERE asis.fecha = CURDATE()
            ORDER BY asis.id_asistencia DESC
            LIMIT 20";

    $resultado = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($resultado);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar bitacora']);
}
?>
