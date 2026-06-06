<?php
/**
 * Utilidades JWT para autenticación
 * Implementación simple sin dependencias externas
 */

class JWT {
    private static $algorithm = 'HS256';

    /**
     * Lee el secret JWT desde el entorno.
     * Lanza excepción si no está configurado — nunca usa un fallback hardcodeado.
     */
    private static function getSecretKey(): string {
        $secret = $_ENV['JWT_SECRET'] ?? null;
        if (!$secret) {
            throw new Exception('JWT_SECRET no configurado en el entorno');
        }
        return $secret;
    }

    /**
     * Genera un token JWT
     */
    public static function encode($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => self::$algorithm]);
        $payload = json_encode($payload);

        $base64UrlHeader = self::base64UrlEncode($header);
        $base64UrlPayload = self::base64UrlEncode($payload);

        $signature = hash_hmac(
            'sha256',
            $base64UrlHeader . "." . $base64UrlPayload,
            self::getSecretKey(),
            true
        );
        $base64UrlSignature = self::base64UrlEncode($signature);

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    /**
     * Decodifica y verifica un token JWT
     */
    public static function decode($jwt) {
        $tokenParts = explode('.', $jwt);

        if (count($tokenParts) !== 3) {
            throw new Exception('Token inválido');
        }

        list($header, $payload, $signature) = $tokenParts;

        // Verificar firma
        $expectedSignature = hash_hmac(
            'sha256',
            $header . "." . $payload,
            self::getSecretKey(),
            true
        );
        $expectedSignature = self::base64UrlEncode($expectedSignature);
        
        if ($signature !== $expectedSignature) {
            throw new Exception('Firma inválida');
        }
        
        // Decodificar payload
        $payload = json_decode(self::base64UrlDecode($payload), true);
        
        // Verificar expiración
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('Token expirado');
        }
        
        return $payload;
    }
    
    /**
     * Obtiene el token del header Authorization
     */
    public static function getBearerToken() {
        $headers = self::getAuthorizationHeader();
        
        if (!empty($headers)) {
            if (preg_match('/Bearer\s+(.*)$/i', $headers, $matches)) {
                return $matches[1];
            }
        }
        
        return null;
    }
    
    /**
     * Obtiene el header Authorization
     */
    private static function getAuthorizationHeader() {
        $headers = null;
        
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values($requestHeaders)
            );
            
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        
        return $headers;
    }
    
    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
    
    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode($data) {
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}

/**
 * Middleware para verificar autenticación.
 *
 * @param array $rolesPermitidos  Lista de roles con acceso. Vacío = cualquier rol autenticado.
 * @return array  Payload completo del JWT (incluye sucursal_activa_id, rol, userId, etc.)
 */
function requireAuth(array $rolesPermitidos = []): array {
    try {
        $token = JWT::getBearerToken();

        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'No autorizado - Token no proporcionado']);
            exit();
        }

        $payload = JWT::decode($token);

        // Retrocompatibilidad: tokens generados antes de multi-sucursal no tienen estos campos.
        if (!isset($payload['sucursal_activa_id'])) {
            $payload['sucursal_activa_id'] = 1;
        }
        if (!isset($payload['sucursales_permitidas'])) {
            $payload['sucursales_permitidas'] = [1];
        }
        // Retrocompatibilidad: rol 'admin' antiguo se trata como superusuario.
        if (($payload['rol'] ?? '') === 'admin') {
            $payload['rol'] = 'superusuario';
        }

        if (!empty($rolesPermitidos) && !in_array($payload['rol'] ?? '', $rolesPermitidos, true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Sin permisos para esta acción']);
            exit();
        }

        return $payload;

    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado - ' . $e->getMessage()]);
        exit();
    }
}