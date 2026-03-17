/**
 * Utilidades para verificar autorización de acceso a alertas
 * Cualquier usuario autenticado puede acceder a las funcionalidades de alertas
 */

import type { Usuario } from '../types';

/**
 * Verifica si el usuario actual está autorizado para acceder a las alertas
 * @param user - Usuario actual del contexto de autenticación
 * @returns boolean - true si está autenticado, false en caso contrario
 */
export const isAlertasAuthorized = (user: Usuario | null): boolean => {
  // Cualquier usuario autenticado puede ver las alertas
  return user !== null;
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