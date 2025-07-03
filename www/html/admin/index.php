<?php
// Simple Admin Dashboard
require_once __DIR__ . '/error_handler.php';
require_once dirname(__DIR__, 3) . '/config/db_connection.php';

// Authentication check
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$isAdmin = true;

// Handle actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_event':
                $stmt = $pdo->prepare("UPDATE events SET status = 'approved' WHERE id = ?");
                $stmt->execute([$_POST['event_id']]);
                $message = "Event approved!";
                break;
                
            case 'delete_event':
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                $stmt->execute([$_POST['event_id']]);
                $message = "Event deleted!";
                break;
                
            case 'delete_shop':
                $stmt = $pdo->prepare("DELETE FROM local_shops WHERE id = ?");
                $stmt->execute([$_POST['shop_id']]);
                $message = "Shop deleted!";
                break;
        }
    }
}

// Get statistics
$stats = [];
$stats['total_events'] = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$stats['pending_events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'pending'")->fetchColumn();
$stats['approved_events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'approved'")->fetchColumn();
$stats['total_shops'] = $pdo->query("SELECT COUNT(*) FROM local_shops")->fetchColumn();

// Get YFClaim statistics
$stats['yfc_sales'] = 0;
$stats['yfc_items'] = 0;
$stats['yfc_offers'] = 0;

try {
    $stats['yfc_sales'] = $pdo->query("SELECT COUNT(*) FROM yfc_sales")->fetchColumn();
    $stats['yfc_items'] = $pdo->query("SELECT COUNT(*) FROM yfc_items")->fetchColumn();
    $stats['yfc_offers'] = $pdo->query("SELECT COUNT(*) FROM yfc_offers")->fetchColumn();
} catch (Exception $e) {
    // YFClaim tables might not exist yet
}

// Get filter parameters  
$status = $_GET['status'] ?? 'all';
$timeFilter = $_GET['time_filter'] ?? 'upcoming'; // Default to upcoming events
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'desc';
$limit = 20; // Keep it at 20 for dashboard

// Build WHERE clause for events
$whereConditions = [];
$params = [];

if ($status !== 'all') {
    $whereConditions[] = 'status = ?';
    $params[] = $status;
}

// Time filter - by default hide past events
if ($timeFilter === 'upcoming') {
    $whereConditions[] = 'start_datetime >= NOW()';
} elseif ($timeFilter === 'past') {
    $whereConditions[] = 'start_datetime < NOW()';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Validate sort parameters
$allowedSorts = ['id', 'title', 'start_datetime', 'location', 'status', 'created_at'];
$sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
$sortOrder = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';

// Get events with filters
$query = "SELECT * FROM events $whereClause ORDER BY $sortBy $sortOrder LIMIT $limit";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Get shops
$shops = $pdo->query("SELECT * FROM local_shops ORDER BY created_at DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents Admin Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        .header {
            background: #333;
            color: white;
            padding: 1rem 2rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .section {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        .status {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.875rem;
        }
        .status.pending {
            background: #ffc107;
            color: #000;
        }
        .status.approved {
            background: #28a745;
            color: white;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        button {
            padding: 0.25rem 0.75rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .btn-approve {
            background: #28a745;
            color: white;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .nav {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .nav a {
            color: #007bff;
            text-decoration: none;
        }
        .nav a:hover {
            text-decoration: underline;
        }
        
        /* Sortable table enhancements */
        th.sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
        }
        th.sortable:hover {
            background: #e9ecef;
        }
        th.sortable::after {
            content: ' ↕';
            opacity: 0.5;
            font-size: 12px;
        }
        th.sort-asc::after {
            content: ' ↑';
            opacity: 1;
            color: #007bff;
        }
        th.sort-desc::after {
            content: ' ↓';
            opacity: 1;
            color: #007bff;
        }
        
        /* Filters */
        .filters {
            background: white;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .filters form {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-weight: bold;
            font-size: 14px;
        }
        .filter-group select {
            padding: 6px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        /* Bulk actions */
        .bulk-actions {
            background: white;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            display: none;
        }
        .bulk-actions.show {
            display: block;
        }
        .bulk-actions button {
            padding: 8px 16px;
            margin-right: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-bulk-approve {
            background: #28a745;
            color: white;
        }
        .btn-bulk-reject {
            background: #dc3545;
            color: white;
        }
        .checkbox-cell {
            width: 40px;
            text-align: center;
        }
        .event-checkbox {
            cursor: pointer;
        }
        .event-row.past-event {
            opacity: 0.6;
        }
        .selected-count {
            font-weight: bold;
            color: #007bff;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>YFEvents Admin Dashboard</h1>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="nav">
            <a href="../">← Back to Calendar</a>
            <a href="calendar/">Advanced Admin</a>
            <a href="../modules/yfclaim/www/admin/">YFClaim Sales</a>
            <a href="scrapers.php">Manage Scrapers</a>
            <a href="intelligent-scraper.php">AI Scraper</a>
            <a href="validate-urls.php">URL Validator</a>
            <a href="events.php">Manage Events</a>
            <a href="shops.php">Manage Shops</a>
            <a href="geocode-fix.php">Fix Geocoding</a>
            <a href="logout.php" style="float: right; color: #dc3545;">Logout</a>
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_events'] ?></div>
                <div>Total Events</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['pending_events'] ?></div>
                <div>Pending Approval</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['approved_events'] ?></div>
                <div>Approved Events</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_shops'] ?></div>
                <div>Local Shops</div>
            </div>
        </div>
        
        <div class="stats" style="margin-top: 2rem;">
            <h3 style="margin-bottom: 1rem; color: #333;">YFClaim Estate Sales</h3>
            <div style="display: flex; gap: 2rem;">
                <div class="stat-box">
                    <div class="stat-number"><?= $stats['yfc_sales'] ?></div>
                    <div>Estate Sales</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?= $stats['yfc_items'] ?></div>
                    <div>Items Listed</div>
                </div>
                <div class="stat-box">
                    <div class="stat-number"><?= $stats['yfc_offers'] ?></div>
                    <div>Offers Made</div>
                </div>
                <div class="stat-box">
                    <a href="../modules/yfclaim/www/admin/" style="text-decoration: none; color: inherit;">
                        <div class="stat-number">→</div>
                        <div>Manage Sales</div>
                    </a>
                </div>
            </div>
        </div>
        
        <div class="filters">
            <form method="get" id="filter-form">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status" onchange="this.form.submit()">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Time Period</label>
                    <select name="time_filter" onchange="this.form.submit()">
                        <option value="upcoming" <?= $timeFilter === 'upcoming' ? 'selected' : '' ?>>Upcoming Events</option>
                        <option value="all" <?= $timeFilter === 'all' ? 'selected' : '' ?>>All Time</option>
                        <option value="past" <?= $timeFilter === 'past' ? 'selected' : '' ?>>Past Events</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <span><strong><?= count($events) ?></strong> events shown (dashboard limit: <?= $limit ?>)</span>
                </div>
                
                <!-- Hidden inputs to preserve sort state -->
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                <input type="hidden" name="order" value="<?= htmlspecialchars($sortOrder) ?>">
            </form>
        </div>
        
        <div class="bulk-actions" id="bulk-actions">
            <span class="selected-count" id="selected-count">0 events selected</span>
            <button class="btn-bulk-approve" onclick="bulkAction('approve')">
                Bulk Approve
            </button>
            <button class="btn-bulk-reject" onclick="bulkAction('reject')">
                Bulk Reject
            </button>
        </div>

        <div class="section" id="events">
            <h2>Recent Events 
                <a href="calendar/events.php" style="font-size: 14px; float: right;">View All →</a>
            </h2>
            <table>
                <thead>
                    <tr>
                        <th class="checkbox-cell">
                            <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)">
                        </th>
                        <th class="sortable <?= $sortBy === 'id' ? 'sort-' . strtolower($sortOrder) : '' ?>" onclick="sortTable('id')">ID</th>
                        <th class="sortable <?= $sortBy === 'title' ? 'sort-' . strtolower($sortOrder) : '' ?>" onclick="sortTable('title')">Title</th>
                        <th class="sortable <?= $sortBy === 'start_datetime' ? 'sort-' . strtolower($sortOrder) : '' ?>" onclick="sortTable('start_datetime')">Date/Time</th>
                        <th class="sortable <?= $sortBy === 'location' ? 'sort-' . strtolower($sortOrder) : '' ?>" onclick="sortTable('location')">Location</th>
                        <th class="sortable <?= $sortBy === 'status' ? 'sort-' . strtolower($sortOrder) : '' ?>" onclick="sortTable('status')">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                    <?php 
                        $isPastEvent = strtotime($event['start_datetime']) < time();
                        $rowClass = $isPastEvent ? 'event-row past-event' : 'event-row';
                    ?>
                    <tr class="<?= $rowClass ?>" data-event-id="<?= $event['id'] ?>">
                        <td class="checkbox-cell">
                            <input type="checkbox" class="event-checkbox" value="<?= $event['id'] ?>" onchange="updateBulkActions()">
                        </td>
                        <td><?= $event['id'] ?></td>
                        <td>
                            <?= htmlspecialchars($event['title']) ?>
                            <?php if ($isPastEvent): ?>
                                <small style="color: #666;"> (Past)</small>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M d, Y g:i A', strtotime($event['start_datetime'])) ?></td>
                        <td><?= htmlspecialchars($event['location']) ?></td>
                        <td>
                            <span class="status <?= $event['status'] ?>">
                                <?= ucfirst($event['status']) ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" class="actions" style="display: inline;">
                                <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                <?php if ($event['status'] === 'pending'): ?>
                                    <button type="submit" name="action" value="approve_event" class="btn-approve">Approve</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="delete_event" class="btn-delete" 
                                        onclick="return confirm('Delete this event?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section" id="shops">
            <h2>Local Shops</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shops as $shop): ?>
                    <tr>
                        <td><?= $shop['id'] ?></td>
                        <td><?= htmlspecialchars($shop['name']) ?></td>
                        <td><?= htmlspecialchars($shop['address']) ?></td>
                        <td><?= htmlspecialchars($shop['phone'] ?? 'N/A') ?></td>
                        <td>
                            <span class="status approved">Active</span>
                        </td>
                        <td>
                            <form method="post" class="actions" style="display: inline;">
                                <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                                <button type="submit" name="action" value="delete_shop" class="btn-delete" 
                                        onclick="return confirm('Delete this shop?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    // Sort table by column
    function sortTable(column) {
        const currentSort = '<?= $sortBy ?>';
        const currentOrder = '<?= $sortOrder ?>';
        
        let newOrder = 'asc';
        if (currentSort === column && currentOrder === 'asc') {
            newOrder = 'desc';
        }
        
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('sort', column);
        currentParams.set('order', newOrder);
        
        window.location.href = '?' + currentParams.toString();
    }
    
    // Toggle select all checkboxes
    function toggleSelectAll(checkbox) {
        const eventCheckboxes = document.querySelectorAll('.event-checkbox');
        eventCheckboxes.forEach(cb => {
            cb.checked = checkbox.checked;
        });
        updateBulkActions();
    }
    
    // Update bulk actions visibility and count
    function updateBulkActions() {
        const checkedBoxes = document.querySelectorAll('.event-checkbox:checked');
        const bulkActions = document.getElementById('bulk-actions');
        const selectedCount = document.getElementById('selected-count');
        const selectAllBox = document.getElementById('select-all');
        
        const count = checkedBoxes.length;
        selectedCount.textContent = `${count} event${count !== 1 ? 's' : ''} selected`;
        
        if (count > 0) {
            bulkActions.classList.add('show');
        } else {
            bulkActions.classList.remove('show');
        }
        
        // Update select all checkbox state
        const allCheckboxes = document.querySelectorAll('.event-checkbox');
        if (count === 0) {
            selectAllBox.indeterminate = false;
            selectAllBox.checked = false;
        } else if (count === allCheckboxes.length) {
            selectAllBox.indeterminate = false;
            selectAllBox.checked = true;
        } else {
            selectAllBox.indeterminate = true;
        }
    }
    
    // Bulk action handler
    function bulkAction(action) {
        const checkedBoxes = document.querySelectorAll('.event-checkbox:checked');
        const eventIds = Array.from(checkedBoxes).map(cb => cb.value);
        
        if (eventIds.length === 0) {
            alert('Please select at least one event.');
            return;
        }
        
        const actionText = action === 'approve' ? 'approve' : 'reject';
        
        if (!confirm(`Are you sure you want to ${actionText} ${eventIds.length} event(s)?`)) {
            return;
        }
        
        // Show loading state
        const bulkActions = document.getElementById('bulk-actions');
        bulkActions.innerHTML = '<span>Processing...</span>';
        
        fetch('/admin/calendar/ajax/bulk-approve-events.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                event_ids: eventIds,
                action: action
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
                location.reload();
            }
        })
        .catch(error => {
            alert('Network error occurred. Please try again.');
            location.reload();
        });
    }
    
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        updateBulkActions(); // Set initial state
    });
    </script>
</body>
</html>