<?php
/**
 * Guardar Token de Firebase Cloud Messaging (FCM)
 * Asocia el token del dispositivo con la matrícula del alumno
 * 
 * PROTECCIÓN CONTRA DUPLICADOS:
 * - Si ya existe el mismo token para la misma matrícula → no hace nada
 * - Si existe un token diferente para la matrícula → actualiza
 * - Si no existe ningún token → inserta uno nuevo
 */

header('Content-Type: application/json');
session_start();

// Verificar que el tutor esté autenticado
if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'No autenticado. Inicie sesion primero.'
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
        throw new Exception('Faltan parametros requeridos (matricula y token)');
    }

    $matricula = trim($data['matricula']);
    $fcm_token = trim($data['token']);

    // Validar que la matrícula no esté vacía
    if (empty($matricula) || strlen($matricula) < 5) {
        throw new Exception('Matricula invalida');
    }

    // Validar que el token no esté vacío
    if (empty($fcm_token) || strlen($fcm_token) < 50) {
        throw new Exception('Token FCM invalido o muy corto');
    }

    // Verificar que la matrícula en sesión coincida con la recibida
    if ($_SESSION['alumno_matricula'] !== $matricula) {
        throw new Exception('La matricula no coincide con la sesion activa');
    }

    // PROTECCIÓN CONTRA DUPLICADOS:
    // Verificar si ya existe EXACTAMENTE el mismo token para esta matrícula
    $check_sql = "SELECT id, token_fcm FROM dispositivos_padres 
                  WHERE matricula_alumno = :matricula 
                  ORDER BY fecha_registro DESC 
                  LIMIT 1";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute(['matricula' => $matricula]);
    $existing = $check_stmt->fetch();

    if ($existing && $existing['token_fcm'] === $fcm_token) {
        // Token IDÉNTICO ya existe → no hacer nada
        echo json_encode([
            'success' => true,
            'status' => 'sin_cambios',
            'message' => 'Token ya registrado correctamente'
        ]);
        exit;
    }

    if ($existing) {
        // Existe un registro pero con token DIFERENTE → actualizar
        $update_sql = "UPDATE dispositivos_padres 
                      SET token_fcm = :token, 
                          fecha_registro = NOW()
                      WHERE matricula_alumno = :matricula";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([
            'token' => $fcm_token,
            'matricula' => $matricula
        ]);

        // Eliminar registros antiguos duplicados (dejar solo 1)
        $cleanup_sql = "DELETE FROM dispositivos_padres 
                       WHERE matricula_alumno = :matricula 
                       AND id != :id_mantener";
        $cleanup_stmt = $pdo->prepare($cleanup_sql);
        $cleanup_stmt->execute([
            'matricula' => $matricula,
            'id_mantener' => $existing['id']
        ]);
        
        $action = 'actualizado';
    } else {
        // No existe ningún token → insertar nuevo
        $insert_sql = "INSERT INTO dispositivos_padres (matricula_alumno, token_fcm, fecha_registro) 
                       VALUES (:matricula, :token, NOW())";
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
        'status' => $action,
        'message' => 'Token ' . $action . ' correctamente'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar en la base de datos'
    ]);
    error_log("Error guardar_token.php: " . $e->getMessage());

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
