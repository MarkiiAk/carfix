-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 11, 2026 at 12:34 PM
-- Server version: 11.4.10-MariaDB-cll-lve-log
-- PHP Version: 8.4.18

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
  `ultima_visita` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- ========================================
-- TABLAS NO USADAS - COMENTADAS 2026-03-11
-- Verificado que no hay referencias en código
-- Mantener comentadas por seguridad
-- ========================================

--
-- TABLA: configuracion_sistema (NO USADA)
-- Table structure for table `configuracion_sistema`
--

-- CREATE TABLE `configuracion_sistema` (
--   `id` int(11) NOT NULL,
--   `clave` varchar(100) NOT NULL,
--   `valor` text DEFAULT NULL,
--   `tipo_dato` enum('string','number','boolean','json') DEFAULT 'string',
--   `descripcion` text DEFAULT NULL,
--   `fecha_modificacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- TABLA: historial_cambios (NO USADA)
-- Table structure for table `historial_cambios`
--

-- CREATE TABLE `historial_cambios` (
--   `id` int(11) NOT NULL,
--   `orden_id` int(11) NOT NULL,
--   `usuario_id` int(11) NOT NULL,
--   `tipo_cambio` enum('creacion','modificacion','cambio_estado','eliminacion') NOT NULL,
--   `campo_modificado` varchar(100) DEFAULT NULL,
--   `valor_anterior` text DEFAULT NULL,
--   `valor_nuevo` text DEFAULT NULL,
--   `descripcion` text DEFAULT NULL,
--   `fecha_cambio` timestamp NULL DEFAULT current_timestamp()
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- TABLA: pagos (NO USADA)
-- Table structure for table `pagos`
--

-- CREATE TABLE `pagos` (
--   `id` int(11) NOT NULL,
--   `orden_id` int(11) NOT NULL,
--   `monto` decimal(10,2) NOT NULL,
--   `metodo_pago` enum('efectivo','tarjeta','transferencia','cheque','otro') NOT NULL,
--   `referencia` varchar(100) DEFAULT NULL,
--   `notas` text DEFAULT NULL,
--   `usuario_registro_id` int(11) NOT NULL,
--   `fecha_pago` timestamp NULL DEFAULT current_timestamp()
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- TABLA: servicios_predefinidos (NO USADA)
-- Table structure for table `servicios_predefinidos`
--

-- CREATE TABLE `servicios_predefinidos` (
--   `id` int(11) NOT NULL,
--   `nombre` varchar(200) NOT NULL,
--   `descripcion` text DEFAULT NULL,
--   `precio_sugerido` decimal(10,2) DEFAULT 0.00,
--   `categoria` varchar(100) DEFAULT NULL,
--   `activo` tinyint(1) DEFAULT 1,
--   `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `fecha_generada` timestamp NULL DEFAULT current_timestamp(),
  `fecha_marcada_leida` timestamp NULL DEFAULT NULL,
  `usuario_marco_leida` int(11) DEFAULT NULL,
  `dias_desde_servicio` int(11) GENERATED ALWAYS AS (to_days(now()) - to_days(`fecha_ultimo_servicio`)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sistema de alertas para servicios que requieren seguimiento a 6 meses';

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
-- VISTA: vista_ordenes_completa (NO USADA)
-- Stand-in structure for view `vista_ordenes_completa`
-- (See below for the actual view)
--
-- CREATE TABLE `vista_ordenes_completa` (
-- `id` int(11)
-- ,`numero_orden` varchar(50)
-- ,`fecha_ingreso` timestamp
-- ,`fecha_promesa_entrega` timestamp
-- ,`estado` varchar(20)
-- ,`total` decimal(10,2)
-- ,`cliente_nombre` varchar(200)
-- ,`cliente_telefono` varchar(20)
-- ,`vehiculo_marca` varchar(100)
-- ,`vehiculo_modelo` varchar(100)
-- ,`vehiculo_anio` year(4)
-- ,`vehiculo_placas` varchar(20)
-- ,`usuario_nombre` varchar(200)
-- ,`problema_reportado` text
-- );

--
-- Indexes for dumped tables
--

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
-- Indexes for table `configuracion_sistema` (COMENTADO - NO USADA)
--
-- ALTER TABLE `configuracion_sistema`
--   ADD PRIMARY KEY (`id`),
--   ADD UNIQUE KEY `clave` (`clave`),
--   ADD KEY `idx_clave` (`clave`);

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
-- Indexes for table `historial_cambios` (COMENTADO - NO USADA)
--
-- ALTER TABLE `historial_cambios`
--   ADD PRIMARY KEY (`id`),
--   ADD KEY `idx_orden` (`orden_id`),
--   ADD KEY `idx_usuario` (`usuario_id`),
--   ADD KEY `idx_fecha` (`fecha_cambio`),
--   ADD KEY `idx_tipo` (`tipo_cambio`);

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
-- Indexes for table `pagos` (COMENTADO - NO USADA)
--
-- ALTER TABLE `pagos`
--   ADD PRIMARY KEY (`id`),
--   ADD KEY `usuario_registro_id` (`usuario_registro_id`),
--   ADD KEY `idx_orden` (`orden_id`),
--   ADD KEY `idx_fecha` (`fecha_pago`),
--   ADD KEY `idx_metodo` (`metodo_pago`);

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
-- Indexes for table `servicios_predefinidos` (COMENTADO - NO USADA)
--
-- ALTER TABLE `servicios_predefinidos`
--   ADD PRIMARY KEY (`id`),
--   ADD KEY `idx_nombre` (`nombre`),
--   ADD KEY `idx_categoria` (`categoria`);
-- ALTER TABLE `servicios_predefinidos` ADD FULLTEXT KEY `idx_busqueda` (`nombre`,`descripcion`,`categoria`);

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
-- Indexes for table `alertas_servicio`
--
ALTER TABLE `alertas_servicio`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_orden_alerta` (`orden_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha_generada` (`fecha_generada`),
  ADD KEY `idx_cliente` (`cliente_id`),
  ADD KEY `idx_orden` (`orden_id`),
  ADD KEY `idx_compuesto_alertas` (`estado`,`fecha_generada`);

--
-- Indexes for table `alertas_ejecucion_log`
--
ALTER TABLE `alertas_ejecucion_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily_execution` (`fecha_ejecucion`),
  ADD KEY `idx_fecha_ejecucion` (`fecha_ejecucion`),
  ADD KEY `idx_created_at` (`created_at`);

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
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `configuracion_sistema` (COMENTADO - NO USADA)
--
-- ALTER TABLE `configuracion_sistema`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `historial_cambios` (COMENTADO - NO USADA)
--
-- ALTER TABLE `historial_cambios`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `pagos` (COMENTADO - NO USADA)
--
-- ALTER TABLE `pagos`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `servicios_predefinidos` (COMENTADO - NO USADA)
--
-- ALTER TABLE `servicios_predefinidos`
--   MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `alertas_servicio`
--
ALTER TABLE `alertas_servicio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `alertas_ejecucion_log`
--
ALTER TABLE `alertas_ejecucion_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

-- --------------------------------------------------------

--
-- Structure for view `vista_ordenes_completa` (COMENTADO - NO USADA)
--
-- DROP TABLE IF EXISTS `vista_ordenes_completa`;

-- CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ordenes_completa`  AS SELECT `o`.`id` AS `id`, `o`.`numero_orden` AS `numero_orden`, `o`.`fecha_ingreso` AS `fecha_ingreso`, `o`.`fecha_promesa_entrega` AS `fecha_promesa_entrega`, `o`.`estado` AS `estado`, `o`.`total` AS `total`, `c`.`nombre` AS `cliente_nombre`, `c`.`telefono` AS `cliente_telefono`, `v`.`marca` AS `vehiculo_marca`, `v`.`modelo` AS `vehiculo_modelo`, `v`.`anio` AS `vehiculo_anio`, `v`.`placas` AS `vehiculo_placas`, `u`.`nombre_completo` AS `usuario_nombre`, `o`.`problema_reportado` AS `problema_reportado` FROM (((`ordenes_servicio` `o` join `clientes` `c` on(`o`.`cliente_id` = `c`.`id`)) join `vehiculos` `v` on(`o`.`vehiculo_id` = `v`.`id`)) join `usuarios` `u` on(`o`.`usuario_id` = `u`.`id`)) ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `danos_vehiculo`
--
ALTER TABLE `danos_vehiculo`
  ADD CONSTRAINT `danos_vehiculo_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `historial_cambios` (COMENTADO - NO USADA)
--
-- ALTER TABLE `historial_cambios`
--   ADD CONSTRAINT `historial_cambios_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE,
--   ADD CONSTRAINT `historial_cambios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

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
-- Constraints for table `pagos` (COMENTADO - NO USADA)
--
-- ALTER TABLE `pagos`
--   ADD CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE,
--   ADD CONSTRAINT `pagos_ibfk_2` FOREIGN KEY (`usuario_registro_id`) REFERENCES `usuarios` (`id`);

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
-- Constraints for table `alertas_servicio`
--
ALTER TABLE `alertas_servicio`
  ADD CONSTRAINT `alertas_servicio_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertas_servicio_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertas_servicio_ibfk_3` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `alertas_servicio_ibfk_4` FOREIGN KEY (`usuario_marco_leida`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD CONSTRAINT `vehiculos_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;