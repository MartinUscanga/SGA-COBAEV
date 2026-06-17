<?php
/**
 * API - Obtener asistencias del alumno activo
 * Devuelve JSON con asistencias, estado actual y resumen del día
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../conexion.php';

$matricula = $_SESSION['alumno_matricula'] ?? '';

if (empty($matricula)) {
    http_response_code(400);
    echo json_encode(['error' => 'Matricula no disponible']);
    exit;
}

try {
    // Obtener últimas 50 asistencias
    $sql = "SELECT id_asistencia, fecha, hora, tipo, registrado_en 
            FROM asistencias 
            WHERE matricula_alumno = :matricula 
            ORDER BY fecha DESC, hora DESC
            LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['matricula' => $matricula]);
    $asistencias = $stmt->fetchAll();

    // Último movimiento
    $ultimo = !empty($asistencias) ? $asistencias[0] : null;
    
    // Estado actual
    $es_plantel = false;
    if ($ultimo && $ultimo['tipo'] === 'Entrada' && $ultimo['fecha'] === date('Y-m-d')) {
        $es_plantel = true;
    }

    // Resumen del día
    $hoy = date('Y-m-d');
    $entradas_hoy = 0;
    $salidas_hoy = 0;
    foreach ($asistencias as $reg) {
        if ($reg['fecha'] === $hoy) {
            if ($reg['tipo'] === 'Entrada') $entradas_hoy++;
            if ($reg['tipo'] === 'Salida') $salidas_hoy++;
        }
    }

    echo json_encode([
        'success' => true,
        'es_plantel' => $es_plantel,
        'ultimo_movimiento' => $ultimo,
        'resumen_hoy' => ['entradas' => $entradas_hoy, 'salidas' => $salidas_hoy],
        'asistencias' => $asistencias
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error del servidor']);
}
