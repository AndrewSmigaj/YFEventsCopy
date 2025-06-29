<?php
// Simple module listing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents - Module Directory</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 40px;
            font-size: 1.1rem;
        }
        
        .modules {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .module {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .module:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .module h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }
        
        .module p {
            color: #666;
            margin-bottom: 15px;
            font-size: 0.95rem;
        }
        
        .module-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .module-links a {
            color: #667eea;
            text-decoration: none;
            padding: 8px 12px;
            border: 1px solid #667eea;
            border-radius: 5px;
            text-align: center;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .module-links a:hover {
            background: #667eea;
            color: white;
        }
        
        .module-links a.admin {
            border-color: #dc3545;
            color: #dc3545;
        }
        
        .module-links a.admin:hover {
            background: #dc3545;
            color: white;
        }
        
        .status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-left: 10px;
        }
        
        .status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .status.beta {
            background: #fff3cd;
            color: #856404;
        }
        
        .status.development {
            background: #f8d7da;
            color: #721c24;
        }
        
        .admin-section {
            margin-top: 40px;
            padding-top: 40px;
            border-top: 2px solid #e0e0e0;
        }
        
        .admin-links {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        
        .admin-links a {
            padding: 12px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            text-decoration: none;
            color: #495057;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .admin-links a:hover {
            background: #e9ecef;
            border-color: #adb5bd;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .modules {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>YFEvents Platform</h1>
        <p class="subtitle">Yakima Valley Event Calendar & Local Business Directory</p>
        
        <div class="modules">
            <!-- Events Calendar Module -->
            <div class="module">
                <h3>📅 Events Calendar <span class="status active">Active</span></h3>
                <p>Browse and discover local events in the Yakima Valley. Features interactive calendar view and map integration.</p>
                <div class="module-links">
                    <a href="/calendar.php">View Calendar</a>
                    <a href="/events/submit/">Submit Event</a>
                </div>
            </div>
            
            <!-- Local Shops Module -->
            <div class="module">
                <h3>🏪 Local Shops Directory <span class="status active">Active</span></h3>
                <p>Explore local businesses and shops in the Yakima area with detailed profiles and map locations.</p>
                <div class="module-links">
                    <a href="/calendar.php#shops">Browse Shops</a>
                    <a href="/claim-shop.php">Claim Your Business</a>
                </div>
            </div>
            
            <!-- YFClaim Estate Sales Module -->
            <div class="module">
                <h3>🏛️ Estate Sales (YFClaim) <span class="status beta">Beta</span></h3>
                <p>Platform for estate sale companies to list sales and manage inventory. Browse current and upcoming sales.</p>
                <div class="module-links">
                    <a href="/modules/yfclaim/www/">Browse Sales</a>
                    <a href="/seller/login.php">Seller Portal</a>
                    <a href="/modules/yfclaim/www/admin/" class="admin">Admin Panel</a>
                </div>
            </div>
            
            <!-- Marketplace Gallery -->
            <div class="module">
                <h3>🛍️ Marketplace Gallery <span class="status active">Active</span></h3>
                <p>Browse all items from estate sales and classifieds in one unified gallery view.</p>
                <div class="module-links">
                    <a href="/gallery.php">View Gallery</a>
                    <a href="/seller/classifieds/create.php">Post Classified</a>
                </div>
            </div>
            
            <!-- YFClassifieds Module -->
            <div class="module">
                <h3>📢 Classifieds <span class="status active">Active</span></h3>
                <p>Post and browse classified ads for items, services, and more in the Yakima Valley area.</p>
                <div class="module-links">
                    <a href="/modules/yfclassifieds/www/">Browse Classifieds</a>
                    <a href="/seller/classifieds/create.php">Create Listing</a>
                    <a href="/modules/yfclassifieds/www/admin/" class="admin">Admin Panel</a>
                </div>
            </div>
            
            <!-- Communication Hub -->
            <div class="module">
                <h3>💬 Communication Hub <span class="status active">Active</span></h3>
                <p>Real-time messaging and collaboration platform for buyers, sellers, and community members.</p>
                <div class="module-links">
                    <a href="/communication/">Enter Hub</a>
                    <a href="/communication/theme-switcher.php">Theme Settings</a>
                </div>
            </div>
            
            <!-- Refactored System -->
            <div class="module">
                <h3>🚀 Refactored System <span class="status development">Development</span></h3>
                <p>Next-generation architecture with modern PHP, domain-driven design, and comprehensive APIs.</p>
                <div class="module-links">
                    <a href="/refactor/">View New System</a>
                    <a href="/refactor/admin/dashboard">Admin Dashboard</a>
                </div>
            </div>
        </div>
        
        <!-- Admin Section -->
        <div class="admin-section">
            <h2>🛠️ Administration</h2>
            <p style="color: #666; margin-top: 10px;">System management and configuration tools</p>
            
            <div class="admin-links">
                <a href="/admin/">Admin Dashboard</a>
                <a href="/admin/events.php">Manage Events</a>
                <a href="/admin/shops.php">Manage Shops</a>
                <a href="/admin/scrapers.php">Event Scrapers</a>
                <a href="/admin/users.php">User Management</a>
                <a href="/admin/settings.php">System Settings</a>
                <a href="/admin/email-events.php">Email Events</a>
                <a href="/admin/theme.php">Theme Editor</a>
                <a href="/admin/debug-logs.php">Debug Logs</a>
            </div>
        </div>
        
        <!-- API Documentation -->
        <div class="admin-section">
            <h2>📡 API Endpoints</h2>
            <p style="color: #666; margin-top: 10px;">Available REST API endpoints for developers</p>
            
            <div class="admin-links">
                <a href="/api/events">Events API</a>
                <a href="/api/shops">Shops API</a>
                <a href="/api/events-simple.php">Simple Events API</a>
                <a href="/ajax/calendar-events.php">Calendar AJAX</a>
            </div>
        </div>
        
        <!-- Footer -->
        <div style="margin-top: 60px; padding-top: 30px; border-top: 1px solid #e0e0e0; text-align: center; color: #666;">
            <p>YFEvents Platform &copy; <?= date('Y') ?> - Yakima Valley Community Platform</p>
            <p style="font-size: 0.9rem; margin-top: 10px;">
                <a href="/refactor/" style="color: #667eea;">Modern System</a> | 
                <a href="/admin/" style="color: #667eea;">Admin Login</a> | 
                <a href="/seller/login.php" style="color: #667eea;">Seller Login</a>
            </p>
        </div>
    </div>
</body>
</html>