<?php

class PuntosSeguridadController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * GET /api/puntos-seguridad/catalogo
     * Obtener catálogo completo de puntos de seguridad activos
     */
    public function getCatalogo() {
        try {
            $query = "SELECT id, nombre, descripcion, orden 
                      FROM puntos_seguridad_catalogo 
                      WHERE activo = 1 
                      ORDER BY orden ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $puntos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convertir a formato esperado por el frontend
            $puntosFormatted = array_map(function($punto) {
                return [
                    'id' => (int)$punto['id'],
                    'nombre' => $punto['nombre'],
                    'descripcion' => $punto['descripcion'],
                    'orden' => (int)$punto['orden']
                ];
            }, $puntos);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $puntosFormatted
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener catálogo de puntos de seguridad',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/puntos-seguridad/catalogo/:id
     * Obtener un punto específico del catálogo
     */
    public function getPuntoById($id) {
        try {
            $query = "SELECT id, nombre, descripcion, orden, activo 
                      FROM puntos_seguridad_catalogo 
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $punto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$punto) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Punto de seguridad no encontrado'
                ]);
                return;
            }

            $puntoFormatted = [
                'id' => (int)$punto['id'],
                'nombre' => $punto['nombre'],
                'descripcion' => $punto['descripcion'],
                'orden' => (int)$punto['orden'],
                'activo' => (bool)$punto['activo']
            ];

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $puntoFormatted
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener punto de seguridad',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/ordenes/:ordenId/puntos-seguridad
     * Obtener puntos de seguridad de una orden específica con su estado
     */
    public function getPuntosByOrden($ordenId) {
        try {
            $query = "SELECT 
                        ops.id,
                        ops.punto_seguridad_id as puntoId,
                        ops.estado_id as estadoId,
                        ops.notas,
                        psc.nombre as puntoNombre,
                        es.nombre_display as estadoNombre,
                        es.color_hex as estadoColor,
                        es.codigo as estadoCodigo
                      FROM orden_puntos_seguridad ops
                      INNER JOIN puntos_seguridad_catalogo psc ON ops.punto_seguridad_id = psc.id
                      INNER JOIN estados_seguridad es ON ops.estado_id = es.id
                      WHERE ops.orden_id = :orden_id
                      ORDER BY psc.orden ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
            $stmt->execute();
            $puntos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formatear datos para el frontend
            $puntosFormatted = array_map(function($punto) {
                return [
                    'id' => (int)$punto['id'],
                    'puntoId' => (int)$punto['puntoId'],
                    'estadoId' => (int)$punto['estadoId'],
                    'puntoNombre' => $punto['puntoNombre'],
                    'estadoNombre' => $punto['estadoNombre'],
                    'estadoColor' => $punto['estadoColor'],
                    'estadoCodigo' => $punto['estadoCodigo'],
                    'notas' => $punto['notas']
                ];
            }, $puntos);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $puntosFormatted
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener puntos de seguridad de la orden',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/ordenes/:ordenId/puntos-seguridad
     * Guardar/actualizar puntos de seguridad de una orden
     * Espera un array de puntos: [{ puntoId, estadoId, notas? }]
     */
    public function savePuntosByOrden($ordenId, $data) {
        try {
            // Validar que vengan datos
            if (!isset($data['puntos']) || !is_array($data['puntos'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Se requiere un array de puntos'
                ]);
                return;
            }

            // Verificar que la orden existe
            $checkOrden = "SELECT id FROM ordenes WHERE id = :orden_id";
            $stmtCheck = $this->db->prepare($checkOrden);
            $stmtCheck->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
            $stmtCheck->execute();
            
            if (!$stmtCheck->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Orden no encontrada'
                ]);
                return;
            }

            $this->db->beginTransaction();

            try {
                // Eliminar puntos existentes de esta orden
                $deleteQuery = "DELETE FROM orden_puntos_seguridad WHERE orden_id = :orden_id";
                $stmtDelete = $this->db->prepare($deleteQuery);
                $stmtDelete->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
                $stmtDelete->execute();

                // Insertar nuevos puntos
                $insertQuery = "INSERT INTO orden_puntos_seguridad 
                                (orden_id, punto_seguridad_id, estado_id, notas) 
                                VALUES (:orden_id, :punto_id, :estado_id, :notas)";
                $stmtInsert = $this->db->prepare($insertQuery);

                foreach ($data['puntos'] as $punto) {
                    // Validar campos requeridos
                    if (!isset($punto['puntoId']) || !isset($punto['estadoId'])) {
                        throw new Exception('Cada punto debe tener puntoId y estadoId');
                    }

                    $stmtInsert->bindParam(':orden_id', $ordenId, PDO::PARAM_INT);
                    $stmtInsert->bindParam(':punto_id', $punto['puntoId'], PDO::PARAM_INT);
                    $stmtInsert->bindParam(':estado_id', $punto['estadoId'], PDO::PARAM_INT);
                    $stmtInsert->bindValue(':notas', $punto['notas'] ?? null);
                    $stmtInsert->execute();
                }

                $this->db->commit();

                http_response_code(200);
                echo json_encode([
                    'success' => true,
                    'message' => 'Puntos de seguridad guardados exitosamente',
                    'data' => [
                        'ordenId' => (int)$ordenId,
                        'puntosGuardados' => count($data['puntos'])
                    ]
                ]);
            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al guardar puntos de seguridad',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/admin/puntos-seguridad/catalogo
     * Crear un nuevo punto en el catálogo (Admin)
     */
    public function createPunto($data) {
        try {
            // Validar datos requeridos
            if (empty($data['nombre'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'El campo nombre es requerido'
                ]);
                return;
            }

            // Insertar nuevo punto
            $query = "INSERT INTO puntos_seguridad_catalogo (nombre, descripcion, orden) 
                      VALUES (:nombre, :descripcion, :orden)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':nombre', $data['nombre']);
            $stmt->bindValue(':descripcion', $data['descripcion'] ?? null);
            $stmt->bindValue(':orden', $data['orden'] ?? 0, PDO::PARAM_INT);
            
            $stmt->execute();
            $newId = $this->db->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Punto de seguridad creado exitosamente',
                'data' => ['id' => (int)$newId]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al crear punto de seguridad',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * PUT /api/admin/puntos-seguridad/catalogo/:id
     * Actualizar un punto del catálogo (Admin)
     */
    public function updatePunto($id, $data) {
        try {
            // Verificar que el punto existe
            $checkQuery = "SELECT id FROM puntos_seguridad_catalogo WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Punto de seguridad no encontrado'
                ]);
                return;
            }

            // Construir query de actualización
            $fieldsToUpdate = [];
            $params = [':id' => $id];

            if (isset($data['nombre'])) {
                $fieldsToUpdate[] = "nombre = :nombre";
                $params[':nombre'] = $data['nombre'];
            }
            if (isset($data['descripcion'])) {
                $fieldsToUpdate[] = "descripcion = :descripcion";
                $params[':descripcion'] = $data['descripcion'];
            }
            if (isset($data['orden'])) {
                $fieldsToUpdate[] = "orden = :orden";
                $params[':orden'] = $data['orden'];
            }
            if (isset($data['activo'])) {
                $fieldsToUpdate[] = "activo = :activo";
                $params[':activo'] = $data['activo'] ? 1 : 0;
            }

            if (empty($fieldsToUpdate)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'No hay campos para actualizar'
                ]);
                return;
            }

            $query = "UPDATE puntos_seguridad_catalogo SET " . implode(', ', $fieldsToUpdate) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Punto de seguridad actualizado exitosamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar punto de seguridad',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * DELETE /api/admin/puntos-seguridad/catalogo/:id
     * Desactivar un punto del catálogo (soft delete) (Admin)
     */
    public function deletePunto($id) {
        try {
            // Verificar que el punto existe
            $checkQuery = "SELECT id FROM puntos_seguridad_catalogo WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Punto de seguridad no encontrado'
                ]);
                return;
            }

            // Soft delete
            $query = "UPDATE puntos_seguridad_catalogo SET activo = 0 WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Punto de seguridad desactivado exitosamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al desactivar punto de seguridad',
                'error' => $e->getMessage()
            ]);
        }
    }
}