<?php
/**
 * Serviço de integração com Stripe
 * Responsável por gerenciar pagamentos, assinaturas e webhooks
 */

class StripeService {
    private $stripe;
    
    /**
     * Construtor
     */
    public function __construct() {
        // Carregar a biblioteca do Stripe
        // Em um projeto real, instalar via Composer: composer require stripe/stripe-php
        require_once APP_PATH . '/vendor/stripe/stripe-php/init.php';
        
        // Configurar chave de API
        $apiKey = getenv('STRIPE_SECRET_KEY') ?: 'sk_test_your_key';
        \Stripe\Stripe::setApiKey($apiKey);
        
        $this->stripe = new \Stripe\StripeClient($apiKey);
    }
    
    /**
     * Cria um cliente no Stripe
     */
    public function createCustomer($data) {
        try {
            return $this->stripe->customers->create([
                'email' => $data['email'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? null
            ]);
        } catch (\Exception $e) {
            $this->logError('createCustomer', $e->getMessage(), $data);
            throw new \Exception('Erro ao criar cliente: ' . $e->getMessage());
        }
    }
    
    /**
     * Cria uma sessão de checkout
     */
    public function createCheckoutSession($data) {
        try {
            return $this->stripe->checkout->sessions->create([
                'customer' => $data['customer'],
                'success_url' => $data['success_url'],
                'cancel_url' => $data['cancel_url'],
                'payment_method_types' => $data['payment_method_types'],
                'mode' => $data['mode'],
                'line_items' => $data['line_items'],
                'metadata' => $data['metadata'] ?? null
            ]);
        } catch (\Exception $e) {
            $this->logError('createCheckoutSession', $e->getMessage(), $data);
            throw new \Exception('Erro ao criar sessão de checkout: ' . $e->getMessage());
        }
    }
    
    /**
     * Recupera uma sessão de checkout
     */
    public function getCheckoutSession($sessionId) {
        try {
            return $this->stripe->checkout->sessions->retrieve($sessionId);
        } catch (\Exception $e) {
            $this->logError('getCheckoutSession', $e->getMessage(), ['session_id' => $sessionId]);
            throw new \Exception('Erro ao recuperar sessão de checkout: ' . $e->getMessage());
        }
    }
    
    /**
     * Cria uma sessão do portal de faturamento
     */
    public function createBillingPortalSession($data) {
        try {
            return $this->stripe->billingPortal->sessions->create([
                'customer' => $data['customer'],
                'return_url' => $data['return_url']
            ]);
        } catch (\Exception $e) {
            $this->logError('createBillingPortalSession', $e->getMessage(), $data);
            throw new \Exception('Erro ao criar sessão do portal de faturamento: ' . $e->getMessage());
        }
    }
    
    /**
     * Cancela uma assinatura
     */
    public function cancelSubscription($subscriptionId) {
        try {
            return $this->stripe->subscriptions->cancel($subscriptionId);
        } catch (\Exception $e) {
            $this->logError('cancelSubscription', $e->getMessage(), ['subscription_id' => $subscriptionId]);
            throw new \Exception('Erro ao cancelar assinatura: ' . $e->getMessage());
        }
    }
    
    /**
     * Cria um produto no Stripe
     */
    public function createProduct($data) {
        try {
            return $this->stripe->products->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'metadata' => $data['metadata'] ?? null
            ]);
        } catch (\Exception $e) {
            $this->logError('createProduct', $e->getMessage(), $data);
            throw new \Exception('Erro ao criar produto: ' . $e->getMessage());
        }
    }
    
    /**
     * Cria um preço no Stripe
     */
    public function createPrice($data) {
        try {
            return $this->stripe->prices->create([
                'product' => $data['product'],
                'unit_amount' => $data['unit_amount'],
                'currency' => $data['currency'] ?? 'brl',
                'recurring' => $data['recurring'] ?? null,
                'metadata' => $data['metadata'] ?? null
            ]);
        } catch (\Exception $e) {
            $this->logError('createPrice', $e->getMessage(), $data);
            throw new \Exception('Erro ao criar preço: ' . $e->getMessage());
        }
    }
    
    /**
     * Processa um webhook do Stripe
     */
    public function handleWebhook($payload, $sigHeader) {
        $webhookSecret = getenv('STRIPE_WEBHOOK_SECRET') ?: 'whsec_your_key';
        
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $webhookSecret
            );
            
            // Log do evento recebido
            $this->logWebhook($event->type, $event->data->object);
            
            // Processar o evento
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    break;
                
                case 'invoice.paid':
                    $this->handleInvoicePaid($event->data->object);
                    break;
                
                case 'invoice.payment_failed':
                    $this->handleInvoicePaymentFailed($event->data->object);
                    break;
                
                case 'customer.subscription.updated':
                    $this->handleSubscriptionUpdated($event->data->object);
                    break;
                
                case 'customer.subscription.deleted':
                    $this->handleSubscriptionDeleted($event->data->object);
                    break;
                
                default:
                    // Evento não tratado
                    break;
            }
            
            return true;
        } catch (\UnexpectedValueException $e) {
            // Payload inválido
            $this->logError('handleWebhook', 'Invalid payload: ' . $e->getMessage(), []);
            throw new \Exception('Webhook Error: ' . $e->getMessage());
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Assinatura inválida
            $this->logError('handleWebhook', 'Invalid signature: ' . $e->getMessage(), []);
            throw new \Exception('Webhook Error: ' . $e->getMessage());
        } catch (\Exception $e) {
            // Erro genérico
            $this->logError('handleWebhook', 'Generic error: ' . $e->getMessage(), []);
            throw new \Exception('Webhook Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Processa o evento checkout.session.completed
     */
    private function handleCheckoutSessionCompleted($session) {
        // Implementação simplificada
        // Em produção, sincronizar com o banco de dados
    }
    
    /**
     * Processa o evento invoice.paid
     */
    private function handleInvoicePaid($invoice) {
        // Implementação simplificada
        // Em produção, registrar fatura paga
    }
    
    /**
     * Processa o evento invoice.payment_failed
     */
    private function handleInvoicePaymentFailed($invoice) {
        // Implementação simplificada
        // Em produção, notificar usuário
    }
    
    /**
     * Processa o evento customer.subscription.updated
     */
    private function handleSubscriptionUpdated($subscription) {
        // Implementação simplificada
        // Em produção, atualizar status da assinatura
    }
    
    /**
     * Processa o evento customer.subscription.deleted
     */
    private function handleSubscriptionDeleted($subscription) {
        // Implementação simplificada
        // Em produção, marcar assinatura como cancelada
    }
    
    /**
     * Registra um erro em log
     */
    private function logError($method, $message, $data) {
        $logFile = APP_PATH . '/logs/stripe_errors.log';
        
        // Garantir que o diretório de logs existe
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        // Formatar dados para log
        $dataString = json_encode($data);
        
        // Formato do log
        $log = date('Y-m-d H:i:s') . " | Method: {$method} | Error: {$message} | Data: {$dataString}\n";
        
        // Adicionar ao arquivo de log
        file_put_contents($logFile, $log, FILE_APPEND);
    }
    
    /**
     * Registra um webhook em log
     */
    private function logWebhook($eventType, $object) {
        $logFile = APP_PATH . '/logs/stripe_webhooks.log';
        
        // Garantir que o diretório de logs existe
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }
        
        // Formatar objeto para log
        $objectString = json_encode($object);
        
        // Formato do log
        $log = date('Y-m-d H:i:s') . " | Event: {$eventType} | Object: {$objectString}\n";
        
        // Adicionar ao arquivo de log
        file_put_contents($logFile, $log, FILE_APPEND);
    }
}

// ESTE ARQUIVO AINDA PRECISA:
// - Implementar funções para lidar com todos os eventos do Stripe
// - Adicionar suporte para manuseio de disputas e reembolsos
// - Melhorar tratamento de erros e retry de operações
// - Implementar cache para evitar chamadas repetidas à API
?>