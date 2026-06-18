<?php
/**
 * Cierre de sesion seguro
 * Destruye la sesion pero NO borra los tokens FCM
 * (los tokens son del dispositivo, no de la sesión)
 */

require_once 'iniciar_sesion_padres.php';

// NO borrar tokens FCM - son del dispositivo, no de la sesión
// Si el padre vuelve a iniciar sesión, app.js re-registra el token

// Destruir todas las variables de sesion
$_SESSION = array();

// Destruir la cookie de sesion si existe
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

// Destruir la sesion
session_destroy();

// Redirigir al login
header("Location: login_padres.php?logout=success");
exit;
