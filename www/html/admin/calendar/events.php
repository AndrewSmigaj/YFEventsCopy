<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';

// Get events directly without models
$status = $_GET['status'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$whereClause = '';
$params = [];

if ($status !== 'all') {
    $whereClause = 'WHERE status = ?';
    $params[] = $status;
}

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
    <title>Event Management - YFEvents Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .filters { background: white; padding: 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .event-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-pending { background: #ffc107; color: #000; }
        .status-approved { background: #28a745; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .actions button { padding: 6px 12px; margin-right: 5px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-edit { background: #007bff; color: white; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { padding: 8px 12px; margin: 0 5px; background: white; text-decoration: none; border-radius: 4px; }
        .pagination .active { background: #007bff; color: white; }
        .back-link { color: #007bff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="/admin/calendar/" class="back-link">← Back to Advanced Admin</a>
            <h1>Event Management</h1>
        </div>
        
        <div class="filters">
            <form method="get" style="display: flex; gap: 20px; align-items: center;">
                <label>Status: 
                    <select name="status" onchange="this.form.submit()">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </label>
                <span>Showing <?= count($events) ?> of <?= $totalEvents ?> events</span>
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
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= $event['id'] ?></td>
                        <td><?= htmlspecialchars($event['title']) ?></td>
                        <td><?= date('M j, Y g:i A', strtotime($event['start_datetime'])) ?></td>
                        <td><?= htmlspecialchars($event['location'] ?? '') ?></td>
                        <td><span class="status-badge status-<?= $event['status'] ?>"><?= ucfirst($event['status']) ?></span></td>
                        <td class="actions">
                            <?php if ($event['status'] === 'pending'): ?>
                                <button class="btn-approve" onclick="updateStatus(<?= $event['id'] ?>, 'approve')">Approve</button>
                                <button class="btn-reject" onclick="updateStatus(<?= $event['id'] ?>, 'reject')">Reject</button>
                            <?php endif; ?>
                            <button class="btn-edit" onclick="window.location.href='/admin/events.php?action=edit&id=<?= $event['id'] ?>'">Edit</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?status=<?= $status ?>&page=<?= $i ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    function updateStatus(eventId, action) {
        if (!confirm('Are you sure?')) return;
        
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
        });
    }
    </script>
</body>
</html>