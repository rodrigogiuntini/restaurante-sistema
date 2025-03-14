<?php
/**
 * Controlador de Assinaturas
 * Responsável por gerenciar planos, assinaturas e pagamentos
 */

class SubscriptionController {
    /**
     * Exibe a página de planos disponíveis
     */
    public function plans() {
        // Se o usuário estiver autenticado, verificar se já tem uma assinatura
        if (isAuthenticated()) {
            $pdo = db();
            $stmt = $pdo->prepare("
                SELECT s.*, p.name as plan_name, p.code as plan_code 
                FROM subscriptions s 
                JOIN plans p ON s.plan_id = p.id 
                WHERE s.tenant_id = ? AND s.status != 'canceled'
            ");
            $stmt->execute([$_SESSION['tenant_id']]);
            $subscription = $stmt->fetch();
            
            if ($subscription) {
                // Redirecionar para página de gestão de assinatura
                redirect(APP_URL . '/subscription/manage');
                return;
            }
        }
        
        // Carregar todos os planos ativos
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE active = 1 ORDER BY price ASC");
        $stmt->execute();
        $plans = $stmt->fetchAll();
        
        // Exibir planos
        $pageTitle = 'Planos de Assinatura';
        require_once APP_PATH . '/views/subscription/plans.php';
    }
    
    /**
     * Exibe a página de checkout para um plano específico
     */
    public function checkout($planCode = null) {
        // Verificar se o usuário está autenticado
        if (!isAuthenticated()) {
            setFlashMessage('error', 'Faça login para continuar com a assinatura.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        // Verificar se o código do plano foi fornecido
        if (empty($planCode)) {
            redirect(APP_URL . '/subscription/plans');
            return;
        }
        
        // Carregar dados do plano
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE code = ? AND active = 1");
        $stmt->execute([$planCode]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            setFlashMessage('error', 'Plano não encontrado ou indisponível.');
            redirect(APP_URL . '/subscription/plans');
            return;
        }
        
        // Carregar dados da conta do usuário
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
        $stmt->execute([$_SESSION['tenant_id']]);
        $tenant = $stmt->fetch();
        
        if (!$tenant) {
            setFlashMessage('error', 'Conta não encontrada.');
            redirect(APP_URL . '/auth/logout');
            return;
        }
        
        // Iniciar o Stripe
        $stripeService = new StripeService();
        
        // Verificar se o cliente já existe no Stripe
        $stripeCustomerId = $tenant['stripe_customer_id'] ?? null;
        
        if (!$stripeCustomerId) {
            // Criar cliente no Stripe
            $customer = $stripeService->createCustomer([
                'email' => $tenant['email'],
                'name' => $tenant['name'],
                'description' => 'Cliente ID: ' . $tenant['id'],
                'metadata' => [
                    'tenant_id' => $tenant['id']
                ]
            ]);
            
            $stripeCustomerId = $customer->id;
            
            // Atualizar o ID do cliente no banco
            $stmt = $pdo->prepare("UPDATE tenants SET stripe_customer_id = ? WHERE id = ?");
            $stmt->execute([$stripeCustomerId, $tenant['id']]);
        }
        
        // Criar sessão de checkout do Stripe
        $session = $stripeService->createCheckoutSession([
            'customer' => $stripeCustomerId,
            'success_url' => APP_URL . '/subscription/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => APP_URL . '/subscription/plans',
            'payment_method_types' => ['card'],
            'mode' => 'subscription',
            'line_items' => [
                [
                    'price' => $plan['stripe_price_id'],
                    'quantity' => 1
                ]
            ],
            'metadata' => [
                'tenant_id' => $tenant['id'],
                'plan_id' => $plan['id'],
                'plan_code' => $plan['code']
            ]
        ]);
        
        // Exibir página de checkout
        $pageTitle = 'Finalizar Assinatura';
        $checkoutSessionId = $session->id;
        $stripePublicKey = getenv('STRIPE_PUBLIC_KEY') ?: 'pk_test_your_key';
        
        require_once APP_PATH . '/views/subscription/checkout.php';
    }
    
    /**
     * Processa o sucesso da assinatura após checkout
     */
    public function success() {
        // Verificar se o usuário está autenticado
        if (!isAuthenticated()) {
            setFlashMessage('error', 'Faça login para continuar.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        // Verificar se o ID da sessão foi fornecido
        $sessionId = $_GET['session_id'] ?? null;
        
        if (empty($sessionId)) {
            redirect(APP_URL . '/subscription/plans');
            return;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Iniciar o Stripe e recuperar a sessão
        $stripeService = new StripeService();
        $session = $stripeService->getCheckoutSession($sessionId);
        
        if (!$session || $session->metadata->tenant_id != $tenantId) {
            setFlashMessage('error', 'Sessão de checkout inválida.');
            redirect(APP_URL . '/subscription/plans');
            return;
        }
        
        // Recuperar o plano
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE id = ?");
        $stmt->execute([$session->metadata->plan_id]);
        $plan = $stmt->fetch();
        
        if (!$plan) {
            setFlashMessage('error', 'Plano não encontrado.');
            redirect(APP_URL . '/subscription/plans');
            return;
        }
        
        // Criar ou atualizar assinatura no sistema
        $subscription = [
            'tenant_id' => $tenantId,
            'plan_id' => $plan['id'],
            'stripe_subscription_id' => $session->subscription,
            'stripe_customer_id' => $session->customer,
            'status' => 'active',
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+' . TRIAL_PERIOD_DAYS . ' days')),
            'next_billing_at' => date('Y-m-d H:i:s', strtotime('+' . TRIAL_PERIOD_DAYS . ' days'))
        ];
        
        // Verificar se já existe uma assinatura
        $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $existingSubscription = $stmt->fetch();
        
        if ($existingSubscription) {
            // Atualizar assinatura existente
            $stmt = $pdo->prepare("
                UPDATE subscriptions 
                SET plan_id = ?, stripe_subscription_id = ?, stripe_customer_id = ?, 
                    status = ?, trial_ends_at = ?, next_billing_at = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([
                $subscription['plan_id'],
                $subscription['stripe_subscription_id'],
                $subscription['stripe_customer_id'],
                $subscription['status'],
                $subscription['trial_ends_at'],
                $subscription['next_billing_at'],
                $existingSubscription['id']
            ]);
        } else {
            // Criar nova assinatura
            $stmt = $pdo->prepare("
                INSERT INTO subscriptions 
                (tenant_id, plan_id, stripe_subscription_id, stripe_customer_id, status, trial_ends_at, next_billing_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $subscription['tenant_id'],
                $subscription['plan_id'],
                $subscription['stripe_subscription_id'],
                $subscription['stripe_customer_id'],
                $subscription['status'],
                $subscription['trial_ends_at'],
                $subscription['next_billing_at']
            ]);
        }
        
        setFlashMessage('success', 'Assinatura realizada com sucesso! Seu período de teste começa agora.');
        
        // Exibir página de sucesso
        $pageTitle = 'Assinatura Realizada';
        require_once APP_PATH . '/views/subscription/success.php';
    }
    
    /**
     * Exibe a página de gerenciamento da assinatura
     */
    public function manage() {
        // Verificar se o usuário está autenticado
        if (!isAuthenticated()) {
            setFlashMessage('error', 'Faça login para continuar.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Carregar dados da assinatura
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT s.*, p.name as plan_name, p.code as plan_code, p.price as plan_price,
                   p.features as plan_features, p.limits as plan_limits
            FROM subscriptions s 
            JOIN plans p ON s.plan_id = p.id 
            WHERE s.tenant_id = ? AND s.status != 'canceled'
        ");
        $stmt->execute([$tenantId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            redirect(APP_URL . '/subscription/plans');
            return;
        }
        
        // Carregar faturas
        $stmt = $pdo->prepare("
            SELECT * FROM invoices
            WHERE tenant_id = ? 
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$tenantId]);
        $invoices = $stmt->fetchAll();
        
        // Carregar uso de recursos
        $stmt = $pdo->prepare("
            SELECT resource_type, SUM(resource_count) as total 
            FROM resource_usage 
            WHERE tenant_id = ? AND year = ? AND month = ? 
            GROUP BY resource_type
        ");
        $stmt->execute([$tenantId, date('Y'), date('n')]);
        $resourceUsage = [];
        
        while ($row = $stmt->fetch()) {
            $resourceUsage[$row['resource_type']] = $row['total'];
        }
        
        // Exibir página de gerenciamento
        $pageTitle = 'Gerenciar Assinatura';
        require_once APP_PATH . '/views/subscription/manage.php';
    }
    
    /**
     * Processa a criação de portal de faturamento do Stripe
     */
    public function billingPortal() {
        // Verificar se o usuário está autenticado
        if (!isAuthenticated()) {
            setFlashMessage('error', 'Faça login para continuar.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Carregar dados da assinatura
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT s.*, t.stripe_customer_id 
            FROM subscriptions s 
            JOIN tenants t ON s.tenant_id = t.id
            WHERE s.tenant_id = ? AND s.status != 'canceled'
        ");
        $stmt->execute([$tenantId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription || !$subscription['stripe_customer_id']) {
            setFlashMessage('error', 'Assinatura não encontrada.');
            redirect(APP_URL . '/dashboard');
            return;
        }
        
        // Criar sessão do portal de faturamento
        $stripeService = new StripeService();
        $session = $stripeService->createBillingPortalSession([
            'customer' => $subscription['stripe_customer_id'],
            'return_url' => APP_URL . '/subscription/manage'
        ]);
        
        // Redirecionar para o portal
        redirect($session->url);
    }
    
    /**
     * Cancela a assinatura atual
     */
    public function cancel() {
        // Verificar se o usuário está autenticado
        if (!isAuthenticated()) {
            setFlashMessage('error', 'Faça login para continuar.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        $tenantId = $_SESSION['tenant_id'];
        
        // Carregar dados da assinatura
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT * FROM subscriptions 
            WHERE tenant_id = ? AND status != 'canceled'
        ");
        $stmt->execute([$tenantId]);
        $subscription = $stmt->fetch();
        
        if (!$subscription) {
            setFlashMessage('error', 'Assinatura não encontrada.');
            redirect(APP_URL . '/dashboard');
            return;
        }
        
        // Formulário de confirmação ou processamento de cancelamento
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $reason = sanitize($_POST['reason'] ?? '');
            $feedback = sanitize($_POST['feedback'] ?? '');
            
            // Cancelar assinatura no Stripe
            if ($subscription['stripe_subscription_id']) {
                $stripeService = new StripeService();
                $stripeService->cancelSubscription($subscription['stripe_subscription_id']);
            }
            
            // Atualizar status da assinatura no sistema
            $stmt = $pdo->prepare("
                UPDATE subscriptions 
                SET status = 'canceled', ends_at = NOW(), updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$subscription['id']]);
            
            // Registrar feedback do cancelamento
            $stmt = $pdo->prepare("
                INSERT INTO subscription_cancellations (tenant_id, subscription_id, reason, feedback) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$tenantId, $subscription['id'], $reason, $feedback]);
            
            setFlashMessage('success', 'Sua assinatura foi cancelada com sucesso.');
            redirect(APP_URL . '/subscription/plans');
            return;
        }
        
        // Exibir formulário de cancelamento
        $pageTitle = 'Cancelar Assinatura';
        require_once APP_PATH . '/views/subscription/cancel.php';
    }

    /**
     * Exibe a página pública de preços
     */
    public function pricing() {
        // Carregar todos os planos ativos
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM plans WHERE active = 1 ORDER BY price ASC");
        $stmt->execute();
        $plans = $stmt->fetchAll();
        
        // Processar features e limites como arrays
        foreach ($plans as &$plan) {
            $plan['features_array'] = json_decode($plan['features'], true) ?: [];
            $plan['limits_array'] = json_decode($plan['limits'], true) ?: [];
        }
        
        // Exibir planos
        $pageTitle = 'Preços e Planos';
        require_once APP_PATH . '/views/subscription/pricing.php';
    }
}

// ESTE ARQUIVO AINDA PRECISA:
// - Implementar webhooks para processamento de eventos do Stripe
// - Melhorar tratamento de erros nas integrações com Stripe
// - Adicionar suporte para upgrades e downgrades de plano
// - Implementar notificações de renovação e falhas de pagamento
?>