<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;

/**
 * Authentication controller
 */
class AuthController extends BaseController
{
    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
    }

    /**
     * Show admin login form
     */
    public function showAdminLogin(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderLoginPage($basePath);
    }

    /**
     * Process admin login
     */
    public function processAdminLogin(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->errorResponse('POST method required', 405);
            return;
        }

        $input = $this->getInput();
        
        if (empty($input['username']) || empty($input['password'])) {
            $this->errorResponse('Username and password are required');
            return;
        }

        // Simple hardcoded admin credentials for the refactored demo
        // In production, this would check against a user database
        $validCredentials = [
            'admin' => 'admin123',
            'yakima' => 'yakima2025',
            'yfevents' => 'yfevents_admin'
        ];

        $username = $input['username'];
        $password = $input['password'];

        if (isset($validCredentials[$username]) && $validCredentials[$username] === $password) {
            // Start session and set admin flag
            session_start();
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_login_time'] = time();

            $this->successResponse([
                'redirect' => dirname($_SERVER['SCRIPT_NAME']) . '/admin/dashboard',
                'username' => $username,
                'login_time' => date('Y-m-d H:i:s')
            ], 'Login successful');
        } else {
            $this->errorResponse('Invalid credentials', 401);
        }
    }

    /**
     * Admin logout
     */
    public function adminLogout(): void
    {
        session_start();
        session_destroy();
        
        $this->successResponse([
            'redirect' => dirname($_SERVER['SCRIPT_NAME']) . '/admin/login'
        ], 'Logged out successfully');
    }

    /**
     * Check admin session status
     */
    public function adminStatus(): void
    {
        session_start();
        
        if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
            $this->successResponse([
                'authenticated' => true,
                'username' => $_SESSION['admin_username'] ?? 'admin',
                'login_time' => isset($_SESSION['admin_login_time']) ? date('Y-m-d H:i:s', $_SESSION['admin_login_time']) : null
            ]);
        } else {
            $this->errorResponse('Not authenticated', 401, [
                'authenticated' => false
            ]);
        }
    }

    /**
     * Show registration form
     */
    public function showRegistration(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderRegistrationPage($basePath);
    }

    /**
     * Process user registration
     */
    public function processRegistration(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->errorResponse('POST method required', 405);
            return;
        }

        $input = $this->getInput();
        
        // Validate required fields
        if (empty($input['username']) || empty($input['email']) || empty($input['password'])) {
            $this->errorResponse('All fields are required');
            return;
        }

        // Validate email
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errorResponse('Invalid email address');
            return;
        }

        // Validate password length
        if (strlen($input['password']) < 6) {
            $this->errorResponse('Password must be at least 6 characters');
            return;
        }

        // In a real implementation, this would:
        // 1. Check if username/email already exists
        // 2. Hash the password
        // 3. Save to database
        // 4. Send confirmation email
        
        // For now, we'll just simulate success
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }
        
        $this->successResponse([
            'username' => $input['username'],
            'email' => $input['email'],
            'redirect' => $basePath . '/login.php'
        ], 'Registration successful! Please login.');
    }

    private function renderLoginPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - Admin Login</title>
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
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 400px;
            text-align: center;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            color: #343a40;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
        }
        
        .credentials {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
            text-align: left;
        }
        
        .credentials h4 {
            color: #343a40;
            margin-bottom: 10px;
        }
        
        .credentials p {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f1aeb5;
        }
        
        .alert-success {
            background: #d1edff;
            color: #0c5460;
            border: 1px solid #b8daff;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">YFEvents V2</div>
        <div class="subtitle">Admin Login</div>
        
        <div id="alert" class="alert"></div>
        
        <form id="loginForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="login-btn">Login to Admin Panel</button>
        </form>
        
        <div class="credentials">
            <h4>🔑 Demo Credentials</h4>
            <p><strong>admin</strong> / admin123</p>
            <p><strong>yakima</strong> / yakima2025</p>
            <p><strong>yfevents</strong> / yfevents_admin</p>
        </div>
        
        <a href="{$basePath}/" class="back-link">← Back to Home</a>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const alert = document.getElementById('alert');
            
            try {
                const response = await fetch('{$basePath}/admin/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ username, password })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert.className = 'alert alert-success';
                    alert.textContent = 'Login successful! Redirecting...';
                    alert.style.display = 'block';
                    
                    setTimeout(() => {
                        window.location.href = data.data.redirect;
                    }, 1000);
                } else {
                    alert.className = 'alert alert-error';
                    alert.textContent = data.message || 'Login failed';
                    alert.style.display = 'block';
                }
            } catch (error) {
                alert.className = 'alert alert-error';
                alert.textContent = 'Connection error. Please try again.';
                alert.style.display = 'block';
            }
        });
    </script>
</body>
</html>
HTML;
    }

    private function renderRegistrationPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents - User Registration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #43cea2 0%, #185a9d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 450px;
            text-align: center;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #43cea2, #185a9d);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            color: #343a40;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #43cea2;
        }
        
        .register-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #43cea2, #185a9d);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s ease;
            margin-top: 10px;
        }
        
        .register-btn:hover {
            transform: translateY(-2px);
        }
        
        .links {
            margin-top: 25px;
        }
        
        .links a {
            color: #185a9d;
            text-decoration: none;
            font-weight: 500;
            margin: 0 10px;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f1aeb5;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .info-text {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 20px;
            line-height: 1.5;
        }
        
        .features {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
            text-align: left;
        }
        
        .features h4 {
            color: #343a40;
            margin-bottom: 10px;
        }
        
        .features ul {
            list-style: none;
            padding: 0;
        }
        
        .features li {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 5px;
            padding-left: 20px;
            position: relative;
        }
        
        .features li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #43cea2;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">YFEvents</div>
        <div class="subtitle">Create Your Account</div>
        
        <div id="alert" class="alert"></div>
        
        <form id="registerForm">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required 
                       placeholder="Choose a username" autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required 
                       placeholder="your@email.com" autocomplete="email">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Minimum 6 characters" autocomplete="new-password">
            </div>
            
            <button type="submit" class="register-btn">Create Account</button>
        </form>
        
        <div class="features">
            <h4>🎉 Join YFEvents to:</h4>
            <ul>
                <li>Submit and manage your events</li>
                <li>Claim your business listing</li>
                <li>Access estate sale features</li>
                <li>Get event notifications</li>
            </ul>
        </div>
        
        <div class="links">
            <a href="{$basePath}/login.php">Already have an account? Login</a>
            <br><br>
            <a href="{$basePath}/">← Back to Home</a>
        </div>
    </div>

    <script>
        document.getElementById('registerForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const alert = document.getElementById('alert');
            
            try {
                const response = await fetch('{$basePath}/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ username, email, password })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert.className = 'alert alert-success';
                    alert.textContent = data.message || 'Registration successful!';
                    alert.style.display = 'block';
                    
                    // Clear form
                    document.getElementById('registerForm').reset();
                    
                    // Redirect after 2 seconds
                    setTimeout(() => {
                        window.location.href = data.data.redirect || '{$basePath}/login.php';
                    }, 2000);
                } else {
                    alert.className = 'alert alert-error';
                    alert.textContent = data.message || 'Registration failed';
                    alert.style.display = 'block';
                }
            } catch (error) {
                alert.className = 'alert alert-error';
                alert.textContent = 'Connection error. Please try again.';
                alert.style.display = 'block';
            }
        });
    </script>
</body>
</html>
HTML;
    }
}