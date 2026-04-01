<?php
/**
 * SETUP AUTOMÁTICO BD WHATSAPP CONVERSACIONAL
 * Ejecuta el SQL necesario para WhatsApp en producción
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== SETUP BD WHATSAPP CONVERSACIONAL ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Incluir conexión
    require_once __DIR__ . '/config/database.php';
    
    echo "✅ Conectando a base de datos...\n";
    
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "✅ Conexión exitosa\n\n";
    
    // Leer archivo SQL
    $sqlFile = __DIR__ . '/../database/whatsapp_conversacional_autonomo.sql';
    
    if (!file_exists($sqlFile)) {
        throw new Exception("Archivo SQL no encontrado: {$sqlFile}");
    }
    
    echo "✅ Leyendo archivo SQL...\n";
    
    $sql = file_get_contents($sqlFile);
    
    if (empty($sql)) {
        throw new Exception("Archivo SQL está vacío");
    }
    
    echo "✅ Archivo SQL leído correctamente (" . strlen($sql) . " caracteres)\n\n";
    
    // Dividir en statements individuales
    $statements = array_filter(explode(';', $sql), function($stmt) {
        return trim($stmt) !== '' && !preg_match('/^\s*--/', $stmt);
    });
    
    echo "🔧 Ejecutando " . count($statements) . " statements SQL...\n\n";
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $index => $statement) {
        $statement = trim($statement);
        
        if (empty($statement)) {
            continue;
        }
        
        try {
            // Skip comments and delimiters
            if (preg_match('/^(DELIMITER|--)/i', $statement)) {
                continue;
            }
            
            $stmt = $db->prepare($statement);
            $result = $stmt->execute();
            
            if ($result) {
                $successCount++;
                echo "✅ Statement " . ($index + 1) . " ejecutado\n";
                
                // Show results for some statements
                if (stripos($statement, 'SELECT') === 0) {
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        echo "   Resultado: " . json_encode($row) . "\n";
                    }
                }
            } else {
                $errorCount++;
                echo "❌ Error en statement " . ($index + 1) . "\n";
            }
            
        } catch (Exception $e) {
            $errorCount++;
            echo "❌ Error en statement " . ($index + 1) . ": " . $e->getMessage() . "\n";
            
            // No fallar por errores de IF EXISTS, etc.
            if (stripos($e->getMessage(), 'already exists') !== false ||
                stripos($e->getMessage(), 'Duplicate') !== false) {
                echo "   (Ignorando error de duplicado)\n";
            }
        }
    }
    
    echo "\n=== RESUMEN ===\n";
    echo "✅ Statements exitosos: {$successCount}\n";
    echo "❌ Statements con error: {$errorCount}\n\n";
    
    // Verificar tablas creadas
    echo "🔍 Verificando tablas creadas...\n";
    
    $tables = [
        'conversaciones_whatsapp',
        'calendario_disponibilidad', 
        'citas_pre_agendadas',
        'twilio_config'
    ];
    
    foreach ($tables as $table) {
        try {
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                echo "✅ Tabla {$table} creada\n";
                
                // Count records
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM {$table}");
                $stmt->execute();
                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "   ({$count} registros)\n";
            } else {
                echo "❌ Tabla {$table} NO existe\n";
            }
        } catch (Exception $e) {
            echo "❌ Error verificando tabla {$table}: " . $e->getMessage() . "\n";
        }
    }
    
    // Verificar configuración Twilio
    echo "\n🔍 Verificando configuración Twilio...\n";
    
    try {
        $stmt = $db->prepare("SELECT config_key, config_value FROM twilio_config WHERE is_active = TRUE ORDER BY config_key");
        $stmt->execute();
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "✅ Configuraciones encontradas: " . count($configs) . "\n";
        
        foreach ($configs as $config) {
            $value = strlen($config['config_value']) > 50 ? substr($config['config_value'], 0, 47) . '...' : $config['config_value'];
            echo "   {$config['config_key']}: {$value}\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Error verificando configuración: " . $e->getMessage() . "\n";
    }
    
    // Actualizar credenciales Twilio básicas
    echo "\n🔧 Configurando credenciales Twilio básicas...\n";
    echo "NOTA: Configurar manualmente en producción\n";
    
    $twilioCredentials = [
        'account_sid' => 'TU_TWILIO_ACCOUNT_SID_AQUI',
        'auth_token' => 'TU_TWILIO_AUTH_TOKEN_AQUI',
        'whatsapp_from' => 'whatsapp:+14155238886',
        'sag_admin_phone' => '5215519330800'
    ];
    
    foreach ($twilioCredentials as $key => $value) {
        try {
            $stmt = $db->prepare("UPDATE twilio_config SET config_value = ? WHERE config_key = ?");
            $result = $stmt->execute([$value, $key]);
            
            if ($result) {
                echo "✅ {$key} configurado\n";
            } else {
                echo "❌ Error configurando {$key}\n";
            }
        } catch (Exception $e) {
            echo "❌ Error configurando {$key}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎉 SETUP COMPLETADO!\n";
    echo "El sistema WhatsApp conversacional está listo.\n\n";
    
    echo "PRÓXIMOS PASOS:\n";
    echo "1. Configurar webhook en Twilio Console\n";
    echo "2. Configurar cron jobs en cPanel\n";
    echo "3. Probar envío de mensajes\n\n";
    
    echo "URL Webhook: https://saggarage.com.mx/gestion/backend-php/webhook/twilio_whatsapp.php\n";
    
} catch (Exception $e) {
    echo "💥 ERROR CRÍTICO: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== FIN SETUP ===\n";
?>