-- =============================================================================
-- SEED: Datos de prueba para módulo financiero — Staging SAG Garage
-- Cubre semanas: 4-10 mayo 2026 y 11-17 mayo 2026
-- Incluye: órdenes abiertas/cerradas, caja chica, sueldos, pagos fijos, gastos
--
-- INSTRUCCIONES:
--   1. Abre phpMyAdmin en cPanel → BD saggarag_staging
--   2. Pestaña "SQL" → pega todo este script → Ejecutar
--   3. El script es idempotente con DELETE previo para no duplicar en reruns
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- LIMPIEZA de datos de prueba anteriores (por concepto único)
-- ============================================================
DELETE FROM caja_chica         WHERE fecha BETWEEN '2026-05-04' AND '2026-05-17';
DELETE FROM gastos_administrativos WHERE anio = 2026 AND mes = 5;
DELETE FROM empleados_sueldos  WHERE nombre IN ('Mayte Macías','Carlos Castillo','Markus Fabela','Roberto Zamora','Karla Betzbae');
DELETE FROM pagos_fijos        WHERE concepto IN ('Renta + Agua','Internet Telmex','Publicación Facebook','Préstamo Taller','AkLabs - Marco');
DELETE FROM refacciones_orden  WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'SEED-%');
DELETE FROM servicios_orden    WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'SEED-%');
DELETE FROM ordenes_servicio   WHERE numero_orden LIKE 'SEED-%';
DELETE FROM vehiculos          WHERE numero_serie LIKE 'SEED-%';
DELETE FROM clientes           WHERE rfc LIKE 'SEED%';

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- VARIABLES DE APOYO (usuario admin de referencia)
-- ============================================================
-- Usamos una variable para el usuario_id admin
SET @admin_id = (SELECT id FROM usuarios WHERE rol = 'admin' OR role = 'admin' LIMIT 1);
-- Fallback por si la columna se llama diferente
SET @admin_id = COALESCE(@admin_id, (SELECT id FROM usuarios LIMIT 1));

-- ============================================================
-- 1. CLIENTES (6 ficticios pero realistas)
-- ============================================================
INSERT INTO clientes (nombre, telefono, rfc, notas, activo) VALUES
  ('Roberto Sánchez Torres',    '+5215512345601', 'SEEDRST', 'Cliente frecuente - BMW negro', 1),
  ('Alejandro Ramírez López',   '+5215512345602', 'SEEDARL', 'Jetta rojo 2016', 1),
  ('Karen Villegas Mora',       '+5215512345603', 'SEEDKVM', 'Mazda CX5 - suspensión', 1),
  ('Fernando Díaz Cruz',        '+5215512345604', 'SEEDFDC', 'Ford Focus - clutch', 1),
  ('Dulce María Anguiano',      '+5215512345605', 'SEEDDMA', 'Mercedes Blanco - servicio mayor', 1),
  ('Jorge Ignacio Cabrera',     '+5215512345606', 'SEEDJIC', 'Toyota Yaris - verificación', 1);

-- Guardar IDs
SET @cli_1 = LAST_INSERT_ID();
SET @cli_2 = @cli_1 + 1;
SET @cli_3 = @cli_1 + 2;
SET @cli_4 = @cli_1 + 3;
SET @cli_5 = @cli_1 + 4;
SET @cli_6 = @cli_1 + 5;

-- ============================================================
-- 2. VEHÍCULOS
-- ============================================================
INSERT INTO vehiculos (cliente_id, marca, modelo, anio, color, placas, numero_serie, kilometraje) VALUES
  (@cli_1, 'BMW',        '320i',      2019, 'Negro',   'MBT624C', 'SEED-BMW001',  82400),
  (@cli_2, 'Volkswagen', 'Jetta',     2016, 'Rojo',    'NDV429A', 'SEED-VW0002',  106273),
  (@cli_3, 'Mazda',      'CX-5',      2019, 'Gris',    'PZH496D', 'SEED-MZD003', 171786),
  (@cli_4, 'Ford',       'Focus',     2015, 'Blanco',  'LVC671A', 'SEED-FRD004',  98500),
  (@cli_5, 'Mercedes',   'Clase C',   2020, 'Blanco',  'PZB870A', 'SEED-MBC005',  45200),
  (@cli_6, 'Toyota',     'Yaris',     2017, 'Gris',    'NZR083B', 'SEED-TYT006',  88900);

SET @veh_1 = LAST_INSERT_ID();
SET @veh_2 = @veh_1 + 1;
SET @veh_3 = @veh_1 + 2;
SET @veh_4 = @veh_1 + 3;
SET @veh_5 = @veh_1 + 4;
SET @veh_6 = @veh_1 + 5;

-- ============================================================
-- 3. ÓRDENES DE SERVICIO
--    Semana 1 (4-10 mayo): 4 órdenes — 2 cerradas S1, 2 abiertas que cierran S2
--    Semana 2 (11-17 mayo): 3 órdenes nuevas + cierre de las 2 abiertas de S1
-- ============================================================

-- ---- SEMANA 1: CERRADAS ESA MISMA SEMANA ----

-- SEED-001: BMW — Full service + frenos delanteros
-- Mano de obra: $2,500 | Refacciones: $5,200 cobradas (costo real: $4,000) | Total: $7,700
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado, diagnostico,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo, fecha_anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-001', @cli_1, @veh_1, @admin_id,
  'Servicio completo + ruido en frenos delanteros', 'Balatas y discos delanteros desgastados',
  2500.00, 0.00, 5200.00,
  0.00, 0, 0.00, 7700.00, 3000.00, '2026-05-05',
  'entregada', '2026-05-05 09:00:00', '2026-05-06 17:00:00', '2026-05-06 18:00:00'
);
SET @ord_1 = LAST_INSERT_ID();

INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@ord_1, 'mano_obra', 'Full service motor', 1500.00, 1, 1500.00),
  (@ord_1, 'mano_obra', 'Cambio frenos delanteros', 1000.00, 1, 1000.00);

INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@ord_1, 'Aceite sintético 5W40 (5 litros)', 1, 975.00, 975.00, 'SAFE'),
  (@ord_1, '2 Discos delanteros SAFE', 2, 1950.00, 3900.00, 'SAFE'),
  (@ord_1, 'Balatas delanteras Textar', 1, 325.00, 325.00, 'SAFE');
-- costo_refacciones real = 5200 / 1.30 = $4,000

-- SEED-002: Jetta — Servicio sin bujías
-- Mano de obra: $0 | Servicios: $3,000 | Refacciones: $390 | Total: $3,390
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-002', @cli_2, @veh_2, @admin_id,
  'Servicio de mantenimiento preventivo sin bujías',
  0.00, 3000.00, 390.00,
  0.00, 0, 0.00, 3390.00, 0.00,
  'entregada', '2026-05-07 10:00:00', '2026-05-07 14:00:00', '2026-05-07 15:30:00'
);
SET @ord_2 = LAST_INSERT_ID();

INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@ord_2, 'servicio', 'Servicio preventivo sin bujías UGSA', 3000.00, 1, 3000.00);

INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@ord_2, 'Filtro de aceite + filtro de aire', 1, 390.00, 390.00, 'UGSA');
-- costo_refacciones real = 390 / 1.30 = $300

-- ---- SEMANA 1: ABIERTAS (CIERRAN EN SEMANA 2) ----

-- SEED-003: Mazda CX5 — Suspensión trasera + soporte derecho
-- Entra lunes 4 mayo, se entregan hasta 14 mayo. Anticipo $4,000 el día 4.
-- Total estimado: $8,850 (mano obra $3,400 + refas $5,450 cobradas)
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado, diagnostico,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo, fecha_anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-003', @cli_3, @veh_3, @admin_id,
  'Ruido en suspensión trasera y vibración en volante', 'Horquillas traseras y soporte derecho desgastados',
  3400.00, 0.00, 5450.00,
  0.00, 0, 0.00, 8850.00, 4000.00, '2026-05-04',
  'entregada', '2026-05-04 09:30:00', '2026-05-13 16:00:00', '2026-05-14 10:00:00'
);
-- fecha_ingreso = semana 1, fecha_entregada = semana 2 (esto es el caso de traspaso)
SET @ord_3 = LAST_INSERT_ID();

INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@ord_3, 'mano_obra', 'Cambio horquillas traseras', 1800.00, 1, 1800.00),
  (@ord_3, 'mano_obra', 'Cambio soporte motor derecho', 1000.00, 1, 1000.00),
  (@ord_3, 'mano_obra', 'Alineación y balanceo', 600.00, 1, 600.00);

INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@ord_3, '2 Horquillas traseras Autozone', 2, 1820.00, 3640.00, 'Autozone'),
  (@ord_3, 'Soporte motor derecho UGSA SYD', 1, 1810.00, 1810.00, 'UGSA');
-- costo_refacciones real = 5450 / 1.30 = $4,192.30

-- SEED-004: Ford Focus — Cambio de clutch completo
-- Entra miércoles 6 mayo, se entrega jueves 15 mayo. Anticipo $2,500 el día 6.
-- Total: $7,850 (mano obra $2,500 + refas $5,350 cobradas)
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado, diagnostico,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo, fecha_anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-004', @cli_4, @veh_4, @admin_id,
  'Clutch patinando, difícil meter primera', 'Disco, plato y collarín desgastados — requiere cambio completo',
  2500.00, 0.00, 5350.00,
  0.00, 0, 0.00, 7850.00, 2500.00, '2026-05-06',
  'entregada', '2026-05-06 11:00:00', '2026-05-14 15:00:00', '2026-05-15 09:00:00'
);
SET @ord_4 = LAST_INSERT_ID();

INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@ord_4, 'mano_obra', 'Cambio de clutch completo', 2500.00, 1, 2500.00);

INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@ord_4, 'Kit clutch LUK Autozone', 1, 5148.00, 5148.00, 'Autozone'),
  (@ord_4, 'Cilindro maestro Autozone', 1, 202.00, 202.00, 'Autozone');
-- costo_refacciones real = 5350 / 1.30 = $4,115.38

-- ---- SEMANA 2: NUEVAS ÓRDENES (abiertas y cerradas en S2) ----

-- SEED-005: Mercedes Blanco — Servicio mayor + soportes
-- Entra lunes 11, se entrega miércoles 13. Sin anticipo. Total: $20,000 — la orden bomba de la semana.
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado, diagnostico,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-005', @cli_5, @veh_5, @admin_id,
  'Servicio mayor + cambio soportes y diagnóstico electrónico', 'Servicio completo, soportes traseros desgastados',
  5000.00, 0.00, 15000.00,
  0.00, 0, 0.00, 20000.00, 0.00,
  'entregada', '2026-05-11 09:00:00', '2026-05-12 17:00:00', '2026-05-13 10:00:00'
);
SET @ord_5 = LAST_INSERT_ID();

INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@ord_5, 'mano_obra', 'Servicio mayor motor', 2500.00, 1, 2500.00),
  (@ord_5, 'mano_obra', 'Cambio soportes traseros', 1500.00, 1, 1500.00),
  (@ord_5, 'mano_obra', 'Diagnóstico electrónico completo', 1000.00, 1, 1000.00);

INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@ord_5, 'Soporte derecho SAFE', 1, 5850.00, 5850.00, 'SAFE'),
  (@ord_5, 'Soporte izquierdo SAFE', 1, 4550.00, 4550.00, 'SAFE'),
  (@ord_5, 'Aceite sintético Mercedes 7 litros', 1, 2600.00, 2600.00, 'SAFE'),
  (@ord_5, 'Filtros (aceite + aire + cabina)', 1, 2000.00, 2000.00, 'SAFE');
-- costo_refacciones real = 15000 / 1.30 = $11,538.46

-- SEED-006: Toyota Yaris — Verificación + aceite
-- Entra y se entrega el mismo día miércoles 14 mayo.
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-006', @cli_6, @veh_6, @admin_id,
  'Verificación EDOMEX + cambio de aceite',
  0.00, 4950.00, 776.00,
  0.00, 0, 0.00, 5726.00, 0.00,
  'entregada', '2026-05-14 08:00:00', '2026-05-14 13:00:00', '2026-05-14 14:00:00'
);
SET @ord_6 = LAST_INSERT_ID();

INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@ord_6, 'servicio', 'Servicio mayor con bujías UGSA', 3700.00, 1, 3700.00),
  (@ord_6, 'servicio', 'Servicio de verificación EDOMEX', 1250.00, 1, 1250.00);

INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@ord_6, 'Aceite + filtro de aceite Autozone', 1, 776.00, 776.00, 'Autozone');

-- SEED-007: VW Jetta abierta en semana 2, anticipo, AÚN NO cerrada
-- Representa trabajo activo en curso al final de la semana 11-17
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado, diagnostico,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo, fecha_anticipo,
  estado, fecha_ingreso
) VALUES (
  'SEED-007', @cli_2, @veh_2, @admin_id,
  'Falla en sensor MAF + cuerpo de aceleración', 'Requiere limpiar inyectores y reemplazar sensor MAF',
  1800.00, 0.00, 2340.00,
  0.00, 0, 0.00, 4140.00, 1500.00, '2026-05-16',
  'en_proceso', '2026-05-16 11:00:00'
);
SET @ord_7 = LAST_INSERT_ID();

INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@ord_7, 'mano_obra', 'Diagnóstico + limpieza inyectores + cuerpo aceleración', 1800.00, 1, 1800.00);

INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@ord_7, 'Sensor MAF Mercado Libre', 1, 1950.00, 1950.00, 'Mercado Libre'),
  (@ord_7, 'Carbuklean 2 piezas Autozone', 2, 195.00, 390.00, 'Autozone');
-- costo_refacciones real = 2340 / 1.30 = $1,800 (la semana muestra -$300 por ahora)

-- ============================================================
-- 4. CONFIGURACIÓN DE EMPLEADOS (sueldos base)
-- ============================================================
INSERT INTO empleados_sueldos (usuario_id, nombre, puesto, sueldo_diario, activo) VALUES
  (NULL, 'Mayte Macías',    'Administrativa',  780.00, 1),  -- $3,900/semana × 5
  (NULL, 'Carlos Castillo', 'Mecánico Senior', 800.00, 1),  -- $4,000/semana × 5
  (NULL, 'Markus Fabela',   'Mecánico',        500.00, 1),  -- $2,500/semana × 5
  (NULL, 'Roberto Zamora',  'Mecánico Junior', 400.00, 1),  -- $2,000/semana × 5
  (NULL, 'Karla Betzbae',   'Marketing',       200.00, 1);  -- $1,000/semana × 5
-- Total sueldos estimados semana: $13,400

-- ============================================================
-- 5. PAGOS FIJOS RECURRENTES
-- ============================================================
INSERT INTO pagos_fijos (concepto, monto, frecuencia, categoria, activo) VALUES
  ('Renta + Agua',            4575.00, 'semanal',  'renta',     1),
  ('Internet Telmex',          135.00, 'mensual',  'servicio',  1),
  ('Publicación Facebook',     500.00, 'mensual',  'marketing', 1),
  ('Préstamo Taller',         1650.00, 'semanal',  'otro',      1),
  ('AkLabs - Marco',          1000.00, 'semanal',  'servicio',  1);
-- Total fijos semana: 4575 + (135/4) + (500/4) = 4575 + 33.75 + 125 + 1650 + 1000 = $7,383.75

-- ============================================================
-- 6. GASTOS ADMINISTRATIVOS VARIABLES — Mayo 2026
-- ============================================================
INSERT INTO gastos_administrativos (mes, anio, concepto, monto, categoria, registrado_por) VALUES
  -- Semana 1 (4-10 mayo)
  (5, 2026, 'Gasolina camioneta Hyundai',    500.00, 'insumo', @admin_id),
  (5, 2026, 'Materiales limpieza (pinol, jalador, higienico)', 350.00, 'insumo', @admin_id),
  (5, 2026, 'Propinas proveedores (Autozone, UGSA)', 80.00, 'otro', @admin_id),
  (5, 2026, 'Garrafones agua (4)',            140.00, 'insumo', @admin_id),
  (5, 2026, 'Comida mecánicos viernes',       280.00, 'otro', @admin_id),
  -- Semana 2 (11-17 mayo)
  (5, 2026, 'Gasolina camioneta Hyundai S2',  500.00, 'insumo', @admin_id),
  (5, 2026, 'Tiempo aire celular taller',     200.00, 'servicio', @admin_id),
  (5, 2026, 'Basura (2 semanas)',              70.00, 'otro', @admin_id),
  (5, 2026, 'Carbuklean + desengrasante',     708.00, 'insumo', @admin_id),
  (5, 2026, 'Curso híbridos Markus',         3500.00, 'otro', @admin_id);
-- Total gastos variables mayo: ~$6,328

-- ============================================================
-- 7. CAJA CHICA — Mayte
--    Semana 1: Paco da fondo inicial + egresos normales
--    Semana 2: saldo arrastra, nuevos egresos
-- ============================================================

-- Semana 1 (4-10 mayo)
INSERT INTO caja_chica (fecha, tipo, concepto, monto) VALUES
  -- Lunes 4 mayo: Paco asigna fondo semanal
  ('2026-05-04', 'ingreso', 'Fondo semanal - Paco',              2000.00),
  -- Martes: recibió efectivo de cliente (Jetta - a cuenta)
  ('2026-05-05', 'ingreso', 'A cuenta SEED-001 BMW Roberto',     1500.00),
  -- Gastos de la semana
  ('2026-05-05', 'egreso',  'Gasolina camioneta',                 500.00),
  ('2026-05-06', 'egreso',  'Propina Autozone (2 visitas)',         40.00),
  ('2026-05-07', 'egreso',  'Basura lunes y jueves',               40.00),
  ('2026-05-07', 'egreso',  'Verificación Jetta - derechos CDMX',  770.00),
  ('2026-05-08', 'egreso',  'Garrafones agua x4',                  140.00),
  ('2026-05-09', 'egreso',  'Materiales limpieza',                 350.00),
  ('2026-05-09', 'egreso',  'Comida mecánicos viernes',            280.00);
-- Saldo cierre S1: 2000 + 1500 - 500 - 40 - 40 - 770 - 140 - 350 - 280 = $1,380

-- Semana 2 (11-17 mayo)
INSERT INTO caja_chica (fecha, tipo, concepto, monto) VALUES
  -- Lunes 11 mayo: fondo semanal nuevo de Paco
  ('2026-05-11', 'ingreso', 'Fondo semanal - Paco',              2000.00),
  -- Entradas de clientes en efectivo semana 2
  ('2026-05-13', 'ingreso', 'Pago Mercedes Dulce María (parcial)', 5000.00),
  -- Gastos semana 2
  ('2026-05-12', 'egreso',  'Gasolina camioneta',                  500.00),
  ('2026-05-12', 'egreso',  'Tiempo aire celular taller',          200.00),
  ('2026-05-13', 'egreso',  'Didi entrega Mercedes Col. Roma',     150.00),
  ('2026-05-14', 'egreso',  'Propina repartidor Autozone',          30.00),
  ('2026-05-14', 'egreso',  'Verificación Toyota EDOMEX derechos', 775.00),
  ('2026-05-15', 'egreso',  'Basura sábado',                        20.00),
  ('2026-05-15', 'egreso',  'Carbuklean Autozone',                 708.00);
-- Saldo semana 2: saldo_anterior ($1,380) + 2000 + 5000 - 500 - 200 - 150 - 30 - 775 - 20 - 708 = $6,997

-- ============================================================
-- VERIFICACIÓN RÁPIDA (opcional — descomenta para revisar)
-- ============================================================
-- SELECT 'Órdenes' as tabla, COUNT(*) as total FROM ordenes_servicio WHERE numero_orden LIKE 'SEED-%'
-- UNION ALL
-- SELECT 'Clientes', COUNT(*) FROM clientes WHERE rfc LIKE 'SEED%'
-- UNION ALL
-- SELECT 'Vehiculos', COUNT(*) FROM vehiculos WHERE numero_serie LIKE 'SEED-%'
-- UNION ALL
-- SELECT 'Empleados sueldos', COUNT(*) FROM empleados_sueldos
-- UNION ALL
-- SELECT 'Pagos fijos', COUNT(*) FROM pagos_fijos
-- UNION ALL
-- SELECT 'Caja chica S1', COUNT(*) FROM caja_chica WHERE fecha BETWEEN '2026-05-04' AND '2026-05-10'
-- UNION ALL
-- SELECT 'Caja chica S2', COUNT(*) FROM caja_chica WHERE fecha BETWEEN '2026-05-11' AND '2026-05-17';

-- ============================================================
-- RESUMEN DE LO QUE DEBERÍAS VER EN STAGING
-- ============================================================
-- SEMANA 4-10 MAYO:
--   Órdenes en tabla desglosada:
--     SEED-001 BMW       → completada S1 → venta $7,700  refas $4,000  ganancia $3,700
--     SEED-002 Jetta     → completada S1 → venta $3,390  refas $300    ganancia $3,090
--     SEED-003 Mazda     → EN PROCESO S1 → anticipo $4,000 (muestra inversión en refas)
--     SEED-004 Focus     → EN PROCESO S1 → anticipo $2,500 (muestra inversión en refas)
--
--   Caja chica S1: saldo $1,380 al cierre
--
-- SEMANA 11-17 MAYO:
--   Órdenes en tabla desglosada:
--     SEED-003 Mazda     → cerrada S2 → venta $8,850 (ingresó S1, cobró S2)
--     SEED-004 Focus     → cerrada S2 → venta $7,850
--     SEED-005 Mercedes  → cerrada S2 → venta $20,000 (la orden bomba)
--     SEED-006 Yaris     → cerrada S2 → venta $5,726
--     SEED-007 Jetta     → EN PROCESO → anticipo $1,500 (abierta viernes 16)
--
--   Caja chica S2: saldo_anterior $1,380 + entradas $7,000 - salidas $2,383 = $5,997
-- ============================================================
