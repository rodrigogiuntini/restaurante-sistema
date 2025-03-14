<?php
/**
 * Serviço para gerenciamento de assinaturas
 * 
 * Gerencia todo o ciclo de vida de assinaturas, incluindo criação,
 * atualização, cancelamento e processamento de pagamentos.
 * 
 * Status: 85% Completo
 * Pendente:
 * - Implementar métodos para análise de retenção
 * - Melhorar tratamento de tentativas de pagamento
 * - Adicionar notificações automáticas de eventos
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/StripeService.php';
require_once __DIR__ . '/../services/EmailService.php';

class SubscriptionService {
    private $db;
    private $stripeService;
    private $emailService;
    
    public function __construct() {
        global $conn;
        $this->db = $conn;
        $this->stripeService = new StripeService();
        $this->emailService = new EmailService();
    }
    
    /**
     * Cria uma nova assinatura para um cliente
     */
    public function createSubscription($tenantId, $planId, $paymentMethodId) {
        try {
            // Obter dados do locatário e plano
            $tenantQuery = "SELECT * FROM tenants WHERE id = ?";
            $stmt = $this->db->prepare($tenantQuery);
            $stmt->bind_param("i", $tenantId);
            $stmt->execute();
            $tenant = $stmt->get_result()->fetch_assoc();
            
            $planQuery = "SELECT * FROM plans WHERE id = ?";
            $stmt = $this->db->prepare($planQuery);
            $stmt->bind_param("i", $planId);
            $stmt->execute();
            $plan = $stmt->get_result()->fetch_assoc();
            
            // Verificar se o cliente já existe no Stripe ou criar um novo
            $stripeCustomerId = $tenant['stripe_customer_id'];
            if (!$stripeCustomerId) {
                $customer = $this->stripeService->createCustomer(
                    $tenant['email'], 
                    $tenant['name'],
                    $paymentMethodId
                );
                $stripeCustomerId = $customer->id;
                
                // Atualizar tenant com o ID do cliente Stripe
                $updateTenantQuery = "UPDATE tenants SET stripe_customer_id = ? WHERE id = ?";
                $stmt = $this->db->prepare($updateTenantQuery);
                $stmt->bind_param("si", $stripeCustomerId, $tenantId);
                $stmt->execute();
            } else {
                // Atualizar método de pagamento para o cliente existente
                $this->stripeService->updateCustomerPaymentMethod($stripeCustomerId, $paymentMethodId);
            }
            
            // Criar assinatura no Stripe
            $subscription = $this->stripeService->createSubscription(
                $stripeCustomerId,
                $plan['stripe_price_id']
            );
            
            // Registrar assinatura no banco de dados
            $status = 'active';
            if ($subscription->status === 'trialing') {
                $status = 'trial';
            }
            
            $insertQuery = "INSERT INTO subscriptions (tenant_id, plan_id, stripe_subscription_id, 
                             stripe_customer_id, status, trial_ends_at, next_billing_at) 
                             VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $trialEndsAt = null;
            if ($subscription->trial_end) {
                $trialEndsAt = date('Y-m-d H:i:s', $subscription->trial_end);
            }
            
            $nextBillingAt = date('Y-m-d H:i:s', $subscription->current_period_end);
            
            $stmt = $this->db->prepare($insertQuery);
            $stmt->bind_param("iissss", $tenantId, $planId, $subscription->id, 
                              $stripeCustomerId, $status, $trialEndsAt, $nextBillingAt);
            $stmt->execute();
            
            // Enviar email de confirmação de assinatura
            $this->emailService->sendSubscriptionConfirmationEmail($tenant['email'], [
                'tenant_name' => $tenant['name'],
                'plan_name' => $plan['name'],
                'amount' => $plan['price'],
                'next_billing_date' => $nextBillingAt,
                'is_trial' => ($status === 'trial')
            ]);
            
            return [
                'success' => true,
                'subscription_id' => $subscription->id,
                'status' => $status
            ];
        } catch (\Exception $e) {
            error_log('Erro ao criar assinatura: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Atualiza o status de uma assinatura existente
     */
    public function updateSubscriptionStatus($stripeCustomerId, $stripeSubscriptionId, $status) {
        try {
            // Mapear status do Stripe para status do sistema
            $systemStatus = 'active';
            switch ($status) {
                case 'trialing':
                    $systemStatus = 'trial';
                    break;
                case 'active':
                    $systemStatus = 'active';
                    break;
                case 'past_due':
                    $systemStatus = 'past_due';
                    break;
                case 'canceled':
                case 'unpaid':
                    $systemStatus = 'canceled';
                    break;
                default:
                    $systemStatus = 'suspended';
            }
            
            // Atualizar status no banco de dados
            $query = "UPDATE subscriptions SET status = ? WHERE stripe_customer_id = ? AND stripe_subscription_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("sss", $systemStatus, $stripeCustomerId, $stripeSubscriptionId);
            $result = $stmt->execute();
            
            // Obter dados do tenant para notificação
            $tenantQuery = "SELECT t.* FROM tenants t 
                           JOIN subscriptions s ON t.id = s.tenant_id 
                           WHERE s.stripe_customer_id = ?";
            $stmt = $this->db->prepare($tenantQuery);
            $stmt->bind_param("s", $stripeCustomerId);
            $stmt->execute();
            $tenant = $stmt->get_result()->fetch_assoc();
            
            // Enviar email de notificação sobre mudança de status
            if ($tenant) {
                $this->emailService->sendSubscriptionStatusUpdateEmail($tenant['email'], [
                    'tenant_name' => $tenant['name'],
                    'status' => $systemStatus,
                    'subscription_id' => $stripeSubscriptionId
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log('Erro ao atualizar status da assinatura: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ativa uma assinatura após a criação bem-sucedida
     */
    public function activateSubscription($stripeCustomerId, $stripeSubscriptionId) {
        // Obter detalhes da assinatura do Stripe
        $subscription = $this->stripeService->getSubscription($stripeSubscriptionId);
        
        // Determinar o status correto
        $status = 'active';
        if ($subscription->status === 'trialing') {
            $status = 'trial';
        }
        
        // Atualizar status e datas de billing
        $query = "UPDATE subscriptions 
                 SET status = ?, 
                     trial_ends_at = ?, 
                     next_billing_at = ? 
                 WHERE stripe_customer_id = ? AND stripe_subscription_id = ?";
                 
        $trialEndsAt = null;
        if ($subscription->trial_end) {
            $trialEndsAt = date('Y-m-d H:i:s', $subscription->trial_end);
        }
        
        $nextBillingAt = date('Y-m-d H:i:s', $subscription->current_period_end);
        
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("sssss", $status, $trialEndsAt, $nextBillingAt, 
                          $stripeCustomerId, $stripeSubscriptionId);
        return $stmt->execute();
    }
    
    /**
     * Cancela uma assinatura existente
     */
    public function cancelSubscription($stripeCustomerId, $stripeSubscriptionId) {
        try {
            // Atualizar status no banco de dados
            $query = "UPDATE subscriptions SET status = 'canceled', 
                     ends_at = CURRENT_TIMESTAMP 
                     WHERE stripe_customer_id = ? AND stripe_subscription_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("ss", $stripeCustomerId, $stripeSubscriptionId);
            $result = $stmt->execute();
            
            // Obter dados do tenant para notificação
            $tenantQuery = "SELECT t.* FROM tenants t 
                           JOIN subscriptions s ON t.id = s.tenant_id 
                           WHERE s.stripe_customer_id = ?";
            $stmt = $this->db->prepare($tenantQuery);
            $stmt->bind_param("s", $stripeCustomerId);
            $stmt->execute();
            $tenant = $stmt->get_result()->fetch_assoc();
            
            // Enviar email de confirmação de cancelamento
            if ($tenant) {
                $this->emailService->sendSubscriptionCancelledEmail($tenant['email'], [
                    'tenant_name' => $tenant['name']
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log('Erro ao cancelar assinatura: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra um pagamento bem-sucedido
     */
    public function recordSuccessfulPayment($stripeCustomerId, $invoiceId, $amount) {
        try {
            // Obter a assinatura relacionada
            $query = "SELECT * FROM subscriptions WHERE stripe_customer_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("s", $stripeCustomerId);
            $stmt->execute();
            $subscription = $stmt->get_result()->fetch_assoc();
            
            if (!$subscription) {
                throw new \Exception("Assinatura não encontrada para o cliente: " . $stripeCustomerId);
            }
            
            // Registrar a fatura
            $query = "INSERT INTO invoices (subscription_id, tenant_id, stripe_invoice_id, 
                     amount, status, paid_at) 
                     VALUES (?, ?, ?, ?, 'paid', CURRENT_TIMESTAMP)";
            $stmt = $this->db->prepare($query);
            $amountDecimal = $amount / 100; // Converter de centavos para valor decimal
            $stmt->bind_param("iis", $subscription['id'], $subscription['tenant_id'], 
                             $invoiceId, $amountDecimal);
            $stmt->execute();
            
            // Atualizar data do próximo billing
            $invoice = $this->stripeService->getInvoice($invoiceId);
            if ($invoice && $invoice->subscription) {
                $subscriptionObject = $this->stripeService->getSubscription($invoice->subscription);
                $nextBillingAt = date('Y-m-d H:i:s', $subscriptionObject->current_period_end);
                
                $updateQuery = "UPDATE subscriptions SET next_billing_at = ? 
                               WHERE id = ?";
                $stmt = $this->db->prepare($updateQuery);
                $stmt->bind_param("si", $nextBillingAt, $subscription['id']);
                $stmt->execute();
            }
            
            // Obter dados do tenant para notificação
            $tenantQuery = "SELECT * FROM tenants WHERE id = ?";
            $stmt = $this->db->prepare($tenantQuery);
            $stmt->bind_param("i", $subscription['tenant_id']);
            $stmt->execute();
            $tenant = $stmt->get_result()->fetch_assoc();
            
            // Enviar email de recibo
            if ($tenant) {
                $this->emailService->sendPaymentReceiptEmail($tenant['email'], [
                    'tenant_name' => $tenant['name'],
                    'amount' => $amountDecimal,
                    'invoice_id' => $invoiceId,
                    'payment_date' => date('Y-m-d H:i:s')
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('Erro ao registrar pagamento: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registra um pagamento falho
     */
    public function recordFailedPayment($stripeCustomerId, $invoiceId) {
        try {
            // Obter a assinatura relacionada
            $query = "SELECT * FROM subscriptions WHERE stripe_customer_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("s", $stripeCustomerId);
            $stmt->execute();
            $subscription = $stmt->get_result()->fetch_assoc();
            
            if (!$subscription) {
                throw new \Exception("Assinatura não encontrada para o cliente: " . $stripeCustomerId);
            }
            
            // Obter detalhes do invoice
            $invoice = $this->stripeService->getInvoice($invoiceId);
            $amountDecimal = $invoice->amount_due / 100;
            
            // Registrar a fatura
            $query = "INSERT INTO invoices (subscription_id, tenant_id, stripe_invoice_id, 
                     amount, status) 
                     VALUES (?, ?, ?, ?, 'uncollectible')";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("iisdd", $subscription['id'], $subscription['tenant_id'], 
                             $invoiceId, $amountDecimal);
            $stmt->execute();
            
            // Atualizar status da assinatura para past_due
            $updateQuery = "UPDATE subscriptions SET status = 'past_due' 
                           WHERE id = ?";
            $stmt = $this->db->prepare($updateQuery);
            $stmt->bind_param("i", $subscription['id']);
            $stmt->execute();
            
            // Obter dados do tenant para notificação
            $tenantQuery = "SELECT * FROM tenants WHERE id = ?";
            $stmt = $this->db->prepare($tenantQuery);
            $stmt->bind_param("i", $subscription['tenant_id']);
            $stmt->execute();
            $tenant = $stmt->get_result()->fetch_assoc();
            
            // Enviar email de falha de pagamento
            if ($tenant) {
                $this->emailService->sendPaymentFailedEmail($tenant['email'], [
                    'tenant_name' => $tenant['name'],
                    'amount' => $amountDecimal,
                    'invoice_id' => $invoiceId,
                    'retry_link' => 'https://app.restaurantesaas.com.br/subscription/retry-payment/' . $invoiceId
                ]);
            }
            
            return true;
        } catch (\Exception $e) {
            error_log('Erro ao registrar falha de pagamento: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Altera o plano de uma assinatura (upgrade/downgrade)
     */
    public function changePlan($tenantId, $newPlanId) {
        try {
            // Obter dados da assinatura atual
            $query = "SELECT * FROM subscriptions WHERE tenant_id = ? AND status != 'canceled'";
            $stmt = $this->db->prepare($query);
            $stmt->bind_param("i", $tenantId);
            $stmt->execute();
            $subscription = $stmt->get_result()->fetch_assoc();
            
            if (!$subscription) {
                throw new \Exception("Assinatura ativa não encontrada para o tenant ID: " . $tenantId);
            }
            
            // Obter dados do novo plano
            $planQuery = "SELECT * FROM plans WHERE id = ?";
            $stmt = $this->db->prepare($planQuery);
            $stmt->bind_param("i", $newPlanId);
            $stmt->execute();
            $newPlan = $stmt->get_result()->fetch_assoc();
            
            if (!$newPlan) {
                throw new \Exception("Plano não encontrado: " . $newPlanId);
            }
            
            // Atualizar assinatura no Stripe
            $this->stripeService->updateSubscriptionPlan(
                $subscription['stripe_subscription_id'],
                $newPlan['stripe_price_id']
            );
            
            // Atualizar assinatura no banco de dados
            $updateQuery = "UPDATE subscriptions SET plan_id = ? WHERE id = ?";
            $stmt = $this->db->prepare($updateQuery);
            $stmt->bind_param("ii", $newPlanId, $subscription['id']);
            $result = $stmt->execute();
            
            // Obter dados do tenant para notificação
            $tenantQuery = "SELECT * FROM tenants WHERE id = ?";
            $stmt = $this->db->prepare($tenantQuery);
            $stmt->bind_param("i", $tenantId);
            $stmt->execute();
            $tenant = $stmt->get_result()->fetch_assoc();
            
            // Enviar email de confirmação de mudança de plano
            if ($tenant) {
                $this->emailService->sendPlanChangeEmail($tenant['email'], [
                    'tenant_name' => $tenant['name'],
                    'plan_name' => $newPlan['name'],
                    'amount' => $newPlan['price'],
                    'effective_date' => date('Y-m-d')
                ]);
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log('Erro ao mudar plano: ' . $e->getMessage());
            return false;
        }
    }
    
    // TODO: Implementar métodos para análise de retenção
    // TODO: Melhorar tratamento de tentativas de pagamento
    // TODO: Adicionar notificações automáticas de eventos
}