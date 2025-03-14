<?php
/**
 * Verificador de recursos disponíveis por plano
 * Controla os limites e funcionalidades disponíveis para cada assinatura
 */

class FeatureChecker {
    private $tenantId;
    private $subscription;
    private $plan;
    private $features;
    private $limits;
    
    /**
     * Construtor
     */
    public function __construct($tenantId = null) {
        $this->tenantId = $tenantId ?: getCurrentTenantId();
        $this->loadSubscriptionData();
    }
    
    /**
     * Carrega os dados da assinatura do tenant
     */
    private function loadSubscriptionData() {
        $pdo = db();
        
        // Carregar assinatura ativa
        $stmt = $pdo->prepare("
            SELECT s.*, p.name as plan_name, p.code as plan_code, 
                   p.features, p.limits
            FROM subscriptions s 
            JOIN plans p ON s.plan_id = p.id 
            WHERE s.tenant_id = ? AND s.status IN ('active', 'trial')
        ");
        $stmt->execute([$this->tenantId]);
        $this->subscription = $stmt->fetch();
        
        if (!$this->subscription) {
            // Se não encontrar assinatura ativa, usar plano padrão (basic)
            $stmt = $pdo->prepare("SELECT * FROM plans WHERE code = ?");
            $stmt->execute([DEFAULT_PLAN]);
            $this->plan = $stmt->fetch();
            
            // Decodificar features e limites
            $this->features = json_decode($this->plan['features'] ?? '[]', true) ?: [];
            $this->limits = json_decode($this->plan['limits'] ?? '{}', true) ?: [];
        } else {
            // Decodificar features e limites da assinatura
            $this->features = json_decode($this->subscription['features'] ?? '[]', true) ?: [];
            $this->limits = json_decode($this->subscription['limits'] ?? '{}', true) ?: [];
        }
    }
    
    /**
     * Verifica se uma feature está disponível no plano atual
     */
    public function hasFeature($featureName) {
        return in_array($featureName, $this->features);
    }
    
    /**
     * Verifica se o tenant atingiu o limite para um recurso específico
     */
    public function hasReachedLimit($resource, $currentValue = null) {
        // Verificar se o recurso tem limite definido
        if (!isset($this->limits[$resource])) {
            return false; // Se não tem limite definido, não atingiu o limite
        }
        
        // Se o limite for -1, significa ilimitado
        if ($this->limits[$resource] === -1) {
            return false;
        }
        
        // Se o valor atual já foi fornecido, usar diretamente
        if ($currentValue !== null) {
            return $currentValue >= $this->limits[$resource];
        }
        
        // Caso contrário, consultar banco de dados para obter o valor atual
        $pdo = db();
        
        switch ($resource) {
            case 'max_tables':
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tables WHERE tenant_id = ?");
                $stmt->execute([$this->tenantId]);
                $result = $stmt->fetch();
                return $result['count'] >= $this->limits[$resource];
                
            case 'max_users':
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE tenant_id = ?");
                $stmt->execute([$this->tenantId]);
                $result = $stmt->fetch();
                return $result['count'] >= $this->limits[$resource];
                
            case 'max_menu_items':
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM menu_items WHERE tenant_id = ?");
                $stmt->execute([$this->tenantId]);
                $result = $stmt->fetch();
                return $result['count'] >= $this->limits[$resource];
                
            case 'max_monthly_orders':
                // Contar pedidos do mês atual
                $startOfMonth = date('Y-m-01 00:00:00');
                $endOfMonth = date('Y-m-t 23:59:59');
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM orders 
                    WHERE tenant_id = ? AND started_at BETWEEN ? AND ?
                ");
                $stmt->execute([$this->tenantId, $startOfMonth, $endOfMonth]);
                $result = $stmt->fetch();
                return $result['count'] >= $this->limits[$resource];
                
            default:
                return false;
        }
    }
    
    /**
     * Retorna o limite para um recurso específico
     */
    public function getLimit($resource) {
        return $this->limits[$resource] ?? null;
    }
    
    /**
     * Retorna o uso atual de um recurso específico
     */
    public function getCurrentUsage($resource) {
        $pdo = db();
        
        switch ($resource) {
            case 'max_tables':
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tables WHERE tenant_id = ?");
                $stmt->execute([$this->tenantId]);
                $result = $stmt->fetch();
                return $result['count'];
                
            case 'max_users':
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE tenant_id = ?");
                $stmt->execute([$this->tenantId]);
                $result = $stmt->fetch();
                return $result['count'];
                
            case 'max_menu_items':
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM menu_items WHERE tenant_id = ?");
                $stmt->execute([$this->tenantId]);
                $result = $stmt->fetch();
                return $result['count'];
                
            case 'max_monthly_orders':
                // Contar pedidos do mês atual
                $startOfMonth = date('Y-m-01 00:00:00');
                $endOfMonth = date('Y-m-t 23:59:59');
                
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count 
                    FROM orders 
                    WHERE tenant_id = ? AND started_at BETWEEN ? AND ?
                ");
                $stmt->execute([$this->tenantId, $startOfMonth, $endOfMonth]);
                $result = $stmt->fetch();
                return $result['count'];
                
            default:
                return 0;
        }
    }
    
    /**
     * Registra o uso de um recurso para o tenant
     */
    public function recordResourceUsage($resourceType, $count = 1) {
        $pdo = db();
        
        $year = date('Y');
        $month = date('n');
        $day = date('j');
        
        // Verificar se já existe registro para hoje
        $stmt = $pdo->prepare("
            SELECT id, resource_count 
            FROM resource_usage 
            WHERE tenant_id = ? AND resource_type = ? AND year = ? AND month = ? AND day = ?
        ");
        $stmt->execute([$this->tenantId, $resourceType, $year, $month, $day]);
        $existingRecord = $stmt->fetch();
        
        if ($existingRecord) {
            // Atualizar registro existente
            $newCount = $existingRecord['resource_count'] + $count;
            $stmt = $pdo->prepare("
                UPDATE resource_usage 
                SET resource_count = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$newCount, $existingRecord['id']]);
        } else {
            // Criar novo registro
            $stmt = $pdo->prepare("
                INSERT INTO resource_usage 
                (tenant_id, resource_type, resource_count, year, month, day) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$this->tenantId, $resourceType, $count, $year, $month, $day]);
        }
        
        return true;
    }
    
    /**
     * Verifica o status da assinatura
     */
    public function getSubscriptionStatus() {
        if (!$this->subscription) {
            return 'none';
        }
        
        return $this->subscription['status'];
    }
    
    /**
     * Verifica se a assinatura está ativa (incluindo período de teste)
     */
    public function isSubscriptionActive() {
        if (!$this->subscription) {
            return false;
        }
        
        return in_array($this->subscription['status'], ['active', 'trial']);
    }
    
    /**
     * Retorna dados da assinatura atual
     */
    public function getSubscriptionData() {
        return $this->subscription;
    }
}

// ARQUIVO COMPLETO E CORRETO