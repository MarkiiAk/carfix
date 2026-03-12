import React from 'react';
import { EstadisticasAlertas as EstadisticasType } from '../../services/alertasAutoService';
import { Card } from '../ui';

interface EstadisticasAlertasProps {
  estadisticas: EstadisticasType | null;
  cargando?: boolean;
}

export const EstadisticasAlertas: React.FC<EstadisticasAlertasProps> = ({
  estadisticas,
  cargando = false
}) => {
  if (cargando) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        {[1, 2, 3, 4].map((i) => (
          <Card key={i} className="p-4">
            <div className="animate-pulse">
              <div className="h-4 bg-gray-200 rounded mb-2"></div>
              <div className="h-6 bg-gray-200 rounded"></div>
            </div>
          </Card>
        ))}
      </div>
    );
  }

  if (!estadisticas) {
    return null;
  }

  const porcentajePendientes = estadisticas.total > 0 
    ? Math.round((estadisticas.pendientes / estadisticas.total) * 100) 
    : 0;

  return (
    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
      {/* Total de Alertas */}
      <Card className="p-4 bg-blue-50 border-blue-200">
        <div className="flex items-center">
          <div className="p-2 bg-blue-500 rounded-lg mr-3">
            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-5 5v-5zM12 17h-7a2 2 0 01-2-2V5a2 2 0 012-2h7m0 14V5m0 12l5-5M12 5l5 5" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-medium text-blue-700">Total Alertas</p>
            <p className="text-2xl font-bold text-blue-900">{estadisticas.total}</p>
          </div>
        </div>
      </Card>

      {/* Alertas Pendientes */}
      <Card className="p-4 bg-red-50 border-red-200">
        <div className="flex items-center">
          <div className="p-2 bg-red-500 rounded-lg mr-3">
            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-medium text-red-700">Pendientes</p>
            <p className="text-2xl font-bold text-red-900">{estadisticas.pendientes}</p>
            <p className="text-xs text-red-600">{porcentajePendientes}% del total</p>
          </div>
        </div>
      </Card>

      {/* Alertas Leídas */}
      <Card className="p-4 bg-green-50 border-green-200">
        <div className="flex items-center">
          <div className="p-2 bg-green-500 rounded-lg mr-3">
            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-medium text-green-700">Leídas</p>
            <p className="text-2xl font-bold text-green-900">{estadisticas.leidas}</p>
            <p className="text-xs text-green-600">{100 - porcentajePendientes}% del total</p>
          </div>
        </div>
      </Card>

      {/* Alertas Urgentes */}
      <Card className="p-4 bg-yellow-50 border-yellow-200">
        <div className="flex items-center">
          <div className="p-2 bg-yellow-500 rounded-lg mr-3">
            <svg className="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
            </svg>
          </div>
          <div>
            <p className="text-sm font-medium text-yellow-700">Urgentes</p>
            <p className="text-2xl font-bold text-yellow-900">{estadisticas.urgentes}</p>
            <p className="text-xs text-yellow-600">requieren atención</p>
          </div>
        </div>
      </Card>
    </div>
  );
};