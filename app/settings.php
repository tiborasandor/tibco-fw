<?php
return [
    'system' => [
        'timezone' => 'Europe/Budapest',
        'locale' => 'hu_HU.utf8',
        // only show detailed error output (stack trace) in the response when explicitly enabled
        'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),
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
        'twig' => [
            'template_dir' => TEMPLATES_DIR,
            'cache' => false,
            'cache_dir' => CACHE_DIR.DS.'twig'
        ],
        'database' => [
            'testdb' => [
                'driver' => 'mysql',
                'host' => getenv('DB_HOST') ?: 'hostname',
                'database' => getenv('DB_DATABASE') ?: 'db_name',
                'username' => getenv('DB_USERNAME') ?: 'db_user',
                'password' => getenv('DB_PASSWORD') ?: 'db_pass',
                'charset'   => 'utf8',
                'collation' => 'utf8_general_ci',
                'prefix'    => '',
            ]
        ]
    ],
    'middlewares' => [
        'RequestLogMiddleware' => ['enabled' => true, 'weight' => 0],
    ],
    'modules' => [
        'auth' => [
            'enabled' => true,
            'weight' => 0
        ],
        'example' => [
            'enabled' => true,
            'weight' => 2
        ]
    ]
];
?>