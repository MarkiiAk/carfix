-- Fixes Categoría B y limpieza adicional
-- Fecha: 2026-04-27
-- Contexto: operaciones resueltas tras consulta con Paco Gudiño (dueño Taller Gudino)
-- APLICAR DESPUÉS de 20260427_merge_categoria_a.sql

-- ============================================================
-- FIX 1: Samanta Sanchez (id 57) — teléfono incorrecto
-- El teléfono 5536570443 pertenece a Julio Mota (id 39)
-- Samanta fue capturada con el teléfono equivocado por error de captura
-- Se deja en NULL hasta confirmar su número real con el taller
-- ============================================================
UPDATE clientes
SET telefono = NULL,
    telefono_normalizado = NULL
WHERE id = 57;

-- ============================================================
-- FIX 2: NIV "NO APLICA" → NULL en vehículos
-- El staff olvidó anotar el número de serie y escribió "NO APLICA" como texto
-- Se normaliza a NULL para no contaminar búsquedas por NIV
-- Afecta vehículos ids: 44, 61, 60, 62, 63
-- ============================================================
UPDATE vehiculos
SET niv = NULL
WHERE niv = 'NO APLICA';

-- ============================================================
-- CASOS NO MERGEADOS — documentados para referencia
-- ============================================================

-- Teléfono 5614058377: RAMON PRADO (id 65) y MIGUEL PEREZ (ids 114, 119)
-- Son personas distintas. Ramón es el patrón de Miguel, ambos traen carros.
-- Comparten teléfono intencionalmente. NO MERGEAR.

-- Teléfono 5522591605: MADDIE HERNANDEZ (id 66) y Miguel Hernández (id 77)
-- Son hermanos. Comparten teléfono. Mismo caso que Ramón/Miguel. NO MERGEAR.

-- ============================================================
-- VERIFICACIÓN POST-FIX
-- ============================================================
-- SELECT id, nombre, telefono, telefono_normalizado
-- FROM clientes WHERE id = 57;
-- Esperado: telefono = NULL, telefono_normalizado = NULL

-- SELECT id, niv, placas, marca, modelo
-- FROM vehiculos WHERE niv IS NULL AND placas IS NOT NULL
-- ORDER BY id;
-- Los ids 44,60,61,62,63 deben aparecer con niv = NULL
