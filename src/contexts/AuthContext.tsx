import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { authAPI } from '../services/api';
import type { AuthContextType, Usuario } from '../types';


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

export const AuthProvider = ({ children }: AuthProviderProps) => {
  const [user, setUser] = useState<Usuario | null>(null);
  const [token, setToken] = useState<string | null>(localStorage.getItem('token'));
  const [isLoading, setIsLoading] = useState(true);

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
          }
        } catch (error) {
          if (isActive) {
            localStorage.removeItem('token');
            setToken(null);
            setUser(null);
          }
        }
      }
      
      if (isActive) {
        setIsLoading(false);
      }
    };

    verifyToken();
    
    return () => {
      isActive = false;
    };
  }, []);

  const login = async (username: string, password: string) => {
    try {
      const data = await authAPI.login(username, password);
      localStorage.setItem('token', data.token);
      setToken(data.token);
      setUser(data.user);
    } catch (error) {
      throw error;
    }
  };

  const logout = async () => {
    try {
      await authAPI.logout();
    } catch (error) {
    } finally {
      localStorage.removeItem('token');
      setToken(null);
      setUser(null);
    }
  };

  const value: AuthContextType = {
    user,
    token,
    login,
    logout,
    isAuthenticated: !!token && !!user,
    isLoading,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};
