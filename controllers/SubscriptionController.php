<?php
/**
 * Controlador para gerenciamento de assinaturas
 * 
 * Gerencia operações relacionadas a assinaturas como visualização de planos,
 * checkout, alterações de plano e cancelamentos.
 * 
 * Status: 90% Completo
 * Pendente:
 * - Implementar histórico de faturamento completo
 * - Adicionar métricas de uso de recursos
 */

require_once __DIR__ . '/../models/Account/Plan.php';
require_once __DIR__ . '/../models/Account/Subscription.php';
require_once __DIR__ . '/../services/SubscriptionService.php';
require_once __DIR__ . '/../services/StripeService.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/feature-checker.php';

class SubscriptionController {
    private $subscriptionService;
    private $stripeService;
    
    public function __construct() {
        $this->subscriptionService = new SubscriptionService();
        $this->stripeService = new StripeService();
    }
    
    /**
     * Exibe a página de planos disponíveis
     */
    public function index() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Obter o tenant_id do usuário logado
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter a assinatura atual, se existir
        $subscription = null;
        if ($tenantId) {
            $subscriptionModel = new Subscription();
            $subscription = $subscriptionModel->getActiveSubscriptionByTenantId($tenantId);
        }
        
        // Obter todos os planos disponíveis
        $planModel = new Plan();
        $plans = $planModel->getAllActivePlans();
        
        // Incluir a view
        require_once __DIR__ . '/../views/subscription/plans.php';
    }
    
    /**
     * Exibe a página de checkout para um plano específico
     */
    public function checkout($planId = null) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se o plano foi especificado
        if (!$planId) {
            $_SESSION['error_message'] = 'Plano não especificado.';
            header('Location: /subscription');
            exit;
        }
        
        // Obter detalhes do plano
        $planModel = new Plan();
        $plan = $planModel->getPlanById($planId);
        
        if (!$plan) {
            $_SESSION['error_message'] = 'Plano não encontrado.';
            header('Location: /subscription');
            exit;
        }
        
        // Obter o tenant_id do usuário logado
        $tenantId = $_SESSION['tenant_id'];
        
        // Verificar se já existe uma assinatura ativa
        $subscriptionModel = new Subscription();
        $existingSubscription = $subscriptionModel->getActiveSubscriptionByTenantId($tenantId);
        
        // Se já existe uma assinatura, redirecionar para página de mudança de plano
        if ($existingSubscription) {
            header('Location: /subscription/change-plan/' . $planId);
            exit;
        }
        
        // Criar cliente Stripe público para o frontend
        $stripePublicKey = STRIPE_PUBLIC_KEY;
        
        // Incluir a view
        require_once __DIR__ . '/../views/subscription/checkout.php';
    }
    
    /**
     * Processa o checkout de uma assinatura
     */
    public function processCheckout() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se os dados foram enviados via POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Método inválido.';
            header('Location: /subscription');
            exit;
        }
        
        // Validar os dados recebidos
        $planId = isset($_POST['plan_id']) ? intval($_POST['plan_id']) : null;
        $paymentMethodId = isset($_POST['payment_method_id']) ? $_POST['payment_method_id'] : null;
        
        if (!$planId || !$paymentMethodId) {
            $_SESSION['error_message'] = 'Dados de pagamento ou plano inválidos.';
            header('Location: /subscription/checkout/' . $planId);
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Criar a assinatura
        $result = $this->subscriptionService->createSubscription($tenantId, $planId, $paymentMethodId);
        
        if (!$result['success']) {
            $_SESSION['error_message'] = 'Erro ao processar assinatura: ' . $result['error'];
            header('Location: /subscription/checkout/' . $planId);
            exit;
        }
        
        // Assinatura criada com sucesso, redirecionar para página de sucesso
        $_SESSION['success_message'] = 'Assinatura criada com sucesso!';
        header('Location: /subscription/success');
        exit;
    }
    
    /**
     * Exibe a página de sucesso após criação da assinatura
     */
    public function success() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter detalhes da assinatura recém-criada
        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getActiveSubscriptionByTenantId($tenantId);
        
        if (!$subscription) {
            $_SESSION['error_message'] = 'Nenhuma assinatura ativa encontrada.';
            header('Location: /subscription');
            exit;
        }
        
        // Obter detalhes do plano
        $planModel = new Plan();
        $plan = $planModel->getPlanById($subscription['plan_id']);
        
        // Incluir a view
        require_once __DIR__ . '/../views/subscription/success.php';
    }
    
    /**
     * Exibe a página de gerenciamento de assinatura
     */
    public function manage() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter detalhes da assinatura
        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getActiveSubscriptionByTenantId($tenantId);
        
        if (!$subscription) {
            $_SESSION['error_message'] = 'Nenhuma assinatura ativa encontrada.';
            header('Location: /subscription');
            exit;
        }
        
        // Obter detalhes do plano
        $planModel = new Plan();
        $plan = $planModel->getPlanById($subscription['plan_id']);
        
        // Obter histórico de faturas
        $invoices = $subscriptionModel->getInvoicesBySubscriptionId($subscription['id']);
        
        // Incluir a view
        require_once __DIR__ . '/../views/subscription/manage.php';
    }
    
    /**
     * Exibe a página de mudança de plano
     */
    public function changePlan($newPlanId = null) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se o plano foi especificado
        if (!$newPlanId) {
            $_SESSION['error_message'] = 'Novo plano não especificado.';
            header('Location: /subscription/manage');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter detalhes da assinatura atual
        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getActiveSubscriptionByTenantId($tenantId);
        
        if (!$subscription) {
            $_SESSION['error_message'] = 'Nenhuma assinatura ativa encontrada.';
            header('Location: /subscription');
            exit;
        }
        
        // Obter detalhes do plano atual
        $planModel = new Plan();
        $currentPlan = $planModel->getPlanById($subscription['plan_id']);
        
        // Obter detalhes do novo plano
        $newPlan = $planModel->getPlanById($newPlanId);
        
        if (!$newPlan) {
            $_SESSION['error_message'] = 'Plano não encontrado.';
            header('Location: /subscription/manage');
            exit;
        }
        
        // Calcular diferença de preço
        $priceDifference = $newPlan['price'] - $currentPlan['price'];
        $isUpgrade = $priceDifference > 0;
        
        // Incluir a view
        require_once __DIR__ . '/../views/subscription/change-plan.php';
    }
    
    /**
     * Processa a mudança de plano
     */
    public function processChangePlan() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se os dados foram enviados via POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Método inválido.';
            header('Location: /subscription/manage');
            exit;
        }
        
        // Validar os dados recebidos
        $newPlanId = isset($_POST['new_plan_id']) ? intval($_POST['new_plan_id']) : null;
        
        if (!$newPlanId) {
            $_SESSION['error_message'] = 'Plano inválido.';
            header('Location: /subscription/manage');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Realizar a mudança de plano
        $result = $this->subscriptionService->changePlan($tenantId, $newPlanId);
        
        if (!$result) {
            $_SESSION['error_message'] = 'Erro ao mudar plano.';
            header('Location: /subscription/change-plan/' . $newPlanId);
            exit;
        }
        
        // Mudança realizada com sucesso
        $_SESSION['success_message'] = 'Plano alterado com sucesso!';
        header('Location: /subscription/manage');
        exit;
    }
    
    /**
     * Exibe a página de cancelamento de assinatura
     */
    public function cancel() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter detalhes da assinatura
        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getActiveSubscriptionByTenantId($tenantId);
        
        if (!$subscription) {
            $_SESSION['error_message'] = 'Nenhuma assinatura ativa encontrada.';
            header('Location: /subscription');
            exit;
        }
        
        // Obter detalhes do plano
        $planModel = new Plan();
        $plan = $planModel->getPlanById($subscription['plan_id']);
        
        // Incluir a view
        require_once __DIR__ . '/../views/subscription/cancel.php';
    }
    
    /**
     * Processa o cancelamento de assinatura
     */
    public function processCancel() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se os dados foram enviados via POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Método inválido.';
            header('Location: /subscription/manage');
            exit;
        }
        
        // Validar dados recebidos
        $confirmCancel = isset($_POST['confirm_cancel']) ? $_POST['confirm_cancel'] : null;
        
        if ($confirmCancel !== 'yes') {
            $_SESSION['error_message'] = 'Confirmação necessária para cancelar a assinatura.';
            header('Location: /subscription/cancel');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter detalhes da assinatura
        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getActiveSubscriptionByTenantId($tenantId);
        
        if (!$subscription) {
            $_SESSION['error_message'] = 'Nenhuma assinatura ativa encontrada.';
            header('Location: /subscription');
            exit;
        }
        
        // Cancelar a assinatura no Stripe
        $result = $this->stripeService->cancelSubscription($subscription['stripe_subscription_id']);
        
        if (!$result) {
            $_SESSION['error_message'] = 'Erro ao cancelar assinatura.';
            header('Location: /subscription/cancel');
            exit;
        }
        
        // Cancelamento realizado com sucesso
        $_SESSION['success_message'] = 'Assinatura cancelada com sucesso.';
        header('Location: /subscription');
        exit;
    }
    
    /**
     * Exibe o Portal de Faturamento
     */
    public function billing() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter detalhes da assinatura
        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getActiveSubscriptionByTenantId($tenantId);
        
        if (!$subscription) {
            $_SESSION['error_message'] = 'Nenhuma assinatura ativa encontrada.';
            header('Location: /subscription');
            exit;
        }
        
        // Obter histórico de faturas
        $invoices = $subscriptionModel->getInvoicesBySubscriptionId($subscription['id']);
        
        // Obter métodos de pagamento
        $paymentMethods = $this->stripeService->getPaymentMethods($subscription['stripe_customer_id']);
        
        // Incluir a view
        require_once __DIR__ . '/../views/subscription/billing.php';
    }
    
    /**
     * Processa a adição de um novo método de pagamento
     */
    public function addPaymentMethod() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se os dados foram enviados via POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Método inválido.';
            header('Location: /subscription/billing');
            exit;
        }
        
        // Validar dados recebidos
        $paymentMethodId = isset($_POST['payment_method_id']) ? $_POST['payment_method_id'] : null;
        
        if (!$paymentMethodId) {
            $_SESSION['error_message'] = 'Método de pagamento inválido.';
            header('Location: /subscription/billing');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter detalhes da assinatura
        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getActiveSubscriptionByTenantId($tenantId);
        
        if (!$subscription) {
            $_SESSION['error_message'] = 'Nenhuma assinatura ativa encontrada.';
            header('Location: /subscription');
            exit;
        }
        
        // Adicionar método de pagamento ao cliente
        $result = $this->stripeService->attachPaymentMethod(
            $subscription['stripe_customer_id'],
            $paymentMethodId
        );
        
        if (!$result) {
            $_SESSION['error_message'] = 'Erro ao adicionar método de pagamento.';
            header('Location: /subscription/billing');
            exit;
        }
        
        // Método adicionado com sucesso
        $_SESSION['success_message'] = 'Método de pagamento adicionado com sucesso.';
        header('Location: /subscription/billing');
        exit;
    }
    
    /**
     * Exibe a página para nova tentativa de pagamento
     */
    public function retryPayment($invoiceId = null) {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se o invoice foi especificado
        if (!$invoiceId) {
            $_SESSION['error_message'] = 'Fatura não especificada.';
            header('Location: /subscription/billing');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter detalhes da assinatura
        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getActiveSubscriptionByTenantId($tenantId);
        
        if (!$subscription) {
            $_SESSION['error_message'] = 'Nenhuma assinatura ativa encontrada.';
            header('Location: /subscription');
            exit;
        }
        
        // Obter detalhes da fatura
        $invoice = $subscriptionModel->getInvoiceByStripeId($invoiceId);
        
        if (!$invoice || $invoice['subscription_id'] != $subscription['id']) {
            $_SESSION['error_message'] = 'Fatura não encontrada.';
            header('Location: /subscription/billing');
            exit;
        }
        
        // Obter métodos de pagamento
        $paymentMethods = $this->stripeService->getPaymentMethods($subscription['stripe_customer_id']);
        
        // Incluir a view
        require_once __DIR__ . '/../views/subscription/retry-payment.php';
    }
    
    /**
     * Processa uma nova tentativa de pagamento
     */
    public function processRetryPayment() {
        // Verificar se o usuário está logado
        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login');
            exit;
        }
        
        // Verificar se os dados foram enviados via POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $_SESSION['error_message'] = 'Método inválido.';
            header('Location: /subscription/billing');
            exit;
        }
        
        // Validar dados recebidos
        $invoiceId = isset($_POST['invoice_id']) ? $_POST['invoice_id'] : null;
        $paymentMethodId = isset($_POST['payment_method_id']) ? $_POST['payment_method_id'] : null;
        
        if (!$invoiceId || !$paymentMethodId) {
            $_SESSION['error_message'] = 'Dados inválidos.';
            header('Location: /subscription/billing');
            exit;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Obter detalhes da assinatura
        $subscriptionModel = new Subscription();
        $subscription = $subscriptionModel->getActiveSubscriptionByTenantId($tenantId);
        
        if (!$subscription) {
            $_SESSION['error_message'] = 'Nenhuma assinatura ativa encontrada.';
            header('Location: /subscription');
            exit;
        }
        
        // Tentar o pagamento novamente
        $result = $this->stripeService->retryInvoicePayment(
            $invoiceId,
            $paymentMethodId
        );
        
        if (!$result['success']) {
            $_SESSION['error_message'] = 'Erro ao processar pagamento: ' . $result['error'];
            header('Location: /subscription/retry-payment/' . $invoiceId);
            exit;
        }
        
        // Pagamento realizado com sucesso
        $_SESSION['success_message'] = 'Pagamento processado com sucesso!';
        header('Location: /subscription/billing');
        exit;
    }
}