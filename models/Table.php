<?php
/**
 * Modelo para gestão de mesas
 * 
 * Gerencia operações de CRUD e lógica de negócio relacionada a mesas
 * e áreas do restaurante.
 * 
 * Status: 80% Completo
 * Pendente:
 * - Implementar estatísticas avançadas de mesas
 * - Melhorar registro de histórico de ocupação
 * - Adicionar suporte a layouts de mesa customizados
 */

require_once __DIR__ . '/../config/database.php';

class Table {
    private $db;
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
    }
    
    /**
     * Retorna todas as mesas de um tenant
     */
    public function getTablesByTenantId($tenantId) {
        try {
            $query = "SELECT t.*, a.name as area_name 
                     FROM tables t 
                     LEFT JOIN restaurant_areas a ON t.area_id = a.id 
                     WHERE t.tenant_id = ? 
                     ORDER BY t.area_id, t.number";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $tenantId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $tables = [];
            while ($row = $result->fetch_assoc()) {
                $tables[] = $row;
            }
            
            return $tables;
        } catch (\Exception $e) {
            error_log('Erro ao obter mesas: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Retorna a quantidade de mesas de um tenant
     */
    public function getTableCountByTenantId($tenantId) {
        try {
            $query = "SELECT COUNT(*) as total FROM tables WHERE tenant_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $tenantId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            return $result['total'];
        } catch (\Exception $e) {
            error_log('Erro ao contar mesas: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Obtém uma mesa específica pelo ID
     */
    public function getTableById($tableId, $tenantId) {
        try {
            $query = "SELECT t.*, a.name as area_name 
                     FROM tables t 
                     LEFT JOIN restaurant_areas a ON t.area_id = a.id 
                     WHERE t.id = ? AND t.tenant_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $tableId, $tenantId);
            $stmt->execute();
            return $stmt->get_result()->fetch_assoc();
        } catch (\Exception $e) {
            error_log('Erro ao obter mesa: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Verifica se um número de mesa já existe
     */
    public function checkTableNumberExists($tenantId, $number, $excludeTableId = null) {
        try {
            $query = "SELECT COUNT(*) as count FROM tables WHERE tenant_id = ? AND number = ?";
            $params = [$tenantId, $number];
            $types = "is";
            
            if ($excludeTableId) {
                $query .= " AND id != ?";
                $params[] = $excludeTableId;
                $types .= "i";
            }
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            return $result['count'] > 0;
        } catch (\Exception $e) {
            error_log('Erro ao verificar número de mesa: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cria uma nova mesa
     */
    public function createTable($data) {
        try {
            $query = "INSERT INTO tables (tenant_id, area_id, number, name, capacity, 
                     position_x, position_y, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("iissiiis", 
                $data['tenant_id'],
                $data['area_id'],
                $data['number'],
                $data['name'],
                $data['capacity'],
                $data['position_x'],
                $data['position_y'],
                $data['status']
            );
            $stmt->execute();
            
            if ($stmt->affected_rows > 0) {
                return $stmt->insert_id;
            }
            
            return false;
        } catch (\Exception $e) {
            error_log('Erro ao criar mesa: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Atualiza uma mesa existente
     */
    public function updateTable($tableId, $data) {
        try {
            $setClause = [];
            $params = [];
            $types = "";
            
            // Construir cláusula SET dinamicamente
            foreach ($data as $key => $value) {
                $setClause[] = "{$key} = ?";
                $params[] = $value;
                
                // Determinar tipo do parâmetro
                if (is_int($value)) {
                    $types .= "i";
                } elseif (is_float($value)) {
                    $types .= "d";
                } else {
                    $types .= "s";
                }
            }
            
            // Finalizar parâmetros
            $params[] = $tableId;
            $types .= "i";
            
            $query = "UPDATE tables SET " . implode(", ", $setClause) . " WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            
            return $stmt->affected_rows >= 0;
        } catch (\Exception $e) {
            error_log('Erro ao atualizar mesa: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Exclui uma mesa
     */
    public function deleteTable($tableId, $tenantId) {
        try {
            $query = "DELETE FROM tables WHERE id = ? AND tenant_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $tableId, $tenantId);
            $stmt->execute();
            
            return $stmt->affected_rows > 0;
        } catch (\Exception $e) {
            error_log('Erro ao excluir mesa: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Inicia um registro de ocupação de mesa
     */
    public function startTableOccupancy($tableId, $tenantId, $numberOfCustomers = 1) {
        try {
            $query = "INSERT INTO table_occupancy_history (tenant_id, table_id, start_time, number_of_customers) 
                     VALUES (?, ?, CURRENT_TIMESTAMP, ?)";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("iii", $tenantId, $tableId, $numberOfCustomers);
            $stmt->execute();
            
            return $stmt->insert_id;
        } catch (\Exception $e) {
            error_log('Erro ao iniciar ocupação de mesa: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Finaliza um registro de ocupação de mesa
     */
    public function endTableOccupancy($tableId, $tenantId, $orderId = null, $totalSpent = null) {
        try {
            // Obter o registro de ocupação mais recente que não foi finalizado
            $query = "SELECT id FROM table_occupancy_history 
                     WHERE table_id = ? AND tenant_id = ? AND end_time IS NULL 
                     ORDER BY start_time DESC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ii", $tableId, $tenantId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if (!$result) {
                return false; // Não há registro para finalizar
            }
            
            $occupancyId = $result['id'];
            
            // Construir a query de atualização
            $updateQuery = "UPDATE table_occupancy_history SET end_time = CURRENT_TIMESTAMP";
            $params = [];
            $types = "";
            
            if ($orderId !== null) {
                $updateQuery .= ", order_id = ?";
                $params[] = $orderId;
                $types .= "i";
            }
            
            if ($totalSpent !== null) {
                $updateQuery .= ", total_spent = ?";
                $params[] = $totalSpent;
                $types .= "d";
            }
            
            $updateQuery .= " WHERE id = ?";
            $params[] = $occupancyId;
            $types .= "i";
            
            $stmt = $this->db->prepare($updateQuery);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            
            return $stmt->affected_rows > 0;
        } catch (\Exception $e) {
            error_log('Erro ao finalizar ocupação de mesa: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtém o histórico de ocupação de uma mesa
     */
    public function getTableOccupancyHistory($tableId, $tenantId, $limit = 50) {
        try {
            $query = "SELECT h.*, o.order_number, o.subtotal, o.total 
                     FROM table_occupancy_history h 
                     LEFT JOIN orders o ON h.order_id = o.id 
                     WHERE h.table_id = ? AND h.tenant_id = ? 
                     ORDER BY h.start_time DESC 
                     LIMIT ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("iii", $tableId, $tenantId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $history = [];
            while ($row = $result->fetch_assoc()) {
                // Calcular duração se tiver hora de término
                if ($row['end_time']) {
                    $startTime = new \DateTime($row['start_time']);
                    $endTime = new \DateTime($row['end_time']);
                    $interval = $startTime->diff($endTime);
                    
                    // Formatar duração
                    $duration = '';
                    if ($interval->h > 0) {
                        $duration .= $interval->h . 'h ';
                    }
                    $duration .= $interval->i . 'min';
                    
                    $row['duration'] = $duration;
                    $row['duration_minutes'] = ($interval->h * 60) + $interval->i;
                } else {
                    $row['duration'] = 'Em andamento';
                    $row['duration_minutes'] = null;
                }
                
                $history[] = $row;
            }
            
            return $history;
        } catch (\Exception $e) {
            error_log('Erro ao obter histórico de ocupação: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtém estatísticas de ocupação da mesa
     */
    public function getTableStatistics($tableId, $tenantId, $days = 30) {
        try {
            // Definir data de início para o período de estatísticas
            $startDate = date('Y-m-d', strtotime("-{$days} days"));
            
            $query = "SELECT 
                        COUNT(*) as total_occupancies,
                        AVG(TIMESTAMPDIFF(MINUTE, start_time, IFNULL(end_time, NOW()))) as avg_duration_minutes,
                        AVG(total_spent) as avg_spent,
                        MAX(total_spent) as max_spent,
                        SUM(total_spent) as total_spent,
                        AVG(number_of_customers) as avg_customers
                     FROM table_occupancy_history 
                     WHERE table_id = ? AND tenant_id = ? AND start_time >= ? AND end_time IS NOT NULL";
            
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("iis", $tableId, $tenantId, $startDate);
            $stmt->execute();
            $stats = $stmt->get_result()->fetch_assoc();
            
            // Formatar estatísticas
            if ($stats['total_occupancies'] > 0) {
                $stats['avg_duration_formatted'] = $this->formatDuration($stats['avg_duration_minutes']);
                $stats['avg_spent'] = round($stats['avg_spent'], 2);
                $stats['total_spent'] = round($stats['total_spent'], 2);
                $stats['avg_customers'] = round($stats['avg_customers'], 1);
            } else {
                $stats = [
                    'total_occupancies' => 0,
                    'avg_duration_minutes' => 0,
                    'avg_duration_formatted' => '0min',
                    'avg_spent' => 0,
                    'max_spent' => 0,
                    'total_spent' => 0,
                    'avg_customers' => 0
                ];
            }
            
            return $stats;
        } catch (\Exception $e) {
            error_log('Erro ao obter estatísticas da mesa: ' . $e->getMessage());
            return [
                'total_occupancies' => 0,
                'avg_duration_minutes' => 0,
                'avg_duration_formatted' => '0min',
                'avg_spent' => 0,
                'max_spent' => 0,
                'total_spent' => 0,
                'avg_customers' => 0
            ];
        }
    }
    
    /**
     * Formata a duração em minutos para um formato legível
     */
    private function formatDuration($minutes) {
        $minutes = (int)$minutes;
        
        if ($minutes < 60) {
            return $minutes . 'min';
        }
        
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        
        return $hours . 'h ' . ($mins > 0 ? $mins . 'min' : '');
    }
}