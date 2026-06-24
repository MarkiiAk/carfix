# Estados Enum — Referencia Canónica CarFix

Este archivo es la fuente de verdad de todos los valores de estado del sistema.
**No inventar nuevos estados sin actualizar este archivo primero.**

---

## Estados de alertas_servicio.estado_whatsapp

| Valor BD | Significado |
|----------|-------------|
| borrador | Alerta generada por el cron, aún no enviada |
| enviado | Mensaje WhatsApp enviado al cliente |
| esperando_respuesta | Bot esperando respuesta SÍ/NO del cliente |
| esperando_fecha | Cliente dijo SÍ, bot esperando selección de horario |
| pre_agendado | Cliente seleccionó un horario, pendiente confirmación de SAG |
| confirmado | Admin confirmó la cita |
| rechazado | Cliente dijo NO o rechazó la cita |
| requiere_contacto | Cliente mandó input inválido 2 veces — necesita atención personalizada |
| cancelado | Cita cancelada por el admin |
| completado | Flujo completado |

### Transiciones válidas (WhatsApp)
```
borrador → enviado → esperando_respuesta → esperando_fecha → pre_agendado → confirmado
                                        ↘ rechazado
esperando_respuesta → requiere_contacto (2 inputs inválidos)
esperando_fecha     → requiere_contacto (2 inputs inválidos)
```

---

## Estados de alertas_servicio.estado

| Valor BD | Significado |
|----------|-------------|
| pendiente | Alerta no leída |
| leida | Alerta marcada como leída |

---

## Estados de citas_pre_agendadas.estado

| Valor BD | Significado |
|----------|-------------|
| pre_agendada | Cita solicitada, pendiente de confirmación |
| confirmada | Cita confirmada por admin |
| cancelada | Cita cancelada |

---

## Estados de ordenes_servicio.estado

| Valor BD | Label UI Kanban | Significado |
|----------|----------------|-------------|
| recibido | Recibido | Vehículo ingresó al taller, aún no se revisa |
| diagnostico | Diagnóstico | Mecánico revisando el vehículo |
| en_reparacion | En reparación | Trabajo en curso |
| listo_entrega | Listo para entrega | Trabajo terminado, cliente no ha recogido |
| entregado | Entregado | Cliente recogió el vehículo |

### Transiciones válidas (órdenes)
```
recibido → diagnostico → en_reparacion → listo_entrega → entregado
```

### Regla financiera
Solo `entregado` cuenta como ingreso en FinancieroController.
Los estados `en_reparacion`, `diagnostico`, `recibido` y `listo_entrega` son órdenes abiertas.

### Migración de valores legacy
| Valor legacy | Valor nuevo |
|-------------|-------------|
| pendiente | recibido |
| abierta | en_reparacion |
| cerrada | entregado |
| completado | entregado |
| completada | entregado |
| entregada | entregado |

> Nota: el campo `estado` en `ordenes_servicio` es `VARCHAR(20)`, no ENUM.
> No se requiere ALTER TABLE MODIFY para agregar valores nuevos.
