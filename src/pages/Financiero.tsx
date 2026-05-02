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
import { faPlus, faTrash, faSpinner } from '@fortawesome/free-solid-svg-icons';
import { financieroAPI, gastosAdminAPI } from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import type {
  ResumenFinancieroResponse,
  MargenRefacciones,
  TopServicio,
  TopCliente,
  IngresosDia,
  GastoAdmin,
  GastosAdminResponse,
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

// ---------------------------------------------------------------------------
// Selector de período
// ---------------------------------------------------------------------------

type TipoPeriodo = 'semana' | 'quincena' | 'mes';

interface SelectorPeriodoProps {
  tipo: TipoPeriodo;
  offset: number;
  label: string;
  onTipoChange: (t: TipoPeriodo) => void;
  onOffsetChange: (delta: number) => void;
}

const SelectorPeriodo = ({ tipo, offset, label, onTipoChange, onOffsetChange }: SelectorPeriodoProps) => {
  const opciones: { key: TipoPeriodo; label: string }[] = [
    { key: 'semana',   label: 'Esta semana'    },
    { key: 'quincena', label: 'Esta quincena'  },
    { key: 'mes',      label: 'Este mes'       },
  ];

  const pillBase = 'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors';
  const pillActive = 'bg-sag-500 text-gray-900';
  const pillInactive = 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600';

  return (
    <div className="flex flex-col items-center gap-3">
      {/* Pills de tipo — scroll horizontal en mobile si no caben */}
      <div className="flex items-center gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl overflow-x-auto max-w-full">
        {opciones.map((o) => (
          <button
            key={o.key}
            onClick={() => onTipoChange(o.key)}
            className={`${pillBase} whitespace-nowrap flex-shrink-0 ${tipo === o.key ? pillActive : pillInactive}`}
          >
            {o.label}
          </button>
        ))}
      </div>

      {/* Navegación anterior/siguiente */}
      <div className="flex items-center gap-2 sm:gap-3">
        <button
          onClick={() => onOffsetChange(1)}
          className="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
          title="Periodo anterior"
        >
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
          </svg>
        </button>

        <span className="text-sm font-semibold text-gray-700 dark:text-gray-200 min-w-[120px] sm:min-w-[160px] text-center">
          {label}
        </span>

        <button
          onClick={() => onOffsetChange(-1)}
          disabled={offset === 0}
          className="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
          title="Periodo siguiente"
        >
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        </button>
      </div>
    </div>
  );
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

const ANIOS_DISPONIBLES = [2024, 2025, 2026, 2027];

// ---------------------------------------------------------------------------
// Página principal
// ---------------------------------------------------------------------------

export const Financiero = () => {
  const { user } = useAuth();
  const navigate = useNavigate();

  const [tipo, setTipo]     = useState<TipoPeriodo>('mes');
  const [offset, setOffset] = useState(0);
  const [datos, setDatos]   = useState<ResumenFinancieroResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError]   = useState<string | null>(null);

  // Estado para gastos administrativos
  const [mesSel, setMesSel]   = useState(() => new Date().getMonth() + 1);
  const [anioSel, setAnioSel] = useState(() => new Date().getFullYear());
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
      const resultado = await financieroAPI.resumen(tipo, offset);
      setDatos(resultado);
    } catch {
      setError('No se pudo cargar la informacion de ingresos.');
    } finally {
      setIsLoading(false);
    }
  }, [tipo, offset]);

  useEffect(() => {
    cargar();
  }, [cargar]);

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

  useEffect(() => {
    cargarAdmin();
  }, [cargarAdmin]);

  const handleTipoChange = (nuevoTipo: TipoPeriodo) => {
    setTipo(nuevoTipo);
    setOffset(0);
  };

  const handleOffsetChange = (delta: number) => {
    setOffset((prev) => Math.max(0, prev + delta));
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
      {/* Header con selector de periodo                                       */}
      {/* ------------------------------------------------------------------ */}
      <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-5">
        <h1 className="text-lg font-bold text-gray-900 dark:text-white mb-4 text-center">
          Ingresos
        </h1>
        <SelectorPeriodo
          tipo={tipo}
          offset={offset}
          label={periodo.label}
          onTipoChange={handleTipoChange}
          onOffsetChange={handleOffsetChange}
        />
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
      {/* Bloque A — Gastos del taller (administrativos por mes)               */}
      {/* ------------------------------------------------------------------ */}
      <section className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/40 rounded-2xl shadow-sm overflow-hidden">
        {/* Header */}
        <div className="px-6 py-4 border-b border-amber-200 dark:border-amber-700/40 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <p className="font-semibold text-gray-800 dark:text-gray-100">Gastos del taller</p>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              Renta, salarios y gastos fijos &middot; Por mes calendario
            </p>
          </div>
          {/* Selector mes/año */}
          <div className="flex items-center gap-2 flex-shrink-0">
            <select
              value={mesSel}
              onChange={e => { setMesSel(Number(e.target.value)); setConfirmandoAdminId(null); }}
              className="text-sm border border-amber-300 dark:border-amber-600 rounded-lg px-2 py-1.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-400"
            >
              {MESES.map((m, i) => (
                <option key={i + 1} value={i + 1}>{m}</option>
              ))}
            </select>
            <select
              value={anioSel}
              onChange={e => { setAnioSel(Number(e.target.value)); setConfirmandoAdminId(null); }}
              className="text-sm border border-amber-300 dark:border-amber-600 rounded-lg px-2 py-1.5 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-400"
            >
              {ANIOS_DISPONIBLES.map(a => (
                <option key={a} value={a}>{a}</option>
              ))}
            </select>
          </div>
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
