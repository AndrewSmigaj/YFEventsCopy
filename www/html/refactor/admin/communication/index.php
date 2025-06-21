<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth_check.php';

// Check admin privileges
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: /refactor/admin/login.php');
    exit;
}

$pageTitle = 'Communication Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stat-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .nav-pills .nav-link.active {
            background-color: #007bff;
        }
        .table-actions {
            display: flex;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/admin/">YFEvents Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/events.php">Events</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/shops.php">Shops</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/admin/communication/">Communication</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/communication/" target="_blank">
                            <i class="fas fa-external-link-alt"></i> View Hub
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/admin/logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-md-3">
                <!-- Side Navigation -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Communication Admin</h5>
                        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                            <button class="nav-link active" data-bs-toggle="pill" data-bs-target="#overview" type="button">
                                <i class="fas fa-tachometer-alt"></i> Overview
                            </button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#users" type="button">
                                <i class="fas fa-users"></i> Users
                            </button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#channels" type="button">
                                <i class="fas fa-hashtag"></i> Channels
                            </button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#messages" type="button">
                                <i class="fas fa-comments"></i> Messages
                            </button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#moderation" type="button">
                                <i class="fas fa-shield-alt"></i> Moderation
                            </button>
                            <button class="nav-link" data-bs-toggle="pill" data-bs-target="#settings" type="button">
                                <i class="fas fa-cog"></i> Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="tab-content" id="v-pills-tabContent">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview">
                        <h2>Communication Overview</h2>
                        
                        <!-- Statistics Cards -->
                        <div class="row mt-4">
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">Total Users</h6>
                                        <div class="stat-number" id="stat-users">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">Active Channels</h6>
                                        <div class="stat-number" id="stat-channels">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">Messages Today</h6>
                                        <div class="stat-number" id="stat-messages">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted">Active Now</h6>
                                        <div class="stat-number" id="stat-active">0</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <div id="recent-activity">
                                    <div class="text-center py-3">
                                        <div class="spinner-border text-primary" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Users Tab -->
                    <div class="tab-pane fade" id="users">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2>User Management</h2>
                            <button class="btn btn-primary" onclick="showAddUserModal()">
                                <i class="fas fa-plus"></i> Add User
                            </button>
                        </div>

                        <!-- User Search -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="user-search" 
                                               placeholder="Search users...">
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" id="user-role-filter">
                                            <option value="">All Roles</option>
                                            <option value="admin">Admin</option>
                                            <option value="moderator">Moderator</option>
                                            <option value="user">User</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" id="user-status-filter">
                                            <option value="">All Status</option>
                                            <option value="active">Active</option>
                                            <option value="suspended">Suspended</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Users Table -->
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="users-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Username</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Joined</th>
                                                <th>Last Active</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Users will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Channels Tab -->
                    <div class="tab-pane fade" id="channels">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2>Channel Management</h2>
                            <button class="btn btn-primary" onclick="showCreateChannelModal()">
                                <i class="fas fa-plus"></i> Create Channel
                            </button>
                        </div>

                        <!-- Channels Table -->
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="channels-table">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Type</th>
                                                <th>Members</th>
                                                <th>Messages</th>
                                                <th>Created</th>
                                                <th>Last Activity</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Channels will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Messages Tab -->
                    <div class="tab-pane fade" id="messages">
                        <h2>Message Overview</h2>
                        
                        <!-- Message Stats -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Message Statistics</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="message-chart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Top Channels</h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="top-channels-list">
                                            <!-- Top channels will be loaded here -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Messages -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Messages</h5>
                            </div>
                            <div class="card-body">
                                <div id="recent-messages">
                                    <!-- Recent messages will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Moderation Tab -->
                    <div class="tab-pane fade" id="moderation">
                        <h2>Content Moderation</h2>
                        
                        <!-- Flagged Content -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Flagged Messages</h5>
                            </div>
                            <div class="card-body">
                                <div id="flagged-messages">
                                    <p class="text-muted">No flagged content at this time.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Banned Words -->
                        <div class="card mt-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Banned Words</h5>
                                <button class="btn btn-sm btn-primary" onclick="addBannedWord()">
                                    <i class="fas fa-plus"></i> Add Word
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="banned-words-list">
                                    <!-- Banned words will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Tab -->
                    <div class="tab-pane fade" id="settings">
                        <h2>Communication Settings</h2>
                        
                        <form id="settings-form" class="mt-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">General Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Allow User Registration</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="allow-registration" checked>
                                            <label class="form-check-label" for="allow-registration">
                                                Enable new user registration
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Default User Role</label>
                                        <select class="form-select" id="default-role">
                                            <option value="user">User</option>
                                            <option value="moderator">Moderator</option>
                                        </select>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Message Retention (days)</label>
                                        <input type="number" class="form-control" id="message-retention" 
                                               value="90" min="1">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Max File Upload Size (MB)</label>
                                        <input type="number" class="form-control" id="max-file-size" 
                                               value="10" min="1" max="50">
                                    </div>
                                </div>
                            </div>

                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5 class="mb-0">Notification Settings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="enable-email-notifications" checked>
                                            <label class="form-check-label" for="enable-email-notifications">
                                                Enable email notifications
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="enable-push-notifications">
                                            <label class="form-check-label" for="enable-push-notifications">
                                                Enable push notifications
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="fas fa-save"></i> Save Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">Add User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="user-form">
                    <div class="modal-body">
                        <input type="hidden" id="user-id">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="user-username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="user-email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" id="user-password">
                            <small class="text-muted">Leave blank to keep existing password</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" id="user-firstname" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="user-lastname" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" id="user-role" required>
                                <option value="user">User</option>
                                <option value="moderator">Moderator</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Channel Modal -->
    <div class="modal fade" id="channelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Channel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="channel-form">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Channel Name</label>
                            <input type="text" class="form-control" id="channel-name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" id="channel-description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Channel Type</label>
                            <select class="form-select" id="channel-type" required>
                                <option value="public">Public</option>
                                <option value="private">Private</option>
                                <option value="announcement">Announcement</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Channel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="js/communication-admin.js"></script>
</body>
</html>