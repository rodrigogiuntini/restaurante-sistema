<?php
// Dashboard genérico para todos os tipos de restaurante
$pageTitle = 'Dashboard';
$extraCss = ['dashboard.css'];
$extraJs = ['dashboard.js', 'charts.js'];
require_once APP_PATH . '/views/partials/header.php';
?>

<div class="dashboard-container">
    <div class="container">
        <div class="dashboard-header">
            <h1>Dashboard</h1>
            <p>Bem-vindo(a), <?= htmlspecialchars($_SESSION['name']) ?>!</p>
        </div>
        
        <div class="stats-overview">
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card orders">
                        <div class="stats-icon">
                            <i class="icon-orders"></i>
                        </div>
                        <div class="stats-info">
                            <h3>Pedidos Hoje</h3>
                            <p class="stats-value"><?= $stats['ordersToday'] ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card sales">
                        <div class="stats-icon">
                            <i class="icon-sales"></i>
                        </div>
                        <div class="stats-info">
                            <h3>Vendas Hoje</h3>
                            <p class="stats-value"><?= formatMoney($stats['salesTotal']) ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card tables">
                        <div class="stats-icon">
                            <i class="icon-tables"></i>
                        </div>
                        <div class="stats-info">
                            <h3>Mesas Ocupadas</h3>
                            <p class="stats-value"><?= $stats['occupiedTables'] ?> / <?= $stats['totalTables'] ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 col-sm-6">
                    <div class="stats-card pending">
                        <div class="stats-icon">
                            <i class="icon-pending"></i>
                        </div>
                        <div class="stats-info">
                            <h3>Pedidos Pendentes</h3>
                            <p class="stats-value"><?= $stats['pendingOrders'] ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <div class="dashboard-card">
                    <h3>Ações Rápidas</h3>
                    <div class="quick-actions">
                        <a href="<?= APP_URL ?>/orders/new" class="quick-action-btn">
                            <i class="icon-new-order"></i>
                            Novo Pedido
                        </a>
                        <a href="<?= APP_URL ?>/tables" class="quick-action-btn">
                            <i class="icon-tables"></i>
                            Mesas
                        </a>
                        <a href="<?= APP_URL ?>/kitchen" class="quick-action-btn">
                            <i class="icon-kitchen"></i>
                            Cozinha
                        </a>
                        <a href="<?= APP_URL ?>/payments" class="quick-action-btn">
                            <i class="icon-payment"></i>
                            Pagamentos
                        </a>
                        <a href="<?= APP_URL ?>/menu" class="quick-action-btn">
                            <i class="icon-menu"></i>
                            Cardápio
                        </a>
                        <a href="<?= APP_URL ?>/reports/daily" class="quick-action-btn">
                            <i class="icon-report"></i>
                            Relatório do Dia
                        </a>
                        <a href="<?= APP_URL ?>/qrcode/generator" class="quick-action-btn">
                            <i class="icon-qrcode"></i>
                            QR Codes
                        </a>
                        <a href="<?= APP_URL ?>/settings" class="quick-action-btn">
                            <i class="icon-settings"></i>
                            Configurações
                        </a>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <h3>Desempenho de Vendas</h3>
                    <div class="sales-chart-container" style="height: 300px;">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="dashboard-card">
                    <h3>Informações do Plano</h3>
                    <?php if (isset($subscription)): ?>
                        <div class="plan-info">
                            <p><strong>Plano Atual:</strong> <?= $subscription['plan_name'] ?></p>
                            <p><strong>Status:</strong> 
                                <?php if ($subscription['status'] === 'active'): ?>
                                    <span class="badge badge-success">Ativo</span>
                                <?php elseif ($subscription['status'] === 'trial'): ?>
                                    <span class="badge badge-info">Período de Teste</span>
                                <?php else: ?>
                                    <span class="badge badge-warning"><?= ucfirst($subscription['status']) ?></span>
                                <?php endif; ?>
                            </p>
                            <?php if ($subscription['status'] === 'trial'): ?>
                                <p><strong>Período de Teste:</strong> Termina em <?= date('d/m/Y', strtotime($subscription['trial_ends_at'])) ?></p>
                            <?php endif; ?>
                            <p><strong>Próxima Cobrança:</strong> <?= date('d/m/Y', strtotime($subscription['next_billing_at'])) ?></p>
                            <a href="<?= APP_URL ?>/subscription/manage" class="btn btn-primary btn-sm">Gerenciar Assinatura</a>
                        </div>
                    <?php else: ?>
                        <div class="plan-info">
                            <p>Você não possui um plano ativo.</p>
                            <a href="<?= APP_URL ?>/subscription/plans" class="btn btn-primary btn-sm">Ver Planos Disponíveis</a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="dashboard-card">
                    <h3>Distribuição de Pedidos</h3>
                    <div class="orders-chart-container" style="height: 200px;">
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <h3>Notificações</h3>
                    <div class="notifications-list">
                        <div class="notification-item">
                            <i class="icon-info"></i>
                            <div class="notification-content">
                                <p>Nova atualização disponível para o sistema.</p>
                                <small>Hoje, 10:30</small>
                            </div>
                        </div>
                        <div class="notification-item">
                            <i class="icon-warning"></i>
                            <div class="notification-content">
                                <p>Estoque baixo para alguns ingredientes.</p>
                                <small>Ontem, 15:45</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-12">
                <div class="dashboard-card">
                    <h3>Últimos Pedidos</h3>
                    <div class="recent-orders">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Nº</th>
                                        <th>Mesa</th>
                                        <th>Cliente</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Hora</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Dados serão carregados via AJAX -->
                                    <tr>
                                        <td colspan="7" class="text-center">Carregando últimos pedidos...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Exemplo de carregamento de últimos pedidos
        fetch('<?= APP_URL ?>/api/orders/recent')
            .then(response => response.json())
            .then(data => {
                const container = document.querySelector('.recent-orders table tbody');
                if (data && data.length > 0) {
                    let html = '';
                    
                    data.forEach(order => {
                        html += `<tr>
                            <td>${order.order_number}</td>
                            <td>${order.table_id ? order.table_id : 'N/A'}</td>
                            <td>${order.customer_name ? order.customer_name : 'Cliente não identificado'}</td>
                            <td>${formatMoney(order.total)}</td>
                            <td><span class="status status-${order.status.toLowerCase()}">${translateStatus(order.status)}</span></td>
                            <td>${formatDateTime(order.started_at)}</td>
                            <td>
                                <a href="<?= APP_URL ?>/orders/view/${order.id}" class="btn btn-sm btn-primary">Ver</a>
                            </td>
                        </tr>`;
                    });
                    
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<tr><td colspan="7" class="text-center">Nenhum pedido encontrado.</td></tr>';
                }
            })
            .catch(error => {
                console.error('Erro ao carregar pedidos:', error);
                document.querySelector('.recent-orders table tbody').innerHTML = 
                    '<tr><td colspan="7" class="text-center">Erro ao carregar pedidos. Tente novamente mais tarde.</td></tr>';
            });
        
        // Função para formatar data/hora
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }
        
        // Função para traduzir status
        function translateStatus(status) {
            const translations = {
                'new': 'Novo',
                'pending': 'Pendente',
                'preparing': 'Preparando',
                'ready': 'Pronto',
                'delivered': 'Entregue',
                'cancelled': 'Cancelado'
            };
            
            return translations[status.toLowerCase()] || status;
        }
        
        // Inicializar gráficos
        initCharts();
    });
    
    function initCharts() {
        // Gráfico de vendas
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'],
                datasets: [{
                    label: 'Vendas da Semana',
                    data: [1200, 1900, 1500, 2000, 2400, 3000, 2500],
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderColor: '#3498db',
                    borderWidth: 2,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value;
                            }
                        }
                    }
                }
            }
        });
        
        // Gráfico de distribuição de pedidos
        const ordersCtx = document.getElementById('ordersChart').getContext('2d');
        const ordersChart = new Chart(ordersCtx, {
            type: 'doughnut',
            data: {
                labels: ['No Local', 'Delivery', 'Pickup'],
                datasets: [{
                    data: [65, 25, 10],
                    backgroundColor: ['#3498db', '#2ecc71', '#f39c12'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
</script>

<?php require_once APP_PATH . '/views/partials/footer.php'; ?>

<!-- ESTE ARQUIVO AINDA PRECISA:
     - Implementar carregamento real de dados para os gráficos
     - Ajustar para diferentes tipos de restaurante
     - Adicionar widgets personalizáveis pelo usuário
-->