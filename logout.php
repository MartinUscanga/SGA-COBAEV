<?php
/**
 * Cierre de sesion seguro
 * Destruye la sesion y desactiva los tokens FCM de todos los alumnos vinculados
 */

session_start();

// Incluir conexion para desactivar los tokens FCM
require_once 'conexion.php';

try {
    // Multi-alumno: desactivar tokens para TODOS los alumnos vinculados
    if (isset($_SESSION['alumnos']) && is_array($_SESSION['alumnos'])) {
        foreach ($_SESSION['alumnos'] as $alumno) {
            $matricula = $alumno['matricula'] ?? '';
            if (!empty($matricula)) {
                $sql = "DELETE FROM dispositivos_padres WHERE matricula_alumno = :matricula";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['matricula' => $matricula]);
            }
        }
    } elseif (isset($_SESSION['alumno_matricula'])) {
        // Compatibilidad: sesion antigua con una sola matricula
        $matricula = $_SESSION['alumno_matricula'];
        $sql = "DELETE FROM dispositivos_padres WHERE matricula_alumno = :matricula";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['matricula' => $matricula]);
    }
} catch (PDOException $e) {
    // Silenciar error para no interrumpir el logout
    error_log("Error al desactivar token FCM en logout: " . $e->getMessage());
}

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

// Redirigir al login con mensaje de exito
header("Location: login_padres.php?logout=success");
exit;
?>
