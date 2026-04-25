-- Migración: agregar columna slots_ofrecidos_json a alertas_servicio
-- Fecha: 2026-04-21
-- Motivo: procesarRespuestaSiSimplificado ofrecía slots generados en memoria sin persistirlos.
--         Cuando el cliente respondía con un número, no había forma de mapear "2" al slot
--         real de calendario_disponibilidad. Esta columna guarda el JSON de slots ofrecidos
--         en el momento del envío para que procesarRespuestaNumericaSimple pueda resolver
--         la selección del cliente contra datos reales de calendario.

ALTER TABLE alertas_servicio
    ADD COLUMN slots_ofrecidos_json TEXT NULL
    COMMENT 'JSON con slots ofrecidos al cliente para mapear su respuesta numérica a un slot real'
    AFTER estado_whatsapp;
