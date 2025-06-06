<?php
// YFEvents Main Landing Page - Portal to All Services

// Check if database is configured
$envFile = __DIR__ . '/../../.env';
$configFile = __DIR__ . '/../../config/database.php';

$isConfigured = file_exists($envFile) && file_exists($configFile);
$dbConnected = false;

if ($isConfigured) {
    try {
        require_once $configFile;
        $dbConnected = isset($db) && $db instanceof PDO;
    } catch (Exception $e) {
        $dbConnected = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yakima Finds - Local Events & Business Directory</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }
        
        .header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .status-bar {
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }
        
        .status {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            margin-right: 10px;
            font-size: 0.9rem;
            font-weight: bold;
        }
        
        .status.online {
            background: #28a745;
            color: white;
        }
        
        .status.warning {
            background: #ffc107;
            color: #000;
        }
        
        .status.offline {
            background: #dc3545;
            color: white;
        }
        
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .module-card {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.2);
        }
        
        .module-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .module-icon {
            font-size: 2.5rem;
            margin-right: 15px;
            width: 60px;
            text-align: center;
        }
        
        .module-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .module-description {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .module-features {
            list-style: none;
            margin-bottom: 25px;
        }
        
        .module-features li {
            padding: 5px 0;
            color: #555;
        }
        
        .module-features li:before {
            content: '✓';
            color: #28a745;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .module-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #545b62;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #1e7e34;
        }
        
        .admin-section {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }
        
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .setup-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .setup-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .footer {
            text-align: center;
            color: rgba(255,255,255,0.8);
            padding: 20px 0;
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .modules-grid {
                grid-template-columns: 1fr;
            }
            
            .module-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-map-marked-alt"></i> Yakima Finds</h1>
            <p>Your Gateway to Local Events, Businesses & Estate Sales</p>
        </div>
        
        <div class="status-bar">
            <span class="status <?= $isConfigured ? 'online' : 'warning' ?>">
                <i class="fas fa-cog"></i> Config: <?= $isConfigured ? 'Ready' : 'Setup Required' ?>
            </span>
            <span class="status <?= $dbConnected ? 'online' : 'offline' ?>">
                <i class="fas fa-database"></i> Database: <?= $dbConnected ? 'Connected' : 'Disconnected' ?>
            </span>
            <span class="status online">
                <i class="fas fa-server"></i> Web Server: Online (PHP <?= phpversion() ?>)
            </span>
        </div>
        
        <?php if (!$isConfigured): ?>
        <div class="setup-warning">
            <h3><i class="fas fa-exclamation-triangle"></i> Setup Required</h3>
            <p>Please configure your environment before using the applications:</p>
            <ol>
                <li>Copy <code>.env.example</code> to <code>.env</code></li>
                <li>Update database credentials in <code>.env</code></li>
                <li>Run database migrations</li>
            </ol>
        </div>
        <?php else: ?>
        <div class="setup-success">
            <h3><i class="fas fa-check-circle"></i> System Ready</h3>
            <p>All systems are configured and ready to use. Choose an application below to get started.</p>
        </div>
        <?php endif; ?>
        
        <div class="modules-grid">
            <!-- YFEvents Calendar -->
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon" style="color: #007bff;">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="module-title">Event Calendar</div>
                </div>
                <div class="module-description">
                    Discover local events with interactive maps, multi-source event aggregation, and community submissions.
                </div>
                <ul class="module-features">
                    <li>Interactive Google Maps integration</li>
                    <li>Multi-source event scraping</li>
                    <li>Community event submissions</li>
                    <li>Mobile-optimized interface</li>
                </ul>
                <div class="module-actions">
                    <a href="/calendar.php" class="btn btn-primary">
                        <i class="fas fa-calendar"></i> View Calendar
                    </a>
                    <a href="/events/submit/" class="btn btn-secondary">
                        <i class="fas fa-plus"></i> Submit Event
                    </a>
                </div>
            </div>
            
            <!-- Local Business Directory -->
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon" style="color: #28a745;">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="module-title">Local Shops</div>
                </div>
                <div class="module-description">
                    Comprehensive business directory with detailed profiles, operating hours, and location services.
                </div>
                <ul class="module-features">
                    <li>Detailed business profiles</li>
                    <li>Operating hours & contact info</li>
                    <li>GPS navigation integration</li>
                    <li>Business owner verification</li>
                </ul>
                <div class="module-actions">
                    <a href="/calendar.php#shops" class="btn btn-success">
                        <i class="fas fa-search"></i> Browse Shops
                    </a>
                    <a href="/claim-shop.php" class="btn btn-primary">
                        <i class="fas fa-handshake"></i> Claim Your Business
                    </a>
                </div>
            </div>
            
            <!-- YFClaim Estate Sales -->
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon" style="color: #dc3545;">
                        <i class="fas fa-gavel"></i>
                    </div>
                    <div class="module-title">Estate Sales (YFClaim)</div>
                </div>
                <div class="module-description">
                    Facebook-style claim platform for estate sales with QR code access and real-time bidding.
                </div>
                <ul class="module-features">
                    <li>QR code sale access</li>
                    <li>Real-time item claiming</li>
                    <li>Seller management portal</li>
                    <li>Buyer offer tracking</li>
                </ul>
                <div class="module-actions">
                    <a href="/modules/yfclaim/www/" class="btn btn-primary">
                        <i class="fas fa-shopping-cart"></i> Browse Sales
                    </a>
                    <a href="/modules/yfclaim/www/dashboard/" class="btn btn-secondary">
                        <i class="fas fa-user-tie"></i> Seller Portal
                    </a>
                </div>
            </div>
            
            <!-- YFAuth Authentication -->
            <div class="module-card">
                <div class="module-header">
                    <div class="module-icon" style="color: #6f42c1;">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="module-title">User Accounts (YFAuth)</div>
                </div>
                <div class="module-description">
                    Secure user authentication and account management with role-based access control.
                </div>
                <ul class="module-features">
                    <li>Secure user registration</li>
                    <li>Role-based permissions</li>
                    <li>Session management</li>
                    <li>Profile management</li>
                </ul>
                <div class="module-actions">
                    <a href="/modules/yfauth/www/admin/login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="/ajax/auth/register.php" class="btn btn-secondary">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Admin Section -->
        <div class="admin-section">
            <h2><i class="fas fa-cogs"></i> Administration & Management</h2>
            <p>Administrative interfaces for system management and configuration.</p>
            
            <div class="admin-grid">
                <a href="/admin/" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt"></i> Main Admin
                </a>
                <a href="/admin/calendar/" class="btn btn-secondary">
                    <i class="fas fa-calendar-check"></i> Advanced Admin
                </a>
                <a href="/modules/yfclaim/www/admin/" class="btn btn-secondary">
                    <i class="fas fa-gavel"></i> YFClaim Admin
                </a>
                <a href="/admin/shops.php" class="btn btn-secondary">
                    <i class="fas fa-store"></i> Shop Management
                </a>
                <a href="/admin/scrapers.php" class="btn btn-secondary">
                    <i class="fas fa-robot"></i> Event Scrapers
                </a>
                <a href="/admin/intelligent-scraper.php" class="btn btn-secondary">
                    <i class="fas fa-brain"></i> AI Scraper
                </a>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="admin-section">
            <h2><i class="fas fa-zap"></i> Quick Actions</h2>
            <p>Common tasks and useful tools for users and administrators.</p>
            
            <div class="admin-grid">
                <a href="/api/events/" class="btn btn-success">
                    <i class="fas fa-code"></i> Events API
                </a>
                <a href="/api/shops/" class="btn btn-success">
                    <i class="fas fa-code"></i> Shops API
                </a>
                <a href="/admin/validate-urls.php" class="btn btn-secondary">
                    <i class="fas fa-link"></i> URL Validator
                </a>
                <a href="/admin/geocode-fix.php" class="btn btn-secondary">
                    <i class="fas fa-map-marker-alt"></i> Geocoding Fix
                </a>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?= date('Y') ?> Yakima Finds. Connecting the community through local events and businesses.</p>
            <p>
                <a href="https://github.com/r0bug/YFEvents" style="color: rgba(255,255,255,0.8);">
                    <i class="fab fa-github"></i> View on GitHub
                </a>
            </p>
        </div>
    </div>
</body>
</html>