-- Datos de prueba para el sistema de alertas
-- Este script inserta alertas de ejemplo para probar el sistema

-- Insertar alertas de ejemplo (asumiendo que existen órdenes con IDs 1, 2, 3)
INSERT INTO alertas_servicio (
    orden_id,
    cliente_id, 
    vehiculo_id,
    fecha_ultimo_servicio,
    servicios_que_dispararon,
    todos_los_servicios,
    dias_desde_servicio,
    estado,
    fecha_generada
) VALUES 
-- Alerta urgente (más de 240 días)
(1, 1, 1, '2026-05-15 10:00:00', 
 '["Full Service con Bujías", "Cambio de Aceite"]',
 '["Full Service con Bujías", "Cambio de Aceite", "Revisión de Frenos"]',
 250, 'pendiente', NOW()),

-- Alerta de alta prioridad (210 días)  
(2, 2, 2, '2026-06-20 14:30:00',
 '["Full Service sin Bujías"]', 
 '["Full Service sin Bujías", "Alineación", "Balanceo"]',
 210, 'pendiente', NOW()),

-- Alerta normal (185 días)
(3, 3, 3, '2026-07-10 09:15:00',
 '["Verificación"]',
 '["Verificación", "Cambio de Filtros"]', 
 185, 'pendiente', NOW()),

-- Alerta ya leída (para ejemplo)
(4, 4, 4, '2026-06-01 16:45:00',
 '["Cambio de Aceite"]',
 '["Cambio de Aceite", "Revisión General"]',
 220, 'leida', '2026-03-10 12:00:00');

-- Nota: Ajusta los IDs de orden_id, cliente_id y vehiculo_id según los datos reales de tu base de datos
-- Para ver los IDs disponibles, ejecuta:
-- SELECT id FROM ordenes_servicio LIMIT 5;
-- SELECT id FROM clientes LIMIT 5;  
-- SELECT id FROM vehiculos LIMIT 5;