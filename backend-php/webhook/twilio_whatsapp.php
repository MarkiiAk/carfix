<?php
/**
 * WEBHOOK BIDIRECCIONAL TWILIO WHATSAPP - SAG GARAGE
 * Procesador de mensajes entrantes y salientes
 * 
 * Flujo:
 * 1. Recibe webhook de Twilio cuando cliente responde
 * 2. Identifica a qué alerta pertenece la respuesta
 * 3. Procesa según el estado actual de la conversación
 * 4. Envía respuesta automática correspondiente
 * 
 * @author Sistema SAG Garage
 * @version 2.0.0
 * @date 01/04/2026
 */

// Configurar respuesta
header('Content-Type: text/plain');
header('Cache-Control: no-cache');

// Configurar zona horaria y errores
date_default_timezone_set('America/Mexico_City');
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en webhook
ini_set('log_errors', 1);

// Función de logging
function logWebhook($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [WEBHOOK-{$level}] {$message}\n";
    
    // Escribir a archivo de log
    $logPath = dirname(__FILE__) . '/../../logs/twilio_webhook.log';
    $logDir = dirname($logPath);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
    
    // También a error_log
    error_log("TwilioWebhook: {$message}");
}

try {
    logWebhook("=== WEBHOOK TWILIO RECIBIDO ===");
    
    // Verificar método HTTP
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logWebhook("Método HTTP inválido: " . $_SERVER['REQUEST_METHOD'], 'ERROR');
        http_response_code(405);
        echo "Method Not Allowed";
        exit;
    }
    
    // Obtener datos del webhook
    $webhookData = $_POST;
    
    if (empty($webhookData)) {
        logWebhook("No se recibieron datos POST", 'ERROR');
        http_response_code(400);
        echo "Bad Request: No POST data";
        exit;
    }
    
    logWebhook("Datos recibidos: " . json_encode($webhookData));
    
    // Extraer información clave
    $messageSid = $webhookData['MessageSid'] ?? $webhookData['SmsMessageSid'] ?? '';
    $from = $webhookData['From'] ?? '';
    $to = $webhookData['To'] ?? '';
    $body = $webhookData['Body'] ?? '';
    $messageStatus = $webhookData['MessageStatus'] ?? $webhookData['SmsStatus'] ?? '';
    
    // Validar datos mínimos
    if (empty($messageSid) || empty($from)) {
        logWebhook("Datos insuficientes - MessageSid: {$messageSid}, From: {$from}", 'ERROR');
        http_response_code(400);
        echo "Bad Request: Missing required fields";
        exit;
    }
    
    logWebhook("Procesando mensaje - SID: {$messageSid}, From: {$from}, Body: '{$body}'");
    
    // Cargar dependencias
    require_once dirname(__FILE__) . '/../config/database.php';
    require_once dirname(__FILE__) . '/../utils/TwilioConversationalBot.php';
    
    // Conectar a BD
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    // Crear instancia del bot
    $bot = crearTwilioBot();
    if (!$bot) {
        throw new Exception("No se pudo crear instancia del bot");
    }
    
    // Limpiar número de teléfono para búsqueda
    $telefonoCliente = limpiarTelefonoWebhook($from);
    
    logWebhook("Teléfono cliente limpio: {$telefonoCliente}");
    
    // Verificar si es mensaje de SAG Admin (confirmaciones)
    if (esMensajeSAGAdmin($from)) {
        logWebhook("Mensaje de SAG Admin detectado");
        $resultado = procesarMensajeSAGAdmin($db, $bot, $body, $messageSid);
    } else {
        // Es mensaje de cliente regular
        logWebhook("Mensaje de cliente regular detectado");
        $resultado = procesarMensajeCliente($db, $bot, $telefonoCliente, $body, $messageSid, $webhookData);
    }
    
    if ($resultado['success']) {
        logWebhook("Procesamiento exitoso: " . $resultado['message']);
        http_response_code(200);
        echo "OK";
    } else {
        logWebhook("Error en procesamiento: " . $resultado['error'], 'ERROR');
        http_response_code(200); // Twilio espera 200 aunque haya error interno
        echo "Error: " . $resultado['error'];
    }
    
} catch (Exception $e) {
    logWebhook("Excepción crítica: " . $e->getMessage(), 'FATAL');
    logWebhook("Stack trace: " . $e->getTraceAsString(), 'FATAL');
    
    http_response_code(500);
    echo "Internal Server Error";
} finally {
    logWebhook("=== FIN WEBHOOK TWILIO ===");
}

/**
 * Procesar mensaje de cliente regular
 */
function procesarMensajeCliente($db, $bot, $telefono, $body, $messageSid, $webhookData) {
    try {
        // Buscar alerta activa para este cliente
        $alerta = buscarAlertaActivaCliente($db, $telefono);
        
        if (!$alerta) {
            logWebhook("No se encontró alerta activa para el teléfono: {$telefono}", 'WARNING');
            return [
                'success' => true,
                'message' => 'No hay alerta activa para este cliente'
            ];
        }
        
        logWebhook("Alerta encontrada - ID: {$alerta['id']}, Estado: {$alerta['estado_whatsapp']}");
        
        // Procesar según el estado actual de la conversación
        switch ($alerta['estado_whatsapp']) {
            case 'enviado':
            case 'esperando_respuesta':
                // Cliente responde a recordatorio inicial (sí/no)
                return $bot->procesarRespuestaInicial($alerta['id'], $body, $messageSid);
                
            case 'esperando_fecha':
                // Cliente selecciona fecha
                return $bot->procesarSeleccionFecha($alerta['id'], $body, $messageSid);
                
            case 'pre_agendado':
                // Cliente escribió algo después de pre-agendar
                // Solo registrar, no hacer nada más
                registrarMensajeCliente($db, $alerta['id'], $messageSid, $telefono, $body, 'post_agenda');
                return [
                    'success' => true,
                    'message' => 'Mensaje post-agenda registrado'
                ];
                
            default:
                logWebhook("Estado no manejable para cliente: {$alerta['estado_whatsapp']}", 'WARNING');
                return [
                    'success' => true,
                    'message' => 'Estado no manejable'
                ];
        }
        
    } catch (Exception $e) {
        logWebhook("Error procesarMensajeCliente: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Procesar mensaje de SAG Admin (confirmaciones)
 */
function procesarMensajeSAGAdmin($db, $bot, $body, $messageSid) {
    try {
        // Buscar alerta pendiente de confirmación SAG más reciente
        $alerta = buscarAlertaPendienteConfirmacion($db);
        
        if (!$alerta) {
            logWebhook("No hay alertas pendientes de confirmación SAG", 'WARNING');
            return [
                'success' => true,
                'message' => 'No hay alertas pendientes de confirmación'
            ];
        }
        
        logWebhook("Procesando confirmación SAG para alerta ID: {$alerta['id']}");
        
        // Procesar confirmación de SAG
        return $bot->procesarConfirmacionSAG($alerta['id'], $body, $messageSid, null);
        
    } catch (Exception $e) {
        logWebhook("Error procesarMensajeSAGAdmin: " . $e->getMessage(), 'ERROR');
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Buscar alerta activa para un cliente por teléfono
 */
function buscarAlertaActivaCliente($db, $telefono) {
    try {
        $sql = "SELECT 
                    a.*,
                    c.nombre as cliente_nombre,
                    c.telefono as cliente_telefono
                FROM alertas_servicio a
                INNER JOIN clientes c ON a.cliente_id = c.id
                WHERE c.telefono = ?
                  AND a.estado_whatsapp IN ('enviado', 'esperando_respuesta', 'esperando_fecha', 'pre_agendado')
                ORDER BY a.ultima_actividad DESC
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$telefono]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        logWebhook("Error buscarAlertaActivaCliente: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * Buscar alerta pendiente de confirmación SAG
 */
function buscarAlertaPendienteConfirmacion($db) {
    try {
        $sql = "SELECT 
                    a.*,
                    c.nombre as cliente_nombre,
                    c.telefono as cliente_telefono
                FROM alertas_servicio a
                INNER JOIN clientes c ON a.cliente_id = c.id
                WHERE a.estado_whatsapp = 'pre_agendado'
                  AND a.confirmacion_sag = 'pendiente'
                ORDER BY a.fecha_pre_agendado DESC
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        logWebhook("Error buscarAlertaPendienteConfirmacion: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

/**
 * Registrar mensaje de cliente en BD
 */
function registrarMensajeCliente($db, $alertaId, $messageSid, $telefono, $body, $step) {
    try {
        $sql = "INSERT INTO conversaciones_whatsapp 
                (alerta_id, twilio_message_sid, direction, from_number, to_number, 
                 message_body, message_type, conversation_step) 
                VALUES (?, ?, 'inbound', ?, '', ?, 'text', ?)";
        
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $alertaId,
            $messageSid,
            "whatsapp:+52{$telefono}",
            $body,
            $step
        ]);
        
    } catch (Exception $e) {
        logWebhook("Error registrarMensajeCliente: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Verificar si el mensaje viene de SAG Admin
 */
function esMensajeSAGAdmin($from) {
    try {
        // Obtener configuración de teléfono admin
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            return false;
        }
        
        $sql = "SELECT config_value FROM twilio_config 
                WHERE config_key = 'sag_admin_phone' 
                AND is_active = TRUE";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $adminPhone = $result['config_value'] ?? '';
        
        if (empty($adminPhone)) {
            return false;
        }
        
        // Limpiar ambos números para comparar
        $fromClean = limpiarTelefonoWebhook($from);
        $adminClean = limpiarTelefonoWebhook($adminPhone);
        
        logWebhook("Comparando teléfonos - From: {$fromClean}, Admin: {$adminClean}");
        
        return $fromClean === $adminClean;
        
    } catch (Exception $e) {
        logWebhook("Error esMensajeSAGAdmin: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Limpiar número de teléfono del webhook
 */
function limpiarTelefonoWebhook($telefono) {
    // Remover prefijo whatsapp:
    $telefono = str_replace('whatsapp:', '', $telefono);
    
    // Remover + y prefijos comunes
    $telefono = preg_replace('/^\+?52/', '', $telefono);
    $telefono = preg_replace('/^01/', '', $telefono);
    
    // Remover espacios, guiones, paréntesis
    $telefono = preg_replace('/[\s\-\(\)\+]+/', '', $telefono);
    
    // Si tiene más de 10 dígitos, tomar los últimos 10
    if (strlen($telefono) > 10) {
        $telefono = substr($telefono, -10);
    }
    
    return $telefono;
}

/**
 * Validar firma de webhook Twilio (opcional)
 */
function validarFirmaTwilio($url, $postData, $signature) {
    try {
        // Obtener auth token
        $database = new Database();
        $db = $database->getConnection();
        
        if (!$db) {
            return false;
        }
        
        $sql = "SELECT config_value FROM twilio_config 
                WHERE config_key = 'auth_token' 
                AND is_active = TRUE";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $authToken = $result['config_value'] ?? '';
        
        if (empty($authToken)) {
            logWebhook("AuthToken no configurado, omitiendo validación de firma", 'WARNING');
            return true; // Permitir si no está configurado
        }
        
        // Construir datos para firma
        ksort($postData);
        $data = $url;
        foreach ($postData as $key => $value) {
            $data .= $key . $value;
        }
        
        // Generar firma esperada
        $expectedSignature = base64_encode(hash_hmac('sha1', $data, $authToken, true));
        
        // Comparar firmas
        $isValid = hash_equals($signature, $expectedSignature);
        
        if (!$isValid) {
            logWebhook("Firma de webhook inválida", 'ERROR');
        }
        
        return $isValid;
        
    } catch (Exception $e) {
        logWebhook("Error validarFirmaTwilio: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

/**
 * Generar respuesta TwiML (si es necesario)
 */
function generarTwiMLResponse($mensaje = null) {
    if (!$mensaje) {
        return '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    }
    
    $twiml = '<?xml version="1.0" encoding="UTF-8"?>';
    $twiml .= '<Response>';
    $twiml .= '<Message>' . htmlspecialchars($mensaje, ENT_XML1) . '</Message>';
    $twiml .= '</Response>';
    
    return $twiml;
}

?>