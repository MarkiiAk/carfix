import { useState, useEffect, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { clientesAPI } from '../services/api';
import type { ClienteListItem } from '../types';

const formatFecha = (fecha: string | null): string => {
  if (!fecha) return 'Sin visitas';
  return new Date(fecha).toLocaleDateString('es-MX', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

export const Clientes = () => {
  const [clientes, setClientes] = useState<ClienteListItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [error, setError] = useState<string | null>(null);
  const navigate = useNavigate();

  // Debounce: esperar 300ms antes de buscar
  useEffect(() => {
    const timer = setTimeout(() => {
      setDebouncedSearch(searchTerm);
    }, 300);
    return () => clearTimeout(timer);
  }, [searchTerm]);

  const loadClientes = useCallback(async (q: string) => {
    try {
      setIsLoading(true);
      setError(null);
      const query = q.length >= 2 ? q : undefined;
      const data = await clientesAPI.listar(query);
      if (data.success) {
        setClientes(data.clientes);
      } else {
        setError('No se pudieron cargar los clientes.');
      }
    } catch {
      setError('Error al conectar con el servidor.');
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    loadClientes(debouncedSearch);
  }, [debouncedSearch, loadClientes]);

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Header de sección */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
          <h2 className="text-2xl font-bold text-gray-900 dark:text-white">Clientes y Vehiculos</h2>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
            Directorio de clientes del taller
          </p>
        </div>
        <button
          onClick={() => navigate('/nueva-orden')}
          className="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium bg-sag-500 text-gray-900 hover:bg-sag-400 transition-colors shadow-sm"
        >
          <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
          </svg>
          Nuevo Cliente
        </button>
      </div>

      {/* Barra de busqueda */}
      <div className="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700 mb-6">
        <div className="relative">
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <svg className="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
          </div>
          <input
            type="text"
            placeholder="Buscar por nombre o telefono (minimo 2 caracteres)..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="w-full pl-10 pr-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-sag-500 focus:border-transparent dark:bg-gray-700 dark:text-white text-sm"
          />
          {searchTerm.length === 1 && (
            <p className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400">
              Escribe al menos 2 caracteres
            </p>
          )}
        </div>
      </div>

      {/* Tabla */}
      <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        {isLoading ? (
          <div className="flex items-center justify-center py-16">
            <div className="text-center space-y-3">
              <div className="animate-spin h-10 w-10 border-4 border-sag-500 border-t-transparent rounded-full mx-auto" />
              <p className="text-gray-500 dark:text-gray-400 text-sm">Cargando clientes...</p>
            </div>
          </div>
        ) : error ? (
          <div className="flex flex-col items-center justify-center py-16 gap-3">
            <svg className="w-12 h-12 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.962-.833-2.732 0L3.07 16.5C2.3 17.333 3.262 19 4.802 19z" />
            </svg>
            <p className="text-gray-600 dark:text-gray-400">{error}</p>
            <button
              onClick={() => loadClientes(debouncedSearch)}
              className="text-sm text-sag-600 dark:text-sag-400 underline"
            >
              Reintentar
            </button>
          </div>
        ) : clientes.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-16 gap-3">
            <svg className="w-16 h-16 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <p className="text-gray-500 dark:text-gray-400 text-lg font-medium">
              {debouncedSearch.length >= 2
                ? `Sin resultados para "${debouncedSearch}"`
                : 'Aun no hay clientes registrados.'}
            </p>
            {debouncedSearch.length < 2 && (
              <p className="text-gray-400 dark:text-gray-500 text-sm">
                Los clientes se crean al guardar una orden de servicio.
              </p>
            )}
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead className="bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700">
                <tr>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Cliente
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Vehiculos
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Ultima visita
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Total visitas
                  </th>
                  <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Accion
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                {clientes.map((cliente) => (
                  <ClienteRow
                    key={cliente.id}
                    cliente={cliente}
                    onVerPerfil={() => navigate(`/cliente/${cliente.id}`)}
                  />
                ))}
              </tbody>
            </table>

            {/* Footer con conteo */}
            <div className="px-6 py-3 bg-gray-50 dark:bg-gray-700/30 border-t border-gray-200 dark:border-gray-700">
              <p className="text-xs text-gray-500 dark:text-gray-400">
                {clientes.length} cliente{clientes.length !== 1 ? 's' : ''}
                {debouncedSearch.length >= 2 ? ` para "${debouncedSearch}"` : ''}
              </p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

// --- Fila individual ---

interface ClienteRowProps {
  cliente: ClienteListItem;
  onVerPerfil: () => void;
}

const ClienteRow = ({ cliente, onVerPerfil }: ClienteRowProps) => {
  const vehiculosLabel = () => {
    if (cliente.total_vehiculos === 0) return <span className="text-gray-400 text-sm">Sin vehiculos</span>;
    return (
      <div className="flex items-center gap-1.5 flex-wrap">
        <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
          {cliente.total_vehiculos} vehiculo{cliente.total_vehiculos !== 1 ? 's' : ''}
        </span>
      </div>
    );
  };

  return (
    <tr className="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
      {/* Cliente */}
      <td className="px-6 py-4">
        <div>
          <p className="text-sm font-medium text-gray-900 dark:text-white">{cliente.nombre}</p>
          {cliente.telefono && (
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{cliente.telefono}</p>
          )}
        </div>
      </td>

      {/* Vehiculos */}
      <td className="px-6 py-4">
        {vehiculosLabel()}
      </td>

      {/* Ultima visita */}
      <td className="px-6 py-4 whitespace-nowrap">
        <span className="text-sm text-gray-700 dark:text-gray-300">
          {formatFecha(cliente.ultima_visita)}
        </span>
      </td>

      {/* Total visitas */}
      <td className="px-6 py-4 whitespace-nowrap">
        <span className={`inline-flex items-center justify-center w-8 h-8 rounded-full text-sm font-semibold ${
          cliente.total_visitas > 0
            ? 'bg-sag-100 dark:bg-sag-900/30 text-sag-700 dark:text-sag-400'
            : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'
        }`}>
          {cliente.total_visitas}
        </span>
      </td>

      {/* Accion */}
      <td className="px-6 py-4 whitespace-nowrap">
        <button
          onClick={onVerPerfil}
          className="text-sm font-medium text-sag-600 dark:text-sag-400 hover:text-sag-700 dark:hover:text-sag-300 transition-colors"
        >
          Ver perfil
        </button>
      </td>
    </tr>
  );
};
