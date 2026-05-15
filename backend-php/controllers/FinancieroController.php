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
    // GET /api/financiero/gastos-admin?mes=X&anio=Y
    // Solo admin. Retorna lista de gastos + balance del mes.
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

            // Lista de gastos administrativos del mes
            $sqlGastos = "
                SELECT g.id, g.mes, g.anio, g.concepto, g.monto, g.categoria,
                       COALESCE(u.nombre_completo, u.username) AS registrado_por_nombre,
                       g.created_at
                FROM gastos_administrativos g
                INNER JOIN usuarios u ON u.id = g.registrado_por
                WHERE g.mes = :mes AND g.anio = :anio
                ORDER BY g.created_at ASC
            ";
            $stmtGastos = $this->db->prepare($sqlGastos);
            $stmtGastos->bindParam(':mes',  $mes,  PDO::PARAM_INT);
            $stmtGastos->bindParam(':anio', $anio, PDO::PARAM_INT);
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

            // Ingresos del mes: desglose detallado para calcular ingresos netos
            // (excluye IVA y costo de refacciones — solo queda margen real del negocio)
            $sqlIngresos = "
                SELECT
                  COALESCE(SUM(o.total), 0)                        AS total_facturado,
                  COALESCE(SUM(o.iva), 0)                           AS total_iva,
                  COALESCE(SUM(o.subtotal_servicios), 0)            AS ingresos_servicios,
                  COALESCE(SUM(o.subtotal_mano_obra), 0)            AS ingresos_mano_obra,
                  COALESCE(SUM(o.subtotal_refacciones), 0)          AS ingresos_refacciones,
                  COALESCE(SUM(o.subtotal_refacciones / 1.30), 0)   AS costo_refacciones
                FROM ordenes_servicio o
                WHERE o.estado IN ('cerrada', 'entregada', 'completada')
                  AND MONTH(COALESCE(o.fecha_completada, o.fecha_entregada, o.fecha_ingreso)) = :mes
                  AND YEAR(COALESCE(o.fecha_completada, o.fecha_entregada, o.fecha_ingreso))  = :anio
            ";
            $stmtIngresos = $this->db->prepare($sqlIngresos);
            $stmtIngresos->bindParam(':mes',  $mes,  PDO::PARAM_INT);
            $stmtIngresos->bindParam(':anio', $anio, PDO::PARAM_INT);
            $stmtIngresos->execute();
            $rowIngresos = $stmtIngresos->fetch(PDO::FETCH_ASSOC);

            $totalFacturado    = (float) ($rowIngresos['total_facturado']    ?? 0);
            $totalIva          = (float) ($rowIngresos['total_iva']          ?? 0);
            $ingresosServicios = (float) ($rowIngresos['ingresos_servicios'] ?? 0);
            $ingresosManoObra  = (float) ($rowIngresos['ingresos_mano_obra'] ?? 0);
            $ingresoRefacc     = (float) ($rowIngresos['ingresos_refacciones'] ?? 0);
            $costoRefacc       = (float) ($rowIngresos['costo_refacciones']  ?? 0);
            $margenRefacc      = round($ingresoRefacc - $costoRefacc, 2);

            // Ingresos netos = servicios + mano de obra + margen de refacciones
            // (no incluye IVA porque va al SAT, ni costo de material porque no es ganancia)
            $ingresosNetos = round($ingresosServicios + $ingresosManoObra + $margenRefacc, 2);

            // Gastos internos de órdenes del mes — solo órdenes cerradas/entregadas
            // para no contaminar el balance con gastos de órdenes que aún no generaron ingreso
            $sqlGastosOrdenes = "
                SELECT COALESCE(SUM(costo_interno_total), 0) AS gastos_ordenes_mes
                FROM ordenes_servicio
                WHERE MONTH(COALESCE(fecha_completada, fecha_entregada, fecha_ingreso)) = :mes
                  AND YEAR(COALESCE(fecha_completada, fecha_entregada, fecha_ingreso))  = :anio
                  AND costo_interno_total > 0
                  AND estado IN ('cerrada', 'entregada')
            ";
            $stmtGO = $this->db->prepare($sqlGastosOrdenes);
            $stmtGO->bindParam(':mes',  $mes,  PDO::PARAM_INT);
            $stmtGO->bindParam(':anio', $anio, PDO::PARAM_INT);
            $stmtGO->execute();
            $rowGO = $stmtGO->fetch(PDO::FETCH_ASSOC);
            $gastosOrdenesMes = (float) ($rowGO['gastos_ordenes_mes'] ?? 0);

            $utilidadNeta = round($ingresosNetos - $totalAdmin - $gastosOrdenesMes, 2);

            http_response_code(200);
            echo json_encode([
                'success'              => true,
                'mes'                  => $mes,
                'anio'                 => $anio,
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
}
