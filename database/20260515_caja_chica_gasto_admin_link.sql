-- Migración 2026-05-15
-- Vincula egresos de caja_chica con gastos_administrativos
-- para que el P&L refleje automáticamente los gastos de caja chica.
-- Solo aplica a egresos; los ingresos no tocan el P&L.

ALTER TABLE `caja_chica`
  ADD COLUMN `gasto_admin_id` INT(11) NULL DEFAULT NULL AFTER `notas`,
  ADD CONSTRAINT `fk_caja_chica_gasto_admin`
    FOREIGN KEY (`gasto_admin_id`) REFERENCES `gastos_administrativos` (`id`)
    ON DELETE SET NULL;
