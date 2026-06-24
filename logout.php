<?php
/**
 * Cierre de sesion seguro
 * Destruye la sesion pero NO borra los tokens FCM
 * (los tokens son del dispositivo, no de la sesión)
 */

// Configuración centralizada de sesiones
require_once 'includes/session_padres.php';

// NO borrar tokens FCM - son del dispositivo, no de la sesión
// Si el padre vuelve a iniciar sesión, app.js re-registra el token

// Cerrar sesión utilizando la función helper
cerrarSesionTutor();

// Destruir la sesion
session_destroy();

// Redirigir al login
header("Location: login_padres.php?logout=success");
exit;
