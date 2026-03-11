/**
 * Utilidades para verificar autorización de acceso a alertas
 * Solo el usuario admin específico puede acceder a las funcionalidades de alertas
 */

import type { Usuario } from '../types';

/**
 * Verifica si el usuario actual está autorizado para acceder a las alertas
 * @param user - Usuario actual del contexto de autenticación
 * @returns boolean - true si está autorizado, false en caso contrario
 */
export const isAlertasAuthorized = (user: Usuario | null): boolean => {
  if (!user) return false;
  
  // Verificar que sea el usuario específico con rol admin
  return user.username === 'markiiak' && user.rol === 'admin';
};

/**
 * Hook personalizado para verificar autorización de alertas
 */
export const useAlertasAuth = (user: Usuario | null) => {
  return {
    isAuthorized: isAlertasAuthorized(user),
    canViewAlertas: isAlertasAuthorized(user),
    canManageAlertas: isAlertasAuthorized(user)
  };
};