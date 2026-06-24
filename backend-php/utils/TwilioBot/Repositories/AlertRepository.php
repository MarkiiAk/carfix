<?php
/**
 * AlertRepository - Repositorio para acceso a datos de alertas
 * 
 * Esta clase encapsula todo el acceso a datos relacionado con alertas de servicio,
 * proporcionando una interfaz limpia y consistente para las operaciones CRUD
 * y consultas específicas del dominio de alertas.
 * 
 * Características:
 * - Operaciones CRUD completas para alertas
 * - Consultas optimizadas para flujos de WhatsApp
 * - Manejo robusto de errores de base de datos
 * - Logging detallado de operaciones
 * - Transacciones para operaciones complejas
 * - Caché de consultas frecuentes
 * 
 * @author Sistema CarFix - Refactorización 2026
 * @version 1.0.0
 */

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../Core/MessageLogger.php';

class AlertRepository 
{
    /**
     * Conexión a la base de datos
     * @var PDO
     */
    private $db;
    
    /**
     * Logger para operaciones
     * @var MessageLogger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() 
    {
        $this->initializeDatabase();
        $this->logger = MessageLogger::getInstance();
        
        $this->logger->logOperation("AlertRepository inicializado", [
            'db_connected' => $this->db !== null
        ]);
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
            error_log("❌ AlertRepository: Error conectando a BD: " . $e->getMessage());
            $this->db = null;
        }
    }
    
    /**
     * Obtener datos completos de una alerta por ID
     * 
     * @param int $alertaId ID de la alerta
     * @return array|null Datos de la alerta o null si no se encuentra
     */
    public function getAlertaById(int $alertaId): ?array 
    {
        try {
            $this->logger->logOperation("Obteniendo alerta por ID", [
                'alerta_id' => $alertaId
            ]);
            
            if (!$this->db) {
                throw new Exception("Base de datos no disponible");
            }
            
            $sql = "SELECT 
                        a.*,
                        c.nombre as cliente_nombre,
                        c.telefono as cliente_telefono,
                        c.email as cliente_email,
                        v.marca,
                        v.modelo,
                        v.anio,
                        v.placas,
                        CONCAT(
                            COALESCE(v.marca, 'Sin marca'), ' ',
                            COALESCE(v.modelo, 'Sin modelo'), ' ',
                            COALESCE(v.anio, 'Sin año')
                        ) as vehiculo_info,
                        DATEDIFF(NOW(), a.fecha_ultimo_servicio) as dias_exactos_desde_servicio
                    FROM alertas_servicio a
                    INNER JOIN clientes c ON a.cliente_id = c.id
                    INNER JOIN vehiculos v ON a.vehiculo_id = v.id
                    WHERE a.id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$alertaId]);
            $alerta = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($alerta) {
                // Decodificar JSON fields
                $alerta['servicios_que_dispararon'] = json_decode($alerta['servicios_que_dispararon'], true) ?: [];
                $alerta['todos_los_servicios'] = json_decode($alerta['todos_los_servicios'], true) ?: [];
                
                $this->logger->logOperation("Alerta encontrada", [
                    'alerta_id' => $alertaId,
                    'cliente' => $alerta['cliente_nombre'],
                    'estado_whatsapp' => $alerta['estado_whatsapp'] ?? 'sin_estado'
                ]);
            } else {
                $this->logger->logOperation("Alerta no encontrada", [
                    'alerta_id' => $alertaId
                ]);
            }
            
            return $alerta ?: null;
            
        } catch (Exception $e) {
            $this->logger->logError("Error obteniendo alerta por ID", $e, [
                'alerta_id' => $alertaId
            ]);
            return null;
        }
    }
    
    /**
     * Actualizar estado de WhatsApp de una alerta
     * 
     * @param int $alertaId ID de la alerta
     * @param string $estado Nuevo estado
     * @param string|null $messageSid SID del mensaje de Twilio
     * @return bool True si se actualizó correctamente
     */
    public function updateEstadoWhatsApp(int $alertaId, string $estado, ?string $messageSid = null): bool 
    {
        try {
            $this->logger->logOperation("Actualizando estado WhatsApp", [
                'alerta_id' => $alertaId,
                'nuevo_estado' => $estado,
                'message_sid' => $messageSid ?? 'null'
            ]);
            
            if (!$this->db) {
                throw new Exception("Base de datos no disponible");
            }
            
            $sql = "UPDATE alertas_servicio
                    SET estado_whatsapp = ?,
                        intentos_invalidos = 0,
                        fecha_envio_whatsapp = CASE WHEN ? IS NOT NULL THEN NOW() ELSE fecha_envio_whatsapp END,
                        twilio_conversation_sid = COALESCE(?, twilio_conversation_sid),
                        ultima_actividad = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$estado, $messageSid, $messageSid, $alertaId]);
            
            $filasAfectadas = $stmt->rowCount();
            
            if ($result && $filasAfectadas > 0) {
                $this->logger->logOperation("Estado WhatsApp actualizado exitosamente", [
                    'alerta_id' => $alertaId,
                    'estado' => $estado,
                    'filas_afectadas' => $filasAfectadas
                ]);
                return true;
            } else {
                $this->logger->logOperation("No se actualizó ningún registro", [
                    'alerta_id' => $alertaId,
                    'estado' => $estado,
                    'sql_result' => $result,
                    'filas_afectadas' => $filasAfectadas
                ]);
                return false;
            }
            
        } catch (Exception $e) {
            $this->logger->logError("Error actualizando estado WhatsApp", $e, [
                'alerta_id' => $alertaId,
                'estado' => $estado
            ]);
            return false;
        }
    }
    
    /**
     * Actualizar respuesta inicial del cliente
     * 
     * @param int $alertaId ID de la alerta
     * @param string $respuesta Respuesta del cliente ('si' o 'no')
     * @return bool True si se actualizó correctamente
     */
    public function updateRespuestaInicial(int $alertaId, string $respuesta): bool 
    {
        try {
            $this->logger->logOperation("Actualizando respuesta inicial", [
                'alerta_id' => $alertaId,
                'respuesta' => $respuesta
            ]);
            
            if (!$this->db) {
                throw new Exception("Base de datos no disponible");
            }
            
            $sql = "UPDATE alertas_servicio 
                    SET respuesta_inicial = ?, 
                        fecha_respuesta_inicial = NOW(),
                        ultima_actividad = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$respuesta, $alertaId]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logger->logOperation("Respuesta inicial actualizada", [
                    'alerta_id' => $alertaId,
                    'respuesta' => $respuesta
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->logError("Error actualizando respuesta inicial", $e, [
                'alerta_id' => $alertaId,
                'respuesta' => $respuesta
            ]);
            return false;
        }
    }
    
    /**
     * Actualizar fecha y hora de cita seleccionada
     * 
     * @param int $alertaId ID de la alerta
     * @param string $fecha Fecha de la cita (Y-m-d)
     * @param string $hora Hora de la cita (H:i)
     * @return bool True si se actualizó correctamente
     */
    public function updateFechaCita(int $alertaId, string $fecha, string $hora): bool 
    {
        try {
            $this->logger->logOperation("Actualizando fecha de cita", [
                'alerta_id' => $alertaId,
                'fecha' => $fecha,
                'hora' => $hora
            ]);
            
            if (!$this->db) {
                throw new Exception("Base de datos no disponible");
            }
            
            $sql = "UPDATE alertas_servicio 
                    SET fecha_cita_seleccionada = ?, 
                        hora_cita_seleccionada = ?,
                        fecha_pre_agendado = NOW(),
                        ultima_actividad = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$fecha, $hora, $alertaId]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logger->logOperation("Fecha de cita actualizada", [
                    'alerta_id' => $alertaId,
                    'fecha' => $fecha,
                    'hora' => $hora
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->logError("Error actualizando fecha de cita", $e, [
                'alerta_id' => $alertaId,
                'fecha' => $fecha,
                'hora' => $hora
            ]);
            return false;
        }
    }
    
    /**
     * Marcar alerta como requiere atención
     * 
     * @param int $alertaId ID de la alerta
     * @param string $prioridad Nivel de prioridad ('alta', 'media', 'baja')
     * @param bool $requiere Si requiere atención o no
     * @return bool True si se actualizó correctamente
     */
    public function markRequiereAtencion(int $alertaId, string $prioridad, bool $requiere = true): bool 
    {
        try {
            $this->logger->logOperation("Marcando requiere atención", [
                'alerta_id' => $alertaId,
                'prioridad' => $prioridad,
                'requiere' => $requiere
            ]);
            
            if (!$this->db) {
                throw new Exception("Base de datos no disponible");
            }
            
            $sql = "UPDATE alertas_servicio 
                    SET requiere_atencion = ?, 
                        prioridad = ?,
                        ultima_actividad = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$requiere ? 1 : 0, $prioridad, $alertaId]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logger->logOperation("Atención marcada correctamente", [
                    'alerta_id' => $alertaId,
                    'prioridad' => $prioridad,
                    'requiere' => $requiere
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->logError("Error marcando requiere atención", $e, [
                'alerta_id' => $alertaId,
                'prioridad' => $prioridad
            ]);
            return false;
        }
    }
    
    /**
     * Actualizar confirmación de SAG
     * 
     * @param int $alertaId ID de la alerta
     * @param string $confirmacion Tipo de confirmación ('confirmado', 'cancelado', 'reprogramado')
     * @param int|null $userId ID del usuario que confirmó
     * @return bool True si se actualizó correctamente
     */
    public function updateConfirmacionSAG(int $alertaId, string $confirmacion, ?int $userId = null): bool 
    {
        try {
            $this->logger->logOperation("Actualizando confirmación SAG", [
                'alerta_id' => $alertaId,
                'confirmacion' => $confirmacion,
                'user_id' => $userId
            ]);
            
            if (!$this->db) {
                throw new Exception("Base de datos no disponible");
            }
            
            $sql = "UPDATE alertas_servicio 
                    SET confirmacion_sag = ?, 
                        fecha_confirmacion_sag = NOW(),
                        usuario_confirmo_sag = ?,
                        ultima_actividad = NOW()
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$confirmacion, $userId, $alertaId]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->logger->logOperation("Confirmación SAG actualizada", [
                    'alerta_id' => $alertaId,
                    'confirmacion' => $confirmacion,
                    'user_id' => $userId
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->logger->logError("Error actualizando confirmación SAG", $e, [
                'alerta_id' => $alertaId,
                'confirmacion' => $confirmacion
            ]);
            return false;
        }
    }
    
    /**
     * Actualizar estado de citas_pre_agendadas cuando el admin confirma o cancela.
     * Si cancela, libera el slot en calendario_disponibilidad.
     *
     * @param int $alertaId
     * @param string $accion  'confirmado' | 'cancelado' | 'requiere_contacto'
     * @return bool
     */
    public function actualizarEstadoCita(int $alertaId, string $accion): bool
    {
        try {
            if (!$this->db) {
                return false;
            }

            if ($accion === 'confirmado') {
                $stmt = $this->db->prepare(
                    "UPDATE citas_pre_agendadas
                     SET estado = 'confirmada', fecha_confirmacion = NOW()
                     WHERE alerta_id = ? AND estado = 'pre_agendada'"
                );
                return $stmt->execute([$alertaId]);
            }

            if ($accion === 'cancelado') {
                // Obtener el slot para poder liberarlo
                $stmtGet = $this->db->prepare(
                    "SELECT calendario_slot_id FROM citas_pre_agendadas
                     WHERE alerta_id = ? AND estado = 'pre_agendada' LIMIT 1"
                );
                $stmtGet->execute([$alertaId]);
                $cita = $stmtGet->fetch(PDO::FETCH_ASSOC);

                $stmtCancel = $this->db->prepare(
                    "UPDATE citas_pre_agendadas
                     SET estado = 'cancelada', fecha_cancelacion = NOW()
                     WHERE alerta_id = ? AND estado = 'pre_agendada'"
                );
                $stmtCancel->execute([$alertaId]);

                // Liberar el slot — GREATEST(0,...) evita valores negativos
                if ($cita) {
                    $stmtSlot = $this->db->prepare(
                        "UPDATE calendario_disponibilidad
                         SET citas_ocupadas = GREATEST(0, citas_ocupadas - 1),
                             esta_disponible = TRUE
                         WHERE id = ?"
                    );
                    $stmtSlot->execute([$cita['calendario_slot_id']]);
                }

                return true;
            }

            // 'requiere_contacto' — el admin gestiona manualmente, no cambia la cita
            return true;

        } catch (Exception $e) {
            $this->logger->logError("Error actualizando estado de cita", $e, ['alerta_id' => $alertaId]);
            return false;
        }
    }

    /**
     * Obtener alertas pendientes para envío
     * 
     * @param int $limit Límite de registros
     * @return array Lista de alertas pendientes
     */
    public function getAlertasPendientesEnvio(int $limit = 50): array 
    {
        try {
            $this->logger->logOperation("Obteniendo alertas pendientes para envío", [
                'limit' => $limit
            ]);
            
            if (!$this->db) {
                return [];
            }
            
            $sql = "SELECT a.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono
                    FROM alertas_servicio a
                    INNER JOIN clientes c ON a.cliente_id = c.id
                    WHERE a.estado_whatsapp = 'borrador' 
                       OR a.estado_whatsapp IS NULL
                    ORDER BY a.fecha_generada ASC
                    LIMIT ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit]);
            $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->logger->logOperation("Alertas pendientes obtenidas", [
                'count' => count($alertas)
            ]);
            
            return $alertas;
            
        } catch (Exception $e) {
            $this->logger->logError("Error obteniendo alertas pendientes", $e);
            return [];
        }
    }
    
    /**
     * Obtener alertas que requieren atención
     * 
     * @param string|null $prioridad Filtrar por prioridad específica
     * @return array Lista de alertas que requieren atención
     */
    public function getAlertasRequierenAtencion(?string $prioridad = null): array 
    {
        try {
            $this->logger->logOperation("Obteniendo alertas que requieren atención", [
                'prioridad_filtro' => $prioridad
            ]);
            
            if (!$this->db) {
                return [];
            }
            
            $sql = "SELECT a.*, c.nombre as cliente_nombre, c.telefono as cliente_telefono,
                           v.marca, v.modelo, v.anio
                    FROM alertas_servicio a
                    INNER JOIN clientes c ON a.cliente_id = c.id
                    INNER JOIN vehiculos v ON a.vehiculo_id = v.id
                    WHERE a.requiere_atencion = TRUE";
            
            $params = [];
            
            if ($prioridad) {
                $sql .= " AND a.prioridad = ?";
                $params[] = $prioridad;
            }
            
            $sql .= " ORDER BY 
                        CASE a.prioridad 
                            WHEN 'alta' THEN 1 
                            WHEN 'media' THEN 2 
                            WHEN 'baja' THEN 3 
                            ELSE 4 
                        END,
                        a.ultima_actividad DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->logger->logOperation("Alertas que requieren atención obtenidas", [
                'count' => count($alertas),
                'prioridad_filtro' => $prioridad
            ]);
            
            return $alertas;
            
        } catch (Exception $e) {
            $this->logger->logError("Error obteniendo alertas que requieren atención", $e);
            return [];
        }
    }
    
    /**
     * Verificar si una alerta existe y está en estado válido para procesar
     * 
     * @param int $alertaId ID de la alerta
     * @param array $estadosValidos Estados válidos para procesar
     * @return bool True si existe y está en estado válido
     */
    public function isAlertaValidaParaProcesar(int $alertaId, array $estadosValidos = ['borrador', 'enviado', 'esperando_respuesta']): bool 
    {
        try {
            if (!$this->db) {
                return false;
            }
            
            $placeholders = str_repeat('?,', count($estadosValidos) - 1) . '?';
            $sql = "SELECT COUNT(*) FROM alertas_servicio 
                    WHERE id = ? AND estado_whatsapp IN ({$placeholders})";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge([$alertaId], $estadosValidos));
            
            return $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            $this->logger->logError("Error verificando si alerta es válida", $e, [
                'alerta_id' => $alertaId
            ]);
            return false;
        }
    }
    
    /**
     * Obtener slots disponibles reales desde calendario_disponibilidad
     *
     * @param int $maxSlots Máximo de slots a retornar
     * @param array $diasFestivos Fechas a excluir en formato Y-m-d
     * @param int $diasMinimo Días mínimos de anticipación
     * @return array Slots indexados con numero, slot_id, calendario_id, fecha, hora, fecha_display, hora_display
     */
    public function getHorariosDisponibles(int $maxSlots = 8, array $diasFestivos = [], int $diasMinimo = 1): array
    {
        try {
            if (!$this->db) {
                return [];
            }

            // Auto-poblar si no hay suficientes slots futuros
            $this->autoPopularCalendario($diasFestivos, $diasMinimo, $maxSlots);

            $sql = "SELECT id, fecha, hora
                    FROM calendario_disponibilidad
                    WHERE esta_disponible = TRUE
                      AND es_dia_laborable = TRUE
                      AND fecha >= DATE_ADD(CURDATE(), INTERVAL :dias_minimo DAY)
                      AND citas_ocupadas < capacidad_total";

            $params = [':dias_minimo' => $diasMinimo];

            if (!empty($diasFestivos)) {
                $placeholders = implode(',', array_map(fn($i) => ":festivo{$i}", array_keys($diasFestivos)));
                $sql .= " AND fecha NOT IN ({$placeholders})";
                foreach ($diasFestivos as $i => $fecha) {
                    $params[":festivo{$i}"] = $fecha;
                }
            }

            $sql .= " ORDER BY fecha ASC, hora ASC LIMIT :max_slots";
            $params[':max_slots'] = $maxSlots;

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($key, $value, $type);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $diasES  = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            $mesesES = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

            $horarios = [];
            foreach ($rows as $i => $row) {
                $fechaObj = new DateTime($row['fecha']);
                $horaObj  = new DateTime('2000-01-01 ' . $row['hora']);

                $fechaDisplay = $diasES[(int)$fechaObj->format('w')] . ' ' .
                                $fechaObj->format('j') . ' ' .
                                $mesesES[(int)$fechaObj->format('n')];

                $horarios[] = [
                    'numero'        => $i + 1,
                    'slot_id'       => 'slot_' . ($i + 1),
                    'calendario_id' => (int)$row['id'],
                    'fecha'         => $row['fecha'],
                    'hora'          => substr($row['hora'], 0, 5),
                    'fecha_display' => $fechaDisplay,
                    'hora_display'  => $horaObj->format('g:i A'),
                ];
            }

            return $horarios;

        } catch (Exception $e) {
            $this->logger->logError("Error obteniendo horarios disponibles", $e);
            return [];
        }
    }

    /**
     * Auto-poblar calendario_disponibilidad con los próximos 60 días hábiles.
     * Se ejecuta solo cuando hay menos de $minSlots slots futuros disponibles.
     * Así el calendario nunca se queda vacío sin intervención manual.
     */
    private function autoPopularCalendario(array $diasFestivos = [], int $diasMinimo = 1, int $minSlots = 8): void
    {
        try {
            // Verificar cuántos slots futuros existen
            $stmtCheck = $this->db->prepare(
                "SELECT COUNT(*) FROM calendario_disponibilidad
                 WHERE esta_disponible = TRUE
                   AND es_dia_laborable = TRUE
                   AND fecha >= DATE_ADD(CURDATE(), INTERVAL :dias_minimo DAY)
                   AND citas_ocupadas < capacidad_total"
            );
            $stmtCheck->execute([':dias_minimo' => $diasMinimo]);
            $disponibles = (int)$stmtCheck->fetchColumn();

            if ($disponibles >= $minSlots) {
                return; // Ya hay suficientes slots, no hacer nada
            }

            // Leer horarios desde config (default: 10:00, 12:00, 14:00, 16:00)
            $horariosRaw = '10:00,12:00,14:00,16:00';
            try {
                $stmtCfg = $this->db->prepare(
                    "SELECT config_value FROM twilio_config WHERE config_key = 'horarios_atencion' AND is_active = 1"
                );
                $stmtCfg->execute();
                $val = $stmtCfg->fetchColumn();
                if ($val) {
                    $horariosRaw = $val;
                }
            } catch (Exception $e) {
                // usar default
            }

            $horarios    = array_map('trim', explode(',', $horariosRaw));
            $festivosSet = array_flip($diasFestivos);

            $stmtInsert = $this->db->prepare(
                "INSERT IGNORE INTO calendario_disponibilidad
                 (fecha, hora, capacidad_total, citas_ocupadas, esta_disponible, es_dia_laborable)
                 VALUES (?, ?, 1, 0, 1, 1)"
            );

            $fecha    = new DateTime('tomorrow');
            $diasGen  = 0;
            $maxDias  = 90; // nunca iterar más de 90 días de calendario
            $contador = 0;

            while ($diasGen < 60 && $contador < $maxDias) {
                $contador++;
                $dowNum     = (int)$fecha->format('N'); // 1=Lun … 7=Dom
                $fechaStr   = $fecha->format('Y-m-d');
                $esFestivo  = isset($festivosSet[$fechaStr]);
                $esLaboral  = ($dowNum >= 1 && $dowNum <= 5);

                if ($esLaboral && !$esFestivo) {
                    foreach ($horarios as $hora) {
                        $stmtInsert->execute([$fechaStr, $hora . ':00']);
                    }
                    $diasGen++;
                }

                $fecha->modify('+1 day');
            }

            $this->logger->logInfo("Calendario auto-poblado", [
                'dias_generados' => $diasGen,
                'horarios'       => $horarios,
            ]);

        } catch (Exception $e) {
            // Fallo silencioso — el sistema intentará devolver slots vacíos
            $this->logger->logError("Error en autoPopularCalendario", $e);
        }
    }

    /**
     * Guardar los slots ofrecidos al cliente en alertas_servicio
     *
     * @param int $alertaId
     * @param array $slots
     * @return bool
     */
    public function guardarSlotsOfrecidos(int $alertaId, array $slots): bool
    {
        try {
            if (!$this->db) {
                return false;
            }

            $stmt = $this->db->prepare(
                "UPDATE alertas_servicio SET slots_ofrecidos_json = ?, ultima_actividad = NOW() WHERE id = ?"
            );
            return $stmt->execute([json_encode($slots, JSON_UNESCAPED_UNICODE), $alertaId]);

        } catch (Exception $e) {
            $this->logger->logError("Error guardando slots ofrecidos", $e, ['alerta_id' => $alertaId]);
            return false;
        }
    }

    /**
     * Leer los slots ofrecidos guardados para una alerta
     *
     * @param int $alertaId
     * @return array|null Array de slots o null si no hay
     */
    public function getSlotsOfrecidos(int $alertaId): ?array
    {
        try {
            if (!$this->db) {
                return null;
            }

            $stmt = $this->db->prepare(
                "SELECT slots_ofrecidos_json FROM alertas_servicio WHERE id = ?"
            );
            $stmt->execute([$alertaId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row || empty($row['slots_ofrecidos_json'])) {
                return null;
            }

            return json_decode($row['slots_ofrecidos_json'], true) ?: null;

        } catch (Exception $e) {
            $this->logger->logError("Error leyendo slots ofrecidos", $e, ['alerta_id' => $alertaId]);
            return null;
        }
    }

    /**
     * Crear pre-agendamiento: incrementa citas_ocupadas en calendario y registra en citas_pre_agendadas.
     * Usa compare-and-swap en el UPDATE para detectar slots ocupados sin transacción
     * (tablas MyISAM no soportan BEGIN/COMMIT).
     *
     * @param int $alertaId
     * @param array $slot  Slot con calendario_id, fecha, hora, fecha_display, hora_display
     * @param array $clienteData  cliente_nombre, cliente_telefono, vehiculo_info, tipo_servicio
     * @return bool False si el slot ya no tiene capacidad
     */
    public function crearCitaPreAgendada(int $alertaId, array $slot, array $clienteData): bool
    {
        try {
            if (!$this->db) {
                return false;
            }

            // Intentar tomar el slot: incrementa solo si aún hay capacidad
            $stmtSlot = $this->db->prepare(
                "UPDATE calendario_disponibilidad
                 SET citas_ocupadas = citas_ocupadas + 1,
                     esta_disponible = IF(citas_ocupadas + 1 >= capacidad_total, FALSE, TRUE)
                 WHERE id = ? AND citas_ocupadas < capacidad_total"
            );
            $stmtSlot->execute([$slot['calendario_id']]);

            if ($stmtSlot->rowCount() === 0) {
                $this->logger->logOperation("Slot sin capacidad disponible", ['calendario_id' => $slot['calendario_id']]);
                return false;
            }

            // Registrar la pre-cita
            $stmtCita = $this->db->prepare(
                "INSERT INTO citas_pre_agendadas
                 (alerta_id, calendario_slot_id, cliente_nombre, cliente_telefono,
                  vehiculo_info, tipo_servicio, estado)
                 VALUES (?, ?, ?, ?, ?, ?, 'pre_agendada')"
            );
            $stmtCita->execute([
                $alertaId,
                $slot['calendario_id'],
                $clienteData['cliente_nombre'],
                $clienteData['cliente_telefono'],
                $clienteData['vehiculo_info'],
                $clienteData['tipo_servicio'],
            ]);

            return true;

        } catch (Exception $e) {
            $this->logger->logError("Error creando cita pre-agendada", $e, [
                'alerta_id'     => $alertaId,
                'calendario_id' => $slot['calendario_id'] ?? null,
            ]);
            return false;
        }
    }

    /**
     * Incrementar el contador de respuestas inválidas en el paso actual.
     * Devuelve el nuevo valor del contador.
     */
    public function incrementarIntentosInvalidos(int $alertaId): int
    {
        if (!$this->db) return 0;
        try {
            $stmt = $this->db->prepare(
                "UPDATE alertas_servicio SET intentos_invalidos = intentos_invalidos + 1, ultima_actividad = NOW() WHERE id = ?"
            );
            $stmt->execute([$alertaId]);

            $stmt2 = $this->db->prepare("SELECT intentos_invalidos FROM alertas_servicio WHERE id = ?");
            $stmt2->execute([$alertaId]);
            return (int)($stmt2->fetchColumn() ?? 0);
        } catch (Exception $e) {
            $this->logger->logError("Error incrementando intentos_invalidos", $e, ['alerta_id' => $alertaId]);
            return 0;
        }
    }

    /**
     * Resetear el contador de respuestas inválidas a 0.
     * Se llama cuando el bot escala al admin o cuando la conversación avanza de paso.
     */
    public function resetearIntentosInvalidos(int $alertaId): bool
    {
        if (!$this->db) return false;
        try {
            $stmt = $this->db->prepare(
                "UPDATE alertas_servicio SET intentos_invalidos = 0 WHERE id = ?"
            );
            return $stmt->execute([$alertaId]);
        } catch (Exception $e) {
            $this->logger->logError("Error reseteando intentos_invalidos", $e, ['alerta_id' => $alertaId]);
            return false;
        }
    }

    /**
     * Obtener estadísticas de alertas
     *
     * @return array Estadísticas detalladas
     */
    public function getEstadisticasAlertas(): array
    {
        try {
            if (!$this->db) {
                return ['error' => 'Base de datos no disponible'];
            }
            
            $sql = "SELECT 
                        COUNT(*) as total_alertas,
                        SUM(CASE WHEN estado_whatsapp = 'borrador' OR estado_whatsapp IS NULL THEN 1 ELSE 0 END) as pendientes_envio,
                        SUM(CASE WHEN estado_whatsapp = 'enviado' THEN 1 ELSE 0 END) as enviadas,
                        SUM(CASE WHEN estado_whatsapp = 'confirmado' THEN 1 ELSE 0 END) as confirmadas,
                        SUM(CASE WHEN estado_whatsapp = 'rechazado' THEN 1 ELSE 0 END) as rechazadas,
                        SUM(CASE WHEN requiere_atencion = TRUE THEN 1 ELSE 0 END) as requieren_atencion,
                        AVG(DATEDIFF(NOW(), fecha_ultimo_servicio)) as promedio_dias_servicio
                    FROM alertas_servicio";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return [
                'total' => (int)$stats['total_alertas'],
                'pendientes_envio' => (int)$stats['pendientes_envio'],
                'enviadas' => (int)$stats['enviadas'],
                'confirmadas' => (int)$stats['confirmadas'],
                'rechazadas' => (int)$stats['rechazadas'],
                'requieren_atencion' => (int)$stats['requieren_atencion'],
                'promedio_dias_servicio' => round($stats['promedio_dias_servicio'], 1)
            ];
            
        } catch (Exception $e) {
            $this->logger->logError("Error obteniendo estadísticas", $e);
            return ['error' => $e->getMessage()];
        }
    }
}