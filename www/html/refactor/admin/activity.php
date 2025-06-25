<?php
session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /refactor/admin/login');
    exit;
}

// Mock activity data for demonstration
$activities = [
    [
        'user' => 'admin',
        'action' => 'Event Approved',
        'details' => 'Approved event "Summer Music Festival"',
        'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'type' => 'success',
        'icon' => 'check-circle'
    ],
    [
        'user' => 'system',
        'action' => 'Scraper Run',
        'details' => 'Yakima Valley Events scraper completed: 12 new events',
        'timestamp' => date('Y-m-d H:i:s', strtotime('-3 hours')),
        'type' => 'info',
        'icon' => 'spider'
    ],
    [
        'user' => 'admin',
        'action' => 'Shop Updated',
        'details' => 'Updated shop "Central Coffee House"',
        'timestamp' => date('Y-m-d H:i:s', strtotime('-5 hours')),
        'type' => 'primary',
        'icon' => 'store'
    ],
    [
        'user' => 'admin',
        'action' => 'User Created',
        'details' => 'Created new user "event_manager"',
        'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'type' => 'success',
        'icon' => 'user-plus'
    ],
    [
        'user' => 'system',
        'action' => 'Cache Cleared',
        'details' => 'System cache cleared automatically',
        'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day -2 hours')),
        'type' => 'warning',
        'icon' => 'trash'
    ]
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .activity-item {
            border-left: 3px solid #dee2e6;
            padding-left: 20px;
            position: relative;
            margin-bottom: 20px;
        }
        .activity-item::before {
            content: '';
            position: absolute;
            left: -8px;
            top: 5px;
            width: 13px;
            height: 13px;
            border-radius: 50%;
            background-color: #fff;
            border: 3px solid #dee2e6;
        }
        .activity-item.success { border-left-color: #198754; }
        .activity-item.success::before { border-color: #198754; }
        .activity-item.info { border-left-color: #0dcaf0; }
        .activity-item.info::before { border-color: #0dcaf0; }
        .activity-item.warning { border-left-color: #ffc107; }
        .activity-item.warning::before { border-color: #ffc107; }
        .activity-item.primary { border-left-color: #0d6efd; }
        .activity-item.primary::before { border-color: #0d6efd; }
        .activity-item.danger { border-left-color: #dc3545; }
        .activity-item.danger::before { border-color: #dc3545; }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
        }
        .filter-badge {
            cursor: pointer;
            user-select: none;
        }
        .filter-badge.active {
            opacity: 1;
        }
        .filter-badge:not(.active) {
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="./dashboard.php">
                <i class="fas fa-calendar-alt"></i> YFEvents Admin
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text text-white me-3">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['admin_username'] ?? 'Admin'; ?>
                </span>
                <a class="btn btn-outline-light btn-sm" href="../">
                    <i class="fas fa-home"></i> View Site
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3">
                    <i class="fas fa-history"></i> Activity Log
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="./dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Activity</li>
                    </ol>
                </nav>
            </div>
            <div class="col-auto">
                <button class="btn btn-outline-primary">
                    <i class="fas fa-download"></i> Export Log
                </button>
            </div>
        </div>

        <!-- Filter Options -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <label class="form-label">Filter by Type:</label>
                        <div>
                            <span class="badge bg-success filter-badge active me-2" onclick="toggleFilter(this)">
                                <i class="fas fa-check"></i> Success
                            </span>
                            <span class="badge bg-info filter-badge active me-2" onclick="toggleFilter(this)">
                                <i class="fas fa-info"></i> Info
                            </span>
                            <span class="badge bg-warning filter-badge active me-2" onclick="toggleFilter(this)">
                                <i class="fas fa-exclamation"></i> Warning
                            </span>
                            <span class="badge bg-primary filter-badge active me-2" onclick="toggleFilter(this)">
                                <i class="fas fa-cog"></i> System
                            </span>
                            <span class="badge bg-danger filter-badge active me-2" onclick="toggleFilter(this)">
                                <i class="fas fa-times"></i> Error
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Time Range:</label>
                        <select class="form-select">
                            <option>Last 24 Hours</option>
                            <option>Last 7 Days</option>
                            <option>Last 30 Days</option>
                            <option>All Time</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Timeline -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <div class="activity-timeline">
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item <?php echo $activity['type']; ?>">
                            <div class="d-flex align-items-start">
                                <div class="activity-icon bg-<?php echo $activity['type']; ?> me-3">
                                    <i class="fas fa-<?php echo $activity['icon']; ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="mb-1"><?php echo $activity['action']; ?></h6>
                                        <small class="text-muted">
                                            <?php 
                                            $time = strtotime($activity['timestamp']);
                                            $diff = time() - $time;
                                            if ($diff < 3600) {
                                                echo round($diff / 60) . ' minutes ago';
                                            } elseif ($diff < 86400) {
                                                echo round($diff / 3600) . ' hours ago';
                                            } else {
                                                echo round($diff / 86400) . ' days ago';
                                            }
                                            ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?php echo $activity['details']; ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-user"></i> <?php echo $activity['user']; ?> • 
                                        <i class="fas fa-clock"></i> <?php echo $activity['timestamp']; ?>
                                    </small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Load More -->
                <div class="text-center mt-4">
                    <button class="btn btn-outline-primary">
                        <i class="fas fa-arrow-down"></i> Load More Activities
                    </button>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="mb-0">152</h4>
                        <small class="text-muted">Total Activities Today</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="mb-0">3</h4>
                        <small class="text-muted">Active Users</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body text-center">
                        <h4 class="mb-0">98.5%</h4>
                        <small class="text-muted">System Uptime</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleFilter(element) {
        element.classList.toggle('active');
        // In a real implementation, this would filter the activity items
    }
    </script>
</body>
</html>