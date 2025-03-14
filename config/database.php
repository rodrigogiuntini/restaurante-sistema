// config/database.php
<?php
/**
 * Configuração de conexão com o banco de dados
 */
return [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'database' => $_ENV['DB_DATABASE'] ?? 'restaurante_saas',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

// config/constants.php
<?php
/**
 * Constantes globais da aplicação
 */
define('APP_NAME', 'Restaurante SaaS');
define('APP_VERSION', '1.0.0');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');
define('ASSETS_URL', APP_URL . '/assets');
define('UPLOADS_PATH', __DIR__ . '/../public/uploads');
define('TEMPLATES_PATH', __DIR__ . '/../views');

// Constantes de status
define('STATUS_ACTIVE', 1);
define('STATUS_INACTIVE', 0);

// Tipos de restaurante
define('RESTAURANT_TYPE_ALACARTE', 'alacarte');
define('RESTAURANT_TYPE_FASTFOOD', 'fastfood');
define('RESTAURANT_TYPE_PIZZARIA', 'pizzaria');
define('RESTAURANT_TYPE_RODIZIO', 'rodizio');
define('RESTAURANT_TYPE_SELFSERVICE', 'selfservice');
define('RESTAURANT_TYPE_DELIVERY', 'delivery');
define('RESTAURANT_TYPE_FOODTRUCK', 'foodtruck');
define('RESTAURANT_TYPE_BAR', 'bar');

// config/stripe.php
<?php
/**
 * Configuração da API do Stripe
 */
return [
    'public_key' => $_ENV['STRIPE_PUBLIC_KEY'] ?? '',
    'secret_key' => $_ENV['STRIPE_SECRET_KEY'] ?? '',
    'webhook_secret' => $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '',
    'currency' => 'brl',
    'statement_descriptor' => 'RESTAURANTE SAAS',
    'webhook_tolerance' => 300, // 5 minutos
];

// config/tenant.php
<?php
/**
 * Configuração multi-tenant
 */
return [
    'tenant_column' => 'tenant_id',
    'default_connection' => 'mysql',
    'tenant_database_prefix' => 'tenant_',
    'use_separate_databases' => false,
    'create_database_for_tenant' => false,
    'tenant_routes_file' => __DIR__ . '/../routes/tenant.php',
    'central_routes_file' => __DIR__ . '/../routes/web.php',
    'tenant_connection_name' => 'tenant',
];

// config/restaurant_types.php
<?php
/**
 * Configuração de tipos de restaurante
 */
return [
    'alacarte' => [
        'name' => 'À La Carte',
        'description' => 'Restaurante tradicional com menu completo',
        'features' => [
            'reservations' => true,
            'table_management' => true,
            'kitchen_display' => true,
            'bill_splitting' => true,
            'waiters' => true,
        ]
    ],
    'fastfood' => [
        'name' => 'Fast Food',
        'description' => 'Atendimento rápido com menu simplificado',
        'features' => [
            'reservations' => false,
            'table_management' => false,
            'kitchen_display' => true,
            'bill_splitting' => false,
            'waiters' => false,
        ]
    ],
    'pizzaria' => [
        'name' => 'Pizzaria',
        'description' => 'Especializada em pizzas e massas',
        'features' => [
            'reservations' => true,
            'table_management' => true,
            'kitchen_display' => true,
            'bill_splitting' => true,
            'waiters' => true,
            'pizza_builder' => true,
        ]
    ],
    'rodizio' => [
        'name' => 'Rodízio',
        'description' => 'Sistema de rodízio com passadores',
        'features' => [
            'reservations' => true,
            'table_management' => true,
            'kitchen_display' => false,
            'bill_splitting' => true,
            'waiters' => true,
            'rounds_control' => true,
        ]
    ],
    'selfservice' => [
        'name' => 'Self-Service/Bufê',
        'description' => 'Sistema de autoatendimento por peso ou preço fixo',
        'features' => [
            'reservations' => false,
            'table_management' => true,
            'kitchen_display' => false,
            'bill_splitting' => true,
            'waiters' => false,
            'scale_integration' => true,
        ]
    ],
    'delivery' => [
        'name' => 'Delivery',
        'description' => 'Focado em entregas',
        'features' => [
            'reservations' => false,
            'table_management' => false,
            'kitchen_display' => true,
            'bill_splitting' => false,
            'waiters' => false,
            'delivery_tracking' => true,
        ]
    ],
    'foodtruck' => [
        'name' => 'Food Truck',
        'description' => 'Operação móvel com menu limitado',
        'features' => [
            'reservations' => false,
            'table_management' => false,
            'kitchen_display' => true,
            'bill_splitting' => false,
            'waiters' => false,
            'mobile_first' => true,
        ]
    ],
    'bar' => [
        'name' => 'Bar/Pub',
        'description' => 'Foco em bebidas com opções de alimentos',
        'features' => [
            'reservations' => true,
            'table_management' => true,
            'kitchen_display' => true,
            'bill_splitting' => true,
            'waiters' => true,
            'tab_management' => true,
        ]
    ],
];