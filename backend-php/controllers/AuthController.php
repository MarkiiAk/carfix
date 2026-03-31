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
            
            // Log para debug
            
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
                http_response_code(401);
                echo json_encode(['error' => 'Credenciales inválidas']);
                return;
            }
            
            
            // Generar token JWT
            $payload = [
                'userId' => $user['id'],
                'email' => $user['email'],
                'username' => $user['username'] ?? $user['email'],
                'role' => $user['rol'], // El campo es 'rol' en la tabla usuarios
                'iat' => time(),
                'exp' => time() + (24 * 60 * 60) // 24 horas
            ];
            
            $token = JWT::encode($payload);
            
            // Preparar respuesta
            $response = [
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'username' => $user['username'] ?? $user['email'],
                    'name' => $user['nombre_completo'] ?? $user['username'] ?? $user['email'],
                    'role' => $user['rol'] // El campo es 'rol' en la tabla usuarios
                ]
            ];
            
            echo json_encode($response);
            
        } catch (Exception $e) {
            error_log('LOGIN EXCEPTION - ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => 'Error al procesar login',
                'message' => $e->getMessage()
            ]);
        }
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
            error_log('VERIFY ERROR: ' . $e->getMessage());
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
            http_response_code(500);
            echo json_encode([
                'error' => 'Error al obtener usuario',
                'message' => $e->getMessage()
            ]);
        }
    }
}
