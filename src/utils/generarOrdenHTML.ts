import type { Presupuesto } from '../types';

// ---------------------------------------------------------------------------
// Helper: escape HTML para evitar XSS al interpolar strings del usuario
// ---------------------------------------------------------------------------
function esc(str: string | null | undefined): string {
  if (str == null) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// ---------------------------------------------------------------------------
// Helper: formato de moneda MXN
// ---------------------------------------------------------------------------
function mxn(value: number | string | null | undefined): string {
  const n = Number(value ?? 0);
  return '$' + n.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// ---------------------------------------------------------------------------
// Helper: barra visual de nivel de gasolina
// ---------------------------------------------------------------------------
function barraGasolina(pct: number): string {
  const p = Math.max(0, Math.min(100, pct));
  return `<div style="display:inline-flex;align-items:center;gap:6px;">
    <div style="width:60px;height:8px;border-radius:4px;background:linear-gradient(to right,#CC0000 ${p}%,#e0e0e0 ${p}%);border:1px solid #ccc;"></div>
    <span style="font-size:10px;color:#333;">${p}%</span>
  </div>`;
}

// ---------------------------------------------------------------------------
// Helper: checkbox visual de inspección
// ---------------------------------------------------------------------------
function chk(val: boolean): string {
  return val
    ? '<span style="color:#CC0000;font-weight:bold;">&#10003;</span>'
    : '<span style="color:#bbb;">&#9675;</span>';
}

// ---------------------------------------------------------------------------
// CSS compartido del documento
// ---------------------------------------------------------------------------
function estilos(): string {
  return `
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Arial', 'Helvetica', sans-serif;
    font-size: 11px;
    color: #111111;
    line-height: 1.4;
  }
  @media print {
    @page { size: letter; margin: 12mm; }
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .page-break { page-break-before: always; }
    .no-print { display: none !important; }
    .page { box-shadow: none !important; margin-bottom: 0 !important; padding: 0 !important; }
  }
  @media screen {
    body { max-width: 816px; margin: 0 auto; padding: 20px; background: #e8e8e8; }
    .page { background: white; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); border-radius: 4px; }
  }

  /* --- Header del documento --- */
  .doc-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 14px;
    padding-bottom: 12px;
    border-bottom: 2px solid #CC0000;
  }
  .doc-header img { height: 52px; width: auto; }
  .taller-info { flex: 1; padding: 0 16px; }
  .taller-nombre { font-size: 15px; font-weight: bold; color: #111; letter-spacing: 0.3px; }
  .taller-sub { font-size: 9px; color: #555; margin-top: 2px; }
  .folio-box { text-align: right; min-width: 160px; }
  .folio-box .label { font-size: 9px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; }
  .folio-box .valor { font-size: 12px; font-weight: bold; color: #111; }
  .folio-box .folio-num { font-size: 14px; font-weight: bold; color: #CC0000; }

  /* --- Header compacto (páginas 2-4) --- */
  .header-compact {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 8px;
    border-bottom: 2px solid #CC0000;
    margin-bottom: 12px;
  }
  .header-compact img { height: 34px; width: auto; }
  .header-compact .info-linea { font-size: 9px; color: #444; }
  .header-compact .folio-compact { font-size: 11px; font-weight: bold; color: #CC0000; }

  /* --- Secciones info cliente/vehículo --- */
  .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    border: 1px solid #e0e0e0;
    margin-bottom: 12px;
  }
  .info-cell {
    padding: 8px 12px;
    border-right: 1px solid #e0e0e0;
  }
  .info-cell:last-child { border-right: none; }
  .info-cell-header {
    font-size: 9px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #666;
    margin-bottom: 5px;
  }
  .info-row { margin-bottom: 3px; font-size: 10px; }
  .info-row strong { color: #111; }

  /* --- Headers de sección --- */
  .section-header {
    background-color: #CC0000;
    color: white;
    padding: 5px 12px;
    font-weight: bold;
    font-size: 10px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-bottom: 0;
  }
  .section-wrapper { margin-bottom: 12px; }

  /* --- Tablas --- */
  table { width: 100%; border-collapse: collapse; }
  .table-subheader th {
    background-color: #333333;
    color: white;
    font-size: 9px;
    padding: 4px 8px;
    text-align: left;
    font-weight: bold;
    letter-spacing: 0.3px;
  }
  .table-subheader th.right { text-align: right; }
  table td {
    padding: 4px 8px;
    border-bottom: 1px solid #e0e0e0;
    font-size: 10px;
    color: #333;
    vertical-align: top;
  }
  table tr:last-child td { border-bottom: none; }
  table tr:nth-child(even) td { background: #fafafa; }
  table td.right { text-align: right; }
  table td.center { text-align: center; }

  /* --- Totales --- */
  .totales-box {
    margin-top: 8px;
    border: 1px solid #e0e0e0;
  }
  .totales-row {
    display: flex;
    justify-content: space-between;
    padding: 4px 12px;
    font-size: 10px;
    border-bottom: 1px solid #f0f0f0;
  }
  .totales-row:last-child { border-bottom: none; }
  .totales-row.total-principal {
    background-color: #CC0000;
    color: white;
    font-weight: bold;
    font-size: 12px;
    padding: 6px 12px;
  }
  .totales-row.total-saldo {
    background-color: #111111;
    color: white;
    font-weight: bold;
    font-size: 12px;
    padding: 6px 12px;
  }
  .totales-row.subtotal-line { background: #f5f5f5; }
  .totales-row.iva-line { color: #555; }
  .totales-row.anticipo-line { color: #555; }

  /* --- Layout de dos columnas --- */
  .two-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 12px;
  }
  .col-full { margin-bottom: 12px; }

  /* --- Inspección --- */
  .inspeccion-tabla td { font-size: 9px; padding: 3px 6px; }
  .inspeccion-tabla th { font-size: 9px; padding: 3px 6px; }

  /* --- Términos breves --- */
  .terminos-box {
    border: 1px solid #e0e0e0;
    padding: 8px 10px;
    font-size: 9px;
    color: #555;
    line-height: 1.5;
  }
  .terminos-box p { margin-bottom: 3px; }

  /* --- Firma --- */
  .firma-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin-top: 40px;
  }
  .firma-linea {
    border-top: 1.5px solid #111;
    padding-top: 6px;
    text-align: center;
    font-size: 10px;
    color: #333;
  }
  .firma-label {
    font-weight: bold;
    font-size: 10px;
    margin-bottom: 2px;
  }

  /* --- Garantía --- */
  .garantia-clausulas { margin-top: 8px; }
  .clausula {
    margin-bottom: 8px;
    padding-left: 12px;
    border-left: 3px solid #CC0000;
    font-size: 10px;
    color: #333;
    line-height: 1.5;
  }
  .clausula-num {
    font-weight: bold;
    color: #CC0000;
    margin-right: 4px;
  }
  .garantia-titulo {
    font-size: 16px;
    font-weight: bold;
    text-align: center;
    color: #CC0000;
    margin: 10px 0 4px;
    letter-spacing: 0.5px;
  }
  .garantia-subtitulo {
    text-align: center;
    font-size: 10px;
    color: #555;
    margin-bottom: 12px;
  }
  .pie-garantia {
    margin-top: 16px;
    padding-top: 10px;
    border-top: 1px solid #e0e0e0;
    font-size: 9px;
    color: #666;
    text-align: center;
  }

  /* --- Resumen caja firma --- */
  .caja-resumen {
    border: 2px solid #CC0000;
    padding: 12px 16px;
    margin-bottom: 20px;
    max-width: 360px;
    margin-left: auto;
    margin-right: auto;
  }
  .caja-resumen-titulo {
    font-size: 11px;
    font-weight: bold;
    color: #CC0000;
    text-align: center;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
  .caja-resumen-row {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    padding: 3px 0;
    border-bottom: 1px dashed #ddd;
  }
  .caja-resumen-row:last-child { border-bottom: none; }
  .caja-resumen-row.destacado {
    font-weight: bold;
    font-size: 12px;
    color: #CC0000;
    border-bottom: none;
    margin-top: 4px;
  }

  /* --- Tabla vacía --- */
  .row-vacio td {
    color: #999;
    font-style: italic;
    font-size: 9px;
  }
</style>`;
}

// ---------------------------------------------------------------------------
// PÁGINA 1 — Presupuesto principal
// ---------------------------------------------------------------------------
function pagina1(p: Presupuesto, logoUrl: string): string {
  const fecha = new Date(p.fecha);
  const fechaFmt = fecha.toLocaleDateString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric' });
  const horaFmt = fecha.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });

  // Servicios
  const serviciosRows = p.servicios.length > 0
    ? p.servicios.map(s => `
        <tr>
          <td>${esc(s.descripcion)}</td>
          <td class="right">${mxn(s.precio)}</td>
        </tr>`).join('')
    : `<tr class="row-vacio"><td colspan="2">Sin servicios registrados</td></tr>`;

  // Refacciones
  const refaccionesRows = p.refacciones.length > 0
    ? p.refacciones.map(r => `
        <tr>
          <td>${esc(r.nombre)}</td>
          <td class="center">${r.cantidad}</td>
          <td class="right">${mxn(r.precioVenta)}</td>
          <td class="right">${mxn(r.total)}</td>
        </tr>`).join('')
    : `<tr class="row-vacio"><td colspan="4">Sin refacciones registradas</td></tr>`;

  // Mano de obra
  const manoObraRows = p.manoDeObra.length > 0
    ? p.manoDeObra.map(m => `
        <tr>
          <td>${esc(m.descripcion)}</td>
          <td class="right">${mxn(m.precio)}</td>
        </tr>`).join('')
    : `<tr class="row-vacio"><td colspan="2">Sin mano de obra registrada</td></tr>`;

  const ivaRow = p.resumen.incluirIVA
    ? `<div class="totales-row iva-line"><span>IVA (16%)</span><span>${mxn(p.resumen.iva)}</span></div>`
    : '';

  return `
  <div class="page">
    <!-- ENCABEZADO -->
    <div class="doc-header">
      <img src="${logoUrl}" alt="Logo Servicio Gudiño" onerror="this.style.display='none'">
      <div class="taller-info">
        <div class="taller-nombre">SERVICIO GUDIÑO</div>
        <div class="taller-sub">Eje Central #61, Col. Ex Hipódromo de Peralvillo, CDMX</div>
        <div class="taller-sub">Tel: 55 8325 8996 &nbsp;|&nbsp; carservicegudino</div>
      </div>
      <div class="folio-box">
        <div class="label">Folio</div>
        <div class="folio-num">${esc(p.folio)}</div>
        <div style="margin-top:4px;">
          <div class="label">Fecha</div>
          <div class="valor">${fechaFmt}</div>
        </div>
        <div style="margin-top:3px;">
          <div class="label">Hora</div>
          <div class="valor">${horaFmt}</div>
        </div>
      </div>
    </div>

    <!-- CLIENTE / VEHÍCULO -->
    <div class="info-grid">
      <div class="info-cell">
        <div class="info-cell-header">Cliente</div>
        <div class="info-row"><strong>${esc(p.cliente.nombreCompleto) || '—'}</strong></div>
        <div class="info-row">Tel: ${esc(p.cliente.telefono) || '—'}</div>
        ${p.cliente.email ? `<div class="info-row">${esc(p.cliente.email)}</div>` : ''}
        ${p.cliente.domicilio ? `<div class="info-row">${esc(p.cliente.domicilio)}</div>` : ''}
      </div>
      <div class="info-cell">
        <div class="info-cell-header">Vehículo</div>
        <div class="info-row"><strong>${esc(p.vehiculo.marca)} ${esc(p.vehiculo.modelo)} ${esc(p.vehiculo.year) || ''}</strong></div>
        <div class="info-row">Placas: <strong>${esc(p.vehiculo.placas) || '—'}</strong> &nbsp; Color: ${esc(p.vehiculo.color) || '—'}</div>
        <div class="info-row">Km entrada: ${esc(p.vehiculo.kilometrajeEntrada) || '—'}</div>
        <div class="info-row">Gasolina: ${barraGasolina(p.vehiculo.nivelGasolina)}</div>
        ${p.vehiculo.niv ? `<div class="info-row" style="font-size:9px;color:#666;">NIV: ${esc(p.vehiculo.niv)}</div>` : ''}
      </div>
    </div>

    <!-- SERVICIOS -->
    <div class="section-wrapper">
      <div class="section-header">1. Servicios</div>
      <table>
        <thead class="table-subheader">
          <tr><th>Descripción</th><th class="right" style="width:120px;">Importe</th></tr>
        </thead>
        <tbody>${serviciosRows}</tbody>
      </table>
    </div>

    <!-- REFACCIONES -->
    <div class="section-wrapper">
      <div class="section-header">2. Refacciones</div>
      <table>
        <thead class="table-subheader">
          <tr>
            <th>Descripción</th>
            <th class="right" style="width:50px;">Cant.</th>
            <th class="right" style="width:100px;">P. Unit.</th>
            <th class="right" style="width:110px;">Total</th>
          </tr>
        </thead>
        <tbody>${refaccionesRows}</tbody>
      </table>
    </div>

    <!-- MANO DE OBRA -->
    <div class="section-wrapper">
      <div class="section-header">3. Mano de Obra</div>
      <table>
        <thead class="table-subheader">
          <tr><th>Descripción</th><th class="right" style="width:120px;">Importe</th></tr>
        </thead>
        <tbody>${manoObraRows}</tbody>
      </table>
    </div>

    <!-- TÉRMINOS + TOTALES -->
    <div style="display:grid;grid-template-columns:1fr 240px;gap:16px;margin-top:4px;">
      <div>
        <div class="section-header" style="margin-bottom:6px;">Términos y Condiciones</div>
        <div class="terminos-box">
          <p>&#9679; Los precios pueden variar si se detectan fallas adicionales durante el servicio.</p>
          <p>&#9679; El taller no se hace responsable por objetos dejados dentro del vehículo.</p>
          <p>&#9679; El tiempo de entrega es estimado y puede variar según disponibilidad de refacciones.</p>
          <p>&#9679; Todo trabajo autorizado requiere un anticipo del 35% del total del presupuesto.</p>
          <p>&#9679; La garantía aplica según las cláusulas de la póliza adjunta.</p>
        </div>
      </div>
      <div>
        <div class="totales-box">
          <div class="totales-row subtotal-line">
            <span>Servicios</span><span>${mxn(p.resumen.servicios)}</span>
          </div>
          <div class="totales-row subtotal-line">
            <span>Refacciones</span><span>${mxn(p.resumen.refacciones)}</span>
          </div>
          <div class="totales-row subtotal-line">
            <span>Mano de Obra</span><span>${mxn(p.resumen.manoDeObra)}</span>
          </div>
          ${p.resumen.incluirIVA ? `<div class="totales-row subtotal-line"><span>Subtotal</span><span>${mxn(p.resumen.subtotal)}</span></div>` : ''}
          ${ivaRow}
          <div class="totales-row total-principal">
            <span>TOTAL ${p.resumen.incluirIVA ? '(CON IVA)' : '(SIN IVA)'}</span>
            <span>${mxn(p.resumen.total)}</span>
          </div>
          <div class="totales-row anticipo-line">
            <span>(-) Anticipo</span><span>${mxn(p.resumen.anticipo)}</span>
          </div>
          <div class="totales-row total-saldo">
            <span>SALDO RESTANTE</span>
            <span>${mxn(p.resumen.restante)}</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

// ---------------------------------------------------------------------------
// PÁGINA 2 — Inspección e información técnica
// ---------------------------------------------------------------------------
function pagina2(p: Presupuesto, logoUrl: string): string {
  const ext = p.inspeccion.exteriores;
  const intr = p.inspeccion.interiores;

  const exterioresItems: [string, boolean][] = [
    ['Luces frontales', ext.lucesFrontales],
    ['Cuarto de luces', ext.cuartoLuces],
    ['Antena', ext.antena],
    ['Espejos laterales', ext.espejosLaterales],
    ['Cristales', ext.cristales],
    ['Emblemas', ext.emblemas],
    ['Llantas', ext.llantas],
    ['Tapón de ruedas', ext.taponRuedas],
    ['Molduras completas', ext.moldurasCompletas],
    ['Tapón de gasolina', ext.taponGasolina],
    ['Limpiadores', ext.limpiadores],
  ];

  const interioresItems: [string, boolean][] = [
    ['Instrumento tablero', intr.instrumentoTablero],
    ['Calefacción', intr.calefaccion],
    ['Sistema de sonido', intr.sistemaSonido],
    ['Bocinas', intr.bocinas],
    ['Espejo retrovisor', intr.espejoRetrovisor],
    ['Cinturones', intr.cinturones],
    ['Botonería general', intr.botoniaGeneral],
    ['Manijas', intr.manijas],
    ['Tapetes', intr.tapetes],
    ['Vestiduras', intr.vestiduras],
    ['Otros', intr.otros],
  ];

  // Dividir en dos columnas
  const mitad = Math.ceil(exterioresItems.length / 2);
  const extCol1 = exterioresItems.slice(0, mitad);
  const extCol2 = exterioresItems.slice(mitad);

  const mitadIntr = Math.ceil(interioresItems.length / 2);
  const intrCol1 = interioresItems.slice(0, mitadIntr);
  const intrCol2 = interioresItems.slice(mitadIntr);

  function filasInspeccion(col1: [string, boolean][], col2: [string, boolean][]): string {
    const maxRows = Math.max(col1.length, col2.length);
    let rows = '';
    for (let i = 0; i < maxRows; i++) {
      const a = col1[i];
      const b = col2[i];
      rows += `<tr>
        <td style="width:50%;">${a ? `${chk(a[1])} ${esc(a[0])}` : ''}</td>
        <td style="width:50%;">${b ? `${chk(b[1])} ${esc(b[0])}` : ''}</td>
      </tr>`;
    }
    return rows;
  }

  // Daños adicionales
  const danosRows = p.inspeccion.danosAdicionales.length > 0
    ? p.inspeccion.danosAdicionales.map(d => `
        <tr>
          <td>${esc(d.ubicacion)}</td>
          <td>${esc(d.tipo)}</td>
          <td>${esc(d.descripcion)}</td>
        </tr>`).join('')
    : `<tr class="row-vacio"><td colspan="3">Sin daños adicionales registrados</td></tr>`;

  // Puntos de seguridad
  let puntosSeguridadHTML = '';
  if (p.puntosSeguridad && p.puntosSeguridad.length > 0) {
    const psRows = p.puntosSeguridad.map(ps => {
      const color = ps.estado?.color || '#666';
      const estadoNombre = ps.estado?.nombre || '—';
      return `<tr>
        <td>${esc(ps.punto?.nombre || '—')}</td>
        <td style="white-space:nowrap;">
          <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:${esc(color)};margin-right:4px;"></span>
          <span style="color:${esc(color)};font-weight:bold;">${esc(estadoNombre)}</span>
        </td>
        <td style="font-size:9px;color:#555;">${esc(ps.observaciones) || '—'}</td>
      </tr>`;
    }).join('');

    puntosSeguridadHTML = `
    <div class="section-wrapper">
      <div class="section-header">Puntos de Seguridad</div>
      <table>
        <thead class="table-subheader">
          <tr><th>Punto</th><th style="width:120px;">Estado</th><th>Observaciones</th></tr>
        </thead>
        <tbody>${psRows}</tbody>
      </table>
    </div>`;
  }

  // Servicios a realizar
  const serviciosLista = p.servicios.length > 0
    ? p.servicios.map(s => `<div style="padding:3px 0;border-bottom:1px dashed #eee;font-size:10px;">&#9658; ${esc(s.descripcion)}</div>`).join('')
    : '<div style="color:#999;font-style:italic;font-size:9px;">Sin servicios registrados</div>';

  return `
  <div class="page page-break">
    <!-- ENCABEZADO COMPACTO -->
    <div class="header-compact">
      <img src="${logoUrl}" alt="Logo" onerror="this.style.display='none'">
      <div class="info-linea">
        <strong>SERVICIO GUDIÑO</strong> &nbsp;|&nbsp;
        Folio: <span class="folio-compact">${esc(p.folio)}</span> &nbsp;|&nbsp;
        Cliente: ${esc(p.cliente.nombreCompleto)} &nbsp;|&nbsp;
        Vehículo: ${esc(p.vehiculo.marca)} ${esc(p.vehiculo.modelo)} ${esc(p.vehiculo.year) || ''} — Placas: ${esc(p.vehiculo.placas)}
      </div>
    </div>

    <!-- PROBLEMA / DIAGNÓSTICO -->
    <div class="two-col">
      <div>
        <div class="section-header" style="margin-bottom:4px;">Problema Reportado por el Cliente</div>
        <div style="border:1px solid #e0e0e0;padding:8px;min-height:60px;font-size:10px;color:#333;line-height:1.5;">
          ${esc(p.problemaReportado) || '<span style="color:#bbb;">No especificado</span>'}
        </div>
      </div>
      <div>
        <div class="section-header" style="margin-bottom:4px;">Diagnóstico Técnico</div>
        <div style="border:1px solid #e0e0e0;padding:8px;min-height:60px;font-size:10px;color:#333;line-height:1.5;">
          ${esc(p.diagnosticoTecnico) || '<span style="color:#bbb;">Sin diagnóstico</span>'}
        </div>
      </div>
    </div>

    <!-- INSPECCIÓN EXTERIORES -->
    <div class="section-wrapper">
      <div class="section-header">Inspección — Exteriores</div>
      <table class="inspeccion-tabla">
        <tbody>${filasInspeccion(extCol1, extCol2)}</tbody>
      </table>
    </div>

    <!-- INSPECCIÓN INTERIORES -->
    <div class="section-wrapper">
      <div class="section-header">Inspección — Interiores</div>
      <table class="inspeccion-tabla">
        <tbody>${filasInspeccion(intrCol1, intrCol2)}</tbody>
      </table>
    </div>

    ${puntosSeguridadHTML}

    <!-- SERVICIOS A REALIZAR -->
    <div class="two-col">
      <div>
        <div class="section-header" style="margin-bottom:6px;">Servicios a Realizar</div>
        ${serviciosLista}
      </div>
      <div>
        <div class="section-header" style="margin-bottom:4px;">Daños Adicionales</div>
        <table>
          <thead class="table-subheader">
            <tr><th>Ubicación</th><th>Tipo</th><th>Descripción</th></tr>
          </thead>
          <tbody>${danosRows}</tbody>
        </table>
      </div>
    </div>
  </div>`;
}

// ---------------------------------------------------------------------------
// PÁGINA 3 — Firmas
// ---------------------------------------------------------------------------
function pagina3(p: Presupuesto, logoUrl: string): string {
  return `
  <div class="page page-break">
    <!-- ENCABEZADO COMPACTO -->
    <div class="header-compact">
      <img src="${logoUrl}" alt="Logo" onerror="this.style.display='none'">
      <div class="info-linea">
        <strong>SERVICIO GUDIÑO</strong> &nbsp;|&nbsp;
        Folio: <span class="folio-compact">${esc(p.folio)}</span> &nbsp;|&nbsp;
        Cliente: ${esc(p.cliente.nombreCompleto)} &nbsp;|&nbsp;
        Vehículo: ${esc(p.vehiculo.marca)} ${esc(p.vehiculo.modelo)} — Placas: ${esc(p.vehiculo.placas)}
      </div>
    </div>

    <!-- RESUMEN FINANCIERO -->
    <div class="caja-resumen">
      <div class="caja-resumen-titulo">Resumen del Presupuesto</div>
      <div class="caja-resumen-row">
        <span>Total Servicios</span><span>${mxn(p.resumen.servicios)}</span>
      </div>
      <div class="caja-resumen-row">
        <span>Total Refacciones</span><span>${mxn(p.resumen.refacciones)}</span>
      </div>
      <div class="caja-resumen-row">
        <span>Total Mano de Obra</span><span>${mxn(p.resumen.manoDeObra)}</span>
      </div>
      ${p.resumen.incluirIVA ? `
      <div class="caja-resumen-row">
        <span>Subtotal</span><span>${mxn(p.resumen.subtotal)}</span>
      </div>
      <div class="caja-resumen-row">
        <span>IVA (16%)</span><span>${mxn(p.resumen.iva)}</span>
      </div>` : ''}
      <div class="caja-resumen-row destacado">
        <span>TOTAL ${p.resumen.incluirIVA ? '(CON IVA)' : '(SIN IVA)'}</span>
        <span>${mxn(p.resumen.total)}</span>
      </div>
      <div class="caja-resumen-row" style="margin-top:4px;">
        <span>(-) Anticipo recibido</span><span>${mxn(p.resumen.anticipo)}</span>
      </div>
      <div class="caja-resumen-row" style="font-weight:bold;color:#111;">
        <span>SALDO RESTANTE</span><span>${mxn(p.resumen.restante)}</span>
      </div>
    </div>

    <!-- NOTA ANTICIPO -->
    <div style="text-align:center;font-size:10px;color:#CC0000;font-weight:bold;margin-bottom:8px;padding:6px;border:1px dashed #CC0000;border-radius:4px;">
      Todo trabajo autorizado requiere un anticipo del 35% del total del presupuesto
    </div>

    <!-- AUTORIZACIÓN -->
    <div style="border:1px solid #e0e0e0;padding:10px;margin-bottom:24px;font-size:10px;color:#333;line-height:1.6;">
      <strong>AUTORIZACIÓN:</strong> Con la firma de este documento, el cliente autoriza la realización de los trabajos
      descritos en este presupuesto conforme a los términos y condiciones establecidos y acepta la póliza de garantía
      de Servicio Gudiño. El taller se compromete a avisar al cliente en caso de encontrar fallas adicionales
      que modifiquen el costo final antes de proceder con los trabajos adicionales.
    </div>

    <!-- FIRMAS -->
    <div class="firma-grid" style="margin-top:60px;">
      <div>
        <div style="height:50px;"></div>
        <div class="firma-linea">
          <div class="firma-label">FIRMA ENCARGADO</div>
          <div style="font-size:9px;color:#666;">Servicio Gudiño</div>
        </div>
      </div>
      <div>
        <div style="height:50px;"></div>
        <div class="firma-linea">
          <div class="firma-label">FIRMA CLIENTE</div>
          <div style="font-size:9px;color:#666;">${esc(p.cliente.nombreCompleto) || '___________________________'}</div>
        </div>
      </div>
    </div>

    <!-- PIE LEGAL -->
    <div style="margin-top:32px;padding-top:10px;border-top:1px solid #e0e0e0;font-size:8px;color:#999;text-align:center;line-height:1.5;">
      Este documento es un presupuesto informativo. Los precios son válidos por 15 días a partir de la fecha de emisión.
      Servicio Gudiño — Eje Central #61, Col. Ex Hipódromo de Peralvillo, CDMX — Tel: 55 8325 8996
    </div>
  </div>`;
}

// ---------------------------------------------------------------------------
// PÁGINA 4 — Póliza de Garantía
// ---------------------------------------------------------------------------
function pagina4(logoUrl: string): string {
  const clausulas = [
    {
      num: '1',
      titulo: 'Cobertura de garantía',
      texto: 'Servicio Gudiño garantiza la mano de obra y refacciones instaladas por un período de <strong>3 meses o 5,000 km</strong> (lo que ocurra primero), contados a partir de la fecha de entrega del vehículo, siempre que el vehículo haya sido retirado dentro de los 5 días hábiles posteriores a la notificación de entrega.',
    },
    {
      num: '2',
      titulo: 'Lugar de garantía',
      texto: 'La garantía es válida <strong>exclusivamente en las instalaciones de Servicio Gudiño</strong>, ubicadas en Eje Central #61, Col. Ex Hipódromo de Peralvillo, CDMX. No se aceptan garantías en talleres externos ni reembolsos por reparaciones realizadas en otros establecimientos.',
    },
    {
      num: '3',
      titulo: 'Exclusiones',
      texto: 'Quedan excluidas de la garantía: (a) fallas causadas por accidentes, uso indebido, negligencia o falta de mantenimiento posterior al servicio; (b) daños por instalación de accesorios o modificaciones no realizadas por Servicio Gudiño; (c) piezas o componentes desgastados naturalmente por el uso; (d) daños por líquidos ajenos al mantenimiento ordinario; (e) fallas eléctricas no relacionadas con el servicio realizado.',
    },
    {
      num: '4',
      titulo: 'Responsabilidad del cliente',
      texto: 'El cliente deberá respetar las indicaciones de mantenimiento entregadas por Servicio Gudiño al momento de recoger el vehículo. El incumplimiento de dichas indicaciones anulará la garantía automáticamente.',
    },
    {
      num: '5',
      titulo: 'Tiempo de revisión',
      texto: 'Al presentar el vehículo por garantía, Servicio Gudiño realizará una revisión técnica en un plazo máximo de <strong>2 días hábiles</strong>. Si se confirma que la falla está cubierta por la garantía, la reparación se realizará sin costo para el cliente en un plazo acordado con el mismo.',
    },
    {
      num: '6',
      titulo: 'Alcance del servicio de garantía',
      texto: 'El servicio de garantía cubre exclusivamente los trabajos especificados en la orden de servicio. Cualquier falla adicional detectada durante la revisión de garantía será presupuestada por separado y requerirá autorización del cliente para su reparación.',
    },
    {
      num: '7',
      titulo: 'Horarios de atención',
      texto: 'Servicio Gudiño atiende solicitudes de garantía en horario de <strong>lunes a viernes de 9:00 a 18:00 hrs y sábados de 9:00 a 14:00 hrs</strong>. No se atienden garantías en días festivos.',
    },
    {
      num: '8',
      titulo: 'Traslado del vehículo',
      texto: 'El traslado del vehículo a las instalaciones de Servicio Gudiño para efectuar la garantía será responsabilidad del cliente. Servicio Gudiño no se hace responsable por gastos de grúa, remolque o transporte, salvo acuerdo previo y escrito.',
    },
    {
      num: '9',
      titulo: 'Responsabilidad limitada',
      texto: 'La responsabilidad de Servicio Gudiño se limita al costo de la reparación cubierta por garantía. En ningún caso se reconocerán daños indirectos, lucro cesante, gastos de transporte, alojamiento u otros costos derivados de la falla del vehículo.',
    },
    {
      num: '10',
      titulo: 'Aceptación',
      texto: 'Al recoger el vehículo y firmar la orden de entrega, el cliente declara haber leído y aceptado íntegramente los términos de esta póliza de garantía. La garantía no es transferible a terceros.',
    },
  ];

  const clausulasHTML = clausulas.map(c => `
    <div class="clausula">
      <span class="clausula-num">${c.num}.</span>
      <strong>${c.titulo}:</strong> <span>${c.texto}</span>
    </div>`).join('');

  return `
  <div class="page page-break">
    <!-- ENCABEZADO CON LOGO -->
    <div style="text-align:center;margin-bottom:4px;">
      <img src="${logoUrl}" alt="Logo Servicio Gudiño" style="height:56px;width:auto;" onerror="this.style.display='none'">
    </div>

    <div class="garantia-titulo">PÓLIZA DE GARANTÍA – SERVICIO GUDIÑO</div>
    <div class="garantia-subtitulo">
      Eje Central #61, Col. Ex Hipódromo de Peralvillo, CDMX &nbsp;|&nbsp; Tel: 55 8325 8996
    </div>

    <div class="garantia-clausulas">
      ${clausulasHTML}
    </div>

    <div class="pie-garantia">
      <strong>Servicio Gudiño</strong> — Eje Central #61, Col. Ex Hipódromo de Peralvillo, CDMX<br>
      Tel: 55 8325 8996 &nbsp;|&nbsp; Facebook / Instagram: <strong>carservicegudino</strong><br>
      <span style="font-size:8px;color:#999;margin-top:4px;display:inline-block;">
        Esta póliza forma parte integral de la orden de servicio y tiene validez únicamente con el sello y firma del encargado del taller.
      </span>
    </div>
  </div>`;
}

// ---------------------------------------------------------------------------
// Función principal exportada
// ---------------------------------------------------------------------------
export function generarOrdenHTML(presupuesto: Presupuesto, logoUrl: string): string {
  return `<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Orden ${esc(presupuesto.folio)} — Servicio Gudiño</title>
  ${estilos()}
</head>
<body>
  ${pagina1(presupuesto, logoUrl)}
  ${pagina2(presupuesto, logoUrl)}
  ${pagina3(presupuesto, logoUrl)}
  ${pagina4(logoUrl)}
</body>
</html>`;
}
