import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { Sun, Moon, ArrowLeft } from 'lucide-react';
import { useAuth } from '../contexts/AuthContext';
import { usePresupuestoStore } from '../store/usePresupuestoStore';
import { useAlertas } from '../hooks/useAlertas';
import { EstadisticasAlertas } from '../components/alertas';
import { Button } from '../components/ui';
import type { Alerta } from '../services/alertasAutoService';
import { NotificacionesDropdown } from '../components/NotificacionesDropdown';

type FiltroAlertas = 'todas' | 'pendientes' | 'leidas' | 'urgente' | 'alta' | 'media';

export const Alertas: React.FC = () => {
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const { themeMode, toggleTheme } = usePresupuestoStore();
  const {
    alertas,
    alertasPendientes,
    alertasLeidas,
    alertasPorPrioridad,
    estadisticas,
    cargando,
    error,
    marcarComoLeida,
    cargarAlertas,
    formatearFecha,
    obtenerTextoTiempo,
    obtenerColorPrioridad,
    obtenerTextoPrioridad
  } = useAlertas();

  const [filtroActivo, setFiltroActivo] = useState<FiltroAlertas>('todas');
  const [searchTerm, setSearchTerm] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 15;

  // Aplicar el tema al documento
  useEffect(() => {
    if (themeMode === 'dark') {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  }, [themeMode]);

  // Obtener alertas filtradas
  const obtenerAlertasFiltradas = (): Alerta[] => {
    let alertasBases: Alerta[] = [];
    
    switch (filtroActivo) {
      case 'pendientes':
        alertasBases = alertasPendientes;
        break;
      case 'leidas':
        alertasBases = alertasLeidas;
        break;
      case 'urgente':
        alertasBases = alertasPorPrioridad.urgente;
        break;
      case 'alta':
        alertasBases = alertasPorPrioridad.alta;
        break;
      case 'media':
        alertasBases = alertasPorPrioridad.media;
        break;
      case 'todas':
      default:
        alertasBases = alertas;
    }

    // Filtrar por búsqueda si hay término
    if (searchTerm) {
      const term = searchTerm.toLowerCase();
      alertasBases = alertasBases.filter(alerta =>
        alerta.cliente_nombre.toLowerCase().includes(term) ||
        (alerta.cliente_telefono && alerta.cliente_telefono.includes(term)) ||
        alerta.vehiculo_marca.toLowerCase().includes(term) ||
        alerta.vehiculo_modelo.toLowerCase().includes(term) ||
        (alerta.vehiculo_placas && alerta.vehiculo_placas.toLowerCase().includes(term)) ||
        alerta.servicios_que_dispararon.some(servicio => 
          servicio.toLowerCase().includes(term)
        )
      );
    }

    return alertasBases;
  };

  const alertasFiltradas = obtenerAlertasFiltradas();

  // Lógica de paginación
  const totalItems = alertasFiltradas.length;
  const totalPages = Math.ceil(totalItems / itemsPerPage);
  const startIndex = (currentPage - 1) * itemsPerPage;
  const endIndex = startIndex + itemsPerPage;
  const currentItems = alertasFiltradas.slice(startIndex, endIndex);

  // Reiniciar página cuando cambien filtros
  useEffect(() => {
    setCurrentPage(1);
  }, [filtroActivo, searchTerm]);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const handleMarcarLeida = async (alertaId: number) => {
    await marcarComoLeida(alertaId);
  };

  // Filtros disponibles
  const filtros = [
    { key: 'todas' as FiltroAlertas, label: 'Todas', count: alertas.length, color: 'blue' },
    { key: 'pendientes' as FiltroAlertas, label: 'Pendientes', count: alertasPendientes.length, color: 'red' },
    { key: 'urgente' as FiltroAlertas, label: 'Urgentes', count: alertasPorPrioridad.urgente.length, color: 'red' },
    { key: 'alta' as FiltroAlertas, label: 'Alta', count: alertasPorPrioridad.alta.length, color: 'orange' },
    { key: 'media' as FiltroAlertas, label: 'Media', count: alertasPorPrioridad.media.length, color: 'yellow' },
    { key: 'leidas' as FiltroAlertas, label: 'Leídas', count: alertasLeidas.length, color: 'green' }
  ];

  const obtenerBadgePrioridad = (dias: number) => {
    const color = obtenerColorPrioridad(dias);
    const texto = obtenerTextoPrioridad(dias);
    
    const classes = {
      yellow: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
      orange: 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
      red: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
    };

    return (
      <span className={`px-2 py-1 rounded-full text-xs font-medium ${classes[color]}`}>
        {texto}
      </span>
    );
  };

  const obtenerBadgeEstado = (estado: string) => {
    return estado === 'leida' ? (
      <span className="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400">
        Leída
      </span>
    ) : (
      <span className="px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
        Pendiente
      </span>
    );
  };

  // Funciones de paginación
  const goToPage = (page: number) => setCurrentPage(page);
  const goToPrevious = () => setCurrentPage(prev => Math.max(prev - 1, 1));
  const goToNext = () => setCurrentPage(prev => Math.min(prev + 1, totalPages));

  const getPageNumbers = () => {
    const pages = [];
    const maxVisiblePages = 5;
    
    if (totalPages <= maxVisiblePages) {
      for (let i = 1; i <= totalPages; i++) {
        pages.push(i);
      }
    } else {
      const startPage = Math.max(1, currentPage - 2);
      const endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
      
      for (let i = startPage; i <= endPage; i++) {
        pages.push(i);
      }
    }
    
    return pages;
  };

  if (error) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
        {/* Header igual al Dashboard */}
        <header className="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="flex justify-between items-center h-16">
              <div className="flex items-center gap-3">
                <img 
                  src="/logo.png" 
                  alt="SAG Garage Logo" 
                  className="w-10 h-10 rounded-xl object-cover shadow-lg"
                />
                <div>
                  <h1 className="text-xl font-bold text-gray-900 dark:text-white">SAG Garage</h1>
                  <p className="text-xs text-gray-500 dark:text-gray-400">Sistema de Alertas</p>
                </div>
              </div>

              <div className="flex items-center gap-4">
                <Button
                  onClick={() => navigate('/dashboard')}
                  className="!p-2 bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                  title="Regresar al Dashboard"
                >
                  <ArrowLeft size={20} />
                </Button>

                <div className="hidden sm:flex items-center gap-2 text-sm">
                  <div className="w-8 h-8 bg-sag-100 dark:bg-sag-900/30 rounded-full flex items-center justify-center">
                    <span className="text-sag-600 dark:text-sag-400 font-semibold">
                      {user?.nombre?.charAt(0).toUpperCase() || user?.username?.charAt(0).toUpperCase() || 'U'}
                    </span>
                  </div>
                  <div className="text-right">
                    <p className="font-medium text-gray-900 dark:text-white">{user?.nombre || user?.username}</p>
                    <p className="text-xs text-gray-500 dark:text-gray-400 capitalize">{user?.rol}</p>
                  </div>
                </div>
                
                <NotificacionesDropdown />
                
                <Button
                  onClick={toggleTheme}
                  className="!p-2 bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                  title={`Cambiar a modo ${themeMode === 'light' ? 'oscuro' : 'claro'}`}
                >
                  {themeMode === 'light' ? <Moon size={20} /> : <Sun size={20} />}
                </Button>

                <Button
                  onClick={handleLogout}
                  className="bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 hover:border-red-300 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-900/30"
                >
                  Cerrar Sesión
                </Button>
              </div>
            </div>
          </div>
        </header>

        {/* Error Content */}
        <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 text-center">
            <div className="text-red-500 mb-4">
              <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
              </svg>
            </div>
            <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">Error al cargar alertas</h3>
            <p className="text-gray-600 dark:text-gray-400 mb-4">{error}</p>
            <Button onClick={cargarAlertas}>Reintentar</Button>
          </div>
        </main>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      {/* Header igual al Dashboard */}
      <header className="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 sticky top-0 z-10">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex justify-between items-center h-16">
            <div className="flex items-center gap-3">
              <img 
                src="/logo.png" 
                alt="SAG Garage Logo" 
                className="w-10 h-10 rounded-xl object-cover shadow-lg"
              />
              <div>
                <h1 className="text-xl font-bold text-gray-900 dark:text-white">SAG Garage</h1>
                <p className="text-xs text-gray-500 dark:text-gray-400">Sistema de Alertas</p>
              </div>
            </div>

            <div className="flex items-center gap-4">
              <Button
                onClick={() => navigate('/dashboard')}
                className="!p-2 bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                title="Regresar al Dashboard"
              >
                <ArrowLeft size={20} />
              </Button>

              <div className="hidden sm:flex items-center gap-2 text-sm">
                <div className="w-8 h-8 bg-sag-100 dark:bg-sag-900/30 rounded-full flex items-center justify-center">
                  <span className="text-sag-600 dark:text-sag-400 font-semibold">
                    {user?.nombre?.charAt(0).toUpperCase() || user?.username?.charAt(0).toUpperCase() || 'U'}
                  </span>
                </div>
                <div className="text-right">
                  <p className="font-medium text-gray-900 dark:text-white">{user?.nombre || user?.username}</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400 capitalize">{user?.rol}</p>
                </div>
              </div>
              
              <NotificacionesDropdown />
              
              <Button
                onClick={toggleTheme}
                className="!p-2 bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-600"
                title={`Cambiar a modo ${themeMode === 'light' ? 'oscuro' : 'claro'}`}
              >
                {themeMode === 'light' ? <Moon size={20} /> : <Sun size={20} />}
              </Button>

              <Button
                onClick={handleLogout}
                className="bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 hover:border-red-300 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-900/30"
              >
                Cerrar Sesión
              </Button>
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Estadísticas */}
        <EstadisticasAlertas estadisticas={estadisticas} cargando={cargando} />

        {/* Buscador y Filtros */}
        <div className="bg-white dark:bg-gray-800 rounded-xl p-6 border border-gray-200 dark:border-gray-700 mb-6">
          <div className="flex flex-col sm:flex-row gap-4 mb-4">
            <div className="flex-1">
              <div className="relative">
                <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                </div>
                <input
                  type="text"
                  placeholder="Buscar por cliente, teléfono, vehículo, placas o servicios..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-sag-500 focus:border-transparent dark:bg-gray-700 dark:text-white"
                />
              </div>
            </div>
          </div>

          {/* Filtros de prioridad */}
          <div className="flex flex-wrap gap-2">
            {filtros.map((filtro) => (
              <button
                key={filtro.key}
                onClick={() => setFiltroActivo(filtro.key)}
                className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                  filtroActivo === filtro.key
                    ? 'bg-sag-500 text-white shadow-lg shadow-sag-500/30'
                    : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600'
                }`}
              >
                {filtro.label}
                {filtro.count > 0 && (
                  <span className={`ml-2 px-2 py-0.5 rounded-full text-xs ${
                    filtroActivo === filtro.key
                      ? 'bg-sag-400 text-sag-100'
                      : 'bg-gray-300 text-gray-600 dark:bg-gray-600 dark:text-gray-300'
                  }`}>
                    {filtro.count}
                  </span>
                )}
              </button>
            ))}
          </div>
        </div>

        {/* Tabla de Alertas */}
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          {cargando && alertasFiltradas.length === 0 ? (
            <div className="flex items-center justify-center py-12">
              <div className="text-center space-y-3">
                <div className="animate-spin h-10 w-10 border-4 border-sag-500 border-t-transparent rounded-full mx-auto shadow-lg shadow-sag-500/30"></div>
                <p className="text-gray-600 dark:text-gray-400">Cargando alertas...</p>
              </div>
            </div>
          ) : alertasFiltradas.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-12">
              <svg className="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-5 5v-5zM3 7v10a2 2 0 002 2h10m-6-4h6M9 9h6" />
              </svg>
              <p className="text-gray-600 dark:text-gray-400 text-lg">
                No hay alertas {filtroActivo !== 'todas' && `de tipo "${filtros.find(f => f.key === filtroActivo)?.label}"`}
              </p>
              <p className="text-gray-500 dark:text-gray-500 text-sm">
                {searchTerm ? 'Intenta ajustar los filtros de búsqueda' : 'Las alertas se generan automáticamente según los servicios de mantenimiento'}
              </p>
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full">
                <thead className="bg-gray-50 dark:bg-gray-700 border-b border-gray-200 dark:border-gray-600">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Cliente
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Vehículo
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Último Servicio
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Servicios
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Prioridad
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Estado
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Acciones
                    </th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                  {currentItems.map((alerta) => (
                    <tr
                      key={alerta.id}
                      className={`hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors ${
                        alerta.estado === 'leida' ? 'opacity-60' : ''
                      }`}
                    >
                      <td className="px-6 py-4">
                        <div>
                          <div className="text-sm font-medium text-gray-900 dark:text-white">
                            {alerta.cliente_nombre}
                          </div>
                          {alerta.cliente_telefono && (
                            <div className="text-sm text-gray-500 dark:text-gray-400">
                              📞 {alerta.cliente_telefono}
                            </div>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900 dark:text-white">
                          {alerta.vehiculo_marca} {alerta.vehiculo_modelo}
                        </div>
                        {alerta.vehiculo_placas && (
                          <div className="text-sm text-gray-500 dark:text-gray-400">
                            {alerta.vehiculo_placas}
                          </div>
                        )}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-900 dark:text-white">
                          {formatearFecha(alerta.fecha_ultimo_servicio)}
                        </div>
                        <div className="text-sm text-gray-500 dark:text-gray-400">
                          {obtenerTextoTiempo(alerta.dias_exactos_desde_servicio)}
                        </div>
                      </td>
                      <td className="px-6 py-4">
                        <div className="flex flex-wrap gap-1">
                          {alerta.servicios_que_dispararon.slice(0, 2).map((servicio, index) => (
                            <span 
                              key={index}
                              className="px-2 py-1 bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 text-xs rounded"
                            >
                              {servicio}
                            </span>
                          ))}
                          {alerta.servicios_que_dispararon.length > 2 && (
                            <span className="text-xs text-gray-500 dark:text-gray-400">
                              +{alerta.servicios_que_dispararon.length - 2} más
                            </span>
                          )}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {obtenerBadgePrioridad(alerta.dias_exactos_desde_servicio)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        {obtenerBadgeEstado(alerta.estado)}
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex gap-2">
                          {alerta.estado === 'pendiente' && (
                            <Button
                              variant="outline"
                              onClick={() => handleMarcarLeida(alerta.id)}
                              className="!text-xs !py-1.5 !px-2 text-green-600 border-green-600 hover:bg-green-50 dark:hover:bg-green-900/20"
                            >
                              Marcar leída
                            </Button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {/* Paginación */}
          {!cargando && alertasFiltradas.length > 0 && totalPages > 1 && (
            <div className="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 px-6 py-4">
              <div className="flex flex-col sm:flex-row items-center justify-between gap-4">
                <div className="flex items-center text-sm text-gray-700 dark:text-gray-300">
                  <span>
                    Mostrando {startIndex + 1} - {Math.min(endIndex, totalItems)} de {totalItems} alertas
                  </span>
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
      </main>
    </div>
  );
};