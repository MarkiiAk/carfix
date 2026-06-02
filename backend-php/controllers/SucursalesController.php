<?php
/**
 * SucursalesController — CRUD de sucursales
 * Todos los métodos requieren rol 'sistemas'.
 *
 * Rutas:
 *   GET    /admin/sucursales
 *   POST   /admin/sucursales
 *   PUT    /admin/sucursales/:id
 *   DELETE /admin/sucursales/:id  (soft delete — activo=0)
 */

class SucursalesController {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // -------------------------------------------------------------------------
    // GET /admin/sucursales
    // -------------------------------------------------------------------------
    public function listar(): void {
        try {
            requireAuth(['sistemas', 'superusuario']);

            $stmt = $this->db->prepare(
                'SELECT id, nombre, direccion, telefono, activo, created_at, updated_at
                 FROM sucursales
                 ORDER BY id ASC'
            );
            $stmt->execute();
            $sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($sucursales as &$s) {
                $s['id']     = (int) $s['id'];
                $s['activo'] = (bool) $s['activo'];
            }
            unset($s);

            echo json_encode(['success' => true, 'sucursales' => $sucursales]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // POST /admin/sucursales
    // -------------------------------------------------------------------------
    public function crear(): void {
        try {
            requireAuth(['sistemas']);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $nombre = trim($data['nombre'] ?? '');
            if ($nombre === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'nombre es requerido']);
                return;
            }

            $stmt = $this->db->prepare(
                'INSERT INTO sucursales (nombre, direccion, telefono)
                 VALUES (?, ?, ?)'
            );
            $stmt->execute([
                $nombre,
                trim($data['direccion'] ?? '') ?: null,
                trim($data['telefono']  ?? '') ?: null,
            ]);

            $id = (int) $this->db->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success'    => true,
                'sucursal'   => ['id' => $id, 'nombre' => $nombre],
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /admin/sucursales/:id
    // -------------------------------------------------------------------------
    public function actualizar(int $id): void {
        try {
            requireAuth(['sistemas']);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $nombre    = trim($data['nombre']    ?? '');
            $direccion = trim($data['direccion'] ?? '');
            $telefono  = trim($data['telefono']  ?? '');

            if ($nombre === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'nombre es requerido']);
                return;
            }

            $stmt = $this->db->prepare(
                'UPDATE sucursales
                 SET nombre = ?, direccion = ?, telefono = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $nombre,
                $direccion ?: null,
                $telefono  ?: null,
                $id,
            ]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Sucursal no encontrada']);
                return;
            }

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /admin/sucursales/:id  — soft delete, bloquea si tiene órdenes activas
    // -------------------------------------------------------------------------
    public function eliminar(int $id): void {
        try {
            requireAuth(['sistemas']);

            // Verificar que no tenga órdenes activas ('abierta' o 'pendiente')
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM ordenes_servicio
                 WHERE sucursal_id = ? AND estado IN ('abierta', 'pendiente')"
            );
            $stmt->execute([$id]);
            $activas = (int) $stmt->fetchColumn();

            if ($activas > 0) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error'   => "No se puede desactivar: tiene {$activas} orden(es) activa(s)",
                ]);
                return;
            }

            $stmt = $this->db->prepare(
                'UPDATE sucursales SET activo = 0 WHERE id = ?'
            );
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Sucursal no encontrada']);
                return;
            }

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
