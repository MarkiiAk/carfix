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
  const [, m, d] = iso.split('T')[0].split('-');
  return `${d.replace(/^0/, '')}/${m}`;
};

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
        Sin órdenes cerradas en este período.
      </p>
    );
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full text-sm">
        <thead>
          <tr className="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
            <th className="pb-2 pr-3 font-medium w-12">Fecha</th>
            <th className="pb-2 pr-3 font-medium">Cliente</th>
            <th className="pb-2 pr-3 font-medium hidden sm:table-cell">Vehículo</th>
            <th className="pb-2 pr-3 font-medium text-right">Venta</th>
            <th className="pb-2 pr-3 font-medium text-right hidden md:table-cell">Refacciones</th>
            <th className="pb-2 font-medium text-right">Ganancia</th>
          </tr>
        </thead>
        <tbody>
          {ordenes.map(o => (
            <tr
              key={o.id}
              className="border-b border-gray-100 dark:border-gray-800 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors"
            >
              <td className="py-2 pr-3 text-gray-500 dark:text-gray-400 tabular-nums whitespace-nowrap">
                {formatFecha(o.fecha)}
              </td>
              <td className="py-2 pr-3 text-gray-800 dark:text-gray-200 max-w-[140px] truncate">
                {o.cliente_nombre}
              </td>
              <td className="py-2 pr-3 text-gray-500 dark:text-gray-400 hidden sm:table-cell max-w-[120px] truncate">
                {o.vehiculo}
              </td>
              <td className="py-2 pr-3 text-right tabular-nums text-gray-700 dark:text-gray-300 whitespace-nowrap">
                {formatMoneda(o.costo_venta)}
              </td>
              <td className="py-2 pr-3 text-right tabular-nums text-gray-400 dark:text-gray-500 hidden md:table-cell whitespace-nowrap">
                {o.costo_refacciones > 0 ? formatMoneda(o.costo_refacciones) : '—'}
              </td>
              <td
                className={`py-2 text-right tabular-nums font-medium whitespace-nowrap ${
                  o.ganancia >= 0
                    ? 'text-green-600 dark:text-green-400'
                    : 'text-red-600 dark:text-red-400'
                }`}
              >
                {formatMoneda(o.ganancia)}
              </td>
            </tr>
          ))}
        </tbody>
        <tfoot>
          <tr className="bg-gray-100 dark:bg-gray-700/50 font-bold">
            <td className="py-2.5 pr-3 pl-1 text-gray-600 dark:text-gray-300 text-xs uppercase tracking-wide" colSpan={3}>
              Total período
            </td>
            <td className="py-2.5 pr-3 text-right tabular-nums text-gray-800 dark:text-gray-100">
              {formatMoneda(totales.costo_venta)}
            </td>
            <td className="py-2.5 pr-3 text-right tabular-nums text-gray-500 dark:text-gray-400 hidden md:table-cell">
              {totales.costo_refacciones > 0 ? formatMoneda(totales.costo_refacciones) : '—'}
            </td>
            <td
              className={`py-2.5 text-right tabular-nums ${
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
