-- ============================================================
-- RESTAURAR ALERTA 36 EN PRODUCCIÓN
-- Reconstruida desde dump saggarag_staging_clean.sql
-- Estado: borrador/pendiente — lista para que el cron la tome
-- ============================================================
-- PREREQUISITO: correr las 2 migraciones de M0-001 primero si no se han corrido:
--   database/20260421_fix_twilio_config_keys.sql
--   database/20260421_add_slots_ofrecidos_json.sql
-- ============================================================

-- 1. Limpiar datos históricos de conversaciones de alerta 36
DELETE FROM conversaciones_whatsapp WHERE alerta_id = 36;

-- 2. Limpiar cita pre-agendada histórica de alerta 36
DELETE FROM citas_pre_agendadas WHERE alerta_id = 36;

-- 3. Eliminar si quedó algún residuo de la alerta (por si acaso)
DELETE FROM alertas_servicio WHERE id = 36;

-- 4. Restaurar alerta 36 en estado borrador/pendiente
INSERT INTO alertas_servicio (
    id,
    orden_id,
    cliente_id,
    vehiculo_id,
    fecha_ultimo_servicio,
    servicios_que_dispararon,
    todos_los_servicios,
    estado,
    estado_whatsapp,
    slots_ofrecidos_json,
    twilio_conversation_sid,
    fecha_envio_whatsapp,
    respuesta_inicial,
    fecha_respuesta_inicial,
    fecha_cita_seleccionada,
    hora_cita_seleccionada,
    fecha_pre_agendado,
    confirmacion_sag,
    estado_twilio,
    twilio_message_sid,
    respuesta_cliente,
    fecha_respuesta,
    fecha_seleccionada_cliente,
    hora_seleccionada_cliente,
    fecha_confirmacion_sag,
    usuario_confirmo_sag,
    requiere_atencion,
    prioridad,
    ultima_actividad,
    confirmado_por,
    fecha_cancelacion,
    cancelado_por,
    fecha_reprogramacion,
    prioridad_atencion,
    fecha_generada,
    fecha_marcada_leida,
    usuario_marco_leida,
    dias_desde_servicio
) VALUES (
    36,
    1694,
    80,
    73,
    '2026-03-11 23:40:34',
    '["MANTENIMIENTO AL CUERPO DE VALVULAS Y CAMBIO DE ACEITE"]',
    '["DESMONTAR Y MONTAR TRANSMISION","MANTENIMIENTO AL CUERPO DE VALVULAS Y CAMBIO DE ACEITE","REPARACION ELECTRONICA DE LINEAS ABS"]',
    'pendiente',
    'borrador',
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    'borrador',
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    NULL,
    0,
    'media',
    NOW(),
    NULL,
    NULL,
    NULL,
    NULL,
    'media',
    '2026-04-06 05:25:02',
    NULL,
    NULL,
    25
);

-- 5. Verificar que quedó bien
SELECT
    id,
    orden_id,
    cliente_id,
    vehiculo_id,
    estado,
    estado_whatsapp,
    twilio_conversation_sid,
    fecha_envio_whatsapp,
    dias_desde_servicio
FROM alertas_servicio
WHERE id = 36;
