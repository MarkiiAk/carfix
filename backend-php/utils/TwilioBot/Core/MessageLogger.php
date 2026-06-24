<?php
/**
 * MessageLogger - Servicio centralizado de logging para mensajes
 * 
 * Esta clase maneja todo el logging relacionado con mensajes de WhatsApp,
 * proporcionando un registro detallado y estructurado de todas las
 * comunicaciones del sistema.
 * 
 * Características:
 * - Registro estructurado en base de datos
 * - Logging a archivos para debugging
 * - Formateo consistente de logs
 * - Filtrado por niveles de logging
 * - Rotación automática de archivos de log
 * 
 * @author Sistema CarFix - Refactorización 2026
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config/database.php';

class MessageLogger 
{
    /**
     * Instancia única del logger (Singleton)
     * @var MessageLogger|null
     */
    private static $instance = null;
    
    /**
     * Conexión a la base de datos
     * @var PDO
     */
    private $db;
    
    /**
     * Ruta del archivo de log
     * @var string
     */
    private $logFilePath;
    
    /**
     * Niveles de logging disponibles
     * @var array
     */
    private const LOG_LEVELS = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];
    
    /**
     * Constructor privado para Singleton pattern
     */
    private function __construct() 
    {
        $this->initializeDatabase();
        $this->initializeFileLogging();
    }
    
    /**
     * Obtener la instancia única del logger
     * 
     * @return MessageLogger
     */
    public static function getInstance(): MessageLogger 
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
        } catch (Exception $e) {
            error_log("❌ MessageLogger: Error conectando a BD: " . $e->getMessage());
            $this->db = null;
        }
    }
    
    /**
     * Inicializar logging a archivos
     * 
     * @return void
     */
    private function initializeFileLogging(): void 
    {
        $this->logFilePath = __DIR__ . '/../../logs/sag_whatsapp_' . date('Y-m-d') . '.log';
        
        // Crear directorio de logs si no existe
        $logDir = dirname($this->logFilePath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }
    
    /**
     * Registrar mensaje en la base de datos
     * 
     * @param int $alertaId ID de la alerta
     * @param string $messageSid SID del mensaje de Twilio
     * @param string $direction Dirección: 'inbound' o 'outbound'
     * @param string $from Número de origen
     * @param string $to Número de destino
     * @param string $body Contenido del mensaje
     * @param string $type Tipo: 'text', 'template', 'interactive'
     * @param string $step Paso del flujo conversacional
     * @param array|null $twilioResponse Respuesta completa de Twilio
     * @return bool True si se registró exitosamente
     */
    public function logMessage(
        int $alertaId, 
        string $messageSid, 
        string $direction, 
        string $from, 
        string $to, 
        string $body, 
        string $type, 
        string $step, 
        ?array $twilioResponse = null
    ): bool {
        try {
            // Log a archivo primero (siempre funciona)
            $this->logToFile('INFO', "Registrando mensaje", [
                'alerta_id' => $alertaId,
                'message_sid' => $messageSid,
                'direction' => $direction,
                'type' => $type,
                'step' => $step
            ]);
            
            // Intentar registrar en BD
            if (!$this->db) {
                $this->logToFile('WARNING', "BD no disponible - mensaje no registrado en BD", [
                    'message_sid' => $messageSid
                ]);
                return false;
            }
            
            $sql = "INSERT INTO conversaciones_whatsapp 
                    (alerta_id, twilio_message_sid, direction, from_number, to_number, 
                     message_body, message_type, conversation_step, twilio_response, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                $alertaId,
                $messageSid,
                $direction,
                $from,
                $to,
                $body,
                $type,
                $step,
                $twilioResponse ? json_encode($twilioResponse) : null
            ]);
            
            if ($result) {
                $this->logToFile('INFO', "Mensaje registrado en BD exitosamente", [
                    'message_sid' => $messageSid,
                    'db_id' => $this->db->lastInsertId()
                ]);
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->logToFile('ERROR', "Error registrando mensaje en BD", [
                'message_sid' => $messageSid,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Log de operación de envío de mensaje
     * 
     * @param string $operation Operación realizada
     * @param array $data Datos del contexto
     * @param string $level Nivel de log
     * @return void
     */
    public function logOperation(string $operation, array $data, string $level = 'INFO'): void 
    {
        $this->logToFile($level, $operation, $data);
    }
    
    /**
     * Log de error con contexto detallado
     * 
     * @param string $errorMessage Mensaje de error
     * @param Exception|null $exception Excepción capturada
     * @param array $context Contexto adicional
     * @return void
     */
    public function logError(string $errorMessage, ?Exception $exception = null, array $context = []): void 
    {
        $errorData = [
            'message' => $errorMessage,
            'context' => $context
        ];
        
        if ($exception) {
            $errorData['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        
        $this->logToFile('ERROR', $errorMessage, $errorData);
    }
    
    /**
     * Log de nivel INFO — alias de logOperation para consistencia interna
     */
    public function logInfo(string $message, array $data = []): void
    {
        $this->logToFile('INFO', $message, $data);
    }

    /**
     * Log de debugging con información detallada
     *
     * @param string $message Mensaje de debug
     * @param array $data Datos para debugging
     * @return void
     */
    public function logDebug(string $message, array $data = []): void
    {
        $this->logToFile('DEBUG', $message, $data);
    }
    
    /**
     * Log específico para flujo conversacional
     * 
     * @param int $alertaId ID de la alerta
     * @param string $step Paso del flujo
     * @param string $action Acción realizada
     * @param array $data Datos del contexto
     * @return void
     */
    public function logConversationFlow(int $alertaId, string $step, string $action, array $data = []): void 
    {
        $flowData = array_merge([
            'alerta_id' => $alertaId,
            'conversation_step' => $step,
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s')
        ], $data);
        
        $this->logToFile('INFO', "FlowStep: {$step} - {$action}", $flowData);
    }
    
    /**
     * Escribir log a archivo con formato estructurado
     * 
     * @param string $level Nivel del log
     * @param string $message Mensaje principal
     * @param array $data Datos adicionales
     * @return void
     */
    private function logToFile(string $level, string $message, array $data = []): void 
    {
        try {
            $timestamp = date('Y-m-d H:i:s');
            $pid = getmypid();
            
            // Formato: [TIMESTAMP] [LEVEL] [PID] MESSAGE | DATA
            $logEntry = "[{$timestamp}] [{$level}] [{$pid}] {$message}";
            
            if (!empty($data)) {
                $logEntry .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
            }
            
            $logEntry .= PHP_EOL;
            
            // Escribir a archivo (con lock para concurrencia)
            file_put_contents($this->logFilePath, $logEntry, FILE_APPEND | LOCK_EX);
            
            // También log a error_log de PHP para compatibilidad
            error_log("🔍 MessageLogger [{$level}]: {$message}" . 
                     (!empty($data) ? " - " . json_encode($data) : ""));
            
        } catch (Exception $e) {
            // Fallback a error_log si falla el archivo
            error_log("❌ MessageLogger: Error escribiendo a archivo: " . $e->getMessage());
            error_log("📝 Original log: [{$level}] {$message}");
        }
    }
    
    /**
     * Obtener logs recientes para debugging
     * 
     * @param int $limit Número máximo de registros
     * @param string|null $alertaId Filtrar por alerta específica
     * @return array Logs recientes
     */
    public function getRecentLogs(int $limit = 50, ?string $alertaId = null): array 
    {
        if (!$this->db) {
            return [];
        }
        
        try {
            $sql = "SELECT * FROM conversaciones_whatsapp WHERE 1=1";
            $params = [];
            
            if ($alertaId) {
                $sql .= " AND alerta_id = ?";
                $params[] = $alertaId;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ?";
            $params[] = $limit;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->logError("Error obteniendo logs recientes", $e);
            return [];
        }
    }
    
    /**
     * Limpiar logs antiguos para mantenimiento
     * 
     * @param int $daysToKeep Días de logs a mantener
     * @return int Número de registros eliminados
     */
    public function cleanupOldLogs(int $daysToKeep = 30): int 
    {
        if (!$this->db) {
            return 0;
        }
        
        try {
            $sql = "DELETE FROM conversaciones_whatsapp 
                    WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$daysToKeep]);
            
            $deletedCount = $stmt->rowCount();
            
            $this->logToFile('INFO', "Limpieza de logs completada", [
                'deleted_records' => $deletedCount,
                'days_kept' => $daysToKeep
            ]);
            
            return $deletedCount;
            
        } catch (Exception $e) {
            $this->logError("Error en limpieza de logs", $e);
            return 0;
        }
    }
    
    /**
     * Obtener estadísticas de logs
     * 
     * @return array Estadísticas detalladas
     */
    public function getLogStatistics(): array 
    {
        if (!$this->db) {
            return ['error' => 'BD no disponible'];
        }
        
        try {
            $sql = "SELECT 
                        COUNT(*) as total_messages,
                        SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as sent_messages,
                        SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as received_messages,
                        COUNT(DISTINCT alerta_id) as unique_conversations,
                        DATE(MIN(created_at)) as oldest_log,
                        DATE(MAX(created_at)) as newest_log
                    FROM conversaciones_whatsapp";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            $this->logError("Error obteniendo estadísticas", $e);
            return ['error' => $e->getMessage()];
        }
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