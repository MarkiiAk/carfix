<?php
/**
 * Script de Prueba para Webhook de WhatsApp - SAG Garage
 * 
 * Este script simula las peticiones que hará Facebook para verificar el webhook
 * Úsalo para probar que todo esté configurado correctamente
 * 
 * Uso: https://saggarage.com.mx/gestion/backend-php/test_whatsapp_webhook.php
 * 
 * @author Marco Candiani
 * @date 31/03/2026
 */

// Configurar headers
header('Content-Type: application/json');
date_default_timezone_set('America/Mexico_City');

echo "=== TEST WHATSAPP WEBHOOK - SAG GARAGE ===\n\n";

// Test 1: Verificar que las tablas existen
echo "1. VERIFICANDO TABLAS DE BASE DE DATOS...\n";

try {
    require_once __DIR__ . '/config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("No se pudo conectar a la base de datos");
    }
    
    echo "✅ Conexión a BD: EXITOSA\n";
    
    // Verificar tabla whatsapp_config
    $query = "SELECT COUNT(*) as total FROM whatsapp_config";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "✅ Tabla whatsapp_config: {$result['total']} registros\n";
    
} catch (Exception $e) {
    echo "❌ ERROR BD: " . $e->getMessage() . "\n";
    echo "\n2. Para solucionar:\n";
    echo "   - Ejecuta: database/whatsapp_schema.sql\n";
    echo "   - Ejecuta: database/whatsapp_config_inicial.sql\n\n";
    exit;
}

// Test 2: Verificar configuración del webhook
echo "\n2. VERIFICANDO CONFIGURACIÓN DE WEBHOOK...\n";

try {
    $query = "SELECT clave, valor FROM whatsapp_config WHERE clave IN ('webhook_token', 'api_token', 'phone_number_id', 'activo')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $configMap = [];
    foreach ($configs as $config) {
        $configMap[$config['clave']] = $config['valor'];
    }
    
    if (isset($configMap['webhook_token']) && !empty($configMap['webhook_token'])) {
        echo "✅ webhook_token: Configurado (" . substr($configMap['webhook_token'], 0, 10) . "...)\n";
    } else {
        echo "❌ webhook_token: NO configurado\n";
    }
    
    if (isset($configMap['api_token']) && !empty($configMap['api_token'])) {
        echo "✅ api_token: Configurado (" . substr($configMap['api_token'], 0, 10) . "...)\n";
    } else {
        echo "❌ api_token: NO configurado\n";
    }
    
    if (isset($configMap['phone_number_id']) && !empty($configMap['phone_number_id'])) {
        echo "✅ phone_number_id: {$configMap['phone_number_id']}\n";
    } else {
        echo "❌ phone_number_id: NO configurado\n";
    }
    
    if (isset($configMap['activo']) && $configMap['activo'] === 'true') {
        echo "✅ sistema activo: SÍ\n";
    } else {
        echo "⚠️ sistema activo: NO (normal durante configuración)\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR configuración: " . $e->getMessage() . "\n";
}

// Test 3: Probar el webhook simulando petición de Facebook
echo "\n3. PROBANDO WEBHOOK (Simulación de Facebook)...\n";

// Simular petición GET de verificación
$hubMode = 'subscribe';
$hubChallenge = 'test_challenge_12345';
$hubVerifyToken = $configMap['webhook_token'] ?? 'SAG_WEBHOOK_2026';

echo "Simulando verificación con token: $hubVerifyToken\n";

$_GET['hub_mode'] = $hubMode;
$_GET['hub_challenge'] = $hubChallenge;
$_GET['hub_verify_token'] = $hubVerifyToken;
$_SERVER['REQUEST_METHOD'] = 'GET';

try {
    // Capturar output del webhook
    ob_start();
    include __DIR__ . '/webhook/whatsapp_webhook.php';
    $output = ob_get_clean();
    
    if ($output === $hubChallenge) {
        echo "✅ Webhook respondió correctamente: $output\n";
        echo "✅ WEBHOOK FUNCIONANDO - Listo para Facebook!\n";
    } else {
        echo "❌ Webhook respondió incorrectamente: $output\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR en webhook: " . $e->getMessage() . "\n";
}

// Test 4: Verificar archivos necesarios
echo "\n4. VERIFICANDO ARCHIVOS...\n";

$archivos = [
    'webhook/whatsapp_webhook.php' => 'Webhook principal',
    'controllers/WhatsappController.php' => 'Controlador',
    'utils/WhatsappAPI.php' => 'Cliente API',
    '.env' => 'Variables de entorno'
];

foreach ($archivos as $archivo => $descripcion) {
    if (file_exists(__DIR__ . '/' . $archivo)) {
        echo "✅ $archivo: OK\n";
    } else {
        echo "❌ $archivo: NO ENCONTRADO\n";
    }
}

// Test 5: Test de conectividad con Facebook
echo "\n5. PROBANDO CONECTIVIDAD CON FACEBOOK...\n";

if (isset($configMap['api_token']) && !empty($configMap['api_token'])) {
    $token = $configMap['api_token'];
    $phoneId = $configMap['phone_number_id'] ?? '';
    
    if (!empty($phoneId)) {
        $url = "https://graph.facebook.com/v22.0/{$phoneId}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_USERAGENT => 'SAG-Garage-Test/1.0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $data = json_decode($response, true);
            echo "✅ Conexión con Facebook API: EXITOSA\n";
            if (isset($data['display_phone_number'])) {
                echo "✅ Número verificado: {$data['display_phone_number']}\n";
            }
            if (isset($data['verified_name'])) {
                echo "✅ Nombre verificado: {$data['verified_name']}\n";
            }
        } else {
            echo "❌ Conexión con Facebook API: ERROR (HTTP $httpCode)\n";
            echo "   Respuesta: $response\n";
        }
    } else {
        echo "❌ No se puede probar: phone_number_id no configurado\n";
    }
} else {
    echo "❌ No se puede probar: api_token no configurado\n";
}

// Resumen final
echo "\n=== RESUMEN ===\n";
echo "Webhook URL: https://saggarage.com.mx/gestion/backend-php/webhook/whatsapp_webhook.php\n";
echo "Verify Token: " . ($configMap['webhook_token'] ?? 'NO_CONFIGURADO') . "\n";
echo "\n";
echo "Próximos pasos:\n";
echo "1. Si todo está ✅, prueba la verificación en Facebook\n";
echo "2. Si hay errores ❌, revisa la configuración\n";
echo "3. Si el webhook responde correctamente, ¡está listo!\n";

?>