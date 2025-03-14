<?php
/**
 * Webhook do Stripe para processamento de eventos de assinatura
 * 
 * Este arquivo processa eventos enviados pelo Stripe como pagamentos,
 * falhas, atualizações de assinatura, etc.
 * 
 * Status: 80% Completo
 * Pendente: 
 * - Tratamento de disputas e reembolsos
 * - Logs detalhados de eventos processados
 */

// Incluir arquivos necessários
require_once '../../config/constants.php';
require_once '../../config/database.php';
require_once '../../config/stripe.php';
require_once '../../includes/functions.php';
require_once '../../services/StripeService.php';
require_once '../../services/SubscriptionService.php';

// Configuração para receber webhook
header('Content-Type: application/json');

try {
    // Recuperar payload e verificar assinatura
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
    
    $stripeService = new StripeService();
    $event = $stripeService->constructWebhookEvent($payload, $sig_header);
    
    // Processar o evento com base no tipo
    switch ($event->type) {
        case 'customer.subscription.created':
            $subscription = $event->data->object;
            $stripeCustomerId = $subscription->customer;
            $subscriptionService = new SubscriptionService();
            $subscriptionService->activateSubscription($stripeCustomerId, $subscription->id);
            break;
            
        case 'customer.subscription.updated':
            $subscription = $event->data->object;
            $stripeCustomerId = $subscription->customer;
            $subscriptionService = new SubscriptionService();
            $subscriptionService->updateSubscriptionStatus($stripeCustomerId, $subscription->id, $subscription->status);
            break;
            
        case 'customer.subscription.deleted':
            $subscription = $event->data->object;
            $stripeCustomerId = $subscription->customer;
            $subscriptionService = new SubscriptionService();
            $subscriptionService->cancelSubscription($stripeCustomerId, $subscription->id);
            break;
            
        case 'invoice.payment_succeeded':
            $invoice = $event->data->object;
            $stripeCustomerId = $invoice->customer;
            $subscriptionService = new SubscriptionService();
            $subscriptionService->recordSuccessfulPayment($stripeCustomerId, $invoice->id, $invoice->amount_paid);
            break;
            
        case 'invoice.payment_failed':
            $invoice = $event->data->object;
            $stripeCustomerId = $invoice->customer;
            $subscriptionService = new SubscriptionService();
            $subscriptionService->recordFailedPayment($stripeCustomerId, $invoice->id);
            break;
            
        // TODO: Implementar tratamento para disputas e reembolsos
        // case 'charge.dispute.created':
        // case 'charge.refunded':
            
        default:
            // Registrar evento não tratado para fins de debug
            error_log('Evento Stripe não tratado: ' . $event->type);
            break;
    }
    
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (\Exception $e) {
    error_log('Erro no webhook do Stripe: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}