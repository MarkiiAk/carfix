import api from './api';

export interface Alerta {
  id: number;
  orden_id: number;
  fecha_ultimo_servicio: string;
  servicios_que_dispararon: string[];
  todos_los_servicios: string[];
  estado: 'pendiente' | 'leida';
  fecha_generada: string;
  fecha_marcada_leida?: string;
  dias_desde_servicio: number;
  dias_exactos_desde_servicio: number;
  
  // Información del cliente
  cliente_nombre: string;
  cliente_telefono: string;
  cliente_email: string;
  
  // Información del vehículo
  marca: string;
  modelo: string;
  año: number;
  placas: string;
}

export interface EstadisticasAlertas {
  total: number;
  pendientes: number;
  leidas: number;
  promedio_dias: number;
}

export interface AlertasResponse {
  success: boolean;
  alertas?: Alerta[];
  error?: string;
}

export interface EstadisticasResponse {
  success: boolean;
  estadisticas?: EstadisticasAlertas;
  error?: string;
}

export interface GenerarAlertasResponse {
  success: boolean;
  alertas_generadas?: number;
  mensaje?: string;
  error?: string;
}

export interface MarcarLeidaResponse {
  success: boolean;
  message?: string;
  error?: string;
}

class AlertasAutoService {
  /**
   * Obtener todas las alertas
   */
  async obtenerAlertas(): Promise<AlertasResponse> {
    try {
      const response = await api.get('/alertas');
      return response.data;
    } catch (error) {
      console.error('Error obteniendo alertas:', error);
      return {
        success: false,
        error: 'Error al cargar las alertas'
      };
    }
  }

  /**
   * Marcar una alerta como leída
   */
  async marcarComoLeida(alertaId: number): Promise<MarcarLeidaResponse> {
    try {
      const response = await api.put(`/alertas/${alertaId}/marcar-leida`);
      return response.data;
    } catch (error) {
      console.error('Error marcando alerta como leída:', error);
      return {
        success: false,
        error: 'Error al marcar la alerta como leída'
      };
    }
  }

  /**
   * Generar nuevas alertas automáticamente
   */
  async generarAlertas(): Promise<GenerarAlertasResponse> {
    try {
      const response = await api.get('/alertas/generar');
      return response.data;
    } catch (error) {
      console.error('Error generando alertas:', error);
      return {
        success: false,
        error: 'Error al generar nuevas alertas'
      };
    }
  }

  /**
   * Obtener estadísticas de alertas
   */
  async obtenerEstadisticas(): Promise<EstadisticasResponse> {
    try {
      const response = await api.get('/alertas/estadisticas');
      return response.data;
    } catch (error) {
      console.error('Error obteniendo estadísticas:', error);
      return {
        success: false,
        error: 'Error al cargar las estadísticas'
      };
    }
  }

  /**
   * Formatear fecha para mostrar
   */
  formatearFecha(fecha: string): string {
    return new Date(fecha).toLocaleDateString('es-MX', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  }

  /**
   * Obtener texto descriptivo de días desde servicio
   */
  obtenerTextoTiempo(dias: number): string {
    if (dias < 30) {
      return `${dias} días`;
    } else if (dias < 365) {
      const meses = Math.floor(dias / 30);
      return `${meses} ${meses === 1 ? 'mes' : 'meses'}`;
    } else {
      const años = Math.floor(dias / 365);
      const meses = Math.floor((dias % 365) / 30);
      if (meses === 0) {
        return `${años} ${años === 1 ? 'año' : 'años'}`;
      }
      return `${años} ${años === 1 ? 'año' : 'años'} y ${meses} ${meses === 1 ? 'mes' : 'meses'}`;
    }
  }

  /**
   * Obtener color de prioridad basado en días desde servicio
   */
  obtenerColorPrioridad(dias: number): 'yellow' | 'orange' | 'red' {
    if (dias < 210) { // 7 meses
      return 'yellow';
    } else if (dias < 270) { // 9 meses
      return 'orange';
    } else {
      return 'red'; // 9+ meses
    }
  }

  /**
   * Obtener texto de prioridad
   */
  obtenerTextoPrioridad(dias: number): string {
    if (dias < 210) {
      return 'Media';
    } else if (dias < 270) {
      return 'Alta';
    } else {
      return 'Urgente';
    }
  }
}

export const alertasAutoService = new AlertasAutoService();