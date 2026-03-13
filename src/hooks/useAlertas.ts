import { useState, useEffect } from 'react';
import { alertasAutoService, Alerta, EstadisticasAlertas } from '../services/alertasAutoService';
import { useToastContext } from '../contexts/ToastContext';

export const useAlertas = () => {
  const [alertas, setAlertas] = useState<Alerta[]>([]);
  const [estadisticas, setEstadisticas] = useState<EstadisticasAlertas | null>(null);
  const [cargando, setCargando] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const { showSuccess, showError } = useToastContext();

  // Cargar alertas
  const cargarAlertas = async () => {
    setCargando(true);
    setError(null);
    
    try {
      const response = await alertasAutoService.obtenerAlertas();
      
      if (response.success && response.alertas) {
        setAlertas(response.alertas);
      } else {
        setError(response.error || 'Error al cargar alertas');
        showError('Error al cargar las alertas');
      }
    } catch (err) {
      const errorMsg = 'Error inesperado al cargar alertas';
      setError(errorMsg);
      showError(errorMsg);
    } finally {
      setCargando(false);
    }
  };

  // Cargar estadísticas
  const cargarEstadisticas = async () => {
    try {
      const response = await alertasAutoService.obtenerEstadisticas();
      
      if (response.success && response.estadisticas) {
        setEstadisticas(response.estadisticas);
      }
    } catch (err) {
      console.error('Error cargando estadísticas:', err);
    }
  };

  // Marcar alerta como leída
  const marcarComoLeida = async (alertaId: number) => {
    try {
      const response = await alertasAutoService.marcarComoLeida(alertaId);
      
      if (response.success) {
        // Actualizar la alerta en el estado local
        setAlertas(prev => 
          prev.map(alerta => 
            alerta.id === alertaId 
              ? { ...alerta, estado: 'leida' as const, fecha_marcada_leida: new Date().toISOString() }
              : alerta
          )
        );
        
        // Actualizar estadísticas
        if (estadisticas) {
          setEstadisticas(prev => prev ? {
            ...prev,
            pendientes: prev.pendientes - 1,
            leidas: prev.leidas + 1
          } : null);
        }
        
        showSuccess('Alerta marcada como leída');
      } else {
        showError(response.error || 'Error al marcar alerta');
      }
    } catch (err) {
      showError('Error inesperado al marcar alerta');
    }
  };

  // Generar nuevas alertas
  const generarAlertas = async () => {
    setCargando(true);
    
    try {
      const response = await alertasAutoService.generarAlertas();
      
      if (response.success) {
        showSuccess(
          response.mensaje || `Se generaron ${response.alertas_generadas} nuevas alertas`
        );
        
        // Recargar alertas y estadísticas
        await cargarAlertas();
        await cargarEstadisticas();
      } else {
        showError(response.error || 'Error al generar alertas');
      }
    } catch (err) {
      showError('Error inesperado al generar alertas');
    } finally {
      setCargando(false);
    }
  };

  // Filtros
  const alertasPendientes = alertas.filter(alerta => alerta.estado === 'pendiente');
  const alertasLeidas = alertas.filter(alerta => alerta.estado === 'leida');

  // Alertas por prioridad
  const alertasPorPrioridad = {
    urgente: alertasPendientes.filter(alerta => 
      alertasAutoService.obtenerColorPrioridad(alerta.dias_exactos_desde_servicio) === 'red'
    ),
    alta: alertasPendientes.filter(alerta => 
      alertasAutoService.obtenerColorPrioridad(alerta.dias_exactos_desde_servicio) === 'orange'
    ),
    media: alertasPendientes.filter(alerta => 
      alertasAutoService.obtenerColorPrioridad(alerta.dias_exactos_desde_servicio) === 'yellow'
    )
  };

  // Cargar datos inicialmente
  useEffect(() => {
    const cargarDatosIniciales = async () => {
      try {
        // Cargar alertas y estadísticas en paralelo
        await Promise.all([
          cargarAlertas(),
          cargarEstadisticas()
        ]);
      } catch (err) {
        console.error('Error cargando datos iniciales:', err);
      }
    };
    
    cargarDatosIniciales();
  }, []);

  return {
    // Estado
    alertas,
    alertasPendientes,
    alertasLeidas,
    alertasPorPrioridad,
    estadisticas,
    cargando,
    error,
    
    // Acciones
    cargarAlertas,
    cargarEstadisticas,
    marcarComoLeida,
    generarAlertas,
    
    // Helpers
    formatearFecha: alertasAutoService.formatearFecha,
    obtenerTextoTiempo: alertasAutoService.obtenerTextoTiempo,
    obtenerColorPrioridad: alertasAutoService.obtenerColorPrioridad,
    obtenerTextoPrioridad: alertasAutoService.obtenerTextoPrioridad
  };
};