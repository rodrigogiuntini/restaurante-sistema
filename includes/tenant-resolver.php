// includes/tenant-resolver.php
<?php
/**
 * Resolvedor de tenant
 */

/**
 * Resolve o tenant atual com base no domínio ou subdomínio
 *
 * @return array|null Dados do tenant ou null se não encontrado
 */
function resolveTenant() {
    global $db;

    // Obtém o host atual
    $host = $_SERVER['HTTP_HOST'] ?? '';
    
    // Verifica se é um domínio personalizado
    $query = "SELECT * FROM tenants WHERE domain = :domain AND active = 1 LIMIT 1";
    $params = [':domain' => $host];
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tenant) {
            return $tenant;
        }
    } catch (PDOException $e) {
        error_log("Erro ao resolver tenant por domínio: " . $e->getMessage());
    }
    
    // Verifica se é um subdomínio
    $parts = explode('.', $host);
    if (count($parts) > 2) {
        $subdomain = $parts[0];
        
        $query = "SELECT * FROM tenants WHERE slug = :slug AND active = 1 LIMIT 1";
        $params = [':slug' => $subdomain];
        
        try {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($tenant) {
                return $tenant;
            }
        } catch (PDOException $e) {
            error_log("Erro ao resolver tenant por subdomínio: " . $e->getMessage());
        }
    }
    
    // Se for a aplicação central, não é um tenant
    return null;
}

/**
 * Define o tenant atual na sessão
 *
 * @param array $tenant Dados do tenant
 * @return void
 */
function setCurrentTenant($tenant) {
    $_SESSION['current_tenant'] = $tenant;
}

/**
 * Obtém o tenant atual da sessão
 *
 * @return array|null Dados do tenant ou null se não definido
 */
function getCurrentTenant() {
    return $_SESSION['current_tenant'] ?? null;
}

/**
 * Obtém o ID do tenant atual
 *
 * @return int|null ID do tenant ou null se não definido
 */
function getCurrentTenantId() {
    $tenant = getCurrentTenant();
    return $tenant['id'] ?? null;
}

/**
 * Inicializa o tenant com base na URL
 *
 * @return void
 */
function initializeTenant() {
    // Resolve o tenant
    $tenant = resolveTenant();
    
    if ($tenant) {
        // Define o tenant atual
        setCurrentTenant($tenant);
        
        // Define constantes específicas do tenant
        defineTenantConstants($tenant);
        
        // Carrega configurações específicas do tenant
        loadTenantConfigurations($tenant);
    }
}

/**
 * Define constantes específicas do tenant
 *
 * @param array $tenant Dados do tenant
 * @return void
 */
function defineTenantConstants($tenant) {
    define('TENANT_ID', $tenant['id']);
    define('TENANT_NAME', $tenant['name']);
    define('TENANT_SLUG', $tenant['slug']);
    define('TENANT_TYPE', $tenant['restaurant_type']);
    define('TENANT_LOGO', $tenant['logo']);
    define('TENANT_THEME_COLOR', $tenant['theme_color']);
    define('TENANT_DOMAIN', $tenant['domain']);
}

/**
 * Carrega configurações específicas do tenant
 *
 * @param array $tenant Dados do tenant
 * @return void
 */
function loadTenantConfigurations($tenant) {
    global $db;
    
    // Carrega configurações do restaurante
    $query = "SELECT * FROM restaurant_configurations WHERE tenant_id = :tenant_id LIMIT 1";
    $params = [':tenant_id' => $tenant['id']];
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($config) {
            $_SESSION['tenant_config'] = $config;
        }
    } catch (PDOException $e) {
        error_log("Erro ao carregar configurações do tenant: " . $e->getMessage());
    }
    
    // Adiciona outras configurações específicas do tenant conforme necessário
}

// controllers/TenantController.php
<?php
/**
 * Controlador de tenant
 */
require_once __DIR__ . '/../includes/tenant-resolver.php';
require_once __DIR__ . '/../models/Tenant.php';
require_once __DIR__ . '/../models/Restaurant.php';
require_once __DIR__ . '/../models/Account.php';

class TenantController {
    private $tenantModel;
    private $restaurantModel;
    private $accountModel;
    
    public function __construct() {
        $this->tenantModel = new Tenant();
        $this->restaurantModel = new Restaurant();
        $this->accountModel = new Account();
    }
    
    /**
     * Cria um novo tenant
     *
     * @return array Resultado da criação com status e mensagem
     */
    public function create() {
        // Captura os dados do formulário
        $name = $_POST['name'] ?? '';
        $companyName = $_POST['company_name'] ?? '';
        $cnpj = $_POST['cnpj'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $restaurantType = $_POST['restaurant_type'] ?? '';
        
        // Validação básica
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Nome do restaurante é obrigatório';
        }
        
        if (empty($companyName)) {
            $errors[] = 'Razão social é obrigatória';
        }
        
        if (empty($cnpj) || !$this->validateCNPJ($cnpj)) {
            $errors[] = 'CNPJ inválido';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }
        
        if (empty($phone)) {
            $errors[] = 'Telefone é obrigatório';
        }
        
        if (empty($restaurantType)) {
            $errors[] = 'Tipo de restaurante é obrigatório';
        }
        
        if (!empty($errors)) {
            return [
                'status' => 'error',
                'message' => 'Corrija os erros abaixo',
                'errors' => $errors
            ];
        }
        
        // Verifica se o CNPJ já existe
        if ($this->tenantModel->cnpjExists($cnpj)) {
            return [
                'status' => 'error',
                'message' => 'CNPJ já cadastrado'
            ];
        }
        
        // Verifica se o email já existe
        if ($this->tenantModel->emailExists($email)) {
            return [
                'status' => 'error',
                'message' => 'Email já cadastrado'
            ];
        }
        
        // Gera o slug a partir do nome
        $slug = $this->generateSlug($name);
        
        // Cria o tenant
        $tenantData = [
            'name' => $name,
            'slug' => $slug,
            'restaurant_type' => $restaurantType,
            'company_name' => $companyName,
            'cnpj' => $cnpj,
            'email' => $email,
            'phone' => $phone,
            'address' => $_POST['address'] ?? '',
            'city' => $_POST['city'] ?? '',
            'state' => $_POST['state'] ?? '',
            'zip_code' => $_POST['zip_code'] ?? '',
            'theme_color' => $_POST['theme_color'] ?? '#3498db',
            'active' => 1
        ];
        
        $tenantId = $this->tenantModel->create($tenantData);
        
        if (!$tenantId) {
            return [
                'status' => 'error',
                'message' => 'Erro ao criar restaurante. Tente novamente.'
            ];
        }
        
        // Cria a assinatura associada ao tenant
        $planId = $_POST['plan_id'] ?? 1; // Plano básico como padrão
        
        $subscriptionData = [
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'status' => 'trial',
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+15 days'))
        ];
        
        $subscriptionId = $this->accountModel->createSubscription($subscriptionData);
        
        if (!$subscriptionId) {
            // Algo deu errado, mas o tenant já foi criado
            // Em produção, seria importante reverter a criação do tenant se a assinatura falhar
            return [
                'status' => 'warning',
                'message' => 'Restaurante criado, mas houve um problema com a assinatura. Entre em contato com o suporte.'
            ];
        }
        
        // Cria as configurações iniciais do restaurante
        $this->createInitialConfigurations($tenantId, $restaurantType);
        
        // Cria o usuário admin inicial
        $adminUserId = $this->createInitialAdminUser($tenantId, $name, $email);
        
        return [
            'status' => 'success',
            'message' => 'Restaurante criado com sucesso!',
            'tenant_id' => $tenantId,
            'admin_user_id' => $adminUserId,
            'redirect' => '/admin/tenants/' . $tenantId . '/setup'
        ];
    }
    
    /**
     * Atualiza um tenant existente
     *
     * @param int $tenantId ID do tenant
     * @return array Resultado da atualização com status e mensagem
     */
    public function update($tenantId) {
        // Primeiro, verifica se o tenant existe
        $tenant = $this->tenantModel->findById($tenantId);
        
        if (!$tenant) {
            return [
                'status' => 'error',
                'message' => 'Restaurante não encontrado'
            ];
        }
        
        // Captura os dados do formulário
        $name = $_POST['name'] ?? '';
        $companyName = $_POST['company_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        // Validação básica
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Nome do restaurante é obrigatório';
        }
        
        if (empty($companyName)) {
            $errors[] = 'Razão social é obrigatória';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido';
        }
        
        if (empty($phone)) {
            $errors[] = 'Telefone é obrigatório';
        }
        
        if (!empty($errors)) {
            return [
                'status' => 'error',
                'message' => 'Corrija os erros abaixo',
                'errors' => $errors
            ];
        }
        
        // Verifica se o email foi alterado e já existe
        if ($email !== $tenant['email'] && $this->tenantModel->emailExists($email)) {
            return [
                'status' => 'error',
                'message' => 'Email já cadastrado'
            ];
        }
        
        // Verifica se o CNPJ foi alterado
        $cnpj = $_POST['cnpj'] ?? '';
        if (!empty($cnpj) && $cnpj !== $tenant['cnpj']) {
            if (!$this->validateCNPJ($cnpj)) {
                return [
                    'status' => 'error',
                    'message' => 'CNPJ inválido'
                ];
            }
            
            if ($this->tenantModel->cnpjExists($cnpj)) {
                return [
                    'status' => 'error',
                    'message' => 'CNPJ já cadastrado'
                ];
            }
        } else {
            // Mantém o CNPJ original
            $cnpj = $tenant['cnpj'];
        }
        
        // Prepara dados para atualização
        $tenantData = [
            'id' => $tenantId,
            'name' => $name,
            'company_name' => $companyName,
            'cnpj' => $cnpj,
            'email' => $email,
            'phone' => $phone,
            'address' => $_POST['address'] ?? $tenant['address'],
            'city' => $_POST['city'] ?? $tenant['city'],
            'state' => $_POST['state'] ?? $tenant['state'],
            'zip_code' => $_POST['zip_code'] ?? $tenant['zip_code'],
            'theme_color' => $_POST['theme_color'] ?? $tenant['theme_color'],
            'domain' => $_POST['domain'] ?? $tenant['domain']
        ];
        
        // Atualiza o slug apenas se o nome mudou
        if ($name !== $tenant['name']) {
            $tenantData['slug'] = $this->generateSlug($name);
        }
        
        // Atualiza o tenant
        $success = $this->tenantModel->update($tenantData);
        
        if (!$success) {
            return [
                'status' => 'error',
                'message' => 'Erro ao atualizar restaurante. Tente novamente.'
            ];
        }
        
        return [
            'status' => 'success',
            'message' => 'Restaurante atualizado com sucesso!'
        ];
    }
    
    /**
     * Ativa ou desativa um tenant
     *
     * @param int $tenantId ID do tenant
     * @param bool $active Status de ativação
     * @return array Resultado da operação com status e mensagem
     */
    public function toggleActive($tenantId, $active) {
        // Verifica se o tenant existe
        $tenant = $this->tenantModel->findById($tenantId);
        
        if (!$tenant) {
            return [
                'status' => 'error',
                'message' => 'Restaurante não encontrado'
            ];
        }
        
        // Atualiza o status
        $success = $this->tenantModel->updateActive($tenantId, $active);
        
        if (!$success) {
            return [
                'status' => 'error',
                'message' => 'Erro ao alterar status do restaurante. Tente novamente.'
            ];
        }
        
        $statusText = $active ? 'ativado' : 'desativado';
        
        return [
            'status' => 'success',
            'message' => 'Restaurante ' . $statusText . ' com sucesso!'
        ];
    }
    
    /**
     * Exclui um tenant
     *
     * @param int $tenantId ID do tenant
     * @return array Resultado da exclusão com status e mensagem
     */
    public function delete($tenantId) {
        // Verifica se o tenant existe
        $tenant = $this->tenantModel->findById($tenantId);
        
        if (!$tenant) {
            return [
                'status' => 'error',
                'message' => 'Restaurante não encontrado'
            ];
        }
        
        // Em produção, seria importante adicionar mais validações
        // e possivelmente mover o tenant para um estado "pendente de exclusão"
        // antes de excluir definitivamente
        
        // Exclui o tenant
        $success = $this->tenantModel->delete($tenantId);
        
        if (!$success) {
            return [
                'status' => 'error',
                'message' => 'Erro ao excluir restaurante. Tente novamente.'
            ];
        }
        
        return [
            'status' => 'success',
            'message' => 'Restaurante excluído com sucesso!'
        ];
    }
    
    /**
     * Obtém lista de tenants
     *
     * @param array $filters Filtros para a consulta
     * @param array $pagination Parâmetros de paginação
     * @return array Lista de tenants e metadados
     */
    public function list($filters = [], $pagination = ['page' => 1, 'perPage' => 20]) {
        // Obtém a lista de tenants
        $tenants = $this->tenantModel->findAll($filters, $pagination);
        
        // Obtém o total para paginação
        $total = $this->tenantModel->count($filters);
        
        return [
            'tenants' => $tenants,
            'total' => $total,
            'page' => $pagination['page'],
            'perPage' => $pagination['perPage'],
            'totalPages' => ceil($total / $pagination['perPage'])
        ];
    }
    
    /**
     * Valida um CNPJ
     *
     * @param string $cnpj CNPJ para validar
     * @return bool Verdadeiro se válido, falso caso contrário
     */
    private function validateCNPJ($cnpj) {
        // Remove caracteres não numéricos
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        // Verifica o tamanho
        if (strlen($cnpj) != 14) {
            return false;
        }
        
        // Verifica se todos os dígitos são iguais
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }
        
        // Valida dígitos verificadores
        for ($t = 12; $t < 14; $t++) {
            $d = 0;
            $c = 0;
            
            for ($m = $t - 7; $m >= 0; $m--) {
                $c = $cnpj[$m];
                $d += $c * (($t - $m) + 1);
            }
            
            for ($m = $t; $m >= $t - 6; $m--) {
                $c = $cnpj[$m];
                $d += $c * (($t - $m) + 1);
            }
            
            $d = ((10 * $d) % 11) % 10;
            
            if ($cnpj[$t + 1] != $d) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Gera um slug a partir do nome
     *
     * @param string $name Nome para gerar o slug
     * @return string Slug gerado
     */
    private function generateSlug($name) {
        // Converte para minúsculas e remove acentos
        $slug = strtolower($name);
        $slug = preg_replace('/[áàãâä]/ui', 'a', $slug);
        $slug = preg_replace('/[éèêë]/ui', 'e', $slug);
        $slug = preg_replace('/[íìîï]/ui', 'i', $slug);
        $slug = preg_replace('/[óòõôö]/ui', 'o', $slug);
        $slug = preg_replace('/[úùûü]/ui', 'u', $slug);
        $slug = preg_replace('/[ç]/ui', 'c', $slug);
        
        // Remove caracteres especiais
        $slug = preg_replace('/[^a-z0-9]/', '-', $slug);
        
        // Remove hífens múltiplos
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Remove hífens no início e fim
        $slug = trim($slug, '-');
        
        // Verifica se o slug já existe e adiciona um número se necessário
        $baseSlug = $slug;
        $counter = 1;
        
        while ($this->tenantModel->slugExists($slug)) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Cria as configurações iniciais do restaurante
     *
     * @param int $tenantId ID do tenant
     * @param string $restaurantType Tipo de restaurante
     * @return bool Sucesso da operação
     */
    private function createInitialConfigurations($tenantId, $restaurantType) {
        // Cria configurações padrão com base no tipo de restaurante
        $config = [
            'tenant_id' => $tenantId,
            'opening_hours' => json_encode([
                'monday' => ['09:00-22:00'],
                'tuesday' => ['09:00-22:00'],
                'wednesday' => ['09:00-22:00'],
                'thursday' => ['09:00-22:00'],
                'friday' => ['09:00-23:00'],
                'saturday' => ['09:00-23:00'],
                'sunday' => ['11:00-22:00']
            ]),
            'operating_days' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
            'allow_reservations' => in_array($restaurantType, ['alacarte', 'pizzaria', 'rodizio', 'bar']),
            'allow_qrcode_orders' => true,
            'allow_group_orders' => true
        ];
        
        // Adiciona configurações específicas por tipo
        switch ($restaurantType) {
            case 'fastfood':
                $config['default_order_type'] = 'pickup';
                $config['estimated_preparation_time'] = 15;
                break;
            case 'delivery':
                $config['default_order_type'] = 'delivery';
                $config['delivery_radius'] = 5;
                $config['delivery_fee'] = 5.00;
                $config['min_delivery_value'] = 20.00;
                break;
            case 'rodizio':
                $config['service_fee'] = 10.00;
                break;
            case 'selfservice':
                $config['default_table_capacity'] = 2;
                break;
        }
        
        // Cria a configuração
        return $this->restaurantModel->createConfiguration($config);
    }
    
    /**
     * Cria o usuário admin inicial para o tenant
     *
     * @param int $tenantId ID do tenant
     * @param string $restaurantName Nome do restaurante
     * @param string $email Email do admin
     * @return int ID do usuário criado
     */
    private function createInitialAdminUser($tenantId, $restaurantName, $email) {
        // Gera senha aleatória
        $password = $this->generateRandomPassword();
        
        // Cria o usuário admin
        $userModel = new User();
        $adminData = [
            'tenant_id' => $tenantId,
            'username' => 'admin' . $tenantId,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'name' => 'Administrador ' . $restaurantName,
            'role' => 'admin',
            'active' => 1,
            'email_verified' => 1
        ];
        
        $userId = $userModel->create($adminData);
        
        // Envia email com os dados de acesso
        // TODO: Implementar envio de email real
        error_log("Dados de acesso para o tenant $tenantId:");
        error_log("Usuário: admin$tenantId");
        error_log("Senha: $password");
        
        return $userId;
    }
    
    /**
     * Gera uma senha aleatória
     *
     * @param int $length Tamanho da senha
     * @return string Senha gerada
     */
    private function generateRandomPassword($length = 10) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
        $password = '';
        $max = strlen($chars) - 1;
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }
        
        return $password;
    }
}