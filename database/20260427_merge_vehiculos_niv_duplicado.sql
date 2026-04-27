-- Merge vehículos con NIV duplicado: 3GAPE1A96RM009744
-- Fecha: 2026-04-27
-- Contexto: Paco Gudiño confirmó que ids 74 y 81 son el mismo auto
-- Decisión: conservar id=74 (primer registro), eliminar id=81

START TRANSACTION;

-- 1. Asegurar que el vehículo canónico tiene el NIV correcto
UPDATE vehiculos
SET niv = '3GAPE1A96RM009744'
WHERE id = 74;

-- 2. Transferir órdenes de servicio al vehículo canónico
UPDATE ordenes_servicio
SET vehiculo_id = 74
WHERE vehiculo_id = 81;

-- 3. Transferir alertas WhatsApp al vehículo canónico
UPDATE alertas_servicio
SET vehiculo_id = 74
WHERE vehiculo_id = 81;

-- 4. Eliminar el duplicado (ya sin referencias)
DELETE FROM vehiculos WHERE id = 81;

COMMIT;

-- VERIFICACIÓN POST-MERGE
-- SELECT id, marca, modelo, placas, niv FROM vehiculos WHERE id = 74;
-- SELECT COUNT(*) FROM ordenes_servicio WHERE vehiculo_id = 81; -- debe ser 0
-- SELECT COUNT(*) FROM alertas_servicio WHERE vehiculo_id = 81; -- debe ser 0
