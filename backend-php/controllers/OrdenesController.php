<?php
/**
 * Controlador de órdenes de servicio
 * Basado en database-schema.sql REAL
 */

class OrdenesController {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Obtener todas las órdenes - GET /api/ordenes
     */
    public function getAll() {
        try {
            requireAuth();
            
            $stmt = $this->db->query('
                SELECT o.*, 
                       c.nombre as cliente_nombre,
                       c.telefono as cliente_telefono,
                       v.marca, v.modelo, v.anio, v.placas
                FROM ordenes_servicio o
                LEFT JOIN clientes c ON o.cliente_id = c.id
                LEFT JOIN vehiculos v ON o.vehiculo_id = v.id
                ORDER BY o.id DESC
            ');
            
            $ordenes = $stmt->fetchAll();
            
            // Procesar cada orden para incluir datos relacionados
            foreach ($ordenes as &$orden) {
                $orden = $this->enrichOrdenData($orden);
            }
            
            echo json_encode($ordenes);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Error al obtener órdenes',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Obtener una orden por ID - GET /api/ordenes/:id
     */
    public function getById($id) {
        try {
            requireAuth();
            
            $stmt = $this->db->prepare('
                SELECT o.*, 
                       c.nombre as cliente_nombre,
                       c.telefono as cliente_telefono,
                       c.email as cliente_email,
                       c.direccion as cliente_direccion,
                       v.marca, v.modelo, v.anio, v.placas, v.color, v.niv
                FROM ordenes_servicio o
                LEFT JOIN clientes c ON o.cliente_id = c.id
                LEFT JOIN vehiculos v ON o.vehiculo_id = v.id
                WHERE o.id = ?
            ');
            $stmt->execute([$id]);
            $orden = $stmt->fetch();
            
            if (!$orden) {
                http_response_code(404);
                echo json_encode(['error' => 'Orden no encontrada']);
                return;
            }
            
            $orden = $this->enrichOrdenData($orden);
            
            echo json_encode($orden);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Error al obtener orden',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Crear nueva orden - POST /api/ordenes
     */
    public function create() {
        try {
            $userData = requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Iniciar transacción
            $this->db->beginTransaction();
            
            // 1. Insertar o actualizar cliente
            $cliente_id = $this->upsertCliente($data['cliente']);
            
            // 2. Insertar o actualizar vehículo
            $vehiculo_id = $this->upsertVehiculo($data['vehiculo'], $cliente_id);
            
            // 3. Preparar datos de inspección desde frontend - TODOS los campos
            $inspeccionData = $data['inspeccion'] ?? [];
            $exteriores = $inspeccionData['exteriores'] ?? [];
            $interiores = $inspeccionData['interiores'] ?? [];
            
            // 4. Insertar orden con TODOS los campos del schema (COMPLETO con 20+ checkboxes + IVA + ANTICIPO)
            // NOTA: numero_orden se generará DESPUÉS del insert usando el ID real
            $stmt = $this->db->prepare('
                INSERT INTO ordenes_servicio (
                    numero_orden, cliente_id, vehiculo_id, usuario_id,
                    problema_reportado, diagnostico,
                    kilometraje_entrada, kilometraje_salida,
                    nivel_combustible,
                    tiene_luces_frontales, tiene_cuarto_luces, tiene_antena,
                    tiene_espejos_laterales, tiene_cristales, tiene_emblemas,
                    tiene_llantas, tiene_llanta_refaccion, tiene_tapon_ruedas,
                    tiene_molduras_completas, tiene_tapon_gasolina, tiene_limpiadores,
                    tiene_gato, tiene_herramienta, tiene_extinguidor,
                    tiene_instrumento_tablero, tiene_calefaccion, tiene_sistema_sonido,
                    tiene_bocinas, tiene_espejo_retrovisor, tiene_cinturones,
                    tiene_botonia_general, tiene_manijas, tiene_tapetes,
                    tiene_vestiduras, tiene_otros,
                    tiene_radio, tiene_encendedor, tiene_documentos,
                    subtotal_servicios, subtotal_mano_obra, subtotal_refacciones, 
                    incluir_iva, iva, total, anticipo,
                    fecha_promesa_entrega,
                    estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $vehiculoData = $data['vehiculo'] ?? [];
            $resumenData = $data['resumen'] ?? [];
            
            // Procesar fecha de salida/promesa
            $fechaSalida = null;
            if (isset($data['fechaSalida']) && $data['fechaSalida']) {
                $fechaSalida = date('Y-m-d H:i:s', strtotime($data['fechaSalida']));
            }
            
            // Generar numero_orden temporal (se actualizará después)
            $numero_orden_temp = 'TEMP-' . time();
            
            $stmt->execute([
                $numero_orden_temp,
                $cliente_id,
                $vehiculo_id,
                $userData['userId'],
                $data['problemaReportado'] ?? '',
                $data['diagnosticoTecnico'] ?? '',
                $vehiculoData['kilometrajeEntrada'] ?? '',
                $vehiculoData['kilometrajeSalida'] ?? '',
                $vehiculoData['nivelCombustible'] ?? 0,
                // EXTERIORES (22 checkboxes)
                isset($exteriores['lucesFrontales']) && $exteriores['lucesFrontales'] ? 1 : 0,
                isset($exteriores['cuartoLuces']) && $exteriores['cuartoLuces'] ? 1 : 0,
                isset($exteriores['antena']) && $exteriores['antena'] ? 1 : 0,
                isset($exteriores['espejosLaterales']) && $exteriores['espejosLaterales'] ? 1 : 0,
                isset($exteriores['cristales']) && $exteriores['cristales'] ? 1 : 0,
                isset($exteriores['emblemas']) && $exteriores['emblemas'] ? 1 : 0,
                isset($exteriores['llantas']) && $exteriores['llantas'] ? 1 : 0,
                isset($exteriores['llantaRefaccion']) && $exteriores['llantaRefaccion'] ? 1 : 0,
                isset($exteriores['taponRuedas']) && $exteriores['taponRuedas'] ? 1 : 0,
                isset($exteriores['moldurasCompletas']) && $exteriores['moldurasCompletas'] ? 1 : 0,
                isset($exteriores['taponGasolina']) && $exteriores['taponGasolina'] ? 1 : 0,
                isset($exteriores['limpiadores']) && $exteriores['limpiadores'] ? 1 : 0,
                isset($exteriores['gato']) && $exteriores['gato'] ? 1 : 0,
                isset($exteriores['herramienta']) && $exteriores['herramienta'] ? 1 : 0,
                isset($exteriores['extinguidor']) && $exteriores['extinguidor'] ? 1 : 0,
                // INTERIORES (20 checkboxes)
                isset($interiores['instrumentoTablero']) && $interiores['instrumentoTablero'] ? 1 : 0,
                isset($interiores['calefaccion']) && $interiores['calefaccion'] ? 1 : 0,
                isset($interiores['sistemaSonido']) && $interiores['sistemaSonido'] ? 1 : 0,
                isset($interiores['bocinas']) && $interiores['bocinas'] ? 1 : 0,
                isset($interiores['espejoRetrovisor']) && $interiores['espejoRetrovisor'] ? 1 : 0,
                isset($interiores['cinturones']) && $interiores['cinturones'] ? 1 : 0,
                isset($interiores['botoniaGeneral']) && $interiores['botoniaGeneral'] ? 1 : 0,
                isset($interiores['manijas']) && $interiores['manijas'] ? 1 : 0,
                isset($interiores['tapetes']) && $interiores['tapetes'] ? 1 : 0,
                isset($interiores['vestiduras']) && $interiores['vestiduras'] ? 1 : 0,
                isset($interiores['otros']) && $interiores['otros'] ? 1 : 0,
                // Originales que siguen existiendo
                isset($interiores['radio']) && $interiores['radio'] ? 1 : 0,
                isset($interiores['encendedor']) && $interiores['encendedor'] ? 1 : 0,
                isset($interiores['documentos']) && $interiores['documentos'] ? 1 : 0,
                // Totales y resumen completo
                $resumenData['servicios'] ?? 0,
                $resumenData['manoDeObra'] ?? 0,
                $resumenData['refacciones'] ?? 0,
                isset($resumenData['incluirIVA']) && $resumenData['incluirIVA'] ? 1 : 0,
                $resumenData['iva'] ?? 0,
                $resumenData['total'] ?? 0,
                $resumenData['anticipo'] ?? 0,
                $fechaSalida,
                'abierta' // Estado inicial siempre es 'abierta'
            ]);
            
            $orden_id = $this->db->lastInsertId();
            
            // 5. Generar numero_orden real usando el ID obtenido
            $numero_orden_real = $this->generateNumeroOrden($orden_id);
            
            // 6. Actualizar la orden con el numero_orden correcto
            $updateStmt = $this->db->prepare('UPDATE ordenes_servicio SET numero_orden = ? WHERE id = ?');
            $updateStmt->execute([$numero_orden_real, $orden_id]);
            
            // 7. Insertar SERVICIOS en servicios_orden (tipo='servicio')
            if (isset($data['servicios']) && !empty($data['servicios'])) {
                $this->insertServiciosOrden($orden_id, $data['servicios'], 'servicio');
            }
            
            // 7b. Insertar MANO DE OBRA en servicios_orden (tipo='mano_obra')
            if (isset($data['manoDeObra']) && !empty($data['manoDeObra'])) {
                $this->insertServiciosOrden($orden_id, $data['manoDeObra'], 'mano_obra');
            }
            
            // 8. Insertar refacciones en refacciones_orden
            if (isset($data['refacciones']) && !empty($data['refacciones'])) {
                $this->insertRefaccionesOrden($orden_id, $data['refacciones']);
            }
            
            // 9. Insertar daños adicionales del vehículo
            if (isset($data['inspeccion']['danosAdicionales']) && !empty($data['inspeccion']['danosAdicionales'])) {
                $this->insertDanosVehiculo($orden_id, $data['inspeccion']['danosAdicionales']);
            }
            
            // 10. Insertar puntos de seguridad
            if (isset($data['puntosSeguridad']) && !empty($data['puntosSeguridad'])) {
                $this->insertPuntosSeguridad($orden_id, $data['puntosSeguridad']);
            }
            
            // Commit transacción
            $this->db->commit();
            
            // Retornar orden completa
            http_response_code(201);
            $this->getById($orden_id);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode([
                'error' => 'Error al crear orden',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Actualizar orden - PUT /api/ordenes/:id
     */
    public function update($id) {
        try {
            $userData = requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Verificar que la orden existe
            $stmt = $this->db->prepare('SELECT id FROM ordenes_servicio WHERE id = ?');
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Orden no encontrada']);
                return;
            }
            
            // Iniciar transacción
            $this->db->beginTransaction();
            
            // 1. Actualizar datos del cliente si se enviaron
            if (isset($data['cliente'])) {
                $stmt = $this->db->prepare('SELECT cliente_id FROM ordenes_servicio WHERE id = ?');
                $stmt->execute([$id]);
                $orden = $stmt->fetch();
                
                if ($orden && isset($data['cliente']['nombreCompleto'])) {
                    $stmt = $this->db->prepare('
                        UPDATE clientes 
                        SET nombre = ?, telefono = ?, email = ?, direccion = ?
                        WHERE id = ?
                    ');
                    $stmt->execute([
                        $data['cliente']['nombreCompleto'],
                        $data['cliente']['telefono'] ?? '',
                        $data['cliente']['email'] ?? '',
                        $data['cliente']['domicilio'] ?? '',
                        $orden['cliente_id']
                    ]);
                    error_log('Cliente actualizado: ' . $data['cliente']['nombreCompleto']);
                }
            }
            
            // 2. Actualizar datos del vehículo si se enviaron
            if (isset($data['vehiculo'])) {
                $stmt = $this->db->prepare('SELECT vehiculo_id FROM ordenes_servicio WHERE id = ?');
                $stmt->execute([$id]);
                $orden = $stmt->fetch();
                
                if ($orden && isset($data['vehiculo']['marca'])) {
                    $stmt = $this->db->prepare('
                        UPDATE vehiculos 
                        SET marca = ?, modelo = ?, color = ?, placas = ?, niv = ?
                        WHERE id = ?
                    ');
                    $stmt->execute([
                        $data['vehiculo']['marca'],
                        $data['vehiculo']['modelo'] ?? '',
                        $data['vehiculo']['color'] ?? '',
                        $data['vehiculo']['placas'] ?? '',
                        $data['vehiculo']['niv'] ?? '',
                        $orden['vehiculo_id']
                    ]);
                    error_log('Vehículo actualizado: ' . $data['vehiculo']['marca']);
                }
            }
            
            // 3. Actualizar campos de la orden
            $updateFields = [];
            $updateValues = [];
            
            // Log para debugging
            error_log('=== UPDATE ORDEN ===');
            error_log('ID: ' . $id);
            error_log('Data recibida: ' . json_encode($data));
            
            // AGREGAR: Actualizar problema reportado
            if (isset($data['problemaReportado'])) {
                $updateFields[] = 'problema_reportado = ?';
                $updateValues[] = $data['problemaReportado'];
                error_log('Problema reportado a actualizar: ' . $data['problemaReportado']);
            }
            
            // AGREGAR: Actualizar diagnóstico técnico
            if (isset($data['diagnosticoTecnico'])) {
                $updateFields[] = 'diagnostico = ?';
                $updateValues[] = $data['diagnosticoTecnico'];
                error_log('Diagnóstico técnico a actualizar: ' . $data['diagnosticoTecnico']);
            }
            
            // LEGACY: Mantener compatibilidad con 'diagnostico' directo
            if (isset($data['diagnostico'])) {
                $updateFields[] = 'diagnostico = ?';
                $updateValues[] = $data['diagnostico'];
            }
            
            if (isset($data['estado'])) {
                $updateFields[] = 'estado = ?';
                $updateValues[] = $data['estado'];
                error_log('Estado a actualizar: ' . $data['estado']);
            }
            
            // Actualizar campos de resumen si se enviaron
            if (isset($data['resumen'])) {
                if (isset($data['resumen']['servicios'])) {
                    $updateFields[] = 'subtotal_servicios = ?';
                    $updateValues[] = $data['resumen']['servicios'];
                }
                if (isset($data['resumen']['manoDeObra'])) {
                    $updateFields[] = 'subtotal_mano_obra = ?';
                    $updateValues[] = $data['resumen']['manoDeObra'];
                }
                if (isset($data['resumen']['refacciones'])) {
                    $updateFields[] = 'subtotal_refacciones = ?';
                    $updateValues[] = $data['resumen']['refacciones'];
                }
                if (isset($data['resumen']['incluirIVA'])) {
                    $updateFields[] = 'incluir_iva = ?';
                    $updateValues[] = $data['resumen']['incluirIVA'] ? 1 : 0;
                    error_log('Incluir IVA: ' . ($data['resumen']['incluirIVA'] ? 'SI' : 'NO'));
                }
                if (isset($data['resumen']['iva'])) {
                    $updateFields[] = 'iva = ?';
                    $updateValues[] = $data['resumen']['iva'];
                }
                if (isset($data['resumen']['total'])) {
                    $updateFields[] = 'total = ?';
                    $updateValues[] = $data['resumen']['total'];
                }
                if (isset($data['resumen']['anticipo'])) {
                    $updateFields[] = 'anticipo = ?';
                    $updateValues[] = $data['resumen']['anticipo'];
                    error_log('Anticipo a actualizar: ' . $data['resumen']['anticipo']);
                }
            }
            
            // Actualizar checkboxes de inspección si se enviaron - TODOS LOS 20+ CAMPOS
            if (isset($data['inspeccion'])) {
                $exteriores = $data['inspeccion']['exteriores'] ?? [];
                $interiores = $data['inspeccion']['interiores'] ?? [];
                
                // EXTERIORES - TODOS
                if (isset($exteriores['lucesFrontales'])) {
                    $updateFields[] = 'tiene_luces_frontales = ?';
                    $updateValues[] = $exteriores['lucesFrontales'] ? 1 : 0;
                }
                if (isset($exteriores['cuartoLuces'])) {
                    $updateFields[] = 'tiene_cuarto_luces = ?';
                    $updateValues[] = $exteriores['cuartoLuces'] ? 1 : 0;
                }
                if (isset($exteriores['antena'])) {
                    $updateFields[] = 'tiene_antena = ?';
                    $updateValues[] = $exteriores['antena'] ? 1 : 0;
                }
                if (isset($exteriores['espejosLaterales'])) {
                    $updateFields[] = 'tiene_espejos_laterales = ?';
                    $updateValues[] = $exteriores['espejosLaterales'] ? 1 : 0;
                }
                if (isset($exteriores['cristales'])) {
                    $updateFields[] = 'tiene_cristales = ?';
                    $updateValues[] = $exteriores['cristales'] ? 1 : 0;
                }
                if (isset($exteriores['emblemas'])) {
                    $updateFields[] = 'tiene_emblemas = ?';
                    $updateValues[] = $exteriores['emblemas'] ? 1 : 0;
                }
                if (isset($exteriores['llantas'])) {
                    $updateFields[] = 'tiene_llantas = ?';
                    $updateValues[] = $exteriores['llantas'] ? 1 : 0;
                }
                if (isset($exteriores['llantaRefaccion'])) {
                    $updateFields[] = 'tiene_llanta_refaccion = ?';
                    $updateValues[] = $exteriores['llantaRefaccion'] ? 1 : 0;
                }
                if (isset($exteriores['taponRuedas'])) {
                    $updateFields[] = 'tiene_tapon_ruedas = ?';
                    $updateValues[] = $exteriores['taponRuedas'] ? 1 : 0;
                }
                if (isset($exteriores['moldurasCompletas'])) {
                    $updateFields[] = 'tiene_molduras_completas = ?';
                    $updateValues[] = $exteriores['moldurasCompletas'] ? 1 : 0;
                }
                if (isset($exteriores['taponGasolina'])) {
                    $updateFields[] = 'tiene_tapon_gasolina = ?';
                    $updateValues[] = $exteriores['taponGasolina'] ? 1 : 0;
                }
                if (isset($exteriores['limpiadores'])) {
                    $updateFields[] = 'tiene_limpiadores = ?';
                    $updateValues[] = $exteriores['limpiadores'] ? 1 : 0;
                }
                if (isset($exteriores['gato'])) {
                    $updateFields[] = 'tiene_gato = ?';
                    $updateValues[] = $exteriores['gato'] ? 1 : 0;
                }
                if (isset($exteriores['herramienta'])) {
                    $updateFields[] = 'tiene_herramienta = ?';
                    $updateValues[] = $exteriores['herramienta'] ? 1 : 0;
                }
                if (isset($exteriores['extinguidor'])) {
                    $updateFields[] = 'tiene_extinguidor = ?';
                    $updateValues[] = $exteriores['extinguidor'] ? 1 : 0;
                }
                
                // INTERIORES - TODOS
                if (isset($interiores['instrumentoTablero'])) {
                    $updateFields[] = 'tiene_instrumento_tablero = ?';
                    $updateValues[] = $interiores['instrumentoTablero'] ? 1 : 0;
                }
                if (isset($interiores['calefaccion'])) {
                    $updateFields[] = 'tiene_calefaccion = ?';
                    $updateValues[] = $interiores['calefaccion'] ? 1 : 0;
                }
                if (isset($interiores['sistemaSonido'])) {
                    $updateFields[] = 'tiene_sistema_sonido = ?';
                    $updateValues[] = $interiores['sistemaSonido'] ? 1 : 0;
                }
                if (isset($interiores['bocinas'])) {
                    $updateFields[] = 'tiene_bocinas = ?';
                    $updateValues[] = $interiores['bocinas'] ? 1 : 0;
                }
                if (isset($interiores['espejoRetrovisor'])) {
                    $updateFields[] = 'tiene_espejo_retrovisor = ?';
                    $updateValues[] = $interiores['espejoRetrovisor'] ? 1 : 0;
                }
                if (isset($interiores['cinturones'])) {
                    $updateFields[] = 'tiene_cinturones = ?';
                    $updateValues[] = $interiores['cinturones'] ? 1 : 0;
                }
                if (isset($interiores['botoniaGeneral'])) {
                    $updateFields[] = 'tiene_botonia_general = ?';
                    $updateValues[] = $interiores['botoniaGeneral'] ? 1 : 0;
                }
                if (isset($interiores['manijas'])) {
                    $updateFields[] = 'tiene_manijas = ?';
                    $updateValues[] = $interiores['manijas'] ? 1 : 0;
                }
                if (isset($interiores['tapetes'])) {
                    $updateFields[] = 'tiene_tapetes = ?';
                    $updateValues[] = $interiores['tapetes'] ? 1 : 0;
                }
                if (isset($interiores['vestiduras'])) {
                    $updateFields[] = 'tiene_vestiduras = ?';
                    $updateValues[] = $interiores['vestiduras'] ? 1 : 0;
                }
                if (isset($interiores['otros'])) {
                    $updateFields[] = 'tiene_otros = ?';
                    $updateValues[] = $interiores['otros'] ? 1 : 0;
                }
                if (isset($interiores['radio'])) {
                    $updateFields[] = 'tiene_radio = ?';
                    $updateValues[] = $interiores['radio'] ? 1 : 0;
                }
                if (isset($interiores['encendedor'])) {
                    $updateFields[] = 'tiene_encendedor = ?';
                    $updateValues[] = $interiores['encendedor'] ? 1 : 0;
                }
                if (isset($interiores['documentos'])) {
                    $updateFields[] = 'tiene_documentos = ?';
                    $updateValues[] = $interiores['documentos'] ? 1 : 0;
                }
                
                error_log('Checkboxes de inspección a actualizar: ' . count($updateFields));
            }
            
            // Actualizar kilometrajes si vienen en vehiculo
            if (isset($data['vehiculo']['kilometrajeEntrada'])) {
                $updateFields[] = 'kilometraje_entrada = ?';
                $updateValues[] = $data['vehiculo']['kilometrajeEntrada'];
                error_log('Kilometraje entrada a actualizar: ' . $data['vehiculo']['kilometrajeEntrada']);
            }
            
            if (isset($data['vehiculo']['kilometrajeSalida'])) {
                $updateFields[] = 'kilometraje_salida = ?';
                $updateValues[] = $data['vehiculo']['kilometrajeSalida'];
                error_log('Kilometraje salida a actualizar: ' . $data['vehiculo']['kilometrajeSalida']);
            }
            
            // Actualizar nivel de combustible si viene en vehiculo
            if (isset($data['vehiculo']['nivelCombustible'])) {
                $updateFields[] = 'nivel_combustible = ?';
                $updateValues[] = $data['vehiculo']['nivelCombustible'];
                error_log('Nivel combustible a actualizar: ' . $data['vehiculo']['nivelCombustible']);
            }
            
            // Actualizar fecha de entrada
            if (isset($data['fechaEntrada'])) {
                if ($data['fechaEntrada']) {
                    $updateFields[] = 'fecha_ingreso = ?';
                    $updateValues[] = date('Y-m-d H:i:s', strtotime($data['fechaEntrada']));
                    error_log('Fecha entrada a actualizar: ' . $data['fechaEntrada']);
                }
            }
            
            // Actualizar fecha de salida/promesa
            if (isset($data['fechaSalida'])) {
                if ($data['fechaSalida']) {
                    $updateFields[] = 'fecha_promesa_entrega = ?';
                    $updateValues[] = date('Y-m-d H:i:s', strtotime($data['fechaSalida']));
                    error_log('Fecha salida a actualizar: ' . $data['fechaSalida']);
                } else {
                    $updateFields[] = 'fecha_promesa_entrega = NULL';
                }
            }
            
            // Siempre actualizar ultima_modificacion
            $updateFields[] = 'ultima_modificacion = NOW()';
            
            if (!empty($updateFields)) {
                $updateValues[] = $id;
                $sql = 'UPDATE ordenes_servicio SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
                error_log('SQL: ' . $sql);
                error_log('Values: ' . json_encode($updateValues));
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute($updateValues);
                
                error_log('Filas afectadas: ' . $stmt->rowCount());
            }
            
            // Actualizar servicios y refacciones si se enviaron
            // IMPORTANTE: Verificar si vienen servicios O manoDeObra (o ambos)
            $shouldUpdateServicios = isset($data['servicios']) || isset($data['manoDeObra']);
            
            if ($shouldUpdateServicios) {
                // Eliminar todos los servicios existentes
                $this->db->prepare('DELETE FROM servicios_orden WHERE orden_id = ?')->execute([$id]);
                error_log('Servicios eliminados para orden: ' . $id);
                
                // Insertar servicios si hay (tipo='servicio')
                if (isset($data['servicios']) && !empty($data['servicios'])) {
                    $this->insertServiciosOrden($id, $data['servicios'], 'servicio');
                    error_log('Servicios insertados: ' . count($data['servicios']));
                }
                
                // Insertar mano de obra si hay (tipo='mano_obra')
                if (isset($data['manoDeObra']) && !empty($data['manoDeObra'])) {
                    $this->insertServiciosOrden($id, $data['manoDeObra'], 'mano_obra');
                    error_log('Mano de obra insertada: ' . count($data['manoDeObra']));
                }
            }
            
            if (isset($data['refacciones'])) {
                $this->db->prepare('DELETE FROM refacciones_orden WHERE orden_id = ?')->execute([$id]);
                if (!empty($data['refacciones'])) {
                    $this->insertRefaccionesOrden($id, $data['refacciones']);
                    error_log('Refacciones insertadas: ' . count($data['refacciones']));
                }
            }
            
            // Actualizar daños adicionales del vehículo si se enviaron
            if (isset($data['inspeccion']['danosAdicionales'])) {
                // Eliminar daños existentes y agregar los nuevos
                $this->db->prepare('DELETE FROM danos_vehiculo WHERE orden_id = ?')->execute([$id]);
                if (!empty($data['inspeccion']['danosAdicionales'])) {
                    $this->insertDanosVehiculo($id, $data['inspeccion']['danosAdicionales']);
                }
            }
            
            // Actualizar puntos de seguridad si se enviaron
            if (isset($data['puntosSeguridad'])) {
                // Eliminar puntos existentes y agregar los nuevos
                $this->db->prepare('DELETE FROM orden_puntos_seguridad WHERE orden_id = ?')->execute([$id]);
                if (!empty($data['puntosSeguridad'])) {
                    $this->insertPuntosSeguridad($id, $data['puntosSeguridad']);
                    error_log('Puntos de seguridad actualizados: ' . count($data['puntosSeguridad']));
                }
            }
            
            $this->db->commit();
            
            // Retornar orden actualizada
            $this->getById($id);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode([
                'error' => 'Error al actualizar orden',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Eliminar orden - DELETE /api/ordenes/:id
     */
    public function delete($id) {
        try {
            requireAuth();
            
            $stmt = $this->db->prepare('DELETE FROM ordenes_servicio WHERE id = ?');
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Orden no encontrada']);
                return;
            }
            
            http_response_code(204);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Error al eliminar orden',
                'message' => $e->getMessage()
            ]);
        }
    }
    
    // ========== MÉTODOS AUXILIARES ==========
    
    private function enrichOrdenData($orden) {
        // Obtener servicios/mano de obra de servicios_orden
        $stmt = $this->db->prepare('SELECT * FROM servicios_orden WHERE orden_id = ?');
        $stmt->execute([$orden['id']]);
        $serviciosOrden = $stmt->fetchAll();
        
        // Convertir formato BD a frontend - SEPARAR por tipo
        $orden['servicios'] = [];
        $orden['manoDeObra'] = [];
        foreach ($serviciosOrden as $servicio) {
            $item = [
                'id' => (string)$servicio['id'],
                'descripcion' => $servicio['descripcion'],
                'precio' => (float)$servicio['precio_unitario'],
                'cantidad' => (float)$servicio['cantidad'],
                'subtotal' => (float)$servicio['subtotal']
            ];
            
            // Separar según el campo 'tipo'
            $tipo = $servicio['tipo'] ?? 'mano_obra'; // Default a mano_obra si no existe el campo
            if ($tipo === 'servicio') {
                $orden['servicios'][] = $item;
            } else {
                $orden['manoDeObra'][] = $item;
            }
        }
        
        // Obtener refacciones
        $stmt = $this->db->prepare('SELECT * FROM refacciones_orden WHERE orden_id = ?');
        $stmt->execute([$orden['id']]);
        $refacciones = $stmt->fetchAll();
        
        // Convertir formato BD a frontend
        $orden['refacciones'] = [];
        foreach ($refacciones as $refaccion) {
            $orden['refacciones'][] = [
                'id' => (string)$refaccion['id'],
                'nombre' => $refaccion['descripcion'],
                'cantidad' => (float)$refaccion['cantidad'],
                'precioVenta' => (float)$refaccion['precio_unitario'],
                'total' => (float)$refaccion['subtotal']
            ];
        }
        
        // Mapear campo diagnostico a diagnosticoTecnico para el frontend
        if (isset($orden['diagnostico'])) {
            $orden['diagnosticoTecnico'] = $orden['diagnostico'];
        }
        
        // Mapear problema_reportado a problemaReportado
        if (isset($orden['problema_reportado'])) {
            $orden['problemaReportado'] = $orden['problema_reportado'];
        }
        
        // Mapear kilometrajes para el frontend
        if (!isset($orden['vehiculo'])) {
            $orden['vehiculo'] = [];
        }
        if (isset($orden['kilometraje_entrada'])) {
            $orden['vehiculo']['kilometrajeEntrada'] = $orden['kilometraje_entrada'];
        }
        if (isset($orden['kilometraje_salida'])) {
            $orden['vehiculo']['kilometrajeSalida'] = $orden['kilometraje_salida'];
        }
        
        // Mapear fecha_promesa_entrega a fechaSalida (SIEMPRE, incluso si es null)
        $orden['fechaSalida'] = $orden['fecha_promesa_entrega'] ?? null;
        
        // Obtener daños adicionales del vehículo
        $stmt = $this->db->prepare('SELECT * FROM danos_vehiculo WHERE orden_id = ?');
        $stmt->execute([$orden['id']]);
        $danosDB = $stmt->fetchAll();
        
        // Mapear TODOS los checkboxes de inspección para el frontend (20+ campos)
        $orden['inspeccion'] = [
            'exteriores' => [
                'lucesFrontales' => (bool)($orden['tiene_luces_frontales'] ?? 1),
                'cuartoLuces' => (bool)($orden['tiene_cuarto_luces'] ?? 1),
                'antena' => (bool)($orden['tiene_antena'] ?? 1),
                'espejosLaterales' => (bool)($orden['tiene_espejos_laterales'] ?? 1),
                'cristales' => (bool)($orden['tiene_cristales'] ?? 1),
                'emblemas' => (bool)($orden['tiene_emblemas'] ?? 1),
                'llantas' => (bool)($orden['tiene_llantas'] ?? 1),
                'llantaRefaccion' => (bool)($orden['tiene_llanta_refaccion'] ?? 1),
                'taponRuedas' => (bool)($orden['tiene_tapon_ruedas'] ?? 1),
                'moldurasCompletas' => (bool)($orden['tiene_molduras_completas'] ?? 1),
                'taponGasolina' => (bool)($orden['tiene_tapon_gasolina'] ?? 1),
                'limpiadores' => (bool)($orden['tiene_limpiadores'] ?? 1),
                'gato' => (bool)($orden['tiene_gato'] ?? 1),
                'herramienta' => (bool)($orden['tiene_herramienta'] ?? 1),
                'extinguidor' => (bool)($orden['tiene_extinguidor'] ?? 1),
            ],
            'interiores' => [
                'instrumentoTablero' => (bool)($orden['tiene_instrumento_tablero'] ?? 1),
                'calefaccion' => (bool)($orden['tiene_calefaccion'] ?? 1),
                'sistemaSonido' => (bool)($orden['tiene_sistema_sonido'] ?? 1),
                'bocinas' => (bool)($orden['tiene_bocinas'] ?? 1),
                'espejoRetrovisor' => (bool)($orden['tiene_espejo_retrovisor'] ?? 1),
                'cinturones' => (bool)($orden['tiene_cinturones'] ?? 1),
                'botoniaGeneral' => (bool)($orden['tiene_botonia_general'] ?? 1),
                'manijas' => (bool)($orden['tiene_manijas'] ?? 1),
                'tapetes' => (bool)($orden['tiene_tapetes'] ?? 1),
                'vestiduras' => (bool)($orden['tiene_vestiduras'] ?? 1),
                'otros' => (bool)($orden['tiene_otros'] ?? 1),
                'radio' => (bool)($orden['tiene_radio'] ?? 1),
                'encendedor' => (bool)($orden['tiene_encendedor'] ?? 1),
                'documentos' => (bool)($orden['tiene_documentos'] ?? 1),
            ],
            'danosAdicionales' => []
        ];
        
        // Mapear daños adicionales
        foreach ($danosDB as $dano) {
            $orden['inspeccion']['danosAdicionales'][] = [
                'id' => (string)$dano['id'],
                'ubicacion' => $dano['ubicacion'],
                'tipo' => $dano['tipo'],
                'descripcion' => $dano['descripcion'] ?? ''
            ];
        }
        
        // Agregar resumen completo con incluirIVA y anticipo
        $anticipo = (float)($orden['anticipo'] ?? 0);
        $total = (float)($orden['total'] ?? 0);
        
        $orden['resumen'] = [
            'servicios' => (float)($orden['subtotal_servicios'] ?? 0),
            'manoDeObra' => (float)($orden['subtotal_mano_obra'] ?? 0),
            'refacciones' => (float)($orden['subtotal_refacciones'] ?? 0),
            'subtotal' => (float)($orden['subtotal_servicios'] ?? 0) + (float)($orden['subtotal_mano_obra'] ?? 0) + (float)($orden['subtotal_refacciones'] ?? 0),
            'incluirIVA' => (bool)($orden['incluir_iva'] ?? false),
            'iva' => (float)($orden['iva'] ?? 0),
            'total' => $total,
            'anticipo' => $anticipo,
            'restante' => $total - $anticipo
        ];
        
        // Obtener puntos de seguridad de la orden
        $orden['puntosSeguridad'] = [];
        
        error_log('🔍 DEBUG: Iniciando obtención de puntos para orden: ' . $orden['id']);
        
        try {
            // PASO 1: Query básico sin JOINs para ver si hay datos
            $basicStmt = $this->db->prepare('SELECT * FROM orden_puntos_seguridad WHERE orden_id = ?');
            $basicStmt->execute([$orden['id']]);
            $basicPuntos = $basicStmt->fetchAll();
            
            error_log('📊 DEBUG: Puntos básicos encontrados: ' . count($basicPuntos));
            error_log('📋 DEBUG: Datos básicos: ' . json_encode($basicPuntos));
            
            if (count($basicPuntos) > 0) {
                // PASO 2: Si hay datos básicos, intentar query completo
                error_log('🔧 DEBUG: Intentando query completo...');
                
                $stmt = $this->db->prepare('
                    SELECT ops.id, ops.orden_id, ops.punto_seguridad_id, ops.estado_id, ops.notas,
                           IFNULL(ps.nombre, CONCAT("Punto ", ops.punto_seguridad_id)) as punto_nombre, 
                           IFNULL(ps.categoria, "General") as categoria,
                           IFNULL(es.nombre, "Sin estado") as estado_nombre, 
                           IFNULL(es.color, "#gray") as color
                    FROM orden_puntos_seguridad ops
                    LEFT JOIN puntos_seguridad_catalogo ps ON ops.punto_seguridad_id = ps.id
                    LEFT JOIN estados_seguridad es ON ops.estado_id = es.id
                    WHERE ops.orden_id = ?
                    ORDER BY ops.id
                ');
                $stmt->execute([$orden['id']]);
                $puntosDB = $stmt->fetchAll();
                
                error_log('✅ DEBUG: Puntos con JOINs encontrados: ' . count($puntosDB));
                error_log('📄 DEBUG: Datos completos: ' . json_encode($puntosDB));
                
                foreach ($puntosDB as $punto) {
                    $puntoProcessed = [
                        'id' => (int)$punto['id'],
                        'ordenId' => (int)$punto['orden_id'],
                        'puntoId' => (int)$punto['punto_seguridad_id'],
                        'estadoId' => (int)$punto['estado_id'],
                        'observaciones' => $punto['notas'] ?? '',
                        'punto' => [
                            'id' => (int)$punto['punto_seguridad_id'],
                            'nombre' => $punto['punto_nombre'],
                            'categoria' => $punto['categoria']
                        ],
                        'estado' => [
                            'id' => (int)$punto['estado_id'],
                            'nombre' => $punto['estado_nombre'],
                            'color' => $punto['color'],
                            'icono' => 'circle' // Default icon since DB doesn't have this field
                        ]
                    ];
                    
                    $orden['puntosSeguridad'][] = $puntoProcessed;
                    error_log('➕ DEBUG: Punto procesado: ' . json_encode($puntoProcessed));
                }
            } else {
                error_log('❌ DEBUG: No se encontraron puntos básicos en la tabla orden_puntos_seguridad');
            }
            
        } catch (Exception $e) {
            error_log('💥 ERROR obteniendo puntos de seguridad: ' . $e->getMessage());
            error_log('🔍 ERROR Details: ' . $e->getFile() . ':' . $e->getLine());
            error_log('📄 ERROR Trace: ' . $e->getTraceAsString());
            $orden['puntosSeguridad'] = [];
        }
        
        error_log('🎯 DEBUG: Total puntos finales: ' . count($orden['puntosSeguridad']));
        
        return $orden;
    }
    
    private function upsertCliente($clienteData) {
        $nombre = $clienteData['nombreCompleto'] ?? $clienteData['nombre'] ?? null;
        $telefono = $clienteData['telefono'] ?? null;
        $email = $clienteData['email'] ?? null;
        $direccion = $clienteData['domicilio'] ?? $clienteData['direccion'] ?? null;
        
        if (!$nombre || !$telefono) {
            throw new Exception('Nombre y teléfono del cliente son requeridos');
        }
        
        // Buscar cliente existente por teléfono
        $stmt = $this->db->prepare('SELECT id FROM clientes WHERE telefono = ? LIMIT 1');
        $stmt->execute([$telefono]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Actualizar
            $stmt = $this->db->prepare('
                UPDATE clientes SET nombre = ?, email = ?, direccion = ?
                WHERE id = ?
            ');
            $stmt->execute([$nombre, $email, $direccion, $existing['id']]);
            return $existing['id'];
        } else {
            // Insertar
            $stmt = $this->db->prepare('
                INSERT INTO clientes (nombre, telefono, email, direccion)
                VALUES (?, ?, ?, ?)
            ');
            $stmt->execute([$nombre, $telefono, $email, $direccion]);
            return $this->db->lastInsertId();
        }
    }
    
    private function upsertVehiculo($vehiculoData, $cliente_id) {
        $marca = $vehiculoData['marca'] ?? null;
        $modelo = $vehiculoData['modelo'] ?? null;
        $anio = $vehiculoData['anio'] ?? null;
        $color = $vehiculoData['color'] ?? null;
        $placas = $vehiculoData['placas'] ?? null;
        $niv = $vehiculoData['niv'] ?? null;
        
        if (!$marca || !$modelo || !$placas) {
            throw new Exception('Marca, modelo y placas del vehículo son requeridos');
        }
        
        // Buscar vehículo existente por placas
        $stmt = $this->db->prepare('SELECT id FROM vehiculos WHERE placas = ? LIMIT 1');
        $stmt->execute([$placas]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Actualizar
            $stmt = $this->db->prepare('
                UPDATE vehiculos SET marca = ?, modelo = ?, anio = ?, 
                       color = ?, niv = ?, cliente_id = ?
                WHERE id = ?
            ');
            $stmt->execute([$marca, $modelo, $anio, $color, $niv, $cliente_id, $existing['id']]);
            return $existing['id'];
        } else {
            // Insertar
            $stmt = $this->db->prepare('
                INSERT INTO vehiculos (marca, modelo, anio, color, placas, niv, cliente_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$marca, $modelo, $anio, $color, $placas, $niv, $cliente_id]);
            return $this->db->lastInsertId();
        }
    }
    
    private function insertServiciosOrden($orden_id, $servicios, $tipo = 'mano_obra') {
        $stmt = $this->db->prepare('
            INSERT INTO servicios_orden (orden_id, tipo, descripcion, precio_unitario, cantidad, subtotal)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        
        foreach ($servicios as $servicio) {
            $cantidad = $servicio['horas'] ?? $servicio['cantidad'] ?? 1;
            $precioUnitario = $servicio['precio'] ?? $servicio['precio_unitario'] ?? 0;
            $subtotal = $cantidad * $precioUnitario;
            
            $stmt->execute([
                $orden_id,
                $tipo,
                $servicio['descripcion'],
                $precioUnitario,
                $cantidad,
                $subtotal
            ]);
        }
    }
    
    private function insertRefaccionesOrden($orden_id, $refacciones) {
        $stmt = $this->db->prepare('
            INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, subtotal)
            VALUES (?, ?, ?, ?, ?)
        ');
        
        foreach ($refacciones as $refaccion) {
            $cantidad = $refaccion['cantidad'] ?? 1;
            $precioUnitario = $refaccion['precioVenta'] ?? $refaccion['precio_unitario'] ?? 0;
            $subtotal = $refaccion['total'] ?? ($cantidad * $precioUnitario);
            
            $stmt->execute([
                $orden_id,
                $refaccion['nombre'] ?? $refaccion['descripcion'],
                $cantidad,
                $precioUnitario,
                $subtotal
            ]);
        }
    }
    
    private function insertDanosVehiculo($orden_id, $danos) {
        $stmt = $this->db->prepare('
            INSERT INTO danos_vehiculo (orden_id, ubicacion, tipo, descripcion)
            VALUES (?, ?, ?, ?)
        ');
        
        foreach ($danos as $dano) {
            $stmt->execute([
                $orden_id,
                $dano['ubicacion'] ?? '',
                $dano['tipo'] ?? '',
                $dano['descripcion'] ?? ''
            ]);
        }
    }
    
    private function insertPuntosSeguridad($orden_id, $puntos) {
        // Columnas reales: orden_id, punto_seguridad_id, estado_id, notas
        $stmt = $this->db->prepare('
            INSERT INTO orden_puntos_seguridad (orden_id, punto_seguridad_id, estado_id, notas)
            VALUES (?, ?, ?, ?)
        ');
        
        foreach ($puntos as $punto) {
            $puntoId = $punto['puntoId'] ?? $punto['punto_id'] ?? $punto['punto_seguridad_id'] ?? null;
            $estadoId = $punto['estadoId'] ?? $punto['estado_id'] ?? 1;
            $notas = $punto['observaciones'] ?? $punto['notas'] ?? '';
            
            if ($puntoId) {
                $stmt->execute([
                    $orden_id,
                    $puntoId,
                    $estadoId,
                    $notas
                ]);
            }
        }
        error_log('Puntos de seguridad insertados: ' . count($puntos) . ' para orden ' . $orden_id);
    }
    
    private function generateNumeroOrden($id) {
        $prefix = 'OS-';
        $year = date('Y');
        
        // Formato: OS-YYYY-ID_REAL
        // Ejemplo: OS-2026-1650, OS-2026-1651, etc.
        return $prefix . $year . '-' . $id;
    }
}
