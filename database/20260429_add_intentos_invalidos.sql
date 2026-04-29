-- Migración: contador de respuestas inválidas por paso de conversación
-- Fecha: 2026-04-29
-- Propósito: Trackear cuántas veces un cliente mandó texto inválido en el paso actual.
--            El contador se reinicia a 0 cada que la conversación avanza de estado.
--            Al llegar a 2 intentos inválidos en el mismo paso, el bot escala al admin.

ALTER TABLE alertas_servicio
    ADD COLUMN intentos_invalidos TINYINT UNSIGNED NOT NULL DEFAULT 0
    COMMENT 'Respuestas fuera de opciones en el paso actual; se resetea al avanzar estado';

-- Insertar SID de plantilla de atención personalizada al admin
INSERT INTO twilio_config (config_key, config_value, description, is_active)
VALUES (
    'template_atencion_personalizada_sid',
    'HXff262c7f59e1257ab4b4e8e666375255',
    'Template Twilio sag_respuesta_invalida_seguimiento',
    TRUE
)
ON DUPLICATE KEY UPDATE
    config_value = VALUES(config_value),
    description  = VALUES(description),
    is_active    = TRUE;
