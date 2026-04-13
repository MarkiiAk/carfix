# 📋 ESTADOS ENUM - REFERENCIA RÁPIDA SAG GARAGE

## 🚨 **PROBLEMA RESUELTO**: Estado inválido 'esperando_seleccion_horario'

**Fecha**: 13/04/2026  
**Problema**: El código intentaba usar el estado `'esperando_seleccion_horario'` que NO EXISTE en el ENUM de la base de datos  
**Solución**: Corregido a `'esperando_fecha'` que SÍ existe en el ENUM  

---

## ⚙️ ESTADOS VÁLIDOS - TABLA `alertas_servicio`

### Campo: `estado_whatsapp` (ENUM)

```sql
enum('borrador','enviado','esperando_respuesta','esperando_fecha','pre_agendado','confirmado','rechazado','requiere_contacto','cancelado','completado')
```

### Estados Disponibles:

| Estado | Descripción | Uso en Flujo |
|--------|-------------|--------------|
| `'borrador'` | Alerta creada, no enviada | Estado inicial |
| `'enviado'` | Recordatorio enviado al cliente | Después del envío |
| `'esperando_respuesta'` | Esperando respuesta inicial del cliente | Flujo de respuesta |
| `'esperando_fecha'` | ✅ **Cliente interesado, enviando horarios** | **ESTADO CORRECTO** |
| `'pre_agendado'` | Cliente seleccionó fecha, esperando confirmación SAG | Pre-agendamiento |
| `'confirmado'` | SAG confirmó la cita | Cita confirmada |
| `'rechazado'` | Cliente rechazó el servicio | Cliente dijo "no" |
| `'requiere_contacto'` | Requiere contacto directo | Casos especiales |
| `'cancelado'` | Cita cancelada | SAG canceló |
| `'completado'` | Flujo completado | Estado final |

---

## ❌ ESTADOS INVÁLIDOS (NO USAR)

| Estado Inválido | Estado Correcto |
|-----------------|-----------------|
| ~~`'esperando_seleccion_horario'`~~ | `'esperando_fecha'` ✅ |
| ~~`'pendiente_respuesta'`~~ | `'esperando_respuesta'` ✅ |
| ~~`'en_proceso'`~~ | `'pre_agendado'` ✅ |
| ~~`'procesando'`~~ | `'pre_agendado'` ✅ |

---

## 🔧 OTROS ENUMS DEL SISTEMA

### Campo: `estado` (tabla alertas_servicio)
- `'pendiente'` ✅ - Alerta pendiente de procesar
- `'leida'` ✅ - Alerta leída pero no procesada  
- `'procesada'` ✅ - Alerta procesada

### Campo: `prioridad` (tabla alertas_servicio)  
- `'baja'` ✅
- `'media'` ✅  
- `'alta'` ✅

---

## 🛠️ VERIFICACIÓN ANTES DE COMMIT

### Comando de Búsqueda:
```bash
grep -r "esperando_seleccion_horario\|pendiente_respuesta\|en_proceso\|procesando" backend-php/
```

### Si encuentras estados inválidos:
1. Verificar en esta documentación cuál es el estado correcto
2. Actualizar el código con el estado válido
3. Probar que funciona correctamente

---

## 📝 NOTAS PARA DESARROLLADORES

- **SIEMPRE** consultar esta documentación antes de agregar nuevos estados
- **NO INVENTAR** nuevos estados sin verificar el schema
- Usar `DESCRIBE alertas_servicio;` en MySQL para ver ENUMs actuales
- Los estados deben ser descriptivos pero seguir la convención existente

---

## 🔍 CONSULTA RÁPIDA EN BD

```sql
-- Ver estructura completa
DESCRIBE alertas_servicio;

-- Ver solo ENUMs
SHOW COLUMNS FROM alertas_servicio LIKE 'estado_whatsapp';
```

---

**✅ PROBLEMA RESUELTO**: El sistema ya NO usa estados inválidos  
**🚀 READY TO GO**: Flujo WhatsApp funcionando correctamente