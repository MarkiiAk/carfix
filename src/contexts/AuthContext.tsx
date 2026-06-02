import { createContext, useContext, useState, useEffect, useCallback, ReactNode } from 'react';
import { authAPI } from '../services/api';
import type { AuthContextType, Usuario, Sucursal } from '../types';

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth debe usarse dentro de un AuthProvider');
  }
  return context;
};

interface AuthProviderProps {
  children: ReactNode;
}

/** Parsea el payload del JWT sin verificar firma (solo para campos de UI). */
function parseJwtPayload(token: string): Record<string, unknown> | null {
  try {
    const base64Payload = token.split('.')[1];
    return JSON.parse(atob(base64Payload.replace(/-/g, '+').replace(/_/g, '/')));
  } catch {
    return null;
  }
}

/**
 * Construye la lista de objetos Sucursal a partir del token JWT y
 * (opcionalmente) del catálogo cargado del backend.
 * Mientras el catálogo no esté disponible, usa IDs con nombre provisional.
 */
function buildSucursalesPermitidas(
  ids: number[],
  catalogo: Sucursal[],
): Sucursal[] {
  return ids.map((id) => {
    const found = catalogo.find((s) => s.id === id);
    return found ?? { id, nombre: `Sucursal ${id}` };
  });
}

export const AuthProvider = ({ children }: AuthProviderProps) => {
  const [user, setUser] = useState<Usuario | null>(null);
  const [token, setToken] = useState<string | null>(localStorage.getItem('token'));
  const [isLoading, setIsLoading] = useState(true);
  const [sucursalActiva, setSucursalActiva] = useState<Sucursal | null>(null);
  const [sucursalesPermitidas, setSucursalesPermitidas] = useState<Sucursal[]>([]);

  // ---------------------------------------------------------------------------
  // Cargar catálogo de sucursales (solo para sistemas/superusuario)
  // ---------------------------------------------------------------------------
  const cargarCatalogoSucursales = useCallback(async (rol: string, ids: number[]): Promise<Sucursal[]> => {
    if (!['sistemas', 'superusuario'].includes(rol)) return [];
    try {
      const res = await fetch(
        `${import.meta.env.VITE_API_URL || ''}/admin/sucursales`,
        { headers: { Authorization: `Bearer ${localStorage.getItem('token') ?? ''}` } },
      );
      if (!res.ok) return [];
      const json = await res.json();
      const catalogo: Sucursal[] = json.sucursales ?? [];
      return buildSucursalesPermitidas(ids, catalogo);
    } catch {
      return buildSucursalesPermitidas(ids, []);
    }
  }, []);

  // ---------------------------------------------------------------------------
  // Inicializar sucursales a partir del token almacenado
  // ---------------------------------------------------------------------------
  const initSucursalesFromToken = useCallback(async (rawToken: string, usuarioRol: string) => {
    const payload = parseJwtPayload(rawToken);
    if (!payload) return;

    const activaId   = Number(payload['sucursal_activa_id'] ?? 1);
    const permitidas = (payload['sucursales_permitidas'] as number[] | undefined) ?? [1];
    const nombre     = (payload['sucursal_nombre'] as string | undefined) ?? `Sucursal ${activaId}`;

    setSucursalActiva({ id: activaId, nombre });

    const lista = await cargarCatalogoSucursales(usuarioRol, permitidas);
    if (lista.length) {
      setSucursalesPermitidas(lista);
      const activa = lista.find(s => s.id === activaId);
      if (activa) setSucursalActiva(activa);
    } else {
      setSucursalesPermitidas([{ id: activaId, nombre }]);
    }
  }, [cargarCatalogoSucursales]);

  // ---------------------------------------------------------------------------
  // Verificar token al montar
  // ---------------------------------------------------------------------------
  useEffect(() => {
    let isActive = true;

    const verifyToken = async () => {
      const storedToken = localStorage.getItem('token');

      if (storedToken && isActive) {
        try {
          const data = await authAPI.verify();
          if (isActive) {
            setUser(data.user);
            setToken(storedToken);
            await initSucursalesFromToken(storedToken, data.user?.rol ?? '');
          }
        } catch {
          if (isActive) {
            localStorage.removeItem('token');
            setToken(null);
            setUser(null);
            setSucursalActiva(null);
            setSucursalesPermitidas([]);
          }
        }
      }

      if (isActive) setIsLoading(false);
    };

    verifyToken();
    return () => { isActive = false; };
  }, [initSucursalesFromToken]);

  // ---------------------------------------------------------------------------
  // login
  // ---------------------------------------------------------------------------
  const login = async (username: string, password: string) => {
    const data = await authAPI.login(username, password);
    localStorage.setItem('token', data.token);
    setToken(data.token);
    setUser(data.user);
    await initSucursalesFromToken(data.token, data.user?.rol ?? '');
  };

  // ---------------------------------------------------------------------------
  // logout
  // ---------------------------------------------------------------------------
  const logout = async () => {
    try {
      await authAPI.logout();
    } catch {
      // silencioso
    } finally {
      localStorage.removeItem('token');
      setToken(null);
      setUser(null);
      setSucursalActiva(null);
      setSucursalesPermitidas([]);
    }
  };

  // ---------------------------------------------------------------------------
  // switchSucursal
  // ---------------------------------------------------------------------------
  const switchSucursal = async (sucursalId: number) => {
    const res = await fetch(
      `${import.meta.env.VITE_API_URL || ''}/backend-php/auth/switch-sucursal`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${localStorage.getItem('token') ?? ''}`,
        },
        body: JSON.stringify({ sucursal_id: sucursalId }),
      },
    );
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error((err as { error?: string }).error ?? 'Error al cambiar sucursal');
    }
    const json = await res.json() as { token: string; sucursal: Sucursal };
    localStorage.setItem('token', json.token);
    setToken(json.token);
    setSucursalActiva(json.sucursal);
    // Actualizar el campo en el objeto usuario en memoria
    setUser((prev) => prev ? { ...prev, sucursal_activa_id: sucursalId, sucursal_nombre: json.sucursal.nombre } : prev);
  };

  const value: AuthContextType = {
    user,
    token,
    login,
    logout,
    isAuthenticated: !!token && !!user,
    isLoading,
    sucursalActiva,
    sucursalesPermitidas,
    switchSucursal,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
