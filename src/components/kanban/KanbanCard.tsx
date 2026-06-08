import { useNavigate } from 'react-router-dom';
import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import type { Orden } from '../../types';

interface KanbanCardProps {
  orden: Orden;
  /** Cuando es true, la card se renderiza como ghost en el DragOverlay y no necesita draggable */
  isOverlay?: boolean;
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

export function KanbanCard({ orden, isOverlay = false }: KanbanCardProps) {
  const navigate = useNavigate();
  // El API devuelve campos extras (snake_case) no presentes en el tipo Orden
  const extra = orden as unknown as Record<string, unknown>;

  const folio = (extra.numero_orden as string) || orden.folio || '';
  const clienteNombre = (extra.cliente_nombre as string) || orden.cliente?.nombreCompleto || 'Sin cliente';
  const marca = (extra.marca as string) || orden.vehiculo?.marca || '';
  const modelo = (extra.modelo as string) || orden.vehiculo?.modelo || '';
  const anio = (extra.anio as string | number) || orden.vehiculo?.year || '';
  const placas = (extra.placas as string) || orden.vehiculo?.placas || '';
  const fecha = (extra.fecha_ingreso as string) || orden.fechaCreacion || '';
  const total = Number((extra.total as number | undefined) ?? orden.resumen?.total ?? 0);

  // dnd-kit: draggable por ID de la orden
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: String(orden.id),
    disabled: isOverlay,
  });

  const style: React.CSSProperties = {
    transform: CSS.Translate.toString(transform),
    opacity: isDragging ? 0.4 : 1,
    cursor: isDragging ? 'grabbing' : 'grab',
    touchAction: 'none',
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...listeners}
      {...attributes}
      className={`w-full text-left bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700
                 rounded-xl p-4 shadow-soft hover:shadow-medium hover:-translate-y-0.5
                 transition-shadow duration-200 ease-out group select-none
                 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-1
                 ${isOverlay ? 'shadow-xl rotate-1 opacity-95' : ''}`}
    >
      {/* Folio */}
      <div className="flex items-center justify-between mb-2">
        <span className="text-xs font-semibold text-gray-500 dark:text-gray-400 tracking-wide">
          {folio}
        </span>
        {/* Botón de navegación — separado de los listeners de drag */}
        <button
          type="button"
          onClick={(e) => {
            e.stopPropagation();
            navigate(`/orden/${orden.id}`);
          }}
          className="p-0.5 rounded hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
          aria-label="Ver detalle de la orden"
          onPointerDown={(e) => e.stopPropagation()}
        >
          <svg
            className="w-3.5 h-3.5 text-gray-300 dark:text-gray-600 group-hover:text-gray-500 dark:group-hover:text-gray-400 transition-colors"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
        </button>
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
    </div>
  );
}
