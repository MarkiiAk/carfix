<?php

class EstadosSeguridadController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * GET /api/estados-seguridad
     * Obtener todos los estados de seguridad activos
     */
    public function getEstados() {
        try {
            $query = "SELECT id, codigo, nombre_display, color_hex, icono, orden 
                      FROM estados_seguridad 
                      WHERE activo = 1 
                      ORDER BY orden ASC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $estados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Convertir a camelCase para el frontend
            $estadosFormatted = array_map(function($estado) {
                return [
                    'id' => (int)$estado['id'],
                    'codigo' => $estado['codigo'],
                    'nombreDisplay' => $estado['nombre_display'],
                    'colorHex' => $estado['color_hex'],
                    'icono' => $estado['icono'],
                    'orden' => (int)$estado['orden']
                ];
            }, $estados);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $estadosFormatted
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener estados de seguridad',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/estados-seguridad/:id
     * Obtener un estado específico por ID
     */
    public function getEstadoById($id) {
        try {
            $query = "SELECT id, codigo, nombre_display, color_hex, icono, orden, activo 
                      FROM estados_seguridad 
                      WHERE id = :id";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            $estado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$estado) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Estado no encontrado'
                ]);
                return;
            }

            // Convertir a camelCase
            $estadoFormatted = [
                'id' => (int)$estado['id'],
                'codigo' => $estado['codigo'],
                'nombreDisplay' => $estado['nombre_display'],
                'colorHex' => $estado['color_hex'],
                'icono' => $estado['icono'],
                'orden' => (int)$estado['orden'],
                'activo' => (bool)$estado['activo']
            ];

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => $estadoFormatted
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al obtener estado',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/admin/estados-seguridad
     * Crear un nuevo estado de seguridad (Admin)
     */
    public function createEstado($data) {
        try {
            // Validar datos requeridos
            if (empty($data['codigo']) || empty($data['nombreDisplay']) || empty($data['colorHex'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Faltan campos requeridos: codigo, nombreDisplay, colorHex'
                ]);
                return;
            }

            // Validar que el código no exista
            $checkQuery = "SELECT id FROM estados_seguridad WHERE codigo = :codigo";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':codigo', $data['codigo']);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Ya existe un estado con ese código'
                ]);
                return;
            }

            // Insertar nuevo estado
            $query = "INSERT INTO estados_seguridad (codigo, nombre_display, color_hex, icono, orden) 
                      VALUES (:codigo, :nombre_display, :color_hex, :icono, :orden)";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':codigo', $data['codigo']);
            $stmt->bindParam(':nombre_display', $data['nombreDisplay']);
            $stmt->bindParam(':color_hex', $data['colorHex']);
            $stmt->bindValue(':icono', $data['icono'] ?? null);
            $stmt->bindValue(':orden', $data['orden'] ?? 0, PDO::PARAM_INT);
            
            $stmt->execute();
            $newId = $this->db->lastInsertId();

            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Estado creado exitosamente',
                'data' => ['id' => (int)$newId]
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al crear estado',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * PUT /api/admin/estados-seguridad/:id
     * Actualizar un estado existente (Admin)
     */
    public function updateEstado($id, $data) {
        try {
            // Verificar que el estado existe
            $checkQuery = "SELECT id FROM estados_seguridad WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Estado no encontrado'
                ]);
                return;
            }

            // Construir query de actualización dinámica
            $fieldsToUpdate = [];
            $params = [':id' => $id];

            if (isset($data['codigo'])) {
                $fieldsToUpdate[] = "codigo = :codigo";
                $params[':codigo'] = $data['codigo'];
            }
            if (isset($data['nombreDisplay'])) {
                $fieldsToUpdate[] = "nombre_display = :nombre_display";
                $params[':nombre_display'] = $data['nombreDisplay'];
            }
            if (isset($data['colorHex'])) {
                $fieldsToUpdate[] = "color_hex = :color_hex";
                $params[':color_hex'] = $data['colorHex'];
            }
            if (isset($data['icono'])) {
                $fieldsToUpdate[] = "icono = :icono";
                $params[':icono'] = $data['icono'];
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

            $query = "UPDATE estados_seguridad SET " . implode(', ', $fieldsToUpdate) . " WHERE id = :id";
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Estado actualizado exitosamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al actualizar estado',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * DELETE /api/admin/estados-seguridad/:id
     * Desactivar un estado (soft delete) (Admin)
     */
    public function deleteEstado($id) {
        try {
            // Verificar que el estado existe
            $checkQuery = "SELECT id FROM estados_seguridad WHERE id = :id";
            $checkStmt = $this->db->prepare($checkQuery);
            $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Estado no encontrado'
                ]);
                return;
            }

            // Soft delete: marcar como inactivo
            $query = "UPDATE estados_seguridad SET activo = 0 WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Estado desactivado exitosamente'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error al desactivar estado',
                'error' => $e->getMessage()
            ]);
        }
    }
}