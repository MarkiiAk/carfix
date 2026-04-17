import React, { useState, useEffect } from 'react';
import { 
  Clock, 
  MessageCircle, 
  Calendar, 
  CheckCircle, 
  XCircle, 
  AlertCircle, 
  Send,
  Phone,
  User,
  DollarSign,
  RefreshCw,
  TrendingUp,
  Activity
} from 'lucide-react';
import './AlertasWhatsApp.css';

interface Alerta {
  id: number;
  orden_id: number;
  cliente_nombre: string;
  cliente_telefono: string;
  cliente_email: string;
  marca: string;
  modelo: string;
  anio: number;
  placas: string;
  fecha_ultimo_servicio: string;
  servicios_que_dispararon: string[];
  estado: string;
  estado_whatsapp: string;
  fecha_envio_whatsapp: string;
  respuesta_inicial: string;
  fecha_cita_seleccionada: string;
  hora_cita_seleccionada: string;
  confirmacion_sag: string;
  requiere_atencion: boolean;
  prioridad: string;
  ultima_actividad: string;
  dias_exactos_desde_servicio: number;
  estado_visual_whatsapp: string;
}

const AlertasWhatsAppPanel: React.FC = () => {
  const [alertas, setAlertas] = useState<Alerta[]>([]);
  const [estadisticas, setEstadisticas] = useState<any>(null);
  const [loading, setLoading] = useState(true);
  const [filtroEstado, setFiltroEstado] = useState<string>('todos');

  useEffect(() => {
    cargarAlertas();
    cargarEstadisticas();
  }, []);

  const cargarAlertas = async () => {
    try {
      setLoading(true);
      
      // Usar el servicio de alertas existente
      const token = localStorage.getItem('auth_token');
      const response = await fetch('/backend-php/api/alertas.php', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        }
      });
      
      const data = await response.json();
      if (data.success && data.alertas) {
        setAlertas(data.alertas);
      } else {
        console.error('Error en respuesta:', data);
      }
    } catch (error) {
      console.error('Error al cargar alertas:', error);
    } finally {
      setLoading(false);
    }
  };

  const cargarEstadisticas = async () => {
    try {
      const token = localStorage.getItem('auth_token');
      const response = await fetch('/backend-php/api/alertas.php?action=estadisticas', {
        method: 'GET',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json',
        }
      });
      
      const data = await response.json();
      if (data.success) {
        setEstadisticas(data.estadisticas);
      }
    } catch (error) {
      console.error('Error al cargar estadísticas:', error);
    }
  };

  const obtenerIcono = (estado: string) => {
    switch (estado) {
      case 'enviado':
      case 'esperando_respuesta':
        return <Send className="h-4 w-4" />;
      case 'esperando_fecha':
        return <Calendar className="h-4 w-4" />;
      case 'pre_agendado':
        return <Clock className="h-4 w-4" />;
      case 'confirmado':
        return <CheckCircle className="h-4 w-4" />;
      case 'rechazado':
        return <XCircle className="h-4 w-4" />;
      case 'requiere_contacto':
        return <AlertCircle className="h-4 w-4" />;
      default:
        return <MessageCircle className="h-4 w-4" />;
    }
  };

  const obtenerColorEstado = (estado: string) => {
    switch (estado) {
      case 'enviado':
      case 'esperando_respuesta':
        return 'bg-blue-100 text-blue-800 border-blue-200';
      case 'esperando_fecha':
        return 'bg-yellow-100 text-yellow-800 border-yellow-200';
      case 'pre_agendado':
        return 'bg-orange-100 text-orange-800 border-orange-200';
      case 'confirmado':
        return 'bg-green-100 text-green-800 border-green-200';
      case 'rechazado':
        return 'bg-red-100 text-red-800 border-red-200';
      case 'requiere_contacto':
        return 'bg-purple-100 text-purple-800 border-purple-200';
      default:
        return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const alertasFiltradas = alertas.filter(alerta => {
    if (filtroEstado === 'todos') return true;
    return alerta.estado_whatsapp === filtroEstado;
  });

  const formatearFecha = (fecha: string) => {
    return new Date(fecha).toLocaleDateString('es-MX', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const formatearMoneda = (cantidad: number) => {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN'
    }).format(cantidad);
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <RefreshCw className="h-8 w-8 animate-spin text-blue-500" />
        <span className="ml-2">Cargando alertas...</span>
      </div>
    );
  }

  const obtenerEstadisticasResumen = () => {
    const resumen = {
      total: alertas.length,
      enviados: 0,
      activos: 0,
      completados: 0,
      perdidos: 0
    };

    alertas.forEach(alerta => {
      const estado = alerta.estado_whatsapp;
      switch (estado) {
        case 'enviado':
        case 'esperando_respuesta':
          resumen.enviados++;
          break;
        case 'esperando_fecha':
        case 'pre_agendado':
          resumen.activos++;
          break;
        case 'confirmado':
          resumen.completados++;
          break;
        case 'rechazado':
        case 'requiere_contacto':
          resumen.perdidos++;
          break;
      }
    });

    return resumen;
  };

  const estadisticasResumen = obtenerEstadisticasResumen();

  return (
    <div className="alertas-whatsapp-panel space-y-6">
      {/* Header con estadísticas */}
      <div className="bg-white rounded-lg shadow-md p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-2xl font-bold text-gray-900">
            Panel de Alertas - Tracking WhatsApp
          </h2>
          <button
            onClick={() => {
              cargarAlertas();
              cargarEstadisticas();
            }}
            className="flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            <RefreshCw className="h-4 w-4 mr-2" />
            Actualizar
          </button>
        </div>

        {/* Estadísticas resumidas */}
        <div className="estadisticas-grid mb-6">
          <div className="estadistica-card">
            <div className="flex items-center justify-center mb-2">
              <Activity className="h-6 w-6 text-blue-500" />
            </div>
            <div className="text-2xl font-bold text-gray-900">{estadisticasResumen.total}</div>
            <div className="text-sm text-gray-600">Total Alertas</div>
          </div>
          
          <div className="estadistica-card">
            <div className="flex items-center justify-center mb-2">
              <Send className="h-6 w-6 text-blue-500" />
            </div>
            <div className="text-2xl font-bold text-blue-600">{estadisticasResumen.enviados}</div>
            <div className="text-sm text-gray-600">Mensajes Enviados</div>
          </div>
          
          <div className="estadistica-card">
            <div className="flex items-center justify-center mb-2">
              <MessageCircle className="h-6 w-6 text-green-500" />
            </div>
            <div className="text-2xl font-bold text-green-600">{estadisticasResumen.activos}</div>
            <div className="text-sm text-gray-600">Conversaciones Activas</div>
          </div>
          
          <div className="estadistica-card">
            <div className="flex items-center justify-center mb-2">
              <CheckCircle className="h-6 w-6 text-green-500" />
            </div>
            <div className="text-2xl font-bold text-green-600">{estadisticasResumen.completados}</div>
            <div className="text-sm text-gray-600">Citas Confirmadas</div>
          </div>
          
          <div className="estadistica-card">
            <div className="flex items-center justify-center mb-2">
              <XCircle className="h-6 w-6 text-red-500" />
            </div>
            <div className="text-2xl font-bold text-red-600">{estadisticasResumen.perdidos}</div>
            <div className="text-sm text-gray-600">No Contactados</div>
          </div>
        </div>
        
        {/* Filtros */}
        <div className="filtros-container flex flex-wrap gap-2 mb-4">
          <button
            onClick={() => setFiltroEstado('todos')}
            className={`px-3 py-1 rounded-full text-sm font-medium transition-colors ${
              filtroEstado === 'todos' 
                ? 'bg-blue-600 text-white' 
                : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
            }`}
          >
            Todos ({alertas.length})
          </button>
          
          {['enviado', 'esperando_respuesta', 'esperando_fecha', 'pre_agendado', 'confirmado', 'rechazado', 'requiere_contacto'].map(estado => {
            const cantidad = alertas.filter(a => a.estado_whatsapp === estado).length;
            if (cantidad === 0) return null;
            
            const etiquetas: any = {
              'enviado': 'Enviado',
              'esperando_respuesta': 'Esperando Respuesta',
              'esperando_fecha': 'Esperando Fecha',
              'pre_agendado': 'Pre-agendado',
              'confirmado': 'Confirmado',
              'rechazado': 'Rechazado',
              'requiere_contacto': 'Requiere Contacto'
            };
            
            return (
              <button
                key={estado}
                onClick={() => setFiltroEstado(estado)}
                className={`px-3 py-1 rounded-full text-sm font-medium transition-colors ${
                  filtroEstado === estado 
                    ? 'bg-blue-600 text-white' 
                    : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                }`}
              >
                {etiquetas[estado]} ({cantidad})
              </button>
            );
          })}
        </div>
      </div>

      {/* Lista de alertas */}
      <div className="space-y-4">
        {alertasFiltradas.length === 0 ? (
          <div className="bg-white rounded-lg shadow-md p-8 text-center">
            <MessageCircle className="h-12 w-12 text-gray-400 mx-auto mb-4" />
            <p className="text-gray-500">No hay alertas que coincidan con el filtro seleccionado.</p>
          </div>
        ) : (
          alertasFiltradas.map((alerta) => (
            <div key={alerta.id} className={`alerta-card ${alerta.estado_whatsapp || ''} bg-white rounded-lg shadow-md p-6`}>
              <div className="flex items-start justify-between">
                <div className="flex-1">
                  {/* Información del cliente */}
                  <div className="flex items-center space-x-4 mb-3">
                    <div className="flex items-center">
                      <User className="h-4 w-4 text-gray-500 mr-2" />
                      <span className="font-medium text-gray-900">{alerta.cliente_nombre}</span>
                    </div>
                    <div className="flex items-center">
                      <Phone className="h-4 w-4 text-gray-500 mr-2" />
                      <span className="text-gray-600">{alerta.cliente_telefono}</span>
                    </div>
                    <div className="flex items-center">
                      <span className="text-sm text-gray-600">{alerta.marca} {alerta.modelo} {alerta.anio}</span>
                    </div>
                  </div>
                  
                  {/* Información del servicio */}
                  <div className="mb-3">
                    <p className="text-gray-800">
                      <strong>Servicios:</strong> {alerta.servicios_que_dispararon.join(', ')}
                    </p>
                    <p className="text-sm text-gray-500">
                      Último servicio: {formatearFecha(alerta.fecha_ultimo_servicio)} 
                      ({alerta.dias_exactos_desde_servicio} días)
                    </p>
                    {alerta.fecha_envio_whatsapp && (
                      <p className="text-sm text-gray-500">
                        WhatsApp enviado: {formatearFecha(alerta.fecha_envio_whatsapp)}
                      </p>
                    )}
                  </div>
                </div>

                {/* Estado de WhatsApp */}
                <div className="ml-6">
                  <div className={`estado-badge flex items-center px-3 py-2 rounded-lg border ${obtenerColorEstado(alerta.estado_whatsapp)} estado-${alerta.estado_whatsapp}`}>
                    <span className="mr-2">
                      {obtenerIcono(alerta.estado_whatsapp)}
                    </span>
                    <div>
                      <div className="font-medium text-sm">
                        {alerta.estado_visual_whatsapp || alerta.estado_whatsapp || 'Sin estado'}
                      </div>
                      
                      {/* Detalles adicionales */}
                      {(alerta.fecha_cita_seleccionada || alerta.respuesta_inicial) && (
                        <div className="text-xs mt-1 space-y-1">
                          {alerta.respuesta_inicial && (
                            <div>Respuesta: {alerta.respuesta_inicial}</div>
                          )}
                          {alerta.fecha_cita_seleccionada && (
                            <div>
                              Cita: {formatearFecha(alerta.fecha_cita_seleccionada)} 
                              {alerta.hora_cita_seleccionada && ` ${alerta.hora_cita_seleccionada}`}
                            </div>
                          )}
                          {alerta.confirmacion_sag && (
                            <div>SAG: {alerta.confirmacion_sag}</div>
                          )}
                        </div>
                      )}
                    </div>
                  </div>
                  
                  {/* Indicador de atención requerida */}
                  {alerta.requiere_atencion && (
                    <div className="mt-2">
                      <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        <AlertCircle className="w-3 h-3 mr-1" />
                        Requiere Atención
                      </span>
                    </div>
                  )}
                </div>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
};

export default AlertasWhatsAppPanel;