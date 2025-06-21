<?php
/**
 * Reusable admin authentication check
 * Include this at the top of admin pages instead of duplicating auth code
 */

function requireAdminAuth() {
    session_start();
    
    // Check if admin is logged in
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        // Show helpful login page instead of potentially broken redirect
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Denied - YFClaim Admin</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
                .error-container { background: white; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .error-title { color: #dc3545; font-size: 24px; margin-bottom: 20px; }
                .error-message { color: #666; line-height: 1.6; margin-bottom: 20px; }
                .login-button { background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px; margin-bottom: 10px; }
                .login-button:hover { background: #0056b3; }
                .help-text { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 14px; }
                .current-page { background: #e9ecef; padding: 10px; border-radius: 5px; margin-bottom: 20px; font-family: monospace; }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-title">🔒 Admin Access Required</div>
                <div class="error-message">
                    <p>You need to be logged in as an administrator to access the YFClaim admin panel.</p>
                    <p>Please log in to the main admin system first, then return to this page.</p>
                </div>
                
                <div class="current-page">
                    <strong>Current page:</strong> <?= $_SERVER['REQUEST_URI'] ?? 'Unknown' ?>
                </div>
                
                <a href="../../../www/html/admin/login.php" class="login-button">Main Admin Login</a>
                <a href="/admin/login.php" class="login-button">Alternative Path 1</a>
                <a href="/www/html/admin/login.php" class="login-button">Alternative Path 2</a>
                
                <div class="help-text">
                    <strong>Login Instructions:</strong><br>
                    Please use your administrator credentials to log in.<br>
                    Contact your system administrator if you need access.
                </div>
                
                <div class="help-text">
                    <strong>After logging in:</strong><br>
                    1. Login to the main admin system with your credentials<br>
                    2. Once logged in, you can access all YFClaim admin pages<br>
                    3. Return to this page or navigate using the admin menu
                </div>
                
                <div class="help-text">
                    <strong>Session Debug Info:</strong><br>
                    <?php if (empty($_SESSION)): ?>
                        No active session found.
                    <?php else: ?>
                        Session exists but admin_logged_in = <?= var_export($_SESSION['admin_logged_in'] ?? 'not set', true) ?>
                    <?php endif; ?>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Auto-require auth when this file is included
requireAdminAuth();
?>