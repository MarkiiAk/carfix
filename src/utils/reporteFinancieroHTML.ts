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
  // Cálculos financieros
  // -----------------------------------------------------------------------
  const ingresosBrutos = resumen?.resumen?.total_facturado ?? 0;
  const costoRefacciones = resumen?.refacciones?.costo ?? 0;
  const ingresoNeto = ingresosBrutos - costoRefacciones;

  // Misma lógica que pagoSemanalEmpleado() en Financiero.tsx:
  //   activo=false o dias=0 → $0
  //   semanal → sueldo_diario es el monto semanal flat
  //   diario  → sueldo_diario × dias_trabajados (default 5)
  const pagoSemanalEmp = (e: EmpleadoSueldo): number => {
    if (!e.activo) return 0;
    const dias = Number(e.dias_trabajados ?? 5);
    if (dias === 0) return 0;
    const tipo = e.tipo_sueldo ?? 'diario';
    if (tipo === 'semanal') return Number(e.sueldo_diario);
    return Number(e.sueldo_diario) * dias;
  };

  const totalSueldos = empleados
    .filter(e => !e.fecha_fin)           // excluir ex-empleados
    .reduce((acc, e) => acc + pagoSemanalEmp(e) * (tipoPeriodo === 'semana' ? 1 : 4), 0);

  const totalFijos = pagosFijos
    .filter(p => p.activo)
    .reduce((acc, p) => {
      // Igual que pantalla: semanal×1 en semana, semanal×4 en mes; mensual/4 en semana
      if (p.frecuencia === 'semanal') return acc + p.monto * (tipoPeriodo === 'semana' ? 1 : 4);
      return acc + (tipoPeriodo === 'semana' ? p.monto / 4 : p.monto);
    }, 0);

  // gastos.total_admin ya es el total del período (filtrado por el API).
  // NO dividir entre 4: cuando tipo=semana el API ya retorna solo los de esa semana.
  const totalVariables = Number(gastos?.total_admin ?? 0);
  // Costos internos de órdenes del período (gastos_orden sumados)
  const gastosOrdenes = Number(gastos?.gastos_ordenes_mes ?? 0);

  const totalGastos = totalSueldos + totalFijos + totalVariables + gastosOrdenes;
  const gananciaNeta = ingresoNeto - totalSueldos - totalFijos - totalVariables - gastosOrdenes;
  const ivaCobrado = resumen?.resumen?.total_iva ?? 0;

  // Porcentajes para barra de distribución (base = ingresosBrutos)
  const pct = (v: number): string =>
    ingresosBrutos > 0 ? Math.max(0, (v / ingresosBrutos) * 100).toFixed(1) : '0';

  const pctSueldos     = pct(totalSueldos);
  const pctRefacciones = pct(costoRefacciones);
  const pctFijos       = pct(totalFijos);
  const pctVariables   = pct(totalVariables + gastosOrdenes);
  const pctGanancia    = pct(Math.max(0, gananciaNeta));

  // -----------------------------------------------------------------------
  // Helpers de formato
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

  // -----------------------------------------------------------------------
  // Filas de la tabla de órdenes
  // -----------------------------------------------------------------------
  const filasOrdenes = (ordenes?.ordenes ?? []).map(o => {
    const enProceso = ['en_proceso', 'abierta', 'pendiente'].includes(o.estado);
    const gananciaColor = o.ganancia < 0 ? 'color:#dc2626' : enProceso ? 'color:#d97706' : '';
    return `
      <tr style="border-bottom:1px solid #e3e8e0">
        <td style="padding:8px 10px;color:#4a5e50;font-size:12px">${fmtFecha(o.fecha)}</td>
        <td style="padding:8px 10px;font-size:12px">${escapeHtml(o.cliente_nombre)}</td>
        <td style="padding:8px 10px;font-size:12px;color:#4a5e50">${escapeHtml(o.vehiculo)}</td>
        <td style="padding:8px 10px;text-align:right;font-size:12px">${fmt(o.costo_venta)}</td>
        <td style="padding:8px 10px;text-align:right;font-size:12px;color:#4a5e50">${fmt(o.costo_refacciones)}</td>
        <td style="padding:8px 10px;text-align:right;font-size:12px;${gananciaColor}">
          ${fmt(o.ganancia)}${enProceso ? '<br><span style="font-size:10px">(anticipo)</span>' : ''}
        </td>
        <td style="padding:8px 10px;font-size:11px;color:#8a9e90">${estadoLabel[o.estado] ?? o.estado}</td>
      </tr>`;
  }).join('');

  const totOrd = ordenes?.totales ?? { costo_venta: 0, costo_refacciones: 0, ganancia: 0 };

  // -----------------------------------------------------------------------
  // Top servicios
  // -----------------------------------------------------------------------
  const topServicios = (resumen?.top_servicios ?? []).slice(0, 5);
  const maxServicio = topServicios.reduce((max, s) => Math.max(max, s.total_generado), 0);

  const filasServicios = topServicios.map(s => {
    const barpct = maxServicio > 0 ? ((s.total_generado / maxServicio) * 100).toFixed(0) : '0';
    return `
      <div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:4px">
          <span style="font-size:12px;color:#111a13">${escapeHtml(s.descripcion)}</span>
          <span style="font-size:11px;color:#4a5e50">${fmt(s.total_generado)} · ${s.veces}x</span>
        </div>
        <div style="height:7px;background:#e3e8e0;border-radius:4px;overflow:hidden">
          <div style="height:100%;width:${barpct}%;background:#CBF518;border-radius:4px"></div>
        </div>
      </div>`;
  }).join('');

  // -----------------------------------------------------------------------
  // Top clientes
  // -----------------------------------------------------------------------
  const topClientes = (resumen?.top_clientes ?? []).slice(0, 5);

  const filasClientes = topClientes.map((c, i) => {
    const circuloStyle = i === 0
      ? 'background:#CBF518;color:#0f2318'
      : 'background:#e3e8e0;color:#4a5e50';
    return `
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
        <div style="width:28px;height:28px;border-radius:50%;${circuloStyle};display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">${i + 1}</div>
        <div style="flex:1;min-width:0">
          <div style="font-size:12px;font-weight:600;color:#111a13;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(c.nombre)}</div>
          <div style="font-size:11px;color:#8a9e90">${fmt(c.total_gastado)} · ${c.num_visitas} visita${c.num_visitas !== 1 ? 's' : ''}</div>
        </div>
      </div>`;
  }).join('');

  // -----------------------------------------------------------------------
  // Empleados activos
  // -----------------------------------------------------------------------
  const empleadosActivos = empleados.filter(e => e.activo);
  const filasEmpleados = empleadosActivos.length === 0
    ? '<p style="font-size:12px;color:#8a9e90">Sin empleados registrados.</p>'
    : empleadosActivos.map(e => `
      <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #e3e8e0;font-size:12px">
        <span style="color:#111a13">${escapeHtml(e.nombre)}${e.puesto ? ` <span style="color:#8a9e90">(${escapeHtml(e.puesto)})</span>` : ''}</span>
        <span style="color:#4a5e50;tabular-nums">${fmt(pagoSemanalEmp(e) * (tipoPeriodo === 'semana' ? 1 : 4))}</span>
      </div>`).join('');

  // -----------------------------------------------------------------------
  // Pagos fijos activos
  // -----------------------------------------------------------------------
  const fijosActivos = pagosFijos.filter(p => p.activo);
  const filasFixos = fijosActivos.length === 0
    ? '<p style="font-size:12px;color:#8a9e90">Sin pagos fijos registrados.</p>'
    : fijosActivos.map(p => {
        const montoPeriodo = p.frecuencia === 'semanal'
          ? p.monto * (tipoPeriodo === 'semana' ? 1 : 4)
          : (tipoPeriodo === 'semana' ? p.monto / 4 : p.monto);
        return `
          <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #e3e8e0;font-size:12px">
            <span style="color:#111a13">${escapeHtml(p.concepto)}</span>
            <span style="color:#4a5e50;tabular-nums">${fmt(montoPeriodo)}</span>
          </div>`;
      }).join('');

  // -----------------------------------------------------------------------
  // Caja chica
  // -----------------------------------------------------------------------
  const cajaSectionHTML = cajaChica ? `
    <div style="margin-top:24px;background:#fff;border:1px solid #e3e8e0;border-radius:10px;padding:20px">
      <h3 style="font-size:13px;font-weight:700;color:#0f2318;margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px">Caja Chica Mayte</h3>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:14px">
        <div style="text-align:center;padding:10px;background:#f5f6f2;border-radius:8px">
          <div style="font-size:10px;color:#8a9e90;margin-bottom:4px">SALDO ANTERIOR</div>
          <div style="font-size:14px;font-weight:700;color:#111a13">${fmt(cajaChica.saldo_anterior)}</div>
        </div>
        <div style="text-align:center;padding:10px;background:#f0fdf4;border-radius:8px">
          <div style="font-size:10px;color:#8a9e90;margin-bottom:4px">ENTRADAS</div>
          <div style="font-size:14px;font-weight:700;color:#16a34a">${fmt(cajaChica.ingresos_semana)}</div>
        </div>
        <div style="text-align:center;padding:10px;background:#fef2f2;border-radius:8px">
          <div style="font-size:10px;color:#8a9e90;margin-bottom:4px">SALIDAS</div>
          <div style="font-size:14px;font-weight:700;color:#dc2626">${fmt(cajaChica.egresos_semana)}</div>
        </div>
      </div>
      <div style="padding:12px 16px;border-radius:8px;background:${cajaChica.saldo_actual < 0 ? '#fef2f2' : '#f0fdf4'};display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:12px;color:#4a5e50;font-weight:600">Saldo actual</span>
        <span style="font-size:18px;font-weight:800;color:${cajaChica.saldo_actual < 0 ? '#dc2626' : '#CBF518'}">${fmt(cajaChica.saldo_actual)}</span>
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
<title>Reporte SAG Garage &mdash; ${escapeHtml(labelPeriodo)}</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --verde-oscuro: #0f2318;
  --lime: #CBF518;
  --fondo: #f5f6f2;
  --card: #ffffff;
  --borde: #e3e8e0;
  --texto: #111a13;
  --gris: #4a5e50;
  --meta: #8a9e90;
  --positivo: #16a34a;
  --negativo: #dc2626;
}
body {
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  background: var(--fondo);
  color: var(--texto);
  font-size: 13px;
  line-height: 1.4;
}

/* ---- Botón flotante ---- */
.no-print {
  position: fixed;
  top: 16px;
  right: 16px;
  z-index: 999;
  background: var(--lime);
  color: var(--verde-oscuro);
  border: none;
  border-radius: 8px;
  padding: 10px 18px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.no-print:hover { opacity: .9; }

/* ---- Layout ---- */
.page { max-width: 900px; margin: 0 auto; padding: 0 0 40px; }
.section { padding: 0 24px; }
.section + .section { margin-top: 20px; }

/* ---- Cabecera ---- */
.header {
  background: var(--verde-oscuro);
  height: 80px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 28px;
  margin-bottom: 20px;
}
.header-brand .name { font-size: 22px; font-weight: 900; color: var(--lime); line-height: 1.1; letter-spacing: -.5px; }
.header-brand .sub  { font-size: 11px; color: #9ab5a0; margin-top: 2px; }
.header-center .titulo { font-size: 13px; font-weight: 700; color: var(--lime); text-align: center; letter-spacing: 1px; }
.header-center .periodo { font-size: 11px; color: #fff; text-align: center; margin-top: 3px; }
.header-fecha { font-size: 10px; color: #9ab5a0; text-align: right; }

/* ---- KPIs ---- */
.kpis { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; padding: 0 24px; margin-bottom: 20px; }
.kpi { background: var(--card); border: 1px solid var(--borde); border-radius: 10px; padding: 16px; }
.kpi.destacado { border-color: var(--lime); }
.kpi-label { font-size: 10px; font-weight: 700; color: var(--meta); letter-spacing: .8px; text-transform: uppercase; margin-bottom: 6px; }
.kpi-valor { font-size: 20px; font-weight: 800; line-height: 1.1; }
.kpi-sub { font-size: 10px; color: var(--gris); margin-top: 4px; }

/* ---- Barra distribución ---- */
.dist-section { padding: 0 24px; margin-bottom: 20px; }
.dist-title { font-size: 12px; font-weight: 700; color: var(--gris); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; }
.dist-bar { height: 12px; border-radius: 6px; overflow: hidden; display: flex; width: 100%; background: var(--borde); }
.dist-seg { height: 100%; }
.dist-legend { display: flex; flex-wrap: wrap; gap: 10px 20px; margin-top: 10px; }
.dist-legend-item { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--gris); }
.dist-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

/* ---- Dos columnas ---- */
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 0 24px; margin-bottom: 20px; }
.col-card { background: var(--card); border: 1px solid var(--borde); border-radius: 10px; padding: 18px; }
.col-title { font-size: 12px; font-weight: 700; color: var(--verde-oscuro); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 14px; border-bottom: 2px solid var(--lime); padding-bottom: 6px; }

/* ---- Estado de resultados ---- */
.er-row { display: flex; justify-content: space-between; align-items: baseline; padding: 5px 0; font-size: 12px; }
.er-row.indent { padding-left: 12px; color: var(--gris); }
.er-row.divider { border-top: 1px solid var(--borde); margin-top: 4px; padding-top: 8px; }
.er-row.total { font-size: 16px; font-weight: 800; margin-top: 6px; }
.er-row.total .er-val { color: var(--positivo); }
.er-row.total.negativo .er-val { color: var(--negativo); }

/* ---- Página 2 ---- */
.page-break { page-break-before: always; padding-top: 24px; }

/* ---- Tabla órdenes ---- */
.tabla-section { padding: 0 24px; margin-bottom: 20px; }
.tabla-title { font-size: 12px; font-weight: 700; color: var(--gris); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; background: var(--card); border: 1px solid var(--borde); border-radius: 10px; overflow: hidden; font-size: 12px; }
thead { background: #f5f6f2; }
th { padding: 9px 10px; text-align: left; font-size: 10px; font-weight: 700; color: var(--meta); text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--borde); }
th.r { text-align: right; }
tr.fila-total { background: var(--verde-oscuro); color: #fff; }
tr.fila-total td { padding: 10px 10px; font-weight: 700; font-size: 12px; }
tr.fila-total td.lime { color: var(--lime); text-align: right; }

/* ---- Dos col página 2 ---- */
.two-col-p2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; padding: 0 24px; margin-bottom: 20px; }

/* ---- Footer ---- */
.footer { background: var(--verde-oscuro); padding: 14px 28px; text-align: center; font-size: 9px; color: #9ab5a0; margin-top: 32px; letter-spacing: .3px; }

/* ---- Print ---- */
@media print {
  @page { size: A4 portrait; margin: 0; }
  .no-print { display: none !important; }
  body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .page { max-width: 100%; padding: 0; }
}
</style>
</head>
<body>

<button class="no-print" onclick="window.print()">&#128438; Imprimir / Guardar PDF</button>

<div class="page">

  <!-- CABECERA -->
  <div class="header">
    <div class="header-brand">
      <div class="name">SAG GARAGE</div>
      <div class="sub">Gestión de Taller</div>
    </div>
    <div class="header-center">
      <div class="titulo">REPORTE ${tipoPeriodo === 'semana' ? 'SEMANAL' : 'MENSUAL'}</div>
      <div class="periodo">${escapeHtml(labelPeriodo)}</div>
    </div>
    <div class="header-fecha">Generado: ${fechaGeneracion}</div>
  </div>

  <!-- KPIs -->
  <div class="kpis">
    <div class="kpi">
      <div class="kpi-label">Facturado</div>
      <div class="kpi-valor" style="color:var(--texto)">${fmt(ingresosBrutos)}</div>
    </div>
    <div class="kpi">
      <div class="kpi-label">Costo Refacciones</div>
      <div class="kpi-valor" style="color:var(--gris)">${fmt(costoRefacciones)}</div>
    </div>
    <div class="kpi">
      <div class="kpi-label">Total Gastos</div>
      <div class="kpi-valor" style="color:var(--texto)">${fmt(totalGastos)}</div>
      <div class="kpi-sub">sueldos + fijos + variables + internos</div>
    </div>
    <div class="kpi destacado">
      <div class="kpi-label">Ganancia Neta</div>
      <div class="kpi-valor" style="color:${gananciaNeta >= 0 ? '#CBF518' : 'var(--negativo)'}">
        ${fmt(gananciaNeta)}
      </div>
    </div>
  </div>

  <!-- BARRA DISTRIBUCIÓN -->
  <div class="dist-section">
    <div class="dist-title">Así se distribuyó cada peso</div>
    <div class="dist-bar">
      <div class="dist-seg" style="width:${pctSueldos}%;background:#6366f1"></div>
      <div class="dist-seg" style="width:${pctRefacciones}%;background:#f59e0b"></div>
      <div class="dist-seg" style="width:${pctFijos}%;background:#ec4899"></div>
      <div class="dist-seg" style="width:${pctVariables}%;background:#14b8a6"></div>
      <div class="dist-seg" style="width:${pctGanancia}%;background:#CBF518"></div>
    </div>
    <div class="dist-legend">
      <div class="dist-legend-item"><div class="dist-dot" style="background:#6366f1"></div>Sueldos (${pctSueldos}%)</div>
      <div class="dist-legend-item"><div class="dist-dot" style="background:#f59e0b"></div>Refacciones costo (${pctRefacciones}%)</div>
      <div class="dist-legend-item"><div class="dist-dot" style="background:#ec4899"></div>Pagos fijos (${pctFijos}%)</div>
      <div class="dist-legend-item"><div class="dist-dot" style="background:#14b8a6"></div>Gastos variables (${pctVariables}%)</div>
      <div class="dist-legend-item"><div class="dist-dot" style="background:#CBF518"></div>Ganancia neta (${pctGanancia}%)</div>
    </div>
  </div>

  <!-- DOS COLUMNAS: Estado de resultados + Equipo y Gastos -->
  <div class="two-col">
    <!-- Estado de Resultados -->
    <div class="col-card">
      <div class="col-title">Estado de Resultados</div>
      <div class="er-row">
        <span>Total facturado</span>
        <span class="er-val">${fmt(ingresosBrutos)}</span>
      </div>
      <div class="er-row indent">
        <span>&minus; Costo refacciones</span>
        <span class="er-val">&minus;${fmt(costoRefacciones)}</span>
      </div>
      <div style="border-top:1px solid var(--borde);margin:6px 0"></div>
      <div class="er-row" style="font-weight:600">
        <span>Ingreso neto operativo</span>
        <span class="er-val">${fmt(ingresoNeto)}</span>
      </div>
      <div class="er-row indent">
        <span>&minus; Sueldos del equipo</span>
        <span class="er-val">&minus;${fmt(totalSueldos)}</span>
      </div>
      <div class="er-row indent">
        <span>&minus; Pagos fijos</span>
        <span class="er-val">&minus;${fmt(totalFijos)}</span>
      </div>
      <div class="er-row indent">
        <span>&minus; Gastos variables</span>
        <span class="er-val">&minus;${fmt(totalVariables)}</span>
      </div>
      ${gastosOrdenes > 0 ? `
      <div class="er-row indent">
        <span>&minus; Costos internos de órdenes</span>
        <span class="er-val">&minus;${fmt(gastosOrdenes)}</span>
      </div>` : ''}
      <div style="border-top:2px solid var(--borde);margin:8px 0"></div>
      <div class="er-row total ${gananciaNeta < 0 ? 'negativo' : ''}">
        <span>GANANCIA NETA</span>
        <span class="er-val">${fmt(gananciaNeta)}</span>
      </div>
    </div>

    <!-- Equipo y Gastos -->
    <div class="col-card">
      <div class="col-title">Equipo y Gastos</div>
      <div style="font-size:11px;font-weight:700;color:var(--meta);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">Empleados</div>
      ${filasEmpleados}
      <div style="font-size:11px;font-weight:700;color:var(--meta);text-transform:uppercase;letter-spacing:.4px;margin-top:14px;margin-bottom:8px">Pagos Fijos</div>
      ${filasFixos}
    </div>
  </div>

  ${notaIVA}

  <!-- PÁGINA 2 -->
  <div class="page-break">

    <!-- TABLA DE ÓRDENES -->
    <div class="tabla-section">
      <div class="tabla-title">Detalle por Orden</div>
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Vehículo</th>
            <th class="r">Ingreso</th>
            <th class="r">Costo Refas</th>
            <th class="r">Ganancia</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          ${filasOrdenes || '<tr><td colspan="7" style="padding:16px;text-align:center;color:#8a9e90">Sin órdenes en este período</td></tr>'}
          <tr class="fila-total">
            <td colspan="3" style="color:#fff">TOTAL</td>
            <td class="lime">${fmt(totOrd.costo_venta)}</td>
            <td style="text-align:right;color:#9ab5a0">${fmt(totOrd.costo_refacciones)}</td>
            <td class="lime">${fmt(totOrd.ganancia)}</td>
            <td></td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- TOP SERVICIOS + TOP CLIENTES -->
    <div class="two-col-p2">
      <div class="col-card">
        <div class="col-title">Top 5 Servicios</div>
        ${filasServicios || '<p style="font-size:12px;color:#8a9e90">Sin datos.</p>'}
      </div>
      <div class="col-card">
        <div class="col-title">Top 5 Clientes</div>
        ${filasClientes || '<p style="font-size:12px;color:#8a9e90">Sin datos.</p>'}
      </div>
    </div>

    ${cajaSectionHTML}

  </div><!-- /page-break -->

  <!-- PIE -->
  <div class="footer">
    SAG Garage &times; AkLabs &nbsp;&middot;&nbsp; Reporte confidencial &nbsp;&middot;&nbsp; saggarage.com.mx
  </div>

</div><!-- /page -->

</body>
</html>`;

  // -----------------------------------------------------------------------
  // Abrir ventana e imprimir
  // -----------------------------------------------------------------------
  const win = window.open('', '_blank', 'width=900,height=700');
  if (!win) return;
  win.document.write(htmlCompleto);
  win.document.close();
  win.document.title = `Reporte SAG Garage — ${params.labelPeriodo}`;
  setTimeout(() => win.print(), 500);
}

// Escapa caracteres HTML para evitar XSS en el template string
function escapeHtml(str: string): string {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
