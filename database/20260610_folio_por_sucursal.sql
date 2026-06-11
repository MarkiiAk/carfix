-- =====================================================================
-- Migración: folio_sucursal — consecutivo propio por sucursal
-- Fecha: 2026-06-10
-- Propósito: cada sucursal tiene su propio contador de órdenes (1, 2, 3...)
--            visible en el Kanban y en PDFs, independiente del ID de BD.
-- =====================================================================

-- 1. Agregar la columna
ALTER TABLE `ordenes_servicio`
  ADD COLUMN IF NOT EXISTS `folio_sucursal` INT UNSIGNED NOT NULL DEFAULT 1
    COMMENT 'Consecutivo propio por sucursal. 1, 2, 3... reinicia en cada sucursal.'
    AFTER `sucursal_id`;

-- 2. Rellenar registros existentes de sucursal 1 con secuencia ordenada por id
--    (si ya existen registros de otras sucursales, también se cubren)
SET @rn1 := 0;
UPDATE `ordenes_servicio`
SET `folio_sucursal` = (@rn1 := @rn1 + 1)
WHERE `sucursal_id` = 1
ORDER BY `id` ASC;

SET @rn2 := 0;
UPDATE `ordenes_servicio`
SET `folio_sucursal` = (@rn2 := @rn2 + 1)
WHERE `sucursal_id` = 2
ORDER BY `id` ASC;

-- Para sucursales > 2 agregar bloques análogos si existen.

-- 3. Índice para acelerar MAX(folio_sucursal) por sucursal en cada nuevo INSERT
ALTER TABLE `ordenes_servicio`
  ADD INDEX IF NOT EXISTS `idx_folio_sucursal` (`sucursal_id`, `folio_sucursal`);
