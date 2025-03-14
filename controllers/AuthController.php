<?php
/**
 * Controlador de Autenticação
 * Responsável por gerenciar login, logout, registro e recuperação de senha
 */

class AuthController {
    /**
     * Exibe a página de login
     */
    public function showLoginForm() {
        require_once APP_PATH . '/views/auth/login.php';
    }
    
    /**
     * Processa o formulário de login
     */
    public function login() {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($email) || empty($password)) {
            setFlashMessage('error', 'Por favor, preencha todos os campos.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, tenant_id, username, email, password, name, role, active, email_verified FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            setFlashMessage('error', 'Credenciais inválidas.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        if (!$user['active']) {
            setFlashMessage('error', 'Sua conta está desativada. Entre em contato com o suporte.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        // Registrar login
        $stmt = $pdo->prepare("
            UPDATE users 
            SET last_login = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        // Registrar log de acesso
        $stmt = $pdo->prepare("
            INSERT INTO access_logs 
            (user_id, tenant_id, ip_address, user_agent, action) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'],
            $user['tenant_id'],
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'],
            'login'
        ]);
        
        // Armazenar dados na sessão
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['tenant_id'] = $user['tenant_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        
        // Criar token "remember me" se solicitado
        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $hashedToken = password_hash($token, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET remember_token = ? 
                WHERE id = ?
            ");
            $stmt->execute([$hashedToken, $user['id']]);
            
            // Definir cookie (30 dias)
            setcookie('remember_token', $token, time() + (86400 * 30), '/', '', false, true);
        }
        
        // Redirecionar com base no papel do usuário
        if ($user['role'] === 'platform_admin') {
            redirect(APP_URL . '/admin/dashboard');
        } else {
            redirect(APP_URL . '/dashboard');
        }
    }
    
    /**
     * Processa o logout do usuário
     */
    public function logout() {
        // Registrar log de acesso se estiver autenticado
        if (isset($_SESSION['user_id'])) {
            $pdo = db();
            $stmt = $pdo->prepare("
                INSERT INTO access_logs 
                (user_id, tenant_id, ip_address, user_agent, action) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $_SESSION['tenant_id'] ?? null,
                $_SERVER['REMOTE_ADDR'],
                $_SERVER['HTTP_USER_AGENT'],
                'logout'
            ]);
            
            // Limpar token "remember me"
            $stmt = $pdo->prepare("
                UPDATE users 
                SET remember_token = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
        
        // Limpar dados da sessão
        session_unset();
        session_destroy();
        
        redirect(APP_URL . '/auth/login');
    }
    
    /**
     * Exibe o formulário de recuperação de senha
     */
    public function showResetPasswordForm() {
        require_once APP_PATH . '/views/auth/reset-password.php';
    }
    
    /**
     * Processa a solicitação de recuperação de senha
     */
    public function sendResetLink() {
        $email = sanitize($_POST['email'] ?? '');
        
        if (empty($email)) {
            setFlashMessage('error', 'Por favor, informe seu email.');
            redirect(APP_URL . '/auth/reset-password');
            return;
        }
        
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Gerar token de redefinição
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $pdo->prepare("
                UPDATE users 
                SET password_reset_token = ?, password_reset_expires = ? 
                WHERE id = ?
            ");
            $stmt->execute([$token, $expires, $user['id']]);
            
            // Enviar email com link de redefinição
            $resetUrl = APP_URL . '/auth/reset-password/confirm?token=' . $token;
            $subject = APP_NAME . ' - Redefinição de Senha';
            $message = "Olá {$user['name']},\n\n";
            $message .= "Você solicitou a redefinição de sua senha. Clique no link abaixo para criar uma nova senha:\n\n";
            $message .= $resetUrl . "\n\n";
            $message .= "Este link expira em 1 hora.\n\n";
            $message .= "Se você não solicitou a redefinição de senha, por favor ignore este email.\n\n";
            $message .= "Atenciosamente,\n";
            $message .= APP_NAME;
            
            // Usar o serviço de email para enviar
            $emailService = new EmailService();
            $emailService->send($email, $subject, $message);
        }
        
        // Sempre exibir mensagem de sucesso, mesmo se o email não existir (segurança)
        setFlashMessage('success', 'Se o email informado estiver cadastrado, enviaremos um link para redefinição de senha.');
        redirect(APP_URL . '/auth/login');
    }
    
    /**
     * Exibe o formulário para criação de nova senha
     */
    public function showConfirmResetForm() {
        $token = sanitize($_GET['token'] ?? '');
        
        if (empty($token)) {
            setFlashMessage('error', 'Token inválido.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT id 
            FROM users 
            WHERE password_reset_token = ? 
            AND password_reset_expires > NOW()
            AND active = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            setFlashMessage('error', 'Token inválido ou expirado.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        // Passar token para o template
        $resetToken = $token;
        require_once APP_PATH . '/views/auth/reset-password-confirm.php';
    }
    
    /**
     * Processa a redefinição de senha
     */
    public function resetPassword() {
        $token = sanitize($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        
        if (empty($token) || empty($password) || empty($passwordConfirm)) {
            setFlashMessage('error', 'Por favor, preencha todos os campos.');
            redirect(APP_URL . '/auth/reset-password/confirm?token=' . $token);
            return;
        }
        
        if ($password !== $passwordConfirm) {
            setFlashMessage('error', 'As senhas não conferem.');
            redirect(APP_URL . '/auth/reset-password/confirm?token=' . $token);
            return;
        }
        
        if (strlen($password) < 8) {
            setFlashMessage('error', 'A senha deve ter pelo menos 8 caracteres.');
            redirect(APP_URL . '/auth/reset-password/confirm?token=' . $token);
            return;
        }
        
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT id 
            FROM users 
            WHERE password_reset_token = ? 
            AND password_reset_expires > NOW()
            AND active = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            setFlashMessage('error', 'Token inválido ou expirado.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        // Atualizar senha
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET password = ?, password_reset_token = NULL, password_reset_expires = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $user['id']]);
        
        setFlashMessage('success', 'Senha redefinida com sucesso. Faça login com sua nova senha.');
        redirect(APP_URL . '/auth/login');
    }
    
    /**
     * Exibe o formulário de registro
     */
    public function showRegisterForm() {
        require_once APP_PATH . '/views/auth/register.php';
    }
    
    /**
     * Processa o registro de novo usuário
     */
    public function register() {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        $terms = isset($_POST['terms']);
        
        // Validações básicas
        if (empty($name) || empty($email) || empty($password) || empty($passwordConfirm)) {
            setFlashMessage('error', 'Por favor, preencha todos os campos.');
            redirect(APP_URL . '/auth/register');
            return;
        }
        
        if (!$terms) {
            setFlashMessage('error', 'Você precisa aceitar os termos de uso e política de privacidade.');
            redirect(APP_URL . '/auth/register');
            return;
        }
        
        if ($password !== $passwordConfirm) {
            setFlashMessage('error', 'As senhas não conferem.');
            redirect(APP_URL . '/auth/register');
            return;
        }
        
        if (strlen($password) < 8) {
            setFlashMessage('error', 'A senha deve ter pelo menos 8 caracteres.');
            redirect(APP_URL . '/auth/register');
            return;
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('error', 'Email inválido.');
            redirect(APP_URL . '/auth/register');
            return;
        }
        
        // Verificar se email já existe
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            setFlashMessage('error', 'Este email já está em uso.');
            redirect(APP_URL . '/auth/register');
            return;
        }
        
        // Gerar username a partir do email
        $username = explode('@', $email)[0];
        $baseUsername = $username;
        $counter = 1;
        
        while (true) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if (!$stmt->fetch()) {
                break;
            }
            
            $username = $baseUsername . $counter;
            $counter++;
        }
        
        // Criar usuário
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $verificationToken = bin2hex(random_bytes(32));
        
        $stmt = $pdo->prepare("
            INSERT INTO users 
            (username, email, password, name, role, active, email_verified, email_verification_token) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $username,
            $email,
            $hashedPassword,
            $name,
            'customer', // Papel padrão
            true,       // Ativo
            false,      // Email não verificado
            $verificationToken
        ]);
        
        $userId = $pdo->lastInsertId();
        
        // Enviar email de verificação
        $verifyUrl = APP_URL . '/auth/verify-email?token=' . $verificationToken;
        $subject = APP_NAME . ' - Verificação de Email';
        $message = "Olá {$name},\n\n";
        $message .= "Obrigado por se cadastrar. Clique no link abaixo para verificar seu email:\n\n";
        $message .= $verifyUrl . "\n\n";
        $message .= "Atenciosamente,\n";
        $message .= APP_NAME;
        
        // Usar o serviço de email para enviar
        $emailService = new EmailService();
        $emailService->send($email, $subject, $message);
        
        setFlashMessage('success', 'Cadastro realizado com sucesso. Verifique seu email para ativar sua conta.');
        redirect(APP_URL . '/auth/login');
    }
    
    /**
     * Verifica o email do usuário através do token enviado por email
     */
    public function verifyEmail() {
        $token = sanitize($_GET['token'] ?? '');
        
        if (empty($token)) {
            setFlashMessage('error', 'Token inválido.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT id 
            FROM users 
            WHERE email_verification_token = ? 
            AND active = 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            setFlashMessage('error', 'Token inválido ou expirado.');
            redirect(APP_URL . '/auth/login');
            return;
        }
        
        // Atualizar status de verificação
        $stmt = $pdo->prepare("
            UPDATE users 
            SET email_verified = 1, email_verification_token = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$user['id']]);
        
        setFlashMessage('success', 'Email verificado com sucesso. Você já pode fazer login.');
        redirect(APP_URL . '/auth/login');
    }
    
    /**
     * Autentica usuário via "remember me" token
     * Chamado automaticamente pelo sistema a cada requisição
     */
    public static function authenticateByRememberToken() {
        if (isAuthenticated() || !isset($_COOKIE['remember_token'])) {
            return false;
        }
        
        $token = $_COOKIE['remember_token'];
        
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT id, tenant_id, username, email, name, role 
            FROM users 
            WHERE remember_token IS NOT NULL AND active = 1
        ");
        $stmt->execute();
        
        while ($user = $stmt->fetch()) {
            if (password_verify($token, $user['remember_token'])) {
                // Usuário encontrado, autenticar
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['tenant_id'] = $user['tenant_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                
                // Renovar token para segurança
                $newToken = bin2hex(random_bytes(32));
                $hashedToken = password_hash($newToken, PASSWORD_DEFAULT);
                
                $updateStmt = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $updateStmt->execute([$hashedToken, $user['id']]);
                
                // Renovar cookie
                setcookie('remember_token', $newToken, time() + (86400 * 30), '/', '', false, true);
                
                return true;
            }
        }
        
        return false;
    }
}

// ESTE ARQUIVO AINDA PRECISA:
// - Implementar autenticação de dois fatores (2FA)
// - Melhorar validação de senhas com requisitos de complexidade
// - Adicionar suporte para login via redes sociais
// - Implementar limitação de tentativas de login (anti-brute force)
?>