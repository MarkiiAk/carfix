import axios from 'axios';
import type { Orden, EstadoSeguridad, PuntoSeguridadCatalogo, PuntoSeguridadOrden, ClienteListItem, ClientePerfil, ResumenFinancieroResponse, GastoOrden, GastoAdmin, GastosAdminResponse, OrdenesFinancieroResponse, EmpleadoSueldo, PagoFijo, MovimientoCajaChica, CajaChicaResponse } from '../types';

const API_URL = import.meta.env.VITE_API_URL || 'http://localhost:3001/api';

const api = axios.create({
  baseURL: API_URL,
  headers: {
    'Content-Type': 'application/json',
  },
});

// Interceptor para agregar el token a todas las peticiones
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => Promise.reject(error)
);

// Interceptor para manejar errores de autenticación
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      // Solo redirigir a login si NO estamos ya en la página de login
      const currentPath = window.location.pathname;
      if (!currentPath.includes('/login')) {
        localStorage.removeItem('token');
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);

export const authAPI = {
  login: async (username: string, password: string) => {
    try {
      const response = await api.post('/auth/login', { username, password });
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  verify: async () => {
    const response = await api.get('/auth/verify');
    return response.data;
  },

  logout: async () => {
    const response = await api.post('/auth/logout');
    localStorage.removeItem('token');
    return response.data;
  },
};

export const ordenesAPI = {
  getAll: async () => {
    try {
      const response = await api.get<Orden[]>('/ordenes');
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  getById: async (id: string) => {
    try {
      const response = await api.get<Orden>(`/ordenes/${id}`);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  search: async (filters: {
    folio?: string;
    cliente?: string;
    placa?: string;
    estado?: string;
    fechaDesde?: string;
    fechaHasta?: string;
  }) => {
    const response = await api.get<Orden[]>('/ordenes/search', { params: filters });
    return response.data;
  },

  create: async (orden: Omit<Orden, 'id' | 'folio' | 'estado' | 'fechaCreacion' | 'fechaActualizacion'>) => {
    try {
      const response = await api.post<Orden>('/ordenes', orden);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  update: async (id: string, orden: Partial<Orden>) => {
    try {
      const response = await api.put<Orden>(`/ordenes/${id}`, orden);
      return response.data;
    } catch (error) {
      throw error;
    }
  },

  updateEstado: async (id: string, estado: string) => {
    const response = await api.patch<Orden>(`/ordenes/${id}/estado`, { estado });
    return response.data;
  },

  delete: async (id: string) => {
    const response = await api.delete(`/ordenes/${id}`);
    return response.data;
  },

  getStats: async () => {
    const response = await api.get('/ordenes/stats');
    return response.data;
  },
};

export const estadosSeguridadAPI = {
  getAll: async () => {
    const response = await api.get<EstadoSeguridad[]>('/estados-seguridad');
    return response.data;
  },

  getById: async (id: number) => {
    const response = await api.get<EstadoSeguridad>(`/estados-seguridad/${id}`);
    return response.data;
  },

  create: async (estado: Omit<EstadoSeguridad, 'id'>) => {
    const response = await api.post<EstadoSeguridad>('/admin/estados-seguridad', estado);
    return response.data;
  },

  update: async (id: number, estado: Partial<EstadoSeguridad>) => {
    const response = await api.put<EstadoSeguridad>(`/admin/estados-seguridad/${id}`, estado);
    return response.data;
  },

  delete: async (id: number) => {
    const response = await api.delete(`/admin/estados-seguridad/${id}`);
    return response.data;
  },
};

export const puntosSeguridadAPI = {
  getCatalogo: async () => {
    const response = await api.get<PuntoSeguridadCatalogo[]>('/puntos-seguridad/catalogo');
    return response.data;
  },

  getPuntoById: async (id: number) => {
    const response = await api.get<PuntoSeguridadCatalogo>(`/puntos-seguridad/catalogo/${id}`);
    return response.data;
  },

  getPuntosByOrden: async (ordenId: string) => {
    const response = await api.get<PuntoSeguridadOrden[]>(`/ordenes/${ordenId}/puntos-seguridad`);
    return response.data;
  },

  savePuntosByOrden: async (ordenId: string, puntos: Omit<PuntoSeguridadOrden, 'id' | 'fechaRevision'>[]) => {
    const response = await api.post<{ success: boolean; message: string }>(`/ordenes/${ordenId}/puntos-seguridad`, { puntos });
    return response.data;
  },

  createPunto: async (punto: Omit<PuntoSeguridadCatalogo, 'id'>) => {
    const response = await api.post<PuntoSeguridadCatalogo>('/admin/puntos-seguridad/catalogo', punto);
    return response.data;
  },

  updatePunto: async (id: number, punto: Partial<PuntoSeguridadCatalogo>) => {
    const response = await api.put<PuntoSeguridadCatalogo>(`/admin/puntos-seguridad/catalogo/${id}`, punto);
    return response.data;
  },

  deletePunto: async (id: number) => {
    const response = await api.delete(`/admin/puntos-seguridad/catalogo/${id}`);
    return response.data;
  },
};

export const clientesAPI = {
  listar: async (q?: string) => {
    const params = q ? { q } : {};
    const response = await api.get<{ success: boolean; clientes: ClienteListItem[]; total: number }>(
      '/clientes',
      { params }
    );
    return response.data;
  },

  perfil: async (id: number) => {
    const response = await api.get<ClientePerfil & { success: boolean }>(`/clientes/${id}`);
    return response.data;
  },

  buscar: async (q: string) => {
    const response = await api.get<{ success: boolean; matches: any[] }>(
      '/clientes/buscar',
      { params: { q } }
    );
    return response.data;
  },

  buscarPorTelefono: async (tel: string) => {
    const response = await api.get<{ success: boolean; matches: any[] }>(
      '/clientes/buscar-por-telefono',
      { params: { tel } }
    );
    return response.data;
  },

  updateCliente: async (id: number, data: { nombre: string; telefono?: string; email?: string }) => {
    const response = await api.put<{ success: boolean; cliente: { id: number; nombre: string; telefono: string | null; email: string | null } }>(
      `/clientes/${id}`,
      data
    );
    return response.data;
  },
};

export const financieroAPI = {
  resumen: async (tipo: 'semana' | 'quincena' | 'mes', offset = 0): Promise<ResumenFinancieroResponse> => {
    const response = await api.get<ResumenFinancieroResponse>('/financiero', { params: { tipo, offset } });
    return response.data;
  },
  ordenes: async (tipo: 'mes' | 'semana', offset = 0): Promise<OrdenesFinancieroResponse> => {
    const response = await api.get<OrdenesFinancieroResponse>('/financiero/ordenes', { params: { tipo, offset } });
    return response.data;
  },
};

export const gastosOrdenAPI = {
  listar: async (ordenId: number): Promise<{ success: boolean; gastos: GastoOrden[]; total: number }> => {
    const response = await api.get(`/financiero/gastos-orden`, { params: { orden_id: ordenId } });
    return response.data;
  },

  crear: async (
    ordenId: number,
    concepto: string,
    monto: number,
    tipo: GastoOrden['tipo']
  ): Promise<{ success: boolean; gasto: GastoOrden }> => {
    const response = await api.post(`/financiero/gastos-orden`, {
      orden_id: ordenId,
      concepto,
      monto,
      tipo,
    });
    return response.data;
  },

  eliminar: async (id: number): Promise<{ success: boolean }> => {
    const response = await api.delete(`/financiero/gastos-orden/${id}`);
    return response.data;
  },
};

export const gastosAdminAPI = {
  listar: (mes: number, anio: number): Promise<GastosAdminResponse> =>
    api.get(`/financiero/gastos-admin?mes=${mes}&anio=${anio}`).then(r => r.data),

  crear: (
    mes: number,
    anio: number,
    concepto: string,
    monto: number,
    categoria: GastoAdmin['categoria']
  ): Promise<{ success: boolean; gasto: GastoAdmin }> =>
    api.post('/financiero/gastos-admin', { mes, anio, concepto, monto, categoria }).then(r => r.data),

  eliminar: (id: number): Promise<{ success: boolean }> =>
    api.delete(`/financiero/gastos-admin/${id}`).then(r => r.data),
};

export const empleadosFinancieroAPI = {
  listar: (): Promise<{ success: boolean; empleados: EmpleadoSueldo[] }> =>
    api.get('/financiero/empleados').then(r => r.data),

  crear: (data: Omit<EmpleadoSueldo, 'id' | 'activo'>): Promise<{ success: boolean; empleado: EmpleadoSueldo }> =>
    api.post('/financiero/empleados', data).then(r => r.data),

  actualizar: (id: number, data: Partial<Omit<EmpleadoSueldo, 'id' | 'activo'>>): Promise<{ success: boolean; empleado: EmpleadoSueldo }> =>
    api.put(`/financiero/empleados/${id}`, data).then(r => r.data),

  toggle: (id: number): Promise<{ success: boolean; activo: boolean }> =>
    api.put(`/financiero/empleados/${id}/toggle`, {}).then(r => r.data),
};

export const pagosFijosAPI = {
  listar: (): Promise<{ success: boolean; pagos_fijos: PagoFijo[] }> =>
    api.get('/financiero/pagos-fijos').then(r => r.data),

  crear: (data: Omit<PagoFijo, 'id' | 'activo'>): Promise<{ success: boolean; pago_fijo: PagoFijo }> =>
    api.post('/financiero/pagos-fijos', data).then(r => r.data),

  actualizar: (id: number, data: Partial<Omit<PagoFijo, 'id' | 'activo'>>): Promise<{ success: boolean; pago_fijo: PagoFijo }> =>
    api.put(`/financiero/pagos-fijos/${id}`, data).then(r => r.data),

  toggle: (id: number): Promise<{ success: boolean; activo: boolean }> =>
    api.put(`/financiero/pagos-fijos/${id}/toggle`, {}).then(r => r.data),
};

export const cajaChicaAPI = {
  resumen: (tipo: 'semana' | 'mes', offset: number): Promise<CajaChicaResponse> =>
    api.get(`/financiero/caja-chica?tipo=${tipo}&offset=${offset}`).then(r => r.data),

  crear: (data: Omit<MovimientoCajaChica, 'id'>): Promise<{ success: boolean; movimiento: MovimientoCajaChica }> =>
    api.post('/financiero/caja-chica', data).then(r => r.data),

  eliminar: (id: number): Promise<{ success: boolean }> =>
    api.delete(`/financiero/caja-chica/${id}`).then(r => r.data),
};

export default api;
