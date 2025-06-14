<?php

return [
    'name' => 'YFEvents',
    'version' => '2.0.0',
    'environment' => 'development',
    'debug' => true,
    'timezone' => 'America/Los_Angeles',
    
    'url' => 'http://137.184.245.149',
    
    'logging' => [
        'level' => 'INFO',
        'path' => __DIR__ . '/../storage/logs',
        'daily' => true,
    ],

    'cache' => [
        'default' => 'file',
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path' => __DIR__ . '/../storage/cache',
            ],
        ],
    ],

    'session' => [
        'lifetime' => 120,
        'encrypt' => false,
        'files' => __DIR__ . '/../storage/sessions',
        'connection' => null,
        'table' => 'sessions',
        'store' => null,
        'lottery' => [2, 100],
        'cookie' => 'yfevents_session',
        'path' => '/',
        'domain' => null,
        'secure' => false,
        'http_only' => true,
        'same_site' => 'lax',
    ],
];