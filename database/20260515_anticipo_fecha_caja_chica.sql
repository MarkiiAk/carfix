-- Migración 2026-05-15
-- (1) Fecha de pago del anticipo en órdenes de servicio
-- (2) Tabla de caja chica semanal

ALTER TABLE `ordenes_servicio`
  ADD COLUMN `fecha_anticipo` DATE NULL DEFAULT NULL AFTER `anticipo`;

CREATE TABLE IF NOT EXISTS `caja_chica` (
  `id`           INT(11)                  NOT NULL AUTO_INCREMENT,
  `fecha`        DATE                     NOT NULL,
  `tipo`         ENUM('ingreso','egreso') NOT NULL,
  `concepto`     VARCHAR(150)             NOT NULL,
  `monto`        DECIMAL(10,2)            NOT NULL DEFAULT 0.00,
  `notas`        TEXT                     NULL,
  `created_at`   TIMESTAMP                NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_caja_chica_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
