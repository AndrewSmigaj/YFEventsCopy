<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Application\Services\AuthService;
use PDO;

/**
 * Authentication controller
 */
class AuthController extends BaseController
{
    private AuthService $authService;
    
    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        
        // Get database connection
        $dbConfig = $config->get('database');
        $charset = $dbConfig['charset'] ?? 'utf8mb4';
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$charset}";
        $options = $dbConfig['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
        
        $this->authService = new AuthService($pdo);
    }

    /**
     * Show admin login form
     */
    public function showAdminLogin(): void
    {
        // Generate CSRF token for the form
        $csrfToken = $this->authService->generateCSRFToken();
        
        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderLoginPage($csrfToken);
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

        // Authenticate using AuthService
        $result = $this->authService->login($input['username'], $input['password']);

        if ($result['success']) {
            // Check if user has admin role
            if (!$this->authService->hasRole('admin') && !$this->authService->hasRole('super_admin')) {
                $this->authService->logout();
                $this->errorResponse('Access denied. Admin role required.', 403);
                return;
            }

            // Get intended URL or default to admin dashboard
            $redirectUrl = $this->authService->getIntendedUrl('/admin/dashboard');

            $this->successResponse([
                'redirect' => $redirectUrl,
                'username' => $result['user']['username'],
                'login_time' => date('Y-m-d H:i:s')
            ], 'Login successful');
        } else {
            $this->errorResponse($result['error'] ?? 'Invalid credentials', 401);
        }
    }

    /**
     * Admin logout
     */
    public function adminLogout(): void
    {
        $this->authService->logout();
        
        $this->successResponse([
            'redirect' => '/admin/login'
        ], 'Logged out successfully');
    }

    /**
     * Check admin session status
     */
    public function adminStatus(): void
    {
        if ($this->authService->isAuthenticated() && $this->authService->hasAnyRole(['admin', 'super_admin'])) {
            $user = $this->authService->getCurrentUser();
            $this->successResponse([
                'authenticated' => true,
                'username' => $user['username'],
                'roles' => $user['roles'],
                'login_time' => isset($_SESSION['auth']['login_time']) ? date('Y-m-d H:i:s', $_SESSION['auth']['login_time']) : null
            ]);
        } else {
            $this->errorResponse('Not authenticated', 401, [
                'authenticated' => false
            ]);
        }
    }

    private function renderLoginPage(string $csrfToken): string
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
        
        <a href="/" class="back-link">← Back to Home</a>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            const alert = document.getElementById('alert');
            
            try {
                const response = await fetch('/admin/login', {
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
}