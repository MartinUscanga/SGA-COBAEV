<?php
// procesar_qr.php
header('Content-Type: application/json');
date_default_timezone_set('America/Mexico_City');

require_once 'conexion.php';
require 'enviar_notificacion.php';

// --- FUNCIONES ---
function obtenerBitacora($pdo) {
    $sql = "SELECT a.nombre, a.apellido_paterno, a.apellido_materno, a.grupo, asis.matricula_alumno, asis.tipo, asis.hora 
            FROM asistencias asis
            JOIN alumnos a ON asis.matricula_alumno = a.matricula
            ORDER BY asis.id_asistencia DESC LIMIT 5";
    return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}

function obtenerTokenPadre($pdo, $matricula) {
    $stmt = $pdo->prepare("SELECT token_fcm FROM dispositivos_padres WHERE matricula_alumno = ? LIMIT 1");
    $stmt->execute([$matricula]);
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $resultado ? $resultado['token_fcm'] : null;
}

// --- PROCESAMIENTO ---
$qr_raw = $_POST['matricula'] ?? $_GET['matricula'] ?? '';
$matricula = substr(strtoupper(trim($qr_raw)), 0, 9);
$fecha_hoy = date('Y-m-d');
$hora_actual = date('H:i:s');

try {
    $stmt_alumno = $pdo->prepare("SELECT nombre, apellido_paterno, apellido_materno, grupo, turno FROM alumnos WHERE matricula = :matricula LIMIT 1");
    $stmt_alumno->execute(['matricula' => $matricula]);
    $alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);

    if (!$alumno) {
        echo json_encode(['status' => 'error', 'icon' => 'error', 'title' => 'No Registrado', 'message' => "La matrícula ($matricula) no pertenece a ningún alumno activo."]);
        exit;
    }

    $nombre_completo = $alumno['nombre'] . ' ' . $alumno['apellido_paterno']. ' ' .$alumno['apellido_materno'];
    $grupo_alumno = $alumno['grupo'] ?? '';
    $turno_alumno = $alumno['turno'] ?? '';
    $token = obtenerTokenPadre($pdo, $matricula);
    $hora_formato = date('h:i A', strtotime($hora_actual));

    // --- REVISAR ENTRADA ---
    $stmt_entrada = $pdo->prepare("SELECT id_asistencia FROM asistencias WHERE matricula_alumno = :matricula AND fecha = :fecha AND tipo = 'Entrada' LIMIT 1");
    $stmt_entrada->execute(['matricula' => $matricula, 'fecha' => $fecha_hoy]);
    
    if (!$stmt_entrada->fetch()) {
        $pdo->prepare("INSERT INTO asistencias (matricula_alumno, fecha, hora, tipo, sincronizado) VALUES (:matricula, :fecha, :hora, 'Entrada', 0)")
            ->execute(['matricula' => $matricula, 'fecha' => $fecha_hoy, 'hora' => $hora_actual]);

        if ($token) {
            $msg = "$nombre_completo ingresó al plantel a las $hora_formato.";
            $res = enviarAlertaFirebase($token, $msg);
            error_log("Firebase Entrada ($matricula): " . $res);
        }

        echo json_encode([
            'status' => 'success', 'icon' => 'success', 'title' => '¡Entrada Registrada!',
            'message' => "Bienvenido: $nombre_completo",
            'grupo' => $grupo_alumno,
            'turno' => $turno_alumno,
            'hora' => $hora_formato,
            'bitacora' => obtenerBitacora($pdo)
        ]);
        exit;
    }

    // --- REVISAR SALIDA ---
    $stmt_salida = $pdo->prepare("SELECT id_asistencia FROM asistencias WHERE matricula_alumno = :matricula AND fecha = :fecha AND tipo = 'Salida' LIMIT 1");
    $stmt_salida->execute(['matricula' => $matricula, 'fecha' => $fecha_hoy]);
    
    if (!$stmt_salida->fetch()) {
        $pdo->prepare("INSERT INTO asistencias (matricula_alumno, fecha, hora, tipo, sincronizado) VALUES (:matricula, :fecha, :hora, 'Salida', 0)")
            ->execute(['matricula' => $matricula, 'fecha' => $fecha_hoy, 'hora' => $hora_actual]);

        if ($token) {
            $msg = "$nombre_completo salió del plantel a las $hora_formato.";
            $res = enviarAlertaFirebase($token, $msg);
            error_log("Firebase Salida ($matricula): " . $res);
        }

        echo json_encode([
            'status' => 'success', 'icon' => 'info', 'title' => '¡Salida Registrada!',
            'message' => "Hasta luego: $nombre_completo",
            'grupo' => $grupo_alumno,
            'turno' => $turno_alumno,
            'hora' => $hora_formato,
            'bitacora' => obtenerBitacora($pdo)
        ]);
        exit;
    }

    // --- CICLO COMPLETADO ---
    echo json_encode([
        'status' => 'warning', 'icon' => 'warning', 'title' => 'Ciclo Completado',
        'message' => "El alumno $nombre_completo ya cuenta con registros de Entrada y Salida hoy.",
        'grupo' => $grupo_alumno,
        'bitacora' => obtenerBitacora($pdo)
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
?>
