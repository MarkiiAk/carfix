SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';
SET NAMES utf8mb4;

CREATE TABLE `alertas_ejecucion_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sucursal_id` int(11) NOT NULL DEFAULT 1,
  `fecha_ejecucion` date NOT NULL,
  `alertas_generadas` int(11) DEFAULT 0,
  `tiempo_ejecucion_ms` int(11) DEFAULT 0,
  `usuario_id` int(11) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de ejecuciones diarias del sistema automático de generación de alertas';

CREATE TABLE `alertas_servicio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL DEFAULT 1,
  `cliente_id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `fecha_ultimo_servicio` timestamp NOT NULL,
  `servicios_que_dispararon` text NOT NULL,
  `todos_los_servicios` text DEFAULT NULL,
  `estado` enum('pendiente','leida') DEFAULT 'pendiente',
  `estado_whatsapp` enum('borrador','enviado','esperando_respuesta','esperando_fecha','pre_agendado','confirmado','rechazado','requiere_contacto','cancelado','completado') DEFAULT 'borrador',
  `slots_ofrecidos_json` text DEFAULT NULL COMMENT 'JSON con slots ofrecidos al cliente para mapear su respuesta numérica a un slot real',
  `twilio_conversation_sid` varchar(100) DEFAULT NULL,
  `fecha_envio_whatsapp` datetime DEFAULT NULL,
  `respuesta_inicial` varchar(50) DEFAULT NULL,
  `fecha_respuesta_inicial` datetime DEFAULT NULL,
  `fecha_cita_seleccionada` date DEFAULT NULL,
  `hora_cita_seleccionada` time DEFAULT NULL,
  `fecha_pre_agendado` datetime DEFAULT NULL,
  `confirmacion_sag` enum('pendiente','confirmado','cancelado') DEFAULT NULL,
  `estado_twilio` enum('borrador','enviado','esperando_respuesta','esperando_fecha','pre_agendado','confirmado','cancelado','completado','requiere_atencion','reprogramar') DEFAULT 'borrador',
  `twilio_message_sid` varchar(100) DEFAULT NULL,
  `respuesta_cliente` text DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL,
  `fecha_seleccionada_cliente` date DEFAULT NULL,
  `hora_seleccionada_cliente` time DEFAULT NULL,
  `fecha_confirmacion_sag` datetime DEFAULT NULL,
  `usuario_confirmo_sag` int(11) DEFAULT NULL,
  `requiere_atencion` tinyint(1) DEFAULT 0,
  `prioridad` enum('baja','media','alta') DEFAULT 'media',
  `ultima_actividad` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `confirmado_por` varchar(20) DEFAULT NULL,
  `fecha_cancelacion` datetime DEFAULT NULL,
  `cancelado_por` varchar(20) DEFAULT NULL,
  `fecha_reprogramacion` datetime DEFAULT NULL,
  `prioridad_atencion` enum('baja','media','alta') DEFAULT 'media',
  `fecha_generada` timestamp NULL DEFAULT current_timestamp(),
  `fecha_marcada_leida` timestamp NULL DEFAULT NULL,
  `usuario_marco_leida` int(11) DEFAULT NULL,
  `dias_desde_servicio` int(11) NOT NULL,
  `intentos_invalidos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Respuestas fuera de opciones en el paso actual; se resetea al avanzar estado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla principal de alertas con integración WhatsApp - Actualizada 30/03/2026';

CREATE TABLE `caja_chica` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `tipo` enum('ingreso','egreso') NOT NULL,
  `concepto` varchar(150) NOT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notas` text DEFAULT NULL,
  `gasto_admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sucursal_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `calendario_disponibilidad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sucursal_id` int(11) NOT NULL DEFAULT 1,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `capacidad_total` int(11) DEFAULT 2,
  `citas_ocupadas` int(11) DEFAULT 0,
  `esta_disponible` tinyint(1) DEFAULT 1,
  `es_dia_laborable` tinyint(1) DEFAULT 1,
  `notas` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `citas_pre_agendadas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alerta_id` int(11) NOT NULL,
  `calendario_slot_id` int(11) NOT NULL,
  `cliente_nombre` varchar(200) NOT NULL,
  `cliente_telefono` varchar(20) NOT NULL,
  `vehiculo_info` varchar(300) NOT NULL,
  `tipo_servicio` varchar(200) NOT NULL,
  `estado` enum('pre_agendada','confirmada','cancelada') DEFAULT 'pre_agendada',
  `fecha_pre_agenda` datetime DEFAULT current_timestamp(),
  `fecha_confirmacion` datetime DEFAULT NULL,
  `fecha_cancelacion` datetime DEFAULT NULL,
  `usuario_confirmo_id` int(11) DEFAULT NULL,
  `notas_cliente` text DEFAULT NULL,
  `notas_internas` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sucursal_id` int(11) NOT NULL DEFAULT 1,
  `nombre` varchar(200) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `rfc` varchar(20) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT current_timestamp(),
  `ultima_visita` timestamp NULL DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fusionado_en` int(11) DEFAULT NULL,
  `fusionado_at` timestamp NULL DEFAULT NULL,
  `notas_merge` varchar(500) DEFAULT NULL,
  `telefono_normalizado` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `conversaciones_whatsapp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `alerta_id` int(11) NOT NULL,
  `twilio_message_sid` varchar(100) NOT NULL,
  `direction` enum('outbound','inbound') NOT NULL,
  `from_number` varchar(50) NOT NULL,
  `to_number` varchar(50) NOT NULL,
  `message_body` text DEFAULT NULL,
  `message_type` enum('text','interactive','template') DEFAULT 'text',
  `message_status` varchar(20) DEFAULT NULL,
  `error_code` varchar(20) DEFAULT NULL,
  `error_message` mediumtext DEFAULT NULL,
  `twilio_response` longtext DEFAULT NULL CHECK (json_valid(`twilio_response`)),
  `conversation_step` enum('recordatorio_inicial','respuesta_si_no','seleccion_fecha','confirmacion_pre_agenda','confirmacion_sag','mensaje_final','contacto_directo') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `danos_vehiculo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_id` int(11) NOT NULL,
  `ubicacion` varchar(200) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `empleados_sueldos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `nombre` varchar(120) NOT NULL,
  `puesto` varchar(80) DEFAULT NULL,
  `sueldo_diario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tipo_sueldo` enum('diario','semanal') NOT NULL DEFAULT 'diario',
  `fecha_inicio` date NOT NULL DEFAULT '2026-01-01',
  `fecha_fin` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sucursal_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `empleado_asistencia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empleado_id` int(11) NOT NULL,
  `semana_inicio` date NOT NULL,
  `dias_trabajados` tinyint(4) NOT NULL DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `estados_seguridad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `color` varchar(20) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden_visualizacion` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gastos_administrativos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mes` tinyint(2) NOT NULL COMMENT '1-12',
  `anio` year(4) NOT NULL,
  `concepto` varchar(300) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `categoria` enum('renta','salario','servicio','insumo','otro') NOT NULL DEFAULT 'otro',
  `registrado_por` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `sucursal_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `gastos_orden` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_id` int(11) NOT NULL,
  `concepto` varchar(300) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `tipo` enum('envio','consumible','propina','otro') NOT NULL DEFAULT 'otro',
  `registrado_por` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `sucursal_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ordenes_servicio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_orden` varchar(50) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL DEFAULT 1,
  `folio_sucursal` int(10) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Consecutivo propio por sucursal.',
  `problema_reportado` text NOT NULL,
  `diagnostico` text DEFAULT NULL,
  `kilometraje_entrada` varchar(20) DEFAULT NULL,
  `kilometraje_salida` varchar(20) DEFAULT NULL,
  `nivel_combustible` decimal(5,2) DEFAULT 0.00,
  `tiene_luces_frontales` tinyint(1) DEFAULT 1,
  `tiene_cuarto_luces` tinyint(1) DEFAULT 1,
  `tiene_radio` tinyint(1) DEFAULT 0,
  `tiene_encendedor` tinyint(1) DEFAULT 0,
  `tiene_gato` tinyint(1) DEFAULT 0,
  `tiene_llanta_refaccion` tinyint(1) DEFAULT 0,
  `tiene_tapon_ruedas` tinyint(1) DEFAULT 1,
  `tiene_molduras_completas` tinyint(1) DEFAULT 1,
  `tiene_tapon_gasolina` tinyint(1) DEFAULT 1,
  `tiene_limpiadores` tinyint(1) DEFAULT 1,
  `tiene_herramienta` tinyint(1) DEFAULT 0,
  `tiene_antena` tinyint(1) DEFAULT 0,
  `tiene_espejos_laterales` tinyint(1) DEFAULT 1,
  `tiene_cristales` tinyint(1) DEFAULT 1,
  `tiene_emblemas` tinyint(1) DEFAULT 1,
  `tiene_llantas` tinyint(1) DEFAULT 1,
  `tiene_tapetes` tinyint(1) DEFAULT 0,
  `tiene_vestiduras` tinyint(1) DEFAULT 1,
  `tiene_otros` tinyint(1) DEFAULT 1,
  `tiene_extinguidor` tinyint(1) DEFAULT 0,
  `tiene_documentos` tinyint(1) DEFAULT 0,
  `tiene_instrumento_tablero` tinyint(1) DEFAULT 1,
  `tiene_calefaccion` tinyint(1) DEFAULT 1,
  `tiene_sistema_sonido` tinyint(1) DEFAULT 1,
  `tiene_bocinas` tinyint(1) DEFAULT 1,
  `tiene_espejo_retrovisor` tinyint(1) DEFAULT 1,
  `tiene_cinturones` tinyint(1) DEFAULT 1,
  `tiene_botonia_general` tinyint(1) DEFAULT 1,
  `tiene_manijas` tinyint(1) DEFAULT 1,
  `objetos_valor` text DEFAULT NULL,
  `subtotal_mano_obra` decimal(10,2) DEFAULT 0.00,
  `subtotal_servicios` decimal(10,2) DEFAULT 0.00,
  `subtotal_refacciones` decimal(10,2) DEFAULT 0.00,
  `descuento` decimal(10,2) DEFAULT 0.00,
  `incluir_iva` tinyint(1) DEFAULT 0,
  `iva` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) DEFAULT 0.00,
  `anticipo` decimal(10,2) DEFAULT 0.00,
  `fecha_anticipo` date DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'abierta',
  `fecha_ingreso` timestamp NULL DEFAULT current_timestamp(),
  `fecha_promesa_entrega` timestamp NULL DEFAULT NULL,
  `fecha_completada` timestamp NULL DEFAULT NULL,
  `fecha_entregada` timestamp NULL DEFAULT NULL,
  `garantia_dias` int(11) DEFAULT 30,
  `garantia_observaciones` text DEFAULT NULL,
  `notas_internas` text DEFAULT NULL,
  `observaciones_cliente` text DEFAULT NULL,
  `ultima_modificacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `costo_interno_total` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orden_puntos_seguridad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_id` int(11) NOT NULL,
  `punto_seguridad_id` int(11) NOT NULL,
  `estado_id` int(11) NOT NULL,
  `notas` text DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pagos_fijos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concepto` varchar(120) NOT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_inicio` date NOT NULL DEFAULT '2026-01-01',
  `fecha_fin` date DEFAULT NULL,
  `frecuencia` enum('semanal','mensual') NOT NULL DEFAULT 'mensual',
  `categoria` enum('renta','servicio','proveedor','marketing','otro') NOT NULL DEFAULT 'otro',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sucursal_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `puntos_seguridad_catalogo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `categoria` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden_visualizacion` int(11) DEFAULT 0,
  `es_critico` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `refacciones_orden` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL DEFAULT 1,
  `descripcion` varchar(500) NOT NULL,
  `cantidad` decimal(8,2) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `precio_costo` decimal(10,2) DEFAULT NULL,
  `margen_ganancia` decimal(5,2) DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `numero_parte` varchar(100) DEFAULT NULL,
  `proveedor` varchar(200) DEFAULT NULL,
  `notas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `servicios_orden` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `orden_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL DEFAULT 1,
  `tipo` enum('servicio','mano_obra') DEFAULT 'mano_obra',
  `descripcion` varchar(500) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `cantidad` decimal(8,2) DEFAULT 1.00,
  `subtotal` decimal(10,2) NOT NULL,
  `notas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sucursales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(200) NOT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de sucursales del taller';

CREATE TABLE `twilio_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre_completo` varchar(200) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `rol` enum('sistemas','superusuario','admin_sucursal','admin','tecnico','recepcionista') DEFAULT 'admin_sucursal',
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `ultima_modificacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_sucursales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL,
  `rol_sucursal` varchar(50) DEFAULT 'admin_sucursal',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relación N:M entre usuarios y sucursales con rol por sucursal';

CREATE TABLE `vehiculos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL DEFAULT 1,
  `marca` varchar(100) NOT NULL,
  `modelo` varchar(100) NOT NULL,
  `anio` year(4) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `numero_serie` varchar(50) DEFAULT NULL,
  `placas` varchar(20) DEFAULT NULL,
  `niv` varchar(50) DEFAULT NULL,
  `kilometraje` int(11) DEFAULT NULL,
  `tipo_combustible` enum('gasolina','diesel','electrico','hibrido') DEFAULT NULL,
  `numero_cilindros` int(11) DEFAULT NULL,
  `transmision` enum('manual','automatica') DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `fecha_registro` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `alertas_ejecucion_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily_sucursal` (`fecha_ejecucion`,`sucursal_id`),
  ADD KEY `idx_fecha_ejecucion` (`fecha_ejecucion`),
  ADD KEY `idx_created_at` (`created_at`);

ALTER TABLE `alertas_servicio`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_orden_alerta` (`orden_id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `usuario_marco_leida` (`usuario_marco_leida`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_generada` (`fecha_generada`),
  ADD KEY `idx_cliente` (`cliente_id`),
  ADD KEY `idx_orden` (`orden_id`),
  ADD KEY `idx_compuesto_alertas` (`estado`,`fecha_generada`),
  ADD KEY `idx_estado_twilio` (`estado_twilio`),
  ADD KEY `idx_twilio_sid` (`twilio_message_sid`),
  ADD KEY `idx_estado_twilio_orden` (`estado_twilio`,`orden_id`),
  ADD KEY `fk_usuario_confirma_sag` (`usuario_confirmo_sag`),
  ADD KEY `idx_estado_whatsapp` (`estado_whatsapp`),
  ADD KEY `idx_requiere_atencion` (`requiere_atencion`,`estado_whatsapp`),
  ADD KEY `idx_ultima_actividad` (`ultima_actividad`),
  ADD KEY `idx_pre_agenda` (`estado_whatsapp`,`fecha_cita_seleccionada`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

ALTER TABLE `caja_chica`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_caja_chica_fecha` (`fecha`),
  ADD KEY `fk_caja_chica_gasto_admin` (`gasto_admin_id`);

ALTER TABLE `calendario_disponibilidad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_fecha_hora` (`fecha`,`hora`),
  ADD KEY `idx_fecha_disponible` (`fecha`,`esta_disponible`),
  ADD KEY `idx_disponibilidad` (`fecha`,`hora`,`esta_disponible`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

ALTER TABLE `citas_pre_agendadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_confirmo_id` (`usuario_confirmo_id`),
  ADD KEY `idx_estado_fecha` (`estado`,`fecha_pre_agenda`),
  ADD KEY `idx_alerta_id` (`alerta_id`),
  ADD KEY `idx_calendario_slot` (`calendario_slot_id`);

ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nombre` (`nombre`),
  ADD KEY `idx_telefono` (`telefono`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_fusionado_en` (`fusionado_en`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_telefono_normalizado` (`telefono_normalizado`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

ALTER TABLE `conversaciones_whatsapp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alerta_conversation` (`alerta_id`,`conversation_step`),
  ADD KEY `idx_twilio_sid` (`twilio_message_sid`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_direction_step` (`direction`,`conversation_step`);

ALTER TABLE `danos_vehiculo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orden` (`orden_id`);

ALTER TABLE `empleados_sueldos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_empleados_sueldos_usuario` (`usuario_id`);

ALTER TABLE `empleado_asistencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_empleado_semana` (`empleado_id`,`semana_inicio`),
  ADD KEY `idx_semana_inicio` (`semana_inicio`);

ALTER TABLE `estados_seguridad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD KEY `idx_nombre` (`nombre`),
  ADD KEY `idx_activo` (`activo`);

ALTER TABLE `gastos_administrativos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mes_anio` (`mes`,`anio`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_gastos_admin_usuario` (`registrado_por`);

ALTER TABLE `gastos_orden`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gastos_orden_usuario` (`registrado_por`),
  ADD KEY `idx_orden_id` (`orden_id`),
  ADD KEY `idx_created_at` (`created_at`);

ALTER TABLE `ordenes_servicio`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_orden` (`numero_orden`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_numero_orden` (`numero_orden`),
  ADD KEY `idx_cliente` (`cliente_id`),
  ADD KEY `idx_vehiculo` (`vehiculo_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_ingreso` (`fecha_ingreso`),
  ADD KEY `idx_fecha_promesa` (`fecha_promesa_entrega`),
  ADD KEY `idx_sucursal` (`sucursal_id`),
  ADD KEY `idx_folio_sucursal` (`sucursal_id`,`folio_sucursal`);

ALTER TABLE `orden_puntos_seguridad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_punto_por_orden` (`orden_id`,`punto_seguridad_id`),
  ADD KEY `idx_orden` (`orden_id`),
  ADD KEY `idx_punto` (`punto_seguridad_id`),
  ADD KEY `idx_estado` (`estado_id`);

ALTER TABLE `pagos_fijos`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `puntos_seguridad_catalogo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_categoria` (`categoria`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_critico` (`es_critico`);

ALTER TABLE `refacciones_orden`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orden` (`orden_id`),
  ADD KEY `idx_numero_parte` (`numero_parte`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

ALTER TABLE `servicios_orden`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orden` (`orden_id`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activo` (`activo`);

ALTER TABLE `twilio_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_rol` (`rol`);

ALTER TABLE `usuario_sucursales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_usuario_sucursal` (`usuario_id`,`sucursal_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cliente` (`cliente_id`),
  ADD KEY `idx_placas` (`placas`),
  ADD KEY `idx_marca_modelo` (`marca`,`modelo`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

ALTER TABLE `alertas_servicio`
  ADD CONSTRAINT `alertas_servicio_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertas_servicio_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertas_servicio_ibfk_3` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertas_servicio_ibfk_4` FOREIGN KEY (`usuario_marco_leida`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_usuario_confirma_sag` FOREIGN KEY (`usuario_confirmo_sag`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

ALTER TABLE `caja_chica`
  ADD CONSTRAINT `fk_caja_chica_gasto_admin` FOREIGN KEY (`gasto_admin_id`) REFERENCES `gastos_administrativos` (`id`) ON DELETE SET NULL;

ALTER TABLE `danos_vehiculo`
  ADD CONSTRAINT `danos_vehiculo_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE;

ALTER TABLE `empleados_sueldos`
  ADD CONSTRAINT `fk_empleados_sueldos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

ALTER TABLE `empleado_asistencia`
  ADD CONSTRAINT `fk_asistencia_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados_sueldos` (`id`) ON DELETE CASCADE;

ALTER TABLE `gastos_administrativos`
  ADD CONSTRAINT `fk_gastos_admin_usuario` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`);

ALTER TABLE `gastos_orden`
  ADD CONSTRAINT `fk_gastos_orden_orden` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_gastos_orden_usuario` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`);

ALTER TABLE `ordenes_servicio`
  ADD CONSTRAINT `ordenes_servicio_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `ordenes_servicio_ibfk_2` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`),
  ADD CONSTRAINT `ordenes_servicio_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

ALTER TABLE `orden_puntos_seguridad`
  ADD CONSTRAINT `orden_puntos_seguridad_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orden_puntos_seguridad_ibfk_2` FOREIGN KEY (`punto_seguridad_id`) REFERENCES `puntos_seguridad_catalogo` (`id`),
  ADD CONSTRAINT `orden_puntos_seguridad_ibfk_3` FOREIGN KEY (`estado_id`) REFERENCES `estados_seguridad` (`id`);

ALTER TABLE `refacciones_orden`
  ADD CONSTRAINT `refacciones_orden_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE;

ALTER TABLE `servicios_orden`
  ADD CONSTRAINT `servicios_orden_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE;

ALTER TABLE `vehiculos`
  ADD CONSTRAINT `vehiculos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

INSERT INTO `estados_seguridad` (`id`, `nombre`, `color`, `descripcion`, `orden_visualizacion`, `activo`, `fecha_creacion`) VALUES
(1, 'Bueno', '#22c55e', 'El componente está en buen estado', 1, 1, '2026-02-02 16:49:35'),
(2, 'Recomendado', '#f59e0b', 'El componente necesita atención pronto', 2, 1, '2026-02-02 16:49:35'),
(3, 'Urgente', '#ef4444', 'El componente requiere reparación inmediata', 3, 1, '2026-02-02 16:49:35');

INSERT INTO `puntos_seguridad_catalogo` (`id`, `nombre`, `categoria`, `descripcion`, `orden_visualizacion`, `es_critico`, `activo`, `fecha_creacion`) VALUES
(1, 'Aceite de Motor', 'General', 'Verificar nivel y calidad del aceite de motor', 1, 0, 1, '2026-02-02 17:27:13'),
(2, 'Afinación', 'General', 'Revisión general de afinación', 2, 0, 1, '2026-02-02 17:27:13'),
(3, 'Frenos Delanteros', 'General', 'Inspección de frenos delanteros', 3, 1, 1, '2026-02-02 17:27:13'),
(4, 'Frenos Traseros', 'General', 'Inspección de frenos traseros', 4, 1, 1, '2026-02-02 17:27:13'),
(5, 'Verificación', 'General', 'Verificación vehicular', 5, 0, 1, '2026-02-02 17:27:13'),
(6, 'Filtro de Cabina', 'General', 'Estado del filtro de cabina', 6, 0, 1, '2026-02-02 17:27:13'),
(7, 'Llantas', 'General', 'Revisión de llantas', 7, 1, 1, '2026-02-02 17:27:13'),
(8, 'Paquete de Ruedas ABR', 'General', 'Paquete de ruedas y alineación', 8, 0, 1, '2026-02-02 17:27:13'),
(9, 'Suspensión', 'General', 'Revisión de suspensión', 9, 1, 1, '2026-02-02 17:27:13'),
(10, 'Plumas Limpiadoras', 'General', 'Estado de plumas limpiadoras', 10, 0, 1, '2026-02-02 17:27:13'),
(11, 'Líquido de Frenos', 'General', 'Nivel de líquido de frenos', 11, 1, 1, '2026-02-02 17:27:13'),
(12, 'Anticongelante Servicio', 'General', 'Servicio de anticongelante', 12, 0, 1, '2026-02-02 17:27:13'),
(13, 'Rellenar Niveles de Fluidos', 'General', 'Revisión y relleno de fluidos', 13, 0, 1, '2026-02-02 17:27:13'),
(14, 'Tapón Radiador', 'General', 'Estado del tapón del radiador', 14, 0, 1, '2026-02-02 17:27:13'),
(15, 'Mangueras', 'General', 'Revisión de mangueras', 15, 0, 1, '2026-02-02 17:27:13'),
(16, 'Luces', 'General', 'Funcionamiento de luces', 16, 1, 1, '2026-02-02 17:27:13'),
(17, 'Batería', 'General', 'Estado de la batería', 17, 1, 1, '2026-02-02 17:27:13'),
(18, 'Bandas', 'General', 'Revisión de bandas', 18, 0, 1, '2026-02-02 17:27:13'),
(19, 'Transmisión / Servicio', 'General', 'Servicio de transmisión', 19, 0, 1, '2026-02-02 17:27:13'),
(20, 'Lavado de Motor', 'General', 'Servicio de lavado de motor', 20, 0, 1, '2026-02-02 17:27:13'),
(21, 'Servicio a Domicilio', 'General', 'Disponibilidad de servicio a domicilio', 21, 0, 1, '2026-02-02 17:27:13');

-- ============================================================
-- CARFIX -- Seed Data Inicial
-- Passwords: carfix=saggarage | carfix_dev=markiiak (mismas que SAG Garage)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE `twilio_config`;
TRUNCATE TABLE `sucursales`;
TRUNCATE TABLE `usuarios`;
TRUNCATE TABLE `estados_seguridad`;
TRUNCATE TABLE `puntos_seguridad_catalogo`;
SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO `sucursales` (`id`,`nombre`,`activo`,`created_at`,`updated_at`)
VALUES (1,'CarFix Matriz',1,NOW(),NOW());

INSERT INTO `usuarios`
  (`id`,`username`,`password_hash`,`nombre_completo`,`email`,`rol`,`activo`,`fecha_creacion`,`ultima_modificacion`)
VALUES
(1,'carfix','$2y$10$BJhm2i5g4ApTLAUYHhDzZOXebkjv9dZxU3R3kNlJWD/gLD1RB2qAC',
   'CarFix Admin','admin@tallercarfix.com.mx','superusuario',1,NOW(),NOW()),
(2,'carfix_dev','$2y$10$.rF6JqF1Xhf13FjOFnrSeeOv0BftEdb.uXcNnt/bpEAADjWeTLIXG',
   'CarFix Dev','dev@tallercarfix.com.mx','sistemas',1,NOW(),NOW());

INSERT INTO `twilio_config` (`config_key`,`config_value`,`description`,`is_active`) VALUES
('account_sid','','Twilio Account SID',1),
('auth_token','','Twilio Auth Token',1),
('whatsapp_from','','Numero envio whatsapp:+521XXXXXXXXXX',1),
('sag_admin_phone','','Numero admin CarFix sin +52',1),
('sag_business_name','CarFix','Nombre del negocio',1),
('horario_matutino','09:00','Hora citas matutinas',1),
('horario_vespertino','14:00','Hora citas vespertinas',1),
('capacidad_por_horario','2','Maximo citas por slot',1),
('dias_anticipacion','7','Dias hacia adelante',1),
('horarios_atencion','10:00,12:00,14:00,16:00','Horarios disponibles',1),
('slots_maximo_mostrar','8','Maximo slots mostrar',1),
('template_mode','interactive','Modo template Twilio',1),
('max_intentos_respuesta','3','Maximo intentos respuesta',1),
('webhook_url','https://tallercarfix.com.mx/gestion/backend-php/webhook/twilio_whatsapp.php','URL webhook',1),
('dias_festivos_2026','["2026-01-01","2026-02-03","2026-03-16","2026-04-13","2026-04-14","2026-05-01","2026-09-16","2026-11-02","2026-11-16","2026-12-25"]','Dias festivos 2026',1),
('mensaje_rechazo','Claro, muchas gracias. Cuando sea el momento estaremos para ti.','Respuesta NO',1),
('mensaje_contacto_directo','Un ejecutivo de CarFix se pondra en contacto para agendar.','Contacto directo',1),
('mensaje_cancelacion','Disculpa las molestias. Un ejecutivo te contactara para reagendar.','Cancelacion',1),
('mensaje_otro_horario','Nuestro equipo se pondra en contacto para coordinar un horario.','Otro horario',1);
