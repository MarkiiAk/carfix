# Implementación Sistema WhatsApp - SAG Garage

## 📋 Resumen Ejecutivo

Sistema automatizado de recordatorios por WhatsApp Business API para clientes de SAG Garage. Envía mensajes personalizados a clientes que requieren servicios de mantenimiento después de 6 meses.

### Beneficios:
- ✅ Automatización completa (no más llamadas manuales)
- ✅ Mensajes personalizados con datos del cliente/vehículo
- ✅ Control anti-spam y duplicados
- ✅ Costo muy bajo (~$0.56 MXN por mensaje)
- ✅ ROI estimado: 545-818%

---

## 🏗️ Arquitectura General

### Flujo del Sistema:
```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   12:00 AM      │    │    10:00 AM      │    │  WhatsApp       │
│ Generar Alertas │───▶│ Enviar Mensajes  │───▶│  Business API   │
│   (Cron Job)    │    │   (Cron Job)     │    │                 │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                        │                       │
         ▼                        ▼                       ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Base de Datos  │    │  Queue Sistema   │    │    Clientes     │
│    (Alertas)    │    │    (Estados)     │    │   (WhatsApp)    │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

### Componentes:
1. **Cron Job Generador** - Crea alertas diarias a medianoche
2. **Cron Job WhatsApp** - Envía mensajes a las 10:00 AM (días hábiles)
3. **WhatsApp Business API** - Integración oficial de Meta
4. **Sistema de Templates** - Mensajes personalizados dinámicos
5. **Control Anti-Spam** - Previene duplicados y límites
6. **Logging Completo** - Trazabilidad y estadísticas

---

## 🗄️ Base de Datos

### Modificaciones a Tabla Existente:
```sql
-- Agregar campos WhatsApp a alertas_servicio
ALTER TABLE alertas_servicio 
ADD COLUMN whatsapp_estado ENUM('pendiente', 'programado', 'enviado', 'entregado', 'leido', 'error', 'omitido') DEFAULT 'pendiente',
ADD COLUMN whatsapp_fecha_programada TIMESTAMP NULL,
ADD COLUMN whatsapp_fecha_enviada TIMESTAMP NULL,
ADD COLUMN whatsapp_template_id INT NULL,
ADD COLUMN whatsapp_mensaje_id VARCHAR(100) NULL,
ADD COLUMN whatsapp_error_mensaje TEXT NULL,
ADD COLUMN whatsapp_intentos_envio INT DEFAULT 0,
ADD COLUMN whatsapp_ultimo_intento TIMESTAMP NULL;
```

### Nuevas Tablas:

#### 1. whatsapp_templates
```sql
CREATE TABLE whatsapp_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    contenido TEXT NOT NULL,
    tipo_servicio VARCHAR(100) DEFAULT 'general',
    variables JSON DEFAULT NULL, -- ["cliente", "vehiculo", "servicio", "tiempo"]
    activo BOOLEAN DEFAULT TRUE,
    uso_count INT DEFAULT 0,
    conversion_rate DECIMAL(5,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Templates iniciales
INSERT INTO whatsapp_templates (nombre, contenido, tipo_servicio, variables) VALUES
('recordatorio_general', 'Hola {{cliente}}, te escribimos de SAG Garage, hace 6 meses trajiste tu {{vehiculo}} a {{servicio}}. Es momento de volver a hacerlo ¿te esperamos?', 'general', '["cliente", "vehiculo", "servicio"]'),
('full_service', 'Hola {{cliente}}! 👋 Tu {{vehiculo}} necesita su Full Service. En SAG Garage cuidamos tu auto como si fuera nuestro. ¿Agendamos cita?', 'Full Service', '["cliente", "vehiculo"]'),
('cambio_aceite', '¡Hola {{cliente}}! 🚗 Es hora del cambio de aceite de tu {{vehiculo}}. Protege tu motor con nosotros en SAG Garage. ¿Cuándo vienes?', 'Cambio de Aceite', '["cliente", "vehiculo"]'),
('verificacion', 'Hola {{cliente}}, tu {{vehiculo}} necesita su verificación. En SAG Garage te ayudamos a cumplir a tiempo. ¿Te esperamos?', 'Verificación', '["cliente", "vehiculo"]');
```

#### 2. whatsapp_config
```sql
CREATE TABLE whatsapp_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    clave VARCHAR(100) UNIQUE NOT NULL,
    valor TEXT NOT NULL,
    descripcion TEXT,
    tipo ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

#### 3. whatsapp_blacklist
```sql
CREATE TABLE whatsapp_blacklist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    telefono VARCHAR(20) NOT NULL UNIQUE,
    cliente_id INT NULL,
    motivo VARCHAR(255) NOT NULL,
    fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    agregado_por_id INT NULL,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
    FOREIGN KEY (agregado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL
);
```

#### 4. whatsapp_logs
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
    FOREIGN KEY (alerta_id) REFERENCES alertas_servicio(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES whatsapp_templates(id) ON DELETE SET NULL
);
```

---

## ⏰ Cron Jobs

### Job 1: Generación de Alertas (12:00 AM)
```bash
# Crontab entry
0 0 * * * /usr/bin/php /path/to/backend-php/cron/generar_alertas.php >> /var/log/alertas_generacion.log 2>&1
```

**Archivo: `backend-php/cron/generar_alertas.php`**
```php
<?php
require_once '../config/database.php';
require_once '../controllers/AlertasController.php';

$db = Database::getConnection();
$alertasController = new AlertasController($db);

// Generar alertas (lógica existente)
$resultado = $alertasController->generarAlertasAutomatico();

// Log del resultado
$logMessage = date('Y-m-d H:i:s') . " - Alertas generadas: " . $resultado['alertas_generadas'] . "\n";
file_put_contents('/var/log/alertas_generacion.log', $logMessage, FILE_APPEND);
?>
```

### Job 2: Envío WhatsApp (10:00 AM - Días Hábiles)
```bash
# Crontab entry
0 10 * * 1-5 /usr/bin/php /path/to/backend-php/cron/enviar_whatsapp.php >> /var/log/whatsapp_envio.log 2>&1
```

**Archivo: `backend-php/cron/enviar_whatsapp.php`**
```php
<?php
require_once '../config/database.php';
require_once '../controllers/WhatsappController.php';

$db = Database::getConnection();
$whatsappController = new WhatsappController($db);

// Verificar si el sistema está activo
if (!$whatsappController->isSistemaActivo()) {
    exit("Sistema WhatsApp inactivo\n");
}

// Procesar cola de mensajes
$resultado = $whatsappController->procesarColaWhatsApp();

// Log del resultado
$logMessage = date('Y-m-d H:i:s') . " - Mensajes enviados: " . $resultado['enviados'] . 
              ", Errores: " . $resultado['errores'] . "\n";
file_put_contents('/var/log/whatsapp_envio.log', $logMessage, FILE_APPEND);
?>
```

---

## 📱 WhatsApp Business API

### Configuración Inicial:
1. **Meta for Developers**: Crear app de WhatsApp Business
2. **Obtener credenciales**:
   - `WHATSAPP_TOKEN`
   - `PHONE_NUMBER_ID` 
   - `WEBHOOK_VERIFY_TOKEN`
3. **Webhook URL**: `https://tudominio.com/backend-php/webhook/whatsapp.php`

### Estructura de Mensaje:
```json
{
  "messaging_product": "whatsapp",
  "to": "521234567890",
  "type": "text",
  "text": {
    "preview_url": false,
    "body": "Mensaje personalizado aquí"
  }
}
```

### Estados de Mensaje:
- `pendiente` - Alerta creada, esperando envío
- `programado` - En cola para envío
- `enviado` - Mensaje enviado a WhatsApp API
- `entregado` - WhatsApp confirmó entrega
- `leido` - Cliente leyó el mensaje
- `error` - Falló el envío
- `omitido` - No se envió (blacklist, límites, etc.)

---

## 📝 Sistema de Plantillas

### Variables Disponibles:
- `{{cliente}}` - Nombre del cliente
- `{{vehiculo}}` - Marca + Modelo del vehículo
- `{{servicio}}` - Último servicio realizado
- `{{tiempo}}` - Tiempo transcurrido (ej: "6 meses")
- `{{telefono_sag}}` - Teléfono de contacto SAG
- `{{direccion_sag}}` - Dirección del taller

### Ejemplo de Template:
```
Hola {{cliente}}! 👋

Tu {{vehiculo}} necesita mantenimiento. En SAG Garage hace {{tiempo}} 
realizamos {{servicio}} y es momento del siguiente servicio.

¿Cuándo nos visitas? 
📞 {{telefono_sag}}
📍 {{direccion_sag}}

¡Te esperamos! 🚗✨
```

### Selección Inteligente de Template:
1. **Por tipo de servicio** - Template específico si existe
2. **Por historial del cliente** - A/B testing basado en conversiones
3. **Template general** - Fallback por defecto

---

## 🚫 Control Anti-Spam

### Reglas de Negocio:
1. **Un mensaje por cliente por mes** máximo
2. **Verificar blacklist** antes de envío
3. **Límite diario** configurable (ej: 100 mensajes/día)
4. **Cooldown period** de 30 días entre mensajes
5. **Solo días hábiles** (Lunes-Viernes)
6. **Horario fijo** (10:00 AM México)

### Validaciones Pre-Envío:
```php
function puedeEnviarMensaje($clienteId, $telefono) {
    // 1. Verificar blacklist
    if (estaEnBlacklist($telefono)) return false;
    
    // 2. Verificar cooldown
    if (tieneEnvioReciente($clienteId, 30)) return false;
    
    // 3. Verificar límite diario
    if (excedeLimiteDiario()) return false;
    
    // 4. Verificar teléfono válido
    if (!esNumeroValido($telefono)) return false;
    
    return true;
}
```

---

## 📊 Logging y Monitoreo

### Logs Principales:
1. **`/var/log/alertas_generacion.log`** - Generación diaria de alertas
2. **`/var/log/whatsapp_envio.log`** - Envíos de WhatsApp
3. **`/var/log/whatsapp_webhook.log`** - Respuestas de clientes
4. **`whatsapp_logs` table** - Base de datos completa

### Métricas a Monitorear:
- Alertas generadas diariamente
- Mensajes enviados exitosamente
- Tasa de entrega (delivery rate)
- Tasa de lectura (read rate)
- Errores y fallos
- Clientes que responden
- ROI por campaña

### Dashboard Básico:
- Total mensajes enviados (hoy/mes)
- Tasa de éxito de envío
- Clientes en blacklist
- Próximos envíos programados
- Costos mensuales WhatsApp
- Alertas sin procesar

---

## 📁 Estructura de Archivos

```
backend-php/
├── cron/
│   ├── generar_alertas.php        # Job medianoche
│   └── enviar_whatsapp.php        # Job 10:00 AM
├── controllers/
│   ├── AlertasController.php      # Existente (modificado)
│   └── WhatsappController.php     # Nuevo
├── webhook/
│   └── whatsapp.php              # Webhook para respuestas
├── config/
│   ├── database.php              # Existente
│   └── whatsapp.php              # Nueva configuración
└── utils/
    ├── WhatsappAPI.php           # Cliente API WhatsApp
    └── TemplateEngine.php        # Motor de plantillas
```

---

## ⚙️ Configuración

### Variables de Entorno (.env):
```env
# WhatsApp Business API
WHATSAPP_TOKEN=your_access_token_here
WHATSAPP_PHONE_NUMBER_ID=123456789
WHATSAPP_WEBHOOK_TOKEN=your_webhook_verify_token
WHATSAPP_API_VERSION=v17.0

# Configuración SAG
SAG_TELEFONO=+52XXXXXXXXXX
SAG_DIRECCION="Dirección del taller"
SAG_HORARIOS="Lun-Vie 8:00-18:00"

# Sistema
TIMEZONE=America/Mexico_City
DEBUG_MODE=false
```

### Configuración en Base de Datos:
```sql
-- Activar sistema
UPDATE whatsapp_config SET valor = 'true' WHERE clave = 'activo';

-- Configurar límites
UPDATE whatsapp_config SET valor = '50' WHERE clave = 'limite_mensajes_dia';

-- Configurar token API
UPDATE whatsapp_config SET valor = 'TU_TOKEN_AQUI' WHERE clave = 'api_token';
```

---

## 🧪 Plan de Testing

### Fase 1: Testing Unitario
- [ ] Generación de alertas (sin WhatsApp)
- [ ] Validación anti-spam
- [ ] Sistema de templates
- [ ] Conexión a base de datos

### Fase 2: Testing de Integración
- [ ] Cron jobs en servidor
- [ ] API WhatsApp en sandbox
- [ ] Webhook de respuestas
- [ ] Logs completos

### Fase 3: Testing con Datos Reales
- [ ] Números de prueba del equipo
- [ ] Validar templates personalizados
- [ ] Verificar estados de mensaje
- [ ] Monitorear costos

### Checklist Pre-Producción:
- [ ] Configurar cron jobs
- [ ] Verificar credenciales API
- [ ] Configurar webhook
- [ ] Backup de base de datos
- [ ] Números de emergencia en blacklist
- [ ] Límites conservadores al inicio

---

## 🚀 Deployment

### Paso 1: Base de Datos
```bash
mysql -u usuario -p base_de_datos < database/whatsapp_schema.sql
```

### Paso 2: Archivos PHP
```bash
# Subir archivos nuevos
rsync -av backend-php/cron/ servidor:/path/to/backend-php/cron/
rsync -av backend-php/controllers/WhatsappController.php servidor:/path/to/backend-php/controllers/
```

### Paso 3: Configurar Cron Jobs
```bash
# En el servidor
crontab -e

# Agregar:
0 0 * * * /usr/bin/php /path/to/backend-php/cron/generar_alertas.php >> /var/log/alertas_generacion.log 2>&1
0 10 * * 1-5 /usr/bin/php /path/to/backend-php/cron/enviar_whatsapp.php >> /var/log/whatsapp_envio.log 2>&1
```

### Paso 4: Configurar WhatsApp API
1. Ir a Meta for Developers
2. Crear app WhatsApp Business
3. Obtener token y phone_number_id
4. Configurar webhook URL

### Paso 5: Activar Sistema
```sql
UPDATE whatsapp_config SET valor = 'true' WHERE clave = 'activo';
```

---

## 💰 Costos Estimados

### WhatsApp Business API:
- **Costo por mensaje**: $0.56 MXN
- **50 mensajes/mes**: $28 MXN
- **100 mensajes/mes**: $56 MXN
- **200 mensajes/mes**: $112 MXN

### Desarrollo:
- **Implementación única**: $2,500 MXN
- **Soporte mensual**: $150-200 MXN (opcional)

### ROI Estimado:
```
Si 1 de cada 20 mensajes genera una visita:
- 100 mensajes = 5 clientes
- 5 clientes × $1,500 promedio = $7,500
- Costo: $56 mensajes + $200 soporte = $256
- ROI: 2,930%
```

---

## 🔧 Troubleshooting

### Problemas Comunes:

#### "No se generan alertas"
```bash
# Verificar cron job
grep "generar_alertas" /var/log/cron

# Ejecutar manualmente
php backend-php/cron/generar_alertas.php

# Verificar permisos
ls -la backend-php/cron/
```

#### "Error de API WhatsApp"
```php
// Verificar token
$response = WhatsappAPI::testConnection();
var_dump($response);

// Verificar configuración
SELECT * FROM whatsapp_config WHERE clave IN ('api_token', 'phone_number_id');
```

#### "Mensajes no se envían"
```sql
-- Verificar alertas pendientes
SELECT COUNT(*) FROM alertas_servicio WHERE whatsapp_estado = 'pendiente';

-- Verificar blacklist
SELECT telefono FROM whatsapp_blacklist;

-- Verificar límites
SELECT valor FROM whatsapp_config WHERE clave = 'limite_mensajes_dia';
```

#### "Cliente no recibe mensaje"
1. Verificar número en formato internacional (+521234567890)
2. Confirmar que WhatsApp está instalado
3. Revisar logs de entrega
4. Verificar que no esté en blacklist

---

## 📋 Checklist de Implementación

### Pre-Implementación:
- [ ] Backup completo de base de datos
- [ ] Documentar configuración actual
- [ ] Definir números de prueba
- [ ] Obtener credenciales WhatsApp API

### Desarrollo:
- [ ] Crear tablas nuevas
- [ ] Modificar tabla alertas_servicio
- [ ] Desarrollar WhatsappController
- [ ] Crear cron jobs
- [ ] Implementar sistema de templates
- [ ] Crear webhook para respuestas
- [ ] Sistema de logs

### Testing:
- [ ] Probar generación de alertas
- [ ] Validar anti-spam
- [ ] Testing con números de prueba
- [ ] Verificar webhook
- [ ] Probar cron jobs
- [ ] Validar logs

### Producción:
- [ ] Configurar cron jobs
- [ ] Activar sistema
- [ ] Monitorear primeros envíos
- [ ] Ajustar límites si es necesario
- [ ] Documentar para el equipo

---

## 📞 Contacto y Soporte

**Desarrollador**: Marco Candiani  
**Implementación**: $2,500 MXN  
**Soporte**: $150-200 MXN/mes (opcional)

### Incluye:
- ✅ Desarrollo completo
- ✅ Configuración de cron jobs
- ✅ Integration WhatsApp API
- ✅ Sistema anti-spam
- ✅ Templates personalizados
- ✅ Dashboard básico
- ✅ Documentación técnica
- ✅ Capacitación al equipo

---

*Documento creado: 30/03/2026*  
*Versión: 1.0*  
*Estado: Diseño técnico completo*