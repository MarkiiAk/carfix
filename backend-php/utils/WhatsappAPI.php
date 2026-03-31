<?php
/**
 * WhatsAppAPI - SAG Garage
 * 
 * Cliente para la API de WhatsApp Business
 * Maneja la comunicación con Meta Graph API
 * 
 * @author Marco Candiani
 * @version 1.0
 * @date 30/03/2026
 */

class WhatsappAPI {
    private $config;
    private $apiUrl;
    private $token;
    private $phoneNumberId;
    private $apiVersion;
    
    public function __construct($config) {
        $this->config = $config;
        $this->apiUrl = $config['api_url'] ?? 'https://graph.facebook.com';
        $this->token = $config['api_token'] ?? '';
        $this->phoneNumberId = $config['phone_number_id'] ?? '';
        $this->apiVersion = $config['api_version'] ?? 'v18.0';
    }

    /**
     * Enviar mensaje de texto a WhatsApp
     */
    public function enviarMensaje($telefono, $mensaje) {
        if (empty($this->token) || empty($this->phoneNumberId)) {
            return [
                'success' => false,
                'error' => 'Configuración de API incompleta (token o phone_number_id)',
                'error_code' => 'CONFIG_MISSING'
            ];
        }

        $url = "{$this->apiUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $this->limpiarTelefono($telefono),
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $mensaje
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];

        try {
            $response = $this->hacerPeticion($url, $data, $headers);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message_id' => $response['data']['messages'][0]['id'] ?? null,
                    'phone_number' => $telefono,
                    'raw_response' => $response['data']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'],
                    'error_code' => $response['error_code'] ?? 'UNKNOWN',
                    'raw_response' => $response['data'] ?? null
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $e->getMessage(),
                'error_code' => 'CONNECTION_ERROR'
            ];
        }
    }

    /**
     * Enviar mensaje con template (para mensajes estructurados)
     */
    public function enviarTemplate($telefono, $templateName, $parametros = []) {
        if (empty($this->token) || empty($this->phoneNumberId)) {
            return [
                'success' => false,
                'error' => 'Configuración de API incompleta',
                'error_code' => 'CONFIG_MISSING'
            ];
        }

        $url = "{$this->apiUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages";
        
        $components = [];
        if (!empty($parametros)) {
            $parameters = [];
            foreach ($parametros as $param) {
                $parameters[] = [
                    'type' => 'text',
                    'text' => $param
                ];
            }
            
            $components[] = [
                'type' => 'body',
                'parameters' => $parameters
            ];
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $this->limpiarTelefono($telefono),
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => 'es_MX'
                ],
                'components' => $components
            ]
        ];

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];

        try {
            $response = $this->hacerPeticion($url, $data, $headers);
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'message_id' => $response['data']['messages'][0]['id'] ?? null,
                    'phone_number' => $telefono,
                    'template_name' => $templateName,
                    'raw_response' => $response['data']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $response['error'],
                    'error_code' => $response['error_code'] ?? 'UNKNOWN',
                    'raw_response' => $response['data'] ?? null
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $e->getMessage(),
                'error_code' => 'CONNECTION_ERROR'
            ];
        }
    }

    /**
     * Verificar webhook de WhatsApp
     */
    public function verificarWebhook($hubMode, $hubChallenge, $hubVerifyToken) {
        $tokenEsperado = $this->config['webhook_token'] ?? '';
        
        if (empty($tokenEsperado)) {
            return [
                'success' => false,
                'error' => 'Token de webhook no configurado'
            ];
        }

        if ($hubMode === 'subscribe' && $hubVerifyToken === $tokenEsperado) {
            return [
                'success' => true,
                'challenge' => $hubChallenge
            ];
        }

        return [
            'success' => false,
            'error' => 'Token de verificación inválido'
        ];
    }

    /**
     * Test de conexión con la API
     */
    public function testConnection() {
        if (empty($this->token) || empty($this->phoneNumberId)) {
            return [
                'success' => false,
                'error' => 'Token o Phone Number ID no configurados'
            ];
        }

        // Hacer una petición simple para verificar credenciales
        $url = "{$this->apiUrl}/{$this->apiVersion}/{$this->phoneNumberId}";
        
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'SAG-Garage-WhatsApp/1.0'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return [
                    'success' => false,
                    'error' => 'Error de cURL: ' . $curlError
                ];
            }

            $data = json_decode($response, true);

            if ($httpCode === 200) {
                return [
                    'success' => true,
                    'message' => 'Conexión exitosa con WhatsApp API',
                    'phone_number' => $data['display_phone_number'] ?? 'N/A',
                    'verified_name' => $data['verified_name'] ?? 'N/A'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error HTTP ' . $httpCode,
                    'details' => $data['error']['message'] ?? 'Error desconocido'
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Excepción: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtener información del número de teléfono
     */
    public function obtenerInfoTelefono() {
        if (empty($this->token) || empty($this->phoneNumberId)) {
            return [
                'success' => false,
                'error' => 'Configuración incompleta'
            ];
        }

        $url = "{$this->apiUrl}/{$this->apiVersion}/{$this->phoneNumberId}";
        
        $headers = [
            'Authorization: Bearer ' . $this->token
        ];

        try {
            $response = $this->hacerPeticion($url, null, $headers, 'GET');
            
            if ($response['success']) {
                return [
                    'success' => true,
                    'phone_number' => $response['data']['display_phone_number'] ?? '',
                    'verified_name' => $response['data']['verified_name'] ?? '',
                    'quality_rating' => $response['data']['quality_rating'] ?? '',
                    'platform_type' => $response['data']['platform_type'] ?? '',
                    'raw_data' => $response['data']
                ];
            } else {
                return $response;
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener información: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Realizar petición HTTP a la API
     */
    private function hacerPeticion($url, $data = null, $headers = [], $method = 'POST') {
        $ch = curl_init();
        
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->config['timeout_api_segundos'] ?? 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'SAG-Garage-WhatsApp/1.0',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ];

        if ($method === 'POST' && $data !== null) {
            $curlOptions[CURLOPT_POST] = true;
            $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'GET') {
            $curlOptions[CURLOPT_HTTPGET] = true;
        }

        curl_setopt_array($ch, $curlOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        // Log para debug si está habilitado
        if ($this->config['modo_debug'] ?? false) {
            error_log("WhatsApp API Request: " . json_encode([
                'url' => $url,
                'method' => $method,
                'data' => $data,
                'http_code' => $httpCode,
                'response' => $response
            ]));
        }

        curl_close($ch);

        if ($curlError) {
            throw new Exception("Error cURL: {$curlError}");
        }

        $responseData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $responseData,
                'http_code' => $httpCode
            ];
        } else {
            $errorMessage = 'Error HTTP ' . $httpCode;
            $errorCode = 'HTTP_' . $httpCode;
            
            if (isset($responseData['error'])) {
                $errorMessage = $responseData['error']['message'] ?? $errorMessage;
                $errorCode = $responseData['error']['code'] ?? $errorCode;
            }

            return [
                'success' => false,
                'error' => $errorMessage,
                'error_code' => $errorCode,
                'http_code' => $httpCode,
                'data' => $responseData
            ];
        }
    }

    /**
     * Limpiar número de teléfono para API
     */
    private function limpiarTelefono($telefono) {
        // Remover espacios, guiones y paréntesis
        $limpio = preg_replace('/[\s\-\(\)]/', '', $telefono);
        
        // Si no empieza con +, agregarlo
        if (!str_starts_with($limpio, '+')) {
            $limpio = '+' . $limpio;
        }
        
        return $limpio;
    }

    /**
     * Validar configuración de API
     */
    public function validarConfiguracion() {
        $errores = [];
        
        if (empty($this->token)) {
            $errores[] = 'Token de API no configurado';
        }
        
        if (empty($this->phoneNumberId)) {
            $errores[] = 'Phone Number ID no configurado';
        }
        
        if (empty($this->config['webhook_token'] ?? '')) {
            $errores[] = 'Token de webhook no configurado';
        }
        
        if (!filter_var($this->apiUrl, FILTER_VALIDATE_URL)) {
            $errores[] = 'URL de API inválida';
        }

        return [
            'valida' => empty($errores),
            'errores' => $errores
        ];
    }

    /**
     * Obtener límites de rate limiting
     */
    public function obtenerLimites() {
        // Los límites de WhatsApp Business API varían según el tier
        // Estos son valores típicos, pero deberían obtenerse dinámicamente
        return [
            'mensajes_por_segundo' => 80,
            'mensajes_por_minuto' => 600,
            'mensajes_por_hora' => 7200,
            'mensajes_por_dia' => 100000, // Depende del tier
            'conversaciones_por_dia' => 1000 // Tier 1
        ];
    }

    /**
     * Formatear error para logging
     */
    public function formatearError($response) {
        if (!isset($response['error'])) {
            return 'Error desconocido';
        }

        $error = $response['error'];
        $codigo = $error['code'] ?? 'UNKNOWN';
        $mensaje = $error['message'] ?? 'Sin mensaje';
        $tipo = $error['type'] ?? 'API_ERROR';

        return "[$codigo] $tipo: $mensaje";
    }

    /**
     * Verificar si un error es recuperable
     */
    public function esErrorRecuperable($errorCode) {
        $erroresRecuperables = [
            'CONNECTION_ERROR',
            'TIMEOUT',
            'HTTP_429', // Rate limit
            'HTTP_500', // Error interno del servidor
            'HTTP_502', // Bad gateway
            'HTTP_503', // Service unavailable
            'HTTP_504'  // Gateway timeout
        ];

        return in_array($errorCode, $erroresRecuperables);
    }

    /**
     * Calcular delay para retry según el error
     */
    public function calcularDelayRetry($intento, $errorCode) {
        // Para rate limiting, usar delay exponencial
        if ($errorCode === 'HTTP_429') {
            return min(300, pow(2, $intento) * 60); // Max 5 minutos
        }

        // Para otros errores, delay más corto
        return min(60, pow(2, $intento) * 5); // Max 1 minuto
    }

    /**
     * Webhook para recibir actualizaciones de estado
     */
    public function procesarWebhook($payload) {
        try {
            $data = json_decode($payload, true);
            
            if (!$data) {
                return [
                    'success' => false,
                    'error' => 'Payload JSON inválido'
                ];
            }

            // Verificar estructura del webhook
            if (!isset($data['entry']) || !is_array($data['entry'])) {
                return [
                    'success' => false,
                    'error' => 'Estructura de webhook inválida'
                ];
            }

            $actualizaciones = [];
            
            foreach ($data['entry'] as $entry) {
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $change) {
                        if (isset($change['value']['statuses'])) {
                            // Actualizaciones de estado de mensaje
                            foreach ($change['value']['statuses'] as $status) {
                                $actualizaciones[] = [
                                    'type' => 'message_status',
                                    'message_id' => $status['id'],
                                    'status' => $status['status'],
                                    'timestamp' => $status['timestamp'] ?? time(),
                                    'recipient_id' => $status['recipient_id'] ?? null
                                ];
                            }
                        }
                        
                        if (isset($change['value']['messages'])) {
                            // Mensajes recibidos (respuestas de clientes)
                            foreach ($change['value']['messages'] as $message) {
                                $actualizaciones[] = [
                                    'type' => 'incoming_message',
                                    'message_id' => $message['id'],
                                    'from' => $message['from'],
                                    'text' => $message['text']['body'] ?? '',
                                    'timestamp' => $message['timestamp'] ?? time(),
                                    'message_type' => $message['type'] ?? 'text'
                                ];
                            }
                        }
                    }
                }
            }

            return [
                'success' => true,
                'updates_count' => count($actualizaciones),
                'updates' => $actualizaciones
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error procesando webhook: ' . $e->getMessage()
            ];
        }
    }
}