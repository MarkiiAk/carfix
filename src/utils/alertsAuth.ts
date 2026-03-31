/**
 * Utilidades para verificar autorización de acceso a alertas
 * CUALQUIER usuario logueado puede acceder - SIN RESTRICCIONES
 */

import type { Usuario } from '../types';

/**
 * Verifica si el usuario actual está autorizado para acceder a las alertas
 * @param user - Usuario actual del contexto de autenticación
 * @returns boolean - true si está autorizado, false en caso contrario
 */
export const isAlertasAuthorized = (user: Usuario | null): boolean => {
  // CUALQUIER usuario logueado puede ver las alertas - SIN RESTRICCIONES
  return !!user;
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