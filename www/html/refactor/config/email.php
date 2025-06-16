<?php

return [
    'email' => [
        // IMAP Configuration for receiving emails
        'imap_server' => '{imap.gmail.com:993/imap/ssl}INBOX',
        'username' => 'yakimafinds@gmail.com',
        'password' => 'turgcygxyudobvgz', // Gmail app password
        
        // Alternative configurations for different email providers
        'gmail' => [
            'imap_server' => '{imap.gmail.com:993/imap/ssl}INBOX',
            'username' => 'events@yakimafinds.com',
            'password' => '', // App-specific password for Gmail
        ],
        
        'cpanel' => [
            'imap_server' => '{mail.yakimafinds.com:993/imap/ssl}INBOX',
            'username' => 'events@yakimafinds.com', 
            'password' => '', // cPanel email password
        ],
        
        // SMTP Configuration for sending confirmation emails
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'yakimafinds@gmail.com',
            'password' => 'turgcygxyudobvgz',
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
        
        // Event submission email addresses (aliases that forward to yakimafinds@gmail.com)
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