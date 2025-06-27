<?php
/**
 * Simple Seller Login Page
 * Direct database connection without autoloader
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// Try to find and include database config
$configPath = dirname(dirname(dirname(__DIR__))) . '/config/database.php';
if (!file_exists($configPath)) {
    die("Database configuration not found at: " . $configPath);
}

require_once $configPath;

// Redirect if already logged in
if (isset($_SESSION['seller_id'])) {
    header('Location: /seller/dashboard.php');
    exit;
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($email && $password) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM yfc_sellers WHERE email = ?");
            $stmt->execute([$email]);
            $seller = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($seller && password_verify($password, $seller['password_hash'])) {
                $_SESSION['seller_id'] = $seller['id'];
                $_SESSION['seller_name'] = $seller['company_name'];
                $_SESSION['seller_email'] = $seller['email'];
                
                // Update last login
                $updateStmt = $pdo->prepare("UPDATE yfc_sellers SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$seller['id']]);
                
                header('Location: /seller/dashboard.php');
                exit;
            } else {
                $error = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    } else {
        $error = 'Please enter both email and password';
    }
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $data = [
        'business_name' => $_POST['business_name'] ?? '',
        'contact_name' => $_POST['contact_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? ''
    ];
    
    // Validate password confirmation
    if ($data['password'] !== ($_POST['password_confirm'] ?? '')) {
        $error = 'Passwords do not match';
    } elseif (strlen($data['password']) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        try {
            // Check if email already exists
            $checkStmt = $pdo->prepare("SELECT id FROM yfc_sellers WHERE email = ?");
            $checkStmt->execute([$data['email']]);
            
            if ($checkStmt->fetch()) {
                $error = 'An account with this email already exists';
            } else {
                // Create account
                $insertStmt = $pdo->prepare("
                    INSERT INTO yfc_sellers (company_name, contact_name, email, username, password_hash, phone, address, created_at, status, email_verified) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'active', 1)
                ");
                
                $username = strtolower(str_replace(' ', '', $data['business_name']));
                
                $success = $insertStmt->execute([
                    $data['business_name'],
                    $data['contact_name'],
                    $data['email'],
                    $username,
                    password_hash($data['password'], PASSWORD_DEFAULT),
                    $data['phone'],
                    $data['address']
                ]);
                
                if ($success) {
                    // Log them in automatically
                    $_SESSION['seller_id'] = $pdo->lastInsertId();
                    $_SESSION['seller_name'] = $data['business_name'];
                    $_SESSION['seller_email'] = $data['email'];
                    
                    header('Location: /seller/dashboard.php');
                    exit;
                } else {
                    $error = 'Failed to create account. Please try again.';
                }
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login - YF Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            min-height: 100vh;
        }
        .login-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header h1 {
            margin: 0;
            font-size: 2rem;
        }
        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .login-body {
            padding: 40px;
        }
        .nav-tabs .nav-link {
            color: #666;
            border: none;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            padding: 10px 20px;
        }
        .nav-tabs .nav-link.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: none;
        }
        .form-floating {
            margin-bottom: 15px;
        }
        .btn-primary {
            background: #667eea;
            border: none;
            padding: 10px 30px;
        }
        .btn-primary:hover {
            background: #764ba2;
        }
        .benefits {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
        }
        .benefit-item {
            display: flex;
            align-items: start;
            margin-bottom: 10px;
        }
        .benefit-item i {
            color: #667eea;
            margin-right: 10px;
            margin-top: 3px;
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="login-card">
            <div class="login-header">
                <h1><i class="bi bi-shop"></i> YF Marketplace</h1>
                <p>Seller Portal - Manage Your Sales & Classifieds</p>
            </div>
            
            <div class="login-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-bs-toggle="tab" href="#login-tab">Sign In</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="tab" href="#register-tab">Create Account</a>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- Login Tab -->
                    <div class="tab-pane fade show active" id="login-tab">
                        <form method="POST">
                            <input type="hidden" name="action" value="login">
                            
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="login-email" name="email" 
                                       placeholder="Email" required autofocus>
                                <label for="login-email">Email Address</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="password" class="form-control" id="login-password" name="password" 
                                       placeholder="Password" required>
                                <label for="login-password">Password</label>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="remember">
                                    <label class="form-check-label" for="remember">
                                        Remember me
                                    </label>
                                </div>
                                <a href="/seller/forgot-password" class="text-decoration-none">Forgot password?</a>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-box-arrow-in-right"></i> Sign In
                            </button>
                        </form>
                        
                        <div class="benefits">
                            <h5>Seller Benefits:</h5>
                            <div class="benefit-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Manage estate sales and classified listings in one place</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Reach thousands of local buyers</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Professional tools for inventory management</span>
                            </div>
                            <div class="benefit-item">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Real-time analytics and reporting</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Register Tab -->
                    <div class="tab-pane fade" id="register-tab">
                        <form method="POST">
                            <input type="hidden" name="action" value="register">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="business_name" name="business_name" 
                                               placeholder="Business Name" required>
                                        <label for="business_name">Business/Seller Name</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="contact_name" name="contact_name" 
                                               placeholder="Contact Name" required>
                                        <label for="contact_name">Contact Person</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="email" class="form-control" id="reg-email" name="email" 
                                       placeholder="Email" required>
                                <label for="reg-email">Email Address</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       placeholder="Phone" required>
                                <label for="phone">Phone Number</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="address" name="address" 
                                          placeholder="Address" style="height: 80px"></textarea>
                                <label for="address">Business Address (Optional)</label>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="reg-password" name="password" 
                                               placeholder="Password" required>
                                        <label for="reg-password">Password</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                                               placeholder="Confirm Password" required>
                                        <label for="password_confirm">Confirm Password</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="/terms" target="_blank">Terms of Service</a> and 
                                    <a href="/privacy" target="_blank">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-person-plus"></i> Create Account
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <a href="/" class="text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Back to Homepage
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>