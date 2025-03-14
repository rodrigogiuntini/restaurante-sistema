<?php
/**
 * Resolvedor de Tenant para Sistema Multi-tenant
 */

class TenantResolver {
    private $config;
    private $tenantCache = [];
    
    /**
     * Construtor
     */
    public function __construct() {
        $this->config = require APP_PATH . '/config/tenant.php';
    }
    
    /**
     * Resolve o tenant atual baseado na configuração
     */
    public function resolve() {
        $method = $this->config['identify_by'];
        $tenantId = null;
        
        // Verificar sessão primeiro
        if (isset($_SESSION['tenant_id'])) {
            return $_SESSION['tenant_id'];
        }
        
        // Resolver por método configurado
        switch ($method) {
            case 'domain':
                $tenantId = $this->resolveByDomain();
                break;
            case 'subdomain':
                $tenantId = $this->resolveBySubdomain();
                break;case 'path':
                    $tenantId = $this->resolveByPath();
                    break;
            }
            
            if (empty($tenantId)) {
                $tenantId = $this->config['default_tenant'];
            }
            
            return $tenantId;
        }
        
        /**
         * Resolve tenant pelo domínio completo
         */
        private function resolveByDomain() {
            $domain = $_SERVER['HTTP_HOST'] ?? '';
            
            // Verificar no cache primeiro
            if (isset($this->tenantCache['domain'][$domain])) {
                return $this->tenantCache['domain'][$domain];
            }
            
            // Verificar no mapeamento estático
            if (isset($this->config['domain_mapping'][$domain])) {
                $tenantId = $this->config['domain_mapping'][$domain];
                $this->tenantCache['domain'][$domain] = $tenantId;
                return $tenantId;
            }
            
            // Verificar na base de dados
            $pdo = db();
            $stmt = $pdo->prepare("SELECT id, slug FROM tenants WHERE domain = ? AND active = 1");
            $stmt->execute([$domain]);
            $tenant = $stmt->fetch();
            
            if ($tenant) {
                $this->tenantCache['domain'][$domain] = $tenant['slug'];
                return $tenant['slug'];
            }
            
            return null;
        }
        
        /**
         * Resolve tenant pelo subdomínio
         */
        private function resolveBySubdomain() {
            $host = $_SERVER['HTTP_HOST'] ?? '';
            
            // Verificar no cache primeiro
            if (isset($this->tenantCache['subdomain'][$host])) {
                return $this->tenantCache['subdomain'][$host];
            }
            
            $parts = explode('.', $host);
            
            if (count($parts) >= 3) {
                $subdomain = $parts[0];
                
                // Verificar na base de dados
                $pdo = db();
                $stmt = $pdo->prepare("SELECT id, slug FROM tenants WHERE slug = ? AND active = 1");
                $stmt->execute([$subdomain]);
                $tenant = $stmt->fetch();
                
                if ($tenant) {
                    $this->tenantCache['subdomain'][$host] = $tenant['slug'];
                    return $tenant['slug'];
                }
            }
            
            return null;
        }
        
        /**
         * Resolve tenant pelo caminho da URL
         */
        private function resolveByPath() {
            $path = $_SERVER['REQUEST_URI'] ?? '';
            
            // Verificar no cache primeiro
            if (isset($this->tenantCache['path'][$path])) {
                return $this->tenantCache['path'][$path];
            }
            
            $segments = explode('/', trim($path, '/'));
            
            if (!empty($segments[0]) && !in_array("/{$segments[0]}", $this->config['excluded_paths'])) {
                // Verificar na base de dados
                $pdo = db();
                $stmt = $pdo->prepare("SELECT id, slug FROM tenants WHERE slug = ? AND active = 1");
                $stmt->execute([$segments[0]]);
                $tenant = $stmt->fetch();
                
                if ($tenant) {
                    $this->tenantCache['path'][$path] = $tenant['slug'];
                    return $tenant['slug'];
                }
            }
            
            return null;
        }
        
        /**
         * Verifica se o tenant existe
         */
        public function tenantExists($tenantId) {
            // Verificar no cache primeiro
            if (isset($this->tenantCache['exists'][$tenantId])) {
                return $this->tenantCache['exists'][$tenantId];
            }
            
            $pdo = db();
            $stmt = $pdo->prepare("SELECT id FROM {$this->config['tenant_table']} WHERE slug = ? AND active = 1");
            $stmt->execute([$tenantId]);
            
            $exists = $stmt->fetch() !== false;
            $this->tenantCache['exists'][$tenantId] = $exists;
            
            return $exists;
        }
        
        /**
         * Configura o tenant na sessão
         */
        public function setTenantSession($tenantId) {
            $_SESSION['tenant_id'] = $tenantId;
        }
        
        /**
         * Limpa o cache do resolvedor
         */
        public function clearCache() {
            $this->tenantCache = [];
        }
        
        /**
         * Recupera os dados completos do tenant atual
         */
        public function getCurrentTenantData() {
            $tenantId = $this->resolve();
            
            // Verificar no cache primeiro
            if (isset($this->tenantCache['data'][$tenantId])) {
                return $this->tenantCache['data'][$tenantId];
            }
            
            $pdo = db();
            $stmt = $pdo->prepare("SELECT * FROM {$this->config['tenant_table']} WHERE slug = ? AND active = 1");
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch();
            
            if ($tenant) {
                $this->tenantCache['data'][$tenantId] = $tenant;
                return $tenant;
            }
            
            return null;
        }
    }
    
    // ARQUIVO COMPLETO E CORRETO