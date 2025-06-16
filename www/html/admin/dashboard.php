<?php
// Unified Admin Landing Page
require_once dirname(__DIR__, 3) . '/config/database.php';

session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

// Get system statistics
$stats = [];

// Core system stats
$stats['events'] = [
    'total' => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'pending'")->fetchColumn(),
    'approved' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'approved'")->fetchColumn(),
    'upcoming' => $pdo->query("SELECT COUNT(*) FROM events WHERE start_datetime > NOW()")->fetchColumn()
];

$stats['shops'] = [
    'total' => $pdo->query("SELECT COUNT(*) FROM local_shops")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM local_shops WHERE is_active = 1")->fetchColumn()
];

$stats['sources'] = [
    'total' => $pdo->query("SELECT COUNT(*) FROM calendar_sources")->fetchColumn(),
    'active' => $pdo->query("SELECT COUNT(*) FROM calendar_sources WHERE is_active = 1")->fetchColumn()
];

// YFClaim stats
$stats['yfclaim'] = ['sales' => 0, 'items' => 0, 'offers' => 0, 'sellers' => 0, 'buyers' => 0];
try {
    $stats['yfclaim'] = [
        'sales' => $pdo->query("SELECT COUNT(*) FROM yfc_sales")->fetchColumn(),
        'items' => $pdo->query("SELECT COUNT(*) FROM yfc_items")->fetchColumn(),
        'offers' => $pdo->query("SELECT COUNT(*) FROM yfc_offers")->fetchColumn(),
        'sellers' => $pdo->query("SELECT COUNT(*) FROM yfc_sellers")->fetchColumn(),
        'buyers' => $pdo->query("SELECT COUNT(*) FROM yfc_buyers")->fetchColumn()
    ];
} catch (Exception $e) {
    // YFClaim not installed
}

// Get recent activity
$recentEvents = $pdo->query("SELECT title, start_datetime, status FROM events ORDER BY created_at DESC LIMIT 5")->fetchAll();
$recentActivity = $pdo->query("SELECT 'event' as type, title as description, created_at FROM events WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY created_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 1.8rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .main-content {
            display: grid;
            gap: 30px;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .admin-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }
        
        .admin-section {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-header {
            background: #34495e;
            color: white;
            padding: 20px;
            font-size: 1.2rem;
            font-weight: 600;
        }
        
        .section-content {
            padding: 20px;
        }
        
        .admin-links {
            display: grid;
            gap: 12px;
        }
        
        .admin-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-decoration: none;
            color: #2c3e50;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .admin-link:hover {
            background: #e9ecef;
            border-left-color: #3498db;
            transform: translateX(3px);
        }
        
        .admin-link .icon {
            width: 20px;
            text-align: center;
            color: #3498db;
        }
        
        .admin-link .title {
            font-weight: 500;
        }
        
        .admin-link .desc {
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        .sidebar {
            display: grid;
            gap: 20px;
        }
        
        .sidebar-widget {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .widget-header {
            background: #95a5a6;
            color: white;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .widget-content {
            padding: 15px 20px;
        }
        
        .recent-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ecf0f1;
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .quick-action {
            display: block;
            background: #3498db;
            color: white;
            text-decoration: none;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }
        
        .quick-action:hover {
            background: #2980b9;
        }
        
        .quick-action.danger {
            background: #e74c3c;
        }
        
        .quick-action.danger:hover {
            background: #c0392b;
        }
        
        .logout-btn {
            background: #e74c3c;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .logout-btn:hover {
            background: #c0392b;
        }
        
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .admin-sections {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>YFEvents Administration Dashboard</h1>
            <div class="user-info">
                <span>Welcome, Administrator</span>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- System Overview Stats -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['events']['total']) ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['events']['pending']) ?></div>
                <div class="stat-label">Pending Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['shops']['total']) ?></div>
                <div class="stat-label">Local Businesses</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($stats['yfclaim']['sales']) ?></div>
                <div class="stat-label">Estate Sales</div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="main-content">
                <!-- Core Event Management -->
                <div class="admin-sections">
                    <div class="admin-section">
                        <div class="section-header">
                            <span class="icon">📅</span> Event Management
                        </div>
                        <div class="section-content">
                            <div class="admin-links">
                                <a href="events.php" class="admin-link">
                                    <span class="icon">📝</span>
                                    <div>
                                        <div class="title">Manage Events</div>
                                        <div class="desc">View, edit, approve events</div>
                                    </div>
                                </a>
                                <a href="calendar/" class="admin-link">
                                    <span class="icon">🗓️</span>
                                    <div>
                                        <div class="title">Advanced Calendar Admin</div>
                                        <div class="desc">Enhanced event management</div>
                                    </div>
                                </a>
                                <a href="scrapers.php" class="admin-link">
                                    <span class="icon">🔗</span>
                                    <div>
                                        <div class="title">Event Scrapers</div>
                                        <div class="desc">Manage automated sources</div>
                                    </div>
                                </a>
                                <a href="intelligent-scraper.php" class="admin-link">
                                    <span class="icon">🤖</span>
                                    <div>
                                        <div class="title">AI Event Scraper</div>
                                        <div class="desc">LLM-powered content extraction</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Business Directory -->
                    <div class="admin-section">
                        <div class="section-header">
                            <span class="icon">🏪</span> Business Directory
                        </div>
                        <div class="section-content">
                            <div class="admin-links">
                                <a href="shops.php" class="admin-link">
                                    <span class="icon">🏬</span>
                                    <div>
                                        <div class="title">Manage Local Shops</div>
                                        <div class="desc">Business profiles and directory</div>
                                    </div>
                                </a>
                                <a href="geocode-fix.php" class="admin-link">
                                    <span class="icon">📍</span>
                                    <div>
                                        <div class="title">Geocoding Repair</div>
                                        <div class="desc">Fix location coordinates</div>
                                    </div>
                                </a>
                                <a href="validate-urls.php" class="admin-link">
                                    <span class="icon">🔗</span>
                                    <div>
                                        <div class="title">URL Validator</div>
                                        <div class="desc">Test website links</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- YFClaim Estate Sales -->
                    <div class="admin-section">
                        <div class="section-header">
                            <span class="icon">🛍️</span> YFClaim Estate Sales
                        </div>
                        <div class="section-content">
                            <div class="admin-links">
                                <a href="../modules/yfclaim/www/admin/" class="admin-link">
                                    <span class="icon">🏷️</span>
                                    <div>
                                        <div class="title">Manage Estate Sales</div>
                                        <div class="desc">Sales, items, and offers</div>
                                    </div>
                                </a>
                                <a href="../modules/yfclaim/www/admin/sellers.php" class="admin-link">
                                    <span class="icon">👥</span>
                                    <div>
                                        <div class="title">Seller Management</div>
                                        <div class="desc">Estate sale companies</div>
                                    </div>
                                </a>
                                <a href="../modules/yfclaim/www/admin/qr-codes.php" class="admin-link">
                                    <span class="icon">📱</span>
                                    <div>
                                        <div class="title">QR Code Generator</div>
                                        <div class="desc">Sale access codes</div>
                                    </div>
                                </a>
                                <a href="../modules/yfclaim/www/admin/reports.php" class="admin-link">
                                    <span class="icon">📊</span>
                                    <div>
                                        <div class="title">Sales Reports</div>
                                        <div class="desc">Analytics and statistics</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Authentication & Users -->
                    <div class="admin-section">
                        <div class="section-header">
                            <span class="icon">🔐</span> User Management
                        </div>
                        <div class="section-content">
                            <div class="admin-links">
                                <a href="../modules/yfauth/www/admin/" class="admin-link">
                                    <span class="icon">👤</span>
                                    <div>
                                        <div class="title">YFAuth User Portal</div>
                                        <div class="desc">User accounts and roles</div>
                                    </div>
                                </a>
                                <a href="users.php" class="admin-link">
                                    <span class="icon">👥</span>
                                    <div>
                                        <div class="title">Admin Users</div>
                                        <div class="desc">Administrative accounts</div>
                                    </div>
                                </a>
                                <a href="permissions.php" class="admin-link">
                                    <span class="icon">🛡️</span>
                                    <div>
                                        <div class="title">Permissions</div>
                                        <div class="desc">Access control settings</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- System Configuration -->
                    <div class="admin-section">
                        <div class="section-header">
                            <span class="icon">⚙️</span> System Configuration
                        </div>
                        <div class="section-content">
                            <div class="admin-links">
                                <a href="settings.php" class="admin-link">
                                    <span class="icon">🔧</span>
                                    <div>
                                        <div class="title">System Settings</div>
                                        <div class="desc">General configuration</div>
                                    </div>
                                </a>
                                <a href="theme-config.php" class="admin-link">
                                    <span class="icon">🎨</span>
                                    <div>
                                        <div class="title">Theme Configuration</div>
                                        <div class="desc">Visual customization</div>
                                    </div>
                                </a>
                                <a href="../modules/yftheme/www/admin/" class="admin-link">
                                    <span class="icon">🖌️</span>
                                    <div>
                                        <div class="title">YFTheme Editor</div>
                                        <div class="desc">Advanced theme management</div>
                                    </div>
                                </a>
                                <a href="system-status.php" class="admin-link">
                                    <span class="icon">📊</span>
                                    <div>
                                        <div class="title">System Status</div>
                                        <div class="desc">Health monitoring</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Development Tools -->
                    <div class="admin-section">
                        <div class="section-header">
                            <span class="icon">🛠️</span> Development Tools
                        </div>
                        <div class="section-content">
                            <div class="admin-links">
                                <a href="../css-diagnostic.php" class="admin-link">
                                    <span class="icon">🔍</span>
                                    <div>
                                        <div class="title">CSS Diagnostics</div>
                                        <div class="desc">Style debugging tools</div>
                                    </div>
                                </a>
                                <a href="logs.php" class="admin-link">
                                    <span class="icon">📋</span>
                                    <div>
                                        <div class="title">System Logs</div>
                                        <div class="desc">Error and activity logs</div>
                                    </div>
                                </a>
                                <a href="database.php" class="admin-link">
                                    <span class="icon">🗄️</span>
                                    <div>
                                        <div class="title">Database Tools</div>
                                        <div class="desc">Database management</div>
                                    </div>
                                </a>
                                <a href="../refactor/" class="admin-link">
                                    <span class="icon">🔬</span>
                                    <div>
                                        <div class="title">Refactor System</div>
                                        <div class="desc">Modern architecture</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Quick Actions -->
                <div class="sidebar-widget">
                    <div class="widget-header">Quick Actions</div>
                    <div class="widget-content">
                        <div class="quick-actions">
                            <a href="../" class="quick-action">View Public Site</a>
                            <a href="../calendar.php" class="quick-action">Event Calendar</a>
                            <a href="system-checkup.php" class="quick-action">System Check</a>
                            <a href="backup.php" class="quick-action danger">Create Backup</a>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Events -->
                <div class="sidebar-widget">
                    <div class="widget-header">Recent Events</div>
                    <div class="widget-content">
                        <?php if (empty($recentEvents)): ?>
                            <p style="color: #7f8c8d; font-style: italic;">No recent events</p>
                        <?php else: ?>
                            <?php foreach ($recentEvents as $event): ?>
                                <div class="recent-item">
                                    <div>
                                        <div style="font-weight: 500;"><?= htmlspecialchars(substr($event['title'], 0, 30)) ?><?= strlen($event['title']) > 30 ? '...' : '' ?></div>
                                        <div style="font-size: 0.8rem; color: #7f8c8d;"><?= date('M j, g:i A', strtotime($event['start_datetime'])) ?></div>
                                    </div>
                                    <span class="status-badge status-<?= $event['status'] ?>">
                                        <?= ucfirst($event['status']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- System Health -->
                <div class="sidebar-widget">
                    <div class="widget-header">System Health</div>
                    <div class="widget-content">
                        <div class="recent-item">
                            <span>Database</span>
                            <span class="status-badge status-approved">Online</span>
                        </div>
                        <div class="recent-item">
                            <span>Event Scrapers</span>
                            <span class="status-badge status-approved"><?= $stats['sources']['active'] ?>/<?= $stats['sources']['total'] ?> Active</span>
                        </div>
                        <div class="recent-item">
                            <span>YFClaim Module</span>
                            <span class="status-badge status-approved">Online</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>