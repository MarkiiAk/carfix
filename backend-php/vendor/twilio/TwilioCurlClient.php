<?php

namespace Twilio\Http;

use Twilio\Exceptions\TwilioException;

/**
 * Cliente HTTP simplificado para Twilio usando cURL
 */
class CurlClient {
    
    public function request($method, $uri, $params = [], $data = [], $headers = [], $username = null, $password = null, $timeout = null) {
        $curl = curl_init();
        
        // Configuración básica de cURL
        curl_setopt_array($curl, [
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout ?: 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'twilio-php/7.16.0 (php/' . PHP_VERSION . ')',
        ]);
        
        // Autenticación
        if ($username && $password) {
            curl_setopt($curl, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        }
        
        // Headers
        $curlHeaders = [];
        foreach ($headers as $key => $value) {
            $curlHeaders[] = $key . ': ' . $value;
        }
        if (!empty($curlHeaders)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $curlHeaders);
        }
        
        // Método HTTP
        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($curl, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
            case 'GET':
                if (!empty($params)) {
                    $uri .= (strpos($uri, '?') !== false ? '&' : '?') . http_build_query($params);
                    curl_setopt($curl, CURLOPT_URL, $uri);
                }
                break;
            case 'PUT':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
            case 'DELETE':
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }
        
        // Ejecutar request
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        
        curl_close($curl);
        
        // Verificar errores de cURL
        if ($response === false || !empty($error)) {
            throw new TwilioException('cURL Error: ' . $error);
        }
        
        // Crear response object
        return new Response($httpCode, $response);
    }
}

/**
 * Objeto Response simplificado
 */
class Response {
    private $statusCode;
    private $content;
    
    public function __construct($statusCode, $content) {
        $this->statusCode = $statusCode;
        $this->content = $content;
    }
    
    public function getStatusCode() {
        return $this->statusCode;
    }
    
    public function getContent() {
        return $this->content;
    }
    
    public function ok() {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }
}

?>