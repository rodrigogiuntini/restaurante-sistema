<?php
/**
 * Controlador para gerenciamento de QR Codes
 * 
 * Gerencia operações de geração, listagem e visualização de QR Codes
 * para mesas, cardápios e pagamentos.
 * 
 * Status: 75% Completo
 * Pendente:
 * - Implementar estatísticas de uso de QR Codes
 * - Adicionar opções avançadas de personalização
 * - Melhorar segurança e validação
 */

require_once __DIR__ . '/../services/QRCodeService.php';
require_once __DIR__ . '/../models/QRCode.php';
require_once __DIR__ . '/../models/Table.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/feature-checker.php';

class QRCodeController {
    private $qrCodeService;
    
    public function __construct() {
        $this->qrCodeService = new QRCodeService();
    }
    
    /**
     * Exibe a página de gerenciamento de QR Codes
     */
    public function index() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'qrcode_manager')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter QR Codes de mesa
        $tableQrCodes = $this->qrCodeService->getQRCodesByType($tenantId, 'table');
        
        // Obter QR Codes de cardápio
        $menuQrCodes = $this->qrCodeService->getQRCodesByType($tenantId, 'menu');
        
        // Obter QR Codes de pagamento (ativos)
        $paymentQrCodes = $this->qrCodeService->getQRCodesByType($tenantId, 'payment');
        
        // Carregar a view
        require_once __DIR__ . '/../views/qrcode/manager.php';
    }
    
    /**
     * Exibe a página de geração de QR Codes
     */
    public function generator() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'qrcode_generator')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Obter todas as mesas do tenant
        $tableModel = new Table();
        $tables = $tableModel->getTablesByTenantId($tenantId);
        
        // Carregar a view
        require_once __DIR__ . '/../views/qrcode/generator.php';
    }
    
    /**
     * Processa a geração de QR Code para mesa
     */
    public function generateTableQRCode() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se a requisição é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Método inválido.';
            header('Location: /qrcode/generator');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'qrcode_generator')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Validar dados recebidos
        $tableId = isset($_POST['table_id']) ? intval($_POST['table_id']) : null;
        
        if (!$tableId) {
            $_SESSION['error_message'] = 'Mesa não especificada.';
            header('Location: /qrcode/generator');
            exit;
        }
        
        // Obter dados da mesa
        $tableModel = new Table();
        $table = $tableModel->getTableById($tableId, $tenantId);
        
        if (!$table) {
            $_SESSION['error_message'] = 'Mesa não encontrada.';
            header('Location: /qrcode/generator');
            exit;
        }
        
        // Gerar QR Code
        $tableName = $table['name'] ? $table['name'] : 'Mesa ' . $table['number'];
        $result = $this->qrCodeService->generateTableQRCode($tenantId, $tableId, $tableName);
        
        if (!$result['success']) {
            $_SESSION['error_message'] = 'Erro ao gerar QR Code: ' . $result['error'];
            header('Location: /qrcode/generator');
            exit;
        }
        
        // QR Code gerado com sucesso
        $_SESSION['success_message'] = 'QR Code gerado com sucesso!';
        $_SESSION['qr_code'] = $result;
        header('Location: /qrcode/preview/' . $result['qr_code_id']);
        exit;
    }
    
    /**
     * Processa a geração de QR Code para cardápio
     */
    public function generateMenuQRCode() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se a requisição é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Método inválido.';
            header('Location: /qrcode/generator');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar permissão de acesso
        if (!checkFeatureAccess($tenantId, 'qrcode_generator')) {
            $_SESSION['error_message'] = 'Seu plano não permite acesso a este recurso.';
            header('Location: /dashboard');
            exit;
        }
        
        // Dados adicionais que podem ser enviados
        $menuName = isset($_POST['menu_name']) ? $_POST['menu_name'] : 'Cardápio Digital';
        $extraData = [
            'menu_name' => $menuName
        ];
        
        // Gerar QR Code para cardápio
        $result = $this->qrCodeService->generateMenuQRCode($tenantId, $extraData);
        
        if (!$result['success']) {
            $_SESSION['error_message'] = 'Erro ao gerar QR Code: ' . $result['error'];
            header('Location: /qrcode/generator');
            exit;
        }
        
        // QR Code gerado com sucesso
        $_SESSION['success_message'] = 'QR Code para cardápio gerado com sucesso!';
        $_SESSION['qr_code'] = $result;
        header('Location: /qrcode/preview/' . $result['qr_code_id']);
        exit;
    }
    
    /**
     * Exibe a pré-visualização de um QR Code
     */
    public function preview($qrCodeId = null) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        if (!$qrCodeId) {
            $_SESSION['error_message'] = 'QR Code não especificado.';
            header('Location: /qrcode/generator');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter dados do QR Code
        $qrCode = null;
        
        // Verificar se temos os dados na sessão (QR Code recém-gerado)
        if (isset($_SESSION['qr_code']) && $_SESSION['qr_code']['qr_code_id'] == $qrCodeId) {
            $qrCodeInfo = $_SESSION['qr_code'];
            unset($_SESSION['qr_code']); // Limpar da sessão após uso
            
            // Obter dados completos do QR Code
            $qrCode = $this->qrCodeService->getQRCodeById($qrCodeId, $tenantId);
            if ($qrCode) {
                $qrCode['image_path'] = $qrCodeInfo['image_path'];
            }
        } else {
            // Buscar no banco de dados
            $qrCode = $this->qrCodeService->getQRCodeById($qrCodeId, $tenantId);
            
            if (!$qrCode) {
                $_SESSION['error_message'] = 'QR Code não encontrado.';
                header('Location: /qrcode/manager');
                exit;
            }
        }
        
        // Obter dados adicionais baseado no tipo
        $additionalData = [];
        
        if ($qrCode['type'] === 'table') {
            // Obter dados da mesa
            $tableModel = new Table();
            $table = $tableModel->getTableById($qrCode['table_id'], $tenantId);
            $additionalData['table'] = $table;
        }
        
        $jsonData = json_decode($qrCode['data'], true);
        
        // Carregar a view
        require_once __DIR__ . '/../views/qrcode/preview.php';
    }
    
    /**
     * Desativa um QR Code
     */
    public function deactivate($qrCodeId = null) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        if (!$qrCodeId) {
            $_SESSION['error_message'] = 'QR Code não especificado.';
            header('Location: /qrcode/manager');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Desativar QR Code
        $result = $this->qrCodeService->deactivateQRCode($qrCodeId, $tenantId);
        
        if (!$result) {
            $_SESSION['error_message'] = 'Erro ao desativar QR Code.';
        } else {
            $_SESSION['success_message'] = 'QR Code desativado com sucesso.';
        }
        
        header('Location: /qrcode/manager');
        exit;
    }
    
    /**
     * Processa um QR Code escaneado
     */
    public function scan($code) {
        // Validar o código
        $qrCode = $this->qrCodeService->getQRCodeByCode($code);
        
        if (!$qrCode) {
            // QR Code inválido ou expirado
            require_once __DIR__ . '/../views/qrcode/invalid.php';
            exit;
        }
        
        // Redirecionar baseado no tipo de QR Code
        switch ($qrCode['type']) {
            case 'table':
                // Verificar se tem table_id
                if (empty($qrCode['table_id'])) {
                    require_once __DIR__ . '/../views/qrcode/invalid.php';
                    exit;
                }
                
                // Redirecionar para o cardápio com a mesa selecionada
                header('Location: /menu/table/' . $qrCode['table_id'] . '?code=' . $code . '&hash=' . $qrCode['hash']);
                break;
                
            case 'menu':
                // Redirecionar para o cardápio geral
                header('Location: /menu?code=' . $code . '&hash=' . $qrCode['hash']);
                break;
                
            case 'payment':
                // Redirecionar para a página de pagamento
                header('Location: /payment/' . $code . '?hash=' . $qrCode['hash']);
                break;
                
            default:
                // Tipo desconhecido
                require_once __DIR__ . '/../views/qrcode/invalid.php';
                break;
        }
        
        exit;
    }
    
    /**
     * Download do QR Code como imagem
     */
    public function download($qrCodeId) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter dados do QR Code
        $qrCode = $this->qrCodeService->getQRCodeById($qrCodeId, $tenantId);
        
        if (!$qrCode) {
            $_SESSION['error_message'] = 'QR Code não encontrado.';
            header('Location: /qrcode/manager');
            exit;
        }
        
        // Caminho para o arquivo de imagem
        $imagePath = __DIR__ . '/../public/qr/' . $tenantId . '/' . $qrCode['code'] . '.png';
        
        if (!file_exists($imagePath)) {
            $_SESSION['error_message'] = 'Imagem do QR Code não encontrada.';
            header('Location: /qrcode/preview/' . $qrCodeId);
            exit;
        }
        
        // Determinar nome do arquivo baseado no tipo
        $fileName = 'qrcode_' . $qrCode['code'] . '.png';
        if ($qrCode['type'] === 'table' && $qrCode['table_id']) {
            $tableModel = new Table();
            $table = $tableModel->getTableById($qrCode['table_id'], $tenantId);
            if ($table) {
                $tableName = $table['name'] ? $table['name'] : 'Mesa' . $table['number'];
                $fileName = 'QRCode_' . sanitizeFileName($tableName) . '.png';
            }
        } elseif ($qrCode['type'] === 'menu') {
            $jsonData = json_decode($qrCode['data'], true);
            if (isset($jsonData['menu_name'])) {
                $fileName = 'QRCode_' . sanitizeFileName($jsonData['menu_name']) . '.png';
            } else {
                $fileName = 'QRCode_Cardapio.png';
            }
        }
        
        // Realizar o download
        header('Content-Description: File Transfer');
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($imagePath));
        readfile($imagePath);
        exit;
    }
    
    /**
     * Imprime um QR Code
     */
    public function print($qrCodeId) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter dados do QR Code
        $qrCode = $this->qrCodeService->getQRCodeById($qrCodeId, $tenantId);
        
        if (!$qrCode) {
            $_SESSION['error_message'] = 'QR Code não encontrado.';
            header('Location: /qrcode/manager');
            exit;
        }
        
        // Adicionar dados adicionais para a impressão
        $printData = [];
        
        if ($qrCode['type'] === 'table') {
            // Obter dados da mesa
            $tableModel = new Table();
            $table = $tableModel->getTableById($qrCode['table_id'], $tenantId);
            $printData['table'] = $table;
        }
        
        $jsonData = json_decode($qrCode['data'], true);
        $printData['qr_code'] = $qrCode;
        $printData['json_data'] = $jsonData;
        
        // Carregar a view de impressão
        require_once __DIR__ . '/../views/qrcode/print.php';
    }
}

/**
 * Função auxiliar para sanitizar nomes de arquivo
 */
function sanitizeFileName($name) {
    $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    return trim($name, '_');
}