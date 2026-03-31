<?php
/**
 * Configuración de Base de Datos - Singleton Pattern
 */

class Database {
    private static $instance = null;
    private $connection;
    
    // Configuración de base de datos - USA VARIABLES DE ENTORNO EN PRODUCCIÓN
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    
    private function __construct() {
        // Configurar credenciales según el entorno
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->dbname = $_ENV['DB_NAME'] ?? 'saggarag_GestionPresupuestos';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? '';
        
        // LOG TEMPORAL PARA DEBUGGING
        error_log('=== DATABASE CONNECTION DEBUG ===');
        error_log('Host: ' . $this->host);
        error_log('DB Name: ' . $this->dbname);
        error_log('Username: ' . $this->username);
        error_log('Password length: ' . strlen($this->password));
        error_log('$_ENV variables: ' . print_r($_ENV, true));
        error_log('putenv variables: ' . print_r(getenv(), true));
        
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log('ERROR de conexión a BD: ' . $e->getMessage());
            http_response_code(500);
            die(json_encode([
                'success' => false,
                'message' => 'Error de conexión a la base de datos'
            ]));
        }
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