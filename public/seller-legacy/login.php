<?php
// YFClaim Seller Login Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if already logged in
if (isset($_SESSION['seller_id'])) {
    header('Location: /seller/dashboard');
    exit;
}

$basePath = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Login - YFClaim Estate Sales</title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
        }
        
        .auth-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 2rem;
            width: 100%;
            max-width: 400px;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-title {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .auth-subtitle {
            color: #6c757d;
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-primary {
            width: 100%;
            background: #667eea;
            color: white;
            border: none;
            padding: 0.875rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .btn-primary:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .auth-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }
        
        .auth-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .demo-credentials {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .demo-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .demo-item {
            color: #6c757d;
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title">🏛️ Seller Login</h1>
                <p class="auth-subtitle">Access your estate sale dashboard</p>
            </div>
            
            <div class="demo-credentials">
                <div class="demo-title">Demo Credentials:</div>
                <div class="demo-item">Email: seller@example.com</div>
                <div class="demo-item">Password: password123</div>
            </div>
            
            <div id="alerts"></div>
            
            <form id="loginForm">
                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input" required
                           placeholder="Enter your email address" value="seller@example.com">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" class="form-input" required
                           placeholder="Enter your password" value="password123">
                </div>
                
                <button type="submit" class="btn-primary" id="loginBtn">
                    Login to Dashboard
                </button>
            </form>
            
            <div class="auth-links">
                <p>Don't have an account? <a href="<?= $basePath ?>/seller/register">Register here</a></p>
                <p><a href="<?= $basePath ?>/claims">← Back to Estate Sales</a></p>
            </div>
        </div>
    </div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.disabled = true;
            loginBtn.textContent = 'Logging in...';
            
            try {
                const response = await fetch(`${basePath}/seller/login`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Login successful! Redirecting to dashboard...', 'success');
                    
                    // Redirect to dashboard
                    setTimeout(() => {
                        window.location.href = `${basePath}/seller/dashboard`;
                    }, 1000);
                } else {
                    showAlert(result.message || 'Invalid credentials. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Login error:', error);
                showAlert('An error occurred during login. Please try again.', 'error');
            } finally {
                loginBtn.disabled = false;
                loginBtn.textContent = 'Login to Dashboard';
            }
        });
        
        function showAlert(message, type) {
            const alertsContainer = document.getElementById('alerts');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            
            alertsContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    ${message}
                </div>
            `;
            
            // Auto-hide success messages after 3 seconds
            if (type === 'success') {
                setTimeout(() => {
                    alertsContainer.innerHTML = '';
                }, 3000);
            }
        }
    </script>
</body>
</html>