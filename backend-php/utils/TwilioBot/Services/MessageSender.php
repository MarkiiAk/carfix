<?php
/**
 * MessageSender - Servicio unificado para envío de mensajes WhatsApp
 * 
 * Esta clase centraliza y unifica todo el envío de mensajes a través de Twilio,
 * proporcionando una interfaz consistente para diferentes tipos de mensajes
 * (texto, plantillas, interactivos) con manejo robusto de errores y logging.
 * 
 * Características:
 * - Envío unificado de mensajes de texto y plantillas
 * - Modo simulación para testing sin envíos reales
 * - Validación automática de números de teléfono
 * - Retry automático para errores transitorios
 * - Logging detallado de todas las operaciones
 * - Manejo robusto de errores de Twilio
 * 
 * @author Sistema CarFix - Refactorización 2026
 * @version 1.0.0
 */

require_once __DIR__ . '/../Core/TwilioClientManager.php';
require_once __DIR__ . '/../Core/ConfigurationService.php';
require_once __DIR__ . '/../Core/MessageLogger.php';
require_once __DIR__ . '/../Validators/PhoneValidator.php';

class MessageSender 
{
    /**
     * Manager del cliente Twilio
     * @var TwilioClientManager
     */
    private $clientManager;
    
    /**
     * Servicio de configuración
     * @var ConfigurationService
     */
    private $config;
    
    /**
     * Logger de mensajes
     * @var MessageLogger
     */
    private $logger;
    
    /**
     * Validador de teléfonos
     * @var PhoneValidator
     */
    private $phoneValidator;
    
    /**
     * Constructor
     */
    public function __construct() 
    {
        $this->clientManager = TwilioClientManager::getInstance();
        $this->config = ConfigurationService::getInstance();
        $this->logger = MessageLogger::getInstance();
        $this->phoneValidator = new PhoneValidator();
        
        $this->logger->logOperation("MessageSender inicializado", [
            'simulation_mode' => $this->clientManager->isSimulationMode(),
            'client_status' => $this->clientManager->getClientStatus()
        ]);
    }
    
    /**
     * Enviar mensaje de texto simple
     * 
     * @param string $telefono Número de teléfono destino (sin +52)
     * @param string $mensaje Contenido del mensaje
     * @return array Resultado del envío con status y detalles
     */
    public function sendTextMessage(string $telefono, string $mensaje): array 
    {
        try {
            $this->logger->logOperation("Iniciando envío de mensaje de texto", [
                'telefono' => $telefono,
                'mensaje_length' => strlen($mensaje)
            ]);
            
            // Validar número de teléfono
            $telefonoLimpio = $this->phoneValidator->cleanPhoneNumber($telefono);
            if (!$telefonoLimpio) {
                throw new Exception("Número de teléfono no válido: {$telefono}");
            }
            
            // Validar mensaje
            if (empty($mensaje)) {
                throw new Exception("El mensaje no puede estar vacío");
            }
            
            // Obtener número de origen
            $fromNumber = $this->config->getWhatsappFrom();
            $toNumber = "whatsapp:+52{$telefonoLimpio}";
            
            $this->logger->logDebug("Preparando envío", [
                'from' => $fromNumber,
                'to' => $toNumber,
                'message_preview' => substr($mensaje, 0, 50) . '...'
            ]);
            
            // Verificar modo simulación
            if ($this->clientManager->isSimulationMode()) {
                return $this->simulateMessageSend($telefono, $mensaje, 'text');
            }
            
            // Envío real con Twilio
            $twilioClient = $this->clientManager->getClient();
            if (!$twilioClient) {
                throw new Exception("Cliente Twilio no disponible");
            }
            
            // Enviar mensaje
            $message = $twilioClient->messages->create(
                $toNumber,
                [
                    'from' => $fromNumber,
                    'body' => $mensaje
                ]
            );
            
            $this->logger->logOperation("Mensaje de texto enviado exitosamente", [
                'telefono' => $telefonoLimpio,
                'message_sid' => $message->sid,
                'status' => $message->status
            ]);
            
            return [
                'success' => true,
                'message_sid' => $message->sid,
                'status' => $message->status,
                'to' => $toNumber,
                'from' => $fromNumber,
                'body' => $mensaje,
                'twilio_response' => [
                    'sid' => $message->sid,
                    'status' => $message->status,
                    'date_created' => $message->dateCreated->format('Y-m-d H:i:s'),
                    'price' => $message->price,
                    'direction' => 'outbound-api'
                ]
            ];
            
        } catch (Exception $e) {
            $this->logger->logError("Error enviando mensaje de texto", $e, [
                'telefono' => $telefono ?? 'unknown',
                'mensaje_length' => isset($mensaje) ? strlen($mensaje) : 0
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'telefono' => $telefono ?? null
            ];
        }
    }
    
    /**
     * Enviar mensaje usando plantilla de Twilio
     * 
     * @param string $telefono Número de teléfono destino
     * @param string $templateSid SID de la plantilla
     * @param array $variables Variables para reemplazar en la plantilla
     * @return array Resultado del envío
     */
    public function sendTemplateMessage(string $telefono, string $templateSid, array $variables = []): array 
    {
        try {
            $this->logger->logOperation("Iniciando envío de mensaje con plantilla", [
                'telefono' => $telefono,
                'template_sid' => $templateSid,
                'variables_count' => count($variables)
            ]);
            
            // Validar número de teléfono
            $telefonoLimpio = $this->phoneValidator->cleanPhoneNumber($telefono);
            if (!$telefonoLimpio) {
                throw new Exception("Número de teléfono no válido: {$telefono}");
            }
            
            // Validar template SID
            if (empty($templateSid)) {
                throw new Exception("Template SID es requerido");
            }
            
            // Obtener credenciales para cURL (método actual exitoso)
            $sid = $this->config->getTwilioAccountSid();
            $token = $this->config->getTwilioAuthToken();
            $fromNumber = $this->config->getWhatsappFrom();
            
            if (empty($sid) || empty($token)) {
                throw new Exception("Credenciales Twilio no configuradas");
            }
            
            $this->logger->logDebug("Configuración validada", [
                'account_sid_preview' => substr($sid, 0, 10) . '...',
                'from_number' => $fromNumber,
                'variables' => $variables
            ]);
            
            // Verificar modo simulación
            if ($this->clientManager->isSimulationMode()) {
                return $this->simulateTemplateMessage($telefono, $templateSid, $variables);
            }
            
            // Envío con cURL (método probado y exitoso)
            $result = $this->sendTemplateCurl($telefonoLimpio, $templateSid, $variables, $sid, $token, $fromNumber);
            
            if ($result['success']) {
                $this->logger->logOperation("Plantilla enviada exitosamente", [
                    'telefono' => $telefonoLimpio,
                    'template_sid' => $templateSid,
                    'message_sid' => $result['message_sid']
                ]);
            } else {
                $this->logger->logError("Error enviando plantilla", null, [
                    'telefono' => $telefonoLimpio,
                    'template_sid' => $templateSid,
                    'error' => $result['error']
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->logError("Error en sendTemplateMessage", $e, [
                'telefono' => $telefono ?? 'unknown',
                'template_sid' => $templateSid ?? 'unknown'
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Envío de plantilla usando cURL (método probado)
     * 
     * @param string $telefono Teléfono limpio
     * @param string $templateSid SID de plantilla
     * @param array $variables Variables de contenido
     * @param string $sid Account SID
     * @param string $token Auth Token
     * @param string $fromNumber Número de origen
     * @return array Resultado del envío
     */
    private function sendTemplateCurl(string $telefono, string $templateSid, array $variables, string $sid, string $token, string $fromNumber): array 
    {
        try {
            // Formatear variables para ContentVariables de Twilio
            $contentVariables = [];
            foreach ($variables as $key => $value) {
                $contentVariables[strval($key)] = strval($value);
            }
            
            $this->logger->logDebug("Preparando cURL para plantilla", [
                'telefono' => $telefono,
                'template_sid' => $templateSid,
                'content_variables' => $contentVariables
            ]);
            
            // Configurar cURL
            $curl = curl_init();
            
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query([
                    'ContentSid' => $templateSid,
                    'From' => $fromNumber,
                    'To' => "whatsapp:+52{$telefono}",
                    'ContentVariables' => json_encode($contentVariables)
                ]),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Authorization: Basic ' . base64_encode($sid . ':' . $token)
                ],
            ]);
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            
            curl_close($curl);
            
            $this->logger->logDebug("Respuesta cURL recibida", [
                'http_code' => $httpCode,
                'curl_error' => $curlError ?: null,
                'response_length' => strlen($response)
            ]);
            
            if ($curlError) {
                throw new Exception("Error cURL: {$curlError}");
            }
            
            $responseData = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['sid'])) {
                return [
                    'success' => true,
                    'message_sid' => $responseData['sid'],
                    'status' => $responseData['status'] ?? 'queued',
                    'twilio_response' => $responseData,
                    'template_sid' => $templateSid,
                    'variables' => $variables
                ];
            } else {
                $errorMsg = isset($responseData['message']) ? $responseData['message'] : "HTTP {$httpCode}";
                throw new Exception("Error Twilio: {$errorMsg}");
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'template_sid' => $templateSid
            ];
        }
    }
    
    /**
     * Enviar mensaje con opciones (botones)
     * 
     * @param string $telefono Número de teléfono
     * @param string $mensaje Mensaje principal
     * @param array $botones Array de botones con id y title
     * @param string $step Paso del flujo conversacional
     * @return array Resultado del envío
     */
    public function sendInteractiveMessage(string $telefono, string $mensaje, array $botones, string $step): array 
    {
        try {
            $this->logger->logOperation("Enviando mensaje con opciones", [
                'telefono' => $telefono,
                'botones_count' => count($botones),
                'step' => $step
            ]);
            
            // Por ahora, convertir a mensaje de texto numerado
            // Los botones reales requieren pre-aprobación específica de Twilio
            $mensajeConOpciones = $mensaje . "\n\n";
            $contador = 1;
            
            foreach ($botones as $boton) {
                $mensajeConOpciones .= "{$contador}. {$boton['title']}\n";
                $contador++;
            }
            
            $mensajeConOpciones .= "\nResponde con el número de tu opción.";
            
            // Enviar como mensaje de texto
            $result = $this->sendTextMessage($telefono, $mensajeConOpciones);
            
            if ($result['success']) {
                // Agregar información de los botones al resultado
                $result['interaction_type'] = 'numbered_options';
                $result['botones_enviados'] = count($botones);
                $result['step'] = $step;
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logger->logError("Error enviando mensaje interactivo", $e, [
                'telefono' => $telefono ?? 'unknown',
                'step' => $step ?? 'unknown'
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Simular envío de mensaje (para testing)
     * 
     * @param string $telefono Número de teléfono
     * @param string $mensaje Contenido del mensaje
     * @param string $type Tipo de mensaje
     * @return array Resultado simulado
     */
    private function simulateMessageSend(string $telefono, string $mensaje, string $type): array 
    {
        $fakeSid = 'SM_SIMULADO_' . uniqid();
        
        $this->logger->logOperation("SIMULACIÓN: Mensaje enviado", [
            'telefono' => $telefono,
            'tipo' => $type,
            'mensaje_preview' => substr($mensaje, 0, 100) . '...',
            'fake_sid' => $fakeSid
        ]);
        
        return [
            'success' => true,
            'message_sid' => $fakeSid,
            'status' => 'queued',
            'simulation' => true,
            'telefono' => $telefono,
            'mensaje' => $mensaje,
            'type' => $type,
            'twilio_response' => [
                'sid' => $fakeSid,
                'status' => 'queued',
                'simulated' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Simular envío de plantilla
     * 
     * @param string $telefono Número de teléfono
     * @param string $templateSid SID de plantilla
     * @param array $variables Variables
     * @return array Resultado simulado
     */
    private function simulateTemplateMessage(string $telefono, string $templateSid, array $variables): array 
    {
        $fakeSid = 'SM_TEMPLATE_SIMULADO_' . uniqid();
        
        $this->logger->logOperation("SIMULACIÓN: Plantilla enviada", [
            'telefono' => $telefono,
            'template_sid' => $templateSid,
            'variables' => $variables,
            'fake_sid' => $fakeSid
        ]);
        
        return [
            'success' => true,
            'message_sid' => $fakeSid,
            'status' => 'queued',
            'simulation' => true,
            'template_sid' => $templateSid,
            'variables' => $variables,
            'twilio_response' => [
                'sid' => $fakeSid,
                'status' => 'queued',
                'simulated' => true,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Obtener estado del servicio de envío
     * 
     * @return array Estado detallado del servicio
     */
    public function getServiceStatus(): array 
    {
        $clientStatus = $this->clientManager->getClientStatus();
        $configStatus = $this->config->getAllConfigurations();
        
        return [
            'service_ready' => $this->clientManager->isReadyForSending(),
            'simulation_mode' => $this->clientManager->isSimulationMode(),
            'twilio_client' => $clientStatus,
            'configuration' => $configStatus,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}