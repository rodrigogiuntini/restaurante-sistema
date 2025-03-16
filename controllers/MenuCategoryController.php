<?php
/**
 * Controlador para gerenciamento de categorias do menu
 * 
 * Responsável por operações CRUD de categorias do cardápio,
 * permitindo organizar os itens do menu em grupos lógicos.
 * 
 * Status: 90% Completo
 * Pendente:
 * - Implementar ordenação por drag-and-drop
 * - Adicionar suporte a imagens para categorias
 * - Implementar categorias específicas por tipo de restaurante
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/feature-checker.php';
require_once __DIR__ . '/../includes/validation.php';

class MenuCategoryController {
    private $db;
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
    }
    
    /**
     * Exibe a lista de categorias do menu
     */
    public function index() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'menu_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter todas as categorias
        $categories = $this->getAllCategories($tenantId);
        
        // Renderizar a view
        require_once __DIR__ . '/../views/menu/category-list.php';
    }
    
    /**
     * Exibe o formulário para adicionar uma nova categoria
     */
    public function create() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'menu_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Renderizar a view
        require_once __DIR__ . '/../views/menu/category-create.php';
    }
    
    /**
     * Processa o formulário para adicionar uma nova categoria
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
            header('Location: /menu/categories');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'menu_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Validar dados recebidos
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        $sortOrder = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
        $active = isset($_POST['active']) ? 1 : 0;
        
        if (empty($name)) {
            $_SESSION['error_message'] = 'O nome da categoria é obrigatório.';
            $_SESSION['form_data'] = $_POST;
            header('Location: /menu/categories/create');
            exit;
        }
        
        // Verificar se o nome da categoria já existe
        if ($this->categoryNameExists($tenantId, $name)) {
            $_SESSION['error_message'] = 'Já existe uma categoria com este nome.';
            $_SESSION['form_data'] = $_POST;
            header('Location: /menu/categories/create');
            exit;
        }
        
        // Processar imagem se enviada
        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $result = $this->processImage($_FILES['image'], $tenantId);
            if ($result['success']) {
                $image = $result['path'];
            } else {
                $_SESSION['error_message'] = 'Erro ao enviar imagem: ' . $result['error'];
                $_SESSION['form_data'] = $_POST;
                header('Location: /menu/categories/create');
                exit;
            }
        }
        
        // Inserir no banco de dados
        $stmt = $this->db->prepare("
            INSERT INTO menu_categories (tenant_id, name, description, image, sort_order, active)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("isssii", $tenantId, $name, $description, $image, $sortOrder, $active);
        
        if ($stmt->execute()) {
            $categoryId = $stmt->insert_id;
            $_SESSION['success_message'] = 'Categoria criada com sucesso!';
            header('Location: /menu/categories');
            exit;
        } else {
            $_SESSION['error_message'] = 'Erro ao criar categoria: ' . $stmt->error;
            $_SESSION['form_data'] = $_POST;
            header('Location: /menu/categories/create');
            exit;
        }
    }
    
    /**
     * Exibe o formulário para editar uma categoria
     */
    public function edit($id) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'menu_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter dados da categoria
        $category = $this->getCategoryById($id, $tenantId);
        
        if (!$category) {
            $_SESSION['error_message'] = 'Categoria não encontrada.';
            header('Location: /menu/categories');
            exit;
        }
        
        // Renderizar a view
        require_once __DIR__ . '/../views/menu/category-edit.php';
    }
    
    /**
     * Processa o formulário para atualizar uma categoria
     */
    public function update($id) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se a requisição é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Método inválido.';
            header('Location: /menu/categories');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'menu_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter categoria atual
        $category = $this->getCategoryById($id, $tenantId);
        
        if (!$category) {
            $_SESSION['error_message'] = 'Categoria não encontrada.';
            header('Location: /menu/categories');
            exit;
        }
        
        // Validar dados recebidos
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $description = isset($_POST['description']) ? trim($_POST['description']) : null;
        $sortOrder = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
        $active = isset($_POST['active']) ? 1 : 0;
        
        if (empty($name)) {
            $_SESSION['error_message'] = 'O nome da categoria é obrigatório.';
            $_SESSION['form_data'] = $_POST;
            header('Location: /menu/categories/edit/' . $id);
            exit;
        }
        
        // Verificar se o nome da categoria já existe (exceto para a própria categoria)
        if ($name !== $category['name'] && $this->categoryNameExists($tenantId, $name)) {
            $_SESSION['error_message'] = 'Já existe uma categoria com este nome.';
            $_SESSION['form_data'] = $_POST;
            header('Location: /menu/categories/edit/' . $id);
            exit;
        }
        
        // Processar imagem se enviada
        $image = $category['image'];
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $result = $this->processImage($_FILES['image'], $tenantId);
            if ($result['success']) {
                // Excluir imagem antiga se existir
                if ($image && file_exists(__DIR__ . '/../public' . $image)) {
                    unlink(__DIR__ . '/../public' . $image);
                }
                $image = $result['path'];
            } else {
                $_SESSION['error_message'] = 'Erro ao enviar imagem: ' . $result['error'];
                $_SESSION['form_data'] = $_POST;
                header('Location: /menu/categories/edit/' . $id);
                exit;
            }
        }
        
        // Remover imagem se solicitado
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            // Excluir arquivo do servidor
            if ($image && file_exists(__DIR__ . '/../public' . $image)) {
                unlink(__DIR__ . '/../public' . $image);
            }
            $image = null;
        }
        
        // Atualizar no banco de dados
        $stmt = $this->db->prepare("
            UPDATE menu_categories
            SET name = ?, description = ?, image = ?, sort_order = ?, active = ?
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->bind_param("sssiiii", $name, $description, $image, $sortOrder, $active, $id, $tenantId);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = 'Categoria atualizada com sucesso!';
            header('Location: /menu/categories');
            exit;
        } else {
            $_SESSION['error_message'] = 'Erro ao atualizar categoria: ' . $stmt->error;
            $_SESSION['form_data'] = $_POST;
            header('Location: /menu/categories/edit/' . $id);
            exit;
        }
    }
    
    /**
     * Exclui uma categoria
     */
    public function delete($id) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'menu_management')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Verificar se há itens associados à categoria
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM menu_items
            WHERE category_id = ? AND tenant_id = ?
        ");
        
        $stmt->bind_param("ii", $id, $tenantId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            $_SESSION['error_message'] = 'Não é possível excluir esta categoria pois existem itens associados a ela.';
            header('Location: /menu/categories');
            exit;
        }
        
        // Obter dados da categoria para excluir imagem
        $category = $this->getCategoryById($id, $tenantId);
        
        // Excluir do banco de dados
        $stmt = $this->db->prepare("
            DELETE FROM menu_categories
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->bind_param("ii", $id, $tenantId);
        
        if ($stmt->execute()) {
            // Excluir imagem se existir
            if ($category && $category['image'] && file_exists(__DIR__ . '/../public' . $category['image'])) {
                unlink(__DIR__ . '/../public' . $category['image']);
            }
            
            $_SESSION['success_message'] = 'Categoria excluída com sucesso!';
        } else {
            $_SESSION['error_message'] = 'Erro ao excluir categoria: ' . $stmt->error;
        }
        
        header('Location: /menu/categories');
        exit;
    }
    
    /**
     * Atualiza a ordem de exibição das categorias
     */
    public function updateOrder() {
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
        if (!checkFeatureAccess($tenantId, 'menu_management')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Acesso negado']);
            exit;
        }
        
        // Obter dados JSON do corpo da requisição
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['categories']) || !is_array($data['categories'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
            exit;
        }
        
        $categories = $data['categories'];
        $updated = 0;
        
        // Atualizar ordem de cada categoria
        foreach ($categories as $index => $categoryId) {
            $sortOrder = $index + 1;
            
            $stmt = $this->db->prepare("
                UPDATE menu_categories
                SET sort_order = ?
                WHERE id = ? AND tenant_id = ?
            ");
            
            $stmt->bind_param("iii", $sortOrder, $categoryId, $tenantId);
            
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $updated++;
            }
        }
        
        // Responder com status da operação
        echo json_encode([
            'success' => true,
            'message' => $updated . ' categorias atualizadas com sucesso',
            'updated_count' => $updated
        ]);
        exit;
    }
    
    /**
     * Métodos auxiliares
     */
    
    /**
     * Obtém todas as categorias de um tenant
     */
    private function getAllCategories($tenantId) {
        $stmt = $this->db->prepare("
            SELECT c.*, COUNT(i.id) as item_count
            FROM menu_categories c
            LEFT JOIN menu_items i ON c.id = i.category_id AND i.tenant_id = c.tenant_id
            WHERE c.tenant_id = ?
            GROUP BY c.id
            ORDER BY c.sort_order, c.name
        ");
        
        $stmt->bind_param("i", $tenantId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        
        return $categories;
    }
    
    /**
     * Obtém uma categoria específica pelo ID
     */
    private function getCategoryById($id, $tenantId) {
        $stmt = $this->db->prepare("
            SELECT *
            FROM menu_categories
            WHERE id = ? AND tenant_id = ?
        ");
        
        $stmt->bind_param("ii", $id, $tenantId);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Verifica se já existe uma categoria com o nome especificado
     */
    private function categoryNameExists($tenantId, $name) {
        $stmt = $this->db->prepare("
            SELECT id
            FROM menu_categories
            WHERE tenant_id = ? AND name = ?
        ");
        
        $stmt->bind_param("is", $tenantId, $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    /**
     * Processa o upload de uma imagem
     */
    private function processImage($file, $tenantId) {
        // Definir tipos MIME permitidos
        $allowedTypes = [
            'image/jpeg',
            'image/png',
            'image/webp'
        ];
        
        // Verificar tipo do arquivo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $fileType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($fileType, $allowedTypes)) {
            return [
                'success' => false,
                'error' => 'Tipo de arquivo não permitido. Use apenas JPEG, PNG ou WebP.'
            ];
        }
        
        // Verificar tamanho do arquivo (limite de 2MB)
        if ($file['size'] > 2 * 1024 * 1024) {
            return [
                'success' => false,
                'error' => 'Tamanho de arquivo excedido. O limite é 2MB.'
            ];
        }
        
        // Criar diretório se não existir
        $uploadDir = __DIR__ . '/../public/uploads/menu/categories/' . $tenantId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Gerar nome de arquivo único
        $filename = md5(uniqid() . time()) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $uploadFile = $uploadDir . '/' . $filename;
        
        // Mover arquivo
        if (move_uploaded_file($file['tmp_name'], $uploadFile)) {
            return [
                'success' => true,
                'path' => '/uploads/menu/categories/' . $tenantId . '/' . $filename
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Falha ao mover arquivo enviado.'
            ];
        }
    }
}