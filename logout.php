<?php
/**
 * Cierre de sesión seguro
 * Destruye la sesión y desactiva el token FCM
 */

session_start();

// Incluir conexión para desactivar el token FCM
require_once 'conexion.php';

try {
    // Si hay una sesión activa, desactivar el token FCM asociado
    if (isset($_SESSION['alumno_matricula'])) {
        $matricula = $_SESSION['alumno_matricula'];
        
        $sql = "UPDATE tokens_fcm SET activo = 0 WHERE matricula_alumno = :matricula";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['matricula' => $matricula]);
    }
} catch (PDOException $e) {
    // Silenciar error para no interrumpir el logout
    error_log("Error al desactivar token FCM: " . $e->getMessage());
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Redirigir al login con mensaje de éxito
header("Location: login_padres.php?logout=success");
exit;
?>
