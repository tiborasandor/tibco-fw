<?php
$devMode = getenv('DEV_MODE') === false ? true : filter_var(getenv('DEV_MODE'), FILTER_VALIDATE_BOOLEAN);

return [
    'system' => [
        'timezone' => 'Europe/Budapest',
        'locale' => 'hu_HU.utf8',
        'debug' => filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN),
        'dev_mode' => $devMode,
        'session' => [
            'name'          => 'tibco',
            'cache_expire'  => 0,
        ],
        'logger' => [
            'name' => 'APP',
            'format' => '[%datetime%] %level_name% %message% %context% %extra%',
            'time_format' => 'Y-m-d H:i:s',
            'log_dir' => LOG_DIR
        ],
        'csrf' => [
            'exempt_routes' => [],
        ],
        'cron' => [
            'enabled' => true,
            'state_file' => LOG_DIR.DS.'cron'.DS.'state.json',
        ],
        'twig' => [
            'template_dir' => TEMPLATES_DIR,
            'cache' => !$devMode,
            'cache_dir' => CACHE_DIR.DS.'twig'
        ],
        'mail' => [
            'host'       => getenv('SMTP_HOST') ?: null,
            'port'       => (int) getenv('SMTP_PORT') ?: null,
            'username'   => getenv('SMTP_USERNAME') ?: null,
            'password'   => getenv('SMTP_PASSWORD') ?: null,
            'secure'     => getenv('SMTP_SECURE') ?: null,
            'from_email' => getenv('SMTP_FROM_EMAIL') ?: null,
            'from_name'  => getenv('SMTP_FROM_NAME') ?: null,
        ],
        'database' => [
            'testdb' => [
                'driver' => 'mysql',
                'host' => getenv('DB_HOST') ?: null,
                'database' => getenv('DB_DATABASE') ?: null,
                'username' => getenv('DB_USERNAME') ?: null,
                'password' => getenv('DB_PASSWORD') ?: null,
                'charset'   => 'utf8',
                'collation' => 'utf8_general_ci',
                'prefix'    => '',
            ]
        ]
    ],
    'middlewares' => [
        'RequestLogMiddleware' => ['enabled' => true, 'weight' => 0],
        'AuthMiddleware' => [
            'enabled' => true,
            'weight' => 1,
            'protected_routes' => ['example_secret'],
        ],
    ],
    'modules' => [
        'example' => [
            'enabled' => true,
            'weight' => 0
        ]
    ]
];
?>