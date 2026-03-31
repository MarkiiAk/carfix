<?php
/**
 * Cron Job: Enviar WhatsApp - SAG Garage
 * 
 * Se ejecuta automáticamente según configuración de cPanel cron
 * Envía mensajes de WhatsApp a clientes con alertas pendientes
 * 
 * Configuración cron ejemplo (días hábiles):
 * 30 10 * * 1-5 /usr/local/bin/php /home/saggarag/public_html/backend-php/cron/enviar_whatsapp.php
 * 
 * @author Marco Candiani
 * @version 1.1
 * @date 31/03/2026
 */

// Configurar timezone
date_default_timezone_set('America/Mexico_City');

// Función para logging
function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
    
    // Escribir a archivo de log (compatible con Windows)
    $logPath = dirname(__FILE__) . '/../../logs/sag_whatsapp_envio.log';
    $logDir = dirname($logPath);
    
    // Crear directorio de logs si no existe
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
    
    // También enviar a syslog si está disponible
    if (function_exists('syslog')) {
        syslog(LOG_INFO, "SAG WhatsApp: {$message}");
    }
    
    // Output para cuando se ejecuta manualmente
    echo $logMessage;
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

// Función para formatear bytes
function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Función para verificar si es día hábil
function esDiaHabil() {
    $diaSemana = date('N'); // 1 (Monday) to 7 (Sunday)
    return $diaSemana >= 1 && $diaSemana <= 5; // Lunes a Viernes
}


// Inicializar
logMessage("=== INICIO ENVÍO AUTOMÁTICO DE WHATSAPP ===");
logMessage("Fecha/Hora: " . date('Y-m-d H:i:s T'));
logMessage("Usuario: " . get_current_user());
logMessage("PHP Version: " . PHP_VERSION);
logMessage("Memoria inicial: " . formatBytes(memory_get_usage()));

try {
    // Verificar día hábil (controlado por configuración cron, pero mantenemos verificación como seguridad)
    if (!esDiaHabil()) {
        logMessage("⏭️  Saltando ejecución: No es día hábil (hoy es " . date('l') . ")");
        exit(0);
    }
    
    logMessage("✅ Es día hábil, procediendo con la ejecución...");
    
    // Verificar que estamos en el directorio correcto
    $currentDir = dirname(__FILE__);
    logMessage("Directorio actual: {$currentDir}");
    
    // Cargar dependencias
    require_once dirname(__FILE__) . '/../config/database.php';
    require_once dirname(__FILE__) . '/../controllers/WhatsappController.php';
    
    logMessage("Dependencias cargadas correctamente");
    
    // Conectar a base de datos
    logMessage("Intentando conectar a base de datos...");
    $db = Database::getInstance()->getConnection();
    
    if (!$db) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    logMessage("Conexión a base de datos establecida");
    
    // Verificar tablas necesarias para WhatsApp
    $tablasRequeridas = [
        'alertas_servicio', 'clientes', 'vehiculos', 
        'whatsapp_config', 'whatsapp_templates', 'whatsapp_logs', 'whatsapp_blacklist'
    ];
    
    foreach ($tablasRequeridas as $tabla) {
        $sql = "SHOW TABLES LIKE '$tabla'";
        $stmt = $db->query($sql);
        if ($stmt->rowCount() === 0) {
            throw new Exception("Tabla requerida '{$tabla}' no existe");
        }
    }
    
    logMessage("Verificación de tablas completada");
    
    // Crear instancia del controlador de WhatsApp
    $whatsappController = new WhatsappController($db);
    
    // Verificar que el sistema esté activo
    if (!$whatsappController->isSistemaActivo()) {
        logMessage("⚠️  Sistema WhatsApp desactivado - saltando ejecución");
        logMessage("ℹ️   Para activar: UPDATE whatsapp_config SET valor = 'true' WHERE clave = 'activo'");
        exit(0);
    }
    
    logMessage("✅ Sistema WhatsApp activo - procediendo con envíos");
    
    // Verificar configuración de API
    $testConexion = $whatsappController->testConexion();
    if (!$testConexion['success']) {
        logMessage("⚠️  API de WhatsApp no configurada: " . $testConexion['error'], 'WARNING');
        logMessage("ℹ️   Continuando en modo TEST - falta configurar API pero el sistema funciona");
        logMessage("🔧  Para producción: configure api_token y phone_number_id en whatsapp_config");
        $modoTesting = true;
    } else {
        logMessage("✅ Conexión con WhatsApp API verificada");
        if (isset($testConexion['phone_number'])) {
            logMessage("📱 Número verificado: " . $testConexion['phone_number']);
        }
        $modoTesting = false;
    }
    
    // Obtener estadísticas antes del envío
    $statsAntes = $whatsappController->obtenerEstadisticas();
    if ($statsAntes['success']) {
        $stats = $statsAntes['estadisticas'];
        logMessage("📊 Estadísticas antes del envío:");
        logMessage("- Alertas pendientes: " . ($stats['alertas_pendientes'] ?? 0));
        logMessage("- Mensajes enviados hoy: " . ($stats['mensajes_hoy'] ?? 0));
        logMessage("- Números bloqueados: " . ($stats['numeros_bloqueados'] ?? 0));
    }
    
    // Ejecutar procesamiento de cola WhatsApp
    logMessage("🚀 Iniciando procesamiento de cola WhatsApp...");
    $tiempoInicio = microtime(true);
    
    $resultado = $whatsappController->procesarColaWhatsApp();
    
    $tiempoFin = microtime(true);
    $tiempoEjecucion = round(($tiempoFin - $tiempoInicio) * 1000); // en milisegundos
    
    // Procesar resultado
    if ($resultado['success']) {
        $enviados = $resultado['enviados'] ?? 0;
        $errores = $resultado['errores'] ?? 0;
        $omitidos = $resultado['omitidos'] ?? 0;
        
        logMessage("✅ Procesamiento completado:");
        logMessage("- ✅ Mensajes enviados: {$enviados}");
        logMessage("- ❌ Errores: {$errores}");
        logMessage("- ⏭️  Omitidos: {$omitidos}");
        logMessage("- ⏱️  Tiempo de ejecución: {$tiempoEjecucion}ms");
        
        // Log de mensajes individuales si hay detalles
        if (isset($resultado['mensajes']) && !empty($resultado['mensajes'])) {
            logMessage("📝 Detalle de mensajes:");
            foreach ($resultado['mensajes'] as $mensaje) {
                logMessage("  • {$mensaje}");
            }
        }
        
        // Si no hay mensajes, mostrar razón
        if ($enviados === 0 && $errores === 0 && $omitidos === 0) {
            $mensaje = $resultado['mensaje'] ?? 'No hay alertas pendientes para procesar';
            logMessage("ℹ️   {$mensaje}");
        }
        
    } else {
        $error = $resultado['error'] ?? 'Error desconocido';
        logMessage("❌ Error en procesamiento: {$error}");
        logMessage("⏱️  Tiempo de ejecución: {$tiempoEjecucion}ms");
        
        // Log de errores específicos
        if (isset($resultado['mensajes']) && !empty($resultado['mensajes'])) {
            logMessage("📝 Detalles de errores:");
            foreach ($resultado['mensajes'] as $mensaje) {
                logMessage("  • {$mensaje}");
            }
        }
        
        exit(1);
    }
    
    // Obtener estadísticas después del envío
    if ($resultado['enviados'] > 0) {
        $statsDespues = $whatsappController->obtenerEstadisticas();
        if ($statsDespues['success']) {
            $statsNew = $statsDespues['estadisticas'];
            logMessage("📊 Estadísticas después del envío:");
            logMessage("- Mensajes enviados hoy: " . ($statsNew['mensajes_hoy'] ?? 0));
            logMessage("- Costo estimado hoy: $" . number_format(($statsNew['costo_hoy'] ?? 0), 2) . " MXN");
        }
    }
    
    // Verificar límites y enviar alertas si es necesario
    $limiteDiario = $whatsappController->getConfig('limite_mensajes_dia', 100);
    $mensajesHoy = ($statsNew['mensajes_hoy'] ?? $stats['mensajes_hoy'] ?? 0);
    
    if ($mensajesHoy >= $limiteDiario * 0.8) { // 80% del límite
        logMessage("⚠️  WARNING: Se ha alcanzado el 80% del límite diario ({$mensajesHoy}/{$limiteDiario})");
    }
    
    // Log de memoria utilizada
    $memoriaFinal = memory_get_usage();
    $memoriaPico = memory_get_peak_usage();
    logMessage("💾 Uso de memoria:");
    logMessage("- Final: " . formatBytes($memoriaFinal));
    logMessage("- Pico: " . formatBytes($memoriaPico));
    
    // Cleanup
    $db = null;
    $whatsappController = null;
    
    logMessage("🧹 Recursos liberados correctamente");
    
    // Resumen final
    $totalProcesados = ($resultado['enviados'] ?? 0) + ($resultado['errores'] ?? 0) + ($resultado['omitidos'] ?? 0);
    logMessage("📋 RESUMEN FINAL:");
    logMessage("- Total procesados: {$totalProcesados}");
    logMessage("- Exitosos: " . ($resultado['enviados'] ?? 0));
    logMessage("- Tiempo total: {$tiempoEjecucion}ms");
    
    if ($resultado['enviados'] > 0) {
        $costoEstimado = $resultado['enviados'] * 0.5614; // Costo por mensaje
        logMessage("- Costo estimado: $" . number_format($costoEstimado, 2) . " MXN");
    }
    
    logMessage("=== FIN ENVÍO AUTOMÁTICO DE WHATSAPP ===");
    
    // Código de salida basado en resultados
    if ($resultado['errores'] > 0) {
        exit(6); // Hubo errores pero también éxitos
    } else {
        exit(0); // Todo exitoso
    }
    
} catch (PDOException $e) {
    logMessage("❌ Error de base de datos: " . $e->getMessage(), 'FATAL');
    logMessage("Código de error: " . $e->getCode(), 'FATAL');
    
    // Intentar diagnóstico básico
    if (strpos($e->getMessage(), 'Connection refused') !== false) {
        logMessage("💡 Posible causa: Servidor de base de datos no disponible", 'FATAL');
    } elseif (strpos($e->getMessage(), 'Access denied') !== false) {
        logMessage("💡 Posible causa: Credenciales incorrectas", 'FATAL');
    }
    
    exit(2);
    
} catch (Exception $e) {
    logMessage("❌ Error general: " . $e->getMessage(), 'FATAL');
    logMessage("Archivo: " . $e->getFile(), 'FATAL');
    logMessage("Línea: " . $e->getLine(), 'FATAL');
    
    // Diagnóstico básico
    if (strpos($e->getMessage(), 'WhatsappController') !== false) {
        logMessage("💡 Posible causa: Error en el controlador de WhatsApp", 'FATAL');
    } elseif (strpos($e->getMessage(), 'file') !== false || strpos($e->getMessage(), 'require') !== false) {
        logMessage("💡 Posible causa: Archivo no encontrado o permisos", 'FATAL');
    }
    
    exit(3);
    
} catch (Error $e) {
    logMessage("❌ Error fatal de PHP: " . $e->getMessage(), 'FATAL');
    logMessage("Archivo: " . $e->getFile(), 'FATAL');
    logMessage("Línea: " . $e->getLine(), 'FATAL');
    
    exit(4);
}

// Esta línea no debería ejecutarse nunca
logMessage("⚠️  WARNING: Se alcanzó el final del script sin exit explícito", 'WARNING');
exit(5);