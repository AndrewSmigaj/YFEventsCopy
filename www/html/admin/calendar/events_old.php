<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../config/database.php';

use YakimaFinds\Models\EventModel;

$eventModel = new EventModel($db);

// Handle filters
$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query
$whereConditions = [];
$params = [];

if ($status !== 'all') {
    $whereConditions[] = 'status = ?';
    $params[] = $status;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "SELECT COUNT(*) FROM events $whereClause";
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$totalEvents = $stmt->fetchColumn();
$totalPages = ceil($totalEvents / $perPage);

// Get events
$query = "SELECT e.*, c.name as category_name, cs.name as source_name 
          FROM events e 
          LEFT JOIN event_categories c ON e.category_id = c.id
          LEFT JOIN calendar_sources cs ON e.source_id = cs.id
          $whereClause 
          ORDER BY e.created_at DESC 
          LIMIT $perPage OFFSET $offset";

$stmt = $db->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - Advanced Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin.css">
    <style>
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px;
        }
        .admin-content {
            flex: 1;
            padding: 20px;
            background: #f5f5f5;
        }
        .filters {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .event-table {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status-pending { background: #ffc107; color: #000; }
        .status-approved { background: #28a745; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .actions button {
            padding: 6px 12px;
            margin-right: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-edit { background: #007bff; color: white; }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            padding: 8px 12px;
            margin: 0 5px;
            background: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination .active {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-sidebar">
            <h2>Advanced Admin</h2>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/calendar/" style="color: white; text-decoration: none;">
                            <i class="fas fa-dashboard"></i> Dashboard
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/calendar/events.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-calendar"></i> Events
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/calendar/sources.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-rss"></i> Sources
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/calendar/shops.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-store"></i> Shops
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/" style="color: white; text-decoration: none;">
                            <i class="fas fa-arrow-left"></i> Back to Main
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        
        <div class="admin-content">
            <h1>Event Management</h1>
            
            <div class="filters">
                <form method="get" style="display: flex; gap: 20px; align-items: center;">
                    <div>
                        <label for="status">Status:</label>
                        <select name="status" id="status" onchange="this.form.submit()">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div>
                        Showing <?= count($events) ?> of <?= $totalEvents ?> events
                    </div>
                </form>
            </div>
            
            <div class="event-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Date/Time</th>
                            <th>Location</th>
                            <th>Category</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                        <tr data-event-id="<?= $event['id'] ?>">
                            <td><?= htmlspecialchars($event['id']) ?></td>
                            <td><?= htmlspecialchars($event['title']) ?></td>
                            <td>
                                <?= date('M j, Y g:i A', strtotime($event['start_datetime'])) ?>
                                <?php if ($event['end_datetime']): ?>
                                    <br>to <?= date('M j, Y g:i A', strtotime($event['end_datetime'])) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($event['location']) ?></td>
                            <td><?= htmlspecialchars($event['category_name'] ?? 'Uncategorized') ?></td>
                            <td><?= htmlspecialchars($event['source_name'] ?? 'Manual') ?></td>
                            <td>
                                <span class="status-badge status-<?= $event['status'] ?>">
                                    <?= ucfirst($event['status']) ?>
                                </span>
                            </td>
                            <td class="actions">
                                <?php if ($event['status'] === 'pending'): ?>
                                    <button class="btn-approve" onclick="approveEvent(<?= $event['id'] ?>)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn-reject" onclick="rejectEvent(<?= $event['id'] ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                <?php endif; ?>
                                <button class="btn-edit" onclick="editEvent(<?= $event['id'] ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?status=<?= $status ?>&page=<?= $i ?>" 
                       class="<?= $i === $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function approveEvent(eventId) {
            if (!confirm('Approve this event?')) return;
            
            updateEventStatus(eventId, 'approve');
        }
        
        function rejectEvent(eventId) {
            if (!confirm('Reject this event?')) return;
            
            updateEventStatus(eventId, 'reject');
        }
        
        function updateEventStatus(eventId, action) {
            fetch('/admin/calendar/ajax/approve-event.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `event_id=${eventId}&action=${action}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                alert('Failed to update event status');
                console.error(err);
            });
        }
        
        function editEvent(eventId) {
            // Redirect to main admin event edit page
            window.location.href = `/admin/events.php?action=edit&id=${eventId}`;
        }
    </script>
</body>
</html>