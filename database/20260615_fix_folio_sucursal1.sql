-- =====================================================================
-- Fix: Restaurar numeración de folios de Matriz (sucursal_id = 1)
-- Contexto: La migración 20260610_folio_por_sucursal.sql asignó
--   folio_sucursal = 1, 2, 3... N (conteo local) pero el sistema
--   anterior usaba el id global de BD (llegaba ~1844). Las órdenes
--   nuevas creadas post-deploy aparecen con folio 182 en lugar de 1845.
-- Solución: folio_sucursal = id para sucursal 1, igual que la lógica
--   anterior. Sucursal 2 no se toca (empezó desde 1 correctamente).
-- =====================================================================

-- DIAGNÓSTICO PREVIO — correr primero en modo SELECT para ver el daño:
-- SELECT id, numero_orden, folio_sucursal, fecha_ingreso
-- FROM ordenes_servicio
-- WHERE sucursal_id = 1 AND fecha_ingreso >= '2026-06-10'
-- ORDER BY id;

-- -----------------------------------------------------------------------
-- PASO 1: Corregir numero_orden de órdenes creadas después del deploy
--   (10 junio 2026). Esas tienen el folio bajo incrustado en el string.
--   Las viejas ya tenían S1-YYMMDD-{id} (formato correcto) — no se tocan.
-- -----------------------------------------------------------------------
UPDATE ordenes_servicio
SET numero_orden = CONCAT(
    'S', sucursal_id, '-',
    DATE_FORMAT(fecha_ingreso, '%y%m%d'), '-',
    id
)
WHERE sucursal_id = 1
  AND fecha_ingreso >= '2026-06-10 00:00:00';

-- -----------------------------------------------------------------------
-- PASO 2: Normalizar folio_sucursal = id para TODA la sucursal 1.
--   Esto hace que MAX(folio_sucursal) sea el id más alto de sucursal 1
--   (~1844), y la próxima orden nueva obtenga folio 1845.
-- -----------------------------------------------------------------------
UPDATE ordenes_servicio
SET folio_sucursal = id
WHERE sucursal_id = 1;

-- -----------------------------------------------------------------------
-- VERIFICACIÓN posterior:
-- -----------------------------------------------------------------------
-- SELECT MAX(folio_sucursal) AS ultimo_folio, COUNT(*) AS total_ordenes
-- FROM ordenes_servicio WHERE sucursal_id = 1;
--
-- Resultado esperado: ultimo_folio ≈ 1844, próxima orden = 1845
-- =====================================================================
