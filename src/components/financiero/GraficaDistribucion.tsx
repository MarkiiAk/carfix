import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer } from 'recharts';

interface Props {
  gananciaReal: number;
  totalSueldos: number;
  costoRefacciones: number;
  totalPagosFijos: number;
  gastosVariables: number;
}

const formatMoneda = (monto: number): string =>
  new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 0,
  }).format(monto);

interface TooltipPayloadItem {
  name: string;
  value: number;
  payload: { color: string };
}

interface CustomTooltipProps {
  active?: boolean;
  payload?: TooltipPayloadItem[];
}

const CustomTooltip = ({ active, payload }: CustomTooltipProps) => {
  if (active && payload && payload.length) {
    const item = payload[0];
    return (
      <div className="bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm shadow-lg">
        <p className="text-gray-400 mb-0.5">{item.name}</p>
        <p className="text-white font-semibold">{formatMoneda(item.value)}</p>
      </div>
    );
  }
  return null;
};

export const GraficaDistribucion = ({
  gananciaReal,
  totalSueldos,
  costoRefacciones,
  totalPagosFijos,
  gastosVariables,
}: Props) => {
  const datos = [
    { name: 'Sueldos',           value: Math.max(0, totalSueldos),      color: '#6366f1' },
    { name: 'Costo refacciones', value: Math.max(0, costoRefacciones),  color: '#f59e0b' },
    { name: 'Pagos fijos',       value: Math.max(0, totalPagosFijos),   color: '#ec4899' },
    { name: 'Gastos variables',  value: Math.max(0, gastosVariables),   color: '#14b8a6' },
    {
      name: gananciaReal >= 0 ? 'Ganancia neta' : 'Déficit',
      value: Math.abs(gananciaReal),
      color: gananciaReal >= 0 ? '#CBF518' : '#ef4444',
    },
  ].filter(d => d.value > 0);

  if (datos.length === 0) {
    return (
      <p className="text-sm text-gray-400 dark:text-gray-500 py-4">
        Sin datos suficientes para mostrar la distribución.
      </p>
    );
  }

  return (
    <div className="flex flex-col items-center gap-6 sm:flex-row sm:items-start sm:gap-8">
      <div className="w-full max-w-[220px] mx-auto sm:mx-0 flex-shrink-0">
        <ResponsiveContainer width="100%" height={220}>
          <PieChart>
            <Pie
              data={datos}
              cx="50%"
              cy="50%"
              innerRadius={55}
              outerRadius={90}
              dataKey="value"
              stroke="none"
            >
              {datos.map((entry, index) => (
                <Cell key={index} fill={entry.color} />
              ))}
            </Pie>
            <Tooltip content={<CustomTooltip />} />
          </PieChart>
        </ResponsiveContainer>
      </div>

      {/* Leyenda */}
      <div className="flex-1 space-y-3">
        {datos.map(d => (
          <div key={d.name} className="flex items-center justify-between gap-4">
            <div className="flex items-center gap-2.5 min-w-0">
              <span
                className="w-3 h-3 rounded-sm flex-shrink-0"
                style={{ backgroundColor: d.color }}
              />
              <span className="text-sm text-gray-700 dark:text-gray-300 truncate">{d.name}</span>
            </div>
            <span className="text-sm font-semibold text-gray-800 dark:text-gray-100 tabular-nums flex-shrink-0">
              {formatMoneda(d.value)}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
};
