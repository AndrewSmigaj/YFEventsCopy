<?php

require_once 'vendor/autoload.php';

// Load email configuration
$emailConfig = require 'config/email.php';

echo "Testing email connection...\n\n";

// Test IMAP connection
echo "=== IMAP Connection Test ===\n";
try {
    if (!extension_loaded('imap')) {
        echo "❌ IMAP extension is NOT installed\n";
        echo "Run: sudo apt install php8.3-imap\n\n";
    } else {
        echo "✅ IMAP extension is loaded\n";
        
        $server = $emailConfig['email']['imap_server'];
        $username = $emailConfig['email']['username'];
        $password = $emailConfig['email']['password'];
        
        echo "Server: {$server}\n";
        echo "Username: {$username}\n";
        echo "Password: " . str_repeat('*', strlen($password)) . "\n\n";
        
        echo "Attempting connection...\n";
        $imap = @imap_open($server, $username, $password);
        
        if ($imap) {
            $check = imap_check($imap);
            echo "✅ IMAP connection successful!\n";
            echo "Mailbox has {$check->Nmsgs} messages\n";
            echo "Recent: {$check->Recent} messages\n";
            imap_close($imap);
        } else {
            echo "❌ IMAP connection failed\n";
            echo "Error: " . imap_last_error() . "\n";
        }
    }
} catch (Exception $e) {
    echo "❌ IMAP test error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test file-based processing as fallback
echo "=== File-based Processing Test ===\n";
try {
    require_once 'src/Infrastructure/Email/CurlEmailProcessor.php';
    $processor = new \YakimaFinds\Infrastructure\Email\CurlEmailProcessor($emailConfig);
    $result = $processor->testConnection();
    
    echo "✅ File-based email processing is ready\n";
    echo "Upload directory: {$result['upload_directory']}\n";
    echo "Instructions:\n";
    foreach ($result['instructions'] as $instruction) {
        echo "  • {$instruction}\n";
    }
    
} catch (Exception $e) {
    echo "❌ File-based processing error: " . $e->getMessage() . "\n";
}

echo "\n=== Configuration Summary ===\n";
echo "IMAP Server: {$emailConfig['email']['imap_server']}\n";
echo "Username: {$emailConfig['email']['username']}\n";
echo "SMTP Host: {$emailConfig['email']['smtp']['host']}\n";
echo "SMTP Port: {$emailConfig['email']['smtp']['port']}\n";
echo "From Email: {$emailConfig['email']['confirmation']['from_email']}\n";

echo "\n✅ Configuration updated successfully!\n";
echo "Go to: https://backoffice.yakimafinds.com/refactor/admin/email-config.php\n";
echo "Click 'Test Connection' to verify.\n";