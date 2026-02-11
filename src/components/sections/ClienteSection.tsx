import React from 'react';
import { User, Phone, Mail } from 'lucide-react';
import { Card, FormField } from '../ui';
import { usePresupuestoStore } from '../../store/usePresupuestoStore';

interface ClienteSectionProps {
  disabled?: boolean;
}

export const ClienteSection: React.FC<ClienteSectionProps> = ({ disabled = false }) => {
  const { presupuesto, updateCliente } = usePresupuestoStore();
  const { cliente } = presupuesto;

  const handleChange = (field: keyof typeof cliente) => (value: string) => {
    updateCliente({ [field]: value });
  };

  return (
    <Card
      title="Información del Cliente"
      subtitle="Datos de contacto del propietario del vehículo"
      className="p-6"
    >
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <FormField
          name="nombreCompleto"
          label="👤 Nombre Completo"
          placeholder="Ej: Juan Pérez García"
          value={cliente.nombreCompleto}
          onChange={handleChange('nombreCompleto')}
          required
          disabled={disabled}
          validation={{
            required: true,
            minLength: 2,
            maxLength: 100,
          }}
        />
        
        <FormField
          name="telefono"
          label="📞 Teléfono"
          type="tel"
          placeholder="Ej: 555-123-4567"
          value={cliente.telefono}
          onChange={handleChange('telefono')}
          required
          disabled={disabled}
          validation={{
            required: true,
            phone: true,
            minLength: 10,
            maxLength: 15,
          }}
        />
        
        <div className="md:col-span-2">
          <FormField
            name="email"
            label="📧 Correo Electrónico"
            type="email"
            placeholder="correo@ejemplo.com"
            value={cliente.email}
            onChange={handleChange('email')}
            disabled={disabled}
            validation={{
              email: true,
              maxLength: 100,
            }}
          />
        </div>
      </div>
    </Card>
  );
};
