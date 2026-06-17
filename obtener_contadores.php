<?php
/**
 * API Endpoint: Contadores del Dia
 * SGA COBAEV - Checador v2
 * 
 * Devuelve JSON: {entradas: N, salidas: N, en_plantel: N}
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
    $hoy = date('Y-m-d');
    
    // Contar entradas del dia
    $stmtEntradas = $pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE fecha = :fecha AND tipo = 'Entrada'");
    $stmtEntradas->execute(['fecha' => $hoy]);
    $entradas = (int) $stmtEntradas->fetchColumn();
    
    // Contar salidas del dia
    $stmtSalidas = $pdo->prepare("SELECT COUNT(*) FROM asistencias WHERE fecha = :fecha AND tipo = 'Salida'");
    $stmtSalidas->execute(['fecha' => $hoy]);
    $salidas = (int) $stmtSalidas->fetchColumn();
    
    // Calcular alumnos en plantel (entradas - salidas del dia, minimo 0)
    $enPlantel = max(0, $entradas - $salidas);
    
    echo json_encode([
        'entradas' => $entradas,
        'salidas' => $salidas,
        'en_plantel' => $enPlantel
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al consultar contadores']);
}
?>
