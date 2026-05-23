-- Migración: tabla de asistencia semanal por empleado
-- Permite registrar los días trabajados por semana para cálculo real de sueldos.
-- Para tipo_sueldo='diario': pago = dias_trabajados × sueldo_diario
-- Para tipo_sueldo='semanal': pago = sueldo_diario flat (sin días)

CREATE TABLE IF NOT EXISTS `empleado_asistencia` (
  `id`              INT          NOT NULL AUTO_INCREMENT,
  `empleado_id`     INT          NOT NULL,
  `semana_inicio`   DATE         NOT NULL COMMENT 'Lunes de la semana (YYYY-MM-DD)',
  `dias_trabajados` TINYINT      NOT NULL DEFAULT 5,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_empleado_semana` (`empleado_id`, `semana_inicio`),
  KEY `idx_semana_inicio` (`semana_inicio`),
  CONSTRAINT `fk_asistencia_empleado`
    FOREIGN KEY (`empleado_id`) REFERENCES `empleados_sueldos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
