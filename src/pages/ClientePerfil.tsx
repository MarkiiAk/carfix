import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { clientesAPI } from '../services/api';
import type { ClientePerfil as ClientePerfilData, VehiculoConHistorial, OrdenResumen, ResumenFinancieroCliente } from '../types';

const formatFecha = (fecha: string | null | undefined): string => {
  if (!fecha) return '—';
  return new Date(fecha).toLocaleDateString('es-MX', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const formatMoneda = (monto: number): string => {
  return new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
  }).format(monto);
};

const getEstadoDot = (estado: string) => {
  const base = 'w-2.5 h-2.5 rounded-full flex-shrink-0';
  if (estado === 'cerrada' || estado === 'entregada') {
    return <span className={`${base} bg-sag-500`} title="Cerrada" />;
  }
  return <span className={`${base} bg-yellow-400`} title="Abierta" />;
};

export const ClientePerfil = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [data, setData] = useState<ClientePerfilData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [notFound, setNotFound] = useState(false);

  useEffect(() => {
    if (!id) {
      setNotFound(true);
      setIsLoading(false);
      return;
    }

    const load = async () => {
      try {
        setIsLoading(true);
        const result = await clientesAPI.perfil(Number(id));
        if ((result as any).success === false) {
          setNotFound(true);
        } else {
          setData(result);
        }
      } catch (err: any) {
        if (err?.response?.status === 404) {
          setNotFound(true);
        }
      } finally {
        setIsLoading(false);
      }
    };

    load();
  }, [id]);

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[60vh]">
        <div className="text-center space-y-3">
          <div className="animate-spin h-10 w-10 border-4 border-sag-500 border-t-transparent rounded-full mx-auto" />
          <p className="text-gray-500 dark:text-gray-400 text-sm">Cargando perfil...</p>
        </div>
      </div>
    );
  }

  if (notFound || !data) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] gap-4">
        <svg className="w-16 h-16 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        <p className="text-gray-600 dark:text-gray-400 text-lg font-medium">Cliente no encontrado</p>
        <button
          onClick={() => navigate('/clientes')}
          className="text-sm text-sag-600 dark:text-sag-400 hover:underline"
        >
          Volver a clientes
        </button>
      </div>
    );
  }

  const { cliente, vehiculos, resumen_financiero } = data;

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      {/* Botón volver */}
      <button
        onClick={() => navigate('/clientes')}
        className="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors mb-6"
      >
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
        </svg>
        Clientes
      </button>

      {/* Bloque 1 — Cabecera */}
      <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          {/* Info principal */}
          <div className="flex items-start gap-4">
            <div className="w-14 h-14 rounded-full bg-sag-500 flex items-center justify-center flex-shrink-0">
              <span className="text-gray-900 text-xl font-bold">
                {cliente.nombre.charAt(0).toUpperCase()}
              </span>
            </div>
            <div>
              <h1 className="text-2xl font-bold text-gray-900 dark:text-white leading-tight">
                {cliente.nombre}
              </h1>
              <div className="flex flex-wrap gap-x-4 gap-y-1 mt-1">
                {cliente.telefono && (
                  <span className="text-sm text-gray-500 dark:text-gray-400">{cliente.telefono}</span>
                )}
                {cliente.email && (
                  <span className="text-sm text-gray-500 dark:text-gray-400">{cliente.email}</span>
                )}
              </div>
            </div>
          </div>

          {/* Acciones */}
          <div className="flex gap-2 flex-shrink-0">
            <button
              onClick={() => navigate(`/nueva-orden?cliente_id=${cliente.id}`)}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-sag-500 text-gray-900 hover:bg-sag-400 transition-colors"
            >
              <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
              </svg>
              Nueva orden
            </button>
            <button
              disabled
              className="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 cursor-not-allowed opacity-60"
            >
              Editar datos
            </button>
          </div>
        </div>

        {/* Chips de métricas */}
        <div className="flex flex-wrap gap-3 mt-5 pt-5 border-t border-gray-100 dark:border-gray-700">
          <div className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-700/50 text-sm text-gray-700 dark:text-gray-300">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <strong>{cliente.total_visitas}</strong>
            {cliente.total_visitas === 1 ? ' visita' : ' visitas'} al taller
          </div>

          <div className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-700/50 text-sm text-gray-700 dark:text-gray-300">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            Ultima visita: {formatFecha(cliente.ultima_visita)}
          </div>

          <div className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-50 dark:bg-gray-700/50 text-sm text-gray-700 dark:text-gray-300">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            </svg>
            <strong>{vehiculos.length}</strong>
            {vehiculos.length === 1 ? ' vehiculo' : ' vehiculos'}
          </div>
        </div>
      </div>

      {/* Bloque financiero — solo si hay más de 1 visita */}
      {cliente.total_visitas > 1 && (
        <ResumenFinancieroBloque resumen={resumen_financiero} />
      )}

      {/* Bloque 2 — Vehiculos con acordeon */}
      {vehiculos.length === 0 ? (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-8 text-center">
          <svg className="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
          </svg>
          <p className="text-gray-500 dark:text-gray-400">No hay vehiculos registrados para este cliente.</p>
        </div>
      ) : (
        <div className="space-y-3">
          {vehiculos.map((vehiculo, index) => (
            <VehiculoAcordeon
              key={vehiculo.id}
              vehiculo={vehiculo}
              defaultOpen={vehiculos.length === 1 || index === 0}
              onVerOrden={(ordenId) => navigate(`/orden/${ordenId}`)}
            />
          ))}
        </div>
      )}
    </div>
  );
};

// --- Resumen financiero ---

const ResumenFinancieroBloque = ({ resumen }: { resumen: ResumenFinancieroCliente }) => (
  <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-6 py-5 mb-6">
    {/* Fila superior: total destacado */}
    <div className="text-center mb-4 pb-4 border-b border-gray-100 dark:border-gray-700">
      <p className="text-2xl font-bold text-gray-900 dark:text-white">
        {formatMoneda(resumen.total_gastado)}
      </p>
      <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">Total gastado</p>
    </div>
    {/* Fila inferior: desglose que suma al total */}
    <div className="grid grid-cols-3 divide-x divide-gray-100 dark:divide-gray-700 text-center">
      <div className="px-3">
        <p className="text-base font-semibold text-gray-800 dark:text-gray-200">
          {formatMoneda(resumen.total_servicios)}
        </p>
        <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">Servicios y mano de obra</p>
      </div>
      <div className="px-3">
        <p className="text-base font-semibold text-gray-800 dark:text-gray-200">
          {formatMoneda(resumen.total_refacciones)}
        </p>
        <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">Refacciones</p>
      </div>
      <div className="px-3">
        <p className="text-base font-semibold text-gray-800 dark:text-gray-200">
          {formatMoneda(resumen.total_iva)}
        </p>
        <p className="text-xs text-gray-400 dark:text-gray-500 mt-1">IVA incluido</p>
      </div>
    </div>
  </div>
);

// --- Acordeon de vehículo ---

interface VehiculoAcordeonProps {
  vehiculo: VehiculoConHistorial;
  defaultOpen: boolean;
  onVerOrden: (id: number) => void;
}

const VehiculoAcordeon = ({ vehiculo, defaultOpen, onVerOrden }: VehiculoAcordeonProps) => {
  const [open, setOpen] = useState(defaultOpen);
  const [mostrarTodas, setMostrarTodas] = useState(false);

  const VISIBLE_COUNT = 3;
  const ordenes = vehiculo.ordenes;
  const visibles = mostrarTodas ? ordenes : ordenes.slice(0, VISIBLE_COUNT);
  const ocultas = ordenes.length - VISIBLE_COUNT;

  return (
    <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
      {/* Header del acordeon */}
      <button
        onClick={() => setOpen(!open)}
        className="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors text-left"
      >
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center flex-shrink-0">
            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" className="text-gray-500 dark:text-gray-400">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
            </svg>
          </div>
          <div>
            <p className="font-semibold text-gray-900 dark:text-white text-sm">
              {vehiculo.marca} {vehiculo.modelo}
              {vehiculo.anio && <span className="font-normal text-gray-500 dark:text-gray-400"> · {vehiculo.anio}</span>}
            </p>
            {vehiculo.placas && (
              <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-mono">
                {vehiculo.placas}
              </p>
            )}
          </div>
        </div>
        <div className="flex items-center gap-3">
          <span className="text-xs text-gray-400 dark:text-gray-500">
            {ordenes.length} {ordenes.length === 1 ? 'servicio' : 'servicios'}
          </span>
          <svg
            width="16" height="16"
            fill="none" stroke="currentColor" viewBox="0 0 24 24"
            className={`text-gray-400 transition-transform duration-200 ${open ? 'rotate-180' : ''}`}
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
          </svg>
        </div>
      </button>

      {/* Contenido del acordeon */}
      {open && (
        <div className="border-t border-gray-100 dark:border-gray-700 px-6 py-4">
          {ordenes.length === 0 ? (
            <p className="text-sm text-gray-400 dark:text-gray-500 py-2">
              Sin ordenes registradas para este vehiculo.
            </p>
          ) : (
            <div className="space-y-0">
              {visibles.map((orden) => (
                <TimelineItem
                  key={orden.id}
                  orden={orden}
                  onVer={() => onVerOrden(orden.id)}
                />
              ))}

              {!mostrarTodas && ocultas > 0 && (
                <button
                  onClick={() => setMostrarTodas(true)}
                  className="mt-3 text-sm text-sag-600 dark:text-sag-400 hover:underline flex items-center gap-1"
                >
                  <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                  </svg>
                  Ver {ocultas} {ocultas === 1 ? 'orden' : 'ordenes'} mas
                </button>
              )}

              {mostrarTodas && ocultas > 0 && (
                <button
                  onClick={() => setMostrarTodas(false)}
                  className="mt-3 text-sm text-gray-400 dark:text-gray-500 hover:underline flex items-center gap-1"
                >
                  <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 15l7-7 7 7" />
                  </svg>
                  Mostrar menos
                </button>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
};

// --- Ítem del timeline ---

interface TimelineItemProps {
  orden: OrdenResumen;
  onVer: () => void;
}

const TimelineItem = ({ orden, onVer }: TimelineItemProps) => {
  return (
    <div className="flex items-center gap-3 py-2.5 border-b border-gray-50 dark:border-gray-700/50 last:border-0 group">
      {/* Dot de estado */}
      {getEstadoDot(orden.estado)}

      {/* Fecha */}
      <span className="text-xs text-gray-400 dark:text-gray-500 w-28 flex-shrink-0 font-mono">
        {formatFecha(orden.fecha_ingreso)}
      </span>

      {/* Servicio + km */}
      <div className="flex-1 min-w-0">
        <span className="text-sm text-gray-700 dark:text-gray-300 truncate block">
          {orden.servicio_principal || 'Servicio general'}
        </span>
        {orden.kilometraje_entrada && (
          <span className="text-xs text-gray-400 dark:text-gray-500">
            {parseInt(orden.kilometraje_entrada).toLocaleString('es-MX')} km
          </span>
        )}
      </div>

      <div className="flex items-center gap-3 flex-shrink-0">
        <span className="text-sm font-medium text-gray-600 dark:text-gray-400">
          {formatMoneda(orden.total)}
        </span>

        <button
          onClick={onVer}
          className="text-xs text-sag-600 dark:text-sag-400 opacity-0 group-hover:opacity-100 transition-opacity hover:underline font-medium"
          title={`Ver orden ${orden.numero_orden}`}
        >
          Ver
        </button>
      </div>
    </div>
  );
};
