<?php
/**
 * TWILIO CONVERSATIONAL BOT - SAG GARAGE **REFACTORIZADO**
 * Sistema de agendamiento automático con WhatsApp
 * 
 * ESTA ES LA VERSIÓN REFACTORIZADA que internamente usa la nueva
 * arquitectura SOLID pero mantiene 100% de compatibilidad con la 
 * interfaz original.
 * 
 * Funcionalidades:
 * - Envío de recordatorios con botones
 * - Manejo de respuestas de clientes  
 * - Generación de fechas disponibles
 * - Pre-agendamiento automático
 * - Notificaciones a SAG Garage
 * 
 * MEJORAS REFACTORIZADAS:
 * - Arquitectura SOLID (8 clases especializadas)
 * - Logging centralizado y estructurado
 * - Validación robusta de teléfonos
 * - Manejo de errores mejorado
 * - Código mantenible y testeable
 * 
 * @author Sistema SAG Garage - Refactorización 2026
 * @version 2.0.0 REFACTORIZADO
 * @date 17/04/2026
 */

// Usar la nueva implementación refactorizada
require_once __DIR__ . '/TwilioBot/TwilioConversationalBotRefactored.php';

// Esta clase ahora es simplemente un alias/wrapper para mantener compatibilidad
// La implementación real está en TwilioBot/TwilioConversationalBotRefactored.php

// NO ES UNA HERENCIA - ES UN WRAPPER/PROXY
class TwilioConversationalBot {
    
    /**
     * Instancia de la versión refactorizada
     * @var TwilioConversationalBotRefactored
     */
    private $refactoredBot;
    
    /**
     * Constructor - Mantiene compatibilidad total
     */
    public function __construct() {
        error_log("🔧 TwilioBot: Cargando VERSIÓN REFACTORIZADA (Wrapper Pattern)");
        
        // Crear instancia de la versión refactorizada
        $this->refactoredBot = new TwilioConversationalBotRefactored();
        
        error_log("✅ TwilioBot: Wrapper inicializado - Usando arquitectura SOLID internamente");
    }
    
    /**
     * PASO 1: Enviar recordatorio inicial con botones
     * Mantiene exactamente la misma interfaz que la versión original
     */
    public function enviarRecordatorioInicial($alertaId) {
        error_log("📧 TwilioBot: enviarRecordatorioInicial (REFACTORIZADO) - AlertaID: {$alertaId}");
        
        // Delegar a la versión refactorizada
        return $this->refactoredBot->enviarRecordatorioInicial($alertaId);
    }
    
    /**
     * PASO 2: Procesar respuesta inicial del cliente
     * Mantiene exactamente la misma interfaz que la versión original
     */
    public function procesarRespuestaInicial($alertaId, $respuesta, $messageSid, $webhookData = []) {
        error_log("💬 TwilioBot: procesarRespuestaInicial (REFACTORIZADO) - AlertaID: {$alertaId}, Respuesta: '{$respuesta}'");
        
        // Delegar a la versión refactorizada
        return $this->refactoredBot->procesarRespuestaInicial($alertaId, $respuesta, $messageSid, $webhookData);
    }
    
    /**
     * Obtener estado del servicio
     * NUEVO MÉTODO para debugging y monitoreo
     */
    public function getServiceStatus() {
        return $this->refactoredBot->getServiceStatus();
    }
    
    /**
     * MÉTODOS ADICIONALES QUE PUEDAN EXISTIR EN LA VERSIÓN ORIGINAL
     * Agregamos compatibilidad para métodos que podrían estar siendo usados
     */
    
    /**
     * Compatibilidad: Fallback final cuando el webhook no reconoció el input del cliente.
     * Delega a procesarRespuestaNumericaSimple, que envía mensaje de ayuda si el texto
     * no es un número válido.
     */
    public function procesarSeleccionFecha($alertaId, $seleccion, $messageSid) {
        return $this->refactoredBot->procesarRespuestaNumericaSimple((int)$alertaId, (string)$seleccion, (string)$messageSid);
    }
    
    /**
     * Compatibilidad: Delega confirmación admin al bot refactorizado
     */
    public function procesarConfirmacionSAG($alertaId, $respuesta, $messageSid, $userId = null) {
        return $this->refactoredBot->procesarConfirmacionSAG((int)$alertaId, (string)$respuesta, (string)$messageSid, $userId);
    }
    
    /**
     * Método mágico para capturar cualquier otro método que pueda existir
     */
    public function __call($method, $args) {
        error_log("🔀 TwilioBot: Método '{$method}' llamado en wrapper - delegando si existe");
        
        // Si el método existe en la versión refactorizada, delegarlo
        if (method_exists($this->refactoredBot, $method)) {
            return call_user_func_array([$this->refactoredBot, $method], $args);
        }
        
        // Si no existe, retornar error informativo
        error_log("⚠️ TwilioBot: Método '{$method}' no implementado en versión refactorizada");
        return [
            'success' => false,
            'error' => "Método '{$method}' no implementado en la versión refactorizada"
        ];
    }
}

/**
 * Función helper para crear instancia del bot
 * Mantiene compatibilidad con código existente
 */
function crearTwilioBot() {
    try {
        error_log("🏭 TwilioBot: crearTwilioBot() - Retornando versión REFACTORIZADA");
        return new TwilioConversationalBot();
    } catch (Exception $e) {
        error_log("❌ ERROR creando TwilioBot REFACTORIZADO: " . $e->getMessage());
        return null;
    }
}

?>