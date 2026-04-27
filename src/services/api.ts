import axios from 'axios';
import type { Orden, EstadoSeguridad, PuntoSeguridadCatalogo, PuntoSeguridadOrden, ClienteListItem, ClientePerfil } from '../types';

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

  buscarPorTelefono: async (tel: string) => {
    const response = await api.get<{ success: boolean; matches: any[] }>(
      '/clientes/buscar-por-telefono',
      { params: { tel } }
    );
    return response.data;
  },
};

export default api;
