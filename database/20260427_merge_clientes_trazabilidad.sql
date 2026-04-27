-- Migración: Preparar schema para merge de clientes duplicados
-- Fecha: 2026-04-27
-- Segura: solo agrega columnas nullable, no modifica existentes ni rompe el sistema actual

ALTER TABLE clientes
  ADD COLUMN activo            TINYINT(1)   NOT NULL DEFAULT 1,
  ADD COLUMN fusionado_en      INT          NULL,
  ADD COLUMN fusionado_at      TIMESTAMP    NULL,
  ADD COLUMN notas_merge       VARCHAR(500) NULL;

ALTER TABLE clientes ADD INDEX idx_fusionado_en (fusionado_en);
ALTER TABLE clientes ADD INDEX idx_activo (activo);

ALTER TABLE clientes
  ADD COLUMN telefono_normalizado VARCHAR(15) NULL;

-- Poblar telefono_normalizado con los datos existentes
UPDATE clientes
SET telefono_normalizado = REGEXP_REPLACE(
  REGEXP_REPLACE(IFNULL(telefono,''), '[^0-9]', ''),
  '^52', ''
)
WHERE telefono IS NOT NULL AND telefono != '';

ALTER TABLE clientes ADD INDEX idx_telefono_normalizado (telefono_normalizado);
