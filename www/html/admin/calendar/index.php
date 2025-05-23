<?php

// Admin Calendar Dashboard
require_once '../../../../config/database.php';
require_once '../../../../src/Models/EventModel.php';
require_once '../../../../src/Models/CalendarSourceModel.php';

use YakimaFinds\Models\EventModel;
use YakimaFinds\Models\CalendarSourceModel;

// Check admin authentication (integrate with existing CMS auth)
// session_start();
// if (!isset($_SESSION['admin_logged_in'])) {
//     header('Location: /admin/login.php');
//     exit;
// }

$db = getDatabaseConnection();
$eventModel = new EventModel($db);
$sourceModel = new CalendarSourceModel($db);

// Get dashboard statistics
$pendingEvents = $eventModel->getPendingEvents();
$recentEvents = $eventModel->getEvents(['limit' => 10]);
$sources = $sourceModel->getSources();
$recentActivity = $sourceModel->getRecentActivity(10);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar Management - Admin</title>
    <link rel="stylesheet" href="/css/calendar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        
        .admin-sidebar {
            background: var(--primary-color);
            color: white;
            padding: 1rem;
        }
        
        .admin-content {
            padding: 2rem;
            background: #f8f9fa;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
        }
        
        .dashboard-card h3 {
            margin: 0 0 1rem 0;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--secondary-color);
        }
        
        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 2rem 0;
        }
        
        .nav-menu li {
            margin-bottom: 0.5rem;
        }
        
        .nav-menu a {
            color: white;
            text-decoration: none;
            padding: 0.75rem 1rem;
            display: block;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .nav-menu a:hover,
        .nav-menu a.active {
            background: rgba(255,255,255,0.1);
        }
        
        .event-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .event-item-admin {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .event-item-admin:last-child {
            border-bottom: none;
        }
        
        .event-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
        }
        
        .source-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .source-status.active {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }
        
        .source-status.inactive {
            background: rgba(231, 76, 60, 0.1);
            color: var(--accent-color);
        }
        
        .activity-item {
            padding: 0.75rem;
            border-left: 3px solid var(--secondary-color);
            margin-bottom: 0.5rem;
            background: #f8f9fa;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: var(--dark-gray);
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <h2><i class="fas fa-calendar-alt"></i> Calendar Admin</h2>
            </div>
            
            <nav>
                <ul class="nav-menu">
                    <li><a href="/admin/calendar/" class="active"><i class="fas fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="/admin/calendar/events.php"><i class="fas fa-calendar-days"></i> Manage Events</a></li>
                    <li><a href="/admin/calendar/sources.php"><i class="fas fa-rss"></i> Event Sources</a></li>
                    <li><a href="/admin/calendar/shops.php"><i class="fas fa-store"></i> Local Shops</a></li>
                    <li><a href="/admin/calendar/settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="/admin/"><i class="fas fa-arrow-left"></i> Back to Main Admin</a></li>
                </ul>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="admin-header">
                <h1>Calendar Dashboard</h1>
                <p>Manage events, sources, and calendar settings</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3><i class="fas fa-clock"></i> Pending Events</h3>
                    <div class="stat-number"><?= count($pendingEvents) ?></div>
                    <p>Events awaiting approval</p>
                    <?php if (count($pendingEvents) > 0): ?>
                        <a href="/admin/calendar/events.php?status=pending" class="btn btn-primary btn-sm">
                            Review Pending
                        </a>
                    <?php endif; ?>
                </div>
                
                <div class="dashboard-card">
                    <h3><i class="fas fa-calendar-check"></i> Total Events</h3>
                    <div class="stat-number"><?= count($recentEvents) ?></div>
                    <p>Approved events this month</p>
                    <a href="/admin/calendar/events.php" class="btn btn-outline btn-sm">
                        Manage All Events
                    </a>
                </div>
                
                <div class="dashboard-card">
                    <h3><i class="fas fa-rss"></i> Active Sources</h3>
                    <div class="stat-number"><?= count(array_filter($sources, fn($s) => $s['active'])) ?></div>
                    <p>Scraping sources configured</p>
                    <a href="/admin/calendar/sources.php" class="btn btn-outline btn-sm">
                        Manage Sources
                    </a>
                </div>
                
                <div class="dashboard-card">
                    <h3><i class="fas fa-chart-line"></i> This Week</h3>
                    <div class="stat-number">
                        <?php
                        // Count events this week
                        $weekStart = date('Y-m-d', strtotime('monday this week'));
                        $weekEnd = date('Y-m-d', strtotime('sunday this week'));
                        $weekEvents = $eventModel->getEvents([
                            'start_date' => $weekStart,
                            'end_date' => $weekEnd,
                            'status' => 'approved'
                        ]);
                        echo count($weekEvents);
                        ?>
                    </div>
                    <p>Events this week</p>
                    <a href="/events" class="btn btn-outline btn-sm" target="_blank">
                        View Public Calendar
                    </a>
                </div>
            </div>
            
            <!-- Recent Activity and Pending Events -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3><i class="fas fa-clock"></i> Recent Pending Events</h3>
                    <div class="event-list">
                        <?php if (empty($pendingEvents)): ?>
                            <p>No pending events to review.</p>
                        <?php else: ?>
                            <?php foreach (array_slice($pendingEvents, 0, 5) as $event): ?>
                                <div class="event-item-admin">
                                    <div>
                                        <strong><?= htmlspecialchars($event['title']) ?></strong><br>
                                        <small><?= date('M j, Y g:i A', strtotime($event['start_datetime'])) ?></small>
                                    </div>
                                    <div class="event-actions">
                                        <button onclick="approveEvent(<?= $event['id'] ?>)" class="btn btn-sm" style="background: var(--success-color); color: white;">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button onclick="viewEvent(<?= $event['id'] ?>)" class="btn btn-outline btn-sm">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="rejectEvent(<?= $event['id'] ?>)" class="btn btn-sm" style="background: var(--accent-color); color: white;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <h3><i class="fas fa-activity"></i> Recent Scraping Activity</h3>
                    <div class="event-list">
                        <?php if (empty($recentActivity)): ?>
                            <p>No recent scraping activity.</p>
                        <?php else: ?>
                            <?php foreach ($recentActivity as $activity): ?>
                                <div class="activity-item">
                                    <div>
                                        <strong><?= htmlspecialchars($activity['source_name']) ?></strong>
                                        <?php if ($activity['status'] === 'success'): ?>
                                            <span style="color: var(--success-color);">
                                                <i class="fas fa-check-circle"></i> Success
                                            </span>
                                        <?php elseif ($activity['status'] === 'error'): ?>
                                            <span style="color: var(--accent-color);">
                                                <i class="fas fa-exclamation-circle"></i> Error
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--warning-color);">
                                                <i class="fas fa-clock"></i> Running
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-time">
                                        <?= date('M j, Y g:i A', strtotime($activity['started_at'])) ?>
                                        <?php if ($activity['events_found']): ?>
                                            - Found <?= $activity['events_found'] ?> events
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sources Overview -->
            <div class="dashboard-card">
                <h3><i class="fas fa-rss"></i> Event Sources Overview</h3>
                <div class="sources-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                    <?php foreach ($sources as $source): ?>
                        <div class="source-card" style="background: #f8f9fa; padding: 1rem; border-radius: var(--border-radius); border: 1px solid var(--light-gray);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                <strong><?= htmlspecialchars($source['name']) ?></strong>
                                <span class="source-status <?= $source['active'] ? 'active' : 'inactive' ?>">
                                    <?= $source['active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--dark-gray);">
                                <div>Type: <?= ucfirst($source['scrape_type']) ?></div>
                                <div>Events: <?= $source['event_count'] ?? 0 ?></div>
                                <div>Last scraped: 
                                    <?= $source['last_scraped'] ? date('M j, g:i A', strtotime($source['last_scraped'])) : 'Never' ?>
                                </div>
                            </div>
                            <div style="margin-top: 1rem;">
                                <a href="/admin/calendar/sources.php?edit=<?= $source['id'] ?>" class="btn btn-outline btn-sm">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <button onclick="testSource(<?= $source['id'] ?>)" class="btn btn-outline btn-sm">
                                    <i class="fas fa-play"></i> Test
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="source-card" style="background: var(--light-gray); padding: 1rem; border-radius: var(--border-radius); border: 2px dashed var(--medium-gray); display: flex; align-items: center; justify-content: center; text-align: center;">
                        <div>
                            <i class="fas fa-plus" style="font-size: 2rem; color: var(--medium-gray); margin-bottom: 0.5rem;"></i><br>
                            <a href="/admin/calendar/sources.php?action=add" class="btn btn-primary btn-sm">
                                Add New Source
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        async function approveEvent(eventId) {
            if (!confirm('Approve this event?')) return;
            
            try {
                const response = await fetch('/admin/calendar/ajax/approve-event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ eventId: eventId, action: 'approve' })
                });
                
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error occurred');
            }
        }
        
        async function rejectEvent(eventId) {
            if (!confirm('Reject this event? This action cannot be undone.')) return;
            
            try {
                const response = await fetch('/admin/calendar/ajax/approve-event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ eventId: eventId, action: 'reject' })
                });
                
                const result = await response.json();
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('Network error occurred');
            }
        }
        
        function viewEvent(eventId) {
            window.open('/admin/calendar/events.php?view=' + eventId, '_blank');
        }
        
        async function testSource(sourceId) {
            try {
                const response = await fetch('/admin/calendar/ajax/test-source.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sourceId: sourceId })
                });
                
                const result = await response.json();
                if (result.success) {
                    alert('Source test successful: ' + result.message);
                } else {
                    alert('Source test failed: ' + result.error);
                }
            } catch (error) {
                alert('Network error occurred');
            }
        }
    </script>
</body>
</html>

<?php

function getDatabaseConnection() {
    // This should match your existing CMS database configuration
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'yakima_finds';
    $username = $_ENV['DB_USER'] ?? 'root';
    $password = $_ENV['DB_PASS'] ?? '';
    
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    
    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        
        return $pdo;
        
    } catch (PDOException $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
}

?>