<?php
/**
 * Configuración centralizada de sesiones para Portal de Padres
 * SGA COBAEV
 * 
 * INCLUIR ESTE ARCHIVO al inicio de TODAS las páginas del portal de padres:
 * - login_padres.php
 * - padres.php
 * - perfil_tutor.php
 * - completar_perfil.php
 * - ayuda_notificaciones.php
 * - logout.php
 * - api/*.php
 */

// Configurar zona horaria
date_default_timezone_set('America/Mexico_City');

// Configurar sesión SOLO si no está ya iniciada
if (session_status() === PHP_SESSION_NONE) {
    // Duración: 30 días (compatibilidad con PWA)
    $lifetime = 2592000;
    
    // Configurar parámetros de cookie ANTES de session_start()
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    // Configurar garbage collector
    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.cookie_lifetime', $lifetime);
    
    // Nombre único de sesión (evita conflictos con admin)
    session_name('SGA_PADRES_SESSION');
    
    // Iniciar sesión
    session_start();
    
    // Regenerar ID periódicamente por seguridad
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 1800) { // Cada 30 min
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

/**
 * Función helper: Verificar autenticación de tutor
 * Redirige a login si no está autenticado
 */
function verificarSesionTutor() {
    if (!isset($_SESSION['tutor_autenticado']) || $_SESSION['tutor_autenticado'] !== true) {
        // Guardar URL solicitada para redirect después del login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: /login_padres.php");
        exit;
    }
    
    // Actualizar timestamp de última actividad
    $_SESSION['ultima_actividad'] = time();
}

/**
 * Función helper: Verificar si hay sesión activa (para APIs JSON)
 * Devuelve true/false sin redirigir
 */
function hayTutorAutenticado() {
    return isset($_SESSION['tutor_autenticado']) && $_SESSION['tutor_autenticado'] === true;
}

/**
 * Función helper: Cerrar sesión del tutor
 */
function cerrarSesionTutor() {
    // Limpiar variables de sesión
    $_SESSION = [];
    
    // Destruir cookie de sesión
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir sesión
    session_destroy();
}
?>
