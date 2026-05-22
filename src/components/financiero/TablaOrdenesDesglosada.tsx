import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faSpinner } from '@fortawesome/free-solid-svg-icons';
import type { OrdenFinanciero, OrdenesFinancieroResponse } from '../../types';

interface Props {
  ordenes: OrdenFinanciero[];
  totales: OrdenesFinancieroResponse['totales'];
  loading: boolean;
}

const formatMoneda = (monto: number): string =>
  new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
  }).format(monto);

const formatFecha = (iso: string): string => {
  if (!iso) return '—';
  const datePart = iso.split('T')[0].split(' ')[0];
  const [, m, d] = datePart.split('-');
  return `${d.replace(/^0/, '')}/${m}`;
};

const ESTADOS_ABIERTOS = new Set([
  'en_proceso', 'en_revision', 'pendiente', 'cotizacion',
  'en_espera', 'abierta', 'en proceso',
]);

export const TablaOrdenesDesglosada = ({ ordenes, totales, loading }: Props) => {
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

  return (
    <div className="overflow-x-auto -mx-1">
      <table className="w-full text-xs min-w-[820px]">
        <thead>
          <tr className="text-left text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-700">
            <th className="pb-2 pr-3 font-semibold whitespace-nowrap w-12">Fecha</th>
            <th className="pb-2 pr-3 font-semibold">Unidad / Cliente</th>
            <th className="pb-2 pr-3 font-semibold">Servicio Realizado</th>
            <th className="pb-2 pr-3 font-semibold">Refacciones Compradas</th>
            <th className="pb-2 pr-3 font-semibold text-right whitespace-nowrap">Costo Venta</th>
            <th className="pb-2 pr-3 font-semibold text-right whitespace-nowrap">Costo Compra</th>
            <th className="pb-2 font-semibold text-right whitespace-nowrap">Ganancia</th>
          </tr>
        </thead>
        <tbody>
          {ordenes.map(o => {
            const esAbierta = ESTADOS_ABIERTOS.has(o.estado?.toLowerCase() ?? '');
            return (
              <tr
                key={o.id}
                className="border-b border-gray-100 dark:border-gray-800 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors align-top"
              >
                {/* Fecha */}
                <td className="py-2 pr-3 tabular-nums text-gray-500 dark:text-gray-400 whitespace-nowrap">
                  {formatFecha(o.fecha)}
                  {esAbierta && (
                    <span className="block mt-0.5 text-[9px] bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 rounded px-1 py-0.5 font-medium leading-none">
                      anticipo
                    </span>
                  )}
                </td>

                {/* Unidad / Cliente */}
                <td className="py-2 pr-3 max-w-[160px]">
                  <div className="font-medium text-gray-800 dark:text-gray-200 leading-tight truncate">
                    {o.vehiculo || '—'}
                  </div>
                  <div className="text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                    {o.cliente_nombre}
                  </div>
                </td>

                {/* Servicio Realizado */}
                <td className="py-2 pr-3 max-w-[200px]">
                  {o.servicios.length > 0 ? (
                    <ul className="space-y-0.5">
                      {o.servicios.map((s, i) => (
                        <li key={i} className="text-gray-700 dark:text-gray-300 leading-tight">
                          {s.descripcion}
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <span className="text-gray-400 dark:text-gray-600">—</span>
                  )}
                </td>

                {/* Refacciones Compradas */}
                <td className="py-2 pr-3 max-w-[200px]">
                  {o.refacciones_detalle.length > 0 ? (
                    <ul className="space-y-0.5">
                      {o.refacciones_detalle.map((r, i) => (
                        <li key={i} className="leading-tight">
                          <span className="text-gray-700 dark:text-gray-300">{r.descripcion}</span>
                          {r.proveedor && (
                            <span className="text-gray-400 dark:text-gray-500 ml-1 italic">
                              ({r.proveedor})
                            </span>
                          )}
                        </li>
                      ))}
                    </ul>
                  ) : (
                    <span className="text-gray-400 dark:text-gray-600">—</span>
                  )}
                </td>

                {/* Costo de Venta */}
                <td className="py-2 pr-3 text-right tabular-nums text-gray-700 dark:text-gray-300 whitespace-nowrap font-medium">
                  {formatMoneda(o.costo_venta)}
                </td>

                {/* Costo de Compra */}
                <td className="py-2 pr-3 text-right tabular-nums text-gray-500 dark:text-gray-400 whitespace-nowrap">
                  {o.costo_refacciones > 0 ? formatMoneda(o.costo_refacciones) : '—'}
                </td>

                {/* Ganancia */}
                <td
                  className={`py-2 text-right tabular-nums font-semibold whitespace-nowrap ${
                    o.ganancia >= 0
                      ? 'text-green-600 dark:text-green-400'
                      : 'text-red-600 dark:text-red-400'
                  }`}
                >
                  {formatMoneda(o.ganancia)}
                </td>
              </tr>
            );
          })}
        </tbody>
        <tfoot>
          <tr className="bg-gray-100 dark:bg-gray-700/50 font-bold border-t-2 border-gray-300 dark:border-gray-600">
            <td
              className="py-2.5 pr-3 pl-1 text-gray-600 dark:text-gray-300 text-xs uppercase tracking-wide"
              colSpan={4}
            >
              Total período · {ordenes.length} {ordenes.length === 1 ? 'orden' : 'órdenes'}
            </td>
            <td className="py-2.5 pr-3 text-right tabular-nums text-gray-800 dark:text-gray-100 whitespace-nowrap">
              {formatMoneda(totales.costo_venta)}
            </td>
            <td className="py-2.5 pr-3 text-right tabular-nums text-gray-500 dark:text-gray-400 whitespace-nowrap">
              {totales.costo_refacciones > 0 ? formatMoneda(totales.costo_refacciones) : '—'}
            </td>
            <td
              className={`py-2.5 text-right tabular-nums whitespace-nowrap ${
                totales.ganancia >= 0
                  ? 'text-green-700 dark:text-green-400'
                  : 'text-red-700 dark:text-red-400'
              }`}
            >
              {formatMoneda(totales.ganancia)}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  );
};
