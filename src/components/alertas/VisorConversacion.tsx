import React, { useState, useEffect, useRef } from 'react';
import { X, MessageCircle, Loader2 } from 'lucide-react';
import api from '../../services/api';
import type { MensajeConversacion } from '../../types';

interface Props {
  alertaId: number;
  nombreCliente: string;
  onClose: () => void;
}

function formatHora(fechaStr: string): string {
  const fecha = new Date(fechaStr);
  return fecha.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', hour12: false });
}

function formatFechaCorta(fechaStr: string): string {
  const fecha = new Date(fechaStr);
  return fecha.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
}

/**
 * Agrupa mensajes consecutivos del mismo día para mostrar separadores de fecha.
 */
function agruparPorDia(mensajes: MensajeConversacion[]): { fecha: string; items: MensajeConversacion[] }[] {
  const grupos: { fecha: string; items: MensajeConversacion[] }[] = [];
  let diaActual = '';

  for (const msg of mensajes) {
    const dia = new Date(msg.created_at).toDateString();
    if (dia !== diaActual) {
      diaActual = dia;
      grupos.push({ fecha: formatFechaCorta(msg.created_at), items: [] });
    }
    grupos[grupos.length - 1].items.push(msg);
  }

  return grupos;
}

export const VisorConversacion: React.FC<Props> = ({ alertaId, nombreCliente, onClose }) => {
  const [mensajes, setMensajes] = useState<MensajeConversacion[]>([]);
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const endRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    let cancelado = false;

    const cargar = async () => {
      setCargando(true);
      setError(null);
      try {
        const resp = await api.get<{ success: boolean; mensajes: MensajeConversacion[]; error?: string }>(
          `/alertas/${alertaId}/conversacion`
        );
        if (cancelado) return;
        if (resp.data.success) {
          setMensajes(resp.data.mensajes ?? []);
        } else {
          setError(resp.data.error ?? 'Error al cargar la conversación');
        }
      } catch {
        if (!cancelado) setError('Error de conexión al cargar la conversación');
      } finally {
        if (!cancelado) setCargando(false);
      }
    };

    cargar();
    return () => { cancelado = true; };
  }, [alertaId]);

  // Scroll al fondo cuando cargan los mensajes
  useEffect(() => {
    if (!cargando && mensajes.length > 0) {
      endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }
  }, [cargando, mensajes]);

  // Cerrar con Escape
  useEffect(() => {
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
    };
    document.addEventListener('keydown', handleKey);
    return () => document.removeEventListener('keydown', handleKey);
  }, [onClose]);

  const grupos = agruparPorDia(mensajes);

  return (
    /* Overlay */
    <div
      className="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/60 backdrop-blur-sm p-0 sm:p-4"
      onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}
    >
      {/* Panel del modal */}
      <div className="bg-white dark:bg-gray-800 w-full sm:w-[480px] sm:max-w-full sm:rounded-2xl rounded-t-2xl shadow-2xl flex flex-col overflow-hidden"
        style={{ maxHeight: 'min(90vh, 700px)' }}
      >
        {/* Header */}
        <div className="flex items-center gap-3 px-4 py-3 bg-[#075E54] text-white flex-shrink-0">
          <div className="w-9 h-9 rounded-full bg-[#25D366] flex items-center justify-center flex-shrink-0">
            <MessageCircle size={18} className="text-white" />
          </div>
          <div className="flex-1 min-w-0">
            <p className="font-semibold text-sm leading-tight truncate">{nombreCliente}</p>
            <p className="text-xs text-white/70 leading-tight">Conversación WhatsApp</p>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 rounded-full hover:bg-white/20 transition-colors flex-shrink-0"
            aria-label="Cerrar conversación"
          >
            <X size={18} />
          </button>
        </div>

        {/* Cuerpo del chat */}
        <div
          className="flex-1 overflow-y-auto px-3 py-4 space-y-1"
          style={{ background: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'60\' height=\'60\'%3E%3C/svg%3E") #e5ddd5' }}
        >
          {cargando && (
            <div className="flex flex-col items-center justify-center h-32 gap-2">
              <Loader2 size={28} className="animate-spin text-[#075E54]" />
              <p className="text-sm text-gray-600">Cargando conversación...</p>
            </div>
          )}

          {!cargando && error && (
            <div className="flex flex-col items-center justify-center h-32 gap-2 text-center px-4">
              <MessageCircle size={28} className="text-gray-400" />
              <p className="text-sm text-gray-600">{error}</p>
            </div>
          )}

          {!cargando && !error && mensajes.length === 0 && (
            <div className="flex flex-col items-center justify-center h-32 gap-2 text-center px-4">
              <MessageCircle size={28} className="text-gray-400" />
              <p className="text-sm text-gray-600">Sin conversación registrada</p>
              <p className="text-xs text-gray-400">Aún no se han enviado mensajes para esta alerta</p>
            </div>
          )}

          {!cargando && !error && grupos.map((grupo) => (
            <div key={grupo.fecha}>
              {/* Separador de fecha */}
              <div className="flex items-center justify-center my-3">
                <span className="bg-[#e1f3fb] dark:bg-gray-700 text-gray-600 dark:text-gray-300 text-xs px-3 py-1 rounded-full shadow-sm">
                  {grupo.fecha}
                </span>
              </div>

              {/* Mensajes del día */}
              {grupo.items.map((msg) => {
                const esSaliente = msg.direction === 'outbound';
                return (
                  <div
                    key={msg.id}
                    className={`flex mb-1 ${esSaliente ? 'justify-end' : 'justify-start'}`}
                  >
                    <div
                      className={`
                        max-w-[78%] px-3 py-2 rounded-lg shadow-sm text-sm relative
                        ${esSaliente
                          ? 'bg-[#dcf8c6] text-gray-800 rounded-tr-none'
                          : 'bg-white dark:bg-gray-700 text-gray-800 dark:text-gray-100 rounded-tl-none'
                        }
                      `}
                    >
                      {/* Etiqueta de paso de conversación — solo en mensajes del bot */}
                      {esSaliente && msg.conversation_step && (
                        <p className="text-[10px] text-gray-400 mb-0.5 leading-none">
                          {msg.conversation_step.replace(/_/g, ' ')}
                        </p>
                      )}

                      {/* Texto del mensaje */}
                      <p className="whitespace-pre-wrap break-words leading-snug">
                        {msg.mensaje ?? <span className="italic text-gray-400">[mensaje sin texto]</span>}
                      </p>

                      {/* Pie de burbuja: hora y estado */}
                      <div className={`flex items-center gap-1 mt-1 ${esSaliente ? 'justify-end' : 'justify-start'}`}>
                        <span className="text-[10px] text-gray-400 leading-none">
                          {formatHora(msg.created_at)}
                        </span>
                        {esSaliente && msg.estado && (
                          <span className="text-[10px] text-gray-400 leading-none">
                            · {msg.estado}
                          </span>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          ))}

          <div ref={endRef} />
        </div>

        {/* Footer — solo lectura */}
        <div className="px-4 py-2.5 bg-[#f0f0f0] dark:bg-gray-700 border-t border-gray-200 dark:border-gray-600 flex-shrink-0">
          <p className="text-xs text-center text-gray-500 dark:text-gray-400">
            Solo lectura — las respuestas se gestionan por WhatsApp
          </p>
        </div>
      </div>
    </div>
  );
};
