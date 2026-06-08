-- Migración: estados Kanban para ordenes_servicio
-- Fecha: 2026-06-07
-- Propósito: Estandarizar los valores del campo `estado` de ordenes_servicio
--            a los 5 estados del Kanban board.
-- Mapeo:
--   pendiente  → recibido
--   abierta    → en_reparacion
--   cerrada    → entregado
--   completado → entregado
--   completada → entregado
--   entregada  → entregado
--
-- IMPORTANTE: el campo es VARCHAR(20), no ENUM — no requiere ALTER TABLE MODIFY.
-- Ejecutar en este orden; aplicar ANTES de hacer deploy del frontend con Kanban.

START TRANSACTION;

UPDATE ordenes_servicio SET estado = 'recibido'     WHERE estado = 'pendiente';
UPDATE ordenes_servicio SET estado = 'en_reparacion' WHERE estado = 'abierta';
UPDATE ordenes_servicio SET estado = 'entregado'
  WHERE estado IN ('cerrada', 'completado', 'completada', 'entregada');

COMMIT;

-- Verificación post-migración (ejecutar por separado para confirmar):
-- SELECT estado, COUNT(*) AS total FROM ordenes_servicio GROUP BY estado;
-- Resultado esperado: solo valores en {recibido, diagnostico, en_reparacion, listo_entrega, entregado}
