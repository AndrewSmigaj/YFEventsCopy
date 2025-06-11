<?php
// Event Management Interface
session_start();

// Check if logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once '../../../config/database.php';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE events SET status = 'approved' WHERE id = ?");
                $stmt->execute([$_POST['event_id']]);
                $message = "Event approved!";
                break;
                
            case 'reject':
                $stmt = $pdo->prepare("UPDATE events SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$_POST['event_id']]);
                $message = "Event rejected!";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                $stmt->execute([$_POST['event_id']]);
                $message = "Event deleted!";
                break;
                
            case 'update':
                try {
                    // Parse datetime
                    $startDatetime = $_POST['start_date'] . ' ' . $_POST['start_time'];
                    $endDatetime = !empty($_POST['end_date']) ? $_POST['end_date'] . ' ' . $_POST['end_time'] : null;
                    
                    $stmt = $pdo->prepare("
                        UPDATE events SET 
                            title = ?,
                            description = ?,
                            start_datetime = ?,
                            end_datetime = ?,
                            location = ?,
                            address = ?,
                            latitude = ?,
                            longitude = ?,
                            external_url = ?,
                            status = ?
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        $startDatetime,
                        $endDatetime,
                        $_POST['location'],
                        $_POST['address'],
                        $_POST['latitude'] ?: null,
                        $_POST['longitude'] ?: null,
                        $_POST['external_url'],
                        $_POST['status'],
                        $_POST['event_id']
                    ]);
                    
                    $message = "Event updated successfully!";
                } catch (Exception $e) {
                    $error = "Error updating event: " . $e->getMessage();
                }
                break;
                
            case 'bulk_action':
                if (!empty($_POST['selected_events']) && !empty($_POST['bulk_action'])) {
                    $eventIds = $_POST['selected_events'];
                    $placeholders = str_repeat('?,', count($eventIds) - 1) . '?';
                    
                    switch ($_POST['bulk_action']) {
                        case 'approve':
                            $stmt = $pdo->prepare("UPDATE events SET status = 'approved' WHERE id IN ($placeholders)");
                            $stmt->execute($eventIds);
                            $message = count($eventIds) . " events approved!";
                            break;
                            
                        case 'delete':
                            $stmt = $pdo->prepare("DELETE FROM events WHERE id IN ($placeholders)");
                            $stmt->execute($eventIds);
                            $message = count($eventIds) . " events deleted!";
                            break;
                    }
                }
                break;
        }
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if ($filter !== 'all') {
    $where[] = 'status = ?';
    $params[] = $filter;
}

if (!empty($search)) {
    $where[] = '(title LIKE ? OR description LIKE ? OR location LIKE ?)';
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count
$countQuery = "SELECT COUNT(*) FROM events $whereClause";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalEvents = $stmt->fetchColumn();
$totalPages = ceil($totalEvents / $perPage);

// Get events
$query = "SELECT * FROM events $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .event-row:hover {
            background-color: #f8f9fa;
        }
        .event-description {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .status-badge {
            font-size: 0.875rem;
        }
        .btn-group-xs .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .edit-form {
            display: none;
            background: #f8f9fa;
            padding: 1rem;
            margin-top: 0.5rem;
            border-radius: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-calendar-alt"></i> Event Management</h1>
                    <a href="./" class="btn btn-secondary">← Back to Dashboard</a>
                </div>
                
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Status Filter</label>
                                <select name="filter" class="form-select" onchange="this.form.submit()">
                                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Events</option>
                                    <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="approved" <?= $filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                                    <option value="rejected" <?= $filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Search</label>
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" 
                                           placeholder="Search events..." value="<?= htmlspecialchars($search) ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <a href="events.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear Filters
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Bulk Actions -->
                <form method="post" id="bulkForm">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Events (<?= $totalEvents ?> total)</h5>
                                <div class="d-flex gap-2">
                                    <select name="bulk_action" class="form-select form-select-sm" style="width: auto;">
                                        <option value="">Bulk Actions</option>
                                        <option value="approve">Approve Selected</option>
                                        <option value="delete">Delete Selected</option>
                                    </select>
                                    <button type="submit" name="action" value="bulk_action" class="btn btn-sm btn-primary">
                                        Apply
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th width="30">
                                                <input type="checkbox" class="form-check-input" id="selectAll">
                                            </th>
                                            <th>Status</th>
                                            <th>Title</th>
                                            <th>Date/Time</th>
                                            <th>Location</th>
                                            <th>Source</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($events as $event): ?>
                                        <tr class="event-row">
                                            <td>
                                                <input type="checkbox" class="form-check-input event-checkbox" 
                                                       name="selected_events[]" value="<?= $event['id'] ?>">
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'pending' => 'warning',
                                                    'approved' => 'success',
                                                    'rejected' => 'danger'
                                                ][$event['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?> status-badge">
                                                    <?= ucfirst($event['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($event['title']) ?></strong>
                                                <?php if ($event['description']): ?>
                                                    <div class="event-description text-muted small">
                                                        <?= htmlspecialchars($event['description']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= date('M d, Y', strtotime($event['start_datetime'])) ?><br>
                                                <small class="text-muted">
                                                    <?= date('g:i A', strtotime($event['start_datetime'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($event['location']) ?><br>
                                                <?php if ($event['address']): ?>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($event['address']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php if ($event['source_id']): ?>
                                                        Source #<?= $event['source_id'] ?>
                                                    <?php else: ?>
                                                        Manual
                                                    <?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <small><?= date('M d', strtotime($event['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-xs">
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            onclick="toggleEditForm(<?= $event['id'] ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <?php if ($event['status'] === 'pending'): ?>
                                                        <button type="submit" name="action" value="approve" 
                                                                class="btn btn-sm btn-success"
                                                                form="quick-action-<?= $event['id'] ?>">
                                                            <i class="fas fa-check"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="submit" name="action" value="delete" 
                                                            class="btn btn-sm btn-danger"
                                                            form="quick-action-<?= $event['id'] ?>"
                                                            onclick="return confirm('Delete this event?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="8" class="p-0">
                                                <div id="edit-form-<?= $event['id'] ?>" class="edit-form">
                                                    <form method="post">
                                                        <input type="hidden" name="action" value="update">
                                                        <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                                        
                                                        <div class="row g-3">
                                                            <div class="col-md-8">
                                                                <label class="form-label">Title *</label>
                                                                <input type="text" name="title" class="form-control" 
                                                                       value="<?= htmlspecialchars($event['title']) ?>" required>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Status</label>
                                                                <select name="status" class="form-select">
                                                                    <option value="pending" <?= $event['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                                    <option value="approved" <?= $event['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                                                    <option value="rejected" <?= $event['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label class="form-label">Description</label>
                                                                <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($event['description']) ?></textarea>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label">Start Date *</label>
                                                                <input type="date" name="start_date" class="form-control" 
                                                                       value="<?= date('Y-m-d', strtotime($event['start_datetime'])) ?>" required>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label">Start Time *</label>
                                                                <input type="time" name="start_time" class="form-control" 
                                                                       value="<?= date('H:i', strtotime($event['start_datetime'])) ?>" required>
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label">End Date</label>
                                                                <input type="date" name="end_date" class="form-control" 
                                                                       value="<?= $event['end_datetime'] ? date('Y-m-d', strtotime($event['end_datetime'])) : '' ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label">End Time</label>
                                                                <input type="time" name="end_time" class="form-control" 
                                                                       value="<?= $event['end_datetime'] ? date('H:i', strtotime($event['end_datetime'])) : '' ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Location *</label>
                                                                <input type="text" name="location" class="form-control" 
                                                                       value="<?= htmlspecialchars($event['location']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Address</label>
                                                                <input type="text" name="address" class="form-control" 
                                                                       value="<?= htmlspecialchars($event['address']) ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label">Latitude</label>
                                                                <input type="number" name="latitude" class="form-control" 
                                                                       step="0.000001" value="<?= $event['latitude'] ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label class="form-label">Longitude</label>
                                                                <input type="number" name="longitude" class="form-control" 
                                                                       step="0.000001" value="<?= $event['longitude'] ?>">
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">External URL</label>
                                                                <input type="url" name="external_url" class="form-control" 
                                                                       value="<?= htmlspecialchars($event['external_url']) ?>">
                                                            </div>
                                                            <div class="col-12">
                                                                <button type="submit" class="btn btn-primary">
                                                                    <i class="fas fa-save"></i> Save Changes
                                                                </button>
                                                                <button type="button" class="btn btn-secondary" 
                                                                        onclick="toggleEditForm(<?= $event['id'] ?>)">
                                                                    Cancel
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <form id="quick-action-<?= $event['id'] ?>" method="post" style="display: none;">
                                            <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                        </form>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php if ($totalPages > 1): ?>
                        <div class="card-footer">
                            <nav>
                                <ul class="pagination mb-0">
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?page=<?= $i ?>&filter=<?= $filter ?>&search=<?= urlencode($search) ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Select all checkbox
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.event-checkbox');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
        
        // Toggle edit form
        function toggleEditForm(eventId) {
            const form = document.getElementById('edit-form-' + eventId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>