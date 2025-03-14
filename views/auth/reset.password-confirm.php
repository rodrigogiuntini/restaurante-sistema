<?php
// Definir título da página
$pageTitle = 'Nova Senha';
$hideNavbar = true;
require_once APP_PATH . '/views/partials/header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <div class="logo-container">
            <img src="<?= ASSETS_URL ?>/images/logo/logo.png" alt="<?= APP_NAME ?>" class="auth-logo">
        </div>
        
        <h2>Criar Nova Senha</h2>
        
        <?php if (hasFlashMessage('error')): ?>
            <div class="alert alert-danger">
                <?= getFlashMessage('error') ?>
            </div>
        <?php endif; ?>
        
        <p>Por favor, crie uma nova senha para sua conta.</p>
        
        <form action="<?= APP_URL ?>/auth/reset-password" method="post" id="resetPasswordForm">
            <input type="hidden" name="token" value="<?= htmlspecialchars($resetToken) ?>">
            
            <div class="form-group">
                <label for="password">Nova Senha</label>
                <input type="password" name="password" id="password" class="form-control" required minlength="8">
                <small class="form-text text-muted">A senha deve ter pelo menos 8 caracteres.</small>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Confirmar Nova Senha</label>
                <input type="password" name="password_confirm" id="password_confirm" class="form-control" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Salvar Nova Senha</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const resetForm = document.getElementById('resetPasswordForm');
        resetForm.addEventListener('submit', function(event) {
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;
            
            if (password !== passwordConfirm) {
                alert('As senhas não conferem.');
                event.preventDefault();
            }
        });
    });
</script>

<?php require_once APP_PATH . '/views/partials/footer.php'; ?>

<!-- ARQUIVO COMPLETO E CORRETO -->