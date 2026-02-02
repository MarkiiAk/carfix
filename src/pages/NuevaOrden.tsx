import { useEffect, useState } from 'react';
import { Sun, Moon, FileText, Download, Save, ArrowLeft } from 'lucide-react';
import { pdf } from '@react-pdf/renderer';
import { useNavigate } from 'react-router-dom';
import { usePresupuestoStore } from '../store/usePresupuestoStore';
import { ordenesAPI } from '../services/api';
import { GarageLoader } from '../components/ui/GarageLoader';
import {
  ClienteSection,
  VehiculoSection,
  InspeccionSection,
  ProblemaSection,
  ServiciosSection,
  RefaccionesSection,
  ManoObraSection,
  ResumenSection,
  GarantiaSection,
  PuntosSeguridadSection,
} from '../components/sections';
import { Button } from '../components/ui';
import { PDFDocument } from '../components/PDFDocument';

export const NuevaOrden = () => {
  const navigate = useNavigate();
  const { presupuesto, themeMode, toggleTheme, resetPresupuesto, markAsSaved } = usePresupuestoStore();
  const [showLoader, setShowLoader] = useState(false);

  // Limpiar formulario al montar el componente
  useEffect(() => {
    resetPresupuesto();
  }, [resetPresupuesto]);

  // Aplicar el tema al documento
  useEffect(() => {
    if (themeMode === 'dark') {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
  }, [themeMode]);

  // Calcular resumen al cargar
  useEffect(() => {
    usePresupuestoStore.getState().calcularResumen();
  }, []);

  const handleGeneratePDF = async () => {
    try {
      const blob = await pdf(<PDFDocument presupuesto={presupuesto} />).toBlob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      const nombreCliente = presupuesto.cliente.nombreCompleto.replace(/\s+/g, '_').toUpperCase();
      const modelo = presupuesto.vehiculo.modelo.replace(/\s+/g, '_').toUpperCase();
      link.download = `SAG_Garage_${presupuesto.folio}_${modelo}_${nombreCliente}.pdf`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Error al generar PDF:', error);
      alert('Hubo un error al generar el PDF. Por favor intenta de nuevo.');
    }
  };

  const handleSaveOrden = async () => {
    try {
      setShowLoader(true);
      
      // Convertir presupuesto a Orden
      const orden = {
        id: presupuesto.id,
        folio: presupuesto.folio,
        taller: presupuesto.taller,
        cliente: presupuesto.cliente,
        vehiculo: {
          ...presupuesto.vehiculo,
          nivelCombustible: presupuesto.vehiculo.nivelGasolina, // Mapear para backend
        },
        inspeccion: presupuesto.inspeccion,
        problemaReportado: presupuesto.problemaReportado,
        diagnosticoTecnico: presupuesto.diagnosticoTecnico,
        servicios: presupuesto.servicios,
        refacciones: presupuesto.refacciones,
        manoDeObra: presupuesto.manoDeObra,
        resumen: presupuesto.resumen,
        puntosSeguridad: presupuesto.puntosSeguridad || [],
        fechaSalida: presupuesto.fechaSalida?.toISOString() || null,
        fechaEntrada: presupuesto.fechaEntrada?.toISOString() || presupuesto.fecha.toISOString(),
        estado: 'abierta' as const,
        fechaCreacion: presupuesto.fecha.toISOString(),
        fechaModificacion: new Date().toISOString(),
      };
      
      console.log('💾 Guardando orden en API...');
      await ordenesAPI.create(orden);
      console.log('✅ Orden guardada exitosamente');
      markAsSaved();
    } catch (error) {
      console.error('Error al guardar la orden:', error);
      setShowLoader(false);
      alert('Hubo un error al guardar la orden. Por favor intenta de nuevo.');
    }
  };

  const handleLoaderComplete = () => {
    setShowLoader(false);
    resetPresupuesto();
    navigate('/dashboard');
  };


  return (
    <div className="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900 dark:to-gray-800 transition-colors duration-300">
      {/* Header */}
      <header className="sticky top-0 z-50 bg-white/80 dark:bg-gray-900/80 backdrop-blur-lg border-b border-gray-200 dark:border-gray-700 shadow-sm">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            {/* Logo y título */}
            <div className="flex items-center gap-4">
              <Button
                variant="secondary"
                onClick={() => navigate('/dashboard')}
                icon={<ArrowLeft size={20} />}
                className="!p-3"
                title="Volver al Dashboard"
              />
              <div className="w-12 h-12 bg-gradient-to-br from-sag-500 to-sag-600 rounded-xl flex items-center justify-center shadow-lg shadow-sag-500/30">
                <FileText className="text-white" size={24} />
              </div>
              <div>
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                  Nueva Orden
                </h1>
                <p className="text-sm text-gray-600 dark:text-gray-400">
                  Crear presupuesto
                </p>
              </div>
            </div>

            {/* Acciones */}
            <div className="flex items-center gap-3">
              {/* Toggle tema */}
              <Button
                variant="secondary"
                onClick={toggleTheme}
                icon={themeMode === 'light' ? <Moon size={20} /> : <Sun size={20} />}
                className="!p-3"
                title={`Cambiar a modo ${themeMode === 'light' ? 'oscuro' : 'claro'}`}
              />

              {/* Guardar Orden */}
              <Button
                variant="primary"
                onClick={handleSaveOrden}
                icon={<Save size={20} />}
                disabled={showLoader}
                className="hidden md:flex"
              >
                Guardar Orden
              </Button>

              {/* Generar PDF */}
              <Button
                variant="success"
                onClick={handleGeneratePDF}
                icon={<Download size={20} />}
                className="hidden md:flex"
              >
                Generar PDF
              </Button>
            </div>
          </div>

          {/* Botones móviles */}
          <div className="flex md:hidden gap-2 mt-3">
            <Button
              variant="primary"
              onClick={handleSaveOrden}
              icon={<Save size={18} />}
              disabled={showLoader}
              className="flex-1 !text-sm"
            >
              Guardar
            </Button>
            <Button
              variant="success"
              onClick={handleGeneratePDF}
              icon={<Download size={18} />}
              className="flex-1 !text-sm"
            >
              PDF
            </Button>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="container mx-auto px-4 py-8">
        <div className="max-w-7xl mx-auto space-y-6">
          {/* Información del Vehículo */}
          <VehiculoSection />

          {/* Información del Cliente */}
          <ClienteSection />

          {/* Inspección Visual del Vehículo */}
          <InspeccionSection />

          {/* Puntos de Seguridad */}
          <PuntosSeguridadSection 
            puntosSeguridad={presupuesto.puntosSeguridad || []}
            onChange={(puntos) => usePresupuestoStore.getState().updatePuntosSeguridad(puntos)}
          />

          {/* Problema y Diagnóstico */}
          <ProblemaSection />

          {/* Servicios */}
          <ServiciosSection />

          {/* Refacciones y Mano de Obra */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <RefaccionesSection />
            <ManoObraSection />
          </div>

          {/* Resumen Financiero */}
          <ResumenSection />

          {/* Garantía */}
          <GarantiaSection />
        </div>
      </main>

      {/* Footer */}
      <footer className="mt-12 py-6 bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-700 no-print">
        <div className="container mx-auto px-4">
          <div className="text-center text-sm text-gray-600 dark:text-gray-400">
            <p className="font-semibold mb-1">SAG Garage - Sistema de Presupuestos</p>
            <p>© {new Date().getFullYear()} Todos los derechos reservados</p>
          </div>
        </div>
      </footer>

      {/* Loader */}
      {showLoader && <GarageLoader onComplete={handleLoaderComplete} />}
    </div>
  );
};
