<?php
/**
 * AUTOLOADER SIMPLIFICADO PARA TWILIO SDK - CPANEL
 * Para hosting compartido sin acceso a terminal/composer
 * 
 * Este archivo simula el autoloader de composer para cargar
 * solo las clases necesarias de Twilio SDK
 */

// Verificar que no estamos duplicando el autoloader
if (defined('TWILIO_AUTOLOADER_LOADED')) {
    return;
}
define('TWILIO_AUTOLOADER_LOADED', true);

// Función de autoloader personalizada
spl_autoload_register(function ($class) {
    // Solo procesar clases de Twilio
    if (strpos($class, 'Twilio\\') !== 0) {
        return;
    }
    
    // Mapear clases principales de Twilio que necesitamos
    $classMap = [
        'Twilio\\Rest\\Client' => __DIR__ . '/twilio/TwilioRestClient.php',
        'Twilio\\TwiML\\MessagingResponse' => __DIR__ . '/twilio/TwilioMessagingResponse.php',
        'Twilio\\Http\\CurlClient' => __DIR__ . '/twilio/TwilioCurlClient.php',
        'Twilio\\Values' => __DIR__ . '/twilio/TwilioValues.php',
        'Twilio\\Exceptions\\TwilioException' => __DIR__ . '/twilio/TwilioException.php',
    ];
    
    if (isset($classMap[$class])) {
        $file = $classMap[$class];
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    
    // Si no encontramos la clase, crear una implementación básica
    if ($class === 'Twilio\\Rest\\Client') {
        // Crear clase Client básica si no existe
        require_once __DIR__ . '/twilio/TwilioRestClient.php';
    }
});

// Cargar archivos base necesarios
require_once __DIR__ . '/twilio/TwilioException.php';
require_once __DIR__ . '/twilio/TwilioValues.php';
require_once __DIR__ . '/twilio/TwilioCurlClient.php';
require_once __DIR__ . '/twilio/TwilioRestClient.php';

?>