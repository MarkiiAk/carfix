-- EXTENSIÓN BD PARA WHATSAPP CONVERSACIONAL AUTÓNOMO
-- SAG Garage - Sistema de Agendamiento Automático
-- Fecha: 01/04/2026

-- ===============================================
-- EXTENSIÓN DE TABLA ALERTAS_SERVICIO
-- ===============================================

-- Estados conversacionales para el flujo de WhatsApp
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS estado_whatsapp ENUM(
    'borrador',           -- Recién generada, no enviada
    'enviado',            -- Mensaje inicial enviado
    'esperando_respuesta', -- Esperando respuesta inicial (sí/no)
    'esperando_fecha',    -- Cliente dijo sí, esperando selección fecha
    'pre_agendado',       -- Cliente seleccionó fecha, esperando confirmación SAG
    'confirmado',         -- SAG confirmó la cita
    'rechazado',          -- Cliente dijo no
    'requiere_contacto',  -- Cliente pidió "otra fecha"
    'cancelado',          -- SAG canceló/reprogramó
    'completado'          -- Flujo terminado
) DEFAULT 'borrador' AFTER estado;

-- Datos del flujo conversacional
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS twilio_conversation_sid VARCHAR(100) NULL AFTER estado_whatsapp;
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS fecha_envio_whatsapp DATETIME NULL AFTER twilio_conversation_sid;
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS respuesta_inicial VARCHAR(50) NULL AFTER fecha_envio_whatsapp; -- 'si' o 'no'
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS fecha_respuesta_inicial DATETIME NULL AFTER respuesta_inicial;

-- Datos de agendamiento
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS fecha_cita_seleccionada DATE NULL AFTER fecha_respuesta_inicial;
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS hora_cita_seleccionada TIME NULL AFTER fecha_cita_seleccionada;
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS fecha_pre_agendado DATETIME NULL AFTER hora_cita_seleccionada;

-- Confirmación SAG
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS confirmacion_sag ENUM('pendiente', 'confirmado', 'cancelado') NULL AFTER fecha_pre_agendado;
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS fecha_confirmacion_sag DATETIME NULL AFTER confirmacion_sag;
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS usuario_confirmo_sag INT NULL AFTER fecha_confirmacion_sag;

-- Control de flujo
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS requiere_atencion BOOLEAN DEFAULT FALSE AFTER usuario_confirmo_sag;
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS prioridad ENUM('baja', 'media', 'alta') DEFAULT 'media' AFTER requiere_atencion;
ALTER TABLE alertas_servicio ADD COLUMN IF NOT EXISTS ultima_actividad DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER prioridad;

-- Agregar FK para usuario que confirma
ALTER TABLE alertas_servicio ADD CONSTRAINT fk_usuario_confirma_sag 
    FOREIGN KEY (usuario_confirmo_sag) REFERENCES usuarios(id) ON DELETE SET NULL;

-- ===============================================
-- TABLA: CONVERSACIONES WHATSAPP
-- ===============================================

CREATE TABLE IF NOT EXISTS conversaciones_whatsapp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alerta_id INT NOT NULL,
    twilio_message_sid VARCHAR(100) NOT NULL,
    
    -- Dirección del mensaje
    direction ENUM('outbound', 'inbound') NOT NULL,
    
    -- Números involucrados
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    
    -- Contenido
    message_body TEXT NULL,
    message_type ENUM('text', 'interactive', 'template') DEFAULT 'text',
    
    -- Metadata Twilio
    message_status VARCHAR(20) NULL, -- sent, delivered, read, failed, etc.
    error_code VARCHAR(20) NULL,
    error_message TEXT NULL,
    twilio_response JSON NULL,
    
    -- Contexto conversacional
    conversation_step ENUM(
        'recordatorio_inicial',
        'respuesta_si_no', 
        'seleccion_fecha',
        'confirmacion_pre_agenda',
        'confirmacion_sag',
        'mensaje_final',
        'contacto_directo'
    ) NOT NULL,
    
    -- Timestamp
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Relaciones
    FOREIGN KEY (alerta_id) REFERENCES alertas_servicio(id) ON DELETE CASCADE,
    
    -- Índices
    INDEX idx_alerta_conversation (alerta_id, conversation_step),
    INDEX idx_twilio_sid (twilio_message_sid),
    INDEX idx_created_at (created_at),
    INDEX idx_direction_step (direction, conversation_step)
);

-- ===============================================
-- TABLA: CALENDARIO DISPONIBILIDAD
-- ===============================================

CREATE TABLE IF NOT EXISTS calendario_disponibilidad (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Fecha y hora del slot
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    
    -- Control de capacidad
    capacidad_total INT DEFAULT 2,          -- Máximo 2 citas por horario
    citas_ocupadas INT DEFAULT 0,          -- Cuántas están ocupadas
    
    -- Estados
    esta_disponible BOOLEAN DEFAULT TRUE,   -- Disponible para agendamiento
    es_dia_laborable BOOLEAN DEFAULT TRUE,  -- Lunes a Viernes
    
    -- Metadata
    notas TEXT NULL,                        -- Notas internas (vacaciones, etc.)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices y constraints
    UNIQUE KEY uk_fecha_hora (fecha, hora),
    INDEX idx_fecha_disponible (fecha, esta_disponible),
    INDEX idx_disponibilidad (fecha, hora, esta_disponible)
);

-- ===============================================
-- TABLA: CITAS PRE-AGENDADAS
-- ===============================================

CREATE TABLE IF NOT EXISTS citas_pre_agendadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    -- Relación con alerta
    alerta_id INT NOT NULL,
    calendario_slot_id INT NOT NULL,
    
    -- Información del cliente (denormalizada para rapidez)
    cliente_nombre VARCHAR(200) NOT NULL,
    cliente_telefono VARCHAR(20) NOT NULL,
    vehiculo_info VARCHAR(300) NOT NULL,    -- "Chevy Spark 2019"
    tipo_servicio VARCHAR(200) NOT NULL,    -- "Full Service"
    
    -- Estados de la cita
    estado ENUM('pre_agendada', 'confirmada', 'cancelada') DEFAULT 'pre_agendada',
    
    -- Timestamps del flujo
    fecha_pre_agenda DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_confirmacion DATETIME NULL,
    fecha_cancelacion DATETIME NULL,
    
    -- Usuario que confirmó
    usuario_confirmo_id INT NULL,
    
    -- Notas
    notas_cliente TEXT NULL,
    notas_internas TEXT NULL,
    
    -- Relaciones
    FOREIGN KEY (alerta_id) REFERENCES alertas_servicio(id) ON DELETE CASCADE,
    FOREIGN KEY (calendario_slot_id) REFERENCES calendario_disponibilidad(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_confirmo_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    
    -- Índices
    INDEX idx_estado_fecha (estado, fecha_pre_agenda),
    INDEX idx_alerta_id (alerta_id),
    INDEX idx_calendario_slot (calendario_slot_id)
);

-- ===============================================
-- TABLA: CONFIGURACIÓN TWILIO
-- ===============================================

CREATE TABLE IF NOT EXISTS twilio_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    description TEXT NULL,
    
    -- Metadata
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Configuraciones iniciales
INSERT INTO twilio_config (config_key, config_value, description) VALUES
-- Credenciales Twilio
('account_sid', '', 'Twilio Account SID'),
('auth_token', '', 'Twilio Auth Token'),
('whatsapp_from', 'whatsapp:+14155238886', 'Número desde el cual enviar (sandbox inicial)'),

-- Configuración SAG Garage  
('sag_admin_phone', '5215551234567', 'Número WhatsApp de SAG Garage para confirmaciones'),
('sag_business_name', 'SAG Garage', 'Nombre del negocio'),

-- Horarios de trabajo
('horario_matutino', '09:00', 'Hora de citas matutinas'),
('horario_vespertino', '14:00', 'Hora de citas vespertinas'), 
('dias_laborables', 'L,M,M,J,V', 'Días laborables (L=Lunes, etc.)'),
('capacidad_por_horario', '2', 'Máximo de citas por slot de horario'),

-- Configuración de fechas
('dias_anticipacion', '7', 'Días hacia adelante para mostrar fechas disponibles'),
('dias_minimo_agenda', '1', 'Días mínimos de anticipación para agendar'),

-- Templates de mensajes
('mensaje_recordatorio', 'Hola {cliente}, notamos que hace 6 meses nos visitaste para un {servicio}. Ya es momento de realizarlo nuevamente. ¿Te gustaría agendar?', 'Mensaje inicial de recordatorio'),
('mensaje_rechazo', 'Entendemos. Esperamos verte pronto cuando necesites nuestros servicios. ¡SAG Garage siempre estará aquí para ti! 🚗', 'Mensaje cuando cliente dice no'),
('mensaje_pre_agenda', '¡Perfecto! Tu cita está PRE-AGENDADA para el {fecha} a las {hora}. Te confirmaremos en breve. ¡Nos vemos!', 'Mensaje de pre-agendamiento'),
('mensaje_confirmacion', '✅ ¡CONFIRMADO! Te esperamos el {fecha} a las {hora} en SAG Garage. ¡Nos vemos pronto! 🚗', 'Mensaje de confirmación final'),
('mensaje_cancelacion', 'Disculpa las molestias. Un ejecutivo se pondrá en contacto contigo para reagendar tu cita. ¡Gracias por tu comprensión!', 'Mensaje cuando SAG cancela'),
('mensaje_contacto_directo', 'Un ejecutivo de SAG Garage se pondrá en contacto contigo para agendar en la fecha que mejor te convenga. ¡Gracias!', 'Mensaje para contacto directo'),

-- Configuración de webhook
('webhook_url', 'https://saggarage.com/backend-php/webhook/twilio_whatsapp.php', 'URL del webhook de Twilio')

ON DUPLICATE KEY UPDATE 
    config_value = VALUES(config_value),
    updated_at = CURRENT_TIMESTAMP;

-- ===============================================
-- VISTAS PARA DASHBOARD
-- ===============================================

-- Vista para campanita inteligente
CREATE OR REPLACE VIEW vista_campanita_whatsapp AS
SELECT 
    a.id,
    a.estado_whatsapp,
    a.requiere_atencion,
    a.prioridad,
    a.ultima_actividad,
    
    -- Info cliente
    c.nombre as cliente_nombre,
    c.telefono as cliente_telefono,
    
    -- Info vehículo
    CONCAT(v.marca, ' ', v.modelo, ' ', v.anio) as vehiculo_info,
    
    -- Info servicio
    a.servicios_que_dispararon as tipo_servicio,
    
    -- Info cita
    a.fecha_cita_seleccionada,
    a.hora_cita_seleccionada,
    a.confirmacion_sag,
    
    -- Determinar tipo de notificación
    CASE 
        WHEN a.estado_whatsapp = 'pre_agendado' AND a.confirmacion_sag = 'pendiente' THEN 'urgente'
        WHEN a.estado_whatsapp = 'requiere_contacto' THEN 'contacto'
        WHEN a.estado_whatsapp = 'rechazado' THEN 'informativo'
        WHEN a.estado_whatsapp = 'cancelado' THEN 'informativo'
        ELSE 'normal'
    END as tipo_notificacion,
    
    -- Mensaje para campanita
    CASE 
        WHEN a.estado_whatsapp = 'pre_agendado' AND a.confirmacion_sag = 'pendiente' THEN 
            CONCAT('🔴 CONFIRMAR: ', c.nombre, ' - ', a.fecha_cita_seleccionada, ' ', a.hora_cita_seleccionada)
        WHEN a.estado_whatsapp = 'requiere_contacto' THEN 
            CONCAT('🟡 CONTACTAR: ', c.nombre, ' pidió otra fecha para ', a.servicios_que_dispararon)
        WHEN a.estado_whatsapp = 'rechazado' THEN 
            CONCAT('🔵 INFO: ', c.nombre, ' no está interesado en ', a.servicios_que_dispararon)
        WHEN a.estado_whatsapp = 'cancelado' THEN 
            CONCAT('🔵 CANCELADO: ', c.nombre, ' - ', a.fecha_cita_seleccionada)
        ELSE 
            CONCAT('📝 ', c.nombre, ' - ', a.estado_whatsapp)
    END as mensaje_campanita,
    
    -- Link directo WhatsApp
    CONCAT('https://wa.me/52', c.telefono, '?text=Hola%20', REPLACE(c.nombre, ' ', '%20'), '%20nos%20comunicamos%20de%20SAG%20Garage') as link_whatsapp

FROM alertas_servicio a
INNER JOIN clientes c ON a.cliente_id = c.id
INNER JOIN vehiculos v ON a.vehiculo_id = v.id
WHERE a.requiere_atencion = TRUE
  AND a.estado_whatsapp IN ('pre_agendado', 'requiere_contacto', 'rechazado', 'cancelado')
ORDER BY 
    CASE 
        WHEN a.estado_whatsapp = 'pre_agendado' AND a.confirmacion_sag = 'pendiente' THEN 1
        WHEN a.estado_whatsapp = 'requiere_contacto' THEN 2
        ELSE 3
    END,
    a.prioridad DESC,
    a.ultima_actividad DESC;

-- Vista para dashboard conversacional
CREATE OR REPLACE VIEW vista_conversaciones_whatsapp AS
SELECT 
    a.id as alerta_id,
    a.estado_whatsapp,
    a.ultima_actividad,
    
    -- Info cliente
    c.nombre as cliente_nombre,
    c.telefono as cliente_telefono,
    
    -- Info conversación
    a.twilio_conversation_sid,
    a.fecha_envio_whatsapp,
    a.respuesta_inicial,
    a.fecha_respuesta_inicial,
    
    -- Info cita
    a.fecha_cita_seleccionada,
    a.hora_cita_seleccionada,
    a.confirmacion_sag,
    
    -- Último mensaje
    (SELECT message_body 
     FROM conversaciones_whatsapp 
     WHERE alerta_id = a.id 
     ORDER BY created_at DESC 
     LIMIT 1) as ultimo_mensaje,
    
    (SELECT created_at 
     FROM conversaciones_whatsapp 
     WHERE alerta_id = a.id 
     ORDER BY created_at DESC 
     LIMIT 1) as fecha_ultimo_mensaje,
     
    -- Conteo de mensajes
    (SELECT COUNT(*) 
     FROM conversaciones_whatsapp 
     WHERE alerta_id = a.id) as total_mensajes

FROM alertas_servicio a
INNER JOIN clientes c ON a.cliente_id = c.id
WHERE a.estado_whatsapp != 'borrador'
ORDER BY a.ultima_actividad DESC;

-- ===============================================
-- FUNCIONES DE UTILIDAD
-- ===============================================

DELIMITER $$

-- Función para obtener configuración Twilio
CREATE FUNCTION IF NOT EXISTS GetTwilioConfig(config_key_param VARCHAR(100))
RETURNS TEXT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE config_val TEXT DEFAULT NULL;
    
    SELECT config_value INTO config_val 
    FROM twilio_config 
    WHERE config_key = config_key_param 
      AND is_active = TRUE
    LIMIT 1;
    
    RETURN config_val;
END$$

-- Función para generar horarios disponibles
CREATE FUNCTION IF NOT EXISTS GenerarFechasDisponibles(dias_adelante INT)
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE fechas_json JSON DEFAULT JSON_ARRAY();
    DECLARE fecha_actual DATE DEFAULT DATE_ADD(CURDATE(), INTERVAL 1 DAY);
    DECLARE contador INT DEFAULT 0;
    DECLARE dias_generados INT DEFAULT 0;
    
    -- Obtener configuración
    DECLARE horario_am TIME DEFAULT TIME(GetTwilioConfig('horario_matutino'));
    DECLARE horario_pm TIME DEFAULT TIME(GetTwilioConfig('horario_vespertino'));
    DECLARE capacidad_max INT DEFAULT CAST(GetTwilioConfig('capacidad_por_horario') AS UNSIGNED);
    
    WHILE dias_generados < dias_adelante AND contador < 30 DO
        SET contador = contador + 1;
        
        -- Solo días laborales (Lunes=0 a Viernes=4)
        IF WEEKDAY(fecha_actual) BETWEEN 0 AND 4 THEN
            -- Verificar slot matutino
            SET @citas_am = (
                SELECT COALESCE(citas_ocupadas, 0) 
                FROM calendario_disponibilidad 
                WHERE fecha = fecha_actual AND hora = horario_am
            );
            
            IF @citas_am < capacidad_max THEN
                SET fechas_json = JSON_ARRAY_APPEND(fechas_json, '$', JSON_OBJECT(
                    'fecha', fecha_actual,
                    'hora', horario_am,
                    'horario', 'matutino',
                    'texto', CONCAT(DATE_FORMAT(fecha_actual, '%W %e de %M'), ' - ', TIME_FORMAT(horario_am, '%H:%i')),
                    'disponible', TRUE
                ));
            END IF;
            
            -- Verificar slot vespertino  
            SET @citas_pm = (
                SELECT COALESCE(citas_ocupadas, 0) 
                FROM calendario_disponibilidad 
                WHERE fecha = fecha_actual AND hora = horario_pm
            );
            
            IF @citas_pm < capacidad_max THEN
                SET fechas_json = JSON_ARRAY_APPEND(fechas_json, '$', JSON_OBJECT(
                    'fecha', fecha_actual,
                    'hora', horario_pm,
                    'horario', 'vespertino', 
                    'texto', CONCAT(DATE_FORMAT(fecha_actual, '%W %e de %M'), ' - ', TIME_FORMAT(horario_pm, '%H:%i')),
                    'disponible', TRUE
                ));
            END IF;
            
            SET dias_generados = dias_generados + 1;
        END IF;
        
        SET fecha_actual = DATE_ADD(fecha_actual, INTERVAL 1 DAY);
    END WHILE;
    
    RETURN fechas_json;
END$$

DELIMITER ;

-- ===============================================
-- PROCEDIMIENTOS
-- ===============================================

DELIMITER $$

-- Procedimiento para inicializar calendario
CREATE PROCEDURE IF NOT EXISTS InicializarCalendario(dias_adelante INT)
BEGIN
    DECLARE fecha_actual DATE DEFAULT CURDATE();
    DECLARE fecha_limite DATE DEFAULT DATE_ADD(CURDATE(), INTERVAL dias_adelante DAY);
    DECLARE horario_am TIME DEFAULT TIME(GetTwilioConfig('horario_matutino'));
    DECLARE horario_pm TIME DEFAULT TIME(GetTwilioConfig('horario_vespertino'));
    
    WHILE fecha_actual <= fecha_limite DO
        -- Solo días laborales
        IF WEEKDAY(fecha_actual) BETWEEN 0 AND 4 THEN
            -- Insertar slot matutino
            INSERT IGNORE INTO calendario_disponibilidad (fecha, hora, es_dia_laborable)
            VALUES (fecha_actual, horario_am, TRUE);
            
            -- Insertar slot vespertino
            INSERT IGNORE INTO calendario_disponibilidad (fecha, hora, es_dia_laborable)
            VALUES (fecha_actual, horario_pm, TRUE);
        END IF;
        
        SET fecha_actual = DATE_ADD(fecha_actual, INTERVAL 1 DAY);
    END WHILE;
END$$

-- Procedimiento para limpiar datos antiguos
CREATE PROCEDURE IF NOT EXISTS LimpiarDatosAntiguos()
BEGIN
    -- Limpiar conversaciones mayores a 3 meses
    DELETE FROM conversaciones_whatsapp 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);
    
    -- Limpiar slots de calendario pasados
    DELETE FROM calendario_disponibilidad 
    WHERE fecha < CURDATE();
    
    -- Limpiar citas pre-agendadas muy antiguas
    DELETE FROM citas_pre_agendadas 
    WHERE estado != 'confirmada' 
    AND fecha_pre_agenda < DATE_SUB(NOW(), INTERVAL 1 MONTH);
    
    SELECT ROW_COUNT() as registros_limpiados;
END$$

DELIMITER ;

-- ===============================================
-- ÍNDICES ADICIONALES PARA PERFORMANCE
-- ===============================================

-- Alertas
ALTER TABLE alertas_servicio ADD INDEX IF NOT EXISTS idx_estado_whatsapp (estado_whatsapp);
ALTER TABLE alertas_servicio ADD INDEX IF NOT EXISTS idx_requiere_atencion (requiere_atencion, estado_whatsapp);
ALTER TABLE alertas_servicio ADD INDEX IF NOT EXISTS idx_ultima_actividad (ultima_actividad);
ALTER TABLE alertas_servicio ADD INDEX IF NOT EXISTS idx_pre_agenda (estado_whatsapp, fecha_cita_seleccionada);

-- ===============================================
-- INICIALIZACIÓN
-- ===============================================

-- Inicializar calendario para los próximos 30 días
CALL InicializarCalendario(30);

-- Mostrar resumen
SELECT 
    'WhatsApp Conversacional Extension Applied Successfully' as status,
    (SELECT COUNT(*) FROM twilio_config WHERE is_active = TRUE) as config_entries,
    (SELECT COUNT(*) FROM calendario_disponibilidad) as calendar_slots,
    (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE '%whatsapp%') as new_tables;