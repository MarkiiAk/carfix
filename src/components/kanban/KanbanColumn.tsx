import { useDroppable } from '@dnd-kit/core';
import type { Orden } from '../../types';
import { KanbanCard } from './KanbanCard';

export type KanbanEstado = 'recibido' | 'diagnostico' | 'en_reparacion' | 'listo_entrega' | 'entregado';

export interface KanbanColumnConfig {
  estado: KanbanEstado;
  label: string;
  /** Clases Tailwind para el borde de acento superior de la columna */
  accentBorder: string;
  /** Clases para el badge contador */
  badgeBg: string;
  badgeText: string;
  /** Icono SVG path */
  iconPath: string;
}

export const KANBAN_COLUMNS: KanbanColumnConfig[] = [
  {
    estado: 'recibido',
    label: 'Recibido',
    accentBorder: 'border-t-gray-400',
    badgeBg: 'bg-gray-100 dark:bg-gray-700',
    badgeText: 'text-gray-700 dark:text-gray-300',
    iconPath: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
  },
  {
    estado: 'diagnostico',
    label: 'Diagnostico',
    accentBorder: 'border-t-warning-500',
    badgeBg: 'bg-warning-100 dark:bg-warning-900/30',
    badgeText: 'text-warning-800 dark:text-warning-300',
    iconPath: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
  },
  {
    estado: 'en_reparacion',
    label: 'En reparacion',
    accentBorder: 'border-t-orange-500',
    badgeBg: 'bg-orange-100 dark:bg-orange-900/30',
    badgeText: 'text-orange-800 dark:text-orange-300',
    iconPath: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
  },
  {
    estado: 'listo_entrega',
    label: 'Listo para entrega',
    accentBorder: 'border-t-info-500',
    badgeBg: 'bg-info-100 dark:bg-info-900/30',
    badgeText: 'text-info-800 dark:text-info-300',
    iconPath: 'M5 13l4 4L19 7',
  },
  {
    estado: 'entregado',
    label: 'Entregado',
    accentBorder: 'border-t-success-500',
    badgeBg: 'bg-success-100 dark:bg-success-900/30',
    badgeText: 'text-success-800 dark:text-success-300',
    iconPath: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
  },
];

interface KanbanColumnProps {
  config: KanbanColumnConfig;
  ordenes: Orden[];
}

export function KanbanColumn({ config, ordenes }: KanbanColumnProps) {
  const { label, accentBorder, badgeBg, badgeText, iconPath, estado } = config;
  const count = ordenes.length;

  // Cada columna es una zona de drop identificada por el estado Kanban
  const { setNodeRef, isOver } = useDroppable({ id: estado });

  return (
    <div className="flex flex-col min-w-[280px] max-w-[320px] flex-shrink-0">
      {/* Column Header */}
      <div
        className={`bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700
                    border-t-4 ${accentBorder} mb-3 px-4 py-3 shadow-soft`}
      >
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            <svg
              className="w-4 h-4 text-gray-500 dark:text-gray-400 flex-shrink-0"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d={iconPath} />
            </svg>
            <h3 className="text-sm font-semibold text-gray-800 dark:text-gray-200 leading-tight">
              {label}
            </h3>
          </div>
          <span
            className={`inline-flex items-center justify-center min-w-[1.5rem] h-6 px-2
                        rounded-full text-xs font-bold ${badgeBg} ${badgeText}`}
          >
            {count}
          </span>
        </div>
      </div>

      {/* Cards Container — drop zone con feedback visual cuando se arrastra sobre ella */}
      <div
        ref={setNodeRef}
        className={`flex flex-col gap-3 overflow-y-auto max-h-[calc(100vh-320px)] pr-1 pb-2
                    rounded-xl transition-colors duration-150
                    ${isOver ? 'bg-slate-100 dark:bg-slate-700/40' : ''}`}
      >
        {count === 0 && !isOver ? (
          <div className="flex flex-col items-center justify-center py-8 px-4
                          bg-gray-50 dark:bg-gray-800/50 rounded-xl border border-dashed
                          border-gray-200 dark:border-gray-700">
            <svg
              className="w-8 h-8 text-gray-300 dark:text-gray-600 mb-2"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p className="text-xs text-gray-400 dark:text-gray-500 text-center">Sin ordenes</p>
          </div>
        ) : (
          ordenes.map((orden) => (
            <KanbanCard key={orden.id} orden={orden} />
          ))
        )}
      </div>
    </div>
  );
}
