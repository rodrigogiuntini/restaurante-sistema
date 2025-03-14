<?php
/**
 * Ponto de entrada principal para o Sistema de Gestão de Restaurantes SaaS
 */

// Definir o diretório raiz da aplicação
define('APP_PATH', dirname(__DIR__));

// Carregar constantes
require_once APP_PATH . '/config/constants.php';

// Carregar funções utilitárias
require_once APP_PATH . '/includes/functions.php';
require_once APP_PATH . '/includes/session.php';

// Resolver tenant
require_once APP_PATH . '/includes/tenant-resolver.php';
$tenantResolver = new TenantResolver();
$tenantId = $tenantResolver->resolve();

// Verificar se o tenant existe e configurar na sessão
if ($tenantId && $tenantResolver->tenantExists($tenantId)) {
    $tenantResolver->setTenantSession($tenantId);
}

// Router básico
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = str_replace('/restaurante-sistema', '', $uri);
$segments = explode('/', trim($uri, '/'));

// Definir controlador, método e parâmetros padrão
$controllerName = isset($segments[0]) && !empty($segments[0]) ? ucfirst($segments[0]) . 'Controller' : 'HomeController';
$methodName = isset($segments[1]) && !empty($segments[1]) ? $segments[1] : 'index';
$params = array_slice($segments, 2);

// Verificar autenticação para rotas protegidas
$publicRoutes = [
    'AuthController' => ['login', 'register', 'resetPassword', 'sendResetLink', 'showLoginForm', 'showRegisterForm', 'showResetPasswordForm', 'showConfirmResetForm', 'resetPassword'],
    'HomeController' => ['index'],
    'SubscriptionController' => ['plans', 'pricing'],
];

$requiresAuth = true;
if (isset($publicRoutes[$controllerName]) && in_array($methodName, $publicRoutes[$controllerName])) {
    $requiresAuth = false;
}

if ($requiresAuth && !isAuthenticated()) {
    setFlashMessage('error', 'Por favor, faça login para acessar esta página.');
    redirect(APP_URL . '/auth/login');
}

// Carregar o controlador específico e executar o método
$controllerFile = APP_PATH . '/controllers/' . $controllerName . '.php';

if (file_exists($controllerFile)) {
    require_once $controllerFile;
    
    if (class_exists($controllerName)) {
        $controller = new $controllerName();
        
        if (method_exists($controller, $methodName)) {
            call_user_func_array([$controller, $methodName], $params);
        } else {
            // Método não encontrado
            header("HTTP/1.0 404 Not Found");
            echo "Método não encontrado.";
        }
    } else {
        // Classe do controlador não encontrada
        header("HTTP/1.0 404 Not Found");
        echo "Controlador não encontrado.";
    }
} else {
    // Arquivo do controlador não encontrado
    header("HTTP/1.0 404 Not Found");
    echo "Página não encontrada.";
}

/*
 * CHECKPOINT GERAL DE DESENVOLVIMENTO - 10% DO PROJETO
 * ===================================================
 * 
 * ARQUIVOS CONCLUÍDOS (100%):
 * - config/database.php
 * - config/constants.php
 * - config/tenant.php
 * - includes/functions.php
 * - includes/session.php
 * - includes/tenant-resolver.php
 * - views/auth/login.php
 * - views/partials/header.php
 * - views/partials/footer.php
 *
 * ARQUIVOS PARCIALMENTE IMPLEMENTADOS:
 * - controllers/AuthController.php (60%) - Falta completar métodos de registro e recuperação de senha
 * - views/auth/register.php (50%) - Falta finalizar formulário e adicionar validações
 * - views/auth/reset-password.php (50%) - Falta finalizar formulário
 * - assets/css/main.css (70%) - Falta implementar temas e responsividade
 * - assets/js/main.js (40%) - Falta implementar validação e interatividade
 * - controllers/DashboardController.php (30%) - Implementação básica apenas
 * 
 * PRÓXIMOS PASSOS PARA ALCANÇAR 20%:
 * 1. Completar Sistema de Autenticação
 *    - Finalizar métodos no AuthController
 *    - Completar views de registro e reset de senha
 * 
 * 2. Implementar Sistema de Assinaturas
 *    - Criar SubscriptionController
 *    - Implementar integração básica com Stripe
 *    - Criar views para planos e checkout
 *
 * 3. Implementar Dashboard Completo
 *    - Finalizar DashboardController
 *    - Criar views específicas para cada tipo de restaurante
 *    - Implementar widgets básicos de estatísticas
 *
 * 4. Implementar Gestão de Perfil de Usuário
 *    - Criar ProfileController
 *    - Criar views para visualização e edição de perfil
 */