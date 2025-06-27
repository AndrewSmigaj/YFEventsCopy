<?php
session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

// Check authentication
if (!isset($_SESSION['seller_id'])) {
    header('Location: /seller/login.php');
    exit;
}

$sellerId = $_SESSION['seller_id'];

// Get filter parameters
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

// Base condition - items from this seller's sales
$where[] = "s.seller_id = :seller_id";
$params[':seller_id'] = $sellerId;

// Apply filters
if ($filter === 'estate') {
    $where[] = "i.listing_type = 'estate_sale'";
} elseif ($filter === 'classified') {
    $where[] = "i.listing_type = 'classified'";
} elseif ($filter === 'active') {
    $where[] = "i.status = 'active'";
} elseif ($filter === 'sold') {
    $where[] = "i.status = 'sold'";
}

// Apply search
if ($search) {
    $where[] = "(i.title LIKE :search OR i.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$whereClause = implode(' AND ', $where);

// Get total count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM yfc_items i
    JOIN yfc_sales s ON i.sale_id = s.id
    WHERE $whereClause
");
$countStmt->execute($params);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Get items
$stmt = $pdo->prepare("
    SELECT i.*, 
           c.name as category_name,
           s.title as sale_name,
           (SELECT photo_url FROM yfc_item_photos WHERE item_id = i.id AND is_primary = 1 LIMIT 1) as primary_photo,
           (SELECT COUNT(*) FROM yfc_offers WHERE item_id = i.id) as offer_count
    FROM yfc_items i
    LEFT JOIN yfc_categories c ON i.category_id = c.id
    JOIN yfc_sales s ON i.sale_id = s.id
    WHERE $whereClause
    ORDER BY i.created_at DESC
    LIMIT :limit OFFSET :offset
");

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Items - YF Marketplace</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .item-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.2s;
        }
        .item-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .item-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
        }
        .item-placeholder {
            width: 100px;
            height: 100px;
            background: #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
            font-size: 2rem;
        }
        .listing-badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }
        .badge-estate {
            background: #e3f2fd;
            color: #1976d2;
        }
        .badge-classified {
            background: #f3e5f5;
            color: #7b1fa2;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            font-size: 0.75rem;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-sold {
            background: #f8d7da;
            color: #721c24;
        }
        .btn-primary {
            background: #667eea;
            border: none;
        }
        .btn-primary:hover {
            background: #764ba2;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="/seller/dashboard.php">
                <i class="bi bi-shop"></i> YF Marketplace - Seller Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/seller/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/seller/items.php">My Items</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/seller/logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-5">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-8">
                <h1>My Items</h1>
                <p class="text-muted">Manage all your estate sale items and classified listings</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="/seller/classifieds/create.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> New Classified
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" class="row align-items-end">
                <div class="col-md-4 mb-3 mb-md-0">
                    <label class="form-label">Filter by Type</label>
                    <select name="filter" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Items</option>
                        <option value="estate" <?= $filter === 'estate' ? 'selected' : '' ?>>Estate Sale Items</option>
                        <option value="classified" <?= $filter === 'classified' ? 'selected' : '' ?>>Classifieds</option>
                        <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active Only</option>
                        <option value="sold" <?= $filter === 'sold' ? 'selected' : '' ?>>Sold Items</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3 mb-md-0">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search by title or description..." 
                           value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Items List -->
        <?php if (empty($items)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No items found. 
                <?php if ($filter === 'all' && !$search): ?>
                    <a href="/seller/classifieds/create.php">Create your first classified ad</a> to get started!
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <small class="text-muted">Showing <?= count($items) ?> of <?= $totalItems ?> items</small>
            </div>
            
            <?php foreach ($items as $item): ?>
                <div class="item-card">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <?php if ($item['primary_photo']): ?>
                                <img src="<?= htmlspecialchars($item['primary_photo']) ?>" 
                                     alt="<?= htmlspecialchars($item['title']) ?>" 
                                     class="item-image">
                            <?php else: ?>
                                <div class="item-placeholder">
                                    <i class="bi bi-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col">
                            <div class="d-flex align-items-start justify-content-between">
                                <div>
                                    <h5 class="mb-1">
                                        <?= htmlspecialchars($item['title']) ?>
                                        <span class="listing-badge <?= $item['listing_type'] === 'estate_sale' ? 'badge-estate' : 'badge-classified' ?>">
                                            <?= $item['listing_type'] === 'estate_sale' ? 'Estate Sale' : 'Classified' ?>
                                        </span>
                                        <span class="status-badge status-<?= $item['status'] ?>">
                                            <?= ucfirst($item['status']) ?>
                                        </span>
                                    </h5>
                                    <p class="text-muted mb-2">
                                        <?= htmlspecialchars($item['sale_name']) ?>
                                        <?php if ($item['category_name']): ?>
                                            • <?= htmlspecialchars($item['category_name']) ?>
                                        <?php endif; ?>
                                    </p>
                                    <div class="d-flex gap-4">
                                        <span class="text-primary fw-bold">
                                            $<?= number_format($item['price'] ?: $item['starting_price'], 2) ?>
                                        </span>
                                        <?php if ($item['offer_count'] > 0): ?>
                                            <span class="text-muted">
                                                <i class="bi bi-chat-dots"></i> <?= $item['offer_count'] ?> offers
                                            </span>
                                        <?php endif; ?>
                                        <span class="text-muted">
                                            <i class="bi bi-eye"></i> <?= $item['views'] ?: 0 ?> views
                                        </span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <a href="/seller/items/<?= $item['id'] ?>/edit" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <?php if ($item['status'] === 'active'): ?>
                                        <button class="btn btn-sm btn-outline-danger" 
                                                onclick="if(confirm('Mark this item as sold?')) window.location.href='/seller/items/<?= $item['id'] ?>/sold'">
                                            <i class="bi bi-check-circle"></i> Mark Sold
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                Previous
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= min($totalPages, 10); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>