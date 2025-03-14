<?php
/**
 * Controlador para gerenciamento de mesas
 * 
 * Gerencia operações de CRUD para mesas, incluindo mapa visual,
 * status e histórico de ocupação.
 * 
 * Status: 75% Completo
 * Pendente:
 * - Implementar histórico detalhado de ocupação
 * - Adicionar estatísticas de mesas
 * - Melhorar gerenciamento de status
 */

require_once __DIR__ . '/../models/Table.php';
require_once __DIR__ . '/../models/RestaurantArea.php';
require_once __DIR__ . '/../services/QRCodeService.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/feature-checker.php';

class TableController {
    private $tableModel;
    private $areaModel;
    private $qrCodeService;
    
    public function __construct() {
        $this->tableModel = new Table();
        $this->areaModel = new RestaurantArea();
        $this->qrCodeService = new QRCodeService();
    }
    
    /**
     * Exibe a página de mapa de mesas
     */
    public function map() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'table_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter lista de áreas do restaurante
        $areas = $this->areaModel->getAreasByTenantId($tenantId);
        
        // Obter todas as mesas do restaurante
        $tables = $this->tableModel->getTablesByTenantId($tenantId);
        
        // Agrupar mesas por área
        $tablesByArea = [];
        foreach ($tables as $table) {
            $areaId = $table['area_id'] ? $table['area_id'] : 0;
            if (!isset($tablesByArea[$areaId])) {
                $tablesByArea[$areaId] = [];
            }
            $tablesByArea[$areaId][] = $table;
        }
        
        // Carregar a view
        require_once __DIR__ . '/../views/tables/map.php';
    }
    
    /**
     * Exibe a página de listagem de mesas
     */
    public function list() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'table_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter lista de áreas do restaurante
        $areas = $this->areaModel->getAreasByTenantId($tenantId);
        
        // Obter todas as mesas do restaurante
        $tables = $this->tableModel->getTablesByTenantId($tenantId);
        
        // Criar mapeamento de áreas para facilitar acesso
        $areasMap = [];
        foreach ($areas as $area) {
            $areasMap[$area['id']] = $area['name'];
        }
        
        // Carregar a view
        require_once __DIR__ . '/../views/tables/list.php';
    }
    
    /**
     * Exibe o formulário para adicionar uma nova mesa
     */
    public function add() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'table_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Verificar limite de mesas
        $tableCount = $this->tableModel->getTableCountByTenantId($tenantId);
        $tableLimit = getFeatureLimit($tenantId, 'max_tables');
        
        if ($tableLimit != -1 && $tableCount >= $tableLimit) {
            $_SESSION['error_message'] = 'Você atingiu o limite de mesas do seu plano.';
            header('Location: /tables/list');
            exit;
        }
        
        // Obter lista de áreas do restaurante
        $areas = $this->areaModel->getAreasByTenantId($tenantId);
        
        // Carregar a view
        require_once __DIR__ . '/../views/tables/add.php';
    }
    
    /**
     * Processa a adição de uma nova mesa
     */
    public function store() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se a requisição é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Método inválido.';
            header('Location: /tables/list');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'table_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Verificar limite de mesas
        $tableCount = $this->tableModel->getTableCountByTenantId($tenantId);
        $tableLimit = getFeatureLimit($tenantId, 'max_tables');
        
        if ($tableLimit != -1 && $tableCount >= $tableLimit) {
            $_SESSION['error_message'] = 'Você atingiu o limite de mesas do seu plano.';
            header('Location: /tables/list');
            exit;
        }
        
        // Validar dados recebidos
        $number = isset($_POST['number']) ? trim($_POST['number']) : '';
        $name = isset($_POST['name']) ? trim($_POST['name']) : null;
        $capacity = isset($_POST['capacity']) ? intval($_POST['capacity']) : 4;
        $areaId = isset($_POST['area_id']) && $_POST['area_id'] !== '' ? intval($_POST['area_id']) : null;
        $positionX = isset($_POST['position_x']) ? intval($_POST['position_x']) : 0;
        $positionY = isset($_POST['position_y']) ? intval($_POST['position_y']) : 0;
        
        if (empty($number)) {
            $_SESSION['error_message'] = 'O número da mesa é obrigatório.';
            header('Location: /tables/add');
            exit;
        }
        
        // Verificar se o número da mesa já existe
        if ($this->tableModel->checkTableNumberExists($tenantId, $number)) {
            $_SESSION['error_message'] = 'Este número de mesa já está em uso.';
            header('Location: /tables/add');
            exit;
        }
        
        // Criar nova mesa
        $result = $this->tableModel->createTable([
            'tenant_id' => $tenantId,
            'area_id' => $areaId,
            'number' => $number,
            'name' => $name,
            'capacity' => $capacity,
            'position_x' => $positionX,
            'position_y' => $positionY,
            'status' => 'available'
        ]);
        
        if (!$result) {
            $_SESSION['error_message'] = 'Erro ao criar mesa.';
            header('Location: /tables/add');
            exit;
        }
        
        $tableId = $result;
        
        // Gerar QR Code para a mesa automaticamente?
        $generateQRCode = isset($_POST['generate_qrcode']) && $_POST['generate_qrcode'] == '1';
        
        if ($generateQRCode) {
            $tableName = $name ? $name : 'Mesa ' . $number;
            $qrCodeResult = $this->qrCodeService->generateTableQRCode($tenantId, $tableId, $tableName);
            
            if (!$qrCodeResult['success']) {
                $_SESSION['warning_message'] = 'Mesa criada, mas houve um erro ao gerar o QR Code: ' . $qrCodeResult['error'];
                header('Location: /tables/list');
                exit;
            }
        }
        
        // Mesa criada com sucesso
        $_SESSION['success_message'] = 'Mesa criada com sucesso!';
        header('Location: /tables/list');
        exit;
    }
    
    /**
     * Exibe o formulário para editar uma mesa
     */
    public function edit($tableId = null) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        if (!$tableId) {
            $_SESSION['error_message'] = 'Mesa não especificada.';
            header('Location: /tables/list');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'table_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter dados da mesa
        $table = $this->tableModel->getTableById($tableId, $tenantId);
        
        if (!$table) {
            $_SESSION['error_message'] = 'Mesa não encontrada.';
            header('Location: /tables/list');
            exit;
        }
        
        // Obter lista de áreas do restaurante
        $areas = $this->areaModel->getAreasByTenantId($tenantId);
        
        // Carregar a view
        require_once __DIR__ . '/../views/tables/edit.php';
    }
    
    /**
     * Processa a atualização de uma mesa
     */
    public function update($tableId = null) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se a requisição é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Método inválido.';
            header('Location: /tables/list');
            exit;
        }
        
        if (!$tableId) {
            $_SESSION['error_message'] = 'Mesa não especificada.';
            header('Location: /tables/list');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'table_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter dados da mesa
        $table = $this->tableModel->getTableById($tableId, $tenantId);
        
        if (!$table) {
            $_SESSION['error_message'] = 'Mesa não encontrada.';
            header('Location: /tables/list');
            exit;
        }
        
        // Validar dados recebidos
        $number = isset($_POST['number']) ? trim($_POST['number']) : '';
        $name = isset($_POST['name']) ? trim($_POST['name']) : null;
        $capacity = isset($_POST['capacity']) ? intval($_POST['capacity']) : 4;
        $areaId = isset($_POST['area_id']) && $_POST['area_id'] !== '' ? intval($_POST['area_id']) : null;
        $positionX = isset($_POST['position_x']) ? intval($_POST['position_x']) : 0;
        $positionY = isset($_POST['position_y']) ? intval($_POST['position_y']) : 0;
        
        if (empty($number)) {
            $_SESSION['error_message'] = 'O número da mesa é obrigatório.';
            header('Location: /tables/edit/' . $tableId);
            exit;
        }
        
        // Verificar se o número da mesa já existe (exceto para a própria mesa)
        if ($number !== $table['number'] && $this->tableModel->checkTableNumberExists($tenantId, $number)) {
            $_SESSION['error_message'] = 'Este número de mesa já está em uso.';
            header('Location: /tables/edit/' . $tableId);
            exit;
        }
        
        // Atualizar mesa
        $result = $this->tableModel->updateTable($tableId, [
            'number' => $number,
            'name' => $name,
            'area_id' => $areaId,
            'capacity' => $capacity,
            'position_x' => $positionX,
            'position_y' => $positionY
        ]);
        
        if (!$result) {
            $_SESSION['error_message'] = 'Erro ao atualizar mesa.';
            header('Location: /tables/edit/' . $tableId);
            exit;
        }
        
        // Mesa atualizada com sucesso
        $_SESSION['success_message'] = 'Mesa atualizada com sucesso!';
        header('Location: /tables/list');
        exit;
    }
    
    /**
     * Processa a exclusão de uma mesa
     */
    public function delete($tableId = null) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        if (!$tableId) {
            $_SESSION['error_message'] = 'Mesa não especificada.';
            header('Location: /tables/list');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'table_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter dados da mesa
        $table = $this->tableModel->getTableById($tableId, $tenantId);
        
        if (!$table) {
            $_SESSION['error_message'] = 'Mesa não encontrada.';
            header('Location: /tables/list');
            exit;
        }
        
        // Verificar se a mesa está em uso (com pedidos ativos)
        if ($table['status'] === 'occupied') {
            $_SESSION['error_message'] = 'Não é possível excluir uma mesa ocupada.';
            header('Location: /tables/list');
            exit;
        }
        
        // Excluir mesa
        $result = $this->tableModel->deleteTable($tableId, $tenantId);
        
        if (!$result) {
            $_SESSION['error_message'] = 'Erro ao excluir mesa.';
            header('Location: /tables/list');
            exit;
        }
        
        // Mesa excluída com sucesso
        $_SESSION['success_message'] = 'Mesa excluída com sucesso!';
        header('Location: /tables/list');
        exit;
    }
    
    /**
     * Atualiza o status de uma mesa
     */
    public function updateStatus($tableId = null) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se a requisição é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Método inválido.';
            header('Location: /tables/map');
            exit;
        }
        
        if (!$tableId) {
            $_SESSION['error_message'] = 'Mesa não especificada.';
            header('Location: /tables/map');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'table_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter dados da mesa
        $table = $this->tableModel->getTableById($tableId, $tenantId);
        
        if (!$table) {
            $_SESSION['error_message'] = 'Mesa não encontrada.';
            header('Location: /tables/map');
            exit;
        }
        
        // Validar dados recebidos
        $newStatus = isset($_POST['status']) ? $_POST['status'] : '';
        
        if (!in_array($newStatus, ['available', 'occupied', 'reserved', 'cleaning', 'inactive'])) {
            $_SESSION['error_message'] = 'Status inválido.';
            header('Location: /tables/map');
            exit;
        }
        
        // Verificar transição de status
        $currentStatus = $table['status'];
        
        // Lógica especial para ocupação de mesa
        if ($newStatus === 'occupied' && $currentStatus !== 'occupied') {
            // Registrar início de ocupação
            $this->tableModel->updateTable($tableId, [
                'status' => $newStatus,
                'occupied_since' => date('Y-m-d H:i:s')
            ]);
            
            // Iniciar registro no histórico de ocupação
            $this->tableModel->startTableOccupancy($tableId, $tenantId);
        } 
        // Lógica especial para liberação de mesa
        elseif ($currentStatus === 'occupied' && $newStatus !== 'occupied') {
            // Limpar timestamp de ocupação
            $this->tableModel->updateTable($tableId, [
                'status' => $newStatus,
                'occupied_since' => null
            ]);
            
            // Finalizar registro no histórico de ocupação
            $this->tableModel->endTableOccupancy($tableId, $tenantId);
        } 
        // Atualização normal de status
        else {
            $this->tableModel->updateTable($tableId, [
                'status' => $newStatus
            ]);
        }
        
        // Redirecionar de volta para a página anterior
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/tables/map';
        header('Location: ' . $referer);
        exit;
    }
    
    /**
     * Salva posições das mesas no mapa
     */
    public function savePositions() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se a requisição é POST e AJAX
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Requisição inválida']);
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'table_management')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }
        
        // Obter dados JSON do corpo da requisição
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['tables']) || !is_array($data['tables'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }
        
        $tables = $data['tables'];
        $updated = 0;
        
        // Atualizar posição de cada mesa
        foreach ($tables as $table) {
            if (!isset($table['id'], $table['position_x'], $table['position_y'])) {
                continue;
            }
            
            $tableId = intval($table['id']);
            $positionX = intval($table['position_x']);
            $positionY = intval($table['position_y']);
            
            // Verificar se a mesa pertence ao tenant
            $tableInfo = $this->tableModel->getTableById($tableId, $tenantId);
            if (!$tableInfo) {
                continue;
            }
            
            // Atualizar posição
            $result = $this->tableModel->updateTable($tableId, [
                'position_x' => $positionX,
                'position_y' => $positionY
            ]);
            
            if ($result) {
                $updated++;
            }
        }
        
        // Responder com status da operação
        echo json_encode([
            'success' => true,
            'message' => $updated . ' mesas atualizadas com sucesso',
            'updated_count' => $updated
        ]);
        exit;
    }
    
    /**
     * Exibe histórico de ocupação de uma mesa
     */
    public function history($tableId = null) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        if (!$tableId) {
            $_SESSION['error_message'] = 'Mesa não especificada.';
            header('Location: /tables/list');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'table_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter dados da mesa
        $table = $this->tableModel->getTableById($tableId, $tenantId);
        
        if (!$table) {
            $_SESSION['error_message'] = 'Mesa não encontrada.';
            header('Location: /tables/list');
            exit;
        }
        
        // Obter histórico de ocupação
        $history = $this->tableModel->getTableOccupancyHistory($tableId, $tenantId);
        
        // Carregar a view
        require_once __DIR__ . '/../views/tables/history.php';
    }
}