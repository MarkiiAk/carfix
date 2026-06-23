import { useState, useEffect, useRef } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faBell, faClock, faUser, faCar } from '@fortawesome/free-solid-svg-icons';
import { useNavigate } from 'react-router-dom';
import { isAlertasAuthorized } from '../utils/alertsAuth';
import { useAuth } from '../contexts/AuthContext';
import type { Alerta } from '../services/alertasAutoService';

interface NotificacionesDropdownProps {
  alertas: Alerta[];
  loading: boolean;
  onRefresh: () => Promise<void>;
}

export const NotificacionesDropdown = ({ alertas, loading, onRefresh }: NotificacionesDropdownProps) => {
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const { user, isLoading: authLoading } = useAuth();
  const navigate = useNavigate();

  // No mostrar mientras se carga la autenticación
  if (authLoading) {
    return null;
  }

  // Solo mostrar si el usuario está autorizado
  if (!isAlertasAuthorized(user)) {
    return null;
  }

  // Cerrar dropdown al hacer click fuera
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const getPrioridadColor = (diasDesdeServicio: number) => {
    if (diasDesdeServicio >= 240) return 'text-red-600 dark:text-red-400'; // Urgente
    if (diasDesdeServicio >= 210) return 'text-orange-600 dark:text-orange-400'; // Alta
    return 'text-yellow-600 dark:text-yellow-400'; // Normal
  };

  const getPrioridadLabel = (diasDesdeServicio: number) => {
    if (diasDesdeServicio >= 240) return 'Urgente';
    if (diasDesdeServicio >= 210) return 'Alta Prioridad';
    return 'Normal';
  };

  const formatearTiempo = (fechaServicio: string) => {
    const fecha = new Date(fechaServicio);
    const ahora = new Date();
    const diffMs = ahora.getTime() - fecha.getTime();
    const diffDias = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    if (diffDias < 1) return 'Hoy';
    if (diffDias === 1) return 'Ayer';
    if (diffDias < 30) return `${diffDias} días`;
    if (diffDias < 365) return `${Math.floor(diffDias / 30)} meses`;
    return `${Math.floor(diffDias / 365)} años`;
  };

  // Mostrar máximo 5 alertas en el dropdown
  const alertasRecientes = alertas.slice(0, 5);
  const totalPendientes = alertas.length;

  // Si está cargando, mostrar campanita con spinner
  if (loading) {
    return (
      <div className="relative">
        <button
          className="relative p-2 text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
          title="Cargando alertas..."
          disabled
        >
          <FontAwesomeIcon icon={faBell} style={{ width: 20, height: 20 }} />
          <div className="absolute -top-1 -right-1 bg-gray-400 rounded-full h-5 w-5 flex items-center justify-center">
            <div className="animate-spin h-3 w-3 border-2 border-white border-t-transparent rounded-full"></div>
          </div>
        </button>
      </div>
    );
  }

  return (
    <div className="relative" ref={dropdownRef}>
      {/* Botón de campana */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="relative p-2 text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700"
        title={`${totalPendientes} alertas pendientes`}
      >
        <FontAwesomeIcon icon={faBell} style={{ width: 20, height: 20 }} />
        {totalPendientes > 0 && (
          <span className="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-medium">
            {totalPendientes > 9 ? '9+' : totalPendientes}
          </span>
        )}
      </button>

      {/* Dropdown */}
      {isOpen && (
        <div className="absolute right-0 mt-2 w-96 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-50 max-h-[500px] flex flex-col">
          {/* Header */}
          <div className="px-4 py-3 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
            <div className="flex items-center justify-between">
              <div>
                <h3 className="font-semibold text-gray-900 dark:text-white">
                  Alertas de Servicio
                </h3>
                <p className="text-sm text-gray-500 dark:text-gray-400">
                  {totalPendientes} {totalPendientes === 1 ? 'alerta pendiente' : 'alertas pendientes'}
                </p>
              </div>
              <button
                onClick={onRefresh}
                className="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-md hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                title="Actualizar alertas"
              >
                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
              </button>
            </div>
          </div>

          {/* Lista de alertas */}
          <div className="flex-1 overflow-y-auto max-h-80">
            {alertasRecientes.length === 0 ? (
              <div className="px-4 py-6 text-center">
                <FontAwesomeIcon icon={faBell} className="mx-auto h-12 w-12 text-gray-400 mb-3" />
                <p className="text-gray-500 dark:text-gray-400">No hay alertas pendientes</p>
              </div>
            ) : (
              alertasRecientes.map((alerta) => (
                <div
                  key={alerta.id}
                  className="px-4 py-3 border-b border-gray-100 dark:border-gray-700 last:border-b-0 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors"
                  onClick={() => {
                    setIsOpen(false);
                    navigate('/alertas');
                  }}
                >
                  <div className="flex items-start gap-3">
                    {/* Icono de prioridad */}
                    <div className={`p-2 rounded-full bg-opacity-20 mt-1 ${
                      alerta.dias_exactos_desde_servicio >= 240 
                        ? 'bg-red-100 dark:bg-red-900/30' 
                        : alerta.dias_exactos_desde_servicio >= 210
                        ? 'bg-orange-100 dark:bg-orange-900/30'
                        : 'bg-yellow-100 dark:bg-yellow-900/30'
                    }`}>
                      <FontAwesomeIcon icon={faClock} className={`w-4 h-4 ${getPrioridadColor(alerta.dias_exactos_desde_servicio)}`} />
                    </div>

                    <div className="flex-1 min-w-0">
                      {/* Cliente y vehículo */}
                      <div className="flex items-center gap-2 mb-1">
                        <FontAwesomeIcon icon={faUser} className="w-3 h-3 text-gray-400" />
                        <span className="font-medium text-gray-900 dark:text-white text-sm truncate">
                          {alerta.cliente_nombre}
                        </span>
                      </div>
                      
                      <div className="flex items-center gap-2 mb-2">
                        <FontAwesomeIcon icon={faCar} className="w-3 h-3 text-gray-400" />
                        <span className="text-sm text-gray-600 dark:text-gray-300 truncate">
                          {alerta.marca} {alerta.modelo} • {alerta.placas}
                        </span>
                      </div>

                      {/* Servicios que dispararon la alerta */}
                      <p className="text-xs text-gray-500 dark:text-gray-400 mb-2">
                        {alerta.servicios_que_dispararon.join(', ')}
                      </p>

                      {/* Prioridad y tiempo */}
                      <div className="flex items-center justify-between">
                        <span className={`text-xs font-medium px-2 py-1 rounded-full ${
                          alerta.dias_exactos_desde_servicio >= 240
                            ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                            : alerta.dias_exactos_desde_servicio >= 210
                            ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300'
                            : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300'
                        }`}>
                          {getPrioridadLabel(alerta.dias_exactos_desde_servicio)}
                        </span>
                        <span className="text-xs text-gray-400">
                          {formatearTiempo(alerta.fecha_ultimo_servicio)}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              ))
            )}
          </div>

          {/* Footer con botón "Ver todas" - Siempre visible */}
          <div className="px-4 py-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <button
              onClick={() => {
                setIsOpen(false);
                navigate('/alertas');
              }}
              className="w-full text-center text-sm font-medium text-sag-600 hover:text-sag-700 dark:text-sag-400 dark:hover:text-sag-300 py-2 hover:bg-sag-50 dark:hover:bg-sag-900/20 rounded-md transition-colors"
            >
              Ver todas las alertas {totalPendientes > 0 && `(${totalPendientes})`}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};