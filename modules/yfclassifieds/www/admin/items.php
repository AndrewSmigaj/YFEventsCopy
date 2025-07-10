<?php
/**
 * YF Classifieds - Items List
 * 
 * Filterable list of all classified items
 */

require_once __DIR__ . '/../../../../www/html/refactor/admin/auth_check.php';
require_once __DIR__ . '/../../../../config/database.php';

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$sort = $_GET['sort'] ?? 'newest';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build WHERE clause
$where = ["listing_type = 'classified'"];
$params = [];

if ($status !== 'all') {
    $where[] = "status = :status";
    $params[':status'] = $status;
}

if ($search) {
    $where[] = "(title LIKE :search OR description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$whereClause = implode(' AND ', $where);

// Sort options
$orderBy = match($sort) {
    'title' => 'title ASC',
    'price_low' => 'COALESCE(price, starting_price) ASC',
    'price_high' => 'COALESCE(price, starting_price) DESC',
    'views' => 'views DESC',
    'oldest' => 'created_at ASC',
    default => 'created_at DESC'
};

// Get total count
$countSql = "SELECT COUNT(*) FROM yfc_items WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Get items
$sql = "SELECT i.*, c.name as category_name,
        COALESCE(i.price, i.starting_price) as display_price,
        (SELECT COUNT(*) FROM yfc_item_photos WHERE item_id = i.id) as photo_count,
        (SELECT photo_url FROM yfc_item_photos WHERE item_id = i.id AND is_primary = TRUE LIMIT 1) as primary_photo
        FROM yfc_items i
        LEFT JOIN yfc_categories c ON i.category_id = c.id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get status counts for filter badges
$statusCounts = [];
$statusSql = "SELECT status, COUNT(*) as count FROM yfc_items WHERE listing_type = 'classified' GROUP BY status";
$statusStmt = $pdo->query($statusSql);
while ($row = $statusStmt->fetch(PDO::FETCH_ASSOC)) {
    $statusCounts[$row['status']] = $row['count'];
}
$statusCounts['all'] = array_sum($statusCounts);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Items List - YF Classifieds Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .status-pills {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        .status-pill {
            padding: 5px 15px;
            border-radius: 20px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.2s;
            border: 1px solid #dee2e6;
        }
        
        .status-pill.active {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        
        .status-pill:not(.active) {
            background: #f8f9fa;
            color: #495057;
        }
        
        .status-pill:hover {
            background: #e9ecef;
            color: #495057;
        }
        
        .status-pill.active:hover {
            background: #0b5ed7;
            color: white;
        }
        
        .item-row {
            transition: all 0.2s;
        }
        
        .item-row:hover {
            background: #f8f9fa;
        }
        
        .item-thumb {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .no-photo {
            width: 60px;
            height: 60px;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #6c757d;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .badge-status {
            font-size: 0.8rem;
            padding: 4px 8px;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1>📋 Items List</h1>
                    <p class="mb-0">Manage all classified items</p>
                </div>
                <a href="simple-index.php" class="btn btn-light">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Filters -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-8">
                    <form method="get" class="d-flex gap-2 mb-3">
                        <input type="search" name="search" class="form-control" 
                               placeholder="Search items..." value="<?= htmlspecialchars($search) ?>">
                        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                        <?php if ($search): ?>
                            <a href="?status=<?= urlencode($status) ?>&sort=<?= urlencode($sort) ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x"></i> Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
                <div class="col-md-4">
                    <select name="sort" class="form-select" onchange="updateSort(this.value)">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title A-Z</option>
                        <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>Most Viewed</option>
                    </select>
                </div>
            </div>
            
            <!-- Status Filter Pills -->
            <div class="status-pills">
                <a href="?status=all&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>" 
                   class="status-pill <?= $status === 'all' ? 'active' : '' ?>">
                    All Items (<?= $statusCounts['all'] ?? 0 ?>)
                </a>
                <a href="?status=active&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>" 
                   class="status-pill <?= $status === 'active' ? 'active' : '' ?>">
                    Active (<?= $statusCounts['active'] ?? 0 ?>)
                </a>
                <a href="?status=sold&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>" 
                   class="status-pill <?= $status === 'sold' ? 'active' : '' ?>">
                    Sold (<?= $statusCounts['sold'] ?? 0 ?>)
                </a>
                <a href="?status=pending&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>" 
                   class="status-pill <?= $status === 'pending' ? 'active' : '' ?>">
                    Pending (<?= $statusCounts['pending'] ?? 0 ?>)
                </a>
                <a href="?status=removed&sort=<?= urlencode($sort) ?>&search=<?= urlencode($search) ?>" 
                   class="status-pill <?= $status === 'removed' ? 'active' : '' ?>">
                    Removed (<?= $statusCounts['removed'] ?? 0 ?>)
                </a>
            </div>
        </div>
        
        <!-- Results Info -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0">
                Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalItems) ?> of <?= $totalItems ?> items
                <?php if ($search): ?>
                    for "<strong><?= htmlspecialchars($search) ?></strong>"
                <?php endif; ?>
            </p>
            <div>
                <a href="create.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus"></i> Add New Item
                </a>
            </div>
        </div>
        
        <!-- Items Table -->
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="80">Photo</th>
                            <th>Title</th>
                            <th width="100">Price</th>
                            <th width="120">Category</th>
                            <th width="80">Views</th>
                            <th width="100">Status</th>
                            <th width="120">Created</th>
                            <th width="140">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bi bi-inbox" style="font-size: 3rem; color: #dee2e6;"></i>
                                    <p class="mt-3 text-muted">
                                        <?php if ($search): ?>
                                            No items found matching "<?= htmlspecialchars($search) ?>"
                                        <?php else: ?>
                                            No items found. <a href="create.php">Add your first item</a>
                                        <?php endif; ?>
                                    </p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($items as $item): ?>
                                <tr class="item-row">
                                    <td>
                                        <?php if ($item['primary_photo']): ?>
                                            <img src="<?= htmlspecialchars($item['primary_photo']) ?>" 
                                                 alt="<?= htmlspecialchars($item['title']) ?>" 
                                                 class="item-thumb">
                                        <?php else: ?>
                                            <div class="no-photo">
                                                <i class="bi bi-image"></i>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($item['photo_count'] > 1): ?>
                                            <small class="text-muted d-block"><?= $item['photo_count'] ?> photos</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="../item.php?id=<?= $item['id'] ?>" target="_blank" class="fw-bold text-decoration-none">
                                            <?= htmlspecialchars($item['title']) ?>
                                        </a>
                                        <br>
                                        <small class="text-muted">ID: <?= $item['id'] ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success">
                                            $<?= number_format($item['display_price'], 2) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['category_name']): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($item['category_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <i class="bi bi-eye"></i> <?= number_format($item['views'] ?? 0) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $badgeClass = match($item['status']) {
                                            'active' => 'bg-success',
                                            'sold' => 'bg-warning',
                                            'removed' => 'bg-danger',
                                            default => 'bg-secondary'
                                        };
                                        ?>
                                        <span class="badge <?= $badgeClass ?> badge-status">
                                            <?= ucfirst($item['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted">
                                            <?= date('M j, Y', strtotime($item['created_at'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../item.php?id=<?= $item['id'] ?>" target="_blank" 
                                               class="btn btn-outline-primary" title="View Item">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?= $item['id'] ?>" 
                                               class="btn btn-outline-secondary" title="Edit Item">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button class="btn btn-outline-info" 
                                                    onclick="managePhotos(<?= $item['id'] ?>)" title="Manage Photos">
                                                <i class="bi bi-images"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" 
                                                    onclick="confirmDelete(<?= $item['id'] ?>, '<?= addslashes($item['title']) ?>')" 
                                                    title="Delete Item">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter(['status' => $status !== 'all' ? $status : null, 'search' => $search, 'sort' => $sort !== 'newest' ? $sort : null])) ?>">
                            Previous
                        </a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter(['status' => $status !== 'all' ? $status : null, 'search' => $search, 'sort' => $sort !== 'newest' ? $sort : null])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter(['status' => $status !== 'all' ? $status : null, 'search' => $search, 'sort' => $sort !== 'newest' ? $sort : null])) ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateSort(sortValue) {
            const url = new URL(window.location);
            url.searchParams.set('sort', sortValue);
            url.searchParams.set('page', '1'); // Reset to first page
            window.location.href = url.toString();
        }
        
        function managePhotos(itemId) {
            window.location.href = `upload.php?item_id=${itemId}`;
        }
        
        function confirmDelete(itemId, title) {
            if (confirm(`Are you sure you want to delete "${title}"?\n\nThis action cannot be undone.`)) {
                fetch(`api/delete-item.php?id=${itemId}`, { method: 'DELETE' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error deleting item: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        alert('Error deleting item: ' + error.message);
                    });
            }
        }
    </script>
</body>
</html>