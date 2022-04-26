<?php
return [
    'system' => [
        'timezone' => 'Europe/Budapest',
        'locale' => 'hu_HU.utf8',
        'logger' => [
            'name' => 'APP',
            'format' => '[%datetime%] %level_name% %message% %context% %extra%',
            'time_format' => 'Y-m-d H:i:s',
            'log_dir' => ROOT_DIR.DS.'log'
        ],
        'twig' => [
            'template_dir' => TEMPLATES_DIR,
            'cache' => false,
            'cache_dir' => CACHE_DIR.DS.'twig'
        ]
    ],
    'modules' => [
        'example' => [
            'enabled' => true,
            'weight' => 2
        ]
    ]
];
?>