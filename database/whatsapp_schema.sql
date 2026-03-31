-- =====================================================
-- SISTEMA WHATSAPP - SAG GARAGE
-- Esquema de Base de Datos
-- Fecha: 30/03/2026
-- =====================================================

-- =====================================================
-- MODIFICACIONES A TABLA EXISTENTE (SEGURAS)
-- =====================================================

-- Agregar campos WhatsApp a alertas_servicio existente SOLO SI NO EXISTEN
-- Verificar si las columnas ya existen antes de agregarlas

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name='alertas_servicio' AND column_name='whatsapp_estado') = 0,
    'ALTER TABLE alertas_servicio ADD COLUMN whatsapp_estado ENUM(''pendiente'', ''programado'', ''enviado'', ''entregado'', ''leido'', ''error'', ''omitido'') DEFAULT ''pendiente'' COMMENT ''Estado del envío de WhatsApp''',
    'SELECT ''Columna whatsapp_estado ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name='alertas_servicio' AND column_name='whatsapp_fecha_programada') = 0,
    'ALTER TABLE alertas_servicio ADD COLUMN whatsapp_fecha_programada TIMESTAMP NULL COMMENT ''Cuándo se programó el envío''',
    'SELECT ''Columna whatsapp_fecha_programada ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name='alertas_servicio' AND column_name='whatsapp_fecha_enviada') = 0,
    'ALTER TABLE alertas_servicio ADD COLUMN whatsapp_fecha_enviada TIMESTAMP NULL COMMENT ''Cuándo se envió realmente''',
    'SELECT ''Columna whatsapp_fecha_enviada ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name='alertas_servicio' AND column_name='whatsapp_template_id') = 0,
    'ALTER TABLE alertas_servicio ADD COLUMN whatsapp_template_id INT NULL COMMENT ''ID del template usado''',
    'SELECT ''Columna whatsapp_template_id ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name='alertas_servicio' AND column_name='whatsapp_mensaje_id') = 0,
    'ALTER TABLE alertas_servicio ADD COLUMN whatsapp_mensaje_id VARCHAR(100) NULL COMMENT ''ID del mensaje en WhatsApp API''',
    'SELECT ''Columna whatsapp_mensaje_id ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name='alertas_servicio' AND column_name='whatsapp_error_mensaje') = 0,
    'ALTER TABLE alertas_servicio ADD COLUMN whatsapp_error_mensaje TEXT NULL COMMENT ''Descripción del error si falla''',
    'SELECT ''Columna whatsapp_error_mensaje ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name='alertas_servicio' AND column_name='whatsapp_intentos_envio') = 0,
    'ALTER TABLE alertas_servicio ADD COLUMN whatsapp_intentos_envio INT DEFAULT 0 COMMENT ''Número de intentos de envío''',
    'SELECT ''Columna whatsapp_intentos_envio ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name='alertas_servicio' AND column_name='whatsapp_ultimo_intento') = 0,
    'ALTER TABLE alertas_servicio ADD COLUMN whatsapp_ultimo_intento TIMESTAMP NULL COMMENT ''Fecha del último intento''',
    'SELECT ''Columna whatsapp_ultimo_intento ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar índices solo si no existen
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='alertas_servicio' AND index_name='idx_whatsapp_estado') = 0,
    'ALTER TABLE alertas_servicio ADD INDEX idx_whatsapp_estado (whatsapp_estado)',
    'SELECT ''Índice idx_whatsapp_estado ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='alertas_servicio' AND index_name='idx_whatsapp_programada') = 0,
    'ALTER TABLE alertas_servicio ADD INDEX idx_whatsapp_programada (whatsapp_fecha_programada)',
    'SELECT ''Índice idx_whatsapp_programada ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='alertas_servicio' AND index_name='idx_whatsapp_pendiente_fecha') = 0,
    'ALTER TABLE alertas_servicio ADD INDEX idx_whatsapp_pendiente_fecha (whatsapp_estado, whatsapp_fecha_programada)',
    'SELECT ''Índice idx_whatsapp_pendiente_fecha ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- TABLA: whatsapp_templates
-- Plantillas de mensajes personalizables
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL COMMENT 'Nombre identificador del template',
    contenido TEXT NOT NULL COMMENT 'Texto del mensaje con variables {{}}',
    tipo_servicio VARCHAR(100) DEFAULT 'general' COMMENT 'Tipo de servicio asociado',
    variables JSON DEFAULT NULL COMMENT 'Array de variables disponibles',
    activo BOOLEAN DEFAULT TRUE COMMENT 'Si el template está activo',
    uso_count INT DEFAULT 0 COMMENT 'Cuántas veces se ha usado',
    conversion_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Tasa de conversión (%)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Plantillas de mensajes WhatsApp personalizables';

-- Índices para whatsapp_templates (solo si no existen)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_templates' AND index_name='idx_tipo_servicio') = 0,
    'ALTER TABLE whatsapp_templates ADD INDEX idx_tipo_servicio (tipo_servicio)',
    'SELECT ''Índice idx_tipo_servicio ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_templates' AND index_name='idx_activo') = 0,
    'ALTER TABLE whatsapp_templates ADD INDEX idx_activo (activo)',
    'SELECT ''Índice idx_activo ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_templates' AND index_name='idx_conversion') = 0,
    'ALTER TABLE whatsapp_templates ADD INDEX idx_conversion (conversion_rate DESC)',
    'SELECT ''Índice idx_conversion ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- TABLA: whatsapp_config
-- Configuraciones del sistema WhatsApp
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    clave VARCHAR(100) UNIQUE NOT NULL COMMENT 'Clave de configuración',
    valor TEXT NOT NULL COMMENT 'Valor de la configuración',
    descripcion TEXT COMMENT 'Descripción de qué hace esta config',
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string' COMMENT 'Tipo de dato',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Configuraciones del sistema WhatsApp';

-- Índices para whatsapp_config (solo si no existen)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_config' AND index_name='idx_clave') = 0,
    'ALTER TABLE whatsapp_config ADD INDEX idx_clave (clave)',
    'SELECT ''Índice idx_clave ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- TABLA: whatsapp_blacklist
-- Números bloqueados para no enviar mensajes
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_blacklist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    telefono VARCHAR(20) NOT NULL UNIQUE COMMENT 'Número de teléfono (formato internacional)',
    cliente_id INT NULL COMMENT 'ID del cliente si está registrado',
    motivo VARCHAR(255) NOT NULL COMMENT 'Razón del bloqueo',
    fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    agregado_por_id INT NULL COMMENT 'Usuario que agregó a la blacklist',
    activo BOOLEAN DEFAULT TRUE COMMENT 'Si el bloqueo está activo',
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (agregado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Lista negra de números para WhatsApp';

-- Índices para whatsapp_blacklist (solo si no existen)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_blacklist' AND index_name='idx_telefono') = 0,
    'ALTER TABLE whatsapp_blacklist ADD INDEX idx_telefono (telefono)',
    'SELECT ''Índice idx_telefono ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_blacklist' AND index_name='idx_cliente') = 0,
    'ALTER TABLE whatsapp_blacklist ADD INDEX idx_cliente (cliente_id)',
    'SELECT ''Índice idx_cliente ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_blacklist' AND index_name='idx_activo') = 0,
    'ALTER TABLE whatsapp_blacklist ADD INDEX idx_activo (activo)',
    'SELECT ''Índice idx_activo ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- TABLA: whatsapp_logs
-- Log completo de todos los mensajes enviados
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alerta_id INT NOT NULL COMMENT 'ID de la alerta que generó el mensaje',
    cliente_id INT NOT NULL COMMENT 'ID del cliente destinatario',
    telefono VARCHAR(20) NOT NULL COMMENT 'Número de teléfono usado',
    template_id INT NULL COMMENT 'Template usado para el mensaje',
    mensaje TEXT NOT NULL COMMENT 'Texto completo del mensaje enviado',
    estado ENUM('enviando', 'enviado', 'entregado', 'leido', 'error') NOT NULL COMMENT 'Estado actual del mensaje',
    whatsapp_message_id VARCHAR(100) NULL COMMENT 'ID del mensaje en WhatsApp API',
    error_codigo VARCHAR(50) NULL COMMENT 'Código de error si falla',
    error_descripcion TEXT NULL COMMENT 'Descripción del error',
    metadata JSON NULL COMMENT 'Metadatos adicionales del envío',
    costo_mensaje DECIMAL(8,4) DEFAULT 0.5614 COMMENT 'Costo del mensaje en MXN',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (alerta_id) REFERENCES alertas_servicio(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Log completo de mensajes WhatsApp enviados';

-- Índices para whatsapp_logs (solo si no existen)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_logs' AND index_name='idx_alerta') = 0,
    'ALTER TABLE whatsapp_logs ADD INDEX idx_alerta (alerta_id)',
    'SELECT ''Índice idx_alerta ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_logs' AND index_name='idx_cliente') = 0,
    'ALTER TABLE whatsapp_logs ADD INDEX idx_cliente (cliente_id)',
    'SELECT ''Índice idx_cliente ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_logs' AND index_name='idx_estado') = 0,
    'ALTER TABLE whatsapp_logs ADD INDEX idx_estado (estado)',
    'SELECT ''Índice idx_estado ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_logs' AND index_name='idx_fecha_creacion') = 0,
    'ALTER TABLE whatsapp_logs ADD INDEX idx_fecha_creacion (created_at)',
    'SELECT ''Índice idx_fecha_creacion ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_logs' AND index_name='idx_telefono') = 0,
    'ALTER TABLE whatsapp_logs ADD INDEX idx_telefono (telefono)',
    'SELECT ''Índice idx_telefono ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_logs' AND index_name='idx_whatsapp_message_id') = 0,
    'ALTER TABLE whatsapp_logs ADD INDEX idx_whatsapp_message_id (whatsapp_message_id)',
    'SELECT ''Índice idx_whatsapp_message_id ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- TABLA: whatsapp_estadisticas_diarias
-- Estadísticas agregadas por día para reporting
-- =====================================================

CREATE TABLE IF NOT EXISTS whatsapp_estadisticas_diarias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    fecha DATE NOT NULL UNIQUE COMMENT 'Fecha de las estadísticas',
    mensajes_programados INT DEFAULT 0 COMMENT 'Mensajes que se programaron enviar',
    mensajes_enviados INT DEFAULT 0 COMMENT 'Mensajes enviados exitosamente',
    mensajes_entregados INT DEFAULT 0 COMMENT 'Mensajes confirmados como entregados',
    mensajes_leidos INT DEFAULT 0 COMMENT 'Mensajes leídos por clientes',
    mensajes_error INT DEFAULT 0 COMMENT 'Mensajes que fallaron',
    costo_total DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Costo total del día en MXN',
    alertas_generadas INT DEFAULT 0 COMMENT 'Nuevas alertas generadas',
    clientes_contactados INT DEFAULT 0 COMMENT 'Clientes únicos contactados',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Estadísticas diarias del sistema WhatsApp';

-- Índices para whatsapp_estadisticas_diarias (solo si no existen)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE table_name='whatsapp_estadisticas_diarias' AND index_name='idx_fecha') = 0,
    'ALTER TABLE whatsapp_estadisticas_diarias ADD INDEX idx_fecha (fecha)',
    'SELECT ''Índice idx_fecha ya existe'' as mensaje'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- DATOS INICIALES
-- =====================================================

-- Templates iniciales (insertar solo si no existen)
INSERT INTO whatsapp_templates (nombre, contenido, tipo_servicio, variables) VALUES
-- Template general (fallback)
('recordatorio_general', 
 'Hola {{cliente}}, te escribimos de SAG Garage 🚗\n\nHace {{tiempo}} trajiste tu {{vehiculo}} para {{servicio}}. Es momento de darle mantenimiento nuevamente.\n\n¿Te esperamos? ¡Agenda tu cita!', 
 'general', 
 '["cliente", "vehiculo", "servicio", "tiempo"]'),

-- Template para Full Service
('full_service', 
 'Hola {{cliente}}! 👋\n\nTu {{vehiculo}} necesita su Full Service. En SAG Garage cuidamos tu auto como si fuera nuestro.\n\n¿Agendamos cita? 📅', 
 'Full Service con Bujías,Full Service sin Bujías', 
 '["cliente", "vehiculo"]'),

-- Template para Cambio de Aceite  
('cambio_aceite', 
 '¡Hola {{cliente}}! 🛠️\n\nEs hora del cambio de aceite de tu {{vehiculo}}. Protege tu motor con aceite de calidad en SAG Garage.\n\n¿Cuándo vienes? Te esperamos 🚗', 
 'Cambio de Aceite', 
 '["cliente", "vehiculo"]'),

-- Template para Verificación
('verificacion', 
 'Hola {{cliente}} 📋\n\nTu {{vehiculo}} necesita su verificación vehicular. En SAG Garage te ayudamos a cumplir a tiempo.\n\n¿Te esperamos? ¡No dejes pasar la fecha límite!', 
 'Verificación', 
 '["cliente", "vehiculo"]'),

-- Template amigable con emojis
('recordatorio_amigable', 
 'Hola {{cliente}}! 😊\n\n¿Cómo está tu {{vehiculo}}? Hace {{tiempo}} le dimos {{servicio}} y ya es hora de otro chequeo.\n\nEn SAG Garage estamos listos para cuidarlo 🔧✨\n\n¿Nos vemos pronto?', 
 'general', 
 '["cliente", "vehiculo", "servicio", "tiempo"]'),

-- Template más directo
('recordatorio_directo', 
 '{{cliente}}, tu {{vehiculo}} necesita mantenimiento.\n\nÚltimo servicio: {{servicio}} ({{tiempo}} atrás)\n\nSAG Garage - Agenda tu cita\n📞 Llámanos o responde este mensaje', 
 'general', 
 '["cliente", "vehiculo", "servicio", "tiempo"]')
ON DUPLICATE KEY UPDATE 
    contenido = VALUES(contenido),
    tipo_servicio = VALUES(tipo_servicio),
    variables = VALUES(variables),
    updated_at = CURRENT_TIMESTAMP;

-- Configuraciones iniciales del sistema (insertar solo si no existen)
INSERT INTO whatsapp_config (clave, valor, descripcion, tipo) VALUES
-- API Configuration
('api_token', '', 'Token de WhatsApp Business API (obtener de Meta)', 'string'),
('phone_number_id', '', 'ID del número de teléfono registrado en WhatsApp Business', 'string'),
('webhook_token', '', 'Token para verificar webhook de WhatsApp', 'string'),
('api_version', 'v18.0', 'Versión de la API de WhatsApp a usar', 'string'),
('api_url', 'https://graph.facebook.com', 'URL base de la API de WhatsApp', 'string'),

-- Horarios y días
('hora_envio', '10:00', 'Hora de envío diaria (formato HH:mm)', 'string'),
('dias_habiles', '1,2,3,4,5', 'Días hábiles para envío (1=Lun, 7=Dom)', 'string'),
('timezone', 'America/Mexico_City', 'Zona horaria para los envíos', 'string'),

-- Límites y control
('limite_mensajes_dia', '100', 'Límite máximo de mensajes por día', 'number'),
('dias_cooldown', '30', 'Días de espera entre mensajes al mismo cliente', 'number'),
('max_intentos_envio', '3', 'Máximo intentos de reenvío por mensaje', 'number'),
('timeout_api_segundos', '30', 'Timeout para llamadas a API WhatsApp', 'number'),

-- Sistema
('activo', 'false', 'Sistema WhatsApp activo/inactivo', 'boolean'),
('modo_debug', 'false', 'Activar logs detallados para debug', 'boolean'),
('envio_automatico', 'true', 'Permitir envío automático via cron', 'boolean'),
('template_default', 'recordatorio_general', 'Template por defecto si no se encuentra específico', 'string'),

-- Información del taller
('sag_nombre', 'SAG Garage', 'Nombre del taller', 'string'),
('sag_telefono', '+52XXXXXXXXXX', 'Teléfono del taller (actualizar)', 'string'),
('sag_direccion', 'Tu dirección aquí', 'Dirección del taller (actualizar)', 'string'),
('sag_horarios', 'Lun-Vie 8:00-18:00, Sáb 8:00-14:00', 'Horarios de atención', 'string'),

-- Costos
('costo_por_mensaje', '0.5614', 'Costo en MXN por mensaje enviado', 'number'),
('limite_presupuesto_mensual', '500.00', 'Límite de gasto mensual en MXN', 'number')
ON DUPLICATE KEY UPDATE 
    valor = VALUES(valor),
    descripcion = VALUES(descripcion),
    tipo = VALUES(tipo),
    updated_at = CURRENT_TIMESTAMP;

-- Crear algunos números de prueba en blacklist para evitar envíos accidentales (solo si no existen)
INSERT INTO whatsapp_blacklist (telefono, motivo, agregado_por_id) VALUES
('+521234567890', 'Número de prueba - no enviar', 3),
('+525555555555', 'Número genérico - blacklist permanente', 3),
('+5215555551234', 'Número de testing - desarrollo', 4)
ON DUPLICATE KEY UPDATE 
    motivo = VALUES(motivo),
    agregado_por_id = VALUES(agregado_por_id);

-- =====================================================
-- VISTA: vista_whatsapp_dashboard
-- Vista para dashboard de estadísticas
-- =====================================================

CREATE VIEW vista_whatsapp_dashboard AS
SELECT 
    -- Estadísticas de hoy
    (SELECT COUNT(*) FROM alertas_servicio WHERE whatsapp_estado = 'pendiente') as alertas_pendientes,
    (SELECT COUNT(*) FROM whatsapp_logs WHERE DATE(created_at) = CURDATE()) as mensajes_hoy,
    (SELECT COUNT(*) FROM whatsapp_logs WHERE DATE(created_at) = CURDATE() AND estado = 'enviado') as enviados_hoy,
    (SELECT COUNT(*) FROM whatsapp_logs WHERE DATE(created_at) = CURDATE() AND estado = 'error') as errores_hoy,
    (SELECT SUM(costo_mensaje) FROM whatsapp_logs WHERE DATE(created_at) = CURDATE()) as costo_hoy,
    
    -- Estadísticas del mes
    (SELECT COUNT(*) FROM whatsapp_logs WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())) as mensajes_mes,
    (SELECT SUM(costo_mensaje) FROM whatsapp_logs WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())) as costo_mes,
    
    -- Estado del sistema
    (SELECT valor FROM whatsapp_config WHERE clave = 'activo') as sistema_activo,
    (SELECT COUNT(*) FROM whatsapp_blacklist WHERE activo = TRUE) as numeros_bloqueados,
    
    -- Próximos envíos
    (SELECT COUNT(*) FROM alertas_servicio WHERE whatsapp_estado = 'programado') as programados_manana;

-- =====================================================
-- FUNCIONES ÚTILES
-- =====================================================

DELIMITER //

-- Función para formatear número de teléfono a formato internacional
CREATE FUNCTION FormatearTelefono(numero VARCHAR(20)) 
RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE numero_limpio VARCHAR(20);
    
    -- Limpiar el número (solo dígitos)
    SET numero_limpio = REGEXP_REPLACE(numero, '[^0-9]', '');
    
    -- Si empieza con 52, ya es formato internacional
    IF LEFT(numero_limpio, 2) = '52' AND LENGTH(numero_limpio) = 12 THEN
        RETURN CONCAT('+', numero_limpio);
    END IF;
    
    -- Si empieza con 1 y tiene 10 dígitos, agregar 52
    IF LEFT(numero_limpio, 1) IN ('1','2','3','4','5','6','7','8','9') AND LENGTH(numero_limpio) = 10 THEN
        RETURN CONCAT('+52', numero_limpio);
    END IF;
    
    -- Si tiene 8 dígitos, asumir que necesita código de área de México
    IF LENGTH(numero_limpio) = 8 THEN
        RETURN CONCAT('+521', numero_limpio);
    END IF;
    
    -- Si no se puede determinar, devolver como está
    RETURN numero;
END //

DELIMITER ;

-- =====================================================
-- TRIGGERS
-- =====================================================

DELIMITER //

-- Trigger para actualizar estadísticas cuando se envía un mensaje
CREATE TRIGGER tr_whatsapp_log_insert 
AFTER INSERT ON whatsapp_logs
FOR EACH ROW
BEGIN
    -- Actualizar o insertar estadísticas del día
    INSERT INTO whatsapp_estadisticas_diarias (fecha, mensajes_enviados, costo_total, clientes_contactados)
    VALUES (DATE(NEW.created_at), 1, NEW.costo_mensaje, 1)
    ON DUPLICATE KEY UPDATE
        mensajes_enviados = mensajes_enviados + 1,
        costo_total = costo_total + NEW.costo_mensaje,
        clientes_contactados = clientes_contactados + 1,
        updated_at = CURRENT_TIMESTAMP;
END //

-- Trigger para actualizar contador de uso de templates
CREATE TRIGGER tr_template_uso_count
AFTER INSERT ON whatsapp_logs
FOR EACH ROW
BEGIN
    IF NEW.template_id IS NOT NULL THEN
        UPDATE whatsapp_templates 
        SET uso_count = uso_count + 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = NEW.template_id;
    END IF;
END //

DELIMITER ;

-- =====================================================
-- COMENTARIOS Y DOCUMENTACIÓN
-- =====================================================

-- Agregar comentarios a la tabla principal
ALTER TABLE alertas_servicio COMMENT = 'Tabla principal de alertas con integración WhatsApp - Actualizada 30/03/2026';

-- =====================================================
-- VERIFICACIONES FINALES
-- =====================================================

-- Mostrar resumen de tablas creadas
SELECT 
    'whatsapp_templates' as tabla,
    COUNT(*) as registros,
    'Plantillas de mensajes' as descripcion
FROM whatsapp_templates
UNION ALL
SELECT 
    'whatsapp_config' as tabla,
    COUNT(*) as registros,
    'Configuraciones del sistema' as descripcion  
FROM whatsapp_config
UNION ALL
SELECT 
    'whatsapp_blacklist' as tabla,
    COUNT(*) as registros,
    'Números bloqueados' as descripcion
FROM whatsapp_blacklist
UNION ALL
SELECT 
    'whatsapp_logs' as tabla,
    COUNT(*) as registros,
    'Log de mensajes enviados' as descripcion
FROM whatsapp_logs
UNION ALL
SELECT 
    'whatsapp_estadisticas_diarias' as tabla,
    COUNT(*) as registros,
    'Estadísticas diarias' as descripcion
FROM whatsapp_estadisticas_diarias;

-- =====================================================
-- FIN DEL ESQUEMA
-- =====================================================
-- 
-- NOTAS DE IMPLEMENTACIÓN:
-- 1. Ejecutar este script en la base de datos existente
-- 2. Actualizar configuraciones con datos reales del taller
-- 3. Configurar tokens de WhatsApp API
-- 4. Activar sistema solo después de testing completo
-- 
-- SIGUIENTE PASO: Crear WhatsappController.php
-- =====================================================