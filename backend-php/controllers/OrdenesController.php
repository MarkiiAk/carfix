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
     * Multi-sucursal: admin_sucursal solo ve su sucursal; sistemas/superusuario ven todo
     * o filtran con ?sucursal_id=N
     */
    public function getAll() {
        try {
            $userData   = requireAuth();
            $sucursalId = (int) ($userData['sucursal_activa_id'] ?? 1);
            // Todos los roles filtran por sucursal_activa_id del token
            $whereClause = 'WHERE o.sucursal_id = ?';
            $params      = [$sucursalId];

            $sql = "
                SELECT o.*,
                       c.nombre  AS cliente_nombre,
                       c.telefono AS cliente_telefono,
                       v.marca, v.modelo, v.anio, v.placas,
                       NOW() AS query_timestamp
                FROM ordenes_servicio o
                LEFT JOIN clientes c ON o.cliente_id = c.id
                LEFT JOIN vehiculos v ON o.vehiculo_id = v.id
                $whereClause
                ORDER BY o.id DESC
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $ordenes = $stmt->fetchAll();

            // Procesar cada orden para incluir datos relacionados
            foreach ($ordenes as &$orden) {
                $orden = $this->enrichOrdenData($orden);
            }

            // Headers anti-cache
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo json_encode($ordenes);
            
        } catch (Exception $e) {
            jsonError('Error al obtener órdenes', $e, 500);
        }
    }
    
    /**
     * Obtener una orden por ID - GET /api/ordenes/:id
     * Multi-sucursal: admin_sucursal solo puede ver órdenes de su sucursal.
     */
    public function getById($id) {
        try {
            $userData   = requireAuth();
            $sucursalId = (int) ($userData['sucursal_activa_id'] ?? 1);

            $stmt = $this->db->prepare('
                SELECT o.*,
                       c.nombre    AS cliente_nombre,
                       c.telefono  AS cliente_telefono,
                       c.email     AS cliente_email,
                       c.direccion AS cliente_direccion,
                       v.marca, v.modelo, v.anio, v.placas, v.color, v.niv
                FROM ordenes_servicio o
                LEFT JOIN clientes c ON o.cliente_id = c.id
                LEFT JOIN vehiculos v ON o.vehiculo_id = v.id
                WHERE o.id = ?
                  AND o.sucursal_id = ?
            ');
            $stmt->execute([(int) $id, $sucursalId]);
            $orden = $stmt->fetch();

            if (!$orden) {
                http_response_code(404);
                echo json_encode(['error' => 'Orden no encontrada']);
                return;
            }

            $orden = $this->enrichOrdenData($orden);

            echo json_encode($orden);
            
        } catch (Exception $e) {
            jsonError('Error al obtener orden', $e, 500);
        }
    }
    
    /**
     * Crear nueva orden - POST /api/ordenes
     */
    public function create() {
        try {
            $userData = requireAuth();
            $data = json_decode(file_get_contents('php://input'), true);
            
            // VALIDAR DATOS ANTES DE PROCESAR
            $validationErrors = $this->validateOrdenData($data);
            if (!empty($validationErrors)) {
                http_response_code(400);
                echo json_encode([
                    'error' => 'Datos inválidos',
                    'validation_errors' => $validationErrors,
                    'message' => 'Por favor corrige los siguientes campos:'
                ]);
                return;
            }
            
            // Iniciar transacción
            $this->db->beginTransaction();

            $sucursalId = (int) ($userData['sucursal_activa_id'] ?? 1);

            // 1. Insertar o actualizar cliente
            $cliente_id = $this->upsertCliente($data['cliente'], $sucursalId);

            // 2. Insertar o actualizar vehículo
            $vehiculo_id = $this->upsertVehiculo($data['vehiculo'], $cliente_id, $sucursalId);
            
            // 3. Preparar datos de inspección desde frontend - TODOS los campos
            $inspeccionData = $data['inspeccion'] ?? [];
            $exteriores = $inspeccionData['exteriores'] ?? [];
            $interiores = $inspeccionData['interiores'] ?? [];
            
            // 4. Insertar orden con TODOS los campos del schema (COMPLETO con 20+ checkboxes + IVA + ANTICIPO)
            // NOTA: numero_orden se generará DESPUÉS del insert usando el ID real
            $stmt = $this->db->prepare('
                INSERT INTO ordenes_servicio (
                    numero_orden, cliente_id, vehiculo_id, usuario_id, sucursal_id,
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
                    incluir_iva, iva, total, anticipo, fecha_anticipo,
                    fecha_promesa_entrega,
                    estado
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $sucursalId = (int) ($userData['sucursal_activa_id'] ?? 1);

            $stmt->execute([
                $numero_orden_temp,
                $cliente_id,
                $vehiculo_id,
                $userData['userId'],
                $sucursalId,
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
                $resumenData['fecha_anticipo'] ?? null,
                $fechaSalida,
                'abierta' // Estado inicial siempre es 'abierta'
            ]);
            
            $orden_id = $this->db->lastInsertId();
            
            // 5. Generar numero_orden real usando el ID obtenido
            $numero_orden_real = $this->generateNumeroOrden($orden_id, $sucursalId);
            
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

            // 11. Recalcular totales desde filas reales (no confiar en lo que envió el frontend)
            $stmtSvc = $this->db->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN tipo = 'servicio'   THEN subtotal ELSE 0 END), 0) AS sum_servicios,
                    COALESCE(SUM(CASE WHEN tipo = 'mano_obra'  THEN subtotal ELSE 0 END), 0) AS sum_mano_obra
                FROM servicios_orden WHERE orden_id = ?
            ");
            $stmtSvc->execute([$orden_id]);
            $rowSvc = $stmtSvc->fetch(PDO::FETCH_ASSOC);

            $stmtRef = $this->db->prepare("SELECT COALESCE(SUM(subtotal), 0) AS sum_refacciones FROM refacciones_orden WHERE orden_id = ?");
            $stmtRef->execute([$orden_id]);
            $rowRef = $stmtRef->fetch(PDO::FETCH_ASSOC);

            $sumServicios   = (float) $rowSvc['sum_servicios'];
            $sumManoObra    = (float) $rowSvc['sum_mano_obra'];
            $sumRefacciones = (float) $rowRef['sum_refacciones'];
            $subtotalCalc   = $sumServicios + $sumManoObra + $sumRefacciones;
            $incluirIVA     = isset($resumenData['incluirIVA']) && $resumenData['incluirIVA'] ? true : false;
            $ivaCalc        = $incluirIVA ? round($subtotalCalc * 0.16, 2) : 0.0;
            $totalCalc      = round($subtotalCalc + $ivaCalc, 2);

            $stmtFix = $this->db->prepare("
                UPDATE ordenes_servicio
                SET subtotal_servicios = ?, subtotal_mano_obra = ?, subtotal_refacciones = ?,
                    iva = ?, total = ?
                WHERE id = ?
            ");
            $stmtFix->execute([$sumServicios, $sumManoObra, $sumRefacciones, $ivaCalc, $totalCalc, $orden_id]);

            // Commit transacción
            $this->db->commit();
            
            // Retornar orden completa
            http_response_code(201);
            $this->getById($orden_id);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            jsonError('Error al crear orden', $e, 500);
        }
    }

    /**
     * Actualizar orden - PUT /api/ordenes/:id
     */
    public function update($id) {
        try {
            $userData   = requireAuth();
            $sucursalId = (int) ($userData['sucursal_activa_id'] ?? 1);
            $data = json_decode(file_get_contents('php://input'), true);

            // Verificar que la orden existe y pertenece a esta sucursal
            $stmt = $this->db->prepare('SELECT id FROM ordenes_servicio WHERE id = ? AND sucursal_id = ?');
            $stmt->execute([$id, $sucursalId]);
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
            
            // Log para debugging (sin datos del cliente por seguridad)
            error_log('=== UPDATE ORDEN ID: ' . $id . ' ===');
            
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

                // Al cerrar una orden, registrar la fecha si aún no tiene ninguna.
                // Así el Financiero puede determinar en qué semana cae el ingreso:
                // COALESCE(fecha_entregada, fecha_completada, fecha_ingreso).
                $estadosCierre = ['cerrada', 'completada', 'completado', 'entregada', 'entregado'];
                if (in_array($data['estado'], $estadosCierre, true)) {
                    // Ambas fechas: COALESCE en Financiero usa fecha_entregada ?? fecha_completada ?? fecha_ingreso
                    // Si ninguna está explícita, NOW() marca la semana real del cierre
                    $updateFields[] = 'fecha_completada = COALESCE(fecha_completada, NOW())';
                    $updateFields[] = 'fecha_entregada  = COALESCE(fecha_entregada,  NOW())';
                    // Sin push a $updateValues: no son placeholders ?
                }
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
                if (array_key_exists('fecha_anticipo', $data['resumen'] ?? [])) {
                    $updateFields[] = 'fecha_anticipo = ?';
                    $updateValues[] = $data['resumen']['fecha_anticipo'] ?: null;
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
                error_log('SQL (campos): ' . count($updateFields) . ' campos a actualizar');
                
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

            // Recalcular totales desde filas reales si se actualizaron partidas
            // (cubre el caso de que el frontend enviara totales incorrectos)
            if ($shouldUpdateServicios || isset($data['refacciones'])) {
                $stmtSvcU = $this->db->prepare("
                    SELECT
                        COALESCE(SUM(CASE WHEN tipo = 'servicio'   THEN subtotal ELSE 0 END), 0) AS sum_servicios,
                        COALESCE(SUM(CASE WHEN tipo = 'mano_obra'  THEN subtotal ELSE 0 END), 0) AS sum_mano_obra
                    FROM servicios_orden WHERE orden_id = ?
                ");
                $stmtSvcU->execute([$id]);
                $rowSvcU = $stmtSvcU->fetch(PDO::FETCH_ASSOC);

                $stmtRefU = $this->db->prepare("SELECT COALESCE(SUM(subtotal), 0) AS sum_refacciones FROM refacciones_orden WHERE orden_id = ?");
                $stmtRefU->execute([$id]);
                $rowRefU = $stmtRefU->fetch(PDO::FETCH_ASSOC);

                $sumServiciosU   = (float) $rowSvcU['sum_servicios'];
                $sumManoObraU    = (float) $rowSvcU['sum_mano_obra'];
                $sumRefaccionesU = (float) $rowRefU['sum_refacciones'];
                $subtotalCalcU   = $sumServiciosU + $sumManoObraU + $sumRefaccionesU;

                // Leer flag incluir_iva actual de la BD (puede haber venido en resumen o no)
                $stmtIvaFlag = $this->db->prepare("SELECT incluir_iva FROM ordenes_servicio WHERE id = ?");
                $stmtIvaFlag->execute([$id]);
                $rowIvaFlag = $stmtIvaFlag->fetch(PDO::FETCH_ASSOC);
                $incluirIVAU = (bool) ($rowIvaFlag['incluir_iva'] ?? false);
                // Si el request trae el flag actualizado, usarlo
                if (isset($data['resumen']['incluirIVA'])) {
                    $incluirIVAU = (bool) $data['resumen']['incluirIVA'];
                }

                $ivaCalcU   = $incluirIVAU ? round($subtotalCalcU * 0.16, 2) : 0.0;
                $totalCalcU = round($subtotalCalcU + $ivaCalcU, 2);

                $stmtFixU = $this->db->prepare("
                    UPDATE ordenes_servicio
                    SET subtotal_servicios = ?, subtotal_mano_obra = ?, subtotal_refacciones = ?,
                        iva = ?, total = ?
                    WHERE id = ?
                ");
                $stmtFixU->execute([$sumServiciosU, $sumManoObraU, $sumRefaccionesU, $ivaCalcU, $totalCalcU, $id]);
                error_log("Totales recalculados desde filas reales: subtotal={$subtotalCalcU} iva={$ivaCalcU} total={$totalCalcU}");
            }

            $this->db->commit();
            
            // Retornar orden actualizada
            $this->getById($id);
            
        } catch (Exception $e) {
            $this->db->rollBack();
            jsonError('Error al actualizar orden', $e, 500);
        }
    }

    /**
     * Eliminar orden - DELETE /api/ordenes/:id
     */
    public function delete($id) {
        try {
            $userData   = requireAuth();
            $sucursalId = (int) ($userData['sucursal_activa_id'] ?? 1);

            $stmt = $this->db->prepare('DELETE FROM ordenes_servicio WHERE id = ? AND sucursal_id = ?');
            $stmt->execute([$id, $sucursalId]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Orden no encontrada']);
                return;
            }
            
            http_response_code(204);
            
        } catch (Exception $e) {
            jsonError('Error al eliminar orden', $e, 500);
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
                'id'             => (string)$refaccion['id'],
                'nombre'         => $refaccion['descripcion'],
                'cantidad'       => (float)$refaccion['cantidad'],
                'precioVenta'    => (float)$refaccion['precio_unitario'],
                'precioCosto'    => $refaccion['precio_costo'] !== null ? (float)$refaccion['precio_costo'] : null,
                'margenGanancia' => $refaccion['margen_ganancia'] !== null ? (float)$refaccion['margen_ganancia'] : 30,
                'total'          => (float)$refaccion['subtotal'],
                'proveedor'      => $refaccion['proveedor'] ?? null,
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
            'fecha_anticipo' => $orden['fecha_anticipo'] ?? null,
            'restante' => $total - $anticipo
        ];
        
        // Obtener puntos de seguridad de la orden
        $orden['puntosSeguridad'] = [];
        
        
        try {
            // PASO 1: Query básico sin JOINs para ver si hay datos
            $basicStmt = $this->db->prepare('SELECT * FROM orden_puntos_seguridad WHERE orden_id = ?');
            $basicStmt->execute([$orden['id']]);
            $basicPuntos = $basicStmt->fetchAll();
            
            
            if (count($basicPuntos) > 0) {
                // PASO 2: Si hay datos básicos, intentar query completo
                
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
                }
            } else {
            }
            
        } catch (Exception $e) {
            $orden['puntosSeguridad'] = [];
        }
        
        
        return $orden;
    }
    
    private function upsertCliente($clienteData, int $sucursalId) {
        $nombre    = $clienteData['nombreCompleto'] ?? $clienteData['nombre'] ?? null;
        $telefono  = $clienteData['telefono'] ?? null;
        $email     = $clienteData['email'] ?? null;
        $direccion = $clienteData['domicilio'] ?? $clienteData['direccion'] ?? null;
        $clienteId = isset($clienteData['cliente_id']) ? (int)$clienteData['cliente_id'] : null;

        if (!$nombre || !$telefono) {
            throw new Exception('Nombre y teléfono del cliente son requeridos');
        }

        $telNormalizado = preg_replace('/[^0-9]/', '', $telefono);
        $telNormalizado = preg_replace('/^52/', '', $telNormalizado);

        // 1. El frontend identificó explícitamente al cliente (solo buscar en esta sucursal)
        if ($clienteId) {
            $stmt = $this->db->prepare(
                'UPDATE clientes SET ultima_visita = NOW() WHERE id = ? AND activo = 1 AND sucursal_id = ?'
            );
            $stmt->execute([$clienteId, $sucursalId]);
            if ($stmt->rowCount() > 0) {
                return $clienteId;
            }
        }

        // 2. Buscar por teléfono normalizado DENTRO de la misma sucursal
        if ($telNormalizado) {
            $stmt = $this->db->prepare(
                'SELECT id FROM clientes WHERE telefono_normalizado = ? AND activo = 1 AND sucursal_id = ?'
            );
            $stmt->execute([$telNormalizado, $sucursalId]);
            $existentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($existentes) === 1) {
                $existingId = (int)$existentes[0]['id'];
                $stmt = $this->db->prepare(
                    'UPDATE clientes SET nombre = ?, email = ?, ultima_visita = NOW() WHERE id = ? AND sucursal_id = ?'
                );
                $stmt->execute([$nombre, $email, $existingId, $sucursalId]);
                return $existingId;
            }
        }

        // 3. Cliente nuevo — asignar a la sucursal activa
        $stmt = $this->db->prepare(
            'INSERT INTO clientes (nombre, telefono, email, direccion, telefono_normalizado, sucursal_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$nombre, $telefono, $email, $direccion, $telNormalizado, $sucursalId]);
        return $this->db->lastInsertId();
    }
    
    private function upsertVehiculo($vehiculoData, $cliente_id, int $sucursalId) {
        $marca  = $vehiculoData['marca']  ?? null;
        $modelo = $vehiculoData['modelo'] ?? null;
        $anio   = $vehiculoData['anio']   ?? null;
        $color  = $vehiculoData['color']  ?? null;
        $placas = $vehiculoData['placas'] ?? null;
        $niv    = $vehiculoData['niv']    ?? null;

        if (!$marca || !$modelo || !$placas) {
            throw new Exception('Marca, modelo y placas del vehículo son requeridos');
        }

        // Buscar vehículo existente por placas DENTRO de la misma sucursal
        $stmt = $this->db->prepare('SELECT id FROM vehiculos WHERE placas = ? AND sucursal_id = ? LIMIT 1');
        $stmt->execute([$placas, $sucursalId]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $this->db->prepare('
                UPDATE vehiculos SET marca = ?, modelo = ?, anio = ?, color = ?, niv = ?
                WHERE id = ? AND sucursal_id = ?
            ');
            $stmt->execute([$marca, $modelo, $anio, $color, $niv, $existing['id'], $sucursalId]);
            return $existing['id'];
        } else {
            $stmt = $this->db->prepare('
                INSERT INTO vehiculos (marca, modelo, anio, color, placas, niv, cliente_id, sucursal_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$marca, $modelo, $anio, $color, $placas, $niv, $cliente_id, $sucursalId]);
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
            INSERT INTO refacciones_orden (orden_id, descripcion, cantidad, precio_unitario, precio_costo, margen_ganancia, subtotal, proveedor)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');

        foreach ($refacciones as $refaccion) {
            $cantidad       = $refaccion['cantidad'] ?? 1;
            $precioUnitario = $refaccion['precioVenta'] ?? $refaccion['precio_unitario'] ?? 0;
            $subtotal       = $refaccion['total'] ?? ($cantidad * $precioUnitario);
            // Margen variable: si el frontend envía precioCosto y margenGanancia, los guardamos.
            // Fallback a NULL para registros anteriores (el sistema usará subtotal/1.30 como costo).
            $precioCosto    = isset($refaccion['precioCosto']) && $refaccion['precioCosto'] > 0
                                ? (float) $refaccion['precioCosto']
                                : null;
            $margenGanancia = isset($refaccion['margenGanancia']) && $refaccion['margenGanancia'] > 0
                                ? (float) $refaccion['margenGanancia']
                                : null;

            $stmt->execute([
                $orden_id,
                $refaccion['nombre'] ?? $refaccion['descripcion'],
                $cantidad,
                $precioUnitario,
                $precioCosto,
                $margenGanancia,
                $subtotal,
                $refaccion['proveedor'] ?? null,
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
    }
    
    /**
     * Genera el número de orden con prefijo de sucursal para evitar colisiones UNIQUE.
     * Formato: S{sucursal_id}-YYMMDD-{id}
     * Ejemplo: S1-260602-1650 / S2-260602-1651
     */
    private function generateNumeroOrden(int $id, int $sucursalId = 1): string {
        $yymmdd = date('ymd');
        return "S{$sucursalId}-{$yymmdd}-{$id}";
    }
    
    /**
     * Validar datos de la orden antes de crear/actualizar
     * Retorna array de errores específicos por campo
     */
    private function validateOrdenData($data) {
        $errors = [];
        
        // ========== VALIDACIÓN CLIENTE ==========
        if (!isset($data['cliente'])) {
            $errors['cliente'] = 'Los datos del cliente son obligatorios';
        } else {
            $cliente = $data['cliente'];
            
            // Nombre obligatorio (VARCHAR 200)
            if (empty($cliente['nombreCompleto'])) {
                $errors['cliente.nombreCompleto'] = 'El nombre del cliente es obligatorio';
            } elseif (strlen($cliente['nombreCompleto']) < 2) {
                $errors['cliente.nombreCompleto'] = 'El nombre debe tener al menos 2 caracteres';
            } elseif (strlen($cliente['nombreCompleto']) > 200) {
                $errors['cliente.nombreCompleto'] = 'El nombre no puede exceder 200 caracteres';
            }
            
            // Teléfono (VARCHAR 20) - Validar formato si se proporciona
            if (!empty($cliente['telefono'])) {
                // Remover espacios y guiones para validar
                $telefonoLimpio = preg_replace('/[\s\-\(\)]/', '', $cliente['telefono']);
                if (!preg_match('/^\d{10}$/', $telefonoLimpio)) {
                    $errors['cliente.telefono'] = 'El teléfono debe tener 10 dígitos';
                }
                if (strlen($cliente['telefono']) > 20) {
                    $errors['cliente.telefono'] = 'El teléfono no puede exceder 20 caracteres';
                }
            }
            
            // Email (VARCHAR 150) - Validar formato si se proporciona
            if (!empty($cliente['email'])) {
                if (!filter_var($cliente['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors['cliente.email'] = 'El formato del email no es válido';
                } elseif (strlen($cliente['email']) > 150) {
                    $errors['cliente.email'] = 'El email no puede exceder 150 caracteres';
                }
            }
        }
        
        // ========== VALIDACIÓN VEHÍCULO ==========
        if (!isset($data['vehiculo'])) {
            $errors['vehiculo'] = 'Los datos del vehículo son obligatorios';
        } else {
            $vehiculo = $data['vehiculo'];
            
            // Marca obligatoria (VARCHAR 100)
            if (empty($vehiculo['marca'])) {
                $errors['vehiculo.marca'] = 'La marca del vehículo es obligatoria';
            } elseif (strlen($vehiculo['marca']) > 100) {
                $errors['vehiculo.marca'] = 'La marca no puede exceder 100 caracteres';
            }
            
            // Modelo obligatorio (VARCHAR 100)
            if (empty($vehiculo['modelo'])) {
                $errors['vehiculo.modelo'] = 'El modelo del vehículo es obligatorio';
            } elseif (strlen($vehiculo['modelo']) > 100) {
                $errors['vehiculo.modelo'] = 'El modelo no puede exceder 100 caracteres';
            }
            
            // Año (YEAR) - Validar si se proporciona
            if (!empty($vehiculo['anio'])) {
                $anio = intval($vehiculo['anio']);
                if ($anio < 1900 || $anio > 2030) {
                    $errors['vehiculo.anio'] = 'El año debe estar entre 1900 y 2030';
                }
            }
            
            // Placas (VARCHAR 20) - Validar si se proporciona
            if (!empty($vehiculo['placas'])) {
                if (strlen($vehiculo['placas']) > 20) {
                    $errors['vehiculo.placas'] = 'Las placas no pueden exceder 20 caracteres';
                }
            }
            
            // NIV (VARCHAR 50) - Validar si se proporciona
            if (!empty($vehiculo['niv'])) {
                if (strlen($vehiculo['niv']) > 50) {
                    $errors['vehiculo.niv'] = 'El NIV no puede exceder 50 caracteres';
                }
            }
            
            // Kilometraje entrada (VARCHAR 20) - Solo números si se proporciona
            if (!empty($vehiculo['kilometrajeEntrada'])) {
                $km = preg_replace('/[^\d]/', '', $vehiculo['kilometrajeEntrada']);
                if (!is_numeric($km)) {
                    $errors['vehiculo.kilometrajeEntrada'] = 'El kilometraje de entrada debe contener solo números';
                }
                if (strlen($vehiculo['kilometrajeEntrada']) > 20) {
                    $errors['vehiculo.kilometrajeEntrada'] = 'El kilometraje no puede exceder 20 caracteres';
                }
            }
            
            // Kilometraje salida (VARCHAR 20) - Solo números si se proporciona
            if (!empty($vehiculo['kilometrajeSalida'])) {
                $km = preg_replace('/[^\d]/', '', $vehiculo['kilometrajeSalida']);
                if (!is_numeric($km)) {
                    $errors['vehiculo.kilometrajeSalida'] = 'El kilometraje de salida debe contener solo números';
                }
                if (strlen($vehiculo['kilometrajeSalida']) > 20) {
                    $errors['vehiculo.kilometrajeSalida'] = 'El kilometraje no puede exceder 20 caracteres';
                }
            }
            
            // Nivel de combustible (DECIMAL 5,2) - Entre 0 y 100
            if (isset($vehiculo['nivelCombustible'])) {
                $nivel = floatval($vehiculo['nivelCombustible']);
                if ($nivel < 0 || $nivel > 100) {
                    $errors['vehiculo.nivelCombustible'] = 'El nivel de combustible debe estar entre 0 y 100';
                }
            }
        }
        
        // ========== VALIDACIÓN PROBLEMA REPORTADO ==========
        // Campo obligatorio (TEXT NOT NULL)
        if (empty($data['problemaReportado'])) {
            $errors['problemaReportado'] = 'El problema reportado es obligatorio';
        }
        
        // ========== VALIDACIÓN RESUMEN FINANCIERO ==========
        if (isset($data['resumen'])) {
            $resumen = $data['resumen'];
            
            // Validar montos - deben ser números positivos o cero
            $camposMonetarios = ['servicios', 'manoDeObra', 'refacciones', 'iva', 'total', 'anticipo'];
            
            foreach ($camposMonetarios as $campo) {
                if (isset($resumen[$campo])) {
                    $valor = $resumen[$campo];
                    if (!is_numeric($valor) || $valor < 0) {
                        $errors["resumen.{$campo}"] = "El campo {$campo} debe ser un número positivo";
                    }
                    // Validar que no exceda DECIMAL(10,2)
                    if ($valor > 99999999.99) {
                        $errors["resumen.{$campo}"] = "El campo {$campo} excede el límite máximo";
                    }
                }
            }
            
            // Validar que el anticipo no sea mayor que el total
            if (isset($resumen['anticipo']) && isset($resumen['total'])) {
                if (floatval($resumen['anticipo']) > floatval($resumen['total'])) {
                    $errors['resumen.anticipo'] = 'El anticipo no puede ser mayor que el total';
                }
            }
        }
        
        // ========== VALIDACIÓN SERVICIOS Y REFACCIONES ==========
        if (isset($data['servicios'])) {
            foreach ($data['servicios'] as $index => $servicio) {
                if (empty($servicio['descripcion'])) {
                    $errors["servicios.{$index}.descripcion"] = 'La descripción del servicio es obligatoria';
                }
                if (!isset($servicio['precio']) || !is_numeric($servicio['precio']) || $servicio['precio'] < 0) {
                    $errors["servicios.{$index}.precio"] = 'El precio debe ser un número positivo';
                }
            }
        }
        
        if (isset($data['manoDeObra'])) {
            foreach ($data['manoDeObra'] as $index => $mano) {
                if (empty($mano['descripcion'])) {
                    $errors["manoDeObra.{$index}.descripcion"] = 'La descripción de mano de obra es obligatoria';
                }
                if (!isset($mano['precio']) || !is_numeric($mano['precio']) || $mano['precio'] < 0) {
                    $errors["manoDeObra.{$index}.precio"] = 'El precio debe ser un número positivo';
                }
            }
        }
        
        if (isset($data['refacciones'])) {
            foreach ($data['refacciones'] as $index => $refaccion) {
                if (empty($refaccion['nombre'])) {
                    $errors["refacciones.{$index}.nombre"] = 'El nombre de la refacción es obligatorio';
                }
                if (!isset($refaccion['cantidad']) || !is_numeric($refaccion['cantidad']) || $refaccion['cantidad'] <= 0) {
                    $errors["refacciones.{$index}.cantidad"] = 'La cantidad debe ser un número mayor a 0';
                }
                if (!isset($refaccion['precioVenta']) || !is_numeric($refaccion['precioVenta']) || $refaccion['precioVenta'] < 0) {
                    $errors["refacciones.{$index}.precioVenta"] = 'El precio de venta debe ser un número positivo';
                }
            }
        }
        
        return $errors;
    }
}
