import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus, faTrash, faSpinner, faPencil, faCheck, faXmark, faDownload, faChevronDown, faChevronUp } from '@fortawesome/free-solid-svg-icons';
import { financieroAPI, gastosAdminAPI, empleadosFinancieroAPI, pagosFijosAPI, cajaChicaAPI } from '../services/api';
import { abrirReporteFinanciero } from '../utils/reporteFinancieroHTML';
import { useAuth } from '../contexts/AuthContext';
import { TablaOrdenesDesglosada } from '../components/financiero/TablaOrdenesDesglosada';
import type {
  ResumenFinancieroResponse,
  TopServicio,
  TopCliente,
  GastoAdmin,
  GastosAdminResponse,
  OrdenFinanciero,
  EmpleadoSueldo,
  PagoFijo,
  CajaChicaResponse,
} from '../types';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const formatMoneda = (monto: number): string =>
  new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
  }).format(monto);


// ---------------------------------------------------------------------------
// Cards KPI
// ---------------------------------------------------------------------------

interface KpiCardProps {
  label: string;
  value: number;
  highlight?: boolean;
  sub?: string;
}

const KpiCard = ({ label, value, highlight = false, sub }: KpiCardProps) => (
  <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-4 flex flex-col gap-1">
    <p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium">
      {label}
    </p>
    <p
      className={`text-2xl font-bold leading-tight ${
        highlight
          ? 'text-sag-500'
          : 'text-gray-900 dark:text-white'
      }`}
    >
      {formatMoneda(value)}
    </p>
    {sub && (
      <p className="text-xs text-gray-400 dark:text-gray-500">{sub}</p>
    )}
  </div>
);

// ---------------------------------------------------------------------------
// Barra de distribución horizontal
// ---------------------------------------------------------------------------

interface BarraDistribucionProps {
  totalSueldos: number;
  costoRefacciones: number;
  totalFijos: number;
  gastosVariables: number;
  gastosOrdenes: number;
  gananciaNeta: number;
  totalBase: number;
}

const BarraDistribucion = ({ totalSueldos, costoRefacciones, totalFijos, gastosVariables, gastosOrdenes, gananciaNeta, totalBase }: BarraDistribucionProps) => {
  if (totalBase <= 0) return null;
  const widthPct = (v: number) => `${Math.max(0, (v / totalBase) * 100).toFixed(1)}%`;
  const fmt = (v: number) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 0 }).format(v);
  const segmentos = [
    { label: 'Sueldos',         valor: totalSueldos,       color: 'bg-indigo-500',  textColor: 'text-indigo-600 dark:text-indigo-400' },
    { label: 'Refacciones',     valor: costoRefacciones,   color: 'bg-amber-500',   textColor: 'text-amber-600 dark:text-amber-400' },
    { label: 'Costos fijos',    valor: totalFijos,         color: 'bg-rose-500',    textColor: 'text-rose-600 dark:text-rose-400' },
    { label: 'Gastos varios',   valor: gastosVariables,    color: 'bg-teal-500',    textColor: 'text-teal-600 dark:text-teal-400' },
    { label: 'Costos internos', valor: gastosOrdenes,      color: 'bg-orange-500',  textColor: 'text-orange-600 dark:text-orange-400' },
    {
      label: gananciaNeta >= 0 ? 'Ganancia' : 'Déficit',
      valor: Math.abs(gananciaNeta),
      color: gananciaNeta >= 0 ? 'bg-sag-500' : 'bg-red-500',
      textColor: gananciaNeta >= 0 ? 'text-sag-500' : 'text-red-500',
    },
  ].filter(s => s.valor > 0);

  return (
    <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-4">
      <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
        ¿A dónde fue cada peso?
      </p>
      <div className="w-full h-4 rounded-full overflow-hidden flex bg-gray-100 dark:bg-gray-700 mb-4">
        {segmentos.map(s => (
          <div
            key={s.label}
            className={`h-full ${s.color} transition-all duration-500`}
            style={{ width: widthPct(s.valor) }}
            title={`${s.label}: ${fmt(s.valor)}`}
          />
        ))}
      </div>
      <div className="grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2">
        {segmentos.map(s => (
          <div key={s.label} className="flex items-center gap-2">
            <span className={`w-2.5 h-2.5 rounded-sm flex-shrink-0 ${s.color}`} />
            <div className="min-w-0">
              <p className="text-xs text-gray-500 dark:text-gray-400 truncate">{s.label}</p>
              <p className={`text-sm font-semibold tabular-nums ${s.textColor}`}>{fmt(s.valor)}</p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};


// ---------------------------------------------------------------------------
// Top servicios
// ---------------------------------------------------------------------------

interface TopServiciosProps {
  servicios: TopServicio[];
}

const TopServicios = ({ servicios }: TopServiciosProps) => {
  if (servicios.length === 0) {
    return (
      <p className="text-sm text-gray-400 dark:text-gray-500 py-4">
        Sin servicios registrados en este periodo.
      </p>
    );
  }

  const maximo = servicios[0].total_generado;

  return (
    <div className="space-y-3">
      {servicios.map((s) => {
        const pct = maximo > 0 ? (s.total_generado / maximo) * 100 : 0;
        return (
          <div key={s.descripcion}>
            <div className="flex items-center justify-between mb-1 gap-2">
              <span className="text-sm text-gray-700 dark:text-gray-300 truncate flex-1">
                {s.descripcion}
              </span>
              <div className="flex items-center gap-2 flex-shrink-0">
                <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
                  {formatMoneda(s.total_generado)}
                </span>
                <span className="text-xs text-gray-400 dark:text-gray-500 w-12 text-right">
                  {s.veces}x
                </span>
              </div>
            </div>
            <div className="h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
              <div
                className="h-full bg-sag-500 rounded-full transition-all duration-500"
                style={{ width: `${pct}%` }}
              />
            </div>
          </div>
        );
      })}
    </div>
  );
};

// ---------------------------------------------------------------------------
// Top clientes
// ---------------------------------------------------------------------------

interface TopClientesProps { clientes: TopCliente[]; }

const TopClientes = ({ clientes }: TopClientesProps) => {
  if (clientes.length === 0) {
    return <p className="text-sm text-gray-400 dark:text-gray-500 py-4">Sin clientes en este periodo.</p>;
  }
  const maximo = clientes[0].total_gastado;
  return (
    <div className="space-y-3">
      {clientes.map((c, i) => {
        const pct = maximo > 0 ? Math.round((c.total_gastado / maximo) * 100) : 0;
        return (
          <div key={c.id} className="space-y-1">
            <div className="flex items-center justify-between text-sm">
              <div className="flex items-center gap-2 min-w-0">
                <span className="text-xs font-bold text-gray-400 dark:text-gray-500 w-4">{i + 1}</span>
                <span className="font-medium text-gray-800 dark:text-gray-200 truncate">{c.nombre}</span>
                <span className="text-xs text-gray-400 hidden sm:inline">· {c.num_visitas} {c.num_visitas === 1 ? 'visita' : 'visitas'}</span>
              </div>
              <span className="font-semibold text-gray-700 dark:text-gray-300 flex-shrink-0 ml-2">
                {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 0 }).format(c.total_gastado)}
              </span>
            </div>
            <div className="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
              <div className="bg-sag-500 h-1.5 rounded-full transition-all" style={{ width: `${pct}%` }} />
            </div>
          </div>
        );
      })}
    </div>
  );
};


// Calcula el label de una semana dado un offset (0 = semana actual)
const labelSemana = (offset: number): string => {
  const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  const hoy = new Date();
  const diaSemana = hoy.getDay() === 0 ? 7 : hoy.getDay(); // 1=lun … 7=dom
  const lunes = new Date(hoy);
  lunes.setDate(hoy.getDate() - (diaSemana - 1) - offset * 7);
  const domingo = new Date(lunes);
  domingo.setDate(lunes.getDate() + 6);
  const fmt = (d: Date) => `${d.getDate()} ${meses[d.getMonth()]}`;
  return `Sem. ${fmt(lunes)} – ${fmt(domingo)}`;
};

// ---------------------------------------------------------------------------
// Estado vacío
// ---------------------------------------------------------------------------

const EstadoVacio = ({ label }: { label: string }) => (
  <div className="flex flex-col items-center justify-center py-16 gap-4">
    <svg className="w-14 h-14 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
    </svg>
    <div className="text-center">
      <p className="text-gray-600 dark:text-gray-400 font-medium">Sin ordenes cerradas</p>
      <p className="text-sm text-gray-400 dark:text-gray-500 mt-1">{label}</p>
    </div>
  </div>
);

// ---------------------------------------------------------------------------
// Gastos administrativos — constantes
// ---------------------------------------------------------------------------

const MESES = [
  'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

const CATEGORIA_LABELS: Record<GastoAdmin['categoria'], string> = {
  renta:    'Renta',
  salario:  'Salario',
  servicio: 'Servicio',
  insumo:   'Insumo',
  otro:     'Otro',
};

const CATEGORIA_OPTIONS: GastoAdmin['categoria'][] = ['renta', 'salario', 'servicio', 'insumo', 'otro'];

// ---------------------------------------------------------------------------
// Página principal
// ---------------------------------------------------------------------------

export const Financiero = () => {
  const { user } = useAuth();
  const navigate = useNavigate();

  const [tipoPeriodo, setTipoPeriodo] = useState<'semana' | 'mes'>('semana');
  const [offset, setOffset] = useState(0);
  const [datos, setDatos]   = useState<ResumenFinancieroResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError]   = useState<string | null>(null);

  // Tabs de la página
  const [tabActivo, setTabActivo] = useState<'resumen' | 'config'>('resumen');

  // Estado de accordions en tab Configuración
  const [openSueldos, setOpenSueldos]       = useState(true);
  const [openCostosFijos, setOpenCostosFijos] = useState(true);
  const [openCajaChica, setOpenCajaChica]   = useState(true);
  const [openGastos, setOpenGastos]         = useState(false);

  // mes/año derivados del período seleccionado — usados por gastosAdmin
  // En semana: calcular el mes del lunes de la semana seleccionada
  // En mes: restar el offset de meses al mes actual
  const hoy = new Date();
  const fechaPeriodo = (() => {
    if (tipoPeriodo === 'semana') {
      const diaSemana = hoy.getDay() || 7;
      const lunes = new Date(hoy);
      lunes.setDate(hoy.getDate() - diaSemana + 1 - offset * 7);
      return lunes;
    }
    return new Date(hoy.getFullYear(), hoy.getMonth() - offset, 1);
  })();
  const mesSel  = fechaPeriodo.getMonth() + 1;
  const anioSel = fechaPeriodo.getFullYear();

  // Label del período activo
  const labelPeriodoActivo = tipoPeriodo === 'semana'
    ? labelSemana(offset)
    : new Date(anioSel, mesSel - 1, 1)
        .toLocaleDateString('es-MX', { month: 'long', year: 'numeric' })
        .replace(/^./, c => c.toUpperCase());

  // Estado para gastos administrativos
  const [gastosAdmin, setGastosAdmin]           = useState<GastosAdminResponse | null>(null);
  const [loadingAdmin, setLoadingAdmin]         = useState(true);
  const [confirmandoAdminId, setConfirmandoAdminId] = useState<number | null>(null);
  const [eliminandoAdminId, setEliminandoAdminId]   = useState<number | null>(null);
  const [guardandoAdmin, setGuardandoAdmin]     = useState(false);

  // Formulario nuevo gasto admin
  const [adminConcepto, setAdminConcepto]     = useState('');
  const [adminMonto, setAdminMonto]           = useState('');
  const [adminCategoria, setAdminCategoria]   = useState<GastoAdmin['categoria']>('otro');
  const [adminError, setAdminError]           = useState<string | null>(null);

  // Estado para tabla de órdenes desglosadas
  const [ordenes, setOrdenes]             = useState<OrdenFinanciero[]>([]);
  const [ordTotales, setOrdTotales]       = useState({ costo_venta: 0, costo_refacciones: 0, ganancia: 0 });
  const [loadingOrdenes, setLoadingOrdenes] = useState(false);

  // Estado para empleados y pagos fijos
  const [empleados, setEmpleados]           = useState<EmpleadoSueldo[]>([]);
  const [pagosFijos, setPagosFijos]         = useState<PagoFijo[]>([]);
  const [loadingEmpleados, setLoadingEmpleados] = useState(false);
  const [loadingPagos, setLoadingPagos]     = useState(false);

  // Fechas del período activo — se llenan cuando llega la respuesta de financieroAPI.resumen
  // Se usan para filtrar empleados y pagos fijos vigentes en ese período
  const [periodoFechaInicio, setPeriodoFechaInicio] = useState<string | null>(null);
  const [periodoFechaFin, setPeriodoFechaFin]       = useState<string | null>(null);

  // Formulario empleados
  const [empEditId, setEmpEditId]                   = useState<number | null>(null);
  const [empNombre, setEmpNombre]                   = useState('');
  const [empPuesto, setEmpPuesto]                   = useState('');
  const [empSueldo, setEmpSueldo]                   = useState('');
  const [empSueldoOriginal, setEmpSueldoOriginal]   = useState('');  // para detectar si cambió
  const [empTipoSueldo, setEmpTipoSueldo]           = useState<'diario' | 'semanal'>('diario');
  const [empFechaInicioCambio, setEmpFechaInicioCambio] = useState(() => new Date().toISOString().split('T')[0]);
  const [empGuardando, setEmpGuardando]             = useState(false);
  const [empError, setEmpError]                     = useState<string | null>(null);
  // Dar de baja
  const [empBajaId, setEmpBajaId]                   = useState<number | null>(null);
  const [empBajaNombre, setEmpBajaNombre]           = useState('');
  const [empBajaFecha, setEmpBajaFecha]             = useState('');
  const [empBajando, setEmpBajando]                 = useState(false);
  // Ex-empleados (dados de baja: fecha_fin <= hoy)
  const [mostrarExEmpleados, setMostrarExEmpleados] = useState(false);
  const [mostrarFormEmp, setMostrarFormEmp]         = useState(false);

  // Formulario pagos fijos
  const [pagoEditId, setPagoEditId]                     = useState<number | null>(null);
  const [pagoConcepto, setPagoConcepto]                 = useState('');
  const [pagoMonto, setPagoMonto]                       = useState('');
  const [pagoMontoOriginal, setPagoMontoOriginal]       = useState('');  // para detectar si cambió
  const [pagoFechaInicioCambio, setPagoFechaInicioCambio] = useState(() => new Date().toISOString().split('T')[0]);
  const [pagoFechaFinCambio, setPagoFechaFinCambio]       = useState('');
  const [pagoFrecuencia, setPagoFrecuencia]             = useState<PagoFijo['frecuencia']>('mensual');
  const [pagoCategoria, setPagoCategoria]               = useState<PagoFijo['categoria']>('otro');
  const [pagoGuardando, setPagoGuardando]               = useState(false);
  const [pagoError, setPagoError]                       = useState<string | null>(null);
  const [mostrarFormPago, setMostrarFormPago]           = useState(false);

  // Estado caja chica
  const [cajaChica, setCajaChica]         = useState<CajaChicaResponse | null>(null);
  const [cargandoCaja, setCargandoCaja]   = useState(false);
  const [cajaTipo, setCajaTipo]           = useState<'ingreso' | 'egreso'>('egreso');
  const [cajaConcepto, setCajaConcepto]   = useState('');
  const [cajaMonto, setCajaMonto]         = useState('');
  const [cajaFecha, setCajaFecha]         = useState(() => new Date().toISOString().split('T')[0]);
  const [cajaError, setCajaError]         = useState('');

  // Redirigir si no es admin
  useEffect(() => {
    if (user && user.rol !== 'admin') {
      navigate('/dashboard', { replace: true });
    }
  }, [user, navigate]);

  const cargar = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const resultado = await financieroAPI.resumen(tipoPeriodo, offset);
      setDatos(resultado);
      // Guardar fechas del período para filtrar empleados y pagos fijos vigentes
      if (resultado.periodo?.fecha_inicio && resultado.periodo?.fecha_fin) {
        setPeriodoFechaInicio(resultado.periodo.fecha_inicio);
        setPeriodoFechaFin(resultado.periodo.fecha_fin);
      }
    } catch {
      setError('No se pudo cargar la informacion de ingresos.');
    } finally {
      setIsLoading(false);
    }
  }, [tipoPeriodo, offset]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  const cargarOrdenes = useCallback(async () => {
    setLoadingOrdenes(true);
    try {
      const res = await financieroAPI.ordenes(tipoPeriodo, offset);
      setOrdenes(res.ordenes);
      setOrdTotales(res.totales);
    } catch {
      // silencioso — la tabla mostrará vacío
    } finally {
      setLoadingOrdenes(false);
    }
  }, [tipoPeriodo, offset]);

  useEffect(() => {
    cargarOrdenes();
  }, [cargarOrdenes]);

  const cargarAdmin = useCallback(async () => {
    setLoadingAdmin(true);
    try {
      const res = await gastosAdminAPI.listar(tipoPeriodo, offset);
      setGastosAdmin({
        ...res,
        total_facturado:      Number(res.total_facturado),
        total_iva:            Number(res.total_iva),
        ingresos_servicios:   Number(res.ingresos_servicios),
        ingresos_mano_obra:   Number(res.ingresos_mano_obra),
        ingresos_refacciones: Number(res.ingresos_refacciones),
        costo_refacciones:    Number(res.costo_refacciones),
        margen_refacciones:   Number(res.margen_refacciones),
        ingresos_netos:       Number(res.ingresos_netos),
        total_admin:          Number(res.total_admin),
        gastos_ordenes_mes:   Number(res.gastos_ordenes_mes),
        utilidad_neta:        Number(res.utilidad_neta),
        gastos: res.gastos.map(g => ({ ...g, monto: Number(g.monto) })),
      });
    } catch {
      // silencioso — no romper la página de ingresos
    } finally {
      setLoadingAdmin(false);
    }
  }, [tipoPeriodo, offset]);

  const cargarEmpleados = useCallback(async (fechaInicio?: string, fechaFin?: string) => {
    setLoadingEmpleados(true);
    try {
      const res = await empleadosFinancieroAPI.listar(fechaInicio, fechaFin);
      setEmpleados(res.empleados);
    } catch {
      // silencioso
    } finally {
      setLoadingEmpleados(false);
    }
  }, []);

  const cargarPagosFijos = useCallback(async (fechaInicio?: string, fechaFin?: string) => {
    setLoadingPagos(true);
    try {
      const res = await pagosFijosAPI.listar(fechaInicio, fechaFin);
      setPagosFijos(res.pagos_fijos);
    } catch {
      // silencioso
    } finally {
      setLoadingPagos(false);
    }
  }, []);

  const cargarCajaChica = useCallback(async () => {
    setCargandoCaja(true);
    try {
      const res = await cajaChicaAPI.resumen(tipoPeriodo === 'semana' ? 'semana' : 'mes', offset);
      setCajaChica(res);
    } catch {
      // silencioso
    } finally {
      setCargandoCaja(false);
    }
  }, [tipoPeriodo, offset]);

  useEffect(() => {
    // Pasar las fechas del período cuando estén disponibles para obtener los registros
    // vigentes en ese período específico (no los actuales)
    cargarEmpleados(periodoFechaInicio ?? undefined, periodoFechaFin ?? undefined);
    cargarPagosFijos(periodoFechaInicio ?? undefined, periodoFechaFin ?? undefined);
  }, [cargarEmpleados, cargarPagosFijos, periodoFechaInicio, periodoFechaFin]);

  useEffect(() => {
    cargarCajaChica();
  }, [cargarCajaChica]);

  useEffect(() => {
    cargarAdmin();
  }, [cargarAdmin]);

  // Handlers para empleados
  const abrirFormEmp = (emp?: EmpleadoSueldo) => {
    const hoy = new Date().toISOString().split('T')[0];
    if (emp) {
      setEmpEditId(emp.id);
      setEmpNombre(emp.nombre);
      setEmpPuesto(emp.puesto ?? '');
      setEmpSueldo(String(emp.sueldo_diario));
      setEmpSueldoOriginal(String(emp.sueldo_diario));
      setEmpTipoSueldo(emp.tipo_sueldo ?? 'diario');
    } else {
      setEmpEditId(null);
      setEmpNombre('');
      setEmpPuesto('');
      setEmpSueldo('');
      setEmpSueldoOriginal('');
      setEmpTipoSueldo('diario');
    }
    setEmpFechaInicioCambio(hoy);
    setEmpError(null);
    setMostrarFormEmp(true);
  };

  const cancelarFormEmp = () => {
    setMostrarFormEmp(false);
    setEmpEditId(null);
    setEmpError(null);
  };

  const guardarEmpleado = async () => {
    const nombre = empNombre.trim();
    const sueldo = parseFloat(empSueldo);
    const labelSueldo = empTipoSueldo === 'semanal' ? 'semanal' : 'diario';
    if (!nombre) { setEmpError('El nombre es obligatorio.'); return; }
    if (isNaN(sueldo) || sueldo < 0) { setEmpError(`Sueldo ${labelSueldo} inválido.`); return; }
    setEmpError(null);
    setEmpGuardando(true);
    try {
      if (empEditId !== null) {
        const sueldoCambio = sueldo !== parseFloat(empSueldoOriginal);
        const payload: Parameters<typeof empleadosFinancieroAPI.actualizar>[1] = {
          nombre,
          puesto: empPuesto || null,
          sueldo_diario: sueldo,
          tipo_sueldo: empTipoSueldo,
        };
        if (sueldoCambio) {
          payload.fecha_inicio_cambio = empFechaInicioCambio;
        }
        await empleadosFinancieroAPI.actualizar(empEditId, payload);
      } else {
        await empleadosFinancieroAPI.crear({
          nombre,
          puesto: empPuesto || null,
          sueldo_diario: sueldo,
          tipo_sueldo: empTipoSueldo,
          usuario_id: null,
          fecha_inicio: empFechaInicioCambio,
          fecha_fin: null,
        });
      }
      await cargarEmpleados(periodoFechaInicio ?? undefined, periodoFechaFin ?? undefined);
      cancelarFormEmp();
    } catch {
      setEmpError('Error al guardar. Intenta de nuevo.');
    } finally {
      setEmpGuardando(false);
    }
  };

  const confirmarDarDeBaja = (emp: EmpleadoSueldo) => {
    const hoy = new Date().toISOString().split('T')[0];
    setEmpBajaId(emp.id);
    setEmpBajaNombre(emp.nombre);
    setEmpBajaFecha(hoy);
  };

  const ejecutarDarDeBaja = async () => {
    if (empBajaId === null) return;
    setEmpBajando(true);
    try {
      await empleadosFinancieroAPI.actualizar(empBajaId, { fecha_fin: empBajaFecha, activo: false });
      setEmpBajaId(null);
      setEmpBajaNombre('');
      setEmpBajaFecha('');
      await cargarEmpleados(periodoFechaInicio ?? undefined, periodoFechaFin ?? undefined);
    } catch {
      // silencioso — el error de red no debería bloquear el modal
      setEmpBajaId(null);
    } finally {
      setEmpBajando(false);
    }
  };

  const toggleEmpleado = async (id: number) => {
    try {
      const res = await empleadosFinancieroAPI.toggle(id);
      setEmpleados(prev => prev.map(e => e.id === id ? { ...e, activo: res.activo } : e));
    } catch { /* silencioso */ }
  };

  const reactivarEmpleado = async (id: number) => {
    try {
      await empleadosFinancieroAPI.actualizar(id, { fecha_fin: null, activo: true });
      await cargarEmpleados(periodoFechaInicio ?? undefined, periodoFechaFin ?? undefined);
    } catch { /* silencioso */ }
  };

  // Handlers para pagos fijos
  const abrirFormPago = (pago?: PagoFijo) => {
    const hoy = new Date().toISOString().split('T')[0];
    if (pago) {
      setPagoEditId(pago.id);
      setPagoConcepto(pago.concepto);
      setPagoMonto(String(pago.monto));
      setPagoMontoOriginal(String(pago.monto));
      setPagoFrecuencia(pago.frecuencia);
      setPagoCategoria(pago.categoria);
    } else {
      setPagoEditId(null);
      setPagoConcepto('');
      setPagoMonto('');
      setPagoMontoOriginal('');
      setPagoFrecuencia('mensual');
      setPagoCategoria('otro');
    }
    setPagoFechaInicioCambio(hoy);
    setPagoFechaFinCambio(pago?.fecha_fin ?? '');
    setPagoError(null);
    setMostrarFormPago(true);
  };

  const cancelarFormPago = () => {
    setMostrarFormPago(false);
    setPagoEditId(null);
    setPagoError(null);
  };

  const guardarPagoFijo = async () => {
    const concepto = pagoConcepto.trim();
    const monto = parseFloat(pagoMonto);
    if (!concepto) { setPagoError('El concepto es obligatorio.'); return; }
    if (isNaN(monto) || monto <= 0) { setPagoError('El monto debe ser mayor a $0.'); return; }
    setPagoError(null);
    setPagoGuardando(true);
    try {
      if (pagoEditId !== null) {
        const montoCambio = monto !== parseFloat(pagoMontoOriginal);
        const payload: Parameters<typeof pagosFijosAPI.actualizar>[1] = {
          concepto,
          monto,
          frecuencia: pagoFrecuencia,
          categoria: pagoCategoria,
        };
        if (montoCambio) {
          payload.fecha_inicio_cambio = pagoFechaInicioCambio;
        }
        if (pagoFechaFinCambio) {
          payload.fecha_fin = pagoFechaFinCambio;
        }
        await pagosFijosAPI.actualizar(pagoEditId, payload);
      } else {
        await pagosFijosAPI.crear({
          concepto,
          monto,
          frecuencia: pagoFrecuencia,
          categoria: pagoCategoria,
          fecha_inicio: pagoFechaInicioCambio,
          fecha_fin: null,
        });
      }
      await cargarPagosFijos(periodoFechaInicio ?? undefined, periodoFechaFin ?? undefined);
      cancelarFormPago();
    } catch {
      setPagoError('Error al guardar. Intenta de nuevo.');
    } finally {
      setPagoGuardando(false);
    }
  };

  const togglePagoFijo = async (id: number) => {
    try {
      const res = await pagosFijosAPI.toggle(id);
      setPagosFijos(prev => prev.map(p => p.id === id ? { ...p, activo: res.activo } : p));
    } catch { /* silencioso */ }
  };

  const handleAgregarCaja = async () => {
    if (!cajaConcepto.trim() || !cajaMonto || parseFloat(cajaMonto) <= 0) {
      setCajaError('Completa todos los campos.');
      return;
    }
    try {
      await cajaChicaAPI.crear({
        fecha: cajaFecha,
        tipo: cajaTipo,
        concepto: cajaConcepto.trim(),
        monto: parseFloat(cajaMonto),
        notas: null,
      });
      setCajaConcepto('');
      setCajaMonto('');
      setCajaError('');
      await cargarCajaChica();
    } catch {
      setCajaError('Error al guardar. Intenta de nuevo.');
    }
  };

  const handleEliminarCaja = async (id: number) => {
    try {
      await cajaChicaAPI.eliminar(id);
      await cargarCajaChica();
    } catch { /* silencioso */ }
  };

  // Totales calculados para la gráfica de distribución
  // Tarifa diaria efectiva: diario → sueldo_diario; semanal → sueldo_diario / 7
  const tarifaDiariaEfectiva = (e: EmpleadoSueldo): number =>
    (e.tipo_sueldo ?? 'diario') === 'semanal'
      ? Number(e.sueldo_diario) / 7
      : Number(e.sueldo_diario);

  // Separar empleados vigentes (en plantilla) de ex-empleados (dados de baja)
  // Usar la fecha fin del período seleccionado como referencia para vigencia.
  // Así un empleado dado de baja después del período visualizado aparece como vigente
  // en esa semana (era activo entonces), no como ex-empleado.
  const fechaRefPeriodo   = periodoFechaFin ?? new Date().toISOString().split('T')[0];
  const empleadosVigentes = empleados.filter(e => !e.fecha_fin || e.fecha_fin > fechaRefPeriodo);
  const exEmpleados       = empleados.filter(e => e.fecha_fin != null && e.fecha_fin <= fechaRefPeriodo);

  const totalSueldosActivos = empleadosVigentes
    .filter(e => e.activo)
    .reduce((acc, e) => acc + tarifaDiariaEfectiva(e) * (tipoPeriodo === 'semana' ? 5 : 22), 0);

  const totalPagosFijosActivos = pagosFijos
    .filter(p => p.activo)
    .reduce((acc, p) => {
      if (p.frecuencia === 'semanal') return acc + p.monto * (tipoPeriodo === 'semana' ? 1 : 4);
      return acc + (tipoPeriodo === 'semana' ? p.monto / 4 : p.monto);
    }, 0);

  // Handler para descargar el reporte (abre HTML en nueva ventana + print)
  const handleDescargarReporte = () => {
    if (!datos || !gastosAdmin) return;
    abrirReporteFinanciero({
      resumen: datos,
      ordenes: {
        success: true,
        ordenes,
        totales: ordTotales,
      },
      empleados,
      pagosFijos,
      gastos: gastosAdmin,
      cajaChica,
      labelPeriodo: labelPeriodoActivo,
      tipoPeriodo,
    });
  };

  // --- Loading ---
  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="text-center space-y-3">
          <div className="animate-spin h-10 w-10 border-4 border-sag-500 border-t-transparent rounded-full mx-auto" />
          <p className="text-gray-500 dark:text-gray-400 text-sm">Cargando ingresos...</p>
        </div>
      </div>
    );
  }

  // --- Error ---
  if (error) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4">
        <p className="text-gray-600 dark:text-gray-400">{error}</p>
        <button
          onClick={cargar}
          className="text-sm text-sag-600 dark:text-sag-400 hover:underline"
        >
          Reintentar
        </button>
      </div>
    );
  }

  if (!datos) return null;

  const { periodo, resumen, top_servicios, top_clientes } = datos;
  const sinDatos = resumen.num_ordenes === 0;

  return (
    <>
    <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

      {/* ------------------------------------------------------------------ */}
      {/* Header con selector de período (tipo + flechas)                     */}
      {/* ------------------------------------------------------------------ */}
      <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-5">
        <div className="flex items-center justify-between mb-4">
          <h1 className="text-lg font-bold text-gray-900 dark:text-white">
            Ingresos
          </h1>
          <button
            onClick={handleDescargarReporte}
            disabled={sinDatos || isLoading || !gastosAdmin}
            className="flex items-center gap-2 px-4 py-2 bg-sag-500 text-gray-900 rounded-lg text-sm font-semibold hover:bg-sag-400 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
          >
            <FontAwesomeIcon icon={faDownload} className="text-xs" />
            Descargar reporte
          </button>
        </div>

        {/* Toggle Semana / Mes */}
        <div className="flex justify-center mb-4">
          <div className="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 p-0.5 gap-0.5">
            {(['semana', 'mes'] as const).map(t => (
              <button
                key={t}
                onClick={() => { setTipoPeriodo(t); setOffset(0); }}
                className={`px-4 py-1.5 text-sm font-medium rounded-md transition-colors ${
                  tipoPeriodo === t
                    ? 'bg-sag-500 text-gray-900 shadow-sm'
                    : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                }`}
              >
                {t === 'semana' ? 'Semana' : 'Mes'}
              </button>
            ))}
          </div>
        </div>

        {/* Selector de flechas */}
        <div className="flex items-center justify-center gap-3">
          <button
            onClick={() => setOffset(o => o + 1)}
            className="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
            title="Período anterior"
          >
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <span className="text-sm font-semibold text-gray-700 dark:text-gray-200 min-w-[160px] text-center capitalize">
            {labelPeriodoActivo}
          </span>
          <button
            onClick={() => setOffset(o => Math.max(0, o - 1))}
            disabled={offset === 0}
            className="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
            title="Período siguiente"
          >
            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>
      </div>

      {/* ------------------------------------------------------------------ */}
      {/* Tabs: Resumen / Configuración                                        */}
      {/* ------------------------------------------------------------------ */}
      <div className="flex border-b border-gray-200 dark:border-gray-700">
        {(['resumen', 'config'] as const).map(tab => (
          <button
            key={tab}
            onClick={() => setTabActivo(tab)}
            className={`px-5 py-2.5 text-sm font-medium border-b-2 transition-colors ${
              tabActivo === tab
                ? 'border-sag-500 text-sag-500'
                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
            }`}
          >
            {tab === 'resumen' ? 'Resumen' : 'Configuración'}
          </button>
        ))}
      </div>

      {/* ================================================================== */}
      {/* TAB: RESUMEN                                                         */}
      {/* ================================================================== */}
      {tabActivo === 'resumen' && (
        <>
          {/* --------------------------------------------------------------- */}
          {/* Estado vacío                                                      */}
          {/* --------------------------------------------------------------- */}
          {sinDatos ? (
            <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
              <EstadoVacio label={`No hay ordenes cerradas en ${periodo.label.toLowerCase()}.`} />
            </div>
          ) : (
            <>
              {/* ----------------------------------------------------------- */}
              {/* 1. Cards KPI                                                  */}
              {/* ----------------------------------------------------------- */}
              <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <KpiCard
                  label="Total facturado"
                  value={resumen.total_facturado}
                  highlight
                  sub={`${resumen.num_ordenes} ${resumen.num_ordenes === 1 ? 'orden' : 'ordenes'}`}
                />
                <KpiCard label="Servicios" value={resumen.ingresos_servicios} />
                <KpiCard label="Mano de obra" value={resumen.ingresos_mano_obra} />
                <KpiCard label="Refacciones" value={resumen.ingresos_refacciones} />
              </div>

              {/* ----------------------------------------------------------- */}
              {/* 2. Barra de distribución + Balance (requieren gastosAdmin)    */}
              {/* ----------------------------------------------------------- */}
              {gastosAdmin !== null && !loadingAdmin && (() => {
                const totalFacturado    = datos?.resumen.total_facturado ?? 0;
                const costoRefas        = datos?.refacciones.costo ?? 0;
                const ivaDelPeriodo     = datos?.resumen.total_iva ?? 0;
                const ingresoNeto       = totalFacturado - costoRefas;
                const gastosVarsRaw     = Number(gastosAdmin.total_admin ?? 0);
                const gastosOrdenesVal  = Number(gastosAdmin.gastos_ordenes_mes ?? 0);
                const gananciaNetaFinal =
                  ingresoNeto - totalSueldosActivos - totalPagosFijosActivos - gastosVarsRaw - gastosOrdenesVal;
                const tituloPeriodo     =
                  tipoPeriodo === 'semana'
                    ? labelPeriodoActivo
                    : `${MESES[mesSel - 1]} ${anioSel}`;

                return (
                  <>
                    {/* Barra de distribución horizontal */}
                    <BarraDistribucion
                      totalSueldos={totalSueldosActivos}
                      costoRefacciones={costoRefas}
                      totalFijos={totalPagosFijosActivos}
                      gastosVariables={gastosVarsRaw}
                      gastosOrdenes={gastosOrdenesVal}
                      gananciaNeta={gananciaNetaFinal}
                      totalBase={totalFacturado}
                    />

                    {/* --------------------------------------------------- */}
                    {/* 3. Balance — hero number + cascada                    */}
                    {/* --------------------------------------------------- */}
                    <div>
                      {/* Hero number */}
                      <div className="bg-gray-900 rounded-2xl px-6 py-5 mb-1 border border-gray-700">
                        <p className="text-xs text-gray-400 uppercase tracking-wide font-medium mb-1">
                          Ganancia neta del período
                        </p>
                        <p className={`text-5xl font-black tabular-nums leading-none ${gananciaNetaFinal >= 0 ? 'text-sag-500' : 'text-red-400'}`}>
                          {formatMoneda(gananciaNetaFinal)}
                        </p>
                      </div>

                      {/* Cascada detallada */}
                      <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-5">
                        <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-5">
                          Balance &mdash; {tituloPeriodo}
                        </h2>

                        {/* Grupo 1: de facturado a ingreso neto */}
                        <div className="space-y-2 mb-2">
                          <div className="flex items-baseline justify-between">
                            <span className="text-sm text-gray-500 dark:text-gray-400">Total facturado</span>
                            <span className="text-sm font-medium text-gray-600 dark:text-gray-300 tabular-nums">
                              {formatMoneda(totalFacturado)}
                            </span>
                          </div>

                          {costoRefas !== 0 && (
                            <div className="flex items-baseline justify-between pl-5">
                              <span className="text-sm text-gray-400 dark:text-gray-500">
                                &minus; Costo refacciones
                              </span>
                              <span className="text-sm text-gray-400 dark:text-gray-500 tabular-nums">
                                &minus;{formatMoneda(costoRefas)}
                              </span>
                            </div>
                          )}

                          <div className="flex items-baseline justify-between border-t border-gray-100 dark:border-gray-700 pt-2 font-semibold text-gray-700 dark:text-gray-200">
                            <span className="text-sm">Ingreso neto operativo</span>
                            <span className="text-sm tabular-nums">{formatMoneda(ingresoNeto)}</span>
                          </div>
                        </div>

                        <div className="my-4 border-t border-dashed border-gray-200 dark:border-gray-700" />

                        {/* Grupo 2: deducciones */}
                        <div className="space-y-2">
                          {totalSueldosActivos > 0 && (
                            <div className="flex items-baseline justify-between pl-5">
                              <span className="text-sm text-gray-400 dark:text-gray-500">&minus; Sueldos del equipo</span>
                              <span className="text-sm text-gray-400 dark:text-gray-500 tabular-nums">&minus;{formatMoneda(totalSueldosActivos)}</span>
                            </div>
                          )}
                          {totalPagosFijosActivos > 0 && (
                            <div className="flex items-baseline justify-between pl-5">
                              <span className="text-sm text-gray-400 dark:text-gray-500">&minus; Costos fijos</span>
                              <span className="text-sm text-gray-400 dark:text-gray-500 tabular-nums">&minus;{formatMoneda(totalPagosFijosActivos)}</span>
                            </div>
                          )}
                          {gastosVarsRaw > 0 && (
                            <div className="flex items-baseline justify-between pl-5">
                              <span className="text-sm text-gray-400 dark:text-gray-500">
                                &minus; Gastos variables
                              </span>
                              <span className="text-sm text-gray-400 dark:text-gray-500 tabular-nums">&minus;{formatMoneda(gastosVarsRaw)}</span>
                            </div>
                          )}
                          {gastosOrdenesVal > 0 && (
                            <div className="flex items-baseline justify-between pl-5">
                              <span className="text-sm text-gray-400 dark:text-gray-500">&minus; Costos internos de órdenes</span>
                              <span className="text-sm text-gray-400 dark:text-gray-500 tabular-nums">&minus;{formatMoneda(gastosOrdenesVal)}</span>
                            </div>
                          )}

                          {/* Línea final — Ganancia neta */}
                          <div className="flex items-baseline justify-between border-t-2 border-gray-300 dark:border-gray-600 pt-3 mt-2">
                            <span className="text-sm font-bold text-gray-800 dark:text-gray-100">Ganancia neta</span>
                            <span className={`text-lg font-black tabular-nums ${gananciaNetaFinal >= 0 ? 'text-sag-500' : 'text-red-500 dark:text-red-400'}`}>
                              {formatMoneda(gananciaNetaFinal)}
                            </span>
                          </div>

                          {/* Nota IVA */}
                          {ivaDelPeriodo > 0 && (
                            <p className="text-xs text-blue-500 dark:text-blue-400 mt-3 leading-relaxed border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 rounded-lg px-3 py-2">
                              IVA recaudado este período: {formatMoneda(ivaDelPeriodo)} — no es ganancia, va al SAT.
                            </p>
                          )}

                          <p className="text-xs text-gray-400 dark:text-gray-500 mt-2 text-center">
                            Utilidad estimada. No incluye otros impuestos ni deducciones fiscales.
                          </p>
                        </div>
                      </div>
                    </div>
                  </>
                );
              })()}

              {/* ----------------------------------------------------------- */}
              {/* 4. Top servicios + Top clientes                              */}
              {/* ----------------------------------------------------------- */}
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-5">
                  <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-0.5">
                    Top 5 servicios
                  </h2>
                  <p className="text-xs text-gray-400 dark:text-gray-500 mb-4">
                    Solo ingresos — sin datos de costos operativos
                  </p>
                  <TopServicios servicios={top_servicios} />
                </div>

                <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-5">
                  <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">
                    Top 5 clientes
                  </h2>
                  <TopClientes clientes={top_clientes} />
                </div>
              </div>

              {/* ----------------------------------------------------------- */}
              {/* 5. Tabla órdenes desglosada                                  */}
              {/* ----------------------------------------------------------- */}
              <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-5">
                <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1">
                  Detalle por orden
                </h2>
                <p className="text-xs text-gray-400 dark:text-gray-500 mb-4">
                  Ganancia = total facturado − costo de refacciones (sin margen)
                </p>
                <TablaOrdenesDesglosada
                  ordenes={ordenes}
                  totales={ordTotales}
                  loading={loadingOrdenes}
                />
              </div>
            </>
          )}
        </>
      )}

      {/* ================================================================== */}
      {/* TAB: CONFIGURACIÓN                                                   */}
      {/* ================================================================== */}
      {tabActivo === 'config' && user?.rol === 'admin' && (
        <div className="space-y-3">

          {/* -------------------------------------------------------------- */}
          {/* Accordion 1: Equipo — sueldos base                              */}
          {/* -------------------------------------------------------------- */}
          <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <button
              onClick={() => setOpenSueldos(!openSueldos)}
              className="w-full flex items-center justify-between px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
              <div className="text-left">
                <p className="font-semibold text-gray-800 dark:text-gray-100 text-sm">Equipo — sueldos base</p>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Tarifa diaria por empleado</p>
              </div>
              <div className="flex items-center gap-3 flex-shrink-0">
                <span className="text-sm tabular-nums text-gray-500 dark:text-gray-400 font-medium">
                  {formatMoneda(totalSueldosActivos)} / período
                </span>
                <FontAwesomeIcon
                  icon={openSueldos ? faChevronUp : faChevronDown}
                  className="text-gray-400 w-3.5 h-3.5"
                />
              </div>
            </button>
            {openSueldos && (
              <div className="border-t border-gray-100 dark:border-gray-700 px-5 py-4 space-y-3">
                <div className="flex justify-end">
                  <button
                    onClick={() => abrirFormEmp()}
                    className="flex items-center gap-1.5 text-sm font-medium bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg px-3 py-1.5 transition-colors"
                  >
                    <FontAwesomeIcon icon={faPlus} style={{ width: 12, height: 12 }} />
                    Agregar
                  </button>
                </div>

                {empError && (
                  <p className="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/40 rounded-lg px-3 py-2">
                    {empError}
                  </p>
                )}

                {mostrarFormEmp && (
                  <div className="flex flex-col gap-2 p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-indigo-200 dark:border-indigo-700/40">
                    {/* Radio: tipo de sueldo */}
                    <div className="flex items-center gap-4">
                      <span className="text-xs text-gray-500 dark:text-gray-400">Tipo:</span>
                      {(['diario', 'semanal'] as const).map(tipo => (
                        <label key={tipo} className="flex items-center gap-1.5 cursor-pointer">
                          <input
                            type="radio"
                            name="empTipoSueldo"
                            value={tipo}
                            checked={empTipoSueldo === tipo}
                            onChange={() => setEmpTipoSueldo(tipo)}
                            className="accent-indigo-500"
                          />
                          <span className="text-sm text-gray-700 dark:text-gray-300">
                            {tipo === 'diario' ? 'Por día' : 'Por semana'}
                          </span>
                        </label>
                      ))}
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <input
                        type="text"
                        placeholder="Nombre"
                        value={empNombre}
                        onChange={e => setEmpNombre(e.target.value)}
                        className="flex-1 min-w-32 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                      />
                      <input
                        type="text"
                        placeholder="Puesto (opcional)"
                        value={empPuesto}
                        onChange={e => setEmpPuesto(e.target.value)}
                        className="flex-1 min-w-28 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                      />
                      <div className="flex flex-col gap-0.5">
                        <label className="text-[10px] font-medium text-gray-500 dark:text-gray-400 px-1">
                          {empTipoSueldo === 'semanal' ? 'Sueldo/semana' : 'Sueldo/día'}
                        </label>
                        <input
                          type="number"
                          placeholder={empTipoSueldo === 'semanal' ? 'Sueldo/semana' : 'Sueldo/día'}
                          value={empSueldo}
                          onChange={e => setEmpSueldo(e.target.value)}
                          min="0"
                          step="0.01"
                          className="w-32 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        />
                      </div>
                      <button
                        onClick={guardarEmpleado}
                        disabled={empGuardando}
                        className="flex items-center gap-1 text-sm font-medium bg-indigo-500 hover:bg-indigo-600 disabled:bg-indigo-300 text-white rounded-lg px-3 py-2 transition-colors"
                      >
                        {empGuardando ? <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 14, height: 14 }} /> : <FontAwesomeIcon icon={faCheck} style={{ width: 14, height: 14 }} />}
                        {empEditId !== null && parseFloat(empSueldo) !== parseFloat(empSueldoOriginal) ? 'Guardar cambio' : 'Guardar'}
                      </button>
                      <button onClick={cancelarFormEmp} className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 px-2">
                        <FontAwesomeIcon icon={faXmark} style={{ width: 14, height: 14 }} />
                      </button>
                    </div>
                    {empEditId !== null && parseFloat(empSueldo) !== parseFloat(empSueldoOriginal) && (
                      <div className="flex items-center gap-2 pt-1 border-t border-indigo-100 dark:border-indigo-700/30">
                        <label className="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0">
                          Aplica desde:
                        </label>
                        <input
                          type="date"
                          value={empFechaInicioCambio}
                          onChange={e => setEmpFechaInicioCambio(e.target.value)}
                          className="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                        />
                        <p className="text-xs text-gray-400 dark:text-gray-500">
                          El registro anterior se conserva para el historial.
                        </p>
                      </div>
                    )}
                  </div>
                )}

                {loadingEmpleados ? (
                  <div className="flex items-center gap-2 text-gray-500 py-4">
                    <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 16, height: 16 }} />
                    <span className="text-sm">Cargando...</span>
                  </div>
                ) : empleadosVigentes.length === 0 && exEmpleados.length === 0 ? (
                  <p className="text-sm text-gray-500 dark:text-gray-400 py-2">Sin empleados registrados.</p>
                ) : (
                  <div className="overflow-x-auto">
                    {empleadosVigentes.length > 0 && (
                      <table className="w-full text-sm">
                        <thead>
                          <tr className="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                            <th className="pb-2 pr-4 font-medium">Nombre</th>
                            <th className="pb-2 pr-4 font-medium">Puesto</th>
                            <th className="pb-2 pr-4 font-medium text-right">Tarifa</th>
                            <th className="pb-2 pr-4 font-medium text-right">Est. período</th>
                            <th className="pb-2 pr-4 font-medium hidden sm:table-cell">Vigente desde</th>
                            <th className="pb-2 font-medium w-16 text-center">Esta semana</th>
                            <th className="pb-2 w-16"></th>
                          </tr>
                        </thead>
                        <tbody>
                          {empleadosVigentes.map(e => (
                            <tr key={e.id} className={`border-b border-gray-50 dark:border-gray-700/50 last:border-0 ${!e.activo ? 'opacity-50' : ''}`}>
                              <td className="py-2 pr-4 font-medium text-gray-800 dark:text-gray-100">{e.nombre}</td>
                              <td className="py-2 pr-4 text-gray-500 dark:text-gray-400">{e.puesto ?? '—'}</td>
                              <td className="py-2 pr-4 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                {(e.tipo_sueldo ?? 'diario') === 'semanal' ? (
                                  <div>
                                    <span>{formatMoneda(Number(e.sueldo_diario))}/sem</span>
                                    <span className="block text-[10px] text-gray-400 dark:text-gray-500">
                                      ({formatMoneda(Number(e.sueldo_diario) / 7)}/día ef.)
                                    </span>
                                  </div>
                                ) : (
                                  <span>{formatMoneda(Number(e.sueldo_diario))}/día</span>
                                )}
                              </td>
                              <td className="py-2 pr-4 text-right tabular-nums text-gray-600 dark:text-gray-400">
                                {e.activo
                                  ? formatMoneda(tarifaDiariaEfectiva(e) * (tipoPeriodo === 'semana' ? 5 : 22))
                                  : '—'}
                              </td>
                              <td className="py-2 pr-4 hidden sm:table-cell">
                                <span className="text-xs text-gray-400 dark:text-gray-500 tabular-nums">
                                  {e.fecha_inicio ? new Date(e.fecha_inicio + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                                </span>
                              </td>
                              <td className="py-2 text-center">
                                <button
                                  onClick={() => toggleEmpleado(e.id)}
                                  title={e.activo ? 'Marcar como inactivo esta semana' : 'Marcar como activo'}
                                  className={`w-10 h-5 rounded-full transition-colors ${e.activo ? 'bg-indigo-500' : 'bg-gray-300 dark:bg-gray-600'}`}
                                >
                                  <span className={`block w-4 h-4 rounded-full bg-white shadow transition-transform mx-0.5 ${e.activo ? 'translate-x-5' : 'translate-x-0'}`} />
                                </button>
                              </td>
                              <td className="py-2 text-right">
                                <div className="flex items-center justify-end gap-2">
                                  <button onClick={() => abrirFormEmp(e)} className="text-gray-400 hover:text-indigo-500 transition-colors">
                                    <FontAwesomeIcon icon={faPencil} style={{ width: 13, height: 13 }} />
                                  </button>
                                  <button
                                    onClick={() => confirmarDarDeBaja(e)}
                                    title="Dar de baja"
                                    className="text-gray-300 dark:text-gray-600 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                                  >
                                    <FontAwesomeIcon icon={faXmark} style={{ width: 13, height: 13 }} />
                                  </button>
                                </div>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    )}
                    <div className="flex justify-end pt-3 border-t border-gray-100 dark:border-gray-700 mt-1">
                      <span className="text-sm text-gray-500 dark:text-gray-400 mr-4">Total estimado del período:</span>
                      <span className="font-bold text-gray-900 dark:text-white tabular-nums">
                        {formatMoneda(totalSueldosActivos)}
                      </span>
                    </div>

                    {/* Ex-empleados — colapsados */}
                    {exEmpleados.length > 0 && (
                      <div className="mt-3 border-t border-gray-100 dark:border-gray-700 pt-3">
                        <button
                          onClick={() => setMostrarExEmpleados(!mostrarExEmpleados)}
                          className="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                        >
                          {mostrarExEmpleados ? 'Ocultar ex-empleados' : `Ex-empleados (${exEmpleados.length})`}
                        </button>
                        {mostrarExEmpleados && (
                          <table className="w-full text-sm mt-2">
                            <thead>
                              <tr className="text-left text-gray-400 dark:text-gray-600 border-b border-gray-100 dark:border-gray-800">
                                <th className="pb-1.5 pr-4 font-medium text-xs">Nombre</th>
                                <th className="pb-1.5 pr-4 font-medium text-xs">Puesto</th>
                                <th className="pb-1.5 pr-4 font-medium text-xs text-right">Tarifa</th>
                                <th className="pb-1.5 pr-4 font-medium text-xs">Baja desde</th>
                                <th className="pb-1.5 w-20"></th>
                              </tr>
                            </thead>
                            <tbody>
                              {exEmpleados.map(e => (
                                <tr key={e.id} className="border-b border-gray-50 dark:border-gray-800 last:border-0 opacity-60">
                                  <td className="py-1.5 pr-4 text-gray-600 dark:text-gray-400 text-sm">{e.nombre}</td>
                                  <td className="py-1.5 pr-4 text-gray-400 dark:text-gray-500 text-sm">{e.puesto ?? '—'}</td>
                                  <td className="py-1.5 pr-4 text-right tabular-nums text-gray-400 dark:text-gray-500 text-sm">
                                    {(e.tipo_sueldo ?? 'diario') === 'semanal'
                                      ? `${formatMoneda(Number(e.sueldo_diario))}/sem`
                                      : `${formatMoneda(Number(e.sueldo_diario))}/día`}
                                  </td>
                                  <td className="py-1.5 pr-4 text-xs text-gray-400 dark:text-gray-600">
                                    {e.fecha_fin
                                      ? new Date(e.fecha_fin + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
                                      : '—'}
                                  </td>
                                  <td className="py-1.5 text-right">
                                    <button
                                      onClick={() => reactivarEmpleado(e.id)}
                                      className="text-xs font-medium text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-900/20 dark:hover:bg-emerald-900/30 rounded-md px-2 py-1 transition-colors"
                                    >
                                      Reactivar
                                    </button>
                                  </td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        )}
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* -------------------------------------------------------------- */}
          {/* Accordion 2: Costos fijos recurrentes                           */}
          {/* -------------------------------------------------------------- */}
          <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <button
              onClick={() => setOpenCostosFijos(!openCostosFijos)}
              className="w-full flex items-center justify-between px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
              <div className="text-left">
                <p className="font-semibold text-gray-800 dark:text-gray-100 text-sm">Costos fijos recurrentes</p>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Renta, internet, proveedores</p>
              </div>
              <div className="flex items-center gap-3 flex-shrink-0">
                <span className="text-sm tabular-nums text-gray-500 dark:text-gray-400 font-medium">
                  {formatMoneda(totalPagosFijosActivos)} / período
                </span>
                <FontAwesomeIcon
                  icon={openCostosFijos ? faChevronUp : faChevronDown}
                  className="text-gray-400 w-3.5 h-3.5"
                />
              </div>
            </button>
            {openCostosFijos && (
              <div className="border-t border-gray-100 dark:border-gray-700 px-5 py-4 space-y-3">
                <div className="flex justify-end">
                  <button
                    onClick={() => abrirFormPago()}
                    className="flex items-center gap-1.5 text-sm font-medium bg-rose-500 hover:bg-rose-600 text-white rounded-lg px-3 py-1.5 transition-colors"
                  >
                    <FontAwesomeIcon icon={faPlus} style={{ width: 12, height: 12 }} />
                    Agregar
                  </button>
                </div>

                {pagoError && (
                  <p className="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/40 rounded-lg px-3 py-2">
                    {pagoError}
                  </p>
                )}

                {mostrarFormPago && (
                  <div className="flex flex-col gap-2 p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-rose-200 dark:border-rose-700/40">
                    <div className="flex flex-wrap gap-2">
                      <input
                        type="text"
                        placeholder="Concepto"
                        value={pagoConcepto}
                        onChange={e => setPagoConcepto(e.target.value)}
                        className="flex-1 min-w-32 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-rose-400"
                      />
                      <input
                        type="number"
                        placeholder="Monto"
                        value={pagoMonto}
                        onChange={e => setPagoMonto(e.target.value)}
                        min="0.01"
                        step="0.01"
                        className="w-28 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-rose-400"
                      />
                      <select
                        value={pagoFrecuencia}
                        onChange={e => setPagoFrecuencia(e.target.value as PagoFijo['frecuencia'])}
                        className="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-rose-400"
                      >
                        <option value="mensual">Mensual</option>
                        <option value="semanal">Semanal</option>
                      </select>
                      <select
                        value={pagoCategoria}
                        onChange={e => setPagoCategoria(e.target.value as PagoFijo['categoria'])}
                        className="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-rose-400"
                      >
                        <option value="renta">Renta</option>
                        <option value="servicio">Servicio</option>
                        <option value="proveedor">Proveedor</option>
                        <option value="marketing">Marketing</option>
                        <option value="otro">Otro</option>
                      </select>
                      <button
                        onClick={guardarPagoFijo}
                        disabled={pagoGuardando}
                        className="flex items-center gap-1 text-sm font-medium bg-rose-500 hover:bg-rose-600 disabled:bg-rose-300 text-white rounded-lg px-3 py-2 transition-colors"
                      >
                        {pagoGuardando ? <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 14, height: 14 }} /> : <FontAwesomeIcon icon={faCheck} style={{ width: 14, height: 14 }} />}
                        {pagoEditId !== null && parseFloat(pagoMonto) !== parseFloat(pagoMontoOriginal) ? 'Guardar cambio' : 'Guardar'}
                      </button>
                      <button onClick={cancelarFormPago} className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 px-2">
                        <FontAwesomeIcon icon={faXmark} style={{ width: 14, height: 14 }} />
                      </button>
                    </div>
                    {pagoEditId !== null && (
                      <div className="space-y-1.5 pt-1 border-t border-rose-100 dark:border-rose-700/30">
                        {parseFloat(pagoMonto) !== parseFloat(pagoMontoOriginal) && (
                          <div className="flex items-center gap-2">
                            <label className="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0 w-24">Aplica desde:</label>
                            <input
                              type="date"
                              value={pagoFechaInicioCambio}
                              onChange={e => setPagoFechaInicioCambio(e.target.value)}
                              className="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-rose-400"
                            />
                            <p className="text-xs text-gray-400 dark:text-gray-500">El historial anterior se conserva.</p>
                          </div>
                        )}
                        <div className="flex items-center gap-2">
                          <label className="text-xs text-gray-500 dark:text-gray-400 flex-shrink-0 w-24">Termina en:</label>
                          <input
                            type="date"
                            value={pagoFechaFinCambio}
                            onChange={e => setPagoFechaFinCambio(e.target.value)}
                            className="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-1.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-rose-400"
                          />
                          <p className="text-xs text-gray-400 dark:text-gray-500">Dejar vacío si sigue activo.</p>
                        </div>
                      </div>
                    )}
                  </div>
                )}

                {loadingPagos ? (
                  <div className="flex items-center gap-2 text-gray-500 py-4">
                    <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 16, height: 16 }} />
                    <span className="text-sm">Cargando...</span>
                  </div>
                ) : pagosFijos.length === 0 ? (
                  <p className="text-sm text-gray-500 dark:text-gray-400 py-2">Sin gastos fijos configurados.</p>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                          <th className="pb-2 pr-4 font-medium">Concepto</th>
                          <th className="pb-2 pr-4 font-medium">Categoría</th>
                          <th className="pb-2 pr-4 font-medium">Frecuencia</th>
                          <th className="pb-2 pr-4 font-medium text-right">Monto</th>
                          <th className="pb-2 pr-4 font-medium hidden sm:table-cell">Vigente desde</th>
                          <th className="pb-2 font-medium w-16 text-center">Activo</th>
                          <th className="pb-2 w-8"></th>
                        </tr>
                      </thead>
                      <tbody>
                        {pagosFijos.map(p => (
                          <tr key={p.id} className={`border-b border-gray-50 dark:border-gray-700/50 last:border-0 ${!p.activo ? 'opacity-50' : ''}`}>
                            <td className="py-2 pr-4 font-medium text-gray-800 dark:text-gray-100">{p.concepto}</td>
                            <td className="py-2 pr-4">
                              <span className="text-xs bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded px-2 py-0.5 capitalize">{p.categoria}</span>
                            </td>
                            <td className="py-2 pr-4">
                              <span className={`text-xs rounded px-2 py-0.5 capitalize ${p.frecuencia === 'semanal' ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300' : 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300'}`}>
                                {p.frecuencia}
                              </span>
                            </td>
                            <td className="py-2 pr-4 text-right tabular-nums text-gray-700 dark:text-gray-300">
                              {formatMoneda(p.monto)}
                            </td>
                            <td className="py-2 pr-4 hidden sm:table-cell">
                              <span className="text-xs text-gray-400 dark:text-gray-500 tabular-nums">
                                {p.fecha_inicio ? new Date(p.fecha_inicio + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—'}
                              </span>
                              {p.fecha_fin && (
                                <span className="text-xs text-orange-500 dark:text-orange-400 block">hasta {new Date(p.fecha_fin + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })}</span>
                              )}
                            </td>
                            <td className="py-2 text-center">
                              <button
                                onClick={() => togglePagoFijo(p.id)}
                                className={`w-10 h-5 rounded-full transition-colors ${p.activo ? 'bg-rose-500' : 'bg-gray-300 dark:bg-gray-600'}`}
                              >
                                <span className={`block w-4 h-4 rounded-full bg-white shadow transition-transform mx-0.5 ${p.activo ? 'translate-x-5' : 'translate-x-0'}`} />
                              </button>
                            </td>
                            <td className="py-2 text-right">
                              <button onClick={() => abrirFormPago(p)} className="text-gray-400 hover:text-rose-500 transition-colors">
                                <FontAwesomeIcon icon={faPencil} style={{ width: 13, height: 13 }} />
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                    <div className="flex justify-end pt-3 border-t border-gray-100 dark:border-gray-700 mt-1">
                      <span className="text-sm text-gray-500 dark:text-gray-400 mr-4">Total activos (estimado período):</span>
                      <span className="font-bold text-gray-900 dark:text-white tabular-nums">
                        {formatMoneda(totalPagosFijosActivos)}
                      </span>
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>

          {/* -------------------------------------------------------------- */}
          {/* Accordion 3: Caja chica                                         */}
          {/* -------------------------------------------------------------- */}
          <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <button
              onClick={() => setOpenCajaChica(!openCajaChica)}
              className="w-full flex items-center justify-between px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
              <div className="text-left">
                <p className="font-semibold text-gray-800 dark:text-gray-100 text-sm">Caja chica — Mayte</p>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Efectivo físico en caja · Saldo se arrastra semana a semana</p>
              </div>
              <div className="flex items-center gap-3 flex-shrink-0">
                {cajaChica && (
                  <span className={`text-sm tabular-nums font-medium ${cajaChica.saldo_actual < 0 ? 'text-red-500' : 'text-gray-500 dark:text-gray-400'}`}>
                    {formatMoneda(cajaChica.saldo_actual)}
                  </span>
                )}
                <FontAwesomeIcon
                  icon={openCajaChica ? faChevronUp : faChevronDown}
                  className="text-gray-400 w-3.5 h-3.5"
                />
              </div>
            </button>
            {openCajaChica && (
              <div className="border-t border-gray-100 dark:border-gray-700 px-5 py-4 space-y-4">
                {cargandoCaja && !cajaChica && (
                  <div className="flex items-center gap-2 text-gray-500 py-2">
                    <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 14, height: 14 }} />
                    <span className="text-sm">Cargando saldo...</span>
                  </div>
                )}

                {cajaChica && (
                  <>
                    <div className="grid grid-cols-3 gap-3">
                      <div className="text-center p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-100 dark:border-gray-700">
                        <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">Saldo anterior</p>
                        <p className="font-bold text-gray-800 dark:text-gray-100 tabular-nums">{formatMoneda(cajaChica.saldo_anterior)}</p>
                      </div>
                      <div className="text-center p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-100 dark:border-gray-700">
                        <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">Entradas</p>
                        <p className="font-bold text-green-700 dark:text-green-400 tabular-nums">{formatMoneda(cajaChica.ingresos_semana)}</p>
                      </div>
                      <div className="text-center p-3 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-100 dark:border-gray-700">
                        <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">Salidas</p>
                        <p className="font-bold text-red-600 dark:text-red-400 tabular-nums">{formatMoneda(cajaChica.egresos_semana)}</p>
                      </div>
                    </div>

                    <div className={`px-4 py-3 rounded-xl border ${cajaChica.saldo_actual < 0 ? 'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-700/40' : 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700/40'}`}>
                      {cajaChica.saldo_actual < 0 ? (
                        <p className="text-sm font-semibold text-red-700 dark:text-red-400">
                          Déficit — {formatMoneda(Math.abs(cajaChica.saldo_actual))} · Se descuenta del siguiente fondo
                        </p>
                      ) : (
                        <div className="flex items-center justify-between">
                          <span className="text-sm font-medium text-gray-700 dark:text-gray-300">Saldo actual</span>
                          <span className="font-bold text-green-800 dark:text-green-300 tabular-nums text-lg">{formatMoneda(cajaChica.saldo_actual)}</span>
                        </div>
                      )}
                    </div>

                    {cajaChica.movimientos.length === 0 && (
                      <p className="text-sm text-gray-400 dark:text-gray-500">Sin movimientos en este período.</p>
                    )}

                    {cajaChica.movimientos.length > 0 && (
                      <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                          <thead>
                            <tr className="text-left text-xs text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                              <th className="pb-2 pr-3 font-medium">Fecha</th>
                              <th className="pb-2 pr-3 font-medium">Tipo</th>
                              <th className="pb-2 pr-3 font-medium">Concepto</th>
                              <th className="pb-2 pr-3 font-medium text-right">Monto</th>
                              <th className="pb-2 font-medium w-6"></th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-gray-50 dark:divide-gray-700/50">
                            {cajaChica.movimientos.map(m => (
                              <tr key={m.id} className="text-gray-700 dark:text-gray-300">
                                <td className="py-2 pr-3 text-xs text-gray-500 whitespace-nowrap">{m.fecha}</td>
                                <td className="py-2 pr-3">
                                  <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${m.tipo === 'ingreso' ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400'}`}>
                                    {m.tipo}
                                  </span>
                                  {m.tipo === 'egreso' && m.gasto_admin_id !== null && (
                                    <span className="text-[10px] text-teal-600 dark:text-teal-400 ml-1">→ P&L</span>
                                  )}
                                </td>
                                <td className="py-2 pr-3">{m.concepto}</td>
                                <td className="py-2 pr-3 text-right tabular-nums font-medium">{formatMoneda(m.monto)}</td>
                                <td className="py-2">
                                  <button onClick={() => handleEliminarCaja(m.id)} className="text-gray-400 hover:text-red-500 transition-colors">
                                    <FontAwesomeIcon icon={faTrash} style={{ width: 12, height: 12 }} />
                                  </button>
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    )}
                  </>
                )}

                {/* Formulario agregar */}
                <div className="flex flex-wrap gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                  <select
                    value={cajaTipo}
                    onChange={e => setCajaTipo(e.target.value as 'ingreso' | 'egreso')}
                    className="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-green-400"
                  >
                    <option value="egreso">Egreso (salida)</option>
                    <option value="ingreso">Ingreso (entrada)</option>
                  </select>
                  <input
                    type="date"
                    value={cajaFecha}
                    onChange={e => setCajaFecha(e.target.value)}
                    className="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-green-400"
                  />
                  <input
                    type="text"
                    placeholder="Concepto (ej: gasolina, propina Autozone)"
                    value={cajaConcepto}
                    onChange={e => setCajaConcepto(e.target.value)}
                    className="flex-1 min-w-40 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-400"
                  />
                  <input
                    type="number"
                    placeholder="Monto"
                    value={cajaMonto}
                    onChange={e => setCajaMonto(e.target.value)}
                    min="0"
                    step="1"
                    className="w-28 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-400"
                  />
                  <button
                    onClick={handleAgregarCaja}
                    className="flex items-center gap-1 text-sm font-medium bg-green-600 hover:bg-green-700 text-white rounded-lg px-4 py-2 transition-colors"
                  >
                    <FontAwesomeIcon icon={faPlus} style={{ width: 12, height: 12 }} />
                    Agregar
                  </button>
                </div>

                {cajaError && (
                  <p className="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg px-3 py-2">{cajaError}</p>
                )}
              </div>
            )}
          </div>

          {/* -------------------------------------------------------------- */}
          {/* Accordion 4: Gastos variables del período                        */}
          {/* -------------------------------------------------------------- */}
          <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
            <button
              onClick={() => setOpenGastos(!openGastos)}
              className="w-full flex items-center justify-between px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
            >
              <div className="text-left">
                <p className="font-semibold text-gray-800 dark:text-gray-100 text-sm">Gastos variables del período</p>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Gasolina, materiales, imprevistos</p>
              </div>
              <div className="flex items-center gap-3 flex-shrink-0">
                {gastosAdmin && (
                  <span className="text-sm tabular-nums text-gray-500 dark:text-gray-400 font-medium">
                    {formatMoneda(Number(gastosAdmin.total_admin ?? 0))} / {tipoPeriodo === 'semana' ? 'semana' : 'mes'}
                  </span>
                )}
                <FontAwesomeIcon
                  icon={openGastos ? faChevronUp : faChevronDown}
                  className="text-gray-400 w-3.5 h-3.5"
                />
              </div>
            </button>
            {openGastos && (
              <div className="border-t border-gray-100 dark:border-gray-700 px-5 py-4 space-y-4">
                {adminError && (
                  <p className="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/40 rounded-lg px-3 py-2">
                    {adminError}
                  </p>
                )}

                {loadingAdmin ? (
                  <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400 py-4">
                    <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 16, height: 16 }} />
                    <span className="text-sm">Cargando gastos...</span>
                  </div>
                ) : !gastosAdmin || gastosAdmin.gastos.length === 0 ? (
                  <p className="text-sm text-gray-500 dark:text-gray-400 py-2">
                    Sin gastos registrados para este mes.
                  </p>
                ) : (
                  <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                      <thead>
                        <tr className="text-left text-gray-500 dark:text-gray-400 border-b border-gray-100 dark:border-gray-700">
                          <th className="pb-2 pr-4 font-medium w-28">Categoria</th>
                          <th className="pb-2 pr-4 font-medium">Concepto</th>
                          <th className="pb-2 pr-4 font-medium text-right">Monto</th>
                          <th className="pb-2 font-medium w-10"></th>
                        </tr>
                      </thead>
                      <tbody>
                        {gastosAdmin.gastos.map(g => (
                          <tr key={g.id} className="border-b border-gray-50 dark:border-gray-700/50 last:border-0">
                            <td className="py-2 pr-4 text-gray-600 dark:text-gray-300">{CATEGORIA_LABELS[g.categoria]}</td>
                            <td className="py-2 pr-4 text-gray-800 dark:text-gray-100">{g.concepto}</td>
                            <td className="py-2 pr-4 text-right text-gray-800 dark:text-gray-100 tabular-nums">
                              {Number(g.monto).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}
                            </td>
                            <td className="py-2 text-right">
                              {eliminandoAdminId === g.id ? (
                                <FontAwesomeIcon icon={faSpinner} className="animate-spin text-gray-400" style={{ width: 14, height: 14 }} />
                              ) : confirmandoAdminId === g.id ? (
                                <span className="inline-flex items-center gap-1">
                                  <button
                                    onClick={async () => {
                                      setEliminandoAdminId(g.id);
                                      setConfirmandoAdminId(null);
                                      try {
                                        await gastosAdminAPI.eliminar(g.id);
                                        await cargarAdmin();
                                      } catch {
                                        setAdminError('Error al eliminar el gasto. Intenta de nuevo.');
                                      } finally {
                                        setEliminandoAdminId(null);
                                      }
                                    }}
                                    className="text-xs font-medium text-white bg-red-500 hover:bg-red-600 rounded px-2 py-0.5 transition-colors"
                                  >
                                    Eliminar
                                  </button>
                                  <button
                                    onClick={() => setConfirmandoAdminId(null)}
                                    className="text-xs font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white rounded px-2 py-0.5 transition-colors"
                                  >
                                    Cancelar
                                  </button>
                                </span>
                              ) : (
                                <button
                                  onClick={() => setConfirmandoAdminId(g.id)}
                                  title="Eliminar"
                                  className="text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                                >
                                  <FontAwesomeIcon icon={faTrash} style={{ width: 14, height: 14 }} />
                                </button>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                    <div className="flex justify-end pt-3 border-t border-gray-100 dark:border-gray-700 mt-1">
                      <span className="text-sm text-gray-500 dark:text-gray-400 mr-4">Total gastos del taller:</span>
                      <span className="font-bold text-gray-900 dark:text-white tabular-nums">
                        {Number(gastosAdmin.total_admin).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}
                      </span>
                    </div>
                  </div>
                )}

                {/* Formulario agregar gasto variable */}
                <div className="flex flex-wrap gap-2 pt-2 border-t border-gray-100 dark:border-gray-700">
                  <select
                    value={adminCategoria}
                    onChange={e => setAdminCategoria(e.target.value as GastoAdmin['categoria'])}
                    disabled={guardandoAdmin}
                    className="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:opacity-50"
                  >
                    {CATEGORIA_OPTIONS.map(c => (
                      <option key={c} value={c}>{CATEGORIA_LABELS[c]}</option>
                    ))}
                  </select>

                  <input
                    type="text"
                    placeholder="Concepto"
                    value={adminConcepto}
                    onChange={e => setAdminConcepto(e.target.value)}
                    disabled={guardandoAdmin}
                    maxLength={300}
                    className="flex-1 min-w-40 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:opacity-50"
                  />

                  <input
                    type="number"
                    placeholder="Monto"
                    value={adminMonto}
                    onChange={e => setAdminMonto(e.target.value)}
                    disabled={guardandoAdmin}
                    min="0.01"
                    step="0.01"
                    className="w-28 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:opacity-50"
                  />

                  <button
                    onClick={async () => {
                      const montoNum = parseFloat(adminMonto);
                      if (!adminConcepto.trim()) {
                        setAdminError('El concepto no puede estar vacio.');
                        return;
                      }
                      if (isNaN(montoNum) || montoNum <= 0) {
                        setAdminError('El monto debe ser mayor a $0.');
                        return;
                      }
                      setAdminError(null);
                      setGuardandoAdmin(true);
                      try {
                        await gastosAdminAPI.crear(mesSel, anioSel, adminConcepto.trim(), montoNum, adminCategoria);
                        setAdminConcepto('');
                        setAdminMonto('');
                        setAdminCategoria('otro');
                        await cargarAdmin();
                      } catch {
                        setAdminError('Error al guardar el gasto. Intenta de nuevo.');
                      } finally {
                        setGuardandoAdmin(false);
                      }
                    }}
                    disabled={guardandoAdmin}
                    className="flex items-center gap-1.5 text-sm font-medium bg-amber-500 hover:bg-amber-600 disabled:bg-amber-300 text-white rounded-lg px-4 py-2 transition-colors"
                  >
                    {guardandoAdmin ? (
                      <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 14, height: 14 }} />
                    ) : (
                      <FontAwesomeIcon icon={faPlus} style={{ width: 14, height: 14 }} />
                    )}
                    Agregar
                  </button>
                </div>
              </div>
            )}
          </div>

        </div>
      )}

    </div>

    {/* ── Modal: confirmar dar de baja ─────────────────────────────── */}
    {empBajaId !== null && (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
        <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-6 max-w-sm w-full border border-gray-200 dark:border-gray-700">
          <h3 className="text-base font-semibold text-gray-900 dark:text-gray-100 mb-2">
            Dar de baja a {empBajaNombre}
          </h3>
          <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
            El empleado dejará de aparecer en los cálculos a partir de la fecha de baja.
            Su historial de sueldos se conserva para los períodos anteriores.
          </p>
          <div className="mb-5">
            <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
              Fecha de baja:
            </label>
            <input
              type="date"
              value={empBajaFecha}
              onChange={e => setEmpBajaFecha(e.target.value)}
              disabled={empBajando}
              className="w-full text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-red-400"
            />
          </div>
          <div className="flex gap-3 justify-end">
            <button
              onClick={() => { setEmpBajaId(null); setEmpBajaNombre(''); setEmpBajaFecha(''); }}
              disabled={empBajando}
              className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-600 transition-colors"
            >
              Cancelar
            </button>
            <button
              onClick={ejecutarDarDeBaja}
              disabled={empBajando || !empBajaFecha}
              className="flex items-center gap-1.5 text-sm font-medium bg-red-500 hover:bg-red-600 disabled:bg-red-300 text-white rounded-lg px-4 py-2 transition-colors"
            >
              {empBajando && <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 13, height: 13 }} />}
              Dar de baja
            </button>
          </div>
        </div>
      </div>
    )}

    </>
  );
};
