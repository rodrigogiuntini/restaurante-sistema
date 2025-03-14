<?php
/**
 * Controlador de Autenticação
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
    
    // Outras funções de autenticação implementadas
    // (logout, registro, recuperação de senha, etc.)
    
    /**
     * Processa o logout do usuário
     */
    public function logout() {
        // Implementado parcialmente
    }
    
    /**
     * Exibe o formulário de recuperação de senha
     */
    public function showResetPasswordForm() {
        // Implementado parcialmente
    }
    
    /**
     * Processa a solicitação de recuperação de senha
     */
    public function sendResetLink() {
        // Implementado parcialmente
    }
    
    /**
     * Exibe o formulário para criação de nova senha
     */
    public function showConfirmResetForm() {
        // Implementado parcialmente
    }
    
    /**
     * Processa a redefinição de senha
     */
    public function resetPassword() {
        // Implementado parcialmente
    }
    
    /**
     * Exibe o formulário de registro
     */
    public function showRegisterForm() {
        // Implementado parcialmente
    }
    
    /**
     * Processa o registro de novo usuário
     */
    public function register() {
        // Implementado parcialmente
    }
}

// CHECKPOINT: Implementação parcial (60%)
// TODO: Completar os métodos de logout, recuperação de senha e registro
// TODO: Adicionar verificação de email após registro
// TODO: Implementar proteção contra força bruta nos formulários
// TODO: Adicionar autenticação em dois fatores