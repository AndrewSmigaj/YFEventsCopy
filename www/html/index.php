<?php
/**
 * YFEvents Modern Landing Page
 * Unified portal for all YFEvents modules and features
 */

// Load configuration
require_once __DIR__ . '/../../config/database.php';

// Get system status
$systemStatus = [
    'events_count' => 0,
    'shops_count' => 0,
    'active_sources' => 0,
    'recent_events' => [],
    'modules_available' => []
];

try {
    // Get event statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'published' AND start_datetime >= NOW()");
    $systemStatus['events_count'] = $stmt->fetchColumn();
    
    // Get shop statistics  
    $stmt = $pdo->query("SELECT COUNT(*) FROM local_shops WHERE is_active = 1");
    $systemStatus['shops_count'] = $stmt->fetchColumn();
    
    // Get active sources
    $stmt = $pdo->query("SELECT COUNT(*) FROM calendar_sources WHERE is_active = 1");
    $systemStatus['active_sources'] = $stmt->fetchColumn();
    
    // Get recent events
    $stmt = $pdo->query("
        SELECT title, start_datetime, city, state 
        FROM events 
        WHERE status = 'published' AND start_datetime >= NOW() 
        ORDER BY start_datetime ASC 
        LIMIT 6
    ");
    $systemStatus['recent_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // Database error - continue with defaults
}

// Check available modules
$moduleDir = __DIR__ . '/../../modules';
if (is_dir($moduleDir)) {
    $modules = scandir($moduleDir);
    foreach ($modules as $module) {
        if ($module != '.' && $module != '..' && is_dir($moduleDir . '/' . $module)) {
            $manifestFile = $moduleDir . '/' . $module . '/module.json';
            if (file_exists($manifestFile)) {
                $manifest = json_decode(file_get_contents($manifestFile), true);
                $systemStatus['modules_available'][] = [
                    'name' => $module,
                    'display_name' => $manifest['display_name'] ?? ucfirst($module),
                    'description' => $manifest['description'] ?? '',
                    'version' => $manifest['version'] ?? '1.0.0'
                ];
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents - Yakima Valley Event Management System</title>
    <link rel="stylesheet" href="/css/calendar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <meta name="description" content="Complete event management system for the Yakima Valley featuring calendar, local businesses, estate sales, and more.">
    <style>
        /* Landing page specific styles */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 60px 20px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 200"><path d="M0,160L48,144C96,128,192,96,288,90.7C384,85,480,107,576,128C672,149,768,171,864,165.3C960,160,1056,128,1152,122.7C1248,117,1344,139,1392,149.3L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z" fill="rgba(255,255,255,0.1)"/></svg>') center bottom;
            background-size: cover;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-subtitle {
            font-size: 1.4rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 40px 0;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .stat-item {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .features-section {
            padding: 80px 20px;
            background: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 30px;
            margin-top: 50px;
        }
        
        .feature-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: var(--transition);
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: white;
            font-size: 1.5rem;
        }
        
        .feature-title {
            font-size: 1.4rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .feature-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .feature-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
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
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        
        .btn-outline {
            background: transparent;
            color: var(--secondary-color);
            border: 2px solid var(--secondary-color);
        }
        
        .btn-outline:hover {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-success:hover {
            background: #219a52;
        }
        
        .modules-section {
            padding: 80px 20px;
            background: white;
        }
        
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }
        
        .module-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            border-left: 4px solid var(--secondary-color);
            transition: var(--transition);
        }
        
        .module-card:hover {
            background: #e9ecef;
            border-left-color: var(--accent-color);
        }
        
        .module-name {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .module-version {
            font-size: 0.8rem;
            color: var(--dark-gray);
            background: white;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 10px;
        }
        
        .recent-events {
            padding: 60px 20px;
            background: #f8f9fa;
        }
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .event-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-left: 4px solid var(--success-color);
        }
        
        .event-title {
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 8px;
        }
        
        .event-meta {
            color: var(--dark-gray);
            font-size: 0.9rem;
        }
        
        .footer-section {
            background: var(--primary-color);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s ease;
        }
        
        .footer-links a:hover {
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .hero-title { font-size: 2.5rem; }
            .hero-subtitle { font-size: 1.2rem; }
            .features-grid { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-online { background: var(--success-color); }
        .status-warning { background: var(--warning-color); }
        .status-offline { background: var(--accent-color); }
    </style>
</head>
<body>
    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1 class="hero-title">
                <i class="fas fa-calendar-alt"></i>
                YFEvents
            </h1>
            <p class="hero-subtitle">
                Complete Event Management System for the Yakima Valley
            </p>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?= number_format($systemStatus['events_count']) ?></span>
                    <span class="stat-label">Upcoming Events</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= number_format($systemStatus['shops_count']) ?></span>
                    <span class="stat-label">Local Businesses</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= $systemStatus['active_sources'] ?></span>
                    <span class="stat-label">Event Sources</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?= count($systemStatus['modules_available']) ?></span>
                    <span class="stat-label">Active Modules</span>
                </div>
            </div>
            
            <div style="margin-top: 40px;">
                <a href="/calendar.php" class="btn btn-primary" style="background: white; color: var(--primary-color); margin: 0 10px;">
                    <i class="fas fa-calendar"></i> View Events Calendar
                </a>
                <a href="/admin/" class="btn btn-outline" style="border-color: white; color: white; margin: 0 10px;">
                    <i class="fas fa-cog"></i> Admin Dashboard
                </a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <h2 style="text-align: center; color: var(--primary-color); font-size: 2.5rem; margin-bottom: 20px;">
                Comprehensive Event Management
            </h2>
            <p style="text-align: center; color: #666; font-size: 1.2rem; max-width: 600px; margin: 0 auto;">
                Discover, manage, and promote events across the Yakima Valley with our integrated platform
            </p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="feature-title">Interactive Event Calendar</h3>
                    <p class="feature-description">
                        Browse events with advanced filtering, map integration, and real-time updates from multiple sources across the Yakima Valley.
                    </p>
                    <div class="feature-actions">
                        <a href="/calendar.php" class="btn btn-primary">
                            <i class="fas fa-calendar"></i> View Calendar
                        </a>
                        <a href="/events/submit/" class="btn btn-outline">
                            <i class="fas fa-plus"></i> Add Event
                        </a>
                    </div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <h3 class="feature-title">Local Business Directory</h3>
                    <p class="feature-description">
                        Comprehensive directory of local shops and businesses with detailed profiles, reviews, and integrated mapping.
                    </p>
                    <div class="feature-actions">
                        <a href="/calendar.php#shops" class="btn btn-primary">
                            <i class="fas fa-search"></i> Browse Shops
                        </a>
                        <a href="/claim-shop.php" class="btn btn-outline">
                            <i class="fas fa-building"></i> Claim Business
                        </a>
                    </div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <h3 class="feature-title">Estate Sale Platform</h3>
                    <p class="feature-description">
                        YFClaim estate sale management system with QR codes, item tracking, and bidding capabilities for buyers and sellers.
                    </p>
                    <div class="feature-actions">
                        <a href="/modules/yfclaim/www/" class="btn btn-primary">
                            <i class="fas fa-gavel"></i> Browse Sales
                        </a>
                        <a href="/modules/yfclaim/www/dashboard/" class="btn btn-outline">
                            <i class="fas fa-user"></i> Seller Portal
                        </a>
                    </div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3 class="feature-title">Intelligent Event Scraping</h3>
                    <p class="feature-description">
                        Automated event collection from multiple sources with AI-powered content extraction and duplicate detection.
                    </p>
                    <div class="feature-actions">
                        <a href="/admin/scrapers.php" class="btn btn-primary">
                            <i class="fas fa-cog"></i> Manage Sources
                        </a>
                        <a href="/admin/intelligent-scraper.php" class="btn btn-outline">
                            <i class="fas fa-brain"></i> AI Scraper
                        </a>
                    </div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="feature-title">Security & Authentication</h3>
                    <p class="feature-description">
                        Enterprise-grade security with role-based access control, multi-factor authentication, and comprehensive audit logging.
                    </p>
                    <div class="feature-actions">
                        <a href="/modules/yfauth/www/admin/login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt"></i> Enhanced Login
                        </a>
                        <a href="/admin/" class="btn btn-outline">
                            <i class="fas fa-users-cog"></i> User Management
                        </a>
                    </div>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-palette"></i>
                    </div>
                    <h3 class="feature-title">Theme Customization</h3>
                    <p class="feature-description">
                        Visual theme editor with live preview, preset themes, and CSS variable management for complete customization.
                    </p>
                    <div class="feature-actions">
                        <a href="/admin/theme-config.php" class="btn btn-primary">
                            <i class="fas fa-paint-brush"></i> Theme Editor
                        </a>
                        <a href="/css-diagnostic.php" class="btn btn-outline">
                            <i class="fas fa-diagnoses"></i> CSS Diagnostic
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Available Modules -->
    <?php if (!empty($systemStatus['modules_available'])): ?>
    <section class="modules-section">
        <div class="container">
            <h2 style="text-align: center; color: var(--primary-color); font-size: 2rem; margin-bottom: 20px;">
                <i class="fas fa-puzzle-piece"></i> Available Modules
            </h2>
            
            <div class="modules-grid">
                <?php foreach ($systemStatus['modules_available'] as $module): ?>
                <div class="module-card">
                    <div class="module-name">
                        <span class="status-indicator status-online"></span>
                        <?= htmlspecialchars($module['display_name']) ?>
                    </div>
                    <div class="module-version">v<?= htmlspecialchars($module['version']) ?></div>
                    <p style="color: #666; margin: 0;">
                        <?= htmlspecialchars($module['description'] ?: 'No description available') ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Recent Events -->
    <?php if (!empty($systemStatus['recent_events'])): ?>
    <section class="recent-events">
        <div class="container">
            <h2 style="text-align: center; color: var(--primary-color); font-size: 2rem; margin-bottom: 20px;">
                <i class="fas fa-clock"></i> Upcoming Events
            </h2>
            
            <div class="events-grid">
                <?php foreach ($systemStatus['recent_events'] as $event): ?>
                <div class="event-card">
                    <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
                    <div class="event-meta">
                        <i class="fas fa-calendar-day"></i>
                        <?= date('M j, Y \a\t g:i A', strtotime($event['start_datetime'])) ?>
                        <?php if ($event['city']): ?>
                            <br><i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($event['city']) ?><?= $event['state'] ? ', ' . htmlspecialchars($event['state']) : '' ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <a href="/calendar.php" class="btn btn-primary">
                    <i class="fas fa-calendar-alt"></i> View All Events
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer-section">
        <div class="footer-links">
            <a href="/calendar.php"><i class="fas fa-calendar"></i> Calendar</a>
            <a href="/admin/"><i class="fas fa-cog"></i> Admin</a>
            <a href="/modules/yfclaim/www/"><i class="fas fa-shopping-bag"></i> Estate Sales</a>
            <a href="/css-diagnostic.php"><i class="fas fa-tools"></i> Diagnostics</a>
            <a href="https://github.com/robugger" target="_blank"><i class="fab fa-github"></i> GitHub</a>
        </div>
        
        <div style="border-top: 1px solid rgba(255,255,255,0.2); padding-top: 20px; margin-top: 20px;">
            <p>&copy; 2025 YFEvents - Yakima Valley Event Management System</p>
            <p style="font-size: 0.9rem; opacity: 0.8;">
                <span class="status-indicator status-online"></span>
                System Status: Online | 
                Last Updated: <?= date('M j, Y g:i A') ?>
            </p>
        </div>
    </footer>

    <script>
        // Add some interactive elements
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats on scroll
            const statNumbers = document.querySelectorAll('.stat-number');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const target = entry.target;
                        const finalValue = parseInt(target.textContent.replace(/,/g, ''));
                        animateValue(target, 0, finalValue, 2000);
                        observer.unobserve(target);
                    }
                });
            });
            
            statNumbers.forEach(stat => observer.observe(stat));
        });
        
        function animateValue(element, start, end, duration) {
            const increment = end / (duration / 16);
            let current = start;
            
            const timer = setInterval(() => {
                current += increment;
                if (current >= end) {
                    current = end;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current).toLocaleString();
            }, 16);
        }
        
        // Add hover effects for cards
        document.querySelectorAll('.feature-card, .module-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    </script>
</body>
</html>