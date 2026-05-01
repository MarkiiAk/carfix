-- Migración: Gastos internos por orden
-- Fecha: 2026-04-30
-- Descripción: Crea tabla gastos_orden para registrar costos internos
--              (envío, consumibles, propinas, etc.) asociados a una orden.
--              Agrega columna costo_interno_total a ordenes_servicio para
--              un acceso rápido al total sin subquery.
--
-- ORDEN DE EJECUCIÓN:
-- 1. Ejecutar este script en BD de staging primero y validar.
-- 2. Aplicar en producción en horario de baja actividad.
-- 3. No requiere limpieza previa (tabla nueva + columna nullable con default).

-- ─────────────────────────────────────────────
-- 1. Tabla de gastos internos por orden
-- ─────────────────────────────────────────────
CREATE TABLE gastos_orden (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  orden_id        INT NOT NULL,
  concepto        VARCHAR(300) NOT NULL,
  monto           DECIMAL(10,2) NOT NULL,
  tipo            ENUM('envio','consumible','propina','otro') NOT NULL DEFAULT 'otro',
  registrado_por  INT NOT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gastos_orden_orden   FOREIGN KEY (orden_id)       REFERENCES ordenes_servicio(id) ON DELETE CASCADE,
  CONSTRAINT fk_gastos_orden_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
  INDEX idx_orden_id   (orden_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- 2. Columna de total en ordenes_servicio
--    DEFAULT 0.00 para no romper filas existentes.
-- ─────────────────────────────────────────────
ALTER TABLE ordenes_servicio
  ADD COLUMN costo_interno_total DECIMAL(10,2) NOT NULL DEFAULT 0.00;
