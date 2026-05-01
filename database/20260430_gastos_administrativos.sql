-- Migración: gastos_administrativos
-- Fecha: 2026-04-30
-- Propósito: Tabla para registrar gastos fijos del taller por mes calendario
--            (renta, salarios, servicios, insumos, otros).
--            El balance mensual se calcula como:
--            ingresos_mes - total_admin - gastos_ordenes_mes

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
