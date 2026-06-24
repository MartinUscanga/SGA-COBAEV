<?php
/**
 * Enviar Notificación Push via Firebase Cloud Messaging (FCM)
 * Utiliza la API v1 de FCM con autenticación OAuth2
 */

require 'vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;

/**
 * Envía una notificación push a un tutor específico
 * 
 * @param string $token_padre Token FCM del dispositivo del tutor
 * @param string $mensaje_texto Cuerpo del mensaje a enviar
 * @param string $titulo_notificacion Titulo de la notificacion (default: 'Alerta de Acceso COBAEV')
 * @param string $url_destino URL destino al hacer click en la notificacion (default: '/padres.php')
 * @return string JSON con resultado de la operación
 */
function enviarAlertaFirebase($token_padre, $mensaje_texto, $titulo_notificacion = 'Alerta de Acceso COBAEV', $url_destino = '/padres.php') {
    // 1. Obtener credenciales del archivo service-account.json
    $rutaCredenciales = __DIR__ . '/config/service-account.json';
    
    if (!file_exists($rutaCredenciales)) {
        return json_encode([
            'status' => 'error',
            'message' => 'Archivo de credenciales no encontrado',
            'path' => $rutaCredenciales
        ]);
    }
    
    $jsonKey = json_decode(file_get_contents($rutaCredenciales), true);
    
    if (!$jsonKey) {
        return json_encode([
            'status' => 'error',
            'message' => 'Error al leer credenciales de Firebase'
        ]);
    }
    
    // 2. Configurar autenticación OAuth2
    $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
    $credentials = new ServiceAccountCredentials($scopes, $jsonKey);
    
    try {
        $accessToken = $credentials->fetchAuthToken(HttpHandlerFactory::build())['access_token'];
    } catch (Exception $e) {
        return json_encode([
            'status' => 'error',
            'message' => 'Error al obtener token de acceso',
            'error' => $e->getMessage()
        ]);
    }

    $projectId = $jsonKey['project_id'];
    $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

    // 3. Preparar payload - SOLO DATA (sin notification)
    // Firebase no muestra nada automáticamente con data-only
    // El Service Worker (sw.js) se encarga de mostrar la notificación
    $mensaje = [
        'message' => [
            'token' => $token_padre,
            'data' => [
                'title' => $titulo_notificacion,
                'body' => $mensaje_texto,
                'tipo' => 'asistencia',
                'timestamp' => date('Y-m-d H:i:s'),
                'url' => $url_destino,
                'icon' => '/logo.png'
            ],
            'android' => [
                'priority' => 'high'
            ],
            'webpush' => [
                'headers' => [
                    'Urgency' => 'high',
                    'TTL' => '0'
                ]
            ],
            'fcm_options' => [
                'analytics_label' => 'sga_push'
            ]
        ]
    ];

    // 4. Ejecutar petición cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mensaje));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $respuesta = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // 5. Procesar respuesta
    if ($httpCode !== 200) {
        return json_encode([
            'status' => 'error',
            'code' => $httpCode,
            'message' => 'Error al enviar notificación',
            'response' => $respuesta,
            'curl_error' => $error
        ]);
    }

    return json_encode([
        'status' => 'success',
        'code' => $httpCode,
        'message' => 'Notificación enviada correctamente',
        'response' => json_decode($respuesta, true)
    ]);
}

/**
 * Notificar a los tutores cuando se registra una asistencia
 * 
 * @param string $matricula_alumno Matrícula del alumno
 * @param string $tipo Tipo de movimiento ('Entrada' o 'Salida')
 * @param PDO $pdo Conexión PDO a la base de datos
 * @return array Resultado de las notificaciones enviadas
 */
function notificarAsistencia($matricula_alumno, $tipo, $pdo) {
    $resultados = [];
    
    try {
        // 1. Obtener información del alumno
        $sql_alumno = "SELECT nombre, apellido_paterno, apellido_materno 
                       FROM alumnos 
                       WHERE matricula = :matricula";
        $stmt_alumno = $pdo->prepare($sql_alumno);
        $stmt_alumno->execute(['matricula' => $matricula_alumno]);
        $alumno = $stmt_alumno->fetch();
        
        if (!$alumno) {
            return ['error' => 'Alumno no encontrado'];
        }
        
        $nombre_completo = trim($alumno['nombre'] . ' ' . $alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno']);
        
        // 2. Obtener tokens FCM activos del tutor
        $sql_tokens = "SELECT token 
                       FROM tokens_fcm 
                       WHERE matricula_alumno = :matricula 
                       AND activo = 1";
        $stmt_tokens = $pdo->prepare($sql_tokens);
        $stmt_tokens->execute(['matricula' => $matricula_alumno]);
        $tokens = $stmt_tokens->fetchAll();
        
        if (empty($tokens)) {
            return ['warning' => 'No hay tokens activos para este alumno'];
        }
        
        // 3. Preparar mensaje personalizado
        $hora_actual = date('h:i A');
        $fecha_actual = date('d/m/Y');
        
        if ($tipo === 'Entrada') {
            $mensaje = "🎒 {$nombre_completo} ha INGRESADO al plantel a las {$hora_actual} del {$fecha_actual}";
        } else {
            $mensaje = "🚪 {$nombre_completo} ha SALIDO del plantel a las {$hora_actual} del {$fecha_actual}";
        }
        
        // 4. Enviar notificación a cada token
        foreach ($tokens as $token_row) {
            $token = $token_row['token'];
            $resultado = enviarAlertaFirebase($token, $mensaje);
            $resultados[] = [
                'token_preview' => substr($token, 0, 20) . '...',
                'resultado' => json_decode($resultado, true)
            ];
        }
        
        return [
            'success' => true,
            'alumno' => $nombre_completo,
            'tipo' => $tipo,
            'notificaciones_enviadas' => count($tokens),
            'detalles' => $resultados
        ];
        
    } catch (PDOException $e) {
        return [
            'error' => 'Error de base de datos',
            'message' => $e->getMessage()
        ];
    }
}

// ========================================
// USO DIRECTO (si se llama como endpoint)
// ========================================
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: application/json');
    
    // Requerir autenticación básica o token de API
    // Por seguridad, esto solo debería ser accesible desde el servidor interno
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['matricula']) || !isset($input['tipo'])) {
            echo json_encode([
                'error' => 'Parámetros requeridos: matricula, tipo'
            ]);
            exit;
        }
        
        require_once 'conexion.php';
        $resultado = notificarAsistencia($input['matricula'], $input['tipo'], $pdo);
        echo json_encode($resultado);
    } else {
        echo json_encode([
            'error' => 'Método no permitido. Use POST',
            'usage' => [
                'method' => 'POST',
                'body' => [
                    'matricula' => 'B2024001',
                    'tipo' => 'Entrada o Salida'
                ]
            ]
        ]);
    }
}
?>
