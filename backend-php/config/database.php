<?php
/**
 * Configuración de Base de Datos - Singleton Pattern
 * Sistema de detección automática de entorno (Local vs Producción)
 */

class Database {
    private static $instance = null;
    private $connection;
    
    // Configuración de base de datos con detección automática
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    
    private function __construct() {
        // Cargar configuración según entorno
        $this->loadEnvironmentConfig();
        
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
            // Log del entorno detectado (solo en desarrollo)
            if ($this->isLocalEnvironment()) {
                error_log('SAG Garage: Conectado a entorno LOCAL (XAMPP)');
            }
            
        } catch (PDOException $e) {
            error_log('SAG Garage: Error de conexión a base de datos');
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Error de conexión a la base de datos'
            ]));
        }
    }
    
    /**
     * Carga configuración según entorno detectado
     * Prioridad: .env.local > .env > credenciales hardcodeadas
     */
    private function loadEnvironmentConfig() {
        $baseDir = __DIR__ . '/..';
        
        // 1. PRIORIDAD MÁXIMA: .env.local (desarrollo local)
        if (file_exists($baseDir . '/.env.local')) {
            $this->loadEnvFile($baseDir . '/.env.local');
            error_log('SAG Garage: Usando .env.local (desarrollo)');
        }
        // 2. SEGUNDA PRIORIDAD: .env (producción)
        else if (file_exists($baseDir . '/.env')) {
            $this->loadEnvFile($baseDir . '/.env');
        }
        
        // 3. Configurar con variables de entorno o fallbacks
        $this->host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
        $this->dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'saggarag_GestionPresupuestos';
        $this->username = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'saggarag_admin';
        
        // Manejar contraseña vacía correctamente para XAMPP
        $password = $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? getenv('DB_PASSWORD') ?: getenv('DB_PASS');
        
        // Asegurar que contraseñas realmente vacías no se interpreten como "usando password"
        if ($password === '' || $password === null || trim($password) === '') {
            $this->password = '';
        } else {
            if (!$password) {
                throw new Exception('DB_PASSWORD no configurado en el entorno. Verifica el archivo .env del servidor.');
            }
            $this->password = $password;
        }
    }
    
    /**
     * Carga archivo .env y establece variables de entorno
     */
    private function loadEnvFile($filePath) {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Ignorar comentarios y líneas vacías
            if (strpos(trim($line), '#') === 0 || empty(trim($line))) {
                continue;
            }
            
            // Parsear línea KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remover comillas si existen
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }
                
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
        
        return true;
    }
    
    /**
     * Detecta si estamos en entorno local
     */
    private function isLocalEnvironment() {
        return file_exists(__DIR__ . '/../.env.local') || 
               $this->username === 'root' || 
               strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
               strpos($_SERVER['SERVER_NAME'] ?? '', 'localhost') !== false;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Prevenir clonación
    private function __clone() {}
    
    // Prevenir deserialización
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}