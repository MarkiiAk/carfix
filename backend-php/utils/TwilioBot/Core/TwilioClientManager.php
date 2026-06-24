<?php
/**
 * TwilioClientManager - Gestor centralizado del cliente Twilio
 * 
 * Esta clase centraliza la inicialización y manejo del cliente Twilio,
 * eliminando la duplicación de código y proporcionando un punto único
 * de configuración para todas las operaciones de Twilio.
 * 
 * Características:
 * - Singleton pattern para garantizar una sola instancia
 * - Lazy loading del cliente Twilio
 * - Manejo unificado de errores de conexión
 * - Modo simulación para testing sin API calls reales
 * - Logging detallado para debugging
 * 
 * @author Sistema SAG Garage - Refactorización 2026
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/ConfigurationService.php';

use Twilio\Rest\Client;

class TwilioClientManager 
{
    /**
     * Instancia única del manager (Singleton)
     * @var TwilioClientManager|null
     */
    private static $instance = null;
    
    /**
     * Cliente Twilio inicializado
     * @var Client|null
     */
    private $twilioClient = null;
    
    /**
     * Servicio de configuración
     * @var ConfigurationService
     */
    private $configService;
    
    /**
     * Indica si el cliente está en modo simulación
     * @var bool
     */
    private $simulationMode = false;
    
    /**
     * Constructor privado para Singleton pattern
     */
    private function __construct() 
    {
        $this->configService = ConfigurationService::getInstance();
        $this->initializeClient();
    }
    
    /**
     * Obtener la instancia única del manager
     * 
     * @return TwilioClientManager
     */
    public static function getInstance(): TwilioClientManager 
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializar el cliente Twilio
     * 
     * Intenta crear el cliente usando las credenciales de configuración.
     * Si falla o no hay credenciales, activa el modo simulación.
     * 
     * @return void
     */
    private function initializeClient(): void 
    {
        try {
            error_log("🔧 TwilioClientManager: Iniciando inicialización del cliente...");
            
            // Obtener credenciales de Twilio
            $accountSid = $this->configService->getTwilioAccountSid();
            $authToken = $this->configService->getTwilioAuthToken();
            
            // Validar que las credenciales existan
            if (empty($accountSid) || empty($authToken)) {
                error_log("⚠️ TwilioClientManager: Credenciales Twilio no configuradas - Activando modo simulación");
                $this->simulationMode = true;
                return;
            }
            
            // Intentar crear cliente Twilio
            $this->twilioClient = new Client($accountSid, $authToken);
            
            error_log("✅ TwilioClientManager: Cliente Twilio inicializado correctamente para envío REAL");
            error_log("📊 TwilioClientManager: Account SID: " . substr($accountSid, 0, 10) . "...");
            
        } catch (Exception $e) {
            error_log("❌ TwilioClientManager: Error inicializando cliente Twilio: " . $e->getMessage());
            error_log("🔄 TwilioClientManager: Fallback a modo simulación activado");
            $this->simulationMode = true;
            $this->twilioClient = null;
        }
    }
    
    /**
     * Obtener el cliente Twilio inicializado
     * 
     * @return Client|null Cliente Twilio o null si está en modo simulación
     */
    public function getClient(): ?Client 
    {
        return $this->twilioClient;
    }
    
    /**
     * Verificar si el cliente está en modo simulación
     * 
     * @return bool True si está en modo simulación
     */
    public function isSimulationMode(): bool 
    {
        return $this->simulationMode;
    }
    
    /**
     * Obtener información del estado del cliente
     * 
     * Útil para debugging y monitoreo del estado del cliente Twilio
     * 
     * @return array Estado del cliente con información detallada
     */
    public function getClientStatus(): array 
    {
        return [
            'initialized' => $this->twilioClient !== null,
            'simulation_mode' => $this->simulationMode,
            'account_sid' => $this->twilioClient ? 
                substr($this->configService->getTwilioAccountSid(), 0, 10) . '...' : 
                'N/A',
            'ready_for_sending' => $this->twilioClient !== null,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Forzar reinicialización del cliente
     * 
     * Útil cuando se actualizan las credenciales en tiempo de ejecución
     * 
     * @return bool True si la reinicialización fue exitosa
     */
    public function reinitialize(): bool 
    {
        try {
            error_log("🔄 TwilioClientManager: Forzando reinicialización del cliente...");
            
            // Reset estado
            $this->twilioClient = null;
            $this->simulationMode = false;
            
            // Recargar configuración
            $this->configService->reloadConfiguration();
            
            // Reinicializar cliente
            $this->initializeClient();
            
            $success = $this->twilioClient !== null;
            error_log($success ? 
                "✅ TwilioClientManager: Reinicialización exitosa" : 
                "⚠️ TwilioClientManager: Reinicialización falló - modo simulación activo");
            
            return $success;
            
        } catch (Exception $e) {
            error_log("❌ TwilioClientManager: Error en reinicialización: " . $e->getMessage());
            $this->simulationMode = true;
            return false;
        }
    }
    
    /**
     * Validar que el cliente esté listo para envío
     * 
     * Verifica que el cliente esté correctamente inicializado
     * y las credenciales sean válidas.
     * 
     * @return bool True si está listo para envío
     */
    public function isReadyForSending(): bool 
    {
        if ($this->simulationMode) {
            return true; // En simulación siempre está "listo"
        }
        
        return $this->twilioClient !== null;
    }
    
    /**
     * Obtener número de WhatsApp configurado para envío
     * 
     * @return string Número de WhatsApp desde configuración
     */
    public function getWhatsappFromNumber(): string 
    {
        return $this->configService->getWhatsappFrom();
    }
    
    /**
     * Prevenir clonación (Singleton)
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización (Singleton)
     */
    public function __wakeup() 
    {
        throw new Exception("Cannot unserialize singleton");
    }
}