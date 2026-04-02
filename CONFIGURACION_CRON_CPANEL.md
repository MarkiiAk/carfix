# Configuración de Cron Jobs en cPanel - SAG Garage

## 🎯 Objetivo
Configurar los cron jobs automáticos para que se ejecuten sin intervención manual en el servidor de producción Neubox.

## ⚡ Configuración Completa

### 1. Cron Job: Generar Alertas
**Ejecuta a las 1:00 PM todos los días**

- **Minuto**: `0`
- **Hora**: `13`
- **Día**: `*`
- **Mes**: `*`
- **Día de la semana**: `*`
- **Comando**: `/usr/local/bin/php /home/saggarag/public_html/gestion/backend-php/cron/generar_alertas.php`

### 2. Cron Job: Enviar WhatsApp  
**Ejecuta a las 1:05 PM solo días hábiles (Lunes-Viernes)**

- **Minuto**: `5`
- **Hora**: `13`
- **Día**: `*`
- **Mes**: `*`
- **Día de la semana**: `1-5`
- **Comando**: `/usr/local/bin/php /home/saggarag/public_html/gestion/backend-php/cron/enviar_whatsapp.php`

## 📋 Pasos para Configurar en cPanel

### 1. Acceder a Cron Jobs
1. Inicia sesión en cPanel de Neubox
2. Busca la sección **"Cron Jobs"**
3. Haz clic en **"Cron Jobs"**

### 2. Configurar Email de Notificaciones
- **Cron Email**: `saggarag` (o el email que prefieras)
- Esto enviará notificaciones si hay errores

### 3. Agregar Primer Cron Job (Generar Alertas)
1. En **"Add New Cron Job"**
2. Completa los campos:
   - **Minute**: `10`
   - **Hour**: `11`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `*`
   - **Command**: `/usr/local/bin/php /home/saggarag/public_html/gestion/backend-php/cron/generar_alertas.php`
3. Clic en **"Add New Cron Job"**

### 4. Agregar Segundo Cron Job (Enviar WhatsApp)
1. En **"Add New Cron Job"**
2. Completa los campos:
   - **Minute**: `15`
   - **Hour**: `11`
   - **Day**: `*`
   - **Month**: `*`
   - **Weekday**: `1-5`
   - **Command**: `/usr/local/bin/php /home/saggarag/public_html/gestion/backend-php/cron/enviar_whatsapp.php`
3. Clic en **"Add New Cron Job"**

## ✅ Verificación

Después de configurar, deberías ver en **"Current Cron Jobs"**:

| Minuto | Hora | Día | Mes | Día de la semana | Comando |
|--------|------|-----|-----|-----------------|---------|
| 10 | 11 | * | * | * | /usr/local/bin/php /home/saggarag/public_html/gestion/backend-php/cron/generar_alertas.php |
| 15 | 11 | * | * | 1-5 | /usr/local/bin/php /home/saggarag/public_html/gestion/backend-php/cron/enviar_whatsapp.php |

## 🔧 Modificaciones de Scripts Realizadas

### ✅ Cambios Implementados:
1. **Eliminadas validaciones de horario específico** de ambos scripts
2. **Mantenida validación de días hábiles** en script WhatsApp
3. **Mantenida protección contra ejecución múltiple** el mismo día
4. **Control total de horarios transferido a cPanel cron**

### ✅ Beneficios:
- **Flexibilidad total**: Cambiar horarios solo desde cPanel
- **Sin conflictos**: No hay validaciones que bloqueen la ejecución
- **Seguridad**: Protegido contra ejecuciones duplicadas
- **Simplicidad**: Control centralizado en cPanel

## 🕐 Horarios de Ejecución

### Configuración Actual:
- **Generar Alertas**: 11:10 AM (todos los días)
- **Enviar WhatsApp**: 11:15 AM (solo días hábiles)

### Para Cambiar Horarios:
1. Ve a cPanel → Cron Jobs
2. Encuentra el cron job que quieres modificar
3. Haz clic en **"Edit"**
4. Cambia los valores de **Minute** y **Hour**
5. Guarda los cambios

**Ejemplos de otros horarios:**
- Para 8:00 AM: `Minute: 0, Hour: 8`
- Para 6:30 PM: `Minute: 30, Hour: 18`
- Para cada 2 horas: `Minute: 0, Hour: */2`

## 📊 Monitoreo y Verificación

### 📄 Logs de Archivos:
- **Generar Alertas**: `/home/saggarag/public_html/logs/sag_alertas_generacion.log`
- **Enviar WhatsApp**: `/home/saggarag/public_html/logs/sag_whatsapp_envio.log`

### 🗄️ Logs de Base de Datos:

#### Generación de Alertas:
- **Tabla**: `alertas_ejecucion_log`
- **Contenido**: Registro de cada ejecución diaria, alertas generadas, tiempo de ejecución
- **Query para verificar**: 
  ```sql
  SELECT * FROM alertas_ejecucion_log ORDER BY fecha_ejecucion DESC LIMIT 10;
  ```

#### Envío de WhatsApp:
- **Tabla**: `whatsapp_logs`
- **Contenido**: Log completo de cada mensaje enviado, estado, errores
- **Query para verificar**:
  ```sql
  SELECT * FROM whatsapp_logs WHERE DATE(created_at) = CURDATE() ORDER BY created_at DESC;
  ```

#### Estadísticas Diarias:
- **Tabla**: `whatsapp_estadisticas_diarias`
- **Contenido**: Estadísticas agregadas por día (mensajes, costos, conversiones)
- **Query para verificar**:
  ```sql
  SELECT * FROM whatsapp_estadisticas_diarias ORDER BY fecha DESC LIMIT 7;
  ```

#### Vista de Dashboard:
- **Vista**: `vista_whatsapp_dashboard`
- **Contenido**: Resumen de estadísticas en tiempo real
- **Query para verificar**:
  ```sql
  SELECT * FROM vista_whatsapp_dashboard;
  ```

### 🔍 Cómo Verificar Ejecución:

#### 1. Logs de Archivos:
1. Accede por FTP/File Manager a `/home/saggarag/public_html/logs/`
2. Revisa los archivos `.log` para timestamps y resultados
3. Los logs muestran inicio, progreso y finalización con códigos de salida

#### 2. Logs de Base de Datos:
1. Conéctate a la base de datos vía phpMyAdmin o similar
2. Ejecuta las queries de verificación mostradas arriba
3. Revisa fechas, horarios y resultados de las ejecuciones

#### 3. Verificación Rápida:
```sql
-- ¿Se ejecutó hoy el generador de alertas?
SELECT * FROM alertas_ejecucion_log WHERE fecha_ejecucion = CURDATE();

-- ¿Se enviaron WhatsApp hoy?
SELECT COUNT(*) as mensajes_hoy FROM whatsapp_logs WHERE DATE(created_at) = CURDATE();

-- ¿Hay errores recientes?
SELECT * FROM whatsapp_logs WHERE estado = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY);
```

## ⚠️ Notas Importantes

1. **Timezone**: Los scripts usan `America/Mexico_City` automáticamente
2. **Ruta PHP**: Neubox usa `/usr/local/bin/php` (confirmado en la interfaz)
3. **Ruta Absoluta**: Siempre usar `/home/saggarag/public_html/` como base
4. **Emails**: cPanel enviará email si hay errores en la ejecución
5. **Testing**: Los scripts funcionan sin validaciones de horario, totalmente controlados por cron

## 🚀 Estado Actual
- ✅ Scripts modificados para control total por cron
- ✅ Validaciones de horario eliminadas
- ✅ Protección contra duplicación mantenida
- ✅ Documentación completa para cPanel
- 🟡 **PENDIENTE**: Configurar en cPanel de producción

---

**Configuración completada el**: 31/03/2026 10:35 AM  
**Próxima acción**: Subir a producción y configurar cron jobs en cPanel Neubox