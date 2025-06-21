#!/usr/bin/env php
<?php
/**
 * Setup production environment for YFEvents
 * Usage: php setup_production.php
 */

echo "\n";
echo "YFEvents Production Setup\n";
echo "========================\n\n";
echo "This script will help you set up secure credentials for production.\n\n";

// Check if .env exists
$envFile = dirname(__DIR__) . '/.env';
$envExampleFile = dirname(__DIR__) . '/.env.example';

if (file_exists($envFile)) {
    echo "WARNING: .env file already exists!\n";
    echo "Do you want to overwrite it? (yes/no): ";
    $answer = strtolower(trim(fgets(STDIN)));
    if ($answer !== 'yes' && $answer !== 'y') {
        echo "Setup cancelled.\n";
        exit(0);
    }
}

// Copy .env.example if it doesn't exist
if (!file_exists($envExampleFile)) {
    echo "ERROR: .env.example file not found!\n";
    exit(1);
}

echo "\n--- Database Configuration ---\n";
echo "Enter database host [localhost]: ";
$dbHost = trim(fgets(STDIN)) ?: 'localhost';

echo "Enter database name [yakima_finds]: ";
$dbName = trim(fgets(STDIN)) ?: 'yakima_finds';

echo "Enter database username: ";
$dbUser = trim(fgets(STDIN));
if (empty($dbUser)) {
    echo "ERROR: Database username is required!\n";
    exit(1);
}

echo "Enter database password: ";
if (PHP_OS !== 'WINNT') {
    system('stty -echo');
}
$dbPassword = trim(fgets(STDIN));
if (PHP_OS !== 'WINNT') {
    system('stty echo');
    echo "\n";
}

echo "\n--- Admin Authentication ---\n";
echo "Enter admin username [admin]: ";
$adminUsername = trim(fgets(STDIN)) ?: 'admin';

echo "Enter admin password: ";
if (PHP_OS !== 'WINNT') {
    system('stty -echo');
}
$adminPassword = trim(fgets(STDIN));
if (PHP_OS !== 'WINNT') {
    system('stty echo');
    echo "\n";
}

if (empty($adminPassword)) {
    echo "ERROR: Admin password is required!\n";
    exit(1);
}

// Generate password hash
$adminPasswordHash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);

echo "\n--- Email Configuration (optional) ---\n";
echo "Enter SMTP host [smtp.gmail.com]: ";
$smtpHost = trim(fgets(STDIN)) ?: 'smtp.gmail.com';

echo "Enter SMTP port [587]: ";
$smtpPort = trim(fgets(STDIN)) ?: '587';

echo "Enter SMTP username (email address): ";
$smtpUsername = trim(fgets(STDIN));

$smtpPassword = '';
if (!empty($smtpUsername)) {
    echo "Enter SMTP password (app password for Gmail): ";
    if (PHP_OS !== 'WINNT') {
        system('stty -echo');
    }
    $smtpPassword = trim(fgets(STDIN));
    if (PHP_OS !== 'WINNT') {
        system('stty echo');
        echo "\n";
    }
}

// Generate random keys
$sessionSecret = bin2hex(random_bytes(16));
$encryptionKey = bin2hex(random_bytes(16));

// Create .env content
$envContent = <<<ENV
# YFEvents Environment Configuration
# Generated on: {date('Y-m-d H:i:s')}

# Database Configuration
DB_HOST=$dbHost
DB_NAME=$dbName
DB_USER=$dbUser
DB_PASSWORD=$dbPassword

# Admin Authentication
ADMIN_USERNAME=$adminUsername
ADMIN_PASSWORD_HASH=$adminPasswordHash

# Email Configuration
SMTP_HOST=$smtpHost
SMTP_PORT=$smtpPort
SMTP_USERNAME=$smtpUsername
SMTP_PASSWORD=$smtpPassword
SMTP_FROM_EMAIL=$smtpUsername
SMTP_FROM_NAME=YakimaFinds

# Application Settings
APP_ENV=production
APP_DEBUG=false
APP_URL=https://backoffice.yakimafinds.com

# Security
SESSION_SECRET=$sessionSecret
ENCRYPTION_KEY=$encryptionKey
ENV;

// Write .env file
if (file_put_contents($envFile, $envContent) === false) {
    echo "ERROR: Failed to write .env file!\n";
    exit(1);
}

// Set proper permissions
chmod($envFile, 0600);

echo "\n";
echo "✅ Production environment configured successfully!\n";
echo "================================================\n\n";
echo "The .env file has been created with secure permissions (0600).\n\n";
echo "IMPORTANT REMINDERS:\n";
echo "- Keep the .env file secure and never commit it to version control\n";
echo "- Ensure the web server user can read the .env file\n";
echo "- Backup your .env file in a secure location\n";
echo "- All hardcoded passwords have been removed from the codebase\n\n";
echo "Admin Login:\n";
echo "- Username: $adminUsername\n";
echo "- Password: [the password you entered]\n\n";

if (!empty($smtpUsername)) {
    echo "Email configured for: $smtpUsername\n\n";
}

echo "Next steps:\n";
echo "1. Test admin login at: https://backoffice.yakimafinds.com/admin/login.php\n";
echo "2. Verify database connection is working\n";
echo "3. Test email functionality if configured\n\n";