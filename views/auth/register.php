<?php
// Definir título da página
$pageTitle = 'Criar Conta';
$hideNavbar = true;
require_once APP_PATH . '/views/partials/header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <div class="logo-container">
            <img src="<?= ASSETS_URL ?>/images/logo/logo.png" alt="<?= APP_NAME ?>" class="auth-logo">
        </div>
        
        <h2>Criar Conta</h2>
        
        <?php if (hasFlashMessage('error')): ?>
            <div class="alert alert-danger">
                <?= getFlashMessage('error') ?>
            </div>
        <?php endif; ?>
        
        <form action="<?= APP_URL ?>/auth/register" method="post" id="registerForm">
            <div class="form-group">
                <label for="name">Nome Completo</label>
                <input type="text" name="name" id="name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" name="password" id="password" class="form-control" required minlength="8">
                <small class="form-text text-muted">A senha deve ter pelo menos 8 caracteres.</small>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Confirmar Senha</label>
                <input type="password" name="password_confirm" id="password_confirm" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>
                    <input type="checkbox" name="terms" id="terms" required> 
                    Concordo com os <a href="<?= APP_URL ?>/termos-uso" target="_blank">Termos de Uso</a> e 
                    <a href="<?= APP_URL ?>/politica-privacidade" target="_blank">Política de Privacidade</a>
                </label>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Cadastrar</button>
            </div>
        </form>
        
        <div class="auth-links">
            <p>Já tem uma conta? <a href="<?= APP_URL ?>/auth/login">Faça login</a></p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const registerForm = document.getElementById('registerForm');
        registerForm.addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            const terms = document.getElementById('terms').checked;
            
            let isValid = true;
            
            // Verificar se as senhas conferem
            if (password !== passwordConfirm) {
                alert('As senhas não conferem.');
                isValid = false;
            }
            
            // Verificar se os termos foram aceitos
            if (!terms) {
                alert('Você precisa aceitar os termos de uso e política de privacidade.');
                isValid = false;
            }
            
            if (!isValid) {
                event.preventDefault();
            }
        });
    });
</script>

<?php require_once APP_PATH . '/views/partials/footer.php'; ?>

<!-- ARQUIVO COMPLETO E CORRETO -->