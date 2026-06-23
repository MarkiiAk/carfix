-- =============================================================================
-- SEED v2: Datos realistas basados en semanas reales de Paco Gudiño
-- Semana 4-10 mayo 2026  → MEJOR semana (replica 13-17 abril 2026)
-- Semana 11-17 mayo 2026 → PEOR semana  (replica 16-21 marzo 2026)
--
-- INSTRUCCIONES: phpMyAdmin → saggarag_staging → SQL → pegar → Ejecutar
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- LIMPIEZA COMPLETA de datos de prueba anteriores
-- ============================================================
DELETE FROM caja_chica              WHERE fecha BETWEEN '2026-05-04' AND '2026-05-17';
DELETE FROM gastos_administrativos  WHERE anio = 2026 AND mes = 5;
DELETE FROM empleados_sueldos       WHERE nombre IN ('Mayte Macías','Carlos Castillo','Markus Fabela','Roberto Zamora','Karla Betzbae','Jorge Marín');
DELETE FROM pagos_fijos             WHERE concepto IN ('Renta + Agua','Internet Telmex','Publicación Facebook','Préstamo Taller','Pago iPhone Taller','AkLabs - Marco');
DELETE FROM refacciones_orden       WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'SEED-%');
DELETE FROM servicios_orden         WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'SEED-%');
DELETE FROM ordenes_servicio        WHERE numero_orden LIKE 'SEED-%';
DELETE FROM vehiculos               WHERE numero_serie LIKE 'SEED-%';
DELETE FROM clientes                WHERE rfc LIKE 'SEED%';

SET FOREIGN_KEY_CHECKS = 1;

SET @admin_id = (SELECT id FROM usuarios WHERE rol = 'admin' LIMIT 1);
SET @admin_id = COALESCE(@admin_id, (SELECT id FROM usuarios LIMIT 1));

-- ============================================================
-- 1. CLIENTES
-- ============================================================
INSERT INTO clientes (nombre, telefono, rfc, notas, activo) VALUES
  ('Dulce María Anguiano',     '+5215512340001', 'SEEDDMA', 'Mercedes negro — cliente grande', 1),
  ('Antonio Vargas Robles',    '+5215512340002', 'SEEDAVR', 'Ertiga 2022 — soportes', 1),
  ('Dulce Razo Hernández',     '+5215512340003', 'SEEDDRH', 'Tida 2011 — clutch completo', 1),
  ('Horte Calderon',           '+5215512340004', 'SEEDHCS', 'Spark 2017 — motor rectificado', 1),
  ('Sr Tlapaleria',            '+5215512340005', 'SEEDSTP', 'Pointer blanco — servicio + inyectores', 1),
  ('Bety Calderon',            '+5215512340006', 'SEEDBCG', 'Gol 2010 — batería', 1),
  ('Elsa Kia Sportage',        '+5215512340007', 'SEEDEKS', 'Kia Sportage — servicio domicilio', 1),
  ('Nicolas Urrieta',          '+5215512340008', 'SEEDNCU', 'Crafter 2024 — aceite y juntas', 1),
  ('Maestra Rocio',            '+5215512340009', 'SEEDMRC', 'Captiva — servicio menor', 1),
  ('Jorge Ignacio',            '+5215512340010', 'SEEDJIS', 'Sentra 2019 — servicio mayor', 1),
  ('Capitan Maldonado',        '+5215512340011', 'SEEDCMA', 'Captiva 2014 — reten original', 1),
  ('Nicolas Urrieta Sunray',   '+5215512340012', 'SEEDNSU', 'Sunray cargo — baleros prensa', 1),
  ('Enrique Escudero',         '+5215512340013', 'SEEDEEG', 'Mercedes GLC 2018 — frenos', 1),
  ('Nora Garcia',              '+5215512340014', 'SEEDNGA', 'Altima 2001 — servicio + frenos', 1),
  ('Bryan Zepeda',             '+5215512340015', 'SEEDBZC', 'Cupra — frenos 4 discos', 1),
  ('Sr Fusion 2016',           '+5215512340016', 'SEEDFSN', 'Fusion 2016 — cambio marcha', 1),
  ('Amiga Paco Aveo',          '+5215512340017', 'SEEDAPA', 'Aveo blanco — pintura', 1);

SET @c1  = LAST_INSERT_ID();
SET @c2  = @c1 + 1;
SET @c3  = @c1 + 2;
SET @c4  = @c1 + 3;
SET @c5  = @c1 + 4;
SET @c6  = @c1 + 5;
SET @c7  = @c1 + 6;
SET @c8  = @c1 + 7;
SET @c9  = @c1 + 8;
SET @c10 = @c1 + 9;
SET @c11 = @c1 + 10;
SET @c12 = @c1 + 11;
SET @c13 = @c1 + 12;
SET @c14 = @c1 + 13;
SET @c15 = @c1 + 14;
SET @c16 = @c1 + 15;
SET @c17 = @c1 + 16;

-- ============================================================
-- 2. VEHÍCULOS
-- ============================================================
INSERT INTO vehiculos (cliente_id, marca, modelo, anio, color, placas, numero_serie, kilometraje) VALUES
  (@c1,  'Mercedes',   'Clase C',       2020, 'Negro',  'PZB870A', 'SEED-MB001', 45200),
  (@c2,  'Suzuki',     'Ertiga',        2022, 'Blanco', 'PBU5583', 'SEED-SZ002', 19314),
  (@c3,  'Volkswagen', 'Tida',          2011, 'Gris',   'LRX8843', 'SEED-VW003', 155000),
  (@c4,  'Chevrolet',  'Spark Clasico', 2017, 'Verde',  'NZW5440', 'SEED-CH004', 80060),
  (@c5,  'Volkswagen', 'Pointer',       2006, 'Blanco', 'NTD040A', 'SEED-VW005', 198000),
  (@c6,  'Volkswagen', 'Gol',           2010, 'Gris',   'NPA313A', 'SEED-VW006', 142000),
  (@c7,  'Kia',        'Sportage',      2018, 'Rojo',   'LJK3344', 'SEED-KI007', 88000),
  (@c8,  'Volkswagen', 'Crafter',       2024, 'Blanco', 'KP6813A', 'SEED-VW008', 65341),
  (@c9,  'Chevrolet',  'Captiva',       2014, 'Negra',  'NPP235B', 'SEED-CH009', 104310),
  (@c10, 'Nissan',     'Sentra',        2019, 'Blanco', 'RRY1122', 'SEED-NS010', 38400),
  (@c11, 'Chevrolet',  'Captiva',       2014, 'Blanca', 'RTX8821', 'SEED-CH011', 91000),
  (@c12, 'JAC',        'Sunray Cargo',  2022, 'Blanco', 'LE29055', 'SEED-JC012', 127556),
  (@c13, 'Mercedes',   'GLC',           2018, 'Gris',   'AAL7722', 'SEED-MB013', 78000),
  (@c14, 'Nissan',     'Altima',        2001, 'Rojo',   'PPQ5544', 'SEED-NS014', 187000),
  (@c15, 'CUPRA',      'Formentor',     2022, 'Negro',  'RTU4433', 'SEED-CU015', 21000),
  (@c16, 'Ford',       'Fusion',        2016, 'Gris',   'LUC8821', 'SEED-FD016', 143000),
  (@c17, 'Chevrolet',  'Aveo',          2015, 'Blanco', 'MHJ9921', 'SEED-CH017', 88000);

SET @v1  = LAST_INSERT_ID();
SET @v2  = @v1 + 1;
SET @v3  = @v1 + 2;
SET @v4  = @v1 + 3;
SET @v5  = @v1 + 4;
SET @v6  = @v1 + 5;
SET @v7  = @v1 + 6;
SET @v8  = @v1 + 7;
SET @v9  = @v1 + 8;
SET @v10 = @v1 + 9;
SET @v11 = @v1 + 10;
SET @v12 = @v1 + 11;
SET @v13 = @v1 + 12;
SET @v14 = @v1 + 13;
SET @v15 = @v1 + 14;
SET @v16 = @v1 + 15;
SET @v17 = @v1 + 16;

-- ==============================================================
-- SEMANA 4-10 MAYO 2026 — MEJOR SEMANA
-- Replica 13-17 abril 2026: Ingresos ~$66k | Ganancia neta ~$26k
-- La estrella: Mercedes Dulce María $20,000 con $520 de costo
-- ==============================================================

-- SEED-001: Mercedes Negro Dulce María — LA ORDEN BOMBA
-- $20,000 cobrado | $520 costo refas | ganancia bruta $19,480
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado, diagnostico,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-001', @c1, @v1, @admin_id,
  'Servicio mayor + soportes traseros + diagnóstico electrónico completo',
  'Soportes motor desgastados, servicio preventivo completo, diagnóstico',
  5000.00, 0.00, 15000.00, 0.00, 0, 0.00, 20000.00, 0.00,
  'entregada', '2026-05-06 09:00:00', '2026-05-06 16:00:00', '2026-05-06 17:00:00'
);
SET @o1 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o1, 'mano_obra', 'Servicio mayor motor completo', 2500.00, 1, 2500.00),
  (@o1, 'mano_obra', 'Cambio 2 soportes traseros', 1500.00, 1, 1500.00),
  (@o1, 'mano_obra', 'Diagnóstico electrónico', 1000.00, 1, 1000.00);
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o1, 'Soporte motor derecho SAFE', 1, 5850.00, 5850.00, 'SAFE'),
  (@o1, 'Soporte motor izquierdo SAFE', 1, 4550.00, 4550.00, 'SAFE'),
  (@o1, 'Aceite sintético Mercedes 7 Lts', 1, 2600.00, 2600.00, 'SAFE'),
  (@o1, 'Filtros aceite + aire + cabina SAFE', 1, 2000.00, 2000.00, 'SAFE');

-- SEED-002: Ertiga 2022 Antonio Vargas — Servicio + 2 soportes
-- $10,050 cobrado | $3,094 costo refas | ganancia $6,956
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-002', @c2, @v2, @admin_id,
  'Servicio sin bujías + vibración al acelerar — soportes motor',
  900.00, 2900.00, 6250.00, 0.00, 0, 0.00, 10050.00, 0.00,
  'entregada', '2026-05-05 10:00:00', '2026-05-05 17:00:00', '2026-05-05 18:00:00'
);
SET @o2 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o2, 'servicio', 'Servicio sin bujías Autozone', 2900.00, 1, 2900.00),
  (@o2, 'mano_obra', 'Cambio 2 soportes motor', 900.00, 1, 900.00);
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o2, 'Soporte derecho Ramírez DAI', 1, 2950.00, 2950.00, 'Ramírez DAI'),
  (@o2, 'Soporte lado caja Ramírez DAI', 1, 3300.00, 3300.00, 'Ramírez DAI');

-- SEED-003: Tida 2011 Dulce Razo — Clutch completo + soporte + banda
-- $11,160 cobrado | $6,025 costo refas | ganancia $5,135
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado, diagnostico,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo, fecha_anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-003', @c3, @v3, @admin_id,
  'Clutch patinando no entran velocidades', 'Cambio kit clutch completo + soporte inferior + banda',
  2800.00, 0.00, 8360.00, 0.00, 0, 0.00, 11160.00, 3000.00, '2026-05-04',
  'entregada', '2026-05-04 11:00:00', '2026-05-07 15:00:00', '2026-05-07 16:30:00'
);
SET @o3 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o3, 'mano_obra', 'Cambio clutch completo + soporte + banda', 2800.00, 1, 2800.00);
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o3, 'Clutch LUK Autozone', 1, 3960.00, 3960.00, 'Autozone'),
  (@o3, 'Cilindro maestro Autozone', 1, 1150.00, 1150.00, 'Autozone'),
  (@o3, 'Banda accesorios Autozone', 1, 400.00, 400.00, 'Autozone'),
  (@o3, 'Soporte inferior UGSA', 1, 950.00, 950.00, 'UGSA'),
  (@o3, 'Chicote velocidades Bucareli', 1, 1900.00, 1900.00, 'Bucareli');

-- SEED-004: Spark Horte — Rectificado motor + inyectores
-- $11,300 cobrado | $4,762 costo refas | ganancia $6,538
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado, diagnostico,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo, fecha_anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-004', @c4, @v4, @admin_id,
  'Motor con fuga de aceite y falla en inyectores',
  'Rectificadora San Mateo + reparación 2 inyectores + kit distribución',
  5500.00, 0.00, 5800.00, 0.00, 0, 0.00, 11300.00, 5000.00, '2026-05-04',
  'entregada', '2026-05-04 09:00:00', '2026-05-08 17:00:00', '2026-05-09 10:00:00'
);
SET @o4 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o4, 'mano_obra', 'Mano de obra taller + reparación 2 inyectores', 3500.00, 1, 3500.00),
  (@o4, 'mano_obra', 'Rectificadora San Mateo', 2000.00, 1, 2000.00);
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o4, 'Junta tapa punterias Autozone', 1, 350.00, 350.00, 'Autozone'),
  (@o4, 'Manguera refrigerante UGSA', 1, 750.00, 750.00, 'UGSA'),
  (@o4, 'Junta cabeza Autozone', 1, 750.00, 750.00, 'Ramirez'),
  (@o4, 'Anticongelante Autozone', 1, 450.00, 450.00, 'Autozone'),
  (@o4, 'Kit distribución Autozone', 1, 3500.00, 3500.00, 'Autozone');

-- SEED-005: Pointer blanco Sr Tlapaleria — Servicio bujías + inyectores + alternador
-- $5,900 cobrado | $2,355 costo refas | ganancia $3,545
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-005', @c5, @v5, @admin_id,
  'Servicio con bujías + falla inyectores + reparación alternador',
  0.00, 2800.00, 3100.00, 0.00, 0, 0.00, 5900.00, 0.00,
  'entregada', '2026-05-07 10:00:00', '2026-05-07 16:00:00', '2026-05-07 17:00:00'
);
SET @o5 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o5, 'servicio', 'Servicio con bujías Autozone', 2800.00, 1, 2800.00);
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o5, 'Inyectores Juventino Rosas', 1, 1950.00, 1950.00, 'Juventino Rosas'),
  (@o5, 'Refacciones alternador + borne', 1, 1150.00, 1150.00, 'Hernández');

-- SEED-006: Gol 2010 Bety Calderon — Batería
-- $2,950 cobrado | $1,800 costo refas | ganancia $1,150
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-006', @c6, @v6, @admin_id,
  'No arranca — batería',
  0.00, 1150.00, 1800.00, 0.00, 0, 0.00, 2950.00, 0.00,
  'entregada', '2026-05-09 11:00:00', '2026-05-09 13:30:00', '2026-05-09 14:00:00'
);
SET @o6 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o6, 'servicio', 'Cambio batería + diagnóstico carga', 1150.00, 1, 1150.00);
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o6, 'Batería Autozone 18 meses garantía', 1, 1800.00, 1800.00, 'Autozone');

-- SEED-007: Kia Sportage Elsa — Servicio domicilio
-- $3,250 cobrado | $0 costo refas | ganancia $3,250
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-007', @c7, @v7, @admin_id,
  'Servicio sin bujías a domicilio',
  0.00, 3250.00, 0.00, 0.00, 0, 0.00, 3250.00, 0.00,
  'entregada', '2026-05-08 09:00:00', '2026-05-08 13:00:00', '2026-05-08 14:00:00'
);
SET @o7 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o7, 'servicio', 'Servicio sin bujías UGSA', 3000.00, 1, 3000.00),
  (@o7, 'servicio', 'Recolección y entrega a domicilio', 250.00, 1, 250.00);

-- SEED-008: Crafter Nico — Cambio aceite
-- $2,080 cobrado | $0 costo refas | ganancia $2,080
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-008', @c8, @v8, @admin_id,
  'Cambio de aceite + 2 juntas',
  0.00, 2080.00, 0.00, 0.00, 0, 0.00, 2080.00, 0.00,
  'entregada', '2026-05-06 09:00:00', '2026-05-06 11:00:00', '2026-05-06 11:30:00'
);
SET @o8 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o8, 'servicio', 'Cambio de aceite Crafter diesel', 2080.00, 1, 2080.00);

-- ==============================================================
-- SEMANA 11-17 MAYO 2026 — PEOR SEMANA
-- Replica 16-21 marzo 2026: Ingresos ~$36k | Ganancia -$6k
-- Spark del taller, Captiva y Sunray sin cobrar se comieron la semana
-- ==============================================================

-- SEED-009: Sentra 2019 Jorge Ignacio — Servicio mayor con bujías
-- $4,900 cobrado | $1,385 costo refas | ganancia $3,515
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-009', @c10, @v10, @admin_id,
  'Servicio mayor con bujías + mantenimiento preventivo',
  0.00, 3600.00, 1300.00, 0.00, 0, 0.00, 4900.00, 0.00,
  'entregada', '2026-05-11 09:00:00', '2026-05-11 15:00:00', '2026-05-11 16:00:00'
);
SET @o9 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o9, 'servicio', 'Servicio mayor con bujías Autozone', 3600.00, 1, 3600.00);
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o9, 'Acumulador LTH 3 años ElectricPower', 1, 1300.00, 1300.00, 'ElectricPower');

-- SEED-010: Captiva Capitán Maldonado — Reten solo gasto, sin cobrar
-- EN PROCESO: compraron refacción pero el carro sigue en taller
-- Gasto $1,236 | Sin ingreso → semana con hoyo
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado, diagnostico,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso
) VALUES (
  'SEED-010', @c11, @v11, @admin_id,
  'Fuga de aceite — reten flecha', 'Requiere reten original UGSA + liga distribución',
  0.00, 0.00, 1236.00, 0.00, 0, 0.00, 4500.00, 0.00,
  'en_proceso', '2026-05-11 10:00:00'
);
SET @o10 = LAST_INSERT_ID();
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o10, 'Reten original UGSA', 1, 950.00, 950.00, 'UGSA'),
  (@o10, 'Junta tapa distribución Autozone', 1, 286.00, 286.00, 'Autozone');

-- SEED-011: Sunray Cargo Nicolas — Baleros prensa Saúl solo gasto
-- EN PROCESO: fueron al tornero, sin cobrar nada
-- Gasto $962 | Sin ingreso
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso
) VALUES (
  'SEED-011', @c12, @v12, @admin_id,
  'Ruido en rueda delantera — baleros',
  0.00, 0.00, 962.00, 0.00, 0, 0.00, 3500.00, 0.00,
  'en_proceso', '2026-05-12 09:00:00'
);
SET @o11 = LAST_INSERT_ID();
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o11, 'Balero Baleromex', 1, 242.00, 242.00, 'Baleromex'),
  (@o11, 'Trabajo en prensa Saúl', 1, 720.00, 720.00, 'Saúl torno');

-- SEED-012: Mercedes GLC Enrique Escudero — Frenos delanteros + brazo
-- $8,114 cobrado | $4,390 costo refas | ganancia $3,724
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-012', @c13, @v13, @admin_id,
  'Frenos delanteros + brazo recto inferior derecho',
  2100.00, 0.00, 6014.00, 0.00, 0, 0.00, 8114.00, 0.00,
  'entregada', '2026-05-12 09:00:00', '2026-05-13 16:00:00', '2026-05-13 17:00:00'
);
SET @o12 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o12, 'mano_obra', 'Frenos delanteros + brazo recto inferior', 2100.00, 1, 2100.00);
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o12, 'Balatas Unibrakes delanteras', 1, 2964.00, 2964.00, 'Unibrakes'),
  (@o12, 'Sensor balata', 1, 350.00, 350.00, 'SAFE'),
  (@o12, 'Brazo recto inferior SAFE', 1, 2100.00, 2100.00, 'SAFE'),
  (@o12, 'Didi Unibrakes a taller', 1, 600.00, 600.00, 'Didi');

-- SEED-013: Altima 2001 Nora García — Servicio sin bujías + frenos
-- $8,094 cobrado | $3,480 costo refas | ganancia $4,614
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-013', @c14, @v14, @admin_id,
  'Servicio sin bujías + ruido frenos delanteros',
  750.00, 3600.00, 3744.00, 0.00, 0, 0.00, 8094.00, 0.00,
  'entregada', '2026-05-13 10:00:00', '2026-05-13 16:00:00', '2026-05-14 10:00:00'
);
SET @o13 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o13, 'servicio', 'Servicio sin bujías', 3600.00, 1, 3600.00),
  (@o13, 'mano_obra', 'Frenos delanteros', 750.00, 1, 750.00);
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o13, '2 Discos delanteros BEST', 2, 1222.00, 2444.00, 'BEST'),
  (@o13, 'Balatas delanteras BEST', 1, 1300.00, 1300.00, 'BEST');

-- SEED-014: Cupra Bryan Zepeda — Frenos 4 ruedas (margen delgado)
-- $11,750 cobrado | $7,840 costo refas | ganancia $3,910
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo, fecha_anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-014', @c15, @v15, @admin_id,
  'Frenos delanteros y traseros completos',
  1350.00, 0.00, 10400.00, 0.00, 0, 0.00, 11750.00, 5000.00, '2026-05-14',
  'entregada', '2026-05-14 09:00:00', '2026-05-15 15:00:00', '2026-05-15 16:00:00'
);
SET @o14 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o14, 'mano_obra', 'Frenos delanteros + traseros', 1350.00, 1, 1350.00);
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o14, '2 Discos delanteros BEST', 2, 2400.00, 4800.00, 'BEST'),
  (@o14, '2 Discos traseros BEST', 2, 2050.00, 4100.00, 'BEST'),
  (@o14, 'Balatas traseras BEST', 1, 1500.00, 1500.00, 'BEST');

-- SEED-015: Fusion 2016 — marcha Autozone
-- $3,450 cobrado | $1,218 costo refas | ganancia $2,232
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso, fecha_completada, fecha_entregada
) VALUES (
  'SEED-015', @c16, @v16, @admin_id,
  'No arranca — marcha Autozone',
  1000.00, 0.00, 2450.00, 0.00, 0, 0.00, 3450.00, 0.00,
  'entregada', '2026-05-15 09:00:00', '2026-05-15 13:00:00', '2026-05-15 14:00:00'
);
SET @o15 = LAST_INSERT_ID();
INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal) VALUES
  (@o15, 'mano_obra', 'Cambio de marcha', 1000.00, 1, 1000.00);
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o15, 'Marcha 2LTS Autozone', 1, 2450.00, 2450.00, 'Autozone');

-- SEED-016: Aveo blanco — pintura SIN COBRAR (orden abierta, ya gastaron)
-- EN PROCESO: pintor Armando ya cobró pero cliente aún no paga
-- Gasto $1,440 | Anticipo $0 → hoyo en la semana
INSERT INTO ordenes_servicio (
  numero_orden, cliente_id, vehiculo_id, usuario_id,
  problema_reportado,
  subtotal_mano_obra, subtotal_servicios, subtotal_refacciones,
  descuento, incluir_iva, iva, total, anticipo,
  estado, fecha_ingreso
) VALUES (
  'SEED-016', @c17, @v17, @admin_id,
  'Baño de pintura completo + salpicadera derecha',
  0.00, 0.00, 1440.00, 0.00, 0, 0.00, 2400.00, 0.00,
  'en_proceso', '2026-05-12 11:00:00'
);
SET @o16 = LAST_INSERT_ID();
INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal, proveedor) VALUES
  (@o16, 'Material pintura + pago pintor Armando', 1, 1440.00, 1440.00, 'Armando pintor');

-- ============================================================
-- 3. EMPLEADOS SUELDOS
-- fecha_inicio='2026-01-01' cubre todos los períodos históricos del sistema
-- ============================================================
INSERT INTO empleados_sueldos (usuario_id, nombre, puesto, sueldo_diario, fecha_inicio, activo) VALUES
  (NULL, 'Mayte Macías',    'Administrativa',  780.00, '2026-01-01', 1),
  (NULL, 'Carlos Castillo', 'Mecánico Senior', 800.00, '2026-01-01', 1),
  (NULL, 'Markus Fabela',   'Mecánico',        500.00, '2026-01-01', 1),
  (NULL, 'Roberto Zamora',  'Mecánico Junior', 400.00, '2026-01-01', 1),
  (NULL, 'Karla Betzbae',   'Marketing',       200.00, '2026-01-01', 1),
  (NULL, 'Jorge Marín',     'Contador',        100.00, '2026-01-01', 1);
-- $2,780/día × 5 días = $13,900/semana

-- ============================================================
-- 4. PAGOS FIJOS
-- AkLabs - Marco: fecha_inicio='2026-05-01' porque antes era pago puntual,
-- no recurrente. Todos los demás desde '2026-01-01'.
-- ============================================================
INSERT INTO pagos_fijos (concepto, monto, fecha_inicio, frecuencia, categoria, activo) VALUES
  ('Renta + Agua',        4575.00, '2026-01-01', 'semanal',  'renta',    1),
  ('Internet Telmex',      135.00, '2026-01-01', 'mensual',  'servicio', 1),
  ('Préstamo Taller',     1650.00, '2026-01-01', 'semanal',  'otro',     1),
  ('Pago iPhone Taller',  1000.00, '2026-01-01', 'semanal',  'servicio', 1),
  ('AkLabs - Marco',      1000.00, '2026-05-01', 'semanal',  'servicio', 1);
-- Semanal (desde mayo): 4575 + 1650 + 1000 + 1000 + 135/4 = $8,258.75
-- Nota: 'Publicación Facebook' eliminada — Paco la canceló. No era pago real en esas semanas.

-- ============================================================
-- 5. GASTOS ADMINISTRATIVOS VARIABLES — Mayo 2026
-- ============================================================
INSERT INTO gastos_administrativos (mes, anio, concepto, monto, categoria, registrado_por) VALUES
  -- Semana buena (4-10 mayo): gastos normales
  (5, 2026, 'Gasolina camioneta Hyundai S1',       500.00, 'insumo',  @admin_id),
  (5, 2026, 'Materiales limpieza taller',           350.00, 'insumo',  @admin_id),
  (5, 2026, 'Propinas Autozone y UGSA S1',           80.00, 'otro',    @admin_id),
  (5, 2026, 'Garrafones agua x4',                   140.00, 'insumo',  @admin_id),
  (5, 2026, 'Comida mecánicos viernes S1',          280.00, 'otro',    @admin_id),
  -- Semana mala (11-17 mayo): Spark del taller arruinó la semana
  (5, 2026, 'Gasolina camioneta Hyundai S2',        500.00, 'insumo',  @admin_id),
  (5, 2026, 'Llantas Spark taller (4)',            1900.00, 'otro',    @admin_id),
  (5, 2026, 'Grúa para recoger Spark averiado',   2000.00, 'otro',    @admin_id),
  (5, 2026, 'Refacciones Spark ProOne (motor)',    2409.00, 'insumo',  @admin_id),
  (5, 2026, 'Lona nueva para taller',              1440.00, 'otro',    @admin_id),
  (5, 2026, 'Tiempo aire celular taller',           200.00, 'servicio',@admin_id),
  (5, 2026, 'Mordida policía — camión gasolina',    400.00, 'otro',    @admin_id);
-- Total mayo: $10,199

-- ============================================================
-- 6. CAJA CHICA — Mayte
-- ============================================================

-- Semana buena (4-10 mayo): Fondo + ingresos clientes, pocos egresos
INSERT INTO caja_chica (fecha, tipo, concepto, monto) VALUES
  ('2026-05-04', 'ingreso', 'Fondo semanal - Paco',                    2000.00),
  ('2026-05-05', 'ingreso', 'A cuenta Tida Dulce Razo (efectivo)',      3000.00),
  ('2026-05-06', 'ingreso', 'Efectivo Mercedes Dulce María (parcial)',  5000.00),
  ('2026-05-05', 'egreso',  'Gasolina camioneta lunes',                  500.00),
  ('2026-05-06', 'egreso',  'Propinas Autozone 2 visitas',                80.00),
  ('2026-05-07', 'egreso',  'Basura jueves',                              20.00),
  ('2026-05-07', 'egreso',  'Garrafones agua x4',                        140.00),
  ('2026-05-08', 'egreso',  'Materiales limpieza',                       350.00),
  ('2026-05-09', 'egreso',  'Comida mecánicos viernes',                  280.00);
-- Saldo cierre S1: 2000+3000+5000 - 500-80-20-140-350-280 = $8,630

-- Semana mala (11-17 mayo): Spark se comió la caja
INSERT INTO caja_chica (fecha, tipo, concepto, monto) VALUES
  ('2026-05-11', 'ingreso', 'Fondo semanal - Paco',                    2000.00),
  ('2026-05-11', 'egreso',  'Gasolina Spark para pruebas motor',         675.00),
  ('2026-05-12', 'egreso',  'Grúa recoger Spark averiado',             2000.00),
  ('2026-05-12', 'egreso',  'Lona nueva taller',                        1440.00),
  ('2026-05-13', 'egreso',  'Refacciones Spark ProOne',                 2409.00),
  ('2026-05-14', 'egreso',  'Tiempo aire celular',                        200.00),
  ('2026-05-14', 'egreso',  'Mordida policía',                            400.00),
  ('2026-05-15', 'egreso',  'Materiales limpieza S2',                    350.00),
  ('2026-05-15', 'egreso',  'Basura sábado',                              20.00);
-- Saldo S2: anterior $8,630 + $2,000 - $7,494 = $3,136

-- ============================================================
-- RESUMEN ESPERADO
-- ============================================================
-- SEMANA 4-10 MAYO (MEJOR):
--   8 órdenes cerradas: Mercedes $20k (bomba) + Ertiga $10k + Spark $11.3k
--   + Tida $11.2k + Pointer $5.9k + Kia $3.25k + Crafter $2.1k + Gol $2.95k
--   Total facturado: ~$66,690
--   Costo refacciones: ~$18,900 (/ 1.30 del total cobrado)
--   Ingreso neto: ~$47,790
--   Sueldos: $13,400 | Fijos: $7,384 | Variables (prorateado): ~$1,350
--   GANANCIA NETA ESTIMADA: ~+$25,656 (BUENA SEMANA)
--
-- SEMANA 11-17 MAYO (PEOR):
--   5 cerradas (Sentra, GLC, Altima, Cupra, Fusion) = $36,308
--   3 en proceso SIN ingreso (Captiva, Sunray, Aveo) = solo gasto
--   Total facturado: ~$36,308
--   Costo refacciones: ~$17,000
--   Ingreso neto: ~$19,308
--   Sueldos: $13,400 | Fijos: $7,384 | Variables (prorateados): ~$9,000 (Spark!)
--   GANANCIA NETA ESTIMADA: ~-$10,476 (MALA SEMANA — Spark del taller)
-- ============================================================
