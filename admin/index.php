<?php
/**
 * YFEvents Admin Panel - Main Dashboard
 * Central admin interface with links to all admin functions
 */

// Check for basic authentication (simple session-based)
session_start();

// Basic admin authentication - in production, this should be enhanced
if (!isset($_SESSION['admin_logged_in']) && !isset($_GET['login'])) {
    if (isset($_POST['admin_password'])) {
        // Simple password check - in production, use proper authentication
        $admin_password = 'admin123'; // Change this!
        if ($_POST['admin_password'] === $admin_password) {
            $_SESSION['admin_logged_in'] = true;
        } else {
            $login_error = 'Invalid password';
        }
    }
    
    if (!isset($_SESSION['admin_logged_in'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>YFEvents Admin Login</title>
            <style>
                body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; margin: 0; padding: 40px; }
                .login-form { max-width: 400px; margin: 100px auto; background: white; padding: 40px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .form-group { margin: 20px 0; }
                label { display: block; margin-bottom: 5px; font-weight: bold; }
                input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; }
                button { background: #3498db; color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
                button:hover { background: #2980b9; }
                .error { color: #e74c3c; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class="login-form">
                <h2>YFEvents Admin Login</h2>
                <?php if (isset($login_error)): ?>
                    <div class="error"><?= htmlspecialchars($login_error) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label for="admin_password">Admin Password:</label>
                        <input type="password" id="admin_password" name="admin_password" required>
                    </div>
                    <button type="submit">Login</button>
                </form>
                <p style="margin-top: 20px; font-size: 14px; color: #666;">
                    <strong>Security Note:</strong> This is a basic login. For production use, implement proper authentication via the YFAuth module.
                </p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Check if we need to logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/');
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Get system statistics
try {
    $stats = [];
    
    // Events count
    $stmt = $pdo->query("SELECT COUNT(*) FROM events");
    $stats['events_count'] = $stmt->fetchColumn();
    
    // Shops count
    $stmt = $pdo->query("SELECT COUNT(*) FROM local_shops");
    $stats['shops_count'] = $stmt->fetchColumn();
    
    // Sources count
    $stmt = $pdo->query("SELECT COUNT(*) FROM calendar_sources");
    $stats['sources_count'] = $stmt->fetchColumn();
    
    // Recent events (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE start_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_events'] = $stmt->fetchColumn();
    
    // Module status
    $modules_status = [];
    
    // Check YFTheme
    $stmt = $pdo->query("SHOW TABLES LIKE 'theme_variables'");
    $modules_status['yftheme'] = $stmt->rowCount() > 0;
    
    // Check YFAuth
    $stmt = $pdo->query("SHOW TABLES LIKE 'yfa_auth_users'");
    $modules_status['yfauth'] = $stmt->rowCount() > 0;
    
    // Check YFClaim
    $stmt = $pdo->query("SHOW TABLES LIKE 'yfc_sellers'");
    $modules_status['yfclaim'] = $stmt->rowCount() > 0;
    
} catch (Exception $e) {
    $db_error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f8f9fa; 
            line-height: 1.6; 
        }
        .header {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 { font-size: 28px; }
        .user-info { font-size: 14px; opacity: 0.9; }
        .user-info a { color: #ecf0f1; text-decoration: none; }
        .user-info a:hover { text-decoration: underline; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .admin-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        .admin-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .section-header {
            background: #34495e;
            color: white;
            padding: 20px;
            font-size: 18px;
            font-weight: bold;
        }
        .section-content {
            padding: 20px;
        }
        .admin-link {
            display: block;
            padding: 12px 0;
            color: #2c3e50;
            text-decoration: none;
            border-bottom: 1px solid #ecf0f1;
            transition: all 0.3s ease;
        }
        .admin-link:hover {
            background: #f8f9fa;
            padding-left: 10px;
            color: #3498db;
        }
        .admin-link:last-child {
            border-bottom: none;
        }
        .admin-link .icon {
            display: inline-block;
            width: 20px;
            margin-right: 10px;
        }
        
        .module-status {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 10px;
        }
        .status-ok { background: #27ae60; }
        .status-error { background: #e74c3c; }
        
        .alert {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-error {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 10px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .admin-sections { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="header-content">
                <h1>🎯 YFEvents Admin Dashboard</h1>
                <div class="user-info">
                    Admin Session Active | <a href="?logout=1">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($db_error)): ?>
            <div class="alert alert-error">
                <strong>Database Error:</strong> <?= htmlspecialchars($db_error) ?>
            </div>
        <?php endif; ?>

        <!-- System Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['events_count'] ?? 0) ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['shops_count'] ?? 0) ?></div>
                <div class="stat-label">Local Shops</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['sources_count'] ?? 0) ?></div>
                <div class="stat-label">Event Sources</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= number_format($stats['recent_events'] ?? 0) ?></div>
                <div class="stat-label">Recent Events (7 days)</div>
            </div>
        </div>

        <!-- Admin Sections -->
        <div class="admin-sections">
            <!-- Event Management -->
            <div class="admin-section">
                <div class="section-header">📅 Event Management</div>
                <div class="section-content">
                    <a href="/admin/calendar/" class="admin-link">
                        <span class="icon">🔧</span>Advanced Calendar Admin
                    </a>
                    <a href="/calendar.php" class="admin-link">
                        <span class="icon">📅</span>View Public Calendar
                    </a>
                    <a href="/admin/scrapers.php" class="admin-link">
                        <span class="icon">🤖</span>Event Scrapers Config
                    </a>
                    <a href="/admin/intelligent-scraper.php" class="admin-link">
                        <span class="icon">🧠</span>AI-Powered Scraper
                    </a>
                    <a href="/admin/validate-urls.php" class="admin-link">
                        <span class="icon">🔗</span>URL Validator
                    </a>
                </div>
            </div>

            <!-- Business Directory -->
            <div class="admin-section">
                <div class="section-header">🏪 Business Directory</div>
                <div class="section-content">
                    <a href="/admin/shops.php" class="admin-link">
                        <span class="icon">🏪</span>Manage Local Shops
                    </a>
                    <a href="/calendar.php#shops" class="admin-link">
                        <span class="icon">🗺️</span>View Shop Directory
                    </a>
                    <a href="/admin/geocode-fix.php" class="admin-link">
                        <span class="icon">📍</span>Fix Geocoding Issues
                    </a>
                    <a href="/claim-shop.php" class="admin-link">
                        <span class="icon">✋</span>Business Claiming Portal
                    </a>
                </div>
            </div>

            <!-- Module Management -->
            <div class="admin-section">
                <div class="section-header">🧩 Module Management</div>
                <div class="section-content">
                    <div class="module-status">
                        <div class="status-indicator <?= ($modules_status['yftheme'] ?? false) ? 'status-ok' : 'status-error' ?>"></div>
                        <span>YFTheme Module</span>
                    </div>
                    <a href="/admin/theme-config.php" class="admin-link">
                        <span class="icon">🎨</span>Theme Configuration
                    </a>
                    
                    <div class="module-status">
                        <div class="status-indicator <?= ($modules_status['yfauth'] ?? false) ? 'status-ok' : 'status-error' ?>"></div>
                        <span>YFAuth Module</span>
                    </div>
                    <a href="/modules/yfauth/www/admin/" class="admin-link">
                        <span class="icon">🔐</span>Authentication Admin
                    </a>
                    
                    <div class="module-status">
                        <div class="status-indicator <?= ($modules_status['yfclaim'] ?? false) ? 'status-ok' : 'status-error' ?>"></div>
                        <span>YFClaim Module</span>
                    </div>
                    <a href="/modules/yfclaim/www/admin/" class="admin-link">
                        <span class="icon">🏷️</span>Estate Sales Admin
                    </a>
                </div>
            </div>

            <!-- System Tools -->
            <div class="admin-section">
                <div class="section-header">🔧 System Tools</div>
                <div class="section-content">
                    <a href="/admin/system-status.php" class="admin-link">
                        <span class="icon">📊</span>System Status Report
                    </a>
                    <a href="/system-debug.php" class="admin-link">
                        <span class="icon">🔍</span>System Diagnostic Tool
                    </a>
                    <a href="/css-diagnostic.php" class="admin-link">
                        <span class="icon">🎨</span>CSS Diagnostic Tool
                    </a>
                    <a href="/tests/run_all_tests.php" class="admin-link">
                        <span class="icon">🧪</span>Run System Tests
                    </a>
                </div>
            </div>
        </div>
        
        <div style="margin-top: 40px; text-align: center; color: #7f8c8d; font-size: 14px;">
            <p>YFEvents Admin Dashboard | Version 1.0 | Generated: <?= date('Y-m-d H:i:s') ?></p>
            <p><a href="/" style="color: #3498db;">← Back to Main Site</a></p>
        </div>
    </div>
</body>
</html>