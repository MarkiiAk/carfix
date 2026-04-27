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
import { financieroAPI } from '../services/api';
import { useAuth } from '../contexts/AuthContext';
import type {
  ResumenFinancieroResponse,
  MargenRefacciones,
  TopServicio,
  IngresosDia,
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
    <ResponsiveContainer width="100%" height={200}>
      <BarChart data={datos} margin={{ top: 4, right: 4, left: 0, bottom: 0 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="#374151" vertical={false} />
        <XAxis
          dataKey="dia"
          tickFormatter={formatDiaCorto}
          tick={{ fill: '#9CA3AF', fontSize: 11 }}
          axisLine={false}
          tickLine={false}
        />
        <YAxis
          tickFormatter={(v) => `$${(v / 1000).toFixed(0)}k`}
          tick={{ fill: '#9CA3AF', fontSize: 11 }}
          axisLine={false}
          tickLine={false}
          width={40}
        />
        <Tooltip content={<CustomTooltip />} cursor={{ fill: 'rgba(203,245,24,0.08)' }} />
        <Bar dataKey="total" fill="#CBF518" radius={[3, 3, 0, 0]} maxBarSize={40} />
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
      {/* Pills de tipo */}
      <div className="flex items-center gap-1.5 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl">
        {opciones.map((o) => (
          <button
            key={o.key}
            onClick={() => onTipoChange(o.key)}
            className={`${pillBase} ${tipo === o.key ? pillActive : pillInactive}`}
          >
            {o.label}
          </button>
        ))}
      </div>

      {/* Navegación anterior/siguiente */}
      <div className="flex items-center gap-3">
        <button
          onClick={() => onOffsetChange(1)}
          className="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
          title="Periodo anterior"
        >
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
          </svg>
        </button>

        <span className="text-sm font-semibold text-gray-700 dark:text-gray-200 min-w-[160px] text-center">
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

  const { periodo, resumen, refacciones, top_servicios, por_dia } = datos;
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
      {/* Placeholder gastos operativos                                        */}
      {/* ------------------------------------------------------------------ */}
      <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 px-6 py-5">
        <div className="flex items-start gap-3">
          <svg className="w-5 h-5 text-gray-400 dark:text-gray-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
              d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
          </svg>
          <div>
            <p className="text-sm font-medium text-gray-600 dark:text-gray-300">
              Gastos operativos — Proximamente
            </p>
            <p className="text-sm text-gray-400 dark:text-gray-500 mt-1 leading-relaxed">
              Registra renta, nomina e insumos para ver tu ganancia real. Por ahora solo se muestran los ingresos.
            </p>
            <p className="text-xs text-gray-400 dark:text-gray-500 mt-2">
              Por ahora, la ganancia neta de refacciones es el unico dato de margen real disponible.
            </p>
          </div>
        </div>
      </div>

    </div>
  );
};
