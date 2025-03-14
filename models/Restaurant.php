<?php
/**
 * Modelo para áreas do restaurante
 * 
 * Gerencia operações de CRUD para áreas do restaurante,
 * como salão principal, terraço, área externa, etc.
 * 
 * Status: 90% Completo
 * Pendente:
 * - Adicionar vinculação com planta baixa ou imagem
 */

require_once __DIR__ . '/../config/database.php';

class RestaurantArea {
    private $db;
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
    }
    
    /**
     * Retorna todas as áreas de um tenant
     */
    public function getAreasByTenantId($tenantId) {
        try {
            $query = "SELECT * FROM restaurant_areas WHERE tenant_id = ? AND is_active = 1 ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $areas = [];
            while ($row = $result->fetch_assoc()) {
                $areas[] = $row;
            }
            
            return $areas;
        } catch (\Exception $e) {
            error_log('Erro ao obter áreas: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém uma área específica pelo ID
     */
    public function getAreaById($areaId, $tenantId) {
        try {
            $query = "SELECT * FROM restaurant_areas WHERE id = ? AND tenant_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $areaId, $tenantId);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc();
        } catch (\Exception $e) {
            error_log('Erro ao obter área: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Cria uma nova área
     */
    public function createArea($data) {
        try {
            $query = "INSERT INTO restaurant_areas (tenant_id, name, description, is_active) 
                     VALUES (?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $isActive = isset($data['is_active']) ? 1 : 0;
            $stmt->bind_param("issi", 
                $data['tenant_id'],
                $data['name'],
                $data['description'],
                $isActive
            );
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                return $stmt->insert_id;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log('Erro ao criar área: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualiza uma área existente
     */
    public function updateArea($areaId, $data) {
        try {
            $query = "UPDATE restaurant_areas SET 
                     name = ?, 
                     description = ?, 
                     is_active = ? 
                     WHERE id = ? AND tenant_id = ?";
            $stmt = $this->db->prepare($query);
            $isActive = isset($data['is_active']) ? 1 : 0;
            $stmt->bind_param("ssiii", 
                $data['name'],
                $data['description'],
                $isActive,
                $areaId,
                $data['tenant_id']
            );
            $stmt->execute();
            
            return $stmt->affected_rows >= 0;
        } catch (\Exception $e) {
            error_log('Erro ao atualizar área: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Exclui uma área
     */
    public function deleteArea($areaId, $tenantId) {
        try {
            // Verificar se existem mesas na área
            $query = "SELECT COUNT(*) as count FROM tables WHERE area_id = ? AND tenant_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $areaId, $tenantId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if ($result['count'] > 0) {
                // Se existem mesas, apenas desative a área
                $query = "UPDATE restaurant_areas SET is_active = 0 WHERE id = ? AND tenant_id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("ii", $areaId, $tenantId);
                $stmt->execute();
            } else {
                // Se não existem mesas, exclua a área
                $query = "DELETE FROM restaurant_areas WHERE id = ? AND tenant_id = ?";
                $stmt = $this->db->prepare($query);
                $stmt->bind_param("ii", $areaId, $tenantId);
                $stmt->execute();
            }
            
            return $stmt->affected_rows > 0;
        } catch (\Exception $e) {
            error_log('Erro ao excluir área: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica se uma área possui mesas
     */
    public function checkAreaHasTables($areaId, $tenantId) {
        try {
            $query = "SELECT COUNT(*) as count FROM tables WHERE area_id = ? AND tenant_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $areaId, $tenantId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            return $result['count'] > 0;
        } catch (\Exception $e) {
            error_log('Erro ao verificar mesas na área: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtém estatísticas das áreas (quantidade de mesas, capacidade total)
     */
    public function getAreaStatistics($tenantId) {
        try {
            $query = "SELECT 
                        a.id, 
                        a.name, 
                        COUNT(t.id) as table_count, 
                        SUM(t.capacity) as total_capacity,
                        COUNT(CASE WHEN t.status = 'occupied' THEN 1 END) as occupied_tables,
                        COUNT(CASE WHEN t.status = 'available' THEN 1 END) as available_tables
                     FROM restaurant_areas a
                     LEFT JOIN tables t ON a.id = t.area_id AND t.tenant_id = a.tenant_id
                     WHERE a.tenant_id = ? AND a.is_active = 1
                     GROUP BY a.id
                     ORDER BY a.name";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $stats = [];
            while ($row = $result->fetch_assoc()) {
                $stats[] = $row;
            }
            
            // Adicionar estatísticas para mesas sem área
            $query = "SELECT 
                        COUNT(id) as table_count, 
                        SUM(capacity) as total_capacity,
                        COUNT(CASE WHEN status = 'occupied' THEN 1 END) as occupied_tables,
                        COUNT(CASE WHEN status = 'available' THEN 1 END) as available_tables
                     FROM tables 
                     WHERE tenant_id = ? AND area_id IS NULL";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $tenantId);
            $stmt->execute();
            $noAreaStats = $stmt->get_result()->fetch_assoc();
            
            if ($noAreaStats && $noAreaStats['table_count'] > 0) {
                $stats[] = array_merge([
                    'id' => 0,
                    'name' => 'Sem área definida'
                ], $noAreaStats);
            }
            
            return $stats;
        } catch (\Exception $e) {
            error_log('Erro ao obter estatísticas das áreas: ' . $e->getMessage());
            return [];
        }
    }
}