<?php
/**
 * PRUEBA SIMPLE DE FCM
 * Este archivo prueba si Firebase está configurado correctamente
 */

require 'vendor/autoload.php';

use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Auth\HttpHandler\HttpHandlerFactory;

// Verificar que exista el archivo de credenciales
$rutaCredenciales = __DIR__ . '/config/service-account.json';

if (!file_exists($rutaCredenciales)) {
    die("❌ ERROR: No existe el archivo config/service-account.json");
}

echo "<h2>🧪 Prueba de Firebase Cloud Messaging</h2>";

// 1. Leer credenciales
$jsonKey = json_decode(file_get_contents($rutaCredenciales), true);
echo "<p>✅ Archivo de credenciales encontrado</p>";
echo "<p>📁 Project ID: " . $jsonKey['project_id'] . "</p>";

// 2. Obtener token de Firebase
try {
    $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
    $credentials = new ServiceAccountCredentials($scopes, $jsonKey);
    $accessToken = $credentials->fetchAuthToken(HttpHandlerFactory::build())['access_token'];
    echo "<p>✅ Token de acceso obtenido correctamente</p>";
} catch (Exception $e) {
    die("<p>❌ ERROR al obtener token: " . $e->getMessage() . "</p>");
}

// 3. Verificar si hay tokens en la BD
require_once 'conexion.php';

$sql = "SELECT matricula_alumno, LEFT(token, 30) as token_preview FROM tokens_fcm WHERE activo = 1";
$stmt = $pdo->query($sql);
$tokens = $stmt->fetchAll();

echo "<h3>📱 Tokens FCM en la base de datos:</h3>";
if (empty($tokens)) {
    echo "<p>⚠️ No hay tokens guardados. Inicia sesión en padres.php primero.</p>";
} else {
    echo "<ul>";
    foreach ($tokens as $token) {
        echo "<li><strong>" . $token['matricula_alumno'] . ":</strong> " . $token['token_preview'] . "...</li>";
    }
    echo "</ul>";
}

// 4. Formulario para enviar notificación de prueba
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test FCM - COBAEV</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .form-group { margin: 15px 0; }
        label { display: block; font-weight: bold; margin-bottom: 5px; }
        input, select, textarea { width: 100%; padding: 8px; font-size: 14px; }
        button { background: #5c1931; color: white; padding: 10px 20px; border: none; cursor: pointer; font-size: 16px; }
        button:hover { opacity: 0.9; }
        .result { margin-top: 20px; padding: 15px; background: #f0f0f0; border-radius: 5px; }
    </style>
</head>
<body>

<h3>🚀 Enviar Notificación de Prueba</h3>

<form method="POST">
    <div class="form-group">
        <label>Matrícula del Alumno:</label>
        <select name="matricula" required>
            <option value="">Seleccione...</option>
            <?php foreach ($tokens as $t): ?>
                <option value="<?= $t['matricula_alumno'] ?>"><?= $t['matricula_alumno'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Título de la Notificación:</label>
        <input type="text" name="titulo" value="🧪 Prueba de Notificación" required>
    </div>

    <div class="form-group">
        <label>Mensaje:</label>
        <textarea name="mensaje" rows="3" required>Esta es una prueba del sistema de notificaciones push de COBAEV.</textarea>
    </div>

    <button type="submit" name="enviar">Enviar Notificación 🔔</button>
</form>

<?php
if (isset($_POST['enviar'])) {
    echo "<div class='result'>";
    echo "<h3>📡 Resultado del Envío:</h3>";
    
    $matricula_test = $_POST['matricula'];
    $titulo_test = $_POST['titulo'];
    $mensaje_test = $_POST['mensaje'];
    
    // Obtener el token completo
    $sql = "SELECT token FROM tokens_fcm WHERE matricula_alumno = :mat AND activo = 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['mat' => $matricula_test]);
    $tokenRow = $stmt->fetch();
    
    if (!$tokenRow) {
        echo "<p>❌ No se encontró token para la matrícula: $matricula_test</p>";
    } else {
        $token_completo = $tokenRow['token'];
        
        // Preparar mensaje FCM
        $url = "https://fcm.googleapis.com/v1/projects/{$jsonKey['project_id']}/messages:send";
        
        $mensaje_fcm = [
            'message' => [
                'token' => $token_completo,
                'notification' => [
                    'title' => $titulo_test,
                    'body' => $mensaje_test
                ],
                'webpush' => [
                    'notification' => [
                        'icon' => '/logo.png',
                        'requireInteraction' => true
                    ]
                ]
            ]
        ];
        
        // Enviar via cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($mensaje_fcm));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $respuesta = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
        
        if ($httpCode === 200) {
            echo "<p style='color: green;'>✅ <strong>¡Notificación enviada exitosamente!</strong></p>";
            echo "<p>Deberías ver la notificación en unos segundos.</p>";
        } else {
            echo "<p style='color: red;'>❌ <strong>Error al enviar:</strong></p>";
            echo "<pre>" . htmlspecialchars($respuesta) . "</pre>";
        }
    }
    
    echo "</div>";
}
?>

<hr>
<p><a href="padres.php">← Volver al Portal de Padres</a></p>

</body>
</html>
