<?php
/**
 * Guardar Token de Firebase Cloud Messaging (FCM)
 * Asocia el token del dispositivo con la(s) matricula(s) del alumno
 * 
 * PROTECCION CONTRA DUPLICADOS:
 * - Si ya existe el mismo token para la misma matricula -> no hace nada
 * - Si existe un token diferente para la matricula -> actualiza
 * - Si no existe ningun token -> inserta uno nuevo
 * 
 * MULTI-ALUMNO:
 * - Acepta {matriculas: [...], token} para registrar token en multiples matriculas
 * - Mantiene compatibilidad con {matricula, token} (formato anterior)
 */

header('Content-Type: application/json');
require_once 'iniciar_sesion_padres.php';

// Verificar que el tutor este autenticado
if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'No autenticado. Inicie sesion primero.'
    ]);
    exit;
}

// Incluir conexion a BD
require_once 'conexion.php';

try {
    // Leer datos JSON del request
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!isset($data['token'])) {
        throw new Exception('Falta parametro requerido (token)');
    }

    $fcm_token = trim($data['token']);

    // Validar que el token no este vacio
    if (empty($fcm_token) || strlen($fcm_token) < 50) {
        throw new Exception('Token FCM invalido o muy corto');
    }

    // Determinar las matriculas a procesar (multi-alumno o individual)
    $matriculas = [];
    
    if (isset($data['matriculas']) && is_array($data['matriculas']) && !empty($data['matriculas'])) {
        // Formato nuevo: array de matriculas
        $matriculas = $data['matriculas'];
    } elseif (isset($data['matricula']) && !empty($data['matricula'])) {
        // Formato anterior: una sola matricula (compatibilidad)
        $matriculas = [$data['matricula']];
    } else {
        throw new Exception('Faltan parametros requeridos (matricula o matriculas)');
    }

    // Validar que las matriculas pertenecen al tutor autenticado
    $alumnos_sesion = $_SESSION['alumnos'] ?? [];
    $matriculas_permitidas = array_map(function($a) { return $a['matricula']; }, $alumnos_sesion);

    // Si no hay array de alumnos en sesion (sesion antigua), usar alumno_matricula
    if (empty($matriculas_permitidas) && isset($_SESSION['alumno_matricula'])) {
        $matriculas_permitidas = [$_SESSION['alumno_matricula']];
    }

    $resultados = [];
    $errores = [];

    foreach ($matriculas as $matricula) {
        $matricula = trim($matricula);

        // Validar formato de matricula
        if (empty($matricula) || strlen($matricula) < 5) {
            $errores[] = "Matricula invalida: {$matricula}";
            continue;
        }

        // Validar que la matricula pertenece al tutor
        if (!empty($matriculas_permitidas) && !in_array($matricula, $matriculas_permitidas)) {
            $errores[] = "Matricula no autorizada: {$matricula}";
            continue;
        }

        // PROTECCION CONTRA DUPLICADOS:
        // Verificar si ya existe EXACTAMENTE el mismo token para esta matricula
        $check_sql = "SELECT id, token_fcm FROM dispositivos_padres 
                      WHERE matricula_alumno = :matricula 
                      ORDER BY fecha_registro DESC 
                      LIMIT 1";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute(['matricula' => $matricula]);
        $existing = $check_stmt->fetch();

        if ($existing && $existing['token_fcm'] === $fcm_token) {
            // Token IDENTICO ya existe -> no hacer nada
            $resultados[] = ['matricula' => $matricula, 'status' => 'sin_cambios'];
            continue;
        }

        if ($existing) {
            // Existe un registro pero con token DIFERENTE -> actualizar
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
            
            $resultados[] = ['matricula' => $matricula, 'status' => 'actualizado'];
        } else {
            // No existe ningun token -> insertar nuevo
            $insert_sql = "INSERT INTO dispositivos_padres (matricula_alumno, token_fcm, fecha_registro) 
                           VALUES (:matricula, :token, NOW())";
            $insert_stmt = $pdo->prepare($insert_sql);
            $insert_stmt->execute([
                'matricula' => $matricula,
                'token' => $fcm_token
            ]);
            
            $resultados[] = ['matricula' => $matricula, 'status' => 'registrado'];
        }
    }

    // Respuesta exitosa
    $status_general = 'procesado';
    if (count($resultados) === 1) {
        $status_general = $resultados[0]['status'];
    }

    $response = [
        'success' => true,
        'status' => $status_general,
        'message' => 'Token procesado para ' . count($resultados) . ' matricula(s)',
        'detalle' => $resultados
    ];

    if (!empty($errores)) {
        $response['errores'] = $errores;
    }

    echo json_encode($response);

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
