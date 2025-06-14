<?php
// YFAuth - User Dashboard
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use YFEvents\Modules\YFAuth\Services\AuthService;

session_start();

// Initialize auth service
$authService = new AuthService($pdo);

// Check authentication
$user = null;
if (isset($_SESSION['yfa_session_id'])) {
    $user = $authService->verifySession($_SESSION['yfa_session_id']);
}

if (!$user) {
    header('Location: /modules/yfauth/www/login.php?return=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get user's active sessions
$activeSessions = $authService->getActiveSessions($user['id']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - YFAuth</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
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
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .welcome-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .welcome-title {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .welcome-subtitle {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .card-icon {
            font-size: 2rem;
        }
        
        .card-title {
            font-size: 1.3rem;
            color: #2c3e50;
        }
        
        .profile-info {
            margin-bottom: 1rem;
        }
        
        .profile-info dt {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .profile-info dd {
            color: #7f8c8d;
            margin-bottom: 1rem;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .badge-primary {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-success {
            background: #e8f5e8;
            color: #2e7d32;
        }
        
        .badge-warning {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .session-item {
            padding: 1rem;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .session-current {
            background: #e8f5e8;
            border-color: #27ae60;
        }
        
        .session-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }
        
        .session-details {
            color: #7f8c8d;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            display: inline-block;
            margin-right: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .quick-actions {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .action-card {
            padding: 1.5rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            transition: all 0.3s;
            text-decoration: none;
            color: inherit;
        }
        
        .action-card:hover {
            border-color: #3498db;
            background: #f8f9fa;
            color: inherit;
            text-decoration: none;
        }
        
        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .action-description {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
            }
            
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="nav-links">
                <a href="/">🏠 Home</a>
                <a href="/modules/yfauth/www/">YFAuth</a>
            </div>
            <div class="nav-links">
                <span>Welcome, <?= htmlspecialchars($user['first_name'] ?: $user['username']) ?></span>
                <a href="/modules/yfauth/www/logout.php">Logout</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <div class="welcome-section">
            <h1 class="welcome-title">Welcome to YFAuth</h1>
            <p class="welcome-subtitle">Manage your account, security settings, and preferences</p>
        </div>
        
        <div class="dashboard-grid">
            <!-- Profile Information -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">👤</div>
                    <h2 class="card-title">Profile Information</h2>
                </div>
                
                <dl class="profile-info">
                    <dt>Username</dt>
                    <dd><?= htmlspecialchars($user['username']) ?></dd>
                    
                    <dt>Email</dt>
                    <dd>
                        <?= htmlspecialchars($user['email']) ?>
                        <?php if ($user['email_verified']): ?>
                            <span class="badge badge-success">Verified</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Unverified</span>
                        <?php endif; ?>
                    </dd>
                    
                    <?php if ($user['first_name'] || $user['last_name']): ?>
                        <dt>Full Name</dt>
                        <dd><?= htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?></dd>
                    <?php endif; ?>
                    
                    <?php if ($user['phone']): ?>
                        <dt>Phone</dt>
                        <dd><?= htmlspecialchars($user['phone']) ?></dd>
                    <?php endif; ?>
                    
                    <dt>Account Status</dt>
                    <dd>
                        <span class="badge badge-<?= $user['status'] === 'active' ? 'success' : 'warning' ?>">
                            <?= ucfirst($user['status']) ?>
                        </span>
                    </dd>
                    
                    <dt>Member Since</dt>
                    <dd><?= date('F j, Y', strtotime($user['created_at'])) ?></dd>
                </dl>
                
                <a href="/modules/yfauth/www/profile.php" class="btn btn-primary">Edit Profile</a>
            </div>
            
            <!-- Roles & Permissions -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">🔐</div>
                    <h2 class="card-title">Roles & Permissions</h2>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <strong>Roles:</strong><br>
                    <?php if (!empty($user['roles'])): ?>
                        <?php foreach ($user['roles'] as $role): ?>
                            <span class="badge badge-primary"><?= htmlspecialchars($role['display_name']) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="badge badge-warning">No roles assigned</span>
                    <?php endif; ?>
                </div>
                
                <div>
                    <strong>Permissions:</strong><br>
                    <?php if (!empty($user['permissions'])): ?>
                        <?php 
                        $permissionGroups = [];
                        foreach ($user['permissions'] as $permission) {
                            $parts = explode('.', $permission['name']);
                            $module = $parts[0];
                            if (!isset($permissionGroups[$module])) {
                                $permissionGroups[$module] = [];
                            }
                            $permissionGroups[$module][] = $permission;
                        }
                        ?>
                        <?php foreach ($permissionGroups as $module => $permissions): ?>
                            <div style="margin-bottom: 0.5rem;">
                                <strong><?= ucfirst($module) ?>:</strong>
                                <?php foreach ($permissions as $permission): ?>
                                    <span class="badge badge-primary" style="font-size: 0.75rem;">
                                        <?= htmlspecialchars(str_replace($module . '.', '', $permission['name'])) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="badge badge-warning">No permissions assigned</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Security Settings -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">🛡️</div>
                    <h2 class="card-title">Security</h2>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <strong>Two-Factor Authentication:</strong><br>
                    <?php if ($user['two_factor_enabled']): ?>
                        <span class="badge badge-success">Enabled</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Disabled</span>
                    <?php endif; ?>
                </div>
                
                <div style="margin-bottom: 1.5rem;">
                    <strong>Last Login:</strong><br>
                    <?php if ($user['last_login_at']): ?>
                        <?= date('F j, Y g:i A', strtotime($user['last_login_at'])) ?>
                        <?php if ($user['last_ip']): ?>
                            <br><small>from <?= htmlspecialchars($user['last_ip']) ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge badge-warning">Never</span>
                    <?php endif; ?>
                </div>
                
                <a href="/modules/yfauth/www/security.php" class="btn btn-primary">Security Settings</a>
            </div>
            
            <!-- Active Sessions -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon">💻</div>
                    <h2 class="card-title">Active Sessions</h2>
                </div>
                
                <?php if (!empty($activeSessions)): ?>
                    <?php foreach ($activeSessions as $session): ?>
                        <div class="session-item <?= $session['id'] === $_SESSION['yfa_session_id'] ? 'session-current' : '' ?>">
                            <div class="session-info">
                                <div>
                                    <?php if ($session['id'] === $_SESSION['yfa_session_id']): ?>
                                        <strong>Current Session</strong>
                                    <?php else: ?>
                                        Other Session
                                    <?php endif; ?>
                                    <div class="session-details">
                                        <?php if ($session['ip_address']): ?>
                                            IP: <?= htmlspecialchars($session['ip_address']) ?><br>
                                        <?php endif; ?>
                                        Last active: <?= date('M j, g:i A', $session['last_activity']) ?>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($session['id'] !== $_SESSION['yfa_session_id']): ?>
                                        <button class="btn btn-danger" onclick="revokeSession('<?= $session['id'] ?>')">Revoke</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No active sessions found.</p>
                <?php endif; ?>
                
                <button class="btn btn-danger" onclick="revokeAllSessions()">Revoke All Other Sessions</button>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>Quick Actions</h2>
            
            <div class="actions-grid">
                <a href="/refactor/" class="action-card">
                    <div class="action-icon">📅</div>
                    <div class="action-title">Event Calendar</div>
                    <div class="action-description">Browse upcoming events</div>
                </a>
                
                <a href="/refactor/shops" class="action-card">
                    <div class="action-icon">🏪</div>
                    <div class="action-title">Local Shops</div>
                    <div class="action-description">Discover local businesses</div>
                </a>
                
                <a href="/modules/yfclaim/www/" class="action-card">
                    <div class="action-icon">🏠</div>
                    <div class="action-title">Estate Sales</div>
                    <div class="action-description">Browse estate sales</div>
                </a>
                
                <a href="/modules/yfauth/www/admin/" class="action-card">
                    <div class="action-icon">⚙️</div>
                    <div class="action-title">Admin Panel</div>
                    <div class="action-description">System administration</div>
                </a>
            </div>
        </div>
    </main>
    
    <script>
        function revokeSession(sessionId) {
            if (confirm('Are you sure you want to revoke this session?')) {
                fetch('/modules/yfauth/api/revoke-session.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ session_id: sessionId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to revoke session: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while revoking the session.');
                });
            }
        }
        
        function revokeAllSessions() {
            if (confirm('Are you sure you want to revoke all other sessions? You will remain logged in on this device.')) {
                fetch('/modules/yfauth/api/revoke-all-sessions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to revoke sessions: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while revoking sessions.');
                });
            }
        }
    </script>
</body>
</html>