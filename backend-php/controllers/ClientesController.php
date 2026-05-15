<?php
/**
 * Controlador de clientes — Directorio, perfil y búsqueda
 * Módulo Mes 1, Roadmap Q2 2026
 */

class ClientesController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * GET /api/clientes
     * Lista todos los clientes activos con métricas agregadas.
     * Soporta ?q= para filtrar por nombre o teléfono.
     */
    public function listar() {
        try {
            requireAuth();

            $q = isset($_GET['q']) ? trim($_GET['q']) : '';

            $baseSelect = "
                SELECT
                    c.id,
                    c.nombre,
                    c.telefono,
                    c.email,
                    COUNT(DISTINCT o.id)  AS total_visitas,
                    MAX(o.fecha_ingreso)  AS ultima_visita,
                    COUNT(DISTINCT v.id)  AS total_vehiculos
                FROM clientes c
                LEFT JOIN ordenes_servicio o ON o.cliente_id = c.id
                LEFT JOIN vehiculos v        ON v.cliente_id = c.id
                WHERE c.activo = 1
            ";

            if ($q !== '') {
                $sql = $baseSelect . "
                      AND (
                          c.nombre   LIKE :q_nombre
                       OR c.telefono LIKE :q_tel
                       OR v.marca    LIKE :q_marca
                       OR v.modelo   LIKE :q_modelo
                       OR v.placas   LIKE :q_placas
                      )
                    GROUP BY c.id
                    ORDER BY total_visitas DESC, ultima_visita DESC
                ";
                $stmt = $this->db->prepare($sql);
                $like = '%' . $q . '%';
                $stmt->bindValue(':q_nombre',  $like, PDO::PARAM_STR);
                $stmt->bindValue(':q_tel',     $like, PDO::PARAM_STR);
                $stmt->bindValue(':q_marca',   $like, PDO::PARAM_STR);
                $stmt->bindValue(':q_modelo',  $like, PDO::PARAM_STR);
                $stmt->bindValue(':q_placas',  $like, PDO::PARAM_STR);
            } else {
                $sql = $baseSelect . "
                    GROUP BY c.id
                    ORDER BY total_visitas DESC, ultima_visita DESC
                ";
                $stmt = $this->db->prepare($sql);
            }

            $stmt->execute();
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Castear tipos numéricos para que el JSON sea consistente
            foreach ($clientes as &$c) {
                $c['id']             = (int) $c['id'];
                $c['total_visitas']  = (int) $c['total_visitas'];
                $c['total_vehiculos'] = (int) $c['total_vehiculos'];
            }
            unset($c);

            http_response_code(200);
            echo json_encode([
                'success'  => true,
                'clientes' => $clientes,
                'total'    => count($clientes),
            ]);

        } catch (Exception $e) {
            error_log('[ClientesController::listar] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Error al obtener clientes',
            ]);
        }
    }

    /**
     * GET /api/clientes/buscar-por-telefono?tel=
     * Búsqueda de clientes activos por teléfono.
     * Devuelve todos los matches (puede haber más de uno con el mismo número).
     */
    public function buscarPorTelefono() {
        try {
            requireAuth();

            $tel = isset($_GET['tel']) ? trim($_GET['tel']) : '';

            if ($tel === '') {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'El parámetro tel es requerido',
                ]);
                return;
            }

            // Buscar clientes que coincidan con el teléfono dado
            $sql = "
                SELECT
                    c.id,
                    c.nombre,
                    c.telefono,
                    c.email,
                    COUNT(DISTINCT o.id) AS total_visitas,
                    MAX(o.fecha_ingreso) AS ultima_visita,
                    COUNT(DISTINCT v.id) AS total_vehiculos
                FROM clientes c
                LEFT JOIN ordenes_servicio o ON o.cliente_id = c.id
                LEFT JOIN vehiculos v        ON v.cliente_id = c.id
                WHERE c.activo = 1
                  AND c.telefono LIKE :tel
                GROUP BY c.id
                ORDER BY ultima_visita DESC
            ";
            $stmt = $this->db->prepare($sql);
            $like = '%' . $tel . '%';
            $stmt->bindParam(':tel', $like, PDO::PARAM_STR);
            $stmt->execute();
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Para cada cliente obtener sus vehículos más recientes
            $matches = [];
            foreach ($clientes as $cliente) {
                $clienteId = (int) $cliente['id'];

                $stmtV = $this->db->prepare("
                    SELECT id, marca, modelo, anio, color, placas, niv
                    FROM vehiculos
                    WHERE cliente_id = :cid
                    ORDER BY id DESC
                    LIMIT 5
                ");
                $stmtV->bindParam(':cid', $clienteId, PDO::PARAM_INT);
                $stmtV->execute();
                $vehiculos = $stmtV->fetchAll(PDO::FETCH_ASSOC);

                $matches[] = [
                    'id'             => $clienteId,
                    'nombre'         => $cliente['nombre'],
                    'telefono'       => $cliente['telefono'],
                    'email'          => $cliente['email'],
                    'total_visitas'  => (int) $cliente['total_visitas'],
                    'ultima_visita'  => $cliente['ultima_visita'],
                    'vehiculos'      => $vehiculos,
                    'total_vehiculos' => (int) $cliente['total_vehiculos'],
                ];
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'matches' => $matches,
            ]);

        } catch (Exception $e) {
            error_log('[ClientesController::buscarPorTelefono] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Error en la búsqueda por teléfono',
            ]);
        }
    }

    /**
     * GET /api/clientes/buscar?q=
     * Búsqueda unificada por nombre o teléfono.
     * Mínimo 2 caracteres; devuelve hasta 6 matches ordenados por total_visitas DESC.
     */
    public function buscar() {
        try {
            requireAuth();

            $q = isset($_GET['q']) ? trim($_GET['q']) : '';

            if (mb_strlen($q) < 2) {
                http_response_code(200);
                echo json_encode(['success' => true, 'matches' => []]);
                return;
            }

            $like = '%' . $q . '%';

            $sql = "
                SELECT
                    c.id,
                    c.nombre,
                    c.telefono,
                    c.email,
                    COUNT(DISTINCT o.id) AS total_visitas,
                    MAX(o.fecha_ingreso) AS ultima_visita,
                    COUNT(DISTINCT v.id) AS total_vehiculos
                FROM clientes c
                LEFT JOIN ordenes_servicio o ON o.cliente_id = c.id
                LEFT JOIN vehiculos v        ON v.cliente_id = c.id
                WHERE c.activo = 1
                  AND (c.nombre LIKE :q_nombre OR c.telefono LIKE :q_tel)
                GROUP BY c.id
                ORDER BY total_visitas DESC, ultima_visita DESC
                LIMIT 6
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':q_nombre', $like, PDO::PARAM_STR);
            $stmt->bindValue(':q_tel',    $like, PDO::PARAM_STR);
            $stmt->execute();
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($clientes)) {
                http_response_code(200);
                echo json_encode(['success' => true, 'matches' => []]);
                return;
            }

            // Cargar vehículos en un solo query para evitar N+1
            $ids = array_map(fn($c) => (int) $c['id'], $clientes);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtV = $this->db->prepare("
                SELECT id, cliente_id, marca, modelo, anio, color, placas, niv
                FROM vehiculos
                WHERE cliente_id IN ($placeholders)
                ORDER BY id DESC
            ");
            $stmtV->execute($ids);
            $todosVehiculos = $stmtV->fetchAll(PDO::FETCH_ASSOC);

            // Indexar vehículos por cliente_id
            $vehiculosPorCliente = [];
            foreach ($todosVehiculos as $v) {
                $cid = (int) $v['cliente_id'];
                if (!isset($vehiculosPorCliente[$cid])) {
                    $vehiculosPorCliente[$cid] = [];
                }
                if (count($vehiculosPorCliente[$cid]) < 5) {
                    $vehiculosPorCliente[$cid][] = [
                        'id'     => (int) $v['id'],
                        'marca'  => $v['marca'],
                        'modelo' => $v['modelo'],
                        'anio'   => $v['anio'],
                        'color'  => $v['color'],
                        'placas' => $v['placas'],
                        'niv'    => $v['niv'],
                    ];
                }
            }

            $matches = [];
            foreach ($clientes as $cliente) {
                $cid = (int) $cliente['id'];
                $matches[] = [
                    'id'              => $cid,
                    'nombre'          => $cliente['nombre'],
                    'telefono'        => $cliente['telefono'],
                    'email'           => $cliente['email'],
                    'total_visitas'   => (int) $cliente['total_visitas'],
                    'ultima_visita'   => $cliente['ultima_visita'],
                    'total_vehiculos' => (int) $cliente['total_vehiculos'],
                    'vehiculos'       => $vehiculosPorCliente[$cid] ?? [],
                ];
            }

            http_response_code(200);
            echo json_encode(['success' => true, 'matches' => $matches]);

        } catch (Exception $e) {
            error_log('[ClientesController::buscar] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Error en la búsqueda de clientes',
            ]);
        }
    }

    /**
     * PUT /api/clientes/:id
     * Actualiza nombre, teléfono y email del cliente.
     */
    public function update($id) {
        try {
            requireAuth();

            $clienteId = (int) $id;
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['nombre']) || trim($data['nombre']) === '') {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error'   => 'El nombre del cliente es requerido',
                ]);
                return;
            }

            $stmt = $this->db->prepare("
                UPDATE clientes
                SET nombre    = :nombre,
                    telefono  = :telefono,
                    email     = :email
                WHERE id = :id
                  AND activo = 1
            ");
            $stmt->bindValue(':nombre',   trim($data['nombre']),             PDO::PARAM_STR);
            $stmt->bindValue(':telefono', isset($data['telefono']) ? trim($data['telefono']) : null, PDO::PARAM_STR);
            $stmt->bindValue(':email',    isset($data['email'])    ? trim($data['email'])    : null, PDO::PARAM_STR);
            $stmt->bindValue(':id',       $clienteId,                        PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Cliente no encontrado',
                ]);
                return;
            }

            $stmtGet = $this->db->prepare("
                SELECT id, nombre, telefono, email
                FROM clientes
                WHERE id = :id
            ");
            $stmtGet->bindValue(':id', $clienteId, PDO::PARAM_INT);
            $stmtGet->execute();
            $cliente = $stmtGet->fetch(PDO::FETCH_ASSOC);
            $cliente['id'] = (int) $cliente['id'];

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'cliente' => $cliente,
            ]);

        } catch (Exception $e) {
            error_log('[ClientesController::update] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Error al actualizar el cliente',
            ]);
        }
    }

    /**
     * GET /api/clientes/:id
     * Perfil completo: datos del cliente + vehículos con historial de órdenes.
     */
    public function perfil($id) {
        try {
            requireAuth();

            $clienteId = (int) $id;

            // 1. Datos básicos del cliente + métricas
            $stmtC = $this->db->prepare("
                SELECT
                    c.id,
                    c.nombre,
                    c.telefono,
                    c.email,
                    COUNT(DISTINCT o.id)  AS total_visitas,
                    MAX(o.fecha_ingreso)  AS ultima_visita
                FROM clientes c
                LEFT JOIN ordenes_servicio o ON o.cliente_id = c.id
                WHERE c.id = :id
                  AND c.activo = 1
                GROUP BY c.id
            ");
            $stmtC->bindParam(':id', $clienteId, PDO::PARAM_INT);
            $stmtC->execute();
            $cliente = $stmtC->fetch(PDO::FETCH_ASSOC);

            if (!$cliente) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Cliente no encontrado',
                ]);
                return;
            }

            // 2. Resumen financiero del cliente
            $stmtF = $this->db->prepare("
                SELECT
                    COALESCE(SUM(o.total), 0)                    AS total_gastado,
                    COALESCE(SUM(o.subtotal_servicios), 0)
                      + COALESCE(SUM(o.subtotal_mano_obra), 0)   AS total_servicios,
                    COALESCE(SUM(o.subtotal_refacciones), 0)     AS total_refacciones,
                    COALESCE(SUM(o.iva), 0)                      AS total_iva
                FROM ordenes_servicio o
                WHERE o.cliente_id = :cid
            ");
            $stmtF->bindParam(':cid', $clienteId, PDO::PARAM_INT);
            $stmtF->execute();
            $financiero = $stmtF->fetch(PDO::FETCH_ASSOC);

            // 3. Vehículos del cliente
            $stmtV = $this->db->prepare("
                SELECT id, marca, modelo, anio, placas, niv
                FROM vehiculos
                WHERE cliente_id = :cid
                ORDER BY id ASC
            ");
            $stmtV->bindParam(':cid', $clienteId, PDO::PARAM_INT);
            $stmtV->execute();
            $vehiculos = $stmtV->fetchAll(PDO::FETCH_ASSOC);

            // 3. Órdenes por cada vehículo
            $vehiculosConHistorial = [];
            foreach ($vehiculos as $vehiculo) {
                $vehiculoId = (int) $vehiculo['id'];

                $stmtO = $this->db->prepare("
                    SELECT
                        o.id,
                        o.numero_orden,
                        o.fecha_ingreso,
                        o.estado,
                        COALESCE(o.total, 0)          AS total,
                        o.kilometraje_entrada,
                        (
                            SELECT s.descripcion
                            FROM servicios_orden s
                            WHERE s.orden_id = o.id
                            ORDER BY s.id ASC
                            LIMIT 1
                        ) AS servicio_principal
                    FROM ordenes_servicio o
                    WHERE o.vehiculo_id = :vid
                    ORDER BY o.fecha_ingreso DESC
                ");
                $stmtO->bindParam(':vid', $vehiculoId, PDO::PARAM_INT);
                $stmtO->execute();
                $ordenes = $stmtO->fetchAll(PDO::FETCH_ASSOC);

                // Castear tipos
                foreach ($ordenes as &$orden) {
                    $orden['id']    = (int) $orden['id'];
                    $orden['total'] = (float) $orden['total'];
                    $orden['servicio_principal'] = $orden['servicio_principal'] ?? '';
                    $orden['kilometraje_entrada'] = $orden['kilometraje_entrada'] ?? null;
                }
                unset($orden);

                $vehiculosConHistorial[] = [
                    'id'      => $vehiculoId,
                    'marca'   => $vehiculo['marca'],
                    'modelo'  => $vehiculo['modelo'],
                    'anio'    => $vehiculo['anio'],
                    'placas'  => $vehiculo['placas'],
                    'niv'     => $vehiculo['niv'],
                    'ordenes' => $ordenes,
                ];
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'cliente' => [
                    'id'            => (int) $cliente['id'],
                    'nombre'        => $cliente['nombre'],
                    'telefono'      => $cliente['telefono'],
                    'email'         => $cliente['email'],
                    'total_visitas' => (int) $cliente['total_visitas'],
                    'ultima_visita' => $cliente['ultima_visita'],
                ],
                'resumen_financiero' => [
                    'total_gastado'    => (float) $financiero['total_gastado'],
                    'total_servicios'  => (float) $financiero['total_servicios'],
                    'total_refacciones'=> (float) $financiero['total_refacciones'],
                    'total_iva'        => (float) $financiero['total_iva'],
                ],
                'vehiculos' => $vehiculosConHistorial,
            ]);

        } catch (Exception $e) {
            error_log('[ClientesController::perfil] ERROR: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error'   => 'Error al obtener perfil del cliente',
            ]);
        }
    }
}
