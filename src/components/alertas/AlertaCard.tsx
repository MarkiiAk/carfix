import React from 'react';
import { Alerta } from '../../services/alertasAutoService';
import { Card } from '../ui';
import { Button } from '../ui';

interface AlertaCardProps {
  alerta: Alerta;
  onMarcarLeida: (alertaId: number) => void;
  onVerOrden?: (ordenId: number) => void;
  formatearFecha: (fecha: string) => string;
  obtenerTextoTiempo: (dias: number) => string;
  obtenerColorPrioridad: (dias: number) => 'yellow' | 'orange' | 'red';
  obtenerTextoPrioridad: (dias: number) => string;
}

export const AlertaCard: React.FC<AlertaCardProps> = ({
  alerta,
  onMarcarLeida,
  onVerOrden,
  formatearFecha,
  obtenerTextoTiempo,
  obtenerColorPrioridad,
  obtenerTextoPrioridad
}) => {
  const colorPrioridad = obtenerColorPrioridad(alerta.dias_exactos_desde_servicio);
  const textoPrioridad = obtenerTextoPrioridad(alerta.dias_exactos_desde_servicio);

  const colorClasses = {
    yellow: 'border-yellow-500 bg-yellow-50',
    orange: 'border-orange-500 bg-orange-50',
    red: 'border-red-500 bg-red-50'
  };

  const badgeClasses = {
    yellow: 'bg-yellow-500 text-white',
    orange: 'bg-orange-500 text-white',
    red: 'bg-red-500 text-white'
  };

  return (
    <Card className={`p-4 border-l-4 ${colorClasses[colorPrioridad]} ${
      alerta.estado === 'leida' ? 'opacity-60' : ''
    }`}>
      <div className="flex justify-between items-start mb-3">
        <div className="flex items-center gap-2">
          <span className={`px-2 py-1 rounded text-xs font-semibold ${badgeClasses[colorPrioridad]}`}>
            Prioridad {textoPrioridad}
          </span>
          {alerta.estado === 'leida' && (
            <span className="px-2 py-1 rounded text-xs bg-gray-500 text-white">
              Leída
            </span>
          )}
        </div>
        <span className="text-sm text-gray-500">
          Orden #{alerta.orden_id}
        </span>
      </div>

      {/* Información del Cliente y Vehículo */}
      <div className="mb-3">
        <h3 className="font-semibold text-lg text-gray-800 mb-1">
          {alerta.cliente_nombre}
        </h3>
        <p className="text-gray-600">
          {alerta.marca} {alerta.modelo} {alerta.año}
          {alerta.placas && ` • Placas: ${alerta.placas}`}
        </p>
        {alerta.cliente_telefono && (
          <p className="text-gray-600">📞 {alerta.cliente_telefono}</p>
        )}
      </div>

      {/* Información del Servicio */}
      <div className="mb-4">
        <p className="text-sm text-gray-600 mb-2">
          <strong>Último servicio:</strong> {formatearFecha(alerta.fecha_ultimo_servicio)}
        </p>
        <p className="text-sm text-gray-600 mb-2">
          <strong>Tiempo transcurrido:</strong> {obtenerTextoTiempo(alerta.dias_exactos_desde_servicio)}
        </p>
        
        <div className="mb-2">
          <p className="text-sm text-gray-700 font-medium mb-1">Servicios que requieren seguimiento:</p>
          <div className="flex flex-wrap gap-1">
            {alerta.servicios_que_dispararon.map((servicio, index) => (
              <span 
                key={index}
                className="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded"
              >
                {servicio}
              </span>
            ))}
          </div>
        </div>
      </div>

      {/* Acciones */}
      <div className="flex gap-2 pt-3 border-t">
        {alerta.estado === 'pendiente' && (
          <Button
            variant="outline"
            onClick={() => onMarcarLeida(alerta.id)}
            className="text-green-600 border-green-600 hover:bg-green-50 text-sm px-3 py-1"
          >
            Marcar como leída
          </Button>
        )}
        
        {onVerOrden && (
          <Button
            variant="outline"
            onClick={() => onVerOrden(alerta.orden_id)}
            className="text-blue-600 border-blue-600 hover:bg-blue-50 text-sm px-3 py-1"
          >
            Ver orden completa
          </Button>
        )}
      </div>

      {/* Fecha de generación */}
      <div className="mt-3 pt-2 border-t border-gray-100">
        <p className="text-xs text-gray-400">
          Alerta generada: {formatearFecha(alerta.fecha_generada)}
          {alerta.fecha_marcada_leida && (
            <> • Leída: {formatearFecha(alerta.fecha_marcada_leida)}</>
          )}
        </p>
      </div>
    </Card>
  );
};