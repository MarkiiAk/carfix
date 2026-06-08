import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { usePresupuestoStore } from '../store/usePresupuestoStore';
import { ordenesAPI } from '../services/api';
import type { Orden } from '../types';
import { Button } from '../components/ui/Button';
import { KanbanColumn, KANBAN_COLUMNS } from '../components/kanban/KanbanColumn';
import type { KanbanEstado } from '../components/kanban/KanbanColumn';

/** Normaliza valores legacy al modelo Kanban de 5 estados */
function normalizarEstado(estado: string): KanbanEstado {
  switch (estado) {
    case 'pendiente': return 'recibido';
    case 'abierta':   return 'en_reparacion';
    case 'cerrada':
    case 'completado':
    case 'completada':
    case 'entregada':  return 'entregado';
    case 'recibido':
    case 'diagnostico':
    case 'en_reparacion':
    case 'listo_entrega':
    case 'entregado':  return estado as KanbanEstado;
    default:           return 'recibido';
  }
}

export const Dashboard = () => {
  const [ordenes, setOrdenes] = useState<Orden[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');

  const { user, isLoading: authLoading } = useAuth();
  const { themeMode } = usePresupuestoStore();
  const navigate = useNavigate();

  // Aplicar el tema al documento
  useEffect(() => {
    if (themeMode === 'dark') {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  }, [themeMode]);

  useEffect(() => {
    let isActive = true;
    const loadData = async () => {
      if (!isActive || authLoading || !user) return;
      await loadOrdenes();
    };
    loadData();
    return () => { isActive = false; };
  }, [authLoading, user]);

  const loadOrdenes = async () => {
    try {
      setIsLoading(true);
      const data = await ordenesAPI.getAll();
      setOrdenes(data);
    } catch {
      // silencioso
    } finally {
      setIsLoading(false);
    }
  };

  // Filtrar por término de búsqueda
  const ordenesFiltradas = searchTerm
    ? ordenes.filter((orden) => {
        const o = orden as any;
        const term = searchTerm.toLowerCase();
        const folio = o.numero_orden || orden.folio || '';
        const cliente = o.cliente_nombre || orden.cliente?.nombreCompleto || '';
        const placas = o.placas || orden.vehiculo?.placas || '';
        const marca = o.marca || orden.vehiculo?.marca || '';
        const modelo = o.modelo || orden.vehiculo?.modelo || '';
        return (
          folio.toLowerCase().includes(term) ||
          cliente.toLowerCase().includes(term) ||
          placas.toLowerCase().includes(term) ||
          marca.toLowerCase().includes(term) ||
          modelo.toLowerCase().includes(term)
        );
      })
    : ordenes;

  // Agrupar órdenes por estado Kanban
  const ordenesporColumna: Record<KanbanEstado, Orden[]> = {
    recibido: [],
    diagnostico: [],
    en_reparacion: [],
    listo_entrega: [],
    entregado: [],
  };

  for (const orden of ordenesFiltradas) {
    const o = orden as any;
    const estadoRaw = o.estado || orden.estado || 'recibido';
    const estadoNorm = normalizarEstado(estadoRaw);
    ordenesporColumna[estadoNorm].push(orden);
  }

  // KPIs
  const totalOrdenes = ordenes.length;
  const totalAbiertas = ordenes.filter((o) => {
    const estado = normalizarEstado((o as any).estado || o.estado || '');
    return estado !== 'entregado';
  }).length;
  const totalEntregadas = ordenes.filter((o) => {
    const estado = normalizarEstado((o as any).estado || o.estado || '');
    return estado === 'entregado';
  }).length;

  return (
    <div className="max-w-full px-4 sm:px-6 lg:px-8 py-8">
      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 shadow-soft">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-600 dark:text-gray-400">Total Ordenes</p>
              <p className="text-3xl font-bold text-gray-900 dark:text-white mt-1">{totalOrdenes}</p>
            </div>
            <div className="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-xl flex items-center justify-center">
              <svg className="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 shadow-soft">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-600 dark:text-gray-400">En proceso</p>
              <p className="text-3xl font-bold text-info-600 dark:text-info-400 mt-1">{totalAbiertas}</p>
            </div>
            <div className="w-12 h-12 bg-info-100 dark:bg-info-900/30 rounded-xl flex items-center justify-center">
              <svg className="w-6 h-6 text-info-600 dark:text-info-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 shadow-soft">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-600 dark:text-gray-400">Entregadas</p>
              <p className="text-3xl font-bold text-success-600 dark:text-success-400 mt-1">{totalEntregadas}</p>
            </div>
            <div className="w-12 h-12 bg-success-100 dark:bg-success-900/30 rounded-xl flex items-center justify-center">
              <svg className="w-6 h-6 text-success-600 dark:text-success-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      {/* Search + Nueva Orden */}
      <div className="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 shadow-soft mb-6">
        <div className="flex flex-col sm:flex-row gap-3">
          <div className="flex-1 relative">
            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>
            <input
              type="text"
              placeholder="Buscar por folio, cliente, placas, marca o modelo..."
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              className="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg
                         focus:ring-2 focus:ring-slate-400 focus:border-transparent
                         dark:bg-gray-700 dark:text-white text-sm"
            />
          </div>
          <Button
            onClick={() => navigate('/nueva-orden')}
            className="bg-gradient-to-r from-sag-500 to-sag-600 hover:from-sag-600 hover:to-sag-700
                       text-white shadow-lg shadow-sag-500/30 hover:shadow-sag-500/40 transition-all whitespace-nowrap"
          >
            + Nueva Orden
          </Button>
        </div>
      </div>

      {/* Kanban Board */}
      {isLoading ? (
        <div className="flex items-center justify-center py-24">
          <div className="text-center space-y-3">
            <div className="animate-spin h-10 w-10 border-4 border-sag-500 border-t-transparent rounded-full mx-auto shadow-lg shadow-sag-500/30" />
            <p className="text-gray-600 dark:text-gray-400">Cargando ordenes...</p>
          </div>
        </div>
      ) : (
        <div className="overflow-x-auto pb-4">
          <div className="flex gap-4 min-w-max">
            {KANBAN_COLUMNS.map((col) => (
              <KanbanColumn
                key={col.estado}
                config={col}
                ordenes={ordenesporColumna[col.estado]}
              />
            ))}
          </div>
        </div>
      )}
    </div>
  );
};
