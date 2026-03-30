<?php
/**
 * WhatsApp Webhook - SAG Garage
 * 
 * Endpoint para recibir actualizaciones de estado de mensajes de WhatsApp
 * Se debe configurar en Meta Developer Console
 * 
 * URL del webhook: https://tu-dominio.com/backend-php/webhook/whatsapp_webhook.php
 * 
 * @author Marco Candiani
 * @version 1.0
 * @date 30/03/2026
 */

// Configurar headers de respuesta
header('Content-Type: application/json');

// Configurar timezone
date_default_timezone_set('America/Mexico_City');

// Función para logging específico de webhook
function logWebhook($message, $level = 'INFO', $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = [
        'timestamp' => $timestamp,
        'level' => $level,
        'message' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    if ($data !== null) {
        $logEntry['data'] = $data;
    }
    
    $logMessage = "[{$timestamp}] [{$level}] WEBHOOK: {$message}\n";
    
    // Log a archivo específico
    file_put_contents('/var/log/sag_whatsapp_webhook.log', $logMessage, FILE_APPEND | LOCK_EX);
    
    // Log detallado en formato JSON para análisis
    if ($data !== null) {
        $detailedLog = json_encode($logEntry) . "\n";
        file_put_contents('/var/log/sag_whatsapp_webhook_detailed.log', $detailedLog, FILE_APPEND | LOCK_EX);
    }
}

// Función para respuesta JSON
function jsonResponse($data, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($data);
    exit;
}

// Función para validar IP de Facebook/Meta (opcional pero recomendado)
function validarIPFacebook($ip) {
    // Rangos de IP de Facebook (actualizar según documentación oficial)
    $facebookRanges = [
        '31.13.24.0/21',
        '31.13.64.0/18',
        '66.220.144.0/20',
        '69.63.176.0/20',
        '69.171.224.0/19',
        '74.119.76.0/22',
        '103.4.96.0/22',
        '173.252.64.0/19',
        '173.252.96.0/19',
        '179.60.192.0/22',
        '185.60.216.0/22',
        '204.15.20.0/22'
    ];
    
    foreach ($facebookRanges as $range) {
        if (ipInRange($ip, $range)) {
            return true;
        }
    }
    
    return false;
}

// Función auxiliar para verificar IP en rango
function ipInRange($ip, $range) {
    list($subnet, $bits) = explode('/', $range);
    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);
    $subnet &= $mask;
    return ($ip & $mask) == $subnet;
}

try {
    logWebhook("Webhook recibido - " . $_SERVER['REQUEST_METHOD']);
    
    // Cargar dependencias
    require_once dirname(__FILE__) . '/../config/database.php';
    require_once dirname(__FILE__) . '/../controllers/WhatsappController.php';
    
    // Conectar a base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        logWebhook("Error: No se pudo conectar a la base de datos", 'ERROR');
        jsonResponse(['error' => 'Database connection failed'], 500);
    }
    
    // Crear controlador
    $whatsappController = new WhatsappController($db);
    
    // Manejar verificación del webhook (GET request)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $hubMode = $_GET['hub_mode'] ?? '';
        $hubChallenge = $_GET['hub_challenge'] ?? '';
        $hubVerifyToken = $_GET['hub_verify_token'] ?? '';
        
        logWebhook("Verificación de webhook recibida", 'INFO', [
            'hub_mode' => $hubMode,
            'hub_verify_token' => substr($hubVerifyToken, 0, 10) . '...' // Solo primeros caracteres por seguridad
        ]);
        
        // Verificar el token
        $verificacion = $whatsappController->testConexion();
        if (!$verificacion['success']) {
            logWebhook("Error en configuración de WhatsApp API", 'ERROR');
            jsonResponse(['error' => 'API configuration error'], 500);
        }
        
        // Obtener configuración del webhook
        $tokenEsperado = $whatsappController->getConfig('webhook_token');
        
        if (empty($tokenEsperado)) {
            logWebhook("Token de webhook no configurado", 'ERROR');
            jsonResponse(['error' => 'Webhook token not configured'], 500);
        }
        
        if ($hubMode === 'subscribe' && $hubVerifyToken === $tokenEsperado) {
            logWebhook("Verificación exitosa - webhook configurado", 'INFO');
            echo $hubChallenge;
            exit;
        } else {
            logWebhook("Verificación fallida - token incorrecto", 'ERROR');
            jsonResponse(['error' => 'Invalid verification token'], 403);
        }
    }
    
    // Manejar actualizaciones del webhook (POST request)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validar IP de origen (opcional)
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($clientIP) && $whatsappController->getConfig('validar_ip_facebook', false)) {
            if (!validarIPFacebook($clientIP)) {
                logWebhook("IP no autorizada: {$clientIP}", 'WARNING');
                jsonResponse(['error' => 'Unauthorized IP'], 403);
            }
        }
        
        // Leer el payload
        $payload = file_get_contents('php://input');
        
        if (empty($payload)) {
            logWebhook("Payload vacío recibido", 'WARNING');
            jsonResponse(['error' => 'Empty payload'], 400);
        }
        
        // Validar JSON
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            logWebhook("JSON inválido: " . json_last_error_msg(), 'ERROR');
            jsonResponse(['error' => 'Invalid JSON'], 400);
        }
        
        logWebhook("Payload recibido", 'INFO', [
            'size' => strlen($payload),
            'structure' => array_keys($data)
        ]);
        
        // Procesar el webhook
        $resultado = $whatsappController->procesarWebhook($data);
        
        if ($resultado['success']) {
            $procesados = $resultado['procesados'] ?? 0;
            logWebhook("Webhook procesado exitosamente - {$procesados} actualizaciones", 'INFO');
            
            // Log de detalles si hay actualizaciones
            if ($procesados > 0 && isset($resultado['updates'])) {
                foreach ($resultado['updates'] as $update) {
                    $tipo = $update['type'] ?? 'unknown';
                    $messageId = $update['message_id'] ?? 'unknown';
                    logWebhook("Actualización: {$tipo} para mensaje {$messageId}", 'INFO');
                }
            }
            
            jsonResponse(['status' => 'success', 'processed' => $procesados]);
            
        } else {
            $error = $resultado['error'] ?? 'Unknown error';
            logWebhook("Error procesando webhook: {$error}", 'ERROR');
            jsonResponse(['error' => $error], 500);
        }
    }
    
    // Método no soportado
    logWebhook("Método HTTP no soportado: " . $_SERVER['REQUEST_METHOD'], 'WARNING');
    jsonResponse(['error' => 'Method not allowed'], 405);
    
} catch (PDOException $e) {
    logWebhook("Error de base de datos: " . $e->getMessage(), 'FATAL');
    jsonResponse(['error' => 'Database error'], 500);
    
} catch (Exception $e) {
    logWebhook("Error general: " . $e->getMessage(), 'FATAL', [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    jsonResponse(['error' => 'Internal server error'], 500);
    
} catch (Error $e) {
    logWebhook("Error fatal de PHP: " . $e->getMessage(), 'FATAL');
    jsonResponse(['error' => 'Fatal error'], 500);
}

// Esta línea no debería ejecutarse nunca
logWebhook("WARNING: Final del script alcanzado sin respuesta", 'WARNING');
jsonResponse(['error' => 'Unexpected end of script'], 500);