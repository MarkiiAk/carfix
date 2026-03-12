/**
 * Servicio para generación automática de alertas
 * Sistema silencioso que verifica y genera alertas cuando sea necesario
 */

import api from './api';

export interface Alerta {
  id: number;
  orden_id: number;
  cliente_id: number;
  cliente_nombre: string;
  cliente_telefono: string;
  cliente_email: string;
  vehiculo_marca: string;
  vehiculo_modelo: string;
  vehiculo_anio: number;
  vehiculo_placas: string;
  vehiculo_kilometraje: number;
  ultimo_servicio: string;
  fecha_ultimo_servicio: string;
  dias_exactos_desde_servicio: number;
  tipo_alerta: string;
  prioridad: 'media' | 'alta' | 'urgente';
  estado: 'pendiente' | 'leida';
  fecha_creacion: string;
  fecha_marcada_leida?: string;
  mensaje: string;
  servicios_realizados: string;
  servicios_que_dispararon: string[];
  // Propiedades adicionales para compatibilidad con el componente
  marca: string;
  modelo: string;
  placas: string;
}

export interface EstadisticasAlertas {
  total: number;
  pendientes: number;
  leidas: number;
  urgentes: number;
  alta_prioridad: number;
  media_prioridad: number;
  por_tipo: {
    [key: string]: number;
  };
}

interface AlertaAutoResult {
  success: boolean;
  alertas_generadas: number;
  mensaje: string;
  tiempo_ejecucion_ms?: number;
  ejecutado_previamente?: boolean;
  error?: string;
}

class AlertasAutoService {
  private static instance: AlertasAutoService;
  private isProcessing = false;
  private lastExecution: Date | null = null;

  private constructor() {}

  static getInstance(): AlertasAutoService {
    if (!AlertasAutoService.instance) {
      AlertasAutoService.instance = new AlertasAutoService();
    }
    return AlertasAutoService.instance;
  }

  /**
   * Genera alertas automáticamente de forma silenciosa
   * - Verifica si ya se ejecutó hoy
   * - Genera alertas solo si es necesario
   * - Maneja errores de forma silenciosa
   * - No bloquea la interfaz de usuario
   */
  async generarAlertasAutomatico(): Promise<AlertaAutoResult | null> {
    // Evitar ejecuciones múltiples simultáneas
    if (this.isProcessing) {
      return null;
    }

    // Evitar ejecuciones muy frecuentes (máximo una vez por hora)
    if (this.lastExecution && (Date.now() - this.lastExecution.getTime()) < 3600000) {
      return null;
    }

    this.isProcessing = true;
    this.lastExecution = new Date();

    try {
      const response = await api.post('/alertas/generar-automatico');
      
      if (response.data.success) {
        // Log silencioso para depuración
        if (response.data.alertas_generadas > 0) {
          console.log(`[Alertas Auto] Se generaron ${response.data.alertas_generadas} alertas automáticamente`);
        }
        
        return {
          success: true,
          alertas_generadas: response.data.alertas_generadas,
          mensaje: response.data.mensaje,
          tiempo_ejecucion_ms: response.data.tiempo_ejecucion_ms,
          ejecutado_previamente: response.data.ejecutado_previamente
        };
      } else {
        // Error del backend - manejar silenciosamente
        console.warn('[Alertas Auto] Error del backend:', response.data.error);
        return {
          success: false,
          alertas_generadas: 0,
          mensaje: 'Error del servidor',
          error: response.data.error
        };
      }
    } catch (error) {
      // Manejar errores de red, autenticación, etc. de forma silenciosa
      console.warn('[Alertas Auto] Error en generación automática:', error);
      
      return {
        success: false,
        alertas_generadas: 0,
        mensaje: 'Error de conexión',
        error: error instanceof Error ? error.message : 'Error desconocido'
      };
    } finally {
      this.isProcessing = false;
    }
  }

  /**
   * Ejecuta generación automática con reintentos en caso de fallo
   * Usado en puntos críticos como login y carga del dashboard
   */
  async ejecutarConReintentos(maxReintentos: number = 2): Promise<void> {
    let intentos = 0;

    while (intentos < maxReintentos) {
      intentos++;

      try {
        const resultado = await this.generarAlertasAutomatico();
        
        if (resultado?.success) {
          // Éxito - salir del loop
          return;
        }

        // Si no fue exitoso pero tampoco hubo excepción, reintentar
        if (intentos < maxReintentos) {
          await new Promise(resolve => setTimeout(resolve, 1000 * intentos)); // Delay progresivo
        }
      } catch (error) {
        // En el último intento, no reintentar más
        if (intentos >= maxReintentos) {
          console.warn('[Alertas Auto] Falló después de', maxReintentos, 'intentos');
          return;
        }
        
        // Delay antes del siguiente intento
        await new Promise(resolve => setTimeout(resolve, 1000 * intentos));
      }
    }
  }

  /**
   * Limpia el estado del servicio (útil para testing o reset)
   */
  resetState(): void {
    this.isProcessing = false;
    this.lastExecution = null;
  }

  /**
   * Obtiene el estado actual del servicio
   */
  getState() {
    return {
      isProcessing: this.isProcessing,
      lastExecution: this.lastExecution,
      canExecute: !this.isProcessing && (!this.lastExecution || (Date.now() - this.lastExecution.getTime()) >= 3600000)
    };
  }

  /**
   * Obtiene todas las alertas
   */
  async obtenerAlertas() {
    try {
      const response = await api.get('/alertas');
      return {
        success: true,
        alertas: response.data.alertas || []
      };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Error al obtener alertas',
        alertas: []
      };
    }
  }

  /**
   * Obtiene estadísticas de alertas
   */
  async obtenerEstadisticas() {
    try {
      const response = await api.get('/alertas/estadisticas');
      return {
        success: true,
        estadisticas: response.data.estadisticas || null
      };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Error al obtener estadísticas',
        estadisticas: null
      };
    }
  }

  /**
   * Marca una alerta como leída
   */
  async marcarComoLeida(alertaId: number) {
    try {
      const response = await api.put(`/alertas/${alertaId}/marcar-leida`);
      return {
        success: true,
        mensaje: response.data.mensaje || 'Alerta marcada como leída'
      };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Error al marcar alerta como leída'
      };
    }
  }

  /**
   * Genera nuevas alertas manualmente
   */
  async generarAlertas() {
    try {
      const response = await api.post('/alertas/generar');
      return {
        success: true,
        alertas_generadas: response.data.alertas_generadas || 0,
        mensaje: response.data.mensaje || 'Alertas generadas correctamente'
      };
    } catch (error) {
      return {
        success: false,
        error: error instanceof Error ? error.message : 'Error al generar alertas',
        alertas_generadas: 0
      };
    }
  }

  /**
   * Helpers para el frontend
   */
  formatearFecha(fecha: string): string {
    return new Date(fecha).toLocaleDateString('es-MX', {
      year: 'numeric',
      month: 'long',
      day: 'numeric'
    });
  }

  obtenerTextoTiempo(dias: number): string {
    if (dias < 30) {
      return `${dias} ${dias === 1 ? 'día' : 'días'}`;
    } else if (dias < 365) {
      const meses = Math.floor(dias / 30);
      return `${meses} ${meses === 1 ? 'mes' : 'meses'}`;
    } else {
      const anos = Math.floor(dias / 365);
      return `${anos} ${anos === 1 ? 'año' : 'años'}`;
    }
  }

  obtenerColorPrioridad(dias: number): 'yellow' | 'orange' | 'red' {
    if (dias >= 270) return 'red';    // 9+ meses - Urgente
    if (dias >= 210) return 'orange'; // 7+ meses - Alta
    return 'yellow';                  // 6+ meses - Media
  }

  obtenerTextoPrioridad(dias: number): string {
    if (dias >= 270) return 'Urgente';
    if (dias >= 210) return 'Alta';
    return 'Media';
  }
}

export const alertasAutoService = AlertasAutoService.getInstance();