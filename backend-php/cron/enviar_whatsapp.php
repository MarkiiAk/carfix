<?php
/**
 * CRON: Enviar WhatsApp - SAG Garage
 * Sistema automático de envío de recordatorios por WhatsApp
 * 
 * Flujo:
 * 1. Busca alertas en estado 'borrador' 
 * 2. Envía recordatorios WhatsApp con botones interactivos
 * 3. Actualiza estados y registra actividad
 * 4. Reporta resultados y estadísticas
 * 
 * Configuración cron ejemplo:
 * 45 10 * * * /usr/local/bin/php /home/saggarag/public_html/backend-php/cron/enviar_whatsapp.php
 * 
 * @author Sistema SAG Garage
 * @version 2.0.0
 * @date 01/04/2026
 */

// Configurar timezone y manejo de errores
date_default_timezone_set('America/Mexico_City');
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Función para logging mejorada
function logWhatsApp($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [WHATSAPP-{$level}] {$message}\n";
    
    // Escribir a archivo de log
    $logPath = dirname(__FILE__) . '/../../logs/sag_whatsapp_envio.log';
    $logDir = dirname($logPath);
    
    // Crear directorio de logs si no existe
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
    
    // También enviar a syslog
    if (function_exists('syslog')) {
        syslog(LOG_INFO, "SAG WhatsApp: {$message}");
    }
    
    // Output para ejecución manual
    echo $logMessage;
    
    // Forzar flush
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

// Manejadores de error
function handleWhatsAppError($errno, $errstr, $errfile, $errline) {
    logWhatsApp("Error PHP: [{$errno}] {$errstr} en {$errfile}:{$errline}", 'ERROR');
    return true;
}

function handleWhatsAppException($exception) {
    logWhatsApp("Excepción: " . $exception->getMessage() . " en " . $exception->getFile() . ":" . $exception->getLine(), 'FATAL');
}

set_error_handler('handleWhatsAppError');
set_exception_handler('handleWhatsAppException');

// Función para formatear bytes
function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// ===============================================
// INICIALIZACIÓN
// ===============================================

logWhatsApp("=== INICIO ENVÍO AUTOMÁTICO WHATSAPP ===");
logWhatsApp("Fecha/Hora: " . date('Y-m-d H:i:s T'));
logWhatsApp("Memoria inicial: " . formatBytes(memory_get_usage()));

try {
    // Verificar directorio actual
    $currentDir = dirname(__FILE__);
    logWhatsApp("Directorio actual: {$currentDir}");
    
    // Cargar dependencias
    require_once dirname(__FILE__) . '/../config/database.php';
    require_once dirname(__FILE__) . '/../utils/TwilioConversationalBot.php';
    
    logWhatsApp("Dependencias cargadas correctamente");
    
    // Conectar a base de datos
    logWhatsApp("Conectando a base de datos...");
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    logWhatsApp("Conexión a BD establecida");
    
    // Verificar tablas necesarias
    $tablasRequeridas = ['alertas_servicio', 'conversaciones_whatsapp', 'twilio_config'];
    foreach ($tablasRequeridas as $tabla) {
        $sql = "SHOW TABLES LIKE '$tabla'";
        $stmt = $db->query($sql);
        if ($stmt->rowCount() === 0) {
            throw new Exception("Tabla requerida '{$tabla}' no existe");
        }
    }
    
    logWhatsApp("Verificación de tablas completada");
    
    // Crear instancia del bot conversacional
    $bot = crearTwilioBot();
    if (!$bot) {
        throw new Exception("No se pudo crear instancia del TwilioBot");
    }
    
    logWhatsApp("Bot conversacional inicializado");
    
    // ===============================================
    // VERIFICAR CONFIGURACIÓN TWILIO
    // ===============================================
    
    logWhatsApp("Verificando configuración Twilio...");
    
    $configRequerida = ['account_sid', 'auth_token', 'whatsapp_from'];
    $configOk = true;
    
    foreach ($configRequerida as $key) {
        $sql = "SELECT config_value FROM twilio_config WHERE config_key = ? AND is_active = TRUE";
        $stmt = $db->prepare($sql);
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $value = $result['config_value'] ?? '';
        if (empty($value)) {
            logWhatsApp("Configuración faltante: {$key}", 'WARNING');
            $configOk = false;
        }
    }
    
    if (!$configOk) {
        logWhatsApp("Configuración Twilio incompleta, continuando en modo simulación", 'WARNING');
    } else {
        logWhatsApp("Configuración Twilio verificada ✓");
    }
    
    // ===============================================
    // BUSCAR ALERTAS PENDIENTES DE ENVÍO
    // ===============================================
    
    logWhatsApp("Buscando alertas pendientes de envío WhatsApp...");
    
    $sql = "SELECT 
                a.id,
                a.cliente_id,
                a.vehiculo_id,
                a.servicios_que_dispararon,
                a.dias_desde_servicio,
                a.fecha_generada,
                
                -- Info cliente
                c.nombre as cliente_nombre,
                c.telefono as cliente_telefono,
                c.email as cliente_email,
                
                -- Info vehículo
                CONCAT(v.marca, ' ', v.modelo, ' ', v.anio) as vehiculo_info,
                v.placas as vehiculo_placas
                
            FROM alertas_servicio a
            INNER JOIN clientes c ON a.cliente_id = c.id
            INNER JOIN vehiculos v ON a.vehiculo_id = v.id
            
            WHERE a.estado_whatsapp = 'borrador'
              AND a.estado = 'pendiente'
              AND c.telefono IS NOT NULL 
              AND c.telefono != ''
              
            ORDER BY a.fecha_generada ASC
            LIMIT 50"; // Procesar máximo 50 por ejecución
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $alertasPendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalAlertas = count($alertasPendientes);
    logWhatsApp("Alertas encontradas para envío: {$totalAlertas}");
    
    if ($totalAlertas === 0) {
        logWhatsApp("No hay alertas pendientes de envío WhatsApp");
        logWhatsApp("=== FIN ENVÍO WHATSAPP (SIN ENVÍOS) ===");
        exit(0);
    }
    
    // ===============================================
    // PROCESAR ENVÍOS
    // ===============================================
    
    $tiempoInicio = microtime(true);
    $enviosExitosos = 0;
    $enviosErrores = 0;
    $telefonosInvalidos = 0;
    
    logWhatsApp("Iniciando procesamiento de envíos...");
    
    foreach ($alertasPendientes as $alerta) {
        try {
            logWhatsApp("--- Procesando Alerta ID: {$alerta['id']} ---");
            logWhatsApp("Cliente: {$alerta['cliente_nombre']} ({$alerta['cliente_telefono']})");
            logWhatsApp("Servicio: {$alerta['servicios_que_dispararon']}");
            logWhatsApp("Vehículo: {$alerta['vehiculo_info']}");
            
            // Validar teléfono
            $telefono = limpiarTelefono($alerta['cliente_telefono']);
            if (empty($telefono)) {
                logWhatsApp("Teléfono inválido: {$alerta['cliente_telefono']}", 'WARNING');
                $telefonosInvalidos++;
                continue;
            }
            
            logWhatsApp("Teléfono validado: {$telefono}");
            
            // Enviar recordatorio inicial
            $resultado = $bot->enviarRecordatorioInicial($alerta['id']);
            
            if ($resultado['success']) {
                $enviosExitosos++;
                logWhatsApp("✅ Enviado exitosamente - MessageSID: {$resultado['message_sid']}");
                
                // Pequeña pausa entre envíos para no saturar la API
                usleep(250000); // 250ms
                
            } else {
                $enviosErrores++;
                logWhatsApp("❌ Error en envío: " . $resultado['error'], 'ERROR');
                
                // Marcar alerta con error para revisión manual
                marcarAlertaConError($db, $alerta['id'], $resultado['error']);
            }
            
        } catch (Exception $e) {
            $enviosErrores++;
            logWhatsApp("❌ Excepción procesando alerta {$alerta['id']}: " . $e->getMessage(), 'ERROR');
            
            // Marcar alerta con error
            marcarAlertaConError($db, $alerta['id'], $e->getMessage());
        }
    }
    
    $tiempoFin = microtime(true);
    $tiempoEjecucion = round(($tiempoFin - $tiempoInicio) * 1000); // en ms
    
    // ===============================================
    // ESTADÍSTICAS FINALES
    // ===============================================
    
    logWhatsApp("=== ESTADÍSTICAS DE ENVÍO ===");
    logWhatsApp("Total alertas procesadas: {$totalAlertas}");
    logWhatsApp("Envíos exitosos: {$enviosExitosos}");
    logWhatsApp("Envíos con error: {$enviosErrores}");
    logWhatsApp("Teléfonos inválidos: {$telefonosInvalidos}");
    logWhatsApp("Tiempo de ejecución: {$tiempoEjecucion}ms");
    
    // Calcular tasa de éxito
    $tasaExito = $totalAlertas > 0 ? round(($enviosExitosos / $totalAlertas) * 100, 2) : 0;
    logWhatsApp("Tasa de éxito: {$tasaExito}%");
    
    // ===============================================
    // REGISTRAR EJECUCIÓN EN LOG
    // ===============================================
    
    try {
        $sql = "INSERT INTO alertas_ejecucion_log 
                (fecha_ejecucion, alertas_generadas, tiempo_ejecucion_ms, detalles) 
                VALUES (CURDATE(), ?, ?, ?)";
        
        $detalles = json_encode([
            'tipo' => 'envio_whatsapp',
            'timestamp' => date('Y-m-d H:i:s'),
            'total_procesadas' => $totalAlertas,
            'envios_exitosos' => $enviosExitosos,
            'envios_errores' => $enviosErrores,
            'telefonos_invalidos' => $telefonosInvalidos,
            'tasa_exito' => $tasaExito
        ]);
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$enviosExitosos, $tiempoEjecucion, $detalles]);
        
        logWhatsApp("Ejecución registrada en base de datos");
        
    } catch (Exception $e) {
        logWhatsApp("Error registrando ejecución: " . $e->getMessage(), 'WARNING');
    }
    
    // ===============================================
    // LIMPIEZA Y VERIFICACIÓN MEMORIA
    // ===============================================
    
    $memoriaFinal = memory_get_usage();
    $memoriaPico = memory_get_peak_usage();
    logWhatsApp("Memoria final: " . formatBytes($memoriaFinal));
    logWhatsApp("Memoria pico: " . formatBytes($memoriaPico));
    
    // Limpiar recursos
    $db = null;
    $bot = null;
    
    // ===============================================
    // NOTIFICACIÓN DE ESTADO
    // ===============================================
    
    if ($enviosErrores > 0) {
        logWhatsApp("⚠️  ATENCIÓN: Hubo errores en algunos envíos", 'WARNING');
    }
    
    if ($enviosExitosos > 0) {
        logWhatsApp("🎉 Envíos WhatsApp completados exitosamente");
    }
    
    logWhatsApp("=== FIN ENVÍO AUTOMÁTICO WHATSAPP ===");
    
    // Código de salida
    exit($enviosErrores > 0 ? 1 : 0);
    
} catch (PDOException $e) {
    logWhatsApp("❌ Error de base de datos: " . $e->getMessage(), 'FATAL');
    
    // Intentar reconectar
    sleep(3);
    try {
        logWhatsApp("Intentando reconectar...", 'WARNING');
        $db = Database::getInstance()->getConnection();
        if ($db) {
            logWhatsApp("Reconexión exitosa");
        }
    } catch (Exception $e2) {
        logWhatsApp("❌ Reconexión falló: " . $e2->getMessage(), 'FATAL');
    }
    
    exit(2);
    
} catch (Exception $e) {
    logWhatsApp("❌ Error general: " . $e->getMessage(), 'FATAL');
    logWhatsApp("Archivo: " . $e->getFile(), 'FATAL');
    logWhatsApp("Línea: " . $e->getLine(), 'FATAL');
    exit(3);
    
} catch (Error $e) {
    logWhatsApp("❌ Error fatal de PHP: " . $e->getMessage(), 'FATAL');
    exit(4);
}

// ===============================================
// FUNCIONES AUXILIARES
// ===============================================

/**
 * Limpiar y validar número de teléfono
 */
function limpiarTelefono($telefono) {
    if (empty($telefono)) {
        return '';
    }
    
    // Remover espacios, guiones, paréntesis
    $telefono = preg_replace('/[\s\-\(\)]+/', '', $telefono);
    
    // Remover prefijos comunes
    $telefono = preg_replace('/^\+?52/', '', $telefono);
    $telefono = preg_replace('/^01/', '', $telefono);
    
    // Validar que sean 10 dígitos
    if (!preg_match('/^\d{10}$/', $telefono)) {
        return '';
    }
    
    return $telefono;
}

/**
 * Marcar alerta con error para revisión manual
 */
function marcarAlertaConError($db, $alertaId, $error) {
    try {
        $sql = "UPDATE alertas_servicio 
                SET requiere_atencion = TRUE,
                    prioridad = 'alta',
                    estado_whatsapp = 'borrador'
                WHERE id = ?";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$alertaId]);
        
        // Registrar el error en un log específico
        $errorLog = json_encode([
            'alerta_id' => $alertaId,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s'),
            'tipo' => 'error_envio_whatsapp'
        ]);
        
        logWhatsApp("Error registrado para alerta {$alertaId}: {$error}", 'ERROR');
        
    } catch (Exception $e) {
        logWhatsApp("Error marcando alerta con error: " . $e->getMessage(), 'ERROR');
    }
}

/**
 * Verificar si es horario laboral
 */
function esHorarioLaboral() {
    $hora = (int)date('H');
    $diaSemana = (int)date('w'); // 0=domingo, 6=sábado
    
    // Lunes a viernes de 8 AM a 6 PM
    $esLaboral = ($diaSemana >= 1 && $diaSemana <= 5) && ($hora >= 8 && $hora <= 18);
    
    if (!$esLaboral) {
        logWhatsApp("Fuera de horario laboral - Día: {$diaSemana}, Hora: {$hora}", 'INFO');
    }
    
    return $esLaboral;
}

/**
 * Estadísticas rápidas del sistema
 */
function mostrarEstadisticasRapidas($db) {
    try {
        $stats = [];
        
        // Alertas por estado WhatsApp
        $sql = "SELECT estado_whatsapp, COUNT(*) as total 
                FROM alertas_servicio 
                GROUP BY estado_whatsapp";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($resultados as $row) {
            $stats[$row['estado_whatsapp']] = $row['total'];
        }
        
        logWhatsApp("--- ESTADÍSTICAS SISTEMA ---");
        foreach ($stats as $estado => $total) {
            logWhatsApp("Estado '{$estado}': {$total} alertas");
        }
        
        // Conversaciones activas
        $sql = "SELECT COUNT(DISTINCT alerta_id) as conversaciones_activas
                FROM conversaciones_whatsapp
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        logWhatsApp("Conversaciones activas (7 días): " . $result['conversaciones_activas']);
        
    } catch (Exception $e) {
        logWhatsApp("Error obteniendo estadísticas: " . $e->getMessage(), 'WARNING');
    }
}

?>