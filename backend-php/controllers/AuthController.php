<?php
/**
 * Controlador de autenticación
 */

class AuthController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Login - POST /api/auth/login
     */
    public function login() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validar que el JSON sea un array (no nulo ni mal formado)
            if (!is_array($data)) {
                http_response_code(400);
                echo json_encode(['error' => 'JSON inválido o malformado']);
                return;
            }

            // Aceptar tanto 'username' como 'email'
            $username = $data['username'] ?? $data['email'] ?? null;
            $password = $data['password'] ?? null;
            
            if (!$username || !$password) {
                http_response_code(400);
                echo json_encode(['error' => 'Usuario/email y contraseña son requeridos']);
                return;
            }
            
            
            // Buscar usuario por username o email
            $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Credenciales inválidas']);
                return;
            }
            
            
            // Verificar contraseña (el campo es password_hash en la tabla usuarios)
            if (!password_verify($password, $user['password_hash'])) {
                sleep(1); // delay mínimo anti-fuerza-bruta en contraseña incorrecta
                http_response_code(401);
                echo json_encode(['error' => 'Credenciales inválidas']);
                return;
            }

            // Normalizar rol legacy
            $rol = $user['rol'];
            if ($rol === 'admin') {
                $rol = 'superusuario';
            }

            // Determinar sucursales permitidas según rol
            [$sucursalActivaId, $sucursalesPermitidas] = $this->getSucursalesParaUsuario($user['id'], $rol);

            // Cargar nombre de sucursal activa (antes del token para incluirlo en el payload)
            $sucursalNombre = $this->getNombreSucursal($sucursalActivaId);

            // Generar token JWT
            $payload = [
                'userId'                => $user['id'],
                'email'                 => $user['email'],
                'username'              => $user['username'] ?? $user['email'],
                'rol'                   => $rol,
                'sucursal_activa_id'    => $sucursalActivaId,
                'sucursal_nombre'       => $sucursalNombre,
                'sucursales_permitidas' => $sucursalesPermitidas,
                'iat'                   => time(),
                'exp'                   => time() + (24 * 60 * 60)
            ];

            $token = JWT::encode($payload);

            // Preparar respuesta
            $response = [
                'token' => $token,
                'user'  => [
                    'id'       => $user['id'],
                    'email'    => $user['email'],
                    'username' => $user['username'] ?? $user['email'],
                    'nombre'   => $user['nombre_completo'] ?? $user['username'] ?? $user['email'],
                    'rol'      => $rol,
                    'sucursal_activa_id'    => $sucursalActivaId,
                    'sucursales_permitidas' => $sucursalesPermitidas,
                    'sucursal_nombre'       => $sucursalNombre,
                ]
            ];

            echo json_encode($response);
            
        } catch (Exception $e) {
            jsonError('Error al procesar login', $e, 500);
        }
    }

    /**
     * Logout - POST /api/auth/logout
     *
     * JWT es stateless — el cliente elimina el token localmente.
     * Este endpoint existe para: (1) no dar 404, (2) punto de extensión
     * para blacklist de tokens en el futuro si se necesita.
     * Nota: auth/logout está en $rutasPublicas del router para permitir que
     * tokens expirados puedan hacer logout sin ser bloqueados por el middleware.
     * Sin embargo, si el token es válido se confirma con 200; si es inválido
     * requireAuth() lanza excepción y retornamos 401 de todas formas — es el
     * comportamiento correcto.
     */
    public function logout(): void {
        try {
            requireAuth(); // Validar que el token es válido antes de procesar
            http_response_code(200);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            // Token inválido o expirado — el cliente debe eliminar el token igualmente
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Token inválido o expirado']);
        }
    }

    /**
     * Cambiar sucursal activa — POST /api/auth/switch-sucursal
     * Genera un nuevo token con sucursal_activa_id actualizado.
     * El exp del token nuevo es el MISMO que el del token actual (no reinicia el timer).
     */
    public function switchSucursal() {
        try {
            $userData = requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);

            $sucursalId = isset($data['sucursal_id']) ? (int) $data['sucursal_id'] : 0;
            if ($sucursalId <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'sucursal_id es requerido']);
                return;
            }

            // Verificar que la sucursal esté en el token actual
            $permitidas = $userData['sucursales_permitidas'] ?? [1];
            if (!in_array($sucursalId, $permitidas, true)) {
                http_response_code(403);
                echo json_encode(['error' => 'No tienes acceso a esa sucursal']);
                return;
            }

            // Generar nuevo token manteniendo el exp original
            $sucursalNombre = $this->getNombreSucursal($sucursalId);
            $nuevoPayload = $userData;
            $nuevoPayload['sucursal_activa_id'] = $sucursalId;
            $nuevoPayload['sucursal_nombre']    = $sucursalNombre;

            $nuevoToken = JWT::encode($nuevoPayload);

            echo json_encode([
                'token'    => $nuevoToken,
                'sucursal' => [
                    'id'     => $sucursalId,
                    'nombre' => $sucursalNombre,
                ]
            ]);

        } catch (Exception $e) {
            jsonError('Error al cambiar sucursal', $e, 500);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers privados
    // -------------------------------------------------------------------------

    /**
     * Retorna [sucursal_activa_id, sucursales_permitidas[]] para un usuario.
     * - sistemas/superusuario: acceso a TODAS las sucursales activas.
     * - admin_sucursal: solo las asignadas en usuario_sucursales.
     */
    private function getSucursalesParaUsuario(int $userId, string $rol): array {
        if (in_array($rol, ['sistemas', 'superusuario'], true)) {
            $stmt = $this->db->prepare('SELECT id FROM sucursales WHERE activo = 1 ORDER BY id ASC');
            $stmt->execute();
            $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
            $ids = array_map('intval', $ids);
            if (empty($ids)) {
                $ids = [1];
            }
            return [(int) $ids[0], $ids];
        }

        // admin_sucursal / asistente — solo sus sucursales asignadas en usuario_sucursales
        $stmt = $this->db->prepare(
            'SELECT sucursal_id FROM usuario_sucursales WHERE usuario_id = ? ORDER BY sucursal_id ASC'
        );
        $stmt->execute([$userId]);
        $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'sucursal_id');
        $ids = array_map('intval', $ids);
        if (empty($ids)) {
            $ids = [1];
        }
        return [(int) $ids[0], $ids];
    }

    /**
     * Devuelve el nombre de una sucursal por su ID.
     * Retorna 'Desconocida' si no existe.
     */
    private function getNombreSucursal(int $sucursalId): string {
        $stmt = $this->db->prepare('SELECT nombre FROM sucursales WHERE id = ? LIMIT 1');
        $stmt->execute([$sucursalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['nombre'] : 'Desconocida';
    }

    /**
     * Verificar token - GET /api/auth/verify
     */
    public function verify() {
        try {
            $userData = requireAuth();
            
            // Obtener usuario actualizado de la base de datos
            $stmt = $this->db->prepare('SELECT id, email, username, nombre_completo, rol FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$userData['userId']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado']);
                return;
            }
            
            
            // Retornar información del usuario
            echo json_encode([
                'valid' => true,
                'user' => [
                    'id' => (string)$user['id'],
                    'email' => $user['email'],
                    'username' => $user['username'] ?? $user['email'],
                    'nombre' => $user['nombre_completo'] ?? $user['username'] ?? $user['email'],
                    'rol' => $user['rol']
                ]
            ]);
            
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'valid' => false,
                'error' => 'Token inválido o expirado'
            ]);
        }
    }
    
    /**
     * Obtener usuario actual - GET /api/auth/me
     */
    public function me() {
        try {
            $userData = requireAuth();
            
            // Obtener usuario actualizado de la base de datos
            $stmt = $this->db->prepare('SELECT id, email, nombre_completo, rol FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$userData['userId']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado']);
                return;
            }
            
            echo json_encode(['user' => $user]);
            
        } catch (Exception $e) {
            error_log('[AuthController::me] ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Error al obtener datos del usuario']);
        }
    }
}
