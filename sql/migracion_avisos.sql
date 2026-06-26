-- ========================================
-- MIGRACION: Sistema de Avisos Mejorado
-- SGA COBAEV
-- ========================================

-- 1. Agregar nuevas columnas a la tabla avisos
ALTER TABLE avisos
    ADD COLUMN contenido TEXT AFTER mensaje,
    ADD COLUMN categoria ENUM('institucional','academico','emergencia','pagos','cultural') DEFAULT 'institucional' AFTER contenido,
    ADD COLUMN prioridad ENUM('normal','importante','urgente') DEFAULT 'normal' AFTER categoria,
    ADD COLUMN archivo_adjunto VARCHAR(255) DEFAULT NULL AFTER prioridad,
    ADD COLUMN fecha_publicacion DATETIME DEFAULT NULL AFTER archivo_adjunto,
    ADD COLUMN activo TINYINT(1) DEFAULT 1 AFTER fecha_publicacion;

-- 2. Migrar datos existentes
UPDATE avisos SET contenido = mensaje WHERE contenido IS NULL;
UPDATE avisos SET fecha_publicacion = fecha_envio WHERE fecha_publicacion IS NULL;

-- 3. Agregar indices para optimizar consultas
ALTER TABLE avisos
    ADD INDEX idx_destinatario (destinatario),
    ADD INDEX idx_fecha_pub (fecha_publicacion),
    ADD INDEX idx_activo (activo),
    ADD INDEX idx_categoria (categoria);

-- 4. Crear tabla de avisos leidos (por tutor individual)
CREATE TABLE IF NOT EXISTS avisos_leidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_aviso INT NOT NULL,
    matricula_tutor VARCHAR(50) NOT NULL,
    fecha_lectura DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_aviso) REFERENCES avisos(id_aviso) ON DELETE CASCADE,
    UNIQUE KEY unique_read (id_aviso, matricula_tutor),
    INDEX idx_tutor (matricula_tutor),
    INDEX idx_fecha (fecha_lectura)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
