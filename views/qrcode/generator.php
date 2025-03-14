<?php
/**
 * View para geração de QR Codes
 * 
 * Esta view permite a geração de QR Codes para mesas,
 * cardápio geral e outros usos no restaurante.
 * 
 * Status: 85% Completo
 * Pendente:
 * - Implementar geração em lote para múltiplas mesas
 * - Adicionar opções de personalização avançadas
 */

// Incluir cabeçalho
require_once __DIR__ . '/../partials/header.php';
?>

<div class="content-wrapper">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="page-title-box">
                    <h4 class="page-title">Gerador de QR Codes</h4>
                    <div class="breadcrumb">
                        <a href="/dashboard" class="breadcrumb-item">Dashboard</a>
                        <a href="/qrcode/manager" class="breadcrumb-item">QR Codes</a>
                        <span class="breadcrumb-item active">Gerador</span>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">QR Code para Mesa</h5>
                    </div>
                    <div class="card-body">
                        <p>Gere QR Codes específicos para mesas do seu restaurante. Os clientes poderão escanear para acessar o cardápio e fazer pedidos diretamente da mesa.</p>
                        
                        <form action="/qrcode/generate-table" method="post">
                            <div class="form-group">
                                <label for="table_id">Selecione a Mesa</label>
                                <select name="table_id" id="table_id" class="form-control" required>
                                    <option value="">Selecione...</option>
                                    <?php foreach ($tables as $table): ?>
                                        <?php 
                                            $tableName = $table['name'] ? $table['name'] : 'Mesa ' . $table['number'];
                                            $hasQrCode = !empty($table['qr_code']);
                                        ?>
                                        <option value="<?php echo $table['id']; ?>">
                                            <?php echo htmlspecialchars($tableName); ?>
                                            <?php if ($hasQrCode): ?> (QR Code já existente)<?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Se a mesa já possui um QR Code, ele será substituído por um novo.</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Gerar QR Code</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">QR Code para Cardápio</h5>
                    </div>
                    <div class="card-body">
                        <p>Gere QR Codes gerais para seu cardápio. Ideal para usar em materiais de marketing, redes sociais ou na entrada do restaurante.</p>
                        
                        <form action="/qrcode/generate-menu" method="post">
                            <div class="form-group">
                                <label for="menu_name">Nome do Cardápio (opcional)</label>
                                <input type="text" name="menu_name" id="menu_name" class="form-control" placeholder="Ex: Cardápio Principal, Menu Almoço, etc.">
                                <small class="form-text text-muted">Este nome será usado apenas para identificação interna.</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Gerar QR Code</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">QR Codes Adicionais</h5>
                    </div>
                    <div class="card-body">
                        <p>Outros tipos de QR Codes úteis para seu restaurante:</p>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card border">
                                    <div class="card-body text-center">
                                        <h5>QR Code para Pagamento</h5>
                                        <p>Os QR Codes de pagamento são gerados automaticamente ao criar um novo pedido.</p>
                                        <a href="/orders" class="btn btn-outline-primary">Ir para Pedidos</a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card border">
                                    <div class="card-body text-center">
                                        <h5>QR Code para Feedback</h5>
                                        <p>Solicite feedback dos clientes sobre sua experiência.</p>
                                        <!-- TODO: Implementar geração de QR Code para feedback -->
                                        <button class="btn btn-outline-secondary" disabled>Em breve</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="card border">
                                    <div class="card-body text-center">
                                        <h5>QR Code para Reservas</h5>
                                        <p>Permita que clientes façam reservas facilmente.</p>
                                        <!-- TODO: Implementar geração de QR Code para reservas -->
                                        <button class="btn btn-outline-secondary" disabled>Em breve</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Alerta para mesas que já têm QR Code
        const tableSelect = document.getElementById('table_id');
        
        tableSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.textContent.includes('QR Code já existente')) {
                const tableName = selectedOption.textContent.replace(' (QR Code já existente)', '');
                if (confirm(`A mesa "${tableName}" já possui um QR Code. Deseja substituí-lo por um novo?`)) {
                    // Continuar normalmente
                } else {
                    // Resetar seleção
                    this.selectedIndex = 0;
                }
            }
        });
    });
</script>

<?php
// Incluir rodapé
require_once __DIR__ . '/../partials/footer.php';
?>