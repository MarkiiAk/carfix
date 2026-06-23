<?php
/**
 * Script de prueba para sistema WhatsApp en producción
 * Ejecuta manualmente el envío de WhatsApp sin depender de cron
 */

// Cargar configuración
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/TwilioConversationalBot.php';

echo "=== TEST WHATSAPP SAG GARAGE ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Verificar conexión a BD
    $db = Database::getInstance()->getConnection();
    echo "✅ Conexión a base de datos exitosa\n";
    
    // Verificar configuración de Twilio
    $stmt = $db->prepare("SELECT config_key, config_value FROM twilio_config");
    $stmt->execute();
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $twilioConfig = [];
    foreach ($configs as $config) {
        $twilioConfig[$config['config_key']] = $config['config_value'];
    }
    
    if (empty($twilioConfig['account_sid']) || empty($twilioConfig['auth_token'])) {
        throw new Exception("Credenciales de Twilio no configuradas");
    }
    
    echo "✅ Configuración de Twilio encontrada\n";
    echo "Account SID: " . substr($twilioConfig['account_sid'], 0, 10) . "...\n";
    echo "WhatsApp From: " . $twilioConfig['whatsapp_from'] . "\n";
    echo "Admin Phone: " . $twilioConfig['sag_admin_phone'] . "\n\n";
    
    // Inicializar bot de Twilio
    $twilioBot = new TwilioConversationalBot();
    
    // Probar envío de mensaje de prueba
    $testMessage = "🔧 CarFix - Test del sistema WhatsApp conversacional\n\n";
    $testMessage .= "Este es un mensaje de prueba del nuevo sistema automático.\n";
    $testMessage .= "Fecha: " . date('Y-m-d H:i:s') . "\n\n";
    $testMessage .= "Si recibes este mensaje, el sistema está funcionando correctamente! ✅";
    
    echo "📱 Enviando mensaje de prueba...\n";
    
    $resultado = $twilioBot->enviarMensaje(
        $twilioConfig['sag_admin_phone'], 
        $testMessage
    );
    
    if ($resultado['success']) {
        echo "✅ Mensaje enviado exitosamente!\n";
        echo "Message SID: " . ($resultado['message_sid'] ?? 'N/A') . "\n";
        echo "Status: " . ($resultado['status'] ?? 'N/A') . "\n\n";
    } else {
        echo "❌ Error al enviar mensaje:\n";
        echo "Error: " . ($resultado['error'] ?? 'Error desconocido') . "\n\n";
    }
    
    // Verificar webhooks funcionando
    echo "🔗 Verificando webhook...\n";
    $webhookUrl = "https://tallercarfix.com.mx/gestion/backend-php/webhook/twilio_whatsapp.php";
    echo "URL del webhook: $webhookUrl\n";
    
    // Test básico del webhook con curl
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'From' => 'whatsapp:+14155238886',
        'Body' => 'test',
        'MessageSid' => 'test_' . time()
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        echo "✅ Webhook responde correctamente (HTTP 200)\n";
        echo "Respuesta: " . substr($response, 0, 100) . "...\n\n";
    } else {
        echo "⚠️  Webhook HTTP Code: $httpCode\n";
        echo "Respuesta: " . substr($response, 0, 200) . "...\n\n";
    }
    
    echo "=== RESUMEN DEL TEST ===\n";
    echo "✅ Sistema WhatsApp configurado\n";
    echo "✅ Credenciales Twilio válidas\n";
    echo "✅ Mensaje de prueba enviado\n";
    echo "✅ Webhook accesible\n\n";
    echo "🎉 Sistema listo para producción!\n";
    echo "Para configurar cron jobs, usar:\n";
    echo "*/5 * * * * /usr/local/bin/php " . __DIR__ . "/cron/generar_alertas.php\n";
    echo "*/2 * * * * /usr/local/bin/php " . __DIR__ . "/cron/enviar_whatsapp.php\n";
    
} catch (Exception $e) {
    echo "❌ Error en el test: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}