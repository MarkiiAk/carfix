-- =============================================================================
-- SEED ABRIL 2026 — PARTE 2: Vehículos + Órdenes
-- Corre este script DESPUÉS de haber corrido la parte 1 exitosamente
-- Lee los IDs de clientes directamente por RFC (robusto, no asume consecutivos)
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Limpiar vehículos y órdenes por si quedaron a medias
DELETE FROM refacciones_orden WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'ABRIL-%');
DELETE FROM servicios_orden   WHERE orden_id IN (SELECT id FROM ordenes_servicio WHERE numero_orden LIKE 'ABRIL-%');
DELETE FROM ordenes_servicio  WHERE numero_orden LIKE 'ABRIL-%';
DELETE FROM vehiculos         WHERE numero_serie LIKE 'ABRIL-%';

SET FOREIGN_KEY_CHECKS = 1;

SET @admin_id = (SELECT id FROM usuarios WHERE rol = 'admin' LIMIT 1);
SET @admin_id = COALESCE(@admin_id, (SELECT id FROM usuarios LIMIT 1));

-- Recuperar IDs de clientes por RFC
SET @c1  = (SELECT id FROM clientes WHERE rfc = 'ABRER1' LIMIT 1);
SET @c2  = (SELECT id FROM clientes WHERE rfc = 'ABRDR2' LIMIT 1);
SET @c3  = (SELECT id FROM clientes WHERE rfc = 'ABRFD3' LIMIT 1);
SET @c4  = (SELECT id FROM clientes WHERE rfc = 'ABRNU4' LIMIT 1);
SET @c5  = (SELECT id FROM clientes WHERE rfc = 'ABRMR5' LIMIT 1);
SET @c6  = (SELECT id FROM clientes WHERE rfc = 'ABRCAQ' LIMIT 1);
SET @c7  = (SELECT id FROM clientes WHERE rfc = 'ABRMP7' LIMIT 1);
SET @c8  = (SELECT id FROM clientes WHERE rfc = 'ABRJA8' LIMIT 1);
SET @c9  = (SELECT id FROM clientes WHERE rfc = 'ABRLF9' LIMIT 1);
SET @c10 = (SELECT id FROM clientes WHERE rfc = 'ABRSD0' LIMIT 1);
SET @c11 = (SELECT id FROM clientes WHERE rfc = 'ABRARI' LIMIT 1);
SET @c12 = (SELECT id FROM clientes WHERE rfc = 'ABRRB2' LIMIT 1);
SET @c13 = (SELECT id FROM clientes WHERE rfc = 'ABRDMA' LIMIT 1);
SET @c14 = (SELECT id FROM clientes WHERE rfc = 'ABREKA' LIMIT 1);
SET @c15 = (SELECT id FROM clientes WHERE rfc = 'ABRBCG' LIMIT 1);
SET @c16 = (SELECT id FROM clientes WHERE rfc = 'ABRDRZ' LIMIT 1);
SET @c17 = (SELECT id FROM clientes WHERE rfc = 'ABRAVR' LIMIT 1);
SET @c18 = (SELECT id FROM clientes WHERE rfc = 'ABRMRC' LIMIT 1);
SET @c19 = (SELECT id FROM clientes WHERE rfc = 'ABRVTL' LIMIT 1);
SET @c20 = (SELECT id FROM clientes WHERE rfc = 'ABRHCS' LIMIT 1);
SET @c21 = (SELECT id FROM clientes WHERE rfc = 'ABREJT' LIMIT 1);
SET @c22 = (SELECT id FROM clientes WHERE rfc = 'ABRIFS' LIMIT 1);
SET @c23 = (SELECT id FROM clientes WHERE rfc = 'ABRDGM' LIMIT 1);
SET @c24 = (SELECT id FROM clientes WHERE rfc = 'ABRDCR' LIMIT 1);
SET @c25 = (SELECT id FROM clientes WHERE rfc = 'ABRLMI' LIMIT 1);
SET @c26 = (SELECT id FROM clientes WHERE rfc = 'ABRSTL' LIMIT 1);
SET @c27 = (SELECT id FROM clientes WHERE rfc = 'ABRCCM' LIMIT 1);
SET @c28 = (SELECT id FROM clientes WHERE rfc = 'ABRDBM' LIMIT 1);
SET @c29 = (SELECT id FROM clientes WHERE rfc = 'ABRUAM' LIMIT 1);
SET @c30 = (SELECT id FROM clientes WHERE rfc = 'ABRDMC' LIMIT 1);
SET @c31 = (SELECT id FROM clientes WHERE rfc = 'ABRJEP' LIMIT 1);
SET @c32 = (SELECT id FROM clientes WHERE rfc = 'ABRJVB' LIMIT 1);
SET @c33 = (SELECT id FROM clientes WHERE rfc = 'ABRAPA' LIMIT 1);
SET @c34 = (SELECT id FROM clientes WHERE rfc = 'ABRTFA' LIMIT 1);
SET @c35 = (SELECT id FROM clientes WHERE rfc = 'ABREMB' LIMIT 1);
SET @c36 = (SELECT id FROM clientes WHERE rfc = 'ABRDPL' LIMIT 1);
SET @c37 = (SELECT id FROM clientes WHERE rfc = 'ABRCAQ' LIMIT 1); -- Fernando Díaz usa mismo cliente que Chrysler

-- ============================================================
-- VEHÍCULOS
-- ============================================================
INSERT INTO vehiculos (cliente_id, marca, modelo, anio, color, placas, numero_serie, kilometraje) VALUES
  (@c1,  'Mazda',      'CX-5',         2019, 'Negro',   'PZH496D', 'ABRIL-M001',  104355),
  (@c2,  'Ford',       'Camioneta',    2015, 'Gris',    'RTX0001', 'ABRIL-F002',   80000),
  (@c3,  'Kia',        'Sorrento',     2016, 'Blanco',  'LVC000A', 'ABRIL-K003',   95000),
  (@c4,  'Ford',       'Transit',      2024, 'Blanco',  'KT2511A', 'ABRIL-F004',   22000),
  (@c5,  'Ford',       'Focus',        2013, 'Gris',    'LVC671B', 'ABRIL-F005',   98500),
  (@c6,  'Jeep',       'Compass',      2010, 'Gris',    'PDP5374', 'ABRIL-J006',  145000),
  (@c7,  'Nissan',     'NP300',        2014, 'Blanco',  'LNW653B', 'ABRIL-N007',   88000),
  (@c8,  'Mini',       'Cooper',       2016, 'Negro',   'MMB001A', 'ABRIL-MI008',  45000),
  (@c9,  'Volkswagen', 'Beatle',       2013, 'Negro',   'LUC888A', 'ABRIL-V009',  110000),
  (@c10, 'Volkswagen', 'Pointer',      2003, 'Blanco',  'NTD040Z', 'ABRIL-V010',  195000),
  (@c11, 'Volkswagen', 'Vento',        2018, 'Rojo',    'PAZ8043', 'ABRIL-V011',   55000),
  (@c12, 'Honda',      'HRV',          2018, 'Azul',    'S13AWS',  'ABRIL-H012',   72000),
  (@c13, 'Mercedes',   'Clase C',      2020, 'Blanco',  'PZB870B', 'ABRIL-M013',   45200),
  (@c14, 'Kia',        'Sportage',     2018, 'Rojo',    'LJK3345', 'ABRIL-K014',   88000),
  (@c15, 'Volkswagen', 'Gol',          2010, 'Gris',    'NPA313B', 'ABRIL-V015',  142000),
  (@c16, 'Volkswagen', 'Tida',         2011, 'Gris',    'LRX8843', 'ABRIL-V016',  155000),
  (@c17, 'Suzuki',     'Ertiga',       2022, 'Blanco',  'PBU5584', 'ABRIL-S017',   19314),
  (@c18, 'Chevrolet',  'Captiva',      2014, 'Negra',   'NPP235C', 'ABRIL-C018',  104310),
  (@c19, 'Volkswagen', 'Pointer',      2006, 'Blanco',  'NTD041B', 'ABRIL-V019',  198000),
  (@c20, 'Chevrolet',  'Spark Clásico',2017, 'Verde',   'NZW5440', 'ABRIL-C020',   80060),
  (@c21, 'Volkswagen', 'Jetta',        2016, 'Rojo',    'NDV429B', 'ABRIL-V021',  106273),
  (@c22, 'Ford',       'Fusion',       2016, 'Negro',   'NZR083C', 'ABRIL-F022',  154428),
  (@c23, 'Mazda',      'CX-5',         2018, 'Gris',    'J14BFH',  'ABRIL-M023',  169758),
  (@c24, 'Honda',      'CRV',          2007, 'Gris',    'LZA878C', 'ABRIL-H024',  180000),
  (@c25, 'Mazda',      'Mazda 2',      2018, 'Azul',    'LLA454B', 'ABRIL-M025',   72870),
  (@c26, 'Volkswagen', 'Pointer',      2006, 'Blanco',  'NTD042C', 'ABRIL-V026',  200000),
  (@c27, 'Mazda',      'CX-5',         2016, 'Azul',    'RDY147B', 'ABRIL-M027',  171786),
  (@c28, 'Nissan',     'March',        2019, 'Gris',    'S47BJU',  'ABRIL-N028',   95000),
  (@c29, 'Mercedes',   'Clase C',      2020, 'Negro',   'PZB870C', 'ABRIL-M029',   46000),
  (@c30, 'Mini',       'Cooper',       2016, 'Rojo',    'SRT001B', 'ABRIL-MI030',  55000),
  (@c31, 'Seat',       'Ibiza',        2013, 'Gris',    'NTH273A', 'ABRIL-S031',  153630),
  (@c32, 'Dodge',      'Bocanegra',    2013, 'Negro',   'LHJ001B', 'ABRIL-D032',   90000),
  (@c33, 'Audi',       'A4',           2015, 'Negro',   'PEC6540', 'ABRIL-A033',   65000),
  (@c34, 'Ford',       'Fiesta',       2015, 'Azul',    'LMN001B', 'ABRIL-F034',   88000),
  (@c35, 'Mini',       'Cooper',       2016, 'Azul',    'WMW001B', 'ABRIL-MI035',  45000),
  (@c36, 'Volkswagen', 'Polo',         2015, 'Blanco',  'NWC105C', 'ABRIL-V036',   84330),
  (@c37, 'Chrysler',   'C200',         2015, 'Gris',    'RTX002B', 'ABRIL-C037',  125000);

-- Recuperar IDs de vehículos por numero_serie
SET @v1  = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-M001' LIMIT 1);
SET @v2  = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-F002' LIMIT 1);
SET @v3  = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-K003' LIMIT 1);
SET @v4  = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-F004' LIMIT 1);
SET @v5  = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-F005' LIMIT 1);
SET @v6  = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-J006' LIMIT 1);
SET @v7  = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-N007' LIMIT 1);
SET @v8  = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-MI008' LIMIT 1);
SET @v9  = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-V009' LIMIT 1);
SET @v10 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-V010' LIMIT 1);
SET @v11 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-V011' LIMIT 1);
SET @v12 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-H012' LIMIT 1);
SET @v13 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-M013' LIMIT 1);
SET @v14 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-K014' LIMIT 1);
SET @v15 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-V015' LIMIT 1);
SET @v16 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-V016' LIMIT 1);
SET @v17 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-S017' LIMIT 1);
SET @v18 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-C018' LIMIT 1);
SET @v19 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-V019' LIMIT 1);
SET @v20 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-C020' LIMIT 1);
SET @v21 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-V021' LIMIT 1);
SET @v22 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-F022' LIMIT 1);
SET @v23 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-M023' LIMIT 1);
SET @v24 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-H024' LIMIT 1);
SET @v25 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-M025' LIMIT 1);
SET @v26 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-V026' LIMIT 1);
SET @v27 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-M027' LIMIT 1);
SET @v28 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-N028' LIMIT 1);
SET @v29 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-M029' LIMIT 1);
SET @v30 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-MI030' LIMIT 1);
SET @v31 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-S031' LIMIT 1);
SET @v32 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-D032' LIMIT 1);
SET @v33 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-A033' LIMIT 1);
SET @v34 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-F034' LIMIT 1);
SET @v35 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-MI035' LIMIT 1);
SET @v36 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-V036' LIMIT 1);
SET @v37 = (SELECT id FROM vehiculos WHERE numero_serie = 'ABRIL-C037' LIMIT 1);

-- ============================================================
-- SEMANA 1: 6-11 ABRIL 2026
-- ============================================================
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-001',@c1,@v1,@admin_id,'Afinación motor + descarbonización + cambio aceite ATF sintético',4400.00,0.00,5300.00,0.00,0,0.00,9700.00,0.00,'entregada','2026-04-06 09:00:00','2026-04-06 16:00:00','2026-04-06 17:00:00');
SET @o1=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o1,'mano_obra','Afinación motor',2900.00,1,2900.00),(@o1,'mano_obra','Descarbonización válvulas',1500.00,1,1500.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o1,'Filtro + aceite Autozone',1,580.00,580.00,'Autozone'),(@o1,'6 Lts aceite ATF STP',1,4720.00,4720.00,'Autozone');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-002',@c2,@v2,@admin_id,'Escaneo diagnóstico',0.00,300.00,0.00,0.00,0,0.00,300.00,0.00,'entregada','2026-04-06 10:00:00','2026-04-06 11:00:00','2026-04-06 11:30:00');
SET @o2=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o2,'servicio','Escaneo diagnóstico',300.00,1,300.00);

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-003',@c3,@v3,@admin_id,'Frenos delanteros + suspensión delantera',2054.00,0.00,7816.00,0.00,0,0.00,9870.00,0.00,'entregada','2026-04-06 09:00:00','2026-04-07 16:00:00','2026-04-07 17:00:00');
SET @o3=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o3,'mano_obra','Frenos delanteros + suspensión',2054.00,1,2054.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o3,'Discos BEST',2,2132.00,4264.00,'BEST'),(@o3,'Bujes Estrella',1,1898.00,1898.00,'Estrella'),(@o3,'Rótulas Estrella',1,1040.00,1040.00,'Estrella'),(@o3,'MO suspensión',1,614.00,614.00,'interno');

-- Transit Nico — ENTRA S1, cierra S2
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-004',@c4,@v4,@admin_id,'Frenos del+traseros + verificación EDOMEX',1870.00,1250.00,3475.00,0.00,0,0.00,6595.00,0.00,'entregada','2026-04-06 09:30:00','2026-04-16 15:00:00','2026-04-17 10:00:00');
SET @o4=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o4,'mano_obra','Frenos del+tras',1870.00,1,1870.00),(@o4,'servicio','Verificación EDOMEX',1250.00,1,1250.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o4,'4 Rectificados BEST',4,290.00,1160.00,'BEST'),(@o4,'Balatas traseras BEST',1,1891.00,1891.00,'BEST'),(@o4,'Sensor BEST',1,424.00,424.00,'BEST');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-005',@c5,@v5,@admin_id,'No arranca — marcha',1000.00,0.00,2800.00,0.00,0,0.00,3800.00,0.00,'entregada','2026-04-08 09:00:00','2026-04-08 13:00:00','2026-04-08 14:00:00');
SET @o5=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o5,'mano_obra','Cambio marcha',1000.00,1,1000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o5,'Marcha Autozone',1,2800.00,2800.00,'Autozone');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-006',@c6,@v6,@admin_id,'Falla carga — alternador',1800.00,0.00,936.00,0.00,0,0.00,3000.00,0.00,'entregada','2026-04-08 10:00:00','2026-04-08 16:00:00','2026-04-08 17:00:00');
SET @o6=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o6,'mano_obra','Reparación alternador',1800.00,1,1800.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o6,'Polea + baleros',1,936.00,936.00,'Hernández');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-007',@c7,@v7,@admin_id,'Afinación preventiva sin bujías',0.00,2800.00,0.00,0.00,0,0.00,2800.00,0.00,'entregada','2026-04-10 09:00:00','2026-04-10 13:00:00','2026-04-10 14:00:00');
SET @o7=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o7,'servicio','Afinación Autozone',2800.00,1,2800.00);

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-007B',@c7,@v7,@admin_id,'Cambiar clutch — cliente trajo su refacción',2000.00,0.00,0.00,0.00,0,0.00,2000.00,0.00,'entregada','2026-04-13 09:00:00','2026-04-13 16:00:00','2026-04-13 17:00:00');
SET @o7b=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o7b,'mano_obra','Cambio clutch (refacción del cliente)',2000.00,1,2000.00);

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-008',@c8,@v8,@admin_id,'Bobinas + bujías + inyectores + ligas',3052.00,0.00,8948.00,0.00,0,0.00,12000.00,0.00,'entregada','2026-04-10 09:00:00','2026-04-10 17:00:00','2026-04-10 18:00:00');
SET @o8=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o8,'mano_obra','Cambio bobinas + bujías + inyectores',3052.00,1,3052.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o8,'2 Bobinas SAFE',2,1690.00,3380.00,'SAFE'),(@o8,'4 Bujías SAFE',1,780.00,780.00,'SAFE'),(@o8,'Ligas tapa punterias',1,3225.20,3225.20,'SAFE'),(@o8,'Filtro aire',1,312.00,312.00,'SAFE'),(@o8,'Enfriador aceite',1,1250.80,1250.80,'SAFE');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-009',@c9,@v9,@admin_id,'Cuerpo aceleración + sensores + inyectores',2500.00,0.00,4450.00,0.00,0,0.00,6950.00,0.00,'entregada','2026-04-10 10:00:00','2026-04-10 17:00:00','2026-04-10 18:00:00');
SET @o9=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o9,'mano_obra','Cuerpo aceleración + inyectores',2500.00,1,2500.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o9,'Cuerpo aceleración J Rosas',1,3900.00,3900.00,'Juventino Rosas'),(@o9,'Bulbo + inyectores',1,550.00,550.00,'Autozone');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-010',@c10,@v10,@admin_id,'Cambio concha espejo',0.00,600.00,1200.00,0.00,0,0.00,1800.00,0.00,'entregada','2026-04-11 09:00:00','2026-04-11 11:00:00','2026-04-11 11:30:00');
SET @o10=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o10,'servicio','Cambio concha espejo',600.00,1,600.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o10,'Concha espejo Doctores',1,1560.00,1560.00,'Doctores');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-011',@c37,@v37,@admin_id,'Bujes + horquillas + bases + amortiguadores',3000.00,0.00,7066.00,0.00,0,0.00,10066.00,0.00,'entregada','2026-04-11 09:00:00','2026-04-11 17:00:00','2026-04-11 18:00:00');
SET @o11=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o11,'mano_obra','Bujes + horquillas + amortiguadores',3000.00,1,3000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o11,'Bases + amortiguadores Autozone',1,4420.00,4420.00,'Autozone'),(@o11,'Bases Autozone',1,2503.20,2503.20,'Autozone'),(@o11,'Bujes UGSA',1,142.80,142.80,'UGSA');

-- ============================================================
-- SEMANA 2: 13-17 ABRIL 2026
-- ============================================================
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-012',@c11,@v11,@admin_id,'Servicio sin bujías + verificación EDOMEX',0.00,4300.00,612.00,0.00,0,0.00,4300.00,0.00,'entregada','2026-04-13 09:00:00','2026-04-13 15:00:00','2026-04-13 16:00:00');
SET @o12=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o12,'servicio','Servicio UGSA',3000.00,1,3000.00),(@o12,'servicio','Verificación EDOMEX',1300.00,1,1300.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o12,'Filtros UGSA',1,612.00,612.00,'UGSA');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-013',@c12,@v12,@admin_id,'Verificación CDMX',0.00,1300.00,0.00,0.00,0,0.00,1300.00,0.00,'entregada','2026-04-14 09:00:00','2026-04-14 12:00:00','2026-04-14 13:00:00');
SET @o13=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o13,'servicio','Verificación CDMX',1300.00,1,1300.00);

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-013B',@c8,@v8,@admin_id,'Cartucho turbo segunda visita',0.00,4200.00,0.00,0.00,0,0.00,4200.00,0.00,'entregada','2026-04-14 09:00:00','2026-04-14 15:00:00','2026-04-14 16:00:00');
SET @o13b=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o13b,'mano_obra','Cartucho turbo Minicooper',4200.00,1,4200.00);

-- LA BOMBA: Mercedes Dulce María $20,000
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-014',@c13,@v13,@admin_id,'Servicio mayor + soportes motor + diagnóstico electrónico',5000.00,0.00,15000.00,0.00,0,0.00,20000.00,0.00,'entregada','2026-04-14 09:00:00','2026-04-14 16:00:00','2026-04-14 17:00:00');
SET @o14=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o14,'mano_obra','Servicio mayor + soportes + diagnóstico',5000.00,1,5000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o14,'Soportes motor SAFE',2,5720.00,11440.00,'SAFE'),(@o14,'Bujías + filtros SAFE',1,2548.00,2548.00,'SAFE'),(@o14,'Vulcanizado buje Legaría',1,520.00,520.00,'Legaría'),(@o14,'Aceite + filtro',1,492.00,492.00,'SAFE');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-015',@c14,@v14,@admin_id,'Servicio sin bujías domicilio',0.00,3250.00,0.00,0.00,0,0.00,3250.00,0.00,'entregada','2026-04-14 08:00:00','2026-04-14 14:00:00','2026-04-14 15:00:00');
SET @o15=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o15,'servicio','Servicio UGSA + domicilio',3000.00,1,3000.00),(@o15,'servicio','Recolección domicilio',250.00,1,250.00);

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-016',@c15,@v15,@admin_id,'No arranca — batería',0.00,1150.00,1800.00,0.00,0,0.00,2950.00,0.00,'entregada','2026-04-14 11:00:00','2026-04-14 13:00:00','2026-04-14 14:00:00');
SET @o16=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o16,'servicio','Cambio batería',1150.00,1,1150.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o16,'Batería Autozone 18m',1,1800.00,1800.00,'Autozone');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,fecha_anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-017',@c16,@v16,@admin_id,'Clutch patinando',2800.00,0.00,8360.00,0.00,0,0.00,11160.00,3000.00,'2026-04-14','entregada','2026-04-14 11:00:00','2026-04-15 15:00:00','2026-04-15 16:00:00');
SET @o17=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o17,'mano_obra','Clutch + soporte + banda + chicote',2800.00,1,2800.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o17,'Clutch LUK Autozone',1,3960.00,3960.00,'Autozone'),(@o17,'Cilindro maestro',1,1150.00,1150.00,'Autozone'),(@o17,'Soporte UGSA',1,950.00,950.00,'UGSA'),(@o17,'Chicote Bucareli',1,1900.00,1900.00,'Bucareli'),(@o17,'Banda Autozone',1,400.00,400.00,'Autozone');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-018',@c17,@v17,@admin_id,'Servicio sin bujías + soportes motor',900.00,2900.00,6250.00,0.00,0,0.00,10050.00,0.00,'entregada','2026-04-17 09:00:00','2026-04-17 17:00:00','2026-04-17 18:00:00');
SET @o18=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o18,'servicio','Servicio sin bujías Autozone',2900.00,1,2900.00),(@o18,'mano_obra','Cambio 2 soportes',900.00,1,900.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o18,'Soporte derecho Ramírez DAI',1,2950.00,2950.00,'Ramírez DAI'),(@o18,'Soporte caja Ramírez DAI',1,3300.00,3300.00,'Ramírez DAI');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-019',@c18,@v18,@admin_id,'Servicio menor + alineación',150.00,2200.00,0.00,0.00,0,0.00,2350.00,0.00,'entregada','2026-04-16 10:00:00','2026-04-16 14:00:00','2026-04-16 15:00:00');
SET @o19=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o19,'servicio','Servicio menor',2200.00,1,2200.00),(@o19,'mano_obra','Alineación',150.00,1,150.00);

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-020',@c19,@v19,@admin_id,'Sensor temperatura + reparación líneas',940.00,0.00,260.00,0.00,0,0.00,1200.00,0.00,'entregada','2026-04-17 11:00:00','2026-04-17 14:00:00','2026-04-17 15:00:00');
SET @o20=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o20,'mano_obra','Sensor + líneas',940.00,1,940.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o20,'Sensor temperatura Autozone',1,260.00,260.00,'Autozone');

-- ============================================================
-- SEMANA 3: 20-24 ABRIL 2026
-- ============================================================
-- Spark — ENTRÓ S1 (abr 4), cierra S3 (abr 20)
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,fecha_anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-021',@c20,@v20,@admin_id,'Rectificado motor + inyectores + distribución',5500.00,0.00,5800.00,0.00,0,0.00,11300.00,5000.00,'2026-04-04','entregada','2026-04-04 09:00:00','2026-04-19 17:00:00','2026-04-20 10:00:00');
SET @o21=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o21,'mano_obra','MO taller',3500.00,1,3500.00),(@o21,'mano_obra','Rectificadora San Mateo',2000.00,1,2000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o21,'Junta tapa Autozone',1,350.00,350.00,'Autozone'),(@o21,'Manguera UGSA',1,750.00,750.00,'UGSA'),(@o21,'Junta cabeza',1,750.00,750.00,'Ramirez'),(@o21,'Anticongelante Autozone',1,450.00,450.00,'Autozone'),(@o21,'Kit distribución Autozone',1,3500.00,3500.00,'Autozone');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,fecha_anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-022',@c21,@v21,@admin_id,'Servicio sin bujías',0.00,3000.00,754.00,0.00,0,0.00,3000.00,2000.00,'2026-04-20','entregada','2026-04-20 10:00:00','2026-04-21 13:00:00','2026-04-21 14:00:00');
SET @o22=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o22,'servicio','Servicio sin bujías Autozone',3000.00,1,3000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o22,'Filtros Autozone',1,754.00,754.00,'Autozone');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-023',@c22,@v22,@admin_id,'Cambio aceite + lavado motor',0.00,1300.00,0.00,0.00,0,0.00,1300.00,0.00,'entregada','2026-04-21 09:00:00','2026-04-21 11:00:00','2026-04-21 11:30:00');
SET @o23=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o23,'servicio','Cambio aceite + lavado',1300.00,1,1300.00);

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-024',@c23,@v23,@admin_id,'Servicio con bujías + sensor ABS + bases',1200.00,3500.00,1400.00,0.00,0,0.00,6100.00,0.00,'entregada','2026-04-21 10:00:00','2026-04-21 16:00:00','2026-04-21 17:00:00');
SET @o24=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o24,'servicio','Servicio con bujías UGSA',3500.00,1,3500.00),(@o24,'mano_obra','Sensor ABS + bases',1200.00,1,1200.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o24,'Sensor + filtros',1,1400.00,1400.00,'UGSA');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-025',@c24,@v24,@admin_id,'Bases amortiguadores + tornillos',2250.00,0.00,0.00,0.00,0,0.00,2250.00,0.00,'entregada','2026-04-21 09:00:00','2026-04-21 14:00:00','2026-04-21 15:00:00');
SET @o25=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o25,'mano_obra','Bases + tornillos (cliente trajo refas)',2250.00,1,2250.00);

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-026',@c25,@v25,@admin_id,'Servicio con bujías + revisión moto',300.00,3800.00,1183.00,0.00,0,0.00,4100.00,0.00,'entregada','2026-04-22 09:00:00','2026-04-22 15:00:00','2026-04-22 16:00:00');
SET @o26=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o26,'servicio','Servicio bujías UGSA',3800.00,1,3800.00),(@o26,'servicio','Revisión moto',300.00,1,300.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o26,'Filtros + aceite UGSA',1,1183.00,1183.00,'UGSA');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-027',@c26,@v26,@admin_id,'Servicio bujías + inyectores + alternador',0.00,2800.00,3100.00,0.00,0,0.00,5900.00,0.00,'entregada','2026-04-22 10:00:00','2026-04-22 16:00:00','2026-04-22 17:00:00');
SET @o27=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o27,'servicio','Servicio bujías Autozone',2800.00,1,2800.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o27,'Inyectores J Rosas',1,1950.00,1950.00,'Juventino Rosas'),(@o27,'Alternador Hernández',1,1150.00,1150.00,'Hernández');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-028',@c27,@v27,@admin_id,'Servicio bujías + soporte + descarbonización',1900.00,3500.00,3450.00,0.00,0,0.00,8850.00,0.00,'entregada','2026-04-23 09:00:00','2026-04-23 16:00:00','2026-04-23 17:00:00');
SET @o28=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o28,'servicio','Servicio bujías Autozone',3500.00,1,3500.00),(@o28,'mano_obra','Soporte + banda + descarbonización',1900.00,1,1900.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o28,'Soporte UGSA SYD',1,2600.00,2600.00,'UGSA'),(@o28,'Banda Autozone',1,850.00,850.00,'Autozone');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-029',@c28,@v28,@admin_id,'Batería no carga — acumulador',200.00,0.00,3300.00,0.00,0,0.00,3500.00,0.00,'entregada','2026-04-23 10:00:00','2026-04-23 12:00:00','2026-04-23 13:00:00');
SET @o29=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o29,'mano_obra','Cambio acumulador',200.00,1,200.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o29,'Acumulador Autozone',1,3300.00,3300.00,'Autozone');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-030',@c29,@v29,@admin_id,'Soporte motor derecho + focos DS1',1050.00,0.00,3400.00,0.00,0,0.00,4450.00,0.00,'entregada','2026-04-23 11:00:00','2026-04-23 16:00:00','2026-04-23 17:00:00');
SET @o30=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o30,'mano_obra','Soporte + focos',1050.00,1,1050.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o30,'Soporte SAFE',1,2418.00,2418.00,'SAFE'),(@o30,'2 Focos DS1',2,491.00,982.00,'SAFE');

-- Ibiza — ENTRA S3, cierra S4
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-031',@c31,@v31,@admin_id,'Horquillas + reparación módulo dirección',5000.00,0.00,11000.00,0.00,0,0.00,16000.00,0.00,'entregada','2026-04-22 09:00:00','2026-04-26 16:00:00','2026-04-27 10:00:00');
SET @o31=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o31,'mano_obra','Horquillas + dirección + alineación',5000.00,1,5000.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o31,'Horquillas Autozone',2,1365.00,2730.00,'Autozone'),(@o31,'Caja dirección El Huevo',1,4160.00,4160.00,'El Huevo'),(@o31,'Aceite dirección + otros',1,1677.00,1677.00,'Autozone'),(@o31,'Alineación + varios',1,2433.00,2433.00,'varios');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-032',@c4,@v4,@admin_id,'Servicio básico — filtro + aceite',0.00,3700.00,394.00,0.00,0,0.00,3700.00,0.00,'entregada','2026-04-23 09:00:00','2026-04-23 12:00:00','2026-04-23 13:00:00');
SET @o32=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o32,'servicio','Servicio básico Autozone',3700.00,1,3700.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o32,'Filtro aire + aceite',1,394.00,394.00,'Autozone');

-- ============================================================
-- SEMANA 4: 27-30 ABRIL 2026
-- ============================================================
INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-033',@c30,@v30,@admin_id,'Frenos traseros',750.00,0.00,3796.00,0.00,0,0.00,4546.00,0.00,'entregada','2026-04-27 09:00:00','2026-04-27 14:00:00','2026-04-27 15:00:00');
SET @o33=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o33,'mano_obra','Frenos traseros',750.00,1,750.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o33,'Balatas Akebono Univbrakes',1,3016.00,3016.00,'Univbrakes'),(@o33,'Rectificados Silva',2,240.00,480.00,'Silva'),(@o33,'Didi',1,300.00,300.00,'Didi');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-034',@c32,@v32,@admin_id,'Servicio sin bujías',0.00,3100.00,676.00,0.00,0,0.00,3100.00,0.00,'entregada','2026-04-27 10:00:00','2026-04-27 15:00:00','2026-04-27 16:00:00');
SET @o34=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o34,'servicio','Servicio sin bujías UGSA',3100.00,1,3100.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o34,'Filtros UGSA',1,676.00,676.00,'UGSA');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-035',@c33,@v33,@admin_id,'Termostato + toma agua + sensor nivel aceite',1800.00,0.00,3820.00,0.00,0,0.00,5620.00,0.00,'entregada','2026-04-27 09:00:00','2026-04-27 16:00:00','2026-04-27 17:00:00');
SET @o35=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o35,'mano_obra','Termostato + toma + sensor',1800.00,1,1800.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o35,'Termostato + toma SAFE',1,1105.00,1105.00,'SAFE'),(@o35,'Sensor SAFE',1,1677.00,1677.00,'SAFE'),(@o35,'Aceite + abrazaderas',1,1038.00,1038.00,'Autozone');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-036',@c34,@v34,@admin_id,'Verificación CDMX',0.00,1600.00,0.00,0.00,0,0.00,1600.00,0.00,'entregada','2026-04-27 08:00:00','2026-04-27 11:00:00','2026-04-27 12:00:00');
SET @o36=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o36,'servicio','Verificación CDMX + brinco',1600.00,1,1600.00);

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-037',@c35,@v35,@admin_id,'Soportes laterales + termostato',2400.00,0.00,6300.00,0.00,0,0.00,8700.00,0.00,'entregada','2026-04-30 09:00:00','2026-04-30 16:00:00','2026-04-30 17:00:00');
SET @o37=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o37,'mano_obra','Soportes + termostato + anticongelante',2400.00,1,2400.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o37,'Soporte der SAFE',1,3965.00,3965.00,'SAFE'),(@o37,'Soporte izq SAFE',1,1761.00,1761.00,'SAFE'),(@o37,'Termostato Univpart',1,574.00,574.00,'Univpart');

INSERT INTO ordenes_servicio (numero_orden,cliente_id,vehiculo_id,usuario_id,problema_reportado,subtotal_mano_obra,subtotal_servicios,subtotal_refacciones,descuento,incluir_iva,iva,total,anticipo,estado,fecha_ingreso,fecha_completada,fecha_entregada) VALUES
('ABRIL-038',@c36,@v36,@admin_id,'Servicio bujías + frenos + bujes horquilla',750.00,3800.00,5050.00,0.00,0,0.00,9600.00,0.00,'entregada','2026-05-01 09:00:00','2026-05-01 16:00:00','2026-05-01 17:00:00');
SET @o38=LAST_INSERT_ID();
INSERT INTO servicios_orden(orden_id,tipo,descripcion,precio_unitario,cantidad,subtotal) VALUES (@o38,'servicio','Servicio bujías Autozone',3800.00,1,3800.00),(@o38,'mano_obra','Frenos delanteros',750.00,1,750.00);
INSERT INTO refacciones_orden(orden_id,descripcion,cantidad,precio_unitario,subtotal,proveedor) VALUES (@o38,'Balatas Bioeramic Silva',1,1235.00,1235.00,'Silva'),(@o38,'Discos Autozone',2,1430.00,2860.00,'Autozone'),(@o38,'Bujes horquilla Autozone',1,955.00,955.00,'Autozone');

SELECT CONCAT('✅ ABRIL seed completo: ', COUNT(*), ' órdenes insertadas') AS resultado
FROM ordenes_servicio WHERE numero_orden LIKE 'ABRIL-%';
