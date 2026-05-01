import { useState, useEffect, useCallback } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faEyeSlash, faPlus, faTrash, faSpinner } from '@fortawesome/free-solid-svg-icons';
import { gastosOrdenAPI } from '../../services/api';
import type { GastoOrden } from '../../types';

interface Props {
  ordenId: number;
}

const TIPO_LABELS: Record<GastoOrden['tipo'], string> = {
  envio: 'Envío',
  consumible: 'Consumible',
  propina: 'Propina',
  otro: 'Otro',
};

const TIPO_OPTIONS: GastoOrden['tipo'][] = ['envio', 'consumible', 'propina', 'otro'];

function formatMXN(value: number | string): string {
  return Number(value).toLocaleString('es-MX', { style: 'currency', currency: 'MXN' });
}

export const GastosInternosOrden = ({ ordenId }: Props) => {
  const [gastos, setGastos] = useState<GastoOrden[]>([]);
  const [total, setTotal] = useState(0);
  const [loadingLista, setLoadingLista] = useState(true);
  const [guardando, setGuardando] = useState(false);
  const [eliminandoId, setEliminandoId] = useState<number | null>(null);
  const [confirmandoId, setConfirmandoId] = useState<number | null>(null);
  const [errorMsg, setErrorMsg] = useState<string | null>(null);

  // Formulario
  const [tipo, setTipo] = useState<GastoOrden['tipo']>('otro');
  const [concepto, setConcepto] = useState('');
  const [monto, setMonto] = useState('');

  const cargar = useCallback(async () => {
    setLoadingLista(true);
    setErrorMsg(null);
    try {
      const data = await gastosOrdenAPI.listar(ordenId);
      setGastos(data.gastos.map((g: GastoOrden) => ({ ...g, monto: Number(g.monto) })));
      setTotal(Number(data.total));
    } catch {
      setErrorMsg('No se pudieron cargar los costos internos.');
    } finally {
      setLoadingLista(false);
    }
  }, [ordenId]);

  useEffect(() => {
    cargar();
  }, [cargar]);

  const handleAgregar = async () => {
    const montoNum = parseFloat(monto);
    if (!concepto.trim()) {
      setErrorMsg('El concepto no puede estar vacío.');
      return;
    }
    if (isNaN(montoNum) || montoNum <= 0) {
      setErrorMsg('El monto debe ser mayor a $0.');
      return;
    }

    setErrorMsg(null);
    setGuardando(true);
    try {
      const data = await gastosOrdenAPI.crear(ordenId, concepto.trim(), montoNum, tipo);
      const gastoNorm = { ...data.gasto, monto: Number(data.gasto.monto) };
      setGastos((prev) => [...prev, gastoNorm]);
      setTotal((prev) => prev + gastoNorm.monto);
      setConcepto('');
      setMonto('');
      setTipo('otro');
    } catch {
      setErrorMsg('Error al guardar el costo. Intenta de nuevo.');
    } finally {
      setGuardando(false);
    }
  };

  const handleEliminar = async (id: number) => {
    const gasto = gastos.find((g) => g.id === id);
    if (!gasto) return;

    setEliminandoId(id);
    setConfirmandoId(null);
    try {
      await gastosOrdenAPI.eliminar(id);
      setGastos((prev) => prev.filter((g) => g.id !== id));
      setTotal((prev) => parseFloat((prev - gasto.monto).toFixed(2)));
    } catch {
      setErrorMsg('Error al eliminar el costo. Intenta de nuevo.');
    } finally {
      setEliminandoId(null);
    }
  };

  return (
    <section className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700/40 rounded-2xl shadow-sm overflow-hidden">
      {/* Header */}
      <div className="px-6 py-4 border-b border-amber-200 dark:border-amber-700/40 flex items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <FontAwesomeIcon icon={faEyeSlash} style={{ width: 16, height: 16 }} className="text-amber-600 dark:text-amber-400 flex-shrink-0" />
          <span className="font-semibold text-gray-800 dark:text-gray-100">
            Costos internos del trabajo
          </span>
        </div>
        <span className="text-xs text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-full px-3 py-1 whitespace-nowrap">
          Solo visible en el sistema &middot; No aparece en el PDF del cliente
        </span>
      </div>

      <div className="px-6 py-4 space-y-4">
        {/* Error */}
        {errorMsg && (
          <p className="text-sm text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700/40 rounded-lg px-3 py-2">
            {errorMsg}
          </p>
        )}

        {/* Lista */}
        {loadingLista ? (
          <div className="flex items-center gap-2 text-gray-500 dark:text-gray-400 py-4">
            <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 16, height: 16 }} />
            <span className="text-sm">Cargando costos...</span>
          </div>
        ) : gastos.length === 0 ? (
          <p className="text-sm text-gray-500 dark:text-gray-400 py-2">
            Sin costos adicionales registrados.
          </p>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-gray-500 dark:text-gray-400 border-b border-amber-200 dark:border-amber-700/40">
                  <th className="pb-2 pr-4 font-medium w-28">Tipo</th>
                  <th className="pb-2 pr-4 font-medium">Concepto</th>
                  <th className="pb-2 pr-4 font-medium text-right">Monto</th>
                  <th className="pb-2 font-medium w-10"></th>
                </tr>
              </thead>
              <tbody>
                {gastos.map((g) => (
                  <tr
                    key={g.id}
                    className="border-b border-amber-100 dark:border-amber-800/30 last:border-0"
                  >
                    <td className="py-2 pr-4 text-gray-600 dark:text-gray-300">
                      {TIPO_LABELS[g.tipo]}
                    </td>
                    <td className="py-2 pr-4 text-gray-800 dark:text-gray-100">
                      {g.concepto}
                    </td>
                    <td className="py-2 pr-4 text-right text-gray-800 dark:text-gray-100 tabular-nums">
                      {formatMXN(g.monto)}
                    </td>
                    <td className="py-2 text-right">
                      {eliminandoId === g.id ? (
                        <FontAwesomeIcon icon={faSpinner} className="animate-spin text-gray-400" style={{ width: 14, height: 14 }} />
                      ) : confirmandoId === g.id ? (
                        <span className="inline-flex items-center gap-1">
                          <button
                            onClick={() => handleEliminar(g.id)}
                            className="text-xs font-medium text-white bg-red-500 hover:bg-red-600 rounded px-2 py-0.5 transition-colors"
                          >
                            Eliminar
                          </button>
                          <button
                            onClick={() => setConfirmandoId(null)}
                            className="text-xs font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white rounded px-2 py-0.5 transition-colors"
                          >
                            Cancelar
                          </button>
                        </span>
                      ) : (
                        <button
                          onClick={() => setConfirmandoId(g.id)}
                          title="Eliminar"
                          className="text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                        >
                          <FontAwesomeIcon icon={faTrash} style={{ width: 14, height: 14 }} />
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>

            {/* Total */}
            <div className="flex justify-end pt-3 border-t border-amber-200 dark:border-amber-700/40 mt-1">
              <span className="text-sm text-gray-500 dark:text-gray-400 mr-4">
                Total costos internos:
              </span>
              <span className="font-bold text-gray-900 dark:text-white tabular-nums">
                {formatMXN(total)}
              </span>
            </div>
          </div>
        )}

        {/* Formulario de agregar */}
        <div className="flex flex-wrap gap-2 pt-2 border-t border-amber-200 dark:border-amber-700/40">
          <select
            value={tipo}
            onChange={(e) => setTipo(e.target.value as GastoOrden['tipo'])}
            disabled={guardando}
            className="text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:opacity-50"
          >
            {TIPO_OPTIONS.map((t) => (
              <option key={t} value={t}>
                {TIPO_LABELS[t]}
              </option>
            ))}
          </select>

          <input
            type="text"
            placeholder="Concepto"
            value={concepto}
            onChange={(e) => setConcepto(e.target.value)}
            disabled={guardando}
            maxLength={300}
            className="flex-1 min-w-40 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:opacity-50"
          />

          <input
            type="number"
            placeholder="Monto"
            value={monto}
            onChange={(e) => setMonto(e.target.value)}
            disabled={guardando}
            min="0.01"
            step="0.01"
            className="w-28 text-sm border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-amber-400 disabled:opacity-50"
          />

          <button
            onClick={handleAgregar}
            disabled={guardando}
            className="flex items-center gap-1.5 text-sm font-medium bg-amber-500 hover:bg-amber-600 disabled:bg-amber-300 text-white rounded-lg px-4 py-2 transition-colors"
          >
            {guardando ? (
              <FontAwesomeIcon icon={faSpinner} className="animate-spin" style={{ width: 14, height: 14 }} />
            ) : (
              <FontAwesomeIcon icon={faPlus} style={{ width: 14, height: 14 }} />
            )}
            Agregar
          </button>
        </div>
      </div>
    </section>
  );
};
