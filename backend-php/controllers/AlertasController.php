<?php
class AlertasController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Verifica si el usuario actual está autorizado para acceder a las alertas
     * CUALQUIER usuario logueado puede acceder - SIN RESTRICCIONES
     */
    private function verificarAutorizacionAlertas($userData) {
        // CUALQUIER usuario logueado puede ver las alertas - SIN RESTRICCIONES  
        return !!$userData;
    }

    // GET /alertas - Obtener todas las alertas con información completa (INTEGRADO CON WHATSAPP)
    public function obtenerAlertas($userData = null) {
        try {
            // Verificar autorización
            if (!$this->verificarAutorizacionAlertas($userData)) {
                return [
                    'success' => false,
                    'error' => 'No autorizado - Acceso denegado a las alertas'
                ];
            }
            
            // NUEVA QUERY CON CAMPOS WHATSAPP
            $query = "
                SELECT 
                    a.id,
                    a.orden_id,
                    a.fecha_ultimo_servicio,
                    a.servicios_que_dispararon,
                    a.todos_los_servicios,
                    a.estado,
                    a.fecha_generada,
                    a.fecha_marcada_leida,
                    a.dias_desde_servicio,
                    
                    -- NUEVOS CAMPOS WHATSAPP
                    a.estado_whatsapp,
                    a.fecha_envio_whatsapp,
                    a.respuesta_inicial,
                    a.fecha_cita_seleccionada,
                    a.hora_cita_seleccionada,
                    a.confirmacion_sag,
                    a.requiere_atencion,
                    a.prioridad,
                    a.ultima_actividad,
                    
                    -- Información del cliente
                    c.nombre as cliente_nombre,
                    c.telefono as cliente_telefono,
                    c.email as cliente_email,
                    
                    -- Información del vehículo
                    v.marca AS vehiculo_marca,
                    v.modelo AS vehiculo_modelo,
                    v.anio AS vehiculo_anio,
                    v.placas AS vehiculo_placas,
                    
                    -- Días exactos desde el último servicio (calculado en tiempo real)
                    DATEDIFF(NOW(), a.fecha_ultimo_servicio) as dias_exactos_desde_servicio,
                    
                    -- Estado conversacional para campanita
                    CASE 
                        WHEN a.requiere_atencion = TRUE AND a.estado_whatsapp = 'pre_agendado' THEN '🔴 CONFIRMAR CITA'
                        WHEN a.requiere_atencion = TRUE AND a.estado_whatsapp = 'requiere_contacto' THEN '🟡 CONTACTAR'
                        WHEN a.estado_whatsapp = 'rechazado' THEN '🔵 CLIENTE RECHAZÓ'
                        WHEN a.estado_whatsapp = 'confirmado' THEN '✅ CONFIRMADO'
                        WHEN a.estado_whatsapp = 'enviado' OR a.estado_whatsapp = 'esperando_respuesta' THEN '📱 ENVIADO'
                        WHEN a.estado_whatsapp = 'esperando_fecha' THEN '📅 ESPERANDO FECHA'
                        ELSE '📝 PENDIENTE ENVÍO'
                    END as estado_visual_whatsapp
                    
                FROM alertas_servicio a
                INNER JOIN clientes c ON a.cliente_id = c.id
                INNER JOIN vehiculos v ON a.vehiculo_id = v.id
                WHERE a.sucursal_id = :sucursal_id
                ORDER BY
                    -- PRIORIZAR POR ATENCIÓN REQUERIDA Y ESTADO WHATSAPP
                    CASE
                        WHEN a.requiere_atencion = TRUE AND a.estado_whatsapp = 'pre_agendado' THEN 1
                        WHEN a.requiere_atencion = TRUE AND a.estado_whatsapp = 'requiere_contacto' THEN 2
                        WHEN a.estado = 'pendiente' THEN 3
                        ELSE 4
                    END,
                    a.prioridad DESC,
                    a.ultima_actividad DESC,
                    a.fecha_generada DESC
            ";

            $sucursalId = (int) ($userData['sucursal_activa_id'] ?? 1);
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sucursal_id', $sucursalId, PDO::PARAM_INT);
            $stmt->execute();
            $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar cada alerta para formatear los datos JSON
            foreach ($alertas as &$alerta) {
                $alerta['servicios_que_dispararon'] = json_decode($alerta['servicios_que_dispararon'], true) ?? [];
                $alerta['todos_los_servicios'] = json_decode($alerta['todos_los_servicios'], true) ?? [];
                
                // Agregar información adicional de WhatsApp
                $alerta['tiene_whatsapp'] = !empty($alerta['estado_whatsapp']) && $alerta['estado_whatsapp'] !== 'borrador';
                $alerta['whatsapp_completado'] = in_array($alerta['estado_whatsapp'], ['confirmado', 'rechazado', 'completado']);
            }

            return [
                'success' => true,
                'alertas' => $alertas
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener alertas: ' . $e->getMessage()
            ];
        }
    }
    
    // PUT /alertas/{id}/marcar-leida - Marcar una alerta como leída
    public function marcarComoLeida($alertaId, $userData = null) {
        try {
            // Verificar autorización
            if (!$this->verificarAutorizacionAlertas($userData)) {
                return [
                    'success' => false,
                    'error' => 'No autorizado - Acceso denegado a las alertas'
                ];
            }

            $sucursalId = (int) ($userData['sucursal_activa_id'] ?? 1);

            // Verificar que la alerta existe, está pendiente y pertenece a esta sucursal
            $checkQuery = "SELECT id, estado FROM alertas_servicio WHERE id = ? AND sucursal_id = ?";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$alertaId, $sucursalId]);
            $alerta = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$alerta) {
                return [
                    'success' => false,
                    'error' => 'Alerta no encontrada'
                ];
            }

            if ($alerta['estado'] === 'leida') {
                return [
                    'success' => false,
                    'error' => 'La alerta ya está marcada como leída'
                ];
            }

            // Marcar como leída (filtro de sucursal incluido en WHERE)
            $updateQuery = "
                UPDATE alertas_servicio
                SET
                    estado = 'leida',
                    fecha_marcada_leida = CURRENT_TIMESTAMP,
                    usuario_marco_leida = ?
                WHERE id = ?
                  AND sucursal_id = ?
            ";

            $updateStmt = $this->db->prepare($updateQuery);
            $userId = $userData['id'] ?? $userData['user_id'] ?? null;

            // Si no podemos obtener un ID válido de usuario, usar NULL
            if (!$userId || !is_numeric($userId)) {
                $userId = null;
            }

            $updateStmt->execute([$userId, $alertaId, $sucursalId]);

            return [
                'success' => true,
                'message' => 'Alerta marcada como leída'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al marcar alerta: ' . $e->getMessage()
            ];
        }
    }

    // GET /alertas/generar - Generar nuevas alertas (proceso manual o automático)
    public function generarAlertas($userData = null) {
        try {
            // Para crons automáticos, permitir ejecución sin verificación de usuario
            // Verificar si es una ejecución automática o manual
            $esEjecucionAutomatica = (php_sapi_name() === 'cli' || !$userData);
            
            if (!$esEjecucionAutomatica && !$this->verificarAutorizacionAlertas($userData)) {
                return [
                    'success' => false,
                    'error' => 'No autorizado - Acceso denegado a las alertas'
                ];
            }
            
            // PRODUCCIÓN: 6 meses - período real de operación
            // SERVICIOS QUE DISPARAN ALERTAS A 6+ MESES:
            $serviciosAlerta = [
                'Full Service con Bujías',
                'Full Service sin Bujías', 
                'Cambio de Aceite',
                'Verificación'
            ];

            $serviciosPattern = implode('|', array_map('preg_quote', $serviciosAlerta));

            // Buscar órdenes de hace 6+ meses que tengan estos servicios
            // y que NO tengan ya una alerta generada
            $query = "
                SELECT DISTINCT
                    os.id as orden_id,
                    os.cliente_id,
                    os.vehiculo_id,
                    os.fecha_ingreso,
                    GROUP_CONCAT(so.descripcion) as servicios_realizados,
                    DATEDIFF(NOW(), os.fecha_ingreso) as dias_desde_servicio
                    
                FROM ordenes_servicio os
                INNER JOIN servicios_orden so ON os.id = so.orden_id
                LEFT JOIN alertas_servicio a ON os.id = a.orden_id
                
                WHERE 
                    os.fecha_ingreso >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    AND os.fecha_ingreso <= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    AND a.id IS NULL -- No tiene alerta generada
                    AND EXISTS (
                        SELECT 1 FROM servicios_orden so2 
                        WHERE so2.orden_id = os.id 
                        AND so2.descripcion REGEXP ?
                    )
                    
                GROUP BY os.id, os.cliente_id, os.vehiculo_id, os.fecha_ingreso
                HAVING dias_desde_servicio >= 180 -- 6 meses (180 días) o más
                ORDER BY os.fecha_ingreso DESC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([$serviciosPattern]);
            $ordenesParaAlerta = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Log de debug para entender qué se está encontrando
            if ($esEjecucionAutomatica) {
                error_log("SAG Debug: Servicios buscados: " . implode(', ', $serviciosAlerta));
                error_log("SAG Debug: Pattern REGEXP: " . $serviciosPattern);
                error_log("SAG Debug: Órdenes encontradas para evaluar: " . count($ordenesParaAlerta));
                
                if (count($ordenesParaAlerta) > 0) {
                    error_log("SAG Debug: Primera orden encontrada - ID: " . $ordenesParaAlerta[0]['orden_id'] . 
                             ", Fecha: " . $ordenesParaAlerta[0]['fecha_ingreso'] . 
                             ", Días: " . $ordenesParaAlerta[0]['dias_desde_servicio']);
                }
            }

            $alertasGeneradas = 0;

            foreach ($ordenesParaAlerta as $orden) {
                // Obtener servicios que dispararon la alerta
                $serviciosRealizados = explode(',', $orden['servicios_realizados']);
                $serviciosQueDispararon = [];
                $todosServicios = [];

                foreach ($serviciosRealizados as $servicio) {
                    $servicio = trim($servicio);
                    $todosServicios[] = $servicio;
                    
                    // CAMBIO: Usar coincidencia parcial flexible en lugar de exacta
                    $servicioUpper = strtoupper($servicio);
                    
                    // Verificar si contiene alguna de las palabras clave
                    if (strpos($servicioUpper, 'FULL SERVICE') !== false ||
                        strpos($servicioUpper, 'CAMBIO DE ACEITE') !== false ||
                        strpos($servicioUpper, 'VERIFICACION') !== false ||
                        strpos($servicioUpper, 'VERIFICACIÓN') !== false) {
                        
                        $serviciosQueDispararon[] = $servicio;
                    }
                }

                // Solo generar alerta si encontramos servicios que la disparan
                if (!empty($serviciosQueDispararon)) {
                    $insertQuery = "
                        INSERT INTO alertas_servicio (
                            orden_id,
                            cliente_id,
                            vehiculo_id,
                            fecha_ultimo_servicio,
                            servicios_que_dispararon,
                            todos_los_servicios,
                            dias_desde_servicio,
                            estado
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')
                    ";

                    $insertStmt = $this->db->prepare($insertQuery);
                    $insertStmt->execute([
                        $orden['orden_id'],
                        $orden['cliente_id'],
                        $orden['vehiculo_id'],
                        $orden['fecha_ingreso'],
                        json_encode($serviciosQueDispararon),
                        json_encode($todosServicios),
                        $orden['dias_desde_servicio']
                    ]);

                    $alertasGeneradas++;
                }
            }

            return [
                'success' => true,
                'alertas_generadas' => $alertasGeneradas,
                'mensaje' => "Se generaron {$alertasGeneradas} nuevas alertas"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al generar alertas: ' . $e->getMessage()
            ];
        }
    }

    // NUEVO: Método para generar alertas automáticamente con control diario
    public function generarAlertasAutomatico($userData = null) {
        try {
            // Para crons automáticos, permitir ejecución sin verificación de usuario
            // Verificar si es una ejecución automática o manual
            $esEjecucionAutomatica = (php_sapi_name() === 'cli' || !$userData);
            
            if (!$esEjecucionAutomatica && !$this->verificarAutorizacionAlertas($userData)) {
                return [
                    'success' => false,
                    'error' => 'No autorizado'
                ];
            }

            $tiempoInicio = microtime(true);

            // Verificar si ya se ejecutó hoy
            $checkQuery = "SELECT id FROM alertas_ejecucion_log WHERE fecha_ejecucion = CURDATE()";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                return [
                    'success' => true,
                    'alertas_generadas' => 0,
                    'mensaje' => 'Ya se ejecutó hoy - no se generaron alertas',
                    'ejecutado_previamente' => true
                ];
            }

            // Generar alertas usando el método existente
            $resultado = $this->generarAlertas($userData);
            
            if (!$resultado['success']) {
                throw new Exception($resultado['error']);
            }

            $tiempoFin = microtime(true);
            $tiempoEjecucion = round(($tiempoFin - $tiempoInicio) * 1000); // en millisegundos

            // Registrar la ejecución en el log
            $logQuery = "
                INSERT INTO alertas_ejecucion_log (
                    fecha_ejecucion, 
                    alertas_generadas, 
                    tiempo_ejecucion_ms, 
                    usuario_id,
                    detalles
                ) VALUES (CURDATE(), ?, ?, ?, ?)
            ";

            $detalles = json_encode([
                'metodo' => 'automatico',
                'timestamp' => date('Y-m-d H:i:s'),
                'mensaje' => $resultado['mensaje']
            ]);

            $logStmt = $this->db->prepare($logQuery);
            $userId = $userData['id'] ?? $userData['user_id'] ?? null;
            $logStmt->execute([
                $resultado['alertas_generadas'],
                $tiempoEjecucion,
                $userId,
                $detalles
            ]);

            return [
                'success' => true,
                'alertas_generadas' => $resultado['alertas_generadas'],
                'mensaje' => $resultado['mensaje'] . " (Automático)",
                'tiempo_ejecucion_ms' => $tiempoEjecucion,
                'ejecutado_previamente' => false
            ];

        } catch (Exception $e) {
            // Registrar error en log si es posible
            try {
                $tiempoFin = microtime(true);
                $tiempoEjecucion = isset($tiempoInicio) ? round(($tiempoFin - $tiempoInicio) * 1000) : 0;
                
                $logQuery = "
                    INSERT INTO alertas_ejecucion_log (
                        fecha_ejecucion, 
                        alertas_generadas, 
                        tiempo_ejecucion_ms, 
                        usuario_id,
                        detalles
                    ) VALUES (CURDATE(), 0, ?, ?, ?)
                ";

                $detalles = json_encode([
                    'metodo' => 'automatico',
                    'timestamp' => date('Y-m-d H:i:s'),
                    'error' => $e->getMessage()
                ]);

                $logStmt = $this->db->prepare($logQuery);
                $userId = $userData['id'] ?? $userData['user_id'] ?? null;
                $logStmt->execute([$tiempoEjecucion, $userId, $detalles]);
            } catch (Exception $logError) {
                // Silenciar errores de log para evitar loops
            }

            return [
                'success' => false,
                'error' => 'Error en generación automática: ' . $e->getMessage(),
                'alertas_generadas' => 0
            ];
        }
    }

    // GET /alertas/estadisticas - Obtener estadísticas de alertas
    public function obtenerEstadisticas($userData = null) {
        try {
            // Verificar autorización
            if (!$this->verificarAutorizacionAlertas($userData)) {
                return [
                    'success' => false,
                    'error' => 'No autorizado - Acceso denegado a las alertas'
                ];
            }

            $sucursalId = (int) ($userData['sucursal_activa_id'] ?? 1);

            $query = "
                SELECT
                    COUNT(*) as total_alertas,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'leida' THEN 1 ELSE 0 END) as leidas,
                    AVG(dias_desde_servicio) as promedio_dias_servicio
                FROM alertas_servicio
                WHERE sucursal_id = :sucursal_id
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':sucursal_id', $sucursalId, PDO::PARAM_INT);
            $stmt->execute();
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'estadisticas' => [
                    'total' => (int)$stats['total_alertas'],
                    'pendientes' => (int)$stats['pendientes'],
                    'leidas' => (int)$stats['leidas'],
                    'promedio_dias' => round($stats['promedio_dias_servicio'], 1)
                ]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener alertas: ' . $e->getMessage()
            ];
        }
    }

    // GET /alertas/{id}/conversacion - Obtener mensajes de conversación WhatsApp para una alerta
    public function obtenerConversacion($alertaId, $userData = null) {
        try {
            if (!$this->verificarAutorizacionAlertas($userData)) {
                return [
                    'success' => false,
                    'error' => 'No autorizado'
                ];
            }

            $sucursalId = (int) ($userData['sucursal_activa_id'] ?? 1);

            // Verificar que la alerta existe y pertenece a esta sucursal
            $checkStmt = $this->db->prepare("SELECT id FROM alertas_servicio WHERE id = ? AND sucursal_id = ?");
            $checkStmt->execute([(int)$alertaId, $sucursalId]);
            if (!$checkStmt->fetch()) {
                return [
                    'success' => false,
                    'error' => 'Alerta no encontrada'
                ];
            }

            $query = "
                SELECT
                    id,
                    direction,
                    message_body AS mensaje,
                    message_status AS estado,
                    conversation_step,
                    created_at
                FROM conversaciones_whatsapp
                WHERE alerta_id = ?
                ORDER BY created_at ASC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute([(int)$alertaId]);
            $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'success' => true,
                'mensajes' => $mensajes
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener conversación: ' . $e->getMessage()
            ];
        }
    }

    private function obtenerEstadoWhatsAppTracking($alerta) {
        $tracking = [
            'estado' => 'no_enviado',
            'descripcion' => 'Sin mensaje enviado',
            'color' => 'gray',
            'icono' => 'clock',
            'detalles' => []
        ];

        // Si no hay estado de WhatsApp, retornar estado por defecto
        if (empty($alerta['whatsapp_estado'])) {
            return $tracking;
        }

        $estadoWA = $alerta['whatsapp_estado'];
        $intentActual = $alerta['intent_actual'];
        
        // Determinar estado y apariencia basado en el estado de conversación
        switch ($estadoWA) {
            case 'inicial':
                $tracking = [
                    'estado' => 'mensaje_enviado',
                    'descripcion' => 'Mensaje inicial enviado',
                    'color' => 'blue',
                    'icono' => 'paper-plane',
                    'detalles' => [
                        'ultimo_envio' => $alerta['fecha_ultimo_mensaje_enviado'],
                        'esperando_respuesta' => true
                    ]
                ];
                break;

            case 'esperando_respuesta':
                $tracking = [
                    'estado' => 'esperando_respuesta',
                    'descripcion' => 'Esperando respuesta del cliente',
                    'color' => 'yellow',
                    'icono' => 'clock',
                    'detalles' => [
                        'ultimo_envio' => $alerta['fecha_ultimo_mensaje_enviado'],
                        'tiempo_espera' => $this->calcularTiempoEspera($alerta['fecha_ultimo_mensaje_enviado'])
                    ]
                ];
                break;

            case 'activa':
                if ($intentActual === 'agendar_cita') {
                    $tracking = [
                        'estado' => 'agendando_cita',
                        'descripcion' => 'Cliente interesado - Agendando cita',
                        'color' => 'green',
                        'icono' => 'calendar',
                        'detalles' => [
                            'ultimo_mensaje' => $alerta['fecha_ultimo_mensaje_recibido'],
                            'interesado' => true,
                            'proceso' => 'agendamiento'
                        ]
                    ];
                } else {
                    $tracking = [
                        'estado' => 'conversacion_activa',
                        'descripcion' => 'Conversación activa',
                        'color' => 'green',
                        'icono' => 'message-circle',
                        'detalles' => [
                            'ultimo_mensaje' => $alerta['fecha_ultimo_mensaje_recibido'],
                            'intent_actual' => $intentActual
                        ]
                    ];
                }
                break;

            case 'cita_agendada':
                $tracking = [
                    'estado' => 'cita_confirmada',
                    'descripcion' => 'Cita agendada exitosamente',
                    'color' => 'green',
                    'icono' => 'check-circle',
                    'detalles' => [
                        'cita_agendada' => $alerta['cita_agendada'],
                        'completado' => true
                    ]
                ];
                break;

            case 'no_interesado':
                $tracking = [
                    'estado' => 'no_interesado',
                    'descripcion' => 'Cliente no interesado',
                    'color' => 'red',
                    'icono' => 'x-circle',
                    'detalles' => [
                        'motivo' => 'Cliente declinó el servicio',
                        'final' => true
                    ]
                ];
                break;

            case 'timeout':
                $tracking = [
                    'estado' => 'sin_respuesta',
                    'descripcion' => 'Cliente no respondió',
                    'color' => 'gray',
                    'icono' => 'clock',
                    'detalles' => [
                        'ultimo_intento' => $alerta['fecha_ultimo_mensaje_enviado'],
                        'timeout' => true
                    ]
                ];
                break;

            case 'error':
                $tracking = [
                    'estado' => 'error',
                    'descripcion' => 'Error en envío de mensaje',
                    'color' => 'red',
                    'icono' => 'alert-circle',
                    'detalles' => [
                        'requiere_atencion' => true
                    ]
                ];
                break;

            default:
                $tracking['detalles']['estado_original'] = $estadoWA;
        }

        return $tracking;
    }

    private function calcularTiempoEspera($fechaEnvio) {
        if (empty($fechaEnvio)) return null;
        
        $envio = new DateTime($fechaEnvio);
        $ahora = new DateTime();
        $diferencia = $ahora->diff($envio);
        
        if ($diferencia->days > 0) {
            return $diferencia->days . ' día(s)';
        } else if ($diferencia->h > 0) {
            return $diferencia->h . ' hora(s)';
        } else {
            return $diferencia->i . ' minuto(s)';
        }
    }

    public function obtenerEstadisticasWhatsApp() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    wc.estado_conversacion,
                    COUNT(*) as cantidad
                FROM alertas a
                LEFT JOIN cotizaciones co ON a.cotizacion_id = co.id
                LEFT JOIN clientes c ON co.cliente_id = c.id
                LEFT JOIN whatsapp_conversaciones wc ON c.telefono = wc.numero_telefono
                WHERE a.activa = 1
                GROUP BY wc.estado_conversacion
            ");
            
            $stmt->execute();
            $estadisticas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => $estadisticas
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }
}
