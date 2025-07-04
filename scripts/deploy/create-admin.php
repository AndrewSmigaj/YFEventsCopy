#!/usr/bin/env php
<?php
/**
 * YFEvents Admin User Creator
 * 
 * Creates admin users in the YFAuth system with proper role assignment.
 * 
 * Usage: php create-admin.php
 */

// Color codes for output
define('GREEN', "\033[0;32m");
define('RED', "\033[0;31m");
define('YELLOW', "\033[1;33m");
define('BLUE', "\033[0;34m");
define('NC', "\033[0m"); // No Color

// Change to project root
$rootDir = dirname(dirname(__DIR__));
chdir($rootDir);

// Load environment configuration
if (!file_exists('.env')) {
    die(RED . "Error: .env file not found. Run installer.php first.\n" . NC);
}

// Parse .env file
$env = parse_ini_file('.env');
if (!$env) {
    die(RED . "Error: Could not parse .env file\n" . NC);
}

// Database configuration
$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbName = $env['DB_DATABASE'] ?? 'yakima_finds';
$dbUser = $env['DB_USERNAME'] ?? 'yfevents';
$dbPass = $env['DB_PASSWORD'] ?? '';

echo BLUE . "YFEvents Admin User Creator\n";
echo "===========================\n\n" . NC;

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die(RED . "Database connection failed: " . $e->getMessage() . "\n" . NC);
}

// Check if YFAuth tables exist
try {
    $stmt = $pdo->query("SELECT 1 FROM yfa_auth_users LIMIT 1");
} catch (PDOException $e) {
    die(RED . "YFAuth tables not found. Run run-sql-files.php first.\n" . NC);
}

// Get available roles
try {
    $stmt = $pdo->query("SELECT id, name, display_name, description FROM yfa_auth_roles ORDER BY id");
    $roles = $stmt->fetchAll();
    
    if (empty($roles)) {
        die(RED . "No roles found in database. YFAuth may not be properly installed.\n" . NC);
    }
} catch (PDOException $e) {
    die(RED . "Error fetching roles: " . $e->getMessage() . "\n" . NC);
}

// Function to prompt for input
function prompt($message, $hideInput = false) {
    echo $message;
    
    if ($hideInput) {
        // Hide password input
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'powershell -Command "$p = Read-Host -AsSecureString; $p = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($p)); echo $p"';
            $password = rtrim(shell_exec($cmd));
            echo "\n";
            return $password;
        } else {
            system('stty -echo');
            $password = trim(fgets(STDIN));
            system('stty echo');
            echo "\n";
            return $password;
        }
    }
    
    return trim(fgets(STDIN));
}

// Function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate username
function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
}

// Collect user information
echo "Please provide the admin user details:\n\n";

// Email
do {
    $email = prompt("Email address: ");
    if (!isValidEmail($email)) {
        echo RED . "Invalid email format. Please try again.\n" . NC;
    }
} while (!isValidEmail($email));

// Check if email already exists
$stmt = $pdo->prepare("SELECT id FROM yfa_auth_users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    die(RED . "Error: A user with this email already exists.\n" . NC);
}

// Username
do {
    $username = prompt("Username (letters, numbers, underscore, 3-50 chars): ");
    if (!isValidUsername($username)) {
        echo RED . "Invalid username format. Please try again.\n" . NC;
    }
} while (!isValidUsername($username));

// Check if username already exists
$stmt = $pdo->prepare("SELECT id FROM yfa_auth_users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    die(RED . "Error: A user with this username already exists.\n" . NC);
}

// Password
do {
    $password = prompt("Password (min 8 characters): ", true);
    if (strlen($password) < 8) {
        echo RED . "Password must be at least 8 characters. Please try again.\n" . NC;
        continue;
    }
    
    $confirmPassword = prompt("Confirm password: ", true);
    if ($password !== $confirmPassword) {
        echo RED . "Passwords do not match. Please try again.\n" . NC;
        $password = '';
    }
} while (strlen($password) < 8);

// First and last name
$firstName = prompt("First name (optional): ");
$lastName = prompt("Last name (optional): ");

// Select role
echo "\nAvailable roles:\n";
foreach ($roles as $index => $role) {
    echo YELLOW . ($index + 1) . ". " . NC . $role['display_name'] . " - " . $role['description'] . "\n";
}

do {
    $roleChoice = prompt("\nSelect role (1-" . count($roles) . "): ");
    $roleIndex = intval($roleChoice) - 1;
} while ($roleIndex < 0 || $roleIndex >= count($roles));

$selectedRole = $roles[$roleIndex];

// Confirm details
echo "\n" . BLUE . "Summary:\n" . NC;
echo "Email: $email\n";
echo "Username: $username\n";
echo "First name: " . ($firstName ?: '(not provided)') . "\n";
echo "Last name: " . ($lastName ?: '(not provided)') . "\n";
echo "Role: " . $selectedRole['display_name'] . "\n";

$confirm = prompt("\nCreate this admin user? (y/n): ");
if (strtolower($confirm) !== 'y') {
    die(YELLOW . "User creation cancelled.\n" . NC);
}

// Create the user
try {
    $pdo->beginTransaction();
    
    // Insert user
    $stmt = $pdo->prepare("
        INSERT INTO yfa_auth_users 
        (email, username, password_hash, first_name, last_name, status, email_verified, created_at, updated_at)
        VALUES 
        (?, ?, ?, ?, ?, 'active', 1, NOW(), NOW())
    ");
    
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $stmt->execute([
        $email,
        $username,
        $passwordHash,
        $firstName ?: null,
        $lastName ?: null
    ]);
    
    $userId = $pdo->lastInsertId();
    
    // Assign role
    $stmt = $pdo->prepare("
        INSERT INTO yfa_auth_user_roles (user_id, role_id, assigned_at)
        VALUES (?, ?, NOW())
    ");
    $stmt->execute([$userId, $selectedRole['id']]);
    
    $pdo->commit();
    
    echo GREEN . "\n✓ Admin user created successfully!\n\n" . NC;
    echo "User details:\n";
    echo "- User ID: $userId\n";
    echo "- Email: $email\n";
    echo "- Username: $username\n";
    echo "- Role: " . $selectedRole['display_name'] . "\n\n";
    echo "You can now log in at: " . BLUE . "http://your-domain.com/admin" . NC . "\n";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    die(RED . "Error creating user: " . $e->getMessage() . "\n" . NC);
}

// Ask if they want to create another admin
echo "\n";
$another = prompt("Create another admin user? (y/n): ");
if (strtolower($another) === 'y') {
    echo "\n";
    include __FILE__;
}