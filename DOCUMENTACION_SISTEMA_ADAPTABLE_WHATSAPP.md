# 🔄 SISTEMA ADAPTABLE DE TEMPLATES WHATSAPP - SAG GARAGE

**Implementado:** 12/04/2026  
**Versión:** 2.0.0

## 📋 Descripción General

El sistema adaptable permite cambiar dinámicamente entre dos modos de interacción con clientes:

1. **MODO SIMPLE** - Plantilla con texto numerado (YA APROBADA)
2. **MODO INTERACTIVE** - Plantilla con botones interactivos (PENDIENTE APROBACIÓN)

## ⚙️ Configuración del Sistema

### Base de Datos

Las configuraciones se almacenan en la tabla `twilio_config`:

```sql
-- Configuraciones principales
template_mode = 'simple'                    -- Modo activo: 'simple' o 'interactive'
template_simple_sid = 'HX183daf481204160ef29a837ce1b22ecb'      -- Template aprobado
template_interactive_sid = 'HX765eae763cf778deacde6238674d4108'  -- Template pendiente
max_intentos_respuesta = '3'                -- Máximo intentos para respuesta válida
mensaje_ayuda_respuesta = 'Por favor responde solo con el *NÚMERO*...'  -- Mensaje de ayuda
```

### Cambio de Modo

Para cambiar entre modos, actualizar la configuración:

```sql
-- ACTIVAR MODO SIMPLE (temporal)
UPDATE twilio_config SET config_value = 'simple' WHERE config_key = 'template_mode';

-- ACTIVAR MODO INTERACTIVE (cuando se apruebe)
UPDATE twilio_config SET config_value = 'interactive' WHERE config_key = 'template_mode';
```

## 🎯 MODO SIMPLE (Activo)

### Características
- ✅ **YA APROBADO** por WhatsApp
- 📝 Usa plantilla con texto numerado
- 🔢 Cliente responde con números (1-9)
- 🛡️ Validación robusta para "clientes estúpidos"

### Plantilla Simple
```
🚗✨ ¡Tenemos opciones para ti, {1}!

Nos encantaría ayudarte a agendar el próximo servicio de tu vehículo 🔧

📅 Estos son los horarios disponibles:
{2}

💬 Contesta con el *NUMERO* de opción que mejor te funcione y con gusto lo apartamos para ti.
```

### Variables
- `{1}` = Nombre del cliente (Pedro)
- `{2}` = Lista de horarios numerados:
  ```
  1. Lunes 10 am
  2. Lunes 3 pm
  3. Martes 2 pm
  ...
  8. Viernes 4 pm
  9. Otro horario
  ```

### Flujo de Respuesta
1. Cliente responde: **"2"**
2. Sistema valida número (1-9)
3. Si válido → Procesa selección
4. Si inválido → Envía mensaje de ayuda

### Validación Robusta
```php
// Acepta respuestas como:
"2"           ✅
" 3 "         ✅ 
"Opción 4"    ✅
"otro"        ✅ (→ opción 9)
"contacto"    ✅ (→ opción 9)

// Rechaza:
"abc"         ❌
"10"          ❌
""            ❌
```

## 🔮 MODO INTERACTIVE (Futuro)

### Características
- ⏳ **PENDIENTE APROBACIÓN** por WhatsApp
- 🔘 Plantilla con botones interactivos
- 🎛️ Cliente hace clic en botones
- 📊 Mejor experiencia de usuario

### Flujo Interactive
1. Cliente ve botones clicables
2. Sistema recibe `ButtonId` del webhook
3. Procesa selección automáticamente

## 🔧 Implementación Técnica

### Archivo Principal: `TwilioConversationalBot.php`

```php
// Detección automática del modo
$templateMode = $this->obtenerConfiguracion('template_mode');

if ($templateMode === 'simple') {
    return $this->enviarCalendarioTemplateSimple($alertaId, $alerta, $slots);
} else {
    return $this->enviarCalendarioTemplateInteractive($alertaId, $alerta, $slots);
}
```

### Webhook: `twilio_whatsapp.php`

```php
case 'esperando_fecha':
    // MODO INTERACTIVE: Buscar ButtonId
    $slotId = $webhookData['Button'] ?? $webhookData['ButtonId'] ?? '';
    if (!empty($slotId)) {
        return $bot->procesarSeleccionSlot($alerta['id'], $slotId, $messageSid);
    }
    
    // MODO SIMPLE: Buscar número
    if (preg_match('/^\s*([1-9])\s*$/', trim($body))) {
        return $bot->procesarRespuestaNumericaSimple($alerta['id'], $body, $messageSid);
    }
```

## 📊 Métodos Clave

### Modo Simple
- `enviarCalendarioTemplateSimple()`
- `formatearHorariosTextoNumerado()`
- `procesarRespuestaNumericaSimple()`
- `validarRespuestaNumericaRobusta()`
- `enviarMensajeAyudaValidacion()`

### Modo Interactive
- `enviarCalendarioTemplateInteractive()`
- `procesarSeleccionSlot()`

## 🎛️ Configuración de Templates

### SQL de Configuración
```sql
-- Ejecutar este archivo para configurar el sistema:
database/configuracion_templates_whatsapp.sql
```

### Verificar Configuración
```sql
SELECT config_key, config_value, description 
FROM twilio_config 
WHERE config_key LIKE 'template_%' OR config_key = 'template_mode';
```

## 🚀 Activación en Producción

### Paso 1: Modo Simple (Inmediato)
```sql
-- Activar modo simple con plantilla aprobada
UPDATE twilio_config SET config_value = 'simple' WHERE config_key = 'template_mode';
```

### Paso 2: Modo Interactive (Cuando se apruebe)
```sql
-- Cambiar a modo interactive
UPDATE twilio_config SET config_value = 'interactive' WHERE config_key = 'template_mode';
```

## 🛡️ Manejo de Errores

### Fallbacks Automáticos
1. Si falla template simple → Mensaje de contacto directo
2. Si respuesta inválida → Mensaje de ayuda (máximo 3 intentos)
3. Si template interactive falla → Fallback a modo simple

### Logging Detallado
```
[WEBHOOK-INFO] MODO SIMPLE - Analizando respuesta numérica: '2'
[WEBHOOK-INFO] MODO SIMPLE - Número detectado: 2
[TwilioBot] 📅 TwilioBot: procesarRespuestaNumericaSimple - Respuesta: '2'
[TwilioBot] 📅 TwilioBot: Slot seleccionado: 2024-04-15 10:00
```

## 🔄 Ventajas del Sistema Adaptable

### ✅ Beneficios
- **Flexibilidad total** - Cambio sin código
- **Zero downtime** - Switch instantáneo en BD
- **Compatibilidad backward** - Mantiene funcionalidad existente
- **Validación robusta** - Maneja clientes "problemáticos"
- **Logging completo** - Debug y monitoreo detallado

### 📈 Estrategia de Migración
1. **Fase 1** - Modo simple (inmediato)
2. **Fase 2** - Testing de modo interactive
3. **Fase 3** - Aprobación de WhatsApp
4. **Fase 4** - Activación de modo interactive
5. **Fase 5** - Monitoreo y optimización

## 🎯 Casos de Uso

### Cliente Inteligente (Modo Simple)
```
Bot: "1. Lunes 10 am\n2. Lunes 3 pm\n..."
Cliente: "2"
Bot: ✅ "¡Perfecto! Tu cita está pre-agendada..."
```

### Cliente "Estúpido" (Modo Simple)
```
Bot: "1. Lunes 10 am\n2. Lunes 3 pm\n..."
Cliente: "el lunes por la tarde"
Bot: ⚠️ "Por favor responde solo con el *NÚMERO*..."
Cliente: "2"
Bot: ✅ "¡Perfecto! Tu cita está pre-agendada..."
```

### Cliente Futuro (Modo Interactive)
```
Bot: [Botones clickeables]
Cliente: [Click en botón]
Bot: ✅ "¡Perfecto! Tu cita está pre-agendada..."
```

---

## ⚡ Estado Actual

- ✅ **Sistema implementado y funcional**
- ✅ **Modo simple configurado y listo**
- ⏳ **Esperando aprobación de plantilla interactive**
- 🎯 **Switch configurado para activación inmediata**

**¡El sistema está preparado para el futuro manteniendo funcionamiento actual!**