<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$timeFilter = $_GET['time_filter'] ?? 'upcoming'; // 'all', 'upcoming', 'past'
$sortBy = $_GET['sort'] ?? 'created_at';
$sortOrder = $_GET['order'] ?? 'desc';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$whereConditions = [];
$params = [];

if ($status !== 'all') {
    $whereConditions[] = 'e.status = ?';
    $params[] = $status;
}

// Time filter - by default hide past events
if ($timeFilter === 'upcoming') {
    $whereConditions[] = 'e.start_datetime >= NOW()';
} elseif ($timeFilter === 'past') {
    $whereConditions[] = 'e.start_datetime < NOW()';
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

// Validate sort parameters
$allowedSorts = ['id', 'title', 'start_datetime', 'location', 'status', 'created_at'];
$sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
$sortOrder = strtolower($sortOrder) === 'asc' ? 'ASC' : 'DESC';

// Get total count
$countQuery = "SELECT COUNT(*) FROM events e $whereClause";
$stmt = $db->prepare($countQuery);
$stmt->execute($params);
$totalEvents = $stmt->fetchColumn();
$totalPages = ceil($totalEvents / $perPage);

// Get events
$query = "SELECT e.*, 
          GROUP_CONCAT(ec.name SEPARATOR ', ') as category_names,
          cs.name as source_name 
          FROM events e 
          LEFT JOIN event_category_relations ecr ON e.id = ecr.event_id
          LEFT JOIN event_categories ec ON ecr.category_id = ec.id
          LEFT JOIN calendar_sources cs ON e.source_id = cs.id
          $whereClause 
          GROUP BY e.id
          ORDER BY e.$sortBy $sortOrder 
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
        .filters form { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 5px; }
        .filter-group label { font-weight: bold; font-size: 14px; }
        .filter-group select, .filter-group input { padding: 6px 12px; border: 1px solid #ddd; border-radius: 4px; }
        .bulk-actions { background: white; padding: 15px 20px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); display: none; }
        .bulk-actions.show { display: block; }
        .bulk-actions button { padding: 8px 16px; margin-right: 10px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .event-table { background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; position: relative; }
        th.sortable { cursor: pointer; user-select: none; }
        th.sortable:hover { background: #e9ecef; }
        th.sortable::after { content: ' ↕'; opacity: 0.5; font-size: 12px; }
        th.sort-asc::after { content: ' ↑'; opacity: 1; color: #007bff; }
        th.sort-desc::after { content: ' ↓'; opacity: 1; color: #007bff; }
        .checkbox-cell { width: 40px; text-align: center; }
        .event-checkbox { cursor: pointer; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .status-pending { background: #ffc107; color: #000; }
        .status-approved { background: #28a745; color: white; }
        .status-rejected { background: #dc3545; color: white; }
        .actions button { padding: 6px 12px; margin-right: 5px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; }
        .btn-edit { background: #007bff; color: white; }
        .btn-bulk-approve { background: #28a745; color: white; }
        .btn-bulk-reject { background: #dc3545; color: white; }
        .btn-bulk-delete { background: #6c757d; color: white; }
        .pagination { margin-top: 20px; text-align: center; }
        .pagination a { padding: 8px 12px; margin: 0 5px; background: white; text-decoration: none; border-radius: 4px; }
        .pagination .active { background: #007bff; color: white; }
        .back-link { color: #007bff; text-decoration: none; }
        .event-row.past-event { opacity: 0.6; }
        .selected-count { font-weight: bold; color: #007bff; }
        .edit-form-container { margin: 10px 0; }
        .edit-form-container h4 { margin-bottom: 15px; color: #333; }
        .edit-form-container label { color: #555; }
        .edit-form-container input[type="text"],
        .edit-form-container input[type="datetime-local"],
        .edit-form-container select,
        .edit-form-container textarea { font-size: 14px; }
        .edit-form-container button { transition: opacity 0.2s; }
        .edit-form-container button:hover { opacity: 0.8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="/admin/calendar/" class="back-link">← Back to Advanced Admin</a>
            <h1>Event Management</h1>
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
                    <span><strong><?= count($events) ?></strong> of <strong><?= $totalEvents ?></strong> events</span>
                </div>
                
                <!-- Hidden inputs to preserve sort state -->
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sortBy) ?>">
                <input type="hidden" name="order" value="<?= htmlspecialchars($sortOrder) ?>">
            </form>
        </div>
        
        <div class="bulk-actions" id="bulk-actions">
            <span class="selected-count" id="selected-count">0 events selected</span>
            <button class="btn-bulk-approve" onclick="bulkAction('approve')">
                <i class="fas fa-check"></i> Bulk Approve
            </button>
            <button class="btn-bulk-reject" onclick="bulkAction('reject')">
                <i class="fas fa-times"></i> Bulk Reject
            </button>
            <button class="btn-bulk-delete" onclick="bulkAction('delete')">
                <i class="fas fa-trash"></i> Bulk Delete
            </button>
        </div>
        
        <div class="event-table">
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
                        <th>Source</th>
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
                        <td><?= date('M j, Y g:i A', strtotime($event['start_datetime'])) ?></td>
                        <td><?= htmlspecialchars($event['location'] ?? '') ?></td>
                        <td><span class="status-badge status-<?= $event['status'] ?>"><?= ucfirst($event['status']) ?></span></td>
                        <td><?= htmlspecialchars($event['source_name'] ?? 'Manual') ?></td>
                        <td class="actions">
                            <?php if ($event['status'] === 'pending'): ?>
                                <button class="btn-approve" onclick="updateStatus(<?= $event['id'] ?>, 'approve')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn-reject" onclick="updateStatus(<?= $event['id'] ?>, 'reject')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            <?php endif; ?>
                            <button class="btn-edit" onclick="toggleEditForm(<?= $event['id'] ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                        </td>
                    </tr>
                    <tr id="edit-form-<?= $event['id'] ?>" style="display: none;">
                        <td colspan="8">
                            <div class="edit-form-container" style="background: #f8f9fa; padding: 20px; border-radius: 8px;">
                                <h4>Edit Event</h4>
                                <form method="post" action="/admin/calendar/ajax/update-event.php" onsubmit="return updateEvent(event, <?= $event['id'] ?>)">
                                    <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Title *</label>
                                            <input type="text" name="title" value="<?= htmlspecialchars($event['title']) ?>" required
                                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Status</label>
                                            <select name="status" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                                <option value="pending" <?= $event['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="approved" <?= $event['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                                <option value="rejected" <?= $event['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Start Date/Time *</label>
                                            <input type="datetime-local" name="start_datetime" 
                                                   value="<?= date('Y-m-d\TH:i', strtotime($event['start_datetime'])) ?>" required
                                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div>
                                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">End Date/Time</label>
                                            <input type="datetime-local" name="end_datetime" 
                                                   value="<?= $event['end_datetime'] ? date('Y-m-d\TH:i', strtotime($event['end_datetime'])) : '' ?>"
                                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div style="grid-column: 1 / -1;">
                                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Location</label>
                                            <input type="text" name="location" value="<?= htmlspecialchars($event['location'] ?? '') ?>"
                                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        </div>
                                        
                                        <div style="grid-column: 1 / -1;">
                                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">Description</label>
                                            <textarea name="description" rows="4"
                                                      style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"><?= htmlspecialchars($event['description'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <button type="submit" style="background: #28a745; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" onclick="toggleEditForm(<?= $event['id'] ?>)" 
                                                style="background: #6c757d; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?status=<?= $status ?>&time_filter=<?= $timeFilter ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>&page=<?= $i ?>" 
                   class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    // Single event status update
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
        currentParams.set('page', '1'); // Reset to first page
        
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
        
        const actionText = action === 'approve' ? 'approve' : 
                          action === 'reject' ? 'reject' : 'delete';
        
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
                location.reload(); // Reload to reset UI state
            }
        })
        .catch(error => {
            alert('Network error occurred. Please try again.');
            location.reload();
        });
    }
    
    // Toggle edit form visibility
    function toggleEditForm(eventId) {
        const editRow = document.getElementById('edit-form-' + eventId);
        if (editRow.style.display === 'none') {
            // Hide all other edit forms first
            document.querySelectorAll('[id^="edit-form-"]').forEach(row => {
                row.style.display = 'none';
            });
            editRow.style.display = 'table-row';
        } else {
            editRow.style.display = 'none';
        }
    }
    
    // Update event via AJAX
    function updateEvent(e, eventId) {
        e.preventDefault();
        
        const form = e.target;
        const formData = new FormData(form);
        
        fetch('/admin/calendar/ajax/update-event.php', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('Network error occurred. Please try again.');
        });
        
        return false;
    }
    
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        updateBulkActions(); // Set initial state
    });
    </script>
</body>
</html>