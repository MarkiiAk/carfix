-- Teardown: elimina todo el seed de enero 2020
DELETE go2 FROM gastos_orden go2
  INNER JOIN ordenes_servicio os ON go2.orden_id = os.id
  WHERE os.numero_orden LIKE 'TEST-2020-%';
DELETE ro FROM refacciones_orden ro
  INNER JOIN ordenes_servicio os ON ro.orden_id = os.id
  WHERE os.numero_orden LIKE 'TEST-2020-%';
DELETE so FROM servicios_orden so
  INNER JOIN ordenes_servicio os ON so.orden_id = os.id
  WHERE os.numero_orden LIKE 'TEST-2020-%';
DELETE FROM ordenes_servicio WHERE numero_orden LIKE 'TEST-2020-%';
DELETE FROM gastos_administrativos WHERE mes = 1 AND anio = 2020 AND concepto LIKE '[SEED]%';
DELETE FROM vehiculos WHERE notas = '[SEED] enero-2020';
DELETE FROM clientes  WHERE notas = '[SEED] enero-2020';
SELECT 'Teardown enero 2020 completo.' AS resultado;
