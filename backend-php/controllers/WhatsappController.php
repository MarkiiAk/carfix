<?php
/**
 * WhatsAppController - SAG Garage
 * 
 * Controlador principal para el sistema de WhatsApp Business API
 * Maneja envío de mensajes, templates, configuración y logs
 * 
 * @author Marco Candiani
 * @version 1.0
 * @date 30/03/2026
 */

require_once __DIR__ . '/../utils/WhatsappAPI.php';
require_once __DIR__ . '/../utils/TemplateEngine.php';

class WhatsappController {
    private $db;
    private $whatsappAPI;
    private $templateEngine;
    private $config = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->loadConfig();
        $this->whatsappAPI = new WhatsappAPI($this->config);
        $this->templateEngine = new TemplateEngine();
    }

    /**
     * Cargar configuraciones desde la base de datos
     */
    private function loadConfig() {
        $query = "SELECT clave, valor, tipo FROM whatsapp_config";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($configs as $config) {
            $valor = $config['valor'];
            
            // Convertir según el tipo
            switch ($config['tipo']) {
                case 'number':
                    $valor = is_numeric($valor) ? (float)$valor : 0;
                    break;
                case 'boolean':
                    $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN);
                    break;
                case 'json':
                    $valor = json_decode($valor, true) ?? [];
                    break;
            }
            
            $this->config[$config['clave']] = $valor;
        }
    }

    /**
     * Verificar si el sistema WhatsApp está activo
     */
    public function isSistemaActivo() {
        return isset($this->config['activo']) && $this->config['activo'] === true;
    }

    /**
     * Procesar cola de mensajes WhatsApp (llamado por cron job)
     * Este es el método principal que ejecuta el envío masivo
     */
    public function procesarColaWhatsApp() {
        if (!$this->isSistemaActivo()) {
            return [
                'success' => false,
                'error' => 'Sistema WhatsApp desactivado',
                'enviados' => 0,
                'errores' => 0
            ];
        }

        $resultado = [
            'success' => true,
            'enviados' => 0,
            'errores' => 0,
            'omitidos' => 0,
            'mensajes' => []
        ];

        try {
            // Obtener alertas pendientes de envío
            $alertasPendientes = $this->obtenerAlertasPendientes();
            
            if (empty($alertasPendientes)) {
                $resultado['mensaje'] = 'No hay alertas pendientes para envío';
                return $resultado;
            }

            $limiteDiario = $this->config['limite_mensajes_dia'] ?? 100;
            $mensajesEnviadosHoy = $this->contarMensajesHoy();

            foreach ($alertasPendientes as $alerta) {
                // Verificar límite diario
                if ($mensajesEnviadosHoy >= $limiteDiario) {
                    $resultado['mensajes'][] = "Límite diario alcanzado ({$limiteDiario} mensajes)";
                    break;
                }

                // Procesar alerta individual
                $resultadoEnvio = $this->procesarAlertaIndividual($alerta);
                
                if ($resultadoEnvio['success']) {
                    $resultado['enviados']++;
                    $mensajesEnviadosHoy++;
                } elseif ($resultadoEnvio['omitido']) {
                    $resultado['omitidos']++;
                } else {
                    $resultado['errores']++;
                }
                
                $resultado['mensajes'][] = $resultadoEnvio['mensaje'];
            }

            return $resultado;

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error en procesamiento: ' . $e->getMessage(),
                'enviados' => $resultado['enviados'],
                'errores' => $resultado['errores']
            ];
        }
    }

    /**
     * Obtener alertas pendientes de envío
     */
    private function obtenerAlertasPendientes() {
        $query = "
            SELECT 
                a.id,
                a.cliente_id,
                a.orden_id,
                a.vehiculo_id,
                a.servicios_que_dispararon,
                a.fecha_ultimo_servicio,
                a.dias_desde_servicio,
                
                c.nombre as cliente_nombre,
                c.telefono as cliente_telefono,
                c.email as cliente_email,
                
                v.marca as vehiculo_marca,
                v.modelo as vehiculo_modelo,
                v.anio as vehiculo_anio,
                v.placas as vehiculo_placas
                
            FROM alertas_servicio a
            INNER JOIN clientes c ON a.cliente_id = c.id
            INNER JOIN vehiculos v ON a.vehiculo_id = v.id
            
            WHERE a.whatsapp_estado = 'pendiente'
            AND a.estado = 'pendiente'
            AND c.telefono IS NOT NULL
            AND c.telefono != ''
            
            ORDER BY a.fecha_generada ASC
            LIMIT 50
        ";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Procesar una alerta individual
     */
    private function procesarAlertaIndividual($alerta) {
        try {
            // 1. Validaciones pre-envío
            $validacion = $this->validarEnvio($alerta);
            if (!$validacion['valido']) {
                $this->marcarAlertaComoOmitida($alerta['id'], $validacion['motivo']);
                return [
                    'success' => false,
                    'omitido' => true,
                    'mensaje' => "Omitido {$alerta['cliente_nombre']}: {$validacion['motivo']}"
                ];
            }

            // 2. Preparar mensaje
            $datosTemplate = $this->prepararDatosTemplate($alerta);
            $template = $this->seleccionarTemplate($alerta);
            $mensaje = $this->templateEngine->renderTemplate($template['contenido'], $datosTemplate);

            // 3. Formatear teléfono
            $telefono = $this->formatearTelefono($alerta['cliente_telefono']);

            // 4. Enviar mensaje
            $resultadoAPI = $this->whatsappAPI->enviarMensaje($telefono, $mensaje);

            if ($resultadoAPI['success']) {
                // Envío exitoso
                $this->registrarEnvioExitoso($alerta, $template, $mensaje, $resultadoAPI);
                return [
                    'success' => true,
                    'mensaje' => "Enviado a {$alerta['cliente_nombre']} ({$telefono})"
                ];
            } else {
                // Error en envío
                $this->registrarErrorEnvio($alerta, $template, $mensaje, $resultadoAPI);
                return [
                    'success' => false,
                    'mensaje' => "Error {$alerta['cliente_nombre']}: {$resultadoAPI['error']}"
                ];
            }

        } catch (Exception $e) {
            $this->registrarErrorInterno($alerta['id'], $e->getMessage());
            return [
                'success' => false,
                'mensaje' => "Error interno {$alerta['cliente_nombre']}: " . $e->getMessage()
            ];
        }
    }

    /**
     * Validar si se puede enviar mensaje a este cliente
     */
    private function validarEnvio($alerta) {
        // 1. Verificar blacklist
        if ($this->estaEnBlacklist($alerta['cliente_telefono'])) {
            return ['valido' => false, 'motivo' => 'Número en blacklist'];
        }

        // 2. Verificar cooldown (no enviar si ya se envió recientemente)
        $diasCooldown = $this->config['dias_cooldown'] ?? 30;
        if ($this->tieneEnvioReciente($alerta['cliente_id'], $diasCooldown)) {
            return ['valido' => false, 'motivo' => "Cooldown activo ({$diasCooldown} días)"];
        }

        // 3. Verificar teléfono válido
        if (!$this->esNumeroValido($alerta['cliente_telefono'])) {
            return ['valido' => false, 'motivo' => 'Número de teléfono inválido'];
        }

        // 4. Verificar que no se haya enviado ya para esta alerta específica
        if ($this->yaSeEnvioParaEstaAlerta($alerta['id'])) {
            return ['valido' => false, 'motivo' => 'Ya enviado para esta alerta'];
        }

        return ['valido' => true];
    }

    /**
     * Verificar si número está en blacklist
     */
    private function estaEnBlacklist($telefono) {
        $telefonoFormateado = $this->formatearTelefono($telefono);
        
        $query = "SELECT id FROM whatsapp_blacklist WHERE telefono = ? AND activo = TRUE";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$telefonoFormateado]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Verificar si tiene envío reciente
     */
    private function tieneEnvioReciente($clienteId, $dias) {
        $query = "
            SELECT id FROM whatsapp_logs 
            WHERE cliente_id = ? 
            AND estado IN ('enviado', 'entregado', 'leido')
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$clienteId, $dias]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Verificar si ya se envió para esta alerta
     */
    private function yaSeEnvioParaEstaAlerta($alertaId) {
        $query = "
            SELECT id FROM whatsapp_logs 
            WHERE alerta_id = ? 
            AND estado IN ('enviado', 'entregado', 'leido')
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$alertaId]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Validar formato de número de teléfono
     */
    private function esNumeroValido($telefono) {
        if (empty($telefono)) return false;
        
        // Limpiar número
        $numeroLimpio = preg_replace('/[^0-9]/', '', $telefono);
        
        // Verificar longitud (8-12 dígitos para México)
        $longitud = strlen($numeroLimpio);
        return $longitud >= 8 && $longitud <= 12;
    }

    /**
     * Formatear número de teléfono a formato internacional
     */
    private function formatearTelefono($telefono) {
        // Usar la función de MySQL si está disponible
        try {
            $query = "SELECT FormatearTelefono(?) as telefono_formateado";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$telefono]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado && !empty($resultado['telefono_formateado'])) {
                return $resultado['telefono_formateado'];
            }
        } catch (Exception $e) {
            // Fallback a formateo manual
        }

        // Formateo manual como fallback
        $numeroLimpio = preg_replace('/[^0-9]/', '', $telefono);
        
        // Si ya empieza con 52 y tiene 12 dígitos
        if (substr($numeroLimpio, 0, 2) === '52' && strlen($numeroLimpio) === 12) {
            return '+' . $numeroLimpio;
        }
        
        // Si tiene 10 dígitos, agregar +52
        if (strlen($numeroLimpio) === 10) {
            return '+52' . $numeroLimpio;
        }
        
        // Si tiene 8 dígitos, agregar +521 (ciudad de México)
        if (strlen($numeroLimpio) === 8) {
            return '+521' . $numeroLimpio;
        }
        
        return '+52' . $numeroLimpio;
    }

    /**
     * Seleccionar template apropiado para la alerta
     */
    private function seleccionarTemplate($alerta) {
        $servicios = json_decode($alerta['servicios_que_dispararon'], true) ?? [];
        
        // Buscar template específico por servicio
        foreach ($servicios as $servicio) {
            $template = $this->buscarTemplatePorServicio($servicio);
            if ($template) {
                return $template;
            }
        }
        
        // Template por defecto
        $templateDefault = $this->config['template_default'] ?? 'recordatorio_general';
        return $this->obtenerTemplatePorNombre($templateDefault);
    }

    /**
     * Buscar template por tipo de servicio
     */
    private function buscarTemplatePorServicio($servicio) {
        $query = "
            SELECT * FROM whatsapp_templates 
            WHERE activo = TRUE 
            AND (tipo_servicio = ? OR FIND_IN_SET(?, tipo_servicio) > 0)
            ORDER BY conversion_rate DESC
            LIMIT 1
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$servicio, $servicio]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener template por nombre
     */
    private function obtenerTemplatePorNombre($nombre) {
        $query = "SELECT * FROM whatsapp_templates WHERE nombre = ? AND activo = TRUE";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$nombre]);
        
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si no encuentra el template, usar uno genérico
        if (!$template) {
            $query = "SELECT * FROM whatsapp_templates WHERE activo = TRUE ORDER BY id ASC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return $template;
    }

    /**
     * Preparar datos para el template
     */
    private function prepararDatosTemplate($alerta) {
        $servicios = json_decode($alerta['servicios_que_dispararon'], true) ?? [];
        $primerServicio = $servicios[0] ?? 'mantenimiento';
        
        return [
            'cliente' => $alerta['cliente_nombre'],
            'vehiculo' => trim($alerta['vehiculo_marca'] . ' ' . $alerta['vehiculo_modelo']),
            'servicio' => $primerServicio,
            'tiempo' => $this->calcularTiempoTranscurrido($alerta['dias_desde_servicio']),
            'telefono_sag' => $this->config['sag_telefono'] ?? '',
            'direccion_sag' => $this->config['sag_direccion'] ?? '',
            'horarios_sag' => $this->config['sag_horarios'] ?? '',
            'nombre_sag' => $this->config['sag_nombre'] ?? 'SAG Garage'
        ];
    }

    /**
     * Calcular tiempo transcurrido en formato legible
     */
    private function calcularTiempoTranscurrido($dias) {
        if ($dias < 30) {
            return $dias . ($dias === 1 ? ' día' : ' días');
        } elseif ($dias < 365) {
            $meses = floor($dias / 30);
            return $meses . ($meses === 1 ? ' mes' : ' meses');
        } else {
            $años = floor($dias / 365);
            return $años . ($años === 1 ? ' año' : ' años');
        }
    }

    /**
     * Registrar envío exitoso
     */
    private function registrarEnvioExitoso($alerta, $template, $mensaje, $resultadoAPI) {
        // Actualizar alerta
        $this->actualizarEstadoAlerta($alerta['id'], 'enviado', $template['id'], $resultadoAPI['message_id'] ?? null);
        
        // Registrar en logs
        $this->crearLogWhatsapp([
            'alerta_id' => $alerta['id'],
            'cliente_id' => $alerta['cliente_id'],
            'telefono' => $this->formatearTelefono($alerta['cliente_telefono']),
            'template_id' => $template['id'],
            'mensaje' => $mensaje,
            'estado' => 'enviado',
            'whatsapp_message_id' => $resultadoAPI['message_id'] ?? null,
            'metadata' => json_encode([
                'vehiculo' => $alerta['vehiculo_marca'] . ' ' . $alerta['vehiculo_modelo'],
                'dias_servicio' => $alerta['dias_desde_servicio'],
                'api_response' => $resultadoAPI
            ])
        ]);
    }

    /**
     * Registrar error en envío
     */
    private function registrarErrorEnvio($alerta, $template, $mensaje, $resultadoAPI) {
        $intentos = $this->obtenerIntentosEnvio($alerta['id']) + 1;
        $maxIntentos = $this->config['max_intentos_envio'] ?? 3;
        
        $estadoFinal = $intentos >= $maxIntentos ? 'error' : 'pendiente';
        
        // Actualizar alerta
        $this->actualizarEstadoAlerta(
            $alerta['id'], 
            $estadoFinal, 
            $template['id'], 
            null,
            $resultadoAPI['error'] ?? 'Error desconocido',
            $intentos
        );
        
        // Registrar en logs
        $this->crearLogWhatsapp([
            'alerta_id' => $alerta['id'],
            'cliente_id' => $alerta['cliente_id'],
            'telefono' => $this->formatearTelefono($alerta['cliente_telefono']),
            'template_id' => $template['id'],
            'mensaje' => $mensaje,
            'estado' => 'error',
            'error_codigo' => $resultadoAPI['error_code'] ?? 'UNKNOWN',
            'error_descripcion' => $resultadoAPI['error'] ?? 'Error desconocido',
            'metadata' => json_encode([
                'intento_numero' => $intentos,
                'max_intentos' => $maxIntentos,
                'api_response' => $resultadoAPI
            ])
        ]);
    }

    /**
     * Marcar alerta como omitida
     */
    private function marcarAlertaComoOmitida($alertaId, $motivo) {
        $this->actualizarEstadoAlerta($alertaId, 'omitido', null, null, $motivo);
    }

    /**
     * Registrar error interno
     */
    private function registrarErrorInterno($alertaId, $error) {
        $this->actualizarEstadoAlerta($alertaId, 'error', null, null, $error);
    }

    /**
     * Actualizar estado de alerta
     */
    private function actualizarEstadoAlerta($alertaId, $estado, $templateId = null, $mensajeId = null, $error = null, $intentos = null) {
        $campos = ['whatsapp_estado = ?', 'whatsapp_ultimo_intento = CURRENT_TIMESTAMP'];
        $valores = [$estado];
        
        if ($estado === 'enviado') {
            $campos[] = 'whatsapp_fecha_enviada = CURRENT_TIMESTAMP';
        }
        
        if ($templateId !== null) {
            $campos[] = 'whatsapp_template_id = ?';
            $valores[] = $templateId;
        }
        
        if ($mensajeId !== null) {
            $campos[] = 'whatsapp_mensaje_id = ?';
            $valores[] = $mensajeId;
        }
        
        if ($error !== null) {
            $campos[] = 'whatsapp_error_mensaje = ?';
            $valores[] = $error;
        }
        
        if ($intentos !== null) {
            $campos[] = 'whatsapp_intentos_envio = ?';
            $valores[] = $intentos;
        }
        
        $valores[] = $alertaId;
        
        $query = "UPDATE alertas_servicio SET " . implode(', ', $campos) . " WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute($valores);
    }

    /**
     * Crear registro en log de WhatsApp
     */
    private function crearLogWhatsapp($datos) {
        $query = "
            INSERT INTO whatsapp_logs (
                alerta_id, cliente_id, telefono, template_id, mensaje, 
                estado, whatsapp_message_id, error_codigo, error_descripcion, 
                metadata, costo_mensaje
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        
        $costo = $this->config['costo_por_mensaje'] ?? 0.5614;
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $datos['alerta_id'],
            $datos['cliente_id'],
            $datos['telefono'],
            $datos['template_id'] ?? null,
            $datos['mensaje'],
            $datos['estado'],
            $datos['whatsapp_message_id'] ?? null,
            $datos['error_codigo'] ?? null,
            $datos['error_descripcion'] ?? null,
            $datos['metadata'] ?? null,
            $costo
        ]);
    }

    /**
     * Obtener intentos de envío para una alerta
     */
    private function obtenerIntentosEnvio($alertaId) {
        $query = "SELECT whatsapp_intentos_envio FROM alertas_servicio WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$alertaId]);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado ? (int)$resultado['whatsapp_intentos_envio'] : 0;
    }

    /**
     * Contar mensajes enviados hoy
     */
    private function contarMensajesHoy() {
        $query = "SELECT COUNT(*) as total FROM whatsapp_logs WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $resultado ? (int)$resultado['total'] : 0;
    }

    // ================================================
    // MÉTODOS PARA WEBHOOK Y ACTUALIZACIONES
    // ================================================

    /**
     * Procesar webhook de WhatsApp (actualizaciones de estado)
     */
    public function procesarWebhook($data) {
        try {
            if (!isset($data['entry'][0]['changes'][0]['value']['statuses'])) {
                return ['success' => false, 'error' => 'No hay actualizaciones de estado'];
            }

            $statuses = $data['entry'][0]['changes'][0]['value']['statuses'];
            
            foreach ($statuses as $status) {
                $this->actualizarEstadoMensaje($status);
            }

            return ['success' => true, 'procesados' => count($statuses)];

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Actualizar estado de mensaje basado en webhook
     */
    private function actualizarEstadoMensaje($status) {
        $mensajeId = $status['id'];
        $nuevoEstado = $status['status']; // sent, delivered, read, failed
        
        // Mapear estados de WhatsApp a nuestros estados
        $estadoMapeado = match($nuevoEstado) {
            'sent' => 'enviado',
            'delivered' => 'entregado', 
            'read' => 'leido',
            'failed' => 'error',
            default => $nuevoEstado
        };

        // Actualizar en whatsapp_logs
        $query = "
            UPDATE whatsapp_logs 
            SET estado = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE whatsapp_message_id = ?
        ";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$estadoMapeado, $mensajeId]);

        // Actualizar también en alertas_servicio si es necesario
        if ($estadoMapeado === 'entregado' || $estadoMapeado === 'leido') {
            $query = "
                UPDATE alertas_servicio 
                SET whatsapp_estado = ? 
                WHERE whatsapp_mensaje_id = ?
            ";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$estadoMapeado, $mensajeId]);
        }
    }

    // ================================================
    // MÉTODOS DE CONFIGURACIÓN Y UTILIDADES
    // ================================================

    /**
     * Obtener configuración específica
     */
    public function getConfig($clave, $default = null) {
        return $this->config[$clave] ?? $default;
    }

    /**
     * Actualizar configuración
     */
    public function updateConfig($clave, $valor) {
        $query = "UPDATE whatsapp_config SET valor = ?, updated_at = CURRENT_TIMESTAMP WHERE clave = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$valor, $clave]);
        
        // Recargar configuración
        $this->loadConfig();
        
        return true;
    }

    /**
     * Obtener estadísticas del dashboard
     */
    public function obtenerEstadisticas() {
        $query = "SELECT * FROM vista_whatsapp_dashboard";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener log de mensajes recientes
     */
    public function obtenerLogReciente($limite = 50) {
        $query = "
            SELECT 
                wl.*,
                c.nombre as cliente_nombre,
                wt.nombre as template_nombre
            FROM whatsapp_logs wl
            LEFT JOIN clientes c ON wl.cliente_id = c.id
            LEFT JOIN whatsapp_templates wt ON wl.template_id = wt.id
            ORDER BY wl.created_at DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$limite]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Agregar número a blacklist
     */
    public function agregarABlacklist($telefono, $motivo, $usuarioId = null) {
        $telefonoFormateado = $this->formatearTelefono($telefono);
        
        $query = "
            INSERT INTO whatsapp_blacklist (telefono, motivo, agregado_por_id) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            motivo = VALUES(motivo), 
            activo = TRUE,
            fecha_agregado = CURRENT_TIMESTAMP
        ";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$telefonoFormateado, $motivo, $usuarioId]);
    }

    /**
     * Test de conexión con WhatsApp API
     */
    public function testConexion() {
        if (!$this->whatsappAPI) {
            return ['success' => false, 'error' => 'API no inicializada'];
        }
        
        return $this->whatsappAPI->testConnection();
    }
}