<?php
/**
 * Configurações para o serviço de envio de emails
 */

return [
    'method' => getenv('EMAIL_METHOD') ?: 'mail', // mail, smtp, sendgrid, mailgun
    'from' => getenv('EMAIL_FROM') ?: 'no-reply@restaurantesaas.com.br',
    'from_name' => getenv('EMAIL_FROM_NAME') ?: APP_NAME,
    'reply_to' => getenv('EMAIL_REPLY_TO') ?: 'suporte@restaurantesaas.com.br',
    
    // Configurações para SMTP
    'smtp' => [
        'host' => getenv('SMTP_HOST') ?: 'smtp.example.com',
        'port' => getenv('SMTP_PORT') ?: 587,
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls', // tls ou ssl
        'username' => getenv('SMTP_USERNAME') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
    ],
    
    // Configurações para SendGrid
    'sendgrid' => [
        'api_key' => getenv('SENDGRID_API_KEY') ?: '',
    ],
    
    // Configurações para Mailgun
    'mailgun' => [
        'api_key' => getenv('MAILGUN_API_KEY') ?: '',
        'domain' => getenv('MAILGUN_DOMAIN') ?: '',
    ],
];

// ARQUIVO COMPLETO E CORRETO