<?php
/**
 * TWILIO CONVERSATIONAL BOT - SAG GARAGE
 * Sistema de agendamiento automático con WhatsApp
 * 
 * Funcionalidades:
 * - Envío de recordatorios con botones
 * - Manejo de respuestas de clientes
 * - Generación de fechas disponibles
 * - Pre-agendamiento automático
 * - Notificaciones a SAG Garage
 * 
 * @author Sistema SAG Garage
 * @version 2.0.0
 * @date 01/04/2026
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Twilio\Rest\Client;

class TwilioConversationalBot {
    private $db;
    private $twilioClient;
    
    // Configuración Twilio
    private $accountSid;
    private $authToken;
    private $whatsappFrom;
    private $sagAdminPhone;
    
    public function __construct() {
        // **DEBUGGING FORZADO - CONSTRUCTOR**
        error_log("🚀 TwilioBot: Constructor iniciado VERSIÓN 2024-04-08 {{DebugConstructor}}");
        
        // Conectar a BD
        $database = Database::getInstance();
        $this->db = $database->getConnection();
        
        error_log("🔗 TwilioBot: Base de datos conectada {{DebugBD}}");
        
        // Cargar configuración
        $this->loadTwilioConfig();
        
        error_log("⚙️ TwilioBot: Configuración cargada {{DebugConfig}}");
        
        // Inicializar cliente Twilio
        $this->initializeTwilioClient();
        
        error_log("📱 TwilioBot: Cliente Twilio inicializado {{DebugCliente}}");
    }
    
    /**
     * Cargar configuración desde BD
     */
    private function loadTwilioConfig() {
        $configs = [
            'account_sid', 'auth_token', 'whatsapp_from', 'sag_admin_phone'
        ];
        
        foreach ($configs as $key) {
            $sql = "SELECT config_value FROM twilio_config WHERE config_key = ? AND is_active = TRUE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            switch ($key) {
                case 'account_sid':
                    $this->accountSid = $result['config_value'] ?? '';
                    break;
                case 'auth_token':
                    $this->authToken = $result['config_value'] ?? '';
                    break;
                case 'whatsapp_from':
                    $this->whatsappFrom = $result['config_value'] ?? 'whatsapp:+525535240846';
                    break;
                case 'sag_admin_phone':
                    $this->sagAdminPhone = $result['config_value'] ?? '';
                    break;
            }
        }
    }
    
    /**
     * Inicializar cliente Twilio
     */
    private function initializeTwilioClient() {
        // Para testing sin Twilio real
        if (empty($this->accountSid) || $this->accountSid === '') {
            error_log("TwilioBot: Configuración no completa, modo simulación activado");
            $this->twilioClient = null;
            return;
        }
        
        try {
            // ACTIVADO PARA ENVÍO REAL CON SDK OFICIAL
            $this->twilioClient = new Client($this->accountSid, $this->authToken);
            error_log("TwilioBot: Cliente Twilio inicializado CORRECTAMENTE para envío REAL");
        } catch (Exception $e) {
            error_log("TwilioBot ERROR inicializando cliente: " . $e->getMessage());
            $this->twilioClient = null;
        }
    }
    
    /**
     * PASO 1: Enviar recordatorio inicial con botones
     */
    public function enviarRecordatorioInicial($alertaId) {
        try {
            // **DEBUGGING FORZADO - ENVIAR RECORDATORIO**
            error_log("📧 TwilioBot: enviarRecordatorioInicial iniciado para alerta ID: {$alertaId} {{DebugEnvio}}");
            
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            if (!$alerta) {
                throw new Exception("Alerta ID {$alertaId} no encontrada");
            }
            
            error_log("👤 TwilioBot: Alerta obtenida - Cliente: {$alerta['cliente_nombre']} {{DebugAlerta}}");
            
            // Validar que esté en estado correcto
            if ($alerta['estado_whatsapp'] !== 'borrador') {
                throw new Exception("Alerta ID {$alertaId} no está en estado borrador");
            }
            
            error_log("✅ TwilioBot: Estado validado: {$alerta['estado_whatsapp']} {{DebugEstado}}");
            
            // Limpiar teléfono
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            if (!$telefono) {
                throw new Exception("Teléfono no válido: {$alerta['cliente_telefono']}");
            }
            
            error_log("📱 TwilioBot: Teléfono limpiado: {$telefono} {{DebugTelefono}}");
            
            // **NUEVO: Enviar con plantilla aprobada de Twilio**
            error_log("🚀 TwilioBot: Iniciando envío con plantilla {{DebugPlantilla}}");
            
            $resultado = $this->enviarMensajeConPlantilla(
                $telefono,
                $alerta,
                'recordatorio_inicial'
            );
            
            error_log("📬 TwilioBot: Resultado envío: " . json_encode($resultado) . " {{DebugResultado}}");
            
            if ($resultado['success']) {
                // Actualizar estado en BD
                $this->actualizarEstadoAlerta($alertaId, 'enviado', $resultado['message_sid']);
                
                // Registrar en historial de conversación
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    "Plantilla: {$alerta['cliente_nombre']}, 6 meses, {$alerta['servicios_que_dispararon']}",
                    'template',
                    'recordatorio_inicial',
                    $resultado
                );
                
                error_log("TwilioBot: Recordatorio enviado exitosamente a {$alerta['cliente_nombre']} ({$telefono})");
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'telefono' => $telefono
                ];
            } else {
                throw new Exception("Error enviando mensaje: " . $resultado['error']);
            }
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarRecordatorioInicial: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PASO 2: Procesar respuesta inicial del cliente (button IDs)
     */
    public function procesarRespuestaInicial($alertaId, $respuesta, $messageSid, $webhookData = []) {
        try {
            error_log("🔥 TwilioBot: procesarRespuestaInicial INICIADO - Respuesta: '{$respuesta}'");
            error_log("🔥 TwilioBot: webhookData: " . json_encode($webhookData));
            
            // Detectar button ID desde webhook data (preferido)
            $buttonId = $webhookData['ButtonId'] ?? $webhookData['ButtonPayload'] ?? $webhookData['Button'] ?? '';
            
            error_log("🔥 TwilioBot: ButtonId extraído: '{$buttonId}'");
            
            if (!empty($buttonId)) {
                error_log("🔥 TwilioBot: Button ID detectado: {$buttonId}");
                
                // Procesar según button ID
                if ($buttonId === 'si_interesa') {
                    error_log("🔥 TwilioBot: DETECTADO si_interesa - Llamando procesarRespuestaSiSimplificado");
                    $resultado = $this->procesarRespuestaSiSimplificado($alertaId, $messageSid);
                    error_log("🔥 TwilioBot: RESULTADO procesarRespuestaSiSimplificado: " . json_encode($resultado));
                    return $resultado;
                } 
                elseif ($buttonId === 'no_gracias') {
                    error_log("🔥 TwilioBot: DETECTADO no_gracias - Llamando procesarRespuestaNoSimplificado");
                    return $this->procesarRespuestaNoSimplificado($alertaId, $messageSid);
                }
            }
            
            // Fallback: detectar por texto (compatibilidad)
            $respuestaLower = strtolower(trim($respuesta));
            error_log("TwilioBot: No button ID, analizando texto: '{$respuestaLower}'");
            
            if (preg_match('/\b(si|sí|1|interesa|yes)\b/', $respuestaLower)) {
                return $this->procesarRespuestaSiSimplificado($alertaId, $messageSid);
            } 
            elseif (preg_match('/\b(no|2|gracias|nah)\b/', $respuestaLower)) {
                return $this->procesarRespuestaNoSimplificado($alertaId, $messageSid);
            } 
            else {
                // Respuesta no reconocida
                error_log("TwilioBot: Respuesta no reconocida: '{$respuesta}'");
                return [
                    'success' => true,
                    'message' => 'Respuesta no reconocida, pero registrada'
                ];
            }
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR procesarRespuestaInicial: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PASO 2A: Cliente dijo SÍ - Enviar fechas disponibles
     */
    private function procesarRespuestaSi($alertaId, $messageSid) {
        try {
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            // Actualizar estado
            $this->actualizarRespuestaInicial($alertaId, 'si');
            
            // Registrar respuesta del cliente
            $this->registrarMensaje(
                $alertaId,
                $messageSid,
                'inbound',
                "whatsapp:+52{$telefono}",
                $this->whatsappFrom,
                'Sí, me interesa',
                'text',
                'respuesta_si_no'
            );
            
            // Generar fechas disponibles
            $fechasDisponibles = $this->generarFechasDisponibles();
            
            if (empty($fechasDisponibles)) {
                // No hay fechas disponibles, enviar a contacto directo
                return $this->enviarContactoDirecto($alertaId);
            }
            
            // Crear mensaje con opciones de fecha
            $mensaje = "¡Excelente! Selecciona la fecha que más te convenga:\n\n";
            $botones = [];
            
            $contador = 1;
            foreach ($fechasDisponibles as $fecha) {
                $mensaje .= "{$contador}. {$fecha['texto']}\n";
                $botones[] = [
                    'id' => "fecha_{$contador}",
                    'title' => $fecha['texto']
                ];
                $contador++;
                
                // Máximo 6 opciones de fecha
                if ($contador > 6) break;
            }
            
            // Agregar opción "otra fecha"
            $mensaje .= "{$contador}. Otra fecha (contacto directo)\n\n";
            $mensaje .= "Escribe el número de tu opción:";
            
            $botones[] = [
                'id' => 'otra_fecha',
                'title' => 'Otra fecha'
            ];
            
            // Enviar mensaje con opciones
            $resultado = $this->enviarMensajeConBotones(
                $telefono,
                $mensaje,
                $botones,
                'seleccion_fecha'
            );
            
            if ($resultado['success']) {
                $this->actualizarEstadoAlerta($alertaId, 'esperando_fecha');
                
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    $mensaje,
                    'interactive',
                    'seleccion_fecha',
                    $resultado
                );
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'fechas_enviadas' => count($fechasDisponibles)
                ];
            }
            
            throw new Exception("Error enviando fechas: " . $resultado['error']);
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR procesarRespuestaSi: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PASO 2B: Cliente dijo NO - Enviar mensaje de despedida
     */
    private function procesarRespuestaNo($alertaId, $messageSid) {
        try {
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            // Actualizar estado
            $this->actualizarRespuestaInicial($alertaId, 'no');
            $this->actualizarEstadoAlerta($alertaId, 'rechazado');
            
            // Registrar respuesta del cliente
            $this->registrarMensaje(
                $alertaId,
                $messageSid,
                'inbound',
                "whatsapp:+52{$telefono}",
                $this->whatsappFrom,
                'No, gracias',
                'text',
                'respuesta_si_no'
            );
            
            // Enviar mensaje de despedida
            $mensajeRechazo = $this->obtenerConfiguracion('mensaje_rechazo');
            
            $resultado = $this->enviarMensajeTexto($telefono, $mensajeRechazo);
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    $mensajeRechazo,
                    'text',
                    'mensaje_final',
                    $resultado
                );
                
                // Marcar como requiere atención para campanita (informativo)
                $this->marcarRequiereAtencion($alertaId, 'baja');
                
                error_log("TwilioBot: Cliente {$alerta['cliente_nombre']} rechazó servicio");
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'rechazado'
                ];
            }
            
            throw new Exception("Error enviando mensaje de rechazo");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR procesarRespuestaNo: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PASO 3: Procesar selección de fecha
     */
    public function procesarSeleccionFecha($alertaId, $seleccion, $messageSid) {
        try {
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            // Registrar respuesta del cliente
            $this->registrarMensaje(
                $alertaId,
                $messageSid,
                'inbound',
                "whatsapp:+52{$telefono}",
                $this->whatsappFrom,
                $seleccion,
                'text',
                'seleccion_fecha'
            );
            
            // Verificar si pidió "otra fecha"
            if (preg_match('/\b(otra|7|contacto|directo)\b/i', $seleccion)) {
                return $this->enviarContactoDirecto($alertaId);
            }
            
            // Extraer número de selección
            preg_match('/\d+/', $seleccion, $matches);
            $numeroSeleccion = isset($matches[0]) ? (int)$matches[0] : 0;
            
            if ($numeroSeleccion < 1 || $numeroSeleccion > 6) {
                return $this->enviarMensajeAclaracion($alertaId);
            }
            
            // Obtener fechas disponibles nuevamente
            $fechasDisponibles = $this->generarFechasDisponibles();
            
            if (!isset($fechasDisponibles[$numeroSeleccion - 1])) {
                throw new Exception("Opción de fecha no válida: {$numeroSeleccion}");
            }
            
            $fechaSeleccionada = $fechasDisponibles[$numeroSeleccion - 1];
            
            // Pre-agendar la cita
            return $this->preAgendarCita($alertaId, $fechaSeleccionada);
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR procesarSeleccionFecha: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PASO 4: Pre-agendar cita y notificar a SAG
     */
    private function preAgendarCita($alertaId, $fechaSeleccionada) {
        try {
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            // Reservar slot en calendario
            $slotId = $this->reservarSlotCalendario($fechaSeleccionada['fecha'], $fechaSeleccionada['hora']);
            
            if (!$slotId) {
                // Slot ya ocupado, ofrecer alternativa
                return $this->enviarContactoDirecto($alertaId);
            }
            
            // Actualizar alerta con fecha seleccionada
            $this->actualizarFechaCitaAlerta($alertaId, $fechaSeleccionada['fecha'], $fechaSeleccionada['hora']);
            
            // Crear registro en citas pre-agendadas
            $citaId = $this->crearCitaPreAgendada($alertaId, $slotId, $alerta);
            
            // FIX: ELIMINADO mensaje_pre_agenda duplicado
            // Solo se mantiene mensaje_pre_agenda_exito que se envía en enviarConfirmacionPreAgenda()
            
            // Enviar notificación a SAG Garage
            $this->enviarNotificacionSAG($alertaId, $alerta, $fechaSeleccionada);
            
            // Actualizar estado y marcar como requiere atención urgente
            $this->actualizarEstadoAlerta($alertaId, 'pre_agendado');
            $this->marcarRequiereAtencion($alertaId, 'alta');
            
            error_log("TwilioBot: Cita pre-agendada para {$alerta['cliente_nombre']} - {$fechaSeleccionada['fecha']} {$fechaSeleccionada['hora']}");
            
            return [
                'success' => true,
                'message_sid' => 'pre_agendado_' . uniqid(),
                'cita_id' => $citaId,
                'fecha' => $fechaSeleccionada['fecha'],
                'hora' => $fechaSeleccionada['hora']
            ];
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR preAgendarCita: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PASO 5: Enviar notificación a SAG Garage para confirmar
     */
    private function enviarNotificacionSAG($alertaId, $alerta, $fechaSeleccionada) {
        try {
            if (empty($this->sagAdminPhone)) {
                error_log("TwilioBot: No hay teléfono admin configurado para SAG");
                return false;
            }
            
            // **NUEVO: Usar plantilla aprobada en lugar de mensaje libre**
            error_log("TwilioBot: Enviando notificación admin con PLANTILLA para evitar 'Outside messaging window'");
            
            // FIX: Limpiar teléfono admin para evitar +52 duplicado
            $adminTelefonoLimpio = $this->limpiarTelefono($this->sagAdminPhone);
            
            // **ENVIAR PLANTILLA ADMIN**
            $resultado = $this->enviarPlantillaNotificacionAdmin(
                $adminTelefonoLimpio,
                $alerta,
                $fechaSeleccionada
            );
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$adminTelefonoLimpio}",
                    "Plantilla admin: {$alerta['cliente_nombre']}, {$fechaSeleccionada['fecha_display']} {$fechaSeleccionada['hora_display']}",
                    'template',
                    'notificacion_admin_pre_agenda',
                    $resultado
                );
                
                error_log("TwilioBot: Notificación admin enviada con PLANTILLA - SID: {$resultado['message_sid']}");
                return true;
            }
            
            throw new Exception("Error enviando plantilla admin: " . $resultado['error']);
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarNotificacionSAG: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * PASO 6: Procesar confirmación de SAG
     */
    public function procesarConfirmacionSAG($alertaId, $respuesta, $messageSid, $userId = null) {
        try {
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            $respuestaLower = strtolower(trim($respuesta));
            
            if (preg_match('/\b(confirmar|1|si|sí)\b/', $respuestaLower)) {
                // SAG CONFIRMA la cita
                return $this->confirmarCitaFinal($alertaId, $userId);
                
            } elseif (preg_match('/\b(cancelar|2|no|cancel)\b/', $respuestaLower)) {
                // SAG CANCELA la cita
                return $this->cancelarCita($alertaId, $userId);
                
            } elseif (preg_match('/\b(reprogramar|3|cambiar)\b/', $respuestaLower)) {
                // SAG REPROGRAMA la cita
                return $this->reprogramarCita($alertaId, $userId);
                
            } else {
                // Respuesta no reconocida de SAG
                return $this->enviarAclaracionSAG($alertaId);
            }
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR procesarConfirmacionSAG: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PASO 6A: Confirmar cita definitivamente
     */
    private function confirmarCitaFinal($alertaId, $userId = null) {
        try {
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            // Actualizar estado en BD
            $this->actualizarConfirmacionSAG($alertaId, 'confirmado', $userId);
            $this->actualizarEstadoAlerta($alertaId, 'confirmado');
            
            // Enviar confirmación final al cliente
            $mensajeConfirmacion = $this->obtenerConfiguracion('mensaje_confirmacion');
            $mensaje = $this->reemplazarVariablesFecha(
                $mensajeConfirmacion,
                $alerta['fecha_cita_seleccionada'], 
                $alerta['hora_cita_seleccionada']
            );
            
            $resultado = $this->enviarMensajeTexto($telefono, $mensaje);
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    $mensaje,
                    'text',
                    'mensaje_final',
                    $resultado
                );
                
                // Ya no requiere atención, flujo completado
                $this->marcarRequiereAtencion($alertaId, 'baja', false);
                
                error_log("TwilioBot: Cita CONFIRMADA para {$alerta['cliente_nombre']}");
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'confirmado'
                ];
            }
            
            throw new Exception("Error enviando confirmación final");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR confirmarCitaFinal: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Métodos auxiliares para contacto directo
     */
    private function enviarContactoDirecto($alertaId) {
        try {
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            $mensajeContacto = $this->obtenerConfiguracion('mensaje_contacto_directo');
            
            $resultado = $this->enviarMensajeTexto($telefono, $mensajeContacto);
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    $mensajeContacto,
                    'text',
                    'contacto_directo',
                    $resultado
                );
                
                $this->actualizarEstadoAlerta($alertaId, 'requiere_contacto');
                $this->marcarRequiereAtencion($alertaId, 'media');
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'contacto_directo'
                ];
            }
            
            throw new Exception("Error enviando mensaje de contacto directo");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarContactoDirecto: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // ===============================================
    // MÉTODOS DE UTILIDAD
    // ===============================================
    
    /**
     * Enviar mensaje con botones interactivos
     */
    private function enviarMensajeConBotones($telefono, $mensaje, $botones, $step) {
        try {
            // Si no hay cliente Twilio, simular
            if ($this->twilioClient === null) {
                error_log("TwilioBot SIMULADO: Mensaje con botones a {$telefono}");
                error_log("TwilioBot SIMULADO: {$mensaje}");
                error_log("TwilioBot SIMULADO: Botones: " . json_encode($botones));
                
                return [
                    'success' => true,
                    'message_sid' => 'SM_SIMULADO_' . uniqid(),
                    'status' => 'queued',
                    'twilio_response' => ['simulado' => true]
                ];
            }
            
            // ENVÍO REAL CON BOTONES INTERACTIVOS TWILIO
            // Para WhatsApp Business, enviamos mensaje de texto con opciones numeradas
            // Los botones reales requieren pre-aprobación de plantillas
            $mensajeConOpciones = $mensaje . "\n\n";
            $contador = 1;
            foreach ($botones as $boton) {
                $mensajeConOpciones .= "{$contador}. {$boton['title']}\n";
                $contador++;
            }
            $mensajeConOpciones .= "\nResponde con el número de tu opción.";
            
            $message = $this->twilioClient->messages->create(
                "whatsapp:+52{$telefono}", // To
                [
                    'from' => $this->whatsappFrom,
                    'body' => $mensajeConOpciones
                ]
            );
            
            error_log("TwilioBot REAL: Mensaje con opciones enviado a {$telefono} - SID: {$message->sid}");
            
            return [
                'success' => true,
                'message_sid' => $message->sid,
                'status' => $message->status,
                'twilio_response' => [
                    'sid' => $message->sid,
                    'status' => $message->status,
                    'to' => $message->to,
                    'from' => $message->from,
                    'date_created' => $message->dateCreated->format('Y-m-d H:i:s'),
                    'botones_enviados' => count($botones)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarMensajeConBotones: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar mensaje de texto simple
     */
    private function enviarMensajeTexto($telefono, $mensaje) {
        try {
            // Si no hay cliente Twilio, simular
            if ($this->twilioClient === null) {
                error_log("TwilioBot SIMULADO: Mensaje texto a {$telefono}");
                error_log("TwilioBot SIMULADO: {$mensaje}");
                
                return [
                    'success' => true,
                    'message_sid' => 'SM_SIMULADO_' . uniqid(),
                    'status' => 'queued',
                    'body' => $mensaje,
                    'twilio_response' => ['simulado' => true]
                ];
            }
            
            // ENVÍO REAL CON TWILIO SDK
            $message = $this->twilioClient->messages->create(
                "whatsapp:+52{$telefono}", // To
                [
                    'from' => $this->whatsappFrom,
                    'body' => $mensaje
                ]
            );
            
            error_log("TwilioBot REAL: Mensaje enviado a {$telefono} - SID: {$message->sid}");
            
            return [
                'success' => true,
                'message_sid' => $message->sid,
                'status' => $message->status,
                'body' => $mensaje,
                'twilio_response' => [
                    'sid' => $message->sid,
                    'status' => $message->status,
                    'to' => $message->to,
                    'from' => $message->from,
                    'date_created' => $message->dateCreated->format('Y-m-d H:i:s')
                ]
            ];
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarMensajeTexto: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar mensaje con plantilla aprobada de Twilio
     * VERSIÓN cURL con .env - EXACTA al cURL exitoso pero desde .env
     */
    private function enviarMensajeConPlantilla($telefono, $alerta, $step) {
        try {
            // Cargar .env con método más directo
            $envPath = __DIR__ . '/../.env';
            error_log("TwilioBot: Buscando .env en: {$envPath}");
            
            if (!file_exists($envPath)) {
                throw new Exception("Archivo .env no encontrado en: {$envPath}");
            }
            
            // Leer .env línea por línea (método robusto)
            $envContent = file_get_contents($envPath);
            $envLines = explode("\n", $envContent);
            $env = [];
            
            foreach ($envLines as $line) {
                $line = trim($line);
                // Ignorar comentarios y líneas vacías
                if (empty($line) || $line[0] === '#') continue;
                
                // Buscar líneas con = 
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $env[trim($key)] = trim($value);
                }
            }
            
            // Buscar variables EXACTAS como tu .env
            $sid = $env['TWILIO_ACCOUNT_SID'] ?? '';
            $token = $env['TWILIO_AUTH_TOKEN'] ?? '';  
            $fromNumber = $env['TWILIO_WHATSAPP_FROM'] ?? '';
            $templateHorarios = $env['TWILIO_TEMPLATE_HORARIOS'] ?? '';
            
            error_log("TwilioBot: Variables cargadas del .env:");
            error_log("TwilioBot: TWILIO_ACCOUNT_SID = " . ($sid ? 'ENCONTRADO (' . substr($sid, 0, 10) . '...)' : 'VACIO'));
            error_log("TwilioBot: TWILIO_AUTH_TOKEN = " . ($token ? 'ENCONTRADO (' . substr($token, 0, 10) . '...)' : 'VACIO'));
            error_log("TwilioBot: TWILIO_WHATSAPP_FROM = " . ($fromNumber ? $fromNumber : 'VACIO'));
            error_log("TwilioBot: TWILIO_TEMPLATE_HORARIOS = " . ($templateHorarios ? $templateHorarios : 'VACIO'));
            
            if (empty($sid) || empty($token) || empty($fromNumber) || empty($templateHorarios)) {
                throw new Exception("Variables Twilio no encontradas en .env. Verificar nombres exactos.");
            }
            
            // Preparar servicios como texto (extraído de BD)
            $serviciosTexto = '';
            if (is_array($alerta['servicios_que_dispararon'])) {
                $serviciosTexto = implode(', ', $alerta['servicios_que_dispararon']);
            } else {
                $serviciosTexto = $alerta['servicios_que_dispararon'];
            }
            
            // Variables exactas del cURL exitoso (extraídas de BD)
            $contentVariables = [
                "1" => $alerta['cliente_nombre'],     // Cliente desde BD
                "2" => "6 meses",                     // Tiempo fijo  
                "3" => $serviciosTexto                // Servicio desde BD
            ];
            
            error_log("TwilioBot: Preparando cURL con variables:");
            error_log("TwilioBot: Cliente = " . $alerta['cliente_nombre']);
            error_log("TwilioBot: Servicios = " . $serviciosTexto);
            error_log("TwilioBot: Teléfono = +52{$telefono}");
            error_log("TwilioBot: ContentVariables = " . json_encode($contentVariables));
            
            // cURL EXACTO como tu versión exitosa (usando variables del .env)
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => http_build_query([
                'ContentSid' => 'HX765eae763cf778deacde6238674d4108',
                'From' => $fromNumber,
                'To' => "whatsapp:+52{$telefono}",
                'ContentVariables' => json_encode($contentVariables)
              ]),
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($sid . ':' . $token)
              ),
            ));
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            
            curl_close($curl);
            
            error_log("TwilioBot cURL: HTTP Code = {$httpCode}");
            error_log("TwilioBot cURL: Response = {$response}");
            if ($curlError) {
                error_log("TwilioBot cURL: Error = {$curlError}");
            }
            
            if ($curlError) {
                throw new Exception("cURL Error: {$curlError}");
            }
            
            $responseData = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['sid'])) {
                error_log("TwilioBot cURL: ¡ÉXITO TOTAL! Message SID = {$responseData['sid']}");
                
                return [
                    'success' => true,
                    'message_sid' => $responseData['sid'],
                    'status' => $responseData['status'] ?? 'queued',
                    'twilio_response' => $responseData
                ];
            } else {
                $errorMsg = isset($responseData['message']) ? $responseData['message'] : "HTTP {$httpCode}";
                error_log("TwilioBot cURL: ERROR = {$errorMsg}");
                error_log("TwilioBot cURL: Full Response = " . json_encode($responseData));
                throw new Exception("Twilio cURL Error: {$errorMsg}");
            }
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR cURL COMPLETO: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generar fechas disponibles
     */
    private function generarFechasDisponibles() {
        try {
            $sql = "SELECT GenerarFechasDisponibles(7) as fechas_json";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['fechas_json']) {
                return json_decode($result['fechas_json'], true) ?: [];
            }
            
            return [];
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR generarFechasDisponibles: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener configuración
     */
    private function obtenerConfiguracion($key) {
        try {
            $sql = "SELECT config_value FROM twilio_config WHERE config_key = ? AND is_active = TRUE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $value = $result ? $result['config_value'] : '';
            error_log("TwilioBot DEBUG obtenerConfiguracion('{$key}'): resultado=" . ($result ? 'FOUND' : 'NOT_FOUND') . ", value='{$value}'");
            return $value;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR obtenerConfiguracion: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Limpiar número de teléfono
     */
    private function limpiarTelefono($telefono) {
        // Remover espacios, guiones, paréntesis
        $telefono = preg_replace('/[\s\-\(\)]+/', '', $telefono);
        
        // Remover prefijos comunes pero conservar el 1 de números móviles mexicanos
        $telefono = preg_replace('/^\+?52/', '', $telefono);
        $telefono = preg_replace('/^01/', '', $telefono);
        
        // Validar que sean 10 u 11 dígitos (10 para fijos, 11 para móviles con el 1)
        if (!preg_match('/^\d{10,11}$/', $telefono)) {
            return '';
        }
        
        return $telefono;
    }
    
    /**
     * Reemplazar variables en templates
     */
    private function reemplazarVariables($template, $alerta) {
        $replacements = [
            '{cliente}' => $alerta['cliente_nombre'],
            '{servicio}' => $alerta['servicios_que_dispararon'],
            '{vehiculo}' => $alerta['vehiculo_info']
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    /**
     * Reemplazar variables de fecha
     */
    private function reemplazarVariablesFecha($template, $fecha, $hora) {
        $fechaFormateada = date('l j \d\e F', strtotime($fecha));
        $horaFormateada = date('H:i', strtotime($hora));
        
        $replacements = [
            '{fecha}' => $fechaFormateada,
            '{hora}' => $horaFormateada
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
    
    // ===============================================
    // MÉTODOS DE BASE DE DATOS
    // ===============================================
    
    private function obtenerDatosAlerta($alertaId) {
        $sql = "SELECT 
                    a.*,
                    c.nombre as cliente_nombre,
                    c.telefono as cliente_telefono,
                    CONCAT(
                        COALESCE(v.marca, 'Sin marca'), ' ',
                        COALESCE(v.modelo, 'Sin modelo'), ' ',
                        COALESCE(v.anio, 'Sin año')
                    ) as vehiculo_info
                FROM alertas_servicio a
                INNER JOIN clientes c ON a.cliente_id = c.id
                INNER JOIN vehiculos v ON a.vehiculo_id = v.id
                WHERE a.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$alertaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener datos reales del vehículo por ID
     */
    private function obtenerDatosVehiculo($vehiculoId) {
        try {
            if (empty($vehiculoId)) {
                return 'Vehículo no especificado';
            }
            
            $sql = "SELECT marca, modelo, anio FROM vehiculos WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$vehiculoId]);
            $vehiculo = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vehiculo) {
                return 'Vehículo no encontrado';
            }
            
            // Formatear datos del vehículo con valores reales
            $marca = !empty($vehiculo['marca']) ? $vehiculo['marca'] : 'Sin especificar';
            $modelo = !empty($vehiculo['modelo']) ? $vehiculo['modelo'] : 'Sin especificar'; 
            $anio = !empty($vehiculo['anio']) ? $vehiculo['anio'] : 'Sin especificar';
            
            return "{$marca} {$modelo} {$anio}";
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR obtenerDatosVehiculo: " . $e->getMessage());
            return 'Error obteniendo datos del vehículo';
        }
    }
    
    private function actualizarEstadoAlerta($alertaId, $estado, $messageSid = null) {
        try {
            error_log("🔄 TwilioBot: actualizarEstadoAlerta - AlertaID: {$alertaId}, Estado: '{$estado}', MessageSid: " . ($messageSid ?? 'NULL'));
            
            $sql = "UPDATE alertas_servicio 
                    SET estado_whatsapp = ?, 
                        fecha_envio_whatsapp = CASE WHEN ? IS NOT NULL THEN NOW() ELSE fecha_envio_whatsapp END,
                        twilio_conversation_sid = COALESCE(?, twilio_conversation_sid),
                        ultima_actividad = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $resultado = $stmt->execute([$estado, $messageSid, $messageSid, $alertaId]);
            
            if ($resultado) {
                $filasAfectadas = $stmt->rowCount();
                error_log("✅ TwilioBot: Estado actualizado exitosamente - Filas afectadas: {$filasAfectadas}");
                
                if ($filasAfectadas === 0) {
                    error_log("⚠️ TwilioBot: WARNING - No se actualizó ninguna fila para AlertaID: {$alertaId}");
                }
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("❌ TwilioBot: ERROR SQL actualizarEstadoAlerta - SQLSTATE: {$errorInfo[0]}, Error: {$errorInfo[2]}");
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("💥 TwilioBot: EXCEPCIÓN actualizarEstadoAlerta - " . $e->getMessage());
            return false;
        }
    }
    
    private function actualizarRespuestaInicial($alertaId, $respuesta) {
        $sql = "UPDATE alertas_servicio 
                SET respuesta_inicial = ?, 
                    fecha_respuesta_inicial = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$respuesta, $alertaId]);
    }
    
    private function actualizarFechaCitaAlerta($alertaId, $fecha, $hora) {
        $sql = "UPDATE alertas_servicio 
                SET fecha_cita_seleccionada = ?, 
                    hora_cita_seleccionada = ?,
                    fecha_pre_agendado = NOW()
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$fecha, $hora, $alertaId]);
    }
    
    private function actualizarConfirmacionSAG($alertaId, $confirmacion, $userId = null) {
        $sql = "UPDATE alertas_servicio 
                SET confirmacion_sag = ?, 
                    fecha_confirmacion_sag = NOW(),
                    usuario_confirmo_sag = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$confirmacion, $userId, $alertaId]);
    }
    
    private function marcarRequiereAtencion($alertaId, $prioridad, $requiere = true) {
        $sql = "UPDATE alertas_servicio 
                SET requiere_atencion = ?, 
                    prioridad = ?
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$requiere, $prioridad, $alertaId]);
    }
    
    private function reservarSlotCalendario($fecha, $hora) {
        try {
            // Verificar disponibilidad
            $sql = "SELECT id, citas_ocupadas, capacidad_total 
                    FROM calendario_disponibilidad 
                    WHERE fecha = ? AND hora = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$fecha, $hora]);
            $slot = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$slot) {
                // Crear slot si no existe
                $sql = "INSERT INTO calendario_disponibilidad (fecha, hora, citas_ocupadas, capacidad_total) 
                        VALUES (?, ?, 1, 2)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$fecha, $hora]);
                return $this->db->lastInsertId();
            }
            
            if ($slot['citas_ocupadas'] >= $slot['capacidad_total']) {
                return false; // No hay capacidad
            }
            
            // Incrementar ocupación
            $sql = "UPDATE calendario_disponibilidad 
                    SET citas_ocupadas = citas_ocupadas + 1 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$slot['id']]);
            
            return $slot['id'];
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR reservarSlotCalendario: " . $e->getMessage());
            return false;
        }
    }
    
    private function crearCitaPreAgendada($alertaId, $slotId, $alerta) {
        $sql = "INSERT INTO citas_pre_agendadas 
                (alerta_id, calendario_slot_id, cliente_nombre, cliente_telefono, 
                 vehiculo_info, tipo_servicio) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $alertaId,
            $slotId,
            $alerta['cliente_nombre'],
            $alerta['cliente_telefono'],
            $alerta['vehiculo_info'],
            $alerta['servicios_que_dispararon']
        ]);
        
        return $this->db->lastInsertId();
    }
    
    private function registrarMensaje($alertaId, $messageSid, $direction, $from, $to, $body, $type, $step, $twilioResponse = null) {
        try {
            $sql = "INSERT INTO conversaciones_whatsapp 
                    (alerta_id, twilio_message_sid, direction, from_number, to_number, 
                     message_body, message_type, conversation_step, twilio_response) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $alertaId,
                $messageSid,
                $direction,
                $from,
                $to,
                $body,
                $type,
                $step,
                json_encode($twilioResponse)
            ]);
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR registrarMensaje: " . $e->getMessage());
            return false;
        }
    }
    /**
     * PASO 2A SIMPLIFICADO: Cliente dijo SÍ - Enviar PLANTILLA TEMPORAL con horarios numerados
     */
    private function procesarRespuestaSiSimplificado($alertaId, $messageSid) {
        try {
            error_log("🚀 TwilioBot: procesarRespuestaSiSimplificado INICIADO - AlertaID: {$alertaId}");
            
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            error_log("📋 TwilioBot: Cliente {$alerta['cliente_nombre']} - Teléfono: {$telefono}");
            
            // Actualizar estado inicial
            $this->actualizarRespuestaInicial($alertaId, 'si');
            
            // Registrar respuesta del cliente
            $this->registrarMensaje(
                $alertaId,
                $messageSid,
                'inbound',
                "whatsapp:+52{$telefono}",
                $this->whatsappFrom,
                'Sí, me interesa',
                'button',
                'respuesta_si_interesa'
            );
            
            error_log("🔍 TwilioBot: Iniciando cálculo de horarios disponibles...");
            
            // **NUEVA LÓGICA: Calcular horarios disponibles**
            $slots = $this->calcularSlotsDisponibles();
            
            error_log("📊 TwilioBot: Resultado cálculo slots: " . count($slots) . " slots encontrados");
            error_log("🔍 TwilioBot: Slots detalle: " . json_encode($slots));
            
            if (empty($slots)) {
                error_log("❌ TwilioBot: CERO horarios disponibles - Enviando fallback contacto directo");
                return $this->enviarContactoDirectoFallback($alertaId);
            }
            
            // **FORMATEAR horarios como texto numerado - SIN NEWLINES PARA TWILIO**
            $horariosTexto = "";
            $contador = 1;
            
            // Agregar slots disponibles (máximo 8) - SIN SALTOS DE LÍNEA
            foreach ($slots as $slot) {
                if ($contador > 8) break;
                
                // USAR SEPARADOR COMPATIBLE CON TWILIO (sin \n)
                if ($contador > 1) {
                    $horariosTexto .= " • ";  // Separador bullet point
                }
                $horariosTexto .= "{$contador}. {$slot['fecha_display']} {$slot['hora_display']}";
                $contador++;
            }
            
            // Siempre agregar opción "9. Otro horario"
            $horariosTexto .= " • 9. Otro horario";
            
            error_log("📅 TwilioBot: Horarios formateados: {$horariosTexto}");
            
            // **ENVIAR NUEVA PLANTILLA MEJORADA**
            $resultado = $this->enviarPlantillaTemporal(
                $telefono,
                $alerta['cliente_nombre'],  // Variable 1
                $slots                      // Array de slots - NO texto
            );
            
            if ($resultado['success']) {
                // **CRÍTICO: Actualizar estado ANTES de guardar slots**
                $this->actualizarEstadoAlerta($alertaId, 'esperando_fecha', $resultado['message_sid']);
                
                // Guardar slots para mapeo posterior cuando responda el cliente  
                $this->guardarSlotsSession($alertaId, $slots);
                
                // **DEBUG CRÍTICO: Verificar inmediatamente qué quedó en la BD**
                $this->verificarEstadoBD($alertaId, "DESPUÉS de guardarSlotsSession");
                
                // Registrar mensaje
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    "Plantilla temporal: {$alerta['cliente_nombre']}, horarios numerados",
                    'template',
                    'plantilla_temporal_horarios',
                    $resultado
                );
                
                error_log("📅 TwilioBot: ¡Plantilla temporal enviada exitosamente!");
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'horarios_enviados',
                    'slots_disponibles' => count($slots)
                ];
            }
            
            // FALLBACK si falla la plantilla
            error_log("📅 TwilioBot: Plantilla temporal falló, usando fallback");
            return $this->enviarContactoDirectoFallback($alertaId);
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR procesarRespuestaSiSimplificado: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * **NUEVA PLANTILLA MEJORADA**: Una variable por slot - FORMATO PERFECTO
     * Template: HX2c89326481fdc97a27d7cb3aa8a873a4
     * Variables: {{1}} = nombre, {{2}}-{{10}} = slots individuales
     */
    private function enviarPlantillaTemporal($telefono, $nombreCliente, $slots) {
        try {
            error_log("📅 TwilioBot: enviarPlantillaTemporal NUEVA - Nombre: {$nombreCliente}");
            error_log("📅 TwilioBot: Slots individuales: " . count($slots) . " slots");
            
            // Cargar .env con método más directo
            $envPath = __DIR__ . '/../.env';
            error_log("TwilioBot: Buscando .env en: {$envPath}");
            
            if (!file_exists($envPath)) {
                throw new Exception("Archivo .env no encontrado en: {$envPath}");
            }
            
            // Leer .env línea por línea (método robusto)
            $envContent = file_get_contents($envPath);
            $envLines = explode("\n", $envContent);
            $env = [];
            
            foreach ($envLines as $line) {
                $line = trim($line);
                // Ignorar comentarios y líneas vacías
                if (empty($line) || $line[0] === '#') continue;
                
                // Buscar líneas con = 
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $env[trim($key)] = trim($value);
                }
            }
            
            // Buscar variables EXACTAS como tu .env
            $sid = $env['TWILIO_ACCOUNT_SID'] ?? '';
            $token = $env['TWILIO_AUTH_TOKEN'] ?? '';  
            $fromNumber = $env['TWILIO_WHATSAPP_FROM'] ?? '';
            
            error_log("TwilioBot: Variables cargadas del .env:");
            error_log("TwilioBot: TWILIO_ACCOUNT_SID = " . ($sid ? 'ENCONTRADO (' . substr($sid, 0, 10) . '...)' : 'VACIO'));
            error_log("TwilioBot: TWILIO_AUTH_TOKEN = " . ($token ? 'ENCONTRADO (' . substr($token, 0, 10) . '...)' : 'VACIO'));
            error_log("TwilioBot: TWILIO_WHATSAPP_FROM = " . ($fromNumber ? $fromNumber : 'VACIO'));
            
            if (empty($sid) || empty($token) || empty($fromNumber)) {
                throw new Exception("Variables Twilio no encontradas en .env. Verificar nombres exactos.");
            }
            
            // **NUEVA LÓGICA: Variables individuales para cada slot**
            $contentVariables = [
                "1" => $nombreCliente    // {{1}} = Nombre cliente
            ];
            
            // Agregar slots individuales {{2}} a {{9}} (máximo 8 horarios)
            $contador = 1;
            foreach ($slots as $slot) {
                if ($contador > 8) break;
                
                $slotTexto = "{$contador}. {$slot['fecha_display']} {$slot['hora_display']}";
                $contentVariables[($contador + 1)] = $slotTexto; // {{2}}, {{3}}, etc.
                
                error_log("📅 TwilioBot: Variable " . ($contador + 1) . " = {$slotTexto}");
                $contador++;
            }
            
            // Completar variables faltantes con vacío si hay menos de 8 slots
            while ($contador <= 8) {
                $contentVariables[($contador + 1)] = ""; // Variables vacías
                $contador++;
            }
            
            // {{10}} siempre = "9. Otro horario"
            $contentVariables["10"] = "9. Otro horario";
            
            error_log("TwilioBot: Preparando cURL con variables individuales:");
            error_log("TwilioBot: Total variables: " . count($contentVariables));
            error_log("TwilioBot: ContentVariables = " . json_encode($contentVariables));
            
            // **NUEVA PLANTILLA SID - LA BONITA**
            $templateNuevo = "HX2c89326481fdc97a27d7cb3aa8a873a4";
            
            // **cURL usando NUEVA plantilla mejorada**
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => http_build_query([
                'ContentSid' => $templateNuevo, // NUEVA PLANTILLA BONITA
                'From' => $fromNumber,
                'To' => "whatsapp:+52{$telefono}",
                'ContentVariables' => json_encode($contentVariables)
              ]),
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($sid . ':' . $token)
              ),
            ));
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            
            curl_close($curl);
            
            error_log("TwilioBot cURL: HTTP Code = {$httpCode}");
            error_log("TwilioBot cURL: Response = {$response}");
            if ($curlError) {
                error_log("TwilioBot cURL: Error = {$curlError}");
            }
            
            if ($curlError) {
                throw new Exception("cURL Error: {$curlError}");
            }
            
            $responseData = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['sid'])) {
                error_log("TwilioBot cURL: ¡ÉXITO CON NUEVA PLANTILLA BONITA! Message SID = {$responseData['sid']}");
                
                return [
                    'success' => true,
                    'message_sid' => $responseData['sid'],
                    'status' => $responseData['status'] ?? 'queued',
                    'twilio_response' => $responseData
                ];
            } else {
                $errorMsg = isset($responseData['message']) ? $responseData['message'] : "HTTP {$httpCode}";
                error_log("TwilioBot cURL: ERROR = {$errorMsg}");
                error_log("TwilioBot cURL: Full Response = " . json_encode($responseData));
                throw new Exception("Twilio cURL Error: {$errorMsg}");
            }
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR cURL COMPLETO: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * **FALLBACK**: Enviar mensaje de contacto directo cuando falla la plantilla
     */
    private function enviarContactoDirectoFallback($alertaId) {
        try {
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            $mensajeFallback = "¡Excelente decisión! 🎉\n\nEn este momento nuestro calendario automático está temporalmente no disponible.\n\nNuestro equipo se pondrá en contacto contigo en breve para coordinar el horario perfecto para tu cita de mantenimiento.\n\n¡Gracias por confiar en nosotros! 🚗⚡";
            
            $resultado = $this->enviarMensajeTexto($telefono, $mensajeFallback);
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    $mensajeFallback,
                    'text',
                    'contacto_directo_fallback',
                    $resultado
                );
                
                $this->actualizarEstadoAlerta($alertaId, 'requiere_contacto');
                $this->marcarRequiereAtencion($alertaId, 'alta');
                
                error_log("TwilioBot: Fallback enviado para {$alerta['cliente_nombre']} - requiere contacto directo");
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'contacto_directo_fallback'
                ];
            }
            
            throw new Exception("Error enviando mensaje de fallback");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarContactoDirectoFallback: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * PASO 2B SIMPLIFICADO: Cliente dijo NO - Enviar mensaje psicológico
     */
    private function procesarRespuestaNoSimplificado($alertaId, $messageSid) {
        try {
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            // Actualizar estado
            $this->actualizarRespuestaInicial($alertaId, 'no');
            $this->actualizarEstadoAlerta($alertaId, 'rechazado');
            
            // Registrar respuesta del cliente
            $this->registrarMensaje(
                $alertaId,
                $messageSid,
                'inbound',
                "whatsapp:+52{$telefono}",
                $this->whatsappFrom,
                'No, gracias',
                'button',
                'respuesta_no_gracias'
            );
            
            // Obtener mensaje psicológico desde BD
            $mensajeNo = $this->obtenerConfiguracion('mensaje_no_gracias');
            
            if (empty($mensajeNo)) {
                $mensajeNo = "Entendemos perfectamente. A veces el momento no es el ideal 😊\n\nTu vehículo siempre será bienvenido cuando sientes que es el momento correcto para darle el cuidado que se merece.\n\nMientras tanto, si tienes alguna duda o emergencia, estamos aquí.\n\n¡Que tengas un excelente día! 🚗✨";
            }
            
            // Enviar mensaje psicológico
            $resultado = $this->enviarMensajeTexto($telefono, $mensajeNo);
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    $mensajeNo,
                    'text',
                    'mensaje_psicologico',
                    $resultado
                );
                
                // Marcar como baja prioridad (informativo)
                $this->marcarRequiereAtencion($alertaId, 'baja');
                
                error_log("TwilioBot: Cliente {$alerta['cliente_nombre']} RECHAZÓ - enviado mensaje psicológico");
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'rechazado'
                ];
            }
            
            throw new Exception("Error enviando mensaje psicológico");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR procesarRespuestaNoSimplificado: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ========================================
     * SISTEMA DE CALENDARIO INTELIGENTE
     * ========================================
     */

    /**
     * Calcular slots disponibles para calendario
     */
    public function calcularSlotsDisponibles($horaActual = null) {
        try {
            $horaActual = $horaActual ?: date('H:i');
            $fechaActual = new DateTime();
            
            error_log("🔍 DEBUG calcularSlotsDisponibles: Hora actual = {$horaActual}");
            error_log("🔍 DEBUG calcularSlotsDisponibles: Fecha actual = " . $fechaActual->format('Y-m-d H:i:s'));
            
            // Obtener configuraciones
            $horariosConfig = $this->obtenerConfiguracion('horarios_atencion');
            $horaLimite = $this->obtenerConfiguracion('hora_limite_dia_siguiente');
            $diasLaborales = explode(',', $this->obtenerConfiguracion('dias_laborales'));
            $diasFestivos = json_decode($this->obtenerConfiguracion('dias_festivos_2026'), true) ?: [];
            $slotsMaximo = (int)$this->obtenerConfiguracion('slots_maximo_mostrar');
            
            error_log("🔍 DEBUG calcularSlotsDisponibles: horariosConfig = '{$horariosConfig}'");
            error_log("🔍 DEBUG calcularSlotsDisponibles: horaLimite = '{$horaLimite}'");
            error_log("🔍 DEBUG calcularSlotsDisponibles: diasLaborales = " . json_encode($diasLaborales));
            error_log("🔍 DEBUG calcularSlotsDisponibles: slotsMaximo = {$slotsMaximo}");
            
            $horarios = explode(',', $horariosConfig);
            $slots = [];
            
            error_log("🔍 DEBUG calcularSlotsDisponibles: horarios array = " . json_encode($horarios));
            
            // Determinar fecha inicial según hora límite
            $fechaInicio = clone $fechaActual;
            if ($horaActual >= $horaLimite) {
                // Después de 6 PM → pasado mañana
                $fechaInicio->modify('+2 days');
            } else {
                // Antes de 6 PM → mañana
                $fechaInicio->modify('+1 day');
            }
            
            $slotCount = 0;
            $diasBuscados = 0;
            $maxDiasBuscar = 14; // Máximo 2 semanas
            
            while ($slotCount < $slotsMaximo && $diasBuscados < $maxDiasBuscar) {
                $fechaBuscar = clone $fechaInicio;
                $fechaBuscar->modify("+{$diasBuscados} days");
                
                // Verificar si es día laborable
                $nombreDia = $fechaBuscar->format('l'); // Monday, Tuesday, etc.
                if (!in_array($nombreDia, $diasLaborales)) {
                    $diasBuscados++;
                    continue;
                }
                
                // Verificar si es día festivo
                $fechaString = $fechaBuscar->format('Y-m-d');
                if (in_array($fechaString, $diasFestivos)) {
                    $diasBuscados++;
                    continue;
                }
                
                // Verificar horarios disponibles para este día
                foreach ($horarios as $hora) {
                    if ($slotCount >= $slotsMaximo) break;
                    
                    $horaFormateada = trim($hora);
                    if ($this->verificarSlotDisponible($fechaString, $horaFormateada)) {
                        $slots[] = [
                            'fecha' => $fechaString,
                            'hora' => $horaFormateada,
                            'fecha_display' => $this->formatearFechaCliente($fechaBuscar),
                            'hora_display' => $this->formatearHoraCliente($horaFormateada),
                            'slot_id' => 'slot_' . ($slotCount + 1)
                        ];
                        $slotCount++;
                    }
                }
                
                $diasBuscados++;
            }
            
            return $slots;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR calcularSlotsDisponibles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verificar si un slot específico está disponible
     */
    private function verificarSlotDisponible($fecha, $hora) {
        try {
            $capacidadTotal = (int)$this->obtenerConfiguracion('capacidad_por_slot');
            
            // Verificar en calendario_disponibilidad
            $sql = "SELECT citas_ocupadas, esta_disponible, capacidad_total FROM calendario_disponibilidad 
                   WHERE fecha = ? AND hora = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$fecha, $hora]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado) {
                return $resultado['esta_disponible'] == 1 && 
                       $resultado['citas_ocupadas'] < $resultado['capacidad_total'];
            }
            
            // Si no existe registro, crear uno y está disponible
            $sqlInsert = "INSERT IGNORE INTO calendario_disponibilidad 
                         (fecha, hora, capacidad_total, citas_ocupadas, esta_disponible) 
                         VALUES (?, ?, ?, 0, 1)";
            $stmtInsert = $this->db->prepare($sqlInsert);
            $stmtInsert->execute([$fecha, $hora, $capacidadTotal]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR verificarSlotDisponible: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Formatear fecha para mostrar al cliente
     */
    private function formatearFechaCliente($fechaObj) {
        $formatoConfig = $this->obtenerConfiguracion('formato_fecha_cliente');
        $formato = $formatoConfig ?: 'D j M';
        
        // Traducir al español
        $meses = [
            'Jan' => 'Ene', 'Feb' => 'Feb', 'Mar' => 'Mar', 'Apr' => 'Abr',
            'May' => 'May', 'Jun' => 'Jun', 'Jul' => 'Jul', 'Aug' => 'Ago',
            'Sep' => 'Sep', 'Oct' => 'Oct', 'Nov' => 'Nov', 'Dec' => 'Dic'
        ];
        
        $dias = [
            'Mon' => 'Lun', 'Tue' => 'Mar', 'Wed' => 'Mié', 'Thu' => 'Jue',
            'Fri' => 'Vie', 'Sat' => 'Sáb', 'Sun' => 'Dom'
        ];
        
        $fechaFormateada = $fechaObj->format($formato);
        $fechaFormateada = strtr($fechaFormateada, $meses);
        $fechaFormateada = strtr($fechaFormateada, $dias);
        
        return $fechaFormateada;
    }

    /**
     * Formatear hora para mostrar al cliente
     */
    private function formatearHoraCliente($hora) {
        $formatoConfig = $this->obtenerConfiguracion('formato_hora_cliente');
        $formato = $formatoConfig ?: 'g:i A';
        
        $horaObj = DateTime::createFromFormat('H:i', $hora);
        return $horaObj->format($formato);
    }

    /**
     * Enviar calendario con template dinámico (SISTEMA ADAPTABLE)
     * Soporta modo simple (texto) y modo interactive (botones)
     */
    public function enviarCalendarioTemplate($alertaId) {
        try {
            // **DEBUGGING CALENDARIO**
            error_log("📅 TwilioBot: enviarCalendarioTemplate iniciado para alerta ID: {$alertaId}");
            
            // Obtener datos de la alerta
            $sql = "SELECT a.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono,
                           v.marca, v.modelo, v.anio
                    FROM alertas_servicio a
                    JOIN clientes c ON a.cliente_id = c.id
                    JOIN vehiculos v ON a.vehiculo_id = v.id
                    WHERE a.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$alertaId]);
            $alerta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$alerta) {
                throw new Exception("Alerta no encontrada: {$alertaId}");
            }
            
            // Calcular slots disponibles
            $slots = $this->calcularSlotsDisponibles();
            
            if (empty($slots)) {
                error_log("📅 TwilioBot: No hay slots disponibles, enviando mensaje de contacto directo");
                return $this->enviarMensajeContactoDirecto($alerta);
            }
            
            error_log("📅 TwilioBot: {" . count($slots) . "} slots calculados");
            
            // **DETECCIÓN DEL MODO DE TEMPLATE**
            $templateMode = $this->obtenerConfiguracion('template_mode');
            
            if ($templateMode === 'simple') {
                // **MODO SIMPLE: Plantilla con texto numerado**
                error_log("📅 TwilioBot: Usando modo SIMPLE (texto numerado)");
                return $this->enviarCalendarioTemplateSimple($alertaId, $alerta, $slots);
            } else {
                // **MODO INTERACTIVE: Plantilla con botones (modo original)**
                error_log("📅 TwilioBot: Usando modo INTERACTIVE (botones)");
                return $this->enviarCalendarioTemplateInteractive($alertaId, $alerta, $slots);
            }
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarCalendarioTemplate: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * MODO SIMPLE: Enviar calendario con plantilla simple (TEXTO NUMERADO)
     * Usa la plantilla aprobada HX183daf481204160ef29a837ce1b22ecb
     */
    private function enviarCalendarioTemplateSimple($alertaId, $alerta, $slots) {
        try {
            error_log("📅 TwilioBot: enviarCalendarioTemplateSimple - Modo TEXTO numerado");
            
            // Obtener template SID para modo simple
            $templateSid = $this->obtenerConfiguracion('template_simple_sid');
            if (empty($templateSid)) {
                throw new Exception("Template simple no configurado en BD");
            }
            
            // Formatear horarios como texto numerado (1-8 + 9=otro horario)
            $horariosTexto = $this->formatearHorariosTextoNumerado($slots);
            
            // Variables para la plantilla simple
            // Variable 1: Nombre cliente
            // Variable 2: Lista horarios numerados
            $variables = [
                $alerta['cliente_nombre'],  // {1}
                $horariosTexto              // {2}
            ];
            
            error_log("📅 TwilioBot: Variables template simple:");
            error_log("📅 TwilioBot: Nombre: " . $alerta['cliente_nombre']);
            error_log("📅 TwilioBot: Horarios: " . $horariosTexto);
            
            // Enviar con plantilla simple
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            $resultado = $this->enviarTemplate($templateSid, $telefono, $variables);
            
            if ($resultado['success']) {
                // Guardar slots en sesión
                $this->guardarSlotsSession($alertaId, $slots);
                
                // Actualizar estado alerta
                $this->actualizarEstadoAlerta($alertaId, 'esperando_fecha', $resultado['message_sid']);
                
                // Registrar mensaje
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    "Template Simple: {$alerta['cliente_nombre']}, horarios numerados",
                    'template',
                    'calendario_simple',
                    $resultado
                );
                
                error_log("📅 TwilioBot: Template SIMPLE enviado exitosamente");
                return $resultado;
            }
            
            throw new Exception("Error enviando template simple");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarCalendarioTemplateSimple: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * MODO INTERACTIVE: Enviar calendario con plantilla interactiva (BOTONES)
     * Usa la plantilla pendiente HX765eae763cf778deacde6238674d4108
     */
    private function enviarCalendarioTemplateInteractive($alertaId, $alerta, $slots) {
        try {
            error_log("📅 TwilioBot: enviarCalendarioTemplateInteractive - Modo BOTONES");
            
            // Obtener template SID para modo interactive
            $templateSid = $this->obtenerConfiguracion('template_interactive_sid');
            if (empty($templateSid)) {
                throw new Exception("Template interactive no configurado en BD");
            }
            
            // Preparar variables del template (modo original)
            $variables = [];
            foreach ($slots as $index => $slot) {
                $variables[] = $slot['fecha_display'] . ' - ' . $slot['hora_display'];
            }
            
            // Completar hasta 8 variables si hay menos slots
            while (count($variables) < 8) {
                $variables[] = '';
            }
            
            error_log("📅 TwilioBot: Variables template interactive: " . json_encode($variables));
            
            // Enviar template interactivo
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            $resultado = $this->enviarTemplate($templateSid, $telefono, $variables);
            
            if ($resultado['success']) {
                // Guardar slots en sesión
                $this->guardarSlotsSession($alertaId, $slots);
                
                // Actualizar estado alerta
                $this->actualizarEstadoAlerta($alertaId, 'esperando_fecha', $resultado['message_sid']);
                
                // Registrar mensaje
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    "Template Interactive: calendario con botones",
                    'template',
                    'calendario_interactive',
                    $resultado
                );
                
                error_log("📅 TwilioBot: Template INTERACTIVE enviado exitosamente");
                return $resultado;
            }
            
            throw new Exception("Error enviando template interactive");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarCalendarioTemplateInteractive: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Formatear horarios como texto numerado para plantilla simple
     * SOLUCION: Sin saltos de línea - Twilio no permite \n en Content Variables
     */
    private function formatearHorariosTextoNumerado($slots) {
        try {
            $horariosTexto = "";
            $contador = 1;
            
            // Agregar slots disponibles (máximo 8) - SIN SALTOS DE LÍNEA
            foreach ($slots as $slot) {
                if ($contador > 8) break;
                
                // USAR SEPARADOR COMPATIBLE CON TWILIO (sin \n)
                if ($contador > 1) {
                    $horariosTexto .= " • ";  // Separador bullet point
                }
                $horariosTexto .= "{$contador}. {$slot['fecha_display']} {$slot['hora_display']}";
                $contador++;
            }
            
            // Siempre agregar opción "9. Otro horario"
            $horariosTexto .= " • 9. Otro horario";
            
            error_log("📅 TwilioBot: Horarios SIN newlines: " . $horariosTexto);
            
            return $horariosTexto;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR formatearHorariosTextoNumerado: " . $e->getMessage());
            return "Error generando horarios";
        }
    }

    /**
     * Procesar respuesta numérica de cliente (para modo simple)
     */
    public function procesarRespuestaNumericaSimple($alertaId, $respuesta, $messageSid) {
        try {
            error_log("📅 TwilioBot: procesarRespuestaNumericaSimple - Respuesta: '{$respuesta}'");
            
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            // Registrar respuesta del cliente
            $this->registrarMensaje(
                $alertaId,
                $messageSid,
                'inbound',
                "whatsapp:+52{$telefono}",
                $this->whatsappFrom,
                $respuesta,
                'text',
                'seleccion_horario_simple'
            );
            
            // Validar respuesta numérica con manejo de "clientes estúpidos" 😂
            $numeroSeleccion = $this->validarRespuestaNumericaRobusta($respuesta);
            
            if ($numeroSeleccion === false) {
                return $this->enviarMensajeAyudaValidacion($alertaId);
            }
            
            // Manejar opción 9 = "Otro horario"
            if ($numeroSeleccion === 9) {
                error_log("📅 TwilioBot: Cliente seleccionó 'Otro horario' (opción 9)");
                return $this->procesarOtroHorario($alertaId, $messageSid);
            }
            
            // Manejar selección de horario (1-8)
            if ($numeroSeleccion >= 1 && $numeroSeleccion <= 8) {
                return $this->procesarSeleccionHorarioSimple($alertaId, $numeroSeleccion, $messageSid);
            }
            
            // Si llega aquí, número fuera de rango
            return $this->enviarMensajeAyudaValidacion($alertaId);
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR procesarRespuestaNumericaSimple: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Validar respuesta numérica con manejo robusto de "clientes estúpidos"
     */
    private function validarRespuestaNumericaRobusta($respuesta) {
        try {
            // Limpiar la respuesta
            $respuestaLimpia = trim(strtolower($respuesta));
            
            // Extraer primer número encontrado
            if (preg_match('/(\d+)/', $respuestaLimpia, $matches)) {
                $numero = (int)$matches[1];
                
                // Validar rango 1-9
                if ($numero >= 1 && $numero <= 9) {
                    return $numero;
                }
            }
            
            // Manejo de texto especial para "otro horario"
            if (preg_match('/\b(otro|contacto|directo|diferente|cambiar)\b/i', $respuestaLimpia)) {
                return 9;
            }
            
            error_log("TwilioBot: Respuesta inválida detectada: '{$respuesta}'");
            return false;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR validarRespuestaNumericaRobusta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Enviar mensaje de ayuda para validación CON SISTEMA DE INTENTOS
     */
    private function enviarMensajeAyudaValidacion($alertaId) {
        try {
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            // **SISTEMA DE INTENTOS: Incrementar contador de intentos fallidos**
            $intentosActuales = $this->incrementarIntentosRespuesta($alertaId);
            $maxIntentos = (int)$this->obtenerConfiguracion('max_intentos_respuesta') ?: 3;
            
            error_log("🚫 TwilioBot: Cliente con respuesta inválida - Intento {$intentosActuales}/{$maxIntentos}");
            
            // **VERIFICAR SI EXCEDIÓ MÁXIMO DE INTENTOS**
            if ($intentosActuales >= $maxIntentos) {
                error_log("🚨 TwilioBot: Cliente excedió máximo de intentos - Enviando a contacto directo");
                return $this->manejarExcesoIntentos($alertaId);
            }
            
            // **MENSAJE DE AYUDA PROGRESIVO**
            $mensajeAyuda = $this->obtenerMensajeAyudaPorIntento($intentosActuales);
            
            $resultado = $this->enviarMensajeTexto($telefono, $mensajeAyuda);
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    $mensajeAyuda,
                    'text',
                    "mensaje_ayuda_intento_{$intentosActuales}",
                    $resultado
                );
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarMensajeAyudaValidacion: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Incrementar contador de intentos de respuesta inválida
     */
    private function incrementarIntentosRespuesta($alertaId) {
        try {
            // Obtener intentos actuales
            $sql = "SELECT intentos_respuesta_invalida FROM alertas_servicio WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$alertaId]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $intentosActuales = ($resultado['intentos_respuesta_invalida'] ?? 0) + 1;
            
            // Actualizar contador
            $sqlUpdate = "UPDATE alertas_servicio 
                         SET intentos_respuesta_invalida = ?, 
                             fecha_ultimo_intento_invalido = NOW()
                         WHERE id = ?";
            $stmtUpdate = $this->db->prepare($sqlUpdate);
            $stmtUpdate->execute([$intentosActuales, $alertaId]);
            
            return $intentosActuales;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR incrementarIntentosRespuesta: " . $e->getMessage());
            return 1; // Default fallback
        }
    }
    
    /**
     * Obtener mensaje de ayuda progresivo según el intento
     */
    private function obtenerMensajeAyudaPorIntento($intento) {
        switch ($intento) {
            case 1:
                // PRIMER INTENTO: Mensaje gentil
                $mensaje = $this->obtenerConfiguracion('mensaje_ayuda_respuesta');
                if (empty($mensaje)) {
                    $mensaje = "Por favor responde solo con el *NÚMERO* de la opción que prefieres (ejemplo: 1, 2, 3...). ¡Así podremos ayudarte mejor! 😊";
                }
                break;
                
            case 2:
                // SEGUNDO INTENTO: Más específico
                $mensaje = "Necesito que respondas *SOLO CON UN NÚMERO* del 1 al 9:\n\n";
                $mensaje .= "✅ Ejemplos correctos: \"1\", \"3\", \"5\"\n";
                $mensaje .= "❌ No escribas: \"la primera\", \"opción 2\", \"me gusta la tercera\"\n\n";
                $mensaje .= "Solo escribe el número y nada más. ¡Inténtalo otra vez! 🎯";
                break;
                
            default:
                // ÚLTIMO INTENTO: Advertencia
                $mensaje = "⚠️ *ÚLTIMO INTENTO*\n\n";
                $mensaje .= "Por favor, responde únicamente con un NÚMERO:\n";
                $mensaje .= "• Para la primera opción: escribe \"1\"\n";
                $mensaje .= "• Para la segunda opción: escribe \"2\"\n";
                $mensaje .= "• Y así sucesivamente...\n\n";
                $mensaje .= "Si no respondes correctamente, nuestro equipo se pondrá en contacto contigo directamente.";
                break;
        }
        
        return $mensaje;
    }
    
    /**
     * Manejar cuando el cliente excede el máximo de intentos
     */
    private function manejarExcesoIntentos($alertaId) {
        try {
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            // Mensaje final explicando que se contactará directamente
            $mensajeFinal = "Entiendo que puede ser confuso elegir de esta manera. 😅\n\n";
            $mensajeFinal .= "No te preocupes, nuestro equipo se pondrá en contacto contigo directamente para coordinar tu cita de mantenimiento.\n\n";
            $mensajeFinal .= "¡Gracias por tu paciencia! 🚗✨";
            
            $resultado = $this->enviarMensajeTexto($telefono, $mensajeFinal);
            
            if ($resultado['success']) {
                // Actualizar estado a requiere contacto directo
                $this->actualizarEstadoAlerta($alertaId, 'requiere_contacto');
                $this->marcarRequiereAtencion($alertaId, 'alta');
                
                // Registrar mensaje
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    $mensajeFinal,
                    'text',
                    'exceso_intentos_contacto_directo',
                    $resultado
                );
                
                // Notificar al admin sobre el caso especial
                $this->notificarAdminExcesoIntentos($alerta);
                
                error_log("TwilioBot: Cliente {$alerta['cliente_nombre']} excedió intentos máximos - derivado a contacto directo");
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR manejarExcesoIntentos: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Notificar al admin cuando un cliente excede intentos
     */
    private function notificarAdminExcesoIntentos($alerta) {
        try {
            if (empty($this->sagAdminPhone)) {
                return false;
            }
            
            $mensaje = "⚠️ CLIENTE CON DIFICULTADES TÉCNICAS\n\n";
            $mensaje .= "Cliente: {$alerta['cliente_nombre']}\n";
            $mensaje .= "Teléfono: {$alerta['cliente_telefono']}\n";
            $mensaje .= "Problema: No pudo seleccionar horario en WhatsApp\n";
            $mensaje .= "Acción: Requiere contacto directo URGENTE\n\n";
            $mensaje .= "📞 Contactar para agendar manualmente";
            
            $this->enviarMensajeTexto($this->sagAdminPhone, $mensaje);
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR notificarAdminExcesoIntentos: " . $e->getMessage());
        }
    }
    
    /**
     * Resetear contador de intentos cuando el cliente responde correctamente
     */
    private function resetearIntentosRespuesta($alertaId) {
        try {
            $sql = "UPDATE alertas_servicio 
                   SET intentos_respuesta_invalida = 0,
                       fecha_ultimo_intento_invalido = NULL
                   WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$alertaId]);
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR resetearIntentosRespuesta: " . $e->getMessage());
        }
    }

    /**
     * Procesar selección de horario en modo simple
     */
    private function procesarSeleccionHorarioSimple($alertaId, $numeroSeleccion, $messageSid) {
        try {
            error_log("📅 TwilioBot: procesarSeleccionHorarioSimple - Opción: {$numeroSeleccion}");
            
            // Obtener slots guardados de la sesión
            $sql = "SELECT respuesta_cliente FROM alertas_servicio WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$alertaId]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $slots = json_decode($resultado['respuesta_cliente'], true);
            if (!$slots) {
                throw new Exception("Slots no encontrados en sesión");
            }
            
            // Obtener slot seleccionado (índice 0-based)
            $indiceSlot = $numeroSeleccion - 1;
            if (!isset($slots[$indiceSlot])) {
                throw new Exception("Slot no válido: {$numeroSeleccion}");
            }
            
            $slotSeleccionado = $slots[$indiceSlot];
            
            error_log("📅 TwilioBot: Slot seleccionado: {$slotSeleccionado['fecha']} {$slotSeleccionado['hora']}");
            
            // **VERIFICACIÓN DOBLE: Re-verificar disponibilidad antes de pre-agendar**
            if (!$this->verificarSlotDisponible($slotSeleccionado['fecha'], $slotSeleccionado['hora'])) {
                error_log("📅 TwilioBot: Slot {$slotSeleccionado['fecha']} {$slotSeleccionado['hora']} ya no está disponible");
                // Slot ocupado desde que se generó la lista, ofrecer alternativa
                return $this->enviarContactoDirectoFallback($alertaId);
            }
            
            // Pre-agendar cita
            $resultadoPreAgenda = $this->preAgendarCita($alertaId, $slotSeleccionado);
            
            if ($resultadoPreAgenda['success']) {
                return $this->enviarConfirmacionPreAgenda($alertaId, $slotSeleccionado);
            }
            
            throw new Exception("Error en pre-agendamiento");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR procesarSeleccionHorarioSimple: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Guardar slots de la sesión para mapeo posterior
     */
    private function guardarSlotsSession($alertaId, $slots) {
        try {
            error_log("🔧 TwilioBot: guardarSlotsSession INICIADO - AlertaID: {$alertaId}");
            
            // **VERIFICAR ESTADO ANTES**
            $this->verificarEstadoBD($alertaId, "ANTES de guardarSlotsSession");
            
            $slotsData = json_encode($slots);
            $sql = "UPDATE alertas_servicio SET 
                   respuesta_cliente = ? 
                   WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $resultado = $stmt->execute([$slotsData, $alertaId]);
            
            if ($resultado) {
                $filasAfectadas = $stmt->rowCount();
                error_log("🔧 TwilioBot: guardarSlotsSession exitoso - Filas afectadas: {$filasAfectadas}");
            } else {
                $errorInfo = $stmt->errorInfo();
                error_log("❌ TwilioBot: ERROR SQL guardarSlotsSession - SQLSTATE: {$errorInfo[0]}, Error: {$errorInfo[2]}");
            }
            
            // **VERIFICAR ESTADO DESPUÉS**
            $this->verificarEstadoBD($alertaId, "DESPUÉS de guardarSlotsSession");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR guardarSlotsSession: " . $e->getMessage());
        }
    }

    /**
     * Procesar selección de slot del calendario
     */
    public function procesarSeleccionSlot($alertaId, $slotId, $messageSid) {
        try {
            error_log("📅 TwilioBot: procesarSeleccionSlot - Slot: {$slotId}");
            
            if ($slotId === 'otro_horario') {
                return $this->procesarOtroHorario($alertaId, $messageSid);
            }
            
            // Obtener slots guardados
            $sql = "SELECT respuesta_cliente FROM alertas_servicio WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$alertaId]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $slots = json_decode($resultado['respuesta_cliente'], true);
            if (!$slots) {
                throw new Exception("Slots no encontrados en sesión");
            }
            
            // Buscar slot seleccionado
            $slotSeleccionado = null;
            foreach ($slots as $slot) {
                if ($slot['slot_id'] === $slotId) {
                    $slotSeleccionado = $slot;
                    break;
                }
            }
            
            if (!$slotSeleccionado) {
                throw new Exception("Slot no válido: {$slotId}");
            }
            
            // Pre-agendar cita
            $resultadoPreAgenda = $this->preAgendarCita($alertaId, $slotSeleccionado);
            
            if ($resultadoPreAgenda['success']) {
                return $this->enviarConfirmacionPreAgenda($alertaId, $slotSeleccionado);
            }
            
            throw new Exception("Error en pre-agendamiento");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR procesarSeleccionSlot: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enviar confirmación de pre-agendamiento
     */
    private function enviarConfirmacionPreAgenda($alertaId, $slot) {
        try {
            // **FIX: Usar obtenerDatosAlerta() que ya incluye datos del vehículo**
            $alerta = $this->obtenerDatosAlerta($alertaId);
            
            if (!$alerta) {
                throw new Exception("Alerta no encontrada: {$alertaId}");
            }
            
            // Mensaje de confirmación al cliente
            $plantillaMensaje = $this->obtenerConfiguracion('mensaje_pre_agenda_exito');
            $mensaje = str_replace(
                ['{{fecha}}', '{{hora}}'],
                [$slot['fecha_display'], $slot['hora_display']],
                $plantillaMensaje
            );
            
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            $resultadoCliente = $this->enviarMensajeTexto($telefono, $mensaje);
            
            // **ELIMINADO: Notificación duplicada por texto (causa "Outside messaging window")**
            // Ya se envió la plantilla admin en preAgendarCita() → enviarNotificacionSAG()
            
            return $resultadoCliente;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarConfirmacionPreAgenda: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Procesar opción "Otro horario"
     */
    private function procesarOtroHorario($alertaId, $messageSid) {
        try {
            // Actualizar estado alerta
            $sql = "UPDATE alertas_servicio 
                   SET estado_whatsapp = 'requiere_contacto',
                       requiere_atencion = 1,
                       fecha_respuesta_inicial = NOW()
                   WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$alertaId]);
            
            // Obtener datos completos
            $sql = "SELECT a.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono,
                           v.marca, v.modelo, v.anio
                    FROM alertas_servicio a
                    JOIN clientes c ON a.cliente_id = c.id
                    JOIN vehiculos v ON a.vehiculo_id = v.id
                    WHERE a.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$alertaId]);
            $alerta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Mensaje al cliente
            $mensajeCliente = $this->obtenerConfiguracion('mensaje_otro_horario');
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            $resultadoCliente = $this->enviarMensajeTexto($telefono, $mensajeCliente);
            
            // Notificar al admin
            if ($resultadoCliente['success']) {
                $this->notificarAdminOtroHorario($alerta);
            }
            
            return $resultadoCliente;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR procesarOtroHorario: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Notificar al admin sobre pre-agendamiento
     */
    private function notificarAdminPreAgenda($alerta, $slot) {
        try {
            $plantillaNotificacion = $this->obtenerConfiguracion('notificacion_admin_pre_agenda');
            
            // **FIX: Usar vehiculo_info que ya viene formateado desde obtenerDatosAlerta()**
            $vehiculoInfo = $alerta['vehiculo_info'] ?? 'Vehículo no especificado';
            
            // Si vehiculo_info contiene "Sin marca Sin modelo Sin año", usar datos individuales si existen
            if (strpos($vehiculoInfo, 'Sin marca Sin modelo Sin año') !== false) {
                // Hacer consulta específica para obtener datos del vehículo
                $sqlVehiculo = "SELECT marca, modelo, anio FROM vehiculos WHERE id = ?";
                $stmtVehiculo = $this->db->prepare($sqlVehiculo);
                $stmtVehiculo->execute([$alerta['vehiculo_id']]);
                $vehiculoData = $stmtVehiculo->fetch(PDO::FETCH_ASSOC);
                
                if ($vehiculoData) {
                    $marca = !empty($vehiculoData['marca']) ? $vehiculoData['marca'] : 'N/A';
                    $modelo = !empty($vehiculoData['modelo']) ? $vehiculoData['modelo'] : 'N/A';
                    $anio = !empty($vehiculoData['anio']) ? $vehiculoData['anio'] : 'N/A';
                    $vehiculoInfo = "{$marca} {$modelo} {$anio}";
                }
            }
            
            $mensaje = str_replace([
                '{{cliente}}', '{{fecha}}', '{{hora}}', 
                '{{vehiculo}}', '{{servicio}}'
            ], [
                $alerta['cliente_nombre'],
                $slot['fecha_display'],
                $slot['hora_display'], 
                $vehiculoInfo,
                $alerta['servicios_que_dispararon']
            ], $plantillaNotificacion);
            
            $adminPhone = $this->sagAdminPhone;
            if (!empty($adminPhone)) {
                $this->enviarMensajeTexto($adminPhone, $mensaje);
            }
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR notificarAdminPreAgenda: " . $e->getMessage());
        }
    }

    /**
     * Notificar al admin sobre "otro horario"
     */
    private function notificarAdminOtroHorario($alerta) {
        try {
            $plantillaNotificacion = $this->obtenerConfiguracion('notificacion_admin_otro_horario');
            
            // **FIX: USAR NUEVO MÉTODO para obtener datos reales del vehículo**
            $vehiculoInfo = $this->obtenerDatosVehiculo($alerta['vehiculo_id'] ?? null);
            
            error_log("📧 TwilioBot: notificarAdminOtroHorario - Vehículo obtenido = {$vehiculoInfo}");
            
            $mensaje = str_replace([
                '{{cliente}}', '{{telefono}}', '{{vehiculo}}', '{{servicio}}'
            ], [
                $alerta['cliente_nombre'],
                $alerta['cliente_telefono'],
                $vehiculoInfo,
                $alerta['servicios_que_dispararon']
            ], $plantillaNotificacion);
            
            $adminPhone = $this->sagAdminPhone;
            if (!empty($adminPhone)) {
                $this->enviarMensajeTexto($adminPhone, $mensaje);
            }
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR notificarAdminOtroHorario: " . $e->getMessage());
        }
    }

    /**
     * Enviar mensaje de contacto directo (fallback)
     */
    private function enviarMensajeContactoDirecto($alerta) {
        try {
            $mensaje = "En este momento no tenemos horarios disponibles en nuestro calendario automático. 📅\n\nNuestro equipo se pondrá en contacto contigo para coordinar tu cita de mantenimiento.\n\n¡Gracias por tu paciencia! 🚗✨";
            
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            $resultado = $this->enviarMensajeTexto($telefono, $mensaje);
            
            if ($resultado['success']) {
                // Actualizar estado y notificar admin
                $sql = "UPDATE alertas_servicio 
                       SET estado_whatsapp = 'requiere_contacto',
                           requiere_atencion = 1
                       WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$alerta['id']]);
                
                $this->notificarAdminOtroHorario($alerta);
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarMensajeContactoDirecto: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * **NUEVO: Enviar plantilla de notificación admin (solución Outside messaging window)**
     */
    private function enviarPlantillaNotificacionAdmin($telefono, $alerta, $fechaSeleccionada) {
        try {
            error_log("📧 TwilioBot: enviarPlantillaNotificacionAdmin - Teléfono: {$telefono}");
            
            // Obtener template SID desde BD (nombre correcto)
            $templateSid = $this->obtenerConfiguracion('sag_notificacion_cita_admin');
            if (empty($templateSid)) {
                throw new Exception("Template sag_notificacion_cita_admin no configurado en BD");
            }
            
            error_log("📧 TwilioBot: Template SID admin: {$templateSid}");
            
            // **FIX: OBTENER DATOS REALES DEL VEHÍCULO DESDE BD**
            $vehiculoInfo = $this->obtenerDatosVehiculo($alerta['vehiculo_id'] ?? null);
            
            error_log("📧 TwilioBot: Vehículo obtenido = {$vehiculoInfo}");
            
            // Formatear fecha y hora para display
            $fechaFormateada = $fechaSeleccionada['fecha_display'];
            $horaFormateada = $fechaSeleccionada['hora_display'];
            
            // Variables para plantilla admin
            // {{1}} = Cliente, {{2}} = Fecha, {{3}} = Hora, {{4}} = Vehículo, {{5}} = Servicio, {{6}} = Teléfono Cliente
            $contentVariables = [
                "1" => $alerta['cliente_nombre'],
                "2" => $fechaFormateada,
                "3" => $horaFormateada,
                "4" => $vehiculoInfo,
                "5" => $alerta['servicios_que_dispararon'],
                "6" => $alerta['cliente_telefono']
            ];
            
            error_log("📧 TwilioBot: Variables admin template:");
            error_log("📧 TwilioBot: Cliente = " . $alerta['cliente_nombre']);
            error_log("📧 TwilioBot: Fecha = {$fechaFormateada}");
            error_log("📧 TwilioBot: Hora = {$horaFormateada}");
            error_log("📧 TwilioBot: Vehículo = {$vehiculoInfo}");
            error_log("📧 TwilioBot: Servicio = " . $alerta['servicios_que_dispararon']);
            error_log("📧 TwilioBot: Teléfono Cliente = " . $alerta['cliente_telefono']);
            
            // **CAMBIO: Obtener credenciales desde BD (consistente con otros templates)**
            $sid = $this->obtenerConfiguracion('account_sid');
            $token = $this->obtenerConfiguracion('auth_token');
            $fromNumber = $this->obtenerConfiguracion('whatsapp_from');
            
            error_log("📧 TwilioBot: Credenciales desde BD:");
            error_log("📧 TwilioBot: Account SID = " . ($sid ? 'ENCONTRADO (' . substr($sid, 0, 10) . '...)' : 'VACIO'));
            error_log("📧 TwilioBot: Auth Token = " . ($token ? 'ENCONTRADO (' . substr($token, 0, 10) . '...)' : 'VACIO'));
            error_log("📧 TwilioBot: WhatsApp From = " . ($fromNumber ? $fromNumber : 'VACIO'));
            
            if (empty($sid) || empty($token) || empty($fromNumber)) {
                throw new Exception("Credenciales Twilio no configuradas en BD (account_sid, auth_token, whatsapp_from)");
            }
            
            // **cURL para enviar plantilla admin**
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => http_build_query([
                'ContentSid' => $templateSid,
                'From' => $fromNumber,
                'To' => "whatsapp:+52{$telefono}",
                'ContentVariables' => json_encode($contentVariables)
              ]),
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($sid . ':' . $token)
              ),
            ));
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            
            curl_close($curl);
            
            error_log("TwilioBot cURL ADMIN: HTTP Code = {$httpCode}");
            error_log("TwilioBot cURL ADMIN: Response = {$response}");
            
            if ($curlError) {
                throw new Exception("cURL Error: {$curlError}");
            }
            
            $responseData = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['sid'])) {
                error_log("TwilioBot cURL ADMIN: ¡ÉXITO PLANTILLA ADMIN! Message SID = {$responseData['sid']}");
                
                return [
                    'success' => true,
                    'message_sid' => $responseData['sid'],
                    'status' => $responseData['status'] ?? 'queued',
                    'twilio_response' => $responseData
                ];
            } else {
                $errorMsg = isset($responseData['message']) ? $responseData['message'] : "HTTP {$httpCode}";
                error_log("TwilioBot cURL ADMIN: ERROR = {$errorMsg}");
                error_log("TwilioBot cURL ADMIN: Full Response = " . json_encode($responseData));
                throw new Exception("Twilio Admin Template Error: {$errorMsg}");
            }
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarPlantillaNotificacionAdmin: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enviar template con cURL directo
     */
    private function enviarTemplate($templateSid, $telefono, $variables) {
        try {
            // Cargar configuración desde .env
            $envPath = __DIR__ . '/../.env';
            
            if (!file_exists($envPath)) {
                throw new Exception("Archivo .env no encontrado");
            }
            
            $envContent = file_get_contents($envPath);
            $envLines = explode("\n", $envContent);
            $env = [];
            
            foreach ($envLines as $line) {
                $line = trim($line);
                if (empty($line) || $line[0] === '#') continue;
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $env[trim($key)] = trim($value);
                }
            }
            
            $sid = $env['TWILIO_ACCOUNT_SID'] ?? '';
            $token = $env['TWILIO_AUTH_TOKEN'] ?? '';  
            $fromNumber = $env['TWILIO_WHATSAPP_FROM'] ?? '';
            
            if (empty($sid) || empty($token) || empty($fromNumber)) {
                throw new Exception("Variables Twilio no configuradas en .env");
            }
            
            // Preparar variables del template
            $contentVariables = [];
            foreach ($variables as $index => $value) {
                $contentVariables[($index + 1)] = $value;
            }
            
            // cURL para enviar template
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => http_build_query([
                'ContentSid' => $templateSid,
                'From' => $fromNumber,
                'To' => "whatsapp:+52{$telefono}",
                'ContentVariables' => json_encode($contentVariables)
              ]),
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . base64_encode($sid . ':' . $token)
              ),
            ));
            
            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            
            curl_close($curl);
            
            if ($curlError) {
                throw new Exception("cURL Error: {$curlError}");
            }
            
            $responseData = json_decode($response, true);
            
            if ($httpCode >= 200 && $httpCode < 300 && isset($responseData['sid'])) {
                return [
                    'success' => true,
                    'message_sid' => $responseData['sid'],
                    'status' => $responseData['status'] ?? 'queued',
                    'twilio_response' => $responseData
                ];
            } else {
                $errorMsg = isset($responseData['message']) ? $responseData['message'] : "HTTP {$httpCode}";
                throw new Exception("Twilio Template Error: {$errorMsg}");
            }
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarTemplate: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * MÉTODOS AUXILIARES FALTANTES
     */
    
    /**
     * Enviar mensaje de aclaración cuando la selección no es válida
     */
    private function enviarMensajeAclaracion($alertaId) {
        try {
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            $mensajeAclaracion = "Por favor, selecciona una opción válida del 1 al 6, o escribe 'otra fecha' para contacto directo.\n\n¿Podrías intentarlo de nuevo?";
            
            $resultado = $this->enviarMensajeTexto($telefono, $mensajeAclaracion);
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    $mensajeAclaracion,
                    'text',
                    'mensaje_aclaracion',
                    $resultado
                );
            }
            
            return $resultado;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarMensajeAclaracion: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Cancelar cita por parte de SAG
     */
    private function cancelarCita($alertaId, $userId = null) {
        try {
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            // Actualizar estado en BD
            $this->actualizarConfirmacionSAG($alertaId, 'cancelado', $userId);
            $this->actualizarEstadoAlerta($alertaId, 'cancelado');
            
            // Liberar slot en calendario
            if ($alerta['fecha_cita_seleccionada'] && $alerta['hora_cita_seleccionada']) {
                $this->liberarSlotCalendario($alerta['fecha_cita_seleccionada'], $alerta['hora_cita_seleccionada']);
            }
            
            // Enviar notificación de cancelación al cliente
            $mensajeCancelacion = $this->obtenerConfiguracion('mensaje_cancelacion');
            if (empty($mensajeCancelacion)) {
                $mensajeCancelacion = "Lamentamos informarte que tu cita ha sido cancelada por motivos internos. Nuestro equipo se pondrá en contacto contigo para reprogramarla. Disculpa las molestias.";
            }
            
            $resultado = $this->enviarMensajeTexto($telefono, $mensajeCancelacion);
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    $mensajeCancelacion,
                    'text',
                    'mensaje_cancelacion',
                    $resultado
                );
                
                // Marcar como requiere atención para reprogramar
                $this->marcarRequiereAtencion($alertaId, 'alta');
                
                error_log("TwilioBot: Cita CANCELADA para {$alerta['cliente_nombre']}");
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'cancelado'
                ];
            }
            
            throw new Exception("Error enviando mensaje de cancelación");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR cancelarCita: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Reprogramar cita por parte de SAG
     */
    private function reprogramarCita($alertaId, $userId = null) {
        try {
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            $telefono = $this->limpiarTelefono($alerta['cliente_telefono']);
            
            // Actualizar estado en BD
            $this->actualizarConfirmacionSAG($alertaId, 'reprogramar', $userId);
            $this->actualizarEstadoAlerta($alertaId, 'reprogramando');
            
            // Liberar slot actual
            if ($alerta['fecha_cita_seleccionada'] && $alerta['hora_cita_seleccionada']) {
                $this->liberarSlotCalendario($alerta['fecha_cita_seleccionada'], $alerta['hora_cita_seleccionada']);
            }
            
            // Enviar mensaje al cliente para reprogramar
            $mensajeReprogramar = $this->obtenerConfiguracion('mensaje_reprogramar');
            if (empty($mensajeReprogramar)) {
                $mensajeReprogramar = "Necesitamos reprogramar tu cita. ¿Te gustaría seleccionar una nueva fecha? Responde 'SÍ' para ver fechas disponibles o nuestro equipo se pondrá en contacto contigo.";
            }
            
            $resultado = $this->enviarMensajeTexto($telefono, $mensajeReprogramar);
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+52{$telefono}",
                    $mensajeReprogramar,
                    'text',
                    'mensaje_reprogramar',
                    $resultado
                );
                
                // Marcar como requiere atención
                $this->marcarRequiereAtencion($alertaId, 'alta');
                
                error_log("TwilioBot: Iniciando REPROGRAMACIÓN para {$alerta['cliente_nombre']}");
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'reprogramando'
                ];
            }
            
            throw new Exception("Error enviando mensaje de reprogramación");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR reprogramarCita: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Enviar mensaje de aclaración a SAG cuando la respuesta no es reconocida
     */
    private function enviarAclaracionSAG($alertaId) {
        try {
            if (empty($this->sagAdminPhone)) {
                error_log("TwilioBot: No hay teléfono admin configurado para enviar aclaración");
                return [
                    'success' => false,
                    'error' => 'No hay teléfono admin configurado'
                ];
            }
            
            // Obtener datos de la alerta
            $alerta = $this->obtenerDatosAlerta($alertaId);
            
            $mensajeAclaracion = "⚠️ RESPUESTA NO RECONOCIDA\n\n";
            $mensajeAclaracion .= "Cliente: {$alerta['cliente_nombre']}\n";
            $mensajeAclaracion .= "Fecha: {$alerta['fecha_cita_seleccionada']} {$alerta['hora_cita_seleccionada']}\n\n";
            $mensajeAclaracion .= "Por favor responde:\n";
            $mensajeAclaracion .= "1. CONFIRMAR\n";
            $mensajeAclaracion .= "2. CANCELAR\n";
            $mensajeAclaracion .= "3. REPROGRAMAR";
            
            $resultado = $this->enviarMensajeTexto($this->sagAdminPhone, $mensajeAclaracion);
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+{$this->sagAdminPhone}",
                    $mensajeAclaracion,
                    'text',
                    'aclaracion_sag',
                    $resultado
                );
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid']
                ];
            }
            
            throw new Exception("Error enviando aclaración a SAG");
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR enviarAclaracionSAG: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Liberar slot en calendario cuando se cancela una cita
     */
    private function liberarSlotCalendario($fecha, $hora) {
        try {
            $sql = "UPDATE calendario_disponibilidad 
                    SET citas_ocupadas = GREATEST(0, citas_ocupadas - 1)
                    WHERE fecha = ? AND hora = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$fecha, $hora]);
            
            error_log("TwilioBot: Slot liberado en calendario - {$fecha} {$hora}");
            return true;
            
        } catch (Exception $e) {
            error_log("TwilioBot ERROR liberarSlotCalendario: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * **DEBUG CRÍTICO**: Verificar inmediatamente el estado en BD
     */
    private function verificarEstadoBD($alertaId, $contexto) {
        try {
            error_log("🔍 TwilioBot: verificarEstadoBD {$contexto} - AlertaID: {$alertaId}");
            
            $sql = "SELECT id, estado_whatsapp, twilio_conversation_sid, ultima_actividad 
                   FROM alertas_servicio WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$alertaId]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($resultado) {
                error_log("🔍 TwilioBot: BD REAL {$contexto}:");
                error_log("🔍 TwilioBot:   ID: {$resultado['id']}");
                error_log("🔍 TwilioBot:   estado_whatsapp: '{$resultado['estado_whatsapp']}'");
                error_log("🔍 TwilioBot:   twilio_conversation_sid: '{$resultado['twilio_conversation_sid']}'");
                error_log("🔍 TwilioBot:   ultima_actividad: '{$resultado['ultima_actividad']}'");
                
                // **CRÍTICO**: Detectar si está vacío después de actualización exitosa
                if (empty($resultado['estado_whatsapp'])) {
                    error_log("🚨 TwilioBot: PROBLEMA CRÍTICO - Estado VACÍO en BD después de actualización exitosa!");
                } else {
                    error_log("✅ TwilioBot: Estado BD CORRECTO: '{$resultado['estado_whatsapp']}'");
                }
            } else {
                error_log("❌ TwilioBot: ERROR - Alerta ID {$alertaId} NO EXISTE en BD!");
            }
            
        } catch (Exception $e) {
            error_log("💥 TwilioBot: EXCEPCIÓN verificarEstadoBD: " . $e->getMessage());
        }
    }
}

/**
 * Función helper para crear instancia del bot
 */
function crearTwilioBot() {
    try {
        return new TwilioConversationalBot();
    } catch (Exception $e) {
        error_log("ERROR creando TwilioBot: " . $e->getMessage());
        return null;
    }
}

?>
