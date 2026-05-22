-- =============================================================================
-- Migración: Vigencia temporal para sueldos y pagos fijos
-- Fecha: 2026-05-15
-- Propósito: Agregar fecha_inicio / fecha_fin para que los cambios de monto
--   no afecten retroactivamente los cálculos históricos.
--
-- Flujo cuando cambia un sueldo o pago fijo:
--   1. UPDATE registro actual → fecha_fin = hoy - 1 día
--   2. INSERT nuevo registro  → fecha_inicio = hoy, fecha_fin = NULL
--
-- El módulo financiero filtra con:
--   WHERE fecha_inicio <= :fecha_fin_periodo
--     AND (fecha_fin IS NULL OR fecha_fin >= :fecha_inicio_periodo)
--
-- DEFAULT '2026-01-01' → todos los registros existentes quedan como vigentes
--   desde el inicio del sistema, cubriendo cualquier período histórico.
-- =============================================================================

ALTER TABLE `empleados_sueldos`
  ADD COLUMN `fecha_inicio` DATE NOT NULL DEFAULT '2026-01-01' AFTER `sueldo_diario`,
  ADD COLUMN `fecha_fin`    DATE NULL     DEFAULT NULL          AFTER `fecha_inicio`;

ALTER TABLE `pagos_fijos`
  ADD COLUMN `fecha_inicio` DATE NOT NULL DEFAULT '2026-01-01' AFTER `monto`,
  ADD COLUMN `fecha_fin`    DATE NULL     DEFAULT NULL          AFTER `fecha_inicio`;
