-- =============================================================================
-- LIMPIEZA TOTAL ABRIL 2026 — Borra TODOS los datos de abril (reales + seed)
-- INSTRUCCIÓN: En phpMyAdmin ejecuta CADA bloque por separado si falla alguno
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- PASO 1: Eliminar hijos de órdenes ABRIL-% por prefijo (incluye ABRIL-038 en mayo)
DELETE FROM refacciones_orden WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'ABRIL-%');
DELETE FROM servicios_orden   WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'ABRIL-%');
DELETE FROM ordenes_servicio  WHERE numero_orden LIKE 'ABRIL-%';

-- PASO 2: Eliminar hijos de órdenes SEED-% (mayo seed anterior)
DELETE FROM refacciones_orden WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'SEED-%');
DELETE FROM servicios_orden   WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'SEED-%');
DELETE FROM ordenes_servicio  WHERE numero_orden LIKE 'SEED-%';

-- PASO 3: Eliminar órdenes reales de abril por fecha (las de Paco sin prefijo)
DELETE FROM refacciones_orden WHERE orden_id IN (
    SELECT id FROM ordenes_servicio
    WHERE (fecha_ingreso    BETWEEN '2026-04-01' AND '2026-04-30')
       OR (fecha_completada BETWEEN '2026-04-01' AND '2026-04-30')
       OR (fecha_entregada  BETWEEN '2026-04-01' AND '2026-04-30')
);
DELETE FROM servicios_orden WHERE orden_id IN (
    SELECT id FROM ordenes_servicio
    WHERE (fecha_ingreso    BETWEEN '2026-04-01' AND '2026-04-30')
       OR (fecha_completada BETWEEN '2026-04-01' AND '2026-04-30')
       OR (fecha_entregada  BETWEEN '2026-04-01' AND '2026-04-30')
);
DELETE FROM ordenes_servicio
WHERE (fecha_ingreso    BETWEEN '2026-04-01' AND '2026-04-30')
   OR (fecha_completada BETWEEN '2026-04-01' AND '2026-04-30')
   OR (fecha_entregada  BETWEEN '2026-04-01' AND '2026-04-30');

-- PASO 4: Eliminar vehículos y clientes del seed (ya sin órdenes que los referencien)
DELETE FROM vehiculos WHERE numero_serie LIKE 'ABRIL-%';
DELETE FROM vehiculos WHERE numero_serie LIKE 'SEED-%';
DELETE FROM clientes  WHERE rfc LIKE 'ABR%';
DELETE FROM clientes  WHERE rfc LIKE 'SEED%';

-- PASO 5: Gastos variables y caja chica de abril y mayo seed
DELETE FROM caja_chica            WHERE fecha BETWEEN '2026-04-01' AND '2026-05-17';
DELETE FROM gastos_administrativos WHERE anio = 2026 AND mes IN (4, 5);

-- PASO 6: Sueldos y pagos fijos (para recargar limpios)
DELETE FROM empleados_sueldos WHERE nombre IN ('Mayte Macías','Carlos Castillo','Markus Fabela','Roberto Zamora','Karla Betzbae','Jorge Marín');
DELETE FROM pagos_fijos       WHERE concepto IN ('Renta + Agua','Internet Telmex','Publicación Facebook','Préstamo Taller','Pago iPhone Taller','AkLabs - Marco');

SET FOREIGN_KEY_CHECKS = 1;

-- Verificación final
SELECT 'Ordenes abril restantes' AS check_name, COUNT(*) AS cantidad
FROM ordenes_servicio
WHERE (fecha_ingreso BETWEEN '2026-04-01' AND '2026-04-30')
   OR (fecha_entregada BETWEEN '2026-04-01' AND '2026-04-30')
UNION ALL
SELECT 'Vehiculos ABRIL restantes', COUNT(*) FROM vehiculos WHERE numero_serie LIKE 'ABRIL-%'
UNION ALL
SELECT 'Clientes ABR restantes', COUNT(*) FROM clientes WHERE rfc LIKE 'ABR%';
