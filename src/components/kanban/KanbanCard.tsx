import { useNavigate } from 'react-router-dom';
import type { Orden } from '../../types';

interface KanbanCardProps {
  orden: Orden;
}

function formatMXN(value: number | string | undefined): string {
  const n = Number(value ?? 0);
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(n);
}

function tiempoTranscurrido(fechaStr: string): string {
  const fecha = new Date(fechaStr);
  const ahora = new Date();
  const diffMs = ahora.getTime() - fecha.getTime();
  const diffMin = Math.floor(diffMs / 60000);
  const diffHrs = Math.floor(diffMin / 60);
  const diffDias = Math.floor(diffHrs / 24);

  if (diffDias > 1) return `hace ${diffDias} dias`;
  if (diffDias === 1) return 'hace 1 dia';
  if (diffHrs > 1) return `hace ${diffHrs} horas`;
  if (diffHrs === 1) return 'hace 1 hora';
  if (diffMin > 1) return `hace ${diffMin} minutos`;
  return 'recien creada';
}

export function KanbanCard({ orden }: KanbanCardProps) {
  const navigate = useNavigate();
  const ordenAny = orden as any;

  const folio = ordenAny.numero_orden || orden.folio || '';
  const clienteNombre = ordenAny.cliente_nombre || orden.cliente?.nombreCompleto || 'Sin cliente';
  const marca = ordenAny.marca || orden.vehiculo?.marca || '';
  const modelo = ordenAny.modelo || orden.vehiculo?.modelo || '';
  const anio = ordenAny.anio || orden.vehiculo?.year || '';
  const placas = ordenAny.placas || orden.vehiculo?.placas || '';
  const fecha = ordenAny.fecha_ingreso || orden.fechaCreacion || '';
  const total = Number(ordenAny.total ?? orden.resumen?.total ?? 0);

  return (
    <button
      onClick={() => navigate(`/orden/${orden.id}`)}
      className="w-full text-left bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700
                 rounded-xl p-4 shadow-soft hover:shadow-medium hover:-translate-y-0.5
                 transition-all duration-200 ease-out cursor-pointer group
                 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-1"
    >
      {/* Folio */}
      <div className="flex items-center justify-between mb-2">
        <span className="text-xs font-semibold text-gray-500 dark:text-gray-400 tracking-wide">
          {folio}
        </span>
        <svg
          className="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 group-hover:text-gray-500 dark:group-hover:text-gray-400 transition-colors"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
        </svg>
      </div>

      {/* Cliente */}
      <p className="text-sm font-semibold text-gray-900 dark:text-white leading-tight mb-1 truncate">
        {clienteNombre}
      </p>

      {/* Vehiculo */}
      <p className="text-xs text-gray-500 dark:text-gray-400 mb-1 truncate">
        {[marca, modelo, anio].filter(Boolean).join(' ')}
      </p>
      {placas && (
        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-mono font-medium
                         bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 mb-3">
          {placas}
        </span>
      )}

      {/* Separador */}
      <div className="border-t border-gray-100 dark:border-gray-700 pt-3 flex items-center justify-between">
        {/* Total */}
        <span className="text-sm font-bold text-gray-900 dark:text-white">
          {formatMXN(total)}
        </span>
        {/* Tiempo */}
        {fecha && (
          <span className="text-xs text-gray-400 dark:text-gray-500">
            {tiempoTranscurrido(fecha)}
          </span>
        )}
      </div>
    </button>
  );
}
