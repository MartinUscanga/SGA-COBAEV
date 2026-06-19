-- ============================================================
-- Migracion v2 del sistema de avisos
-- ============================================================
-- Esta migracion crea la tabla avisos_leidos para el seguimiento
-- individual de lectura de avisos por alumno.
--
-- NOTA: El campo `leido` en la tabla avisos se mantiene por
-- compatibilidad hacia atras, pero el seguimiento individual
-- de lectura ahora se realiza a traves de avisos_leidos.
-- ============================================================

CREATE TABLE IF NOT EXISTS avisos_leidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_aviso INT NOT NULL,
    matricula_alumno VARCHAR(8) NOT NULL,
    fecha_lectura DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_aviso) REFERENCES avisos(id_aviso) ON DELETE CASCADE,
    FOREIGN KEY (matricula_alumno) REFERENCES alumnos(matricula) ON DELETE CASCADE,
    UNIQUE KEY unique_aviso_alumno (id_aviso, matricula_alumno),
    INDEX idx_matricula (matricula_alumno),
    INDEX idx_aviso (id_aviso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
