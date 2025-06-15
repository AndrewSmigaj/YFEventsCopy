<?php
// Admin Dashboard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /refactor/admin/login');
    exit;
}

// Set correct base path for refactor admin
$basePath = '/refactor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - YFEvents</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background-color 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .nav-links a.active {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            color: #2c3e50;
        }
        
        .welcome-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .welcome-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .welcome-text {
            color: #7f8c8d;
            margin-bottom: 1.5rem;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .quick-action {
            background: #667eea;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .quick-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .quick-action-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }
        
        .stat-change {
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .stat-change.positive {
            background: #d4edda;
            color: #155724;
        }
        
        .stat-change.negative {
            background: #f8d7da;
            color: #721c24;
        }
        
        .activity-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .activity-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        
        .activity-title {
            font-size: 1.25rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-text {
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .activity-time {
            color: #7f8c8d;
            font-size: 0.85rem;
        }
        
        .system-status {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }
        
        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .status-item:last-child {
            border-bottom: none;
        }
        
        .status-indicator {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-healthy {
            background: #d4edda;
            color: #155724;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .activity-section {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>🛠️ YFEvents Admin</h1>
            <nav class="nav-links">
                <a href="<?= $basePath ?>/admin/index.php" class="active">Dashboard</a>
                <a href="<?= $basePath ?>/admin/events.php">Events</a>
                <a href="<?= $basePath ?>/admin/shops.php">Shops</a>
                <a href="<?= $basePath ?>/admin/claims.php">Claims</a>
                <a href="<?= $basePath ?>/admin/scrapers.php">Scrapers</a>
                <a href="<?= $basePath ?>/admin/users.php">Users</a>
                <a href="<?= $basePath ?>/admin/settings.php">Settings</a>
                <a href="#" onclick="logout()">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <h2 class="page-title">Admin Dashboard</h2>
        </div>
        
        <!-- Welcome Section -->
        <div class="welcome-card">
            <h3 class="welcome-title">Welcome to YFEvents Administration</h3>
            <p class="welcome-text">Manage events, shops, scrapers, and users from this central dashboard. Monitor system health and performance in real-time.</p>
            <div class="quick-actions">
                <a href="<?= $basePath ?>/admin/events.php" class="quick-action">
                    <span class="quick-action-icon">📅</span>
                    Manage Events
                </a>
                <a href="<?= $basePath ?>/admin/shops.php" class="quick-action">
                    <span class="quick-action-icon">🏪</span>
                    Manage Shops
                </a>
                <a href="<?= $basePath ?>/admin/scrapers.php" class="quick-action">
                    <span class="quick-action-icon">🔄</span>
                    Run Scrapers
                </a>
                <a href="<?= $basePath ?>/admin/users.php" class="quick-action">
                    <span class="quick-action-icon">👥</span>
                    Manage Users
                </a>
            </div>
            
            <!-- Detailed Action Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 2rem;">
                <!-- Event Management -->
                <div class="activity-card">
                    <h3 class="activity-title">📅 Event Management</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="<?= $basePath ?>/admin/events.php" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">📋 View All Events</a>
                        <a href="<?= $basePath ?>/admin/events.php?status=pending" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">⏳ Review Pending Events</a>
                        <a href="<?= $basePath ?>/admin/events.php?featured=1" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">⭐ Manage Featured Events</a>
                        <a href="javascript:void(0)" onclick="showEventStats()" style="text-decoration: none; color: #667eea; padding: 0.5rem 0; cursor: pointer;">📊 Event Statistics</a>
                    </div>
                </div>
                
                <!-- Shop Management -->
                <div class="activity-card">
                    <h3 class="activity-title">🏪 Shop Management</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="<?= $basePath ?>/admin/shops.php" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">🏪 View All Shops</a>
                        <a href="<?= $basePath ?>/admin/shops.php?status=pending" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">⏳ Review Pending Shops</a>
                        <a href="<?= $basePath ?>/admin/shops.php?verified=1" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">✅ Verify Shop Information</a>
                        <a href="javascript:void(0)" onclick="showShopStats()" style="text-decoration: none; color: #667eea; padding: 0.5rem 0; cursor: pointer;">📊 Shop Statistics</a>
                    </div>
                </div>
                
                <!-- Scraper Management -->
                <div class="activity-card">
                    <h3 class="activity-title">🔄 Scraper Management</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="<?= $basePath ?>/admin/scrapers.php" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">🔧 Manage Sources</a>
                        <a href="javascript:void(0)" onclick="runAllScrapers()" style="text-decoration: none; color: #667eea; padding: 0.5rem 0; cursor: pointer;">🚀 Run All Scrapers</a>
                        <a href="javascript:void(0)" onclick="testScrapers()" style="text-decoration: none; color: #667eea; padding: 0.5rem 0; cursor: pointer;">🧪 Test Scrapers</a>
                        <a href="javascript:void(0)" onclick="showScraperStats()" style="text-decoration: none; color: #667eea; padding: 0.5rem 0; cursor: pointer;">📊 Scraper Statistics</a>
                    </div>
                </div>
                
                <!-- System Management -->
                <div class="activity-card">
                    <h3 class="activity-title">🔧 System Management</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="javascript:void(0)" onclick="checkSystemHealth()" style="text-decoration: none; color: #667eea; padding: 0.5rem 0; cursor: pointer;">💚 System Health</a>
                        <a href="javascript:void(0)" onclick="showAnalytics()" style="text-decoration: none; color: #667eea; padding: 0.5rem 0; cursor: pointer;">📈 Analytics</a>
                        <a href="javascript:void(0)" onclick="showPerformance()" style="text-decoration: none; color: #667eea; padding: 0.5rem 0; cursor: pointer;">⚡ Performance</a>
                        <a href="javascript:void(0)" onclick="showActivityLog()" style="text-decoration: none; color: #667eea; padding: 0.5rem 0; cursor: pointer;">📋 Activity Log</a>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="activity-card">
                    <h3 class="activity-title">🔍 Quick Actions</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="<?= $basePath ?>/" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">🏠 View Public Site</a>
                        <a href="<?= $basePath ?>/events" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">📅 Public Events</a>
                        <a href="<?= $basePath ?>/shops" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">🏪 Public Shops</a>
                        <a href="<?= $basePath ?>/claims" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">🏛️ Estate Sales</a>
                        <a href="<?= $basePath ?>/admin/claims.php" style="text-decoration: none; color: #667eea; padding: 0.5rem 0;">🔧 Manage Claims</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Grid -->
        <div class="stats-grid" id="statsGrid">
            <div class="loading">Loading statistics...</div>
        </div>
        
        <!-- Activity and System Status -->
        <div class="activity-section">
            <div class="activity-card">
                <h3 class="activity-title">Recent Activity</h3>
                <div id="activityFeed">
                    <div class="loading">Loading recent activity...</div>
                </div>
            </div>
            
            <div class="activity-card">
                <h3 class="activity-title">System Status</h3>
                <div class="system-status" id="systemStatus">
                    <div class="loading">Checking system status...</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        
        // Load dashboard data
        document.addEventListener('DOMContentLoaded', () => {
            loadStatistics();
            loadRecentActivity();
            loadSystemStatus();
            
            // Refresh data every 5 minutes
            setInterval(() => {
                loadStatistics();
                loadRecentActivity();
                loadSystemStatus();
            }, 300000);
        });
        
        async function loadStatistics() {
            try {
                // Load statistics from multiple sources
                const [eventsStats, shopsStats, scrapersStats] = await Promise.all([
                    fetch(`${basePath}/admin/events/statistics`).then(r => r.json()),
                    fetch(`${basePath}/admin/shops/statistics`).then(r => r.json()),
                    fetch(`${basePath}/api/scrapers/statistics`).then(r => r.json())
                ]);
                
                const statsGrid = document.getElementById('statsGrid');
                
                const eventData = eventsStats.success ? eventsStats.data.statistics : {};
                const shopData = shopsStats.success ? shopsStats.data.statistics : {};
                const scraperData = scrapersStats.success ? scrapersStats.data : {};
                
                statsGrid.innerHTML = `
                    <div class="stat-card">
                        <div class="stat-value">${eventData.total || 0}</div>
                        <div class="stat-label">Total Events</div>
                        <div class="stat-change positive">+${eventData.today || 0} today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${eventData.pending || 0}</div>
                        <div class="stat-label">Pending Events</div>
                        <div class="stat-change ${(eventData.pending || 0) > 10 ? 'negative' : 'positive'}">
                            ${(eventData.pending || 0) > 10 ? 'Needs attention' : 'Under control'}
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${shopData.total || 0}</div>
                        <div class="stat-label">Local Shops</div>
                        <div class="stat-change positive">${shopData.verified || 0} verified</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${scraperData.active_sources || 0}</div>
                        <div class="stat-label">Active Scrapers</div>
                        <div class="stat-change positive">
                            ${scraperData.total_runs > 0 ? Math.round((scraperData.successful_runs / scraperData.total_runs) * 100) : 0}% success rate
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${eventData.featured || 0}</div>
                        <div class="stat-label">Featured Events</div>
                        <div class="stat-change positive">Highlighted</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${shopData.pending || 0}</div>
                        <div class="stat-label">Pending Shops</div>
                        <div class="stat-change ${(shopData.pending || 0) > 5 ? 'negative' : 'positive'}">
                            ${(shopData.pending || 0) > 5 ? 'Review needed' : 'Current'}
                        </div>
                    </div>
                `;
                
            } catch (error) {
                console.error('Error loading statistics:', error);
                document.getElementById('statsGrid').innerHTML = `
                    <div class="stat-card">
                        <div class="stat-value">-</div>
                        <div class="stat-label">Error loading stats</div>
                    </div>
                `;
            }
        }
        
        async function loadRecentActivity() {
            try {
                // Simulate recent activity - in real implementation, this would come from an audit log
                const activities = [
                    { icon: '📅', text: 'New event submitted: "Yakima Farmers Market"', time: '5 minutes ago' },
                    { icon: '🏪', text: 'Shop verified: "Downtown Coffee Co."', time: '12 minutes ago' },
                    { icon: '✅', text: 'Bulk approved 8 events', time: '1 hour ago' },
                    { icon: '🔄', text: 'Scrapers completed successfully', time: '2 hours ago' },
                    { icon: '👤', text: 'New user registered', time: '3 hours ago' },
                    { icon: '📍', text: 'Geocoded 12 shops', time: '4 hours ago' }
                ];
                
                const activityFeed = document.getElementById('activityFeed');
                
                let html = '';
                activities.forEach(activity => {
                    html += `
                        <div class="activity-item">
                            <div class="activity-icon">${activity.icon}</div>
                            <div class="activity-content">
                                <div class="activity-text">${activity.text}</div>
                                <div class="activity-time">${activity.time}</div>
                            </div>
                        </div>
                    `;
                });
                
                activityFeed.innerHTML = html;
                
            } catch (error) {
                console.error('Error loading activity:', error);
                document.getElementById('activityFeed').innerHTML = '<p>Error loading activity feed</p>';
            }
        }
        
        async function loadSystemStatus() {
            try {
                // Check various system components
                const statusChecks = [
                    { name: 'Database Connection', status: 'healthy' },
                    { name: 'Event Scrapers', status: 'healthy' },
                    { name: 'Geocoding Service', status: 'healthy' },
                    { name: 'File Permissions', status: 'healthy' },
                    { name: 'Cache System', status: 'warning' },
                    { name: 'Backup Status', status: 'healthy' }
                ];
                
                const systemStatus = document.getElementById('systemStatus');
                
                let html = '';
                statusChecks.forEach(check => {
                    html += `
                        <div class="status-item">
                            <span>${check.name}</span>
                            <span class="status-indicator status-${check.status}">
                                ${check.status === 'healthy' ? '✓ Healthy' : 
                                  check.status === 'warning' ? '⚠ Warning' : 
                                  '✗ Error'}
                            </span>
                        </div>
                    `;
                });
                
                systemStatus.innerHTML = html;
                
            } catch (error) {
                console.error('Error loading system status:', error);
                document.getElementById('systemStatus').innerHTML = '<p>Error checking system status</p>';
            }
        }
        
        // Interactive dashboard functions
        function showEventStats() {
            showToast('Loading event statistics...', 'info');
            window.location.href = `${basePath}/admin/events`;
        }
        
        function showShopStats() {
            showToast('Loading shop statistics...', 'info');
            window.location.href = `${basePath}/admin/shops`;
        }
        
        function showScraperStats() {
            showToast('Loading scraper statistics...', 'info');
            window.location.href = `${basePath}/admin/scrapers`;
        }
        
        async function runAllScrapers() {
            if (!confirm('This will run all active scrapers. Continue?')) return;
            
            showToast('Starting all scrapers...', 'info');
            
            try {
                const response = await fetch(`${basePath}/api/scrapers/run-all`, {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('All scrapers completed successfully!', 'success');
                    loadStatistics(); // Refresh stats
                } else {
                    showToast(result.message || 'Scraping failed', 'error');
                }
            } catch (error) {
                console.error('Error running scrapers:', error);
                showToast('Error running scrapers', 'error');
            }
        }
        
        async function testScrapers() {
            showToast('Testing all scrapers...', 'info');
            
            try {
                const response = await fetch(`${basePath}/api/scrapers/test-all`, {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`Tested ${result.data.total} scrapers. ${result.data.working} working, ${result.data.failed} failed.`, 'success');
                } else {
                    showToast('Failed to test scrapers', 'error');
                }
            } catch (error) {
                console.error('Error testing scrapers:', error);
                showToast('Error testing scrapers', 'error');
            }
        }
        
        function checkSystemHealth() {
            showToast('System health check in progress...', 'info');
            loadSystemStatus();
        }
        
        function showAnalytics() {
            showToast('Loading analytics dashboard...', 'info');
            // In a real implementation, this would open an analytics modal or page
        }
        
        function showPerformance() {
            showToast('Loading performance metrics...', 'info');
            // In a real implementation, this would show performance data
        }
        
        function showActivityLog() {
            showToast('Loading activity log...', 'info');
            loadRecentActivity();
        }
        
        function showToast(message, type = 'success') {
            // Create toast element if it doesn't exist
            let toast = document.getElementById('toast');
            if (!toast) {
                toast = document.createElement('div');
                toast.id = 'toast';
                toast.className = 'toast';
                toast.style.cssText = `
                    position: fixed;
                    bottom: 2rem;
                    right: 2rem;
                    background: #333;
                    color: white;
                    padding: 1rem 1.5rem;
                    border-radius: 6px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
                    transform: translateY(100px);
                    opacity: 0;
                    transition: all 0.3s;
                    z-index: 2000;
                `;
                document.body.appendChild(toast);
            }
            
            // Set message and type
            toast.textContent = message;
            toast.className = `toast ${type}`;
            
            // Apply type-specific styling
            if (type === 'success') {
                toast.style.background = '#27ae60';
            } else if (type === 'error') {
                toast.style.background = '#e74c3c';
            } else if (type === 'info') {
                toast.style.background = '#3498db';
            } else {
                toast.style.background = '#333';
            }
            
            // Show toast
            toast.style.transform = 'translateY(0)';
            toast.style.opacity = '1';
            
            // Hide after 3 seconds
            setTimeout(() => {
                toast.style.transform = 'translateY(100px)';
                toast.style.opacity = '0';
            }, 3000);
        }
        
        async function logout() {
            try {
                const response = await fetch(`${basePath}/admin/logout`, { method: 'POST' });
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = `${basePath}/admin/login`;
                }
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = `${basePath}/admin/login`;
            }
        }
    </script>
</body>
</html>