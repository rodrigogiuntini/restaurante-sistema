<?php
// Definir título da página
$pageTitle = 'Recuperar Senha';
$hideNavbar = true;
require_once APP_PATH . '/views/partials/header.php';
?>

<div class="auth-container">
    <div class="auth-box">
        <div class="logo-container">
            <img src="<?= ASSETS_URL ?>/images/logo/logo.png" alt="<?= APP_NAME ?>" class="auth-logo">
        </div>
        
        <h2>Recuperar Senha</h2>
        
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
        
        <p>Informe seu email para receber um link de redefinição de senha.</p>
        
        <form action="<?= APP_URL ?>/auth/reset-password/send" method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Enviar Link</button>
            </div>
        </form>
        
        <div class="auth-links">
            <p><a href="<?= APP_URL ?>/auth/login">Voltar para o login</a></p>
        </div>
    </div>
</div>

<?php require_once APP_PATH . '/views/partials/footer.php'; ?>

<!-- ARQUIVO COMPLETO E CORRETO -->