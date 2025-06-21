<?php

// Load environment variables
require_once dirname(__DIR__, 4) . '/src/Utils/EnvLoader.php';
use YFEvents\Utils\EnvLoader;
EnvLoader::load();

return [
    'email' => [
        // IMAP Configuration for receiving emails
        'imap_server' => '{imap.gmail.com:993/imap/ssl}INBOX',
        'username' => EnvLoader::get('SMTP_USERNAME', 'yakimafinds@gmail.com'),
        'password' => EnvLoader::get('SMTP_PASSWORD', ''), // Gmail app password from .env
        
        // Alternative configurations for different email providers
        'gmail' => [
            'imap_server' => '{imap.gmail.com:993/imap/ssl}INBOX',
            'username' => EnvLoader::get('SMTP_USERNAME', ''),
            'password' => EnvLoader::get('SMTP_PASSWORD', ''), // App-specific password for Gmail
        ],
        
        'cpanel' => [
            'imap_server' => '{mail.yourdomain.com:993/imap/ssl}INBOX',
            'username' => 'events@yourdomain.com', 
            'password' => '', // cPanel email password
        ],
        
        // SMTP Configuration for sending confirmation emails
        'smtp' => [
            'host' => EnvLoader::get('SMTP_HOST', 'smtp.gmail.com'),
            'port' => (int)EnvLoader::get('SMTP_PORT', 587),
            'username' => EnvLoader::get('SMTP_USERNAME', ''),
            'password' => EnvLoader::get('SMTP_PASSWORD', ''),
            'encryption' => 'tls'
        ],
        
        // Email processing settings
        'processing' => [
            'batch_size' => 50, // Process max 50 emails per run
            'mark_as_read' => true,
            'delete_processed' => false,
            'retry_failed' => true,
            'max_retries' => 3
        ],
        
        // Event submission email addresses (aliases that forward to your main email)
        'submission_addresses' => [
            'events@yakimafinds.com',
            'calendar@yakimafinds.com', 
            'submit@yakimafinds.com',
            'yakimafinds@gmail.com'  // Main receiving address
        ],
        
        // Email validation patterns
        'facebook_domains' => [
            'facebook.com',
            'facebookmail.com',
            'notification.facebook.com',
            'facebooknotifications.com'
        ],
        
        // Auto-response settings
        'confirmation' => [
            'enabled' => true,
            'from_email' => 'yakimafinds@gmail.com',
            'from_name' => 'YakimaFinds Community Calendar',
            'reply_to' => 'yakimafinds@gmail.com',
            'template_path' => 'templates/email/event_confirmation.php'
        ]
    ]
];