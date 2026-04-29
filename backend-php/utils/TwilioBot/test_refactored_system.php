<?php
/**
 * Script de Testing - Sistema WhatsApp Refactorizado
 * 
 * Este script realiza un testing básico de la nueva arquitectura refactorizada
 * para verificar que todos los componentes funcionen correctamente y mantengan
 * compatibilidad con la versión original.
 * 
 * Ejecutar desde línea de comandos:
 * php test_refactored_system.php
 * 
 * @author Sistema SAG Garage - Refactorización 2026
 * @version 1.0.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Headers para CLI
if (php_sapi_name() === 'cli') {
    echo "🔧 Sistema WhatsApp Refactorizado - Testing Suite\n";
    echo "================================================\n\n";
}

// Incluir todas las clases refactorizadas
require_once __DIR__ . '/Core/TwilioClientManager.php';
require_once __DIR__ . '/Core/ConfigurationService.php';
require_once __DIR__ . '/Core/MessageLogger.php';
require_once __DIR__ . '/Services/MessageSender.php';
require_once __DIR__ . '/Repositories/AlertRepository.php';
require_once __DIR__ . '/Validators/PhoneValidator.php';
require_once __DIR__ . '/TwilioConversationalBotRefactored.php';

/**
 * Función helper para mostrar resultados de tests
 */
function testResult($testName, $success, $details = '') {
    $icon = $success ? '✅' : '❌';
    $status = $success ? 'PASS' : 'FAIL';
    
    echo "{$icon} {$testName}: {$status}\n";
    if (!empty($details)) {
        echo "   └─ {$details}\n";
    }
    echo "\n";
    
    return $success;
}

/**
 * Test 1: Verificar carga de clases Core
 */
function testCoreClassesLoading() {
    echo "📋 Testing: Carga de Clases Core\n";
    echo "================================\n";
    
    $results = [];
    
    // Test TwilioClientManager
    try {
        $clientManager = TwilioClientManager::getInstance();
        $results[] = testResult(
            'TwilioClientManager',
            $clientManager !== null,
            'Singleton pattern funcionando'
        );
    } catch (Exception $e) {
        $results[] = testResult(
            'TwilioClientManager',
            false,
            'Error: ' . $e->getMessage()
        );
    }
    
    // Test ConfigurationService
    try {
        $config = ConfigurationService::getInstance();
        $results[] = testResult(
            'ConfigurationService',
            $config !== null,
            'Configuración cargada'
        );
    } catch (Exception $e) {
        $results[] = testResult(
            'ConfigurationService',
            false,
            'Error: ' . $e->getMessage()
        );
    }
    
    // Test MessageLogger
    try {
        $logger = MessageLogger::getInstance();
        $results[] = testResult(
            'MessageLogger',
            $logger !== null,
            'Logger inicializado'
        );
    } catch (Exception $e) {
        $results[] = testResult(
            'MessageLogger',
            false,
            'Error: ' . $e->getMessage()
        );
    }
    
    return !in_array(false, $results);
}

/**
 * Test 2: Verificar servicios de negocio
 */
function testBusinessServices() {
    echo "🔧 Testing: Servicios de Negocio\n";
    echo "================================\n";
    
    $results = [];
    
    // Test MessageSender
    try {
        $messageSender = new MessageSender();
        $status = $messageSender->getServiceStatus();
        $results[] = testResult(
            'MessageSender',
            isset($status['service_ready']),
            'Status: ' . ($status['simulation_mode'] ? 'Simulación' : 'Producción')
        );
    } catch (Exception $e) {
        $results[] = testResult(
            'MessageSender',
            false,
            'Error: ' . $e->getMessage()
        );
    }
    
    // Test AlertRepository
    try {
        $alertRepo = new AlertRepository();
        $stats = $alertRepo->getEstadisticasAlertas();
        $results[] = testResult(
            'AlertRepository',
            !isset($stats['error']),
            isset($stats['total']) ? "Total alertas: {$stats['total']}" : 'BD no disponible'
        );
    } catch (Exception $e) {
        $results[] = testResult(
            'AlertRepository',
            false,
            'Error: ' . $e->getMessage()
        );
    }
    
    // Test PhoneValidator
    try {
        $phoneValidator = new PhoneValidator();
        $testPhone = $phoneValidator->cleanPhoneNumber('55-1234-5678');
        $results[] = testResult(
            'PhoneValidator',
            $testPhone === '5512345678',
            "Teléfono test: {$testPhone}"
        );
    } catch (Exception $e) {
        $results[] = testResult(
            'PhoneValidator',
            false,
            'Error: ' . $e->getMessage()
        );
    }
    
    return !in_array(false, $results);
}

/**
 * Test 3: Validación detallada de PhoneValidator
 */
function testPhoneValidator() {
    echo "📱 Testing: Validación de Teléfonos\n";
    echo "===================================\n";
    
    $phoneValidator = new PhoneValidator();
    $testCases = [
        '+52 55 1234 5678' => '5512345678',
        '(55) 1234-5678' => '5512345678',
        '55.1234.5678' => '5512345678',
        '01 55 1234 5678' => '5512345678',
        '5512345678' => '5512345678',
        '123' => null, // Muy corto
        'abc' => null, // No numérico
    ];
    
    $results = [];
    
    foreach ($testCases as $input => $expected) {
        $result = $phoneValidator->cleanPhoneNumber($input);
        $success = $result === $expected;
        
        $details = $success ? 
            "'{$input}' → '{$result}'" : 
            "'{$input}' → '{$result}' (esperado: '{$expected}')";
            
        $results[] = testResult(
            "Teléfono: {$input}",
            $success,
            $details
        );
    }
    
    // Test de validación para WhatsApp
    $whatsappTest = $phoneValidator->validateForWhatsApp('5512345678');
    $results[] = testResult(
        'Validación WhatsApp',
        $whatsappTest['valido'] === true,
        "Formato: {$whatsappTest['numero_whatsapp']}"
    );
    
    return !in_array(false, $results);
}

/**
 * Test 4: Sistema de logging
 */
function testLoggingSystem() {
    echo "📝 Testing: Sistema de Logging\n";
    echo "==============================\n";
    
    $logger = MessageLogger::getInstance();
    $results = [];
    
    // Test logging básico
    try {
        $logger->logOperation('TEST_OPERATION', ['test' => true]);
        $results[] = testResult(
            'Log Operation',
            true,
            'Operación registrada correctamente'
        );
    } catch (Exception $e) {
        $results[] = testResult(
            'Log Operation',
            false,
            'Error: ' . $e->getMessage()
        );
    }
    
    // Test conversation flow logging
    try {
        $logger->logConversationFlow(999999, 'test_step', 'testing', ['data' => 'test']);
        $results[] = testResult(
            'Conversation Flow Log',
            true,
            'Flujo conversacional registrado'
        );
    } catch (Exception $e) {
        $results[] = testResult(
            'Conversation Flow Log',
            false,
            'Error: ' . $e->getMessage()
        );
    }
    
    // Test error logging
    try {
        $testException = new Exception('Test exception for logging');
        $logger->logError('Test error message', $testException, ['context' => 'testing']);
        $results[] = testResult(
            'Error Logging',
            true,
            'Error registrado con contexto'
        );
    } catch (Exception $e) {
        $results[] = testResult(
            'Error Logging',
            false,
            'Error: ' . $e->getMessage()
        );
    }
    
    return !in_array(false, $results);
}

/**
 * Test 5: Bot conversacional refactorizado
 */
function testRefactoredBot() {
    echo "🤖 Testing: Bot Conversacional Refactorizado\n";
    echo "============================================\n";
    
    $results = [];
    
    // Test inicialización del bot
    try {
        $bot = new TwilioConversationalBot();
        $status = $bot->getServiceStatus();
        
        $results[] = testResult(
            'Inicialización Bot',
            isset($status['version']) && $status['version'] === '2.0.0 - Refactorizado',
            "Versión: {$status['version']}"
        );
        
        $results[] = testResult(
            'Servicios Ready',
            $status['services_ready'] === true,
            'Todos los servicios inicializados'
        );
        
        // Test envío en modo simulación (sin alerta real)
        // Solo verificamos que el método esté disponible
        $results[] = testResult(
            'Método enviarRecordatorioInicial',
            method_exists($bot, 'enviarRecordatorioInicial'),
            'Método disponible con interfaz compatible'
        );
        
        $results[] = testResult(
            'Método procesarRespuestaInicial',
            method_exists($bot, 'procesarRespuestaInicial'),
            'Método disponible con interfaz compatible'
        );
        
    } catch (Exception $e) {
        $results[] = testResult(
            'Bot Initialization',
            false,
            'Error: ' . $e->getMessage()
        );
    }
    
    return !in_array(false, $results);
}

/**
 * Test 6: Compatibilidad con versión original
 */
function testCompatibility() {
    echo "🔄 Testing: Compatibilidad con Versión Original\n";
    echo "===============================================\n";
    
    $results = [];
    
    // Verificar que la clase refactorizada tenga la misma interfaz
    $originalMethods = ['enviarRecordatorioInicial', 'procesarRespuestaInicial'];
    
    foreach ($originalMethods as $method) {
        $hasMethod = method_exists('TwilioConversationalBot', $method);
        $results[] = testResult(
            "Método '{$method}'",
            $hasMethod,
            $hasMethod ? 'Interfaz compatible mantenida' : 'Método no encontrado'
        );
    }
    
    // Verificar que la clase se pueda instanciar igual que la original
    try {
        $bot = new TwilioConversationalBot();
        $results[] = testResult(
            'Constructor Compatible',
            true,
            'Se puede instanciar sin parámetros'
        );
    } catch (Exception $e) {
        $results[] = testResult(
            'Constructor Compatible',
            false,
            'Error: ' . $e->getMessage()
        );
    }
    
    return !in_array(false, $results);
}

/**
 * Función principal de testing
 */
function runAllTests() {
    echo "🚀 Iniciando Testing Suite Completo\n";
    echo "====================================\n\n";
    
    $testResults = [];
    
    // Ejecutar todos los tests
    $testResults['Core Classes'] = testCoreClassesLoading();
    $testResults['Business Services'] = testBusinessServices();
    $testResults['Phone Validator'] = testPhoneValidator();
    $testResults['Logging System'] = testLoggingSystem();
    $testResults['Refactored Bot'] = testRefactoredBot();
    $testResults['Compatibility'] = testCompatibility();
    
    // Mostrar resumen final
    echo "📊 RESUMEN FINAL DE TESTING\n";
    echo "===========================\n";
    
    $totalTests = count($testResults);
    $passedTests = count(array_filter($testResults));
    $failedTests = $totalTests - $passedTests;
    
    foreach ($testResults as $testSuite => $passed) {
        $icon = $passed ? '✅' : '❌';
        $status = $passed ? 'PASS' : 'FAIL';
        echo "{$icon} {$testSuite}: {$status}\n";
    }
    
    echo "\n";
    echo "📈 ESTADÍSTICAS:\n";
    echo "   • Total test suites: {$totalTests}\n";
    echo "   • Exitosos: {$passedTests}\n";
    echo "   • Fallidos: {$failedTests}\n";
    echo "   • Porcentaje éxito: " . round(($passedTests / $totalTests) * 100, 1) . "%\n\n";
    
    if ($passedTests === $totalTests) {
        echo "🎉 ¡TODOS LOS TESTS PASARON!\n";
        echo "   Sistema refactorizado funcional y compatible.\n";
        echo "   ✅ Listo para producción.\n\n";
        return true;
    } else {
        echo "⚠️  ALGUNOS TESTS FALLARON\n";
        echo "   Revisar errores antes de proceder.\n";
        echo "   ❌ No listo para producción.\n\n";
        return false;
    }
}

/**
 * Función para mostrar información del sistema
 */
function showSystemInfo() {
    echo "ℹ️  INFORMACIÓN DEL SISTEMA\n";
    echo "==========================\n";
    echo "• PHP Version: " . PHP_VERSION . "\n";
    echo "• OS: " . PHP_OS . "\n";
    echo "• Memory Limit: " . ini_get('memory_limit') . "\n";
    echo "• Execution Time: " . ini_get('max_execution_time') . "s\n";
    echo "• Error Reporting: " . (error_reporting() ? 'ON' : 'OFF') . "\n";
    echo "• Working Directory: " . getcwd() . "\n";
    echo "• Script: " . __FILE__ . "\n\n";
}

// Ejecutar testing si el script se ejecuta directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME']) || php_sapi_name() === 'cli') {
    showSystemInfo();
    
    $startTime = microtime(true);
    $allTestsPassed = runAllTests();
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 3);
    
    echo "⏱️  Tiempo de ejecución: {$executionTime}s\n\n";
    
    if (php_sapi_name() === 'cli') {
        exit($allTestsPassed ? 0 : 1);
    }
}