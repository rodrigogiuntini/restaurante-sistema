<?php
/**
 * Funções auxiliares para assinaturas
 * Facilita a manipulação e verificação de assinaturas no sistema
 */

/**
 * Retorna o status da assinatura atual do tenant como texto
 */
function getSubscriptionStatusText($status) {
    $statusTexts = [
        'trial' => 'Período de Teste',
        'active' => 'Ativa',
        'past_due' => 'Pagamento Pendente',
        'canceled' => 'Cancelada',
        'suspended' => 'Suspensa'
    ];
    
    return $statusTexts[$status] ?? $status;
}

/**
 * Retorna a classe CSS para o status da assinatura
 */
function getSubscriptionStatusClass($status) {
    $statusClasses = [
        'trial' => 'info',
        'active' => 'success',
        'past_due' => 'warning',
        'canceled' => 'danger',
        'suspended' => 'danger'
    ];
    
    return $statusClasses[$status] ?? 'secondary';
}

/**
 * Verifica se a assinatura está em período de teste
 */
function isSubscriptionTrial() {
    $featureChecker = new FeatureChecker();
    return $featureChecker->getSubscriptionStatus() === 'trial';
}

/**
 * Retorna o número de dias restantes no período de teste
 */
function getRemainingTrialDays() {
    $featureChecker = new FeatureChecker();
    $subscription = $featureChecker->getSubscriptionData();
    
    if (!$subscription || $subscription['status'] !== 'trial') {
        return 0;
    }
    
    $trialEndsAt = strtotime($subscription['trial_ends_at']);
    $now = time();
    
    if ($trialEndsAt <= $now) {
        return 0;
    }
    
    return ceil(($trialEndsAt - $now) / 86400); // 86400 segundos = 1 dia
}

/**
 * Retorna o nome do plano de assinatura formatado para exibição
 */
function getFormattedPlanName($planCode) {
    $planNames = [
        'basic' => 'Plano Básico',
        'standard' => 'Plano Padrão',
        'premium' => 'Plano Premium',
        'enterprise' => 'Plano Enterprise'
    ];
    
    return $planNames[$planCode] ?? $planCode;
}

/**
 * Traduz uma feature de plano para texto amigável
 */
function translateFeature($featureCode) {
    $featureTranslations = [
        'qrcode_basic' => 'QR Code básico para mesas',
        'qrcode_advanced' => 'QR Code avançado com personalização',
        'basic_reports' => 'Relatórios básicos',
        'full_reports' => 'Relatórios completos e análises',
        'inventory_management' => 'Gerenciamento de estoque',
        'multi_branch' => 'Suporte para múltiplas unidades',
        'loyalty_program' => 'Programa de fidelidade',
        'api_access' => 'Acesso à API',
        'custom_integrations' => 'Integrações personalizadas',
        'advanced_analytics' => 'Analytics avançado',
        'staff_management' => 'Gestão de funcionários',
        'supplier_management' => 'Gestão de fornecedores',
        'marketing_tools' => 'Ferramentas de marketing'
    ];
    
    return $featureTranslations[$featureCode] ?? $featureCode;
}

/**
 * Verifica se o tenant tem acesso a um recurso específico
 */
function hasAccess($feature) {
    $featureChecker = new FeatureChecker();
    return $featureChecker->hasFeature($feature);
}

/**
 * Verifica se o tenant atingiu o limite para um recurso específico
 */
function hasReachedLimit($resource, $currentValue = null) {
    $featureChecker = new FeatureChecker();
    return $featureChecker->hasReachedLimit($resource, $currentValue);
}

/**
 * Registra o uso de um recurso para o tenant
 */
function recordResourceUsage($resourceType, $count = 1) {
    $featureChecker = new FeatureChecker();
    return $featureChecker->recordResourceUsage($resourceType, $count);
}

/**
 * Exibe um badge HTML para o status da assinatura
 */
function subscriptionStatusBadge($status) {
    $class = getSubscriptionStatusClass($status);
    $text = getSubscriptionStatusText($status);
    
    return '<span class="badge badge-' . $class . '">' . $text . '</span>';
}

/**
 * Verifica se o usuário pode fazer upgrade para um plano específico
 */
function canUpgradeToPlan($targetPlanCode) {
    $featureChecker = new FeatureChecker();
    $subscription = $featureChecker->getSubscriptionData();
    
    if (!$subscription) {
        return true; // Sem assinatura, pode assinar qualquer plano
    }
    
    // Ordenar planos por nível
    $planHierarchy = [
        'basic' => 1,
        'standard' => 2,
        'premium' => 3,
        'enterprise' => 4
    ];
    
    $currentPlanLevel = $planHierarchy[$subscription['plan_code']] ?? 0;
    $targetPlanLevel = $planHierarchy[$targetPlanCode] ?? 0;
    
    return $targetPlanLevel > $currentPlanLevel;
}

/**
 * Verifica se o usuário pode fazer downgrade para um plano específico
 */
function canDowngradeToPlan($targetPlanCode) {
    $featureChecker = new FeatureChecker();
    $subscription = $featureChecker->getSubscriptionData();
    
    if (!$subscription) {
        return false; // Sem assinatura, não pode fazer downgrade
    }
    
    // Ordenar planos por nível
    $planHierarchy = [
        'basic' => 1,
        'standard' => 2,
        'premium' => 3,
        'enterprise' => 4
    ];
    
    $currentPlanLevel = $planHierarchy[$subscription['plan_code']] ?? 0;
    $targetPlanLevel = $planHierarchy[$targetPlanCode] ?? 0;
    
    return $targetPlanLevel < $currentPlanLevel && $targetPlanLevel > 0;
}

// ARQUIVO COMPLETO E CORRETO