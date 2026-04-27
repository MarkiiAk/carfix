import React, { useState, useEffect, useRef } from 'react';
import { Card, FormField } from '../ui';
import { usePresupuestoStore } from '../../store/usePresupuestoStore';
import { clientesAPI } from '../../services/api';

interface ClienteSectionProps {
  disabled?: boolean;
}

interface ClienteMatch {
  id: number;
  nombre: string;
  telefono: string | null;
  total_visitas: number;
  ultima_visita: string | null;
  vehiculos: { marca: string; modelo: string; placas: string }[];
  total_vehiculos: number;
}

const formatFecha = (fecha: string | null | undefined) => {
  if (!fecha) return null;
  return new Date(fecha).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

export const ClienteSection: React.FC<ClienteSectionProps> = ({ disabled = false }) => {
  const { presupuesto, updateCliente } = usePresupuestoStore();
  const { cliente } = presupuesto;

  const [matches, setMatches]         = useState<ClienteMatch[]>([]);
  const [buscando, setBuscando]       = useState(false);
  const [seleccionado, setSeleccionado] = useState<ClienteMatch | null>(null);
  const [mostrarForm, setMostrarForm] = useState(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const handleChange = (field: keyof typeof cliente) => (value: string) => {
    updateCliente({ [field]: value });
  };

  // Búsqueda por teléfono con debounce
  const handleTelefonoChange = (value: string) => {
    updateCliente({ telefono: value });
    setSeleccionado(null);
    setMostrarForm(false);
    setMatches([]);

    if (debounceRef.current) clearTimeout(debounceRef.current);

    const digits = value.replace(/\D/g, '');
    if (digits.length < 8) return;

    debounceRef.current = setTimeout(async () => {
      try {
        setBuscando(true);
        const result = await clientesAPI.buscarPorTelefono(value) as any;
        if (result?.matches?.length > 0) {
          setMatches(result.matches);
        } else {
          setMatches([]);
          setMostrarForm(true);
        }
      } catch {
        setMostrarForm(true);
      } finally {
        setBuscando(false);
      }
    }, 350);
  };

  const seleccionarCliente = (match: ClienteMatch) => {
    setSeleccionado(match);
    setMatches([]);
    setMostrarForm(true);
    updateCliente({
      nombreCompleto: match.nombre,
      telefono: match.telefono ?? cliente.telefono,
      email: '',
    });
  };

  const ignorarSugerencia = () => {
    setMatches([]);
    setMostrarForm(true);
    updateCliente({ nombreCompleto: '', email: '', domicilio: '' });
  };

  // Limpiar selección si el usuario edita el teléfono manualmente
  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const numeroCompartido = matches.length > 1;

  return (
    <Card
      title="Información del Cliente"
      subtitle="Datos de contacto del propietario del vehículo"
      className="p-6"
    >
      <div className="space-y-5">

        {/* TELÉFONO — siempre primero */}
        <div>
          <FormField
            name="telefono"
            label="📞 Teléfono"
            type="tel"
            placeholder="Ej: 5541357626"
            value={cliente.telefono}
            onChange={disabled ? handleChange('telefono') : handleTelefonoChange}
            required
            disabled={disabled}
            validation={{ required: true, phone: true, minLength: 10, maxLength: 15 }}
          />

          {/* Chip número compartido */}
          {numeroCompartido && (
            <p className="mt-1.5 text-xs text-gray-400 dark:text-gray-500">
              Número compartido · {matches.length} personas
            </p>
          )}

          {/* Spinner buscando */}
          {buscando && (
            <p className="mt-1.5 text-xs text-gray-400 dark:text-gray-500 flex items-center gap-1.5">
              <span className="inline-block w-3 h-3 border-2 border-sag-500 border-t-transparent rounded-full animate-spin" />
              Buscando cliente...
            </p>
          )}
        </div>

        {/* SUGERENCIAS */}
        {matches.length > 0 && !disabled && (
          <div className="space-y-2">
            <p className="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
              {matches.length === 1 ? 'Cliente encontrado' : `${matches.length} clientes con este número`}
            </p>

            {matches.map((match) => (
              <button
                key={match.id}
                type="button"
                onClick={() => seleccionarCliente(match)}
                className="w-full text-left px-4 py-3 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50 hover:border-sag-400 hover:bg-sag-50 dark:hover:bg-sag-900/10 transition-colors group"
              >
                <div className="flex items-center justify-between gap-3">
                  <div className="min-w-0">
                    <p className="font-semibold text-gray-900 dark:text-white text-sm truncate">
                      {match.nombre}
                    </p>
                    <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                      {match.total_visitas} {match.total_visitas === 1 ? 'visita' : 'visitas'}
                      {match.ultima_visita && ` · última: ${formatFecha(match.ultima_visita)}`}
                    </p>
                    {match.vehiculos.length > 0 && (
                      <p className="text-xs text-gray-400 dark:text-gray-500 mt-0.5 truncate">
                        {match.vehiculos[0].marca} {match.vehiculos[0].modelo} · {match.vehiculos[0].placas}
                        {match.total_vehiculos > 1 && (
                          <span className="ml-1 text-gray-400">+{match.total_vehiculos - 1} vehículo{match.total_vehiculos - 1 > 1 ? 's' : ''}</span>
                        )}
                      </p>
                    )}
                  </div>
                  <span className="text-xs text-sag-600 dark:text-sag-400 font-medium flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                    Seleccionar →
                  </span>
                </div>
              </button>
            ))}

            <button
              type="button"
              onClick={ignorarSugerencia}
              className="w-full text-center text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 py-2 transition-colors"
            >
              + Registrar como cliente nuevo con este número
            </button>
          </div>
        )}

        {/* Banner cliente seleccionado */}
        {seleccionado && !disabled && (
          <div className="flex items-center justify-between px-4 py-2.5 rounded-lg bg-sag-50 dark:bg-sag-900/20 border border-sag-200 dark:border-sag-800">
            <span className="text-sm text-sag-700 dark:text-sag-400 font-medium">
              ✓ Datos pre-cargados de {seleccionado.nombre}
            </span>
            <button
              type="button"
              onClick={ignorarSugerencia}
              className="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
            >
              Limpiar
            </button>
          </div>
        )}

        {/* CAMPOS DE DATOS — visibles después de búsqueda o cuando no hay matches */}
        {(mostrarForm || disabled || cliente.nombreCompleto) && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <FormField
              name="nombreCompleto"
              label="👤 Nombre Completo"
              placeholder="Ej: Juan Pérez García"
              value={cliente.nombreCompleto}
              onChange={handleChange('nombreCompleto')}
              required
              disabled={disabled}
              validation={{ required: true, minLength: 2, maxLength: 100 }}
            />

            <FormField
              name="email"
              label="📧 Correo Electrónico"
              type="email"
              placeholder="correo@ejemplo.com"
              value={cliente.email}
              onChange={handleChange('email')}
              disabled={disabled}
              validation={{ email: true, maxLength: 100 }}
            />
          </div>
        )}

      </div>
    </Card>
  );
};
