<?php
// Configurações para o sistema multi-tenant
return [
    'tenant_column' => 'tenant_id',
    'identify_by' => 'domain', // domain, subdomain ou path
    'domain_mapping' => [
        'localhost' => 'demo' // tenant_id para desenvolvimento
    ],
    'default_tenant' => 'demo',
    'tenant_table' => 'tenants',
    'excluded_paths' => [
        '/admin',
        '/auth',
        '/subscription',
        '/webhook',
        '/assets'
    ]
];

// CHECKPOINT: Implementação básica completa (100%) 
// TODO: Implementar cache de resoluções de tenant
// TODO: Adicionar suporte a domínios customizados