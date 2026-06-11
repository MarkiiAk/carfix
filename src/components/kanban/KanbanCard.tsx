import { useRef, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import type { Orden } from '../../types';

interface KanbanCardProps {
  orden: Orden;
  /** Cuando es true, la card se renderiza como ghost en el DragOverlay y no necesita draggable */
  isOverlay?: boolean;
  /** Clases Tailwind para la barra de acento izquierda (ej: 'border-l-slate-500') */
  cardAccent?: string;
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

export function KanbanCard({ orden, isOverlay = false, cardAccent = 'border-l-slate-400' }: KanbanCardProps) {
  const navigate = useNavigate();
  // El API devuelve campos extras (snake_case) no presentes en el tipo Orden
  const extra = orden as unknown as Record<string, unknown>;

  const folio = (extra.numero_orden as string) || orden.folio || '';
  const folioSucursal = (extra.folio_sucursal as number | undefined) ?? orden.folio_sucursal;
  const clienteNombre = (extra.cliente_nombre as string) || orden.cliente?.nombreCompleto || 'Sin cliente';
  const marca = (extra.marca as string) || orden.vehiculo?.marca || '';
  const modelo = (extra.modelo as string) || orden.vehiculo?.modelo || '';
  const anio = (extra.anio as string | number) || orden.vehiculo?.year || '';
  const placas = (extra.placas as string) || orden.vehiculo?.placas || '';
  const fecha = (extra.fecha_ingreso as string) || orden.fechaCreacion || '';
  const total = Number((extra.total as number | undefined) ?? orden.resumen?.total ?? 0);
  const numeroSerie = (extra.numero_serie as string | undefined) || '';
  const vehiculoKm = (extra.vehiculo_kilometraje as number | undefined) ?? null;
  const problemaReportado = (extra.problema_reportado as string | undefined) || '';
  const fechaPromesaEntrega = (extra.fecha_promesa_entrega as string | undefined) || '';

  // dnd-kit: draggable por ID de la orden
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: String(orden.id),
    disabled: isOverlay,
  });

  // Bug 1: detectar drag para evitar navegar al soltar
  const hasDragged = useRef(false);
  useEffect(() => {
    if (isDragging) hasDragged.current = true;
  }, [isDragging]);

  const handleCardClick = () => {
    if (hasDragged.current) {
      hasDragged.current = false;
      return;
    }
    navigate(`/orden/${orden.id}`);
  };

  const style: React.CSSProperties = {
    transform: CSS.Translate.toString(transform),
    opacity: isDragging ? 0.4 : 1,
    cursor: isDragging ? 'grabbing' : 'pointer',
    touchAction: 'none',
  };

  // Formatear fecha promesa a DD/MM
  function formatFechaCorta(fechaStr: string): string {
    const d = new Date(fechaStr);
    if (isNaN(d.getTime())) return '';
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit' });
  }

  return (
    <div
      ref={setNodeRef}
      style={style}
      {...listeners}
      {...attributes}
      onClick={handleCardClick}
      className={`w-full text-left bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700
                 border-l-4 ${cardAccent} rounded-xl p-4 shadow-soft hover:shadow-medium hover:-translate-y-0.5
                 transition-shadow duration-200 ease-out group select-none
                 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-1
                 ${isOverlay ? 'shadow-xl rotate-1 opacity-95' : ''}`}
    >
      {/* Folio */}
      <div className="flex items-center gap-1.5 mb-2">
        <span className="text-xs font-semibold text-gray-500 dark:text-gray-400 tracking-wide">
          {folio}
        </span>
        {folioSucursal != null && (
          <span className="text-xs font-bold text-white bg-gray-400 dark:bg-gray-600 rounded px-1 leading-tight">
            #{folioSucursal}
          </span>
        )}
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
                         bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 mb-1.5">
          {placas}
        </span>
      )}

      {/* Problema reportado */}
      {problemaReportado && (
        <p className="text-xs text-gray-600 dark:text-gray-300 mb-1.5 line-clamp-2 leading-snug">
          {problemaReportado}
        </p>
      )}

      {/* Serie y Kilometraje */}
      {(numeroSerie || vehiculoKm != null) && (
        <div className="flex items-center gap-3 mb-3 flex-wrap">
          {numeroSerie && (
            <span className="flex items-center gap-0.5 text-xs text-gray-400 dark:text-gray-500">
              <svg className="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                  d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
              </svg>
              <span>{numeroSerie}</span>
            </span>
          )}
          {vehiculoKm != null && (
            <span className="flex items-center gap-0.5 text-xs text-gray-400 dark:text-gray-500">
              <svg className="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span>{Number(vehiculoKm).toLocaleString('es-MX')} km</span>
            </span>
          )}
        </div>
      )}

      {/* Separador */}
      <div className="border-t border-gray-100 dark:border-gray-700 pt-3 flex items-center justify-between">
        {/* Total */}
        <span className="text-sm font-bold text-gray-900 dark:text-white">
          {formatMXN(total)}
        </span>
        {/* Promesa de entrega o tiempo transcurrido */}
        <div className="flex items-center gap-2">
          {fechaPromesaEntrega && (
            <span className="text-xs text-amber-600 dark:text-amber-400 font-medium">
              Promesa: {formatFechaCorta(fechaPromesaEntrega)}
            </span>
          )}
          {fecha && (
            <span className="text-xs text-gray-400 dark:text-gray-500">
              {tiempoTranscurrido(fecha)}
            </span>
          )}
        </div>
      </div>
    </div>
  );
}
