<?php

return [
    'email' => [
        // IMAP Configuration for receiving emails
        'imap_server' => '{imap.gmail.com:993/imap/ssl}INBOX',
        'username' => 'your-email@gmail.com',
        'password' => 'your-app-password', // Gmail app password
        
        // Alternative configurations for different email providers
        'gmail' => [
            'imap_server' => '{imap.gmail.com:993/imap/ssl}INBOX',
            'username' => 'your-email@gmail.com',
            'password' => 'your-app-password', // App-specific password for Gmail
        ],
        
        'cpanel' => [
            'imap_server' => '{mail.yourdomain.com:993/imap/ssl}INBOX',
            'username' => 'events@yourdomain.com', 
            'password' => 'your-cpanel-password', // cPanel email password
        ],
        
        // SMTP Configuration for sending confirmation emails
        'smtp' => [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'your-email@gmail.com',
            'password' => 'your-app-password',
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
            'events@yourdomain.com',
            'calendar@yourdomain.com', 
            'submit@yourdomain.com',
            'your-email@gmail.com'  // Main receiving address
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
            'from_email' => 'your-email@gmail.com',
            'from_name' => 'Your Community Calendar',
            'reply_to' => 'your-email@gmail.com',
            'template_path' => 'templates/email/event_confirmation.php'
        ]
    ]
];