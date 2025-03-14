<?php
/**
 * Funções utilitárias para o Sistema de Gestão de Restaurantes
 */

/**
 * Converte um valor para o formato de moeda brasileiro
 */
function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

/**
 * Sanitiza valores de entrada
 */
function sanitize($input) {
    if (is_array($input)) {
        foreach ($input as $key => $val) {
            $input[$key] = sanitize($val);
        }
    } else {
        $input = htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    return $input;
}

/**
 * Gera um slug a partir de um texto
 */
function generateSlug($text) {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('/[^a-zA-Z0-9]/', '-', $text);
    $text = strtolower(trim($text, '-'));
    $text = preg_replace('/-+/', '-', $text);
    return $text;
}

/**
 * Retorna a instância da conexão com o banco de dados
 */
function db() {
    static $pdo = null;
    
    if ($pdo === null) {
        $config = require_once APP_PATH . '/config/database.php';
        $dsn = "mysql:host={$config['host']};dbname={$config['database']};charset={$config['charset']}";
        $options = $config['options'];
        
        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        } catch (PDOException $e) {
            die('Erro de conexão: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Redireciona para uma URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Verifica se o usuário está autenticado
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Retorna o ID do tenant atual
 */
function getCurrentTenantId() {
    if (isset($_SESSION['tenant_id'])) {
        return $_SESSION['tenant_id'];
    }
    
    $config = require_once APP_PATH . '/config/tenant.php';
    return $config['default_tenant'];
}

// CHECKPOINT: Implementação básica completa (100%)
// TODO: Adicionar funções para logging de erros
// TODO: Adicionar funções para validação de dados de formulários
// TODO: Adicionar funções para manipulação de datas