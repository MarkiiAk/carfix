import { useLocation, useNavigate } from 'react-router-dom';
import { useEffect, useState } from 'react';
import { alertasAutoService } from '../services/alertasAutoService';
import { isAlertasAuthorized } from '../utils/alertsAuth';
import { useAuth } from '../contexts/AuthContext';
import { usePresupuestoStore } from '../store/usePresupuestoStore';
import type { Alerta } from '../services/alertasAutoService';
import type { Sucursal } from '../types';

// --- Iconos SVG inline para no depender de paquetes adicionales ---

const IconOrdenes = () => (
  <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
  </svg>
);

const IconClientes = () => (
  <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
      d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
  </svg>
);

const IconFinanciero = () => (
  <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
  </svg>
);

const IconBell = () => (
  <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
  </svg>
);

const IconSwitch = () => (
  <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
      d="M8 7h12m0 0l-4-4m4 4l-4 4M4 17h12m0 0l-4-4m4 4l-4 4" />
  </svg>
);

const IconSistemas = () => (
  <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
      d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
  </svg>
);

// ---------------------------------------------------------------------------
// BranchSwitcher — dropdown de sucursales para sistemas/superusuario
// ---------------------------------------------------------------------------
interface BranchSwitcherProps {
  sucursalActiva: Sucursal | null;
  sucursalesPermitidas: Sucursal[];
  onSwitch: (id: number) => void;
}

const BranchSwitcher = ({ sucursalActiva, sucursalesPermitidas, onSwitch }: BranchSwitcherProps) => {
  const [open, setOpen] = useState(false);

  if (sucursalesPermitidas.length <= 1) return null;

  return (
    <div className="relative mx-3 mb-3">
      <button
        onClick={() => setOpen((p) => !p)}
        className="w-full flex items-center justify-between gap-2 px-3 py-2 rounded-lg bg-white/5 border border-gray-700 hover:bg-white/10 transition-colors text-xs text-gray-300"
      >
        <span className="flex items-center gap-2 truncate">
          <IconSwitch />
          <span className="truncate font-medium">{sucursalActiva?.nombre ?? 'Sucursal'}</span>
        </span>
        <svg
          className={`w-3 h-3 transition-transform flex-shrink-0 ${open ? 'rotate-180' : ''}`}
          fill="none" stroke="currentColor" viewBox="0 0 24 24"
        >
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
        </svg>
      </button>

      {open && (
        <div className="absolute left-0 right-0 top-full mt-1 z-50 rounded-lg border border-gray-700 bg-gray-900 shadow-xl overflow-hidden">
          {sucursalesPermitidas.map((s) => (
            <button
              key={s.id}
              onClick={() => { onSwitch(s.id); setOpen(false); }}
              className={`w-full text-left px-3 py-2 text-xs transition-colors
                ${sucursalActiva?.id === s.id
                  ? 'bg-sag-500/20 text-sag-400 font-semibold'
                  : 'text-gray-300 hover:bg-white/5'
                }`}
            >
              {s.nombre}
            </button>
          ))}
        </div>
      )}
    </div>
  );
};

interface SidebarProps {
  alertasPendientes?: number;
}

export const Sidebar = ({ alertasPendientes = 0 }: SidebarProps) => {
  const location = useLocation();
  const navigate = useNavigate();
  const { user, sucursalActiva, sucursalesPermitidas, switchSucursal } = useAuth();

  const isActive = (path: string) => location.pathname === path || location.pathname.startsWith(path + '/');

  const navItemBase =
    'flex items-center gap-3 px-3 py-2.5 rounded-lg cursor-pointer transition-colors text-sm font-medium select-none';

  const activeClasses =
    'bg-white/10 text-white border-l-[3px] border-sag-500 pl-[9px]';

  const inactiveClasses =
    'text-gray-400 hover:bg-white/5 hover:text-gray-200 border-l-[3px] border-transparent pl-[9px]';

  const esSuperAdmin = user?.rol === 'sistemas' || user?.rol === 'superusuario';

  const handleSwitch = async (id: number) => {
    try {
      await switchSucursal(id);
      navigate('/dashboard');
    } catch {
      // silencioso — el contexto ya maneja el error
    }
  };

  // --- Desktop Sidebar ---
  const DesktopSidebar = () => (
    <aside
      className="hidden md:flex flex-col w-[220px] min-h-screen bg-gray-900 border-r border-gray-800 fixed top-0 left-0 z-30"
      style={{ backgroundColor: '#161b27' }}
    >
      {/* Logo */}
      <div className="flex items-center gap-2.5 px-4 py-5 border-b border-gray-800">
        <img
          src="/logo.png"
          alt="SAG Garage"
          className="w-8 h-8 rounded-lg object-cover"
        />
        <div>
          <p className="text-white font-semibold text-sm leading-tight">SAG Garage</p>
          <p className="text-gray-500 text-xs leading-tight">Gestión de taller</p>
        </div>
      </div>

      {/* Branch Switcher — solo sistemas/superusuario con 2+ sucursales */}
      {esSuperAdmin && (
        <div className="pt-3">
          <BranchSwitcher
            sucursalActiva={sucursalActiva}
            sucursalesPermitidas={sucursalesPermitidas}
            onSwitch={handleSwitch}
          />
        </div>
      )}

      {/* Nav items */}
      <nav className="flex-1 px-3 py-4 space-y-1">
        <button
          onClick={() => navigate('/dashboard')}
          className={`${navItemBase} w-full text-left ${isActive('/dashboard') ? activeClasses : inactiveClasses}`}
        >
          <IconOrdenes />
          <span>Ordenes</span>
        </button>

        <button
          onClick={() => navigate('/clientes')}
          className={`${navItemBase} w-full text-left ${isActive('/clientes') || isActive('/cliente') ? activeClasses : inactiveClasses}`}
        >
          <IconClientes />
          <span>Clientes y Vehiculos</span>
        </button>

        <button
          onClick={() => navigate('/financiero')}
          className={`${navItemBase} w-full text-left ${isActive('/financiero') ? activeClasses : inactiveClasses}`}
        >
          <IconFinanciero />
          <span>Financiero</span>
        </button>

        {/* Separador */}
        <div className="border-t border-gray-800 my-2" />

        {/* Alertas WhatsApp */}
        <button
          onClick={() => navigate('/alertas')}
          className={`${navItemBase} w-full text-left ${isActive('/alertas') ? activeClasses : inactiveClasses}`}
        >
          <span className="relative">
            <IconBell />
            {alertasPendientes > 0 && (
              <span className="absolute -top-1.5 -right-1.5 bg-sag-500 text-gray-900 text-xs font-bold rounded-full w-4 h-4 flex items-center justify-center leading-none">
                {alertasPendientes > 9 ? '9+' : alertasPendientes}
              </span>
            )}
          </span>
          <span className="flex-1">Alertas WhatsApp</span>
        </button>

        {/* Sección Sistemas — solo rol sistemas */}
        {user?.rol === 'sistemas' && (
          <>
            <div className="border-t border-gray-800 my-2" />
            <p className="px-3 text-[10px] font-semibold text-gray-600 uppercase tracking-wider mb-1">Sistemas</p>
            <button
              onClick={() => navigate('/sistemas/sucursales')}
              className={`${navItemBase} w-full text-left ${isActive('/sistemas/sucursales') ? activeClasses : inactiveClasses}`}
            >
              <IconSistemas />
              <span>Sucursales</span>
            </button>
            <button
              onClick={() => navigate('/sistemas/usuarios')}
              className={`${navItemBase} w-full text-left ${isActive('/sistemas/usuarios') ? activeClasses : inactiveClasses}`}
            >
              <IconClientes />
              <span>Usuarios</span>
            </button>
          </>
        )}
      </nav>
    </aside>
  );

  // --- Mobile Bottom Nav ---
  const MobileNav = () => (
    <nav className="md:hidden fixed bottom-0 left-0 right-0 z-30 border-t border-gray-800 flex justify-around items-center h-16"
      style={{ backgroundColor: '#161b27' }}>
      <button
        onClick={() => navigate('/dashboard')}
        className={`flex flex-col items-center gap-0.5 px-4 py-2 transition-colors ${isActive('/dashboard') ? 'text-sag-500' : 'text-gray-500'}`}
      >
        <IconOrdenes />
        <span className="text-xs">Ordenes</span>
      </button>

      <button
        onClick={() => navigate('/clientes')}
        className={`flex flex-col items-center gap-0.5 px-4 py-2 transition-colors ${isActive('/clientes') || isActive('/cliente') ? 'text-sag-500' : 'text-gray-500'}`}
      >
        <IconClientes />
        <span className="text-xs">Clientes</span>
      </button>

      <button
        onClick={() => navigate('/financiero')}
        className={`flex flex-col items-center gap-0.5 px-4 py-2 transition-colors ${isActive('/financiero') ? 'text-sag-500' : 'text-gray-500'}`}
      >
        <IconFinanciero />
        <span className="text-xs">Financiero</span>
      </button>

      <button
        onClick={() => navigate('/alertas')}
        className={`flex flex-col items-center gap-0.5 px-4 py-2 transition-colors ${isActive('/alertas') ? 'text-sag-500' : 'text-gray-500'}`}
      >
        <span className="relative">
          <IconBell />
          {alertasPendientes > 0 && (
            <span className="absolute -top-1.5 -right-1.5 bg-sag-500 text-gray-900 text-xs font-bold rounded-full w-4 h-4 flex items-center justify-center leading-none">
              {alertasPendientes > 9 ? '9+' : alertasPendientes}
            </span>
          )}
        </span>
        <span className="text-xs">Alertas</span>
      </button>
    </nav>
  );

  return (
    <>
      <DesktopSidebar />
      <MobileNav />
    </>
  );
};

// --- App Shell: envuelve todas las rutas autenticadas ---

interface AppShellProps {
  children: React.ReactNode;
  moduleName?: string;
}

export const AppShell = ({ children, moduleName }: AppShellProps) => {
  const { user, logout, sucursalActiva, sucursalesPermitidas, switchSucursal } = useAuth();
  const navigate = useNavigate();
  const [alertasPendientes, setAlertasPendientes] = useState(0);
  const [mobileBranchOpen, setMobileBranchOpen] = useState(false);

  const esSuperAdminShell = user?.rol === 'sistemas' || user?.rol === 'superusuario';

  const handleMobileBranchSwitch = async (id: number) => {
    try {
      await switchSucursal(id);
      navigate('/dashboard');
    } catch {
      // silencioso
    }
  };

  useEffect(() => {
    if (!isAlertasAuthorized(user)) return;

    const fetchCount = async () => {
      try {
        const result = await alertasAutoService.obtenerAlertas();
        if (result.success && result.alertas) {
          const pendientes = result.alertas.filter(
            (a: Alerta) => a.estado === 'pendiente'
          ).length;
          setAlertasPendientes(pendientes);
        }
      } catch {
        // silencioso — badge no crítico
      }
    };

    fetchCount();
  }, [user]);

  const { themeMode, toggleTheme } = usePresupuestoStore();

  // Aplicar tema al documento
  useEffect(() => {
    if (themeMode === 'dark') {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  }, [themeMode]);

  const handleLogout = async () => {
    await logout();
    navigate('/login');
  };

  const isStaging =
    (import.meta.env.VITE_BASE_PATH ?? '').includes('staging') ||
    window.location.pathname.includes('/staging/');

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex overflow-x-hidden">
      {/* Banner staging — solo visible en ambiente de pruebas */}
      {isStaging && (
        <div className="fixed top-0 left-0 right-0 z-50 flex items-center justify-center gap-2 bg-amber-500 text-black text-xs font-semibold py-2 px-4">
          <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5}
              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
          </svg>
          AMBIENTE DE PRUEBAS — Los cambios aquí no afectan el sistema real
        </div>
      )}

      {/* Badge flotante staging */}
      {isStaging && (
        <div className="fixed bottom-20 right-4 md:bottom-6 z-50 bg-amber-500 text-black text-xs font-bold px-3 py-1.5 rounded-full shadow-lg select-none">
          STAGING
        </div>
      )}

      {/* Sidebar fijo */}
      <Sidebar alertasPendientes={alertasPendientes} />

      {/* Contenido principal — margen izquierdo para dejar espacio al sidebar en desktop */}
      <div className={`flex-1 min-w-0 flex flex-col md:ml-[220px] ${isStaging ? 'pt-8' : ''}`}>
        {/* Top bar simplificado */}
        <header
          className={`sticky z-20 flex items-center justify-between px-4 sm:px-6 h-14 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 ${isStaging ? 'top-8' : 'top-0'}`}
        >
          <div className="flex flex-row items-center gap-2">
            <span className="text-sm font-semibold text-gray-700 dark:text-gray-200">
              {moduleName ?? 'SAG Garage'}
            </span>
            {/* Selector de sucursal en móvil — solo superadmin con 2+ sucursales */}
            {esSuperAdminShell && sucursalesPermitidas.length > 1 && (
              <div className="md:hidden relative">
                <button
                  onClick={() => setMobileBranchOpen((p) => !p)}
                  className="flex items-center gap-1 text-[11px] text-sag-500 font-medium"
                >
                  <span className="truncate max-w-[140px]">{sucursalActiva?.nombre ?? 'Sucursal'}</span>
                  <svg
                    className={`w-3 h-3 flex-shrink-0 transition-transform ${mobileBranchOpen ? 'rotate-180' : ''}`}
                    fill="none" stroke="currentColor" viewBox="0 0 24 24"
                  >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                  </svg>
                </button>
                {mobileBranchOpen && (
                  <div className="absolute left-0 top-full mt-1 z-50 min-w-[180px] rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-xl overflow-hidden">
                    {sucursalesPermitidas.map((s) => (
                      <button
                        key={s.id}
                        onClick={() => { handleMobileBranchSwitch(s.id); setMobileBranchOpen(false); }}
                        className={`w-full text-left px-3 py-2.5 text-xs transition-colors
                          ${sucursalActiva?.id === s.id
                            ? 'bg-sag-500/10 text-sag-500 font-semibold'
                            : 'text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
                          }`}
                      >
                        {s.nombre}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>

          <div className="flex items-center gap-3">
            {/* Nombre del usuario */}
            <div className="hidden sm:flex items-center gap-2">
              <div className="w-7 h-7 rounded-full bg-sag-500 flex items-center justify-center">
                <span className="text-gray-900 text-xs font-bold">
                  {user?.nombre?.charAt(0).toUpperCase() ?? user?.username?.charAt(0).toUpperCase() ?? 'U'}
                </span>
              </div>
              <span className="text-sm text-gray-700 dark:text-gray-300">
                {user?.nombre ?? user?.username}
              </span>
            </div>

            {/* Toggle tema */}
            <button
              onClick={toggleTheme}
              className="w-8 h-8 rounded-lg flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
              title={themeMode === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'}
            >
              {themeMode === 'dark' ? (
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z" />
                </svg>
              ) : (
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                </svg>
              )}
            </button>

            <button
              onClick={handleLogout}
              className="flex items-center gap-1.5 text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors"
              title="Cerrar sesión"
            >
              {/* Icono logout — siempre visible */}
              <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24" className="flex-shrink-0">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                  d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
              </svg>
              {/* Texto oculto en mobile */}
              <span className="hidden sm:inline">Salir</span>
            </button>
          </div>
        </header>

        {/* Página */}
        <main className="flex-1 pb-20 md:pb-0">
          {children}
        </main>
      </div>
    </div>
  );
};
