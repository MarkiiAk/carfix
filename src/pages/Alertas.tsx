import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAlertas } from '../hooks/useAlertas';
import { AlertaCard, EstadisticasAlertas } from '../components/alertas';
import { Button, Card } from '../components/ui';
import type { Alerta } from '../services/alertasAutoService';

type FiltroAlertas = 'todas' | 'pendientes' | 'leidas' | 'urgente' | 'alta' | 'media';

export const Alertas: React.FC = () => {
  const navigate = useNavigate();
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

  // Obtener alertas filtradas
  const obtenerAlertasFiltradas = (): Alerta[] => {
    switch (filtroActivo) {
      case 'pendientes':
        return alertasPendientes;
      case 'leidas':
        return alertasLeidas;
      case 'urgente':
        return alertasPorPrioridad.urgente;
      case 'alta':
        return alertasPorPrioridad.alta;
      case 'media':
        return alertasPorPrioridad.media;
      case 'todas':
      default:
        return alertas;
    }
  };

  const alertasFiltradas = obtenerAlertasFiltradas();


  const handleVerOrden = (ordenId: number) => {
    navigate(`/detalle-orden/${ordenId}`);
  };

  // Filtros disponibles
  const filtros = [
    { key: 'todas' as FiltroAlertas, label: 'Todas', count: alertas.length },
    { key: 'pendientes' as FiltroAlertas, label: 'Pendientes', count: alertasPendientes.length },
    { key: 'urgente' as FiltroAlertas, label: 'Urgentes', count: alertasPorPrioridad.urgente.length },
    { key: 'alta' as FiltroAlertas, label: 'Prioridad Alta', count: alertasPorPrioridad.alta.length },
    { key: 'media' as FiltroAlertas, label: 'Prioridad Media', count: alertasPorPrioridad.media.length },
    { key: 'leidas' as FiltroAlertas, label: 'Leídas', count: alertasLeidas.length }
  ];

  if (error) {
    return (
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <Card className="p-6 text-center">
          <div className="text-red-500 mb-4">
            <svg className="w-12 h-12 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
          </div>
          <h3 className="text-lg font-semibold text-gray-900 mb-2">Error al cargar alertas</h3>
          <p className="text-gray-600 mb-4">{error}</p>
          <Button onClick={cargarAlertas}>Reintentar</Button>
        </Card>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Header */}
      <div className="flex justify-between items-center mb-8">
        <div>
          <h1 className="text-3xl font-bold text-gray-900">Sistema de Alertas Automáticas</h1>
          <p className="text-gray-600 mt-2">
            Seguimiento de clientes que requieren servicios de mantenimiento
          </p>
        </div>
        
        <div className="flex gap-3">
          <Button
            variant="outline"
            onClick={cargarAlertas}
            loading={cargando}
            icon={
              <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
              </svg>
            }
          >
            Actualizar
          </Button>
        </div>
      </div>

      {/* Estadísticas */}
      <EstadisticasAlertas estadisticas={estadisticas} cargando={cargando} />

      {/* Filtros */}
      <Card className="p-4 mb-6">
        <div className="flex flex-wrap gap-2">
          {filtros.map((filtro) => (
            <button
              key={filtro.key}
              onClick={() => setFiltroActivo(filtro.key)}
              className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                filtroActivo === filtro.key
                  ? 'bg-blue-500 text-white'
                  : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
              }`}
            >
              {filtro.label}
              {filtro.count > 0 && (
                <span className={`ml-2 px-2 py-0.5 rounded-full text-xs ${
                  filtroActivo === filtro.key
                    ? 'bg-blue-400 text-blue-100'
                    : 'bg-gray-300 text-gray-600'
                }`}>
                  {filtro.count}
                </span>
              )}
            </button>
          ))}
        </div>
      </Card>

      {/* Lista de Alertas */}
      {cargando && alertasFiltradas.length === 0 ? (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <Card key={i} className="p-6">
              <div className="animate-pulse">
                <div className="flex justify-between items-start mb-4">
                  <div className="flex gap-2">
                    <div className="h-6 bg-gray-200 rounded w-24"></div>
                    <div className="h-6 bg-gray-200 rounded w-16"></div>
                  </div>
                  <div className="h-4 bg-gray-200 rounded w-20"></div>
                </div>
                <div className="h-6 bg-gray-200 rounded w-48 mb-2"></div>
                <div className="h-4 bg-gray-200 rounded w-64 mb-4"></div>
                <div className="h-16 bg-gray-200 rounded"></div>
              </div>
            </Card>
          ))}
        </div>
      ) : alertasFiltradas.length === 0 ? (
        <Card className="p-12 text-center">
          <div className="text-gray-400 mb-4">
            <svg className="w-16 h-16 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
          </div>
          <h3 className="text-lg font-semibold text-gray-900 mb-2">
            No hay alertas {filtroActivo !== 'todas' && `de tipo "${filtros.find(f => f.key === filtroActivo)?.label}"`}
          </h3>
          <p className="text-gray-600 mb-4">
            {filtroActivo === 'todas' 
              ? 'Las alertas se generan automáticamente al hacer login y al cargar el dashboard. Si no ves alertas, significa que no hay clientes que requieran servicios de mantenimiento en este momento.'
              : 'Cambia el filtro para ver otras alertas disponibles.'
            }
          </p>
        </Card>
      ) : (
        <div className="space-y-4">
          {alertasFiltradas.map((alerta) => (
            <AlertaCard
              key={alerta.id}
              alerta={alerta}
              onMarcarLeida={marcarComoLeida}
              onVerOrden={handleVerOrden}
              formatearFecha={formatearFecha}
              obtenerTextoTiempo={obtenerTextoTiempo}
              obtenerColorPrioridad={obtenerColorPrioridad}
              obtenerTextoPrioridad={obtenerTextoPrioridad}
            />
          ))}
        </div>
      )}

      {/* Información adicional */}
      {alertasFiltradas.length > 0 && (
        <Card className="mt-8 p-6 bg-blue-50 border-blue-200">
          <div className="flex items-start">
            <div className="p-2 bg-blue-500 rounded-lg mr-4">
              <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <h3 className="font-semibold text-blue-900 mb-2">Información sobre las Alertas</h3>
              <div className="text-sm text-blue-800 space-y-1">
                <p>• <strong>Alertas de 6 meses:</strong> Se generan automáticamente para servicios de Full Service (con/sin bujías), Cambio de Aceite y Verificación.</p>
                <p>• <strong>Prioridades:</strong> Media (6-7 meses), Alta (7-9 meses), Urgente (9+ meses).</p>
                <p>• <strong>Contacto:</strong> Usa la información de contacto para llamar o enviar recordatorios a los clientes.</p>
              </div>
            </div>
          </div>
        </Card>
      )}
    </div>
  );
};