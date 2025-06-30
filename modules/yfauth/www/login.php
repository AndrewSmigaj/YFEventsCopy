<?php
// YFAuth - Login Page
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use YFEvents\Modules\YFAuth\Services\AuthService;

session_start();

// Initialize auth service
$authService = new AuthService($pdo);

$error = '';
$message = '';

// Check if already logged in
if (isset($_SESSION['yfa_session_id'])) {
    $user = $authService->verifySession($_SESSION['yfa_session_id']);
    if ($user) {
        $returnUrl = $_GET['return'] ?? '/modules/yfauth/www/dashboard.php';
        header('Location: ' . $returnUrl);
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $credential = trim($_POST['credential'] ?? '');
        $password = $_POST['password'] ?? '';
        $rememberMe = isset($_POST['remember_me']);
        
        if (empty($credential) || empty($password)) {
            throw new Exception('Username/email and password are required.');
        }
        
        $result = $authService->authenticate($credential, $password, $rememberMe);
        
        // Store session
        $_SESSION['yfa_session_id'] = $result['session_id'];
        $_SESSION['yfa_user'] = $result['user'];
        
        // Set remember me cookie
        if ($rememberMe) {
            setcookie('yfa_session', $result['session_id'], $result['expires_at'], '/');
        }
        
        // Redirect
        $returnUrl = $_GET['return'] ?? '/modules/yfauth/www/dashboard.php';
        header('Location: ' . $returnUrl);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check remember me cookie
if (empty($_SESSION['yfa_session_id']) && isset($_COOKIE['yfa_session'])) {
    $user = $authService->verifySession($_COOKIE['yfa_session']);
    if ($user) {
        $_SESSION['yfa_session_id'] = $_COOKIE['yfa_session'];
        $_SESSION['yfa_user'] = $user;
        
        $returnUrl = $_GET['return'] ?? '/modules/yfauth/www/dashboard.php';
        header('Location: ' . $returnUrl);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - YFAuth</title>
    
    <meta name="description" content="Secure login to your YFEvents account">
    <meta name="robots" content="noindex, nofollow">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-logo {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #667eea;
        }
        
        .login-title {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .login-subtitle {
            color: #7f8c8d;
            font-size: 1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group input:invalid {
            border-color: #e74c3c;
        }
        
        .form-group .form-help {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: normal;
            color: #495057;
        }
        
        .btn {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 1rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
            transform: translateY(-1px);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .login-links {
            text-align: center;
            margin-top: 2rem;
        }
        
        .login-links a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
            margin: 0 1rem;
        }
        
        .login-links a:hover {
            text-decoration: underline;
        }
        
        .divider {
            text-align: center;
            margin: 2rem 0;
            position: relative;
        }
        
        .divider:before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
        }
        
        .divider span {
            background: white;
            padding: 0 1rem;
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 2rem;
            font-size: 0.9rem;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .security-features {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 2rem;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .security-features h4 {
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .security-features ul {
            list-style: none;
            padding-left: 0;
        }
        
        .security-features li {
            margin-bottom: 0.25rem;
        }
        
        .security-features li:before {
            content: '🔒';
            margin-right: 0.5rem;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 2rem;
                margin: 1rem;
            }
            
            .login-title {
                font-size: 1.5rem;
            }
            
            .login-links a {
                display: block;
                margin: 0.5rem 0;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <a href="/modules/yfauth/www/" class="back-link">
            ← Back to Home
        </a>
        
        <div class="login-header">
            <div class="login-logo">🔐</div>
            <h1 class="login-title">Welcome Back</h1>
            <p class="login-subtitle">Sign in to your YFEvents account</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="credential">Username or Email</label>
                <input 
                    type="text" 
                    id="credential" 
                    name="credential" 
                    value="<?= htmlspecialchars($_POST['credential'] ?? '') ?>"
                    required 
                    autocomplete="username"
                    placeholder="Enter your username or email">
                <div class="form-help">You can use either your username or email address</div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    autocomplete="current-password"
                    placeholder="Enter your password">
            </div>
            
            <div class="checkbox-group">
                <input type="checkbox" id="remember_me" name="remember_me">
                <label for="remember_me">Keep me signed in for 30 days</label>
            </div>
            
            <button type="submit" class="btn btn-primary">Sign In</button>
        </form>
        
        <div class="login-links">
            <a href="/modules/yfauth/www/forgot-password.php">Forgot Password?</a>
            <a href="/modules/yfauth/www/register.php">Create Account</a>
        </div>
        
        <div class="divider">
            <span>or</span>
        </div>
        
        <div style="text-align: center;">
            <a href="/modules/yfauth/www/admin/" class="btn" style="background: #6c757d; color: white; display: inline-block; text-decoration: none; width: auto; padding: 0.75rem 1.5rem;">
                Admin Panel
            </a>
        </div>
        
        <div class="security-features">
            <h4>Security Features</h4>
            <ul>
                <li>Rate limiting protection</li>
                <li>Secure password hashing</li>
                <li>Session management</li>
                <li>Remember me tokens</li>
            </ul>
        </div>
    </div>
    
    <script>
        // Focus first input
        document.getElementById('credential').focus();
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const credential = document.getElementById('credential').value.trim();
            const password = document.getElementById('password').value;
            
            if (!credential || !password) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('.btn-primary');
            submitBtn.textContent = 'Signing In...';
            submitBtn.disabled = true;
        });
        
        // Show password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const length = password.length;
            
            // Simple password strength indication
            if (length >= 8) {
                this.style.borderColor = '#28a745';
            } else if (length >= 4) {
                this.style.borderColor = '#ffc107';
            } else {
                this.style.borderColor = '#dc3545';
            }
        });
    </script>
</body>
</html>