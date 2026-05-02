-- =====================================================================
-- SEED: Fixture de prueba — Enero 2024 (Módulo Financiero)
--
-- Propósito: verificar que el endpoint GET /api/financiero/gastos-admin
--   calcula correctamente el balance mensual.
--
-- SOLO para staging. NUNCA ejecutar en producción.
-- Idempotente: limpia registros previos antes de insertar.
--
-- Ejecutar: mysql -u user -p saggarag_staging < seed_test_enero_2024.sql
-- Teardown: descomentar el bloque al final y re-ejecutar.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------
-- EXPECTED OUTPUT (referencia — calculado a mano)
--
-- GET /api/financiero/gastos-admin?mes=1&anio=2024
--
-- total_facturado:       5674.00   (SUM total, solo cerrada/entregada)
-- total_iva:              424.00   (192 ord1 + 232 ord3)
-- ingresos_servicios:    1900.00   (800+0+500+600)
-- ingresos_mano_obra:    1400.00   (400+500+300+200)
-- ingresos_refacciones:  1950.00   (0+1300+650+0)
-- costo_refacciones:     1500.00   (1950/1.30 = 1500 exacto)
-- margen_refacciones:     450.00   (1950-1500)
-- ingresos_netos:        3750.00   (1900+1400+450)
-- total_admin:          14200.00   (5000+8000+1200)
-- gastos_ordenes_mes:     230.00   (150+0+80+0, excluye ord5 abierta)
-- utilidad_neta:       -10680.00   (3750-14200-230; IVA ya excluido de ingresos_netos)
--
-- NOTA: IVA NO se resta de utilidad_neta porque ingresos_netos ya lo excluye
-- por construcción (svc+mo+margen, nunca incluye IVA). Restarlo sería double-counting.
--
-- TRAMPAS DE VALIDACIÓN (si el filtro por estado falla):
--   total_facturado = 9502.00  si ord5 abierta se incluye en ingresos
--   gastos_ordenes  =  430.00  si ord5 abierta se incluye en gastos
-- ---------------------------------------------------------------

SET @seed_user = (SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id ASC LIMIT 1);
SELECT CONCAT('[SEED] Usando usuario id=', IFNULL(@seed_user, 'NULL — no hay admin en BD')) AS info;

-- ---------------------------------------------------------------
-- TEARDOWN previo (idempotente)
-- ---------------------------------------------------------------
DELETE go2 FROM gastos_orden go2
  INNER JOIN ordenes_servicio os ON go2.orden_id = os.id
  WHERE os.numero_orden LIKE 'TEST-2024-%';
DELETE ro FROM refacciones_orden ro
  INNER JOIN ordenes_servicio os ON ro.orden_id = os.id
  WHERE os.numero_orden LIKE 'TEST-2024-%';
DELETE so FROM servicios_orden so
  INNER JOIN ordenes_servicio os ON so.orden_id = os.id
  WHERE os.numero_orden LIKE 'TEST-2024-%';
DELETE FROM ordenes_servicio WHERE numero_orden LIKE 'TEST-2024-%';
DELETE FROM gastos_administrativos WHERE mes = 1 AND anio = 2024 AND concepto LIKE '[SEED]%';
DELETE FROM vehiculos WHERE notas = '[SEED] enero-2024';
DELETE FROM clientes  WHERE notas = '[SEED] enero-2024';

-- ---------------------------------------------------------------
-- CLIENTES (5)
-- ---------------------------------------------------------------
INSERT INTO clientes (nombre, telefono, notas) VALUES
  ('TEST-A Afinacion con IVA',   '5500000001', '[SEED] enero-2024'),
  ('TEST-B Frenos sin IVA',      '5500000002', '[SEED] enero-2024'),
  ('TEST-C Aceite con IVA',      '5500000003', '[SEED] enero-2024'),
  ('TEST-D Diagnostico sin IVA', '5500000004', '[SEED] enero-2024'),
  ('TEST-E Transmision ABIERTA', '5500000005', '[SEED] enero-2024');

SET @c1 = (SELECT id FROM clientes WHERE telefono = '5500000001');
SET @c2 = (SELECT id FROM clientes WHERE telefono = '5500000002');
SET @c3 = (SELECT id FROM clientes WHERE telefono = '5500000003');
SET @c4 = (SELECT id FROM clientes WHERE telefono = '5500000004');
SET @c5 = (SELECT id FROM clientes WHERE telefono = '5500000005');

-- ---------------------------------------------------------------
-- VEHÍCULOS (5)
-- ---------------------------------------------------------------
INSERT INTO vehiculos (cliente_id, marca, modelo, anio, notas) VALUES
  (@c1, 'Nissan',     'Sentra',  2015, '[SEED] enero-2024'),
  (@c2, 'Toyota',     'Corolla', 2018, '[SEED] enero-2024'),
  (@c3, 'Volkswagen', 'Jetta',   2017, '[SEED] enero-2024'),
  (@c4, 'Chevrolet',  'Aveo',    2016, '[SEED] enero-2024'),
  (@c5, 'Ford',       'Focus',   2019, '[SEED] enero-2024');

SET @v1 = (SELECT id FROM vehiculos WHERE cliente_id = @c1 AND notas = '[SEED] enero-2024');
SET @v2 = (SELECT id FROM vehiculos WHERE cliente_id = @c2 AND notas = '[SEED] enero-2024');
SET @v3 = (SELECT id FROM vehiculos WHERE cliente_id = @c3 AND notas = '[SEED] enero-2024');
SET @v4 = (SELECT id FROM vehiculos WHERE cliente_id = @c4 AND notas = '[SEED] enero-2024');
SET @v5 = (SELECT id FROM vehiculos WHERE cliente_id = @c5 AND notas = '[SEED] enero-2024');

-- ---------------------------------------------------------------
-- ÓRDENES (5)
-- ---------------------------------------------------------------

-- Orden 1: cerrada | CON IVA | sin refacciones | costo_interno=150
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id, problema_reportado,
  estado, fecha_ingreso,
  subtotal_servicios, subtotal_mano_obra, subtotal_refacciones,
  incluir_iva, iva, total, costo_interno_total
) VALUES (
  'TEST-2024-001', @c1, @v1, @seed_user, 'Afinacion mayor programada',
  'cerrada', '2024-01-15 10:00:00',
  800.00, 400.00, 0.00,
  1, 192.00, 1392.00, 150.00
);
SET @o1 = LAST_INSERT_ID();

-- Orden 2: cerrada | SIN IVA | refacciones sell=1300 (cost=1000) | sin costo_interno
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id, problema_reportado,
  estado, fecha_ingreso,
  subtotal_servicios, subtotal_mano_obra, subtotal_refacciones,
  incluir_iva, iva, total, costo_interno_total
) VALUES (
  'TEST-2024-002', @c2, @v2, @seed_user, 'Cambio de frenos delanteros',
  'cerrada', '2024-01-16 10:00:00',
  0.00, 500.00, 1300.00,
  0, 0.00, 1800.00, 0.00
);
SET @o2 = LAST_INSERT_ID();

-- Orden 3: entregada | CON IVA | refacciones sell=650 (cost=500) | costo_interno=80
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id, problema_reportado,
  estado, fecha_ingreso,
  subtotal_servicios, subtotal_mano_obra, subtotal_refacciones,
  incluir_iva, iva, total, costo_interno_total
) VALUES (
  'TEST-2024-003', @c3, @v3, @seed_user, 'Cambio de aceite y filtro, revision de suspension',
  'entregada', '2024-01-18 10:00:00',
  500.00, 300.00, 650.00,
  1, 232.00, 1682.00, 80.00
);
SET @o3 = LAST_INSERT_ID();

-- Orden 4: cerrada | SIN IVA | sin refacciones | sin costo_interno
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id, problema_reportado,
  estado, fecha_ingreso,
  subtotal_servicios, subtotal_mano_obra, subtotal_refacciones,
  incluir_iva, iva, total, costo_interno_total
) VALUES (
  'TEST-2024-004', @c4, @v4, @seed_user, 'Diagnostico computarizado y balanceo',
  'cerrada', '2024-01-22 10:00:00',
  600.00, 200.00, 0.00,
  0, 0.00, 800.00, 0.00
);
SET @o4 = LAST_INSERT_ID();

-- Orden 5: ABIERTA — NO debe aparecer en el balance
-- Si aparece: total_facturado=9502, gastos_ordenes=430 → bug confirmado
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id, problema_reportado,
  estado, fecha_ingreso,
  subtotal_servicios, subtotal_mano_obra, subtotal_refacciones,
  incluir_iva, iva, total, costo_interno_total
) VALUES (
  'TEST-2024-005', @c5, @v5, @seed_user, 'Reparacion de transmision (en proceso)',
  'abierta', '2024-01-25 10:00:00',
  0.00, 2000.00, 1300.00,
  1, 528.00, 3828.00, 200.00
);
SET @o5 = LAST_INSERT_ID();

-- ---------------------------------------------------------------
-- SERVICIOS_ORDEN
-- ---------------------------------------------------------------
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o1, 'servicio',  'Afinacion mayor',          800.00, 1, 800.00),
  (@o1, 'mano_obra', 'Mano de obra - afinacion', 400.00, 1, 400.00);

INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o2, 'mano_obra', 'Mano de obra - cambio de frenos', 500.00, 1, 500.00);

INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o3, 'servicio',  'Cambio de aceite y filtro', 300.00, 1, 300.00),
  (@o3, 'servicio',  'Revision de suspension',    200.00, 1, 200.00),
  (@o3, 'mano_obra', 'Mano de obra general',      300.00, 1, 300.00);

INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o4, 'servicio',  'Diagnostico computarizado',      400.00, 1, 400.00),
  (@o4, 'servicio',  'Balanceo y rotacion de llantas', 200.00, 1, 200.00),
  (@o4, 'mano_obra', 'Mano de obra diagnostico',       200.00, 1, 200.00);

INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o5, 'mano_obra', 'Reparacion de transmision', 2000.00, 1, 2000.00);

-- ---------------------------------------------------------------
-- REFACCIONES_ORDEN
-- precio_unitario = precio de VENTA al cliente (igual que en producción real)
-- subtotal        = precio_unitario * cantidad (total venta)
-- El costo se deriva como subtotal_refacciones / 1.30 (igual que hace el backend)
-- ---------------------------------------------------------------
-- Orden 2: sell=1300, costo derivado=1000, margen=300
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal) VALUES
  (@o2, 'Pastillas de freno Brembo (juego)', 1.00, 1300.00, 1300.00);

-- Orden 3: sell=650, costo derivado=500, margen=150
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal) VALUES
  (@o3, 'Filtro de aceite + aceite sintetico 4L (kit)', 1.00, 650.00, 650.00);

-- Orden 5 (abierta): sell=1300
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal) VALUES
  (@o5, 'Kit empaques transmision', 1.00, 1300.00, 1300.00);

-- ---------------------------------------------------------------
-- GASTOS ADMINISTRATIVOS — enero 2024
-- ---------------------------------------------------------------
INSERT INTO gastos_administrativos (mes, anio, concepto, monto, categoria, registrado_por) VALUES
  (1, 2024, '[SEED] Renta del local',      5000.00, 'renta',   @seed_user),
  (1, 2024, '[SEED] Salario mecanico',      8000.00, 'salario', @seed_user),
  (1, 2024, '[SEED] Agua, luz y telefono',  1200.00, 'servicio',@seed_user);

-- ---------------------------------------------------------------
-- VERIFICACIÓN RÁPIDA
-- ---------------------------------------------------------------
SELECT 'SEED insertado. Verificacion:' AS '';

SELECT
  COUNT(*)                            AS ordenes_total,
  SUM(estado = 'cerrada')             AS cerradas,
  SUM(estado = 'entregada')           AS entregadas,
  SUM(estado = 'abierta')             AS abiertas,
  SUM(CASE WHEN estado IN ('cerrada','entregada') THEN total ELSE 0 END) AS sum_total_validas
FROM ordenes_servicio
WHERE numero_orden LIKE 'TEST-2024-%';

SELECT '' AS '';
SELECT '=== EXPECTED OUTPUT del endpoint ===' AS '';
SELECT 'total_facturado'      AS campo,   5674.00 AS esperado UNION ALL
SELECT 'total_iva',                        424.00             UNION ALL
SELECT 'ingresos_servicios',              1900.00             UNION ALL
SELECT 'ingresos_mano_obra',              1400.00             UNION ALL
SELECT 'ingresos_refacciones',            1950.00             UNION ALL
SELECT 'costo_refacciones',               1500.00             UNION ALL
SELECT 'margen_refacciones',               450.00             UNION ALL
SELECT 'ingresos_netos',                  3750.00             UNION ALL
SELECT 'total_admin',                    14200.00             UNION ALL
SELECT 'gastos_ordenes_mes',               230.00             UNION ALL
SELECT 'utilidad_neta',                 -10680.00;

-- =====================================================================
-- TEARDOWN — descomentar y re-ejecutar para limpiar
-- =====================================================================
/*
DELETE go2 FROM gastos_orden go2
  INNER JOIN ordenes_servicio os ON go2.orden_id = os.id
  WHERE os.numero_orden LIKE 'TEST-2024-%';
DELETE ro FROM refacciones_orden ro
  INNER JOIN ordenes_servicio os ON ro.orden_id = os.id
  WHERE os.numero_orden LIKE 'TEST-2024-%';
DELETE so FROM servicios_orden so
  INNER JOIN ordenes_servicio os ON so.orden_id = os.id
  WHERE os.numero_orden LIKE 'TEST-2024-%';
DELETE FROM ordenes_servicio WHERE numero_orden LIKE 'TEST-2024-%';
DELETE FROM gastos_administrativos WHERE mes = 1 AND anio = 2024 AND concepto LIKE '[SEED]%';
DELETE FROM vehiculos WHERE notas = '[SEED] enero-2024';
DELETE FROM clientes  WHERE notas = '[SEED] enero-2024';
SELECT 'Teardown enero 2024 completo.' AS resultado;
*/
