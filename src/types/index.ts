// Tipos principales del sistema de presupuestos SAG Garage

export interface TallerInfo {
  nombre: string;
  encargado: string;
  telefono: string;
  direccion: string;
  logo?: string;
}

export interface ClienteInfo {
  nombreCompleto: string;
  telefono: string;
  email: string;
  domicilio: string;
  cliente_id?: number | null;
}

export interface VehiculoInfo {
  marca: string;
  modelo: string;
  year?: string;
  color: string;
  placas: string;
  niv: string; // Número de Identificación Vehicular (VIN)
  kilometrajeEntrada: string;
  kilometrajeSalida: string;
  nivelGasolina: number; // 0-100 (porcentaje)
  vehiculo_id?: number | null;
}

export interface InspeccionVehiculo {
  exteriores: {
    lucesFrontales: boolean;
    cuartoLuces: boolean;
    antena: boolean;
    espejosLaterales: boolean;
    cristales: boolean;
    emblemas: boolean;
    llantas: boolean;
    taponRuedas: boolean;
    moldurasCompletas: boolean;
    taponGasolina: boolean;
    limpiadores: boolean;
  };
  interiores: {
    instrumentoTablero: boolean;
    calefaccion: boolean;
    sistemaSonido: boolean;
    bocinas: boolean;
    espejoRetrovisor: boolean;
    cinturones: boolean;
    botoniaGeneral: boolean;
    manijas: boolean;
    tapetes: boolean;
    vestiduras: boolean;
    otros: boolean;
  };
  danosAdicionales: DanoVehiculo[];
}

export interface DanoVehiculo {
  id: string;
  ubicacion: string; // ej: "Puerta delantera izquierda"
  tipo: string; // ej: "Rayón", "Golpe", "Abolladura"
  descripcion: string;
}

export interface Servicio {
  id: string;
  descripcion: string;
  precio: number;
}

export interface Refaccion {
  id: string;
  nombre: string;
  cantidad: number;
  precioCosto: number; // Precio de costo (lo que paga el taller)
  precioVenta: number; // Precio de venta (con 30% de ganancia)
  margenGanancia: number; // Porcentaje de ganancia (ej: 30)
  proveedor?: string;   // Proveedor opcional (ej: Autozone)
  total: number; // cantidad * precioVenta
}

export interface ManoDeObra {
  id: string;
  descripcion: string;
  precio: number;
}

export interface ResumenFinanciero {
  servicios: number;
  refacciones: number;
  manoDeObra: number;
  subtotal: number;
  incluirIVA: boolean;
  iva: number;
  total: number;
  anticipo: number;
  fecha_anticipo?: string | null;
  restante: number;
}

export interface Presupuesto {
  id: string;
  folio: string;
  fecha: Date;
  fechaEntrada: Date;
  fechaSalida?: Date;
  sucursal_id?: number | null;
  taller: TallerInfo;
  cliente: ClienteInfo;
  vehiculo: VehiculoInfo;
  inspeccion: InspeccionVehiculo;
  problemaReportado: string;
  diagnosticoTecnico: string;
  servicios: Servicio[];
  refacciones: Refaccion[];
  manoDeObra: ManoDeObra[];
  resumen: ResumenFinanciero;
  puntosSeguridad?: PuntoSeguridadOrden[];
}

export interface PolizaGarantia {
  cobertura: string;
  lugarGarantia: string;
  exclusiones: string[];
  responsabilidadCliente: string;
  tiempoRevision: string;
  alcance: string;
  horarios: string;
  traslado: string;
  responsabilidadLimitada: string[];
  ajustesSinCosto: string;
  revisionInmediata: string;
  aceptacion: string;
}

export type ThemeMode = 'light' | 'dark';

export interface AppState {
  presupuesto: Presupuesto;
  themeMode: ThemeMode;
  autoSave: boolean;
  lastSaved: Date | null;
}

// Tipos para el sistema de gestión de órdenes

// Nuevos tipos para Puntos de Seguridad

export interface EstadoSeguridad {
  id: number;
  nombre: string;
  color: string;
  icono: string;
  descripcion: string;
  orden: number;
  activo: boolean;
}

export interface PuntoSeguridadCatalogo {
  id: number;
  nombre: string;
  categoria: string;
  descripcion: string;
  ubicacion: string;
  orden: number;
  activo: boolean;
}

export interface PuntoSeguridadOrden {
  id: number;
  ordenId: number;
  puntoId: number;
  estadoId: number;
  observaciones: string;
  fotoUrl?: string;
  fechaRevision: string;
  revisadoPor?: string;
  punto?: PuntoSeguridadCatalogo;
  estado?: EstadoSeguridad;
}

export interface Orden {
  id: string;
  folio: string;
  folio_sucursal?: number | null;
  sucursal_id?: number | null;
  estado: 'recibido' | 'diagnostico' | 'en_reparacion' | 'listo_entrega' | 'entregado'
        | 'abierta' | 'cerrada' | 'pendiente'; // legacy: abierta/cerrada/pendiente para compatibilidad con datos pre-migración
  fechaCreacion: string;
  fechaActualizacion: string;
  fechaCierre?: string;
  creadoPor?: string;
  modificadoPor?: string;
  taller: TallerInfo;
  cliente: ClienteInfo;
  vehiculo: VehiculoInfo;
  inspeccion: InspeccionVehiculo;
  problemaReportado: string;
  diagnosticoTecnico: string;
  servicios: Servicio[];
  refacciones: Refaccion[];
  manoDeObra: ManoDeObra[];
  resumen: ResumenFinanciero;
  puntosSeguridad?: PuntoSeguridadOrden[];
  fecha_entregada?: string | null;
}

export interface Sucursal {
  id: number;
  nombre: string;
  direccion?: string | null;
  telefono?: string | null;
  activo?: boolean;
}

export interface Usuario {
  id: string;
  username: string;
  nombre: string;
  rol: 'sistemas' | 'superusuario' | 'admin_sucursal' | 'admin';
  sucursal_activa_id?: number;
  sucursales_permitidas?: number[];
  sucursal_nombre?: string;
}

export interface AuthContextType {
  user: Usuario | null;
  token: string | null;
  login: (username: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
  isAuthenticated: boolean;
  isLoading: boolean;
  sucursalActiva: Sucursal | null;
  sucursalesPermitidas: Sucursal[];
  switchSucursal: (sucursalId: number) => Promise<void>;
}

// Tipos para el módulo de Clientes y Vehículos (Mes 1 Roadmap Q2 2026)

export interface ClienteListItem {
  id: number;
  nombre: string;
  telefono: string | null;
  email: string | null;
  total_visitas: number;
  ultima_visita: string | null;
  total_vehiculos: number;
}

export interface OrdenResumen {
  id: number;
  numero_orden: string;
  fecha_ingreso: string;
  servicio_principal: string;
  total: number;
  estado: string;
  kilometraje_entrada: string | null;
}

export interface VehiculoConHistorial {
  id: number;
  marca: string;
  modelo: string;
  anio: string | null;
  placas: string;
  niv: string | null;
  ordenes: OrdenResumen[];
}

export interface ResumenFinancieroCliente {
  total_gastado: number;
  total_servicios: number;
  total_refacciones: number;
  total_iva: number;
}

export interface ClientePerfil {
  cliente: {
    id: number;
    nombre: string;
    telefono: string | null;
    email: string | null;
    total_visitas: number;
    ultima_visita: string | null;
  };
  resumen_financiero: ResumenFinancieroCliente;
  vehiculos: VehiculoConHistorial[];
}

// Tipos para el visor de conversaciones WhatsApp (M2-002)

export type DireccionMensaje = 'inbound' | 'outbound';

export interface MensajeConversacion {
  id: number;
  direction: DireccionMensaje;
  mensaje: string | null;
  estado: string | null;
  conversation_step: string;
  created_at: string;
}

export interface ConversacionResponse {
  success: boolean;
  mensajes: MensajeConversacion[];
  error?: string;
}

// Tipos para el módulo de Gastos Internos por Orden

export interface GastoOrden {
  id: number;
  concepto: string;
  monto: number;
  tipo: 'envio' | 'consumible' | 'propina' | 'otro';
  registrado_por_nombre: string;
  created_at: string;
}
// Tipos para el módulo de Ingresos (M2-005, Q2 2026)

export interface ResumenPeriodo {
  total_facturado: number;
  ingresos_servicios: number;
  ingresos_mano_obra: number;
  ingresos_refacciones: number;
  total_iva: number;
  num_ordenes: number;
}

export interface MargenRefacciones {
  vendido: number;
  costo: number;
  margen: number;
  margen_pct: number;
}

export interface TopCliente {
  id: number;
  nombre: string;
  telefono: string | null;
  num_visitas: number;
  total_gastado: number;
}

export interface TopServicio {
  descripcion: string;
  veces: number;
  total_generado: number;
}

export interface IngresosDia {
  dia: string;
  total: number;
}

export interface ResumenFinancieroResponse {
  success: boolean;
  periodo: {
    tipo: string;
    fecha_inicio: string;
    fecha_fin: string;
    label: string;
  };
  resumen: ResumenPeriodo;
  refacciones: MargenRefacciones;
  top_servicios: TopServicio[];
  top_clientes: TopCliente[];
  por_dia: IngresosDia[];
}

export interface GastoAdmin {
  id: number;
  mes: number;
  anio: number;
  concepto: string;
  monto: number;
  categoria: 'renta' | 'salario' | 'servicio' | 'insumo' | 'otro';
  registrado_por_nombre: string;
  created_at: string;
}

export interface GastosAdminResponse {
  success: boolean;
  mes: number;
  anio: number;
  gastos: GastoAdmin[];
  total_facturado: number;
  total_iva: number;
  ingresos_servicios: number;
  ingresos_mano_obra: number;
  ingresos_refacciones: number;
  costo_refacciones: number;
  margen_refacciones: number;
  ingresos_netos: number;
  total_admin: number;
  gastos_ordenes_mes: number;
  utilidad_neta: number;
  total_sueldos_periodo: number;
  total_pagos_fijos_periodo: number;
}

export interface ServicioOrdenFinanciero {
  descripcion: string;
  subtotal: number;
}

export interface RefaccionOrdenFinanciero {
  descripcion: string;
  proveedor: string | null;
  subtotal: number;
  precio_costo?: number | null;  // null = registro anterior a 2026-05-25 (usar subtotal/1.30)
  cantidad?: number;
}

export interface OrdenFinanciero {
  id: number;
  numero_orden: string;
  fecha: string;
  cliente_nombre: string;
  vehiculo: string;
  costo_venta: number;
  costo_refacciones: number;   // costo de compra (sin margen 30%)
  costo_interno?: number;      // costos internos de la orden (total, para el TOTAL row)
  gastos_internos?: Array<{ tipo: string; concepto: string; monto: number }>; // desglose por concepto
  iva?: number;                // IVA de la orden (0 en órdenes abiertas/anticipo)
  ganancia: number;
  estado: string;
  /** Flujo de caja real:
   *  'apertura' = orden abierta (anticipo en su semana)
   *  'anticipo' = orden ya cerrada, pero mostrando el anticipo en la semana que entró
   *  'cierre'   = orden cerrada mostrando el restante (total − anticipo) en la semana de entrega */
  tipo_fila?: 'apertura' | 'anticipo' | 'cierre';
  servicios: ServicioOrdenFinanciero[];
  refacciones_detalle: RefaccionOrdenFinanciero[];
}

export interface OrdenesFinancieroResponse {
  success: boolean;
  ordenes: OrdenFinanciero[];
  totales: {
    costo_venta: number;
    costo_refacciones: number;
    ganancia: number;
  };
}

export interface EmpleadoSueldo {
  id: number;
  usuario_id: number | null;
  nombre: string;
  puesto: string | null;
  sueldo_diario: number;
  tipo_sueldo: 'diario' | 'semanal';   // 'diario' = tarifa_diaria; 'semanal' = sueldo_diario/7 tarifa efectiva
  fecha_inicio: string;    // 'YYYY-MM-DD'
  fecha_fin: string | null;
  activo: boolean;
  dias_trabajados?: number; // días trabajados en la semana activa (0-7, default 5)
}

export interface PagoFijo {
  id: number;
  concepto: string;
  monto: number;
  fecha_inicio: string;    // 'YYYY-MM-DD'
  fecha_fin: string | null;
  frecuencia: 'semanal' | 'mensual';
  categoria: 'renta' | 'servicio' | 'proveedor' | 'marketing' | 'otro';
  activo: boolean;
}

export interface MovimientoCajaChica {
  id: number;
  fecha: string;
  tipo: 'ingreso' | 'egreso';
  concepto: string;
  monto: number;
  notas: string | null;
  gasto_admin_id: number | null;
}

export interface CajaChicaResponse {
  success: boolean;
  movimientos: MovimientoCajaChica[];
  saldo_anterior: number;
  ingresos_semana: number;
  egresos_semana: number;
  saldo_actual: number;
}
