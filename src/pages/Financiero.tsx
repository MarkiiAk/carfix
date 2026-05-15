import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  BarChart,
  Bar,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from 'recharts';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus, faTrash, faSpinner, faPencil, faCheck, faXmark, faDownload } from '@fortawesome/free-solid-svg-icons';
import { financieroAPI, gastosAdminAPI, empleadosFinancieroAPI, pagosFijosAPI, cajaChicaAPI } from '../services/api';
import { abrirReporteFinanciero } from '../utils/reporteFinancieroHTML';
import { useAuth } from '../contexts/AuthContext';
import { TablaOrdenesDesglosada } from '../components/financiero/TablaOrdenesDesglosada';
import { GraficaDistribucion } from '../components/financiero/GraficaDistribucion';
import type {
  ResumenFinancieroResponse,
  MargenRefacciones,
  TopServicio,
  TopCliente,
  IngresosDia,
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

const formatDiaCorto = (isoDate: string): string => {
  const [, , d] = isoDate.split('-');
  return d.replace(/^0/, '');
};

const formatDiaTooltip = (isoDate: string): string => {
  const meses = [
    'ene', 'feb', 'mar', 'abr', 'may', 'jun',
    'jul', 'ago', 'sep', 'oct', 'nov', 'dic',
  ];
  const [, m, d] = isoDate.split('-');
  return `${parseInt(d)} ${meses[parseInt(m) - 1]}`;
};

// ---------------------------------------------------------------------------
// Tooltip personalizado para la gráfica
// ---------------------------------------------------------------------------

interface CustomTooltipProps {
  active?: boolean;
  payload?: Array<{ value: number }>;
  label?: string;
}

const CustomTooltip = ({ active, payload, label }: CustomTooltipProps) => {
  if (active && payload && payload.length && label) {
    return (
      <div className="bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm shadow-lg">
        <p className="text-gray-400 mb-0.5">{formatDiaTooltip(label)}</p>
        <p className="text-white font-semibold">{formatMoneda(payload[0].value)}</p>
      </div>
    );
  }
  return null;
};

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
// Gráfica de barras por día
// ---------------------------------------------------------------------------

interface GraficaDiaProps {
  datos: IngresosDia[];
}

const GraficaDia = ({ datos }: GraficaDiaProps) => {
  if (datos.length === 0) {
    return (
      <div className="flex items-center justify-center h-40 text-gray-400 dark:text-gray-500 text-sm">
        Sin ordenes cerradas en este periodo.
      </div>
    );
  }

  return (
    <ResponsiveContainer width="100%" height={180}>
      <BarChart data={datos} margin={{ top: 4, right: 4, left: -8, bottom: 0 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="#374151" vertical={false} />
        <XAxis
          dataKey="dia"
          tickFormatter={formatDiaCorto}
          tick={{ fill: '#9CA3AF', fontSize: 10 }}
          axisLine={false}
          tickLine={false}
        />
        <YAxis
          tickFormatter={(v) => `$${(v / 1000).toFixed(0)}k`}
          tick={{ fill: '#9CA3AF', fontSize: 10 }}
          axisLine={false}
          tickLine={false}
          width={36}
        />
        <Tooltip content={<CustomTooltip />} cursor={{ fill: 'rgba(203,245,24,0.08)' }} />
        <Bar dataKey="total" fill="#CBF518" radius={[3, 3, 0, 0]} maxBarSize={36} />
      </BarChart>
    </ResponsiveContainer>
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

// ---------------------------------------------------------------------------
// Margen en refacciones
// ---------------------------------------------------------------------------

interface MargenRefaccionesCardProps {
  datos: MargenRefacciones;
}

const MargenRefaccionesCard = ({ datos }: MargenRefaccionesCardProps) => {
  const { vendido, costo, margen, margen_pct } = datos;
  const pctCosto  = vendido > 0 ? (costo  / vendido) * 100 : 0;
  const pctMargen = vendido > 0 ? (margen / vendido) * 100 : 0;

  return (
    <div className="space-y-5">
      {/* Ganancia neta — protagonista */}
      <div className="bg-gray-50 dark:bg-gray-900/50 rounded-xl px-4 py-4 text-center">
        <p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide font-medium mb-1">
          Ganancia neta en refacciones
        </p>
        <p className="text-2xl font-bold text-gray-900 dark:text-white leading-tight">
          {formatMoneda(margen)}
        </p>
        {margen_pct > 0 && (
          <p className="text-sm text-gray-400 dark:text-gray-500 mt-1">
            {margen_pct}% de margen
          </p>
        )}
      </div>

      {/* Desglose costo / precio cobrado */}
      <div className="space-y-2.5">
        <div className="flex justify-between items-baseline">
          <span className="text-sm text-gray-500 dark:text-gray-400">Costo pagado por el taller</span>
          <span className="font-semibold text-gray-700 dark:text-gray-300">{formatMoneda(costo)}</span>
        </div>
        <div className="flex justify-between items-baseline">
          <span className="text-sm text-gray-500 dark:text-gray-400">Precio cobrado al cliente</span>
          <span className="font-semibold text-gray-700 dark:text-gray-300">{formatMoneda(vendido)}</span>
        </div>
      </div>

      {/* Barra visual costo/margen */}
      {vendido > 0 && (
        <>
          <div className="h-3 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden flex">
            <div
              className="h-full bg-gray-300 dark:bg-gray-500 transition-all duration-500"
              style={{ width: `${pctCosto}%` }}
              title={`Costo: ${formatMoneda(costo)}`}
            />
            <div
              className="h-full bg-sag-500 transition-all duration-500"
              style={{ width: `${pctMargen}%` }}
              title={`Ganancia: ${formatMoneda(margen)}`}
            />
          </div>
          <div className="flex items-center gap-4 text-xs text-gray-400 dark:text-gray-500">
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-gray-300 dark:bg-gray-500 inline-block" />
              Costo del taller
            </span>
            <span className="flex items-center gap-1.5">
              <span className="w-2.5 h-2.5 rounded-sm bg-sag-500 inline-block" />
              Ganancia neta
            </span>
          </div>
        </>
      )}
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

  // Formulario empleados
  const [empEditId, setEmpEditId]           = useState<number | null>(null);
  const [empNombre, setEmpNombre]           = useState('');
  const [empPuesto, setEmpPuesto]           = useState('');
  const [empSueldo, setEmpSueldo]           = useState('');
  const [empGuardando, setEmpGuardando]     = useState(false);
  const [empError, setEmpError]             = useState<string | null>(null);
  const [mostrarFormEmp, setMostrarFormEmp] = useState(false);

  // Formulario pagos fijos
  const [pagoEditId, setPagoEditId]         = useState<number | null>(null);
  const [pagoConcepto, setPagoConcepto]     = useState('');
  const [pagoMonto, setPagoMonto]           = useState('');
  const [pagoFrecuencia, setPagoFrecuencia] = useState<PagoFijo['frecuencia']>('mensual');
  const [pagoCategoria, setPagoCategoria]   = useState<PagoFijo['categoria']>('otro');
  const [pagoGuardando, setPagoGuardando]   = useState(false);
  const [pagoError, setPagoError]           = useState<string | null>(null);
  const [mostrarFormPago, setMostrarFormPago] = useState(false);

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
      const res = await gastosAdminAPI.listar(mesSel, anioSel);
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
  }, [mesSel, anioSel]);

  const cargarEmpleados = useCallback(async () => {
    setLoadingEmpleados(true);
    try {
      const res = await empleadosFinancieroAPI.listar();
      setEmpleados(res.empleados);
    } catch {
      // silencioso
    } finally {
      setLoadingEmpleados(false);
    }
  }, []);

  const cargarPagosFijos = useCallback(async () => {
    setLoadingPagos(true);
    try {
      const res = await pagosFijosAPI.listar();
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
    cargarEmpleados();
    cargarPagosFijos();
  }, [cargarEmpleados, cargarPagosFijos]);

  useEffect(() => {
    cargarCajaChica();
  }, [cargarCajaChica]);

  useEffect(() => {
    cargarAdmin();
  }, [cargarAdmin]);

  // Handlers para empleados
  const abrirFormEmp = (emp?: EmpleadoSueldo) => {
    if (emp) {
      setEmpEditId(emp.id);
      setEmpNombre(emp.nombre);
      setEmpPuesto(emp.puesto ?? '');
      setEmpSueldo(String(emp.sueldo_diario));
    } else {
      setEmpEditId(null);
      setEmpNombre('');
      setEmpPuesto('');
      setEmpSueldo('');
    }
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
    if (!nombre) { setEmpError('El nombre es obligatorio.'); return; }
    if (isNaN(sueldo) || sueldo < 0) { setEmpError('Sueldo diario inválido.'); return; }
    setEmpError(null);
    setEmpGuardando(true);
    try {
      if (empEditId !== null) {
        await empleadosFinancieroAPI.actualizar(empEditId, { nombre, puesto: empPuesto || null, sueldo_diario: sueldo });
      } else {
        await empleadosFinancieroAPI.crear({ nombre, puesto: empPuesto || null, sueldo_diario: sueldo, usuario_id: null });
      }
      await cargarEmpleados();
      cancelarFormEmp();
    } catch {
      setEmpError('Error al guardar. Intenta de nuevo.');
    } finally {
      setEmpGuardando(false);
    }
  };

  const toggleEmpleado = async (id: number) => {
    try {
      const res = await empleadosFinancieroAPI.toggle(id);
      setEmpleados(prev => prev.map(e => e.id === id ? { ...e, activo: res.activo } : e));
    } catch { /* silencioso */ }
  };

  // Handlers para pagos fijos
  const abrirFormPago = (pago?: PagoFijo) => {
    if (pago) {
      setPagoEditId(pago.id);
      setPagoConcepto(pago.concepto);
      setPagoMonto(String(pago.monto));
      setPagoFrecuencia(pago.frecuencia);
      setPagoCategoria(pago.categoria);
    } else {
      setPagoEditId(null);
      setPagoConcepto('');
      setPagoMonto('');
      setPagoFrecuencia('mensual');
      setPagoCategoria('otro');
    }
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
        await pagosFijosAPI.actualizar(pagoEditId, { concepto, monto, frecuencia: pagoFrecuencia, categoria: pagoCategoria });
      } else {
        await pagosFijosAPI.crear({ concepto, monto, frecuencia: pagoFrecuencia, categoria: pagoCategoria });
      }
      await cargarPagosFijos();
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
  const totalSueldosActivos = empleados
    .filter(e => e.activo)
    .reduce((acc, e) => acc + e.sueldo_diario * (tipoPeriodo === 'semana' ? 5 : 22), 0);

  const totalPagosFijosActivos = pagosFijos
    .filter(p => p.activo)
    .reduce((acc, p) => {
      if (p.frecuencia === 'semanal') return acc + p.monto * (tipoPeriodo === 'semana' ? 1 : 4);
      return acc + p.monto;
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

  const { periodo, resumen, refacciones, top_servicios, top_clientes, por_dia } = datos;
  const sinDatos = resumen.num_ordenes === 0;

  return (
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
      {/* Estado vacío                                                         */}
      {/* ------------------------------------------------------------------ */}
      {sinDatos ? (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700">
          <EstadoVacio label={`No hay ordenes cerradas en ${periodo.label.toLowerCase()}.`} />
        </div>
      ) : (
        <>
          {/* --------------------------------------------------------------- */}
          {/* Cards KPI                                                         */}
          {/* --------------------------------------------------------------- */}
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

          {/* --------------------------------------------------------------- */}
          {/* Grafica de barras                                                 */}
          {/* --------------------------------------------------------------- */}
          <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-5">
            <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">
              Ingresos por dia
            </h2>
            <GraficaDia datos={por_dia} />
          </div>

          {/* --------------------------------------------------------------- */}
          {/* Dos columnas: top servicios (izq) + ganancia refacciones (der)    */}
          {/* --------------------------------------------------------------- */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {/* Top servicios */}
            <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-5">
              <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-0.5">
                Top 5 servicios
              </h2>
              <p className="text-xs text-gray-400 dark:text-gray-500 mb-4">
                Solo ingresos — sin datos de costos operativos
              </p>
              <TopServicios servicios={top_servicios} />
            </div>

            {/* Ganancia en refacciones */}
            <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-5">
              <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">
                Margen en refacciones
              </h2>
              {refacciones.vendido === 0 ? (
                <p className="text-sm text-gray-400 dark:text-gray-500 py-4">
                  Sin refacciones registradas en este periodo.
                </p>
              ) : (
                <MargenRefaccionesCard datos={refacciones} />
              )}
            </div>
          </div>
        </>
      )}

      {/* ------------------------------------------------------------------ */}
      {/* Top clientes que más gastan                                          */}
      {/* ------------------------------------------------------------------ */}
      {!sinDatos && (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-5">
          <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">
            Top 5 clientes
          </h2>
          <TopClientes clientes={top_clientes} />
        </div>
      )}

      {/* ------------------------------------------------------------------ */}
      {/* Tabla órdenes desglosadas                                            */}
      {/* ------------------------------------------------------------------ */}
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

      {/* ------------------------------------------------------------------ */}
      {/* Gráfica de distribución del ingreso                                  */}
      {/* ------------------------------------------------------------------ */}
      {ordTotales.costo_venta > 0 && (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-5 py-5">
          <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-4">
            Distribución del ingreso
          </h2>
          <GraficaDistribucion
            gananciaReal={ordTotales.ganancia - totalSueldosActivos - totalPagosFijosActivos}
            totalSueldos={totalSueldosActivos}
            costoRefacciones={ordTotales.costo_refacciones}
            totalPagosFijos={totalPagosFijosActivos}
          />
        </div>
      )}

      {/* ------------------------------------------------------------------ */}
      {/* Sección: Equipo y sueldos (solo admin)                               */}
      {/* ------------------------------------------------------------------ */}
      {user?.rol === 'admin' && (
        <section className="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700/40 rounded-2xl shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-indigo-200 dark:border-indigo-700/40 flex items-center justify-between">
            <div>
              <p className="font-semibold text-gray-800 dark:text-gray-100">Equipo — sueldos base</p>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Tarifa diaria por empleado — configúrala una vez, se aplica a cada período ({tipoPeriodo === 'semana' ? '5 días hábiles' : '22 días hábiles'})
              </p>
            </div>
            <button
              onClick={() => abrirFormEmp()}
              className="flex items-center gap-1.5 text-sm font-medium bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg px-3 py-1.5 transition-colors"
            >
              <FontAwesomeIcon icon={faPlus} style={{ width: 12, height: 12 }} />
              Agregar
            </button>
          </div>

          <div className="px-6 py-4 space-y-3">
            {empError && (
              <p className="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/40 rounded-lg px-3 py-2">
                {empError}
              </p>
            )}

            {mostrarFormEmp && (
              <div className="flex flex-wrap gap-2 p-3 bg-white dark:bg-gray-800 rounded-xl border border-indigo-200 dark:border-indigo-700/40">
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
                <input
                  type="number"
                  placeholder="Sueldo/día"
                  value={empSueldo}
                  onChange={e => setEmpSueldo(e.target.value)}
                  min="0"
                  step="0.01"
                  className="w-28 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-400"
                />
                <button
                  onClick={guardarEmpleado}
                  disabled={empGuardando}
                  className="flex items-center gap-1 text-sm font-medium bg-indigo-500 hover:bg-indigo-600 disabled:bg-indigo-300 text-white rounded-lg px-3 py-2 transition-colors"
                >
                  {empGuardando ? <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 14, height: 14 }} /> : <FontAwesomeIcon icon={faCheck} style={{ width: 14, height: 14 }} />}
                  Guardar
                </button>
                <button onClick={cancelarFormEmp} className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 px-2">
                  <FontAwesomeIcon icon={faXmark} style={{ width: 14, height: 14 }} />
                </button>
              </div>
            )}

            {loadingEmpleados ? (
              <div className="flex items-center gap-2 text-gray-500 py-4">
                <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 16, height: 16 }} />
                <span className="text-sm">Cargando...</span>
              </div>
            ) : empleados.length === 0 ? (
              <p className="text-sm text-gray-500 dark:text-gray-400 py-2">Sin empleados registrados.</p>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-gray-500 dark:text-gray-400 border-b border-indigo-200 dark:border-indigo-700/40">
                      <th className="pb-2 pr-4 font-medium">Nombre</th>
                      <th className="pb-2 pr-4 font-medium">Puesto</th>
                      <th className="pb-2 pr-4 font-medium text-right">Sueldo/día</th>
                      <th className="pb-2 pr-4 font-medium text-right">Est. período</th>
                      <th className="pb-2 font-medium w-16 text-center">Activo</th>
                      <th className="pb-2 w-8"></th>
                    </tr>
                  </thead>
                  <tbody>
                    {empleados.map(e => (
                      <tr key={e.id} className={`border-b border-indigo-100 dark:border-indigo-800/30 last:border-0 ${!e.activo ? 'opacity-50' : ''}`}>
                        <td className="py-2 pr-4 font-medium text-gray-800 dark:text-gray-100">{e.nombre}</td>
                        <td className="py-2 pr-4 text-gray-500 dark:text-gray-400">{e.puesto ?? '—'}</td>
                        <td className="py-2 pr-4 text-right tabular-nums text-gray-700 dark:text-gray-300">
                          {formatMoneda(e.sueldo_diario)}
                        </td>
                        <td className="py-2 pr-4 text-right tabular-nums text-gray-600 dark:text-gray-400">
                          {formatMoneda(e.sueldo_diario * (tipoPeriodo === 'semana' ? 5 : 22))}
                        </td>
                        <td className="py-2 text-center">
                          <button
                            onClick={() => toggleEmpleado(e.id)}
                            className={`w-10 h-5 rounded-full transition-colors ${e.activo ? 'bg-indigo-500' : 'bg-gray-300 dark:bg-gray-600'}`}
                          >
                            <span className={`block w-4 h-4 rounded-full bg-white shadow transition-transform mx-0.5 ${e.activo ? 'translate-x-5' : 'translate-x-0'}`} />
                          </button>
                        </td>
                        <td className="py-2 text-right">
                          <button onClick={() => abrirFormEmp(e)} className="text-gray-400 hover:text-indigo-500 transition-colors">
                            <FontAwesomeIcon icon={faPencil} style={{ width: 13, height: 13 }} />
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                <div className="flex justify-end pt-3 border-t border-indigo-200 dark:border-indigo-700/40 mt-1">
                  <span className="text-sm text-gray-500 dark:text-gray-400 mr-4">Total estimado del período:</span>
                  <span className="font-bold text-gray-900 dark:text-white tabular-nums">
                    {formatMoneda(totalSueldosActivos)}
                  </span>
                </div>
              </div>
            )}
          </div>
        </section>
      )}

      {/* ------------------------------------------------------------------ */}
      {/* Sección: Gastos fijos del taller (solo admin)                        */}
      {/* ------------------------------------------------------------------ */}
      {user?.rol === 'admin' && (
        <section className="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-700/40 rounded-2xl shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-rose-200 dark:border-rose-700/40 flex items-center justify-between">
            <div>
              <p className="font-semibold text-gray-800 dark:text-gray-100">Costos fijos recurrentes</p>
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Renta, internet, proveedores — configúralos una vez, se prorratean solos cada semana
              </p>
            </div>
            <button
              onClick={() => abrirFormPago()}
              className="flex items-center gap-1.5 text-sm font-medium bg-rose-500 hover:bg-rose-600 text-white rounded-lg px-3 py-1.5 transition-colors"
            >
              <FontAwesomeIcon icon={faPlus} style={{ width: 12, height: 12 }} />
              Agregar
            </button>
          </div>

          <div className="px-6 py-4 space-y-3">
            {pagoError && (
              <p className="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/40 rounded-lg px-3 py-2">
                {pagoError}
              </p>
            )}

            {mostrarFormPago && (
              <div className="flex flex-wrap gap-2 p-3 bg-white dark:bg-gray-800 rounded-xl border border-rose-200 dark:border-rose-700/40">
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
                  Guardar
                </button>
                <button onClick={cancelarFormPago} className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 px-2">
                  <FontAwesomeIcon icon={faXmark} style={{ width: 14, height: 14 }} />
                </button>
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
                    <tr className="text-left text-gray-500 dark:text-gray-400 border-b border-rose-200 dark:border-rose-700/40">
                      <th className="pb-2 pr-4 font-medium">Concepto</th>
                      <th className="pb-2 pr-4 font-medium">Categoría</th>
                      <th className="pb-2 pr-4 font-medium">Frecuencia</th>
                      <th className="pb-2 pr-4 font-medium text-right">Monto</th>
                      <th className="pb-2 font-medium w-16 text-center">Activo</th>
                      <th className="pb-2 w-8"></th>
                    </tr>
                  </thead>
                  <tbody>
                    {pagosFijos.map(p => (
                      <tr key={p.id} className={`border-b border-rose-100 dark:border-rose-800/30 last:border-0 ${!p.activo ? 'opacity-50' : ''}`}>
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
                <div className="flex justify-end pt-3 border-t border-rose-200 dark:border-rose-700/40 mt-1">
                  <span className="text-sm text-gray-500 dark:text-gray-400 mr-4">Total activos (estimado período):</span>
                  <span className="font-bold text-gray-900 dark:text-white tabular-nums">
                    {formatMoneda(totalPagosFijosActivos)}
                  </span>
                </div>
              </div>
            )}
          </div>
        </section>
      )}

      {/* ------------------------------------------------------------------ */}
      {/* Sección: Caja chica (solo admin)                                     */}
      {/* ------------------------------------------------------------------ */}
      {user?.rol === 'admin' && (
        <section className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700/40 rounded-2xl shadow-sm overflow-hidden">
          <div className="px-6 py-4 border-b border-green-200 dark:border-green-700/40">
            <p className="font-semibold text-gray-800 dark:text-gray-100">Caja chica — Mayte</p>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              Efectivo físico en caja · El saldo se arrastra semana a semana
            </p>
          </div>

          <div className="px-6 py-4 space-y-4">

            {/* Saldos — solo cuando cargaron los datos */}
            {cargandoCaja && !cajaChica && (
              <div className="flex items-center gap-2 text-gray-500 py-2">
                <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 14, height: 14 }} />
                <span className="text-sm">Cargando saldo...</span>
              </div>
            )}

            {cajaChica && (
              <>
                <div className="grid grid-cols-3 gap-3">
                  <div className="text-center p-3 bg-white dark:bg-gray-800 rounded-xl border border-green-200 dark:border-green-700/40">
                    <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">Saldo anterior</p>
                    <p className="font-bold text-gray-800 dark:text-gray-100 tabular-nums">{formatMoneda(cajaChica.saldo_anterior)}</p>
                  </div>
                  <div className="text-center p-3 bg-white dark:bg-gray-800 rounded-xl border border-green-200 dark:border-green-700/40">
                    <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">Entradas</p>
                    <p className="font-bold text-green-700 dark:text-green-400 tabular-nums">{formatMoneda(cajaChica.ingresos_semana)}</p>
                  </div>
                  <div className="text-center p-3 bg-white dark:bg-gray-800 rounded-xl border border-green-200 dark:border-green-700/40">
                    <p className="text-xs text-gray-500 dark:text-gray-400 mb-1">Salidas</p>
                    <p className="font-bold text-red-600 dark:text-red-400 tabular-nums">{formatMoneda(cajaChica.egresos_semana)}</p>
                  </div>
                </div>

                <div className={`px-4 py-3 rounded-xl border ${cajaChica.saldo_actual < 0 ? 'bg-red-50 dark:bg-red-900/20 border-red-300 dark:border-red-700/40' : 'bg-green-100 dark:bg-green-900/30 border-green-300 dark:border-green-700/40'}`}>
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
                        <tr className="text-left text-xs text-gray-500 dark:text-gray-400 border-b border-green-200 dark:border-green-700/40">
                          <th className="pb-2 pr-3 font-medium">Fecha</th>
                          <th className="pb-2 pr-3 font-medium">Tipo</th>
                          <th className="pb-2 pr-3 font-medium">Concepto</th>
                          <th className="pb-2 pr-3 font-medium text-right">Monto</th>
                          <th className="pb-2 font-medium w-6"></th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-green-100 dark:divide-green-900/30">
                        {cajaChica.movimientos.map(m => (
                          <tr key={m.id} className="text-gray-700 dark:text-gray-300">
                            <td className="py-2 pr-3 text-xs text-gray-500 whitespace-nowrap">{m.fecha}</td>
                            <td className="py-2 pr-3">
                              <span className={`inline-block px-2 py-0.5 rounded-full text-xs font-medium ${m.tipo === 'ingreso' ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400' : 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400'}`}>
                                {m.tipo}
                              </span>
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

            {/* Formulario agregar — siempre visible */}
            <div className="flex flex-wrap gap-2 pt-2 border-t border-green-200 dark:border-green-700/40">
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
        </section>
      )}

      {/* ------------------------------------------------------------------ */}
      {/* Bloque A — Gastos del taller (administrativos por mes)               */}
      {/* ------------------------------------------------------------------ */}
      <section className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/40 rounded-2xl shadow-sm overflow-hidden">
        {/* Header */}
        <div className="px-6 py-4 border-b border-amber-200 dark:border-amber-700/40">
          <p className="font-semibold text-gray-800 dark:text-gray-100">Gastos variables del período</p>
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            Gasolina, materiales, imprevistos y otros gastos que cambian semana a semana
          </p>
        </div>

        <div className="px-6 py-4 space-y-4">
          {/* Error formulario */}
          {adminError && (
            <p className="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/40 rounded-lg px-3 py-2">
              {adminError}
            </p>
          )}

          {/* Lista de gastos */}
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
                  <tr className="text-left text-gray-500 dark:text-gray-400 border-b border-amber-200 dark:border-amber-700/40">
                    <th className="pb-2 pr-4 font-medium w-28">Categoria</th>
                    <th className="pb-2 pr-4 font-medium">Concepto</th>
                    <th className="pb-2 pr-4 font-medium text-right">Monto</th>
                    <th className="pb-2 font-medium w-10"></th>
                  </tr>
                </thead>
                <tbody>
                  {gastosAdmin.gastos.map(g => (
                    <tr
                      key={g.id}
                      className="border-b border-amber-100 dark:border-amber-800/30 last:border-0"
                    >
                      <td className="py-2 pr-4 text-gray-600 dark:text-gray-300">
                        {CATEGORIA_LABELS[g.categoria]}
                      </td>
                      <td className="py-2 pr-4 text-gray-800 dark:text-gray-100">
                        {g.concepto}
                      </td>
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

              {/* Total gastos admin */}
              <div className="flex justify-end pt-3 border-t border-amber-200 dark:border-amber-700/40 mt-1">
                <span className="text-sm text-gray-500 dark:text-gray-400 mr-4">
                  Total gastos del taller:
                </span>
                <span className="font-bold text-gray-900 dark:text-white tabular-nums">
                  {Number(gastosAdmin.total_admin).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })}
                </span>
              </div>
            </div>
          )}

          {/* Formulario agregar gasto admin */}
          <div className="flex flex-wrap gap-2 pt-2 border-t border-amber-200 dark:border-amber-700/40">
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
      </section>

      {/* ------------------------------------------------------------------ */}
      {/* Bloque B — Balance del mes (desglose completo)                       */}
      {/* ------------------------------------------------------------------ */}
      {gastosAdmin !== null && !loadingAdmin && (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-5">
          <h2 className="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-5">
            Balance &mdash; {MESES[mesSel - 1]} {anioSel}
          </h2>

          {/* Grupo 1: del total facturado al ingreso neto */}
          <div className="space-y-2 mb-2">
            <div className="flex items-baseline justify-between">
              <span className="text-sm text-gray-500 dark:text-gray-400">Total facturado</span>
              <span className="text-sm font-medium text-gray-600 dark:text-gray-300 tabular-nums">
                {formatMoneda(Number(gastosAdmin.total_facturado))}
              </span>
            </div>

            {Number(gastosAdmin.total_iva) !== 0 && (
              <div className="flex items-baseline justify-between">
                <span className="text-sm text-gray-400 dark:text-gray-500">
                  &minus; IVA cobrado <span className="text-xs">(va al SAT)</span>
                </span>
                <span className="text-sm text-gray-400 dark:text-gray-500 tabular-nums">
                  &minus;{formatMoneda(Number(gastosAdmin.total_iva))}
                </span>
              </div>
            )}

            {Number(gastosAdmin.costo_refacciones) !== 0 && (
              <div className="flex items-baseline justify-between">
                <span className="text-sm text-gray-400 dark:text-gray-500">
                  &minus; Costo refacciones <span className="text-xs">(material comprado)</span>
                </span>
                <span className="text-sm text-gray-400 dark:text-gray-500 tabular-nums">
                  &minus;{formatMoneda(Number(gastosAdmin.costo_refacciones))}
                </span>
              </div>
            )}

            <div className="flex items-baseline justify-between border-t border-gray-100 dark:border-gray-700 pt-2">
              <span className="text-sm font-semibold text-gray-700 dark:text-gray-200">Ingresos netos</span>
              <span className="text-sm font-bold text-gray-800 dark:text-white tabular-nums">
                {formatMoneda(Number(gastosAdmin.ingresos_netos))}
              </span>
            </div>
            {Number(gastosAdmin.margen_refacciones) !== 0 && (
              <p className="text-xs text-gray-400 dark:text-gray-500 pl-1">
                Incluye {formatMoneda(Number(gastosAdmin.margen_refacciones))} de margen en refacciones (30%)
              </p>
            )}
          </div>

          {/* Divisor */}
          <div className="my-4 border-t border-dashed border-gray-200 dark:border-gray-700" />

          {/* Grupo 2: del ingreso neto a la utilidad */}
          <div className="space-y-2">
            <div className="flex items-baseline justify-between">
              <span className="text-sm text-red-500 dark:text-red-400">
                {Number(gastosAdmin.total_admin) > 0 ? '− ' : ''}Gastos del taller
              </span>
              <span className="text-sm font-medium text-red-500 dark:text-red-400 tabular-nums">
                {Number(gastosAdmin.total_admin) > 0 ? '−' : ''}{formatMoneda(Number(gastosAdmin.total_admin))}
              </span>
            </div>

            <div className="flex items-baseline justify-between">
              <span className="text-sm text-orange-500 dark:text-orange-400">
                {Number(gastosAdmin.gastos_ordenes_mes) > 0 ? '− ' : ''}Costos de órdenes
              </span>
              <span className="text-sm font-medium text-orange-500 dark:text-orange-400 tabular-nums">
                {Number(gastosAdmin.gastos_ordenes_mes) > 0 ? '−' : ''}{formatMoneda(Number(gastosAdmin.gastos_ordenes_mes))}
              </span>
            </div>

            <div className="flex items-baseline justify-between border-t border-gray-200 dark:border-gray-700 pt-3 mt-1">
              <span className="text-base font-bold text-gray-800 dark:text-gray-100">Utilidad neta</span>
              <span
                className={`text-2xl font-bold tabular-nums ${
                  Number(gastosAdmin.utilidad_neta) >= 0
                    ? 'text-green-600 dark:text-green-400'
                    : 'text-red-600 dark:text-red-400'
                }`}
              >
                {formatMoneda(Number(gastosAdmin.utilidad_neta))}
              </span>
            </div>

            <p className="text-xs text-gray-400 dark:text-gray-500 mt-3 text-center">
              Utilidad estimada. No incluye otros impuestos ni deducciones fiscales.
            </p>
          </div>

          <p className="text-xs text-gray-400 dark:text-gray-500 mt-4 leading-relaxed">
            Los costos de ordenes son los gastos internos registrados en cada orden de trabajo (envios, consumibles, etc.)
          </p>
        </div>
      )}

    </div>
  );
};
