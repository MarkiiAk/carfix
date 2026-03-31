<?php
class AlertasController {
    private $db;
    private $jwt_secret;

    public function __construct($db) {
        $this->db = $db;
        $this->jwt_secret = $_ENV['JWT_SECRET'] ?? 'sag_garage_jwt_secret_key_2024';
    }

    /**
     * Verifica si el usuario actual está autorizado para acceder a las alertas
     * CUALQUIER usuario logueado puede acceder - SIN RESTRICCIONES
     */
    private function verificarAutorizacionAlertas($userData) {
        // CUALQUIER usuario logueado puede ver las alertas - SIN RESTRICCIONES  
        return !!$userData;
    }

    // GET /alertas - Obtener todas las alertas con información completa
    public function obtenerAlertas($userData = null) {
        try {
            // Verificar autorización
            if (!$this->verificarAutorizacionAlertas($userData)) {
                return [
                    'success' => false,
                    'error' => 'No autorizado - Acceso denegado a las alertas'
                ];
            }
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
                    
                    -- Información del cliente
                    c.nombre as cliente_nombre,
                    c.telefono as cliente_telefono,
                    c.email as cliente_email,
                    
                    -- Información del vehículo  
                    v.marca,
                    v.modelo,
                    v.anio,
                    v.placas,
                    
                    -- Días exactos desde el último servicio (calculado en tiempo real)
                    DATEDIFF(NOW(), a.fecha_ultimo_servicio) as dias_exactos_desde_servicio
                    
                FROM alertas_servicio a
                INNER JOIN clientes c ON a.cliente_id = c.id
                INNER JOIN vehiculos v ON a.vehiculo_id = v.id
                ORDER BY 
                    CASE WHEN a.estado = 'pendiente' THEN 0 ELSE 1 END,
                    a.fecha_generada DESC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $alertas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar cada alerta para formatear los datos JSON
            foreach ($alertas as &$alerta) {
                $alerta['servicios_que_dispararon'] = json_decode($alerta['servicios_que_dispararon'], true) ?? [];
                $alerta['todos_los_servicios'] = json_decode($alerta['todos_los_servicios'], true) ?? [];
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
            // Verificar que la alerta existe y está pendiente
            $checkQuery = "SELECT id, estado FROM alertas_servicio WHERE id = ?";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->execute([$alertaId]);
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

            // Marcar como leída
            $updateQuery = "
                UPDATE alertas_servicio 
                SET 
                    estado = 'leida',
                    fecha_marcada_leida = CURRENT_TIMESTAMP,
                    usuario_marco_leida = ?
                WHERE id = ?
            ";

            $updateStmt = $this->db->prepare($updateQuery);
            $userId = $userData['id'] ?? $userData['user_id'] ?? null;
            
            // Si no podemos obtener un ID válido de usuario, usar NULL
            if (!$userId || !is_numeric($userId)) {
                $userId = null;
            }
            
            $updateStmt->execute([$userId, $alertaId]);

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
            
            // PRODUCCIÓN: 6 meses (180 días) - período estándar para alertas de servicio
            // SERVICIOS QUE DISPARAN ALERTAS A 6 MESES:
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
            $query = "
                SELECT 
                    COUNT(*) as total_alertas,
                    SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
                    SUM(CASE WHEN estado = 'leida' THEN 1 ELSE 0 END) as leidas,
                    AVG(dias_desde_servicio) as promedio_dias_servicio
                FROM alertas_servicio
            ";

            $stmt = $this->db->prepare($query);
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
                'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }
}