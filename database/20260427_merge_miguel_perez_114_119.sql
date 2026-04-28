-- Merge Miguel Pérez duplicado: ids 114 y 119
-- Fecha: 2026-04-27
-- Confirmado por Marco: son la misma persona
-- Decisión: conservar id=114 (primer registro), eliminar id=119
-- Nota: id=119 tiene 1 vehículo (NP300 2014, id=95) e id=114 no tiene vehículos

START TRANSACTION;

UPDATE ordenes_servicio  SET cliente_id  = 114 WHERE cliente_id  = 119;
UPDATE vehiculos         SET cliente_id  = 114 WHERE cliente_id  = 119;
UPDATE alertas_servicio  SET cliente_id  = 114 WHERE cliente_id  = 119;

UPDATE clientes
SET activo = 0, fusionado_en = 114, fusionado_at = NOW(),
    notas_merge = 'Merge Miguel Pérez duplicado 2026-04-27'
WHERE id = 119;

UPDATE clientes SET nombre = 'MIGUEL PEREZ' WHERE id = 114;

COMMIT;

-- VERIFICACIÓN
-- SELECT id, nombre, activo, fusionado_en FROM clientes WHERE id IN (114, 119);
-- SELECT COUNT(*) FROM ordenes_servicio WHERE cliente_id = 119; -- debe ser 0
-- SELECT id, marca, modelo, placas FROM vehiculos WHERE cliente_id = 114; -- debe incluir el NP300
