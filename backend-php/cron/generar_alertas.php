<?php
/**
 * Cron Job: Generar Alertas - CarFix
 * 
 * Se ejecuta automáticamente según configuración de cPanel cron
 * Genera alertas automáticamente para clientes que necesitan servicio
 * 
 * Configuración cron ejemplo:
 * 25 10 * * * /usr/local/bin/php /home/saggarag/public_html/backend-php/cron/generar_alertas.php
 * 
 * @author Marco Candiani
 * @version 1.1
 * @date 31/03/2026
 */

// Configurar timezone
date_default_timezone_set('America/Mexico_City');

// Función para logging mejorada con flush
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    
    // Escribir a archivo de log (compatible con Windows)
    $logPath = dirname(__FILE__) . '/../../logs/sag_alertas_generacion.log';
    $logDir = dirname($logPath);
    
    // Crear directorio de logs si no existe
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
    
    // También enviar a syslog si está disponible
    if (function_exists('syslog')) {
        syslog(LOG_INFO, "SAG Alertas: {$message}");
    }
    
    // Output para cuando se ejecuta manualmente con flush inmediato
    echo $logMessage;
    
    // Forzar flush del output para ver logs en tiempo real
    if (ob_get_level()) {
        ob_flush();
    }
    flush();
}

// Función para manejo de errores
function handleError($errno, $errstr, $errfile, $errline) {
    logMessage("Error PHP: [{$errno}] {$errstr} en {$errfile}:{$errline}", 'ERROR');
    return true;
}

// Función para manejo de excepciones no capturadas
function handleException($exception) {
    logMessage("Excepción no capturada: " . $exception->getMessage() . " en " . $exception->getFile() . ":" . $exception->getLine(), 'FATAL');
}

// Configurar manejadores de error
set_error_handler('handleError');
set_exception_handler('handleException');

// Inicializar
logMessage("=== INICIO GENERACIÓN AUTOMÁTICA DE ALERTAS ===");
logMessage("Fecha/Hora: " . date('Y-m-d H:i:s T'));
logMessage("Usuario: " . get_current_user());
logMessage("PHP Version: " . PHP_VERSION);
logMessage("Memoria inicial: " . formatBytes(memory_get_usage()));

// Función para formatear bytes
function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

try {
    // Verificar que estamos en el directorio correcto
    $currentDir = dirname(__FILE__);
    logMessage("Directorio actual: {$currentDir}");
    
    // Cargar dependencias
    require_once dirname(__FILE__) . '/../config/database.php';
    require_once dirname(__FILE__) . '/../controllers/AlertasController.php';
    
    logMessage("Dependencias cargadas correctamente");
    
    // Conectar a base de datos
    logMessage("Intentando conectar a base de datos...");
    $db = Database::getInstance()->getConnection();
    
    if (!$db) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    logMessage("Conexión a base de datos establecida");
    
    // Verificar tablas necesarias
    $tablasRequeridas = ['alertas_servicio', 'clientes', 'vehiculos', 'ordenes_servicio', 'servicios_orden'];
    foreach ($tablasRequeridas as $tabla) {
        $sql = "SHOW TABLES LIKE '$tabla'";
        $stmt = $db->query($sql);
        if ($stmt->rowCount() === 0) {
            throw new Exception("Tabla requerida '{$tabla}' no existe");
        }
    }
    
    logMessage("Verificación de tablas completada");
    
    // Crear instancia del controlador
    $alertasController = new AlertasController($db);
    
    // Verificar configuración actual del sistema
    logMessage("Verificando configuración del sistema de alertas...");
    
    // Obtener estadísticas antes de generar
    $statsAntes = $alertasController->obtenerEstadisticas();
    if ($statsAntes['success']) {
        $stats = $statsAntes['estadisticas'];
        logMessage("Estadísticas antes de generar:");
        logMessage("- Total alertas existentes: {$stats['total']}");
        logMessage("- Alertas pendientes: {$stats['pendientes']}");
        logMessage("- Alertas leídas: {$stats['leidas']}");
    }
    
    // Ejecutar generación de alertas
    logMessage("Iniciando generación de alertas automáticas...");
    $tiempoInicio = microtime(true);
    
    // Llamar al método de generación automática
    $resultado = $alertasController->generarAlertasAutomatico();
    
    $tiempoFin = microtime(true);
    $tiempoEjecucion = round(($tiempoFin - $tiempoInicio) * 1000); // en milisegundos
    
    // Procesar resultado
    if ($resultado['success']) {
        $alertasGeneradas = $resultado['alertas_generadas'];
        $mensaje = $resultado['mensaje'];
        
        logMessage("✅ Generación exitosa:");
        logMessage("- Alertas generadas: {$alertasGeneradas}");
        logMessage("- Tiempo de ejecución: {$tiempoEjecucion}ms");
        logMessage("- Mensaje: {$mensaje}");
        
        if (isset($resultado['ejecutado_previamente']) && $resultado['ejecutado_previamente']) {
            logMessage("ℹ️  Ya se había ejecutado hoy - no se duplicaron alertas");
        }
        
        // Si se generaron alertas, obtener estadísticas actualizadas
        if ($alertasGeneradas > 0) {
            $statsDespues = $alertasController->obtenerEstadisticas();
            if ($statsDespues['success']) {
                $statsNew = $statsDespues['estadisticas'];
                logMessage("Estadísticas después de generar:");
                logMessage("- Total alertas: {$statsNew['total']} (incremento: " . ($statsNew['total'] - $stats['total']) . ")");
                logMessage("- Alertas pendientes: {$statsNew['pendientes']}");
            }
        }
        
    } else {
        logMessage("❌ Error en generación:");
        logMessage("- Error: " . ($resultado['error'] ?? 'Error desconocido'));
        logMessage("- Tiempo de ejecución: {$tiempoEjecucion}ms");
        
        // Intentar obtener más detalles del error
        if (isset($resultado['debug_info'])) {
            logMessage("- Debug info: " . json_encode($resultado['debug_info']));
        }
        
        exit(1); // Código de salida de error
    }
    
    // Verificar memoria utilizada
    $memoriaFinal = memory_get_usage();
    $memoriaPico = memory_get_peak_usage();
    logMessage("Uso de memoria:");
    logMessage("- Final: " . formatBytes($memoriaFinal));
    logMessage("- Pico: " . formatBytes($memoriaPico));
    
    // Limpiar recursos
    $db = null;
    $alertasController = null;
    
    logMessage("Recursos liberados correctamente");
    logMessage("=== FIN GENERACIÓN AUTOMÁTICA DE ALERTAS ===");
    
    // Código de salida exitoso
    exit(0);
    
} catch (PDOException $e) {
    logMessage("❌ Error de base de datos: " . $e->getMessage(), 'FATAL');
    logMessage("Código de error: " . $e->getCode(), 'FATAL');
    
    // Intentar reconectar una vez
    sleep(5);
    try {
        logMessage("Intentando reconectar a la base de datos...", 'WARNING');
        $db = Database::getInstance()->getConnection();
        if ($db) {
            logMessage("Reconexión exitosa, reintentando operación...");
            // Aquí podrías reintentar la operación
        }
    } catch (Exception $e2) {
        logMessage("❌ Reconexión falló: " . $e2->getMessage(), 'FATAL');
    }
    
    exit(2); // Código específico para errores de BD
    
} catch (Exception $e) {
    logMessage("❌ Error general: " . $e->getMessage(), 'FATAL');
    logMessage("Archivo: " . $e->getFile(), 'FATAL');
    logMessage("Línea: " . $e->getLine(), 'FATAL');
    logMessage("Stack trace: " . $e->getTraceAsString(), 'FATAL');
    
    exit(3); // Código específico para errores generales
    
} catch (Error $e) {
    logMessage("❌ Error fatal de PHP: " . $e->getMessage(), 'FATAL');
    logMessage("Archivo: " . $e->getFile(), 'FATAL');
    logMessage("Línea: " . $e->getLine(), 'FATAL');
    
    exit(4); // Código específico para errores fatales de PHP
}

// Esta línea no debería ejecutarse nunca
logMessage("⚠️  WARNING: Se alcanzó el final del script sin exit explícito", 'WARNING');
exit(5);