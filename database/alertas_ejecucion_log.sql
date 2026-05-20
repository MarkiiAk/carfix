-- Tabla para controlar la ejecución diaria de generación de alertas
-- Evita duplicaciones y proporciona trazabilidad

CREATE TABLE IF NOT EXISTS alertas_ejecucion_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha_ejecucion DATE NOT NULL,
    alertas_generadas INT DEFAULT 0,
    tiempo_ejecucion_ms INT DEFAULT 0,
    usuario_id INT DEFAULT NULL,
    detalles TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices para optimizar consultas
    INDEX idx_fecha_ejecucion (fecha_ejecucion),
    INDEX idx_created_at (created_at),
    
    -- Evitar múltiples ejecuciones el mismo día
    UNIQUE KEY unique_daily_execution (fecha_ejecucion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comentario de la tabla
ALTER TABLE alertas_ejecucion_log COMMENT = 'Log de ejecuciones diarias del sistema automático de generación de alertas';