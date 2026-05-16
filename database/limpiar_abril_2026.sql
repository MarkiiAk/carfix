-- =============================================================================
-- LIMPIEZA TOTAL ABRIL 2026 — Borra TODOS los datos de abril (reales + seed)
-- Correr ANTES de volver a cargar el seed_abril_2026_parte2.sql
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Órdenes que tienen actividad en abril (por cualquier fecha relevante)
DELETE FROM refacciones_orden WHERE orden_id IN (
    SELECT id FROM ordenes_servicio
    WHERE (fecha_ingreso       BETWEEN '2026-04-01' AND '2026-04-30')
       OR (fecha_completada    BETWEEN '2026-04-01' AND '2026-04-30')
       OR (fecha_entregada     BETWEEN '2026-04-01' AND '2026-04-30')
);

DELETE FROM servicios_orden WHERE orden_id IN (
    SELECT id FROM ordenes_servicio
    WHERE (fecha_ingreso       BETWEEN '2026-04-01' AND '2026-04-30')
       OR (fecha_completada    BETWEEN '2026-04-01' AND '2026-04-30')
       OR (fecha_entregada     BETWEEN '2026-04-01' AND '2026-04-30')
);

DELETE FROM ordenes_servicio
WHERE (fecha_ingreso    BETWEEN '2026-04-01' AND '2026-04-30')
   OR (fecha_completada BETWEEN '2026-04-01' AND '2026-04-30')
   OR (fecha_entregada  BETWEEN '2026-04-01' AND '2026-04-30');

-- Clientes y vehículos del seed (no tocar los reales sin prefijo ABRIL)
DELETE FROM vehiculos WHERE numero_serie LIKE 'ABRIL-%';
DELETE FROM clientes  WHERE rfc LIKE 'ABR%';

-- Gastos y caja chica de abril
DELETE FROM caja_chica            WHERE fecha BETWEEN '2026-04-01' AND '2026-04-30';
DELETE FROM gastos_administrativos WHERE anio = 2026 AND mes = 4;

-- También limpiar el seed de mayo si quedó
DELETE FROM caja_chica            WHERE fecha BETWEEN '2026-05-01' AND '2026-05-17';
DELETE FROM gastos_administrativos WHERE anio = 2026 AND mes = 5;
DELETE FROM refacciones_orden WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'SEED-%');
DELETE FROM servicios_orden   WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'SEED-%');
DELETE FROM ordenes_servicio  WHERE numero_orden LIKE 'SEED-%';
DELETE FROM vehiculos         WHERE numero_serie LIKE 'SEED-%';
DELETE FROM clientes          WHERE rfc LIKE 'SEED%';

SET FOREIGN_KEY_CHECKS = 1;

SELECT CONCAT('✅ Limpieza completa. Órdenes abril restantes: ', COUNT(*)) AS resultado
FROM ordenes_servicio
WHERE (fecha_ingreso BETWEEN '2026-04-01' AND '2026-04-30')
   OR (fecha_entregada BETWEEN '2026-04-01' AND '2026-04-30');
