/**
 * Utilidades para verificar autorización de acceso a alertas
 * Solo usuarios específicos pueden acceder a las funcionalidades de alertas
 */

import type { Usuario } from '../types';

/**
 * Verifica si el usuario actual está autorizado para acceder a las alertas
 * @param user - Usuario actual del contexto de autenticación
 * @returns boolean - true si está autorizado, false en caso contrario
 */
export const isAlertasAuthorized = (user: Usuario | null): boolean => {
  if (!user) return false;
  
  // Usuarios específicos autorizados para acceder a alertas
  const authorizedUsers = ['markiiak', 'temporaldemo'];
  
  // Solo verificar que sea un usuario autorizado (SIN verificación de rol)
  return authorizedUsers.includes(user.username || '');
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