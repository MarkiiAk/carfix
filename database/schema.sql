-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 09, 2026 at 11:06 AM
-- Server version: 11.4.10-MariaDB-cll-lve-log
-- PHP Version: 8.4.19
-- 
-- ACTUALIZADO: 2026-05-15 — columna gasto_admin_id + FK en caja_chica para vínculo automático al P&L

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `saggarag_GestionPresupuestos`
--

-- --------------------------------------------------------

--
-- Table structure for table `alertas_ejecucion_log`
--

CREATE TABLE `alertas_ejecucion_log` (
  `id` int(11) NOT NULL,
  `fecha_ejecucion` date NOT NULL,
  `alertas_generadas` int(11) DEFAULT 0,
  `tiempo_ejecucion_ms` int(11) DEFAULT 0,
  `usuario_id` int(11) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de ejecuciones diarias del sistema automático de generación de alertas';

-- --------------------------------------------------------

--
-- Table structure for table `alertas_servicio`
--

CREATE TABLE `alertas_servicio` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `fecha_ultimo_servicio` timestamp NOT NULL,
  `servicios_que_dispararon` text NOT NULL,
  `todos_los_servicios` text DEFAULT NULL,
  `estado` enum('pendiente','leida') DEFAULT 'pendiente',
  `estado_whatsapp` enum('borrador','enviado','esperando_respuesta','esperando_fecha','pre_agendado','confirmado','rechazado','requiere_contacto','cancelado','completado') DEFAULT 'borrador',
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
  `slots_ofrecidos_json` text DEFAULT NULL COMMENT 'JSON con slots ofrecidos al cliente para mapear su respuesta numérica a un slot real',
  `intentos_invalidos` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Respuestas fuera de opciones en el paso actual; se resetea al avanzar estado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabla principal de alertas con integración WhatsApp - Actualizada 30/04/2026';

-- --------------------------------------------------------

--
-- Table structure for table `calendario_disponibilidad`
--

CREATE TABLE `calendario_disponibilidad` (
  `id` int(11) NOT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `citas_pre_agendadas`
--

CREATE TABLE `citas_pre_agendadas` (
  `id` int(11) NOT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `conversaciones_whatsapp`
--

CREATE TABLE `conversaciones_whatsapp` (
  `id` int(11) NOT NULL,
  `alerta_id` int(11) NOT NULL,
  `twilio_message_sid` varchar(100) NOT NULL,
  `direction` enum('outbound','inbound') NOT NULL,
  `from_number` varchar(50) NOT NULL,
  `to_number` varchar(50) NOT NULL,
  `message_body` text DEFAULT NULL,
  `message_type` enum('text','interactive','template') DEFAULT 'text',
  `message_status` varchar(20) DEFAULT NULL,
  `error_code` varchar(20) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `twilio_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`twilio_response`)),
  `conversation_step` enum('recordatorio_inicial','respuesta_si_no','seleccion_fecha','confirmacion_pre_agenda','confirmacion_sag','mensaje_final','contacto_directo') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `danos_vehiculo`
--

CREATE TABLE `danos_vehiculo` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `ubicacion` varchar(200) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `estados_seguridad`
--

CREATE TABLE `estados_seguridad` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `color` varchar(20) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden_visualizacion` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ordenes_servicio`
--

CREATE TABLE `ordenes_servicio` (
  `id` int(11) NOT NULL,
  `numero_orden` varchar(50) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
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
  `ultima_modificacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orden_puntos_seguridad`
--

CREATE TABLE `orden_puntos_seguridad` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `punto_seguridad_id` int(11) NOT NULL,
  `estado_id` int(11) NOT NULL,
  `notas` text DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_modificacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `puntos_seguridad_catalogo`
--

CREATE TABLE `puntos_seguridad_catalogo` (
  `id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `categoria` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden_visualizacion` int(11) DEFAULT 0,
  `es_critico` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `refacciones_orden`
--

CREATE TABLE `refacciones_orden` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `descripcion` varchar(500) NOT NULL,
  `cantidad` decimal(8,2) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `numero_parte` varchar(100) DEFAULT NULL,
  `proveedor` varchar(200) DEFAULT NULL,
  `notas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `servicios_orden`
--

CREATE TABLE `servicios_orden` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `tipo` enum('servicio','mano_obra') DEFAULT 'mano_obra',
  `descripcion` varchar(500) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `cantidad` decimal(8,2) DEFAULT 1.00,
  `subtotal` decimal(10,2) NOT NULL,
  `notas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `twilio_config`
--

CREATE TABLE `twilio_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre_completo` varchar(200) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `rol` enum('admin','tecnico','recepcionista') DEFAULT 'tecnico',
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `ultima_modificacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vehiculos`
--

CREATE TABLE `vehiculos` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
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

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_campanita_whatsapp`
-- (See below for the actual view)
--
CREATE TABLE `vista_campanita_whatsapp` (
`id` int(11)
,`estado_whatsapp` enum('borrador','enviado','esperando_respuesta','esperando_fecha','pre_agendado','confirmado','rechazado','requiere_contacto','cancelado','completado')
,`requiere_atencion` tinyint(1)
,`prioridad` enum('baja','media','alta')
,`ultima_actividad` datetime
,`cliente_nombre` varchar(200)
,`cliente_telefono` varchar(20)
,`vehiculo_info` varchar(206)
,`tipo_servicio` text
,`fecha_cita_seleccionada` date
,`hora_cita_seleccionada` time
,`confirmacion_sag` enum('pendiente','confirmado','cancelado')
,`tipo_notificacion` varchar(11)
,`mensaje_campanita` mediumtext
,`link_whatsapp` text
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_conversaciones_whatsapp`
-- (See below for the actual view)
--
CREATE TABLE `vista_conversaciones_whatsapp` (
`alerta_id` int(11)
,`estado_whatsapp` enum('borrador','enviado','esperando_respuesta','esperando_fecha','pre_agendado','confirmado','rechazado','requiere_contacto','cancelado','completado')
,`ultima_actividad` datetime
,`cliente_nombre` varchar(200)
,`cliente_telefono` varchar(20)
,`twilio_conversation_sid` varchar(100)
,`fecha_envio_whatsapp` datetime
,`respuesta_inicial` varchar(50)
,`fecha_respuesta_inicial` datetime
,`fecha_cita_seleccionada` date
,`hora_cita_seleccionada` time
,`confirmacion_sag` enum('pendiente','confirmado','cancelado')
,`ultimo_mensaje` text
,`fecha_ultimo_mensaje` timestamp
,`total_mensajes` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_ordenes_completa`
-- (See below for the actual view)
--
CREATE TABLE `vista_ordenes_completa` (
`id` int(11)
,`numero_orden` varchar(50)
,`fecha_ingreso` timestamp
,`fecha_promesa_entrega` timestamp
,`estado` varchar(20)
,`total` decimal(10,2)
,`cliente_nombre` varchar(200)
,`cliente_telefono` varchar(20)
,`vehiculo_marca` varchar(100)
,`vehiculo_modelo` varchar(100)
,`vehiculo_anio` year(4)
,`vehiculo_placas` varchar(20)
,`usuario_nombre` varchar(200)
,`problema_reportado` text
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_rentabilidad_servicios`
-- (See below for the actual view)
--
CREATE TABLE `vista_rentabilidad_servicios` (
`tipo_servicio` varchar(12)
,`descripcion` varchar(500)
,`veces_realizado` bigint(21)
,`precio_promedio` varchar(16)
,`ingresos_totales` varchar(44)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_reporte_mensual`
-- (See below for the actual view)
--
CREATE TABLE `vista_reporte_mensual` (
`anio` int(5)
,`mes` int(3)
,`periodo` varchar(7)
,`nombre_mes` varchar(9)
,`ingresos` decimal(32,2)
,`ingresos_mano_obra` decimal(32,2)
,`ingresos_servicios` decimal(32,2)
,`ingresos_refacciones` decimal(32,2)
,`iva_cobrado` decimal(32,2)
,`ordenes_completadas` bigint(21)
,`egresos` decimal(40,4)
,`balance_bruto` decimal(41,4)
,`margen_refacciones` decimal(43,6)
,`ganancia_neta` decimal(42,4)
,`porcentaje_rentabilidad` decimal(47,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_top_clientes_anual`
-- (See below for the actual view)
--
CREATE TABLE `vista_top_clientes_anual` (
`id` int(11)
,`cliente` varchar(200)
,`telefono` varchar(20)
,`ordenes_completadas` bigint(21)
,`facturación_total` varchar(44)
,`promedio_por_orden` varchar(16)
,`ultima_visita` varchar(10)
);

-- --------------------------------------------------------



--
-- Indexes for dumped tables
--

--
-- Indexes for table `alertas_ejecucion_log`
--
ALTER TABLE `alertas_ejecucion_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily_execution` (`fecha_ejecucion`),
  ADD KEY `idx_fecha_ejecucion` (`fecha_ejecucion`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `alertas_servicio`
--
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
  ADD KEY `idx_whatsapp_estado` (`whatsapp_estado`),
  ADD KEY `idx_whatsapp_programada` (`whatsapp_fecha_programada`),
  ADD KEY `idx_whatsapp_pendiente_fecha` (`whatsapp_estado`,`whatsapp_fecha_programada`),
  ADD KEY `idx_estado_twilio` (`estado_twilio`),
  ADD KEY `idx_twilio_sid` (`twilio_message_sid`),
  ADD KEY `idx_estado_twilio_orden` (`estado_twilio`,`orden_id`),
  ADD KEY `fk_usuario_confirma_sag` (`usuario_confirmo_sag`),
  ADD KEY `idx_estado_whatsapp` (`estado_whatsapp`),
  ADD KEY `idx_requiere_atencion` (`requiere_atencion`,`estado_whatsapp`),
  ADD KEY `idx_ultima_actividad` (`ultima_actividad`),
  ADD KEY `idx_pre_agenda` (`estado_whatsapp`,`fecha_cita_seleccionada`);

--
-- Indexes for table `calendario_disponibilidad`
--
ALTER TABLE `calendario_disponibilidad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_fecha_hora` (`fecha`,`hora`),
  ADD KEY `idx_fecha_disponible` (`fecha`,`esta_disponible`),
  ADD KEY `idx_disponibilidad` (`fecha`,`hora`,`esta_disponible`);

--
-- Indexes for table `citas_pre_agendadas`
--
ALTER TABLE `citas_pre_agendadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_confirmo_id` (`usuario_confirmo_id`),
  ADD KEY `idx_estado_fecha` (`estado`,`fecha_pre_agenda`),
  ADD KEY `idx_alerta_id` (`alerta_id`),
  ADD KEY `idx_calendario_slot` (`calendario_slot_id`);

--
-- Indexes for table `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_nombre` (`nombre`),
  ADD KEY `idx_telefono` (`telefono`),
  ADD KEY `idx_email` (`email`);
ALTER TABLE `clientes` ADD FULLTEXT KEY `idx_busqueda` (`nombre`,`telefono`,`email`);

--
-- Indexes for table `conversaciones_whatsapp`
--
ALTER TABLE `conversaciones_whatsapp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_alerta_conversation` (`alerta_id`,`conversation_step`),
  ADD KEY `idx_twilio_sid` (`twilio_message_sid`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_direction_step` (`direction`,`conversation_step`);

--
-- Indexes for table `danos_vehiculo`
--
ALTER TABLE `danos_vehiculo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orden` (`orden_id`);

--
-- Indexes for table `estados_seguridad`
--
ALTER TABLE `estados_seguridad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD KEY `idx_nombre` (`nombre`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indexes for table `ordenes_servicio`
--
ALTER TABLE `ordenes_servicio`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_orden` (`numero_orden`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `idx_numero_orden` (`numero_orden`),
  ADD KEY `idx_cliente` (`cliente_id`),
  ADD KEY `idx_vehiculo` (`vehiculo_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_ingreso` (`fecha_ingreso`),
  ADD KEY `idx_fecha_promesa` (`fecha_promesa_entrega`);
ALTER TABLE `ordenes_servicio` ADD FULLTEXT KEY `idx_busqueda` (`numero_orden`,`problema_reportado`,`diagnostico`);

--
-- Indexes for table `orden_puntos_seguridad`
--
ALTER TABLE `orden_puntos_seguridad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_punto_por_orden` (`orden_id`,`punto_seguridad_id`),
  ADD KEY `idx_orden` (`orden_id`),
  ADD KEY `idx_punto` (`punto_seguridad_id`),
  ADD KEY `idx_estado` (`estado_id`);

--
-- Indexes for table `puntos_seguridad_catalogo`
--
ALTER TABLE `puntos_seguridad_catalogo`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_categoria` (`categoria`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_critico` (`es_critico`);
ALTER TABLE `puntos_seguridad_catalogo` ADD FULLTEXT KEY `idx_busqueda` (`nombre`,`descripcion`);

--
-- Indexes for table `refacciones_orden`
--
ALTER TABLE `refacciones_orden`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orden` (`orden_id`),
  ADD KEY `idx_numero_parte` (`numero_parte`);

--
-- Indexes for table `servicios_orden`
--
ALTER TABLE `servicios_orden`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orden` (`orden_id`);

--
-- Indexes for table `twilio_config`
--
ALTER TABLE `twilio_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

--
-- Indexes for table `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_rol` (`rol`);

--
-- Indexes for table `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cliente` (`cliente_id`),
  ADD KEY `idx_placas` (`placas`),
  ADD KEY `idx_marca_modelo` (`marca`,`modelo`);
ALTER TABLE `vehiculos` ADD FULLTEXT KEY `idx_busqueda` (`marca`,`modelo`,`placas`,`numero_serie`);


--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alertas_ejecucion_log`
--
ALTER TABLE `alertas_ejecucion_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `alertas_servicio`
--
ALTER TABLE `alertas_servicio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `calendario_disponibilidad`
--
ALTER TABLE `calendario_disponibilidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `citas_pre_agendadas`
--
ALTER TABLE `citas_pre_agendadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversaciones_whatsapp`
--
ALTER TABLE `conversaciones_whatsapp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `danos_vehiculo`
--
ALTER TABLE `danos_vehiculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `estados_seguridad`
--
ALTER TABLE `estados_seguridad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ordenes_servicio`
--
ALTER TABLE `ordenes_servicio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orden_puntos_seguridad`
--
ALTER TABLE `orden_puntos_seguridad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `puntos_seguridad_catalogo`
--
ALTER TABLE `puntos_seguridad_catalogo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refacciones_orden`
--
ALTER TABLE `refacciones_orden`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `servicios_orden`
--
ALTER TABLE `servicios_orden`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `twilio_config`
--
ALTER TABLE `twilio_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


-- --------------------------------------------------------

--
-- Structure for view `vista_campanita_whatsapp`
--
DROP TABLE IF EXISTS `vista_campanita_whatsapp`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_campanita_whatsapp`  AS SELECT `a`.`id` AS `id`, `a`.`estado_whatsapp` AS `estado_whatsapp`, `a`.`requiere_atencion` AS `requiere_atencion`, `a`.`prioridad` AS `prioridad`, `a`.`ultima_actividad` AS `ultima_actividad`, `c`.`nombre` AS `cliente_nombre`, `c`.`telefono` AS `cliente_telefono`, concat(`v`.`marca`,' ',`v`.`modelo`,' ',`v`.`anio`) AS `vehiculo_info`, `a`.`servicios_que_dispararon` AS `tipo_servicio`, `a`.`fecha_cita_seleccionada` AS `fecha_cita_seleccionada`, `a`.`hora_cita_seleccionada` AS `hora_cita_seleccionada`, `a`.`confirmacion_sag` AS `confirmacion_sag`, CASE WHEN `a`.`estado_whatsapp` = 'pre_agendado' AND `a`.`confirmacion_sag` = 'pendiente' THEN 'urgente' WHEN `a`.`estado_whatsapp` = 'requiere_contacto' THEN 'contacto' WHEN `a`.`estado_whatsapp` = 'rechazado' THEN 'informativo' WHEN `a`.`estado_whatsapp` = 'cancelado' THEN 'informativo' ELSE 'normal' END AS `tipo_notificacion`, CASE WHEN `a`.`estado_whatsapp` = 'pre_agendado' AND `a`.`confirmacion_sag` = 'pendiente' THEN concat('🔴 CONFIRMAR: ',`c`.`nombre`,' - ',`a`.`fecha_cita_seleccionada`,' ',`a`.`hora_cita_seleccionada`) WHEN `a`.`estado_whatsapp` = 'requiere_contacto' THEN concat('🟡 CONTACTAR: ',`c`.`nombre`,' pidió otra fecha para ',`a`.`servicios_que_dispararon`) WHEN `a`.`estado_whatsapp` = 'rechazado' THEN concat('🔵 INFO: ',`c`.`nombre`,' no está interesado en ',`a`.`servicios_que_dispararon`) WHEN `a`.`estado_whatsapp` = 'cancelado' THEN concat('🔵 CANCELADO: ',`c`.`nombre`,' - ',`a`.`fecha_cita_seleccionada`) ELSE concat('📝 ',`c`.`nombre`,' - ',`a`.`estado_whatsapp`) END AS `mensaje_campanita`, concat('https://wa.me/52',`c`.`telefono`,'?text=Hola%20',replace(`c`.`nombre`,' ','%20'),'%20nos%20comunicamos%20de%20SAG%20Garage') AS `link_whatsapp` FROM ((`alertas_servicio` `a` join `clientes` `c` on(`a`.`cliente_id` = `c`.`id`)) join `vehiculos` `v` on(`a`.`vehiculo_id` = `v`.`id`)) WHERE `a`.`requiere_atencion` = 1 AND `a`.`estado_whatsapp` in ('pre_agendado','requiere_contacto','rechazado','cancelado') ORDER BY CASE WHEN `a`.`estado_whatsapp` = 'pre_agendado' AND `a`.`confirmacion_sag` = 'pendiente' THEN 1 WHEN `a`.`estado_whatsapp` = 'requiere_contacto' THEN 2 ELSE 3 END ASC, `a`.`prioridad` DESC, `a`.`ultima_actividad` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vista_conversaciones_whatsapp`
--
DROP TABLE IF EXISTS `vista_conversaciones_whatsapp`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_conversaciones_whatsapp`  AS SELECT `a`.`id` AS `alerta_id`, `a`.`estado_whatsapp` AS `estado_whatsapp`, `a`.`ultima_actividad` AS `ultima_actividad`, `c`.`nombre` AS `cliente_nombre`, `c`.`telefono` AS `cliente_telefono`, `a`.`twilio_conversation_sid` AS `twilio_conversation_sid`, `a`.`fecha_envio_whatsapp` AS `fecha_envio_whatsapp`, `a`.`respuesta_inicial` AS `respuesta_inicial`, `a`.`fecha_respuesta_inicial` AS `fecha_respuesta_inicial`, `a`.`fecha_cita_seleccionada` AS `fecha_cita_seleccionada`, `a`.`hora_cita_seleccionada` AS `hora_cita_seleccionada`, `a`.`confirmacion_sag` AS `confirmacion_sag`, (select `conversaciones_whatsapp`.`message_body` from `conversaciones_whatsapp` where `conversaciones_whatsapp`.`alerta_id` = `a`.`id` order by `conversaciones_whatsapp`.`created_at` desc limit 1) AS `ultimo_mensaje`, (select `conversaciones_whatsapp`.`created_at` from `conversaciones_whatsapp` where `conversaciones_whatsapp`.`alerta_id` = `a`.`id` order by `conversaciones_whatsapp`.`created_at` desc limit 1) AS `fecha_ultimo_mensaje`, (select count(0) from `conversaciones_whatsapp` where `conversaciones_whatsapp`.`alerta_id` = `a`.`id`) AS `total_mensajes` FROM (`alertas_servicio` `a` join `clientes` `c` on(`a`.`cliente_id` = `c`.`id`)) WHERE `a`.`estado_whatsapp` <> 'borrador' ORDER BY `a`.`ultima_actividad` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vista_ordenes_completa`
--
DROP TABLE IF EXISTS `vista_ordenes_completa`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ordenes_completa`  AS SELECT `o`.`id` AS `id`, `o`.`numero_orden` AS `numero_orden`, `o`.`fecha_ingreso` AS `fecha_ingreso`, `o`.`fecha_promesa_entrega` AS `fecha_promesa_entrega`, `o`.`estado` AS `estado`, `o`.`total` AS `total`, `c`.`nombre` AS `cliente_nombre`, `c`.`telefono` AS `cliente_telefono`, `v`.`marca` AS `vehiculo_marca`, `v`.`modelo` AS `vehiculo_modelo`, `v`.`anio` AS `vehiculo_anio`, `v`.`placas` AS `vehiculo_placas`, `u`.`nombre_completo` AS `usuario_nombre`, `o`.`problema_reportado` AS `problema_reportado` FROM (((`ordenes_servicio` `o` join `clientes` `c` on(`o`.`cliente_id` = `c`.`id`)) join `vehiculos` `v` on(`o`.`vehiculo_id` = `v`.`id`)) join `usuarios` `u` on(`o`.`usuario_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vista_rentabilidad_servicios`
--
DROP TABLE IF EXISTS `vista_rentabilidad_servicios`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cpses_savmef08v9`@`localhost` SQL SECURITY DEFINER VIEW `vista_rentabilidad_servicios`  AS SELECT CASE WHEN `so`.`tipo` = 'mano_obra' THEN 'Mano de Obra' ELSE 'Servicios' END AS `tipo_servicio`, `so`.`descripcion` AS `descripcion`, count(0) AS `veces_realizado`, format(avg(`so`.`precio_unitario`),2) AS `precio_promedio`, format(sum(`so`.`subtotal`),2) AS `ingresos_totales` FROM (`servicios_orden` `so` join `ordenes_servicio` `o` on(`so`.`orden_id` = `o`.`id`)) WHERE `o`.`estado` in ('completada','entregada') AND year(ifnull(`o`.`fecha_completada`,`o`.`fecha_entregada`)) = year(curdate()) GROUP BY `so`.`tipo`, `so`.`descripcion` ORDER BY sum(`so`.`subtotal`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vista_reporte_mensual`
--
DROP TABLE IF EXISTS `vista_reporte_mensual`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cpses_savmef08v9`@`localhost` SQL SECURITY DEFINER VIEW `vista_reporte_mensual`  AS SELECT year(`periodos`.`fecha_periodo`) AS `anio`, month(`periodos`.`fecha_periodo`) AS `mes`, date_format(`periodos`.`fecha_periodo`,'%Y-%m') AS `periodo`, monthname(`periodos`.`fecha_periodo`) AS `nombre_mes`, ifnull(`ingresos`.`ingresos_totales`,0.00) AS `ingresos`, ifnull(`ingresos`.`ingresos_mano_obra`,0.00) AS `ingresos_mano_obra`, ifnull(`ingresos`.`ingresos_servicios`,0.00) AS `ingresos_servicios`, ifnull(`ingresos`.`ingresos_refacciones`,0.00) AS `ingresos_refacciones`, ifnull(`ingresos`.`total_iva_cobrado`,0.00) AS `iva_cobrado`, ifnull(`ingresos`.`ordenes_completadas`,0) AS `ordenes_completadas`, ifnull(`egresos`.`egresos_refacciones`,0.00) AS `egresos`, ifnull(`ingresos`.`ingresos_totales`,0.00) - ifnull(`egresos`.`egresos_refacciones`,0.00) AS `balance_bruto`, ifnull(`egresos`.`egresos_refacciones`,0.00) * 0.30 AS `margen_refacciones`, ifnull(`ingresos`.`ingresos_mano_obra`,0.00) + ifnull(`ingresos`.`ingresos_servicios`,0.00) + (ifnull(`ingresos`.`ingresos_refacciones`,0.00) - ifnull(`egresos`.`egresos_refacciones`,0.00)) AS `ganancia_neta`, CASE WHEN ifnull(`egresos`.`egresos_refacciones`,0.00) > 0 THEN round((ifnull(`ingresos`.`ingresos_totales`,0.00) - ifnull(`egresos`.`egresos_refacciones`,0.00)) / ifnull(`egresos`.`egresos_refacciones`,0.00) * 100,2) ELSE 100.00 END AS `porcentaje_rentabilidad` FROM (((select '2025-01-01' + interval `numbers`.`n` month AS `fecha_periodo` from (select `a`.`N` + `b`.`N` * 10 + `c`.`N` * 100 AS `n` from (((select 0 AS `N` union select 1 AS `1` union select 2 AS `2` union select 3 AS `3` union select 4 AS `4` union select 5 AS `5` union select 6 AS `6` union select 7 AS `7` union select 8 AS `8` union select 9 AS `9`) `a` join (select 0 AS `N` union select 1 AS `1` union select 2 AS `2` union select 3 AS `3` union select 4 AS `4` union select 5 AS `5` union select 6 AS `6` union select 7 AS `7` union select 8 AS `8` union select 9 AS `9`) `b`) join (select 0 AS `N` union select 1 AS `1` union select 2 AS `2` union select 3 AS `3` union select 4 AS `4` union select 5 AS `5` union select 6 AS `6` union select 7 AS `7` union select 8 AS `8` union select 9 AS `9`) `c`)) `numbers` where '2025-01-01' + interval `numbers`.`n` month <= curdate()) `periodos` left join (select date_format(`ordenes_servicio`.`fecha_ingreso`,'%Y-%m-01') AS `periodo_ingreso`,count(0) AS `ordenes_completadas`,sum(`ordenes_servicio`.`total`) AS `ingresos_totales`,sum(`ordenes_servicio`.`subtotal_mano_obra`) AS `ingresos_mano_obra`,sum(`ordenes_servicio`.`subtotal_servicios`) AS `ingresos_servicios`,sum(`ordenes_servicio`.`subtotal_refacciones`) AS `ingresos_refacciones`,sum(`ordenes_servicio`.`iva`) AS `total_iva_cobrado` from `ordenes_servicio` where `ordenes_servicio`.`estado` = 'cerrada' and `ordenes_servicio`.`fecha_ingreso` is not null and date_format(`ordenes_servicio`.`fecha_ingreso`,'%Y-%m') >= '2025-01' group by date_format(`ordenes_servicio`.`fecha_ingreso`,'%Y-%m')) `ingresos` on(`periodos`.`fecha_periodo` = `ingresos`.`periodo_ingreso`)) left join (select date_format(`o`.`fecha_ingreso`,'%Y-%m-01') AS `periodo_egreso`,sum(`r`.`precio_unitario` * `r`.`cantidad`) AS `egresos_refacciones` from (`refacciones_orden` `r` join `ordenes_servicio` `o` on(`r`.`orden_id` = `o`.`id`)) where `o`.`estado` = 'cerrada' and `o`.`fecha_ingreso` is not null and date_format(`o`.`fecha_ingreso`,'%Y-%m') >= '2025-01' group by date_format(`o`.`fecha_ingreso`,'%Y-%m')) `egresos` on(`periodos`.`fecha_periodo` = `egresos`.`periodo_egreso`)) WHERE year(`periodos`.`fecha_periodo`) >= 2025 ORDER BY year(`periodos`.`fecha_periodo`) DESC, month(`periodos`.`fecha_periodo`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vista_top_clientes_anual`
--
DROP TABLE IF EXISTS `vista_top_clientes_anual`;

CREATE ALGORITHM=UNDEFINED DEFINER=`cpses_savmef08v9`@`localhost` SQL SECURITY DEFINER VIEW `vista_top_clientes_anual`  AS SELECT `c`.`id` AS `id`, `c`.`nombre` AS `cliente`, `c`.`telefono` AS `telefono`, count(distinct `o`.`id`) AS `ordenes_completadas`, format(sum(`o`.`total`),2) AS `facturación_total`, format(avg(`o`.`total`),2) AS `promedio_por_orden`, date_format(max(ifnull(`o`.`fecha_completada`,`o`.`fecha_entregada`)),'%d/%m/%Y') AS `ultima_visita` FROM (`clientes` `c` join `ordenes_servicio` `o` on(`c`.`id` = `o`.`cliente_id`)) WHERE `o`.`estado` in ('completada','entregada') AND year(ifnull(`o`.`fecha_completada`,`o`.`fecha_entregada`)) = year(curdate()) GROUP BY `c`.`id`, `c`.`nombre`, `c`.`telefono` ORDER BY sum(`o`.`total`) DESC LIMIT 0, 10 ;

-- --------------------------------------------------------


--
-- Constraints for dumped tables
--

--
-- Constraints for table `alertas_servicio`
--
ALTER TABLE `alertas_servicio`
  ADD CONSTRAINT `alertas_servicio_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertas_servicio_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertas_servicio_ibfk_3` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertas_servicio_ibfk_4` FOREIGN KEY (`usuario_marco_leida`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_usuario_confirma_sag` FOREIGN KEY (`usuario_confirmo_sag`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `danos_vehiculo`
--
ALTER TABLE `danos_vehiculo`
  ADD CONSTRAINT `danos_vehiculo_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ordenes_servicio`
--
ALTER TABLE `ordenes_servicio`
  ADD CONSTRAINT `ordenes_servicio_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  ADD CONSTRAINT `ordenes_servicio_ibfk_2` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`),
  ADD CONSTRAINT `ordenes_servicio_ibfk_3` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `orden_puntos_seguridad`
--
ALTER TABLE `orden_puntos_seguridad`
  ADD CONSTRAINT `orden_puntos_seguridad_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `orden_puntos_seguridad_ibfk_2` FOREIGN KEY (`punto_seguridad_id`) REFERENCES `puntos_seguridad_catalogo` (`id`),
  ADD CONSTRAINT `orden_puntos_seguridad_ibfk_3` FOREIGN KEY (`estado_id`) REFERENCES `estados_seguridad` (`id`);

--
-- Constraints for table `refacciones_orden`
--
ALTER TABLE `refacciones_orden`
  ADD CONSTRAINT `refacciones_orden_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `servicios_orden`
--
ALTER TABLE `servicios_orden`
  ADD CONSTRAINT `servicios_orden_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD CONSTRAINT `vehiculos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;

-- ─────────────────────────────────────────────────────────────────────────────
-- MIGRACIONES POSTERIORES AL DUMP ORIGINAL (2026-04-09)
-- Aplicar en orden cronológico sobre una BD limpia creada con el schema base.
-- ─────────────────────────────────────────────────────────────────────────────

-- 2026-04-21: Slots horarios en alertas (archivo: 20260421_add_slots_ofrecidos_json.sql)
-- YA INCLUIDO arriba en CREATE TABLE alertas_servicio: slots_ofrecidos_json, intentos_invalidos

-- 2026-04-21: Corrección llaves twilio_config (archivo: 20260421_fix_twilio_config_keys.sql)
-- Solo datos (UPDATE/INSERT) — sin cambio de estructura.

-- 2026-04-25: Fix template agendar SID (archivo: 20260425_fix_template_agendar_sid.sql)
-- Solo datos — sin cambio de estructura.

-- 2026-04-27: Columnas trazabilidad merge clientes (archivo: 20260427_merge_clientes_trazabilidad.sql)
-- YA INCLUIDO arriba en CREATE TABLE clientes: activo, fusionado_en, fusionado_at, notas_merge, telefono_normalizado

-- 2026-04-27: Operaciones merge de clientes específicos (archivos 20260427_merge_*.sql)
-- Solo datos — sin cambio de estructura.

-- 2026-04-29: Contador intentos_invalidos en alertas (archivo: 20260429_add_intentos_invalidos.sql)
-- YA INCLUIDO arriba en CREATE TABLE alertas_servicio: intentos_invalidos

-- 2026-04-30: Módulo financiero — gastos internos por orden
-- Archivo: database/20260430_gastos_orden.sql

ALTER TABLE `ordenes_servicio`
  ADD COLUMN `costo_interno_total` DECIMAL(10,2) NOT NULL DEFAULT 0.00;

CREATE TABLE `gastos_orden` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `orden_id`        INT NOT NULL,
  `concepto`        VARCHAR(300) NOT NULL,
  `monto`           DECIMAL(10,2) NOT NULL,
  `tipo`            ENUM('envio','consumible','propina','otro') NOT NULL DEFAULT 'otro',
  `registrado_por`  INT NOT NULL,
  `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_gastos_orden_orden`   FOREIGN KEY (`orden_id`)      REFERENCES `ordenes_servicio`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gastos_orden_usuario` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios`(`id`),
  INDEX `idx_gastos_orden_orden_id`   (`orden_id`),
  INDEX `idx_gastos_orden_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2026-04-30: Gastos administrativos del taller (renta, salarios, etc.)
-- Archivo: database/20260430_gastos_administrativos.sql

CREATE TABLE `gastos_administrativos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mes` tinyint(2) NOT NULL COMMENT '1-12',
  `anio` year(4) NOT NULL,
  `concepto` varchar(300) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `categoria` enum('renta','salario','servicio','insumo','otro') NOT NULL DEFAULT 'otro',
  `registrado_por` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_mes_anio` (`mes`,`anio`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_gastos_admin_usuario` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2026-05-15: Sueldos de empleados y pagos fijos del taller
-- Archivo: database/20260515_financiero_sueldos_pagos_fijos.sql

CREATE TABLE IF NOT EXISTS `empleados_sueldos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `nombre` varchar(120) NOT NULL,
  `puesto` varchar(80) DEFAULT NULL,
  `sueldo_diario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_empleados_sueldos_usuario` (`usuario_id`),
  CONSTRAINT `fk_empleados_sueldos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pagos_fijos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `concepto` varchar(120) NOT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `frecuencia` enum('semanal','mensual') NOT NULL DEFAULT 'mensual',
  `categoria` enum('renta','servicio','proveedor','marketing','otro') NOT NULL DEFAULT 'otro',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2026-05-15: Vínculo egresos caja chica → gastos_administrativos (P&L)
-- Archivo: database/20260515_caja_chica_gasto_admin_link.sql

CREATE TABLE IF NOT EXISTS `caja_chica` (
  `id`             INT(11)                  NOT NULL AUTO_INCREMENT,
  `fecha`          DATE                     NOT NULL,
  `tipo`           ENUM('ingreso','egreso') NOT NULL,
  `concepto`       VARCHAR(150)             NOT NULL,
  `monto`          DECIMAL(10,2)            NOT NULL DEFAULT 0.00,
  `notas`          TEXT                     NULL,
  `gasto_admin_id` INT(11)                  NULL DEFAULT NULL,
  `created_at`     TIMESTAMP                NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_caja_chica_fecha` (`fecha`),
  CONSTRAINT `fk_caja_chica_gasto_admin`
    FOREIGN KEY (`gasto_admin_id`) REFERENCES `gastos_administrativos` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;