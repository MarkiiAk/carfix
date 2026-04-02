import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { GarageLoader } from './ui/GarageLoader';

interface AlertasGuardProps {
  children: React.ReactNode;
}

/**
 * Componente guard que protege el acceso a las páginas de alertas
 * Permite el acceso a CUALQUIER usuario logueado - SIN RESTRICCIONES
 */
export const AlertasGuard: React.FC<AlertasGuardProps> = ({ children }) => {
  const { user, isLoading } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    // Si ya cargó y no está logueado, redirigir al login
    if (!isLoading && !user) {
      navigate('/login', { replace: true });
    }
  }, [user, isLoading, navigate]);

  // Mostrar loader mientras se verifica la autenticación
  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex items-center justify-center">
        <GarageLoader />
      </div>
    );
  }

  // Si no está logueado, no mostrar nada (se redirigirá)
  if (!user) {
    return null;
  }

  // Si está logueado, mostrar el contenido (TODOS pueden ver alertas)
  return <>{children}</>;
};
