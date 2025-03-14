<?php require_once APP_PATH . '/views/partials/header.php'; ?>

<div class="auth-container">
    <div class="auth-box">
        <div class="logo-container">
            <img src="<?= ASSETS_URL ?>/images/logo/logo.png" alt="<?= APP_NAME ?>" class="auth-logo">
        </div>
        
        <h2>Login</h2>
        
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
        
        <form action="<?= APP_URL ?>/auth/login" method="post">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label for="password">Senha</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            
            <div class="form-group remember-me">
                <label>
                    <input type="checkbox" name="remember"> Lembrar-me
                </label>
                <a href="<?= APP_URL ?>/auth/reset-password" class="forgot-password">Esqueci minha senha</a>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary btn-block">Entrar</button>
            </div>
        </form>
        
        <div class="auth-links">
            <p>Não tem uma conta? <a href="<?= APP_URL ?>/auth/register">Cadastre-se</a></p>
        </div>
    </div>
</div>

<?php require_once APP_PATH . '/views/partials/footer.php'; ?>

<!-- CHECKPOINT: Implementação completa (100%) -->
<!-- TODO: Adicionar opção de login com redes sociais -->