<?php
// Set page-specific data
$pageTitle = '🛠️ Admin Dashboard';
$title = 'YFEvents V2 - Admin Dashboard';
?>

<?php $this->startSection('styles'); ?>
<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    
    .stat-icon {
        font-size: 2.5rem;
        margin-bottom: 10px;
    }
    
    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #343a40;
        margin-bottom: 5px;
    }
    
    .stat-label {
        color: #6c757d;
        font-size: 0.9rem;
    }
    
    .actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .action-section {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .action-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #343a40;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .action-list {
        list-style: none;
    }
    
    .action-list li {
        margin-bottom: 10px;
    }
    
    .action-link {
        color: #007bff;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 0;
    }
    
    .action-link:hover {
        color: #0056b3;
        text-decoration: underline;
    }
    
    .recent-activity {
        background: white;
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }
    
    .activity-title {
        font-size: 1.25rem;
        font-weight: 600;
        color: #343a40;
        margin-bottom: 15px;
    }
    
    .activity-item {
        padding: 10px 0;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .activity-item:last-child {
        border-bottom: none;
    }
    
    .activity-text {
        color: #495057;
    }
    
    .activity-time {
        color: #6c757d;
        font-size: 0.85rem;
    }
    
    .loading {
        text-align: center;
        color: #6c757d;
        font-style: italic;
    }
</style>
<?php $this->stopSection(); ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-number" id="total-events">-</div>
        <div class="stat-label">Total Events</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div class="stat-number" id="pending-events">-</div>
        <div class="stat-label">Pending Approval</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">🏪</div>
        <div class="stat-number" id="total-shops">-</div>
        <div class="stat-label">Local Shops</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">⭐</div>
        <div class="stat-number" id="featured-events">-</div>
        <div class="stat-label">Featured Events</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">💬</div>
        <div class="stat-number" id="active-channels">-</div>
        <div class="stat-label">Active Channels</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-number" id="total-users">-</div>
        <div class="stat-label">Total Users</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">📨</div>
        <div class="stat-number" id="total-messages">-</div>
        <div class="stat-label">Messages Today</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon">📢</div>
        <div class="stat-number" id="active-announcements">-</div>
        <div class="stat-label">Active Announcements</div>
    </div>
</div>

<div class="actions-grid">
    <div class="action-section">
        <div class="action-title">📅 Event Management</div>
        <ul class="action-list">
            <li><a href="<?= $this->url('admin/events') ?>" class="action-link">📋 View All Events</a></li>
            <li><a href="<?= $this->url('admin/events?status=pending') ?>" class="action-link">⏳ Review Pending Events</a></li>
            <li><a href="<?= $this->url('admin/events?featured=true') ?>" class="action-link">⭐ Manage Featured Events</a></li>
            <li><a href="<?= $this->url('admin/events/statistics') ?>" class="action-link">📊 Event Statistics</a></li>
        </ul>
    </div>
    
    <div class="action-section">
        <div class="action-title">🏪 Shop Management</div>
        <ul class="action-list">
            <li><a href="<?= $this->url('admin/shops') ?>" class="action-link">🏪 View All Shops</a></li>
            <li><a href="<?= $this->url('admin/shops?status=pending') ?>" class="action-link">⏳ Review Pending Shops</a></li>
            <li><a href="<?= $this->url('admin/shops?verified=false') ?>" class="action-link">✅ Verify Shop Information</a></li>
            <li><a href="<?= $this->url('admin/shops/statistics') ?>" class="action-link">📊 Shop Statistics</a></li>
        </ul>
    </div>
    
    <div class="action-section">
        <div class="action-title">📧 Email Management</div>
        <ul class="action-list">
            <li><a href="<?= $this->url('admin/email-events') ?>" class="action-link">📧 Email Events</a></li>
            <li><a href="<?= $this->url('admin/email-config') ?>" class="action-link">⚙️ Email Configuration</a></li>
            <li><a href="<?= $this->url('admin/email-config#test') ?>" class="action-link">🔌 Test Connection</a></li>
        </ul>
    </div>
    
    <div class="action-section">
        <div class="action-title">🎨 Theme Management</div>
        <ul class="action-list">
            <li><a href="<?= $this->url('admin/theme') ?>" class="action-link">🎨 Theme Editor</a></li>
            <li><a href="<?= $this->url('admin/theme#seo') ?>" class="action-link">🔍 SEO Settings</a></li>
            <li><a href="<?= $this->url('admin/theme#social') ?>" class="action-link">📱 Social Media</a></li>
            <li><a href="<?= $this->url('admin/theme#presets') ?>" class="action-link">🎯 Theme Presets</a></li>
        </ul>
    </div>
    
    <div class="action-section">
        <div class="action-title">🤖 Event Scrapers</div>
        <ul class="action-list">
            <li><a href="<?= $this->url('admin/scrapers') ?>" class="action-link">🔧 Manage Scrapers</a></li>
            <li><a href="<?= $this->url('admin/scrapers#create') ?>" class="action-link">➕ Add New Scraper</a></li>
            <li><a href="<?= $this->url('admin/scrapers#statistics') ?>" class="action-link">📊 Scraper Statistics</a></li>
        </ul>
    </div>
    
    <div class="action-section">
        <div class="action-title">🏷️ YF Claims</div>
        <ul class="action-list">
            <li><a href="<?= $this->url('admin/claims') ?>" class="action-link">🏷️ Claims Dashboard</a></li>
            <li><a href="<?= $this->url('claims') ?>" class="action-link">👀 View Public Sales</a></li>
            <li><a href="<?= $this->url('seller/dashboard') ?>" class="action-link">🏪 Seller Portal</a></li>
            <li><a href="<?= $this->url('buyer/auth') ?>" class="action-link">🛍️ Buyer Portal</a></li>
        </ul>
    </div>
    
    <div class="action-section">
        <div class="action-title">💬 Communication Hub</div>
        <ul class="action-list">
            <li><a href="<?= $this->url('admin/communication') ?>" class="action-link">📊 Communication Dashboard</a></li>
            <li><a href="<?= $this->url('admin/communication#channels') ?>" class="action-link">📢 Manage Channels</a></li>
            <li><a href="<?= $this->url('admin/communication#users') ?>" class="action-link">👥 Manage Users</a></li>
            <li><a href="<?= $this->url('admin/communication#messages') ?>" class="action-link">💬 Message Moderation</a></li>
            <li><a href="<?= $this->url('admin/communication#announcements') ?>" class="action-link">📢 Announcements</a></li>
            <li><a href="<?= $this->url('admin/communication#statistics') ?>" class="action-link">📈 Usage Statistics</a></li>
            <li><a href="<?= $this->url('communication') ?>" class="action-link">👀 View Public Hub</a></li>
        </ul>
    </div>
    
    <div class="action-section">
        <div class="action-title">🔧 System Management</div>
        <ul class="action-list">
            <li><a href="<?= $this->url('admin/users') ?>" class="action-link">👥 User Management</a></li>
            <li><a href="<?= $this->url('admin/modules') ?>" class="action-link">🧩 Module Management</a></li>
            <li><a href="<?= $this->url('admin/dashboard/health') ?>" class="action-link">💚 System Health</a></li>
            <li><a href="<?= $this->url('admin/dashboard/analytics') ?>" class="action-link">📈 Analytics</a></li>
            <li><a href="<?= $this->url('admin/dashboard/performance') ?>" class="action-link">⚡ Performance</a></li>
            <li><a href="<?= $this->url('admin/dashboard/activity') ?>" class="action-link">📋 Activity Log</a></li>
        </ul>
    </div>
    
    <div class="action-section">
        <div class="action-title">🔍 Quick Actions</div>
        <ul class="action-list">
            <li><a href="<?= $this->url() ?>" class="action-link">🏠 View Public Site</a></li>
            <li><a href="<?= $this->url('events') ?>" class="action-link">📅 Public Events</a></li>
            <li><a href="<?= $this->url('shops') ?>" class="action-link">🏪 Public Shops</a></li>
            <li><a href="<?= $this->url('claims') ?>" class="action-link">🏷️ Estate Sales</a></li>
            <li><a href="<?= $this->url('communication/') ?>" class="action-link">💬 Communication Hub</a></li>
            <li><a href="<?= $this->url('admin/settings') ?>" class="action-link">⚙️ System Settings</a></li>
        </ul>
    </div>
</div>

<div class="recent-activity">
    <div class="activity-title">📋 Recent Activity</div>
    <div id="activity-content">
        <div class="loading">Loading recent activity...</div>
    </div>
</div>

<?php $this->startSection('scripts'); ?>
<script>
    async function loadDashboardStats() {
        try {
            // Load event statistics
            const eventStatsResponse = await fetch(basePath + '/admin/events/statistics');
            if (eventStatsResponse.ok) {
                const eventStats = await eventStatsResponse.json();
                if (eventStats.success) {
                    document.getElementById('total-events').textContent = eventStats.data.statistics.total || 0;
                    document.getElementById('pending-events').textContent = eventStats.data.statistics.pending || 0;
                    document.getElementById('featured-events').textContent = eventStats.data.statistics.featured || 0;
                }
            }
            
            // Load shop statistics
            const shopStatsResponse = await fetch(basePath + '/admin/shops/statistics');
            if (shopStatsResponse.ok) {
                const shopStats = await shopStatsResponse.json();
                if (shopStats.success) {
                    document.getElementById('total-shops').textContent = shopStats.data.statistics.total || 0;
                }
            }
            
            // Load communication statistics
            try {
                const commStatsResponse = await fetch(basePath + '/api/communication/admin/statistics');
                if (commStatsResponse.ok) {
                    const commStats = await commStatsResponse.json();
                    if (commStats.success) {
                        document.getElementById('active-channels').textContent = commStats.data.channels || 0;
                        document.getElementById('total-users').textContent = commStats.data.users || 0;
                        document.getElementById('total-messages').textContent = commStats.data.messages_today || 0;
                        document.getElementById('active-announcements').textContent = commStats.data.announcements || 0;
                    }
                }
            } catch (error) {
                console.error('Error loading communication stats:', error);
            }
            
            // Load recent activity
            const activityResponse = await fetch(basePath + '/admin/dashboard/activity');
            if (activityResponse.ok) {
                const activity = await activityResponse.json();
                if (activity.success && activity.data.activities) {
                    const activityContent = document.getElementById('activity-content');
                    if (activity.data.activities.length > 0) {
                        activityContent.innerHTML = activity.data.activities.map(item => `
                            <div class="activity-item">
                                <div class="activity-text">${item.description}</div>
                                <div class="activity-time">${item.time}</div>
                            </div>
                        `).join('');
                    } else {
                        activityContent.innerHTML = '<div class="loading">No recent activity</div>';
                    }
                }
            }
            
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
        }
    }
    
    // Load dashboard data on page load
    document.addEventListener('DOMContentLoaded', loadDashboardStats);
</script>
<?php $this->stopSection(); ?>