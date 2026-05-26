import React, { useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faPlus, faXmark, faBoxOpen } from '@fortawesome/free-solid-svg-icons';
import { Card, Input, Button } from '../ui';
import { usePresupuestoStore } from '../../store/usePresupuestoStore';

interface RefaccionesSectionProps {
  disabled?: boolean;
}

export const RefaccionesSection: React.FC<RefaccionesSectionProps> = ({ disabled = false }) => {
  const { presupuesto, addRefaccion, deleteRefaccion } = usePresupuestoStore();
  const { refacciones } = presupuesto;

  const [showForm, setShowForm] = useState(false);
  const [nombre, setNombre]           = useState('');
  const [cantidad, setCantidad]       = useState('');
  const [precioCosto, setPrecioCosto] = useState('');
  const [proveedor, setProveedor]     = useState('');
  const [margen, setMargen]           = useState('30'); // editable, default 30%

  const margenNum    = parseFloat(margen)  || 0;
  const cantidadNum  = parseInt(cantidad)  || 0;
  const costoNum     = parseFloat(precioCosto) || 0;
  const ventaUnit    = costoNum * (1 + margenNum / 100);
  const previewOk    = cantidadNum > 0 && costoNum > 0;

  const handleAddRefaccion = () => {
    if (nombre && cantidadNum > 0 && costoNum > 0) {
      addRefaccion({
        nombre,
        cantidad: cantidadNum,
        precioCosto: costoNum,
        precioVenta: ventaUnit,   // store también lo recalcula, pero lo pasamos explícito
        margenGanancia: margenNum,
        proveedor: proveedor.trim() || undefined,
      });

      // Reset form
      setNombre('');
      setCantidad('');
      setPrecioCosto('');
      setProveedor('');
      setMargen('30');
      setShowForm(false);
    }
  };

  const formatCurrency = (value: number) =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value);

  return (
    <Card
      title="Refacciones"
      subtitle="Agrega las refacciones necesarias para la reparación"
      className="p-6"
    >
      {/* ── Lista de refacciones agregadas ─────────────────────────── */}
      {refacciones.length > 0 && (
        <div className="mb-6 space-y-2">
          {refacciones.map((refaccion) => (
            <div
              key={refaccion.id}
              className="flex items-start justify-between gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600"
            >
              {/* Icono — oculto en pantallas muy pequeñas para ahorrar espacio */}
              <FontAwesomeIcon
                icon={faBoxOpen}
                className="text-sag-600 mt-0.5 shrink-0 hidden xs:block"
                style={{ width: 18, height: 18 }}
              />

              {/* Contenido — 2 líneas: nombre+total / detalles */}
              <div className="flex-1 min-w-0">
                {/* Línea 1: nombre (izq) + total (der) */}
                <div className="flex items-baseline justify-between gap-2">
                  <p className="font-medium text-gray-900 dark:text-gray-100 truncate">
                    {refaccion.nombre}
                  </p>
                  <span className="text-sm font-bold text-gray-900 dark:text-gray-100 whitespace-nowrap shrink-0">
                    {formatCurrency(refaccion.total)}
                  </span>
                </div>

                {/* Línea 2: detalles compactos */}
                <div className="flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                  <span>Cant: {refaccion.cantidad}</span>
                  <span>${refaccion.precioVenta.toFixed(2)}/u</span>
                  {refaccion.margenGanancia !== undefined && refaccion.margenGanancia !== 30 && (
                    <span className="text-sag-500 dark:text-sag-400">
                      Margen: {refaccion.margenGanancia}%
                    </span>
                  )}
                  {refaccion.proveedor && (
                    <span className="text-gray-400 dark:text-gray-500">{refaccion.proveedor}</span>
                  )}
                </div>
              </div>

              {/* Botón eliminar */}
              <button
                onClick={() => deleteRefaccion(refaccion.id)}
                disabled={disabled}
                aria-label="Eliminar refacción"
                className="shrink-0 p-1.5 rounded text-gray-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20
                           disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
              >
                <FontAwesomeIcon icon={faXmark} style={{ width: 14, height: 14 }} />
              </button>
            </div>
          ))}
        </div>
      )}

      {/* ── Botón "Agregar Refacción" ───────────────────────────────── */}
      {!showForm && (
        <button
          onClick={() => setShowForm(true)}
          disabled={disabled}
          className="w-full flex items-center justify-center gap-2 px-4 py-3
                     bg-white dark:bg-gray-900
                     border-2 border-sag-500 dark:border-sag-400
                     text-sag-600 dark:text-sag-400 font-medium rounded-lg
                     hover:bg-sag-50 dark:hover:bg-sag-950/30
                     active:bg-sag-100 dark:active:bg-sag-950/50
                     transition-all duration-200
                     disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-white dark:disabled:hover:bg-gray-900"
        >
          <FontAwesomeIcon icon={faPlus} style={{ width: 20, height: 20 }} />
          <span>Agregar Refacción</span>
        </button>
      )}

      {/* ── Formulario ─────────────────────────────────────────────── */}
      {showForm && (
        <div className="space-y-4 p-4 bg-primary-50 dark:bg-primary-900/20 rounded-lg border border-primary-200 dark:border-primary-800">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Input
              label="Refacción"
              placeholder="Ej: Filtro de aceite"
              value={nombre}
              onChange={(e) => setNombre(e.target.value)}
              required
              disabled={disabled}
            />

            <Input
              label="Cantidad"
              type="number"
              placeholder="1"
              value={cantidad}
              onChange={(e) => setCantidad(e.target.value)}
              min="1"
              required
              disabled={disabled}
            />

            <Input
              label="Precio de Costo"
              type="number"
              placeholder="0.00"
              value={precioCosto}
              onChange={(e) => setPrecioCosto(e.target.value)}
              min="0"
              step="0.01"
              required
              disabled={disabled}
            />

            {/* ── Margen variable ── */}
            <div className="relative">
              <Input
                label="Margen de ganancia (%)"
                type="number"
                placeholder="30"
                value={margen}
                onChange={(e) => setMargen(e.target.value)}
                min="0"
                max="999"
                step="1"
                disabled={disabled}
              />
              <span className="absolute right-3 top-[38px] text-gray-400 text-sm pointer-events-none">%</span>
            </div>

            <Input
              label="Proveedor"
              placeholder="Ej: Autozone"
              value={proveedor}
              onChange={(e) => setProveedor(e.target.value)}
              disabled={disabled}
            />
          </div>

          {/* Preview del cálculo */}
          {previewOk && (
            <div className="p-4 bg-sag-50 dark:bg-sag-900/20 rounded-lg border border-sag-200 dark:border-sag-800 space-y-1.5 text-sm">
              <div className="flex justify-between">
                <span className="text-gray-600 dark:text-gray-400">Precio de Costo (unitario):</span>
                <span className="font-medium text-gray-900 dark:text-gray-100">
                  {formatCurrency(costoNum)}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-sag-700 dark:text-sag-300">+ Margen ({margenNum}%):</span>
                <span className="font-medium text-sag-700 dark:text-sag-300">
                  {formatCurrency(costoNum * (margenNum / 100))}
                </span>
              </div>
              <div className="h-px bg-sag-200 dark:bg-sag-700" />
              <div className="flex justify-between">
                <span className="font-semibold text-gray-900 dark:text-gray-100">Precio de Venta (unitario):</span>
                <span className="font-bold text-sag-600 dark:text-sag-400">
                  {formatCurrency(ventaUnit)}
                </span>
              </div>
              <div className="h-px bg-sag-200 dark:bg-sag-700" />
              <div className="flex justify-between text-base">
                <span className="font-semibold text-gray-900 dark:text-gray-100">
                  Total ({cantidadNum} pza{cantidadNum !== 1 ? 's' : ''}):
                </span>
                <span className="font-bold text-primary-600 dark:text-primary-400">
                  {formatCurrency(cantidadNum * ventaUnit)}
                </span>
              </div>
            </div>
          )}

          <div className="flex gap-3">
            <Button
              variant="success"
              onClick={handleAddRefaccion}
              icon={<FontAwesomeIcon icon={faPlus} style={{ width: 20, height: 20 }} />}
              disabled={disabled || !nombre || !cantidadNum || !costoNum || cantidadNum <= 0 || costoNum <= 0}
            >
              Agregar
            </Button>
            <Button
              variant="secondary"
              onClick={() => {
                setShowForm(false);
                setNombre('');
                setCantidad('');
                setPrecioCosto('');
                setProveedor('');
                setMargen('30');
              }}
              disabled={disabled}
            >
              Cancelar
            </Button>
          </div>
        </div>
      )}

      {refacciones.length === 0 && !showForm && (
        <p className="text-center text-gray-500 dark:text-gray-400 py-8">
          No hay refacciones agregadas. Haz clic en "Agregar Refacción" para comenzar.
        </p>
      )}
    </Card>
  );
};
