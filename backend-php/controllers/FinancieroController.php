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
            $fechaFin    = $ultimo->format('Y-m-d');
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
        $sql = "
            SELECT
                COALESCE(SUM(o.subtotal_servicios), 0)   AS ingresos_servicios,
                COALESCE(SUM(o.subtotal_mano_obra), 0)   AS ingresos_mano_obra,
                COALESCE(SUM(o.subtotal_refacciones), 0) AS ingresos_refacciones,
                COALESCE(SUM(o.iva), 0)                  AS total_iva,
                COALESCE(SUM(o.total), 0)                AS total_facturado,
                COUNT(o.id)                              AS num_ordenes
            FROM ordenes_servicio o
            WHERE o.estado IN ('cerrada', 'entregada', 'completada')
              AND COALESCE(o.fecha_completada, o.fecha_entregada, o.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
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
        // precio_unitario ya incluye el 30% de margen fijo: precioVenta = precioCosto * 1.30
        // Por lo tanto: costo = subtotal / 1.30, margen = subtotal - (subtotal / 1.30)
        $sql = "
            SELECT
                COALESCE(SUM(r.subtotal), 0)                    AS vendido,
                COALESCE(SUM(r.subtotal / 1.30), 0)             AS costo_estimado,
                COALESCE(SUM(r.subtotal - r.subtotal / 1.30), 0) AS margen_estimado,
                COUNT(r.id)                                      AS num_items
            FROM refacciones_orden r
            INNER JOIN ordenes_servicio o ON r.orden_id = o.id
            WHERE o.estado IN ('cerrada', 'entregada', 'completada')
              AND COALESCE(o.fecha_completada, o.fecha_entregada, o.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
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
            WHERE o.estado IN ('cerrada', 'entregada', 'completada')
              AND COALESCE(o.fecha_completada, o.fecha_entregada, o.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
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
            WHERE o.estado IN ('cerrada', 'entregada', 'completada')
              AND COALESCE(o.fecha_completada, o.fecha_entregada, o.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
            GROUP BY DATE(COALESCE(o.fecha_completada, o.fecha_entregada, o.fecha_ingreso))
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
            WHERE o.estado IN ('cerrada', 'entregada', 'completada')
              AND c.activo = 1
              AND COALESCE(o.fecha_completada, o.fecha_entregada, o.fecha_ingreso) BETWEEN :fecha_inicio AND :fecha_fin
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
                  COALESCE(SUM(o.subtotal_refacciones / 1.30), 0)  AS costo_refacciones
                FROM ordenes_servicio o
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

            // Gastos internos de órdenes del período
            $sqlGastosOrdenes = "
                SELECT COALESCE(SUM(costo_interno_total), 0) AS gastos_ordenes_mes
                FROM ordenes_servicio
                WHERE COALESCE(fecha_completada, fecha_entregada, fecha_ingreso)
                      BETWEEN :fecha_inicio AND :fecha_fin
                  AND costo_interno_total > 0
                  AND estado IN ('cerrada', 'entregada')
            ";
            $stmtGO = $this->db->prepare($sqlGastosOrdenes);
            $stmtGO->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
            $stmtGO->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
            $stmtGO->execute();
            $rowGO = $stmtGO->fetch(PDO::FETCH_ASSOC);
            $gastosOrdenesMes = (float) ($rowGO['gastos_ordenes_mes'] ?? 0);

            $utilidadNeta = round($ingresosNetos - $totalAdmin - $gastosOrdenesMes, 2);

            http_response_code(200);
            echo json_encode([
                'success'              => true,
                'mes'                  => $mes,
                'anio'                 => $anio,
                'fecha_inicio'         => $fechaInicio,
                'fecha_fin'            => $fechaFin,
                'label'                => $labelPeriodo,
                'gastos'               => $gastos,
                'total_facturado'      => round($totalFacturado, 2),
                'total_iva'            => round($totalIva, 2),
                'ingresos_servicios'   => round($ingresosServicios, 2),
                'ingresos_mano_obra'   => round($ingresosManoObra, 2),
                'ingresos_refacciones' => round($ingresoRefacc, 2),
                'costo_refacciones'    => round($costoRefacc, 2),
                'margen_refacciones'   => $margenRefacc,
                'ingresos_netos'       => $ingresosNetos,
                'total_admin'          => round($totalAdmin, 2),
                'gastos_ordenes_mes'   => round($gastosOrdenesMes, 2),
                'utilidad_neta'        => $utilidadNeta,
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

            // UNION de dos lógicas:
            // 1. Órdenes EN PROCESO → aparecen en la semana que ingresó el coche.
            //    Ingreso = anticipo recibido (puede ser 0). Costo = refacciones ya compradas.
            //    Muestra la "inversión" de esta semana aunque no se haya cobrado.
            // 2. Órdenes CERRADAS → aparecen en la semana en que se entregaron/cobraron.
            //    Ingreso = total cobrado. Costo = refacciones (deducción en el cierre).
            //    Así si el coche entró la semana pasada y se cobró esta semana, el ingreso
            //    aparece aquí, no la semana pasada.
            $sql = "
                SELECT
                    os.id,
                    os.numero_orden,
                    os.fecha_ingreso                                                        AS fecha,
                    c.nombre                                                                AS cliente_nombre,
                    CONCAT(v.marca, ' ', v.modelo, IF(v.anio IS NOT NULL, CONCAT(' ', v.anio), '')) AS vehiculo,
                    COALESCE(os.anticipo, 0)                                                AS costo_venta,
                    COALESCE(os.subtotal_refacciones, 0) / 1.30                            AS costo_refacciones,
                    (COALESCE(os.anticipo, 0) - COALESCE(os.subtotal_refacciones, 0) / 1.30) AS ganancia,
                    os.estado
                FROM ordenes_servicio os
                LEFT JOIN clientes c  ON os.cliente_id  = c.id
                LEFT JOIN vehiculos v ON os.vehiculo_id = v.id
                WHERE os.fecha_ingreso BETWEEN :fecha_inicio_a AND :fecha_fin_a
                  AND os.estado NOT IN ('completado','completada','entregado','entregada','cerrada')

                UNION ALL

                SELECT
                    os.id,
                    os.numero_orden,
                    COALESCE(os.fecha_entregada, os.fecha_completada, os.fecha_ingreso)    AS fecha,
                    c.nombre                                                                AS cliente_nombre,
                    CONCAT(v.marca, ' ', v.modelo, IF(v.anio IS NOT NULL, CONCAT(' ', v.anio), '')) AS vehiculo,
                    os.total                                                                AS costo_venta,
                    COALESCE(os.subtotal_refacciones, 0) / 1.30                            AS costo_refacciones,
                    (os.total - COALESCE(os.subtotal_refacciones, 0) / 1.30)               AS ganancia,
                    os.estado
                FROM ordenes_servicio os
                LEFT JOIN clientes c  ON os.cliente_id  = c.id
                LEFT JOIN vehiculos v ON os.vehiculo_id = v.id
                WHERE COALESCE(os.fecha_entregada, os.fecha_completada, os.fecha_ingreso)
                      BETWEEN :fecha_inicio_b AND :fecha_fin_b
                  AND os.estado IN ('completado','completada','entregado','entregada','cerrada')

                ORDER BY fecha ASC
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':fecha_inicio_a', $fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin_a',    $fechaFin,    PDO::PARAM_STR);
            $stmt->bindParam(':fecha_inicio_b', $fechaInicio, PDO::PARAM_STR);
            $stmt->bindParam(':fecha_fin_b',    $fechaFin,    PDO::PARAM_STR);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $ordenes = array_map(function ($r) {
                return [
                    'id'               => (int)   $r['id'],
                    'numero_orden'     => $r['numero_orden'],
                    'fecha'            => $r['fecha'],
                    'cliente_nombre'   => trim($r['cliente_nombre']),
                    'vehiculo'         => trim($r['vehiculo']),
                    'costo_venta'      => round((float) $r['costo_venta'], 2),
                    'costo_refacciones'=> round((float) $r['costo_refacciones'], 2),
                    'ganancia'         => round((float) $r['ganancia'], 2),
                    'estado'           => $r['estado'],
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

            $stmt = $this->db->prepare(
                'SELECT id, usuario_id, nombre, puesto, sueldo_diario, activo FROM empleados_sueldos ORDER BY nombre ASC'
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $empleados = array_map(function ($r) {
                return [
                    'id'           => (int)    $r['id'],
                    'usuario_id'   => $r['usuario_id'] !== null ? (int) $r['usuario_id'] : null,
                    'nombre'       => $r['nombre'],
                    'puesto'       => $r['puesto'],
                    'sueldo_diario'=> (float)  $r['sueldo_diario'],
                    'activo'       => (bool)   $r['activo'],
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

            $nombre      = isset($body['nombre'])      ? trim((string) $body['nombre'])      : '';
            $puesto      = isset($body['puesto'])      ? trim((string) $body['puesto'])      : null;
            $sueldoDiario= isset($body['sueldo_diario'])? (float) $body['sueldo_diario']     : 0;
            $usuarioId   = isset($body['usuario_id'])  ? (int)   $body['usuario_id']        : null;

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
                INSERT INTO empleados_sueldos (nombre, puesto, sueldo_diario, usuario_id)
                VALUES (:nombre, :puesto, :sueldo_diario, :usuario_id)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':nombre',       $nombre,       PDO::PARAM_STR);
            $stmt->bindParam(':puesto',        $puesto,       PDO::PARAM_STR);
            $stmt->bindParam(':sueldo_diario', $sueldoDiario);
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

            $stmtCheck = $this->db->prepare('SELECT id FROM empleados_sueldos WHERE id = :id LIMIT 1');
            $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtCheck->execute();
            if (!$stmtCheck->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Empleado no encontrado']);
                return;
            }

            $campos = [];
            $params = [];

            if (isset($body['nombre'])) {
                $nombre = trim((string) $body['nombre']);
                if ($nombre === '') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'El nombre no puede estar vacío']);
                    return;
                }
                $campos[]          = 'nombre = :nombre';
                $params['nombre']  = $nombre;
            }
            if (array_key_exists('puesto', $body)) {
                $campos[]         = 'puesto = :puesto';
                $params['puesto'] = $body['puesto'] !== null ? trim((string) $body['puesto']) : null;
            }
            if (isset($body['sueldo_diario'])) {
                $sd = (float) $body['sueldo_diario'];
                if ($sd < 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'El sueldo diario no puede ser negativo']);
                    return;
                }
                $campos[]               = 'sueldo_diario = :sueldo_diario';
                $params['sueldo_diario']= $sd;
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
                'SELECT id, usuario_id, nombre, puesto, sueldo_diario, activo FROM empleados_sueldos WHERE id = :id LIMIT 1'
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
                    'activo'       => (bool)  $row['activo'],
                ],
            ]);

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

            $stmt = $this->db->prepare(
                'SELECT id, concepto, monto, frecuencia, categoria, activo FROM pagos_fijos ORDER BY concepto ASC'
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $pagos = array_map(function ($r) {
                return [
                    'id'         => (int)   $r['id'],
                    'concepto'   => $r['concepto'],
                    'monto'      => (float) $r['monto'],
                    'frecuencia' => $r['frecuencia'],
                    'categoria'  => $r['categoria'],
                    'activo'     => (bool)  $r['activo'],
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

            $concepto   = isset($body['concepto'])   ? trim((string) $body['concepto'])   : '';
            $monto      = isset($body['monto'])      ? (float) $body['monto']             : 0;
            $frecuencia = isset($body['frecuencia']) ? trim((string) $body['frecuencia']) : 'mensual';
            $categoria  = isset($body['categoria'])  ? trim((string) $body['categoria'])  : 'otro';

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
                INSERT INTO pagos_fijos (concepto, monto, frecuencia, categoria)
                VALUES (:concepto, :monto, :frecuencia, :categoria)
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':concepto',   $concepto,   PDO::PARAM_STR);
            $stmt->bindParam(':monto',      $monto);
            $stmt->bindParam(':frecuencia', $frecuencia, PDO::PARAM_STR);
            $stmt->bindParam(':categoria',  $categoria,  PDO::PARAM_STR);
            $stmt->execute();
            $nuevoId = (int) $this->db->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success'    => true,
                'pago_fijo'  => [
                    'id'         => $nuevoId,
                    'concepto'   => $concepto,
                    'monto'      => $monto,
                    'frecuencia' => $frecuencia,
                    'categoria'  => $categoria,
                    'activo'     => true,
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

            $stmtCheck = $this->db->prepare('SELECT id FROM pagos_fijos WHERE id = :id LIMIT 1');
            $stmtCheck->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtCheck->execute();
            if (!$stmtCheck->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Pago fijo no encontrado']);
                return;
            }

            $campos = [];
            $params = [];

            if (isset($body['concepto'])) {
                $c = trim((string) $body['concepto']);
                if ($c === '') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'El concepto no puede estar vacío']);
                    return;
                }
                $campos[]           = 'concepto = :concepto';
                $params['concepto'] = $c;
            }
            if (isset($body['monto'])) {
                $m = (float) $body['monto'];
                if ($m <= 0) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'El monto debe ser mayor a 0']);
                    return;
                }
                $campos[]        = 'monto = :monto';
                $params['monto'] = $m;
            }
            if (isset($body['frecuencia'])) {
                $f = trim((string) $body['frecuencia']);
                if (!in_array($f, ['semanal', 'mensual'], true)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'frecuencia inválida']);
                    return;
                }
                $campos[]             = 'frecuencia = :frecuencia';
                $params['frecuencia'] = $f;
            }
            if (isset($body['categoria'])) {
                $cat = trim((string) $body['categoria']);
                if (!in_array($cat, ['renta', 'servicio', 'proveedor', 'marketing', 'otro'], true)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'categoria inválida']);
                    return;
                }
                $campos[]            = 'categoria = :categoria';
                $params['categoria'] = $cat;
            }

            if (empty($campos)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'No se enviaron campos a actualizar']);
                return;
            }

            $sql  = 'UPDATE pagos_fijos SET ' . implode(', ', $campos) . ' WHERE id = :id';
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $val) {
                $stmt->bindValue(':' . $key, $val);
            }
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $stmtGet = $this->db->prepare(
                'SELECT id, concepto, monto, frecuencia, categoria, activo FROM pagos_fijos WHERE id = :id LIMIT 1'
            );
            $stmtGet->bindParam(':id', $id, PDO::PARAM_INT);
            $stmtGet->execute();
            $row = $stmtGet->fetch(PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode([
                'success'   => true,
                'pago_fijo' => [
                    'id'         => (int)   $row['id'],
                    'concepto'   => $row['concepto'],
                    'monto'      => (float) $row['monto'],
                    'frecuencia' => $row['frecuencia'],
                    'categoria'  => $row['categoria'],
                    'activo'     => (bool)  $row['activo'],
                ],
            ]);

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
