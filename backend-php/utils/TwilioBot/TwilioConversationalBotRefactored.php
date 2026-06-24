<?php
/**
 * TwilioConversationalBot - VERSIÓN REFACTORIZADA
 * 
 * Esta es la nueva implementación del bot conversacional de Twilio que mantiene
 * 100% de compatibilidad con la versión anterior pero internamente usa la
 * arquitectura refactorizada siguiendo principios SOLID.
 * 
 * IMPORTANTE: Esta clase mantiene exactamente la misma interfaz pública que
 * TwilioConversationalBot.php original para garantizar compatibilidad total.
 * 
 * Mejoras implementadas:
 * - Separación de responsabilidades (SRP)
 * - Inyección de dependencias
 * - Logging centralizado y mejorado
 * - Manejo de errores más robusto
 * - Código más mantenible y testeable
 * - Mejor organización de funcionalidades
 * 
 * @author Sistema CarFix - Refactorización 2026
 * @version 2.0.0
 */

require_once __DIR__ . '/Core/TwilioClientManager.php';
require_once __DIR__ . '/Core/ConfigurationService.php';
require_once __DIR__ . '/Core/MessageLogger.php';
require_once __DIR__ . '/Services/MessageSender.php';
require_once __DIR__ . '/Repositories/AlertRepository.php';
require_once __DIR__ . '/Validators/PhoneValidator.php';

class TwilioConversationalBotRefactored 
{
    /**
     * Gestor del cliente Twilio
     * @var TwilioClientManager
     */
    private $clientManager;
    
    /**
     * Servicio de configuración
     * @var ConfigurationService
     */
    private $config;
    
    /**
     * Logger de mensajes
     * @var MessageLogger
     */
    private $logger;
    
    /**
     * Servicio de envío de mensajes
     * @var MessageSender
     */
    private $messageSender;
    
    /**
     * Repositorio de alertas
     * @var AlertRepository
     */
    private $alertRepo;
    
    /**
     * Validador de teléfonos
     * @var PhoneValidator
     */
    private $phoneValidator;
    
    /**
     * Constructor - Mantiene compatibilidad con versión original
     */
    public function __construct() 
    {
        $this->logger = MessageLogger::getInstance();
        $this->logger->logOperation("TwilioConversationalBot REFACTORIZADO inicializando...", [
            'version' => '2.0.0',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Inicializar servicios refactorizados
        $this->initializeServices();
        
        $this->logger->logOperation("TwilioConversationalBot REFACTORIZADO inicializado correctamente", [
            'simulation_mode' => $this->clientManager->isSimulationMode(),
            'services_ready' => $this->areServicesReady()
        ]);
    }
    
    /**
     * Inicializar todos los servicios refactorizados
     * 
     * @return void
     */
    private function initializeServices(): void 
    {
        try {
            // Core services
            $this->clientManager = TwilioClientManager::getInstance();
            $this->config = ConfigurationService::getInstance();
            
            // Business services
            $this->messageSender = new MessageSender();
            $this->alertRepo = new AlertRepository();
            $this->phoneValidator = new PhoneValidator();
            
            $this->logger->logOperation("Servicios refactorizados inicializados", [
                'client_ready' => $this->clientManager->isReadyForSending(),
                'config_loaded' => true,
                'repositories_ready' => true
            ]);
            
        } catch (Exception $e) {
            $this->logger->logError("Error inicializando servicios refactorizados", $e);
            throw $e;
        }
    }
    
    /**
     * Verificar que todos los servicios estén listos
     * 
     * @return bool True si todos los servicios están listos
     */
    private function areServicesReady(): bool 
    {
        return $this->clientManager !== null &&
               $this->config !== null &&
               $this->messageSender !== null &&
               $this->alertRepo !== null &&
               $this->phoneValidator !== null;
    }
    
    // ============================================================================
    // MÉTODOS PÚBLICOS - MANTIENEN EXACTAMENTE LA MISMA INTERFAZ QUE LA VERSIÓN ORIGINAL
    // ============================================================================
    
    /**
     * PASO 1: Enviar recordatorio inicial con botones
     * 
     * Mantiene exactamente la misma interfaz que la versión original
     * pero internamente usa la nueva arquitectura refactorizada.
     * 
     * @param int $alertaId ID de la alerta
     * @return array Resultado del envío (misma estructura que versión original)
     */
    public function enviarRecordatorioInicial($alertaId): array 
    {
        try {
            $this->logger->logConversationFlow($alertaId, 'enviar_recordatorio_inicial', 'iniciado');
            
            // Obtener datos de la alerta usando el repositorio refactorizado
            $alerta = $this->alertRepo->getAlertaById($alertaId);
            if (!$alerta) {
                throw new Exception("Alerta ID {$alertaId} no encontrada");
            }
            
            // Validar estado (manteniendo la lógica original)
            if ($alerta['estado_whatsapp'] !== 'borrador' && !is_null($alerta['estado_whatsapp'])) {
                throw new Exception("Alerta ID {$alertaId} no está en estado borrador");
            }
            
            // Validar teléfono con el validador refactorizado
            $telefonoLimpio = $this->phoneValidator->cleanPhoneNumber($alerta['cliente_telefono']);
            if (!$telefonoLimpio) {
                throw new Exception("Teléfono no válido: {$alerta['cliente_telefono']}");
            }
            
            $this->logger->logConversationFlow($alertaId, 'enviar_recordatorio_inicial', 'datos_validados', [
                'cliente' => $alerta['cliente_nombre'],
                'telefono' => $telefonoLimpio
            ]);
            
            // Enviar mensaje con plantilla usando el servicio refactorizado
            $resultado = $this->messageSender->sendTemplateMessage(
                $telefonoLimpio,
                $this->config->getConfig('template_interactive_sid'), // SID recordatorio inicial (carfix_recordatorio)
                [
                    '1' => $alerta['cliente_nombre'],
                    '2' => '6 meses',
                    '3' => is_array($alerta['servicios_que_dispararon']) ? 
                           implode(', ', $alerta['servicios_que_dispararon']) : 
                           $alerta['servicios_que_dispararon']
                ]
            );
            
            if ($resultado['success']) {
                // Actualizar estado usando el repositorio refactorizado
                $this->alertRepo->updateEstadoWhatsApp($alertaId, 'enviado', $resultado['message_sid']);

                // Construir el body resuelto con las variables que ya están en scope.
                // Opción C: se hace aquí mismo, sin reconstrucción posterior.
                $serviciosStr = is_array($alerta['servicios_que_dispararon'])
                    ? implode(', ', $alerta['servicios_que_dispararon'])
                    : $alerta['servicios_que_dispararon'];
                $textoRecordatorio = str_replace(
                    ['{{1}}', '{{2}}', '{{3}}'],
                    [$alerta['cliente_nombre'], '6 meses', $serviciosStr],
                    "👋 ¡Hola {{1}}! Espero que estés muy bien 😊\n\n"
                    . "🔧 Han pasado {{2}} desde tu {{3}} en CarFix y queremos asegurarnos de que tu vehículo siga en perfectas condiciones.\n\n"
                    . "🚗💙 Sabemos lo importante que es tu auto para ti, por eso te recordamos con cariño que es momento del siguiente servicio.\n\n"
                    . "✨ ¿Te gustaría que te ayudemos a agendar una cita? Estamos aquí para cuidar tu vehículo como se merece 🙌"
                );
                $this->logger->logMessage(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->config->getWhatsappFrom(),
                    "whatsapp:+52{$telefonoLimpio}",
                    $textoRecordatorio,
                    'template',
                    'recordatorio_inicial',
                    $resultado['twilio_response'] ?? null
                );
                
                $this->logger->logConversationFlow($alertaId, 'enviar_recordatorio_inicial', 'completado', [
                    'message_sid' => $resultado['message_sid'],
                    'cliente' => $alerta['cliente_nombre']
                ]);
                
                // Retorna exactamente la misma estructura que la versión original
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'telefono' => $telefonoLimpio
                ];
            } else {
                throw new Exception("Error enviando mensaje: " . $resultado['error']);
            }
            
        } catch (Exception $e) {
            $this->logger->logConversationFlow($alertaId, 'enviar_recordatorio_inicial', 'error', [
                'error' => $e->getMessage()
            ]);
            
            // Retorna exactamente la misma estructura que la versión original
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PASO 2: Procesar respuesta inicial del cliente (button IDs)
     * 
     * Mantiene exactamente la misma interfaz que la versión original.
     * 
     * @param int $alertaId ID de la alerta
     * @param string $respuesta Respuesta del cliente
     * @param string $messageSid SID del mensaje
     * @param array $webhookData Datos del webhook
     * @return array Resultado del procesamiento
     */
    public function procesarRespuestaInicial($alertaId, $respuesta, $messageSid, $webhookData = []): array 
    {
        try {
            $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_inicial', 'iniciado', [
                'respuesta' => $respuesta,
                'message_sid' => $messageSid,
                'webhook_data' => $webhookData
            ]);
            
            // Detectar button ID desde webhook data (lógica original preservada)
            $buttonId = $webhookData['ButtonId'] ?? $webhookData['ButtonPayload'] ?? $webhookData['Button'] ?? '';
            
            $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_inicial', 'button_detected', [
                'button_id' => $buttonId
            ]);
            
            if (!empty($buttonId)) {
                if ($buttonId === 'si_interesa') {
                    $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_inicial', 'si_interesa_detected');
                    return $this->procesarRespuestaSiSimplificado($alertaId, $messageSid);
                } elseif ($buttonId === 'no_gracias') {
                    $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_inicial', 'no_gracias_detected');
                    return $this->procesarRespuestaNoSimplificado($alertaId, $messageSid);
                }
            }
            
            // Fallback: detectar por texto (lógica original preservada)
            $respuestaLower = strtolower(trim($respuesta));
            
            if (preg_match('/\b(si|sí|1|interesa|yes)\b/', $respuestaLower)) {
                return $this->procesarRespuestaSiSimplificado($alertaId, $messageSid);
            } elseif (preg_match('/\b(no|2|gracias|nah)\b/', $respuestaLower)) {
                return $this->procesarRespuestaNoSimplificado($alertaId, $messageSid);
            } else {
                // Respuesta no reconocida: aplicar lógica de reintentos inválidos
                $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_inicial', 'respuesta_no_reconocida', [
                    'respuesta' => $respuesta
                ]);

                return $this->manejarRespuestaInvalida($alertaId, $messageSid, $respuesta);
            }
            
        } catch (Exception $e) {
            $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_inicial', 'error', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PASO 2A: Cliente dijo SÍ - Enviar fechas disponibles
     * 
     * Versión simplificada que mantiene la funcionalidad original.
     * 
     * @param int $alertaId ID de la alerta
     * @param string $messageSid SID del mensaje
     * @return array Resultado del procesamiento
     */
    private function procesarRespuestaSiSimplificado($alertaId, $messageSid): array 
    {
        try {
            $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_si', 'iniciado');
            
            // Obtener datos de la alerta
            $alerta = $this->alertRepo->getAlertaById($alertaId);
            $telefonoLimpio = $this->phoneValidator->cleanPhoneNumber($alerta['cliente_telefono']);
            
            // Actualizar respuesta inicial
            $this->alertRepo->updateRespuestaInicial($alertaId, 'si');
            
            // Registrar respuesta del cliente
            $this->logger->logMessage(
                $alertaId,
                $messageSid,
                'inbound',
                "whatsapp:+52{$telefonoLimpio}",
                $this->config->getWhatsappFrom(),
                'Sí, me interesa',
                'button',
                'respuesta_si_interesa'
            );
            
            // Obtener horarios reales desde calendario_disponibilidad
            $maxSlots    = (int)($this->config->getConfig('slots_maximo_mostrar') ?? 8);
            $diasMinimo  = (int)($this->config->getConfig('dias_minimo_agenda') ?? 1);
            $diasFestivos = json_decode($this->config->getConfig('dias_festivos_2026') ?? '[]', true) ?: [];
            $horarios = $this->alertRepo->getHorariosDisponibles($maxSlots, $diasFestivos, $diasMinimo);

            if (empty($horarios)) {
                $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_si', 'sin_horarios_disponibles');
                return $this->enviarContactoDirectoFallback($alertaId);
            }

            // Enviar plantilla con horarios
            $resultado = $this->messageSender->sendTemplateMessage(
                $telefonoLimpio,
                $this->config->getConfig('template_agendar_sid'),
                $this->formatearVariablesHorarios($alerta['cliente_nombre'], $horarios)
            );

            if ($resultado['success']) {
                // Persistir slots para que procesarRespuestaNumericaSimple pueda resolver la selección
                $this->alertRepo->guardarSlotsOfrecidos($alertaId, $horarios);

                // Actualizar estado
                $this->alertRepo->updateEstadoWhatsApp($alertaId, 'esperando_fecha', $resultado['message_sid']);
                
                // Construir el body resuelto con las variables que ya están en scope.
                // Opción C: usamos las mismas variables que se pasaron a sendTemplateMessage.
                $variablesHorarios = $this->formatearVariablesHorarios($alerta['cliente_nombre'], $horarios);
                $slotLines = '';
                for ($i = 2; $i <= 10; $i++) {
                    $linea = $variablesHorarios[(string)$i] ?? '';
                    if ($linea !== '') {
                        $slotLines .= $linea . "\n";
                    }
                }
                $textoHorarios = "🚗✨ ¡Tenemos opciones para ti, {$alerta['cliente_nombre']}!\n\n"
                    . "Nos encantaría ayudarte a agendar el próximo servicio de tu vehículo 🔧\n\n"
                    . "📅 Estos son los horarios disponibles:\n"
                    . rtrim($slotLines) . "\n"
                    . "💬 Elige el que mejor te funcione y con gusto lo apartamos para ti";
                $this->logger->logMessage(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->config->getWhatsappFrom(),
                    "whatsapp:+52{$telefonoLimpio}",
                    $textoHorarios,
                    'template',
                    'plantilla_horarios',
                    $resultado['twilio_response'] ?? null
                );
                
                $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_si', 'completado', [
                    'message_sid' => $resultado['message_sid']
                ]);
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'horarios_enviados',
                    'slots_disponibles' => count($horarios)
                ];
            }
            
            // Fallback si falla
            return $this->enviarContactoDirectoFallback($alertaId);
            
        } catch (Exception $e) {
            $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_si', 'error', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * PASO 2B: Cliente dijo NO - Enviar mensaje de despedida
     * 
     * @param int $alertaId ID de la alerta
     * @param string $messageSid SID del mensaje
     * @return array Resultado del procesamiento
     */
    private function procesarRespuestaNoSimplificado($alertaId, $messageSid): array 
    {
        try {
            $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_no', 'iniciado');
            
            // Obtener datos de la alerta
            $alerta = $this->alertRepo->getAlertaById($alertaId);
            $telefonoLimpio = $this->phoneValidator->cleanPhoneNumber($alerta['cliente_telefono']);
            
            // Actualizar estado
            $this->alertRepo->updateRespuestaInicial($alertaId, 'no');
            $this->alertRepo->updateEstadoWhatsApp($alertaId, 'rechazado');
            
            // Registrar respuesta del cliente
            $this->logger->logMessage(
                $alertaId,
                $messageSid,
                'inbound',
                "whatsapp:+52{$telefonoLimpio}",
                $this->config->getWhatsappFrom(),
                'No, gracias',
                'button',
                'respuesta_no_gracias'
            );
            
            // Obtener mensaje de rechazo
            $mensajeRechazo = $this->config->getMessage('mensaje_no_gracias');
            
            // Enviar mensaje de despedida
            $resultado = $this->messageSender->sendTextMessage($telefonoLimpio, $mensajeRechazo);
            
            if ($resultado['success']) {
                $this->logger->logMessage(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->config->getWhatsappFrom(),
                    "whatsapp:+52{$telefonoLimpio}",
                    $mensajeRechazo,
                    'text',
                    'mensaje_rechazo',
                    $resultado['twilio_response'] ?? null
                );
                
                // Marcar como baja prioridad
                $this->alertRepo->markRequiereAtencion($alertaId, 'baja');
                
                $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_no', 'completado', [
                    'message_sid' => $resultado['message_sid']
                ]);
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'rechazado'
                ];
            }
            
            throw new Exception("Error enviando mensaje de rechazo");
            
        } catch (Exception $e) {
            $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_no', 'error', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    // ============================================================================
    // MÉTODOS DE UTILIDAD - Versiones simplificadas que mantienen funcionalidad
    // ============================================================================
    
    /**
     * Formatear variables para plantilla HX2c89326481fdc97a27d7cb3aa8a873a4
     * {{1}}=nombre, {{2}}-{{9}}=slots individuales, {{10}}="9. Otro horario"
     * Una variable por slot — Twilio rechaza \n dentro de ContentVariables.
     */
    private function formatearVariablesHorarios(string $clienteNombre, array $horarios): array
    {
        $variables = ['1' => $clienteNombre];

        $contador = 1;
        foreach ($horarios as $h) {
            if ($contador > 8) break;
            $variables[strval($contador + 1)] = "{$contador}. {$h['fecha_display']} {$h['hora_display']}";
            $contador++;
        }

        // Rellenar con vacío si hay menos de 8 slots
        while ($contador <= 8) {
            $variables[strval($contador + 1)] = '';
            $contador++;
        }

        $variables['10'] = '9. Otro horario';

        return $variables;
    }
    
    /**
     * Enviar mensaje de contacto directo cuando falla algo
     * 
     * @param int $alertaId ID de la alerta
     * @return array Resultado
     */
    private function enviarContactoDirectoFallback($alertaId): array 
    {
        try {
            $alerta = $this->alertRepo->getAlertaById($alertaId);
            $telefonoLimpio = $this->phoneValidator->cleanPhoneNumber($alerta['cliente_telefono']);
            
            $mensajeFallback = $this->config->getMessage('mensaje_contacto_directo');
            $resultado = $this->messageSender->sendTextMessage($telefonoLimpio, $mensajeFallback);
            
            if ($resultado['success']) {
                $this->logger->logMessage(
                    $alertaId,
                    $resultado['message_sid'],
                    'outbound',
                    $this->config->getWhatsappFrom(),
                    "whatsapp:+52{$telefonoLimpio}",
                    $mensajeFallback,
                    'text',
                    'contacto_directo_fallback',
                    $resultado['twilio_response'] ?? null
                );
                
                $this->alertRepo->updateEstadoWhatsApp($alertaId, 'requiere_contacto');
                $this->alertRepo->markRequiereAtencion($alertaId, 'alta');
                
                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'contacto_directo_fallback'
                ];
            }
            
            throw new Exception("Error enviando mensaje de fallback");
            
        } catch (Exception $e) {
            $this->logger->logError("Error en enviarContactoDirectoFallback", $e, [
                'alerta_id' => $alertaId
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Los slots guardados vencieron (cliente respondió días después).
     * Obtiene fechas frescas y reenvía el calendario sin cambiar el estado.
     */
    private function reenviarCalendarioActualizado(int $alertaId, array $alerta, string $telefonoLimpio): array
    {
        try {
            $maxSlots    = (int)($this->config->getConfig('slots_maximo_mostrar') ?? 8);
            $diasMinimo  = (int)($this->config->getConfig('dias_minimo_agenda') ?? 1);
            $diasFestivos = json_decode($this->config->getConfig('dias_festivos_2026') ?? '[]', true) ?: [];
            $horarios = $this->alertRepo->getHorariosDisponibles($maxSlots, $diasFestivos, $diasMinimo);

            if (empty($horarios)) {
                $this->logger->logConversationFlow($alertaId, 'reenviar_calendario', 'sin_horarios');
                return $this->enviarContactoDirectoFallback($alertaId);
            }

            // Avisar al cliente que la fecha ya pasó antes de mandar el nuevo calendario
            $this->messageSender->sendTextMessage(
                $telefonoLimpio,
                "Ups, parece que esa fecha ya pasó 😅 No hay problema, aquí te van nuevas opciones disponibles:"
            );

            $resultado = $this->messageSender->sendTemplateMessage(
                $telefonoLimpio,
                $this->config->getConfig('template_agendar_sid'),
                $this->formatearVariablesHorarios($alerta['cliente_nombre'], $horarios)
            );

            if ($resultado['success']) {
                $this->alertRepo->guardarSlotsOfrecidos($alertaId, $horarios);
                // Actualizar conversation_sid al nuevo mensaje; estado sigue esperando_fecha
                $this->alertRepo->updateEstadoWhatsApp($alertaId, 'esperando_fecha', $resultado['message_sid']);

                $this->logger->logConversationFlow($alertaId, 'reenviar_calendario', 'completado', [
                    'slots' => count($horarios)
                ]);

                return [
                    'success' => true,
                    'message_sid' => $resultado['message_sid'],
                    'accion' => 'calendario_actualizado',
                    'slots_disponibles' => count($horarios)
                ];
            }

            return $this->enviarContactoDirectoFallback($alertaId);

        } catch (Exception $e) {
            $this->logger->logError("Error en reenviarCalendarioActualizado", $e, ['alerta_id' => $alertaId]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // PASO 3: SELECCIÓN DE HORARIO
    // ============================================================================

    /**
     * PASO 3A: Cliente responde con un número (1-N) al listado de horarios
     *
     * @param int $alertaId
     * @param string $body   Texto del cliente (se espera un número)
     * @param string $messageSid
     * @return array
     */
    public function procesarRespuestaNumericaSimple(int $alertaId, string $body, string $messageSid): array
    {
        try {
            $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_numerica', 'iniciado', [
                'body' => $body
            ]);

            if (!preg_match('/^\s*(\d+)\s*$/', trim($body), $matches)) {
                return $this->manejarRespuestaInvalida($alertaId, $messageSid, $body);
            }
            $numero = (int)$matches[1];

            $slots = $this->alertRepo->getSlotsOfrecidos($alertaId);
            if (empty($slots)) {
                $this->logger->logConversationFlow($alertaId, 'procesar_respuesta_numerica', 'sin_slots_guardados');
                return $this->enviarContactoDirectoFallback($alertaId);
            }

            $maxOpcion = count($slots) + 1; // último número = "Otro horario"

            if ($numero < 1 || $numero > $maxOpcion) {
                return $this->manejarRespuestaInvalida($alertaId, $messageSid, $body);
            }

            if ($numero === $maxOpcion) {
                return $this->procesarSeleccionSlot($alertaId, 'otro_horario', $messageSid);
            }

            $slotSeleccionado = null;
            foreach ($slots as $slot) {
                if ((int)$slot['numero'] === $numero) {
                    $slotSeleccionado = $slot;
                    break;
                }
            }

            if (!$slotSeleccionado) {
                return $this->manejarRespuestaInvalida($alertaId, $messageSid, $body);
            }

            return $this->procesarSeleccionSlot($alertaId, $slotSeleccionado['slot_id'], $messageSid);

        } catch (Exception $e) {
            $this->logger->logError("Error en procesarRespuestaNumericaSimple", $e, [
                'alerta_id' => $alertaId
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * PASO 3B: Reservar el slot seleccionado, confirmar al cliente, notificar al admin
     *
     * @param int $alertaId
     * @param string $slotId  'slot_N' o 'otro_horario'
     * @param string $messageSid
     * @return array
     */
    public function procesarSeleccionSlot(int $alertaId, string $slotId, string $messageSid): array
    {
        try {
            $this->logger->logConversationFlow($alertaId, 'procesar_seleccion_slot', 'iniciado', [
                'slot_id' => $slotId
            ]);

            $alerta = $this->alertRepo->getAlertaById($alertaId);
            $telefonoLimpio = $this->phoneValidator->cleanPhoneNumber($alerta['cliente_telefono']);

            $this->logger->logMessage(
                $alertaId, $messageSid, 'inbound',
                "whatsapp:+52{$telefonoLimpio}", $this->config->getWhatsappFrom(),
                $slotId, 'text', 'seleccion_slot'
            );

            if ($slotId === 'otro_horario') {
                return $this->procesarOtroHorario($alertaId, $alerta, $telefonoLimpio);
            }

            $slots = $this->alertRepo->getSlotsOfrecidos($alertaId);
            $slot  = null;
            foreach ((array)$slots as $s) {
                if ($s['slot_id'] === $slotId) {
                    $slot = $s;
                    break;
                }
            }

            if (!$slot) {
                $this->logger->logConversationFlow($alertaId, 'procesar_seleccion_slot', 'slot_no_encontrado');
                return $this->enviarContactoDirectoFallback($alertaId);
            }

            // Si el slot guardado ya venció (cliente respondió días/semanas después),
            // limpiar el JSON y reenviar el calendario con fechas frescas
            if (!empty($slot['fecha']) && $slot['fecha'] < date('Y-m-d')) {
                $this->logger->logConversationFlow($alertaId, 'procesar_seleccion_slot', 'slot_vencido', [
                    'fecha_slot' => $slot['fecha']
                ]);
                return $this->reenviarCalendarioActualizado($alertaId, $alerta, $telefonoLimpio);
            }

            $tipoServicio = is_array($alerta['servicios_que_dispararon'])
                ? implode(', ', $alerta['servicios_que_dispararon'])
                : ($alerta['servicios_que_dispararon'] ?? 'Servicio general');

            $citaCreada = $this->alertRepo->crearCitaPreAgendada($alertaId, $slot, [
                'cliente_nombre'   => $alerta['cliente_nombre'],
                'cliente_telefono' => $alerta['cliente_telefono'],
                'vehiculo_info'    => $alerta['vehiculo_info'] ?? 'Sin vehículo',
                'tipo_servicio'    => $tipoServicio,
            ]);

            if (!$citaCreada) {
                $this->logger->logConversationFlow($alertaId, 'procesar_seleccion_slot', 'slot_sin_capacidad');
                return $this->enviarContactoDirectoFallback($alertaId);
            }

            $this->alertRepo->updateEstadoWhatsApp($alertaId, 'pre_agendado');
            $this->alertRepo->updateFechaCita($alertaId, $slot['fecha'], $slot['hora']);

            // Confirmar al cliente
            $plantillaConfirmacion = $this->config->getConfig('mensaje_pre_agenda_exito')
                ?? "¡Perfecto! 🎉 Tu cita ha sido pre-agendada para {{fecha}} a las {{hora}}.\n\nEn breve nuestro equipo confirmará tu cita. ¡Gracias!";
            $mensajeCliente = str_replace(
                ['{{fecha}}', '{{hora}}'],
                [$slot['fecha_display'], $slot['hora_display']],
                $plantillaConfirmacion
            );
            $resCliente = $this->messageSender->sendTextMessage($telefonoLimpio, $mensajeCliente);
            if ($resCliente['success']) {
                $this->logger->logMessage(
                    $alertaId, $resCliente['message_sid'], 'outbound',
                    $this->config->getWhatsappFrom(), "whatsapp:+52{$telefonoLimpio}",
                    $mensajeCliente, 'text', 'confirmacion_pre_agenda',
                    $resCliente['twilio_response'] ?? null
                );
            }

            $this->notificarAdminPreAgenda($alertaId, $alerta, $slot, $tipoServicio);

            $this->logger->logConversationFlow($alertaId, 'procesar_seleccion_slot', 'completado', [
                'fecha' => $slot['fecha'], 'hora' => $slot['hora']
            ]);

            return [
                'success'    => true,
                'accion'     => 'cita_pre_agendada',
                'fecha_cita' => $slot['fecha'],
                'hora_cita'  => $slot['hora'],
            ];

        } catch (Exception $e) {
            $this->logger->logError("Error en procesarSeleccionSlot", $e, ['alerta_id' => $alertaId]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Manejar respuesta inválida del cliente (texto libre fuera de opciones).
     *
     * - 1er intento en el paso actual: envía mensaje de advertencia amable.
     * - 2do intento en el paso actual: escala al admin y cierra el flujo automático.
     * El contador se almacena en alertas_servicio.intentos_invalidos y se
     * resetea automáticamente cada vez que el estado de la alerta avanza.
     *
     * @param int    $alertaId
     * @param string $messageSid    SID del mensaje inválido que envió el cliente
     * @param string $textoCliente  Texto literal que mandó el cliente (para el admin)
     */
    private function manejarRespuestaInvalida(int $alertaId, string $messageSid, string $textoCliente = ''): array
    {
        try {
            $alerta = $this->alertRepo->getAlertaById($alertaId);
            $telefonoLimpio = $this->phoneValidator->cleanPhoneNumber($alerta['cliente_telefono']);

            $intentos = $this->alertRepo->incrementarIntentosInvalidos($alertaId);

            $this->logger->logConversationFlow($alertaId, 'manejar_respuesta_invalida', 'intento_registrado', [
                'intentos' => $intentos,
                'texto_cliente' => $textoCliente,
            ]);

            if ($intentos < 2) {
                // --- Primer intento: advertencia amable ---
                $mensajeWarning = $this->config->getConfig('mensaje_respuesta_invalida_warning')
                    ?? "¡Casi! 😊 Por favor elige una de las opciones respondiendo con el número correspondiente.\n\nSi tienes alguna duda o necesitas atención personalizada, envía tu mensaje de nuevo y con gusto se lo haremos llegar al equipo de CarFix 🙌";

                $res = $this->messageSender->sendTextMessage($telefonoLimpio, $mensajeWarning);
                if ($res['success']) {
                    $this->logger->logMessage(
                        $alertaId, $res['message_sid'], 'outbound',
                        $this->config->getWhatsappFrom(), "whatsapp:+52{$telefonoLimpio}",
                        $mensajeWarning, 'text', 'warning_respuesta_invalida',
                        $res['twilio_response'] ?? null
                    );
                }
                return ['success' => true, 'accion' => 'warning_enviado', 'intentos' => $intentos];
            }

            // --- Segundo intento: escalar al admin ---
            $this->alertRepo->resetearIntentosInvalidos($alertaId);

            // Mensaje al cliente
            $mensajeEscalada = $this->config->getConfig('mensaje_atencion_personalizada_cliente')
                ?? "Lo siento, no pude entender tu mensaje 😊 Enseguida le aviso a alguien del equipo de CarFix para que te atiendan personalmente. ¡Gracias por tu paciencia! 🙌";

            $resCliente = $this->messageSender->sendTextMessage($telefonoLimpio, $mensajeEscalada);
            if ($resCliente['success']) {
                $this->logger->logMessage(
                    $alertaId, $resCliente['message_sid'], 'outbound',
                    $this->config->getWhatsappFrom(), "whatsapp:+52{$telefonoLimpio}",
                    $mensajeEscalada, 'text', 'escalada_atencion_personalizada',
                    $resCliente['twilio_response'] ?? null
                );
            }

            $this->alertRepo->updateEstadoWhatsApp($alertaId, 'requiere_contacto');
            $this->alertRepo->markRequiereAtencion($alertaId, 'alta');

            // Notificar al admin
            $this->notificarAdminAtencionPersonalizada($alertaId, $alerta, $textoCliente);

            return [
                'success'     => true,
                'accion'      => 'escalada_admin',
                'message_sid' => $resCliente['message_sid'] ?? null,
            ];

        } catch (Exception $e) {
            $this->logger->logError("Error en manejarRespuestaInvalida", $e, ['alerta_id' => $alertaId]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Notificar al admin de SAG que un cliente envió un mensaje fuera del flujo y requiere atención.
     * Usa template sag_respuesta_invalida_seguimiento (HXff262c7f59e1257ab4b4e8e666375255):
     * {{1}}=nombre, {{2}}=teléfono, {{3}}=servicio, {{4}}=mensaje del cliente
     */
    private function notificarAdminAtencionPersonalizada(int $alertaId, array $alerta, string $mensajeCliente): void
    {
        try {
            $adminPhone  = $this->config->getConfig('sag_admin_phone');
            $templateSid = $this->config->getConfig('template_atencion_personalizada_sid');
            if (empty($adminPhone) || empty($templateSid)) {
                $this->logger->logConversationFlow($alertaId, 'notificar_admin_atencion', 'config_faltante');
                return;
            }

            $tipoServicio = is_array($alerta['servicios_que_dispararon'])
                ? implode(', ', $alerta['servicios_que_dispararon'])
                : ($alerta['servicios_que_dispararon'] ?? 'Servicio general');

            $telefonoCliente = $this->phoneValidator->cleanPhoneNumber($alerta['cliente_telefono'])
                ?? $alerta['cliente_telefono'];

            $resultado = $this->messageSender->sendTemplateMessage(
                $adminPhone,
                $templateSid,
                [
                    '1' => $alerta['cliente_nombre'],
                    '2' => $telefonoCliente,
                    '3' => $tipoServicio,
                    '4' => $mensajeCliente ?: '(sin texto)',
                ]
            );

            if ($resultado['success']) {
                // Construir el body resuelto con las variables que ya están en scope.
                // Opción C: se construye aquí mismo, sin llamada a reconstruirTextoTemplate.
                $mensajeClienteSafe = $mensajeCliente ?: '(sin texto)';
                $textoAtencion = "🔔 Cliente requiere atención personalizada\n\n"
                    . "👤 Nombre: {$alerta['cliente_nombre']}\n"
                    . "📱 Teléfono: {$telefonoCliente}\n"
                    . "🔧 Servicio: {$tipoServicio}\n"
                    . "💬 Mensaje: \"{$mensajeClienteSafe}\"\n\n"
                    . "El bot no pudo manejar su respuesta. Por favor contáctalo directamente.";
                $this->logger->logMessage(
                    $alertaId, $resultado['message_sid'], 'outbound',
                    $this->config->getWhatsappFrom(), "whatsapp:{$adminPhone}",
                    $textoAtencion,
                    'template', 'notificacion_admin_atencion_personalizada',
                    $resultado['twilio_response'] ?? null
                );
            }
        } catch (Exception $e) {
            $this->logger->logError("Error notificando admin atención personalizada", $e, ['alerta_id' => $alertaId]);
        }
    }

    /**
     * Cliente eligió "Otro horario": confirmar al cliente y notificar al admin para coordinar.
     */
    private function procesarOtroHorario(int $alertaId, array $alerta, string $telefonoLimpio): array
    {
        try {
            // 1. Confirmar al cliente
            $mensajeCliente = $this->config->getMessage('mensaje_contacto_directo');
            $resultado = $this->messageSender->sendTextMessage($telefonoLimpio, $mensajeCliente);

            if ($resultado['success']) {
                $this->logger->logMessage(
                    $alertaId, $resultado['message_sid'], 'outbound',
                    $this->config->getWhatsappFrom(), "whatsapp:+52{$telefonoLimpio}",
                    $mensajeCliente, 'text', 'otro_horario_confirmacion_cliente',
                    $resultado['twilio_response'] ?? null
                );
                $this->alertRepo->updateEstadoWhatsApp($alertaId, 'requiere_contacto');
                $this->alertRepo->markRequiereAtencion($alertaId, 'alta');
            }

            // 2. Notificar al admin (no bloquea el flujo del cliente)
            $this->notificarAdminOtroHorario($alertaId, $alerta);

            return [
                'success'    => $resultado['success'] ?? false,
                'message_sid' => $resultado['message_sid'] ?? null,
                'accion'     => 'otro_horario',
            ];

        } catch (Exception $e) {
            $this->logger->logError("Error en procesarOtroHorario", $e, ['alerta_id' => $alertaId]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Notificar al admin que un cliente solicitó coordinar horario directamente.
     * Usa template sag_otro_horario_cita (HX8426371cf12251f300d04c2884f869f0):
     * {{1}}=nombre, {{2}}=teléfono, {{3}}=vehículo, {{4}}=servicio
     */
    private function notificarAdminOtroHorario(int $alertaId, array $alerta): void
    {
        try {
            $adminPhone  = $this->config->getConfig('sag_admin_phone');
            $templateSid = $this->config->getConfig('template_otro_horario_sid');
            if (empty($adminPhone) || empty($templateSid)) {
                return;
            }

            $tipoServicio = is_array($alerta['servicios_que_dispararon'])
                ? implode(', ', $alerta['servicios_que_dispararon'])
                : ($alerta['servicios_que_dispararon'] ?? 'Servicio general');

            $telefonoCliente = $this->phoneValidator->cleanPhoneNumber($alerta['cliente_telefono'])
                ?? $alerta['cliente_telefono'];

            $resultado = $this->messageSender->sendTemplateMessage(
                $adminPhone,
                $templateSid,
                [
                    '1' => $alerta['cliente_nombre'],
                    '2' => $telefonoCliente,
                    '3' => $alerta['vehiculo_info'] ?? 'Sin vehículo',
                    '4' => $tipoServicio,
                ]
            );

            if ($resultado['success']) {
                // Construir el body resuelto con las variables que ya están en scope.
                // Opción C: se construye aquí mismo, sin llamada a reconstruirTextoTemplate.
                $vehiculoInfo = $alerta['vehiculo_info'] ?? 'Sin vehículo';
                $textoOtroHorario = "🟡 CONTACTO DIRECTO SOLICITADO\n\n"
                    . "Cliente: {$alerta['cliente_nombre']}\n"
                    . "Teléfono: {$telefonoCliente}\n"
                    . "Vehículo: {$vehiculoInfo}\n"
                    . "Servicio: {$tipoServicio}\n\n"
                    . "El cliente prefiere coordinar horario directamente.";
                $this->logger->logMessage(
                    $alertaId, $resultado['message_sid'], 'outbound',
                    $this->config->getWhatsappFrom(), "whatsapp:{$adminPhone}",
                    $textoOtroHorario,
                    'template', 'notificacion_admin_otro_horario',
                    $resultado['twilio_response'] ?? null
                );
            }
        } catch (Exception $e) {
            $this->logger->logError("Error notificando admin otro horario", $e, ['alerta_id' => $alertaId]);
        }
    }

    /**
     * Notificar al admin de CarFix que hay una nueva cita pre-agendada
     * Los errores aquí no bloquean el flujo del cliente.
     */
    private function notificarAdminPreAgenda(int $alertaId, array $alerta, array $slot, string $tipoServicio): void
    {
        try {
            $adminPhone = $this->config->getConfig('sag_admin_phone');
            $templateSid = $this->config->getConfig('sag_confirma_cita');
            if (empty($adminPhone) || empty($templateSid)) {
                return;
            }

            $resultado = $this->messageSender->sendTemplateMessage(
                $adminPhone,
                $templateSid,
                [
                    '1' => $alerta['cliente_nombre'],
                    '2' => $slot['fecha_display'],
                    '3' => $slot['hora_display'],
                    '4' => $alerta['vehiculo_info'] ?? 'Sin vehículo',
                    '5' => $tipoServicio,
                    '6' => $alerta['cliente_telefono'],
                ]
            );

            if ($resultado['success']) {
                // Construir el body resuelto con las variables que ya están en scope.
                // Opción C: se construye aquí mismo, sin llamada a reconstruirTextoTemplate.
                $vehiculoInfo = $alerta['vehiculo_info'] ?? 'Sin vehículo';
                $textoPreAgenda = "🟢 CITA PREVIA NUEVA\n\n"
                    . "Cliente: {$alerta['cliente_nombre']}\n"
                    . "Fecha: {$slot['fecha_display']} {$slot['hora_display']}\n"
                    . "Vehículo: {$vehiculoInfo}\n"
                    . "Servicio: {$tipoServicio}\n"
                    . "Teléfono: {$alerta['cliente_telefono']}\n\n"
                    . "⚠️ Es necesario confirmar manualmente y de forma directa con el cliente";
                $this->logger->logMessage(
                    $alertaId, $resultado['message_sid'], 'outbound',
                    $this->config->getWhatsappFrom(), "whatsapp:{$adminPhone}",
                    $textoPreAgenda,
                    'template', 'notificacion_admin_pre_agenda', $resultado['twilio_response'] ?? null
                );
            }
        } catch (Exception $e) {
            $this->logger->logError("Error notificando admin pre-agenda", $e, ['alerta_id' => $alertaId]);
        }
    }

    /**
     * PASO 4: Procesar confirmación del admin de CarFix
     *
     * El admin responde vía WhatsApp con "confirmar", "cancelar" o "reprogramar".
     * Actualiza la cita, libera el slot si es cancelación, y notifica al cliente.
     *
     * @param int         $alertaId
     * @param string      $body       Texto libre del admin
     * @param string      $messageSid
     * @param int|null    $userId
     * @return array
     */
    public function procesarConfirmacionSAG(int $alertaId, string $body, string $messageSid, ?int $userId = null): array
    {
        try {
            $this->logger->logConversationFlow($alertaId, 'procesar_confirmacion_sag', 'iniciado', [
                'body' => $body,
            ]);

            $alerta = $this->alertRepo->getAlertaById($alertaId);
            if (!$alerta) {
                return ['success' => false, 'error' => "Alerta {$alertaId} no encontrada"];
            }

            // Detectar intención del admin
            $bodyNorm = mb_strtolower(trim($body));
            if (preg_match('/\b(confirm|sí|si|ok|listo|acept)/u', $bodyNorm)) {
                $accion = 'confirmado';
            } elseif (preg_match('/\b(cancel|no\b|rechaz)/u', $bodyNorm)) {
                $accion = 'cancelado';
            } else {
                // "reprogramar", "cambiar", "otro", o cualquier texto ambiguo
                $accion = 'requiere_contacto';
            }

            $this->logger->logConversationFlow($alertaId, 'procesar_confirmacion_sag', "accion_{$accion}");

            // Log del mensaje entrante del admin
            $adminPhone = $this->config->getConfig('sag_admin_phone') ?? '';
            $this->logger->logMessage(
                $alertaId, $messageSid, 'inbound',
                "whatsapp:{$adminPhone}", $this->config->getWhatsappFrom(),
                $body, 'text', 'confirmacion_admin'
            );

            // Actualizar citas_pre_agendadas (y liberar slot si cancelado)
            $this->alertRepo->actualizarEstadoCita($alertaId, $accion);

            // Actualizar alertas_servicio
            $this->alertRepo->updateConfirmacionSAG($alertaId, $accion, $userId);
            $this->alertRepo->updateEstadoWhatsApp($alertaId, $accion);

            // Preparar mensaje para el cliente
            $telefonoLimpio = $this->phoneValidator->cleanPhoneNumber($alerta['cliente_telefono']);
            $fechaFormato   = !empty($alerta['fecha_cita'])
                ? date('d/m/Y', strtotime($alerta['fecha_cita']))
                : '';
            $horaFormato    = !empty($alerta['hora_cita'])
                ? date('g:i A', strtotime($alerta['hora_cita']))
                : '';

            if ($accion === 'confirmado') {
                $msgCliente = $this->config->getConfig('mensaje_cita_confirmada')
                    ?? "¡Excelente noticia! 🎉 Tu cita está *confirmada* para el {$fechaFormato} a las {$horaFormato}.\n\n¡Te esperamos en CarFix! 🚗🔧";
                $msgCliente = str_replace(['{{fecha}}', '{{hora}}'], [$fechaFormato, $horaFormato], $msgCliente);
                $step = 'cita_confirmada';
            } elseif ($accion === 'cancelado') {
                $sagTelefono = $this->config->getConfig('sag_telefono') ?? '';
                $msgCliente  = $this->config->getConfig('mensaje_cita_cancelada')
                    ?? "Lamentamos informarte que tu cita fue cancelada. 😔\n\nSi deseas reprogramar, contáctanos al {$sagTelefono} o escríbenos aquí. ¡Quedamos a tus órdenes!";
                $msgCliente  = str_replace('{{telefono}}', $sagTelefono, $msgCliente);
                $step = 'cita_cancelada';
            } else {
                $msgCliente = $this->config->getConfig('mensaje_requiere_contacto')
                    ?? "Gracias por tu paciencia. 🙏 En breve uno de nuestros asesores se pondrá en contacto contigo para coordinar tu cita.";
                $step = 'requiere_contacto';
            }

            $resCliente = $this->messageSender->sendTextMessage($telefonoLimpio, $msgCliente);
            if ($resCliente['success']) {
                $this->logger->logMessage(
                    $alertaId, $resCliente['message_sid'], 'outbound',
                    $this->config->getWhatsappFrom(), "whatsapp:+52{$telefonoLimpio}",
                    $msgCliente, 'text', $step, $resCliente['twilio_response'] ?? null
                );
            }

            $this->logger->logConversationFlow($alertaId, 'procesar_confirmacion_sag', 'completado', [
                'accion' => $accion,
            ]);

            return [
                'success' => true,
                'accion'  => $accion,
            ];

        } catch (Exception $e) {
            $this->logger->logError("Error en procesarConfirmacionSAG", $e, ['alerta_id' => $alertaId]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============================================================================
    // UTILIDAD INTERNA — Reconstrucción de texto de templates para logging
    // ============================================================================

    /**
     * @deprecated Opción C (2026-04-30): el body resuelto ahora se construye en el
     * momento exacto del envío, dentro de cada método que llama a sendTemplateMessage(),
     * usando las variables que ya están en scope. Este método ya no se invoca y retorna
     * null siempre para no romper ningún caller potencial.
     *
     * @param string $tipoTemplate
     * @param array  $variables
     * @return null
     */
    private function reconstruirTextoTemplate(string $tipoTemplate, array $variables): ?string
    {
        return null;
    }

    /**
     * Obtener estado del servicio refactorizado
     *
     * @return array Estado detallado
     */
    public function getServiceStatus(): array
    {
        return [
            'version' => '2.0.0 - Refactorizado',
            'services_ready' => $this->areServicesReady(),
            'message_sender' => $this->messageSender->getServiceStatus(),
            'client_manager' => $this->clientManager->getClientStatus(),
            'configuration' => $this->config->getAllConfigurations(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}