<?php

use YFEvents\Infrastructure\Utils\EnvLoader;

return [
    'name' => EnvLoader::get('APP_NAME', 'YFEvents'),
    'version' => EnvLoader::get('APP_VERSION', '2.0.0'),
    'environment' => EnvLoader::get('APP_ENV', 'development'),
    'debug' => filter_var(EnvLoader::get('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
    'timezone' => EnvLoader::get('APP_TIMEZONE', 'America/Los_Angeles'),
    
    'url' => EnvLoader::get('APP_URL', 'http://localhost'),
    
    'logging' => [
        'level' => EnvLoader::get('LOG_LEVEL', 'INFO'),
        'path' => __DIR__ . '/../' . EnvLoader::get('LOG_PATH', 'storage/logs'),
        'daily' => filter_var(EnvLoader::get('LOG_DAILY', 'true'), FILTER_VALIDATE_BOOLEAN),
    ],

    'cache' => [
        'default' => EnvLoader::get('CACHE_DRIVER', 'file'),
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path' => __DIR__ . '/../' . EnvLoader::get('CACHE_PATH', 'storage/cache'),
            ],
        ],
    ],

    'session' => [
        'lifetime' => (int) EnvLoader::get('SESSION_LIFETIME', '120'),
        'encrypt' => filter_var(EnvLoader::get('SESSION_ENCRYPT', 'false'), FILTER_VALIDATE_BOOLEAN),
        'files' => __DIR__ . '/../' . EnvLoader::get('SESSION_PATH', 'storage/sessions'),
        'connection' => null,
        'table' => 'sessions',
        'store' => null,
        'lottery' => [2, 100],
        'cookie' => EnvLoader::get('SESSION_COOKIE', 'yfevents_session'),
        'path' => '/',
        'domain' => null,
        'secure' => filter_var(EnvLoader::get('SESSION_SECURE_COOKIE', 'false'), FILTER_VALIDATE_BOOLEAN),
        'http_only' => filter_var(EnvLoader::get('SESSION_HTTP_ONLY', 'true'), FILTER_VALIDATE_BOOLEAN),
        'same_site' => EnvLoader::get('SESSION_SAME_SITE', 'lax'),
    ],

    'sms' => [
        'enabled' => filter_var(EnvLoader::get('SMS_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN),
        'provider' => EnvLoader::get('SMS_PROVIDER', 'twilio'), // twilio, aws, nexmo
        'from_number' => EnvLoader::get('SMS_FROM_NUMBER', ''),
        
        // Twilio Configuration
        'twilio' => [
            'account_sid' => EnvLoader::get('TWILIO_ACCOUNT_SID', ''),
            'auth_token' => EnvLoader::get('TWILIO_AUTH_TOKEN', ''),
            'from_number' => EnvLoader::get('TWILIO_FROM_NUMBER', ''),
        ],
        
        // AWS SNS Configuration
        'aws' => [
            'key' => EnvLoader::get('AWS_KEY', ''),
            'secret' => EnvLoader::get('AWS_SECRET', ''),
            'region' => EnvLoader::get('AWS_REGION', 'us-east-1'),
            'from_number' => '',
        ],
        
        // Nexmo/Vonage Configuration
        'nexmo' => [
            'api_key' => EnvLoader::get('NEXMO_API_KEY', ''),
            'api_secret' => EnvLoader::get('NEXMO_API_SECRET', ''),
            'from_number' => '',
        ],
        
        // Test mode settings
        'test_mode' => filter_var(EnvLoader::get('SMS_TEST_MODE', 'true'), FILTER_VALIDATE_BOOLEAN),
        'test_numbers' => [], // Numbers that will receive actual SMS in test mode
    ],

    'email' => [
        'enabled' => filter_var(EnvLoader::get('MAIL_ENABLED', 'true'), FILTER_VALIDATE_BOOLEAN),
        'driver' => EnvLoader::get('MAIL_DRIVER', 'mail'), // mail, smtp, sendmail
        'from_email' => EnvLoader::get('MAIL_FROM_EMAIL', 'noreply@yakimafinds.com'),
        'from_name' => EnvLoader::get('MAIL_FROM_NAME', 'YakimaFinds'),
        
        // SMTP Configuration
        'smtp' => [
            'host' => EnvLoader::get('MAIL_HOST', 'smtp.gmail.com'),
            'port' => (int) EnvLoader::get('MAIL_PORT', '587'),
            'username' => EnvLoader::get('MAIL_USERNAME', ''),
            'password' => EnvLoader::get('MAIL_PASSWORD', ''),
            'encryption' => EnvLoader::get('MAIL_ENCRYPTION', 'tls'), // tls, ssl
        ],
        
        // Test mode settings
        'test_mode' => filter_var(EnvLoader::get('MAIL_TEST_MODE', 'false'), FILTER_VALIDATE_BOOLEAN),
        'test_emails' => [], // Emails that will receive actual emails in test mode
    ],
];