import { jsPDF } from 'jspdf';
import type {
  ResumenFinancieroResponse,
  OrdenesFinancieroResponse,
  EmpleadoSueldo,
  PagoFijo,
  GastosAdminResponse,
} from '../types';

// ---------------------------------------------------------------------------
// Tipos de entrada
// ---------------------------------------------------------------------------

export interface ReporteFinancieroParams {
  resumen: ResumenFinancieroResponse;
  ordenes: OrdenesFinancieroResponse;
  empleados: EmpleadoSueldo[];
  pagosFijos: PagoFijo[];
  gastos: GastosAdminResponse;
  labelPeriodo: string;
  tipoPeriodo: 'semana' | 'mes';
}

// ---------------------------------------------------------------------------
// Paleta de colores (RGB)
// ---------------------------------------------------------------------------

type RGB = [number, number, number];

const C: Record<string, RGB> = {
  fondoOscuro:      [15,  35,  24],
  lime:             [203, 245, 24],
  blanco:           [255, 255, 255],
  textoOscuro:      [30,  30,  30],
  textoSecundario:  [120, 130, 120],
  textoSobreOscuro: [200, 200, 200],
  filaNormal:       [255, 255, 255],
  filaAlterna:      [245, 248, 245],
  separador:        [200, 215, 200],
  fondoAlerta:      [255, 240, 240],
  bordeAlerta:      [200, 80,  80],
  fondoFiscal:      [255, 250, 235],
  bordeFiscal:      [200, 180, 80],
  barraSubeldos:    [255, 165, 60],
  barraRefas:       [80,  140, 255],
  barraFijos:       [160, 160, 160],
  barraGanancia:    [203, 245, 24],
  fondoNegativo:    [90,  20,  20],
  montoNegativo:    [255, 120, 120],
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const fmt = (n: number): string =>
  new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
  }).format(n);

function setFill(doc: jsPDF, color: RGB): void {
  doc.setFillColor(color[0], color[1], color[2]);
}

function setDraw(doc: jsPDF, color: RGB): void {
  doc.setDrawColor(color[0], color[1], color[2]);
}

function setTextColor(doc: jsPDF, color: RGB): void {
  doc.setTextColor(color[0], color[1], color[2]);
}

// ---------------------------------------------------------------------------
// Función principal
// ---------------------------------------------------------------------------

export function generarReporteFinanciero(params: ReporteFinancieroParams): void {
  const { resumen, empleados, pagosFijos, gastos, labelPeriodo, tipoPeriodo } = params;

  // --- Cálculos financieros ---
  const ingresosbrutos = resumen.resumen.total_facturado;
  const costoRefacciones = resumen.refacciones.costo;
  const gananciaRefacciones = resumen.refacciones.margen;

  const diasPeriodo = tipoPeriodo === 'semana' ? 5 : 22;

  const totalSueldos = empleados
    .filter(e => e.activo)
    .reduce((acc, e) => acc + e.sueldo_diario * diasPeriodo, 0);

  const totalPagosFijos = pagosFijos
    .filter(p => p.activo)
    .reduce((acc, p) => {
      if (p.frecuencia === 'semanal') {
        return acc + (tipoPeriodo === 'semana' ? p.monto : p.monto * 4);
      }
      return acc + (tipoPeriodo === 'semana' ? p.monto / 4 : p.monto);
    }, 0);

  const totalGastosVariables = gastos.gastos?.reduce((acc, g) => acc + g.monto, 0) ?? 0;
  const totalCostos = costoRefacciones + totalSueldos + totalPagosFijos + totalGastosVariables;
  const gananciaNeta = ingresosbrutos - costoRefacciones - totalSueldos - totalPagosFijos - totalGastosVariables;
  const margenPct = ingresosbrutos > 0 ? (gananciaNeta / ingresosbrutos) * 100 : 0;
  const porSocio = gananciaNeta / 2;

  const alertaSueldos = ingresosbrutos > 0 && totalSueldos / ingresosbrutos > 0.40;
  const alertaMargen = margenPct < 15;
  const alertaNegativa = gananciaNeta < 0;

  const hoyStr = new Date().toLocaleDateString('es-MX');

  // --- Instanciar jsPDF ---
  const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });

  // =========================================================================
  // PÁGINA 1
  // =========================================================================

  // -------------------------------------------------------------------------
  // Header (y: 0 → 42mm)
  // -------------------------------------------------------------------------
  setFill(doc, C.fondoOscuro);
  doc.rect(0, 0, 210, 42, 'F');

  // "SAG GARAGE" — izquierda, bold 18pt, lime
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(18);
  setTextColor(doc, C.lime);
  doc.text('SAG GARAGE', 14, 18);

  // "Reporte Financiero" — 9pt blanco
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  setTextColor(doc, C.blanco);
  doc.text('Reporte Financiero', 14, 25);

  // Label período — derecha, 8pt textoSobreOscuro
  doc.setFontSize(8);
  setTextColor(doc, C.textoSobreOscuro);
  doc.text(labelPeriodo, 196, 18, { align: 'right' });

  // Fecha generado
  doc.setFontSize(7);
  doc.text(`Generado: ${hoyStr}`, 196, 24, { align: 'right' });

  // Línea lime
  setDraw(doc, C.lime);
  doc.setLineWidth(1.5);
  doc.line(0, 42, 210, 42);

  // -------------------------------------------------------------------------
  // 4 KPI Cards (y: 48 → 78mm)
  // -------------------------------------------------------------------------
  const cardX = [14, 57, 100, 143];
  const cardW = 41;
  const cardH = 26;
  const cardY = 48;

  // Card 1 — Ingresos Brutos
  setFill(doc, [240, 245, 240] as RGB);
  setDraw(doc, C.separador);
  doc.setLineWidth(0.3);
  doc.rect(cardX[0], cardY, cardW, cardH, 'FD');
  doc.setFontSize(6);
  doc.setFont('helvetica', 'normal');
  setTextColor(doc, C.textoSecundario);
  doc.text('INGRESOS BRUTOS', cardX[0] + 2, cardY + 7);
  doc.setFontSize(15);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.textoOscuro);
  doc.text(fmt(ingresosbrutos), cardX[0] + cardW / 2, cardY + 18, { align: 'center' });

  // Card 2 — Costos Totales
  setFill(doc, [240, 245, 240] as RGB);
  doc.rect(cardX[1], cardY, cardW, cardH, 'FD');
  doc.setFontSize(6);
  doc.setFont('helvetica', 'normal');
  setTextColor(doc, C.textoSecundario);
  doc.text('COSTOS TOTALES', cardX[1] + 2, cardY + 7);
  doc.setFontSize(15);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.textoOscuro);
  doc.text(fmt(totalCostos), cardX[1] + cardW / 2, cardY + 18, { align: 'center' });

  // Card 3 — Ganancia Neta (protagonista)
  if (alertaNegativa) {
    setFill(doc, C.fondoNegativo);
  } else {
    setFill(doc, C.fondoOscuro);
  }
  doc.rect(cardX[2], cardY, cardW, cardH, 'FD');
  doc.setFontSize(6);
  doc.setFont('helvetica', 'normal');
  setTextColor(doc, C.blanco);
  doc.text('GANANCIA NETA', cardX[2] + 2, cardY + 7);
  doc.setFontSize(18);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, alertaNegativa ? C.montoNegativo : C.lime);
  const gananciaStr = fmt(gananciaNeta);
  const gananciaFontSize = gananciaStr.length > 10 ? 11 : 18;
  doc.setFontSize(gananciaFontSize);
  doc.text(gananciaStr, cardX[2] + cardW / 2, cardY + 18, { align: 'center' });

  // Card 4 — Por Socio
  setFill(doc, [230, 240, 230] as RGB);
  setDraw(doc, C.separador);
  doc.rect(cardX[3], cardY, cardW, cardH, 'FD');
  doc.setFontSize(6);
  doc.setFont('helvetica', 'normal');
  setTextColor(doc, C.textoSecundario);
  doc.text('POR SOCIO', cardX[3] + 2, cardY + 7);
  doc.setFontSize(13);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.textoOscuro);
  doc.text(fmt(porSocio), cardX[3] + cardW / 2, cardY + 16, { align: 'center' });
  doc.setFontSize(6);
  doc.setFont('helvetica', 'normal');
  setTextColor(doc, C.textoSecundario);
  doc.text('Paco / Enrique', cardX[3] + cardW / 2, cardY + 22, { align: 'center' });

  // -------------------------------------------------------------------------
  // Barra de distribución (y: 84 → 98mm)
  // -------------------------------------------------------------------------
  doc.setFontSize(8);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.textoOscuro);
  doc.text('DISTRIBUCIÓN DE INGRESOS', 14, 82);

  const barraX = 14;
  const barraY = 86;
  const barraW = 182;
  const barraH = 8;

  // Fondo de la barra
  setFill(doc, [220, 225, 220] as RGB);
  doc.rect(barraX, barraY, barraW, barraH, 'F');

  if (ingresosbrutos > 0) {
    let xCursor = barraX;

    const segSueldos = (totalSueldos / ingresosbrutos) * barraW;
    const segRefas = (costoRefacciones / ingresosbrutos) * barraW;
    const segFijos = (totalPagosFijos / ingresosbrutos) * barraW;
    const segGanancia = Math.max(0, barraW - segSueldos - segRefas - segFijos);

    if (segSueldos > 0) {
      setFill(doc, C.barraSubeldos);
      doc.rect(xCursor, barraY, segSueldos, barraH, 'F');
      xCursor += segSueldos;
    }
    if (segRefas > 0) {
      setFill(doc, C.barraRefas);
      doc.rect(xCursor, barraY, segRefas, barraH, 'F');
      xCursor += segRefas;
    }
    if (segFijos > 0) {
      setFill(doc, C.barraFijos);
      doc.rect(xCursor, barraY, segFijos, barraH, 'F');
      xCursor += segFijos;
    }
    if (segGanancia > 0) {
      setFill(doc, C.barraGanancia);
      doc.rect(xCursor, barraY, segGanancia, barraH, 'F');
    }

    // Leyenda (y = 97)
    const legendY = 97;
    const items: Array<{ color: RGB; label: string; pct: number }> = [
      { color: C.barraSubeldos, label: 'Sueldos',   pct: (totalSueldos / ingresosbrutos) * 100 },
      { color: C.barraRefas,    label: 'Refacciones', pct: (costoRefacciones / ingresosbrutos) * 100 },
      { color: C.barraFijos,    label: 'Pagos fijos', pct: (totalPagosFijos / ingresosbrutos) * 100 },
      { color: C.barraGanancia, label: 'Ganancia',   pct: Math.max(0, (gananciaNeta / ingresosbrutos) * 100) },
    ];

    let lx = barraX;
    items.forEach(item => {
      setFill(doc, item.color);
      doc.rect(lx, legendY - 3, 3, 3, 'F');
      doc.setFontSize(6);
      doc.setFont('helvetica', 'normal');
      setTextColor(doc, C.textoOscuro);
      doc.text(`${item.label} ${item.pct.toFixed(0)}%`, lx + 4, legendY);
      lx += 36;
    });

    // Alerta de sueldos
    if (alertaSueldos) {
      const pctSueldos = ((totalSueldos / ingresosbrutos) * 100).toFixed(0);
      doc.setFontSize(6);
      setTextColor(doc, [180, 80, 0] as RGB);
      doc.text(`! Sueldos: ${pctSueldos}% — umbral: 40%`, 196, legendY, { align: 'right' });
    }
  }

  // -------------------------------------------------------------------------
  // P&L y Gastos (y: 106 → 175mm)
  // -------------------------------------------------------------------------

  // --- Columna izquierda: Estado de resultados (x=14, w=85) ---
  const colIzqX = 14;
  const colDerX = 108;
  const plY = 106;
  const filaH = 8;

  doc.setFontSize(8);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.textoOscuro);
  doc.text('INGRESOS Y COSTOS', colIzqX, plY);

  setDraw(doc, C.separador);
  doc.setLineWidth(0.4);
  doc.line(colIzqX, plY + 3, colIzqX + 85, plY + 3);

  const filasIzq: Array<{ label: string; valor: string; color?: RGB; bold?: boolean; fondo?: boolean }> = [
    { label: 'Ingresos brutos',       valor: fmt(ingresosbrutos),                                     bold: false },
    { label: '  Costo refacciones',   valor: '-' + fmt(costoRefacciones),                             color: [180, 60, 60] as RGB },
    { label: '  Margen refacciones',  valor: '+' + fmt(gananciaRefacciones),                          color: [60, 150, 60] as RGB },
  ];

  filasIzq.forEach((fila, i) => {
    const fy = plY + 6 + i * filaH;
    // Fondo alterno
    const fondoColor: RGB = i % 2 === 0 ? C.filaNormal : C.filaAlterna;
    setFill(doc, fondoColor);
    doc.rect(colIzqX, fy, 85, filaH, 'F');

    doc.setFontSize(7);
    doc.setFont('helvetica', fila.bold === false ? 'normal' : 'normal');
    setTextColor(doc, fila.color ?? C.textoOscuro);
    doc.text(fila.label, colIzqX + 2, fy + filaH - 2);
    doc.text(fila.valor, colIzqX + 83, fy + filaH - 2, { align: 'right' });
  });

  const sepYIzq = plY + 6 + filasIzq.length * filaH + 1;
  setDraw(doc, C.separador);
  doc.line(colIzqX, sepYIzq, colIzqX + 85, sepYIzq);

  // Subtotal operativo
  const subtotalY = sepYIzq + 6;
  setFill(doc, C.filaAlterna);
  doc.rect(colIzqX, subtotalY - filaH + 2, 85, filaH, 'F');
  doc.setFontSize(7);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.textoOscuro);
  doc.text('Subtotal operativo', colIzqX + 2, subtotalY);
  doc.text(fmt(ingresosbrutos - costoRefacciones), colIzqX + 83, subtotalY, { align: 'right' });

  // --- Columna derecha: Gastos del período (x=108, w=88) ---
  doc.setFontSize(8);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.textoOscuro);
  doc.text('GASTOS DEL PERÍODO', colDerX, plY);

  doc.setLineWidth(0.4);
  setDraw(doc, C.separador);
  doc.line(colDerX, plY + 3, colDerX + 88, plY + 3);

  const filasDer: Array<{ label: string; valor: string }> = [
    { label: 'Sueldos',          valor: fmt(totalSueldos) },
    { label: 'Pagos fijos',      valor: fmt(totalPagosFijos) },
    { label: 'Gastos variables', valor: fmt(totalGastosVariables) },
  ];

  filasDer.forEach((fila, i) => {
    const fy = plY + 6 + i * filaH;
    const fondoColor: RGB = i % 2 === 0 ? C.filaNormal : C.filaAlterna;
    setFill(doc, fondoColor);
    doc.rect(colDerX, fy, 88, filaH, 'F');
    doc.setFontSize(7);
    doc.setFont('helvetica', 'normal');
    setTextColor(doc, C.textoOscuro);
    doc.text(fila.label, colDerX + 2, fy + filaH - 2);
    doc.text(fila.valor, colDerX + 86, fy + filaH - 2, { align: 'right' });
  });

  const sepYDer = plY + 6 + filasDer.length * filaH + 1;
  setDraw(doc, C.separador);
  doc.line(colDerX, sepYDer, colDerX + 88, sepYDer);

  const totalGastosY = sepYDer + 6;
  setFill(doc, C.filaAlterna);
  doc.rect(colDerX, totalGastosY - filaH + 2, 88, filaH, 'F');
  doc.setFontSize(7);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.textoOscuro);
  doc.text('Total gastos', colDerX + 2, totalGastosY);
  doc.text(fmt(totalSueldos + totalPagosFijos + totalGastosVariables), colDerX + 86, totalGastosY, { align: 'right' });

  // -------------------------------------------------------------------------
  // Franja de resultado final (y: 176 → 190mm)
  // -------------------------------------------------------------------------
  const franjaY = 176;
  setFill(doc, alertaNegativa ? C.fondoNegativo : C.fondoOscuro);
  doc.rect(14, franjaY, 182, 14, 'F');

  doc.setFontSize(8);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.blanco);
  doc.text('GANANCIA NETA DEL PERÍODO', 20, franjaY + 9);

  const simbolo = gananciaNeta >= 0 ? '▲' : '▼';
  setTextColor(doc, alertaNegativa ? C.montoNegativo : C.lime);
  doc.setFontSize(14);
  doc.text(fmt(gananciaNeta), 190, franjaY + 9, { align: 'right' });
  doc.setFontSize(8);
  doc.text(simbolo, 196, franjaY + 9);

  // -------------------------------------------------------------------------
  // Footer página 1 (y: 286)
  // -------------------------------------------------------------------------
  setDraw(doc, C.separador);
  doc.setLineWidth(0.5);
  doc.line(0, 286, 210, 286);
  doc.setFontSize(6);
  doc.setFont('helvetica', 'normal');
  setTextColor(doc, C.textoSecundario);
  doc.text('SAG Garage × AkLabs  ·  Reporte confidencial  ·  Página 1 de 2', 105, 291, { align: 'center' });

  // =========================================================================
  // PÁGINA 2
  // =========================================================================
  doc.addPage();

  // -------------------------------------------------------------------------
  // Header reducido (y: 0 → 18mm)
  // -------------------------------------------------------------------------
  setFill(doc, C.fondoOscuro);
  doc.rect(0, 0, 210, 18, 'F');
  doc.setFontSize(8);
  doc.setFont('helvetica', 'normal');
  setTextColor(doc, C.blanco);
  doc.text(`SAG GARAGE  ·  ${labelPeriodo}  ·  Página 2`, 105, 11, { align: 'center' });

  setDraw(doc, C.lime);
  doc.setLineWidth(1.5);
  doc.line(0, 18, 210, 18);

  // -------------------------------------------------------------------------
  // Top 5 Servicios (y: 24 → 105mm)
  // -------------------------------------------------------------------------
  doc.setFontSize(9);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.textoOscuro);
  doc.text('TOP SERVICIOS DEL PERÍODO', 14, 28);

  const topServicios = resumen.top_servicios?.slice(0, 5) ?? [];

  if (topServicios.length === 0) {
    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    setTextColor(doc, C.textoSecundario);
    doc.text('Sin datos para este período', 105, 55, { align: 'center' });
  } else {
    const maxServicio = topServicios[0].total_generado;

    topServicios.forEach((s, i) => {
      const fy = 32 + i * 14;
      const fondoColor: RGB = i % 2 === 0 ? C.filaNormal : C.filaAlterna;
      setFill(doc, fondoColor);
      doc.rect(14, fy, 182, 14, 'F');

      // Número
      doc.setFontSize(7);
      doc.setFont('helvetica', 'bold');
      setTextColor(doc, C.textoSecundario);
      doc.text(String(i + 1), 16, fy + 9);

      // Nombre
      doc.setFont('helvetica', 'normal');
      setTextColor(doc, C.textoOscuro);
      const nombreTrunc = s.descripcion.length > 38 ? s.descripcion.slice(0, 38) + '…' : s.descripcion;
      doc.text(nombreTrunc, 24, fy + 9);

      // Veces
      setTextColor(doc, C.textoSecundario);
      doc.text(`${s.veces}x`, 96, fy + 9, { align: 'right' });

      // Monto
      doc.setFont('helvetica', 'bold');
      setTextColor(doc, C.textoOscuro);
      doc.text(fmt(s.total_generado), 130, fy + 9, { align: 'right' });

      // Barra proporcional
      const propW = maxServicio > 0 ? (s.total_generado / maxServicio) * 46 : 0;
      setFill(doc, fondoColor);
      doc.rect(134, fy + 5, 46, 4, 'F');
      if (propW > 0) {
        setFill(doc, C.lime);
        doc.rect(134, fy + 5, propW, 4, 'F');
      }
    });
  }

  // -------------------------------------------------------------------------
  // Top 5 Clientes (y: 112 → 193mm)
  // -------------------------------------------------------------------------
  const topCliY = 112;
  doc.setFontSize(9);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.textoOscuro);
  doc.text('TOP CLIENTES DEL PERÍODO', 14, topCliY);

  const topClientes = resumen.top_clientes?.slice(0, 5) ?? [];

  if (topClientes.length === 0) {
    doc.setFontSize(8);
    doc.setFont('helvetica', 'normal');
    setTextColor(doc, C.textoSecundario);
    doc.text('Sin datos para este período', 105, topCliY + 20, { align: 'center' });
  } else {
    topClientes.forEach((c, i) => {
      const fy = topCliY + 4 + i * 14;
      const fondoColor: RGB = i % 2 === 0 ? C.filaNormal : C.filaAlterna;
      setFill(doc, fondoColor);
      doc.rect(14, fy, 182, 14, 'F');

      // Número
      doc.setFontSize(7);
      doc.setFont('helvetica', 'bold');
      setTextColor(doc, C.textoSecundario);
      doc.text(String(i + 1), 16, fy + 9);

      // Nombre
      doc.setFont('helvetica', 'normal');
      setTextColor(doc, C.textoOscuro);
      const nombreTrunc = c.nombre.length > 38 ? c.nombre.slice(0, 38) + '…' : c.nombre;
      doc.text(nombreTrunc, 24, fy + 9);

      // Visitas
      setTextColor(doc, C.textoSecundario);
      doc.text(`${c.num_visitas} ${c.num_visitas === 1 ? 'visita' : 'visitas'}`, 116, fy + 9, { align: 'right' });

      // Total
      doc.setFont('helvetica', 'bold');
      setTextColor(doc, C.textoOscuro);
      doc.text(fmt(c.total_gastado), 160, fy + 9, { align: 'right' });
    });
  }

  // -------------------------------------------------------------------------
  // Nota fiscal + alertas (y: 200mm)
  // -------------------------------------------------------------------------
  let notaY = 200;

  // Nota IVA — siempre visible
  setFill(doc, C.fondoFiscal);
  setDraw(doc, C.bordeFiscal);
  doc.setLineWidth(0.5);
  doc.rect(14, notaY, 182, 18, 'FD');

  doc.setFontSize(7);
  doc.setFont('helvetica', 'bold');
  setTextColor(doc, C.textoOscuro);
  doc.text('NOTA FISCAL', 18, notaY + 7);

  doc.setFont('helvetica', 'normal');
  setTextColor(doc, C.textoOscuro);
  const notaTexto = 'El IVA recaudado en este período no representa ganancia del taller. Debe declararse ante el SAT.';
  const notaLineas = doc.splitTextToSize(notaTexto, 172);
  doc.text(notaLineas, 18, notaY + 13);

  notaY += 22;

  // Alertas activas
  const tieneAlertas = alertaNegativa || alertaSueldos || alertaMargen;
  if (tieneAlertas) {
    setFill(doc, C.fondoAlerta);
    setDraw(doc, C.bordeAlerta);
    doc.setLineWidth(0.5);
    doc.rect(14, notaY, 182, 16, 'FD');

    doc.setFontSize(7);
    doc.setFont('helvetica', 'bold');
    setTextColor(doc, C.bordeAlerta);
    doc.text('AVISO', 18, notaY + 7);

    doc.setFont('helvetica', 'normal');
    let alertaTexto = '';
    if (alertaNegativa) {
      alertaTexto = 'El taller operó con pérdida este período. Revisar costos operativos.';
    } else if (alertaSueldos) {
      const pct = ((totalSueldos / ingresosbrutos) * 100).toFixed(0);
      alertaTexto = `Los sueldos representan el ${pct}% de los ingresos. Umbral saludable: 40%.`;
    } else if (alertaMargen) {
      alertaTexto = `Margen neto del ${margenPct.toFixed(0)}%. El umbral mínimo saludable es 15%.`;
    }
    const alertaLineas = doc.splitTextToSize(alertaTexto, 172);
    doc.text(alertaLineas, 18, notaY + 12);
  }

  // -------------------------------------------------------------------------
  // Footer página 2
  // -------------------------------------------------------------------------
  setDraw(doc, C.separador);
  doc.setLineWidth(0.5);
  doc.line(0, 286, 210, 286);
  doc.setFontSize(6);
  doc.setFont('helvetica', 'normal');
  setTextColor(doc, C.textoSecundario);
  doc.text('SAG Garage × AkLabs  ·  Reporte confidencial  ·  Página 2 de 2', 105, 291, { align: 'center' });

  // =========================================================================
  // Guardar
  // =========================================================================
  doc.save('reporte-sag-garage.pdf');
}
