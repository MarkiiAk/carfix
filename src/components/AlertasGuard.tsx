import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../contexts/AuthContext';
import { isAlertasAuthorized } from '../utils/alertsAuth';
import { GarageLoader } from './ui/GarageLoader';

interface AlertasGuardProps {
  children: React.ReactNode;
}

/**
 * Componente guard que protege el acceso a las páginas de alertas
 * Solo permite el acceso al usuario admin específico (markiiak)
 */
export const AlertasGuard: React.FC<AlertasGuardProps> = ({ children }) => {
  const { user, isLoading } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    // Si ya cargó y no está autorizado, redirigir
    if (!isLoading && !isAlertasAuthorized(user)) {
      console.log('🚫 Acceso denegado a alertas - Redirigiendo al dashboard');
      navigate('/dashboard', { replace: true });
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

  // Si no está autorizado, no mostrar nada (se redirigirá)
  if (!isAlertasAuthorized(user)) {
    return null;
  }

  // Si está autorizado, mostrar el contenido
  return <>{children}</>;
};