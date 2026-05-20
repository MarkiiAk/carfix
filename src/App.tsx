import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { ToastProvider } from './contexts/ToastContext';
import { ProtectedRoute } from './components/ProtectedRoute';
import { AppShell } from './components/Sidebar';
import { AlertasGuard } from './components/AlertasGuard';
import { Login, Dashboard, NuevaOrden, DetalleOrden, Alertas } from './pages';
import { Clientes } from './pages/Clientes';
import { ClientePerfil } from './pages/ClientePerfil';
import { Financiero } from './pages/Financiero';

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
                  <AppShell moduleName="Ingresos">
                    <Financiero />
                  </AppShell>
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
