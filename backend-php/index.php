<?php
/**
 * SAG Garage - Backend API PHP
 * Compatible con cPanel / Hosting compartido
 */

// Cargar variables de entorno si existe el archivo .env
error_log('=== ENV LOADING DEBUG ===');
error_log('Looking for .env file at: ' . __DIR__ . '/.env');
error_log('File exists: ' . (file_exists(__DIR__ . '/.env') ? 'YES' : 'NO'));

if (file_exists(__DIR__ . '/.env')) {
    error_log('Loading .env file...');
    $envVars = parse_ini_file(__DIR__ . '/.env', false, INI_SCANNER_NORMAL);
    error_log('Parsed .env contents: ' . print_r($envVars, true));
    
    if ($envVars) {
        foreach ($envVars as $key => $value) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
            error_log("Set env var: $key = $value");
        }
    } else {
        error_log('ERROR: Could not parse .env file');
    }
} else {
    error_log('No .env file found, using defaults');
}

error_log('Final $_ENV DB vars:');
error_log('DB_HOST: ' . ($_ENV['DB_HOST'] ?? 'NOT SET'));
error_log('DB_NAME: ' . ($_ENV['DB_NAME'] ?? 'NOT SET'));
error_log('DB_USER: ' . ($_ENV['DB_USER'] ?? 'NOT SET'));
error_log('DB_PASS length: ' . strlen($_ENV['DB_PASS'] ?? ''));

// Configuración de CORS - Detecta automáticamente desarrollo vs producción
$allowedOrigins = [
    'https://saggarage.com',           // Producción
    'https://www.saggarage.com',       // Producción con www
    'http://localhost:3000',           // Desarrollo local
    'http://localhost:3001',           // Desarrollo local puerto alternativo
    'http://127.0.0.1:3000',          // Desarrollo local alternativo
    'http://127.0.0.1:3001'           // Desarrollo local alternativo puerto 2
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Fallback para desarrollo local sin origin header
    header('Access-Control-Allow-Origin: http://localhost:3000');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Cargar configuración
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jwt.php';

// Cargar controladores
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/OrdenesController.php';
require_once __DIR__ . '/controllers/EstadosSeguridadController.php';
require_once __DIR__ . '/controllers/PuntosSeguridadController.php';
require_once __DIR__ . '/controllers/AlertasController.php';
require_once __DIR__ . '/controllers/WhatsappController.php';

// Obtener conexión a base de datos
$db = Database::getInstance()->getConnection();

// Obtener la ruta y método
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remover query string y obtener path
$path = parse_url($request_uri, PHP_URL_PATH);

// Remover prefijos según el entorno
$path = str_replace('/gestion/backend-php/', '', $path);
$path = str_replace('/backend-php/', '', $path);
$path = trim($path, '/');


// Router simple
try {
    // Rutas de autenticación
    if ($path === 'auth/login' && $request_method === 'POST') {
        $controller = new AuthController();
        $controller->login();
    }
    elseif ($path === 'auth/verify' && $request_method === 'GET') {
        $controller = new AuthController();
        $controller->verify();
    }
    elseif ($path === 'auth/me' && $request_method === 'GET') {
        $controller = new AuthController();
        $controller->me();
    }
    
    // Rutas de órdenes
    elseif ($path === 'ordenes' && $request_method === 'GET') {
        $controller = new OrdenesController();
        $controller->getAll();
    }
    elseif (preg_match('#^ordenes/([0-9]+)$#', $path, $matches) && $request_method === 'GET') {
        $controller = new OrdenesController();
        $controller->getById($matches[1]);
    }
    elseif ($path === 'ordenes' && $request_method === 'POST') {
        $controller = new OrdenesController();
        $controller->create();
    }
    elseif (preg_match('#^ordenes/([0-9]+)$#', $path, $matches) && $request_method === 'PUT') {
        $controller = new OrdenesController();
        $controller->update($matches[1]);
    }
    elseif (preg_match('#^ordenes/([0-9]+)$#', $path, $matches) && $request_method === 'DELETE') {
        $controller = new OrdenesController();
        $controller->delete($matches[1]);
    }
    
    // Rutas de Estados de Seguridad
    elseif ($path === 'estados-seguridad' && $request_method === 'GET') {
        $controller = new EstadosSeguridadController($db);
        $controller->getEstados();
    }
    elseif (preg_match('#^estados-seguridad/([0-9]+)$#', $path, $matches) && $request_method === 'GET') {
        $controller = new EstadosSeguridadController($db);
        $controller->getEstadoById($matches[1]);
    }
    elseif ($path === 'admin/estados-seguridad' && $request_method === 'POST') {
        $controller = new EstadosSeguridadController($db);
        $data = json_decode(file_get_contents('php://input'), true);
        $controller->createEstado($data);
    }
    elseif (preg_match('#^admin/estados-seguridad/([0-9]+)$#', $path, $matches) && $request_method === 'PUT') {
        $controller = new EstadosSeguridadController($db);
        $data = json_decode(file_get_contents('php://input'), true);
        $controller->updateEstado($matches[1], $data);
    }
    elseif (preg_match('#^admin/estados-seguridad/([0-9]+)$#', $path, $matches) && $request_method === 'DELETE') {
        $controller = new EstadosSeguridadController($db);
        $controller->deleteEstado($matches[1]);
    }
    
    // Rutas de Puntos de Seguridad
    elseif ($path === 'puntos-seguridad/catalogo' && $request_method === 'GET') {
        $controller = new PuntosSeguridadController($db);
        $controller->getCatalogo();
    }
    elseif (preg_match('#^puntos-seguridad/catalogo/([0-9]+)$#', $path, $matches) && $request_method === 'GET') {
        $controller = new PuntosSeguridadController($db);
        $controller->getPuntoById($matches[1]);
    }
    elseif (preg_match('#^ordenes/([0-9]+)/puntos-seguridad$#', $path, $matches) && $request_method === 'GET') {
        $controller = new PuntosSeguridadController($db);
        $controller->getPuntosByOrden($matches[1]);
    }
    elseif (preg_match('#^ordenes/([0-9]+)/puntos-seguridad$#', $path, $matches) && $request_method === 'POST') {
        $controller = new PuntosSeguridadController($db);
        $data = json_decode(file_get_contents('php://input'), true);
        $controller->savePuntosByOrden($matches[1], $data);
    }
    elseif ($path === 'admin/puntos-seguridad/catalogo' && $request_method === 'POST') {
        $controller = new PuntosSeguridadController($db);
        $data = json_decode(file_get_contents('php://input'), true);
        $controller->createPunto($data);
    }
    elseif (preg_match('#^admin/puntos-seguridad/catalogo/([0-9]+)$#', $path, $matches) && $request_method === 'PUT') {
        $controller = new PuntosSeguridadController($db);
        $data = json_decode(file_get_contents('php://input'), true);
        $controller->updatePunto($matches[1], $data);
    }
    elseif (preg_match('#^admin/puntos-seguridad/catalogo/([0-9]+)$#', $path, $matches) && $request_method === 'DELETE') {
        $controller = new PuntosSeguridadController($db);
        $controller->deletePunto($matches[1]);
    }
    
    // Rutas de Alertas de Servicio (requieren autorización especial)
    elseif ($path === 'alertas' && $request_method === 'GET') {
        $userData = requireAuth();
        $controller = new AlertasController($db);
        $result = $controller->obtenerAlertas($userData);
        echo json_encode($result);
    }
    elseif (preg_match('#^alertas/([0-9]+)/marcar-leida$#', $path, $matches) && $request_method === 'PUT') {
        $userData = requireAuth();
        $controller = new AlertasController($db);
        $result = $controller->marcarComoLeida($matches[1], $userData);
        echo json_encode($result);
    }
    elseif ($path === 'alertas/generar' && $request_method === 'GET') {
        $userData = requireAuth();
        $controller = new AlertasController($db);
        $result = $controller->generarAlertas($userData);
        echo json_encode($result);
    }
    elseif ($path === 'alertas/generar-automatico' && $request_method === 'POST') {
        $userData = requireAuth();
        $controller = new AlertasController($db);
        $result = $controller->generarAlertasAutomatico($userData);
        echo json_encode($result);
    }
    elseif ($path === 'alertas/estadisticas' && $request_method === 'GET') {
        $userData = requireAuth();
        $controller = new AlertasController($db);
        $result = $controller->obtenerEstadisticas($userData);
        echo json_encode($result);
    }
    
    // Rutas de WhatsApp (requieren autorización especial)
    elseif ($path === 'whatsapp/dashboard' && $request_method === 'GET') {
        $userData = requireAuth();
        $controller = new WhatsappController($db);
        $result = $controller->obtenerEstadisticas();
        echo json_encode($result);
    }
    elseif ($path === 'whatsapp/logs' && $request_method === 'GET') {
        $userData = requireAuth();
        $controller = new WhatsappController($db);
        $limite = $_GET['limite'] ?? 50;
        $result = $controller->obtenerLogReciente($limite);
        echo json_encode(['success' => true, 'logs' => $result]);
    }
    elseif ($path === 'whatsapp/test-conexion' && $request_method === 'GET') {
        $userData = requireAuth();
        $controller = new WhatsappController($db);
        $result = $controller->testConexion();
        echo json_encode($result);
    }
    elseif ($path === 'whatsapp/procesar-cola' && $request_method === 'POST') {
        $userData = requireAuth();
        $controller = new WhatsappController($db);
        $result = $controller->procesarColaWhatsApp();
        echo json_encode($result);
    }
    elseif ($path === 'whatsapp/configuracion' && $request_method === 'GET') {
        $userData = requireAuth();
        $controller = new WhatsappController($db);
        $config = [
            'sistema_activo' => $controller->isSistemaActivo(),
            'limite_diario' => $controller->getConfig('limite_mensajes_dia', 100),
            'dias_cooldown' => $controller->getConfig('dias_cooldown', 30),
            'template_default' => $controller->getConfig('template_default', 'recordatorio_general')
        ];
        echo json_encode(['success' => true, 'configuracion' => $config]);
    }
    elseif ($path === 'whatsapp/configuracion' && $request_method === 'PUT') {
        $userData = requireAuth();
        $controller = new WhatsappController($db);
        $data = json_decode(file_get_contents('php://input'), true);
        
        $results = [];
        foreach ($data as $clave => $valor) {
            $results[$clave] = $controller->updateConfig($clave, $valor);
        }
        
        echo json_encode(['success' => true, 'updated' => $results]);
    }
    elseif ($path === 'whatsapp/blacklist' && $request_method === 'POST') {
        $userData = requireAuth();
        $controller = new WhatsappController($db);
        $data = json_decode(file_get_contents('php://input'), true);
        
        $result = $controller->agregarABlacklist(
            $data['telefono'], 
            $data['motivo'], 
            $userData['id'] ?? null
        );
        
        echo json_encode(['success' => $result, 'message' => $result ? 'Número agregado a blacklist' : 'Error al agregar número']);
    }
    
    // Ruta de salud
    elseif ($path === 'health' && $request_method === 'GET') {
        echo json_encode([
            'status' => 'ok',
            'database' => 'MySQL conectado',
            'timestamp' => time()
        ]);
    }
    
    // ENDPOINT TEMPORAL DE DEBUG
    elseif ($path === 'debug/env' && $request_method === 'GET') {
        echo json_encode([
            'env_file_exists' => file_exists(__DIR__ . '/.env'),
            'env_file_path' => __DIR__ . '/.env',
            'db_host' => $_ENV['DB_HOST'] ?? 'NOT SET',
            'db_name' => $_ENV['DB_NAME'] ?? 'NOT SET', 
            'db_user' => $_ENV['DB_USER'] ?? 'NOT SET',
            'db_pass_length' => strlen($_ENV['DB_PASS'] ?? ''),
            'current_dir' => __DIR__,
            'all_env_vars' => $_ENV,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
        ]);
    }
    
    // Ruta no encontrada
    else {
        http_response_code(404);
        echo json_encode(['error' => 'Ruta no encontrada']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ]);
}