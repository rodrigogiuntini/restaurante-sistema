<?php
/**
 * Gerenciamento de sessão
 */
session_start();

/**
 * Inicia uma sessão para o usuário
 *
 * @param array $user Dados do usuário
 * @param bool $remember Lembrar usuário
 * @return void
 */
function startUserSession($user, $remember = false) {
    // Remove dados sensíveis
    unset($user['password']);
    
    // Registra a sessão
    $_SESSION['user'] = $user;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Atualiza o último login
    if (!empty($user['id'])) {
        updateLastLogin($user['id']);
    }
    
    // Gera token de lembrar se solicitado
    if ($remember) {
        $token = generateRememberToken($user['id']);
        setRememberCookie($token);
    }
}

/**
 * Encerra a sessão do usuário
 *
 * @return void
 */
function endUserSession() {
    // Limpa a sessão
    $_SESSION = [];
    
    // Limpa o cookie da sessão
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    
    // Destrói a sessão
    session_destroy();
    
    // Remove o cookie de lembrar
    setcookie('remember_token', '', time() - 3600, '/');
}

/**
 * Verifica se o usuário está logado
 *
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Retorna os dados do usuário logado
 *
 * @return array|null
 */
function getLoggedUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Verifica se o usuário tem a permissão especificada
 *
 * @param string $permission Nome da permissão
 * @return bool
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user = getLoggedUser();
    
    // Admin tem todas as permissões
    if ($user['role'] === 'admin' || $user['role'] === 'platform_admin') {
        return true;
    }
    
    // Verificar as permissões do papel do usuário
    $tenantId = $user['tenant_id'] ?? null;
    
    if ($tenantId === null) {
        return false;
    }
    
    // TODO: Implementar verificação de permissão específica baseada no papel
    return false;
}

/**
 * Atualiza o último login do usuário
 *
 * @param int $userId ID do usuário
 * @return void
 */
function updateLastLogin($userId) {
    global $db;
    
    $query = "UPDATE users SET last_login = NOW() WHERE id = :id";
    $params = [':id' => $userId];
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
    } catch (PDOException $e) {
        // Log do erro
        error_log("Erro ao atualizar último login: " . $e->getMessage());
    }
}

/**
 * Gera um token para "lembrar usuário"
 *
 * @param int $userId ID do usuário
 * @return string Token gerado
 */
function generateRememberToken($userId) {
    global $db;
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    $query = "UPDATE users SET remember_token = :token WHERE id = :id";
    $params = [
        ':token' => $token,
        ':id' => $userId
    ];
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $token;
    } catch (PDOException $e) {
        // Log do erro
        error_log("Erro ao gerar token de lembrar: " . $e->getMessage());
        return '';
    }
}

/**
 * Define o cookie para "lembrar usuário"
 *
 * @param string $token Token a ser armazenado
 * @return void
 */
function setRememberCookie($token) {
    // Expira em 30 dias
    $expire = time() + (86400 * 30);
    setcookie('remember_token', $token, $expire, '/', '', true, true);
}

/**
 * Verifica o token "lembrar usuário" e loga o usuário
 *
 * @return bool
 */
function checkRememberToken() {
    global $db;
    
    if (!isset($_COOKIE['remember_token']) || empty($_COOKIE['remember_token'])) {
        return false;
    }
    
    $token = $_COOKIE['remember_token'];
    
    $query = "SELECT * FROM users WHERE remember_token = :token AND active = 1";
    $params = [':token' => $token];
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            startUserSession($user);
            return true;
        }
    } catch (PDOException $e) {
        // Log do erro
        error_log("Erro ao verificar token de lembrar: " . $e->getMessage());
    }
    
    // Token inválido, remove o cookie
    setcookie('remember_token', '', time() - 3600, '/');
    return false;
}

// controllers/AuthController.php
<?php
/**
 * Controlador de autenticação
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../models/User.php';

class AuthController {
    /**
     * Processa o login do usuário
     *
     * @return array Resultado do login com status e mensagem
     */
    public function login() {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === 'on';
        
        // Validação básica
        if (empty($username) || empty($password)) {
            return [
                'status' => 'error',
                'message' => 'Usuário e senha são obrigatórios'
            ];
        }
        
        // Busca o usuário
        $userModel = new User();
        $user = $userModel->findByUsernameOrEmail($username);
        
        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'Credenciais inválidas'
            ];
        }
        
        // Verifica se o usuário está ativo
        if (!$user['active']) {
            return [
                'status' => 'error',
                'message' => 'Conta inativa. Entre em contato com o suporte.'
            ];
        }
        
        // Verifica a senha
        if (!verifyPassword($password, $user['password'])) {
            // Registra tentativa de login falha
            $this->logLoginAttempt($user['id'], false);
            
            return [
                'status' => 'error',
                'message' => 'Credenciais inválidas'
            ];
        }
        
        // Inicia a sessão
        startUserSession($user, $remember);
        
        // Registra login bem-sucedido
        $this->logLoginAttempt($user['id'], true);
        
        // Determina redirecionamento com base no papel
        $redirect = $this->getRedirectBasedOnRole($user['role']);
        
        return [
            'status' => 'success',
            'message' => 'Login realizado com sucesso',
            'redirect' => $redirect
        ];
    }
    
    /**
     * Processa o logout do usuário
     *
     * @return array Resultado do logout com status e mensagem
     */
    public function logout() {
        endUserSession();
        
        return [
            'status' => 'success',
            'message' => 'Logout realizado com sucesso',
            'redirect' => '/login'
        ];
    }
    
    /**
     * Processa o registro de um novo usuário
     *
     * @return array Resultado do registro com status e mensagem
     */
    public function register() {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validação
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Nome é obrigatório';
        }
        
        if (empty($email) || !validateEmail($email)) {
            $errors[] = 'Email inválido';
        }
        
        if (empty($username) || !validateUsername($username)) {
            $errors[] = 'Nome de usuário inválido (mínimo 3 caracteres, apenas letras, números e _)';
        }
        
        if (empty($password) || strlen($password) < 8) {
            $errors[] = 'Senha deve ter no mínimo 8 caracteres';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'As senhas não coincidem';
        }
        
        if (!empty($errors)) {
            return [
                'status' => 'error',
                'message' => 'Corrija os erros abaixo',
                'errors' => $errors
            ];
        }
        
        // Verifica se email ou username já existem
        $userModel = new User();
        if ($userModel->emailExists($email)) {
            return [
                'status' => 'error',
                'message' => 'Este email já está em uso'
            ];
        }
        
        if ($userModel->usernameExists($username)) {
            return [
                'status' => 'error',
                'message' => 'Este nome de usuário já está em uso'
            ];
        }
        
        // Cria o usuário
        $hashedPassword = hashPassword($password);
        $userId = $userModel->create([
            'name' => $name,
            'email' => $email,
            'username' => $username,
            'password' => $hashedPassword,
            'role' => 'customer', // Papel padrão para novos registros
            'active' => 1,
            'email_verified' => 0
        ]);
        
        if (!$userId) {
            return [
                'status' => 'error',
                'message' => 'Erro ao criar conta. Tente novamente.'
            ];
        }
        
        // Envia email de verificação
        $this->sendVerificationEmail($email, $userId);
        
        return [
            'status' => 'success',
            'message' => 'Conta criada com sucesso! Verifique seu email para ativar.',
            'redirect' => '/login'
        ];
    }
    
    /**
     * Solicita redefinição de senha
     *
     * @return array Resultado da solicitação com status e mensagem
     */
    public function forgotPassword() {
        $email = $_POST['email'] ?? '';
        
        if (empty($email) || !validateEmail($email)) {
            return [
                'status' => 'error',
                'message' => 'Email inválido'
            ];
        }
        
        $userModel = new User();
        $user = $userModel->findByEmail($email);
        
        if (!$user) {
            // Por segurança, não informamos se o email existe ou não
            return [
                'status' => 'success',
                'message' => 'Se o email existir em nossa base, você receberá instruções para redefinir sua senha.'
            ];
        }
        
        // Gera token de redefinição
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $userModel->updateResetToken($user['id'], $token, $expires);
        
        // Envia email com instruções
        $this->sendPasswordResetEmail($email, $token);
        
        return [
            'status' => 'success',
            'message' => 'Se o email existir em nossa base, você receberá instruções para redefinir sua senha.'
        ];
    }
    
    /**
     * Redefine a senha do usuário
     *
     * @return array Resultado da redefinição com status e mensagem
     */
    public function resetPassword() {
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($token)) {
            return [
                'status' => 'error',
                'message' => 'Token inválido'
            ];
        }
        
        if (empty($password) || strlen($password) < 8) {
            return [
                'status' => 'error',
                'message' => 'Senha deve ter no mínimo 8 caracteres'
            ];
        }
        
        if ($password !== $confirmPassword) {
            return [
                'status' => 'error',
                'message' => 'As senhas não coincidem'
            ];
        }
        
        $userModel = new User();
        $user = $userModel->findByResetToken($token);
        
        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'Token inválido ou expirado'
            ];
        }
        
        // Verifica se o token expirou
        if (strtotime($user['password_reset_expires']) < time()) {
            return [
                'status' => 'error',
                'message' => 'Token expirado. Solicite uma nova redefinição.'
            ];
        }
        
        // Atualiza a senha
        $hashedPassword = hashPassword($password);
        $userModel->updatePassword($user['id'], $hashedPassword);
        
        // Limpa o token de redefinição
        $userModel->clearResetToken($user['id']);
        
        return [
            'status' => 'success',
            'message' => 'Senha redefinida com sucesso',
            'redirect' => '/login'
        ];
    }
    
    /**
     * Verifica o email do usuário
     *
     * @return array Resultado da verificação com status e mensagem
     */
    public function verifyEmail() {
        $token = $_GET['token'] ?? '';
        $userId = $_GET['id'] ?? '';
        
        if (empty($token) || empty($userId)) {
            return [
                'status' => 'error',
                'message' => 'Link de verificação inválido'
            ];
        }
        
        $userModel = new User();
        $user = $userModel->findById($userId);
        
        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'Usuário não encontrado'
            ];
        }
        
        // Verifica se o email já foi verificado
        if ($user['email_verified']) {
            return [
                'status' => 'info',
                'message' => 'Email já verificado anteriormente',
                'redirect' => '/login'
            ];
        }
        
        // Verifica o token (implementação simplificada)
        // Em produção, armazenar tokens de verificação no banco
        $expectedToken = md5($user['email'] . $user['id'] . 'verification_salt');
        
        if ($token !== $expectedToken) {
            return [
                'status' => 'error',
                'message' => 'Token de verificação inválido'
            ];
        }
        
        // Marca email como verificado
        $userModel->verifyEmail($user['id']);
        
        return [
            'status' => 'success',
            'message' => 'Email verificado com sucesso! Agora você pode fazer login.',
            'redirect' => '/login'
        ];
    }
    
    /**
     * Registra tentativa de login
     *
     * @param int $userId ID do usuário
     * @param bool $success Sucesso da tentativa
     * @return void
     */
    private function logLoginAttempt($userId, $success) {
        // TODO: Implementar registro de tentativas
    }
    
    /**
     * Determina redirecionamento com base no papel do usuário
     *
     * @param string $role Papel do usuário
     * @return string URL de redirecionamento
     */
    private function getRedirectBasedOnRole($role) {
        switch ($role) {
            case 'platform_admin':
                return '/admin/dashboard';
            case 'admin':
            case 'manager':
                return '/dashboard';
            case 'cashier':
                return '/cash-register';
            case 'waiter':
                return '/tables';
            case 'cook':
                return '/kitchen';
            case 'delivery':
                return '/delivery';
            case 'customer':
                return '/customer/dashboard';
            default:
                return '/dashboard';
        }
    }
    
    /**
     * Envia email de verificação
     *
     * @param string $email Email do usuário
     * @param int $userId ID do usuário
     * @return void
     */
    private function sendVerificationEmail($email, $userId) {
        // Cria token de verificação
        $token = md5($email . $userId . 'verification_salt');
        
        // TODO: Implementar envio de email real
        // Em ambiente de desenvolvimento, exibe o link no log
        $verificationUrl = APP_URL . '/verify-email?id=' . $userId . '&token=' . $token;
        error_log("Link de verificação: " . $verificationUrl);
    }
    
    /**
     * Envia email para redefinição de senha
     *
     * @param string $email Email do usuário
     * @param string $token Token de redefinição
     * @return void
     */
    private function sendPasswordResetEmail($email, $token) {
        // TODO: Implementar envio de email real
        // Em ambiente de desenvolvimento, exibe o link no log
        $resetUrl = APP_URL . '/reset-password?token=' . $token;
        error_log("Link de redefinição de senha: " . $resetUrl);
    }
}