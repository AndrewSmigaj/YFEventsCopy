<?php
/**
 * System Status Dashboard
 * Comprehensive monitoring and status overview
 */

require_once __DIR__ . '/../config/database.php';

// System status checks
$systemStatus = [
    'database' => 'online',
    'authentication' => 'checking',
    'scraping' => 'checking',
    'theming' => 'checking',
    'modules' => [],
    'performance' => [],
    'security' => [],
    'recommendations' => []
];

try {
    // Database connectivity
    $pdo->query("SELECT 1")->fetch();
    $systemStatus['database'] = 'online';
    
    // Get basic statistics
    $stats = [
        'events' => $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn(),
        'published_events' => $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'published'")->fetchColumn(),
        'shops' => $pdo->query("SELECT COUNT(*) FROM local_shops")->fetchColumn(),
        'active_shops' => $pdo->query("SELECT COUNT(*) FROM local_shops WHERE is_active = 1")->fetchColumn(),
        'sources' => $pdo->query("SELECT COUNT(*) FROM calendar_sources")->fetchColumn(),
        'active_sources' => $pdo->query("SELECT COUNT(*) FROM calendar_sources WHERE is_active = 1")->fetchColumn()
    ];
    
} catch (Exception $e) {
    $systemStatus['database'] = 'offline';
    $stats = ['error' => $e->getMessage()];
}

// Check authentication system
try {
    if ($pdo->query("SELECT 1 FROM yfa_auth_users LIMIT 1")->fetch()) {
        $systemStatus['authentication'] = 'enhanced';
    } else {
        $systemStatus['authentication'] = 'basic';
    }
} catch (Exception $e) {
    $systemStatus['authentication'] = 'basic';
}

// Check scraping system components
$scrapingComponents = [
    'QueueManager' => file_exists(__DIR__ . '/../src/Scrapers/Queue/QueueManager.php'),
    'ScraperWorker' => file_exists(__DIR__ . '/../src/Scrapers/Queue/ScraperWorker.php'),
    'RateLimiter' => file_exists(__DIR__ . '/../src/Scrapers/Queue/RateLimiter.php'),
    'WorkerManager' => file_exists(__DIR__ . '/../src/Scrapers/Queue/WorkerManager.php'),
    'ScraperScheduler' => file_exists(__DIR__ . '/../src/Scrapers/Queue/ScraperScheduler.php')
];

$systemStatus['scraping'] = array_sum($scrapingComponents) == count($scrapingComponents) ? 'optimized' : 'basic';

// Check theme system
$themeComponents = [
    'ThemeService' => file_exists(__DIR__ . '/../modules/yftheme/src/Services/ThemeService.php'),
    'SimpleThemeService' => file_exists(__DIR__ . '/../modules/yftheme/src/Services/SimpleThemeService.php'),
    'ThemeEditor' => file_exists(__DIR__ . '/../modules/yftheme/www/admin/theme-editor.php'),
    'ThemeInstaller' => file_exists(__DIR__ . '/../modules/yftheme/install.php')
];

$systemStatus['theming'] = array_sum($themeComponents) >= 3 ? 'available' : 'basic';

// Check modules
$moduleDir = __DIR__ . '/../modules';
if (is_dir($moduleDir)) {
    $modules = scandir($moduleDir);
    foreach ($modules as $module) {
        if ($module != '.' && $module != '..' && is_dir($moduleDir . '/' . $module)) {
            $manifestFile = $moduleDir . '/' . $module . '/module.json';
            $status = 'installed';
            $version = '1.0.0';
            
            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true);
                $version = $manifest['version'] ?? '1.0.0';
                $status = 'configured';
            }
            
            $systemStatus['modules'][] = [
                'name' => $module,
                'status' => $status,
                'version' => $version
            ];
        }
    }
}

// Performance checks
$systemStatus['performance'] = [
    'database_size' => 'calculating...',
    'cache_status' => is_dir(__DIR__ . '/../cache') ? 'available' : 'missing',
    'css_optimization' => file_exists(__DIR__ . '/../css-diagnostic.php') ? 'tools_available' : 'basic'
];

// Security recommendations status
$securityFeatures = [
    'Enhanced Auth Schema' => file_exists(__DIR__ . '/../modules/yfauth/database/enhanced_auth_schema.sql'),
    'Database Security Script' => file_exists(__DIR__ . '/../database/security_improvements.sql'),
    'Audit Logging' => file_exists(__DIR__ . '/../database/audit_logging.sql'),
    'Performance Optimization' => file_exists(__DIR__ . '/../database/performance_optimization.sql')
];

$systemStatus['security'] = $securityFeatures;

// Generate recommendations
$recommendations = [];

if ($systemStatus['authentication'] === 'basic') {
    $recommendations[] = [
        'type' => 'security',
        'priority' => 'high',
        'title' => 'Upgrade Authentication System',
        'description' => 'Install enhanced authentication with RBAC and MFA',
        'action' => 'Run /modules/yfauth/install.php'
    ];
}

if ($systemStatus['scraping'] === 'basic') {
    $recommendations[] = [
        'type' => 'performance',
        'priority' => 'medium',
        'title' => 'Optimize Scraping System',
        'description' => 'Enhanced scraping system with queues and workers is available',
        'action' => 'Review scraping system implementation'
    ];
}

if ($systemStatus['theming'] === 'basic') {
    $recommendations[] = [
        'type' => 'ui',
        'priority' => 'low',
        'title' => 'Install Theme System',
        'description' => 'Enable visual theme customization',
        'action' => 'Run /modules/yftheme/install.php'
    ];
}

$systemStatus['recommendations'] = $recommendations;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status - YFEvents</title>
    <link rel="stylesheet" href="/css/calendar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .status-header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 12px;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .status-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid var(--secondary-color);
        }
        
        .status-card.critical { border-left-color: var(--accent-color); }
        .status-card.warning { border-left-color: var(--warning-color); }
        .status-card.success { border-left-color: var(--success-color); }
        
        .status-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }
        
        .status-online { background: var(--success-color); }
        .status-warning { background: var(--warning-color); }
        .status-offline { background: var(--accent-color); }
        .status-partial { background: #3498db; }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--secondary-color);
            display: block;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--dark-gray);
        }
        
        .module-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .module-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .feature-list {
            margin-top: 15px;
        }
        
        .feature-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .feature-item:last-child {
            border-bottom: none;
        }
        
        .recommendations-section {
            margin-top: 30px;
        }
        
        .recommendation-item {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-left: 4px solid #3498db;
        }
        
        .recommendation-item.high { border-left-color: var(--accent-color); }
        .recommendation-item.medium { border-left-color: var(--warning-color); }
        .recommendation-item.low { border-left-color: var(--success-color); }
        
        .recommendation-title {
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 8px;
        }
        
        .recommendation-action {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9rem;
            margin-top: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        .nav-links {
            margin-bottom: 20px;
        }
        
        .nav-links a {
            color: var(--secondary-color);
            text-decoration: none;
            margin-right: 20px;
            font-weight: 500;
        }
        
        .nav-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="status-container">
        <div class="nav-links">
            <a href="/"><i class="fas fa-home"></i> Home</a>
            <a href="/admin/"><i class="fas fa-cog"></i> Admin</a>
            <a href="/calendar.php"><i class="fas fa-calendar"></i> Calendar</a>
            <a href="/css-diagnostic.php"><i class="fas fa-tools"></i> CSS Diagnostic</a>
        </div>
        
        <div class="status-header">
            <h1><i class="fas fa-chart-line"></i> YFEvents System Status</h1>
            <p>Comprehensive system monitoring and health overview</p>
            <p style="opacity: 0.9; margin-top: 15px;">
                <span class="status-indicator status-online"></span>
                Last Updated: <?= date('M j, Y g:i A') ?>
            </p>
        </div>
        
        <div class="status-grid">
            <!-- Database Status -->
            <div class="status-card <?= $systemStatus['database'] === 'online' ? 'success' : 'critical' ?>">
                <div class="status-title">
                    <i class="fas fa-database"></i>
                    Database System
                    <span class="status-indicator status-<?= $systemStatus['database'] === 'online' ? 'online' : 'offline' ?>"></span>
                </div>
                
                <?php if ($systemStatus['database'] === 'online'): ?>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?= number_format($stats['events']) ?></span>
                        <span class="stat-label">Total Events</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= number_format($stats['published_events']) ?></span>
                        <span class="stat-label">Published</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= number_format($stats['shops']) ?></span>
                        <span class="stat-label">Total Shops</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?= $stats['sources'] ?></span>
                        <span class="stat-label">Sources</span>
                    </div>
                </div>
                <?php else: ?>
                <p style="color: var(--accent-color);">❌ Database connection failed</p>
                <p style="font-size: 0.9rem; color: #666;"><?= htmlspecialchars($stats['error'] ?? 'Unknown error') ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Authentication Status -->
            <div class="status-card <?= $systemStatus['authentication'] === 'enhanced' ? 'success' : 'warning' ?>">
                <div class="status-title">
                    <i class="fas fa-shield-alt"></i>
                    Authentication System
                    <span class="status-indicator status-<?= $systemStatus['authentication'] === 'enhanced' ? 'online' : 'warning' ?>"></span>
                </div>
                
                <p><strong>Status:</strong> <?= ucfirst($systemStatus['authentication']) ?></p>
                
                <div class="feature-list">
                    <div class="feature-item">
                        <span>Enhanced Auth Schema</span>
                        <span><?= $systemStatus['security']['Enhanced Auth Schema'] ? '✅' : '❌' ?></span>
                    </div>
                    <div class="feature-item">
                        <span>RBAC System</span>
                        <span><?= $systemStatus['authentication'] === 'enhanced' ? '✅' : '❌' ?></span>
                    </div>
                    <div class="feature-item">
                        <span>MFA Support</span>
                        <span><?= $systemStatus['authentication'] === 'enhanced' ? '✅' : '❌' ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Scraping System -->
            <div class="status-card <?= $systemStatus['scraping'] === 'optimized' ? 'success' : 'warning' ?>">
                <div class="status-title">
                    <i class="fas fa-robot"></i>
                    Scraping System
                    <span class="status-indicator status-<?= $systemStatus['scraping'] === 'optimized' ? 'online' : 'warning' ?>"></span>
                </div>
                
                <p><strong>Status:</strong> <?= ucfirst($systemStatus['scraping']) ?></p>
                
                <div class="feature-list">
                    <?php foreach ($scrapingComponents as $component => $exists): ?>
                    <div class="feature-item">
                        <span><?= $component ?></span>
                        <span><?= $exists ? '✅' : '❌' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Theme System -->
            <div class="status-card <?= $systemStatus['theming'] === 'available' ? 'success' : 'warning' ?>">
                <div class="status-title">
                    <i class="fas fa-palette"></i>
                    Theme System
                    <span class="status-indicator status-<?= $systemStatus['theming'] === 'available' ? 'online' : 'warning' ?>"></span>
                </div>
                
                <p><strong>Status:</strong> <?= ucfirst($systemStatus['theming']) ?></p>
                
                <div class="feature-list">
                    <?php foreach ($themeComponents as $component => $exists): ?>
                    <div class="feature-item">
                        <span><?= $component ?></span>
                        <span><?= $exists ? '✅' : '❌' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Available Modules -->
            <div class="status-card">
                <div class="status-title">
                    <i class="fas fa-puzzle-piece"></i>
                    Available Modules (<?= count($systemStatus['modules']) ?>)
                </div>
                
                <div class="module-list">
                    <?php foreach ($systemStatus['modules'] as $module): ?>
                    <div class="module-item">
                        <span><strong><?= htmlspecialchars($module['name']) ?></strong></span>
                        <span style="font-size: 0.8rem; color: var(--dark-gray);">v<?= htmlspecialchars($module['version']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (empty($systemStatus['modules'])): ?>
                <p style="color: var(--dark-gray); font-style: italic;">No modules detected</p>
                <?php endif; ?>
            </div>
            
            <!-- Security Features -->
            <div class="status-card">
                <div class="status-title">
                    <i class="fas fa-lock"></i>
                    Security Features
                </div>
                
                <div class="feature-list">
                    <?php foreach ($systemStatus['security'] as $feature => $status): ?>
                    <div class="feature-item">
                        <span><?= htmlspecialchars($feature) ?></span>
                        <span><?= $status ? '✅' : '❌' ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Recommendations -->
        <?php if (!empty($systemStatus['recommendations'])): ?>
        <div class="recommendations-section">
            <h2 style="color: var(--primary-color); margin-bottom: 20px;">
                <i class="fas fa-lightbulb"></i> System Recommendations
            </h2>
            
            <?php foreach ($systemStatus['recommendations'] as $rec): ?>
            <div class="recommendation-item <?= $rec['priority'] ?>">
                <div class="recommendation-title"><?= htmlspecialchars($rec['title']) ?></div>
                <p><?= htmlspecialchars($rec['description']) ?></p>
                <div class="recommendation-action"><?= htmlspecialchars($rec['action']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="/admin/theme-config.php" class="btn btn-primary">
                <i class="fas fa-paint-brush"></i> Theme Configuration
            </a>
            <a href="/css-diagnostic.php" class="btn btn-warning">
                <i class="fas fa-diagnoses"></i> CSS Diagnostic
            </a>
            <a href="/modules/yftheme/install.php" class="btn btn-success">
                <i class="fas fa-download"></i> Install Theme Module
            </a>
            <a href="/database/apply_all_improvements.sql" class="btn btn-primary" target="_blank">
                <i class="fas fa-database"></i> Database Improvements
            </a>
            <a href="/" class="btn btn-success">
                <i class="fas fa-home"></i> View Landing Page
            </a>
        </div>
        
        <div style="margin-top: 40px; text-align: center; color: var(--dark-gray);">
            <p>YFEvents System Status Dashboard | Generated: <?= date('Y-m-d H:i:s') ?></p>
        </div>
    </div>
    
    <script>
        // Auto-refresh every 5 minutes
        setTimeout(() => {
            window.location.reload();
        }, 300000);
        
        // Add click tracking for action buttons
        document.querySelectorAll('.btn').forEach(btn => {
            btn.addEventListener('click', function() {
                console.log('Action clicked:', this.textContent.trim());
            });
        });
    </script>
</body>
</html>