<?php
// YFClaim - Buyer Logout
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use YFEvents\Modules\YFClaim\Models\BuyerModel;

// Start session
session_start();

// Initialize models
$buyerModel = new BuyerModel($pdo);

// Get return URL
$returnUrl = $_GET['return'] ?? '/modules/yfclaim/www/';

// Handle logout
if (isset($_SESSION['buyer_token'])) {
    // Invalidate session in database
    $buyerModel->invalidateSession($_SESSION['buyer_token']);
    
    // Clear session data
    unset($_SESSION['buyer_token']);
    unset($_SESSION['buyer_auth']);
    
    // Clear cookie
    setcookie('yfclaim_buyer_token', '', time() - 3600, '/');
}

// Clear all buyer session data
unset($_SESSION['pending_buyer_id']);
unset($_SESSION['auth_method']);
unset($_SESSION['auth_contact']);

// Destroy session if it's empty
if (empty($_SESSION)) {
    session_destroy();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out - YFClaim Estate Sales</title>
    
    <meta name="description" content="You have been successfully logged out of YFClaim.">
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
        
        .logout-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
            text-align: center;
        }
        
        .logout-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #27ae60;
        }
        
        .logout-title {
            font-size: 1.8rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .logout-message {
            color: #7f8c8d;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .actions {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .security-note {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 2rem;
            font-size: 0.9rem;
            color: #0c5460;
        }
        
        .auto-redirect {
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .countdown {
            font-weight: 600;
            color: #3498db;
        }
        
        @media (max-width: 480px) {
            .logout-container {
                padding: 2rem;
            }
            
            .logout-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">👋</div>
        
        <h1 class="logout-title">Successfully Logged Out</h1>
        
        <p class="logout-message">
            You have been safely logged out of your YFClaim account. 
            Your session has been securely terminated and all authentication tokens have been cleared.
        </p>
        
        <div class="actions">
            <a href="<?= htmlspecialchars($returnUrl) ?>" class="btn btn-primary">
                Continue Browsing Sales
            </a>
            <a href="/modules/yfclaim/www/buyer-login.php" class="btn btn-secondary">
                Sign In Again
            </a>
        </div>
        
        <div class="security-note">
            <strong>🔒 Security Tip:</strong> 
            For your protection, we recommend closing your browser if you're using a shared computer.
        </div>
        
        <div class="auto-redirect">
            Automatically redirecting in <span class="countdown" id="countdown">5</span> seconds...
        </div>
    </div>
    
    <script>
        // Auto-redirect countdown
        let timeLeft = 5;
        const countdownElement = document.getElementById('countdown');
        
        const countdown = setInterval(() => {
            timeLeft--;
            countdownElement.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(countdown);
                window.location.href = '<?= htmlspecialchars($returnUrl) ?>';
            }
        }, 1000);
        
        // Cancel auto-redirect if user interacts with page
        document.addEventListener('click', () => {
            clearInterval(countdown);
            document.querySelector('.auto-redirect').style.display = 'none';
        });
        
        document.addEventListener('keydown', () => {
            clearInterval(countdown);
            document.querySelector('.auto-redirect').style.display = 'none';
        });
    </script>
</body>
</html>