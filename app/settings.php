<?php
return [
    'system' => [
        'timezone' => 'Europe/Budapest',
        'locale' => 'hu_HU.utf8',
        // csak akkor mutasson részletes hibát (stack trace) a válaszban, ha explicit be van kapcsolva
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
        'csrf' => [
            // route-nevek, amelyeknél nincs CSRF ellenőrzés (pl. külső webhook, API végpont)
            'exempt_routes' => [],
        ],
        'twig' => [
            'template_dir' => TEMPLATES_DIR,
            'cache' => false,
            'cache_dir' => CACHE_DIR.DS.'twig'
        ],
        'mail' => [
            // SMTP kapcsolat a $this->helper->mail-hez (system/helpers/MailHelper.php);
            // username nélkül nincs SMTP hitelesítés (pl. belső relay esetén)
            'host'       => getenv('SMTP_HOST') ?: 'localhost',
            'port'       => (int) (getenv('SMTP_PORT') ?: 25),
            'username'   => getenv('SMTP_USERNAME') ?: null,
            'password'   => getenv('SMTP_PASSWORD') ?: null,
            'secure'     => getenv('SMTP_SECURE') ?: null,
            'from_email' => getenv('SMTP_FROM_EMAIL') ?: null,
            'from_name'  => getenv('SMTP_FROM_NAME') ?: '',
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
        // példa: sessionben tárolt user + route-védelem (app/middlewares/AuthMiddleware.php)
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