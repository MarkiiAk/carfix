-- Merge Categoría A — 19 grupos seguros
-- Fecha: 2026-04-27
-- Ejecutar en staging primero, luego en producción
-- Cada grupo es una transacción independiente
-- Si una falla, las demás siguen siendo válidas

-- ============================================================
-- GRUPO 1: URRIETA LOGISTICS (12 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 34 WHERE cliente_id IN (45,51,64,78,81,83,90,100,110,124,145);
UPDATE vehiculos SET cliente_id = 34 WHERE cliente_id IN (45,51,64,78,81,83,90,100,110,124,145);
UPDATE alertas_servicio SET cliente_id = 34 WHERE cliente_id IN (45,51,64,78,81,83,90,100,110,124,145);
UPDATE clientes SET activo = 0, fusionado_en = 34, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (45,51,64,78,81,83,90,100,110,124,145);
UPDATE clientes SET nombre = 'URRIETA LOGISTICS' WHERE id = 34;
COMMIT;

-- ============================================================
-- GRUPO 2: KENYA SOMMERS (4 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 99 WHERE cliente_id IN (104,105,107);
UPDATE vehiculos SET cliente_id = 99 WHERE cliente_id IN (104,105,107);
UPDATE alertas_servicio SET cliente_id = 99 WHERE cliente_id IN (104,105,107);
UPDATE clientes SET activo = 0, fusionado_en = 99, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (104,105,107);
UPDATE clientes SET nombre = 'KENYA SOMMERS' WHERE id = 99;
COMMIT;

-- ============================================================
-- GRUPO 3: CARLOS ANGELES QUINTERO (3 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 71 WHERE cliente_id IN (113,132);
UPDATE vehiculos SET cliente_id = 71 WHERE cliente_id IN (113,132);
UPDATE alertas_servicio SET cliente_id = 71 WHERE cliente_id IN (113,132);
UPDATE clientes SET activo = 0, fusionado_en = 71, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (113,132);
COMMIT;

-- ============================================================
-- GRUPO 4: UMA ANGUIANO (3 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 72 WHERE cliente_id IN (131,146);
UPDATE vehiculos SET cliente_id = 72 WHERE cliente_id IN (131,146);
UPDATE alertas_servicio SET cliente_id = 72 WHERE cliente_id IN (131,146);
UPDATE clientes SET activo = 0, fusionado_en = 72, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (131,146);
UPDATE clientes SET nombre = 'UMA ANGUIANO' WHERE id = 72;
COMMIT;

-- ============================================================
-- GRUPO 5: DORIAN RICO (3 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 76 WHERE cliente_id IN (101,123);
UPDATE vehiculos SET cliente_id = 76 WHERE cliente_id IN (101,123);
UPDATE alertas_servicio SET cliente_id = 76 WHERE cliente_id IN (101,123);
UPDATE clientes SET activo = 0, fusionado_en = 76, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (101,123);
UPDATE clientes SET nombre = 'DORIAN RICO' WHERE id = 76;
COMMIT;

-- ============================================================
-- GRUPO 6: ILSE FLORES (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 54 WHERE cliente_id IN (137);
UPDATE vehiculos SET cliente_id = 54 WHERE cliente_id IN (137);
UPDATE alertas_servicio SET cliente_id = 54 WHERE cliente_id IN (137);
UPDATE clientes SET activo = 0, fusionado_en = 54, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (137);
UPDATE clientes SET nombre = 'ILSE FLORES' WHERE id = 54;
COMMIT;

-- ============================================================
-- GRUPO 7: CAPITAN MALDONADO (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 82 WHERE cliente_id IN (86);
UPDATE vehiculos SET cliente_id = 82 WHERE cliente_id IN (86);
UPDATE alertas_servicio SET cliente_id = 82 WHERE cliente_id IN (86);
UPDATE clientes SET activo = 0, fusionado_en = 82, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (86);
UPDATE clientes SET nombre = 'CAPITAN MALDONADO' WHERE id = 82;
COMMIT;

-- ============================================================
-- GRUPO 8: RAUL BARRIOS (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 56 WHERE cliente_id IN (97);
UPDATE vehiculos SET cliente_id = 56 WHERE cliente_id IN (97);
UPDATE alertas_servicio SET cliente_id = 56 WHERE cliente_id IN (97);
UPDATE clientes SET activo = 0, fusionado_en = 56, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (97);
COMMIT;

-- ============================================================
-- GRUPO 9: RODRIGO SALVIO (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 75 WHERE cliente_id IN (103);
UPDATE vehiculos SET cliente_id = 75 WHERE cliente_id IN (103);
UPDATE alertas_servicio SET cliente_id = 75 WHERE cliente_id IN (103);
UPDATE clientes SET activo = 0, fusionado_en = 75, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (103);
COMMIT;

-- ============================================================
-- GRUPO 10: DULCE RAZO (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 125 WHERE cliente_id IN (133);
UPDATE vehiculos SET cliente_id = 125 WHERE cliente_id IN (133);
UPDATE alertas_servicio SET cliente_id = 125 WHERE cliente_id IN (133);
UPDATE clientes SET activo = 0, fusionado_en = 125, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (133);
COMMIT;

-- ============================================================
-- GRUPO 11: OSCAR SOTOMAYOR (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 53 WHERE cliente_id IN (58);
UPDATE vehiculos SET cliente_id = 53 WHERE cliente_id IN (58);
UPDATE alertas_servicio SET cliente_id = 53 WHERE cliente_id IN (58);
UPDATE clientes SET activo = 0, fusionado_en = 53, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (58);
COMMIT;

-- ============================================================
-- GRUPO 12: EMANUEL FABELA (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 52 WHERE cliente_id IN (55);
UPDATE vehiculos SET cliente_id = 52 WHERE cliente_id IN (55);
UPDATE alertas_servicio SET cliente_id = 52 WHERE cliente_id IN (55);
UPDATE clientes SET activo = 0, fusionado_en = 52, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (55);
COMMIT;

-- ============================================================
-- GRUPO 13: EDITH MOSQUEDA (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 62 WHERE cliente_id IN (93);
UPDATE vehiculos SET cliente_id = 62 WHERE cliente_id IN (93);
UPDATE alertas_servicio SET cliente_id = 62 WHERE cliente_id IN (93);
UPDATE clientes SET activo = 0, fusionado_en = 62, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (93);
UPDATE clientes SET nombre = 'EDITH MOSQUEDA' WHERE id = 62;
COMMIT;

-- ============================================================
-- GRUPO 14: ANTONIO HERNANDEZ MELCHOR (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 129 WHERE cliente_id IN (147);
UPDATE vehiculos SET cliente_id = 129 WHERE cliente_id IN (147);
UPDATE alertas_servicio SET cliente_id = 129 WHERE cliente_id IN (147);
UPDATE clientes SET activo = 0, fusionado_en = 129, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (147);
COMMIT;

-- ============================================================
-- GRUPO 15: FERNANDO DIAZ (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 111 WHERE cliente_id IN (117);
UPDATE vehiculos SET cliente_id = 111 WHERE cliente_id IN (117);
UPDATE alertas_servicio SET cliente_id = 111 WHERE cliente_id IN (117);
UPDATE clientes SET activo = 0, fusionado_en = 111, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (117);
COMMIT;

-- ============================================================
-- GRUPO 16: BRYAN ZEPEDA (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 49 WHERE cliente_id IN (89);
UPDATE vehiculos SET cliente_id = 49 WHERE cliente_id IN (89);
UPDATE alertas_servicio SET cliente_id = 49 WHERE cliente_id IN (89);
UPDATE clientes SET activo = 0, fusionado_en = 49, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (89);
COMMIT;

-- ============================================================
-- GRUPO 17: JORGE IGNACIO GARCIA FIGUEROA (2 → 1)
-- Nota: id 84 tiene nombre más completo — se actualiza en el destino
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 68 WHERE cliente_id IN (84);
UPDATE vehiculos SET cliente_id = 68 WHERE cliente_id IN (84);
UPDATE alertas_servicio SET cliente_id = 68 WHERE cliente_id IN (84);
UPDATE clientes SET activo = 0, fusionado_en = 68, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (84);
UPDATE clientes SET nombre = 'JORGE IGNACIO GARCIA FIGUEROA' WHERE id = 68;
COMMIT;

-- ============================================================
-- GRUPO 18: ENRIQUE ESCUDERO (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 87 WHERE cliente_id IN (118);
UPDATE vehiculos SET cliente_id = 87 WHERE cliente_id IN (118);
UPDATE alertas_servicio SET cliente_id = 87 WHERE cliente_id IN (118);
UPDATE clientes SET activo = 0, fusionado_en = 87, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (118);
UPDATE clientes SET nombre = 'ENRIQUE ESCUDERO' WHERE id = 87;
COMMIT;

-- ============================================================
-- GRUPO 19: RICARDO HUERTA (2 → 1)
-- ============================================================
START TRANSACTION;
UPDATE ordenes_servicio SET cliente_id = 46 WHERE cliente_id IN (47);
UPDATE vehiculos SET cliente_id = 46 WHERE cliente_id IN (47);
UPDATE alertas_servicio SET cliente_id = 46 WHERE cliente_id IN (47);
UPDATE clientes SET activo = 0, fusionado_en = 46, fusionado_at = NOW(), notas_merge = 'Merge Categoria A 2026-04-27' WHERE id IN (47);
UPDATE clientes SET nombre = 'RICARDO HUERTA' WHERE id = 46;
COMMIT;

-- ============================================================
-- VERIFICACIÓN POST-MERGE
-- Corre esto después para confirmar que no quedaron registros huérfanos
-- ============================================================
-- SELECT COUNT(*) as registros_activos FROM clientes WHERE activo = 1;
-- SELECT COUNT(*) as registros_fusionados FROM clientes WHERE activo = 0;
-- SELECT COUNT(*) as ordenes_sin_cliente FROM ordenes_servicio o
--   LEFT JOIN clientes c ON c.id = o.cliente_id
--   WHERE c.id IS NULL OR c.activo = 0;
