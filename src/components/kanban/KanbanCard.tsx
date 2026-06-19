import { useRef, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import type { Orden } from '../../types';
import { ordenesAPI } from '../../services/api';
import { useToastContext } from '../../contexts/ToastContext';

const NEXT_ESTADO: Record<string, string> = {
  recibido:      'diagnostico',
  diagnostico:   'en_reparacion',
  en_reparacion: 'listo_entrega',
  listo_entrega: 'entregado',
};

const ESTADO_LABELS: Record<string, string> = {
  recibido:      'Recibido',
  diagnostico:   'Diagnostico',
  en_reparacion: 'En reparacion',
  listo_entrega: 'Listo para entrega',
  entregado:     'Entregado',
};

interface KanbanCardProps {
  orden: Orden;
  /** Cuando es true, la card se renderiza como ghost en el DragOverlay y no necesita draggable */
  isOverlay?: boolean;
  /** Clases Tailwind para la barra de acento izquierda (ej: 'border-l-slate-500') */
  cardAccent?: string;
  /** Callback para que el padre actualice su estado local al avanzar la orden */
  onEstadoChange?: (id: number, nuevoEstado: string) => void;
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

export function KanbanCard({ orden, isOverlay = false, cardAccent = 'border-l-slate-400', onEstadoChange }: KanbanCardProps) {
  const navigate = useNavigate();
  const { showError } = useToastContext();
  const [isAdvancing, setIsAdvancing] = useState(false);

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
  const numeroSerie = (extra.numero_serie as string | undefined) || '';
  const kmEntrada = (extra.kilometraje_entrada as string | number | undefined);

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

  const estadoActual: string = (extra.estado as string) || orden.estado || '';
  const nextEstado = NEXT_ESTADO[estadoActual];

  const handleAvanzarEstado = async (e: React.MouseEvent) => {
    e.stopPropagation();
    if (!nextEstado || isAdvancing || isOverlay) return;

    const idNum = Number(orden.id);
    setIsAdvancing(true);
    // Actualización optimista
    onEstadoChange?.(idNum, nextEstado);
    try {
      await ordenesAPI.update(String(orden.id), { estado: nextEstado } as Partial<Orden>);
    } catch {
      // Revertir al estado anterior
      onEstadoChange?.(idNum, estadoActual);
      showError('Error al avanzar estado', 'No se pudo actualizar el estado de la orden. Intenta de nuevo.');
    } finally {
      setIsAdvancing(false);
    }
  };

  const style: React.CSSProperties = {
    transform: CSS.Translate.toString(transform),
    opacity: isDragging ? 0.4 : 1,
    cursor: isDragging ? 'grabbing' : 'pointer',
    touchAction: 'none',
  };

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
                 ${isOverlay ? 'shadow-xl rotate-1 opacity-95' : 'kanban-card-enter'}`}
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

      {/* Serie y Kilometraje — siempre visibles */}
      <div className="flex items-center gap-3 mb-3 flex-wrap">
        <span className="flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
          <svg className="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
              d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
          </svg>
          <span className="font-medium text-gray-500 dark:text-gray-400">Serie:</span>
          <span>{numeroSerie || '—'}</span>
        </span>
        <span className="flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
          <svg className="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
              d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
          </svg>
          <span className="font-medium text-gray-500 dark:text-gray-400">Km:</span>
          <span>{kmEntrada != null && kmEntrada !== '' ? Number(kmEntrada).toLocaleString('es-MX') : '—'}</span>
        </span>
      </div>

      {/* Footer */}
      {fecha && (
        <div className="border-t border-gray-100 dark:border-gray-700 pt-2 mt-2 flex items-center justify-between">
          <span className="text-xs text-gray-400 dark:text-gray-500">
            {tiempoTranscurrido(fecha)}
          </span>

          {/* Botón avanzar al siguiente estado — solo visible en hover, oculto en entregado */}
          {nextEstado && !isOverlay && (
            <button
              type="button"
              title={`Mover a ${ESTADO_LABELS[nextEstado] ?? nextEstado}`}
              aria-label={`Mover a ${ESTADO_LABELS[nextEstado] ?? nextEstado}`}
              disabled={isAdvancing}
              onPointerDown={(e) => e.stopPropagation()}
              onClick={handleAvanzarEstado}
              className="opacity-0 group-hover:opacity-100 transition-opacity duration-150
                         flex items-center justify-center w-6 h-6 rounded-full
                         bg-gray-100 hover:bg-sag-100 dark:bg-gray-700 dark:hover:bg-sag-900/40
                         text-gray-500 hover:text-sag-600 dark:text-gray-400 dark:hover:text-sag-400
                         disabled:opacity-50 disabled:cursor-not-allowed
                         focus:outline-none focus:ring-2 focus:ring-sag-400 focus:ring-offset-1"
            >
              {isAdvancing ? (
                <svg className="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                  <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                </svg>
              ) : (
                <svg className="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M9 5l7 7-7 7" />
                </svg>
              )}
            </button>
          )}
        </div>
      )}
    </div>
  );
}
