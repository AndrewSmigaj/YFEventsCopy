#!/usr/bin/env php
<?php
/**
 * Generate bcrypt password hash for .env file
 * Usage: php generate_password_hash.php
 */

echo "Generate Password Hash for YFEvents Admin\n";
echo "========================================\n\n";

// Get password from user
echo "Enter password: ";
if (PHP_OS === 'WINNT') {
    $password = trim(fgets(STDIN));
} else {
    // Hide password on Unix systems
    system('stty -echo');
    $password = trim(fgets(STDIN));
    system('stty echo');
    echo "\n";
}

if (empty($password)) {
    echo "Error: Password cannot be empty\n";
    exit(1);
}

// Generate hash
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

echo "\nPassword hash generated successfully!\n";
echo "=====================================\n\n";
echo "Add this to your .env file:\n";
echo "ADMIN_PASSWORD_HASH=$hash\n\n";
echo "Example .env entry:\n";
echo "ADMIN_USERNAME=admin\n";
echo "ADMIN_PASSWORD_HASH=$hash\n";