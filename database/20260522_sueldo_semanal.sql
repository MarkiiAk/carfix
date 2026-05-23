-- =============================================================================
-- Migración: soporte para sueldo semanal en empleados
-- Fecha: 2026-05-22
-- Archivo: database/20260522_sueldo_semanal.sql
-- =============================================================================
--
-- Añade `tipo_sueldo` ENUM a `empleados_sueldos`.
-- La columna `sueldo_diario` reutiliza su valor para almacenar el monto semanal
-- cuando tipo_sueldo = 'semanal'. La tasa diaria efectiva se calcula en código:
--   diario  → tarifa_diaria = sueldo_diario
--   semanal → tarifa_diaria = sueldo_diario / 7
--
-- Compatibilidad hacia atrás: DEFAULT 'diario' no afecta registros existentes.
-- =============================================================================

ALTER TABLE `empleados_sueldos`
  ADD COLUMN `tipo_sueldo` ENUM('diario','semanal') NOT NULL DEFAULT 'diario'
  AFTER `sueldo_diario`;
