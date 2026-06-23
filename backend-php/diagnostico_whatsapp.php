<?php
/**
 * DIAGNÓSTICO RÁPIDO WHATSAPP - SAG GARAGE
 * Verificar qué está fallando exactamente
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNÓSTICO WHATSAPP ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// 1. VERIFICAR CONEXIÓN BD
echo "🔍 1. CONEXIÓN BASE DE DATOS\n";
try {
    require_once __DIR__ . '/config/database.php';
    
    $database = Database::getInstance();
    $db = $database->getConnection();
    
    if ($db) {
        echo "✅ Conexión BD: OK\n";
    } else {
        echo "❌ Conexión BD: FALLO\n";
        exit;
    }
} catch (Exception $e) {
    echo "❌ Error conexión BD: " . $e->getMessage() . "\n";
    exit;
}

// 2. VERIFICAR TABLAS WHATSAPP
echo "\n🔍 2. TABLAS WHATSAPP\n";
$tables = ['twilio_config', 'conversaciones_whatsapp', 'calendario_disponibilidad'];

foreach ($tables as $table) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE '{$table}'");
        $stmt->execute();
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "✅ Tabla {$table}: EXISTS\n";
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM {$table}");
            $stmt->execute();
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "   Records: {$count}\n";
        } else {
            echo "❌ Tabla {$table}: NO EXISTS\n";
        }
    } catch (Exception $e) {
        echo "❌ Error tabla {$table}: " . $e->getMessage() . "\n";
    }
}

// 3. VERIFICAR CONFIGURACIÓN TWILIO
echo "\n🔍 3. CONFIGURACIÓN TWILIO\n";
try {
    $stmt = $db->prepare("SELECT config_key, config_value FROM twilio_config WHERE is_active = TRUE");
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($configs) > 0) {
        echo "✅ Configuraciones: " . count($configs) . "\n";
        foreach ($configs as $config) {
            $value = strlen($config['config_value']) > 20 ? substr($config['config_value'], 0, 17) . '...' : $config['config_value'];
            echo "   {$config['config_key']}: {$value}\n";
        }
    } else {
        echo "❌ Sin configuraciones Twilio\n";
    }
} catch (Exception $e) {
    echo "❌ Error config Twilio: " . $e->getMessage() . "\n";
}

// 4. VERIFICAR ARCHIVOS NECESARIOS
echo "\n🔍 4. ARCHIVOS SISTEMA\n";
$files = [
    'utils/TwilioConversationalBot.php',
    'webhook/twilio_whatsapp.php',
    'config/database.php'
];

foreach ($files as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        echo "✅ {$file}: EXISTS\n";
    } else {
        echo "❌ {$file}: NO EXISTS\n";
    }
}

// 5. PROBAR CREACIÓN BOT
echo "\n🔍 5. TWILIO BOT\n";
try {
    require_once __DIR__ . '/utils/TwilioConversationalBot.php';
    
    $bot = new TwilioConversationalBot();
    if ($bot) {
        echo "✅ TwilioConversationalBot: OK\n";
    } else {
        echo "❌ TwilioConversationalBot: FALLO\n";
    }
} catch (Exception $e) {
    echo "❌ Error TwilioBot: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}

// 6. PROBAR FUNCIÓN HELPER
echo "\n🔍 6. FUNCIÓN HELPER\n";
try {
    $bot2 = crearTwilioBot();
    if ($bot2) {
        echo "✅ crearTwilioBot(): OK\n";
    } else {
        echo "❌ crearTwilioBot(): RETORNA NULL\n";
    }
} catch (Exception $e) {
    echo "❌ Error crearTwilioBot(): " . $e->getMessage() . "\n";
}

// 7. PROBAR WEBHOOK SIMPLE
echo "\n🔍 7. TEST WEBHOOK SIMPLE\n";
try {
    $_POST = [
        'From' => 'whatsapp:+14155238886',
        'Body' => 'test',
        'MessageSid' => 'test123'
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    ob_start();
    include __DIR__ . '/webhook/twilio_whatsapp.php';
    $output = ob_get_clean();
    
    echo "✅ Webhook ejecutado\n";
    echo "Output: " . substr($output, 0, 100) . "...\n";
    
} catch (Exception $e) {
    echo "❌ Error webhook: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DIAGNÓSTICO ===\n";
?>