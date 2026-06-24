import { useState, useEffect } from 'react';
import type { Sucursal } from '../../types';

interface UsuarioRow {
  id: number;
  username: string;
  nombre_completo: string | null;
  email: string | null;
  rol: string;
  activo: boolean;
  sucursales: Sucursal[];
}

interface UsuarioForm {
  username: string;
  nombre_completo: string;
  email: string;
  password: string;
  rol: string;
}

const EMPTY_FORM: UsuarioForm = {
  username: '',
  nombre_completo: '',
  email: '',
  password: '',
  rol: 'admin_sucursal',
};

const API = (import.meta.env.VITE_API_URL ?? '') + '/backend-php';

function authHeaders(): Record<string, string> {
  return {
    'Content-Type': 'application/json',
    Authorization: `Bearer ${localStorage.getItem('token') ?? ''}`,
  };
}

const ROLES = [
  { value: 'sistemas',       label: 'Sistemas (SaaS admin)' },
  { value: 'superusuario',   label: 'Superusuario (Dueño del taller)' },
  { value: 'admin_sucursal', label: 'Admin sucursal (Gerente)' },
  { value: 'asistente',      label: 'Asistente (Sin módulo financiero)' },
];

export function UsuariosPage() {
  const [usuarios, setUsuarios]       = useState<UsuarioRow[]>([]);
  const [sucursales, setSucursales]   = useState<Sucursal[]>([]);
  const [loading, setLoading]         = useState(true);
  const [error, setError]             = useState<string | null>(null);
  const [showModal, setShowModal]     = useState(false);
  const [form, setForm]               = useState<UsuarioForm>(EMPTY_FORM);
  const [saving, setSaving]           = useState(false);
  // Para gestionar sucursales de un usuario
  const [gestionTarget, setGestionTarget] = useState<UsuarioRow | null>(null);
  const [sucursalAsignar, setSucursalAsignar] = useState<number>(0);
  const [pwTarget, setPwTarget]   = useState<UsuarioRow | null>(null);
  const [pwNueva, setPwNueva]     = useState('');
  const [pwConfirm, setPwConfirm] = useState('');
  const [pwSaving, setPwSaving]   = useState(false);

  const fetchTodo = async () => {
    setLoading(true);
    try {
      const [resU, resS] = await Promise.all([
        fetch(`${API}/admin/usuarios`,   { headers: authHeaders() }),
        fetch(`${API}/admin/sucursales`, { headers: authHeaders() }),
      ]);
      const jsonU = await resU.json();
      const jsonS = await resS.json();
      if (!resU.ok) throw new Error(jsonU.error ?? 'Error al cargar usuarios');
      setUsuarios(jsonU.usuarios ?? []);
      setSucursales(jsonS.sucursales ?? []);
    } catch (e) {
      setError(String(e));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { fetchTodo(); }, []);

  const handleCrear = async () => {
    if (!form.username.trim() || !form.password.trim()) return;
    setSaving(true);
    try {
      const res  = await fetch(`${API}/admin/usuarios`, {
        method: 'POST',
        headers: authHeaders(),
        body: JSON.stringify(form),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error ?? 'Error al crear usuario');
      setShowModal(false);
      setForm(EMPTY_FORM);
      await fetchTodo();
    } catch (e) {
      alert(String(e));
    } finally {
      setSaving(false);
    }
  };

  const handleToggle = async (u: UsuarioRow) => {
    try {
      const res  = await fetch(`${API}/admin/usuarios/${u.id}`, {
        method: 'PUT',
        headers: authHeaders(),
        body: JSON.stringify({ activo: !u.activo }),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error ?? 'Error al actualizar');
      await fetchTodo();
    } catch (e) {
      alert(String(e));
    }
  };

  const handleAsignarSucursal = async () => {
    if (!gestionTarget || sucursalAsignar <= 0) return;
    try {
      const res  = await fetch(`${API}/admin/usuarios/${gestionTarget.id}/sucursal`, {
        method: 'POST',
        headers: authHeaders(),
        body: JSON.stringify({ sucursal_id: sucursalAsignar }),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error ?? 'Error al asignar');
      await fetchTodo();
      // Actualizar gestion target
      const actualizado = usuarios.find((u) => u.id === gestionTarget.id);
      if (actualizado) setGestionTarget(actualizado);
    } catch (e) {
      alert(String(e));
    }
  };

  const handleRemoverSucursal = async (userId: number, sucursalId: number) => {
    try {
      const res  = await fetch(`${API}/admin/usuarios/${userId}/sucursal/${sucursalId}`, {
        method: 'DELETE',
        headers: authHeaders(),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error ?? 'Error al remover');
      await fetchTodo();
    } catch (e) {
      alert(String(e));
    }
  };

  const handleCambiarPassword = async () => {
    if (!pwTarget) return;
    if (pwNueva.length < 6) { alert('La contraseña debe tener al menos 6 caracteres'); return; }
    if (pwNueva !== pwConfirm) { alert('Las contraseñas no coinciden'); return; }
    setPwSaving(true);
    try {
      const res  = await fetch(`${API}/admin/usuarios/${pwTarget.id}/password`, {
        method: 'PUT',
        headers: authHeaders(),
        body: JSON.stringify({ nueva_password: pwNueva }),
      });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error ?? 'Error al cambiar contraseña');
      setPwTarget(null);
      setPwNueva('');
      setPwConfirm('');
    } catch (e) {
      alert(String(e));
    } finally {
      setPwSaving(false);
    }
  };

  const sucursalesDisponibles = sucursales.filter(
    (s) => s.activo && !gestionTarget?.sucursales.some((gs) => gs.id === s.id),
  );

  return (
    <div className="p-6 max-w-5xl mx-auto">
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-xl font-bold text-gray-900 dark:text-white">Usuarios</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">Gestiona los usuarios y sus accesos</p>
        </div>
        <button
          onClick={() => { setForm(EMPTY_FORM); setShowModal(true); }}
          className="flex items-center gap-2 px-4 py-2 bg-sag-500 text-gray-900 font-semibold rounded-lg text-sm hover:bg-sag-400 transition-colors"
        >
          + Nuevo usuario
        </button>
      </div>

      {loading && <p className="text-gray-500">Cargando...</p>}
      {error   && <p className="text-red-500">{error}</p>}

      {!loading && !error && (
        <div className="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase text-gray-500 dark:text-gray-400">
              <tr>
                <th className="px-4 py-3 text-left">Usuario</th>
                <th className="px-4 py-3 text-left">Nombre</th>
                <th className="px-4 py-3 text-left">Rol</th>
                <th className="px-4 py-3 text-left">Sucursales</th>
                <th className="px-4 py-3 text-left">Estado</th>
                <th className="px-4 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
              {usuarios.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-gray-400">Sin usuarios registrados</td>
                </tr>
              )}
              {usuarios.map((u) => (
                <tr key={u.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                  <td className="px-4 py-3 font-mono text-gray-700 dark:text-gray-200">{u.username}</td>
                  <td className="px-4 py-3 text-gray-600 dark:text-gray-300">{u.nombre_completo ?? '—'}</td>
                  <td className="px-4 py-3">
                    <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                      {u.rol}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs">
                    {u.sucursales.length > 0
                      ? u.sucursales.map((s) => s.nombre).join(', ')
                      : 'Todas'}
                  </td>
                  <td className="px-4 py-3">
                    <span className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${
                      u.activo
                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                        : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'
                    }`}>
                      {u.activo ? 'Activo' : 'Inactivo'}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button
                        onClick={() => setGestionTarget(u)}
                        className="text-xs px-2.5 py-1 rounded border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                      >
                        Sucursales
                      </button>
                      <button
                        onClick={() => { setPwTarget(u); setPwNueva(''); setPwConfirm(''); }}
                        className="text-xs px-2.5 py-1 rounded border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                      >
                        Cambiar contraseña
                      </button>
                      <button
                        onClick={() => handleToggle(u)}
                        className={`text-xs px-2.5 py-1 rounded border transition-colors ${
                          u.activo
                            ? 'border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'
                            : 'border-green-200 dark:border-green-800 text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/20'
                        }`}
                      >
                        {u.activo ? 'Desactivar' : 'Activar'}
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Modal nuevo usuario */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6 w-full max-w-md mx-4">
            <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-4">Nuevo usuario</h2>

            <div className="space-y-3">
              {[
                { key: 'username',        label: 'Username',       type: 'text',     required: true  },
                { key: 'password',        label: 'Password',       type: 'password', required: true  },
                { key: 'nombre_completo', label: 'Nombre completo',type: 'text',     required: false },
                { key: 'email',           label: 'Email',          type: 'email',    required: false },
              ].map(({ key, label, type, required }) => (
                <div key={key}>
                  <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {label} {required && <span className="text-red-500">*</span>}
                  </label>
                  <input
                    type={type}
                    value={form[key as keyof UsuarioForm]}
                    onChange={(e) => setForm((p) => ({ ...p, [key]: e.target.value }))}
                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sag-500"
                  />
                </div>
              ))}

              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Rol</label>
                <select
                  value={form.rol}
                  onChange={(e) => setForm((p) => ({ ...p, rol: e.target.value }))}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sag-500"
                >
                  {ROLES.map((r) => (
                    <option key={r.value} value={r.value}>{r.label}</option>
                  ))}
                </select>
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
                onClick={handleCrear}
                disabled={saving || !form.username.trim() || !form.password.trim()}
                className="px-4 py-2 text-sm bg-sag-500 text-gray-900 font-semibold rounded-lg hover:bg-sag-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {saving ? 'Guardando...' : 'Crear'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal cambiar contraseña */}
      {pwTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6 w-full max-w-sm mx-4">
            <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-1">Cambiar contraseña</h2>
            <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
              Usuario: <span className="font-mono font-medium text-gray-800 dark:text-gray-200">{pwTarget.username}</span>
            </p>

            <div className="space-y-3">
              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Nueva contraseña <span className="text-red-500">*</span>
                </label>
                <input
                  type="password"
                  value={pwNueva}
                  onChange={(e) => setPwNueva(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sag-500"
                  placeholder="Mínimo 6 caracteres"
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">
                  Confirmar contraseña <span className="text-red-500">*</span>
                </label>
                <input
                  type="password"
                  value={pwConfirm}
                  onChange={(e) => setPwConfirm(e.target.value)}
                  className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-sag-500"
                  placeholder="Repite la contraseña"
                />
              </div>
              {pwNueva.length > 0 && pwConfirm.length > 0 && pwNueva !== pwConfirm && (
                <p className="text-xs text-red-500">Las contraseñas no coinciden</p>
              )}
            </div>

            <div className="flex gap-3 justify-end mt-6">
              <button
                onClick={() => setPwTarget(null)}
                className="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
                Cancelar
              </button>
              <button
                onClick={handleCambiarPassword}
                disabled={pwSaving || pwNueva.length < 6 || pwNueva !== pwConfirm}
                className="px-4 py-2 text-sm bg-sag-500 text-gray-900 font-semibold rounded-lg hover:bg-sag-400 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {pwSaving ? 'Guardando...' : 'Guardar'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Panel gestionar sucursales */}
      {gestionTarget && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
          <div className="bg-white dark:bg-gray-800 rounded-xl shadow-2xl p-6 w-full max-w-md mx-4">
            <h2 className="text-lg font-bold text-gray-900 dark:text-white mb-1">
              Sucursales de {gestionTarget.username}
            </h2>
            <p className="text-xs text-gray-500 dark:text-gray-400 mb-4">
              {gestionTarget.rol === 'sistemas' || gestionTarget.rol === 'superusuario'
                ? 'Este rol tiene acceso a todas las sucursales automaticamente.'
                : 'Asigna o remueve accesos por sucursal.'}
            </p>

            {/* Sucursales asignadas */}
            <div className="space-y-2 mb-4">
              {gestionTarget.sucursales.length === 0 && (
                <p className="text-sm text-gray-400">Sin sucursales asignadas</p>
              )}
              {gestionTarget.sucursales.map((s) => (
                <div key={s.id} className="flex items-center justify-between px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-700">
                  <span className="text-sm text-gray-800 dark:text-gray-200">{s.nombre}</span>
                  <button
                    onClick={() => handleRemoverSucursal(gestionTarget.id, Number(s.id))}
                    className="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-300"
                  >
                    Remover
                  </button>
                </div>
              ))}
            </div>

            {/* Asignar nueva */}
            {sucursalesDisponibles.length > 0 && (
              <div className="flex gap-2 mb-4">
                <select
                  value={sucursalAsignar}
                  onChange={(e) => setSucursalAsignar(Number(e.target.value))}
                  className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-gray-700 text-gray-900 dark:text-white"
                >
                  <option value={0}>Seleccionar sucursal...</option>
                  {sucursalesDisponibles.map((s) => (
                    <option key={s.id} value={s.id}>{s.nombre}</option>
                  ))}
                </select>
                <button
                  onClick={handleAsignarSucursal}
                  disabled={sucursalAsignar <= 0}
                  className="px-3 py-2 text-sm bg-sag-500 text-gray-900 font-semibold rounded-lg hover:bg-sag-400 transition-colors disabled:opacity-50"
                >
                  Asignar
                </button>
              </div>
            )}

            <div className="flex justify-end">
              <button
                onClick={() => setGestionTarget(null)}
                className="px-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
              >
                Cerrar
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
