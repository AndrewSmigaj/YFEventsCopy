<?php
// YFAuth - Registration Page
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
        header('Location: /modules/yfauth/www/dashboard.php');
        exit;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'username' => trim($_POST['username'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? '')
        ];
        
        // Validate password confirmation
        $passwordConfirm = $_POST['password_confirm'] ?? '';
        if ($data['password'] !== $passwordConfirm) {
            throw new Exception('Passwords do not match.');
        }
        
        // Register user
        $user = $authService->register($data);
        
        $message = 'Account created successfully! Please check your email to verify your account.';
        
        // Clear form data
        $_POST = [];
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - YFAuth</title>
    
    <meta name="description" content="Create your YFEvents account">
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
        
        .register-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 3rem;
            width: 100%;
            max-width: 500px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-logo {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #667eea;
        }
        
        .register-title {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .register-subtitle {
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
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
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
        
        .form-group label .required {
            color: #e74c3c;
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
        
        .password-strength {
            margin-top: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.875rem;
            display: none;
        }
        
        .password-strength.weak {
            background: #f8d7da;
            color: #721c24;
            display: block;
        }
        
        .password-strength.medium {
            background: #fff3cd;
            color: #856404;
            display: block;
        }
        
        .password-strength.strong {
            background: #d4edda;
            color: #155724;
            display: block;
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
        
        .btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .register-links {
            text-align: center;
            margin-top: 2rem;
        }
        
        .register-links a {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .register-links a:hover {
            text-decoration: underline;
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
        
        .terms-notice {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1.5rem;
            font-size: 0.875rem;
            color: #6c757d;
            text-align: center;
        }
        
        @media (max-width: 600px) {
            .register-container {
                padding: 2rem;
                margin: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .register-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <a href="/modules/yfauth/www/login.php" class="back-link">
            ← Back to Login
        </a>
        
        <div class="register-header">
            <div class="register-logo">👤</div>
            <h1 class="register-title">Create Account</h1>
            <p class="register-subtitle">Join the YFEvents community</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php else: ?>
            <form method="POST" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                            placeholder="John">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input 
                            type="text" 
                            id="last_name" 
                            name="last_name" 
                            value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                            placeholder="Doe">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="username">Username <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        required 
                        pattern="[a-zA-Z0-9_]{3,20}"
                        placeholder="johndoe123">
                    <div class="form-help">3-20 characters, letters, numbers, and underscores only</div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required 
                        placeholder="john.doe@example.com">
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                        placeholder="(555) 123-4567">
                    <div class="form-help">Optional, for account recovery</div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        minlength="8"
                        placeholder="Create a strong password">
                    <div id="passwordStrength" class="password-strength"></div>
                    <div class="form-help">Minimum 8 characters</div>
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirm Password <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="password_confirm" 
                        name="password_confirm" 
                        required 
                        placeholder="Confirm your password">
                    <div id="passwordMatch" class="form-help"></div>
                </div>
                
                <button type="submit" class="btn btn-primary" id="submitBtn">Create Account</button>
            </form>
        <?php endif; ?>
        
        <div class="register-links">
            <a href="/modules/yfauth/www/login.php">Already have an account? Sign in</a>
        </div>
        
        <div class="terms-notice">
            By creating an account, you agree to our Terms of Service and Privacy Policy.
        </div>
    </div>
    
    <script>
        // Focus first input
        document.getElementById('first_name').focus();
        
        // Password strength checker
        function checkPasswordStrength(password) {
            const strengthEl = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthEl.style.display = 'none';
                return;
            }
            
            let score = 0;
            
            // Length
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            
            // Character types
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            strengthEl.className = 'password-strength';
            
            if (score < 3) {
                strengthEl.className += ' weak';
                strengthEl.textContent = 'Weak password';
            } else if (score < 5) {
                strengthEl.className += ' medium';
                strengthEl.textContent = 'Medium strength password';
            } else {
                strengthEl.className += ' strong';
                strengthEl.textContent = 'Strong password';
            }
        }
        
        // Password confirmation checker
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;
            const matchEl = document.getElementById('passwordMatch');
            
            if (confirm.length === 0) {
                matchEl.textContent = '';
                matchEl.style.color = '';
                return;
            }
            
            if (password === confirm) {
                matchEl.textContent = 'Passwords match';
                matchEl.style.color = '#28a745';
            } else {
                matchEl.textContent = 'Passwords do not match';
                matchEl.style.color = '#dc3545';
            }
        }
        
        // Event listeners
        document.getElementById('password').addEventListener('input', function() {
            checkPasswordStrength(this.value);
            checkPasswordMatch();
        });
        
        document.getElementById('password_confirm').addEventListener('input', checkPasswordMatch);
        
        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const isValid = /^[a-zA-Z0-9_]{3,20}$/.test(username);
            
            if (username.length > 0 && !isValid) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });
        
        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('password_confirm').value;
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            
            // Check required fields
            if (!username || !email || !password || !confirm) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            // Check password match
            if (password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match.');
                document.getElementById('password_confirm').focus();
                return false;
            }
            
            // Check username format
            if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
                e.preventDefault();
                alert('Username must be 3-20 characters and contain only letters, numbers, and underscores.');
                document.getElementById('username').focus();
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.textContent = 'Creating Account...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>