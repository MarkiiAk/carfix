# 🛠️ Documentación Técnica para Desarrolladores - SAG Garage

**Sistema Integral de Gestión para Talleres Mecánicos**  
*Documentación técnica completa para desarrolladores*

---

## 📋 Índice

1. [🏗️ Arquitectura del Sistema](#%EF%B8%8F-arquitectura-del-sistema)
2. [💻 Stack Tecnológico](#-stack-tecnológico)
3. [🗄️ Base de Datos](#%EF%B8%8F-base-de-datos)
4. [🔐 Sistema de Autenticación](#-sistema-de-autenticación)
5. [⚡ Sistema de Alertas y WhatsApp](#-sistema-de-alertas-y-whatsapp)
6. [🛠️ Setup de Desarrollo](#%EF%B8%8F-setup-de-desarrollo)
7. [📡 APIs y Endpoints](#-apis-y-endpoints)
8. [🚀 Deployment y CI/CD](#-deployment-y-cicd)
9. [🧪 Testing y Troubleshooting](#-testing-y-troubleshooting)
10. [⚙️ Configuraciones](#%EF%B8%8F-configuraciones)

---

## 🏗️ Arquitectura del Sistema

### Arquitectura General
```
┌─────────────────────────────────────────────────────────┐
│                    FRONTEND                             │
│  React 18 + TypeScript + Tailwind CSS + Vite          │
│  ┌─────────────┬─────────────┬─────────────────────┐   │
│  │ Components  │   Pages     │       Store        │   │
│  │   UI/UX     │ Dashboard   │      Zustand       │   │
│  │ Sections    │ Alertas     │   Auth Context     │   │
│  └─────────────┴─────────────┴─────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────┐
│                    BACKEND                              │
│  • Node.js/Express (Sistema Original Presupuestos)     │
│  • PHP 8.0+ (Sistema de Alertas + WhatsApp)           │
│  ┌─────────────────┬─────────────────┬─────────────┐   │
│  │ Controllers     │ Cron Jobs       │ Webhooks    │   │
│  │ AlertasCtrl     │ generar_alertas │ WhatsApp    │   │
│  │ WhatsAppCtrl    │ enviar_whatsapp │ Responses   │   │
│  │ AuthController  │ (12:00 AM)      │             │   │
│  └─────────────────┴─────────────────┴─────────────┘   │
└─────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────┐
│                   BASE DE DATOS                        │
│              MySQL 8.0+ (Production)                   │
│  ┌─────────────────────────────────────────────────┐   │
│  │ • usuarios (auth)                               │   │
│  │ • alertas_servicio (core)                      │   │
│  │ • whatsapp_templates                           │   │
│  │ • whatsapp_config                              │   │
│  │ • whatsapp_blacklist                           │   │
│  │ • whatsapp_logs                                │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────┐
│              SERVICIOS EXTERNOS                         │
│  WhatsApp Business API + Cron Jobs + GitHub Actions    │
└─────────────────────────────────────────────────────────┘
```

### Flujo de Datos WhatsApp
```
[12:00 AM] Cron Job Generador
     │
     ├─ Detecta clientes con servicios > 6 meses
     ├─ Crea registros en alertas_servicio
     ├─ Estado inicial: whatsapp_estado = 'pendiente'
     └─ Log: /var/log/alertas_generacion.log
     
[10:00 AM] Cron Job WhatsApp (Días Hábiles)
     │
     ├─ Lee alertas con estado 'pendiente'
     ├─ Aplica validaciones anti-spam
     ├─ Selecciona template inteligente
     ├─ Envía a WhatsApp Business API
     ├─ Actualiza estados y logs
     └─ Log: /var/log/whatsapp_envio.log

[Webhook] Respuestas de Clientes
     │
     ├─ Recibe actualizaciones de estado
     ├─ Actualiza whatsapp_logs
     ├─ Estados: enviado → entregado → leido
     └─ Log: /var/log/whatsapp_webhook.log
```

### Patrones de Diseño Implementados
- **MVC Pattern**: Controllers para lógica de negocio
- **Repository Pattern**: Para acceso a datos
- **Factory Pattern**: Para creación de templates
- **Observer Pattern**: Para webhooks WhatsApp
- **Strategy Pattern**: Para selección de templates
- **Command Pattern**: Para cron jobs

---

## 💻 Stack Tecnológico

### Frontend Stack
```javascript
// package.json dependencies principales
{
  "react": "^18.2.0",              // Framework UI
  "typescript": "^5.0.0",          // Type safety
  "vite": "^4.4.0",               // Build tool ultra-rápido
  "tailwindcss": "^3.3.0",        // CSS utility-first
  "zustand": "^4.4.0",            // State management
  "react-router-dom": "^6.15.0",  // Routing
  "lucide-react": "^0.263.0",     // Iconos modernos
  "jspdf": "^2.5.1",              // PDF generation
  "html2canvas": "^1.4.1"         // Canvas rendering
}
```

### Backend Stack
```php
// PHP 8.0+ con extensiones
- php-mysql     // Conexión a MySQL
- php-json      // Manejo de JSON
- php-curl      // HTTP requests a WhatsApp API
- php-bcrypt    // Password hashing
- php-mbstring  // Manejo de strings UTF-8
```

### Base de Datos
```sql
-- MySQL 8.0+ con configuraciones específicas
SET sql_mode = 'TRADITIONAL';
SET character_set_server = utf8mb4;
SET collation_server = utf8mb4_unicode_ci;
```

### Herramientas de Desarrollo
```bash
# Desarrollo
Node.js 18+      # Runtime JavaScript
PHP 8.0+         # Backend scripting
MySQL 8.0+       # Base de datos
Git              # Version control

# Deployment
GitHub Actions   # CI/CD pipeline
cPanel           # Hosting management
FTP              # File deployment
```

---

## 🗄️ Base de Datos

### Esquema Completo

#### Tabla: usuarios
```sql
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,  -- BCRYPT hash
    nombre_completo VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    rol ENUM('admin', 'tecnico', 'recepcionista') DEFAULT 'tecnico',
    activo BOOLEAN DEFAULT TRUE,
    ultimo_acceso TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_rol_activo (rol, activo)
);
```

#### Tabla: alertas_servicio (Core + WhatsApp)
```sql
CREATE TABLE alertas_servicio (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- Datos del servicio original
    folio VARCHAR(20) NOT NULL,
    fecha_servicio DATE NOT NULL,
    cliente_id INT,
    cliente_nombre VARCHAR(255) NOT NULL,
    cliente_telefono VARCHAR(20),
    cliente_email VARCHAR(255),
    vehiculo_marca VARCHAR(100),
    vehiculo_modelo VARCHAR(100),
    vehiculo_año INT,
    vehiculo_placas VARCHAR(20),
    servicio_realizado TEXT,
    costo_servicio DECIMAL(10,2),
    fecha_proxima_alerta DATE,
    
    -- Estados del sistema
    estado ENUM('activa', 'notificada', 'completada', 'cancelada') DEFAULT 'activa',
    
    -- Campos WhatsApp (agregados)
    whatsapp_estado ENUM('pendiente', 'programado', 'enviado', 'entregado', 'leido', 'error', 'omitido') DEFAULT 'pendiente',
    whatsapp_fecha_programada TIMESTAMP NULL,
    whatsapp_fecha_enviada TIMESTAMP NULL,
    whatsapp_template_id INT NULL,
    whatsapp_mensaje_id VARCHAR(100) NULL,
    whatsapp_error_mensaje TEXT NULL,
    whatsapp_intentos_envio INT DEFAULT 0,
    whatsapp_ultimo_intento TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices para performance
    INDEX idx_folio (folio),
    INDEX idx_cliente_id (cliente_id),
    INDEX idx_fecha_servicio (fecha_servicio),
    INDEX idx_fecha_proxima (fecha_proxima_alerta),
    INDEX idx_estado (estado),
    INDEX idx_whatsapp_estado (whatsapp_estado),
    INDEX idx_whatsapp_fecha_programada (whatsapp_fecha_programada),
    
    -- Foreign Keys
    FOREIGN KEY (whatsapp_template_id) REFERENCES whatsapp_templates(id) ON DELETE SET NULL
);
```

#### Tabla: whatsapp_templates
```sql
CREATE TABLE whatsapp_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    contenido TEXT NOT NULL,
    tipo_servicio VARCHAR(100) DEFAULT 'general',
    variables JSON DEFAULT NULL, -- ["cliente", "vehiculo", "servicio", "tiempo"]
    activo BOOLEAN DEFAULT TRUE,
    uso_count INT DEFAULT 0,
    conversion_rate DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_nombre (nombre),
    INDEX idx_tipo_servicio (tipo_servicio),
    INDEX idx_activo (activo)
);

-- Templates iniciales
INSERT INTO whatsapp_templates (nombre, contenido, tipo_servicio, variables) VALUES
('recordatorio_general', 'Hola {{cliente}}, te escribimos de SAG Garage, hace 6 meses trajiste tu {{vehiculo}} a {{servicio}}. Es momento de volver a hacerlo ¿te esperamos?', 'general', '["cliente", "vehiculo", "servicio"]'),
('full_service', 'Hola {{cliente}}! 👋 Tu {{vehiculo}} necesita su Full Service. En SAG Garage cuidamos tu auto como si fuera nuestro. ¿Agendamos cita?', 'Full Service', '["cliente", "vehiculo"]'),
('cambio_aceite', '¡Hola {{cliente}}! 🚗 Es hora del cambio de aceite de tu {{vehiculo}}. Protege tu motor con nosotros en SAG Garage. ¿Cuándo vienes?', 'Cambio de Aceite', '["cliente", "vehiculo"]'),
('verificacion', 'Hola {{cliente}}, tu {{vehiculo}} necesita su verificación. En SAG Garage te ayudamos a cumplir a tiempo. ¿Te esperamos?', 'Verificación', '["cliente", "vehiculo"]');
```

#### Tabla: whatsapp_config
```sql
CREATE TABLE whatsapp_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT NOT NULL,
    descripcion TEXT,
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_clave (clave)
);

-- Configuraciones iniciales
INSERT INTO whatsapp_config (clave, valor, descripcion, tipo) VALUES
('api_token', '', 'Token de WhatsApp Business API', 'string'),
('phone_number_id', '', 'ID del número de teléfono de WhatsApp', 'string'),
('hora_envio', '10:00', 'Hora de envío diaria (formato HH:mm)', 'string'),
('dias_habiles', '1,2,3,4,5', 'Días hábiles para envío (1=Lun, 7=Dom)', 'string'),
('limite_mensajes_dia', '100', 'Límite máximo de mensajes por día', 'number'),
('dias_cooldown', '30', 'Días de espera entre mensajes al mismo cliente', 'number'),
('activo', 'false', 'Sistema WhatsApp activo/inactivo', 'boolean'),
('webhook_token', '', 'Token para verificar webhook de WhatsApp', 'string');
```

#### Tabla: whatsapp_blacklist
```sql
CREATE TABLE whatsapp_blacklist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    telefono VARCHAR(20) NOT NULL UNIQUE,
    cliente_id INT NULL,
    motivo VARCHAR(255) NOT NULL,
    fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    agregado_por_id INT NULL,
    
    INDEX idx_telefono (telefono),
    INDEX idx_cliente_id (cliente_id),
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (agregado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
);
```

#### Tabla: whatsapp_logs
```sql
CREATE TABLE whatsapp_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alerta_id INT NOT NULL,
    cliente_id INT NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    template_id INT,
    mensaje TEXT NOT NULL,
    estado ENUM('enviando', 'enviado', 'entregado', 'leido', 'error') NOT NULL,
    whatsapp_message_id VARCHAR(100),
    error_codigo VARCHAR(50),
    error_descripcion TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_alerta_id (alerta_id),
    INDEX idx_cliente_id (cliente_id),
    INDEX idx_telefono (telefono),
    INDEX idx_estado (estado),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (alerta_id) REFERENCES alertas_servicio(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE SET NULL
);
```

---

## 🔐 Sistema de Autenticación

### Implementación BCRYPT
```php
// Configuración BCRYPT
class AuthSystem {
    private const BCRYPT_COST = 10;
    private const PASSWORD_MIN_LENGTH = 8;
    
    /**
     * Genera hash seguro para contraseña
     */
    public static function hashPassword($password) {
        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            throw new Exception('Password too short');
        }
        
        return password_hash($password, PASSWORD_DEFAULT, [
            'cost' => self::BCRYPT_COST
        ]);
    }
    
    /**
     * Verifica contraseña contra hash
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Genera token JWT
     */
    public static function generateJWT($userId, $username, $rol) {
        $payload = [
            'user_id' => $userId,
            'username' => $username,
            'rol' => $rol,
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 horas
        ];
        
        return JWT::encode($payload);
    }
}
```

### Proceso de Creación de Usuarios
```javascript
// Script para generar hash (generate_hash.cjs)
const bcrypt = require('bcryptjs');

function generateUserHash(password) {
    const saltRounds = 10;
    const hash = bcrypt.hashSync(password, saltRounds);
    
    console.log('=== GENERADOR DE HASH ===');
    console.log(`Password: ${password}`);
    console.log(`Hash: ${hash}`);
    
    // Verificar
    const isValid = bcrypt.compareSync(password, hash);
    console.log(`Verificación: ${isValid ? 'EXITOSA' : 'FALLÓ'}`);
    
    return hash;
}

// Uso
const password = 'TU_PASSWORD_AQUI';
const hash = generateUserHash(password);
console.log(`\nSQL: INSERT INTO usuarios (username, password_hash, nombre_completo, email, rol, activo) VALUES ('nuevo_usuario', '${hash}', 'Nombre Completo', 'email@ejemplo.com', 'admin', 1);`);
```

### Sistema de Roles
```php
// Definición de roles y permisos
class RoleSystem {
    const ROLES = [
        'admin' => [
            'presupuestos' => ['create', 'read', 'update', 'delete'],
            'alertas' => ['create', 'read', 'update', 'delete'],
            'whatsapp' => ['create', 'read', 'update', 'delete'],
            'usuarios' => ['create', 'read', 'update', 'delete']
        ],
        'tecnico' => [
            'presupuestos' => ['create', 'read', 'update'],
            'alertas' => ['read'],
            'whatsapp' => ['read']
        ],
        'recepcionista' => [
            'presupuestos' => ['create', 'read'],
            'alertas' => ['read']
        ]
    ];
    
    public static function hasPermission($userRole, $resource, $action) {
        return in_array($action, self::ROLES[$userRole][$resource] ?? []);
    }
}
```

### Middleware de Autenticación
```php
// Middleware para proteger endpoints
class AuthMiddleware {
    public static function authenticate($requiredRole = null) {
        $token = self::getBearerToken();
        
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Token requerido']);
            exit;
        }
        
        try {
            $payload = JWT::decode($token);
            
            if ($requiredRole && !RoleSystem::hasRole($payload->rol, $requiredRole)) {
                http_response_code(403);
                echo json_encode(['error' => 'Permisos insuficientes']);
                exit;
            }
            
            return $payload;
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido']);
            exit;
        }
    }
    
    private static function getBearerToken() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
}
```

---

## ⚡ Sistema de Alertas y WhatsApp

### Arquitectura de Cron Jobs

#### Job 1: Generador de Alertas (12:00 AM)
```bash
# Crontab entry
0 0 * * * /usr/bin/php /path/to/backend-php/cron/generar_alertas.php >> /var/log/alertas_generacion.log 2>&1
```

```php
<?php
// backend-php/cron/generar_alertas.php
require_once '../config/database.php';
require_once '../controllers/AlertasController.php';

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
}

try {
    logMessage("🚀 Iniciando generación de alertas...");
    
    $db = Database::getConnection();
    $alertasController = new AlertasController($db);
    
    // Generar alertas para clientes con servicios > 6 meses
    $resultado = $alertasController->generarAlertasAutomatico();
    
    logMessage("✅ Proceso completado:");
    logMessage("   - Alertas generadas: " . $resultado['alertas_generadas']);
    logMessage("   - Clientes procesados: " . $resultado['clientes_procesados']);
    logMessage("   - Errores: " . $resultado['errores']);
    
} catch (Exception $e) {
    logMessage("❌ Error en generación: " . $e->getMessage());
    exit(1);
}
?>
```

#### Job 2: Envío WhatsApp (10:00 AM - Días Hábiles)
```bash
# Crontab entry
0 10 * * 1-5 /usr/bin/php /path/to/backend-php/cron/enviar_whatsapp.php >> /var/log/whatsapp_envio.log 2>&1
```

```php
<?php
// backend-php/cron/enviar_whatsapp.php
require_once '../config/database.php';
require_once '../controllers/WhatsappController.php';

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
}

try {
    logMessage("📱 Iniciando envío de WhatsApp...");
    
    $db = Database::getConnection();
    $whatsappController = new WhatsappController($db);
    
    // Verificar si el sistema está activo
    if (!$whatsappController->isSistemaActivo()) {
        logMessage("⏸️  Sistema WhatsApp inactivo");
        exit(0);
    }
    
    // Procesar cola de mensajes
    $resultado = $whatsappController->procesarColaWhatsApp();
    
    logMessage("✅ Envío completado:");
    logMessage("   - Mensajes enviados: " . $resultado['enviados']);
    logMessage("   - Omitidos (spam/blacklist): " . $resultado['omitidos']);
    logMessage("   - Errores: " . $resultado['errores']);
    logMessage("   - Costo estimado: $" . number_format($resultado['enviados'] * 0.56, 2) . " MXN");
    
} catch (Exception $e) {
    logMessage("❌ Error en envío: " . $e->getMessage());
    exit(1);
}
?>
```

### Sistema Anti-Spam
```php
class AntiSpamSystem {
    private $db;
    private $config;
    
    public function __construct($database) {
        $this->db = $database;
        $this->loadConfig();
    }
    
    /**
     * Valida si se puede enviar mensaje al cliente
     */
    public function puedeEnviarMensaje($clienteId, $telefono) {
        // 1. Verificar blacklist
        if ($this->estaEnBlacklist($telefono)) {
            return ['permitido' => false, 'razon' => 'Teléfono en blacklist'];
        }
        
        // 2. Verificar cooldown
        if ($this->tieneEnvioReciente($clienteId)) {
            return ['permitido' => false, 'razon' => 'Cooldown activo'];
        }
        
        // 3. Verificar límite diario
        if ($this->excedeLimiteDiario()) {
            return ['permitido' => false, 'razon' => 'Límite diario alcanzado'];
        }
        
        // 4. Verificar teléfono válido
        if (!$this->esNumeroValido($telefono)) {
            return ['permitido' => false, 'razon' => 'Número inválido'];
        }
        
        return ['permitido' => true];
    }
    
    private function estaEnBlacklist($telefono) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM whatsapp_blacklist WHERE telefono = ?");
        $stmt->execute([$telefono]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function tieneEnvioReciente($clienteId) {
        $diasCooldown = $this->config['dias_cooldown'];
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM whatsapp_logs 
            WHERE cliente_id = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL ? DAY)
            AND estado IN ('enviado', 'entregado', 'leido')
        ");
        $stmt->execute([$clienteId, $diasCooldown]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function excedeLimiteDiario() {
        $limite = $this->config['limite_mensajes_dia'];
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM whatsapp_logs 
            WHERE DATE(created_at) = CURDATE()
            AND estado = 'enviado'
        ");
        $stmt->execute();
        return $stmt->fetchColumn() >= $limite;
    }
    
    private function esNumeroValido($telefono) {
        // Formato internacional: +521234567890
        return preg_match('/^\+52[1-9]\d{9}$/', $telefono);
    }
}
```

### Sistema de Templates
```php
class TemplateEngine {
    private $templates = [];
    
    /**
     * Procesa template con variables dinámicas
     */
    public function procesarTemplate($templateId, $variables) {
        $template = $this->getTemplate($templateId);
        $mensaje = $template['contenido'];
        
        // Reemplazar variables
        foreach ($variables as $key => $value) {
            $mensaje = str_replace("{{$key}}", $value, $mensaje);
        }
        
        return $mensaje;
    }
    
    /**
     * Selecciona template inteligente basado en servicio
     */
    public function seleccionarTemplate($servicioRealizado) {
        // Mapeo de servicios a templates específicos
        $mapeoServicios = [
            'Full Service' => 'full_service',
            'Cambio de Aceite' => 'cambio_aceite',
            'Verificación' => 'verificacion'
        ];
        
        $templateNombre = $mapeoServicios[$servicioRealizado] ?? 'recordatorio_general';
        
        return $this->getTemplateByName($templateNombre);
    }
    
    /**
     * Variables disponibles para templates
     */
    public function getVariablesDisponibles() {
        return [
            'cliente' => 'Nombre del cliente',
            'vehiculo' => 'Marca + Modelo del vehículo',
            'servicio' => 'Último servicio realizado',
            'tiempo' => 'Tiempo transcurrido (ej: "6 meses")',
            'telefono_sag' => 'Teléfono de contacto SAG',
            'direccion_sag' => 'Dirección del taller'
        ];
    }
}
```

### WhatsApp Business API Client
```php
class WhatsappAPI {
    private $apiUrl;
    private $token;
    private $phoneNumberId;
    
    public function __construct($config) {
        $this->apiUrl = $config['api_url'] ?? 'https://graph.facebook.com/v17.0';
        $this->token = $config['api_token'];
        $this->phoneNumberId = $config['phone_number_id'];
    }
    
    /**
     * Envía mensaje de texto
     */
    public function enviarMensaje($telefono, $mensaje) {
        if (empty($this->token) || empty($this->phoneNumberId)) {
            return [
                'success' => false,
                'error' => 'Configuración de API incompleta',
                'error_code' => 'CONFIG_MISSING'
            ];
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $telefono,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $mensaje
            ]
        ];
        
        $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";
        
        $response = $this->makeRequest('POST', $url, $data);
        
        if ($response['success']) {
            return [
                'success' => true,
                'message_id' => $response['data']['messages'][0]['id'] ?? null
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['error'],
            'error_code' => $response['error_code']
        ];
    }
    
    /**
     * Realiza petición HTTP a WhatsApp API
     */
    private function makeRequest($method, $url, $data = null) {
        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30
        ]);
        
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => json_decode($response, true)
            ];
        }
        
        return [
            'success' => false,
            'error' => $response,
            'error_code' => $httpCode
        ];
    }
}
```

---

## 🛠️ Setup de Desarrollo

### Requisitos del Sistema
```bash
# Versiones mínimas requeridas
Node.js     >= 18.0.0
PHP         >= 8.0.0
MySQL       >= 8.0.0
Composer    >= 2.0.0  (opcional)
Git         >= 2.30.0
```

### Configuración Local Completa

#### 1. Clonar y Setup Inicial
```bash
# Clonar repositorio
git clone <repository-url>
cd sag-garage-presupuestos

# Instalar dependencias frontend
npm install

# Verificar Node.js y versiones
node --version  # debe ser >= 18
npm --version
php --version   # debe ser >= 8.0
```

#### 2. Configuración de Base de Datos
```sql
-- Crear base de datos
CREATE DATABASE sag_garage_local CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Crear usuario de desarrollo
CREATE USER 'sag_dev'@'localhost' IDENTIFIED BY 'dev_password_2024';
GRANT ALL PRIVILEGES ON sag_garage_local.* TO 'sag_dev'@'localhost';
FLUSH PRIVILEGES;
```

#### 3. Variables de Entorno de Desarrollo
```env
# .env.local (Frontend)
VITE_API_URL=http://localhost:8080/backend-php
VITE_MODE=development
VITE_DEBUG=true
```

```env
# backend-php/.env (Backend)
DB_HOST=localhost
DB_NAME=sag_garage_local
DB_USER=sag_dev
DB_PASSWORD=dev_password_2024

JWT_SECRET=development_jwt_secret_key_change_in_production

# WhatsApp (desarrollo - usar sandbox)
WHATSAPP_TOKEN=development_token
WHATSAPP_PHONE_NUMBER_ID=dev_phone_id
WHATSAPP_WEBHOOK_TOKEN=dev_webhook_token
WHATSAPP_API_VERSION=v17.0

# Configuración SAG (desarrollo)
SAG_TELEFONO=+521234567890
SAG_DIRECCION=Dirección de desarrollo
SAG_HORARIOS=Lun-Vie 8:00-18:00

# Sistema
TIMEZONE=America/Mexico_City
DEBUG_MODE=true
```

#### 4. Configuración de Vite
```typescript
// vite.config.ts
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  base: process.env.NODE_ENV === 'production' ? '/gestion/' : '/',
  server: {
    port: 3000,
    proxy: {
      '/backend-php': {
        target: 'http://localhost:8080',
        changeOrigin: true,
        secure: false
      }
    }
  },
  build: {
    outDir: 'dist',
    sourcemap: true,
    rollupOptions: {
      output: {
        manualChunks: {
          'react-vendor': ['react', 'react-dom'],
          'router': ['react-router-dom'],
          'ui-vendor': ['lucide-react'],
          'pdf-vendor': ['jspdf', 'html2canvas']
        }
      }
    }
  }
})
```

#### 5. Configuración PHP Local
```php
<?php
// backend-php/config/database.php
class Database {
    private $host = 'localhost';
    private $dbname = 'sag_garage_local';
    private $username = 'sag_dev';
    private $password = 'dev_password_2024';
    private $charset = 'utf8mb4';
    
    public static function getConnection() {
        static $instance = null;
        
        if ($instance === null) {
            $instance = new self();
            $instance->connect();
        }
        
        return $instance->connection;
    }
    
    private function connect() {
        $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
}
?>
```

### Scripts de Desarrollo
```json
{
  "scripts": {
    "dev": "vite",
    "build": "tsc && vite build",
    "preview": "vite preview",
    "lint": "eslint . --ext ts,tsx --report-unused-disable-directives --max-warnings 0",
    "type-check": "tsc --noEmit",
    "dev:backend": "cd backend-php && php -S localhost:8080",
    "dev:full": "concurrently \"npm run dev:backend\" \"npm run dev\"",
    "test": "vitest",
    "test:ui": "vitest --ui"
  }
}
```

### Flujo de Desarrollo Local
```bash
# Terminal 1: Backend PHP
cd backend-php
php -S localhost:8080

# Terminal 2: Frontend React  
npm run dev

# Terminal 3: Logs (opcional)
tail -f /var/log/alertas_generacion.log
tail -f /var/log/whatsapp_envio.log
```

### Configuración de CORS
```php
<?php
// backend-php/index.php
header('Access-Control-Allow-Origin: http://localhost:3000');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Manejar preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
?>
```

---

## 📡 APIs y Endpoints

### Estructura de Endpoints

#### Autenticación
```php
// POST /backend-php/api/auth/login
{
  "username": "string",
  "password": "string"
}
// Response: { "token": "jwt_token", "user": { ... } }

// GET /backend-php/api/auth/verify
// Headers: Authorization: Bearer {token}
// Response: { "valid": true, "user": { ... } }

// POST /backend-php/api/auth/logout
// Headers: Authorization: Bearer {token}
// Response: { "message": "Logged out successfully" }
```

#### Sistema de Alertas
```php
// GET /backend-php/api/alertas
// Query params: page, limit, search, estado, fecha_desde, fecha_hasta
// Response: { "data": [...], "total": number, "page": number }

// POST /backend-php/api/alertas
{
  "folio": "string",
  "fecha_servicio": "YYYY-MM-DD",
  "cliente_nombre": "string",
  "cliente_telefono": "string",
  "vehiculo_marca": "string",
  "vehiculo_modelo": "string",
  "servicio_realizado": "string",
  "costo_servicio": number
}

// GET /backend-php/api/alertas/{id}
// Response: { "id": number, "folio": "string", ... }

// PUT /backend-php/api/alertas/{id}
// Request: { campo_a_actualizar: "nuevo_valor", ... }

// DELETE /backend-php/api/alertas/{id}
// Response: { "message": "Alerta eliminada" }

// GET /backend-php/api/alertas/estadisticas
// Response: { 
//   "total": number,
//   "por_estado": { "activa": number, "notificada": number, ... },
//   "por_mes": [{ "mes": "string", "count": number }]
// }
```

#### Sistema WhatsApp
```php
// GET /backend-php/api/whatsapp/config
// Response: { "activo": boolean, "limite_dia": number, ... }

// PUT /backend-php/api/whatsapp/config
// Request: { "clave": "valor", ... }

// GET /backend-php/api/whatsapp/templates
// Response: [{ "id": number, "nombre": "string", "contenido": "string", ... }]

// POST /backend-php/api/whatsapp/templates
// Request: { "nombre": "string", "contenido": "string", "tipo_servicio": "string" }

// GET /backend-php/api/whatsapp/logs
// Query params: page, limit, estado, fecha_desde, fecha_hasta
// Response: { "data": [...], "total": number }

// POST /backend-php/api/whatsapp/test
// Request: { "telefono": "string", "mensaje": "string" }
// Response: { "success": boolean, "message_id": "string" }

// POST /backend-php/api/whatsapp/blacklist
// Request: { "telefono": "string", "motivo": "string" }

// DELETE /backend-php/api/whatsapp/blacklist/{telefono}
```

#### Webhook WhatsApp
```php
// GET /backend-php/webhook/whatsapp
// Query params: hub.mode, hub.challenge, hub.verify_token
// Response: hub.challenge (si verificación exitosa)

// POST /backend-php/webhook/whatsapp
// Request: WhatsApp webhook payload
// Updates message status in whatsapp_logs table
```

### Códigos de Error Estándar
```php
// HTTP Status Codes utilizados
200 // OK - Operación exitosa
201 // Created - Recurso creado exitosamente  
400 // Bad Request - Datos inválidos
401 // Unauthorized - Token inválido o faltante
403 // Forbidden - Permisos insuficientes
404 // Not Found - Recurso no encontrado
422 // Unprocessable Entity - Validación fallida
500 // Internal Server Error - Error del servidor

// Estructura de respuesta de error
{
  "error": "Descripción del error",
  "error_code": "ERROR_CODE",
  "details": { ... } // opcional
}
```

---

## 🚀 Deployment y CI/CD

### GitHub Actions Workflow
```yaml
# .github/workflows/deploy-cpanel.yml
name: Deploy to cPanel

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - name: Checkout code
      uses: actions/checkout@v3
      
    - name: Setup Node.js
      uses: actions/setup-node@v3
      with:
        node-version: '18'
        
    - name: Install dependencies
      run: npm ci
      
    - name: Build project
      run: npm run build
      env:
        VITE_API_URL: ${{ secrets.VITE_API_URL }}
        
    - name: Deploy to cPanel
      uses: SamKirkland/FTP-Deploy-Action@4.3.3
      with:
        server: ${{ secrets.FTP_SERVER }}
        username: ${{ secrets.FTP_USERNAME }}
        password: ${{ secrets.FTP_PASSWORD }}
        local-dir: ./dist/
        server-dir: /public_html/gestion/
        
    - name: Deploy backend
      uses: SamKirkland/FTP-Deploy-Action@4.3.3
      with:
        server: ${{ secrets.FTP_SERVER }}
        username: ${{ secrets.FTP_USERNAME }}
        password: ${{ secrets.FTP_PASSWORD }}
        local-dir: ./backend-php/
        server-dir: /public_html/backend-php/
```

### Configuración de Producción
```php
// backend-php/.env.production
DB_HOST=localhost
DB_NAME=production_db_name
DB_USER=production_user
DB_PASSWORD=secure_production_password

JWT_SECRET=super_secure_jwt_secret_production_2024

# WhatsApp Business API (producción)
WHATSAPP_TOKEN=production_token_from_meta
WHATSAPP_PHONE_NUMBER_ID=production_phone_id
WHATSAPP_WEBHOOK_TOKEN=secure_webhook_token
WHATSAPP_API_VERSION=v17.0

# Configuración SAG (producción)
SAG_TELEFONO=+52XXXXXXXXXX
SAG_DIRECCION=Dirección real del taller
SAG_HORARIOS=Lun-Vie 8:00-18:00, Sáb 8:00-14:00

# Sistema
TIMEZONE=America/Mexico_City
DEBUG_MODE=false
```

### Configuración de Cron Jobs en Producción
```bash
# Acceder al cPanel -> Cron Jobs
# O editar crontab directamente:
crontab -e

# Agregar jobs para SAG Garage
# Generar alertas diariamente a medianoche
0 0 * * * /usr/bin/php /home/usuario/public_html/backend-php/cron/generar_alertas.php >> /home/usuario/logs/alertas_generacion.log 2>&1

# Enviar WhatsApp días hábiles a las 10:00 AM
0 10 * * 1-5 /usr/bin/php /home/usuario/public_html/backend-php/cron/enviar_whatsapp.php >> /home/usuario/logs/whatsapp_envio.log 2>&1

# Limpiar logs antiguos (mensual)
0 0 1 * * find /home/usuario/logs -name "*.log" -mtime +30 -delete
```

### Variables de Entorno de Producción
```bash
# Variables que deben configurarse en el servidor
export DB_HOST="localhost"
export DB_NAME="production_db"
export DB_USER="production_user"
export DB_PASSWORD="secure_password"
export JWT_SECRET="super_secure_jwt_key"
export WHATSAPP_TOKEN="actual_whatsapp_token"
export WHATSAPP_PHONE_NUMBER_ID="actual_phone_id"
```

---

## 🧪 Testing y Troubleshooting

### Testing del Sistema de Alertas
```php
// Script de testing: test_alertas.php
<?php
require_once 'config/database.php';
require_once 'controllers/AlertasController.php';

function testGeneracionAlertas() {
    echo "🧪 Testing generación de alertas...\n";
    
    $db = Database::getConnection();
    $controller = new AlertasController($db);
    
    // Test 1: Generar alertas
    $resultado = $controller->generarAlertasAutomatico();
    
    if ($resultado['alertas_generadas'] >= 0) {
        echo "✅ Generación de alertas: OK\n";
        echo "   - Alertas generadas: {$resultado['alertas_generadas']}\n";
    } else {
        echo "❌ Generación de alertas: FAIL\n";
    }
    
    // Test 2: Verificar estructura de datos
    $stmt = $db->query("SELECT COUNT(*) FROM alertas_servicio WHERE whatsapp_estado = 'pendiente'");
    $pendientes = $stmt->fetchColumn();
    
    echo "📊 Alertas pendientes: $pendientes\n";
}

testGeneracionAlertas();
?>
```

### Testing del Sistema WhatsApp
```php
// Script de testing: test_whatsapp.php
<?php
require_once 'config/database.php';
require_once 'utils/WhatsappAPI.php';
require_once 'utils/TemplateEngine.php';

function testWhatsAppAPI() {
    echo "🧪 Testing WhatsApp API...\n";
    
    $config = [
        'api_token' => getenv('WHATSAPP_TOKEN'),
        'phone_number_id' => getenv('WHATSAPP_PHONE_NUMBER_ID')
    ];
    
    $api = new WhatsappAPI($config);
    
    // Test 1: Conexión API
    $test = $api->testConnection();
    if ($test['success']) {
        echo "✅ Conexión WhatsApp API: OK\n";
    } else {
        echo "❌ Conexión WhatsApp API: FAIL - {$test['error']}\n";
        return;
    }
    
    // Test 2: Template engine
    $engine = new TemplateEngine();
    $mensaje = $engine->procesarTemplate(1, [
        'cliente' => 'Test Cliente',
        'vehiculo' => 'Test Toyota Corolla 2020'
    ]);
    
    if (strpos($mensaje, 'Test Cliente') !== false) {
        echo "✅ Template engine: OK\n";
    } else {
        echo "❌ Template engine: FAIL\n";
    }
}

testWhatsAppAPI();
?>
```

### Troubleshooting Común

#### Error: "No se conecta a la base de datos"
```bash
# Verificar conexión MySQL
mysql -u usuario -p -e "SELECT 1"

# Verificar configuración PHP
php -m | grep mysql
php -m | grep pdo

# Test de conexión
php -r "
try {
    \$pdo = new PDO('mysql:host=localhost;dbname=test', 'user', 'pass');
    echo 'Conexión exitosa\n';
} catch (Exception \$e) {
    echo 'Error: ' . \$e->getMessage() . '\n';
}
"
```

#### Error: "CORS policy"
```php
// Verificar headers CORS en backend-php/index.php
header('Access-Control-Allow-Origin: ' . getAllowedOrigin());
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

function getAllowedOrigin() {
    $allowedOrigins = [
        'http://localhost:3000',  // Desarrollo
        'https://saggarage.com'   // Producción
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    return in_array($origin, $allowedOrigins) ? $origin : $allowedOrigins[0];
}
```

#### Error: "Cron job no ejecuta"
```bash
# Verificar cron service
systemctl status cron

# Verificar logs de cron
tail -f /var/log/cron

# Test manual del script
php /path/to/backend-php/cron/generar_alertas.php

# Verificar permisos
chmod +x /path/to/backend-php/cron/*.php
chown www-data:www-data /path/to/backend-php/cron/*.php
```

#### Error: "WhatsApp API token inválido"
```bash
# Verificar token en Meta for Developers
curl -X GET "https://graph.facebook.com/v17.0/me" \
  -H "Authorization: Bearer YOUR_TOKEN"

# Verificar configuración en base de datos
mysql -e "SELECT * FROM whatsapp_config WHERE clave = 'api_token'"

# Test de conexión
php -r "
\$config = ['api_token' => 'YOUR_TOKEN', 'phone_number_id' => 'YOUR_PHONE_ID'];
\$api = new WhatsappAPI(\$config);
var_dump(\$api->testConnection());
"
```

#### Error: "Frontend no carga"
```bash
# Verificar build
npm run build
npm run preview

# Verificar dependencias
npm install
npm audit

# Verificar configuración Vite
cat vite.config.ts

# Test local
npm run dev
```

### Logs de Debugging
```bash
# Ubicaciones de logs importantes
/var/log/apache2/error.log          # Apache errors
/var/log/mysql/error.log            # MySQL errors
/var/log/alertas_generacion.log     # Generación de alertas
/var/log/whatsapp_envio.log         # WhatsApp envíos
/var/log/whatsapp_webhook.log       # WhatsApp webhook
/home/usuario/logs/php_errors.log   # PHP errors
```

### Scripts de Monitoreo
```bash
#!/bin/bash
# monitor_system.sh - Script de monitoreo

echo "🔍 SAG Garage System Health Check"
echo "=================================="

# Check MySQL
if mysql -e "SELECT 1" 2>/dev/null; then
    echo "✅ MySQL: OK"
else
    echo "❌ MySQL: DOWN"
fi

# Check PHP
if php -v >/dev/null 2>&1; then
    echo "✅ PHP: OK"
else
    echo "❌ PHP: DOWN"
fi

# Check cron jobs
if pgrep -f "generar_alertas.php" >/dev/null; then
    echo "✅ Cron generar_alertas: RUNNING"
else
    echo "ℹ️  Cron generar_alertas: IDLE"
fi

# Check logs
if [ -f "/var/log/whatsapp_envio.log" ]; then
    last_msg=$(tail -1 /var/log/whatsapp_envio.log)
    echo "📝 Último envío WhatsApp: $last_msg"
fi

# Check disk space
df -h | grep -E "/$|/home"
```

---

## ⚙️ Configuraciones

### Configuración de Git y Seguridad
```gitignore
# .gitignore - Archivos que NO se deben commitear
node_modules/
dist/
*.log

# Archivos de configuración sensibles
.env
.env.local
.env.production
backend-php/.env

# Archivos temporales de desarrollo
generate_hash.*
*_hash.*
insert_usuario_*
fix_usuario_*
*.cjs
test_*.php

# Archivos del sistema
.DS_Store
Thumbs.db
*.tmp
*.backup

# IDE
.vscode/settings.json
.idea/
*.swp
*.swo

# Base de datos
*.sql.backup
dump_*.sql
```

### Configuración de TypeScript
```json
// tsconfig.json
{
  "compilerOptions": {
    "target": "ES2020",
    "useDefineForClassFields": true,
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "module": "ESNext",
    "skipLibCheck": true,
    
    /* Bundler mode */
    "moduleResolution": "bundler",
    "allowImportingTsExtensions": true,
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "react-jsx",
    
    /* Linting */
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noFallthroughCasesInSwitch": true,
    
    /* Path mapping */
    "baseUrl": ".",
    "paths": {
      "@/*": ["src/*"],
      "@/components/*": ["src/components/*"],
      "@/pages/*": ["src/pages/*"],
      "@/services/*": ["src/services/*"],
      "@/utils/*": ["src/utils/*"]
    }
  },
  "include": ["src"],
  "references": [{ "path": "./tsconfig.node.json" }]
}
```

### Configuración de Tailwind CSS
```javascript
// tailwind.config.js
/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#eff6ff',
          500: '#3b82f6',
          600: '#2563eb',
          700: '#1d4ed8'
        },
        secondary: {
          50: '#f8fafc',
          500: '#64748b',
          600: '#475569',
          700: '#334155'
        }
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif']
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-in-out',
        'slide-in': 'slideIn 0.3s ease-out'
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' }
        },
        slideIn: {
          '0%': { transform: 'translateY(-10px)', opacity: '0' },
          '100%': { transform: 'translateY(0)', opacity: '1' }
        }
      }
    },
  },
  plugins: [
    require('@tailwindcss/forms'),
    require('@tailwindcss/typography')
  ],
}
```

### Configuración ESLint
```json
// .eslintrc.json
{
  "env": {
    "browser": true,
    "es2020": true
  },
  "extends": [
    "eslint:recommended",
    "@typescript-eslint/recommended",
    "plugin:react-hooks/recommended"
  ],
  "parser": "@typescript-eslint/parser",
  "parserOptions": {
    "ecmaVersion": "latest",
    "sourceType": "module"
  },
  "plugins": ["react-refresh"],
  "rules": {
    "react-refresh/only-export-components": "warn",
    "@typescript-eslint/no-unused-vars": "error",
    "no-console": "warn"
  }
}
```

### Scripts de Utilidad
```bash
#!/bin/bash
# setup_dev.sh - Script de configuración inicial

echo "🚀 Configurando entorno de desarrollo SAG Garage..."

# Verificar dependencias
command -v node >/dev/null 2>&1 || { echo "❌ Node.js requerido"; exit 1; }
command -v php >/dev/null 2>&1 || { echo "❌ PHP requerido"; exit 1; }
command -v mysql >/dev/null 2>&1 || { echo "❌ MySQL requerido"; exit 1; }

# Instalar dependencias
echo "📦 Instalando dependencias..."
npm install

# Crear archivos de configuración
echo "⚙️ Creando archivos de configuración..."
cp .env.example .env.local
cp backend-php/.env.example backend-php/.env

# Crear directorios necesarios
mkdir -p logs
mkdir -p backups

# Configurar permisos
chmod +x backend-php/cron/*.php
chmod 755 logs/

echo "✅ Configuración completada"
echo "📝 Edita .env.local y backend-php/.env con tus configuraciones"
echo "🚀 Ejecuta: npm run dev:full"
```

### Configuración de Backup
```bash
#!/bin/bash
# backup_system.sh - Script de backup automático

BACKUP_DIR="/backups/sag-garage"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="sag_garage_production"

# Crear directorio de backup
mkdir -p "$BACKUP_DIR"

# Backup de base de datos
echo "📊 Respaldando base de datos..."
mysqldump -u backup_user -p$BACKUP_PASSWORD \
  --single-transaction \
  --routines \
  --triggers \
  "$DB_NAME" > "$BACKUP_DIR/db_backup_$DATE.sql"

# Backup de archivos
echo "📁 Respaldando archivos..."
tar -czf "$BACKUP_DIR/files_backup_$DATE.tar.gz" \
  /path/to/backend-php \
  /path/to/frontend \
  --exclude="node_modules" \
  --exclude="*.log"

# Limpiar backups antiguos (> 30 días)
find "$BACKUP_DIR" -type f -mtime +30 -delete

echo "✅ Backup completado: $BACKUP_DIR"
```

---

## 🎯 Consideraciones de Performance

### Optimizaciones de Base de Datos
```sql
-- Índices críticos para performance
CREATE INDEX idx_alertas_fecha_proxima ON alertas_servicio(fecha_proxima_alerta);
CREATE INDEX idx_alertas_whatsapp_estado ON alertas_servicio(whatsapp_estado);
CREATE INDEX idx_whatsapp_logs_created ON whatsapp_logs(created_at);
CREATE INDEX idx_whatsapp_logs_estado ON whatsapp_logs(estado);

-- Configuraciones MySQL para performance
SET GLOBAL innodb_buffer_pool_size = 268435456;  -- 256MB
SET GLOBAL query_cache_size = 67108864;          -- 64MB
SET GLOBAL max_connections = 100;
```

### Optimizaciones Frontend
```javascript
// Lazy loading de componentes
const AlertasPage = lazy(() => import('./pages/Alertas'));
const Dashboard = lazy(() => import('./pages/Dashboard'));

// Memoización de componentes costosos
const ExpensiveComponent = memo(({ data }) => {
  return <div>{/* render expensive content */}</div>;
});

// Optimización de renders con useMemo
const filteredData = useMemo(() => {
  return data.filter(item => item.active);
}, [data]);
```

### Caching Strategies
```php
// Cache simple para configuraciones
class ConfigCache {
    private static $cache = [];
    
    public static function get($key) {
        if (!isset(self::$cache[$key])) {
            self::$cache[$key] = self::loadFromDB($key);
        }
        return self::$cache[$key];
    }
}

// Cache de templates WhatsApp
class TemplateCache {
    private static $templates = null;
    
    public static function getTemplates() {
        if (self::$templates === null) {
            self::$templates = self::loadAllTemplates();
        }
        return self::$templates;
    }
}
```

---

## 🔒 Mejores Prácticas de Seguridad

### Validación y Sanitización
```php
class InputValidator {
    public static function sanitizePhone($phone) {
        // Remover caracteres no numéricos excepto +
        $phone = preg_replace('/[^\d+]/', '', $phone);
        
        // Validar formato mexicano
        if (!preg_match('/^\+52[1-9]\d{9}$/', $phone)) {
            throw new InvalidArgumentException('Formato de teléfono inválido');
        }
        
        return $phone;
    }
    
    public static function sanitizeString($string, $maxLength = 255) {
        $string = trim($string);
        $string = htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
        return substr($string, 0, $maxLength);
    }
    
    public static function validateEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Email inválido');
        }
        return strtolower($email);
    }
}
```

### Rate Limiting
```php
class RateLimiter {
    private $redis;  // O usar archivo/DB si no hay Redis
    
    public function isAllowed($identifier, $maxAttempts = 10, $timeWindow = 3600) {
        $key = "rate_limit:$identifier";
        $current = $this->redis->get($key) ?? 0;
        
        if ($current >= $maxAttempts) {
            return false;
        }
        
        $this->redis->incr($key);
        $this->redis->expire($key, $timeWindow);
        
        return true;
    }
}
```

---

## 📚 Documentación de APIs Externas

### WhatsApp Business API
```javascript
// Estructura completa del webhook
{
  "object": "whatsapp_business_account",
  "entry": [{
    "id": "WHATSAPP_BUSINESS_ACCOUNT_ID",
    "changes": [{
      "value": {
        "messaging_product": "whatsapp",
        "metadata": {
          "display_phone_number": "PHONE_NUMBER",
          "phone_number_id": "PHONE_NUMBER_ID"
        },
        "statuses": [{
          "id": "MESSAGE_ID",
          "status": "delivered|read|sent|failed",
          "timestamp": "TIMESTAMP",
          "recipient_id": "PHONE_NUMBER"
        }]
      },
      "field": "messages"
    }]
  }]
}
```

### Códigos de Estado WhatsApp
```
sent      - Mensaje enviado a WhatsApp
delivered - Mensaje entregado al dispositivo del cliente
read      - Cliente leyó el mensaje
failed    - Falló la entrega
```

---

*Documentación técnica completa para desarrolladores de SAG Garage*  
*Versión: 1.0 | Fecha: 30/03/2026*  
*Sistema integral de gestión para talleres mecánicos*