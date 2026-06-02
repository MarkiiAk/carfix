import { useState, useEffect } from 'react';
import type { Sucursal } from '../../types';

interface SucursalForm {
  nombre: string;
  direccion: string;
  telefono: string;
}

const EMPTY_FORM: SucursalForm = { nombre: '', direccion: '', telefono: '' };

const API = (import.meta.env.VITE_API_URL ?? '') + '/backend-php';

function authHeaders(): Record<string, string> {
  const token = localStorage.getItem('token') ?? '';
  return { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` };
}

export function SucursalesPage() {
  const [sucursales, setSucursales] = useState<Sucursal[]>([]);
  const [loading, setLoading]       = useState(true);
  const [error, setError]           = useState<string | null>(null);
  const [showModal, setShowModal]   = useState(false);
  const [editTarget, setEditTarget] = useState<Sucursal | null>(null);
  const [form, setForm]             = useState<SucursalForm>(EMPTY_FORM);
  const [saving, setSaving]         = useState(false);

  const fetchSucursales = async () => {
    setLoading(true);
    try {
      const res = await fetch(`${API}/admin/sucursales`, { headers: authHeaders() });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error ?? 'Error al cargar sucursales');
      setSucursales(json.sucursales ?? []);
    } catch (e) {
      setError(String(e));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchSucursales(); }, []);

  const openCreate = () => {
    setEditTarget(null);
    setForm(EMPTY_FORM);
    setShowModal(true);
  };

  const openEdit = (s: Sucursal) => {
    setEditTarget(s);
    setForm({ nombre: s.nombre, direccion: s.direccion ?? '', telefono: s.telefono ?? '' });
    setShowModal(true);
  };

  const handleSave = async () => {
    if (!form.nombre.trim()) return;
    setSaving(true);
    try {
      const method  = editTarget ? 'PUT' : 'POST';
      const url     = editTarget ? `${API}/admin/sucursales/${editTarget.id}` : `${API}/admin/sucursales`;
      const res     = await fetch(url, { method, headers: authHeaders(), body: JSON.stringify(form) });
      const json    = await res.json();
      if (!res.ok) throw new Error(json.error ?? 'Error al guardar');
      setShowModal(false);
      await fetchSucursales();
    } catch (e) {
      alert(String(e));
    } finally {
      setSaving(false);
    }
  };

  const handleDesactivar = async (id: number) => {
    if (!confirm('¿Desactivar esta sucursal?')) return;
    try {
      const res  = await fetch(`${API}/admin/sucursales/${id}`, { method: 'DELETE', headers: authHeaders() });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error ?? 'Error al desactivar');
      await fetchSucursales();
    } catch (e) {
      alert(String(e));
    }
  };

  return (
    <div className="p-6 max-w-4xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-xl font-bold text-gray-900 dark:text-white">Sucursales</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">Gestiona las sucursales del sistema</p>
        </div>
        <button
          onClick={openCreate}
          className="flex items-center gap-2 px-4 py-2 bg-sag-500 text-gray-900 font-semibold rounded-lg text-sm hover:bg-sag-400 transition-colors"
        >
          + Nueva sucursal
        </button>
      </div>

      {loading && <p className="text-gray-500">Cargando...</p>}
      {error   && <p className="text-red-500">{error}</p>}

      {!loading && !error && (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500 dark:text-gray-400">
              <tr>
                <th className="px-4 py-3 text-left">ID</th>
                <th className="px-4 py-3 text-left">Nombre</th>
                <th className="px-4 py-3 text-left">Direccion</th>
                <th className="px-4 py-3 text-left">Telefono</th>
                <th className="px-4 py-3 text-left">Estado</th>
                <th className="px-4 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {sucursales.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-gray-400">Sin sucursales registradas</td>
                </tr>
              )}
              {sucursales.map((s) => (
                <tr key={s.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                  <td className="px-4 py-3 font-mono text-gray-500">{s.id}</td>
                  <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{s.nombre}</td>
                  <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{s.direccion ?? '—'}</td>
                  <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{s.telefono ?? '—'}</td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                      s.activo
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                        : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                    }`}>
                      {s.activo ? 'Activa' : 'Inactiva'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => openEdit(s)}
                        className="text-xs px-2.5 py-1 rounded border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                      >
                        Editar
                      </button>
                      {s.activo && s.id !== 1 && (
                        <button
                          onClick={() => handleDesactivar(Number(s.id))}
                          className="text-xs px-2.5 py-1 rounded border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                        >
                          Desactivar
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Modal */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6 w-full max-w-md mx-4">
            <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-4">
              {editTarget ? 'Editar sucursal' : 'Nueva sucursal'}
            </h2>

            <div className="space-y-4">
              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Nombre <span className="text-red-500">*</span>
                </label>
                <input
                  type="text"
                  value={form.nombre}
                  onChange={(e) => setForm((p) => ({ ...p, nombre: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sag-500 focus:border-transparent"
                  placeholder="Ej: Sucursal Norte"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Direccion</label>
                <input
                  type="text"
                  value={form.direccion}
                  onChange={(e) => setForm((p) => ({ ...p, direccion: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sag-500"
                  placeholder="Calle, colonia, ciudad"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Telefono</label>
                <input
                  type="text"
                  value={form.telefono}
                  onChange={(e) => setForm((p) => ({ ...p, telefono: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sag-500"
                  placeholder="+52 XXX XXX XXXX"
                />
              </div>
            </div>

            <div className="flex gap-3 justify-end mt-6">
              <button
                onClick={() => setShowModal(false)}
                className="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
                Cancelar
              </button>
              <button
                onClick={handleSave}
                disabled={saving || !form.nombre.trim()}
                className="px-4 py-2 text-sm bg-sag-500 text-gray-900 font-semibold rounded-lg hover:bg-sag-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {saving ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
