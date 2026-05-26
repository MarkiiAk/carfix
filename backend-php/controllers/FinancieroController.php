<?php
/**
 * FinancieroController — Módulo de Ingresos
 * GET /api/financiero?tipo=semana|quincena|mes&offset=0
 *
 * offset=0 = período actual, offset=1 = anterior, etc.
 * Solo accesible para rol admin.
 */

class FinancieroController {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // -----------------------------------------------------------------------
    // GET /api/financiero
    // -----------------------------------------------------------------------
    public function resumen(): void {
        try {
            $userData = requireAuth();

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $tipo   = isset($_GET['tipo'])   ? trim($_GET['tipo'])   : 'mes';
            $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

            if (!in_array($tipo, ['semana', 'quincena', 'mes'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'tipo debe ser semana, quincena o mes']);
                return;
            }

            if ($offset < 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'offset no puede ser negativo']);
                return;
            }

            [$fechaInicio, $fechaFin, $label] = $this->calcularPeriodo($tipo, $offset);

            $resumen      = $this->queryResumen($fechaInicio, $fechaFin);
            $refacciones  = $this->queryRefacciones($fechaInicio, $fechaFin);
            $topServicios = $this->queryTopServicios($fechaInicio, $fechaFin);
            $topClientes  = $this->queryTopClientes($fechaInicio, $fechaFin);
            $porDia       = $this->queryPorDia($fechaInicio, $fechaFin);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'periodo' => [
                    'tipo'         => $tipo,
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin'    => $fechaFin,
                    'label'        => $label,
                ],
                'resumen'       => $resumen,
                'refacciones'   => $refacciones,
                'top_servicios' => $topServicios,
                'top_clientes'  => $topClientes,
                'por_dia'      => $porDia,
            ]);

        } catch (Exception $e) {
            error_log('[FinancieroController::resumen] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener datos financieros']);
        }
    }

    // -----------------------------------------------------------------------
    // GET /api/financiero/gastos-orden?orden_id=X
    // -----------------------------------------------------------------------
    public function gastosOrden(int $ordenId, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            if ($ordenId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'orden_id inválido']);
                return;
            }

            $sql = "
                SELECT
                    g.id,
                    g.concepto,
                    g.monto,
                    g.tipo,
                    COALESCE(u.nombre_completo, u.username) AS registrado_por_nombre,
                    g.created_at
                FROM gastos_orden g
                INNER JOIN usuarios u ON u.id = g.registrado_por
                WHERE g.orden_id = :orden_id
                ORDER BY g.created_at ASC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $gastos = array_map(function ($r) {
                return [
                    'id'                   => (int)   $r['id'],
                    'concepto'             => $r['concepto'],
                    'monto'                => (float) $r['monto'],
                    'tipo'                 => $r['tipo'],
                    'registrado_por_nombre'=> $r['registrado_por_nombre'],
                    'created_at'           => $r['created_at'],
                ];
            }, $rows);

            $totalGastos = array_sum(array_column($gastos, 'monto'));

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'gastos'  => $gastos,
                'total'   => round($totalGastos, 2),
            ]);

        } catch (Exception $e) {
            error_log('[FinancieroController::gastosOrden] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener gastos internos']);
        }
    }

    // -----------------------------------------------------------------------
    // POST /api/financiero/gastos-orden
    // Body: { orden_id, concepto, monto, tipo }
    // -----------------------------------------------------------------------
    public function crearGastoOrden(array $body, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            $ordenId  = isset($body['orden_id'])  ? (int)   $body['orden_id']  : 0;
            $concepto = isset($body['concepto'])  ? trim((string) $body['concepto']) : '';
            $monto    = isset($body['monto'])      ? (float) $body['monto']     : 0;
            $tipo     = isset($body['tipo'])       ? trim((string) $body['tipo'])    : '';

            // Validaciones
            if ($ordenId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'orden_id inválido']);
                return;
            }
            if ($concepto === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El concepto no puede estar vacío']);
                return;
            }
            if ($monto <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El monto debe ser mayor a 0']);
                return;
            }
            $tiposValidos = ['envio', 'consumible', 'propina', 'otro'];
            if (!in_array($tipo, $tiposValidos, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'tipo inválido']);
                return;
            }

            // Verificar que la orden existe
            $stmtCheck = $this->db->prepare('SELECT id FROM ordenes_servicio WHERE id = :id LIMIT 1');
            $stmtCheck->bindParam(':id', $ordenId, PDO::PARAM_INT);
            $stmtCheck->execute();
            if (!$stmtCheck->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Orden no encontrada']);
                return;
            }

            $registradoPor = (int) ($userData['userId'] ?? $userData['id'] ?? 0);

            $this->db->beginTransaction();

            // INSERT gasto
            $sqlInsert = "
                INSERT INTO gastos_orden (orden_id, concepto, monto, tipo, registrado_por)
                VALUES (:orden_id, :concepto, :monto, :tipo, :registrado_por)
            ";
            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->bindParam(':orden_id',       $ordenId,      PDO::PARAM_INT);
            $stmtInsert->bindParam(':concepto',        $concepto,     PDO::PARAM_STR);
            $stmtInsert->bindParam(':monto',           $monto);
            $stmtInsert->bindParam(':tipo',            $tipo,         PDO::PARAM_STR);
            $stmtInsert->bindParam(':registrado_por',  $registradoPor, PDO::PARAM_INT);
            $stmtInsert->execute();

            $nuevoId = (int) $this->db->lastInsertId();

            // Recalcular costo_interno_total
            $sqlUpdate = "
                UPDATE ordenes_servicio
                SET costo_interno_total = (
                    SELECT COALESCE(SUM(monto), 0)
                    FROM gastos_orden
                    WHERE orden_id = :orden_id
                )
                WHERE id = :id
            ";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':id',       $ordenId, PDO::PARAM_INT);
            $stmtUpdate->execute();

            $this->db->commit();

            // Obtener el nombre del usuario para devolver en la respuesta
            $stmtUser = $this->db->prepare('SELECT COALESCE(nombre_completo, username) AS nombre FROM usuarios WHERE id = :id LIMIT 1');
            $stmtUser->bindParam(':id', $registradoPor, PDO::PARAM_INT);
            $stmtUser->execute();
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $nombreUsuario = $usuario ? $usuario['nombre'] : '';

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'gasto'   => [
                    'id'                    => $nuevoId,
                    'concepto'              => $concepto,
                    'monto'                 => (float) $monto,
                    'tipo'                  => $tipo,
                    'registrado_por_nombre' => $nombreUsuario,
                    'created_at'            => date('Y-m-d H:i:s'),
                ],
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[FinancieroController::crearGastoOrden] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al registrar el gasto']);
        }
    }

    // -----------------------------------------------------------------------
    // DELETE /api/financiero/gastos-orden/:id
    // Solo admin puede eliminar.
    // -----------------------------------------------------------------------
    public function eliminarGastoOrden(int $id, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Solo el administrador puede eliminar costos internos']);
                return;
            }

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                return;
            }

            // Obtener orden_id antes de borrar (para recalcular total)
            $stmtGet = $this->db->prepare('SELECT orden_id FROM gastos_orden WHERE id = :id LIMIT 1');
            $stmtGet->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtGet->execute();
            $gasto = $stmtGet->fetch(PDO::FETCH_ASSOC);

            if (!$gasto) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Gasto no encontrado']);
                return;
            }

            $ordenId = (int) $gasto['orden_id'];

            $this->db->beginTransaction();

            // DELETE gasto
            $stmtDel = $this->db->prepare('DELETE FROM gastos_orden WHERE id = :id');
            $stmtDel->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtDel->execute();

            // Recalcular costo_interno_total del padre
            $sqlUpdate = "
                UPDATE ordenes_servicio
                SET costo_interno_total = (
                    SELECT COALESCE(SUM(monto), 0)
                    FROM gastos_orden
                    WHERE orden_id = :orden_id
                )
                WHERE id = :id
            ";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
            $stmtUpdate->bindParam(':id',       $ordenId, PDO::PARAM_INT);
            $stmtUpdate->execute();

            $this->db->commit();

            http_response_code(200);
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[FinancieroController::eliminarGastoOrden] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al eliminar el gasto']);
        }
    }

    // -----------------------------------------------------------------------
    // Cálculo de fechas según tipo + offset
    // -----------------------------------------------------------------------
    private function calcularPeriodo(string $tipo, int $offset): array {
        date_default_timezone_set('America/Mexico_City');
        $hoy = new DateTime();

        if ($tipo === 'semana') {
            // Lunes de la semana actual, luego retroceder $offset semanas
            $diaSemana = (int) $hoy->format('N'); // 1=Lun … 7=Dom
            $lunes = clone $hoy;
            $lunes->modify('-' . ($diaSemana - 1) . ' days');
            $lunes->modify('-' . $offset . ' weeks');

            $domingo = clone $lunes;
            $domingo->modify('+6 days');

            $fechaInicio = $lunes->format('Y-m-d');
            $fechaFin    = $domingo->format('Y-m-d');

            // Label: "Semana 15 abr – 21 abr 2026"
            $label = 'Semana ' . $this->labelFechaCorta($lunes) . ' – ' . $this->labelFechaCorta($domingo, true);

        } elseif ($tipo === 'quincena') {
            // Quincena: 1-15 o 16-fin, retroceder $offset quincenas
            $totalOffset = $offset;

            // Quincena base de hoy
            $dia = (int) $hoy->format('j');
            $mes = (int) $hoy->format('n');
            $anio = (int) $hoy->format('Y');

            // Codificar quincena actual como número secuencial: (año*12 + mes - 1)*2 + (1=primera, 2=segunda)
            $quincenaBase = ($anio * 12 + $mes - 1) * 2 + ($dia <= 15 ? 1 : 2);
            $quincenaTarget = $quincenaBase - $totalOffset;

            // Decodificar
            $esSegunda = ($quincenaTarget % 2 === 0);
            $mesIndex  = (int) floor(($quincenaTarget - 1) / 2); // 0-based mes
            $anioQ     = (int) floor($mesIndex / 12);
            $mesQ      = ($mesIndex % 12) + 1;

            if ($esSegunda) {
                $diaInicio   = 16;
                $ultimoDia   = (int) (new DateTime("{$anioQ}-{$mesQ}-01"))->format('t');
                $fechaInicio = sprintf('%04d-%02d-%02d', $anioQ, $mesQ, 16);
                $fechaFin    = sprintf('%04d-%02d-%02d', $anioQ, $mesQ, $ultimoDia);
                $label       = '2a quincena de ' . $this->nombreMes($mesQ) . ' ' . $anioQ;
            } else {
                $fechaInicio = sprintf('%04d-%02d-%02d', $anioQ, $mesQ, 1);
                $fechaFin    = sprintf('%04d-%02d-%02d', $anioQ, $mesQ, 15);
                $label       = '1a quincena de ' . $this->nombreMes($mesQ) . ' ' . $anioQ;
            }

        } else { // mes
            // Primer y último día del mes, retroceder $offset meses
            $base = clone $hoy;
            $base->modify('first day of this month');
            if ($offset > 0) {
                $base->modify('-' . $offset . ' months');
            }

            $ultimo = clone $base;
            $ultimo->modify('last day of this month');

            $fechaInicio = $base->format('Y-m-d');
            // Incluir todo el último día del mes (BETWEEN '2026-04-30' excluye entradas con hora > 00:00:00)
            $fechaFin    = $ultimo->format('Y-m-d') . ' 23:59:59';
            $label       = $this->nombreMes((int) $base->format('n')) . ' ' . $base->format('Y');
        }

        return [$fechaInicio, $fechaFin, $label];
    }

    // -----------------------------------------------------------------------
    // Helpers de formato de fechas
    // -----------------------------------------------------------------------
    private function nombreMes(int $mes): string {
        $meses = [
            1  => 'Enero',   2  => 'Febrero', 3  => 'Marzo',
            4  => 'Abril',   5  => 'Mayo',     6  => 'Junio',
            7  => 'Julio',   8  => 'Agosto',   9  => 'Septiembre',
            10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        return $meses[$mes] ?? '';
    }

    private function labelFechaCorta(DateTime $fecha, bool $conAnio = false): string {
        $mesesCortos = [
            1  => 'ene', 2  => 'feb', 3  => 'mar', 4  => 'abr',
            5  => 'may', 6  => 'jun', 7  => 'jul', 8  => 'ago',
            9  => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic',
        ];
        $dia = $fecha->format('j');
        $mes = $mesesCortos[(int) $fecha->format('n')];
        $anio = $fecha->format('Y');
        return $conAnio ? "{$dia} {$mes} {$anio}" : "{$dia} {$mes}";
    }

    // -----------------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------------
    private function queryResumen(string $fechaInicio, string $fechaFin): array {
        // UNION de 3 partes (modelo flujo de caja real):
        // A. Órdenes ABIERTAS       → fecha_ingreso,   ingreso = anticipo
        // B. Órdenes CERRADAS c/ant → fecha_ingreso,   ingreso = anticipo
        //    Usa id real (no NULL) + COUNT(DISTINCT) para no duplicar num_ordenes
        //    cuando la misma orden aparece en B (semana apertura) y C (semana cierre)
        // C. Órdenes CERRADAS       → fecha_entregada, ingreso = (total − anticipo) o total
        $sql = "
            SELECT
                COALESCE(SUM(q.total_facturado), 0)      AS total_facturado,
                COALESCE(SUM(q.ingresos_servicios), 0)   AS ingresos_servicios,
                COALESCE(SUM(q.ingresos_mano_obra), 0)   AS ingresos_mano_obra,
                COALESCE(SUM(q.ingresos_refacciones), 0) AS ingresos_refacciones,
                COALESCE(SUM(q.total_iva), 0)            AS total_iva,
                COUNT(DISTINCT q.id)                     AS num_ordenes
            FROM (
                -- Parte A: abiertas por fecha_ingreso, ingreso = anticipo
                SELECT id,
                    COALESCE(anticipo, 0)  AS total_facturado,
                    0 AS ingresos_servicios, 0 AS ingresos_mano_obra,
                    0 AS ingresos_refacciones, 0 AS total_iva
                FROM ordenes_servicio
                WHERE fecha_ingreso BETWEEN :fi_a AND :ff_a
                  AND estado NOT IN ('completado','completada','entregado','entregada','cerrada')

                UNION ALL

                -- Parte B: cerradas con anticipo, por fecha_ingreso — solo el anticipo recibido
                SELECT id,
                    COALESCE(anticipo, 0)  AS total_facturado,
                    0 AS ingresos_servicios, 0 AS ingresos_mano_obra,
                    0 AS ingresos_refacciones, 0 AS total_iva
                FROM ordenes_servicio
                WHERE fecha_ingreso BETWEEN :fi_b AND :ff_b
                  AND estado IN ('completado','completada','entregado','entregada','cerrada')
                  AND COALESCE(anticipo, 0) > 0

                UNION ALL

                -- Parte C: cerradas por fecha_entregada — solo el restante (total − anticipo)
                -- o el total completo si no hubo anticipo
                SELECT id,
                    CASE WHEN COALESCE(anticipo, 0) > 0
                         THEN (COALESCE(total, 0) - COALESCE(anticipo, 0))
                         ELSE COALESCE(total, 0)
                    END AS total_facturado,
                    COALESCE(subtotal_servicios, 0)   AS ingresos_servicios,
                    COALESCE(subtotal_mano_obra, 0)   AS ingresos_mano_obra,
                    COALESCE(subtotal_refacciones, 0) AS ingresos_refacciones,
                    COALESCE(iva, 0)                  AS total_iva
                FROM ordenes_servicio
                WHERE COALESCE(fecha_entregada, fecha_completada, fecha_ingreso) BETWEEN :fi_c AND :ff_c
                  AND estado IN ('completado','completada','entregado','entregada','cerrada')
            ) q
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fi_a', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':ff_a', $fechaFin,    PDO::PARAM_STR);
        $stmt->bindParam(':fi_b', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':ff_b', $fechaFin,    PDO::PARAM_STR);
        $stmt->bindParam(':fi_c', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':ff_c', $fechaFin,    PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_facturado'     => round((float) $row['total_facturado'], 2),
            'ingresos_servicios'  => round((float) $row['ingresos_servicios'], 2),
            'ingresos_mano_obra'  => round((float) $row['ingresos_mano_obra'], 2),
            'ingresos_refacciones'=> round((float) $row['ingresos_refacciones'], 2),
            'total_iva'           => round((float) $row['total_iva'], 2),
            'num_ordenes'         => (int)   $row['num_ordenes'],
        ];
    }

    private function queryRefacciones(string $fechaInicio, string $fechaFin): array {
        // precio_unitario = precioVenta. Para órdenes ≥ 2026-05-25, precio_costo está almacenado.
        // Para órdenes antiguas (precio_costo IS NULL) se estima como subtotal / 1.30.
        $sql = "
            SELECT
                COALESCE(SUM(r.subtotal), 0) AS vendido,
                COALESCE(SUM(
                    COALESCE(r.precio_costo * r.cantidad, r.subtotal / 1.30)
                ), 0) AS costo_estimado,
                COALESCE(SUM(
                    r.subtotal - COALESCE(r.precio_costo * r.cantidad, r.subtotal / 1.30)
                ), 0) AS margen_estimado,
                COUNT(r.id) AS num_items
            FROM refacciones_orden r
            INNER JOIN ordenes_servicio o ON r.orden_id = o.id
            WHERE o.fecha_ingreso BETWEEN :fi AND :ff
            -- Todas las órdenes por fecha_ingreso: la refacción se compra cuando entra el coche,
            -- independientemente de cuándo se cierra la orden (consistente con el modelo 3-partes)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fi', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':ff', $fechaFin,    PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $vendido = (float) $row['vendido'];
        $costo   = (float) $row['costo_estimado'];
        $margen  = (float) $row['margen_estimado'];

        return [
            'vendido'    => $vendido,
            'num_items'  => (int) $row['num_items'],
            'costo'      => round($costo, 2),
            'margen'     => round($margen, 2),
            'margen_pct' => 30.0, // siempre 30% — regla fija del negocio
        ];
    }

    private function queryTopServicios(string $fechaInicio, string $fechaFin): array {
        $sql = "
            SELECT
                s.descripcion,
                COUNT(s.id)       AS veces,
                SUM(s.subtotal)   AS total_generado
            FROM servicios_orden s
            INNER JOIN ordenes_servicio o ON s.orden_id = o.id
            WHERE o.estado IN ('completado','completada','entregado','entregada','cerrada')
              AND COALESCE(o.fecha_entregada, o.fecha_completada, o.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
              AND s.tipo != 'mano_obra'
            GROUP BY s.descripcion
            ORDER BY total_generado DESC
            LIMIT 5
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($r) {
            return [
                'descripcion'    => $r['descripcion'],
                'veces'          => (int)   $r['veces'],
                'total_generado' => (float) $r['total_generado'],
            ];
        }, $rows);
    }

    private function queryPorDia(string $fechaInicio, string $fechaFin): array {
        $sql = "
            SELECT
                DATE(COALESCE(o.fecha_completada, o.fecha_entregada, o.fecha_ingreso)) AS dia,
                COALESCE(SUM(o.total), 0)                                              AS total
            FROM ordenes_servicio o
            WHERE o.estado IN ('completado','completada','entregado','entregada','cerrada')
              AND COALESCE(o.fecha_entregada, o.fecha_completada, o.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
            GROUP BY DATE(COALESCE(o.fecha_entregada, o.fecha_completada, o.fecha_ingreso))
            ORDER BY dia ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($r) {
            return [
                'dia'   => $r['dia'],
                'total' => (float) $r['total'],
            ];
        }, $rows);
    }

    private function queryTopClientes(string $fechaInicio, string $fechaFin): array {
        $sql = "
            SELECT
                c.id,
                c.nombre,
                c.telefono,
                COUNT(o.id)            AS num_visitas,
                SUM(o.total)           AS total_gastado
            FROM ordenes_servicio o
            INNER JOIN clientes c ON c.id = o.cliente_id
            WHERE o.estado IN ('completado','completada','entregado','entregada','cerrada')
              AND c.activo = 1
              AND COALESCE(o.fecha_entregada, o.fecha_completada, o.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
            GROUP BY c.id
            ORDER BY total_gastado DESC
            LIMIT 5
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($r) {
            return [
                'id'           => (int)   $r['id'],
                'nombre'       => $r['nombre'],
                'telefono'     => $r['telefono'],
                'num_visitas'  => (int)   $r['num_visitas'],
                'total_gastado'=> round((float) $r['total_gastado'], 2),
            ];
        }, $rows);
    }

    // -----------------------------------------------------------------------
    // GET /api/financiero/gastos-admin?tipo=mes|semana&offset=0
    // Acepta también ?mes=X&anio=Y para retrocompatibilidad.
    // Solo admin. Retorna lista de gastos + balance del período.
    // -----------------------------------------------------------------------
    public function gastosAdmin(int $mes, int $anio, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            // Soporte dual: tipo+offset (nuevo) o mes+anio (retrocompatible)
            if (isset($_GET['tipo'])) {
                $tipo   = trim($_GET['tipo']);
                $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
                if (!in_array($tipo, ['semana', 'quincena', 'mes'], true)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'tipo debe ser semana, quincena o mes']);
                    return;
                }
                [$fechaInicio, $fechaFin, $labelPeriodo] = $this->calcularPeriodo($tipo, $offset);
            } else {
                if ($mes < 1 || $mes > 12) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'mes debe estar entre 1 y 12']);
                    return;
                }
                if ($anio < 2020 || $anio > 2030) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'anio fuera de rango permitido']);
                    return;
                }
                $fechaInicio  = sprintf('%04d-%02d-01', $anio, $mes);
                $fechaFin     = date('Y-m-t', strtotime($fechaInicio));
                $labelPeriodo = $this->nombreMes($mes) . ' ' . $anio;
            }

            // Lista de gastos administrativos del período
            $sqlGastos = "
                SELECT g.id, g.mes, g.anio, g.concepto, g.monto, g.categoria,
                       COALESCE(u.nombre_completo, u.username) AS registrado_por_nombre,
                       g.created_at
                FROM gastos_administrativos g
                INNER JOIN usuarios u ON u.id = g.registrado_por
                WHERE DATE(g.created_at) BETWEEN :fecha_inicio AND :fecha_fin
                ORDER BY g.created_at ASC
            ";
            $stmtGastos = $this->db->prepare($sqlGastos);
            $stmtGastos->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
            $stmtGastos->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
            $stmtGastos->execute();
            $rows = $stmtGastos->fetchAll(PDO::FETCH_ASSOC);

            $gastos = array_map(function ($r) {
                return [
                    'id'                    => (int)   $r['id'],
                    'mes'                   => (int)   $r['mes'],
                    'anio'                  => (int)   $r['anio'],
                    'concepto'              => $r['concepto'],
                    'monto'                 => (float) $r['monto'],
                    'categoria'             => $r['categoria'],
                    'registrado_por_nombre' => $r['registrado_por_nombre'],
                    'created_at'            => $r['created_at'],
                ];
            }, $rows);

            $totalAdmin = array_sum(array_column($gastos, 'monto'));

            // Ingresos del período usando rango de fechas (compatible con semanas)
            $sqlIngresos = "
                SELECT
                  COALESCE(SUM(o.total), 0)                        AS total_facturado,
                  COALESCE(SUM(o.iva), 0)                          AS total_iva,
                  COALESCE(SUM(o.subtotal_servicios), 0)           AS ingresos_servicios,
                  COALESCE(SUM(o.subtotal_mano_obra), 0)           AS ingresos_mano_obra,
                  COALESCE(SUM(o.subtotal_refacciones), 0)         AS ingresos_refacciones,
                  COALESCE(SUM(COALESCE(rc.costo_real, o.subtotal_refacciones / 1.30)), 0) AS costo_refacciones
                FROM ordenes_servicio o
                LEFT JOIN (
                  SELECT orden_id,
                         SUM(COALESCE(precio_costo * cantidad, subtotal / 1.30)) AS costo_real
                  FROM refacciones_orden
                  GROUP BY orden_id
                ) rc ON rc.orden_id = o.id
                WHERE o.estado IN ('cerrada', 'entregada', 'completada')
                  AND COALESCE(o.fecha_completada, o.fecha_entregada, o.fecha_ingreso)
                      BETWEEN :fecha_inicio AND :fecha_fin
            ";
            $stmtIngresos = $this->db->prepare($sqlIngresos);
            $stmtIngresos->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
            $stmtIngresos->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
            $stmtIngresos->execute();
            $rowIngresos = $stmtIngresos->fetch(PDO::FETCH_ASSOC);

            $totalFacturado    = (float) ($rowIngresos['total_facturado']      ?? 0);
            $totalIva          = (float) ($rowIngresos['total_iva']            ?? 0);
            $ingresosServicios = (float) ($rowIngresos['ingresos_servicios']   ?? 0);
            $ingresosManoObra  = (float) ($rowIngresos['ingresos_mano_obra']   ?? 0);
            $ingresoRefacc     = (float) ($rowIngresos['ingresos_refacciones'] ?? 0);
            $costoRefacc       = (float) ($rowIngresos['costo_refacciones']    ?? 0);
            $margenRefacc      = round($ingresoRefacc - $costoRefacc, 2);

            $ingresosNetos = round($ingresosServicios + $ingresosManoObra + $margenRefacc, 2);

            // Gastos internos de órdenes del período (todas, incluyendo abiertas)
            // Se incluyen órdenes abiertas para que el balance coincida con la tabla de detalle,
            // que también muestra costos de órdenes en proceso (anticipo).
            $sqlGastosOrdenes = "
                SELECT COALESCE(SUM(costo_interno_total), 0) AS gastos_ordenes_mes
                FROM ordenes_servicio
                WHERE COALESCE(fecha_completada, fecha_entregada, fecha_ingreso)
                      BETWEEN :fecha_inicio AND :fecha_fin
                  AND costo_interno_total > 0
            ";
            $stmtGO = $this->db->prepare($sqlGastosOrdenes);
            $stmtGO->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
            $stmtGO->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
            $stmtGO->execute();
            $rowGO = $stmtGO->fetch(PDO::FETCH_ASSOC);
            $gastosOrdenesMes = (float) ($rowGO['gastos_ordenes_mes'] ?? 0);

            $utilidadNeta = round($ingresosNetos - $totalAdmin - $gastosOrdenesMes, 2);

            // Calcular sueldos vigentes en el período
            // Empleados cuyo rango (fecha_inicio, fecha_fin) se solapa con (fechaInicio, fechaFin).
            // Para tipo_sueldo='semanal', sueldo_diario almacena el monto semanal;
            // la tarifa diaria efectiva = sueldo_diario / 7.
            $sqlSueldos = "
                SELECT sueldo_diario, tipo_sueldo
                FROM empleados_sueldos
                WHERE activo = 1
                  AND fecha_inicio <= :fecha_fin
                  AND (fecha_fin IS NULL OR fecha_fin >= :fecha_inicio)
            ";
            $stmtSueldos = $this->db->prepare($sqlSueldos);
            $stmtSueldos->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
            $stmtSueldos->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
            $stmtSueldos->execute();
            $rowsSueldos = $stmtSueldos->fetchAll(PDO::FETCH_ASSOC);

            // Días hábiles estimados: 5 para semana, 22 para mes
            $diasHabiles = isset($tipo) && $tipo === 'semana' ? 5 : 22;

            // Sumar usando la tarifa diaria efectiva de cada empleado
            $sumaDiaria = 0.0;
            foreach ($rowsSueldos as $rowS) {
                $monto = (float) $rowS['sueldo_diario'];
                if (($rowS['tipo_sueldo'] ?? 'diario') === 'semanal') {
                    $sumaDiaria += $monto / 7.0;
                } else {
                    $sumaDiaria += $monto;
                }
            }
            $totalSueldosPeriodo = round($sumaDiaria * $diasHabiles, 2);

            // Calcular pagos fijos vigentes en el período
            $sqlFijos = "
                SELECT id, monto, frecuencia
                FROM pagos_fijos
                WHERE activo = 1
                  AND fecha_inicio <= :fecha_fin
                  AND (fecha_fin IS NULL OR fecha_fin >= :fecha_inicio)
            ";
            $stmtFijos = $this->db->prepare($sqlFijos);
            $stmtFijos->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
            $stmtFijos->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
            $stmtFijos->execute();
            $rowsFijos = $stmtFijos->fetchAll(PDO::FETCH_ASSOC);

            $totalFijosPeriodo = 0.0;
            foreach ($rowsFijos as $fijo) {
                $montoFijo  = (float) $fijo['monto'];
                $frecuencia = $fijo['frecuencia'];
                if ($diasHabiles === 5) {
                    // Modo semana: semanal = 1x, mensual = /4
                    $totalFijosPeriodo += ($frecuencia === 'semanal') ? $montoFijo : $montoFijo / 4;
                } else {
                    // Modo mes: semanal = x4, mensual = 1x
                    $totalFijosPeriodo += ($frecuencia === 'semanal') ? $montoFijo * 4 : $montoFijo;
                }
            }
            $totalFijosPeriodo = round($totalFijosPeriodo, 2);

            http_response_code(200);
            echo json_encode([
                'success'                   => true,
                'mes'                       => $mes,
                'anio'                      => $anio,
                'fecha_inicio'              => $fechaInicio,
                'fecha_fin'                 => $fechaFin,
                'label'                     => $labelPeriodo,
                'gastos'                    => $gastos,
                'total_facturado'           => round($totalFacturado, 2),
                'total_iva'                 => round($totalIva, 2),
                'ingresos_servicios'        => round($ingresosServicios, 2),
                'ingresos_mano_obra'        => round($ingresosManoObra, 2),
                'ingresos_refacciones'      => round($ingresoRefacc, 2),
                'costo_refacciones'         => round($costoRefacc, 2),
                'margen_refacciones'        => $margenRefacc,
                'ingresos_netos'            => $ingresosNetos,
                'total_admin'               => round($totalAdmin, 2),
                'gastos_ordenes_mes'        => round($gastosOrdenesMes, 2),
                'utilidad_neta'             => $utilidadNeta,
                'total_sueldos_periodo'     => $totalSueldosPeriodo,
                'total_pagos_fijos_periodo' => $totalFijosPeriodo,
            ]);

        } catch (Exception $e) {
            error_log('[FinancieroController::gastosAdmin] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener gastos administrativos']);
        }
    }

    // -----------------------------------------------------------------------
    // POST /api/financiero/gastos-admin
    // Body: { mes, anio, concepto, monto, categoria }
    // Solo admin.
    // -----------------------------------------------------------------------
    public function crearGastoAdmin(array $body, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $mes       = isset($body['mes'])       ? (int)   $body['mes']                   : 0;
            $anio      = isset($body['anio'])      ? (int)   $body['anio']                  : 0;
            $concepto  = isset($body['concepto'])  ? trim((string) $body['concepto'])        : '';
            $monto     = isset($body['monto'])     ? (float) $body['monto']                 : 0;
            $categoria = isset($body['categoria']) ? trim((string) $body['categoria'])       : '';

            if ($mes < 1 || $mes > 12) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'mes debe estar entre 1 y 12']);
                return;
            }
            if ($anio < 2020 || $anio > 2030) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'anio fuera de rango permitido']);
                return;
            }
            if ($concepto === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El concepto no puede estar vacío']);
                return;
            }
            if ($monto <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El monto debe ser mayor a 0']);
                return;
            }
            $categoriasValidas = ['renta', 'salario', 'servicio', 'insumo', 'otro'];
            if (!in_array($categoria, $categoriasValidas, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'categoria inválida']);
                return;
            }

            $registradoPor = (int) ($userData['userId'] ?? $userData['id'] ?? 0);

            $sqlInsert = "
                INSERT INTO gastos_administrativos (mes, anio, concepto, monto, categoria, registrado_por)
                VALUES (:mes, :anio, :concepto, :monto, :categoria, :registrado_por)
            ";
            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->bindParam(':mes',           $mes,          PDO::PARAM_INT);
            $stmtInsert->bindParam(':anio',          $anio,         PDO::PARAM_INT);
            $stmtInsert->bindParam(':concepto',      $concepto,     PDO::PARAM_STR);
            $stmtInsert->bindParam(':monto',         $monto);
            $stmtInsert->bindParam(':categoria',     $categoria,    PDO::PARAM_STR);
            $stmtInsert->bindParam(':registrado_por',$registradoPor,PDO::PARAM_INT);
            $stmtInsert->execute();

            $nuevoId = (int) $this->db->lastInsertId();

            $stmtUser = $this->db->prepare('SELECT COALESCE(nombre_completo, username) AS nombre FROM usuarios WHERE id = :id LIMIT 1');
            $stmtUser->bindParam(':id', $registradoPor, PDO::PARAM_INT);
            $stmtUser->execute();
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            $nombreUsuario = $usuario ? $usuario['nombre'] : '';

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'gasto'   => [
                    'id'                    => $nuevoId,
                    'mes'                   => $mes,
                    'anio'                  => $anio,
                    'concepto'              => $concepto,
                    'monto'                 => (float) $monto,
                    'categoria'             => $categoria,
                    'registrado_por_nombre' => $nombreUsuario,
                    'created_at'            => date('Y-m-d H:i:s'),
                ],
            ]);

        } catch (Exception $e) {
            error_log('[FinancieroController::crearGastoAdmin] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al registrar el gasto administrativo']);
        }
    }

    // -----------------------------------------------------------------------
    // DELETE /api/financiero/gastos-admin/:id
    // Solo admin.
    // -----------------------------------------------------------------------
    public function eliminarGastoAdmin(int $id, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Solo el administrador puede eliminar gastos administrativos']);
                return;
            }

            if ($id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID inválido']);
                return;
            }

            $stmtCheck = $this->db->prepare('SELECT id FROM gastos_administrativos WHERE id = :id LIMIT 1');
            $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtCheck->execute();
            if (!$stmtCheck->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Gasto no encontrado']);
                return;
            }

            $stmtDel = $this->db->prepare('DELETE FROM gastos_administrativos WHERE id = :id');
            $stmtDel->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtDel->execute();

            http_response_code(200);
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            error_log('[FinancieroController::eliminarGastoAdmin] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al eliminar el gasto administrativo']);
        }
    }

    // -----------------------------------------------------------------------
    // GET /api/financiero/ordenes?tipo=mes|semana&offset=0
    // Órdenes del período con desglose financiero por orden.
    // Solo admin.
    // -----------------------------------------------------------------------
    public function ordenesDesglosadas(): void {
        try {
            $userData = requireAuth();

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $tipo   = isset($_GET['tipo'])   ? trim($_GET['tipo'])   : 'mes';
            $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

            if (!in_array($tipo, ['semana', 'quincena', 'mes'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'tipo debe ser semana, quincena o mes']);
                return;
            }

            [$fechaInicio, $fechaFin] = $this->calcularPeriodo($tipo, $offset);

            // ── Modelo flujo de caja real ──────────────────────────────────────────────
            // Se usan 3 queries separadas para evitar ambigüedad de PDO con UNION complejos.
            //
            // Query A: Órdenes ABIERTAS        → fecha_ingreso, venta=anticipo, costo=refac
            // Query B: Órdenes CERRADAS c/ant  → fecha_ingreso, venta=anticipo, costo=refac
            //          (el anticipo se mantiene histórico en su semana aunque la orden cierre después)
            // Query C: Órdenes CERRADAS        → fecha_entregada, venta=restante(total-anticipo), costo=0
            //          Si no hubo anticipo: venta=total, costo=refac normal

            $estadosAbiertos  = "NOT IN ('completado','completada','entregado','entregada','cerrada')";
            $estadosCerrados  = "IN ('completado','completada','entregado','entregada','cerrada')";
            $costoRefacSubq   = "(SELECT SUM(COALESCE(r.precio_costo * r.cantidad, r.subtotal / 1.30))
                                   FROM refacciones_orden r WHERE r.orden_id = os.id)";
            $costoRefacFallbk = "COALESCE(os.subtotal_refacciones, 0) / 1.30";
            $costoRefac       = "COALESCE($costoRefacSubq, $costoRefacFallbk)";

            // ── Query A: abiertas ──────────────────────────────────────────────────────
            $sqlA = "
                SELECT
                    os.id, os.numero_orden,
                    os.fecha_ingreso AS fecha,
                    c.nombre AS cliente_nombre,
                    CONCAT(v.marca, ' ', v.modelo, IF(v.anio IS NOT NULL, CONCAT(' ', v.anio), '')) AS vehiculo,
                    COALESCE(os.anticipo, 0)                                     AS costo_venta,
                    COALESCE($costoRefacSubq, $costoRefacFallbk)                 AS costo_refacciones,
                    COALESCE(os.costo_interno_total, 0)                          AS costo_interno_total,
                    (COALESCE(os.anticipo, 0)
                      - COALESCE($costoRefacSubq, $costoRefacFallbk)
                      - COALESCE(os.costo_interno_total, 0))                     AS ganancia,
                    0                                                             AS iva_orden,
                    os.estado,
                    'apertura'                                                    AS tipo_fila,
                    0                                                             AS tiene_anticipo
                FROM ordenes_servicio os
                LEFT JOIN clientes c ON os.cliente_id = c.id
                LEFT JOIN vehiculos v ON os.vehiculo_id = v.id
                WHERE os.fecha_ingreso BETWEEN ? AND ?
                  AND os.estado $estadosAbiertos
                ORDER BY fecha ASC
            ";
            $stmtA = $this->db->prepare($sqlA);
            $stmtA->execute([$fechaInicio, $fechaFin]);
            $rowsA = $stmtA->fetchAll(PDO::FETCH_ASSOC);

            // ── Query B: cerradas con anticipo, por fecha_ingreso (histórico anticipo) ──
            $sqlB = "
                SELECT
                    os.id, os.numero_orden,
                    os.fecha_ingreso AS fecha,
                    c.nombre AS cliente_nombre,
                    CONCAT(v.marca, ' ', v.modelo, IF(v.anio IS NOT NULL, CONCAT(' ', v.anio), '')) AS vehiculo,
                    COALESCE(os.anticipo, 0)                                     AS costo_venta,
                    COALESCE($costoRefacSubq, $costoRefacFallbk)                 AS costo_refacciones,
                    COALESCE(os.costo_interno_total, 0)                          AS costo_interno_total,
                    (COALESCE(os.anticipo, 0)
                      - COALESCE($costoRefacSubq, $costoRefacFallbk)
                      - COALESCE(os.costo_interno_total, 0))                     AS ganancia,
                    0                                                             AS iva_orden,
                    os.estado,
                    'anticipo'                                                    AS tipo_fila,
                    1                                                             AS tiene_anticipo
                FROM ordenes_servicio os
                LEFT JOIN clientes c ON os.cliente_id = c.id
                LEFT JOIN vehiculos v ON os.vehiculo_id = v.id
                WHERE os.fecha_ingreso BETWEEN ? AND ?
                  AND os.estado $estadosCerrados
                  AND COALESCE(os.anticipo, 0) > 0
                ORDER BY fecha ASC
            ";
            $stmtB   = $this->db->prepare($sqlB);
            $stmtB->execute([$fechaInicio, $fechaFin]);
            $rowsB = $stmtB->fetchAll(PDO::FETCH_ASSOC);

            // ── Query C: cerradas por fecha_entregada (solo el restante) ──────────────
            $sqlC = "
                SELECT
                    os.id, os.numero_orden,
                    COALESCE(os.fecha_entregada, os.fecha_completada, os.fecha_ingreso) AS fecha,
                    c.nombre AS cliente_nombre,
                    CONCAT(v.marca, ' ', v.modelo, IF(v.anio IS NOT NULL, CONCAT(' ', v.anio), '')) AS vehiculo,
                    CASE WHEN COALESCE(os.anticipo, 0) > 0
                         THEN (os.total - COALESCE(os.anticipo, 0))
                         ELSE os.total
                    END AS costo_venta,
                    CASE WHEN COALESCE(os.anticipo, 0) > 0
                         THEN 0
                         ELSE COALESCE($costoRefacSubq, $costoRefacFallbk)
                    END AS costo_refacciones,
                    CASE WHEN COALESCE(os.anticipo, 0) > 0
                         THEN 0
                         ELSE COALESCE(os.costo_interno_total, 0)
                    END AS costo_interno_total,
                    CASE WHEN COALESCE(os.anticipo, 0) > 0
                         THEN (os.total - COALESCE(os.anticipo, 0))
                         ELSE (os.total
                           - COALESCE($costoRefacSubq, $costoRefacFallbk)
                           - COALESCE(os.costo_interno_total, 0))
                    END AS ganancia,
                    COALESCE(os.iva, 0) AS iva_orden,
                    os.estado,
                    'cierre' AS tipo_fila,
                    CASE WHEN COALESCE(os.anticipo, 0) > 0 THEN 1 ELSE 0 END AS tiene_anticipo
                FROM ordenes_servicio os
                LEFT JOIN clientes c ON os.cliente_id = c.id
                LEFT JOIN vehiculos v ON os.vehiculo_id = v.id
                WHERE COALESCE(os.fecha_entregada, os.fecha_completada, os.fecha_ingreso)
                      BETWEEN ? AND ?
                  AND os.estado $estadosCerrados
                ORDER BY fecha ASC
            ";
            $stmtC = $this->db->prepare($sqlC);
            $stmtC->execute([$fechaInicio, $fechaFin]);
            $rowsC = $stmtC->fetchAll(PDO::FETCH_ASSOC);

            // Merge A + B + C y ordenar por fecha ASC
            $rows = array_merge($rowsA, $rowsB, $rowsC);
            usort($rows, fn($x, $y) => strcmp($x['fecha'] ?? '', $y['fecha'] ?? ''));

            // ── Cargar servicios, refacciones y gastos internos por orden ──
            $serviciosPorOrden   = [];
            $refaccionesPorOrden = [];
            $gastosPorOrden      = [];

            if (!empty($rows)) {
                // Deduplicar IDs: una orden cerrada con anticipo aparece en Parte B y C
                // array_values() fuerza índices 0,1,2... — PDO 8.x tira HY093 con índices no-secuenciales
                $orderIds    = array_values(array_unique(array_map(fn($r) => (int) $r['id'], $rows)));
                $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

                $stmtSvc = $this->db->prepare("
                    SELECT orden_id, descripcion, subtotal
                    FROM servicios_orden
                    WHERE orden_id IN ($placeholders)
                    ORDER BY id ASC
                ");
                $stmtSvc->execute($orderIds);
                foreach ($stmtSvc->fetchAll(PDO::FETCH_ASSOC) as $svc) {
                    $serviciosPorOrden[(int)$svc['orden_id']][] = [
                        'descripcion' => $svc['descripcion'],
                        'subtotal'    => round((float)$svc['subtotal'], 2),
                    ];
                }

                $stmtRef = $this->db->prepare("
                    SELECT orden_id, descripcion, proveedor, cantidad, precio_costo, subtotal
                    FROM refacciones_orden
                    WHERE orden_id IN ($placeholders)
                    ORDER BY id ASC
                ");
                $stmtRef->execute($orderIds);
                foreach ($stmtRef->fetchAll(PDO::FETCH_ASSOC) as $ref) {
                    $precioCosto = $ref['precio_costo'] !== null
                        ? (float)$ref['precio_costo']
                        : null;
                    $refaccionesPorOrden[(int)$ref['orden_id']][] = [
                        'descripcion' => $ref['descripcion'],
                        'proveedor'   => $ref['proveedor'] ?: null,
                        'subtotal'    => round((float)$ref['subtotal'], 2),
                        'precio_costo'=> $precioCosto,
                        'cantidad'    => (float)$ref['cantidad'],
                    ];
                }

                $stmtGastos = $this->db->prepare("
                    SELECT go.orden_id, go.tipo, go.concepto, go.monto
                    FROM gastos_orden go
                    WHERE go.orden_id IN ($placeholders)
                    ORDER BY go.orden_id ASC, go.id ASC
                ");
                $stmtGastos->execute($orderIds);
                foreach ($stmtGastos->fetchAll(PDO::FETCH_ASSOC) as $g) {
                    $gastosPorOrden[(int)$g['orden_id']][] = [
                        'tipo'     => $g['tipo'],
                        'concepto' => $g['concepto'],
                        'monto'    => round((float)$g['monto'], 2),
                    ];
                }
            }

            $ordenes = array_map(function ($r) use ($serviciosPorOrden, $refaccionesPorOrden, $gastosPorOrden) {
                $id       = (int) $r['id'];
                $tipoFila = $r['tipo_fila'] ?? 'apertura';
                // Las filas 'cierre' de órdenes con anticipo no repiten servicios/refac/gastos
                // (ya aparecen en la fila 'anticipo' de la semana de apertura)
                $esRestante = ($tipoFila === 'cierre' && (int)($r['tiene_anticipo'] ?? 0) === 1);
                return [
                    'id'                 => $id,
                    'numero_orden'       => $r['numero_orden'],
                    'fecha'              => $r['fecha'],
                    'cliente_nombre'     => trim($r['cliente_nombre']),
                    'vehiculo'           => trim($r['vehiculo']),
                    'costo_venta'        => round((float) $r['costo_venta'], 2),
                    'costo_refacciones'  => round((float) $r['costo_refacciones'], 2),
                    'costo_interno'      => round((float) ($r['costo_interno_total'] ?? 0), 2),
                    'gastos_internos'    => $esRestante ? [] : ($gastosPorOrden[$id] ?? []),
                    'iva'                => round((float) ($r['iva_orden'] ?? 0), 2),
                    'ganancia'           => round((float) $r['ganancia'], 2),
                    'estado'             => $r['estado'],
                    'tipo_fila'          => $tipoFila,
                    'servicios'          => $esRestante ? [] : ($serviciosPorOrden[$id]    ?? []),
                    'refacciones_detalle'=> $esRestante ? [] : ($refaccionesPorOrden[$id]  ?? []),
                ];
            }, $rows);

            $totales = [
                'costo_venta'       => round(array_sum(array_column($ordenes, 'costo_venta')), 2),
                'costo_refacciones' => round(array_sum(array_column($ordenes, 'costo_refacciones')), 2),
                'ganancia'          => round(array_sum(array_column($ordenes, 'ganancia')), 2),
            ];

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'ordenes' => $ordenes,
                'totales' => $totales,
            ]);

        } catch (Exception $e) {
            error_log('[FinancieroController::ordenesDesglosadas] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener las órdenes']);
        }
    }

    // -----------------------------------------------------------------------
    // GET /api/financiero/empleados
    // Listar empleados. Todos los roles autenticados.
    // -----------------------------------------------------------------------
    public function empleadosSueldos(): void {
        try {
            $userData = requireAuth();
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            // Sin filtro de fechas: devuelve TODOS los empleados (vigentes y ex-empleados).
            // El frontend separa:
            //   - empleadosVigentes: sin fecha_fin o fecha_fin > hoy → aparecen en tabla principal
            //   - exEmpleados: fecha_fin <= hoy → aparecen en sección colapsada "Ex-empleados"
            // Dentro de vigentes, activo=false se muestra greyed out (sin pago esa semana).
            // Si se recibe fecha_inicio, se hace LEFT JOIN con empleado_asistencia para la semana.
            $semanaInicio = isset($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : null;

            $sql = "
                SELECT es.id, es.usuario_id, es.nombre, es.puesto, es.sueldo_diario, es.tipo_sueldo,
                       es.fecha_inicio, es.fecha_fin, es.activo,
                       COALESCE(ea.dias_trabajados, 5) AS dias_trabajados
                FROM empleados_sueldos es
                LEFT JOIN empleado_asistencia ea
                  ON ea.empleado_id = es.id
                 AND ea.semana_inicio = :semana_inicio
                WHERE 1=1
                ORDER BY es.activo DESC, (es.fecha_fin IS NULL) DESC, es.nombre ASC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':semana_inicio', $semanaInicio, $semanaInicio !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $empleados = array_map(function ($r) {
                return [
                    'id'              => (int)    $r['id'],
                    'usuario_id'      => $r['usuario_id'] !== null ? (int) $r['usuario_id'] : null,
                    'nombre'          => $r['nombre'],
                    'puesto'          => $r['puesto'],
                    'sueldo_diario'   => (float)  $r['sueldo_diario'],
                    'tipo_sueldo'     => $r['tipo_sueldo'] ?? 'diario',
                    'fecha_inicio'    => $r['fecha_inicio'],
                    'fecha_fin'       => $r['fecha_fin'],
                    'activo'          => (bool)   $r['activo'],
                    'dias_trabajados' => (int)    $r['dias_trabajados'],
                ];
            }, $rows);

            http_response_code(200);
            echo json_encode(['success' => true, 'empleados' => $empleados]);

        } catch (Exception $e) {
            error_log('[FinancieroController::empleadosSueldos] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener empleados']);
        }
    }

    // -----------------------------------------------------------------------
    // PUT /api/financiero/empleados/:id/asistencia
    // Body: { semana_inicio: 'YYYY-MM-DD', dias_trabajados: 0-7 }
    // UPSERT días trabajados para un empleado en una semana dada.
    // -----------------------------------------------------------------------
    public function asistenciaEmpleado(int $id, array $body, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            $semanaInicio   = isset($body['semana_inicio'])   ? trim((string) $body['semana_inicio'])   : null;
            $diasTrabajados = isset($body['dias_trabajados']) ? (int) $body['dias_trabajados']          : null;

            if (!$semanaInicio || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $semanaInicio)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'semana_inicio inválido (formato YYYY-MM-DD requerido)']);
                return;
            }
            if ($diasTrabajados === null || $diasTrabajados < 0 || $diasTrabajados > 7) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'dias_trabajados debe ser entre 0 y 7']);
                return;
            }

            $sql = "
                INSERT INTO empleado_asistencia (empleado_id, semana_inicio, dias_trabajados)
                VALUES (:empleado_id, :semana_inicio, :dias)
                ON DUPLICATE KEY UPDATE dias_trabajados = VALUES(dias_trabajados),
                                        updated_at = CURRENT_TIMESTAMP
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':empleado_id',   $id,             PDO::PARAM_INT);
            $stmt->bindParam(':semana_inicio', $semanaInicio,   PDO::PARAM_STR);
            $stmt->bindParam(':dias',          $diasTrabajados, PDO::PARAM_INT);
            $stmt->execute();

            http_response_code(200);
            echo json_encode(['success' => true, 'dias_trabajados' => $diasTrabajados]);

        } catch (Exception $e) {
            error_log('[FinancieroController::asistenciaEmpleado] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al guardar asistencia']);
        }
    }

    // -----------------------------------------------------------------------
    // POST /api/financiero/empleados
    // Body: { nombre, puesto?, sueldo_diario, usuario_id? }
    // Solo admin.
    // -----------------------------------------------------------------------
    public function crearEmpleado(array $body, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $nombre       = isset($body['nombre'])       ? trim((string) $body['nombre'])       : '';
            $puesto       = isset($body['puesto'])       ? trim((string) $body['puesto'])       : null;
            $sueldoDiario = isset($body['sueldo_diario']) ? (float) $body['sueldo_diario']      : 0;
            $tipoSueldo   = isset($body['tipo_sueldo'])  && $body['tipo_sueldo'] === 'semanal' ? 'semanal' : 'diario';
            $usuarioId    = isset($body['usuario_id'])   ? (int)   $body['usuario_id']         : null;
            $fechaInicio  = isset($body['fecha_inicio']) ? trim((string) $body['fecha_inicio']) : date('Y-m-d');

            if ($nombre === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El nombre no puede estar vacío']);
                return;
            }
            if ($sueldoDiario < 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El sueldo diario no puede ser negativo']);
                return;
            }

            $sql = "
                INSERT INTO empleados_sueldos (nombre, puesto, sueldo_diario, tipo_sueldo, fecha_inicio, usuario_id)
                VALUES (:nombre, :puesto, :sueldo_diario, :tipo_sueldo, :fecha_inicio, :usuario_id)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':nombre',        $nombre,       PDO::PARAM_STR);
            $stmt->bindParam(':puesto',         $puesto,       PDO::PARAM_STR);
            $stmt->bindParam(':sueldo_diario',  $sueldoDiario);
            $stmt->bindParam(':tipo_sueldo',    $tipoSueldo,   PDO::PARAM_STR);
            $stmt->bindParam(':fecha_inicio',   $fechaInicio,  PDO::PARAM_STR);
            if ($usuarioId !== null) {
                $stmt->bindParam(':usuario_id', $usuarioId, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':usuario_id', null, PDO::PARAM_NULL);
            }
            $stmt->execute();
            $nuevoId = (int) $this->db->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success'  => true,
                'empleado' => [
                    'id'           => $nuevoId,
                    'usuario_id'   => $usuarioId,
                    'nombre'       => $nombre,
                    'puesto'       => $puesto,
                    'sueldo_diario'=> $sueldoDiario,
                    'tipo_sueldo'  => $tipoSueldo,
                    'fecha_inicio' => $fechaInicio,
                    'fecha_fin'    => null,
                    'activo'       => true,
                ],
            ]);

        } catch (Exception $e) {
            error_log('[FinancieroController::crearEmpleado] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al crear empleado']);
        }
    }

    // -----------------------------------------------------------------------
    // PUT /api/financiero/empleados/:id
    // Body: { nombre?, puesto?, sueldo_diario? }
    // Solo admin.
    // -----------------------------------------------------------------------
    public function actualizarEmpleado(int $id, array $body, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $stmtCheck = $this->db->prepare(
                'SELECT id, nombre, puesto, sueldo_diario, tipo_sueldo, usuario_id, activo FROM empleados_sueldos WHERE id = :id LIMIT 1'
            );
            $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtCheck->execute();
            $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$existente) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Empleado no encontrado']);
                return;
            }

            // Validar campos entrantes
            $cambiaNombre      = isset($body['nombre']);
            $cambiaPuesto      = array_key_exists('puesto', $body);
            // Versioning solo cuando el MONTO realmente cambia — el frontend siempre manda sueldo_diario,
            // así que comparamos valor contra el registro existente en lugar de solo detectar presencia.
            $cambiaSueldo      = isset($body['sueldo_diario'])
                && ((float)$body['sueldo_diario'] !== (float)$existente['sueldo_diario']);
            $cambiaTipoSueldo  = isset($body['tipo_sueldo']);
            $cambiaFechaInicio = isset($body['fecha_inicio']);

            if (!$cambiaNombre && !$cambiaPuesto && !$cambiaSueldo && !$cambiaTipoSueldo
                && !$cambiaFechaInicio
                && !array_key_exists('fecha_fin', $body) && !array_key_exists('activo', $body)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No se enviaron campos a actualizar']);
                return;
            }

            if ($cambiaNombre) {
                $nuevoNombre = trim((string) $body['nombre']);
                if ($nuevoNombre === '') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'El nombre no puede estar vacío']);
                    return;
                }
            } else {
                $nuevoNombre = $existente['nombre'];
            }

            $nuevoPuesto = $cambiaPuesto
                ? ($body['puesto'] !== null ? trim((string) $body['puesto']) : null)
                : $existente['puesto'];

            if ($cambiaSueldo) {
                $nuevoSueldo = (float) $body['sueldo_diario'];
                if ($nuevoSueldo < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'El sueldo diario no puede ser negativo']);
                    return;
                }
            } else {
                $nuevoSueldo = (float) $existente['sueldo_diario'];
            }

            $nuevoTipoSueldo = $cambiaTipoSueldo
                ? (in_array($body['tipo_sueldo'], ['diario','semanal'], true) ? $body['tipo_sueldo'] : 'diario')
                : ($existente['tipo_sueldo'] ?? 'diario');

            // Actualización directa cuando viene fecha_fin o activo (dar de baja o reactivar)
            $cambiaFechaFin = array_key_exists('fecha_fin', $body);
            $cambiaActivo   = array_key_exists('activo', $body);
            $darDeBajaOReactivar = $cambiaFechaFin || $cambiaActivo;

            if ($cambiaSueldo) {
                // PATRÓN VERSIONAR: solo cuando cambia el monto/sueldo.
                // 1. Cerrar el registro actual: fecha_fin = fecha_inicio_cambio - 1 día
                // 2. Insertar nuevo registro con el nuevo sueldo y fecha_inicio = fecha_inicio_cambio
                // Así el historial queda intacto para cualquier período anterior.
                $nuevaFechaInicio = isset($body['fecha_inicio_cambio'])
                    ? trim((string) $body['fecha_inicio_cambio'])
                    : date('Y-m-d');

                $fechaFinAnterior = date('Y-m-d', strtotime($nuevaFechaInicio . ' -1 day'));

                $this->db->beginTransaction();
                try {
                    // Cerrar el registro existente
                    $stmtCierre = $this->db->prepare(
                        'UPDATE empleados_sueldos SET fecha_fin = :fecha_fin WHERE id = :id'
                    );
                    $stmtCierre->bindParam(':fecha_fin', $fechaFinAnterior, PDO::PARAM_STR);
                    $stmtCierre->bindParam(':id',        $id,               PDO::PARAM_INT);
                    $stmtCierre->execute();

                    // Crear nuevo registro vigente
                    $usuarioId = $existente['usuario_id'] !== null ? (int) $existente['usuario_id'] : null;
                    $sqlNuevo  = "
                        INSERT INTO empleados_sueldos
                            (nombre, puesto, sueldo_diario, tipo_sueldo, fecha_inicio, usuario_id, activo)
                        VALUES
                            (:nombre, :puesto, :sueldo_diario, :tipo_sueldo, :fecha_inicio, :usuario_id, 1)
                    ";
                    $stmtNuevo = $this->db->prepare($sqlNuevo);
                    $stmtNuevo->bindParam(':nombre',        $nuevoNombre,     PDO::PARAM_STR);
                    $stmtNuevo->bindParam(':puesto',         $nuevoPuesto,     PDO::PARAM_STR);
                    $stmtNuevo->bindParam(':sueldo_diario',  $nuevoSueldo);
                    $stmtNuevo->bindParam(':tipo_sueldo',    $nuevoTipoSueldo, PDO::PARAM_STR);
                    $stmtNuevo->bindParam(':fecha_inicio',   $nuevaFechaInicio, PDO::PARAM_STR);
                    if ($usuarioId !== null) {
                        $stmtNuevo->bindParam(':usuario_id', $usuarioId, PDO::PARAM_INT);
                    } else {
                        $stmtNuevo->bindValue(':usuario_id', null, PDO::PARAM_NULL);
                    }
                    $stmtNuevo->execute();
                    $nuevoId = (int) $this->db->lastInsertId();

                    $this->db->commit();

                    // Devolver el nuevo registro creado
                    http_response_code(200);
                    echo json_encode([
                        'success'  => true,
                        'empleado' => [
                            'id'           => $nuevoId,
                            'usuario_id'   => $usuarioId,
                            'nombre'       => $nuevoNombre,
                            'puesto'       => $nuevoPuesto,
                            'sueldo_diario'=> $nuevoSueldo,
                            'tipo_sueldo'  => $nuevoTipoSueldo,
                            'fecha_inicio' => $nuevaFechaInicio,
                            'fecha_fin'    => null,
                            'activo'       => true,
                        ],
                    ]);
                } catch (Exception $e) {
                    $this->db->rollBack();
                    throw $e;
                }
            } else {
                // Cambio de nombre, puesto, tipo_sueldo, fecha_inicio, fecha_fin o activo — UPDATE directo
                $campos = [];
                $params = [];
                if ($cambiaNombre) {
                    $campos[]         = 'nombre = :nombre';
                    $params['nombre'] = $nuevoNombre;
                }
                if ($cambiaPuesto) {
                    $campos[]         = 'puesto = :puesto';
                    $params['puesto'] = $nuevoPuesto;
                }
                if ($cambiaTipoSueldo) {
                    $campos[]              = 'tipo_sueldo = :tipo_sueldo';
                    $params['tipo_sueldo'] = $nuevoTipoSueldo;
                }
                if ($cambiaFechaInicio) {
                    $fechaInicioVal           = trim((string) $body['fecha_inicio']);
                    $campos[]                 = 'fecha_inicio = :fecha_inicio';
                    $params['fecha_inicio']   = $fechaInicioVal;
                }
                if ($darDeBajaOReactivar) {
                    if ($cambiaFechaFin) {
                        // Acepta fecha string (dar de baja) o null/"" (reactivar)
                        $fechaFinVal = $body['fecha_fin'];
                        if ($fechaFinVal === null || $fechaFinVal === '') {
                            // Reactivar: poner fecha_fin a NULL
                            $campos[]              = 'fecha_fin = :fecha_fin';
                            $params['fecha_fin']   = null;
                        } else {
                            $fechaFinVal = trim((string) $fechaFinVal);
                            $campos[]              = 'fecha_fin = :fecha_fin';
                            $params['fecha_fin']   = $fechaFinVal;
                        }
                    }
                    if ($cambiaActivo) {
                        $activoVal = $body['activo'] ? 1 : 0;
                        $campos[]           = 'activo = :activo';
                        $params['activo']   = $activoVal;
                    }
                }

                if (empty($campos)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'No se enviaron campos a actualizar']);
                    return;
                }

                $sql  = 'UPDATE empleados_sueldos SET ' . implode(', ', $campos) . ' WHERE id = :id';
                $stmt = $this->db->prepare($sql);
                foreach ($params as $key => $val) {
                    if ($val === null) {
                        $stmt->bindValue(':' . $key, null, PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue(':' . $key, $val);
                    }
                }
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();

                // Re-fetch para devolver el registro actualizado
                $stmtGet = $this->db->prepare(
                    'SELECT id, usuario_id, nombre, puesto, sueldo_diario, tipo_sueldo, fecha_inicio, fecha_fin, activo
                     FROM empleados_sueldos WHERE id = :id LIMIT 1'
                );
                $stmtGet->bindParam(':id', $id, PDO::PARAM_INT);
                $stmtGet->execute();
                $row = $stmtGet->fetch(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success'  => true,
                    'empleado' => [
                        'id'           => (int)   $row['id'],
                        'usuario_id'   => $row['usuario_id'] !== null ? (int) $row['usuario_id'] : null,
                        'nombre'       => $row['nombre'],
                        'puesto'       => $row['puesto'],
                        'sueldo_diario'=> (float) $row['sueldo_diario'],
                        'tipo_sueldo'  => $row['tipo_sueldo'] ?? 'diario',
                        'fecha_inicio' => $row['fecha_inicio'],
                        'fecha_fin'    => $row['fecha_fin'],
                        'activo'       => (bool)  $row['activo'],
                    ],
                ]);
            }

        } catch (Exception $e) {
            error_log('[FinancieroController::actualizarEmpleado] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al actualizar empleado']);
        }
    }

    // -----------------------------------------------------------------------
    // PUT /api/financiero/empleados/:id/toggle
    // Cambia activo ↔ inactivo. Solo admin.
    // -----------------------------------------------------------------------
    public function toggleEmpleado(int $id, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $stmtCheck = $this->db->prepare('SELECT id, activo FROM empleados_sueldos WHERE id = :id LIMIT 1');
            $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtCheck->execute();
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Empleado no encontrado']);
                return;
            }

            $nuevoEstado = $row['activo'] ? 0 : 1;
            $stmtUp = $this->db->prepare('UPDATE empleados_sueldos SET activo = :activo WHERE id = :id');
            $stmtUp->bindParam(':activo', $nuevoEstado, PDO::PARAM_INT);
            $stmtUp->bindParam(':id',     $id,          PDO::PARAM_INT);
            $stmtUp->execute();

            http_response_code(200);
            echo json_encode(['success' => true, 'activo' => (bool) $nuevoEstado]);

        } catch (Exception $e) {
            error_log('[FinancieroController::toggleEmpleado] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al cambiar estado del empleado']);
        }
    }

    // -----------------------------------------------------------------------
    // GET /api/financiero/pagos-fijos
    // Listar pagos fijos. Todos los roles autenticados.
    // -----------------------------------------------------------------------
    public function pagosFijos(): void {
        try {
            $userData = requireAuth();
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            // Filtrado por vigencia. Si no se pasan fechas, se usa CURDATE() para ambas
            // (devuelve solo los registros vigentes hoy).
            $fechaConsultaInicio = isset($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : date('Y-m-d');
            $fechaConsultaFin    = isset($_GET['fecha_fin'])    ? trim($_GET['fecha_fin'])    : date('Y-m-d');

            // Muestra activos E inactivos vigentes en el período.
            // El frontend filtra activo=true para los cálculos y muestra inactivos en gris.
            $sql = "
                SELECT id, concepto, monto, fecha_inicio, fecha_fin, frecuencia, categoria, activo
                FROM pagos_fijos
                WHERE fecha_inicio <= :fecha_consulta_fin
                  AND (fecha_fin IS NULL OR fecha_fin >= :fecha_consulta_inicio)
                ORDER BY activo DESC, concepto ASC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':fecha_consulta_inicio', $fechaConsultaInicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_consulta_fin',    $fechaConsultaFin,    PDO::PARAM_STR);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $pagos = array_map(function ($r) {
                return [
                    'id'          => (int)   $r['id'],
                    'concepto'    => $r['concepto'],
                    'monto'       => (float) $r['monto'],
                    'fecha_inicio'=> $r['fecha_inicio'],
                    'fecha_fin'   => $r['fecha_fin'],
                    'frecuencia'  => $r['frecuencia'],
                    'categoria'   => $r['categoria'],
                    'activo'      => (bool)  $r['activo'],
                ];
            }, $rows);

            http_response_code(200);
            echo json_encode(['success' => true, 'pagos_fijos' => $pagos]);

        } catch (Exception $e) {
            error_log('[FinancieroController::pagosFijos] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener pagos fijos']);
        }
    }

    // -----------------------------------------------------------------------
    // POST /api/financiero/pagos-fijos
    // Body: { concepto, monto, frecuencia, categoria }
    // Solo admin.
    // -----------------------------------------------------------------------
    public function crearPagoFijo(array $body, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $concepto    = isset($body['concepto'])      ? trim((string) $body['concepto'])      : '';
            $monto       = isset($body['monto'])         ? (float) $body['monto']               : 0;
            $frecuencia  = isset($body['frecuencia'])    ? trim((string) $body['frecuencia'])    : 'mensual';
            $categoria   = isset($body['categoria'])     ? trim((string) $body['categoria'])     : 'otro';
            $fechaInicio = isset($body['fecha_inicio'])  ? trim((string) $body['fecha_inicio'])  : date('Y-m-d');

            if ($concepto === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El concepto no puede estar vacío']);
                return;
            }
            if ($monto <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'El monto debe ser mayor a 0']);
                return;
            }
            if (!in_array($frecuencia, ['semanal', 'mensual'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'frecuencia debe ser semanal o mensual']);
                return;
            }
            if (!in_array($categoria, ['renta', 'servicio', 'proveedor', 'marketing', 'otro'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'categoria inválida']);
                return;
            }

            $sql = "
                INSERT INTO pagos_fijos (concepto, monto, fecha_inicio, frecuencia, categoria)
                VALUES (:concepto, :monto, :fecha_inicio, :frecuencia, :categoria)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':concepto',    $concepto,    PDO::PARAM_STR);
            $stmt->bindParam(':monto',       $monto);
            $stmt->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(':frecuencia',  $frecuencia,  PDO::PARAM_STR);
            $stmt->bindParam(':categoria',   $categoria,   PDO::PARAM_STR);
            $stmt->execute();
            $nuevoId = (int) $this->db->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success'    => true,
                'pago_fijo'  => [
                    'id'          => $nuevoId,
                    'concepto'    => $concepto,
                    'monto'       => $monto,
                    'fecha_inicio'=> $fechaInicio,
                    'fecha_fin'   => null,
                    'frecuencia'  => $frecuencia,
                    'categoria'   => $categoria,
                    'activo'      => true,
                ],
            ]);

        } catch (Exception $e) {
            error_log('[FinancieroController::crearPagoFijo] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al crear pago fijo']);
        }
    }

    // -----------------------------------------------------------------------
    // PUT /api/financiero/pagos-fijos/:id
    // Body: { concepto?, monto?, frecuencia?, categoria? }
    // Solo admin.
    // -----------------------------------------------------------------------
    public function actualizarPagoFijo(int $id, array $body, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $stmtCheck = $this->db->prepare(
                'SELECT id, concepto, monto, frecuencia, categoria, activo FROM pagos_fijos WHERE id = :id LIMIT 1'
            );
            $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtCheck->execute();
            $existente = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$existente) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Pago fijo no encontrado']);
                return;
            }

            // Validar campos entrantes
            $cambiaConcepto   = isset($body['concepto']);
            $cambiaMonto      = isset($body['monto']);
            $cambiaFrecuencia = isset($body['frecuencia']);
            $cambiaCategoria  = isset($body['categoria']);

            if (!$cambiaConcepto && !$cambiaMonto && !$cambiaFrecuencia && !$cambiaCategoria) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No se enviaron campos a actualizar']);
                return;
            }

            if ($cambiaConcepto) {
                $nuevoConcepto = trim((string) $body['concepto']);
                if ($nuevoConcepto === '') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'El concepto no puede estar vacío']);
                    return;
                }
            } else {
                $nuevoConcepto = $existente['concepto'];
            }

            if ($cambiaMonto) {
                $nuevoMonto = (float) $body['monto'];
                if ($nuevoMonto <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'El monto debe ser mayor a 0']);
                    return;
                }
            } else {
                $nuevoMonto = (float) $existente['monto'];
            }

            if ($cambiaFrecuencia) {
                $nuevaFrecuencia = trim((string) $body['frecuencia']);
                if (!in_array($nuevaFrecuencia, ['semanal', 'mensual'], true)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'frecuencia inválida']);
                    return;
                }
            } else {
                $nuevaFrecuencia = $existente['frecuencia'];
            }

            if ($cambiaCategoria) {
                $nuevaCategoria = trim((string) $body['categoria']);
                if (!in_array($nuevaCategoria, ['renta', 'servicio', 'proveedor', 'marketing', 'otro'], true)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'categoria inválida']);
                    return;
                }
            } else {
                $nuevaCategoria = $existente['categoria'];
            }

            if ($cambiaMonto) {
                // PATRÓN VERSIONAR: solo cuando cambia el monto.
                // 1. Cerrar el registro actual: fecha_fin = fecha_inicio_cambio - 1 día
                // 2. Insertar nuevo registro con el nuevo monto y fecha_inicio = fecha_inicio_cambio
                $nuevaFechaInicio = isset($body['fecha_inicio_cambio'])
                    ? trim((string) $body['fecha_inicio_cambio'])
                    : date('Y-m-d');

                $fechaFinAnterior = date('Y-m-d', strtotime($nuevaFechaInicio . ' -1 day'));

                $this->db->beginTransaction();
                try {
                    $stmtCierre = $this->db->prepare(
                        'UPDATE pagos_fijos SET fecha_fin = :fecha_fin WHERE id = :id'
                    );
                    $stmtCierre->bindParam(':fecha_fin', $fechaFinAnterior, PDO::PARAM_STR);
                    $stmtCierre->bindParam(':id',        $id,               PDO::PARAM_INT);
                    $stmtCierre->execute();

                    $sqlNuevo = "
                        INSERT INTO pagos_fijos
                            (concepto, monto, fecha_inicio, frecuencia, categoria, activo)
                        VALUES
                            (:concepto, :monto, :fecha_inicio, :frecuencia, :categoria, 1)
                    ";
                    $stmtNuevo = $this->db->prepare($sqlNuevo);
                    $stmtNuevo->bindParam(':concepto',    $nuevoConcepto,   PDO::PARAM_STR);
                    $stmtNuevo->bindParam(':monto',       $nuevoMonto);
                    $stmtNuevo->bindParam(':fecha_inicio', $nuevaFechaInicio, PDO::PARAM_STR);
                    $stmtNuevo->bindParam(':frecuencia',  $nuevaFrecuencia,  PDO::PARAM_STR);
                    $stmtNuevo->bindParam(':categoria',   $nuevaCategoria,   PDO::PARAM_STR);
                    $stmtNuevo->execute();
                    $nuevoId = (int) $this->db->lastInsertId();

                    $this->db->commit();

                    http_response_code(200);
                    echo json_encode([
                        'success'   => true,
                        'pago_fijo' => [
                            'id'          => $nuevoId,
                            'concepto'    => $nuevoConcepto,
                            'monto'       => $nuevoMonto,
                            'fecha_inicio'=> $nuevaFechaInicio,
                            'fecha_fin'   => null,
                            'frecuencia'  => $nuevaFrecuencia,
                            'categoria'   => $nuevaCategoria,
                            'activo'      => true,
                        ],
                    ]);
                } catch (Exception $e) {
                    $this->db->rollBack();
                    throw $e;
                }
            } else {
                // Solo cambió concepto, frecuencia o categoría — UPDATE directo
                $campos = [];
                $params = [];
                if ($cambiaConcepto)   { $campos[] = 'concepto = :concepto';     $params['concepto']   = $nuevoConcepto; }
                if ($cambiaFrecuencia) { $campos[] = 'frecuencia = :frecuencia'; $params['frecuencia'] = $nuevaFrecuencia; }
                if ($cambiaCategoria)  { $campos[] = 'categoria = :categoria';   $params['categoria']  = $nuevaCategoria; }

                $sql  = 'UPDATE pagos_fijos SET ' . implode(', ', $campos) . ' WHERE id = :id';
                $stmt = $this->db->prepare($sql);
                foreach ($params as $key => $val) {
                    $stmt->bindValue(':' . $key, $val);
                }
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();

                $stmtGet = $this->db->prepare(
                    'SELECT id, concepto, monto, fecha_inicio, fecha_fin, frecuencia, categoria, activo
                     FROM pagos_fijos WHERE id = :id LIMIT 1'
                );
                $stmtGet->bindParam(':id', $id, PDO::PARAM_INT);
                $stmtGet->execute();
                $row = $stmtGet->fetch(PDO::FETCH_ASSOC);

                http_response_code(200);
                echo json_encode([
                    'success'   => true,
                    'pago_fijo' => [
                        'id'          => (int)   $row['id'],
                        'concepto'    => $row['concepto'],
                        'monto'       => (float) $row['monto'],
                        'fecha_inicio'=> $row['fecha_inicio'],
                        'fecha_fin'   => $row['fecha_fin'],
                        'frecuencia'  => $row['frecuencia'],
                        'categoria'   => $row['categoria'],
                        'activo'      => (bool)  $row['activo'],
                    ],
                ]);
            }

        } catch (Exception $e) {
            error_log('[FinancieroController::actualizarPagoFijo] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al actualizar pago fijo']);
        }
    }

    // -----------------------------------------------------------------------
    // PUT /api/financiero/pagos-fijos/:id/toggle
    // Solo admin.
    // -----------------------------------------------------------------------
    public function togglePagoFijo(int $id, $userData): void {
        try {
            if (!$userData) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'No autenticado']);
                return;
            }

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $stmtCheck = $this->db->prepare('SELECT id, activo FROM pagos_fijos WHERE id = :id LIMIT 1');
            $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtCheck->execute();
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Pago fijo no encontrado']);
                return;
            }

            $nuevoEstado = $row['activo'] ? 0 : 1;
            $stmtUp = $this->db->prepare('UPDATE pagos_fijos SET activo = :activo WHERE id = :id');
            $stmtUp->bindParam(':activo', $nuevoEstado, PDO::PARAM_INT);
            $stmtUp->bindParam(':id',     $id,          PDO::PARAM_INT);
            $stmtUp->execute();

            http_response_code(200);
            echo json_encode(['success' => true, 'activo' => (bool) $nuevoEstado]);

        } catch (Exception $e) {
            error_log('[FinancieroController::togglePagoFijo] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al cambiar estado del pago fijo']);
        }
    }

    // ──────────────────────────────────────────────
    // CAJA CHICA
    // ──────────────────────────────────────────────

    public function cajaChica(): void {
        try {
            $userData = requireAuth();

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $tipo   = isset($_GET['tipo'])   ? trim($_GET['tipo'])   : 'semana';
            $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

            if (!in_array($tipo, ['semana', 'mes'], true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'tipo debe ser semana o mes']);
                return;
            }

            [$fechaInicio, $fechaFin] = $this->calcularPeriodo($tipo, $offset);

            $stmtAnterior = $this->db->prepare(
                'SELECT COALESCE(SUM(CASE WHEN tipo = :ing THEN monto ELSE -monto END), 0) AS saldo_anterior
                 FROM caja_chica WHERE fecha < :fecha_inicio'
            );
            $stmtAnterior->bindValue(':ing',          'ingreso',    PDO::PARAM_STR);
            $stmtAnterior->bindValue(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
            $stmtAnterior->execute();
            $saldoAnterior = (float) $stmtAnterior->fetchColumn();

            $stmtMov = $this->db->prepare(
                'SELECT id, fecha, tipo, concepto, monto, notas, gasto_admin_id
                 FROM caja_chica
                 WHERE fecha BETWEEN :fecha_inicio AND :fecha_fin
                 ORDER BY fecha ASC, id ASC'
            );
            $stmtMov->bindValue(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
            $stmtMov->bindValue(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
            $stmtMov->execute();
            $rows = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

            $movimientos    = [];
            $ingresosSemana = 0.0;
            $egresosSemana  = 0.0;

            foreach ($rows as $r) {
                $monto = (float) $r['monto'];
                if ($r['tipo'] === 'ingreso') {
                    $ingresosSemana += $monto;
                } else {
                    $egresosSemana += $monto;
                }
                $movimientos[] = [
                    'id'             => (int) $r['id'],
                    'fecha'          => $r['fecha'],
                    'tipo'           => $r['tipo'],
                    'concepto'       => $r['concepto'],
                    'monto'          => round($monto, 2),
                    'notas'          => $r['notas'],
                    'gasto_admin_id' => $r['gasto_admin_id'] !== null ? (int) $r['gasto_admin_id'] : null,
                ];
            }

            $saldoActual = round($saldoAnterior + $ingresosSemana - $egresosSemana, 2);

            http_response_code(200);
            echo json_encode([
                'success'         => true,
                'movimientos'     => $movimientos,
                'saldo_anterior'  => round($saldoAnterior, 2),
                'ingresos_semana' => round($ingresosSemana, 2),
                'egresos_semana'  => round($egresosSemana, 2),
                'saldo_actual'    => $saldoActual,
            ]);

        } catch (Exception $e) {
            error_log('[FinancieroController::cajaChica] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener caja chica']);
        }
    }

    public function crearMovimientoCajaChica(): void {
        try {
            $userData = requireAuth();

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $body = json_decode(file_get_contents('php://input'), true) ?? [];

            $fecha    = trim($body['fecha']    ?? '');
            $tipo     = trim($body['tipo']     ?? '');
            $concepto = trim($body['concepto'] ?? '');
            $monto    = isset($body['monto']) ? (float) $body['monto'] : 0.0;
            $notas    = isset($body['notas']) && $body['notas'] !== '' ? trim($body['notas']) : null;

            if (!in_array($tipo, ['ingreso', 'egreso'], true)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'tipo debe ser ingreso o egreso']);
                return;
            }
            if ($monto <= 0) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'monto debe ser mayor a cero']);
                return;
            }
            if ($concepto === '') {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'concepto no puede estar vacío']);
                return;
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
                http_response_code(422);
                echo json_encode(['success' => false, 'error' => 'fecha inválida']);
                return;
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                'INSERT INTO caja_chica (fecha, tipo, concepto, monto, notas)
                 VALUES (:fecha, :tipo, :concepto, :monto, :notas)'
            );
            $stmt->bindValue(':fecha',    $fecha,    PDO::PARAM_STR);
            $stmt->bindValue(':tipo',     $tipo,     PDO::PARAM_STR);
            $stmt->bindValue(':concepto', $concepto, PDO::PARAM_STR);
            $stmt->bindValue(':monto',    $monto);
            $stmt->bindValue(':notas',    $notas,    $notas === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();

            $nuevoId = (int) $this->db->lastInsertId();

            // Si es egreso, crear entrada en gastos_administrativos para el P&L
            $gastoAdminId = null;
            if ($tipo === 'egreso') {
                $mes  = (int) date('n', strtotime($fecha));
                $anio = (int) date('Y', strtotime($fecha));
                $registradoPor = (int) ($userData['userId'] ?? $userData['id'] ?? 0);
                $conceptoAdmin = 'Caja chica: ' . $concepto;

                $stmtAdmin = $this->db->prepare(
                    'INSERT INTO gastos_administrativos (mes, anio, concepto, monto, categoria, registrado_por)
                     VALUES (:mes, :anio, :concepto, :monto, :categoria, :registrado_por)'
                );
                $stmtAdmin->bindValue(':mes',           $mes,          PDO::PARAM_INT);
                $stmtAdmin->bindValue(':anio',          $anio,         PDO::PARAM_INT);
                $stmtAdmin->bindValue(':concepto',      $conceptoAdmin, PDO::PARAM_STR);
                $stmtAdmin->bindValue(':monto',         $monto);
                $stmtAdmin->bindValue(':categoria',     'otro',        PDO::PARAM_STR);
                $stmtAdmin->bindValue(':registrado_por',$registradoPor, PDO::PARAM_INT);
                $stmtAdmin->execute();

                $gastoAdminId = (int) $this->db->lastInsertId();

                $stmtLink = $this->db->prepare(
                    'UPDATE caja_chica SET gasto_admin_id = :gasto_admin_id WHERE id = :id'
                );
                $stmtLink->bindValue(':gasto_admin_id', $gastoAdminId, PDO::PARAM_INT);
                $stmtLink->bindValue(':id',             $nuevoId,      PDO::PARAM_INT);
                $stmtLink->execute();
            }

            $this->db->commit();

            http_response_code(201);
            echo json_encode([
                'success'     => true,
                'movimiento'  => [
                    'id'             => $nuevoId,
                    'fecha'          => $fecha,
                    'tipo'           => $tipo,
                    'concepto'       => $concepto,
                    'monto'          => round($monto, 2),
                    'notas'          => $notas,
                    'gasto_admin_id' => $gastoAdminId,
                ],
            ]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[FinancieroController::crearMovimientoCajaChica] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al crear movimiento de caja chica']);
        }
    }

    public function eliminarMovimientoCajaChica(int $id): void {
        try {
            $userData = requireAuth();

            if (($userData['rol'] ?? $userData['role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Acceso restringido a administradores']);
                return;
            }

            $stmtCheck = $this->db->prepare('SELECT id, gasto_admin_id FROM caja_chica WHERE id = :id LIMIT 1');
            $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtCheck->execute();
            $movimiento = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$movimiento) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Movimiento no encontrado']);
                return;
            }

            $this->db->beginTransaction();

            // Si el egreso tenía un gasto admin vinculado, eliminarlo del P&L
            if ($movimiento['gasto_admin_id'] !== null) {
                $gastoAdminId = (int) $movimiento['gasto_admin_id'];
                $stmtDelAdmin = $this->db->prepare('DELETE FROM gastos_administrativos WHERE id = :id');
                $stmtDelAdmin->bindValue(':id', $gastoAdminId, PDO::PARAM_INT);
                $stmtDelAdmin->execute();
            }

            $stmtDel = $this->db->prepare('DELETE FROM caja_chica WHERE id = :id');
            $stmtDel->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtDel->execute();

            $this->db->commit();

            http_response_code(200);
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[FinancieroController::eliminarMovimientoCajaChica] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al eliminar movimiento de caja chica']);
        }
    }
}
