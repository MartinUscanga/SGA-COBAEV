<?php
/**
 * Guardar Token de Firebase Cloud Messaging (FCM)
 * Asocia el token del dispositivo con la matrícula del alumno
 */

header('Content-Type: application/json');
session_start();

// Verificar que el tutor esté autenticado
if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'No autenticado. Inicie sesión primero.'
    ]);
    exit;
}

// Incluir conexión a BD
require_once 'conexion.php';

try {
    // Leer datos JSON del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // Validar datos recibidos
    if (!isset($data['matricula']) || !isset($data['token'])) {
        throw new Exception('Faltan parámetros requeridos (matricula y token)');
    }

    $matricula = strtoupper(trim($data['matricula']));
    $fcm_token = trim($data['token']);

    // Validar formato de matrícula
    if (!preg_match('/^[A-Z]\d{7}$/', $matricula)) {
        throw new Exception('Formato de matrícula inválido');
    }

    // Validar que el token no esté vacío
    if (empty($fcm_token) || strlen($fcm_token) < 50) {
        throw new Exception('Token FCM inválido o muy corto');
    }

    // Verificar que la matrícula en sesión coincida con la recibida
    if ($_SESSION['alumno_matricula'] !== $matricula) {
        throw new Exception('La matrícula no coincide con la sesión activa');
    }

    // Verificar si ya existe un token para esta matrícula
    $check_sql = "SELECT id, token FROM tokens_fcm WHERE matricula_alumno = :matricula LIMIT 1";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute(['matricula' => $matricula]);
    $existing = $check_stmt->fetch();

    if ($existing) {
        // Si el token es diferente, actualizar
        if ($existing['token'] !== $fcm_token) {
            $update_sql = "UPDATE tokens_fcm 
                          SET token = :token, 
                              fecha_actualizacion = NOW(),
                              activo = 1
                          WHERE matricula_alumno = :matricula";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([
                'token' => $fcm_token,
                'matricula' => $matricula
            ]);
            
            $action = 'actualizado';
        } else {
            // Token idéntico, solo actualizar fecha
            $update_sql = "UPDATE tokens_fcm 
                          SET fecha_actualizacion = NOW(),
                              activo = 1
                          WHERE matricula_alumno = :matricula";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute(['matricula' => $matricula]);
            
            $action = 'reconfirmado';
        }
    } else {
        // Insertar nuevo token
        $insert_sql = "INSERT INTO tokens_fcm (matricula_alumno, token, fecha_registro, fecha_actualizacion, activo) 
                       VALUES (:matricula, :token, NOW(), NOW(), 1)";
        $insert_stmt = $pdo->prepare($insert_sql);
        $insert_stmt->execute([
            'matricula' => $matricula,
            'token' => $fcm_token
        ]);
        
        $action = 'registrado';
    }

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Token ' . $action . ' correctamente',
        'data' => [
            'matricula' => $matricula,
            'token_preview' => substr($fcm_token, 0, 20) . '...',
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s')
        ],
        'debug_firebase' => [
            'session_valid' => isset($_SESSION['tutor_autenticado']),
            'matricula_match' => $_SESSION['alumno_matricula'] === $matricula,
            'token_length' => strlen($fcm_token)
        ]
    ]);

} catch (PDOException $e) {
    // Error de base de datos
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar en la base de datos',
        'error' => 'Database error',
        'debug_firebase' => [
            'error_type' => 'PDOException',
            'error_code' => $e->getCode()
        ]
    ]);

    // Log interno del error
    error_log("Error guardar_token.php: " . $e->getMessage());

} catch (Exception $e) {
    // Error de validación
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'debug_firebase' => [
            'error_type' => 'ValidationException'
        ]
    ]);
}
?>
