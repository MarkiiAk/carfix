import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { usePresupuestoStore } from '../store/usePresupuestoStore';
import { ordenesAPI } from '../services/api';
import type { Orden } from '../types';
import { Button } from '../components/ui/Button';

export const Dashboard = () => {
  const [ordenes, setOrdenes] = useState<Orden[]>([]);
  const [filteredOrdenes, setFilteredOrdenes] = useState<Orden[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [estadoFilter, setEstadoFilter] = useState<string>('todas');
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 15;

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

  useEffect(() => {
    filterOrdenes();
    setCurrentPage(1);
  }, [searchTerm, estadoFilter, ordenes]);

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

  const filterOrdenes = () => {
    let filtered = [...ordenes];

    if (searchTerm) {
      const term = searchTerm.toLowerCase();
      filtered = filtered.filter((orden) => {
        const ordenAny = orden as any;
        const clienteNombre = ordenAny.cliente_nombre || orden.cliente?.nombreCompleto || '';
        const placas = ordenAny.placas || orden.vehiculo?.placas || '';
        const marca = ordenAny.marca || orden.vehiculo?.marca || '';
        const modelo = ordenAny.modelo || orden.vehiculo?.modelo || '';
        const folio = ordenAny.numero_orden || orden.folio || '';
        return (
          folio.toLowerCase().includes(term) ||
          clienteNombre.toLowerCase().includes(term) ||
          placas.toLowerCase().includes(term) ||
          marca.toLowerCase().includes(term) ||
          modelo.toLowerCase().includes(term)
        );
      });
    }

    if (estadoFilter !== 'todas') {
      filtered = filtered.filter((orden) => orden.estado === estadoFilter);
    }

    setFilteredOrdenes(filtered);
  };

  const getEstadoBadge = (estado: string) => {
    const normalizedEstado = (estado === 'pendiente' ? 'abierta' : estado) as 'abierta' | 'cerrada';
    const badges = {
      abierta: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
      cerrada: 'bg-sag-100 text-sag-800 dark:bg-sag-900/30 dark:text-sag-400',
    };
    const labels = {
      abierta: 'Abierta',
      cerrada: 'Cerrada',
    };
    return (
      <span className={`px-3 py-1 rounded-full text-xs font-medium ${badges[normalizedEstado]}`}>
        {labels[normalizedEstado]}
      </span>
    );
  };

  const totalItems = filteredOrdenes.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentItems = filteredOrdenes.slice(startIndex, endIndex);

  const stats = {
    total: ordenes.length,
    abiertas: ordenes.filter((o) => {
      const estado = (o as any).estado || o.estado;
      return estado === 'abierta' || estado === 'pendiente';
    }).length,
    cerradas: ordenes.filter((o) => {
      const estado = (o as any).estado || o.estado;
      return estado === 'cerrada' || estado === 'entregada';
    }).length,
  };

  const goToPage = (page: number) => setCurrentPage(page);
  const goToPrevious = () => setCurrentPage((p) => Math.max(p - 1, 1));
  const goToNext = () => setCurrentPage((p) => Math.min(p + 1, totalPages));

  const getPageNumbers = () => {
    const pages: number[] = [];
    const maxVisiblePages = 5;
    if (totalPages <= maxVisiblePages) {
      for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
      const startPage = Math.max(1, currentPage - 2);
      const endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
      for (let i = startPage; i <= endPage; i++) pages.push(i);
    }
    return pages;
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Stats Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-600 dark:text-gray-400">Total Ordenes</p>
              <p className="text-3xl font-bold text-gray-900 dark:text-white mt-1">{stats.total}</p>
            </div>
            <div className="w-12 h-12 bg-sag-100 dark:bg-sag-900/30 rounded-xl flex items-center justify-center">
              <svg className="w-6 h-6 text-sag-600 dark:text-sag-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-600 dark:text-gray-400">Abiertas</p>
              <p className="text-3xl font-bold text-sag-600 dark:text-sag-400 mt-1">{stats.abiertas}</p>
            </div>
            <div className="w-12 h-12 bg-sag-100 dark:bg-sag-900/30 rounded-xl flex items-center justify-center">
              <svg className="w-6 h-6 text-sag-600 dark:text-sag-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
              </svg>
            </div>
          </div>
        </div>

        <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-600 dark:text-gray-400">Cerradas</p>
              <p className="text-3xl font-bold text-sag-600 dark:text-sag-400 mt-1">{stats.cerradas}</p>
            </div>
            <div className="w-12 h-12 bg-sag-100 dark:bg-sag-900/30 rounded-xl flex items-center justify-center">
              <svg className="w-6 h-6 text-sag-600 dark:text-sag-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
          </div>
        </div>
      </div>

      {/* Search and Filters */}
      <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 mb-6">
        <div className="flex flex-col sm:flex-row gap-4">
          <div className="flex-1">
            <div className="relative">
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
                className="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-sag-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
              />
            </div>
          </div>

          <div className="flex gap-2">
            <select
              value={estadoFilter}
              onChange={(e) => setEstadoFilter(e.target.value)}
              className="px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-sag-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
            >
              <option value="todas">Todos los estados</option>
              <option value="abierta">Abiertas</option>
              <option value="cerrada">Cerradas</option>
            </select>

            <Button
              onClick={() => navigate('/nueva-orden')}
              className="bg-gradient-to-r from-sag-500 to-sag-600 hover:from-sag-600 hover:to-sag-700 text-white shadow-lg shadow-sag-500/30 hover:shadow-sag-500/40 transition-all"
            >
              + Nueva Orden
            </Button>
          </div>
        </div>
      </div>

      {/* Table */}
      <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center py-12">
            <div className="text-center space-y-3">
              <div className="animate-spin h-10 w-10 border-4 border-sag-500 border-t-transparent rounded-full mx-auto shadow-lg shadow-sag-500/30" />
              <p className="text-gray-600 dark:text-gray-400">Cargando ordenes...</p>
            </div>
          </div>
        ) : filteredOrdenes.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-12">
            <svg className="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <p className="text-gray-600 dark:text-gray-400 text-lg">No se encontraron ordenes</p>
            <p className="text-gray-500 dark:text-gray-500 text-sm">Intenta ajustar los filtros de busqueda</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                <tr>
                  <th className="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Folio</th>
                  <th className="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cliente</th>
                  <th className="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Vehiculo</th>
                  <th className="hidden md:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Placas</th>
                  <th className="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                  <th className="hidden sm:table-cell px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Fecha</th>
                  <th className="px-4 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                {currentItems.map((orden) => {
                  const ordenAny = orden as any;
                  const folio = ordenAny.numero_orden || orden.folio || '';
                  const clienteNombre = ordenAny.cliente_nombre || orden.cliente?.nombreCompleto || '';
                  const marca = ordenAny.marca || orden.vehiculo?.marca || '';
                  const modelo = ordenAny.modelo || orden.vehiculo?.modelo || '';
                  const placas = ordenAny.placas || orden.vehiculo?.placas || '';
                  const fecha = ordenAny.fecha_ingreso || orden.fechaCreacion || '';
                  return (
                    <tr key={orden.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                      <td className="px-4 sm:px-6 py-4 whitespace-nowrap">
                        <span className="text-sm font-medium text-gray-900 dark:text-white">{folio}</span>
                      </td>
                      <td className="px-4 sm:px-6 py-4">
                        <span className="text-sm text-gray-900 dark:text-white leading-tight">{clienteNombre}</span>
                      </td>
                      <td className="px-4 sm:px-6 py-4 whitespace-nowrap">
                        <span className="text-sm text-gray-900 dark:text-white">{marca} {modelo}</span>
                      </td>
                      <td className="hidden md:table-cell px-6 py-4 whitespace-nowrap">
                        <span className="text-sm text-gray-900 dark:text-white">{placas}</span>
                      </td>
                      <td className="px-4 sm:px-6 py-4 whitespace-nowrap">
                        {getEstadoBadge(ordenAny.estado || orden.estado)}
                      </td>
                      <td className="hidden sm:table-cell px-6 py-4 whitespace-nowrap">
                        <span className="text-sm text-gray-900 dark:text-white">
                          {new Date(fecha).toLocaleDateString('es-MX')}
                        </span>
                      </td>
                      <td className="px-4 sm:px-6 py-4 whitespace-nowrap">
                        <Button
                          variant="secondary"
                          onClick={() => navigate(`/orden/${orden.id}`)}
                          className="!text-xs !py-1.5"
                        >
                          Ver
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        )}

        {/* Paginacion */}
        {!isLoading && filteredOrdenes.length > 0 && totalPages > 1 && (
          <div className="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 px-6 py-4">
            <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
              <div className="flex items-center text-sm text-gray-700 dark:text-gray-300">
                <span>Mostrando {startIndex + 1} - {Math.min(endIndex, totalItems)} de {totalItems} ordenes</span>
              </div>
              <div className="flex items-center gap-2">
                <Button
                  onClick={goToPrevious}
                  disabled={currentPage === 1}
                  className="!px-3 !py-2 !text-sm bg-gray-100 text-gray-700 border border-gray-300 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                >
                  Anterior
                </Button>
                <div className="flex items-center gap-1">
                  {getPageNumbers().map((pageNumber) => (
                    <button
                      key={pageNumber}
                      onClick={() => goToPage(pageNumber)}
                      className={`px-3 py-2 text-sm rounded-lg transition-colors ${
                        pageNumber === currentPage
                          ? 'bg-sag-500 text-white shadow-md'
                          : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                      }`}
                    >
                      {pageNumber}
                    </button>
                  ))}
                </div>
                <Button
                  onClick={goToNext}
                  disabled={currentPage === totalPages}
                  className="!px-3 !py-2 !text-sm bg-gray-100 text-gray-700 border border-gray-300 hover:bg-gray-200 disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                >
                  Siguiente
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
