<?php
require_once __DIR__ . '/../vendor/autoload.php';
use YFEvents\Helpers\PathHelper;

// Admin Dashboard Page
require_once __DIR__ . '/bootstrap.php';

// Set correct base path for refactor admin
$basePath = PathHelper::getBasePath();

// Get database connection
$db = $GLOBALS['db'] ?? null;
if (!$db) {
    die('Database connection not available');
}

// Get basic statistics
$stats = [];

// Count events
$result = $db->query("SELECT COUNT(*) as total, SUM(status = 'approved') as approved FROM events");
$stats['events'] = $result->fetch(PDO::FETCH_ASSOC);

// Count shops
$result = $db->query("SELECT COUNT(*) as total, SUM(status = 'active') as active FROM local_shops");
$stats['shops'] = $result->fetch(PDO::FETCH_ASSOC);

// Count users
$result = $db->query("SELECT COUNT(*) as total, SUM(role = 'admin') as admins FROM users");
$stats['users'] = $result->fetch(PDO::FETCH_ASSOC);

// Count scrapers
$result = $db->query("SELECT COUNT(*) as total, SUM(active = 1) as active FROM calendar_sources");
$stats['scrapers'] = $result->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="./assets/admin-styles.css">
    <style>
        .stat-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            background: white;
            text-decoration: none;
            color: inherit;
            display: block;
        }
        .stat-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
            text-decoration: none;
            color: inherit;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            font-size: 1rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-detail {
            font-size: 0.9rem;
            color: #999;
            margin-top: 5px;
        }
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .welcome-section h1 {
            margin-bottom: 10px;
        }
        .welcome-section p {
            margin-bottom: 0;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/includes/admin-navigation.php'; ?>
    
    <div class="admin-layout">
        <div class="admin-content">
            <div class="welcome-section">
                <h1>Welcome to YFEvents Admin Dashboard</h1>
                <p>Hi <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>! Here's an overview of your system.</p>
            </div>
            
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <a href="<?= $basePath ?>/admin/events.php" class="stat-card">
                        <i class="bi bi-calendar-event" style="font-size: 2rem; color: #667eea; margin-bottom: 10px;"></i>
                        <div class="stat-number"><?= number_format($stats['events']['total'] ?? 0) ?></div>
                        <div class="stat-label">Total Events</div>
                        <div class="stat-detail"><?= number_format($stats['events']['approved'] ?? 0) ?> approved</div>
                    </a>
                </div>
                
                <div class="col-md-3">
                    <a href="<?= $basePath ?>/admin/shops.php" class="stat-card">
                        <i class="bi bi-shop" style="font-size: 2rem; color: #28a745; margin-bottom: 10px;"></i>
                        <div class="stat-number"><?= number_format($stats['shops']['total'] ?? 0) ?></div>
                        <div class="stat-label">Local Shops</div>
                        <div class="stat-detail"><?= number_format($stats['shops']['active'] ?? 0) ?> active</div>
                    </a>
                </div>
                
                <div class="col-md-3">
                    <a href="<?= $basePath ?>/admin/users.php" class="stat-card">
                        <i class="bi bi-people" style="font-size: 2rem; color: #17a2b8; margin-bottom: 10px;"></i>
                        <div class="stat-number"><?= number_format($stats['users']['total'] ?? 0) ?></div>
                        <div class="stat-label">Users</div>
                        <div class="stat-detail"><?= number_format($stats['users']['admins'] ?? 0) ?> admins</div>
                    </a>
                </div>
                
                <div class="col-md-3">
                    <a href="<?= $basePath ?>/admin/scrapers.php" class="stat-card">
                        <i class="bi bi-robot" style="font-size: 2rem; color: #ffc107; margin-bottom: 10px;"></i>
                        <div class="stat-number"><?= number_format($stats['scrapers']['total'] ?? 0) ?></div>
                        <div class="stat-label">Scrapers</div>
                        <div class="stat-detail"><?= number_format($stats['scrapers']['active'] ?? 0) ?> active</div>
                    </a>
                </div>
            </div>
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-speedometer2"></i> Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?= $basePath ?>/admin/events.php?action=new" class="btn btn-outline-primary">
                                    <i class="bi bi-plus-circle"></i> Add New Event
                                </a>
                                <a href="<?= $basePath ?>/admin/scrapers.php?action=run" class="btn btn-outline-success">
                                    <i class="bi bi-play-circle"></i> Run Scrapers
                                </a>
                                <a href="<?= $basePath ?>/admin/email-events.php" class="btn btn-outline-info">
                                    <i class="bi bi-envelope"></i> Process Email Events
                                </a>
                                <a href="<?= $basePath ?>/admin/settings.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-gear"></i> System Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-boxes"></i> Modules</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?= $basePath ?>/admin/claims.php" class="btn btn-outline-primary">
                                    <i class="bi bi-house-door"></i> YFClaim - Estate Sales
                                </a>
                                <a href="<?= $basePath ?>/admin/communication/" class="btn btn-outline-info">
                                    <i class="bi bi-chat-dots"></i> Communication Hub
                                </a>
                                <a href="<?= $basePath ?>/admin/theme.php" class="btn btn-outline-warning">
                                    <i class="bi bi-palette"></i> Theme Editor
                                </a>
                                <a href="<?= $basePath ?>/admin/modules.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-puzzle"></i> All Modules
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>