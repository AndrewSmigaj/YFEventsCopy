<?php

declare(strict_types=1);

/**
 * Setup script for Email Events feature
 * Run this script to configure email processing for Facebook events
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use YFEvents\Infrastructure\Services\EmailEventProcessor;

echo "🚀 Email Events Setup Script\n";
echo "============================\n\n";

// Check requirements
echo "Checking requirements...\n";

$requirements = [
    'PHP IMAP Extension' => extension_loaded('imap'),
    'PDO Extension' => extension_loaded('pdo'),
    'MySQL PDO Driver' => extension_loaded('pdo_mysql'),
    'Mail Function' => function_exists('mail'),
];

$allGood = true;
foreach ($requirements as $requirement => $check) {
    $status = $check ? '✅ OK' : '❌ MISSING';
    echo "  {$requirement}: {$status}\n";
    if (!$check) {
        $allGood = false;
    }
}

if (!$allGood) {
    echo "\n❌ Some requirements are missing. Please install required extensions.\n";
    echo "For Ubuntu/Debian: sudo apt-get install php-imap php-mysql\n";
    echo "For CentOS/RHEL: sudo yum install php-imap php-mysql\n\n";
}

// Create logs directory
echo "\nCreating directories...\n";
$logsDir = dirname(__DIR__) . '/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
    echo "  ✅ Created logs directory: {$logsDir}\n";
} else {
    echo "  ✅ Logs directory exists: {$logsDir}\n";
}

// Test database connection
echo "\nTesting database connection...\n";
try {
    $config = require dirname(__DIR__) . '/config/database.php';
    $dbConfig = $config['database'];
    
    $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    echo "  ✅ Database connection successful\n";
    
    // Check if events table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'events'");
    if ($stmt->rowCount() > 0) {
        echo "  ✅ Events table exists\n";
    } else {
        echo "  ❌ Events table missing - please run database migrations\n";
    }
    
} catch (Exception $e) {
    echo "  ❌ Database connection failed: " . $e->getMessage() . "\n";
}

// Check email configuration
echo "\nChecking email configuration...\n";
try {
    $emailConfig = require dirname(__DIR__) . '/config/email.php';
    
    if (!empty($emailConfig['email']['username'])) {
        echo "  ✅ Email username configured\n";
    } else {
        echo "  ⚠️  Email username not configured\n";
    }
    
    if (!empty($emailConfig['email']['password'])) {
        echo "  ✅ Email password configured\n";
    } else {
        echo "  ⚠️  Email password not configured\n";
    }
    
} catch (Exception $e) {
    echo "  ❌ Email config error: " . $e->getMessage() . "\n";
}

// Test email processing
echo "\nTesting email processor...\n";
try {
    
    $emailConfig = require dirname(__DIR__) . '/config/email.php';
    $processor = new EmailEventProcessor($pdo, $emailConfig);
    
    echo "  ✅ EmailEventProcessor created successfully\n";
    
    // Test statistics method
    $stats = $processor->getProcessingStats();
    echo "  ✅ Statistics method working - found {$stats['total_events']} total events\n";
    
} catch (Exception $e) {
    echo "  ❌ Email processor error: " . $e->getMessage() . "\n";
}

// Setup cron job instructions
echo "\n📋 Cron Job Setup Instructions\n";
echo "===============================\n";
echo "Add this line to your crontab (run 'crontab -e'):\n\n";

$scriptPath = dirname(__DIR__) . '/scripts/process_event_emails.php';
echo "*/15 * * * * /usr/bin/php {$scriptPath} >> " . dirname(__DIR__) . "/logs/cron.log 2>&1\n\n";

echo "This will:\n";
echo "  - Process emails every 15 minutes\n";
echo "  - Log output to cron.log\n";
echo "  - Automatically add Facebook events to the calendar\n\n";

// Email setup instructions
echo "📧 Email Account Setup Instructions\n";
echo "=====================================\n";
echo "1. Create email account: events@yakimafinds.com\n";
echo "2. Update /config/email.php with credentials:\n";
echo "   - IMAP server settings\n";
echo "   - Username and password\n";
echo "   - SMTP settings for confirmations\n\n";

echo "3. Test with Facebook:\n";
echo "   - Create a test Facebook event\n";
echo "   - Invite events@yakimafinds.com\n";
echo "   - Run: php scripts/process_event_emails.php\n";
echo "   - Check admin panel at /admin/email-events.php\n\n";

// Security recommendations
echo "🔒 Security Recommendations\n";
echo "=============================\n";
echo "1. Use strong password for email account\n";
echo "2. Enable 2FA if supported by email provider\n";
echo "3. Restrict IMAP access to server IP only\n";
echo "4. Monitor logs for suspicious activity\n";
echo "5. Set up email forwarding rules if needed\n\n";

// Final status
if ($allGood) {
    echo "🎉 Setup Complete!\n";
    echo "=================\n";
    echo "Email events system is ready to use.\n";
    echo "Visit /admin/email-events.php to manage and monitor.\n\n";
    
    echo "Next steps:\n";
    echo "1. Configure email credentials in config/email.php\n";
    echo "2. Set up cron job for automated processing\n";
    echo "3. Share submission instructions with businesses\n";
    echo "4. Test with a Facebook event invitation\n\n";
} else {
    echo "⚠️  Setup Incomplete\n";
    echo "===================\n";
    echo "Please resolve the issues above before using email events.\n\n";
}

echo "For support, check the documentation or contact the development team.\n";
echo "Log files are stored in: {$logsDir}\n\n";