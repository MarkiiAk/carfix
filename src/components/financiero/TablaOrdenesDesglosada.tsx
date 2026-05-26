import { Fragment, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faSpinner, faArrowUpRightFromSquare } from '@fortawesome/free-solid-svg-icons';
import type { OrdenFinanciero, OrdenesFinancieroResponse } from '../../types';

type FiltroEstado = 'todas' | 'abiertas' | 'cerradas';

interface Props {
  ordenes: OrdenFinanciero[];
  totales: OrdenesFinancieroResponse['totales'];
  loading: boolean;
}

const fmt = (monto: number): string =>
  new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
  }).format(monto);

const formatFecha = (iso: string): string => {
  if (!iso) return '—';
  const datePart = iso.split('T')[0].split(' ')[0];
  const [y, m, d] = datePart.split('-');
  return `${d.replace(/^0/, '')}/${m}/${y.slice(2)}`;
};

const ESTADOS_ABIERTOS = new Set([
  'en_proceso', 'en_revision', 'pendiente', 'cotizacion',
  'en_espera', 'abierta', 'en proceso',
]);

/* ── Badge de estado de la orden ────────────────────────────────────── */
type EstadoBadgeConfig = {
  label: string;
  className: string;
};

function getEstadoBadge(estado: string): EstadoBadgeConfig {
  switch (estado?.toLowerCase()) {
    case 'abierta':
    case 'en_espera':
    case 'pendiente':
    case 'cotizacion':
      return {
        label: 'Abierta',
        className: 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
      };
    case 'en_proceso':
    case 'en proceso':
    case 'en_revision':
      return {
        label: 'En proceso',
        className: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
      };
    case 'completado':
    case 'completada':
      return {
        label: 'Lista',
        className: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400',
      };
    case 'entregado':
    case 'entregada':
      return {
        label: 'Entregada ✓',
        className: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400',
      };
    case 'cerrada':
    case 'cerrado':
      return {
        label: 'Cerrada',
        className: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-800 dark:text-emerald-400',
      };
    default:
      return {
        label: estado ?? '—',
        className: 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400',
      };
  }
}

const EstadoBadge = ({ estado }: { estado: string }) => {
  const cfg = getEstadoBadge(estado);
  return (
    <span className={`inline-block text-[9px] font-medium rounded px-1.5 py-0.5 leading-none mt-1 ${cfg.className}`}>
      {cfg.label}
    </span>
  );
};

/* ── Fila de un ítem de la orden ─────────────────────────────────── */
type ItemFila =
  | { tipo: 'servicio';        descripcion: string; subtotal: number; costoCompra?: null;   proveedor?: null }
  | { tipo: 'refaccion';       descripcion: string; subtotal: number; costoCompra: number;  proveedor: string | null }
  | { tipo: 'iva';             descripcion: string; subtotal: number; costoCompra?: null;   proveedor?: null }
  | { tipo: 'costo_interno';   descripcion: string; subtotal: number; costoCompra?: null;   proveedor?: null };

function buildItems(o: OrdenFinanciero): ItemFila[] {
  const items: ItemFila[] = [
    ...o.servicios.map(s => ({
      tipo: 'servicio' as const,
      descripcion: s.descripcion,
      subtotal: s.subtotal,
    })),
    ...o.refacciones_detalle.map(r => {
      // precio_costo disponible en refacciones ≥ 2026-05-25; fallback a subtotal/1.30
      const costo = r.precio_costo != null && r.cantidad != null
        ? r.precio_costo * r.cantidad
        : r.subtotal / 1.30;
      return {
        tipo: 'refaccion' as const,
        descripcion: r.descripcion,
        subtotal: r.subtotal,
        costoCompra: costo,
        proveedor: r.proveedor,
      };
    }),
  ];
  // IVA — solo si aplica (órdenes cerradas con IVA > 0)
  if ((o.iva ?? 0) > 0) {
    items.push({ tipo: 'iva' as const, descripcion: 'IVA (16%)', subtotal: o.iva! });
  }
  // Desglose por concepto: un ítem por cada gasto interno
  items.push(...(o.gastos_internos ?? []).map(g => ({
    tipo: 'costo_interno' as const,
    descripcion: g.concepto,           // solo el concepto, sin el tipo
    subtotal: g.monto,
  })));
  // Fallback: si hay costo_interno total pero no hay detalle (datos legacy), mostrar una línea genérica
  if ((o.gastos_internos ?? []).length === 0 && (o.costo_interno ?? 0) > 0) {
    items.push({
      tipo: 'costo_interno' as const,
      descripcion: 'Costos internos',
      subtotal: o.costo_interno!,
    });
  }
  return items;
}

/* ─────────────────────────────────────────────────────────────────── */
export const TablaOrdenesDesglosada = ({ ordenes, totales, loading }: Props) => {
  const navigate = useNavigate();
  const [filtro, setFiltro] = useState<FiltroEstado>('todas');

  // Resetear filtro al cambiar de semana/período (evita que 'abiertas' oculte rows 'anticipo')
  useEffect(() => {
    setFiltro('todas');
  }, [ordenes]);

  if (loading) {
    return (
      <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400 py-6">
        <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 16, height: 16 }} />
        <span className="text-sm">Cargando órdenes...</span>
      </div>
    );
  }

  if (ordenes.length === 0) {
    return (
      <p className="text-sm text-gray-400 dark:text-gray-500 py-4">
        Sin órdenes registradas en este período.
      </p>
    );
  }

  // Filtrado por tipo_fila (modelo flujo de caja) con fallback a ESTADOS_ABIERTOS para datos legacy
  const esFilaApertura = (o: OrdenFinanciero): boolean =>
    o.tipo_fila ? o.tipo_fila === 'apertura' : ESTADOS_ABIERTOS.has(o.estado?.toLowerCase() ?? '');

  const ordenesFiltradas = filtro === 'todas'
    ? ordenes
    : ordenes.filter(o => {
        const esApertura = esFilaApertura(o);
        return filtro === 'abiertas' ? esApertura : !esApertura;
      });

  // Totales recalculados sobre el conjunto filtrado
  const totalesFiltrados = {
    costo_venta:       ordenesFiltradas.reduce((s, o) => s + o.costo_venta, 0),
    costo_refacciones: ordenesFiltradas.reduce((s, o) => s + o.costo_refacciones, 0),
    ganancia:          ordenesFiltradas.reduce((s, o) => s + o.ganancia, 0),
  };
  const totalesVista = filtro === 'todas' ? totales : totalesFiltrados;

  // Botones de filtro
  const btnBase = 'px-3 py-1 text-xs font-medium rounded-full border transition-colors';
  const btnActivo   = 'bg-sag-500 border-sag-500 text-white';
  const btnInactivo = 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 hover:border-sag-400';

  return (
    <div className="space-y-3">
      {/* ── Filtros ──────────────────────────────────────────────── */}
      <div className="flex items-center gap-2 flex-wrap">
        <span className="text-xs text-gray-500 dark:text-gray-400">Ver:</span>
        {(['todas', 'abiertas', 'cerradas'] as FiltroEstado[]).map(f => (
          <button
            key={f}
            onClick={() => setFiltro(f)}
            className={`${btnBase} ${filtro === f ? btnActivo : btnInactivo}`}
          >
            {f.charAt(0).toUpperCase() + f.slice(1)}
            {f !== 'todas' && (
              <span className="ml-1 opacity-70">
                ({f === 'abiertas'
                  ? ordenes.filter(esFilaApertura).length
                  : ordenes.filter(o => !esFilaApertura(o)).length})
              </span>
            )}
          </button>
        ))}
        {filtro !== 'todas' && (
          <span className="text-xs text-gray-400 dark:text-gray-500 ml-1">
            — {ordenesFiltradas.length} de {ordenes.length} órdenes
          </span>
        )}
      </div>

    <div className="overflow-x-auto -mx-1">
      <table className="w-full text-xs min-w-[820px] border-collapse">

        {/* ── Encabezados ─────────────────────────────── */}
        <thead>
          <tr className="text-left text-gray-500 dark:text-gray-400 border-b-2 border-gray-300 dark:border-gray-600">
            <th className="pb-2 pr-3 font-semibold whitespace-nowrap">Fecha</th>
            <th className="pb-2 pr-3 font-semibold">Unidad / Cliente</th>
            <th className="pb-2 pr-3 font-semibold">Concepto</th>
            <th className="pb-2 pr-3 font-semibold text-right whitespace-nowrap">Costo Venta</th>
            <th className="pb-2 pr-3 font-semibold text-right whitespace-nowrap">Costo Compra</th>
            <th className="pb-2     font-semibold text-right whitespace-nowrap">Ganancia</th>
          </tr>
        </thead>

        {/* ── Cuerpo — una orden = N filas de ítem + 1 fila TOTAL ──── */}
        <tbody>
          {ordenesFiltradas.map(o => {
            const items     = buildItems(o);
            // tipo_fila para chips visuales — fallback a comportamiento legacy si no viene del API
            const tipoFila  = o.tipo_fila ?? (ESTADOS_ABIERTOS.has(o.estado?.toLowerCase() ?? '') ? 'apertura' : 'cierre');
            // Si no hay detalle, mostramos una sola fila
            const totalRows = items.length > 0 ? items.length + 1 : 1;

            return (
              <Fragment key={`${o.id}_${tipoFila}`}>

                {/* Sin detalle — fila única */}
                {items.length === 0 && (
                  <tr className="border-b border-gray-200 dark:border-gray-700">
                    <td className="py-2 pr-3 text-gray-500 dark:text-gray-400 whitespace-nowrap align-top">
                      {formatFecha(o.fecha)}
                      <FilaChip tipoFila={tipoFila} />
                    </td>
                    <td className="py-2 pr-3 align-top">
                      <UnidadCliente vehiculo={o.vehiculo} cliente={o.cliente_nombre} estado={o.estado} />
                    </td>
                    <td className="py-2 pr-3 text-gray-400 dark:text-gray-600 align-top">—</td>
                    <td className="py-2 pr-3 text-right tabular-nums font-medium text-gray-700 dark:text-gray-300 align-top whitespace-nowrap">
                      {fmt(o.costo_venta)}
                    </td>
                    <td className="py-2 pr-3 text-right tabular-nums text-gray-400 dark:text-gray-500 align-top whitespace-nowrap">
                      {o.costo_refacciones > 0 ? fmt(o.costo_refacciones) : '—'}
                    </td>
                    <td className={`py-2 text-right tabular-nums font-semibold align-top whitespace-nowrap ${gananciaColor(o.ganancia)}`}>
                      {fmt(o.ganancia)}
                    </td>
                  </tr>
                )}

                {/* Con detalle — filitas por ítem */}
                {items.map((item, idx) => (
                  <tr
                    key={idx}
                    className="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/20 transition-colors"
                  >
                    {/* Fecha + Unidad — solo en la primera fila, rowspan cubre ítems + TOTAL */}
                    {idx === 0 && (
                      <>
                        <td
                          rowSpan={totalRows}
                          className="py-2 pr-3 text-gray-500 dark:text-gray-400 whitespace-nowrap align-top border-b-2 border-gray-200 dark:border-gray-700"
                        >
                          {formatFecha(o.fecha)}
                          <FilaChip tipoFila={tipoFila} />
                        </td>
                        <td
                          rowSpan={totalRows}
                          className="py-2 pr-3 align-top border-b-2 border-gray-200 dark:border-gray-700 max-w-[140px]"
                        >
                          <UnidadCliente vehiculo={o.vehiculo} cliente={o.cliente_nombre} estado={o.estado} />
                        </td>
                      </>
                    )}

                    {/* Concepto */}
                    <td className="py-1.5 pr-3 align-top">
                      {item.tipo === 'costo_interno' && (
                        <span className="italic text-gray-400 dark:text-gray-500">{item.descripcion}</span>
                      )}
                      {item.tipo === 'iva' && (
                        <span className="italic text-gray-400 dark:text-gray-500">{item.descripcion}</span>
                      )}
                      {(item.tipo === 'servicio' || item.tipo === 'refaccion') && (
                        <span className="text-gray-700 dark:text-gray-300">{item.descripcion}</span>
                      )}
                      {item.tipo === 'refaccion' && item.proveedor && (
                        <span className="block text-[10px] text-gray-400 dark:text-gray-500 mt-0.5">
                          {item.proveedor}
                        </span>
                      )}
                    </td>

                    {/* Costo Venta — IVA aparece aquí en gris; costos internos vacío */}
                    <td className="py-1.5 pr-3 text-right tabular-nums align-top whitespace-nowrap">
                      {item.tipo === 'iva' && (
                        <span className="text-gray-400 dark:text-gray-500 italic">{fmt(item.subtotal)}</span>
                      )}
                      {(item.tipo === 'servicio' || item.tipo === 'refaccion') && (
                        <span className="text-gray-600 dark:text-gray-400">{fmt(item.subtotal)}</span>
                      )}
                    </td>

                    {/* Costo Compra — refacciones: costo real o estimado; costos internos: monto en rojo */}
                    <td className="py-1.5 pr-3 text-right tabular-nums align-top whitespace-nowrap">
                      {item.tipo === 'refaccion' && (
                        <span className="text-gray-400 dark:text-gray-500">{fmt(item.costoCompra)}</span>
                      )}
                      {item.tipo === 'costo_interno' && (
                        <span className="text-red-400 dark:text-red-500">{fmt(item.subtotal)}</span>
                      )}
                    </td>

                    {/* Ganancia — vacía en filas de ítem */}
                    <td className="py-1.5 align-top" />
                  </tr>
                ))}

                {/* TOTAL row — aparece solo si hay ítems */}
                {items.length > 0 && (
                  <tr className="border-b-2 border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800/40">
                    {/* Fecha + Unidad ya cubiertos por rowspan */}
                    <td
                      className="py-2 pr-3 pl-1 text-gray-500 dark:text-gray-400 font-semibold tracking-widest text-[10px] uppercase"
                    >
                      Total
                    </td>
                    <td className="py-2 pr-3 text-right tabular-nums font-bold text-gray-800 dark:text-gray-100 whitespace-nowrap">
                      {fmt(o.costo_venta)}
                    </td>
                    <td className="py-2 pr-3 text-right tabular-nums font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">
                      {(o.costo_refacciones + (o.costo_interno ?? 0)) > 0
                        ? fmt(o.costo_refacciones + (o.costo_interno ?? 0))
                        : '—'}
                    </td>
                    {/* Ganancia + botón Ver orden */}
                    <td className={`py-2 text-right tabular-nums font-bold whitespace-nowrap ${gananciaColor(o.ganancia)}`}>
                      <span className="inline-flex items-center gap-2 justify-end">
                        {fmt(o.ganancia)}
                        <button
                          onClick={() => navigate(`/orden/${o.id}`)}
                          title="Ver orden"
                          className="text-gray-400 hover:text-indigo-500 dark:hover:text-indigo-400 transition-colors flex-shrink-0"
                        >
                          <FontAwesomeIcon icon={faArrowUpRightFromSquare} style={{ width: 10, height: 10 }} />
                        </button>
                      </span>
                    </td>
                  </tr>
                )}

              </Fragment>
            );
          })}
        </tbody>

        {/* ── Footer global del período ────────────────── */}
        <tfoot>
          <tr className="bg-gray-100 dark:bg-gray-700/50 font-bold border-t-2 border-gray-400 dark:border-gray-500">
            <td
              colSpan={3}
              className="py-2.5 pr-3 pl-1 text-gray-600 dark:text-gray-300 text-xs uppercase tracking-wide"
            >
              {filtro === 'todas'
                ? `Total período · ${ordenes.length} ${ordenes.length === 1 ? 'orden' : 'órdenes'}`
                : `${filtro.charAt(0).toUpperCase() + filtro.slice(1)} · ${ordenesFiltradas.length} de ${ordenes.length}`}
            </td>
            <td className="py-2.5 pr-3 text-right tabular-nums text-gray-800 dark:text-gray-100 whitespace-nowrap">
              {fmt(totalesVista.costo_venta)}
            </td>
            <td className="py-2.5 pr-3 text-right tabular-nums text-gray-500 dark:text-gray-400 whitespace-nowrap">
              {(() => {
                const totalCompra = ordenesFiltradas.reduce((acc, o) => acc + o.costo_refacciones + (o.costo_interno ?? 0), 0);
                return totalCompra > 0 ? fmt(totalCompra) : '—';
              })()}
            </td>
            <td className={`py-2.5 text-right tabular-nums whitespace-nowrap ${gananciaColor(totalesVista.ganancia)}`}>
              {fmt(totalesVista.ganancia)}
            </td>
          </tr>
        </tfoot>

      </table>
    </div>
    </div>
  );
};

/* ── Sub-componentes ─────────────────────────────────────────────── */

// Chip visual que indica el tipo de fila en el modelo de flujo de caja
const FilaChip = ({ tipoFila }: { tipoFila: string }) => {
  if (tipoFila === 'apertura') {
    // Orden abierta con anticipo parcial
    return (
      <span className="block mt-0.5 text-[9px] bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded px-1 py-0.5 font-medium leading-none w-fit">
        anticipo
      </span>
    );
  }
  if (tipoFila === 'anticipo') {
    // Orden ya cerrada — mostrando el anticipo en su semana histórica
    return (
      <span className="block mt-0.5 text-[9px] bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400 rounded px-1 py-0.5 font-medium leading-none w-fit">
        anticipo ✓
      </span>
    );
  }
  if (tipoFila === 'cierre') {
    // Orden cerrada en su semana de entrega
    return (
      <span className="block mt-0.5 text-[9px] bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400 rounded px-1 py-0.5 font-medium leading-none w-fit">
        restante
      </span>
    );
  }
  return null;
};

const UnidadCliente = ({ vehiculo, cliente, estado }: { vehiculo: string; cliente: string; estado: string }) => (
  <div>
    <div className="font-medium text-gray-800 dark:text-gray-200 leading-tight truncate">
      {vehiculo || '—'}
    </div>
    <div className="text-gray-500 dark:text-gray-400 mt-0.5 truncate">
      {cliente}
    </div>
    <EstadoBadge estado={estado} />
  </div>
);

const gananciaColor = (g: number) =>
  g >= 0
    ? 'text-green-600 dark:text-green-400'
    : 'text-red-600 dark:text-red-400';
