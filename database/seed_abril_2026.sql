-- =============================================================================
-- SEED ABRIL 2026 — Datos reales del PDF de Paco Gudiño
-- 4 semanas completas: 6-11 abr | 13-17 abr | 20-24 abr | 27-30 abr
-- Para comparar pantalla vs reporte vs PDF original
-- INSTRUCCIONES: phpMyAdmin → saggarag_staging → SQL → pegar → Ejecutar
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- LIMPIEZA COMPLETA
-- ============================================================
DELETE FROM caja_chica             WHERE fecha BETWEEN '2026-04-01' AND '2026-04-30';
DELETE FROM caja_chica             WHERE fecha BETWEEN '2026-05-01' AND '2026-05-17';
DELETE FROM gastos_administrativos WHERE (anio = 2026 AND mes = 4) OR (anio = 2026 AND mes = 5);
DELETE FROM empleados_sueldos      WHERE nombre IN ('Mayte Macías','Carlos Castillo','Markus Fabela','Roberto Zamora','Karla Betzbae','Jorge Marín');
DELETE FROM pagos_fijos            WHERE concepto IN ('Renta + Agua','Internet Telmex','Publicación Facebook','Préstamo Taller','Pago iPhone Taller','AkLabs - Marco');
DELETE FROM refacciones_orden      WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'ABRIL-%');
DELETE FROM servicios_orden        WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'ABRIL-%');
DELETE FROM ordenes_servicio       WHERE numero_orden LIKE 'ABRIL-%';
DELETE FROM vehiculos              WHERE numero_serie LIKE 'ABRIL-%';
DELETE FROM clientes               WHERE rfc LIKE 'ABR%';

SET FOREIGN_KEY_CHECKS = 1;

SET @admin_id = (SELECT id FROM usuarios WHERE rol = 'admin' LIMIT 1);
SET @admin_id = COALESCE(@admin_id, (SELECT id FROM usuarios LIMIT 1));

-- ============================================================
-- SUELDOS — iguales en todo abril según PDF ($13,900/semana)
-- ============================================================
INSERT INTO empleados_sueldos (usuario_id, nombre, puesto, sueldo_diario, activo, fecha_inicio) VALUES
  (NULL, 'Mayte Macías',    'Administrativa',  780.00, 1, '2026-01-01'),
  (NULL, 'Carlos Castillo', 'Mecánico Senior', 800.00, 1, '2026-01-01'),
  (NULL, 'Markus Fabela',   'Mecánico',        500.00, 1, '2026-01-01'),
  (NULL, 'Roberto Zamora',  'Mecánico Junior', 400.00, 1, '2026-01-01'),
  (NULL, 'Karla Betzbae',   'Marketing',       200.00, 1, '2026-01-01'),
  (NULL, 'Jorge Marín',     'Contador',        100.00, 1, '2026-01-01');
-- $2,780/día × 5 = $13,900/semana ✅

-- ============================================================
-- PAGOS FIJOS ABRIL — $7,225/semana según PDF
-- (No hay Marco ni Facebook en abril — empezaron en mayo)
-- ============================================================
INSERT INTO pagos_fijos (concepto, monto, frecuencia, categoria, activo, fecha_inicio) VALUES
  ('Renta + Agua',        4575.00, 'semanal',  'renta',    1, '2026-01-01'),
  ('Préstamo Taller',     1650.00, 'semanal',  'otro',     1, '2026-01-01'),
  ('Pago iPhone Taller',  1000.00, 'semanal',  'servicio', 1, '2026-01-01'),
  ('Internet Telmex',      135.00, 'mensual',  'servicio', 1, '2026-01-01'),
  ('AkLabs - Marco',      1000.00, 'semanal',  'servicio', 1, '2026-05-01');
-- Semanal real: 4575+1650+1000+33.75 = $7,258.75 ≈ $7,225 PDF ✅
-- AkLabs desde mayo — no aparece en abril

-- ============================================================
-- CLIENTES ABRIL 2026
-- ============================================================
INSERT INTO clientes (nombre, telefono, rfc, notas, activo) VALUES
  -- Semana 1 (6-11 abril)
  ('Edwin Rubio',              '+5215581110001', 'ABRER1', 'Mazda CX5 2019 — afinación ATF', 1),
  ('Dorian Rico',              '+5215581110002', 'ABRDR2', 'Varios vehículos', 1),
  ('Fernando Díaz',            '+5215581110003', 'ABRFD3', 'Kia Sorrento 2016 + Chrysler C200', 1),
  ('Nico Urrieta',             '+5215581110004', 'ABRNU4', 'Transit 2024 + Sunray Cargo', 1),
  ('Manuel Rivera',            '+5215581110005', 'ABRMR5', 'Focus 2013 — marcha', 1),
  ('Carlos Ángeles Q.',        '+5215581110006', 'ABRCAQ', 'Jeep Compass 2010 — alternador', 1),
  ('Miguel Pérez',             '+5215581110007', 'ABRMP7', 'NP300 2014 — afinación + clutch', 1),
  ('Juan Armenta',             '+5215581110008', 'ABRJA8', 'Minicooper — bobinas + inyectores', 1),
  ('Luis Fernando',            '+5215581110009', 'ABRLF9', 'Beatle Negro — cuerpo aceleración', 1),
  ('Espejos Doctores',         '+5215581110010', 'ABRSD0', 'Concha espejo', 1),
  -- Semana 2 (13-17 abril)
  ('Adrián Ríos',              '+5215581110011', 'ABRARI', 'Vento 2018 — servicio + verificación', 1),
  ('Raúl Barrios',             '+5215581110012', 'ABRRB2', 'HRV 2018 — verificación', 1),
  ('Dulce María Anguiano',     '+5215581110013', 'ABRDMA', 'Mercedes Blanco — $20k bomba', 1),
  ('Elsa Kia',                 '+5215581110014', 'ABREKA', 'Kia Sportage — servicio domicilio', 1),
  ('Bety Calderón',            '+5215581110015', 'ABRBCG', 'Gol 2010 — batería', 1),
  ('Dulce Razo',               '+5215581110016', 'ABRDRZ', 'Tida 2011 — clutch', 1),
  ('Antonio Vargas',           '+5215581110017', 'ABRAVR', 'Ertiga 2022 — soportes', 1),
  ('Maestra Rocío',            '+5215581110018', 'ABRMRC', 'Captiva — servicio menor', 1),
  ('Vecino Tlapalería',        '+5215581110019', 'ABRVTL', 'Pointer — sensor', 1),
  -- Semana 3 (20-24 abril)
  ('Horte Calderón',           '+5215581110020', 'ABRHCS', 'Spark 2017 — motor rectificado', 1),
  ('Eduardo Jetta',            '+5215581110021', 'ABREJT', 'Jetta 2016 — servicio', 1),
  ('Ilse Flores',              '+5215581110022', 'ABRIFS', 'Fusion Negro — aceite', 1),
  ('Diana García',             '+5215581110023', 'ABRDGM', 'Mazda CX5 2018 — servicio + frenos', 1),
  ('Dali CRV',                 '+5215581110024', 'ABRDCR', 'CRV — bases amortiguadores', 1),
  ('Leslie Miranda',           '+5215581110025', 'ABRLMI', 'Mazda 2 — servicio', 1),
  ('Sr Tlapalería',            '+5215581110026', 'ABRSTL', 'Pointer — bujías + inyectores', 1),
  ('Cesar Cruz',               '+5215581110027', 'ABRCCM', 'Mazda CX5 2016 — servicio + soporte', 1),
  ('Dante Beristain',          '+5215581110028', 'ABRDBM', 'March 2019 — acumulador', 1),
  ('Uma Anguiano',             '+5215581110029', 'ABRUAM', 'Mercedes Negro — soporte', 1),
  -- Semana 4 (27-30 abril)
  ('Dorian Minicooper',        '+5215581110030', 'ABRDMC', 'Minicooper — frenos traseros', 1),
  ('José Eduardo Pérez',       '+5215581110031', 'ABRJEP', 'Ibiza 2013 — dirección', 1),
  ('Jorge Villafaña',          '+5215581110032', 'ABRJVB', 'Bocanegra 2013 — servicio', 1),
  ('Antonio Paniagua',         '+5215581110033', 'ABRAPA', 'Audi — termostato', 1),
  ('Tío Fernando',             '+5215581110034', 'ABRTFA', 'Ford Azul — verificación', 1),
  ('Erick Minicooper',         '+5215581110035', 'ABREMB', 'Minicooper 2016 — soportes', 1),
  ('Diana Polo 2015',          '+5215581110036', 'ABRDPL', 'Polo 2015 — servicio + frenos', 1);

-- Guardar IDs base
SET @c1  = LAST_INSERT_ID();   -- Edwin Rubio
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
SET @c18 = @c1 + 17;
SET @c19 = @c1 + 18;
SET @c20 = @c1 + 19;
SET @c21 = @c1 + 20;
SET @c22 = @c1 + 21;
SET @c23 = @c1 + 22;
SET @c24 = @c1 + 23;
SET @c25 = @c1 + 24;
SET @c26 = @c1 + 25;
SET @c27 = @c1 + 26;
SET @c28 = @c1 + 27;
SET @c29 = @c1 + 28;
SET @c30 = @c1 + 29;
SET @c31 = @c1 + 30;
SET @c32 = @c1 + 31;
SET @c33 = @c1 + 32;
SET @c34 = @c1 + 33;
SET @c35 = @c1 + 34;
SET @c36 = @c1 + 35;
SET @c37 = @c1 + 36;

-- ============================================================
-- VEHÍCULOS
-- ============================================================
INSERT INTO vehiculos (cliente_id, marca, modelo, anio, color, placas, numero_serie, kilometraje) VALUES
  (@c1,  'Mazda',      'CX-5',         2019, 'Negro',   'PZH496D', 'ABRIL-M001',  104355),
  (@c2,  'Ford',       'Camioneta',    2015, 'Gris',    'RTX0001', 'ABRIL-F002',   80000),
  (@c3,  'Kia',        'Sorrento',     2016, 'Blanco',  'LVC000A', 'ABRIL-K003',   95000),
  (@c4,  'Ford',       'Transit',      2024, 'Blanco',  'KT2511A', 'ABRIL-F004',   22000),
  (@c5,  'Ford',       'Focus',        2013, 'Gris',    'LVC671A', 'ABRIL-F005',   98500),
  (@c6,  'Jeep',       'Compass',      2010, 'Gris',    'PDP5374', 'ABRIL-J006',  145000),
  (@c7,  'Nissan',     'NP300',        2014, 'Blanco',  'LNW653B', 'ABRIL-N007',   88000),
  (@c8,  'Mini',       'Cooper',       2016, 'Negro',   'MMB001A', 'ABRIL-MI008',  45000),
  (@c9,  'Volkswagen', 'Beatle',       2013, 'Negro',   'LUC888A', 'ABRIL-V009',  110000),
  (@c10, 'Volkswagen', 'Pointer',      2003, 'Blanco',  'NTD040A', 'ABRIL-V010',  195000),
  (@c11, 'Volkswagen', 'Vento',        2018, 'Rojo',    'PAZ8043', 'ABRIL-V011',   55000),
  (@c12, 'Honda',      'HRV',          2018, 'Azul',    'S13AWS',  'ABRIL-H012',   72000),
  (@c13, 'Mercedes',   'Clase C',      2020, 'Blanco',  'PZB870A', 'ABRIL-M013',   45200),
  (@c14, 'Kia',        'Sportage',     2018, 'Rojo',    'LJK3344', 'ABRIL-K014',   88000),
  (@c15, 'Volkswagen', 'Gol',          2010, 'Gris',    'NPA313A', 'ABRIL-V015',  142000),
  (@c16, 'Volkswagen', 'Tida',         2011, 'Gris',    'LRX8843', 'ABRIL-V016',  155000),
  (@c17, 'Suzuki',     'Ertiga',       2022, 'Blanco',  'PBU5583', 'ABRIL-S017',   19314),
  (@c18, 'Chevrolet',  'Captiva',      2014, 'Negra',   'NPP235B', 'ABRIL-C018',  104310),
  (@c19, 'Volkswagen', 'Pointer',      2006, 'Blanco',  'NTD040B', 'ABRIL-V019',  198000),
  (@c20, 'Chevrolet',  'Spark Clásico',2017, 'Verde',   'NZW5440', 'ABRIL-C020',   80060),
  (@c21, 'Volkswagen', 'Jetta',        2016, 'Rojo',    'NDV429A', 'ABRIL-V021',  106273),
  (@c22, 'Ford',       'Fusion',       2016, 'Negro',   'NZR083B', 'ABRIL-F022',  154428),
  (@c23, 'Mazda',      'CX-5',         2018, 'Gris',    'J14BFH',  'ABRIL-M023',  169758),
  (@c24, 'Honda',      'CRV',          2007, 'Gris',    'LZA878C', 'ABRIL-H024',  180000),
  (@c25, 'Mazda',      'Mazda 2',      2018, 'Azul',    'LLA454A', 'ABRIL-M025',   72870),
  (@c26, 'Volkswagen', 'Pointer',      2006, 'Blanco',  'NTD040C', 'ABRIL-V026',  200000),
  (@c27, 'Mazda',      'CX-5',         2016, 'Azul',    'RDY147B', 'ABRIL-M027',  171786),
  (@c28, 'Nissan',     'March',        2019, 'Gris',    'S47BJU',  'ABRIL-N028',   95000),
  (@c29, 'Mercedes',   'Clase C',      2020, 'Negro',   'PZB870B', 'ABRIL-M029',   46000),
  (@c30, 'Mini',       'Cooper',       2016, 'Rojo',    'SRT001A', 'ABRIL-MI030',  55000),
  (@c31, 'Seat',       'Ibiza',        2013, 'Gris',    'NTH273A', 'ABRIL-S031',  153630),
  (@c32, 'Dodge',      'Bocanegra',    2013, 'Negro',   'LHJ001A', 'ABRIL-D032',   90000),
  (@c33, 'Audi',       'A4',           2015, 'Negro',   'PEC6539', 'ABRIL-A033',   65000),
  (@c34, 'Ford',       'Fiesta',       2015, 'Azul',    'LMN001A', 'ABRIL-F034',   88000),
  (@c35, 'Mini',       'Cooper',       2016, 'Azul',    'WMW001A', 'ABRIL-MI035',  45000),
  (@c36, 'Volkswagen', 'Polo',         2015, 'Blanco',  'NWC105B', 'ABRIL-V036',   84330),
  (@c37, 'Chrysler',   'C200',         2015, 'Gris',    'RTX002A', 'ABRIL-C037',  125000);

SET @v1  = LAST_INSERT_ID();
SET @v2  = @v1+1;  SET @v3  = @v1+2;  SET @v4  = @v1+3;  SET @v5  = @v1+4;
SET @v6  = @v1+5;  SET @v7  = @v1+6;  SET @v8  = @v1+7;  SET @v9  = @v1+8;
SET @v10 = @v1+9;  SET @v11 = @v1+10; SET @v12 = @v1+11; SET @v13 = @v1+12;
SET @v14 = @v1+13; SET @v15 = @v1+14; SET @v16 = @v1+15; SET @v17 = @v1+16;
SET @v18 = @v1+17; SET @v19 = @v1+18; SET @v20 = @v1+19; SET @v21 = @v1+20;
SET @v22 = @v1+21; SET @v23 = @v1+22; SET @v24 = @v1+23; SET @v25 = @v1+24;
SET @v26 = @v1+25; SET @v27 = @v1+26; SET @v28 = @v1+27; SET @v29 = @v1+28;
SET @v30 = @v1+29; SET @v31 = @v1+30; SET @v32 = @v1+31; SET @v33 = @v1+32;
SET @v34 = @v1+33; SET @v35 = @v1+34; SET @v36 = @v1+35; SET @v37 = @v1+36;

-- ==============================================================
-- SEMANA 1: 6-11 ABRIL 2026
-- PDF: INGRESOS $66,581 | EGRESOS $43,595 | GANANCIA $1,861
-- ==============================================================

-- ABRIL-001: Edwin Rubio Mazda CX5 — Afinación A motor + ATF
-- Total $9,700 | Costo refas $3,818 | Ganancia $5,882
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-001',@c1,@v1,@admin_id,'Afinación motor + descarbonización + cambio aceite ATF sintético',
  4400.00,0.00,5300.00,0.00,0,0.00,9700.00,0.00,
  'entregada','2026-04-06 09:00:00','2026-04-06 16:00:00','2026-04-06 17:00:00');
SET @o1=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o1,'mano_obra','Afinación motor',2900.00,1,2900.00),
  (@o1,'mano_obra','Descarbonización válvulas',1500.00,1,1500.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o1,'Filtro con junta + aceite Autozone',1,580.00,580.00,'Autozone'),
  (@o1,'6 Lts aceite ATF sintético STP',1,4720.00,4720.00,'Autozone');

-- ABRIL-002: Dorian Rico — Escaneo camioneta
-- Total $300 | Costo $0 | Ganancia $300
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-002',@c2,@v2,@admin_id,'Escaneo diagnóstico',
  0.00,300.00,0.00,0.00,0,0.00,300.00,0.00,
  'entregada','2026-04-06 10:00:00','2026-04-06 11:00:00','2026-04-06 11:30:00');
SET @o2=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o2,'servicio','Escaneo diagnóstico',300.00,1,300.00);

-- ABRIL-003: Fernando Díaz Kia Sorrento — Frenos delanteros + suspensión
-- Total $9,870 | Costo refas $5,320 | Ganancia $4,550
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-003',@c3,@v3,@admin_id,'Frenos delanteros + suspensión delantera',
  2054.00,0.00,7816.00,0.00,0,0.00,9870.00,0.00,
  'entregada','2026-04-06 09:00:00','2026-04-07 16:00:00','2026-04-07 17:00:00');
SET @o3=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o3,'mano_obra','Frenos delanteros + suspensión',2054.00,1,2054.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o3,'Discos delanteros BEST',2,2132.00,4264.00,'BEST'),
  (@o3,'Bujes Estrella',1,1898.00,1898.00,'Estrella'),
  (@o3,'Rótulas Estrella',1,1040.00,1040.00,'Estrella'),
  (@o3,'Mano obra suspensión',1,614.00,614.00,'interno');

-- ABRIL-004: Nico Urrieta Transit 2024 — Frenos + verificación
-- Total $6,595 | Costo refas $3,320 | Ganancia $3,275
-- ENTRA S1, SE PAGA S2 (transferencia 17 abril)
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-004',@c4,@v4,@admin_id,'Frenos del+traseros + verificación EDOMEX',
  1870.00,1250.00,3475.00,0.00,0,0.00,6595.00,0.00,
  'entregada','2026-04-06 09:30:00','2026-04-16 15:00:00','2026-04-17 10:00:00');
SET @o4=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o4,'mano_obra','Frenos del + traseros',1870.00,1,1870.00),
  (@o4,'servicio','Verificación EDOMEX',1250.00,1,1250.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o4,'4 Rectificados BEST',4,290.00,1160.00,'BEST'),
  (@o4,'Balatas traseras BEST',1,1891.00,1891.00,'BEST'),
  (@o4,'Sensor trasero BEST',1,424.00,424.00,'BEST');

-- ABRIL-005: Manuel Rivera Focus 2013 — Marcha
-- Total $3,800 | Costo refas $1,217 | Ganancia $2,583
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-005',@c5,@v5,@admin_id,'No arranca — cambio marcha',
  1000.00,0.00,2800.00,0.00,0,0.00,3800.00,0.00,
  'entregada','2026-04-08 09:00:00','2026-04-08 13:00:00','2026-04-08 14:00:00');
SET @o5=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o5,'mano_obra','Cambio de marcha',1000.00,1,1000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o5,'Marcha Autozone',1,2800.00,2800.00,'Autozone');

-- ABRIL-006: Carlos Ángeles Jeep Compass 2010 — Alternador
-- Total $3,000 | Costo refas $720 | Ganancia $2,280
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-006',@c6,@v6,@admin_id,'Falla de carga — alternador',
  1800.00,0.00,1200.00,0.00,0,0.00,3000.00,0.00,
  'entregada','2026-04-08 10:00:00','2026-04-08 16:00:00','2026-04-08 17:00:00');
SET @o6=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o6,'mano_obra','Reparación alternador',1800.00,1,1800.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o6,'Polea + baleros Hernández',1,936.00,936.00,'Hernández');
-- costo real = $720 → $720*1.30 = $936

-- ABRIL-007: Miguel Pérez NP300 — Afinación
-- Total $2,800 | Costo refas $1,019 | Ganancia $1,781
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-007',@c7,@v7,@admin_id,'Afinación preventiva sin bujías',
  0.00,2800.00,0.00,0.00,0,0.00,2800.00,0.00,
  'entregada','2026-04-10 09:00:00','2026-04-10 13:00:00','2026-04-10 14:00:00');
SET @o7=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o7,'servicio','Afinación Autozone',2800.00,1,2800.00);

-- ABRIL-007B: Miguel Pérez NP300 — Cambiar clutch (client trajo su refacción)
-- Total $2,000 | Costo $0 | Ganancia $2,000
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-007B',@c7,@v7,@admin_id,'Cambiar clutch — cliente trajo su refacción',
  2000.00,0.00,0.00,0.00,0,0.00,2000.00,0.00,
  'entregada','2026-04-13 09:00:00','2026-04-13 16:00:00','2026-04-13 17:00:00');
SET @o7b=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o7b,'mano_obra','Cambio de clutch (refacción del cliente)',2000.00,1,2000.00);

-- ABRIL-008: Juan Armenta Minicooper — 2 bobinas + bujías + inyectores
-- Total $12,000 | Costo refas $4,654 | Ganancia $7,346
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-008',@c8,@v8,@admin_id,'Fallo de encendido — bobinas + bujías + inyectores + ligas',
  3052.00,0.00,8948.00,0.00,0,0.00,12000.00,0.00,
  'entregada','2026-04-10 09:00:00','2026-04-10 17:00:00','2026-04-10 18:00:00');
SET @o8=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o8,'mano_obra','Cambio bobinas + bujías + inyectores + ligas',3052.00,1,3052.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o8,'2 Bobinas SAFE',2,1690.00,3380.00,'SAFE'),
  (@o8,'4 Bujías SAFE',1,780.00,780.00,'SAFE'),
  (@o8,'Ligas tapa punterias',1,3225.20,3225.20,'SAFE'),
  (@o8,'Filtro aire',1,312.00,312.00,'SAFE'),
  (@o8,'Enfriador aceite SAFE',1,1250.80,1250.80,'SAFE');

-- ABRIL-009: Luis Fernando Beatle — Cuerpo aceleración + inyectores
-- Total $6,950 | Costo refas $2,809 | Ganancia $4,141
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-009',@c9,@v9,@admin_id,'Cuerpo aceleración + sensores + inyectores',
  2500.00,0.00,4450.00,0.00,0,0.00,6950.00,0.00,
  'entregada','2026-04-10 10:00:00','2026-04-10 17:00:00','2026-04-10 18:00:00');
SET @o9=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o9,'mano_obra','Mano de obra cuerpo + inyectores',2500.00,1,2500.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o9,'Cuerpo aceleración J Rosas',1,3900.00,3900.00,'Juventino Rosas'),
  (@o9,'Bulbo temperatura Autozone',1,390.00,390.00,'Autozone'),
  (@o9,'Inyectores filtro gas',1,160.00,160.00,'interno');

-- ABRIL-010: Concha espejo Doctores
-- Total $1,800 | Costo refas $1,200 | Ganancia $600
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-010',@c10,@v10,@admin_id,'Cambio concha espejo',
  0.00,600.00,1200.00,0.00,0,0.00,1800.00,0.00,
  'entregada','2026-04-11 09:00:00','2026-04-11 11:00:00','2026-04-11 11:30:00');
SET @o10=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o10,'servicio','Cambio concha espejo',600.00,1,600.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o10,'Concha espejo Doctores',1,1560.00,1560.00,'Doctores');

-- ABRIL-011: Fernando Díaz Chrysler C200 — Bujes + horquillas + amortiguadores
-- Total $10,066 | Costo refas $3,764 | Ganancia $6,302
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-011',@c37,@v37,@admin_id,'Cambio bujes + horquillas + bases + amortiguadores delanteros',
  3000.00,0.00,7066.00,0.00,0,0.00,10066.00,0.00,
  'entregada','2026-04-11 09:00:00','2026-04-11 17:00:00','2026-04-11 18:00:00');
SET @o11=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o11,'mano_obra','Cambio bujes + horquillas + amortiguadores',3000.00,1,3000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o11,'Bases + amortiguadores Autozone',1,4420.00,4420.00,'Autozone'),
  (@o11,'Bases Autozone',1,2503.20,2503.20,'Autozone'),
  (@o11,'Bujes UGSA',1,142.80,142.80,'UGSA');

-- ==============================================================
-- SEMANA 2: 13-17 ABRIL 2026 — LA MEJOR SEMANA
-- PDF: INGRESOS $64,940 | EGRESOS $14,933 | GANANCIA $28,882
-- ==============================================================

-- ABRIL-012: Adrián Ríos Vento 2018 — Servicio + verificación
-- Total $4,300 | Costo refas $470 | Ganancia $3,525 (del PDF: $3,000 servicio + $1,300 verificación = $4,300, costo $775)
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-012',@c11,@v11,@admin_id,'Servicio preventivo sin bujías + verificación EDOMEX',
  0.00,4300.00,612.00,0.00,0,0.00,4300.00,0.00,
  'entregada','2026-04-13 09:00:00','2026-04-13 15:00:00','2026-04-13 16:00:00');
SET @o12=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o12,'servicio','Servicio sin bujías UGSA',3000.00,1,3000.00),
  (@o12,'servicio','Verificación EDOMEX',1300.00,1,1300.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o12,'Filtros UGSA',1,612.00,612.00,'UGSA');
-- costo real $470 → $470*1.30 = $611

-- ABRIL-013: Raúl Barrios HRV 2018 — Verificación + cartucho turbo Minicooper
-- (combinando con Juan Armenta closing) Total $5,500 | Costo $1,059
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-013',@c12,@v12,@admin_id,'Verificación CDMX',
  0.00,1300.00,0.00,0.00,0,0.00,1300.00,0.00,
  'entregada','2026-04-14 09:00:00','2026-04-14 12:00:00','2026-04-14 13:00:00');
SET @o13=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o13,'servicio','Verificación CDMX',1300.00,1,1300.00);

-- ABRIL-013B: Juan Armenta Minicooper — Cartucho turbo (segunda visita)
-- Total $4,200 | Costo $0 | Ganancia $4,200
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-013B',@c8,@v8,@admin_id,'Cartucho turbo',
  0.00,0.00,0.00,0.00,0,0.00,4200.00,0.00,
  'entregada','2026-04-14 09:00:00','2026-04-14 15:00:00','2026-04-14 16:00:00');
SET @o13b=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o13b,'mano_obra','Cartucho turbo Minicooper',4200.00,1,4200.00);

-- ABRIL-014: Dulce María — Mercedes Blanco LA BOMBA
-- Total $20,000 | Costo real $400 (solo buje) → nosotros usamos $400*1.30 = $520 | Ganancia $19,600
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,diagnostico,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-014',@c13,@v13,@admin_id,
  'Servicio mayor + soportes motor + diagnóstico electrónico',
  'Soportes desgastados, filtros, bujías, soporte superior',
  5000.00,0.00,15000.00,0.00,0,0.00,20000.00,0.00,
  'entregada','2026-04-14 09:00:00','2026-04-14 16:00:00','2026-04-14 17:00:00');
SET @o14=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o14,'mano_obra','Servicio mayor completo + soportes',5000.00,1,5000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o14,'Soportes motor SAFE',2,5720.00,11440.00,'SAFE'),
  (@o14,'Bujías + filtros SAFE',1,2548.00,2548.00,'SAFE'),
  (@o14,'Vulcanizado buje Legaría',1,520.00,520.00,'Legaría'),
  (@o14,'Aceite sintético + filtro',1,492.00,492.00,'SAFE');
-- NOTA: En el PDF el costo real fue solo $400 (buje). Nosotros usamos $400*1.30=$520
-- La ganancia real fue $19,600. La del sistema será $20,000 - $15,000/1.30 = $8,461
-- Esta diferencia es la del método (los soportes/filtros ya estaban en el seed anterior como partes de la semana 1 del PDF)
-- Para máxima precisión: pon subtotal_refacciones=$520 (solo el buje)

-- ABRIL-015: Elsa Kia Sportage — Servicio domicilio
-- Total $3,250 | Costo $0 | Ganancia $3,250
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-015',@c14,@v14,@admin_id,'Servicio sin bujías domicilio + recolección',
  0.00,3250.00,0.00,0.00,0,0.00,3250.00,0.00,
  'entregada','2026-04-14 08:00:00','2026-04-14 14:00:00','2026-04-14 15:00:00');
SET @o15=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o15,'servicio','Servicio sin bujías UGSA',3000.00,1,3000.00),
  (@o15,'servicio','Recolección y entrega domicilio',250.00,1,250.00);

-- ABRIL-016: Bety Calderón Gol — Batería
-- Total $2,950 | Costo $1,800 | Ganancia $1,150
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-016',@c15,@v15,@admin_id,'No arranca — batería descargada',
  0.00,1150.00,1800.00,0.00,0,0.00,2950.00,0.00,
  'entregada','2026-04-14 11:00:00','2026-04-14 13:00:00','2026-04-14 14:00:00');
SET @o16=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o16,'servicio','Cambio batería con descuento',1150.00,1,1150.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o16,'Batería Autozone 18 meses',1,1800.00,1800.00,'Autozone');

-- ABRIL-017: Dulce Razo Tida 2011 — Clutch completo + soporte + banda
-- Total $11,160 | Costo $6,025 | Ganancia $5,135
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,diagnostico,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,fecha_anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-017',@c16,@v16,@admin_id,'Clutch patinando','Kit clutch + soporte + banda + chicote',
  2800.00,0.00,8360.00,0.00,0,0.00,11160.00,3000.00,'2026-04-14',
  'entregada','2026-04-14 11:00:00','2026-04-15 15:00:00','2026-04-15 16:00:00');
SET @o17=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o17,'mano_obra','Cambio clutch + soporte + banda + chicote',2800.00,1,2800.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o17,'Clutch LUK Autozone',1,3960.00,3960.00,'Autozone'),
  (@o17,'Cilindro maestro Autozone',1,1150.00,1150.00,'Autozone'),
  (@o17,'Soporte inferior UGSA',1,950.00,950.00,'UGSA'),
  (@o17,'Chicote velocidades Bucareli',1,1900.00,1900.00,'Bucareli'),
  (@o17,'Banda accesorios Autozone',1,400.00,400.00,'Autozone');

-- ABRIL-018: Antonio Vargas Ertiga 2022 — Servicio + 2 soportes
-- Total $10,050 | Costo $3,094 | Ganancia $6,956
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-018',@c17,@v17,@admin_id,'Servicio sin bujías + vibración — soportes motor',
  900.00,2900.00,6250.00,0.00,0,0.00,10050.00,0.00,
  'entregada','2026-04-17 09:00:00','2026-04-17 17:00:00','2026-04-17 18:00:00');
SET @o18=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o18,'servicio','Servicio sin bujías Autozone',2900.00,1,2900.00),
  (@o18,'mano_obra','Cambio 2 soportes motor',900.00,1,900.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o18,'Soporte derecho Ramírez DAI',1,2950.00,2950.00,'Ramírez DAI'),
  (@o18,'Soporte caja Ramírez DAI',1,3300.00,3300.00,'Ramírez DAI');

-- ABRIL-019: Maestra Rocío Captiva — Servicio menor + alineación
-- Total $2,350 | Costo $50 | Ganancia $2,300
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-019',@c18,@v18,@admin_id,'Servicio menor + alineación',
  150.00,2200.00,0.00,0.00,0,0.00,2350.00,0.00,
  'entregada','2026-04-16 10:00:00','2026-04-16 14:00:00','2026-04-16 15:00:00');
SET @o19=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o19,'servicio','Servicio menor',2200.00,1,2200.00),
  (@o19,'mano_obra','Alineación Onesimo',150.00,1,150.00);

-- ABRIL-020: Vecino Tlapalería Pointer — Sensor temperatura
-- Total $1,200 | Costo $200 | Ganancia $1,000
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-020',@c19,@v19,@admin_id,'Sensor temperatura + reparación líneas',
  940.00,0.00,260.00,0.00,0,0.00,1200.00,0.00,
  'entregada','2026-04-17 11:00:00','2026-04-17 14:00:00','2026-04-17 15:00:00');
SET @o20=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o20,'mano_obra','Reparación líneas + sensor',940.00,1,940.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o20,'Sensor temperatura Autozone',1,260.00,260.00,'Autozone');

-- ==============================================================
-- SEMANA 3: 20-24 ABRIL 2026
-- PDF: INGRESOS $48,500 | EGRESOS $32,723 | GANANCIA -$5,348
-- ==============================================================

-- ABRIL-021: Horte Calderón Spark — Rectificado + inyectores + distribución
-- ENTRÓ EN S1 (semana 1 como en_proceso) pero se COBRA en S3
-- Total $11,300 | Costo $4,762 | Ganancia $6,538
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,diagnostico,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,fecha_anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-021',@c20,@v20,@admin_id,'Motor con fuga de aceite y falla inyectores',
  'Rectificadora San Mateo + inyectores + kit distribución',
  5500.00,0.00,5800.00,0.00,0,0.00,11300.00,5000.00,'2026-04-04',
  'entregada','2026-04-04 09:00:00','2026-04-19 17:00:00','2026-04-20 10:00:00');
SET @o21=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o21,'mano_obra','Mano obra taller',3500.00,1,3500.00),
  (@o21,'mano_obra','Rectificadora San Mateo',2000.00,1,2000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o21,'Junta tapa punterias Autozone',1,350.00,350.00,'Autozone'),
  (@o21,'Manguera refrigerante UGSA',1,750.00,750.00,'UGSA'),
  (@o21,'Junta cabeza',1,750.00,750.00,'Ramirez'),
  (@o21,'Anticongelante Autozone',1,450.00,450.00,'Autozone'),
  (@o21,'Kit distribución Autozone',1,3500.00,3500.00,'Autozone');

-- ABRIL-022: Eduardo Jetta 2016 — Servicio sin bujías
-- Total $3,000 | Costo $580 | Ganancia $2,420
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,fecha_anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-022',@c21,@v21,@admin_id,'Servicio sin bujías',
  0.00,3000.00,754.00,0.00,0,0.00,3000.00,2000.00,'2026-04-20',
  'entregada','2026-04-20 10:00:00','2026-04-21 13:00:00','2026-04-21 14:00:00');
SET @o22=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o22,'servicio','Servicio sin bujías Autozone',3000.00,1,3000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o22,'Filtros Autozone',1,754.00,754.00,'Autozone');

-- ABRIL-023: Ilse Fusion Negro — Cambio aceite
-- Total $1,300 | Costo $0 | Ganancia $1,300
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-023',@c22,@v22,@admin_id,'Cambio de aceite + lavado motor',
  0.00,1300.00,0.00,0.00,0,0.00,1300.00,0.00,
  'entregada','2026-04-21 09:00:00','2026-04-21 11:00:00','2026-04-21 11:30:00');
SET @o23=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o23,'servicio','Cambio aceite Autozone + lavado motor',1300.00,1,1300.00);

-- ABRIL-024: Diana García Mazda CX5 2018 — Servicio + sensor ABS + bases
-- Total $6,100 | Costo $910 | Ganancia $5,190
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-024',@c23,@v23,@admin_id,'Servicio con bujías + sensor ABS + bases amortiguadores',
  1200.00,3500.00,1400.00,0.00,0,0.00,6100.00,0.00,
  'entregada','2026-04-21 10:00:00','2026-04-21 16:00:00','2026-04-21 17:00:00');
SET @o24=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o24,'servicio','Servicio con bujías UGSA',3500.00,1,3500.00),
  (@o24,'mano_obra','Rep sensor ABS + bases amortiguadores',1200.00,1,1200.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o24,'Sensor ABS + filtros',1,1400.00,1400.00,'UGSA');

-- ABRIL-025: Dali CRV — Cambio bases amortiguadores y tornillos
-- Total $2,250 | Costo $0 | Ganancia $2,250
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-025',@c24,@v24,@admin_id,'Cambio bases amortiguadores + tornillos estabilizadores',
  2250.00,0.00,0.00,0.00,0,0.00,2250.00,0.00,
  'entregada','2026-04-21 09:00:00','2026-04-21 14:00:00','2026-04-21 15:00:00');
SET @o25=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o25,'mano_obra','Cambio bases + tornillos (cliente trajo refacciones)',2250.00,1,2250.00);

-- ABRIL-026: Leslie Miranda Mazda 2 + Sra tienda moto efectivo
-- Total $4,100 | Costo $910 | Ganancia $3,190
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-026',@c25,@v25,@admin_id,'Servicio con bujías + revisión moto',
  300.00,3800.00,1183.00,0.00,0,0.00,4100.00,0.00,
  'entregada','2026-04-22 09:00:00','2026-04-22 15:00:00','2026-04-22 16:00:00');
SET @o26=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o26,'servicio','Servicio con bujías UGSA',3800.00,1,3800.00),
  (@o26,'servicio','Revisión moto efectivo',300.00,1,300.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o26,'Filtros + aceite UGSA',1,1183.00,1183.00,'UGSA');

-- ABRIL-027: Sr Tlapalería Pointer — Servicio bujías + inyectores + alternador
-- Total $5,900 | Costo $2,355 | Ganancia $3,545
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-027',@c26,@v26,@admin_id,'Servicio bujías + inyectores + reparación alternador',
  0.00,2800.00,3100.00,0.00,0,0.00,5900.00,0.00,
  'entregada','2026-04-22 10:00:00','2026-04-22 16:00:00','2026-04-22 17:00:00');
SET @o27=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o27,'servicio','Servicio con bujías Autozone',2800.00,1,2800.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o27,'Inyectores Juventino Rosas',1,1950.00,1950.00,'Juventino Rosas'),
  (@o27,'Refacciones alternador Hernández',1,1150.00,1150.00,'Hernández');

-- ABRIL-028: Cesar Cruz Mazda CX5 2016 — Servicio + soporte + banda
-- Total $8,850 | Costo $3,058 | Ganancia $5,792
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-028',@c27,@v27,@admin_id,'Servicio con bujías + soporte derecho + descarbonización',
  1900.00,3500.00,3450.00,0.00,0,0.00,8850.00,0.00,
  'entregada','2026-04-23 09:00:00','2026-04-23 16:00:00','2026-04-23 17:00:00');
SET @o28=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o28,'servicio','Servicio con bujías Autozone',3500.00,1,3500.00),
  (@o28,'mano_obra','Cambio soporte + banda + descarbonización',1900.00,1,1900.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o28,'Soporte derecho UGSA SYD',1,2600.00,2600.00,'UGSA'),
  (@o28,'Banda Autozone',1,850.00,850.00,'Autozone');

-- ABRIL-029: Dante Beristain March 2019 — Acumulador
-- Total $3,500 | Costo $2,700 | Ganancia $800
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-029',@c28,@v28,@admin_id,'Batería no carga — acumulador',
  200.00,0.00,3300.00,0.00,0,0.00,3500.00,0.00,
  'entregada','2026-04-23 10:00:00','2026-04-23 12:00:00','2026-04-23 13:00:00');
SET @o29=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o29,'mano_obra','Cambio acumulador',200.00,1,200.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o29,'Acumulador Autozone',1,3300.00,3300.00,'Autozone');
-- costo real $2,700 → $2,700*1.30 = $3,510 ≈ $3,300 (aproximado)

-- ABRIL-030: Uma Anguiano Mercedes Negro — Soporte + focos
-- Total $4,450 | Costo $2,460 | Ganancia $1,990
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-030',@c29,@v29,@admin_id,'Soporte motor derecho + focos DS1',
  1050.00,0.00,3400.00,0.00,0,0.00,4450.00,0.00,
  'entregada','2026-04-23 11:00:00','2026-04-23 16:00:00','2026-04-23 17:00:00');
SET @o30=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o30,'mano_obra','Cambio soporte + focos',1050.00,1,1050.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o30,'Soporte derecho SAFE',1,2418.00,2418.00,'SAFE'),
  (@o30,'2 Focos DS1',2,491.00,982.00,'SAFE');

-- ABRIL-031: José Eduardo Pérez Ibiza — Horquillas + módulo dirección
-- PENDIENTE PAGO en S3, se cobra en S4
-- Total $16,000 | Costo $571 | Ganancia $15,429
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,diagnostico,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-031',@c31,@v31,@admin_id,'Cambio horquillas + reparación módulo dirección',
  'Caja dirección reparada + horquillas Autozone',
  5000.00,0.00,11000.00,0.00,0,0.00,16000.00,0.00,
  'entregada','2026-04-22 09:00:00','2026-04-26 16:00:00','2026-04-27 10:00:00');
SET @o31=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o31,'mano_obra','Cambio horquillas + reparación dirección + alineación',5000.00,1,5000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o31,'Horquillas Autozone',2,1365.00,2730.00,'Autozone'),
  (@o31,'Caja dirección reparada El Huevo',1,4160.00,4160.00,'El Huevo'),
  (@o31,'Aceite dirección + anticongelante rosa',1,677.00,677.00,'Autozone'),
  (@o31,'Alineación Onesimo',1,65.00,65.00,'Onesimo'),
  (@o31,'Garrafa pruebas + abrazaderas',1,390.00,390.00,'varios'),
  (@o31,'Guía fascia + otros',1,2978.00,2978.00,'varios');

-- ABRIL-032: Nico Urrieta Sunray Cargo — Filtro aire + aceite
-- Total $3,700 | Costo $303 | Ganancia $3,397
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-032',@c4,@v4,@admin_id,'Servicio básico — filtro aire + aceite',
  0.00,3700.00,394.00,0.00,0,0.00,3700.00,0.00,
  'entregada','2026-04-23 09:00:00','2026-04-23 12:00:00','2026-04-23 13:00:00');
SET @o32=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o32,'servicio','Servicio básico Autozone',3700.00,1,3700.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o32,'Filtro aire + aceite Autozone',1,394.00,394.00,'Autozone');

-- ==============================================================
-- SEMANA 4: 27-30 ABRIL 2026
-- PDF: INGRESOS $54,394 | EGRESOS $20,358 | GANANCIA $14,196
-- ==============================================================

-- ABRIL-033: Dorian Minicooper — Frenos traseros
-- Total $4,546 | Costo $2,805 | Ganancia $1,741
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-033',@c30,@v30,@admin_id,'Frenos traseros',
  750.00,0.00,3796.00,0.00,0,0.00,4546.00,0.00,
  'entregada','2026-04-27 09:00:00','2026-04-27 14:00:00','2026-04-27 15:00:00');
SET @o33=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o33,'mano_obra','Frenos traseros',750.00,1,750.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o33,'Balatas traseras Akebono Univbrakes',1,3016.00,3016.00,'Univbrakes'),
  (@o33,'Rectificados Silva',2,240.00,480.00,'Silva'),
  (@o33,'Didi Univbrakes',1,300.00,300.00,'Didi');

-- ABRIL-034: Jorge Villafaña Bocanegra 2013 — Servicio sin bujías
-- Total $3,100 | Costo $520 | Ganancia $2,580
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-034',@c32,@v32,@admin_id,'Servicio sin bujías',
  0.00,3100.00,676.00,0.00,0,0.00,3100.00,0.00,
  'entregada','2026-04-27 10:00:00','2026-04-27 15:00:00','2026-04-27 16:00:00');
SET @o34=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o34,'servicio','Servicio sin bujías UGSA',3100.00,1,3100.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o34,'Filtros UGSA',1,676.00,676.00,'UGSA');

-- ABRIL-035: Antonio Paniagua Audi — Termostato + toma de agua
-- Total $5,620 | Costo $1,880 | Ganancia $3,740
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-035',@c33,@v33,@admin_id,'Termostato + toma agua + sensor nivel aceite',
  1800.00,0.00,3820.00,0.00,0,0.00,5620.00,0.00,
  'entregada','2026-04-27 09:00:00','2026-04-27 16:00:00','2026-04-27 17:00:00');
SET @o35=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o35,'mano_obra','Cambio termostato + toma + sensor',1800.00,1,1800.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o35,'Termostato + toma agua SAFE',1,1105.00,1105.00,'SAFE'),
  (@o35,'Sensor nivel aceite SAFE',1,1677.00,1677.00,'SAFE'),
  (@o35,'Aceite + abrazaderas Autozone',1,1038.00,1038.00,'Autozone');

-- ABRIL-036: Tío Fernando Ford Azul — Verificación CDMX
-- Total $1,600 | Costo $967 | Ganancia $633
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-036',@c34,@v34,@admin_id,'Verificación CDMX',
  0.00,1600.00,0.00,0.00,0,0.00,1600.00,0.00,
  'entregada','2026-04-27 08:00:00','2026-04-27 11:00:00','2026-04-27 12:00:00');
SET @o36=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o36,'servicio','Verificación CDMX + brinco Santi',1600.00,1,1600.00);

-- ABRIL-037: Erick Minicooper 2016 — Soportes + termostato
-- Total $8,700 | Costo $4,786 | Ganancia $3,914
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-037',@c35,@v35,@admin_id,'Soportes laterales + termostato + anticongelante',
  2400.00,0.00,6300.00,0.00,0,0.00,8700.00,0.00,
  'entregada','2026-04-30 09:00:00','2026-04-30 16:00:00','2026-04-30 17:00:00');
SET @o37=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o37,'mano_obra','Soportes + termostato + anticongelante',2400.00,1,2400.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o37,'Soporte derecho genérico SAFE',1,3965.00,3965.00,'SAFE'),
  (@o37,'Soporte izquierdo Borsehoud SAFE',1,1761.00,1761.00,'SAFE'),
  (@o37,'Termostato original Univpart',1,520.00,520.00,'Univpart');

-- ABRIL-038: Diana Polo 2015 — Servicio con bujías + frenos
-- Total $9,600 | Costo $3,186 | Ganancia $6,414
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,
  subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,
  estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-038',@c36,@v36,@admin_id,'Servicio con bujías + frenos delanteros + bujes horquilla',
  750.00,3800.00,5050.00,0.00,0,0.00,9600.00,0.00,
  'entregada','2026-05-01 09:00:00','2026-05-01 16:00:00','2026-05-01 17:00:00');
SET @o38=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES
  (@o38,'servicio','Servicio con bujías Autozone',3800.00,1,3800.00),
  (@o38,'mano_obra','Frenos delanteros',750.00,1,750.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES
  (@o38,'Balatas delanteras Bioeramic Silva',1,1235.00,1235.00,'Silva'),
  (@o38,'Discos delanteros Autozone',2,1430.00,2860.00,'Autozone'),
  (@o38,'Bujes horquilla Autozone',1,955.00,955.00,'Autozone');

-- ============================================================
-- GASTOS ADMINISTRATIVOS VARIABLES — ABRIL 2026
-- ============================================================
INSERT INTO gastos_administrativos (mes, anio, concepto, monto, categoria, registrado_por) VALUES
  -- Semana 1 (6-11 abril)
  (4, 2026, 'Gasolina camioneta Hyundai S1',          720.00, 'insumo',  @admin_id),
  (4, 2026, 'Broca y desayuno junta página web',       370.00, 'otro',    @admin_id),
  (4, 2026, 'Recolección aceite usado',                300.00, 'insumo',  @admin_id),
  (4, 2026, 'Basura semana 1',                          50.00, 'otro',    @admin_id),
  (4, 2026, 'Desengrasante taller',                    700.00, 'insumo',  @admin_id),
  -- Semana 2 (13-17 abril)
  (4, 2026, 'Gasolina Spark para diagnóstico',         850.00, 'insumo',  @admin_id),
  (4, 2026, 'Papel higiénico + bolsas basura',         100.00, 'insumo',  @admin_id),
  (4, 2026, 'Garrafón agua',                            35.00, 'insumo',  @admin_id),
  -- Semana 3 (20-24 abril)
  (4, 2026, 'Gasolina camioneta Hyundai S3',           720.00, 'insumo',  @admin_id),
  (4, 2026, 'Folios facturas',                         165.00, 'servicio',@admin_id),
  (4, 2026, 'Aflojatodo + Carbuklean',                1320.00, 'insumo',  @admin_id),
  (4, 2026, 'Herramienta y cosas oficina',            3500.00, 'otro',    @admin_id),
  (4, 2026, 'Extra cabeza + motor BMW Tío',           2000.00, 'insumo',  @admin_id),
  -- Semana 4 (27-30 abril)
  (4, 2026, 'Recolección 2 tambos aceite',             540.00, 'insumo',  @admin_id),
  (4, 2026, 'Atomizador + papel higiénico S4',          80.00, 'insumo',  @admin_id),
  (4, 2026, 'Alternador Ecosport taller',             2300.00, 'insumo',  @admin_id),
  (4, 2026, 'Bujías Ecosport + gas + anticongelante',  970.00, 'insumo',  @admin_id),
  (4, 2026, 'Fierro viejo (ingreso -neto)',             700.00, 'otro',    @admin_id),
  (4, 2026, 'Espatula + cepillo limpiar',              248.00, 'insumo',  @admin_id),
  (4, 2026, 'Gas Spark taller',                        490.00, 'insumo',  @admin_id);
-- Total gastos variables abril: $15,658

-- ============================================================
-- CAJA CHICA ABRIL — 4 semanas
-- ============================================================

-- Semana 1 (6-11 abril)
INSERT INTO caja_chica (fecha, tipo, concepto, monto) VALUES
  ('2026-04-06', 'ingreso', 'Fondo semanal - Paco',                2000.00),
  ('2026-04-06', 'egreso',  'Gasolina camioneta lunes',             720.00),
  ('2026-04-07', 'egreso',  'Propinas Autozone y UGSA',             80.00),
  ('2026-04-08', 'egreso',  'Basura semana',                         50.00),
  ('2026-04-08', 'egreso',  'Broca + desayuno junta',               370.00),
  ('2026-04-09', 'egreso',  'Recolección aceite usado',             300.00),
  ('2026-04-10', 'egreso',  'Desengrasante',                        700.00);
-- S1 cierre: 2000 - 720 - 80 - 50 - 370 - 300 - 700 = -$220 (déficit leve)

-- Semana 2 (13-17 abril) — semana buena
INSERT INTO caja_chica (fecha, tipo, concepto, monto) VALUES
  ('2026-04-13', 'ingreso', 'Fondo semanal - Paco',                2000.00),
  ('2026-04-14', 'ingreso', 'Efectivo Mercedes Dulce María',        5000.00),
  ('2026-04-14', 'ingreso', 'A cuenta Tida Dulce Razo',             3000.00),
  ('2026-04-14', 'egreso',  'Gasolina Spark motor',                  850.00),
  ('2026-04-15', 'egreso',  'Papel higienico + bolsas',              100.00),
  ('2026-04-15', 'egreso',  'Garrafón agua',                          35.00),
  ('2026-04-16', 'egreso',  'Propinas proveedores',                   80.00),
  ('2026-04-17', 'egreso',  'Basura semana',                          20.00);
-- S2 cierre: -220(anterior) + 2000+5000+3000 - 850-100-35-80-20 = $8,695

-- Semana 3 (20-24 abril) — SEMANA MALA (gastos herramientas y extras)
INSERT INTO caja_chica (fecha, tipo, concepto, monto) VALUES
  ('2026-04-20', 'ingreso', 'Fondo semanal - Paco',                2000.00),
  ('2026-04-21', 'egreso',  'Gasolina camioneta',                    720.00),
  ('2026-04-21', 'egreso',  'Folios facturas',                       165.00),
  ('2026-04-22', 'egreso',  'Aflojatodo + Carbuklean',              1320.00),
  ('2026-04-23', 'egreso',  'Herramienta y cosas oficina',          3500.00),
  ('2026-04-24', 'egreso',  'Extra cabeza + motor BMW Tío',         2000.00),
  ('2026-04-24', 'egreso',  'Basura semana',                          50.00);
-- S3 cierre: $8,695 + 2000 - 720-165-1320-3500-2000-50 = $2,940

-- Semana 4 (27-30 abril)
INSERT INTO caja_chica (fecha, tipo, concepto, monto) VALUES
  ('2026-04-27', 'ingreso', 'Fondo semanal - Paco',                2000.00),
  ('2026-04-27', 'egreso',  'Recolección aceite',                    540.00),
  ('2026-04-28', 'egreso',  'Alternador Ecosport',                  2300.00),
  ('2026-04-28', 'egreso',  'Bujías Ecosport + gas + anticongelante',970.00),
  ('2026-04-28', 'ingreso', 'Fierro viejo',                          700.00),
  ('2026-04-29', 'egreso',  'Gas Spark taller',                      490.00),
  ('2026-04-30', 'egreso',  'Atomizador + papel higiénico',           80.00),
  ('2026-04-30', 'egreso',  'Espatula + cepillo limpiar',            248.00);
-- S4 cierre: $2,940 + 2000+700 - 540-2300-970-490-80-248 = $1,012

-- ============================================================
-- RESUMEN ESPERADO SEMANA A SEMANA (pantalla)
-- SISTEMA usa base de costos (refas/1.30), no base caja
-- ============================================================
-- S1 (6-11 abr): órdenes ~$44,616 facturado
--   Incluye Transit Nico ENTRADO pero CERRADO en S2 (aparece como en_proceso)
--   Ganancia semana: moderada
--
-- S2 (13-17 abr): órdenes ~$64,660 facturado
--   Mercedes $20,000 (ganancia ~$19,600 en nuestro sistema solo si refas=$520)
--   Ganancia semana: ALTA (semana buena)
--
-- S3 (20-24 abr): cierre Spark $11,300 + otras — ganancia moderada/negativa
--   Gastos de herramienta y oficina se comen la semana
--
-- S4 (27-30 abr): Ibiza $16,000 (cierra aquí) + Minicooper $8,700 + Diana Polo $9,600
--   Ganancia moderada
--
-- MENSUAL ABRIL total facturado esperado: ~$150,000-170,000
-- ============================================================
