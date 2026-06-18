<?php
/**
 * Configuracion centralizada de sesion para el Portal de Padres
 * SGA COBAEV
 * 
 * Este archivo garantiza que la sesion se configure de forma consistente
 * en todos los endpoints del portal de padres, evitando que el garbage collector
 * de PHP destruya la sesion prematuramente cuando se accede desde diferentes archivos.
 * 
 * Duracion: 30 dias (2592000 segundos)
 * Esto permite que la PWA mantenga la sesion activa al cerrar y reabrir la app.
 * 
 * USO: require_once 'iniciar_sesion_padres.php';
 *      (o require_once '../iniciar_sesion_padres.php'; desde subcarpetas)
 * 
 * NOTA: Este archivo NO verifica autenticacion. Cada archivo debe hacer su propia
 *       verificacion de $_SESSION['tutor_autenticado'] segun corresponda.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Cookie de sesion con duracion larga (30 dias) para PWA
    ini_set('session.cookie_lifetime', 2592000);
    ini_set('session.gc_maxlifetime', 2592000);
    session_set_cookie_params([
        'lifetime' => 2592000,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
