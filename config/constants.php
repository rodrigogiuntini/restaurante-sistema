<?php
// Constantes globais utilizadas no sistema
define('APP_NAME', 'Sistema de Gestão de Restaurantes');
define('APP_VERSION', '1.0.0');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/restaurante-sistema');
define('APP_PATH', dirname(__DIR__));
define('ASSETS_URL', APP_URL . '/assets');
define('UPLOADS_PATH', APP_PATH . '/uploads');
define('UPLOADS_URL', APP_URL . '/uploads');
define('TIMEZONE', 'America/Sao_Paulo');

// Constantes específicas do SaaS
define('TRIAL_PERIOD_DAYS', 15);
define('DEFAULT_PLAN', 'basic');

// CHECKPOINT: Implementação básica completa (100%)
// TODO: Adicionar constantes para as integrações externas (Stripe, iFood)