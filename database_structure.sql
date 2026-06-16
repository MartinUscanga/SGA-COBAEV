-- ========================================
-- BASE DE DATOS: sga_cobaev
-- Sistema de Gestión de Asistencias COBAEV
-- ========================================

CREATE DATABASE IF NOT EXISTS sga_cobaev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sga_cobaev;

-- ========================================
-- TABLA: alumnos
-- ========================================
CREATE TABLE IF NOT EXISTS alumnos (
    matricula VARCHAR(8) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido_paterno VARCHAR(50) NOT NULL,
    apellido_materno VARCHAR(50),
    grupo VARCHAR(10),
    fecha_nacimiento DATE,
    email VARCHAR(100),
    telefono VARCHAR(15),
    direccion TEXT,
    activo TINYINT(1) DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_grupo (grupo),
    INDEX idx_nombre (nombre, apellido_paterno)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TABLA: tutores
-- ========================================
CREATE TABLE IF NOT EXISTS tutores (
    id_tutor INT AUTO_INCREMENT PRIMARY KEY,
    matricula_alumno VARCHAR(8) NOT NULL,
    nombre_tutor VARCHAR(100) NOT NULL,
    password_tutor VARCHAR(255) NOT NULL,
    telefono_tutor VARCHAR(15),
    email_tutor VARCHAR(100),
    relacion_parentesco ENUM('Padre', 'Madre', 'Tutor', 'Otro') DEFAULT 'Padre',
    activo TINYINT(1) DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (matricula_alumno) REFERENCES alumnos(matricula) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_tutor_alumno (matricula_alumno),
    INDEX idx_email (email_tutor)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TABLA: asistencias
-- ========================================
CREATE TABLE IF NOT EXISTS asistencias (
    id_asistencia INT AUTO_INCREMENT PRIMARY KEY,
    matricula_alumno VARCHAR(8) NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    tipo ENUM('Entrada', 'Salida') NOT NULL,
    dispositivo_id VARCHAR(50) DEFAULT NULL,
    metodo_registro ENUM('QR', 'Manual', 'Biométrico') DEFAULT 'QR',
    observaciones TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (matricula_alumno) REFERENCES alumnos(matricula) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_matricula_fecha (matricula_alumno, fecha DESC),
    INDEX idx_fecha_hora (fecha DESC, hora DESC),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TABLA: tokens_fcm
-- Almacena los tokens de Firebase Cloud Messaging
-- ========================================
CREATE TABLE IF NOT EXISTS tokens_fcm (
    id INT AUTO_INCREMENT PRIMARY KEY,
    matricula_alumno VARCHAR(8) NOT NULL,
    token TEXT NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (matricula_alumno) REFERENCES alumnos(matricula) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX idx_matricula_activo (matricula_alumno, activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- TABLA: logs_acceso (Opcional)
-- Registra los accesos al sistema
-- ========================================
CREATE TABLE IF NOT EXISTS logs_acceso (
    id_log INT AUTO_INCREMENT PRIMARY KEY,
    matricula_alumno VARCHAR(8),
    tipo_usuario ENUM('tutor', 'admin', 'alumno') NOT NULL,
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    INDEX idx_matricula_fecha (matricula_alumno, fecha_hora DESC),
    INDEX idx_tipo_fecha (tipo_usuario, fecha_hora DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================
-- DATOS DE PRUEBA
-- ========================================

-- Insertar alumnos de ejemplo
INSERT INTO alumnos (matricula, nombre, apellido_paterno, apellido_materno, grupo, fecha_nacimiento, email) VALUES
('B2024001', 'Juan', 'Pérez', 'González', '6-A', '2007-05-15', 'juan.perez@ejemplo.com'),
('B2024002', 'María', 'López', 'Martínez', '6-A', '2007-08-22', 'maria.lopez@ejemplo.com'),
('B2024003', 'Carlos', 'Ramírez', 'Hernández', '5-B', '2008-03-10', 'carlos.ramirez@ejemplo.com');

-- Insertar tutores (contraseñas en texto plano para pruebas)
-- En producción, usar password_hash('padre123', PASSWORD_DEFAULT)
INSERT INTO tutores (matricula_alumno, nombre_tutor, password_tutor, telefono_tutor, email_tutor, relacion_parentesco) VALUES
('B2024001', 'Roberto Pérez Sánchez', 'padre123', '2281234567', 'roberto.perez@ejemplo.com', 'Padre'),
('B2024002', 'Ana López Ruiz', 'madre123', '2289876543', 'ana.lopez@ejemplo.com', 'Madre'),
('B2024003', 'Luis Ramírez Castro', 'tutor123', '2285551234', 'luis.ramirez@ejemplo.com', 'Tutor');

-- Insertar asistencias de ejemplo (últimos 7 días)
INSERT INTO asistencias (matricula_alumno, fecha, hora, tipo, metodo_registro) VALUES
-- Hoy
('B2024001', CURDATE(), '07:45:00', 'Entrada', 'QR'),
('B2024001', CURDATE(), '14:30:00', 'Salida', 'QR'),
('B2024002', CURDATE(), '07:50:00', 'Entrada', 'QR'),

-- Ayer
('B2024001', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '07:40:00', 'Entrada', 'QR'),
('B2024001', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '14:25:00', 'Salida', 'QR'),
('B2024002', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '07:55:00', 'Entrada', 'QR'),
('B2024002', DATE_SUB(CURDATE(), INTERVAL 1 DAY), '14:35:00', 'Salida', 'QR'),

-- Hace 2 días
('B2024001', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '07:48:00', 'Entrada', 'QR'),
('B2024001', DATE_SUB(CURDATE(), INTERVAL 2 DAY), '14:28:00', 'Salida', 'QR'),

-- Hace 3 días
('B2024001', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '07:52:00', 'Entrada', 'QR'),
('B2024001', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '14:32:00', 'Salida', 'QR'),
('B2024002', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:00:00', 'Entrada', 'QR'),
('B2024002', DATE_SUB(CURDATE(), INTERVAL 3 DAY), '14:40:00', 'Salida', 'QR');

-- ========================================
-- VISTAS ÚTILES
-- ========================================

-- Vista: Último estatus de cada alumno
CREATE OR REPLACE VIEW v_ultimo_estatus_alumnos AS
SELECT 
    a.matricula,
    a.nombre,
    a.apellido_paterno,
    a.grupo,
    asist.fecha,
    asist.hora,
    asist.tipo,
    CASE 
        WHEN asist.tipo = 'Entrada' THEN 'En plantel'
        ELSE 'Fuera de plantel'
    END AS estatus_actual
FROM alumnos a
LEFT JOIN (
    SELECT matricula_alumno, fecha, hora, tipo
    FROM asistencias
    WHERE (matricula_alumno, fecha, hora) IN (
        SELECT matricula_alumno, fecha, MAX(hora)
        FROM asistencias
        GROUP BY matricula_alumno, fecha
    )
) asist ON a.matricula = asist.matricula_alumno
WHERE a.activo = 1;

-- Vista: Resumen de asistencias por alumno
CREATE OR REPLACE VIEW v_resumen_asistencias AS
SELECT 
    a.matricula,
    CONCAT(a.nombre, ' ', a.apellido_paterno, ' ', a.apellido_materno) AS nombre_completo,
    a.grupo,
    COUNT(DISTINCT asist.fecha) AS dias_asistidos,
    COUNT(CASE WHEN asist.tipo = 'Entrada' THEN 1 END) AS total_entradas,
    COUNT(CASE WHEN asist.tipo = 'Salida' THEN 1 END) AS total_salidas
FROM alumnos a
LEFT JOIN asistencias asist ON a.matricula = asist.matricula_alumno
WHERE a.activo = 1
GROUP BY a.matricula, a.nombre, a.apellido_paterno, a.apellido_materno, a.grupo;

-- ========================================
-- PROCEDIMIENTO ALMACENADO: Registrar asistencia
-- ========================================
DELIMITER //
CREATE PROCEDURE sp_registrar_asistencia(
    IN p_matricula VARCHAR(8),
    IN p_tipo ENUM('Entrada', 'Salida'),
    IN p_metodo ENUM('QR', 'Manual', 'Biométrico')
)
BEGIN
    DECLARE v_alumno_existe INT;
    
    -- Verificar si el alumno existe
    SELECT COUNT(*) INTO v_alumno_existe FROM alumnos WHERE matricula = p_matricula AND activo = 1;
    
    IF v_alumno_existe = 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Alumno no encontrado o inactivo';
    ELSE
        -- Insertar asistencia
        INSERT INTO asistencias (matricula_alumno, fecha, hora, tipo, metodo_registro)
        VALUES (p_matricula, CURDATE(), CURTIME(), p_tipo, p_metodo);
        
        SELECT 'Asistencia registrada correctamente' AS mensaje;
    END IF;
END //
DELIMITER ;

-- ========================================
-- INFORMACIÓN DEL SCRIPT
-- ========================================
SELECT 
    'Base de datos creada correctamente' AS estado,
    DATABASE() AS base_datos_actual,
    NOW() AS fecha_creacion;
