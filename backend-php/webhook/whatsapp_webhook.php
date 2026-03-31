<?php
/**
 * WhatsApp Webhook - SAG Garage
 * Versión simplificada y funcional para cPanel
 * 
 * @author Marco Candiani
 * @version 2.0
 * @date 31/03/2026
 */

// Headers básicos
header('Content-Type: text/plain');
date_default_timezone_set('America/Mexico_City');

// Función simple de logging
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logFile = __DIR__ . '/../../logs/whatsapp_webhook.log';
    
    // Crear directorio logs si no existe
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $logEntry = "[{$timestamp}] {$message}\n";
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

try {
    logMessage("=== WEBHOOK START ===");
    logMessage("Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
    logMessage("Query: " . ($_SERVER['QUERY_STRING'] ?? 'NONE'));
    
    // Cargar configuración
    require_once __DIR__ . '/../config/database.php';
    
    // Conectar a base de datos
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    if (!$db) {
        logMessage("ERROR: No se pudo conectar a la base de datos");
        http_response_code(500);
        echo "Database connection failed";
        exit;
    }
    
    logMessage("BD conectada exitosamente");
    
    // MANEJAR VERIFICACIÓN (GET)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $hubMode = $_GET['hub_mode'] ?? '';
        $hubChallenge = $_GET['hub_challenge'] ?? '';
        $hubVerifyToken = $_GET['hub_verify_token'] ?? '';
        
        logMessage("Verificación - Mode: {$hubMode}, Challenge: {$hubChallenge}, Token: " . substr($hubVerifyToken, 0, 10) . "...");
        
        // Obtener token esperado de la BD
        $query = "SELECT valor FROM whatsapp_config WHERE clave = 'webhook_token' LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            logMessage("ERROR: No se encontró webhook_token en BD");
            http_response_code(500);
            echo "Webhook token not configured";
            exit;
        }
        
        $tokenEsperado = $result['valor'];
        logMessage("Token esperado: " . substr($tokenEsperado, 0, 10) . "...");
        
        // Verificar token
        if ($hubMode === 'subscribe' && $hubVerifyToken === $tokenEsperado) {
            logMessage("ÉXITO: Verificación exitosa");
            echo $hubChallenge;
            exit;
        } else {
            logMessage("ERROR: Verificación fallida");
            logMessage("- hub_mode recibido: '{$hubMode}' (esperado: 'subscribe')");
            logMessage("- Token coincide: " . ($hubVerifyToken === $tokenEsperado ? 'SÍ' : 'NO'));
            http_response_code(403);
            echo "Verification failed";
            exit;
        }
    }
    
    // MANEJAR ACTUALIZACIONES (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        logMessage("POST recibido - procesando actualizaciones");
        
        $payload = file_get_contents('php://input');
        logMessage("Payload size: " . strlen($payload));
        
        if (empty($payload)) {
            logMessage("ERROR: Payload vacío");
            http_response_code(400);
            echo "Empty payload";
            exit;
        }
        
        $data = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            logMessage("ERROR: JSON inválido - " . json_last_error_msg());
            http_response_code(400);
            echo "Invalid JSON";
            exit;
        }
        
        logMessage("JSON válido - estructura: " . implode(', ', array_keys($data)));
        
        // Log básico del webhook para análisis
        $logQuery = "INSERT INTO whatsapp_logs (
            cliente_id, 
            telefono, 
            mensaje, 
            estado, 
            metadata, 
            created_at
        ) VALUES (0, 'webhook', ?, 'webhook_received', ?, NOW())";
        
        $stmt = $db->prepare($logQuery);
        $stmt->execute([
            'Webhook recibido: ' . date('Y-m-d H:i:s'),
            json_encode($data)
        ]);
        
        logMessage("Webhook guardado en BD");
        
        // Responder éxito a Facebook
        echo "OK";
        logMessage("Respuesta enviada: OK");
        exit;
    }
    
    // Método no soportado
    logMessage("ERROR: Método no soportado - " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    echo "Method not allowed";
    exit;
    
} catch (Exception $e) {
    logMessage("EXCEPCIÓN FATAL: " . $e->getMessage());
    logMessage("Archivo: " . $e->getFile() . " Línea: " . $e->getLine());
    
    http_response_code(500);
    echo "Internal server error";
    exit;
}

logMessage("=== WEBHOOK END ===");
?>