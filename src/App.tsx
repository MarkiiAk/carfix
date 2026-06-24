import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { useAuth } from './contexts/AuthContext';
import { ToastProvider } from './contexts/ToastContext';
import { ProtectedRoute } from './components/ProtectedRoute';
import { AppShell } from './components/Sidebar';
import { AlertasGuard } from './components/AlertasGuard';
import { Login, Dashboard, NuevaOrden, DetalleOrden, Alertas } from './pages';
import { Clientes } from './pages/Clientes';
import { ClientePerfil } from './pages/ClientePerfil';
import { Financiero } from './pages/Financiero';
import { SucursalesPage } from './pages/sistemas/SucursalesPage';
import { UsuariosPage } from './pages/sistemas/UsuariosPage';
import type { ReactNode } from 'react';

/** Guard para rutas exclusivas del rol 'sistemas'. */
function SistemasRoute({ children }: { children: ReactNode }) {
  const { user, isLoading } = useAuth();
  if (isLoading) return null;
  if (!user || user.rol !== 'sistemas') {
    return <Navigate to="/dashboard" replace />;
  }
  return <>{children}</>;
}

/** Guard para módulo financiero — bloqueado para rol 'asistente'. */
function FinancieroRoute({ children }: { children: ReactNode }) {
  const { user, isLoading } = useAuth();
  if (isLoading) return null;
  if (!user || user.rol === 'asistente') {
    return <Navigate to="/dashboard" replace />;
  }
  return <>{children}</>;
}

function App() {
  return (
    <BrowserRouter basename={import.meta.env.VITE_BASE_PATH ?? '/gestion'}>
      <ToastProvider>
        <AuthProvider>
          <Routes>
            {/* Ruta pública */}
            <Route path="/login" element={<Login />} />

            {/* Rutas protegidas — todas dentro de AppShell */}
            <Route
              path="/dashboard"
              element={
                <ProtectedRoute>
                  <AppShell moduleName="Ordenes">
                    <Dashboard />
                  </AppShell>
                </ProtectedRoute>
              }
            />

            <Route
              path="/nueva-orden"
              element={
                <ProtectedRoute>
                  <AppShell moduleName="Nueva Orden">
                    <NuevaOrden />
                  </AppShell>
                </ProtectedRoute>
              }
            />

            <Route
              path="/orden/:id"
              element={
                <ProtectedRoute>
                  <AppShell moduleName="Detalle de Orden">
                    <DetalleOrden />
                  </AppShell>
                </ProtectedRoute>
              }
            />

            <Route
              path="/alertas"
              element={
                <ProtectedRoute>
                  <AlertasGuard>
                    <AppShell moduleName="Alertas WhatsApp">
                      <Alertas />
                    </AppShell>
                  </AlertasGuard>
                </ProtectedRoute>
              }
            />

            <Route
              path="/clientes"
              element={
                <ProtectedRoute>
                  <AppShell moduleName="Clientes y Vehiculos">
                    <Clientes />
                  </AppShell>
                </ProtectedRoute>
              }
            />

            <Route
              path="/cliente/:id"
              element={
                <ProtectedRoute>
                  <AppShell moduleName="Perfil de Cliente">
                    <ClientePerfil />
                  </AppShell>
                </ProtectedRoute>
              }
            />

            <Route
              path="/financiero"
              element={
                <ProtectedRoute>
                  <FinancieroRoute>
                    <AppShell moduleName="Ingresos">
                      <Financiero />
                    </AppShell>
                  </FinancieroRoute>
                </ProtectedRoute>
              }
            />

            {/* Rutas Sistemas — solo rol 'sistemas' */}
            <Route
              path="/sistemas/sucursales"
              element={
                <ProtectedRoute>
                  <SistemasRoute>
                    <AppShell moduleName="Sistemas - Sucursales">
                      <SucursalesPage />
                    </AppShell>
                  </SistemasRoute>
                </ProtectedRoute>
              }
            />
            <Route
              path="/sistemas/usuarios"
              element={
                <ProtectedRoute>
                  <SistemasRoute>
                    <AppShell moduleName="Sistemas - Usuarios">
                      <UsuariosPage />
                    </AppShell>
                  </SistemasRoute>
                </ProtectedRoute>
              }
            />

            {/* Defaults */}
            <Route path="/" element={<Navigate to="/dashboard" replace />} />
            <Route path="*" element={<Navigate to="/dashboard" replace />} />
          </Routes>
        </AuthProvider>
      </ToastProvider>
    </BrowserRouter>
  );
}

export default App;
