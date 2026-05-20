-- ============================================================================
-- SCRIPT DE REPORTE MENSUAL - INGRESOS, EGRESOS Y BALANCE (MYSQL)
-- Sistema de Gestión de Presupuestos - SAG Garage
-- ============================================================================
-- 
-- OPTIMIZADO PARA MySQL/MariaDB
-- Este script genera un reporte mensual que muestra:
-- - INGRESOS: Lo que se cobra a los clientes (órdenes completadas/entregadas)
-- - EGRESOS: Costo de materiales/refacciones compradas
-- - BALANCE: Ganancia real (ingresos - egresos)
-- ============================================================================

-- Crear la vista principal (eliminar primero si existe)
DROP VIEW IF EXISTS vista_reporte_mensual;

CREATE VIEW vista_reporte_mensual AS
SELECT 
    YEAR(fecha_periodo) as anio,
    MONTH(fecha_periodo) as mes,
    DATE_FORMAT(fecha_periodo, '%Y-%m') as periodo,
    MONTHNAME(fecha_periodo) as nombre_mes,
    
    -- INGRESOS (lo que cobramos)
    IFNULL(ingresos_totales, 0.00) as ingresos,
    IFNULL(ingresos_mano_obra, 0.00) as ingresos_mano_obra,
    IFNULL(ingresos_servicios, 0.00) as ingresos_servicios, 
    IFNULL(ingresos_refacciones, 0.00) as ingresos_refacciones,
    IFNULL(total_iva_cobrado, 0.00) as iva_cobrado,
    IFNULL(ordenes_completadas, 0) as ordenes_completadas,
    
    -- EGRESOS (lo que gastamos en material)
    IFNULL(egresos_refacciones, 0.00) as egresos,
    
    -- BALANCE Y RENTABILIDAD
    (IFNULL(ingresos_totales, 0.00) - IFNULL(egresos_refacciones, 0.00)) as balance_bruto,
    
    -- Margen de ganancia en refacciones (asumiendo margen del 30% sobre costo)
    IFNULL(egresos_refacciones, 0.00) * 0.30 as margen_refacciones,
    
    -- BALANCE REAL (considerando que en el precio de refacciones ya hay ganancia)
    (IFNULL(ingresos_mano_obra, 0.00) + IFNULL(ingresos_servicios, 0.00) + 
     (IFNULL(ingresos_refacciones, 0.00) - IFNULL(egresos_refacciones, 0.00))) as ganancia_neta,
    
    -- Porcentaje de rentabilidad
    CASE 
        WHEN IFNULL(egresos_refacciones, 0.00) > 0 THEN
            ROUND(((IFNULL(ingresos_totales, 0.00) - IFNULL(egresos_refacciones, 0.00)) / 
                   IFNULL(egresos_refacciones, 0.00)) * 100, 2)
        ELSE 100.00
    END as porcentaje_rentabilidad

FROM (
    -- Generamos todos los meses desde 2025 hasta ahora
    SELECT DATE_ADD('2025-01-01', INTERVAL n MONTH) as fecha_periodo
    FROM (
        SELECT a.N + b.N * 10 + c.N * 100 as n
        FROM (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
        CROSS JOIN (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
        CROSS JOIN (SELECT 0 as N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) c
    ) numbers
    WHERE DATE_ADD('2025-01-01', INTERVAL n MONTH) <= CURDATE()
) periodos

LEFT JOIN (
    -- INGRESOS: Órdenes cerradas agrupadas por mes usando fecha_ingreso
    SELECT 
        DATE_FORMAT(fecha_ingreso, '%Y-%m-01') as periodo_ingreso,
        COUNT(*) as ordenes_completadas,
        SUM(total) as ingresos_totales,
        SUM(subtotal_mano_obra) as ingresos_mano_obra,
        SUM(subtotal_servicios) as ingresos_servicios,
        SUM(subtotal_refacciones) as ingresos_refacciones,
        SUM(iva) as total_iva_cobrado
    FROM ordenes_servicio 
    WHERE estado = 'cerrada'
        AND fecha_ingreso IS NOT NULL
        AND DATE_FORMAT(fecha_ingreso, '%Y-%m') >= '2025-01'
    GROUP BY DATE_FORMAT(fecha_ingreso, '%Y-%m')
) ingresos ON periodos.fecha_periodo = ingresos.periodo_ingreso

LEFT JOIN (
    -- EGRESOS: Costo real de refacciones usando fecha_ingreso de la orden
    SELECT 
        DATE_FORMAT(o.fecha_ingreso, '%Y-%m-01') as periodo_egreso,
        SUM(r.precio_unitario * r.cantidad) as egresos_refacciones
    FROM refacciones_orden r
    INNER JOIN ordenes_servicio o ON r.orden_id = o.id
    WHERE o.estado = 'cerrada'
        AND o.fecha_ingreso IS NOT NULL
        AND DATE_FORMAT(o.fecha_ingreso, '%Y-%m') >= '2025-01'
    GROUP BY DATE_FORMAT(o.fecha_ingreso, '%Y-%m')
) egresos ON periodos.fecha_periodo = egresos.periodo_egreso

WHERE YEAR(fecha_periodo) >= 2025
ORDER BY anio DESC, mes DESC;

-- ============================================================================
-- PROCEDIMIENTOS ALMACENADOS (Compatibles con MySQL)
-- ============================================================================

-- Eliminar procedimientos existentes
DROP PROCEDURE IF EXISTS sp_reporte_anual;
DROP PROCEDURE IF EXISTS sp_reporte_mensual;

DELIMITER //

-- Procedimiento para reporte anual completo
CREATE PROCEDURE sp_reporte_anual(IN p_anio INT)
BEGIN
    SELECT 
        '=== REPORTE ANUAL ===' as titulo,
        p_anio as anio;
        
    SELECT 
        periodo,
        nombre_mes,
        FORMAT(ingresos, 2) as ingresos_formatted,
        FORMAT(egresos, 2) as egresos_formatted, 
        FORMAT(ganancia_neta, 2) as ganancia_formatted,
        CONCAT(porcentaje_rentabilidad, '%') as rentabilidad,
        ordenes_completadas
    FROM vista_reporte_mensual
    WHERE anio = p_anio
    ORDER BY mes;
    
    -- Resumen anual
    SELECT 
        '=== RESUMEN ANUAL ===' as resumen,
        FORMAT(SUM(ingresos), 2) as total_ingresos,
        FORMAT(SUM(egresos), 2) as total_egresos,
        FORMAT(SUM(ganancia_neta), 2) as ganancia_total,
        FORMAT(AVG(porcentaje_rentabilidad), 2) as rentabilidad_promedio,
        SUM(ordenes_completadas) as total_ordenes
    FROM vista_reporte_mensual
    WHERE anio = p_anio;
END //

-- Procedimiento para reporte mensual específico
CREATE PROCEDURE sp_reporte_mensual(IN p_anio INT, IN p_mes INT)
BEGIN
    DECLARE v_nombre_mes VARCHAR(20);
    
    SELECT MONTHNAME(CONCAT(p_anio, '-', LPAD(p_mes, 2, '0'), '-01')) INTO v_nombre_mes;
    
    SELECT 
        CONCAT('=== REPORTE MENSUAL: ', v_nombre_mes, ' ', p_anio, ' ===') as titulo;
    
    -- Resumen del mes
    SELECT 
        periodo,
        nombre_mes,
        FORMAT(ingresos, 2) as 'Ingresos Totales',
        FORMAT(ingresos_mano_obra, 2) as 'Mano de Obra',
        FORMAT(ingresos_servicios, 2) as 'Servicios',
        FORMAT(ingresos_refacciones, 2) as 'Refacciones Cobradas',
        FORMAT(egresos, 2) as 'Costo Refacciones',
        FORMAT(ganancia_neta, 2) as 'Ganancia Neta',
        CONCAT(porcentaje_rentabilidad, '%') as 'Rentabilidad',
        ordenes_completadas as 'Órdenes Completadas'
    FROM vista_reporte_mensual
    WHERE anio = p_anio AND mes = p_mes;
    
    -- Detalle de órdenes del mes
    SELECT 
        '=== DETALLE DE ÓRDENES ===' as detalle;
        
    SELECT 
        o.numero_orden as 'No. Orden',
        c.nombre as Cliente,
        CONCAT(v.marca, ' ', v.modelo, ' ', IFNULL(v.anio, '')) as Vehiculo,
        DATE_FORMAT(o.fecha_ingreso, '%d/%m/%Y') as 'Fecha',
        FORMAT(o.total, 2) as 'Total Cobrado',
        FORMAT(IFNULL(ref_total.costo_refacciones, 0), 2) as 'Costo Refacciones',
        FORMAT(o.total - IFNULL(ref_total.costo_refacciones, 0), 2) as 'Ganancia'
    FROM ordenes_servicio o
    INNER JOIN clientes c ON o.cliente_id = c.id
    INNER JOIN vehiculos v ON o.vehiculo_id = v.id
    LEFT JOIN (
        SELECT 
            orden_id,
            SUM(precio_unitario * cantidad) as costo_refacciones
        FROM refacciones_orden
        GROUP BY orden_id
    ) ref_total ON o.id = ref_total.orden_id
    WHERE o.estado = 'cerrada'
        AND o.fecha_ingreso IS NOT NULL
        AND YEAR(o.fecha_ingreso) = p_anio
        AND MONTH(o.fecha_ingreso) = p_mes
    ORDER BY o.fecha_ingreso;
END //

DELIMITER ;

-- ============================================================================
-- VISTAS ADICIONALES PARA ANÁLISIS
-- ============================================================================

-- Eliminar vistas existentes
DROP VIEW IF EXISTS vista_top_clientes_anual;
DROP VIEW IF EXISTS vista_rentabilidad_servicios;

-- Top 10 clientes por facturación (año actual)
CREATE VIEW vista_top_clientes_anual AS
SELECT 
    c.id,
    c.nombre as cliente,
    c.telefono,
    COUNT(DISTINCT o.id) as ordenes_completadas,
    FORMAT(SUM(o.total), 2) as facturación_total,
    FORMAT(AVG(o.total), 2) as promedio_por_orden,
    DATE_FORMAT(MAX(IFNULL(o.fecha_completada, o.fecha_entregada)), '%d/%m/%Y') as ultima_visita
FROM clientes c
INNER JOIN ordenes_servicio o ON c.id = o.cliente_id
WHERE o.estado IN ('completada', 'entregada')
    AND YEAR(IFNULL(o.fecha_completada, o.fecha_entregada)) = YEAR(CURDATE())
GROUP BY c.id, c.nombre, c.telefono
ORDER BY SUM(o.total) DESC
LIMIT 10;

-- Análisis de rentabilidad por tipo de servicio
CREATE VIEW vista_rentabilidad_servicios AS
SELECT 
    CASE 
        WHEN so.tipo = 'mano_obra' THEN 'Mano de Obra'
        ELSE 'Servicios'
    END as tipo_servicio,
    so.descripcion,
    COUNT(*) as veces_realizado,
    FORMAT(AVG(so.precio_unitario), 2) as precio_promedio,
    FORMAT(SUM(so.subtotal), 2) as ingresos_totales
FROM servicios_orden so
INNER JOIN ordenes_servicio o ON so.orden_id = o.id
WHERE o.estado IN ('completada', 'entregada')
    AND YEAR(IFNULL(o.fecha_completada, o.fecha_entregada)) = YEAR(CURDATE())
GROUP BY so.tipo, so.descripcion
ORDER BY SUM(so.subtotal) DESC;

-- ============================================================================
-- CONSULTAS DE EJEMPLO Y USO
-- ============================================================================

/*
EJEMPLOS DE USO:

1. Reporte del mes actual:
   CALL sp_reporte_mensual(YEAR(CURDATE()), MONTH(CURDATE()));

2. Reporte de marzo 2026:
   CALL sp_reporte_mensual(2026, 3);

3. Reporte completo del año 2026:
   CALL sp_reporte_anual(2026);

4. Vista rápida últimos 6 meses:
   SELECT * FROM vista_reporte_mensual 
   WHERE fecha_periodo >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
   ORDER BY periodo DESC;

5. Ver top clientes del año:
   SELECT * FROM vista_top_clientes_anual;

6. Análisis de servicios más rentables:
   SELECT * FROM vista_rentabilidad_servicios;

7. Consulta simple para mes específico:
   SELECT 
       nombre_mes,
       FORMAT(ingresos, 2) as 'Ingresos',
       FORMAT(egresos, 2) as 'Egresos', 
       FORMAT(ganancia_neta, 2) as 'Ganancia',
       CONCAT(porcentaje_rentabilidad, '%') as 'Rentabilidad'
   FROM vista_reporte_mensual 
   WHERE anio = 2026 AND mes = 3;
*/

-- ============================================================================
-- NOTAS IMPORTANTES PARA MySQL
-- ============================================================================

/*
DIFERENCIAS CON OTRAS VERSIONES DE SQL:
- Usa IFNULL() en lugar de COALESCE() para mejor compatibilidad
- Evita CREATE OR REPLACE VIEW - usa DROP VIEW IF EXISTS
- Genera secuencia de números con CROSS JOIN para compatibilidad
- Procedimientos optimizados para sintaxis MySQL/MariaDB

REGLAS DE NEGOCIO:
- Margen del 30% sobre costo de refacciones (ajustable en línea 35)
- Solo considera órdenes 'completada' o 'entregada'
- Usa fecha_completada prioritariamente, fecha_entregada como respaldo
- Ganancia neta = mano_obra + servicios + (precio_refacciones - costo_refacciones)
*/