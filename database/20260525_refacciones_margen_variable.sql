-- ============================================================
-- Migración: Margen variable en refacciones
-- Fecha: 2026-05-25
-- Archivo: database/20260525_refacciones_margen_variable.sql
-- ============================================================
-- Permite almacenar el precio de costo y el margen de ganancia
-- por refacción individualmente, en lugar del 30% fijo global.
-- NULL = registro anterior (el sistema usa subtotal/1.30 como fallback).
-- ============================================================

ALTER TABLE `refacciones_orden`
  ADD COLUMN `precio_costo` decimal(10,2) DEFAULT NULL
    COMMENT 'Precio de costo por unidad (sin margen). NULL = registro previo a esta migración'
    AFTER `precio_unitario`,
  ADD COLUMN `margen_ganancia` decimal(5,2) DEFAULT NULL
    COMMENT 'Porcentaje de ganancia aplicado (ej: 30.00). NULL = se asume 30%'
    AFTER `precio_costo`;
