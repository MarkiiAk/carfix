-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 23, 2026 at 06:11 PM
-- Server version: 11.4.10-MariaDB-cll-lve-log
-- PHP Version: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `saggarag_staging`
--


-- --------------------------------------------------------

--
-- Table structure for table `alertas_ejecucion_log`
--

CREATE TABLE `alertas_ejecucion_log` (
  `id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL DEFAULT 1,
  `fecha_ejecucion` date NOT NULL,
  `alertas_generadas` int(11) DEFAULT 0,
  `tiempo_ejecucion_ms` int(11) DEFAULT 0,
  `usuario_id` int(11) DEFAULT NULL,
  `detalles` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de ejecuciones diarias del sistema automático de generación de alertas';

--
-- Dumping data for table `alertas_ejecucion_log`
--


-- --------------------------------------------------------

--
-- Table structure for table `alertas_servicio`
--

CREATE TABLE `alertas_servicio` (
  `id` int(11) NOT NULL,
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

--
-- Dumping data for table `alertas_servicio`
--


-- --------------------------------------------------------

--

--

-- --------------------------------------------------------

--
-- Table structure for table `caja_chica`
--

CREATE TABLE `caja_chica` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo` enum('ingreso','egreso') NOT NULL,
  `concepto` varchar(150) NOT NULL,
  `monto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notas` text DEFAULT NULL,
  `gasto_admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sucursal_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `caja_chica`
--


-- --------------------------------------------------------

--
-- Table structure for table `calendario_disponibilidad`
--

CREATE TABLE `calendario_disponibilidad` (
  `id` int(11) NOT NULL,
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

--
-- Dumping data for table `calendario_disponibilidad`
--


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

--
-- Dumping data for table `citas_pre_agendadas`
--


-- --------------------------------------------------------

--
-- Table structure for table `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
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

--
-- Dumping data for table `clientes`
--


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
  `error_message` mediumtext DEFAULT NULL,
  `twilio_response` longtext DEFAULT NULL CHECK (json_valid(`twilio_response`)),
  `conversation_step` enum('recordatorio_inicial','respuesta_si_no','seleccion_fecha','confirmacion_pre_agenda','confirmacion_sag','mensaje_final','contacto_directo') NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conversaciones_whatsapp`
--


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

--
-- Dumping data for table `danos_vehiculo`
--


-- --------------------------------------------------------

--
-- Table structure for table `empleados_sueldos`
--

CREATE TABLE `empleados_sueldos` (
  `id` int(11) NOT NULL,
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

--
-- Dumping data for table `empleados_sueldos`
--


-- --------------------------------------------------------

--
-- Table structure for table `empleado_asistencia`
--

CREATE TABLE `empleado_asistencia` (
  `id` int(11) NOT NULL,
  `empleado_id` int(11) NOT NULL,
  `semana_inicio` date NOT NULL,
  `dias_trabajados` tinyint(4) NOT NULL DEFAULT 5,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `empleado_asistencia`
--


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

--
-- Dumping data for table `estados_seguridad`
--

INSERT INTO `estados_seguridad` (`id`, `nombre`, `color`, `descripcion`, `orden_visualizacion`, `activo`, `fecha_creacion`) VALUES
(1, 'Bueno', '#22c55e', 'El componente está en buen estado', 1, 1, '2026-02-02 16:49:35'),
(2, 'Recomendado', '#f59e0b', 'El componente necesita atención pronto', 2, 1, '2026-02-02 16:49:35'),
(3, 'Urgente', '#ef4444', 'El componente requiere reparación inmediata', 3, 1, '2026-02-02 16:49:35');

-- --------------------------------------------------------

--
-- Table structure for table `gastos_administrativos`
--

CREATE TABLE `gastos_administrativos` (
  `id` int(11) NOT NULL,
  `mes` tinyint(2) NOT NULL COMMENT '1-12',
  `anio` year(4) NOT NULL,
  `concepto` varchar(300) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `categoria` enum('renta','salario','servicio','insumo','otro') NOT NULL DEFAULT 'otro',
  `registrado_por` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `sucursal_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gastos_administrativos`
--


-- --------------------------------------------------------

--
-- Table structure for table `gastos_orden`
--

CREATE TABLE `gastos_orden` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `concepto` varchar(300) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `tipo` enum('envio','consumible','propina','otro') NOT NULL DEFAULT 'otro',
  `registrado_por` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `sucursal_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `gastos_orden`
--


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
  `sucursal_id` int(11) NOT NULL DEFAULT 1,
  `folio_sucursal` int(10) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Consecutivo propio por sucursal. 1, 2, 3... reinicia en cada sucursal.',
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

--
-- Dumping data for table `ordenes_servicio`
--


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

--
-- Dumping data for table `orden_puntos_seguridad`
--


-- --------------------------------------------------------

--
-- Table structure for table `pagos_fijos`
--

CREATE TABLE `pagos_fijos` (
  `id` int(11) NOT NULL,
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

--
-- Dumping data for table `pagos_fijos`
--


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

--
-- Dumping data for table `puntos_seguridad_catalogo`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `refacciones_orden`
--

CREATE TABLE `refacciones_orden` (
  `id` int(11) NOT NULL,
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

--
-- Dumping data for table `refacciones_orden`
--


-- --------------------------------------------------------

--
-- Table structure for table `servicios_orden`
--

CREATE TABLE `servicios_orden` (
  `id` int(11) NOT NULL,
  `orden_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL DEFAULT 1,
  `tipo` enum('servicio','mano_obra') DEFAULT 'mano_obra',
  `descripcion` varchar(500) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `cantidad` decimal(8,2) DEFAULT 1.00,
  `subtotal` decimal(10,2) NOT NULL,
  `notas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `servicios_orden`
--


-- --------------------------------------------------------

--
-- Table structure for table `sucursales`
--

CREATE TABLE `sucursales` (
  `id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `direccion` text DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de sucursales del taller';

--
-- Dumping data for table `sucursales`
--

INSERT INTO `sucursales` (`id`,`nombre`,`activo`,`created_at`,`updated_at`) VALUES
(1,'CarFix Staging',1,NOW(),NOW());


-- --------------------------------------------------------

--
-- Table structure for table `twilio_config`
--

CREATE TABLE `twilio_config` (
  `id` int(11) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `twilio_config`
--

INSERT INTO `twilio_config` (`id`, `config_key`, `config_value`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'account_sid', 'XXXX_ACCOUNT_SID_CARFIX', 'Twilio Account SID', 1, '2026-04-01 20:36:33', '2026-04-02 02:53:14'),
(2, 'auth_token', 'XXXX_AUTH_TOKEN_CARFIX', 'Twilio Auth Token', 1, '2026-04-01 20:36:33', '2026-04-02 02:53:14'),
(3, 'whatsapp_from', '', 'Número desde el cual enviar (Produccion)', 1, '2026-04-01 20:36:33', '2026-04-08 02:19:20'),
(4, 'sag_admin_phone', '5640020052', 'Número WhatsApp de SAG Garage para confirmaciones', 1, '2026-04-01 20:36:33', '2026-04-14 03:20:21'),
(5, 'sag_business_name', 'SAG Garage', 'Nombre del negocio', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(6, 'horario_matutino', '09:00', 'Hora de citas matutinas', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(7, 'horario_vespertino', '14:00', 'Hora de citas vespertinas', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(8, 'dias_laborables', 'L,M,M,J,V', 'Días laborables (L=Lunes, etc.)', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(9, 'capacidad_por_horario', '2', 'Máximo de citas por slot de horario', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(10, 'dias_anticipacion', '7', 'Días hacia adelante para mostrar fechas disponibles', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(11, 'dias_minimo_agenda', '1', 'Días mínimos de anticipación para agendar', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(12, 'mensaje_recordatorio', 'Hola {cliente}, notamos que hace 6 meses nos visitaste para un {servicio}. Ya es momento de realizarlo nuevamente. ¿Te gustaría agendar?', 'Mensaje inicial de recordatorio', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(13, 'mensaje_rechazo', '¡Claro! ? Muchas gracias por responder.\n\nLo entendemos perfectamente. Cuando sea el momento indicado para ti, con gusto estaremos para ayudarte a dar seguimiento al servicio de tu vehículo y mantenerlo en óptimas condiciones ??\n\nSi más adelante tienes alguna duda o necesitas apoyo, seguimos a tus órdenes ?\n\n¡Que tengas un excelente día! ?', 'Mensaje cuando cliente dice no', 1, '2026-04-01 20:36:33', '2026-04-09 22:26:43'),
(42, 'template_otro_horario_sid', 'HX8426371cf12251f300d04c2884f869f0', 'ContentSid cuando cliente pide otro horario (sag_otro_horario_cita)', 1, '2026-04-25 01:51:12', '2026-04-25 01:51:12'),
(15, 'mensaje_confirmacion', '? ¡CONFIRMADO! Te esperamos el {fecha} a las {hora} en SAG Garage. ¡Nos vemos pronto! ?', 'Mensaje de confirmación final', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(16, 'mensaje_cancelacion', 'Disculpa las molestias. Un ejecutivo se pondrá en contacto contigo para reagendar tu cita. ¡Gracias por tu comprensión!', 'Mensaje cuando SAG cancela', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(17, 'mensaje_contacto_directo', 'Un ejecutivo de SAG Garage se pondrá en contacto contigo para agendar en la fecha que mejor te convenga. ¡Gracias!', 'Mensaje para contacto directo', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(18, 'webhook_url', 'https://saggarage.com/backend-php/webhook/twilio_whatsapp.php', 'URL del webhook de Twilio', 1, '2026-04-01 20:36:33', '2026-04-01 20:36:33'),
(19, 'content_sid', 'HX765eae763cf778deacde6238674d4108', 'Content SID de la plantilla WhatsApp aprobada: sag_garage_recordatorio', 1, '2026-04-08 15:43:57', '2026-04-08 15:43:57'),
(20, 'mensaje_no_gracias', '¡Claro! 😊 Muchas gracias por responder.\r\n\r\nLo entendemos perfectamente. Cuando sea el momento indicado para ti, con gusto estaremos para ayudarte a dar seguimiento al servicio de tu vehículo y mantenerlo en óptimas condiciones 🚗✨\r\n\r\nSi más adelante tienes alguna duda o necesitas apoyo, seguimos a tus órdenes 🙌\r\n\r\n¡Que tengas un excelente día! 💙', 'Mensaje automático para respuesta NO (psicología)', 1, '2026-04-09 02:50:10', '2026-04-09 22:28:14'),
(21, 'mensaje_si_interesa', '¡Excelente decisión!\n\nEn breve te asignaremos una fecha que se ajuste perfecto a tu agenda.\n\nNuestro equipo se pondrá en contacto contigo para coordinar todos los detalles.\n\n¡Gracias por confiar en nosotros!', 'Mensaje automático para respuesta SÍ', 1, '2026-04-09 02:50:10', '2026-04-09 18:01:17'),
(22, 'horarios_atencion', '10:00,12:00,14:00,16:00', 'Horarios disponibles para citas (formato 24h)', 1, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(23, 'hora_limite_dia_siguiente', '18:00', 'Límite para agendar día siguiente (después pasa a pasado mañana)', 1, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(24, 'dias_laborales', 'Monday,Tuesday,Wednesday,Thursday,Friday', 'Días de la semana que se atiende', 1, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(25, 'capacidad_por_slot', '1', 'Número máximo de citas por horario', 1, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(26, 'template_calendario_sid', '', 'SID del template de calendario (llenar cuando se apruebe)', 0, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(27, 'dias_festivos_2026', '[\"2026-01-01\",\"2026-02-03\",\"2026-03-16\",\"2026-04-13\",\"2026-04-14\",\"2026-05-01\",\"2026-09-16\",\"2026-11-02\",\"2026-11-16\",\"2026-12-25\"]', 'Días festivos México 2026 (formato JSON)', 1, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(28, 'mensaje_pre_agenda_exito', '¡Perfecto! 🎉 Tu cita ha sido pre-agendada para {{fecha}} a las {{hora}}.\n\nEn breve nuestro equipo confirmará tu cita y te enviará los detalles finales.\n\n¡Gracias por confiar en SAG Garage! 🚗✨', 'Mensaje cuando cliente selecciona horario', 1, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(29, 'mensaje_otro_horario', 'Entendido 👍 En breve nuestro equipo se pondrá en contacto contigo para coordinar un horario que te convenga mejor.\n\n¡Gracias por tu paciencia! 📞🚗', 'Mensaje cuando cliente elige \"Otro horario\"', 1, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(30, 'notificacion_admin_otro_horario', '🟡 CONTACTO DIRECTO SOLICITADO\n\nCliente: {{cliente}}\nTeléfono: {{telefono}}\nVehículo: {{vehiculo}}\nServicio: {{servicio}}\n\nEl cliente prefiere coordinar horario directamente.', 'Notificación al admin cuando cliente elige \"Otro horario\"', 1, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(31, 'notificacion_admin_pre_agenda', '🟢 NUEVA CITA PRE-AGENDADA\n\nCliente: {{cliente}}\nFecha: {{fecha}} {{hora}}\nVehículo: {{vehiculo}}\nServicio: {{servicio}}\n\n⚠️ REQUIERE CONFIRMACIÓN DIRECTA CON EL CLIENTE', 'Notificación al admin cuando cliente selecciona horario', 1, '2026-04-10 01:32:16', '2026-04-14 03:19:00'),
(32, 'formato_fecha_cliente', 'D j M', 'Formato fecha para mostrar al cliente (ej: Lun 10 Abr)', 1, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(33, 'formato_hora_cliente', 'g:i A', 'Formato hora para mostrar al cliente (ej: 10:00 AM)', 1, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(34, 'slots_maximo_mostrar', '8', 'Máximo número de slots a mostrar (sin contar \"Otro horario\")', 1, '2026-04-10 01:32:16', '2026-04-10 01:32:16'),
(35, 'template_mode', 'interactive', 'Modo de template: simple (texto) o interactive (botones)', 1, '2026-04-13 01:30:23', '2026-04-22 02:17:58'),
(36, 'template_agendar_sid', 'HX2c89326481fdc97a27d7cb3aa8a873a4', 'ContentSid del template de horarios con variables individuales por slot', 1, '2026-04-13 01:30:23', '2026-04-25 06:00:36'),
(37, 'template_interactive_sid', 'HX765eae763cf778deacde6238674d4108', 'ContentSid del template con botones interactivos', 1, '2026-04-13 01:30:23', '2026-04-13 02:20:17'),
(38, 'max_intentos_respuesta', '3', 'Máximo número de intentos para respuesta válida', 1, '2026-04-13 01:30:23', '2026-04-13 02:20:17'),
(39, 'mensaje_ayuda_respuesta', 'Por favor responde solo con el *NÚMERO* de la opción que prefieres (ejemplo: 1, 2, 3...). ¡Así podremos ayudarte mejor! 😊', 'Mensaje cuando el cliente da respuesta inválida', 1, '2026-04-13 01:30:23', '2026-04-13 02:20:17'),
(40, 'sag_confirma_cita', 'HXdbb9d77bdadb595d64d808ea314652a8', 'Template SID para notificaciones al admin', 1, '2026-04-14 03:55:27', '2026-04-16 13:57:35'),
(43, 'template_atencion_personalizada_sid', 'HXff262c7f59e1257ab4b4e8e666375255', 'Template Twilio sag_respuesta_invalida_seguimiento', 1, '2026-04-29 19:55:56', '2026-04-29 19:55:56');

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
  `rol` enum('sistemas','superusuario','admin_sucursal','admin','tecnico','recepcionista') DEFAULT 'admin_sucursal',
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `ultima_modificacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `usuarios`
--

INSERT INTO `usuarios` (`id`, `username`, `password_hash`, `nombre_completo`, `email`, `rol`, `activo`, `fecha_creacion`, `ultima_modificacion`) VALUES
(3, 'saggarage', '$2b$10$QA99BEkgi1ooYWBmf.rknOQAq8TSQQegRiO7lzZOO6YH1uM.1wOC.', 'SAG Garage Usuario', 'saggarage@saggarage.com', 'superusuario', 1, '2026-01-26 22:53:22', '2026-06-02 20:21:05'),
(4, 'markiiak', '$2y$10$.rF6JqF1Xhf13FjOFnrSeeOv0BftEdb.uXcNnt/bpEAADjWeTLIXG', 'Markii Ak', 'markiiak@saggarage.com', 'sistemas', 1, '2026-01-29 00:43:13', '2026-06-02 20:22:14'),
(5, 'temporaldemo', '$2b$10$bU9MwUS.Kt0AQjsgKUoafeDt3ED4Z21fokbHxg4R1FbdgJV2iOu2W', 'Usuario Demo Temporal', 'demo@saggarage.com', 'superusuario', 0, '2026-03-13 03:13:38', '2026-06-02 20:28:23'),
(6, 'nuevasucursal', '$2y$10$zhnw/0LwubkJ345nJVWxaeO/gfbUqUxol27z/g18Jxx7pwDjtIsoa', 'Nueva Sucursal del papa', NULL, 'admin_sucursal', 1, '2026-06-02 20:28:57', '2026-06-02 20:28:57');

-- --------------------------------------------------------

--
-- Table structure for table `usuario_sucursales`
--

CREATE TABLE `usuario_sucursales` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL,
  `rol_sucursal` varchar(50) DEFAULT 'admin_sucursal',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relación N:M entre usuarios y sucursales con rol por sucursal';

--
-- Dumping data for table `usuario_sucursales`
--

INSERT INTO `usuario_sucursales` (`id`, `usuario_id`, `sucursal_id`, `rol_sucursal`, `created_at`) VALUES
(1, 6, 2, 'admin_sucursal', '2026-06-02 20:29:07');

-- --------------------------------------------------------

--
-- Table structure for table `vehiculos`
--

CREATE TABLE `vehiculos` (
  `id` int(11) NOT NULL,
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

--
-- Dumping data for table `vehiculos`
--


-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_campanita_whatsapp`
-- (See below for the actual view)
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_conversaciones_whatsapp`
-- (See below for the actual view)
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_ordenes_completa`
-- (See below for the actual view)
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_rentabilidad_servicios`
-- (See below for the actual view)
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_reporte_mensual`
-- (See below for the actual view)
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `vista_top_clientes_anual`
-- (See below for the actual view)
--

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alertas_ejecucion_log`
--
ALTER TABLE `alertas_ejecucion_log`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_daily_sucursal` (`fecha_ejecucion`,`sucursal_id`),
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
  ADD KEY `idx_estado_twilio` (`estado_twilio`),
  ADD KEY `idx_twilio_sid` (`twilio_message_sid`),
  ADD KEY `idx_estado_twilio_orden` (`estado_twilio`,`orden_id`),
  ADD KEY `fk_usuario_confirma_sag` (`usuario_confirmo_sag`),
  ADD KEY `idx_estado_whatsapp` (`estado_whatsapp`),
  ADD KEY `idx_requiere_atencion` (`requiere_atencion`,`estado_whatsapp`),
  ADD KEY `idx_ultima_actividad` (`ultima_actividad`),
  ADD KEY `idx_pre_agenda` (`estado_whatsapp`,`fecha_cita_seleccionada`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

--
-- Indexes for table `caja_chica`
--
ALTER TABLE `caja_chica`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_caja_chica_fecha` (`fecha`),
  ADD KEY `fk_caja_chica_gasto_admin` (`gasto_admin_id`);

--
-- Indexes for table `calendario_disponibilidad`
--
ALTER TABLE `calendario_disponibilidad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_fecha_hora` (`fecha`,`hora`),
  ADD KEY `idx_fecha_disponible` (`fecha`,`esta_disponible`),
  ADD KEY `idx_disponibilidad` (`fecha`,`hora`,`esta_disponible`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

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
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_fusionado_en` (`fusionado_en`),
  ADD KEY `idx_activo` (`activo`),
  ADD KEY `idx_telefono_normalizado` (`telefono_normalizado`),
  ADD KEY `idx_sucursal` (`sucursal_id`);
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
-- Indexes for table `empleados_sueldos`
--
ALTER TABLE `empleados_sueldos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_empleados_sueldos_usuario` (`usuario_id`);

--
-- Indexes for table `empleado_asistencia`
--
ALTER TABLE `empleado_asistencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_empleado_semana` (`empleado_id`,`semana_inicio`),
  ADD KEY `idx_semana_inicio` (`semana_inicio`);

--
-- Indexes for table `estados_seguridad`
--
ALTER TABLE `estados_seguridad`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`),
  ADD KEY `idx_nombre` (`nombre`),
  ADD KEY `idx_activo` (`activo`);

--
-- Indexes for table `gastos_administrativos`
--
ALTER TABLE `gastos_administrativos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mes_anio` (`mes`,`anio`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_gastos_admin_usuario` (`registrado_por`);

--
-- Indexes for table `gastos_orden`
--
ALTER TABLE `gastos_orden`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_gastos_orden_usuario` (`registrado_por`),
  ADD KEY `idx_orden_id` (`orden_id`),
  ADD KEY `idx_created_at` (`created_at`);

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
  ADD KEY `idx_fecha_promesa` (`fecha_promesa_entrega`),
  ADD KEY `idx_sucursal` (`sucursal_id`),
  ADD KEY `idx_folio_sucursal` (`sucursal_id`,`folio_sucursal`);
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
-- Indexes for table `pagos_fijos`
--
ALTER TABLE `pagos_fijos`
  ADD PRIMARY KEY (`id`);

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
  ADD KEY `idx_numero_parte` (`numero_parte`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

--
-- Indexes for table `servicios_orden`
--
ALTER TABLE `servicios_orden`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_orden` (`orden_id`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

--
-- Indexes for table `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_activo` (`activo`);

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
-- Indexes for table `usuario_sucursales`
--
ALTER TABLE `usuario_sucursales`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_usuario_sucursal` (`usuario_id`,`sucursal_id`),
  ADD KEY `idx_usuario` (`usuario_id`),
  ADD KEY `idx_sucursal` (`sucursal_id`);

--
-- Indexes for table `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cliente` (`cliente_id`),
  ADD KEY `idx_placas` (`placas`),
  ADD KEY `idx_marca_modelo` (`marca`,`modelo`),
  ADD KEY `idx_sucursal` (`sucursal_id`);
ALTER TABLE `vehiculos` ADD FULLTEXT KEY `idx_busqueda` (`marca`,`modelo`,`placas`,`numero_serie`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alertas_ejecucion_log`
--
ALTER TABLE `alertas_ejecucion_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=316;

--
-- AUTO_INCREMENT for table `alertas_servicio`
--
ALTER TABLE `alertas_servicio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `caja_chica`
--
ALTER TABLE `caja_chica`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `calendario_disponibilidad`
--
ALTER TABLE `calendario_disponibilidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `citas_pre_agendadas`
--
ALTER TABLE `citas_pre_agendadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=280;

--
-- AUTO_INCREMENT for table `conversaciones_whatsapp`
--
ALTER TABLE `conversaciones_whatsapp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=155;

--
-- AUTO_INCREMENT for table `danos_vehiculo`
--
ALTER TABLE `danos_vehiculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=157;

--
-- AUTO_INCREMENT for table `empleados_sueldos`
--
ALTER TABLE `empleados_sueldos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `empleado_asistencia`
--
ALTER TABLE `empleado_asistencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `estados_seguridad`
--
ALTER TABLE `estados_seguridad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `gastos_administrativos`
--
ALTER TABLE `gastos_administrativos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=78;

--
-- AUTO_INCREMENT for table `gastos_orden`
--
ALTER TABLE `gastos_orden`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `ordenes_servicio`
--
ALTER TABLE `ordenes_servicio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1915;

--
-- AUTO_INCREMENT for table `orden_puntos_seguridad`
--
ALTER TABLE `orden_puntos_seguridad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6868;

--
-- AUTO_INCREMENT for table `pagos_fijos`
--
ALTER TABLE `pagos_fijos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `puntos_seguridad_catalogo`
--
ALTER TABLE `puntos_seguridad_catalogo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `refacciones_orden`
--
ALTER TABLE `refacciones_orden`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=647;

--
-- AUTO_INCREMENT for table `servicios_orden`
--
ALTER TABLE `servicios_orden`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=779;

--
-- AUTO_INCREMENT for table `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `twilio_config`
--
ALTER TABLE `twilio_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `usuario_sucursales`
--
ALTER TABLE `usuario_sucursales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=327;

-- --------------------------------------------------------

--
-- Structure for view `vista_campanita_whatsapp`
--
DROP TABLE IF EXISTS `vista_campanita_whatsapp`;

CREATE OR REPLACE VIEW `vista_campanita_whatsapp`  AS SELECT `a`.`id` AS `id`, `a`.`estado_whatsapp` AS `estado_whatsapp`, `a`.`requiere_atencion` AS `requiere_atencion`, `a`.`prioridad` AS `prioridad`, `a`.`ultima_actividad` AS `ultima_actividad`, `c`.`nombre` AS `cliente_nombre`, `c`.`telefono` AS `cliente_telefono`, concat(`v`.`marca`,' ',`v`.`modelo`,' ',`v`.`anio`) AS `vehiculo_info`, `a`.`servicios_que_dispararon` AS `tipo_servicio`, `a`.`fecha_cita_seleccionada` AS `fecha_cita_seleccionada`, `a`.`hora_cita_seleccionada` AS `hora_cita_seleccionada`, `a`.`confirmacion_sag` AS `confirmacion_sag`, CASE WHEN `a`.`estado_whatsapp` = 'pre_agendado' AND `a`.`confirmacion_sag` = 'pendiente' THEN 'urgente' WHEN `a`.`estado_whatsapp` = 'requiere_contacto' THEN 'contacto' WHEN `a`.`estado_whatsapp` = 'rechazado' THEN 'informativo' WHEN `a`.`estado_whatsapp` = 'cancelado' THEN 'informativo' ELSE 'normal' END AS `tipo_notificacion`, CASE WHEN `a`.`estado_whatsapp` = 'pre_agendado' AND `a`.`confirmacion_sag` = 'pendiente' THEN concat('🔴 CONFIRMAR: ',`c`.`nombre`,' - ',`a`.`fecha_cita_seleccionada`,' ',`a`.`hora_cita_seleccionada`) WHEN `a`.`estado_whatsapp` = 'requiere_contacto' THEN concat('🟡 CONTACTAR: ',`c`.`nombre`,' pidió otra fecha para ',`a`.`servicios_que_dispararon`) WHEN `a`.`estado_whatsapp` = 'rechazado' THEN concat('🔵 INFO: ',`c`.`nombre`,' no está interesado en ',`a`.`servicios_que_dispararon`) WHEN `a`.`estado_whatsapp` = 'cancelado' THEN concat('🔵 CANCELADO: ',`c`.`nombre`,' - ',`a`.`fecha_cita_seleccionada`) ELSE concat('📝 ',`c`.`nombre`,' - ',`a`.`estado_whatsapp`) END AS `mensaje_campanita`, concat('https://wa.me/52',`c`.`telefono`,'?text=Hola%20',replace(`c`.`nombre`,' ','%20'),'%20nos%20comunicamos%20de%20SAG%20Garage') AS `link_whatsapp` FROM ((`alertas_servicio` `a` join `clientes` `c` on(`a`.`cliente_id` = `c`.`id`)) join `vehiculos` `v` on(`a`.`vehiculo_id` = `v`.`id`)) WHERE `a`.`requiere_atencion` = 1 AND `a`.`estado_whatsapp` in ('pre_agendado','requiere_contacto','rechazado','cancelado') ORDER BY CASE WHEN `a`.`estado_whatsapp` = 'pre_agendado' AND `a`.`confirmacion_sag` = 'pendiente' THEN 1 WHEN `a`.`estado_whatsapp` = 'requiere_contacto' THEN 2 ELSE 3 END ASC, `a`.`prioridad` DESC, `a`.`ultima_actividad` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vista_conversaciones_whatsapp`
--
DROP TABLE IF EXISTS `vista_conversaciones_whatsapp`;

CREATE OR REPLACE VIEW `vista_conversaciones_whatsapp`  AS SELECT `a`.`id` AS `alerta_id`, `a`.`estado_whatsapp` AS `estado_whatsapp`, `a`.`ultima_actividad` AS `ultima_actividad`, `c`.`nombre` AS `cliente_nombre`, `c`.`telefono` AS `cliente_telefono`, `a`.`twilio_conversation_sid` AS `twilio_conversation_sid`, `a`.`fecha_envio_whatsapp` AS `fecha_envio_whatsapp`, `a`.`respuesta_inicial` AS `respuesta_inicial`, `a`.`fecha_respuesta_inicial` AS `fecha_respuesta_inicial`, `a`.`fecha_cita_seleccionada` AS `fecha_cita_seleccionada`, `a`.`hora_cita_seleccionada` AS `hora_cita_seleccionada`, `a`.`confirmacion_sag` AS `confirmacion_sag`, (select `conversaciones_whatsapp`.`message_body` from `conversaciones_whatsapp` where `conversaciones_whatsapp`.`alerta_id` = `a`.`id` order by `conversaciones_whatsapp`.`created_at` desc limit 1) AS `ultimo_mensaje`, (select `conversaciones_whatsapp`.`created_at` from `conversaciones_whatsapp` where `conversaciones_whatsapp`.`alerta_id` = `a`.`id` order by `conversaciones_whatsapp`.`created_at` desc limit 1) AS `fecha_ultimo_mensaje`, (select count(0) from `conversaciones_whatsapp` where `conversaciones_whatsapp`.`alerta_id` = `a`.`id`) AS `total_mensajes` FROM (`alertas_servicio` `a` join `clientes` `c` on(`a`.`cliente_id` = `c`.`id`)) WHERE `a`.`estado_whatsapp` <> 'borrador' ORDER BY `a`.`ultima_actividad` DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vista_ordenes_completa`
--
DROP TABLE IF EXISTS `vista_ordenes_completa`;

CREATE OR REPLACE VIEW `vista_ordenes_completa`  AS SELECT `o`.`id` AS `id`, `o`.`numero_orden` AS `numero_orden`, `o`.`fecha_ingreso` AS `fecha_ingreso`, `o`.`fecha_promesa_entrega` AS `fecha_promesa_entrega`, `o`.`estado` AS `estado`, `o`.`total` AS `total`, `c`.`nombre` AS `cliente_nombre`, `c`.`telefono` AS `cliente_telefono`, `v`.`marca` AS `vehiculo_marca`, `v`.`modelo` AS `vehiculo_modelo`, `v`.`anio` AS `vehiculo_anio`, `v`.`placas` AS `vehiculo_placas`, `u`.`nombre_completo` AS `usuario_nombre`, `o`.`problema_reportado` AS `problema_reportado` FROM (((`ordenes_servicio` `o` join `clientes` `c` on(`o`.`cliente_id` = `c`.`id`)) join `vehiculos` `v` on(`o`.`vehiculo_id` = `v`.`id`)) join `usuarios` `u` on(`o`.`usuario_id` = `u`.`id`)) ;

-- --------------------------------------------------------

--
-- Structure for view `vista_rentabilidad_servicios`
--
DROP TABLE IF EXISTS `vista_rentabilidad_servicios`;

CREATE OR REPLACE VIEW `vista_rentabilidad_servicios`  AS SELECT CASE WHEN `so`.`tipo` = 'mano_obra' THEN 'Mano de Obra' ELSE 'Servicios' END AS `tipo_servicio`, `so`.`descripcion` AS `descripcion`, count(0) AS `veces_realizado`, format(avg(`so`.`precio_unitario`),2) AS `precio_promedio`, format(sum(`so`.`subtotal`),2) AS `ingresos_totales` FROM (`servicios_orden` `so` join `ordenes_servicio` `o` on(`so`.`orden_id` = `o`.`id`)) WHERE `o`.`estado` in ('completada','entregada') AND year(ifnull(`o`.`fecha_completada`,`o`.`fecha_entregada`)) = year(curdate()) GROUP BY `so`.`tipo`, `so`.`descripcion` ORDER BY sum(`so`.`subtotal`) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `vista_reporte_mensual`
--
DROP TABLE IF EXISTS `vista_reporte_mensual`;
-- Error reading structure for table saggarag_staging.vista_reporte_mensual: #1267 - Illegal mix of collations (utf8mb4_unicode_ci,COERCIBLE) and (utf8mb4_general_ci,COERCIBLE) for operation &#039;&gt;=&#039;

-- --------------------------------------------------------

--
-- Structure for view `vista_top_clientes_anual`
--
DROP TABLE IF EXISTS `vista_top_clientes_anual`;

CREATE OR REPLACE VIEW `vista_top_clientes_anual`  AS SELECT `c`.`id` AS `id`, `c`.`nombre` AS `cliente`, `c`.`telefono` AS `telefono`, count(distinct `o`.`id`) AS `ordenes_completadas`, format(sum(`o`.`total`),2) AS `facturación_total`, format(avg(`o`.`total`),2) AS `promedio_por_orden`, date_format(max(ifnull(`o`.`fecha_completada`,`o`.`fecha_entregada`)),'%d/%m/%Y') AS `ultima_visita` FROM (`clientes` `c` join `ordenes_servicio` `o` on(`c`.`id` = `o`.`cliente_id`)) WHERE `o`.`estado` in ('completada','entregada') AND year(ifnull(`o`.`fecha_completada`,`o`.`fecha_entregada`)) = year(curdate()) GROUP BY `c`.`id`, `c`.`nombre`, `c`.`telefono` ORDER BY sum(`o`.`total`) DESC LIMIT 0, 10 ;

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
-- Constraints for table `caja_chica`
--
ALTER TABLE `caja_chica`
  ADD CONSTRAINT `fk_caja_chica_gasto_admin` FOREIGN KEY (`gasto_admin_id`) REFERENCES `gastos_administrativos` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `danos_vehiculo`
--
ALTER TABLE `danos_vehiculo`
  ADD CONSTRAINT `danos_vehiculo_ibfk_1` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `empleados_sueldos`
--
ALTER TABLE `empleados_sueldos`
  ADD CONSTRAINT `fk_empleados_sueldos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `empleado_asistencia`
--
ALTER TABLE `empleado_asistencia`
  ADD CONSTRAINT `fk_asistencia_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados_sueldos` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `gastos_administrativos`
--
ALTER TABLE `gastos_administrativos`
  ADD CONSTRAINT `fk_gastos_admin_usuario` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`);

--
-- Constraints for table `gastos_orden`
--
ALTER TABLE `gastos_orden`
  ADD CONSTRAINT `fk_gastos_orden_orden` FOREIGN KEY (`orden_id`) REFERENCES `ordenes_servicio` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_gastos_orden_usuario` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`);

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- ============================================================
-- CarFix Staging: limpiar credenciales SAG Garage
-- ============================================================

UPDATE `twilio_config` SET `config_value` = '' WHERE `config_key` IN (
  'account_sid','auth_token','whatsapp_from','sag_admin_phone',
  'content_sid','template_agendar_sid','template_interactive_sid',
  'template_otro_horario_sid','sag_confirma_cita',
  'template_atencion_personalizada_sid','template_calendario_sid'
);
UPDATE `twilio_config` SET `config_value` = 'CarFix Staging' WHERE `config_key` = 'sag_business_name';
UPDATE `twilio_config` SET `config_value` = 'https://staging.tallercarfix.com.mx/gestion/backend-php/webhook/twilio_whatsapp.php' WHERE `config_key` = 'webhook_url';

COMMIT;
