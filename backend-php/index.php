<?php
/**
 * SAG Garage - Backend API PHP
 * Compatible con cPanel / Hosting compartido
 */

// Configuración de CORS con detección automática de entorno
function getAllowedOrigin() {
    // Detectar entorno local usando el mismo sistema que database.php
    $isLocalEnvironment = file_exists(__DIR__ . '/.env.local') || 
                         strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
                         strpos($_SERVER['SERVER_NAME'] ?? '', 'localhost') !== false;
    
    if ($isLocalEnvironment) {
        // Desarrollo local: permitir localhost:3000
        $allowedOrigins = [
            'http://localhost:3000',
            'http://127.0.0.1:3000'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        return in_array($origin, $allowedOrigins) ? $origin : 'http://localhost:3000';
    } else {
        // Producción: solo saggarage.com.mx
        return 'https://saggarage.com.mx';
    }
}

$allowedOrigin = getAllowedOrigin();
header("Access-Control-Allow-Origin: $allowedOrigin");
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Cargar configuración
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/jwt.php';
require_once __DIR__ . '/config/helpers.php';

// Cargar controladores
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/OrdenesController.php';
require_once __DIR__ . '/controllers/EstadosSeguridadController.php';
require_once __DIR__ . '/controllers/PuntosSeguridadController.php';
require_once __DIR__ . '/controllers/AlertasController.php';
require_once __DIR__ . '/controllers/ClientesController.php';
require_once __DIR__ . '/controllers/FinancieroController.php';
require_once __DIR__ . '/controllers/SucursalesController.php';
require_once __DIR__ . '/controllers/UsuariosController.php';
// require_once __DIR__ . '/controllers/WhatsappController.php'; // No necesario - usando TwilioConversationalBot

// Obtener conexión a base de datos
$db = Database::getInstance()->getConnection();

// Obtener la ruta y método
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remover query string y obtener path
$path = parse_url($request_uri, PHP_URL_PATH);

// Remover el prefijo /[entorno]/backend-php/ de la URL (funciona para /gestion/ y /staging/)
$path = preg_replace('#^.*/backend-php/#', '', $path);
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
    elseif ($path === 'auth/switch-sucursal' && $request_method === 'POST') {
        $controller = new AuthController();
        $controller->switchSucursal();
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
    elseif (preg_match('#^alertas/([0-9]+)/conversacion$#', $path, $matches) && $request_method === 'GET') {
        $userData = requireAuth();
        $controller = new AlertasController($db);
        $result = $controller->obtenerConversacion($matches[1], $userData);
        echo json_encode($result);
    }
    elseif ($path === 'alertas/generar' && $request_method === 'GET') {
        $userData = requireAuth();
        $controller = new AlertasController($db);
        $result = $controller->generarAlertas($userData);
        echo json_encode($result);
    }
    elseif ($path === 'alertas/estadisticas' && $request_method === 'GET') {
        $userData = requireAuth();
        $controller = new AlertasController($db);
        $result = $controller->obtenerEstadisticas($userData);
        echo json_encode($result);
    }
    
    // Rutas de WhatsApp comentadas - usando TwilioConversationalBot directamente
    /*
    elseif ($path === 'whatsapp/dashboard' && $request_method === 'GET') {
        $userData = requireAuth();
        $controller = new WhatsappController($db);
        $result = $controller->obtenerEstadisticas();
        echo json_encode($result);
    }
    */
    
    // Rutas de Clientes
    // IMPORTANTE: las rutas literales (buscar, buscar-por-telefono) deben ir ANTES del patrón /:id
    elseif ($path === 'clientes/buscar' && $request_method === 'GET') {
        $controller = new ClientesController();
        $controller->buscar();
    }
    elseif ($path === 'clientes/buscar-por-telefono' && $request_method === 'GET') {
        $controller = new ClientesController();
        $controller->buscarPorTelefono();
    }
    elseif ($path === 'clientes' && $request_method === 'GET') {
        $controller = new ClientesController();
        $controller->listar();
    }
    elseif (preg_match('#^clientes/([0-9]+)$#', $path, $matches) && $request_method === 'GET') {
        $controller = new ClientesController();
        $controller->perfil($matches[1]);
    }
    elseif (preg_match('#^clientes/([0-9]+)$#', $path, $matches) && $request_method === 'PUT') {
        $controller = new ClientesController();
        $controller->update($matches[1]);
    }

    // Módulo Financiero / Ingresos
    elseif ($path === 'financiero' && $request_method === 'GET') {
        // requireAuth() se llama internamente en resumen()
        $controller = new FinancieroController();
        $controller->resumen();
    }

    // Gastos internos por orden
    elseif ($path === 'financiero/gastos-orden' && $request_method === 'GET') {
        $userData = requireAuth();
        $ordenId  = isset($_GET['orden_id']) ? (int) $_GET['orden_id'] : 0;
        $controller = new FinancieroController();
        $controller->gastosOrden($ordenId, $userData);
    }
    elseif ($path === 'financiero/gastos-orden' && $request_method === 'POST') {
        $userData = requireAuth();
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller = new FinancieroController();
        $controller->crearGastoOrden($body, $userData);
    }
    elseif (preg_match('#^financiero/gastos-orden/([0-9]+)$#', $path, $matches) && $request_method === 'DELETE') {
        $userData = requireAuth();
        $controller = new FinancieroController();
        $controller->eliminarGastoOrden((int) $matches[1], $userData);
    }

    // Gastos administrativos del taller (renta, salarios, etc.)
    elseif ($path === 'financiero/gastos-admin' && $request_method === 'GET') {
        $userData = requireAuth();
        $mes  = isset($_GET['mes'])  ? (int) $_GET['mes']  : (int) date('n');
        $anio = isset($_GET['anio']) ? (int) $_GET['anio'] : (int) date('Y');
        $controller = new FinancieroController();
        $controller->gastosAdmin($mes, $anio, $userData);
    }
    elseif ($path === 'financiero/gastos-admin' && $request_method === 'POST') {
        $userData = requireAuth();
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller = new FinancieroController();
        $controller->crearGastoAdmin($body, $userData);
    }
    elseif (preg_match('#^financiero/gastos-admin/([0-9]+)$#', $path, $matches) && $request_method === 'DELETE') {
        $userData = requireAuth();
        $controller = new FinancieroController();
        $controller->eliminarGastoAdmin((int) $matches[1], $userData);
    }

    // Órdenes desglosadas por período
    elseif ($path === 'financiero/ordenes' && $request_method === 'GET') {
        // requireAuth() se llama internamente en ordenesDesglosadas()
        $controller = new FinancieroController();
        $controller->ordenesDesglosadas();
    }

    // Empleados y sueldos
    elseif ($path === 'financiero/empleados' && $request_method === 'GET') {
        // requireAuth() se llama internamente en empleadosSueldos()
        $controller = new FinancieroController();
        $controller->empleadosSueldos();
    }
    elseif ($path === 'financiero/empleados' && $request_method === 'POST') {
        $userData = requireAuth();
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller = new FinancieroController();
        $controller->crearEmpleado($body, $userData);
    }
    elseif (preg_match('#^financiero/empleados/([0-9]+)/asistencia$#', $path, $matches) && $request_method === 'PUT') {
        $userData = requireAuth();
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller = new FinancieroController();
        $controller->asistenciaEmpleado((int) $matches[1], $body, $userData);
    }
    elseif (preg_match('#^financiero/empleados/([0-9]+)/toggle$#', $path, $matches) && $request_method === 'PUT') {
        $userData = requireAuth();
        $controller = new FinancieroController();
        $controller->toggleEmpleado((int) $matches[1], $userData);
    }
    elseif (preg_match('#^financiero/empleados/([0-9]+)$#', $path, $matches) && $request_method === 'PUT') {
        $userData = requireAuth();
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller = new FinancieroController();
        $controller->actualizarEmpleado((int) $matches[1], $body, $userData);
    }

    // Pagos fijos del taller
    elseif ($path === 'financiero/pagos-fijos' && $request_method === 'GET') {
        // requireAuth() se llama internamente en pagosFijos()
        $controller = new FinancieroController();
        $controller->pagosFijos();
    }
    elseif ($path === 'financiero/pagos-fijos' && $request_method === 'POST') {
        $userData = requireAuth();
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller = new FinancieroController();
        $controller->crearPagoFijo($body, $userData);
    }
    elseif (preg_match('#^financiero/pagos-fijos/([0-9]+)/toggle$#', $path, $matches) && $request_method === 'PUT') {
        $userData = requireAuth();
        $controller = new FinancieroController();
        $controller->togglePagoFijo((int) $matches[1], $userData);
    }
    elseif (preg_match('#^financiero/pagos-fijos/([0-9]+)$#', $path, $matches) && $request_method === 'PUT') {
        $userData = requireAuth();
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $controller = new FinancieroController();
        $controller->actualizarPagoFijo((int) $matches[1], $body, $userData);
    }

    // Caja chica
    elseif ($path === 'financiero/caja-chica' && $request_method === 'GET') {
        // requireAuth() se llama internamente en cajaChica()
        $controller = new FinancieroController();
        $controller->cajaChica();
    }
    elseif ($path === 'financiero/caja-chica' && $request_method === 'POST') {
        // requireAuth() se llama internamente en crearMovimientoCajaChica()
        $controller = new FinancieroController();
        $controller->crearMovimientoCajaChica();
    }
    elseif (preg_match('#^financiero/caja-chica/([0-9]+)$#', $path, $matches) && $request_method === 'DELETE') {
        // requireAuth() se llama internamente en eliminarMovimientoCajaChica()
        $controller = new FinancieroController();
        $controller->eliminarMovimientoCajaChica((int) $matches[1]);
    }

    // -----------------------------------------------------------------------
    // Admin — Sucursales (requiere rol sistemas)
    // -----------------------------------------------------------------------
    elseif ($path === 'admin/sucursales' && $request_method === 'GET') {
        $controller = new SucursalesController();
        $controller->listar();
    }
    elseif ($path === 'admin/sucursales' && $request_method === 'POST') {
        $controller = new SucursalesController();
        $controller->crear();
    }
    elseif (preg_match('#^admin/sucursales/([0-9]+)$#', $path, $matches) && $request_method === 'PUT') {
        $controller = new SucursalesController();
        $controller->actualizar((int) $matches[1]);
    }
    elseif (preg_match('#^admin/sucursales/([0-9]+)$#', $path, $matches) && $request_method === 'DELETE') {
        $controller = new SucursalesController();
        $controller->eliminar((int) $matches[1]);
    }

    // -----------------------------------------------------------------------
    // Admin — Usuarios (requiere rol sistemas)
    // -----------------------------------------------------------------------
    elseif ($path === 'admin/usuarios' && $request_method === 'GET') {
        $controller = new UsuariosController();
        $controller->listar();
    }
    elseif ($path === 'admin/usuarios' && $request_method === 'POST') {
        $controller = new UsuariosController();
        $controller->crear();
    }
    elseif (preg_match('#^admin/usuarios/([0-9]+)$#', $path, $matches) && $request_method === 'PUT') {
        $controller = new UsuariosController();
        $controller->actualizar((int) $matches[1]);
    }
    elseif (preg_match('#^admin/usuarios/([0-9]+)$#', $path, $matches) && $request_method === 'DELETE') {
        $controller = new UsuariosController();
        $controller->eliminar((int) $matches[1]);
    }
    elseif (preg_match('#^admin/usuarios/([0-9]+)/sucursal$#', $path, $matches) && $request_method === 'POST') {
        $controller = new UsuariosController();
        $controller->asignarSucursal((int) $matches[1]);
    }
    elseif (preg_match('#^admin/usuarios/([0-9]+)/sucursal/([0-9]+)$#', $path, $matches) && $request_method === 'DELETE') {
        $controller = new UsuariosController();
        $controller->removerSucursal((int) $matches[1], (int) $matches[2]);
    }

    // Ruta de salud (requiere autenticación para no exponer información a usuarios anónimos)
    elseif ($path === 'health' && $request_method === 'GET') {
        requireAuth();
        echo json_encode([
            'status' => 'ok',
            'database' => 'MySQL conectado',
            'timestamp' => time()
        ]);
    }
    
    // Ruta no encontrada
    else {
        http_response_code(404);
        echo json_encode(['error' => 'Ruta no encontrada']);
    }
    
} catch (Exception $e) {
    jsonError('Error interno del servidor', $e, 500);
}