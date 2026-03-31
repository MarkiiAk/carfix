-- =====================================================
-- CONFIGURACIÓN INICIAL DE WHATSAPP - SAG GARAGE
-- Insertará las credenciales obtenidas de Meta
-- Fecha: 31/03/2026
-- =====================================================

-- Actualizar configuraciones de la API de WhatsApp con credenciales reales
INSERT INTO whatsapp_config (clave, valor, descripcion, tipo) VALUES
-- API Configuration - CREDENCIALES REALES
('api_token', 'EAANV3uRbIZAABRNtxZACsf5dOhgnW4ApTvJe9F2rT1VbaHi3QsAZCB8n4E69d3c1eVsw36mDaKI9wB39U2sLLwil39HqOveSzMyAyvYzaACzmr2ZCrOU2cgTtktq6TKabMSDZAbbHW0IuEZALGksoOWd9YVPhQerL2NtmMZCrh2g5WX9V38SIralXZC6RGccnmlMUcO03Gq2BPWYuQQSihZBjppZB1exWM1gvoLtflY7n3ewI9AUB5q2fQRMEYizyncauPWZBPcO09oYjM87iGslUP1tZC9yFOBiU7ZA626IZD', 'Token de WhatsApp Business API de Meta', 'string'),
('phone_number_id', '1108963935628963', 'ID del número de teléfono (+1 555 154 2714)', 'string'),
('business_account_id', '1607363090521725', 'ID de la cuenta de WhatsApp Business', 'string'),
('webhook_token', 'SAG_WEBHOOK_2026', 'Token para verificar webhook de WhatsApp', 'string'),
('api_version', 'v22.0', 'Versión de la API de WhatsApp a usar', 'string'),
('api_url', 'https://graph.facebook.com', 'URL base de la API de WhatsApp', 'string'),

-- Configuraciones operativas
('activo', 'true', 'Sistema WhatsApp activo para recibir webhooks', 'boolean'),
('modo_debug', 'true', 'Activar logs detallados para debug inicial', 'boolean'),
('envio_automatico', 'false', 'Envío automático desactivado hasta finalizar configuración', 'boolean'),
('template_default', 'recordatorio_general', 'Template por defecto', 'string'),

-- Límites conservadores para inicio
('limite_mensajes_dia', '50', 'Límite conservador para pruebas', 'number'),
('max_intentos_envio', '3', 'Máximo intentos de reenvío por mensaje', 'number'),
('timeout_api_segundos', '30', 'Timeout para llamadas a API WhatsApp', 'number'),

-- Información del taller SAG
('sag_nombre', 'SAG Servicio Automotriz Gudiño', 'Nombre del taller', 'string'),
('sag_telefono', '+52 1 55 1342 2917', 'Teléfono del taller (número real)', 'string'),
('sag_direccion', 'Ciudad de México', 'Dirección del taller', 'string'),
('sag_horarios', 'Lun-Vie 8:00-18:00, Sáb 8:00-14:00', 'Horarios de atención', 'string'),

-- Validación de IP de Facebook (opcional)
('validar_ip_facebook', 'false', 'Validar que webhooks vengan de IPs de Facebook', 'boolean')

ON DUPLICATE KEY UPDATE 
    valor = VALUES(valor),
    descripcion = VALUES(descripcion),
    tipo = VALUES(tipo),
    updated_at = CURRENT_TIMESTAMP;

-- Verificar que se insertaron correctamente
SELECT 
    clave, 
    CASE 
        WHEN clave = 'api_token' THEN CONCAT(LEFT(valor, 20), '...[TOKEN_OCULTO]')
        ELSE valor 
    END as valor_mostrado,
    tipo,
    updated_at
FROM whatsapp_config 
WHERE clave IN (
    'api_token', 
    'phone_number_id', 
    'business_account_id', 
    'webhook_token', 
    'api_version',
    'activo',
    'sag_nombre'
)
ORDER BY clave;