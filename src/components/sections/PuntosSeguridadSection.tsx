import { useState, useEffect } from 'react';
import { Card } from '../ui';
import { estadosSeguridadAPI, puntosSeguridadAPI } from '../../services/api';
import type { EstadoSeguridad, PuntoSeguridadCatalogo, PuntoSeguridadOrden } from '../../types';

interface PuntosSeguridadSectionProps {
  puntosSeguridad: PuntoSeguridadOrden[];
  onChange: (puntos: PuntoSeguridadOrden[]) => void;
  disabled?: boolean;
}

export function PuntosSeguridadSection({ 
  puntosSeguridad, 
  onChange, 
  disabled = false 
}: PuntosSeguridadSectionProps) {
  const [estados, setEstados] = useState<EstadoSeguridad[]>([]);
  const [catalogo, setCatalogo] = useState<PuntoSeguridadCatalogo[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      setError(null);
      const [estadosData, catalogoData] = await Promise.all([
        estadosSeguridadAPI.getAll(),
        puntosSeguridadAPI.getCatalogo()
      ]);
      
      setEstados(estadosData.filter(e => e.activo).sort((a, b) => a.orden - b.orden));
      setCatalogo(catalogoData.filter(c => c.activo).sort((a, b) => a.orden - b.orden));
      
      // Si no hay puntos inicializados, crear array vacío para cada punto del catálogo
      if (puntosSeguridad.length === 0) {
        const puntosIniciales: PuntoSeguridadOrden[] = catalogoData
          .filter(c => c.activo)
          .map(punto => ({
            id: 0,
            ordenId: 0,
            puntoId: punto.id,
            estadoId: estadosData.find(e => e.nombre === 'Bueno')?.id || 1,
            observaciones: '',
            fechaRevision: new Date().toISOString(),
            punto
          }));
        onChange(puntosIniciales);
      }
    } catch (err) {
      console.error('Error cargando datos de seguridad:', err);
      setError('Error al cargar los datos de seguridad');
    } finally {
      setLoading(false);
    }
  };

  const handleEstadoChange = (puntoId: number, nuevoEstadoId: number) => {
    const puntosActualizados = puntosSeguridad.map(p => 
      p.puntoId === puntoId ? { ...p, estadoId: nuevoEstadoId } : p
    );
    onChange(puntosActualizados);
  };

  const handleObservacionChange = (puntoId: number, observaciones: string) => {
    const puntosActualizados = puntosSeguridad.map(p => 
      p.puntoId === puntoId ? { ...p, observaciones } : p
    );
    onChange(puntosActualizados);
  };

  if (loading) {
    return (
      <Card>
        <div className="flex items-center justify-center p-8">
          <div className="text-gray-500">Cargando puntos de seguridad...</div>
        </div>
      </Card>
    );
  }

  if (error) {
    return (
      <Card>
        <div className="flex items-center justify-center p-8">
          <div className="text-red-500">{error}</div>
        </div>
      </Card>
    );
  }

  // Agrupar por categoría
  const categorias = catalogo.reduce((acc, punto) => {
    if (!acc[punto.categoria]) {
      acc[punto.categoria] = [];
    }
    acc[punto.categoria].push(punto);
    return acc;
  }, {} as Record<string, PuntoSeguridadCatalogo[]>);

  return (
    <Card className="space-y-6">
      <div className="border-b border-gray-200 pb-4">
        <h2 className="text-2xl font-bold text-gray-900">Inspección de Seguridad</h2>
        <p className="mt-1 text-sm text-gray-600">
          Revisa cada punto de seguridad y registra su estado actual
        </p>
      </div>

      {Object.entries(categorias).map(([categoria, puntos]) => (
        <div key={categoria} className="space-y-4">
          <h3 className="text-lg font-semibold text-gray-900 border-l-4 border-red-600 pl-3">
            {categoria}
          </h3>
          
          <div className="space-y-3">
            {puntos.map(punto => {
              const puntoEstado = puntosSeguridad.find(p => p.puntoId === punto.id);
              const estadoActual = estados.find(e => e.id === puntoEstado?.estadoId);
              
              return (
                <div 
                  key={punto.id} 
                  className="bg-gray-50 rounded-lg p-4 space-y-3 hover:bg-gray-100 transition-colors"
                >
                  {/* Header del punto */}
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex-1 min-w-0">
                      <h4 className="font-medium text-gray-900 truncate">
                        {punto.nombre}
                      </h4>
                      {punto.descripcion && (
                        <p className="text-sm text-gray-600 mt-1">{punto.descripcion}</p>
                      )}
                      {punto.ubicacion && (
                        <p className="text-xs text-gray-500 mt-1">
                          📍 {punto.ubicacion}
                        </p>
                      )}
                    </div>
                  </div>

                  {/* Selector de estado - Grid responsivo optimizado para táctil */}
                  <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                    {estados.map(estado => {
                      const isSelected = puntoEstado?.estadoId === estado.id;
                      return (
                        <button
                          key={estado.id}
                          type="button"
                          disabled={disabled}
                          onClick={() => handleEstadoChange(punto.id, estado.id)}
                          className={`
                            relative flex items-center justify-center gap-2 p-3 rounded-lg
                            border-2 transition-all duration-200 font-medium text-sm
                            min-h-[56px] touch-manipulation
                            ${isSelected 
                              ? `border-${estado.color}-500 bg-${estado.color}-50 shadow-md` 
                              : 'border-gray-300 bg-white hover:border-gray-400 hover:shadow-sm'
                            }
                            ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer active:scale-95'}
                          `}
                          style={{
                            borderColor: isSelected ? estado.color : undefined,
                            backgroundColor: isSelected ? `${estado.color}15` : undefined,
                          }}
                        >
                          <span className="text-lg">{estado.icono}</span>
                          <span className={isSelected ? 'font-bold' : ''}>
                            {estado.nombre}
                          </span>
                          {isSelected && (
                            <span className="absolute -top-1 -right-1 w-5 h-5 bg-green-500 rounded-full flex items-center justify-center">
                              <span className="text-white text-xs">✓</span>
                            </span>
                          )}
                        </button>
                      );
                    })}
                  </div>

                  {/* Campo de observaciones */}
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      Observaciones {estadoActual?.nombre !== 'Bueno' && (
                        <span className="text-red-500">*</span>
                      )}
                    </label>
                    <textarea
                      disabled={disabled}
                      value={puntoEstado?.observaciones || ''}
                      onChange={(e) => handleObservacionChange(punto.id, e.target.value)}
                      placeholder={
                        estadoActual?.nombre !== 'Bueno'
                          ? 'Describe el problema encontrado...'
                          : 'Observaciones adicionales (opcional)'
                      }
                      rows={2}
                      className={`
                        w-full px-3 py-2 border rounded-lg text-sm resize-none
                        focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent
                        ${disabled ? 'bg-gray-100 cursor-not-allowed' : 'bg-white'}
                        ${estadoActual?.nombre !== 'Bueno' && !puntoEstado?.observaciones 
                          ? 'border-red-300' 
                          : 'border-gray-300'
                        }
                      `}
                    />
                    {estadoActual?.nombre !== 'Bueno' && !puntoEstado?.observaciones && (
                      <p className="text-xs text-red-500 mt-1">
                        Las observaciones son requeridas para estados diferentes a "Bueno"
                      </p>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      ))}

      {/* Resumen al final */}
      <div className="bg-blue-50 rounded-lg p-4 mt-6">
        <h4 className="font-semibold text-blue-900 mb-2">📊 Resumen de Inspección</h4>
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
          {estados.map(estado => {
            const count = puntosSeguridad.filter(p => p.estadoId === estado.id).length;
            return (
              <div 
                key={estado.id} 
                className="bg-white rounded-lg p-3 text-center shadow-sm"
                style={{ borderTop: `3px solid ${estado.color}` }}
              >
                <div className="text-2xl mb-1">{estado.icono}</div>
                <div className="text-lg font-bold text-gray-900">{count}</div>
                <div className="text-xs text-gray-600">{estado.nombre}</div>
              </div>
            );
          })}
        </div>
      </div>
    </Card>
  );
}