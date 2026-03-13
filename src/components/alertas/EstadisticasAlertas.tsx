import React from 'react';
import { Card } from '../ui';

interface EstadisticasAlertasProps {
  estadisticas: {
    total: number;
    pendientes: number;
    leidas: number;
    urgentes: number;
    alta_prioridad: number;
    media_prioridad: number;
    por_tipo: {
      [key: string]: number;
    };
  } | null;
  cargando: boolean;
}

export const EstadisticasAlertas: React.FC<EstadisticasAlertasProps> = ({
  estadisticas,
  cargando
}) => {
  if (cargando || !estadisticas) {
    return (
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        {[1, 2, 3, 4].map((i) => (
          <Card key={i} className="p-6 border border-gray-200 dark:border-gray-700">
            <div className="animate-pulse">
              <div className="flex items-center justify-between">
                <div>
                  <div className="h-4 bg-gray-200 dark:bg-gray-600 rounded w-16 mb-2"></div>
                  <div className="h-8 bg-gray-200 dark:bg-gray-600 rounded w-12"></div>
                </div>
                <div className="w-12 h-12 bg-gray-200 dark:bg-gray-600 rounded-xl"></div>
              </div>
            </div>
          </Card>
        ))}
      </div>
    );
  }


  const stats = [
    {
      label: 'Total Alertas',
      value: estadisticas.total,
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-5 5v-5zM3 7v10a2 2 0 002 2h10m-6-4h6M9 9h6" />
        </svg>
      ),
      color: 'sag',
      bgColor: 'bg-sag-100 dark:bg-sag-900/30',
      textColor: 'text-sag-600 dark:text-sag-400',
      valueColor: 'text-gray-900 dark:text-white'
    },
    {
      label: 'Pendientes',
      value: estadisticas.pendientes,
      subtitle: 'Requieren atención',
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
        </svg>
      ),
      color: 'red',
      bgColor: 'bg-red-100 dark:bg-red-900/30',
      textColor: 'text-red-600 dark:text-red-400',
      valueColor: 'text-red-600 dark:text-red-400'
    },
    {
      label: 'Leídas',
      value: estadisticas.leidas,
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
      color: 'green',
      bgColor: 'bg-green-100 dark:bg-green-900/30',
      textColor: 'text-green-600 dark:text-green-400',
      valueColor: 'text-green-600 dark:text-green-400'
    },
    {
      label: 'Urgentes',
      value: estadisticas.urgentes,
      icon: (
        <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      ),
      color: 'orange',
      bgColor: 'bg-orange-100 dark:bg-orange-900/30',
      textColor: 'text-orange-600 dark:text-orange-400',
      valueColor: 'text-orange-600 dark:text-orange-400'
    }
  ];

  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      {stats.map((stat, index) => (
        <Card key={index} className="p-6 border border-gray-200 dark:border-gray-700">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-600 dark:text-gray-400">{stat.label}</p>
              <p className={`text-3xl font-bold mt-1 ${stat.valueColor}`}>{stat.value}</p>
              {stat.subtitle && (
                <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                  {stat.subtitle}
                </p>
              )}
            </div>
            <div className={`w-12 h-12 ${stat.bgColor} rounded-xl flex items-center justify-center`}>
              <div className={stat.textColor}>
                {stat.icon}
              </div>
            </div>
          </div>
        </Card>
      ))}
    </div>
  );
};