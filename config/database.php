<?php

use YFEvents\Infrastructure\Utils\EnvLoader;

return [
    'database' => [
        'host' => EnvLoader::get('DB_HOST', 'localhost'),
        'name' => EnvLoader::get('DB_DATABASE', 'yakima_finds'),
        'username' => EnvLoader::get('DB_USERNAME', 'yfevents'),
        'password' => EnvLoader::get('DB_PASSWORD', 'yfevents_pass'),
        'charset' => EnvLoader::get('DB_CHARSET', 'utf8mb4'),
        'options' => [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . EnvLoader::get('DB_CHARSET', 'utf8mb4') . " COLLATE " . EnvLoader::get('DB_COLLATION', 'utf8mb4_unicode_ci')
        ]
    ]
];