-- Migración: corrección de claves y datos en twilio_config
-- Fecha: 2026-04-21
-- Problema: template_simple_sid apuntaba al template de oferta de slots (sag_garage_agendar),
--            no a un recordatorio simple. template_mode='simple' era incoherente porque ese
--            template no existe. sag_otro_horario_cita no estaba registrado en config.

-- 1. Renombrar template_simple_sid → template_agendar_sid (refleja su propósito real)
UPDATE twilio_config
SET config_key   = 'template_agendar_sid',
    description  = 'ContentSid del template de oferta de horarios (sag_garage_agendar — HX183daf481204160ef29a837ce1b22ecb)'
WHERE config_key = 'template_simple_sid';

-- 2. Corregir template_mode: no existe un recordatorio de texto plano, el único
--    recordatorio aprobado es el Quick Reply (sag_garage_recordatorio)
UPDATE twilio_config
SET config_value = 'interactive'
WHERE config_key = 'template_mode';

-- 3. Registrar el template de "otro horario" que faltaba en config
INSERT INTO twilio_config (config_key, config_value, description, is_active)
VALUES (
    'template_otro_horario_sid',
    'HX8426371cf12251f300d04c2884f869f0',
    'ContentSid cuando cliente pide otro horario (sag_otro_horario_cita)',
    1
);
