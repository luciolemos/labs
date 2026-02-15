<?php

use App\Config\Env;

return [
    'name' => Env::get('APP_NAME', 'Painel de Sites'),
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => filter_var(Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => Env::get('APP_URL', ''),
    'paths' => [
        'storage' => dirname(__DIR__, 2) . '/storage',
        'logs' => dirname(__DIR__, 2) . '/storage/logs/app.log',
        'sites' => dirname(__DIR__, 2) . '/storage/data/sites.json',
    ],
    'admin' => [
        'user' => Env::get('ADMIN_USER', 'admin'),
        'pass' => Env::get('ADMIN_PASS', 'changeme'),
        'pass_hash' => Env::get('ADMIN_PASS_HASH', ''),
        'session_minutes' => (int)Env::get('ADMIN_SESSION_MINUTES', 60),
        'provision' => filter_var(Env::get('ADMIN_PROVISION', 'false'), FILTER_VALIDATE_BOOLEAN),
        'provision_host' => Env::get('ADMIN_PROVISION_HOST', '88.198.104.148'),
        'provision_base' => Env::get('ADMIN_PROVISION_BASE', '/var/www'),
        'apache_conf' => Env::get('ADMIN_APACHE_CONF', '/etc/apache2/conf-available/site-paths.conf'),
        'apache_conf_name' => Env::get('ADMIN_APACHE_CONF_NAME', 'site-paths'),
        'deprovision' => filter_var(Env::get('ADMIN_DEPROVISION', 'false'), FILTER_VALIDATE_BOOLEAN),
        'deprovision_remove_dir' => filter_var(Env::get('ADMIN_DEPROVISION_REMOVE_DIR', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],
    'pagination' => [
        'per_page' => (int)Env::get('SITE_PER_PAGE', 12),
    ],
    'sites' => [
        [
            'name' => 'site1',
            'url' => 'http://88.198.104.148/site1/',
        ],
        [
            'name' => 'site2',
            'url' => 'http://88.198.104.148/site2/',
        ],
        [
            'name' => 'site3',
            'url' => 'http://88.198.104.148/site3/',
        ],
    ],
];
