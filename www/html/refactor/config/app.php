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

    'sms' => [
        'enabled' => false,
        'provider' => 'twilio', // twilio, aws, nexmo
        'from_number' => '',
        
        // Twilio Configuration
        'twilio' => [
            'account_sid' => '',
            'auth_token' => '',
            'from_number' => '',
        ],
        
        // AWS SNS Configuration
        'aws' => [
            'key' => '',
            'secret' => '',
            'region' => 'us-east-1',
            'from_number' => '',
        ],
        
        // Nexmo/Vonage Configuration
        'nexmo' => [
            'api_key' => '',
            'api_secret' => '',
            'from_number' => '',
        ],
        
        // Test mode settings
        'test_mode' => true,
        'test_numbers' => [], // Numbers that will receive actual SMS in test mode
    ],

    'email' => [
        'enabled' => true,
        'driver' => 'mail', // mail, smtp, sendmail
        'from_email' => 'noreply@yakimafinds.com',
        'from_name' => 'YakimaFinds',
        
        // SMTP Configuration
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => '',
            'password' => '',
            'encryption' => 'tls', // tls, ssl
        ],
        
        // Test mode settings
        'test_mode' => false,
        'test_emails' => [], // Emails that will receive actual emails in test mode
    ],
];