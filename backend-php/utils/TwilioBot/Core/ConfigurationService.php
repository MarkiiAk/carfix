<?php
/**
 * ConfigurationService - Servicio centralizado de configuración
 * 
 * Esta clase unifica el acceso a configuraciones desde múltiples fuentes:
 * - Variables de entorno (.env)
 * - Base de datos (tabla twilio_config)
 * - Valores por defecto del sistema
 * 
 * Características:
 * - Singleton pattern para consistencia
 * - Caché de configuraciones para performance
 * - Fallback automático entre fuentes
 * - Recarga de configuración en tiempo real
 * - Logging detallado de configuraciones cargadas
 * 
 * @author Sistema CarFix - Refactorización 2026
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config/database.php';

class ConfigurationService 
{
    /**
     * Instancia única del servicio (Singleton)
     * @var ConfigurationService|null
     */
    private static $instance = null;
    
    /**
     * Conexión a la base de datos
     * @var PDO
     */
    private $db;
    
    /**
     * Caché de configuraciones cargadas
     * @var array
     */
    private $configCache = [];
    
    /**
     * Variables de entorno cargadas
     * @var array
     */
    private $envVars = [];
    
    /**
     * Indica si las configuraciones han sido cargadas
     * @var bool
     */
    private $isLoaded = false;
    
    /**
     * Constructor privado para Singleton pattern
     */
    private function __construct() 
    {
        $this->initializeDatabase();
        $this->loadConfigurations();
    }
    
    /**
     * Obtener la instancia única del servicio
     * 
     * @return ConfigurationService
     */
    public static function getInstance(): ConfigurationService 
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Inicializar conexión a la base de datos
     * 
     * @return void
     */
    private function initializeDatabase(): void 
    {
        try {
            $database = Database::getInstance();
            $this->db = $database->getConnection();
            error_log("📊 ConfigurationService: Conexión a BD establecida");
        } catch (Exception $e) {
            error_log("❌ ConfigurationService: Error conectando a BD: " . $e->getMessage());
            $this->db = null;
        }
    }
    
    /**
     * Cargar todas las configuraciones
     * 
     * Carga configuraciones desde .env y base de datos,
     * estableciendo prioridades y fallbacks apropiados.
     * 
     * @return void
     */
    private function loadConfigurations(): void 
    {
        try {
            error_log("🔧 ConfigurationService: Iniciando carga de configuraciones...");
            
            // 1. Cargar variables de entorno
            $this->loadEnvironmentVariables();
            
            // 2. Cargar configuraciones de base de datos
            $this->loadDatabaseConfigurations();
            
            $this->isLoaded = true;
            error_log("✅ ConfigurationService: Configuraciones cargadas exitosamente");
            
        } catch (Exception $e) {
            error_log("❌ ConfigurationService: Error cargando configuraciones: " . $e->getMessage());
        }
    }
    
    /**
     * Cargar variables de entorno desde archivo .env
     * 
     * @return void
     */
    private function loadEnvironmentVariables(): void 
    {
        $envPath = __DIR__ . '/../../.env';
        
        if (!file_exists($envPath)) {
            error_log("⚠️ ConfigurationService: Archivo .env no encontrado en: {$envPath}");
            return;
        }
        
        try {
            $envContent = file_get_contents($envPath);
            $envLines = explode("\n", $envContent);
            
            foreach ($envLines as $line) {
                $line = trim($line);
                
                // Ignorar comentarios y líneas vacías
                if (empty($line) || $line[0] === '#') {
                    continue;
                }
                
                // Procesar líneas con formato KEY=VALUE
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $this->envVars[trim($key)] = trim($value);
                }
            }
            
            error_log("📝 ConfigurationService: Cargadas " . count($this->envVars) . " variables de entorno");
            
        } catch (Exception $e) {
            error_log("❌ ConfigurationService: Error cargando .env: " . $e->getMessage());
        }
    }
    
    /**
     * Cargar configuraciones desde la base de datos
     * 
     * @return void
     */
    private function loadDatabaseConfigurations(): void 
    {
        if (!$this->db) {
            error_log("⚠️ ConfigurationService: BD no disponible, usando solo variables de entorno");
            return;
        }
        
        try {
            $sql = "SELECT config_key, config_value FROM twilio_config WHERE is_active = TRUE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($configs as $config) {
                $this->configCache[$config['config_key']] = $config['config_value'];
            }
            
            error_log("🗄️ ConfigurationService: Cargadas " . count($configs) . " configuraciones de BD");
            
        } catch (Exception $e) {
            error_log("❌ ConfigurationService: Error cargando configuraciones de BD: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener Account SID de Twilio
     * 
     * Prioridad: BD > .env > null
     * 
     * @return string|null
     */
    public function getTwilioAccountSid(): ?string 
    {
        return $this->getConfig('account_sid', 'TWILIO_ACCOUNT_SID');
    }
    
    /**
     * Obtener Auth Token de Twilio
     * 
     * Prioridad: BD > .env > null
     * 
     * @return string|null
     */
    public function getTwilioAuthToken(): ?string 
    {
        return $this->getConfig('auth_token', 'TWILIO_AUTH_TOKEN');
    }
    
    /**
     * Obtener número de WhatsApp para envío
     * 
     * Prioridad: BD > .env > default
     * 
     * @return string
     */
    public function getWhatsappFrom(): string 
    {
        return $this->getConfig('whatsapp_from', 'TWILIO_WHATSAPP_FROM', 'whatsapp:+525535240846');
    }
    
    /**
     * Obtener teléfono del administrador SAG
     * 
     * @return string|null
     */
    public function getSagAdminPhone(): ?string 
    {
        return $this->getConfig('sag_admin_phone', 'SAG_ADMIN_PHONE');
    }
    
    /**
     * Obtener configuración específica con fallbacks
     * 
     * @param string $dbKey Clave en la base de datos
     * @param string|null $envKey Clave en variables de entorno
     * @param string|null $defaultValue Valor por defecto
     * @return string|null
     */
    public function getConfig(string $dbKey, ?string $envKey = null, ?string $defaultValue = null): ?string 
    {
        // 1. Intentar desde caché de BD
        if (isset($this->configCache[$dbKey]) && !empty($this->configCache[$dbKey])) {
            return $this->configCache[$dbKey];
        }
        
        // 2. Intentar desde variables de entorno
        if ($envKey && isset($this->envVars[$envKey]) && !empty($this->envVars[$envKey])) {
            return $this->envVars[$envKey];
        }
        
        // 3. Usar valor por defecto
        return $defaultValue;
    }
    
    /**
     * Obtener configuración de horarios de atención
     * 
     * @return string
     */
    public function getHorariosAtencion(): string 
    {
        return $this->getConfig('horarios_atencion', null, '09:00,11:00,14:00,16:00');
    }
    
    /**
     * Obtener hora límite para día siguiente
     * 
     * @return string
     */
    public function getHoraLimiteDiaSiguiente(): string 
    {
        return $this->getConfig('hora_limite_dia_siguiente', null, '18:00');
    }
    
    /**
     * Obtener días laborales
     * 
     * @return array
     */
    public function getDiasLaborales(): array 
    {
        $dias = $this->getConfig('dias_laborales', null, 'Monday,Tuesday,Wednesday,Thursday,Friday,Saturday');
        return explode(',', $dias);
    }
    
    /**
     * Obtener días festivos del año
     * 
     * @return array
     */
    public function getDiasFestivos(): array 
    {
        $festivos = $this->getConfig('dias_festivos_2026', null, '[]');
        return json_decode($festivos, true) ?: [];
    }
    
    /**
     * Obtener capacidad por slot de calendario
     * 
     * @return int
     */
    public function getCapacidadPorSlot(): int 
    {
        $capacidad = $this->getConfig('capacidad_por_slot', null, '2');
        return (int)$capacidad;
    }
    
    /**
     * Obtener máximo de slots a mostrar
     * 
     * @return int
     */
    public function getSlotsMaximoMostrar(): int 
    {
        $slots = $this->getConfig('slots_maximo_mostrar', null, '8');
        return (int)$slots;
    }
    
    /**
     * Obtener un mensaje de template específico
     * 
     * @param string $messageKey Clave del mensaje
     * @return string
     */
    public function getMessage(string $messageKey): string 
    {
        $defaultMessages = [
            'mensaje_rechazo' => 'Entendemos. ¡Estaremos aquí cuando lo necesites! 🚗',
            'mensaje_confirmacion' => '¡Excelente! Tu cita está confirmada para {fecha} a las {hora}. ¡Te esperamos! 🎉',
            'mensaje_contacto_directo' => 'Nuestro equipo se pondrá en contacto contigo para coordinar el mejor horario. ¡Gracias por confiar en nosotros! 📞',
            'mensaje_no_gracias' => 'Entendemos perfectamente. Tu vehículo siempre será bienvenido cuando sea el momento correcto. ¡Que tengas un excelente día! 🚗✨'
        ];
        
        return $this->getConfig($messageKey, null, $defaultMessages[$messageKey] ?? '');
    }
    
    /**
     * Recargar todas las configuraciones
     * 
     * Útil cuando se actualizan configuraciones en tiempo real
     * 
     * @return void
     */
    public function reloadConfiguration(): void 
    {
        error_log("🔄 ConfigurationService: Recargando configuraciones...");
        
        // Limpiar caché
        $this->configCache = [];
        $this->envVars = [];
        $this->isLoaded = false;
        
        // Recargar todo
        $this->loadConfigurations();
        
        error_log("✅ ConfigurationService: Configuraciones recargadas");
    }
    
    /**
     * Obtener todas las configuraciones cargadas (para debugging)
     * 
     * @return array
     */
    public function getAllConfigurations(): array 
    {
        return [
            'env_vars_count' => count($this->envVars),
            'db_configs_count' => count($this->configCache),
            'is_loaded' => $this->isLoaded,
            'twilio_configured' => !empty($this->getTwilioAccountSid()) && !empty($this->getTwilioAuthToken()),
            'whatsapp_from' => $this->getWhatsappFrom(),
            'admin_phone_configured' => !empty($this->getSagAdminPhone())
        ];
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