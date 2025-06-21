<?php
/**
 * Shared Admin Navigation Component
 * 
 * Provides consistent navigation across all admin pages
 * Includes YF Classifieds integration and quick stats
 */

// Determine current page for active highlighting
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$basePath = '';

// Get quick stats for navigation badges
function getQuickStats() {
    global $db;
    $stats = [
        'pending_events' => 0,
        'total_classifieds' => 0,
        'pending_shops' => 0,
        'system_alerts' => 0
    ];
    
    try {
        // Get pending events
        $stmt = $db->query("SELECT COUNT(*) FROM events WHERE status = 'pending'");
        $stats['pending_events'] = $stmt->fetchColumn();
        
        // Get total classifieds
        $stmt = $db->query("SELECT COUNT(*) FROM yfc_items WHERE listing_type = 'classified'");
        $stats['total_classifieds'] = $stmt->fetchColumn();
        
        // Get pending shops  
        $stmt = $db->query("SELECT COUNT(*) FROM local_shops WHERE status = 'pending'");
        $stats['pending_shops'] = $stmt->fetchColumn();
        
    } catch (Exception $e) {
        // Ignore errors, keep defaults
    }
    
    return $stats;
}

$quickStats = getQuickStats();
?>

<!-- Admin Navigation Sidebar -->
<div class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <h4>🛠️ Admin Panel</h4>
        <button class="sidebar-toggle d-lg-none" onclick="toggleSidebar()">
            <i class="bi bi-x"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <!-- Dashboard -->
        <div class="nav-section">
            <a href="/refactor/admin/dashboard" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </div>
        
        <!-- Event Management -->
        <div class="nav-section">
            <div class="nav-section-title">📅 Events</div>
            <a href="events.php" class="nav-link <?= $currentPage === 'events' ? 'active' : '' ?>">
                <i class="bi bi-calendar-event"></i>
                <span>Manage Events</span>
                <?php if ($quickStats['pending_events'] > 0): ?>
                    <span class="badge bg-warning"><?= $quickStats['pending_events'] ?></span>
                <?php endif; ?>
            </a>
            <a href="email-events.php" class="nav-link <?= $currentPage === 'email-events' ? 'active' : '' ?>">
                <i class="bi bi-envelope"></i>
                <span>Email Events</span>
            </a>
            <a href="scrapers.php" class="nav-link <?= $currentPage === 'scrapers' ? 'active' : '' ?>">
                <i class="bi bi-robot"></i>
                <span>Event Scrapers</span>
            </a>
        </div>
        
        <!-- Shop Management -->
        <div class="nav-section">
            <div class="nav-section-title">🏪 Shops</div>
            <a href="shops.php" class="nav-link <?= $currentPage === 'shops' ? 'active' : '' ?>">
                <i class="bi bi-shop"></i>
                <span>Manage Shops</span>
                <?php if ($quickStats['pending_shops'] > 0): ?>
                    <span class="badge bg-warning"><?= $quickStats['pending_shops'] ?></span>
                <?php endif; ?>
            </a>
        </div>
        
        <!-- YF Classifieds -->
        <div class="nav-section">
            <div class="nav-section-title">🛍️ Classifieds</div>
            <a href="../modules/yfclassifieds/www/admin/simple-index.php" class="nav-link" target="_blank">
                <i class="bi bi-grid"></i>
                <span>Classifieds Dashboard</span>
                <span class="badge bg-info"><?= $quickStats['total_classifieds'] ?></span>
            </a>
            <a href="../modules/yfclassifieds/www/admin/create.php" class="nav-link" target="_blank">
                <i class="bi bi-plus-circle"></i>
                <span>Add New Item</span>
            </a>
            <a href="../modules/yfclassifieds/www/admin/upload.php" class="nav-link" target="_blank">
                <i class="bi bi-camera"></i>
                <span>Upload Photos</span>
            </a>
            <a href="../modules/yfclassifieds/www/index.php" class="nav-link" target="_blank">
                <i class="bi bi-eye"></i>
                <span>View Gallery</span>
            </a>
        </div>
        
        <!-- Module Management -->
        <div class="nav-section">
            <div class="nav-section-title">🧩 Modules</div>
            <a href="modules.php" class="nav-link <?= $currentPage === 'modules' ? 'active' : '' ?>">
                <i class="bi bi-puzzle"></i>
                <span>Module Manager</span>
            </a>
            <a href="claims.php" class="nav-link <?= $currentPage === 'claims' ? 'active' : '' ?>">
                <i class="bi bi-tag"></i>
                <span>YF Claim</span>
            </a>
        </div>
        
        <!-- System -->
        <div class="nav-section">
            <div class="nav-section-title">⚙️ System</div>
            <a href="users.php" class="nav-link <?= $currentPage === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a>
            <a href="settings.php" class="nav-link <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
            <a href="theme.php" class="nav-link <?= $currentPage === 'theme' ? 'active' : '' ?>">
                <i class="bi bi-palette"></i>
                <span>Theme & SEO</span>
            </a>
            <a href="email-config.php" class="nav-link <?= $currentPage === 'email-config' ? 'active' : '' ?>">
                <i class="bi bi-envelope-gear"></i>
                <span>Email Config</span>
            </a>
        </div>
        
        <!-- Quick Actions -->
        <div class="nav-section">
            <div class="nav-section-title">🚀 Quick Actions</div>
            <a href="/refactor/" class="nav-link" target="_blank">
                <i class="bi bi-house"></i>
                <span>View Site</span>
            </a>
            <a href="browser-scrapers.php" class="nav-link <?= $currentPage === 'browser-scrapers' ? 'active' : '' ?>">
                <i class="bi bi-browser-chrome"></i>
                <span>Browser Scrapers</span>
            </a>
        </div>
    </nav>
    
    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="user-info">
            <i class="bi bi-person-circle"></i>
            <span><?= $_SESSION['admin_username'] ?? 'Admin' ?></span>
        </div>
        <button class="logout-btn" onclick="logout()">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </button>
    </div>
</div>

<!-- Mobile sidebar overlay -->
<div class="sidebar-overlay d-lg-none" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Mobile toggle button -->
<button class="mobile-sidebar-toggle d-lg-none" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
</button>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('/refactor/admin/logout', { method: 'POST' })
            .then(() => window.location.href = '/refactor/admin/login')
            .catch(() => window.location.href = '/refactor/admin/login');
    }
}
</script>