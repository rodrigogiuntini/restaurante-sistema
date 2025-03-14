<?php
// Definir título da página
$pageTitle = 'Planos de Assinatura';
$extraCss = ['subscription.css'];
require_once APP_PATH . '/views/partials/header.php';
?>

<div class="subscription-container">
    <div class="container">
        <div class="page-header">
            <h1>Escolha seu Plano</h1>
            <p>Selecione o plano ideal para seu restaurante</p>
        </div>
        
        <?php if (hasFlashMessage('error')): ?>
            <div class="alert alert-danger">
                <?= getFlashMessage('error') ?>
            </div>
        <?php endif; ?>
        
        <?php if (hasFlashMessage('success')): ?>
            <div class="alert alert-success">
                <?= getFlashMessage('success') ?>
            </div>
        <?php endif; ?>
        
        <div class="plans-grid">
            <?php foreach ($plans as $plan): ?>
                <?php 
                // Decodificar features e limites
                $features = json_decode($plan['features'], true) ?: [];
                $limits = json_decode($plan['limits'], true) ?: [];
                
                // Determinar classe CSS para destacar plano recomendado
                $planClass = '';
                if ($plan['code'] === 'standard') {
                    $planClass = 'plan-recommended';
                } elseif ($plan['code'] === 'premium') {
                    $planClass = 'plan-premium';
                }
                ?>
                
                <div class="plan-card <?= $planClass ?>">
                    <?php if ($plan['code'] === 'standard'): ?>
                        <div class="plan-badge">Recomendado</div>
                    <?php endif; ?>
                    
                    <div class="plan-header">
                        <h2><?= htmlspecialchars($plan['name']) ?></h2>
                        <div class="plan-price">
                            <span class="currency">R$</span>
                            <span class="amount"><?= number_format($plan['price'], 2, ',', '.') ?></span>
                            <span class="period">/mês</span>
                        </div>
                        <p class="plan-description"><?= htmlspecialchars($plan['description']) ?></p>
                    </div>
                    
                    <div class="plan-features">
                        <ul>
                            <?php if (isset($limits['max_tables'])): ?>
                                <li>
                                    <i class="icon-check"></i>
                                    <?= $limits['max_tables'] === -1 ? 'Mesas ilimitadas' : "Até {$limits['max_tables']} mesas" ?>
                                </li>
                            <?php endif; ?>
                            
                            <?php if (isset($limits['max_users'])): ?>
                                <li>
                                    <i class="icon-check"></i>
                                    <?= $limits['max_users'] === -1 ? 'Usuários ilimitados' : "Até {$limits['max_users']} usuários" ?>
                                </li>
                            <?php endif; ?>
                            
                            <?php if (isset($limits['max_menu_items'])): ?>
                                <li>
                                    <i class="icon-check"></i>
                                    <?= $limits['max_menu_items'] === -1 ? 'Itens de cardápio ilimitados' : "Até {$limits['max_menu_items']} itens no cardápio" ?>
                                </li>
                            <?php endif; ?>
                            
                            <?php if (isset($limits['max_monthly_orders'])): ?>
                                <li>
                                    <i class="icon-check"></i>
                                    <?= $limits['max_monthly_orders'] === -1 ? 'Pedidos ilimitados' : "Até {$limits['max_monthly_orders']} pedidos/mês" ?>
                                </li>
                            <?php endif; ?>
                            
                            <?php foreach ($features as $feature): ?>
                                <li>
                                    <i class="icon-check"></i>
                                    <?= $this->translateFeature($feature) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <div class="plan-footer">
                        <a href="<?= APP_URL ?>/subscription/checkout/<?= $plan['code'] ?>" class="btn btn-primary">
                            Escolher Plano
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="subscription-info">
            <h3>Todos os planos incluem:</h3>
            <ul class="benefits-list">
                <li><i class="icon-check"></i> Hospedagem incluída</li>
                <li><i class="icon-check"></i> Suporte técnico</li>
                <li><i class="icon-check"></i> Atualizações gratuitas</li>
                <li><i class="icon-check"></i> Backup diário</li>
                <li><i class="icon-check"></i> Período de teste gratuito de <?= TRIAL_PERIOD_DAYS ?> dias</li>
                <li><i class="icon-check"></i> Cancelamento a qualquer momento</li>
            </ul>
            
            <div class="faq-section">
                <h3>Perguntas Frequentes</h3>
                
                <div class="faq-item">
                    <h4>O que acontece após o período de teste?</h4>
                    <p>Após o período de teste de <?= TRIAL_PERIOD_DAYS ?> dias, sua assinatura será automaticamente convertida para o plano escolhido e seu cartão será cobrado. Você receberá uma notificação antes do término do período de teste.</p>
                </div>
                
                <div class="faq-item">
                    <h4>Posso mudar de plano depois?</h4>
                    <p>Sim, você pode fazer upgrade ou downgrade de plano a qualquer momento. Ao fazer upgrade, a diferença será cobrada proporcionalmente ao tempo restante do ciclo de faturamento atual. Ao fazer downgrade, o novo valor será aplicado no próximo ciclo.</p>
                </div>
                
                <div class="faq-item">
                    <h4>Como funciona o cancelamento?</h4>
                    <p>Você pode cancelar sua assinatura a qualquer momento. Após o cancelamento, você continuará tendo acesso ao sistema até o final do período pago atual. Não fazemos reembolsos proporcionais para períodos parciais.</p>
                </div>
                
                <div class="faq-item">
                    <h4>Preciso fornecer um cartão de crédito para o teste gratuito?</h4>
                    <p>Sim, solicitamos os dados do cartão de crédito para evitar abusos do período de teste, mas você não será cobrado até o término dos <?= TRIAL_PERIOD_DAYS ?> dias. Você pode cancelar a qualquer momento durante o período de teste sem nenhuma cobrança.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once APP_PATH . '/views/partials/footer.php'; ?>

<!-- ARQUIVO PRECISA SER COMPLETADO:
     - Adicionar tradução de features (método translateFeature)
     - Melhorar exibição dos limites de forma mais amigável
     - Adicionar suporte para promoções e descontos
-->