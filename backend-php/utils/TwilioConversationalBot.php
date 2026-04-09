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
     * PASO 2: Procesar respuesta inicial del cliente (sí/no)
     */
    public function procesarRespuestaInicial($alertaId, $respuesta, $messageSid) {
        try {
            $respuestaLower = strtolower(trim($respuesta));
            
            // Detectar tipo de respuesta
            if (preg_match('/\b(si|sí|1|interesa|yes)\b/', $respuestaLower)) {
                return $this->procesarRespuestaSi($alertaId, $messageSid);
            } 
            elseif (preg_match('/\b(no|2|gracias|nah)\b/', $respuestaLower)) {
                return $this->procesarRespuestaNo($alertaId, $messageSid);
            } 
            else {
                // Respuesta no reconocida
                return $this->enviarMensajeAclaracion($alertaId);
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
            
            // Enviar confirmación al cliente
            $mensajePreAgenda = $this->obtenerConfiguracion('mensaje_pre_agenda');
            $mensaje = $this->reemplazarVariablesFecha($mensajePreAgenda, $fechaSeleccionada['fecha'], $fechaSeleccionada['hora']);
            
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
                    'confirmacion_pre_agenda',
                    $resultado
                );
                
                // Enviar notificación a SAG Garage
                $this->enviarNotificacionSAG($alertaId, $alerta, $fechaSeleccionada);
                
                // Actualizar estado y marcar como requiere atención urgente
                $this->actualizarEstadoAlerta($alertaId, 'pre_agendado');
                $this->marcarRequiereAtencion($alertaId, 'alta');
                
                error_log("TwilioBot: Cita pre-agendada para {$alerta['cliente_nombre']} - {$fechaSeleccionada['fecha']} {$fechaSeleccionada['hora']}");
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'cita_id' => $citaId,
                    'fecha' => $fechaSeleccionada['fecha'],
                    'hora' => $fechaSeleccionada['hora']
                ];
            }
            
            throw new Exception("Error enviando confirmación de pre-agenda");
            
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
            
            // Crear mensaje para SAG
            $mensaje = "📅 CONFIRMAR CITA\n\n";
            $mensaje .= "Cliente: {$alerta['cliente_nombre']}\n";
            $mensaje .= "Servicio: {$alerta['servicios_que_dispararon']}\n";
            $mensaje .= "Vehículo: {$alerta['vehiculo_info']}\n";
            $mensaje .= "Fecha: " . date('l j \d\e F', strtotime($fechaSeleccionada['fecha'])) . "\n";
            $mensaje .= "Hora: " . date('H:i', strtotime($fechaSeleccionada['hora'])) . "\n\n";
            $mensaje .= "Responde para confirmar:\n";
            $mensaje .= "1. ✅ CONFIRMAR\n";
            $mensaje .= "2. ❌ CANCELAR\n";
            $mensaje .= "3. 📅 REPROGRAMAR";
            
            // Enviar via Twilio con botones
            $resultado = $this->enviarMensajeConBotones(
                $this->sagAdminPhone,
                $mensaje,
                [
                    ['id' => 'confirmar_cita', 'title' => '✅ CONFIRMAR'],
                    ['id' => 'cancelar_cita', 'title' => '❌ CANCELAR'],
                    ['id' => 'reprogramar_cita', 'title' => '📅 REPROGRAMAR']
                ],
                'confirmacion_sag'
            );
            
            if ($resultado['success']) {
                $this->registrarMensaje(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->whatsappFrom,
                    "whatsapp:+{$this->sagAdminPhone}",
                    $mensaje,
                    'interactive',
                    'confirmacion_sag',
                    $resultado
                );
                
                error_log("TwilioBot: Notificación enviada a SAG para confirmación");
                return true;
            }
            
            throw new Exception("Error enviando notificación a SAG");
            
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
            
            error_log("TwilioBot: Variables cargadas del .env:");
            error_log("TwilioBot: TWILIO_ACCOUNT_SID = " . ($sid ? 'ENCONTRADO (' . substr($sid, 0, 10) . '...)' : 'VACIO'));
            error_log("TwilioBot: TWILIO_AUTH_TOKEN = " . ($token ? 'ENCONTRADO (' . substr($token, 0, 10) . '...)' : 'VACIO'));
            error_log("TwilioBot: TWILIO_WHATSAPP_FROM = " . ($fromNumber ? $fromNumber : 'VACIO'));
            
            if (empty($sid) || empty($token) || empty($fromNumber)) {
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
                    CONCAT(v.marca, ' ', v.modelo, ' ', v.anio) as vehiculo_info
                FROM alertas_servicio a
                INNER JOIN clientes c ON a.cliente_id = c.id
                INNER JOIN vehiculos v ON a.vehiculo_id = v.id
                WHERE a.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$alertaId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function actualizarEstadoAlerta($alertaId, $estado, $messageSid = null) {
        $sql = "UPDATE alertas_servicio 
                SET estado_whatsapp = ?, 
                    fecha_envio_whatsapp = CASE WHEN ? IS NOT NULL THEN NOW() ELSE fecha_envio_whatsapp END,
                    twilio_conversation_sid = COALESCE(?, twilio_conversation_sid)
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$estado, $messageSid, $messageSid, $alertaId]);
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