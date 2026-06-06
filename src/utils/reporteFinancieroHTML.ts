import type {
  ResumenFinancieroResponse,
  OrdenesFinancieroResponse,
  EmpleadoSueldo,
  PagoFijo,
  GastosAdminResponse,
  CajaChicaResponse,
} from '../types';

interface ReporteParams {
  resumen: ResumenFinancieroResponse;
  ordenes: OrdenesFinancieroResponse;
  empleados: EmpleadoSueldo[];
  pagosFijos: PagoFijo[];
  gastos: GastosAdminResponse;
  cajaChica: CajaChicaResponse | null;
  labelPeriodo: string;
  tipoPeriodo: 'semana' | 'mes';
}

export function abrirReporteFinanciero(params: ReporteParams): void {
  const { resumen, ordenes, empleados, pagosFijos, gastos, cajaChica, labelPeriodo, tipoPeriodo } = params;

  // -----------------------------------------------------------------------
  // Cálculos financieros (misma lógica que Financiero.tsx)
  // -----------------------------------------------------------------------
  const ingresosBrutos    = resumen?.resumen?.total_facturado    ?? 0;
  const ingresosServicios = resumen?.resumen?.ingresos_servicios ?? 0;
  const ingresosMO        = resumen?.resumen?.ingresos_mano_obra ?? 0;
  const ingresosRefas     = resumen?.resumen?.ingresos_refacciones ?? 0;
  const numOrdenes        = resumen?.resumen?.num_ordenes ?? 0;
  const costoRefacciones  = resumen?.refacciones?.costo ?? 0;
  const ingresoNeto       = ingresosBrutos - costoRefacciones;
  const ivaCobrado        = resumen?.resumen?.total_iva ?? 0;

  const pagoSemanalEmp = (e: EmpleadoSueldo): number => {
    const dias = Number(e.dias_trabajados ?? 5);
    if (dias === 0) return 0;
    const tipo = e.tipo_sueldo ?? 'diario';
    if (tipo === 'semanal') return Number(e.sueldo_diario);
    return Number(e.sueldo_diario) * dias;
  };

  const totalSueldos = empleados
    .reduce((acc, e) => acc + pagoSemanalEmp(e) * (tipoPeriodo === 'semana' ? 1 : 4), 0);

  const totalFijos = pagosFijos
    .filter(p => p.activo)
    .reduce((acc, p) => {
      if (p.frecuencia === 'semanal') return acc + p.monto * (tipoPeriodo === 'semana' ? 1 : 4);
      return acc + (tipoPeriodo === 'semana' ? p.monto / 4 : p.monto);
    }, 0);

  const totalVariables = Number(gastos?.total_admin ?? 0);
  const gastosOrdenes  = Number(gastos?.gastos_ordenes_mes ?? 0);
  const gananciaNeta   = ingresoNeto - totalSueldos - totalFijos - totalVariables - gastosOrdenes;

  // -----------------------------------------------------------------------
  // Helpers
  // -----------------------------------------------------------------------
  const fmt = (n: number): string =>
    new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 0 }).format(n);

  const fmtFecha = (iso: string): string => {
    const d = iso.split('T')[0].split(' ')[0];
    const [, m, day] = d.split('-');
    return `${day.replace(/^0/, '')}/${m}`;
  };

  const estadoLabel: Record<string, string> = {
    completada: 'Entregada', completado: 'Entregada',
    entregada: 'Entregada', entregado: 'Entregada', cerrada: 'Cerrada',
    en_proceso: 'En proceso', abierta: 'Abierta', pendiente: 'Pendiente',
  };

  const fechaGeneracion = new Date().toLocaleDateString('es-MX', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });

  const pct = (v: number): string =>
    ingresosBrutos > 0 ? Math.max(0, (v / ingresosBrutos) * 100).toFixed(1) : '0';

  // -----------------------------------------------------------------------
  // Sección 1 — Barra de distribución
  // -----------------------------------------------------------------------
  const pctSueldos     = pct(totalSueldos);
  const pctRefacciones = pct(costoRefacciones);
  const pctFijos       = pct(totalFijos);
  const pctVariables   = pct(totalVariables + gastosOrdenes);
  const pctGanancia    = pct(Math.max(0, gananciaNeta));

  // -----------------------------------------------------------------------
  // Sección 2 — Tabla de órdenes desglosada por ítem (igual que UI)
  // -----------------------------------------------------------------------
  const ESTADOS_ABIERTOS = new Set(['en_proceso','en_revision','pendiente','cotizacion','en_espera','abierta','en proceso']);

  const filasOrdenes = (ordenes?.ordenes ?? []).map(o => {
    const tipoFila = o.tipo_fila ?? (ESTADOS_ABIERTOS.has(o.estado?.toLowerCase() ?? '') ? 'apertura' : 'cierre');
    const gananciaColor = o.ganancia < 0 ? 'color:#dc2626' : 'color:#16a34a';
    const totalCompra = (o.costo_refacciones ?? 0) + (o.costo_interno ?? 0);
    const estadoBadge = estadoLabel[o.estado] ?? o.estado;

    const tipoChip = tipoFila === 'anticipo'
      ? '<span style="background:#dbeafe;color:#1d4ed8;font-size:9px;padding:1px 5px;border-radius:3px;font-weight:700;display:inline-block;margin-top:2px">anticipo ✓</span>'
      : tipoFila === 'cierre'
      ? '<span style="background:#dcfce7;color:#15803d;font-size:9px;padding:1px 5px;border-radius:3px;font-weight:700;display:inline-block;margin-top:2px">restante</span>'
      : tipoFila === 'apertura'
      ? '<span style="background:#fef3c7;color:#92400e;font-size:9px;padding:1px 5px;border-radius:3px;font-weight:700;display:inline-block;margin-top:2px">anticipo</span>'
      : '';

    // Construir ítems igual que buildItems() en TablaOrdenesDesglosada
    type Item = { concepto: string; costoVenta: number | null; costoCompra: number | null; tipo: string; proveedor?: string | null };
    const items: Item[] = [
      ...(o.servicios ?? []).map(s => ({ concepto: s.descripcion, costoVenta: s.subtotal, costoCompra: null, tipo: 'servicio' })),
      ...(o.refacciones_detalle ?? []).map(r => {
        const costo = r.precio_costo != null && r.cantidad != null ? r.precio_costo * r.cantidad : r.subtotal / 1.30;
        return { concepto: r.descripcion, costoVenta: r.subtotal, costoCompra: costo, tipo: 'refaccion', proveedor: r.proveedor };
      }),
    ];
    if ((o.iva ?? 0) > 0) items.push({ concepto: 'IVA (16%)', costoVenta: o.iva!, costoCompra: null, tipo: 'iva' });
    (o.gastos_internos ?? []).forEach(g => items.push({ concepto: g.concepto, costoVenta: null, costoCompra: g.monto, tipo: 'costo_interno' }));
    if ((o.gastos_internos ?? []).length === 0 && (o.costo_interno ?? 0) > 0)
      items.push({ concepto: 'Costos internos', costoVenta: null, costoCompra: o.costo_interno!, tipo: 'costo_interno' });

    const celdasFijas = (rowspan: number) => `
        <td rowspan="${rowspan}" style="padding:7px 8px;color:#4a5e50;font-size:11px;vertical-align:top;border-right:1px solid #f0f0f0;white-space:nowrap">
          ${fmtFecha(o.fecha)}<br>${tipoChip}
        </td>
        <td rowspan="${rowspan}" style="padding:7px 8px;font-size:11px;vertical-align:top;border-right:1px solid #f0f0f0;min-width:120px;max-width:150px">
          <div style="font-weight:600;color:#111a13;font-size:11px">${escapeHtml(o.vehiculo || '—')}</div>
          <div style="color:#4a5e50;font-size:10px;margin-top:1px">${escapeHtml(o.cliente_nombre)}</div>
          <div style="font-size:9px;color:#8a9e90;margin-top:2px">${estadoBadge}</div>
        </td>`;

    if (items.length === 0) {
      return `
      <tr style="border-bottom:1px solid #e3e8e0">
        ${celdasFijas(1)}
        <td style="padding:7px 8px;color:#8a9e90;font-size:11px">—</td>
        <td style="padding:7px 8px;text-align:right;font-size:11px">${fmt(o.costo_venta)}</td>
        <td style="padding:7px 8px;text-align:right;font-size:11px;color:#8a9e90">${totalCompra > 0 ? fmt(totalCompra) : '—'}</td>
        <td style="padding:7px 8px;text-align:right;font-size:11px;font-weight:600;${gananciaColor}">${fmt(o.ganancia)}</td>
      </tr>`;
    }

    const totalRows = items.length + 1;
    const itemRows = items.map((item, idx) => `
      <tr style="border-bottom:1px solid #f5f5f5">
        ${idx === 0 ? celdasFijas(totalRows) : ''}
        <td style="padding:5px 8px;font-size:11px;vertical-align:top">
          ${item.tipo === 'costo_interno' || item.tipo === 'iva'
            ? `<span style="font-style:italic;color:#8a9e90">${escapeHtml(item.concepto)}</span>`
            : `<span style="color:#111a13">${escapeHtml(item.concepto)}</span>`}
          ${item.tipo === 'refaccion' && item.proveedor ? `<div style="font-size:9px;color:#8a9e90;margin-top:1px">${escapeHtml(item.proveedor)}</div>` : ''}
        </td>
        <td style="padding:5px 8px;text-align:right;font-size:11px;vertical-align:top">
          ${item.tipo === 'iva' ? `<span style="color:#8a9e90;font-style:italic">${fmt(item.costoVenta!)}</span>`
            : (item.tipo === 'servicio' || item.tipo === 'refaccion') && item.costoVenta != null ? `<span style="color:#374151">${fmt(item.costoVenta)}</span>`
            : ''}
        </td>
        <td style="padding:5px 8px;text-align:right;font-size:11px;vertical-align:top">
          ${item.tipo === 'refaccion' && item.costoCompra != null ? `<span style="color:#8a9e90">${fmt(item.costoCompra)}</span>`
            : item.tipo === 'costo_interno' && item.costoCompra != null ? `<span style="color:#dc2626">${fmt(item.costoCompra)}</span>`
            : ''}
        </td>
        <td style="padding:5px 8px;vertical-align:top"></td>
      </tr>`).join('');

    const totalRow = `
      <tr style="border-bottom:2px solid #d1d5db;background:#f9fafb">
        <td style="padding:7px 8px;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.5px">Total</td>
        <td style="padding:7px 8px;text-align:right;font-size:11px;font-weight:700;color:#111a13">${fmt(o.costo_venta)}</td>
        <td style="padding:7px 8px;text-align:right;font-size:11px;color:#8a9e90">${totalCompra > 0 ? fmt(totalCompra) : '—'}</td>
        <td style="padding:7px 8px;text-align:right;font-size:11px;font-weight:700;${gananciaColor}">${fmt(o.ganancia)}</td>
      </tr>`;

    return itemRows + totalRow;
  }).join('');

  const totOrd = ordenes?.totales ?? { costo_venta: 0, costo_refacciones: 0, ganancia: 0 };

  // -----------------------------------------------------------------------
  // Sección 5 — Equipo y gastos (appendix)
  // -----------------------------------------------------------------------
  const empleadosActivos = empleados.filter(e => Number(e.dias_trabajados ?? 5) > 0);
  const fijosActivos = pagosFijos.filter(p => p.activo);

  // -----------------------------------------------------------------------
  // Caja chica
  // -----------------------------------------------------------------------
  const cajaSectionHTML = cajaChica ? `
    <div style="background:#fff;border:1px solid #e3e8e0;border-radius:12px;padding:20px;margin-top:20px">
      <h3 style="font-size:12px;font-weight:700;color:#0f2318;margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid #CBF518;padding-bottom:6px">Caja Chica</h3>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px">
        <div style="text-align:center;padding:10px;background:#f5f6f2;border-radius:8px">
          <div style="font-size:10px;color:#8a9e90;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Saldo anterior</div>
          <div style="font-size:15px;font-weight:700;color:#111a13">${fmt(cajaChica.saldo_anterior)}</div>
        </div>
        <div style="text-align:center;padding:10px;background:#f0fdf4;border-radius:8px">
          <div style="font-size:10px;color:#8a9e90;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Entradas</div>
          <div style="font-size:15px;font-weight:700;color:#16a34a">${fmt(cajaChica.ingresos_semana)}</div>
        </div>
        <div style="text-align:center;padding:10px;background:#fef2f2;border-radius:8px">
          <div style="font-size:10px;color:#8a9e90;margin-bottom:4px;text-transform:uppercase;letter-spacing:.4px">Salidas</div>
          <div style="font-size:15px;font-weight:700;color:#dc2626">${fmt(cajaChica.egresos_semana)}</div>
        </div>
      </div>
      <div style="padding:12px 16px;border-radius:8px;background:${cajaChica.saldo_actual < 0 ? '#fef2f2' : '#f0fdf4'};display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:#4a5e50;font-weight:600">Saldo actual</span>
        <span style="font-size:20px;font-weight:800;color:${cajaChica.saldo_actual < 0 ? '#dc2626' : '#16a34a'}">${fmt(cajaChica.saldo_actual)}</span>
      </div>
    </div>` : '';

  // -----------------------------------------------------------------------
  // Nota IVA
  // -----------------------------------------------------------------------
  const notaIVA = ivaCobrado > 0 ? `
    <div style="background:#fffbeb;border:1px solid #d97706;border-radius:8px;padding:12px 16px;margin-top:16px;font-size:12px;color:#92400e">
      &#x26A0;&#xFE0F; IVA recaudado este período: <strong>${fmt(ivaCobrado)}</strong> &middot; No es ganancia del taller &mdash; va al SAT en la declaración mensual.
    </div>` : '';

  // -----------------------------------------------------------------------
  // HTML completo
  // -----------------------------------------------------------------------
  const htmlCompleto = `<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reporte Financiero &mdash; ${escapeHtml(labelPeriodo)}</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  background: #f5f6f2;
  color: #111a13;
  font-size: 13px;
  line-height: 1.45;
}

/* Botón imprimir */
.no-print {
  position: fixed; top: 16px; right: 16px; z-index: 999;
  background: #CBF518; color: #0f2318; border: none; border-radius: 8px;
  padding: 10px 18px; font-size: 13px; font-weight: 700; cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.no-print:hover { opacity: .9; }

/* Layout */
.page { max-width: 900px; margin: 0 auto; padding-bottom: 48px; }

/* Header */
.header {
  background: #0f2318; height: 80px;
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 28px; margin-bottom: 20px;
}
.header-brand .name { font-size: 22px; font-weight: 900; color: #CBF518; letter-spacing: -.5px; }
.header-brand .sub  { font-size: 11px; color: #9ab5a0; margin-top: 2px; }
.header-center .titulo { font-size: 13px; font-weight: 700; color: #CBF518; text-align: center; letter-spacing: 1px; }
.header-center .periodo { font-size: 11px; color: #fff; text-align: center; margin-top: 3px; }
.header-fecha { font-size: 10px; color: #9ab5a0; text-align: right; }

/* Secciones */
.s { padding: 0 24px; margin-bottom: 20px; }

/* Barra distribución */
.dist-title { font-size: 10px; font-weight: 700; color: #8a9e90; text-transform: uppercase; letter-spacing: .8px; margin-bottom: 10px; }
.dist-bar { height: 14px; border-radius: 7px; overflow: hidden; display: flex; width: 100%; background: #e3e8e0; }
.dist-seg { height: 100%; }
.dist-legend { display: flex; flex-wrap: wrap; gap: 8px 20px; margin-top: 10px; }
.dist-legend-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: #4a5e50; }
.dist-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }

/* Tabla órdenes */
.tabla-title { font-size: 10px; font-weight: 700; color: #8a9e90; text-transform: uppercase; letter-spacing: .8px; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e3e8e0; border-radius: 10px; overflow: hidden; }
thead { background: #f5f6f2; }
th { padding: 9px 10px; text-align: left; font-size: 10px; font-weight: 700; color: #8a9e90; text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid #e3e8e0; }
th.r { text-align: right; } th.c { text-align: center; }
tr.fila-total { background: #0f2318; color: #fff; }
tr.fila-total td { padding: 10px; font-weight: 700; font-size: 12px; }
tr.fila-total td.lime { color: #CBF518; text-align: right; }

/* Balance hero */
.hero-card {
  background: #111827; border-radius: 16px; padding: 22px 28px; margin-bottom: 4px;
}
.hero-label { font-size: 10px; color: #9ca3af; letter-spacing: .8px; text-transform: uppercase; margin-bottom: 6px; }
.hero-valor { font-size: 48px; font-weight: 900; line-height: 1; font-variant-numeric: tabular-nums; }

/* Balance cascada */
.balance-card {
  background: #fff; border: 1px solid #e3e8e0; border-radius: 12px; padding: 22px 24px;
}
.bal-title { font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 16px; }
.bal-row { display: flex; justify-content: space-between; align-items: baseline; padding: 5px 0; font-size: 12px; color: #6b7280; }
.bal-row.indent { padding-left: 16px; }
.bal-row.subtotal { font-weight: 600; color: #374151; border-top: 1px solid #e5e7eb; margin-top: 4px; padding-top: 10px; }
.bal-dashed { border-top: 1px dashed #e5e7eb; margin: 12px 0; }
.bal-total { display: flex; justify-content: space-between; align-items: baseline; padding: 12px 0 0; font-size: 13px; font-weight: 800; border-top: 2px solid #d1d5db; margin-top: 6px; color: #111827; }
.bal-nota { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 12px; }

/* KPI cards al fondo */
.kpis { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; }
.kpi { background: #fff; border: 1px solid #e3e8e0; border-radius: 10px; padding: 16px; }
.kpi.destacado { border-color: #CBF518; }
.kpi-label { font-size: 10px; font-weight: 700; color: #8a9e90; letter-spacing: .8px; text-transform: uppercase; margin-bottom: 6px; }
.kpi-valor { font-size: 20px; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; }
.kpi-sub { font-size: 10px; color: #4a5e50; margin-top: 4px; }

/* Equipo y gastos */
.col-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.col-card { background: #fff; border: 1px solid #e3e8e0; border-radius: 10px; padding: 18px; }
.col-title { font-size: 10px; font-weight: 700; color: #0f2318; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 12px; border-bottom: 2px solid #CBF518; padding-bottom: 6px; }
.sub-title { font-size: 10px; font-weight: 700; color: #8a9e90; text-transform: uppercase; letter-spacing: .4px; margin: 14px 0 8px; }

/* Footer */
.footer { background: #0f2318; padding: 14px 28px; text-align: center; font-size: 9px; color: #9ab5a0; margin-top: 32px; letter-spacing: .3px; }

/* Print */
@media print {
  @page { size: A4 portrait; margin: 10mm; }
  .no-print { display: none !important; }
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; background: #fff; }
  .page { max-width: 100%; }
  .hero-card { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
</head>
<body>

<button class="no-print" onclick="window.print()">&#128438; Imprimir / Guardar PDF</button>

<div class="page">

  <!-- CABECERA -->
  <div class="header">
    <div class="header-brand">
      <div class="name">REPORTE FINANCIERO</div>
      <div class="sub">Gestión de Taller</div>
    </div>
    <div class="header-center">
      <div class="titulo">${tipoPeriodo === 'semana' ? 'SEMANA' : 'MES'}</div>
      <div class="periodo">${escapeHtml(labelPeriodo)}</div>
    </div>
    <div class="header-fecha">Generado: ${fechaGeneracion}</div>
  </div>

  <!-- 1. BARRA DE DISTRIBUCIÓN -->
  <div class="s">
    <div class="dist-title">¿A dónde fue cada peso?</div>
    <div class="dist-bar">
      <div class="dist-seg" style="width:${pctSueldos}%;background:#6366f1" title="Sueldos"></div>
      <div class="dist-seg" style="width:${pctRefacciones}%;background:#f59e0b" title="Refacciones"></div>
      <div class="dist-seg" style="width:${pctFijos}%;background:#f43f5e" title="Costos fijos"></div>
      <div class="dist-seg" style="width:${pctVariables}%;background:#14b8a6" title="Gastos varios"></div>
      <div class="dist-seg" style="width:${pctGanancia}%;background:#CBF518" title="Ganancia"></div>
    </div>
    <div class="dist-legend">
      <div class="dist-legend-item"><div class="dist-dot" style="background:#6366f1"></div>Sueldos (${pctSueldos}%)</div>
      <div class="dist-legend-item"><div class="dist-dot" style="background:#f59e0b"></div>Refacciones costo (${pctRefacciones}%)</div>
      <div class="dist-legend-item"><div class="dist-dot" style="background:#f43f5e"></div>Costos fijos (${pctFijos}%)</div>
      <div class="dist-legend-item"><div class="dist-dot" style="background:#14b8a6"></div>Gastos variables (${pctVariables}%)</div>
      <div class="dist-legend-item"><div class="dist-dot" style="background:#CBF518"></div>Ganancia (${pctGanancia}%)</div>
    </div>
  </div>

  <!-- 2. DETALLE POR ORDEN -->
  <div class="s">
    <div class="tabla-title">Detalle por orden</div>
    <table>
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Unidad / Cliente</th>
          <th>Concepto</th>
          <th class="r">Costo Venta</th>
          <th class="r">Costo Compra</th>
          <th class="r">Ganancia</th>
        </tr>
      </thead>
      <tbody>
        ${filasOrdenes || '<tr><td colspan="6" style="padding:16px;text-align:center;color:#8a9e90">Sin órdenes en este período</td></tr>'}
        <tr class="fila-total">
          <td colspan="3">TOTAL PERÍODO &middot; ${(ordenes?.ordenes ?? []).length} ${(ordenes?.ordenes ?? []).length === 1 ? 'orden' : 'órdenes'}</td>
          <td class="lime">${fmt(totOrd.costo_venta)}</td>
          <td style="text-align:right;color:#9ab5a0">${fmt(totOrd.costo_refacciones)}</td>
          <td class="lime">${fmt(totOrd.ganancia)}</td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- 3. BALANCE — cascada (fusionado después de tabla) -->
  <div class="s">
    <div class="balance-card">
      <div class="bal-title">Balance &mdash; ${escapeHtml(labelPeriodo)}</div>

      <div class="bal-row">
        <span style="color:#374151">Total facturado</span>
        <span style="color:#374151;font-variant-numeric:tabular-nums">${fmt(ingresosBrutos)}</span>
      </div>
      ${costoRefacciones !== 0 ? `
      <div class="bal-row indent">
        <span>&minus; Costo refacciones</span>
        <span style="font-variant-numeric:tabular-nums">&minus;${fmt(costoRefacciones)}</span>
      </div>` : ''}
      <div class="bal-row subtotal">
        <span>Ingreso neto operativo</span>
        <span style="font-variant-numeric:tabular-nums">${fmt(ingresoNeto)}</span>
      </div>

      <div class="bal-dashed"></div>

      ${totalSueldos > 0 ? `
        ${empleadosActivos.map(e => `
        <div class="bal-row indent" style="font-size:11px">
          <span style="color:#8a9e90">${escapeHtml(e.nombre)}${e.puesto ? ` (${escapeHtml(e.puesto)})` : ''}</span>
          <span style="color:#8a9e90;font-variant-numeric:tabular-nums">&minus;${fmt(pagoSemanalEmp(e) * (tipoPeriodo === 'semana' ? 1 : 4))}</span>
        </div>`).join('')}
        <div class="bal-row indent" style="border-top:1px solid #e3e8e0;padding-top:6px;margin-top:2px;font-weight:600">
          <span>&minus; Sueldos del equipo</span>
          <span style="font-variant-numeric:tabular-nums">&minus;${fmt(totalSueldos)}</span>
        </div>` : ''}

      ${totalFijos > 0 ? `
        ${fijosActivos.map(p => {
          const montoPeriodo = p.frecuencia === 'semanal'
            ? p.monto * (tipoPeriodo === 'semana' ? 1 : 4)
            : (tipoPeriodo === 'semana' ? p.monto / 4 : p.monto);
          return `
        <div class="bal-row indent" style="font-size:11px">
          <span style="color:#8a9e90">${escapeHtml(p.concepto)}</span>
          <span style="color:#8a9e90;font-variant-numeric:tabular-nums">&minus;${fmt(montoPeriodo)}</span>
        </div>`;
        }).join('')}
        <div class="bal-row indent" style="border-top:1px solid #e3e8e0;padding-top:6px;margin-top:2px;font-weight:600">
          <span>&minus; Costos fijos</span>
          <span style="font-variant-numeric:tabular-nums">&minus;${fmt(totalFijos)}</span>
        </div>` : ''}

      ${totalVariables > 0 ? `
      <div class="bal-row indent">
        <span>&minus; Gastos variables</span>
        <span style="font-variant-numeric:tabular-nums">&minus;${fmt(totalVariables)}</span>
      </div>` : ''}
      ${gastosOrdenes > 0 ? `
      <div class="bal-row indent">
        <span>&minus; Costos internos de órdenes</span>
        <span style="font-variant-numeric:tabular-nums">&minus;${fmt(gastosOrdenes)}</span>
      </div>` : ''}

      <div class="bal-total">
        <span>Ganancia neta</span>
        <span style="font-size:16px;color:${gananciaNeta >= 0 ? '#CBF518' : '#ef4444'};font-variant-numeric:tabular-nums">${fmt(gananciaNeta)}</span>
      </div>

      <div class="bal-nota">Utilidad estimada. No incluye otros impuestos ni deducciones fiscales.</div>
    </div>
  </div>

  <!-- 4. KPI CARDS: Total facturado + desglose -->
  <div class="s">
    <div class="kpis">
      <div class="kpi destacado">
        <div class="kpi-label">Total Facturado</div>
        <div class="kpi-valor" style="color:#111a13">${fmt(ingresosBrutos)}</div>
        <div class="kpi-sub">${numOrdenes} ${numOrdenes === 1 ? 'orden' : 'órdenes'}</div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Servicios</div>
        <div class="kpi-valor" style="color:#4a5e50">${fmt(ingresosServicios)}</div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Mano de Obra</div>
        <div class="kpi-valor" style="color:#4a5e50">${fmt(ingresosMO)}</div>
      </div>
      <div class="kpi">
        <div class="kpi-label">Refacciones</div>
        <div class="kpi-valor" style="color:#4a5e50">${fmt(ingresosRefas)}</div>
      </div>
    </div>
  </div>

  ${notaIVA ? `<div class="s">${notaIVA}</div>` : ''}

  <!-- 5. CAJA CHICA -->
  ${cajaChica ? `<div class="s">${cajaSectionHTML}</div>` : ''}

  <!-- PIE -->
  <div class="footer">
    Reporte Financiero &times; Gestión de Taller &nbsp;&middot;&nbsp; Documento confidencial
  </div>

</div>
</body>
</html>`;

  const win = window.open('', '_blank', 'width=960,height=760');
  if (!win) return;
  win.document.write(htmlCompleto);
  win.document.close();
  win.document.title = `Reporte Financiero — ${params.labelPeriodo}`;
  setTimeout(() => win.print(), 600);
}

function escapeHtml(str: string): string {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
