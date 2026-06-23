-- Tabla para el sistema de alertas de servicios a 6 meses
-- Servicios que disparan alertas: Full Service (con/sin bujías), Cambio de Aceite, Verificación
CREATE TABLE alertas_servicio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    orden_id INT NOT NULL,
    cliente_id INT NOT NULL,
    vehiculo_id INT NOT NULL,
    
    -- Información del servicio que disparó la alerta
    fecha_ultimo_servicio TIMESTAMP NOT NULL,
    servicios_que_dispararon TEXT NOT NULL, -- JSON: ["Full Service con Bujías", "Cambio de Aceite"]
    todos_los_servicios TEXT, -- JSON: Todos los servicios de esa orden para contexto
    
    -- Control de la alerta
    estado ENUM('pendiente', 'leida') DEFAULT 'pendiente',
    fecha_generada TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_marcada_leida TIMESTAMP NULL,
    usuario_marco_leida INT NULL,
    
    -- Información adicional para mostrar en UI (se calcula en consultas)
    dias_desde_servicio INT NOT NULL, -- Se calculará y almacenará al generar la alerta
    
    -- Claves foráneas
    FOREIGN KEY (orden_id) REFERENCES ordenes_servicio(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (vehiculo_id) REFERENCES vehiculos(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_marco_leida) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    -- Índices para optimización
    INDEX idx_estado (estado),
    INDEX idx_fecha_generada (fecha_generada),
    INDEX idx_cliente (cliente_id),
    INDEX idx_orden (orden_id),
    INDEX idx_compuesto_alertas (estado, fecha_generada),
    
    -- Evitar alertas duplicadas para la misma orden
    UNIQUE KEY uk_orden_alerta (orden_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Sistema de alertas para servicios que requieren seguimiento a 6 meses';