import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './contexts/AuthContext';
import { ToastProvider } from './contexts/ToastContext';
import { ProtectedRoute } from './components/ProtectedRoute';
import { Login, Dashboard, NuevaOrden, DetalleOrden, Alertas } from './pages';
import { AlertasGuard } from './components/AlertasGuard';

function App() {
  return (
    <BrowserRouter basename="/gestion">
      <ToastProvider>
        <AuthProvider>
          <Routes>
            {/* Ruta pública de login */}
            <Route path="/login" element={<Login />} />

            {/* Rutas protegidas */}
            <Route
              path="/dashboard"
              element={
                <ProtectedRoute>
                  <Dashboard />
                </ProtectedRoute>
              }
            />

            <Route
              path="/nueva-orden"
              element={
                <ProtectedRoute>
                  <NuevaOrden />
                </ProtectedRoute>
              }
            />

            <Route
              path="/orden/:id"
              element={
                <ProtectedRoute>
                  <DetalleOrden />
                </ProtectedRoute>
              }
            />

            <Route
              path="/alertas"
              element={
                <ProtectedRoute>
                  <AlertasGuard>
                    <Alertas />
                  </AlertasGuard>
                </ProtectedRoute>
              }
            />

            {/* Ruta por defecto - redirige al dashboard */}
            <Route path="/" element={<Navigate to="/dashboard" replace />} />

            {/* Ruta 404 - redirige al dashboard */}
            <Route path="*" element={<Navigate to="/dashboard" replace />} />
          </Routes>
        </AuthProvider>
      </ToastProvider>
    </BrowserRouter>
  );
}

export default App;
