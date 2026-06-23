-- Configuraciones para sistema adaptable de templates WhatsApp
-- Implementado: 12/04/2026

-- Agregar configuraciones para el sistema de templates
INSERT INTO twilio_config (config_key, config_value, description, is_active, created_at) VALUES
-- Modo de template (simple o interactive)
('template_mode', 'simple', 'Modo de template: simple (texto) o interactive (botones)', TRUE, NOW()),

-- Template simple (aprobado)
('template_simple_sid', 'HX183daf481204160ef29a837ce1b22ecb', 'ContentSid del template simple con texto', TRUE, NOW()),

-- Template interactivo (pendiente de aprobación)
('template_interactive_sid', 'HX765eae763cf778deacde6238674d4108', 'ContentSid del template con botones interactivos', TRUE, NOW()),

-- Configuración de validación para clientes
('max_intentos_respuesta', '3', 'Máximo número de intentos para respuesta válida', TRUE, NOW()),

-- Mensaje de ayuda para respuestas inválidas
('mensaje_ayuda_respuesta', 'Por favor responde solo con el *NÚMERO* de la opción que prefieres (ejemplo: 1, 2, 3...). ¡Así podremos ayudarte mejor! 😊', 'Mensaje cuando el cliente da respuesta inválida', TRUE, NOW())

ON DUPLICATE KEY UPDATE 
config_value = VALUES(config_value),
description = VALUES(description),
updated_at = NOW();
