-- Migración: Nuevo rol 'asistente'
-- Sin acceso al módulo financiero, limitado a su sucursal asignada.
-- Aplica a: CarFix

-- 1. Agregar 'asistente' al ENUM de roles en usuarios
ALTER TABLE `usuarios`
  MODIFY COLUMN `rol` ENUM('sistemas','superusuario','admin_sucursal','asistente')
    NOT NULL DEFAULT 'admin_sucursal';

-- 2. Agregar 'asistente' al ENUM de rol_sucursal en usuario_sucursales
ALTER TABLE `usuario_sucursales`
  MODIFY COLUMN `rol_sucursal` ENUM('admin_sucursal','asistente')
    NOT NULL DEFAULT 'admin_sucursal';
