<?php
/**
 * Helpers globales de respuesta y error
 *
 * Se incluye desde index.php justo después de database.php y jwt.php.
 */

/**
 * Registra el error real en el log del servidor y devuelve al cliente
 * un mensaje genérico (o el detalle técnico solo si DEBUG_MODE=true).
 *
 * @param string     $mensajePublico Texto seguro para mostrar al cliente.
 * @param \Exception $e              Excepción capturada (solo se loggea, nunca se expone).
 * @param int        $httpCode       Código HTTP de respuesta (default 500).
 */
function jsonError(string $mensajePublico, \Exception $e, int $httpCode = 500): void {
    error_log('[SAG-ERROR] ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
    http_response_code($httpCode);
    $debugMode = filter_var($_ENV['DEBUG_MODE'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
    $response  = ['success' => false, 'error' => $mensajePublico];
    if ($debugMode) {
        $response['debug'] = $e->getMessage();
    }
    echo json_encode($response);
}
