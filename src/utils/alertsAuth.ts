/**
 * Utilidades para verificar autorización de acceso a alertas
 * Cualquier usuario admin puede acceder a las funcionalidades de alertas
 */

import type { Usuario } from '../types';

/**
 * Verifica si el usuario actual está autorizado para acceder a las alertas
 * @param user - Usuario actual del contexto de autenticación
 * @returns boolean - true si está autorizado, false en caso contrario
 */
export const isAlertasAuthorized = (user: Usuario | null): boolean => {
  if (!user) return false;
  
  // CUALQUIER usuario con rol admin puede acceder a alertas
  const userRole = user.rol || '';
  
  // Verificar que tenga rol de admin
  return userRole === 'admin';
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