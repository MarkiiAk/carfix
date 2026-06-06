-- =============================================================================
-- Migración: Multi-sucursal — SAG Garage
-- Fecha: 2026-06-02
-- Descripción: Tablas sucursales + usuario_sucursales, nuevos roles, sucursal_id
--              en 12 tablas operativas. Script IDEMPOTENTE — seguro de ejecutar
--              más de una vez sin romper datos.
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- ============================================================
-- 1. TABLA sucursales
-- ============================================================
CREATE TABLE IF NOT EXISTS `sucursales` (
  `id`          int(11)      NOT NULL AUTO_INCREMENT,
  `nombre`      varchar(200) NOT NULL,
  `direccion`   text         DEFAULT NULL,
  `telefono`    varchar(20)  DEFAULT NULL,
  `activo`      tinyint(1)   NOT NULL DEFAULT 1,
  `created_at`  timestamp    NULL DEFAULT current_timestamp(),
  `updated_at`  timestamp    NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Catálogo de sucursales del taller';

-- ============================================================
-- 2. TABLA usuario_sucursales
-- ============================================================
CREATE TABLE IF NOT EXISTS `usuario_sucursales` (
  `id`           int(11)     NOT NULL AUTO_INCREMENT,
  `usuario_id`   int(11)     NOT NULL,
  `sucursal_id`  int(11)     NOT NULL,
  `rol_sucursal` varchar(50) DEFAULT 'admin_sucursal',
  `created_at`   timestamp   NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_sucursal` (`usuario_id`, `sucursal_id`),
  KEY `idx_usuario`  (`usuario_id`),
  KEY `idx_sucursal` (`sucursal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Relación N:M entre usuarios y sucursales con rol por sucursal';

-- ============================================================
-- 3. Modificar ENUM de rol en usuarios
--    MODIFY COLUMN es idempotente si el tipo ya existe.
-- ============================================================
ALTER TABLE `usuarios`
  MODIFY COLUMN `rol` ENUM('sistemas','superusuario','admin_sucursal','admin','tecnico','recepcionista')
  DEFAULT 'admin_sucursal';

-- ============================================================
-- 4. Agregar sucursal_id a las 12 tablas operativas
--    ADD COLUMN IF NOT EXISTS (MariaDB 10.3+ / MySQL 8.0+)
-- ============================================================

-- ordenes_servicio
ALTER TABLE `ordenes_servicio`
  ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1 AFTER `usuario_id`;
ALTER TABLE `ordenes_servicio`
  ADD KEY IF NOT EXISTS `idx_sucursal` (`sucursal_id`);

-- alertas_servicio
ALTER TABLE `alertas_servicio`
  ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1 AFTER `orden_id`;
ALTER TABLE `alertas_servicio`
  ADD KEY IF NOT EXISTS `idx_sucursal` (`sucursal_id`);

-- clientes
ALTER TABLE `clientes`
  ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1 AFTER `id`;
ALTER TABLE `clientes`
  ADD KEY IF NOT EXISTS `idx_sucursal` (`sucursal_id`);

-- vehiculos
ALTER TABLE `vehiculos`
  ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1 AFTER `cliente_id`;
ALTER TABLE `vehiculos`
  ADD KEY IF NOT EXISTS `idx_sucursal` (`sucursal_id`);

-- servicios_orden
ALTER TABLE `servicios_orden`
  ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1 AFTER `orden_id`;
ALTER TABLE `servicios_orden`
  ADD KEY IF NOT EXISTS `idx_sucursal` (`sucursal_id`);

-- refacciones_orden
ALTER TABLE `refacciones_orden`
  ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1 AFTER `orden_id`;
ALTER TABLE `refacciones_orden`
  ADD KEY IF NOT EXISTS `idx_sucursal` (`sucursal_id`);

-- gastos_orden (si existe)
SET @tbl = (SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos_orden');
SET @sql = IF(@tbl > 0,
  'ALTER TABLE `gastos_orden` ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- gastos_administrativos (si existe)
SET @tbl = (SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos_administrativos');
SET @sql = IF(@tbl > 0,
  'ALTER TABLE `gastos_administrativos` ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- empleados_sueldos (si existe)
SET @tbl = (SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleados_sueldos');
SET @sql = IF(@tbl > 0,
  'ALTER TABLE `empleados_sueldos` ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- pagos_fijos (si existe)
SET @tbl = (SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pagos_fijos');
SET @sql = IF(@tbl > 0,
  'ALTER TABLE `pagos_fijos` ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- caja_chica (si existe)
SET @tbl = (SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'caja_chica');
SET @sql = IF(@tbl > 0,
  'ALTER TABLE `caja_chica` ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- calendario_disponibilidad
ALTER TABLE `calendario_disponibilidad`
  ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1 AFTER `id`;
ALTER TABLE `calendario_disponibilidad`
  ADD KEY IF NOT EXISTS `idx_sucursal` (`sucursal_id`);

-- ============================================================
-- 5. Fix del UNIQUE constraint en alertas_ejecucion_log
--    El constraint solo-por-fecha impide multi-sucursal en el mismo día.
--    Se reemplaza por (fecha_ejecucion, sucursal_id).
-- ============================================================
ALTER TABLE `alertas_ejecucion_log`
  ADD COLUMN IF NOT EXISTS `sucursal_id` INT NOT NULL DEFAULT 1 AFTER `id`;

-- Eliminar UNIQUE anterior solo si existe
SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'alertas_ejecucion_log'
              AND INDEX_NAME   = 'unique_daily_execution');
SET @sql = IF(@idx > 0,
  'ALTER TABLE `alertas_ejecucion_log` DROP INDEX `unique_daily_execution`',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Agregar UNIQUE compuesto solo si no existe
SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'alertas_ejecucion_log'
              AND INDEX_NAME   = 'unique_daily_sucursal');
SET @sql = IF(@idx = 0,
  'ALTER TABLE `alertas_ejecucion_log` ADD UNIQUE KEY `unique_daily_sucursal` (`fecha_ejecucion`, `sucursal_id`)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ============================================================
-- 6. Insertar la sucursal Matriz (id=1) si no existe
-- ============================================================
INSERT IGNORE INTO `sucursales` (`id`, `nombre`, `direccion`, `telefono`, `activo`)
VALUES (1, 'Matriz', NULL, NULL, 1);

-- ============================================================
-- 7. Migrar roles viejos → nuevos
--    admin → superusuario (dueño del taller)
-- ============================================================
UPDATE `usuarios` SET `rol` = 'superusuario' WHERE `rol` = 'admin';

-- ============================================================
-- 8. Registrar en usuario_sucursales todos los admin_sucursal
--    que ya existían y que no sean sistemas/superusuario
-- ============================================================
INSERT IGNORE INTO `usuario_sucursales` (`usuario_id`, `sucursal_id`, `rol_sucursal`)
SELECT `id`, 1, 'admin_sucursal'
FROM `usuarios`
WHERE `rol` NOT IN ('sistemas', 'superusuario')
  AND `activo` = 1;

-- =============================================================================
-- FIN DE MIGRACIÓN
-- =============================================================================
