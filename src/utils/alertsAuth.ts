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
  
  // Usuarios autorizados para acceder a alertas
  const authorizedUsers = ['markiiak', 'temporaldemo'];
  
  // Verificar que sea un usuario autorizado con rol admin
  return authorizedUsers.includes(user.username || '') && user.rol === 'admin';
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