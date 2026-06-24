<?php
/**
 * WEBHOOK BIDIRECCIONAL TWILIO WHATSAPP - CARFIX
 * Procesador de mensajes entrantes y salientes
 * 
 * Flujo:
 * 1. Recibe webhook de Twilio cuando cliente responde
 * 2. Identifica a qué alerta pertenece la respuesta
 * 3. Procesa según el estado actual de la conversación
 * 4. Envía respuesta automática correspondiente
 * 
 * @author Sistema CarFix
 * @version 2.0.0
 * @date 01/04/2026
 */

// Configurar respuesta
header('Content-Type: text/xml');
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
        echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
        exit;
    }
    
    // Obtener datos del webhook
    $webhookData = $_POST;
    
    if (empty($webhookData)) {
        logWebhook("No se recibieron datos POST", 'ERROR');
        http_response_code(400);
        echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
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
        echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
        exit;
    }
    
    logWebhook("Procesando mensaje - SID: {$messageSid}, From: {$from}, Body: '{$body}'");
    
    // Cargar dependencias
    require_once dirname(__FILE__) . '/../config/database.php';
    require_once dirname(__FILE__) . '/../utils/TwilioConversationalBot.php';
    
    // Conectar a BD
    $database = Database::getInstance();
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
        $message = $resultado['message'] ?? $resultado['accion'] ?? 'Procesado exitosamente';
        logWebhook("Procesamiento exitoso: " . $message);
        http_response_code(200);
        // Respuesta TwiML vacía - no enviar mensaje adicional
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    } else {
        logWebhook("Error en procesamiento: " . $resultado['error'], 'ERROR');
        http_response_code(200); // Twilio espera 200 aunque haya error interno
        // Respuesta TwiML vacía - no enviar mensaje adicional
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
    }
    
} catch (Exception $e) {
    logWebhook("Excepción crítica: " . $e->getMessage(), 'FATAL');
    logWebhook("Stack trace: " . $e->getTraceAsString(), 'FATAL');
    
    http_response_code(200);
    echo '<?xml version="1.0" encoding="UTF-8"?><Response></Response>';
} finally {
    logWebhook("=== FIN WEBHOOK TWILIO ===");
}

/**
 * Procesar mensaje de cliente regular
 */
function procesarMensajeCliente($db, $bot, $telefono, $body, $messageSid, $webhookData) {
    try {
        // Extraer OriginalRepliedMessageSid del webhook (clave para vincular conversación)
        $originalRepliedMessageSid = $webhookData['OriginalRepliedMessageSid'] ?? null;
        
        logWebhook("OriginalRepliedMessageSid extraído: " . ($originalRepliedMessageSid ?? 'NULL'));
        
        // Buscar alerta activa para este cliente (búsqueda híbrida)
        $alerta = buscarAlertaActivaCliente($db, $telefono, $originalRepliedMessageSid);
        
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
                logWebhook("LLAMANDO a procesarRespuestaInicial - AlertaID: {$alerta['id']}, Body: '{$body}'");
                $resultado = $bot->procesarRespuestaInicial($alerta['id'], $body, $messageSid, $webhookData);
                logWebhook("RESULTADO procesarRespuestaInicial: " . json_encode($resultado));
                return $resultado;
                
            case 'esperando_fecha':
                // **SISTEMA ADAPTABLE: Cliente selecciona horario del calendario**
                logWebhook("Cliente seleccionando horario de calendario - Sistema Adaptable");
                
                // **MODO INTERACTIVE: Detectar slot ID desde webhook data (List Picker/Button response)**
                $slotId = $webhookData['Button'] ?? $webhookData['ButtonId'] ?? $webhookData['ListItem'] ?? '';
                
                if (!empty($slotId)) {
                    logWebhook("MODO INTERACTIVE - Slot ID detectado desde webhook: {$slotId}");
                    return $bot->procesarSeleccionSlot($alerta['id'], $slotId, $messageSid);
                }
                
                // **MODO SIMPLE: Detectar respuesta numérica (1-9)**
                logWebhook("MODO SIMPLE - Analizando respuesta numérica: '{$body}'");
                
                // Validar si es respuesta numérica simple (1-9)
                if (preg_match('/^\s*([1-9])\s*$/', trim($body), $matches)) {
                    $numeroSeleccion = (int)$matches[1];
                    logWebhook("MODO SIMPLE - Número detectado: {$numeroSeleccion}");
                    return $bot->procesarRespuestaNumericaSimple($alerta['id'], $body, $messageSid);
                }
                
                // **FALLBACKS para compatibilidad**
                // Buscar patrones de slot en el texto (modo interactive legacy)
                if (preg_match('/slot_(\d+)/', $body, $matches)) {
                    $slotId = 'slot_' . $matches[1];
                    logWebhook("FALLBACK - Slot ID extraído del texto: {$slotId}");
                    return $bot->procesarSeleccionSlot($alerta['id'], $slotId, $messageSid);
                } 
                elseif (preg_match('/\b(otro|9|contacto|directo)\b/i', $body)) {
                    logWebhook("FALLBACK - Detectada selección 'Otro horario'");
                    return $bot->procesarSeleccionSlot($alerta['id'], 'otro_horario', $messageSid);
                } 
                else {
                    // Último fallback: método original
                    logWebhook("FALLBACK FINAL - Usando método original procesarSeleccionFecha");
                    return $bot->procesarSeleccionFecha($alerta['id'], $body, $messageSid);
                }
                
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
 * Buscar alerta activa para un cliente por twilio_conversation_sid o teléfono
 * NUEVO: Búsqueda híbrida - primero por OriginalRepliedMessageSid, luego por teléfono
 */
function buscarAlertaActivaCliente($db, $telefono, $originalRepliedMessageSid = null) {
    try {
        // ESTRATEGIA 1: Buscar por OriginalRepliedMessageSid (más preciso)
        if (!empty($originalRepliedMessageSid)) {
            logWebhook("Buscando alerta por OriginalRepliedMessageSid: {$originalRepliedMessageSid}");
            
            $sql = "SELECT 
                        a.*,
                        c.nombre as cliente_nombre,
                        c.telefono as cliente_telefono
                    FROM alertas_servicio a
                    INNER JOIN clientes c ON a.cliente_id = c.id
                    WHERE a.twilio_conversation_sid = ?
                      AND a.estado_whatsapp IN ('enviado', 'esperando_respuesta', 'esperando_fecha', 'pre_agendado')
                    ORDER BY a.ultima_actividad DESC
                    LIMIT 1";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([$originalRepliedMessageSid]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                logWebhook("Alerta encontrada por conversation_sid - ID: {$result['id']}");
                return $result;
            }
            
            logWebhook("No se encontró alerta por conversation_sid, probando por teléfono");
        }
        
        // ESTRATEGIA 2: Fallback por teléfono (método original)
        logWebhook("Buscando alerta por teléfono: {$telefono}");
        
        // Comparar los últimos 10 dígitos para tolerar formatos 10-dígitos (5517295626)
        // vs 13-dígitos con prefijo +521 (5215517295626) que envía Twilio
        $sql = "SELECT
                    a.*,
                    c.nombre as cliente_nombre,
                    c.telefono as cliente_telefono
                FROM alertas_servicio a
                INNER JOIN clientes c ON a.cliente_id = c.id
                WHERE RIGHT(c.telefono, 10) = RIGHT(?, 10)
                  AND (a.estado_whatsapp IN ('enviado', 'esperando_respuesta', 'esperando_fecha', 'pre_agendado')
                       OR a.estado_whatsapp IS NULL OR a.estado_whatsapp = '')
                ORDER BY a.ultima_actividad DESC
                LIMIT 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$telefono]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            logWebhook("Alerta encontrada por teléfono - ID: {$result['id']}");
        } else {
            logWebhook("No se encontró alerta activa para teléfono: {$telefono}");
        }
        
        return $result;
        
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
        $database = Database::getInstance();
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
    
    // Remover espacios, guiones, paréntesis
    $telefono = preg_replace('/[\s\-\(\)\+]+/', '', $telefono);
    
    // **SOLUCIÓN CORRECTA:** Si viene +5215519330800, quitar +
    // Si viene 15519330800, agregar 52 adelante
    
    // Quitar + inicial
    $telefono = ltrim($telefono, '+');
    
    // Si NO empieza con 52, agregarlo (para números que empiezan con 1)
    if (!preg_match('/^52/', $telefono) && preg_match('/^1\d{10}$/', $telefono)) {
        $telefono = '52' . $telefono;
    }
    
    return $telefono; // Formato: 5215519330800
}

/**
 * Validar firma de webhook Twilio (opcional)
 */
function validarFirmaTwilio($url, $postData, $signature) {
    try {
        // Obtener auth token
        $database = Database::getInstance();
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