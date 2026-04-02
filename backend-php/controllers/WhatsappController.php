<?php
/**
 * WhatsappController.php
 * Controlador para manejar las operaciones de WhatsApp
 * Integra con TwilioConversationalBot para funcionalidad completa
 */

require_once __DIR__ . '/../utils/TwilioConversationalBot.php';

class WhatsappController {
    private $db;
    private $twilioBot;
    
    public function __construct($db = null) {
        if ($db === null) {
            $this->db = Database::getInstance()->getConnection();
        } else {
            $this->db = $db;
        }
        $this->twilioBot = new TwilioConversationalBot();
    }
    
    /**
     * Obtener estadísticas del sistema WhatsApp
     */
    public function obtenerEstadisticas() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_mensajes,
                    COUNT(CASE WHEN fecha_envio >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as mensajes_24h,
                    COUNT(CASE WHEN estado = 'enviado' THEN 1 END) as mensajes_enviados,
                    COUNT(CASE WHEN estado = 'pendiente' THEN 1 END) as mensajes_pendientes,
                    COUNT(CASE WHEN estado = 'error' THEN 1 END) as mensajes_error
                FROM whatsapp_cola
            ");
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Obtener configuración actual
            $config = $this->obtenerConfiguracion();
            
            return [
                'success' => true,
                'estadisticas' => $stats,
                'configuracion' => $config,
                'sistema_activo' => $this->isSistemaActivo()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener configuración de Twilio
     */
    public function obtenerConfiguracion() {
        try {
            $stmt = $this->db->prepare("SELECT config_key, config_value FROM twilio_config");
            $stmt->execute();
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($configs as $config) {
                $result[$config['config_key']] = $config['config_value'];
            }
            
            return $result;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Verificar si el sistema está activo
     */
    public function isSistemaActivo() {
        $config = $this->obtenerConfiguracion();
        return !empty($config['account_sid']) && !empty($config['auth_token']);
    }
    
    /**
     * Obtener logs recientes
     */
    public function obtenerLogReciente($limite = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT fecha_envio, telefono, mensaje, estado, response_twilio, fecha_creacion
                FROM whatsapp_cola 
                ORDER BY fecha_creacion DESC 
                LIMIT :limite
            ");
            $stmt->bindValue(':limite', (int)$limite, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Test de conexión con Twilio
     */
    public function testConexion() {
        try {
            $config = $this->obtenerConfiguracion();
            
            if (empty($config['account_sid']) || empty($config['auth_token'])) {
                return [
                    'success' => false,
                    'error' => 'Credenciales de Twilio no configuradas'
                ];
            }
            
            // Probar conexión básica con Twilio
            $resultado = $this->twilioBot->testConexion();
            
            return [
                'success' => $resultado['success'],
                'message' => $resultado['message'] ?? 'Test completado',
                'details' => $resultado['details'] ?? null
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error en test de conexión: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Procesar cola de WhatsApp manualmente
     */
    public function procesarColaWhatsApp() {
        try {
            $resultado = $this->twilioBot->procesarCola();
            return [
                'success' => true,
                'procesados' => $resultado['procesados'] ?? 0,
                'errores' => $resultado['errores'] ?? 0,
                'message' => 'Cola procesada exitosamente'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al procesar cola: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Obtener valor de configuración específica
     */
    public function getConfig($key, $default = null) {
        try {
            $stmt = $this->db->prepare("SELECT config_value FROM twilio_config WHERE config_key = :key");
            $stmt->bindValue(':key', $key);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['config_value'] : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * Actualizar configuración
     */
    public function updateConfig($key, $value) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO twilio_config (config_key, config_value) 
                VALUES (:key, :value)
                ON DUPLICATE KEY UPDATE config_value = :value2
            ");
            $stmt->bindValue(':key', $key);
            $stmt->bindValue(':value', $value);
            $stmt->bindValue(':value2', $value);
            
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Agregar número a blacklist
     */
    public function agregarABlacklist($telefono, $motivo, $userId = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO whatsapp_blacklist (telefono, motivo, fecha_agregado, agregado_por)
                VALUES (:telefono, :motivo, NOW(), :user_id)
            ");
            $stmt->bindValue(':telefono', $telefono);
            $stmt->bindValue(':motivo', $motivo);
            $stmt->bindValue(':user_id', $userId);
            
            return $stmt->execute();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Enviar mensaje de prueba
     */
    public function enviarMensajePrueba($telefono, $mensaje) {
        try {
            return $this->twilioBot->enviarMensaje($telefono, $mensaje);
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al enviar mensaje: ' . $e->getMessage()
            ];
        }
    }
}