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

            if (($userData->rol ?? '') !== 'admin') {
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
                'resumen'      => $resumen,
                'refacciones'  => $refacciones,
                'top_servicios' => $topServicios,
                'por_dia'      => $porDia,
            ]);

        } catch (Exception $e) {
            error_log('[FinancieroController::resumen] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Error al obtener datos financieros']);
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
              AND o.fecha_ingreso BETWEEN :fecha_inicio AND :fecha_fin
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_facturado'     => (float) $row['total_facturado'],
            'ingresos_servicios'  => (float) $row['ingresos_servicios'],
            'ingresos_mano_obra'  => (float) $row['ingresos_mano_obra'],
            'ingresos_refacciones'=> (float) $row['ingresos_refacciones'],
            'total_iva'           => (float) $row['total_iva'],
            'num_ordenes'         => (int)   $row['num_ordenes'],
        ];
    }

    private function queryRefacciones(string $fechaInicio, string $fechaFin): array {
        $sql = "
            SELECT
                COALESCE(SUM(r.precio_venta * r.cantidad), 0)                      AS vendido,
                COALESCE(SUM(r.precio_costo * r.cantidad), 0)                      AS costo,
                COALESCE(SUM((r.precio_venta - r.precio_costo) * r.cantidad), 0)   AS margen
            FROM refacciones_orden r
            INNER JOIN ordenes_servicio o ON r.orden_id = o.id
            WHERE o.estado IN ('cerrada', 'entregada', 'completada')
              AND o.fecha_ingreso BETWEEN :fecha_inicio AND :fecha_fin
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fecha_inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindParam(':fecha_fin',    $fechaFin,    PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $vendido = (float) $row['vendido'];
        $costo   = (float) $row['costo'];
        $margen  = (float) $row['margen'];
        $margenPct = $vendido > 0 ? round($margen / $vendido * 100, 1) : 0.0;

        return [
            'vendido'    => $vendido,
            'costo'      => $costo,
            'margen'     => $margen,
            'margen_pct' => $margenPct,
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
              AND o.fecha_ingreso BETWEEN :fecha_inicio AND :fecha_fin
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
                DATE(o.fecha_ingreso)        AS dia,
                COALESCE(SUM(o.total), 0)   AS total
            FROM ordenes_servicio o
            WHERE o.estado IN ('cerrada', 'entregada', 'completada')
              AND o.fecha_ingreso BETWEEN :fecha_inicio AND :fecha_fin
            GROUP BY DATE(o.fecha_ingreso)
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
}
