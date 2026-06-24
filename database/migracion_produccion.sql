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
-- Database: `saggarag_GestionPresupuestos`
--

DELIMITER $$
--
-- Procedures
--
CREATE PROCEDURE `InicializarCalendario` (`dias_adelante` INT)   BEGIN
    DECLARE fecha_actual DATE DEFAULT CURDATE();
    DECLARE fecha_limite DATE DEFAULT DATE_ADD(CURDATE(), INTERVAL dias_adelante DAY);
    DECLARE horario_am TIME DEFAULT TIME(GetTwilioConfig('horario_matutino'));
    DECLARE horario_pm TIME DEFAULT TIME(GetTwilioConfig('horario_vespertino'));
    
    WHILE fecha_actual <= fecha_limite DO
        -- Solo días laborales
        IF WEEKDAY(fecha_actual) BETWEEN 0 AND 4 THEN
            -- Insertar slot matutino
            INSERT IGNORE INTO calendario_disponibilidad (fecha, hora, es_dia_laborable)
            VALUES (fecha_actual, horario_am, TRUE);
            
            -- Insertar slot vespertino
            INSERT IGNORE INTO calendario_disponibilidad (fecha, hora, es_dia_laborable)
            VALUES (fecha_actual, horario_pm, TRUE);
        END IF;
        
        SET fecha_actual = DATE_ADD(fecha_actual, INTERVAL 1 DAY);
    END WHILE;
END$$

CREATE PROCEDURE `LimpiarDatosAntiguos` ()   BEGIN
    -- Limpiar conversaciones mayores a 3 meses
    DELETE FROM conversaciones_whatsapp 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL 3 MONTH);
    
    -- Limpiar slots de calendario pasados
    DELETE FROM calendario_disponibilidad 
    WHERE fecha < CURDATE();
    
    -- Limpiar citas pre-agendadas muy antiguas
    DELETE FROM citas_pre_agendadas 
    WHERE estado != 'confirmada' 
    AND fecha_pre_agenda < DATE_SUB(NOW(), INTERVAL 1 MONTH);
    
    SELECT ROW_COUNT() as registros_limpiados;
END$$

CREATE PROCEDURE `sp_reporte_anual` (IN `p_anio` INT)   BEGIN
    SELECT 
        '=== REPORTE ANUAL ===' as titulo,
        p_anio as anio;
        
    SELECT 
        periodo,
        nombre_mes,
        FORMAT(ingresos, 2) as ingresos_formatted,
        FORMAT(egresos, 2) as egresos_formatted, 
        FORMAT(ganancia_neta, 2) as ganancia_formatted,
        CONCAT(porcentaje_rentabilidad, '%') as rentabilidad,
        ordenes_completadas
    FROM vista_reporte_mensual
    WHERE anio = p_anio
    ORDER BY mes;
    
    -- Resumen anual
    SELECT 
        '=== RESUMEN ANUAL ===' as resumen,
        FORMAT(SUM(ingresos), 2) as total_ingresos,
        FORMAT(SUM(egresos), 2) as total_egresos,
        FORMAT(SUM(ganancia_neta), 2) as ganancia_total,
        FORMAT(AVG(porcentaje_rentabilidad), 2) as rentabilidad_promedio,
        SUM(ordenes_completadas) as total_ordenes
    FROM vista_reporte_mensual
    WHERE anio = p_anio;
END$$

CREATE PROCEDURE `sp_reporte_mensual` (IN `p_anio` INT, IN `p_mes` INT)   BEGIN
    DECLARE v_nombre_mes VARCHAR(20);
    
    SELECT MONTHNAME(CONCAT(p_anio, '-', LPAD(p_mes, 2, '0'), '-01')) INTO v_nombre_mes;
    
    SELECT 
        CONCAT('=== REPORTE MENSUAL: ', v_nombre_mes, ' ', p_anio, ' ===') as titulo;
    
    -- Resumen del mes
    SELECT 
        periodo,
        nombre_mes,
        FORMAT(ingresos, 2) as 'Ingresos Totales',
        FORMAT(ingresos_mano_obra, 2) as 'Mano de Obra',
        FORMAT(ingresos_servicios, 2) as 'Servicios',
        FORMAT(ingresos_refacciones, 2) as 'Refacciones Cobradas',
        FORMAT(egresos, 2) as 'Costo Refacciones',
        FORMAT(ganancia_neta, 2) as 'Ganancia Neta',
        CONCAT(porcentaje_rentabilidad, '%') as 'Rentabilidad',
        ordenes_completadas as 'Órdenes Completadas'
    FROM vista_reporte_mensual
    WHERE anio = p_anio AND mes = p_mes;
    
    -- Detalle de órdenes del mes
    SELECT 
        '=== DETALLE DE ÓRDENES ===' as detalle;
        
    SELECT 
        o.numero_orden as 'No. Orden',
        c.nombre as Cliente,
        CONCAT(v.marca, ' ', v.modelo, ' ', IFNULL(v.anio, '')) as Vehiculo,
        DATE_FORMAT(o.fecha_ingreso, '%d/%m/%Y') as 'Fecha',
        FORMAT(o.total, 2) as 'Total Cobrado',
        FORMAT(IFNULL(ref_total.costo_refacciones, 0), 2) as 'Costo Refacciones',
        FORMAT(o.total - IFNULL(ref_total.costo_refacciones, 0), 2) as 'Ganancia'
    FROM ordenes_servicio o
    INNER JOIN clientes c ON o.cliente_id = c.id
    INNER JOIN vehiculos v ON o.vehiculo_id = v.id
    LEFT JOIN (
        SELECT 
            orden_id,
            SUM(precio_unitario * cantidad) as costo_refacciones
        FROM refacciones_orden
        GROUP BY orden_id
    ) ref_total ON o.id = ref_total.orden_id
    WHERE o.estado = 'cerrada'
        AND o.fecha_ingreso IS NOT NULL
        AND YEAR(o.fecha_ingreso) = p_anio
        AND MONTH(o.fecha_ingreso) = p_mes
    ORDER BY o.fecha_ingreso;
END$$

--
-- Functions
--
CREATE FUNCTION `FormatearTelefono` (`numero` VARCHAR(20)) RETURNS VARCHAR(20) CHARSET latin1 COLLATE latin1_swedish_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE numero_limpio VARCHAR(20);
    
    -- Limpiar el número (solo dígitos)
    SET numero_limpio = REGEXP_REPLACE(numero, '[^0-9]', '');
    
    -- Si empieza con 52, ya es formato internacional
    IF LEFT(numero_limpio, 2) = '52' AND LENGTH(numero_limpio) = 12 THEN
        RETURN CONCAT('+', numero_limpio);
    END IF;
    
    -- Si empieza con 1 y tiene 10 dígitos, agregar 52
    IF LEFT(numero_limpio, 1) IN ('1','2','3','4','5','6','7','8','9') AND LENGTH(numero_limpio) = 10 THEN
        RETURN CONCAT('+52', numero_limpio);
    END IF;
    
    -- Si tiene 8 dígitos, asumir que necesita código de área de México
    IF LENGTH(numero_limpio) = 8 THEN
        RETURN CONCAT('+521', numero_limpio);
    END IF;
    
    -- Si no se puede determinar, devolver como está
    RETURN numero;
END$$

CREATE FUNCTION `GenerarFechasDisponibles` (`dias_adelante` INT) RETURNS LONGTEXT CHARSET utf8mb4 COLLATE utf8mb4_bin DETERMINISTIC READS SQL DATA BEGIN
    DECLARE fechas_json JSON DEFAULT JSON_ARRAY();
    DECLARE fecha_actual DATE DEFAULT DATE_ADD(CURDATE(), INTERVAL 1 DAY);
    DECLARE contador INT DEFAULT 0;
    DECLARE dias_generados INT DEFAULT 0;
    
    -- Obtener configuración
    DECLARE horario_am TIME DEFAULT TIME(GetTwilioConfig('horario_matutino'));
    DECLARE horario_pm TIME DEFAULT TIME(GetTwilioConfig('horario_vespertino'));
    DECLARE capacidad_max INT DEFAULT CAST(GetTwilioConfig('capacidad_por_horario') AS UNSIGNED);
    
    WHILE dias_generados < dias_adelante AND contador < 30 DO
        SET contador = contador + 1;
        
        -- Solo días laborales (Lunes=0 a Viernes=4)
        IF WEEKDAY(fecha_actual) BETWEEN 0 AND 4 THEN
            -- Verificar slot matutino
            SET @citas_am = (
                SELECT COALESCE(citas_ocupadas, 0) 
                FROM calendario_disponibilidad 
                WHERE fecha = fecha_actual AND hora = horario_am
            );
            
            IF @citas_am < capacidad_max THEN
                SET fechas_json = JSON_ARRAY_APPEND(fechas_json, '$', JSON_OBJECT(
                    'fecha', fecha_actual,
                    'hora', horario_am,
                    'horario', 'matutino',
                    'texto', CONCAT(DATE_FORMAT(fecha_actual, '%W %e de %M'), ' - ', TIME_FORMAT(horario_am, '%H:%i')),
                    'disponible', TRUE
                ));
            END IF;
            
            -- Verificar slot vespertino  
            SET @citas_pm = (
                SELECT COALESCE(citas_ocupadas, 0) 
                FROM calendario_disponibilidad 
                WHERE fecha = fecha_actual AND hora = horario_pm
            );
            
            IF @citas_pm < capacidad_max THEN
                SET fechas_json = JSON_ARRAY_APPEND(fechas_json, '$', JSON_OBJECT(
                    'fecha', fecha_actual,
                    'hora', horario_pm,
                    'horario', 'vespertino', 
                    'texto', CONCAT(DATE_FORMAT(fecha_actual, '%W %e de %M'), ' - ', TIME_FORMAT(horario_pm, '%H:%i')),
                    'disponible', TRUE
                ));
            END IF;
            
            SET dias_generados = dias_generados + 1;
        END IF;
        
        SET fecha_actual = DATE_ADD(fecha_actual, INTERVAL 1 DAY);
    END WHILE;
    
    RETURN fechas_json;
END$$

CREATE FUNCTION `GetTwilioConfig` (`config_key_param` VARCHAR(100)) RETURNS TEXT CHARSET latin1 COLLATE latin1_swedish_ci DETERMINISTIC READS SQL DATA BEGIN
    DECLARE config_val TEXT DEFAULT NULL;
    
    SELECT config_value INTO config_val 
    FROM twilio_config 
    WHERE config_key = config_key_param 
      AND is_active = TRUE
    LIMIT 1;
    
    RETURN config_val;
END$$

DELIMITER ;

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
--


-- --------------------------------------------------------

--
--

  `id` int(11) NOT NULL DEFAULT 0,
  `orden_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `vehiculo_id` int(11) NOT NULL,
  `fecha_ultimo_servicio` timestamp NOT NULL,
  `servicios_que_dispararon` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `todos_los_servicios` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` enum('pendiente','leida') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `estado_whatsapp` enum('borrador','enviado','esperando_respuesta','esperando_fecha','pre_agendado','confirmado','rechazado','requiere_contacto','cancelado','completado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'borrador',
  `twilio_conversation_sid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_envio_whatsapp` datetime DEFAULT NULL,
  `respuesta_inicial` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_respuesta_inicial` datetime DEFAULT NULL,
  `fecha_cita_seleccionada` date DEFAULT NULL,
  `hora_cita_seleccionada` time DEFAULT NULL,
  `fecha_pre_agendado` datetime DEFAULT NULL,
  `confirmacion_sag` enum('pendiente','confirmado','cancelado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_twilio` enum('borrador','enviado','esperando_respuesta','esperando_fecha','pre_agendado','confirmado','cancelado','completado','requiere_atencion','reprogramar') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'borrador',
  `twilio_message_sid` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `respuesta_cliente` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL,
  `fecha_seleccionada_cliente` date DEFAULT NULL,
  `hora_seleccionada_cliente` time DEFAULT NULL,
  `fecha_confirmacion_sag` datetime DEFAULT NULL,
  `usuario_confirmo_sag` int(11) DEFAULT NULL,
  `requiere_atencion` tinyint(1) DEFAULT 0,
  `prioridad` enum('baja','media','alta') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'media',
  `ultima_actividad` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `confirmado_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_cancelacion` datetime DEFAULT NULL,
  `cancelado_por` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_reprogramacion` datetime DEFAULT NULL,
  `prioridad_atencion` enum('baja','media','alta') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'media',
  `fecha_generada` timestamp NULL DEFAULT current_timestamp(),
  `fecha_marcada_leida` timestamp NULL DEFAULT NULL,
  `usuario_marco_leida` int(11) DEFAULT NULL,
  `dias_desde_servicio` int(11) NOT NULL,
  `whatsapp_estado` enum('pendiente','programado','enviado','entregado','leido','error','omitido') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente' COMMENT 'Estado del envío de WhatsApp',
  `whatsapp_fecha_programada` timestamp NULL DEFAULT NULL COMMENT 'Cuándo se programó el envío',
  `whatsapp_fecha_enviada` timestamp NULL DEFAULT NULL COMMENT 'Cuándo se envió realmente',
  `whatsapp_template_id` int(11) DEFAULT NULL COMMENT 'ID del template usado',
  `whatsapp_mensaje_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ID del mensaje en WhatsApp API',
  `whatsapp_error_mensaje` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Descripción del error si falla',
  `whatsapp_intentos_envio` int(11) DEFAULT 0 COMMENT 'Número de intentos de envío',
  `whatsapp_ultimo_intento` timestamp NULL DEFAULT NULL COMMENT 'Fecha del último intento'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

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

--
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
--


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
--


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
--


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
--


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
--


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
,`ultimo_mensaje` mediumtext
,`fecha_ultimo_mensaje` timestamp /* mariadb-5.3 */
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `alertas_servicio`
--
ALTER TABLE `alertas_servicio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `caja_chica`
--
ALTER TABLE `caja_chica`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `calendario_disponibilidad`
--
ALTER TABLE `calendario_disponibilidad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `citas_pre_agendadas`
--
ALTER TABLE `citas_pre_agendadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `conversaciones_whatsapp`
--
ALTER TABLE `conversaciones_whatsapp`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `danos_vehiculo`
--
ALTER TABLE `danos_vehiculo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `empleados_sueldos`
--
ALTER TABLE `empleados_sueldos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `empleado_asistencia`
--
ALTER TABLE `empleado_asistencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `estados_seguridad`
--
ALTER TABLE `estados_seguridad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `gastos_administrativos`
--
ALTER TABLE `gastos_administrativos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `gastos_orden`
--
ALTER TABLE `gastos_orden`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `ordenes_servicio`
--
ALTER TABLE `ordenes_servicio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `orden_puntos_seguridad`
--
ALTER TABLE `orden_puntos_seguridad`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `pagos_fijos`
--
ALTER TABLE `pagos_fijos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `puntos_seguridad_catalogo`
--
ALTER TABLE `puntos_seguridad_catalogo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `refacciones_orden`
--
ALTER TABLE `refacciones_orden`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `servicios_orden`
--
ALTER TABLE `servicios_orden`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `twilio_config`
--
ALTER TABLE `twilio_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `usuario_sucursales`
--
ALTER TABLE `usuario_sucursales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

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

CREATE OR REPLACE VIEW `vista_reporte_mensual`  AS SELECT year(`periodos`.`fecha_periodo`) AS `anio`, month(`periodos`.`fecha_periodo`) AS `mes`, date_format(`periodos`.`fecha_periodo`,'%Y-%m') AS `periodo`, monthname(`periodos`.`fecha_periodo`) AS `nombre_mes`, ifnull(`ingresos`.`ingresos_totales`,0.00) AS `ingresos`, ifnull(`ingresos`.`ingresos_mano_obra`,0.00) AS `ingresos_mano_obra`, ifnull(`ingresos`.`ingresos_servicios`,0.00) AS `ingresos_servicios`, ifnull(`ingresos`.`ingresos_refacciones`,0.00) AS `ingresos_refacciones`, ifnull(`ingresos`.`total_iva_cobrado`,0.00) AS `iva_cobrado`, ifnull(`ingresos`.`ordenes_completadas`,0) AS `ordenes_completadas`, ifnull(`egresos`.`egresos_refacciones`,0.00) AS `egresos`, ifnull(`ingresos`.`ingresos_totales`,0.00) - ifnull(`egresos`.`egresos_refacciones`,0.00) AS `balance_bruto`, ifnull(`egresos`.`egresos_refacciones`,0.00) * 0.30 AS `margen_refacciones`, ifnull(`ingresos`.`ingresos_mano_obra`,0.00) + ifnull(`ingresos`.`ingresos_servicios`,0.00) + (ifnull(`ingresos`.`ingresos_refacciones`,0.00) - ifnull(`egresos`.`egresos_refacciones`,0.00)) AS `ganancia_neta`, CASE WHEN ifnull(`egresos`.`egresos_refacciones`,0.00) > 0 THEN round((ifnull(`ingresos`.`ingresos_totales`,0.00) - ifnull(`egresos`.`egresos_refacciones`,0.00)) / ifnull(`egresos`.`egresos_refacciones`,0.00) * 100,2) ELSE 100.00 END AS `porcentaje_rentabilidad` FROM (((select '2025-01-01' + interval `numbers`.`n` month AS `fecha_periodo` from (select `a`.`N` + `b`.`N` * 10 + `c`.`N` * 100 AS `n` from (((select 0 AS `N` union select 1 AS `1` union select 2 AS `2` union select 3 AS `3` union select 4 AS `4` union select 5 AS `5` union select 6 AS `6` union select 7 AS `7` union select 8 AS `8` union select 9 AS `9`) `a` join (select 0 AS `N` union select 1 AS `1` union select 2 AS `2` union select 3 AS `3` union select 4 AS `4` union select 5 AS `5` union select 6 AS `6` union select 7 AS `7` union select 8 AS `8` union select 9 AS `9`) `b`) join (select 0 AS `N` union select 1 AS `1` union select 2 AS `2` union select 3 AS `3` union select 4 AS `4` union select 5 AS `5` union select 6 AS `6` union select 7 AS `7` union select 8 AS `8` union select 9 AS `9`) `c`)) `numbers` where '2025-01-01' + interval `numbers`.`n` month <= curdate()) `periodos` left join (select date_format(`ordenes_servicio`.`fecha_ingreso`,'%Y-%m-01') AS `periodo_ingreso`,count(0) AS `ordenes_completadas`,sum(`ordenes_servicio`.`total`) AS `ingresos_totales`,sum(`ordenes_servicio`.`subtotal_mano_obra`) AS `ingresos_mano_obra`,sum(`ordenes_servicio`.`subtotal_servicios`) AS `ingresos_servicios`,sum(`ordenes_servicio`.`subtotal_refacciones`) AS `ingresos_refacciones`,sum(`ordenes_servicio`.`iva`) AS `total_iva_cobrado` from `ordenes_servicio` where `ordenes_servicio`.`estado` = 'cerrada' and `ordenes_servicio`.`fecha_ingreso` is not null and date_format(`ordenes_servicio`.`fecha_ingreso`,'%Y-%m') >= '2025-01' group by date_format(`ordenes_servicio`.`fecha_ingreso`,'%Y-%m')) `ingresos` on(`periodos`.`fecha_periodo` = `ingresos`.`periodo_ingreso`)) left join (select date_format(`o`.`fecha_ingreso`,'%Y-%m-01') AS `periodo_egreso`,sum(`r`.`precio_unitario` * `r`.`cantidad`) AS `egresos_refacciones` from (`refacciones_orden` `r` join `ordenes_servicio` `o` on(`r`.`orden_id` = `o`.`id`)) where `o`.`estado` = 'cerrada' and `o`.`fecha_ingreso` is not null and date_format(`o`.`fecha_ingreso`,'%Y-%m') >= '2025-01' group by date_format(`o`.`fecha_ingreso`,'%Y-%m')) `egresos` on(`periodos`.`fecha_periodo` = `egresos`.`periodo_egreso`)) WHERE year(`periodos`.`fecha_periodo`) >= 2025 ORDER BY year(`periodos`.`fecha_periodo`) DESC, month(`periodos`.`fecha_periodo`) DESC ;

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
-- CARFIX -- Seed Data Inicial (2026-06-23)
-- ============================================================

INSERT INTO `sucursales` (`id`, `nombre`, `activo`, `created_at`, `updated_at`)
VALUES (1, 'CarFix Matriz', 1, NOW(), NOW());

-- Contrasenas iniciales iguales a las de SAG Garage:
--   carfix     = contrasena del usuario saggarage
--   carfix_dev = contrasena del usuario markiiak
-- Cambiar desde Sistemas > Usuarios despues del primer login.
INSERT INTO `usuarios`
  (`id`, `username`, `password_hash`, `nombre_completo`, `email`, `rol`, `activo`, `fecha_creacion`, `ultima_modificacion`)
VALUES
(1,'carfix','\\\/gLD1RB2qAC',
   'CarFix Admin','admin@tallercarfix.com.mx','superusuario',1,NOW(),NOW()),
(2,'carfix_dev','\\\$.rF6JqF1Xhf13FjOFnrSeeOv0BftEdb.uXcNnt/bpEAADjWeTLIXG',
   'CarFix Dev','dev@tallercarfix.com.mx','sistemas',1,NOW(),NOW());

INSERT INTO `twilio_config` (`config_key`, `config_value`, `description`, `is_active`) VALUES
('account_sid','','Twilio Account SID',1),
('auth_token','','Twilio Auth Token',1),
('whatsapp_from','','Numero WhatsApp de envio (whatsapp:+521XXXXXXXXXX)',1),
('sag_admin_phone','','Numero WhatsApp del admin CarFix (sin +52)',1),
('sag_business_name','CarFix','Nombre del negocio',1),
('horario_matutino','09:00','Hora de citas matutinas',1),
('horario_vespertino','14:00','Hora de citas vespertinas',1),
('dias_laborables','L,M,M,J,V','Dias laborables',1),
('capacidad_por_horario','2','Maximo de citas por slot',1),
('dias_anticipacion','7','Dias hacia adelante para fechas disponibles',1),
('dias_minimo_agenda','1','Dias minimos de anticipacion para agendar',1),
('horarios_atencion','10:00,12:00,14:00,16:00','Horarios disponibles para citas',1),
('slots_maximo_mostrar','8','Maximo de slots a mostrar al cliente',1),
('template_mode','interactive','Modo template Twilio',1),
('max_intentos_respuesta','3','Maximo de intentos para respuesta valida',1),
('template_interactive_sid','','ContentSid template recordatorio',0),
('template_agendar_sid','','ContentSid template de horarios',0),
('sag_confirma_cita','','ContentSid para notificaciones al admin',0),
('template_otro_horario_sid','','ContentSid cuando cliente pide otro horario',0),
('template_atencion_personalizada_sid','','ContentSid para escalada al admin',0),
('webhook_url','https://tallercarfix.com.mx/gestion/backend-php/webhook/twilio_whatsapp.php','URL del webhook de Twilio',1),
('dias_festivos_2026','["2026-01-01","2026-02-03","2026-03-16","2026-04-13","2026-04-14","2026-05-01","2026-09-16","2026-11-02","2026-11-16","2026-12-25"]','Dias festivos Mexico 2026',1),
('mensaje_rechazo','Claro, muchas gracias por responder. Cuando sea el momento indicado, con gusto estaremos para ti.','Mensaje para respuesta NO',1),
('mensaje_contacto_directo','Un ejecutivo de CarFix se pondra en contacto contigo para agendar en la fecha que mejor te convenga.','Mensaje para contacto directo',1),
('mensaje_cancelacion','Disculpa las molestias. Un ejecutivo se pondra en contacto contigo para reagendar tu cita.','Mensaje cuando CarFix cancela',1),
('mensaje_otro_horario','Entendido. Nuestro equipo se pondra en contacto contigo para coordinar un horario que te convenga mejor.','Mensaje cuando cliente elige Otro horario',1);
