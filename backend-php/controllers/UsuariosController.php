<?php
/**
 * UsuariosController — CRUD de usuarios del sistema
 * Todos los métodos requieren rol 'sistemas'.
 *
 * Rutas:
 *   GET    /admin/usuarios
 *   POST   /admin/usuarios
 *   PUT    /admin/usuarios/:id
 *   DELETE /admin/usuarios/:id              (soft delete — activo=0)
 *   POST   /admin/usuarios/:id/sucursal     (asignar sucursal)
 *   DELETE /admin/usuarios/:id/sucursal/:sid (remover acceso)
 */

class UsuariosController {

    private PDO $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // -------------------------------------------------------------------------
    // GET /admin/usuarios
    // -------------------------------------------------------------------------
    public function listar(): void {
        try {
            requireAuth(['sistemas']);

            $stmt = $this->db->prepare(
                "SELECT u.id, u.username, u.nombre_completo, u.email, u.rol, u.activo,
                        u.fecha_creacion,
                        GROUP_CONCAT(us.sucursal_id ORDER BY us.sucursal_id SEPARATOR ',') AS sucursal_ids,
                        GROUP_CONCAT(s.nombre       ORDER BY us.sucursal_id SEPARATOR '|') AS sucursal_nombres
                 FROM usuarios u
                 LEFT JOIN usuario_sucursales us ON us.usuario_id = u.id
                 LEFT JOIN sucursales s          ON s.id = us.sucursal_id
                 GROUP BY u.id
                 ORDER BY u.id ASC"
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $usuarios = array_map(function ($u) {
                $ids     = $u['sucursal_ids']    ? array_map('intval', explode(',', $u['sucursal_ids']))    : [];
                $nombres = $u['sucursal_nombres'] ? explode('|', $u['sucursal_nombres']) : [];

                $sucursales = [];
                foreach ($ids as $i => $sid) {
                    $sucursales[] = ['id' => $sid, 'nombre' => $nombres[$i] ?? ''];
                }

                return [
                    'id'              => (int) $u['id'],
                    'username'        => $u['username'],
                    'nombre_completo' => $u['nombre_completo'],
                    'email'           => $u['email'],
                    'rol'             => $u['rol'],
                    'activo'          => (bool) $u['activo'],
                    'fecha_creacion'  => $u['fecha_creacion'],
                    'sucursales'      => $sucursales,
                ];
            }, $rows);

            echo json_encode(['success' => true, 'usuarios' => $usuarios]);

        } catch (Exception $e) {
            jsonError('Error al obtener usuarios', $e, 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /admin/usuarios
    // -------------------------------------------------------------------------
    public function crear(): void {
        try {
            requireAuth(['sistemas']);
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'JSON inválido o malformado']);
                return;
            }

            $username = trim($data['username'] ?? '');
            $password = $data['password'] ?? '';
            $nombre   = trim($data['nombre_completo'] ?? '');
            $email    = trim($data['email'] ?? '');
            $rol      = $data['rol'] ?? 'admin_sucursal';

            // Validaciones básicas
            if ($username === '' || $password === '') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'username y password son requeridos']);
                return;
            }

            $rolesValidos = ['sistemas', 'superusuario', 'admin_sucursal', 'asistente'];
            if (!in_array($rol, $rolesValidos, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'rol no válido. Use: ' . implode(', ', $rolesValidos)]);
                return;
            }

            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $this->db->prepare(
                'INSERT INTO usuarios (username, password_hash, nombre_completo, email, rol)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$username, $passwordHash, $nombre ?: null, $email ?: null, $rol]);
            $id = (int) $this->db->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'usuario' => ['id' => $id, 'username' => $username, 'rol' => $rol],
            ]);

        } catch (Exception $e) {
            // Clave duplicada (username/email) — este mensaje es seguro exponer al cliente
            if (str_contains($e->getMessage(), '1062')) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'username o email ya existe']);
                return;
            }
            jsonError('Error al crear usuario', $e, 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /admin/usuarios/:id
    // -------------------------------------------------------------------------
    public function actualizar(int $id): void {
        try {
            requireAuth(['sistemas']);
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'JSON inválido o malformado']);
                return;
            }

            $nombre = trim($data['nombre_completo'] ?? '');
            $email  = trim($data['email'] ?? '');
            $rol    = $data['rol'] ?? '';

            $rolesValidos = ['sistemas', 'superusuario', 'admin_sucursal', 'asistente'];
            if ($rol !== '' && !in_array($rol, $rolesValidos, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'rol no válido']);
                return;
            }

            // Construir SET dinámico solo con campos enviados
            $setClauses = [];
            $params     = [];

            if ($nombre !== '') {
                $setClauses[] = 'nombre_completo = ?';
                $params[]     = $nombre;
            }
            if ($email !== '') {
                $setClauses[] = 'email = ?';
                $params[]     = $email;
            }
            if ($rol !== '') {
                $setClauses[] = 'rol = ?';
                $params[]     = $rol;
            }
            if (isset($data['activo'])) {
                $setClauses[] = 'activo = ?';
                $params[]     = $data['activo'] ? 1 : 0;
            }

            if (empty($setClauses)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nada que actualizar']);
                return;
            }

            $params[] = $id;
            $sql      = 'UPDATE usuarios SET ' . implode(', ', $setClauses) . ' WHERE id = ?';
            $stmt     = $this->db->prepare($sql);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
                return;
            }

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            jsonError('Error al actualizar usuario', $e, 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /admin/usuarios/:id  — soft delete
    // -------------------------------------------------------------------------
    public function eliminar(int $id): void {
        try {
            requireAuth(['sistemas']);

            $stmt = $this->db->prepare('UPDATE usuarios SET activo = 0 WHERE id = ?');
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
                return;
            }

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            jsonError('Error al eliminar usuario', $e, 500);
        }
    }

    // -------------------------------------------------------------------------
    // POST /admin/usuarios/:id/sucursal — asignar sucursal
    // -------------------------------------------------------------------------
    public function asignarSucursal(int $userId): void {
        try {
            requireAuth(['sistemas']);
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            $sucursalId  = (int) ($data['sucursal_id'] ?? 0);
            $rolSucursal = $data['rol_sucursal'] ?? 'admin_sucursal';

            if ($sucursalId <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'sucursal_id es requerido']);
                return;
            }

            $stmt = $this->db->prepare(
                'INSERT INTO usuario_sucursales (usuario_id, sucursal_id, rol_sucursal)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE rol_sucursal = VALUES(rol_sucursal)'
            );
            $stmt->execute([$userId, $sucursalId, $rolSucursal]);

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            jsonError('Error al asignar sucursal', $e, 500);
        }
    }

    // -------------------------------------------------------------------------
    // PUT /admin/usuarios/:id/password — cambiar contraseña
    // -------------------------------------------------------------------------
    public function cambiarPassword(int $id): void {
        try {
            requireAuth(['sistemas']);
            $data = json_decode(file_get_contents('php://input'), true);
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'JSON inválido']);
                return;
            }

            $nueva = $data['nueva_password'] ?? '';
            if (strlen($nueva) < 6) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']);
                return;
            }

            $hash = password_hash($nueva, PASSWORD_BCRYPT);
            $stmt = $this->db->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?');
            $stmt->execute([$hash, $id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
                return;
            }

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            jsonError('Error al cambiar contraseña', $e, 500);
        }
    }

    // -------------------------------------------------------------------------
    // DELETE /admin/usuarios/:id/sucursal/:sucursal_id — remover acceso
    // -------------------------------------------------------------------------
    public function removerSucursal(int $userId, int $sucursalId): void {
        try {
            requireAuth(['sistemas']);

            $stmt = $this->db->prepare(
                'DELETE FROM usuario_sucursales WHERE usuario_id = ? AND sucursal_id = ?'
            );
            $stmt->execute([$userId, $sucursalId]);

            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            jsonError('Error al remover acceso a sucursal', $e, 500);
        }
    }
}
