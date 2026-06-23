-- Merge vehículo duplicado de Carlos Ángeles Quintero (cliente id=71)
-- Fecha: 2026-04-27
-- Contexto: el cliente reportó que capturaron el mismo auto con año y placas distintos
-- id=64: JEEP COMPASS 2010, placas PDP5374 (correcto, 2 órdenes) ← CONSERVAR
-- id=108: JEEP COMPASS 2014, placas PDF5374 (typo en año y placa, 1 orden) ← FUSIONAR

START TRANSACTION;

-- 1. Transferir órdenes de servicio al vehículo correcto
UPDATE ordenes_servicio SET vehiculo_id = 64 WHERE vehiculo_id = 108;

-- 2. Transferir alertas WhatsApp al vehículo correcto
UPDATE alertas_servicio SET vehiculo_id = 64 WHERE vehiculo_id = 108;

-- 3. Limpiar el vehículo canónico: modelo sin año embebido, año explícito
UPDATE vehiculos
SET modelo = 'COMPASS',
    anio   = '2010',
    placas = 'PDP5374'
WHERE id = 64;

-- 4. Eliminar el duplicado (ya sin referencias)
DELETE FROM vehiculos WHERE id = 108;

COMMIT;

-- VERIFICACIÓN
-- SELECT id, marca, modelo, anio, placas, cliente_id FROM vehiculos WHERE cliente_id = 71;
-- Esperado: un solo vehículo con modelo='COMPASS', anio='2010', placas='PDP5374'
-- SELECT COUNT(*) FROM ordenes_servicio WHERE vehiculo_id = 108; -- debe ser 0
