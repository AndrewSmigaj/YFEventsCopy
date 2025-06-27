<?php
/**
 * Unified Seller Dashboard
 * Manage both Estate Sales and Classified Listings
 */

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once dirname(dirname(dirname(__DIR__))) . '/config/database.php';

// Check authentication
if (!isset($_SESSION['seller_id'])) {
    header('Location: /seller/login.php');
    exit;
}

// Get current seller info
$stmt = $pdo->prepare("SELECT * FROM yfc_sellers WHERE id = ?");
$stmt->execute([$_SESSION['seller_id']]);
$seller = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$seller) {
    session_destroy();
    header('Location: /seller/login.php');
    exit;
}

$sellerId = $seller['id'];

// Get seller's stats
$stats = [];

// Estate sales count
$salesStmt = $pdo->prepare("SELECT COUNT(*) FROM yfc_sales WHERE seller_id = ?");
$salesStmt->execute([$sellerId]);
$stats['sales_count'] = $salesStmt->fetchColumn();

// Active estate sale items
$estateItemsStmt = $pdo->prepare("
    SELECT COUNT(*) FROM yfc_items i
    JOIN yfc_sales s ON i.sale_id = s.id
    WHERE s.seller_id = ? AND i.listing_type = 'estate_sale' AND i.status = 'active'
");
$estateItemsStmt->execute([$sellerId]);
$stats['estate_items'] = $estateItemsStmt->fetchColumn();

// Active classified items (for now, count all classifieds as we don't have seller_id on items)
$classifiedStmt = $pdo->prepare("
    SELECT COUNT(*) FROM yfc_items 
    WHERE listing_type = 'classified' AND status = 'active'
");
$classifiedStmt->execute();
$stats['classified_items'] = $classifiedStmt->fetchColumn();

// Total offers (only from estate sales for this seller)
$offersStmt = $pdo->prepare("
    SELECT COUNT(*) FROM yfc_offers o
    JOIN yfc_items i ON o.item_id = i.id
    JOIN yfc_sales s ON i.sale_id = s.id
    WHERE s.seller_id = ?
");
$offersStmt->execute([$sellerId]);
$stats['total_offers'] = $offersStmt->fetchColumn();

// Recent items (only estate sale items for this seller)
$recentItemsStmt = $pdo->prepare("
    SELECT i.*, c.name as category_name,
           s.title as sale_name,
           (SELECT photo_url FROM yfc_item_photos WHERE item_id = i.id AND is_primary = 1 LIMIT 1) as primary_photo
    FROM yfc_items i
    LEFT JOIN yfc_categories c ON i.category_id = c.id
    JOIN yfc_sales s ON i.sale_id = s.id
    WHERE s.seller_id = ? AND i.listing_type = 'estate_sale'
    ORDER BY i.created_at DESC
    LIMIT 5
");
$recentItemsStmt->execute([$sellerId]);
$recentItems = $recentItemsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - YF Marketplace</title>
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
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }
        .action-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            height: 100%;
        }
        .action-card i {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }
        .btn-primary {
            background: #667eea;
            border: none;
        }
        .btn-primary:hover {
            background: #764ba2;
        }
        .recent-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            margin-right: 15px;
        }
        .item-placeholder {
            width: 60px;
            height: 60px;
            background: #e9ecef;
            border-radius: 8px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #adb5bd;
        }
        .item-info {
            flex: 1;
        }
        .item-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        .item-meta {
            font-size: 0.9rem;
            color: #666;
        }
        .listing-badge {
            display: inline-block;
            padding: 4px 8px;
            font-size: 0.75rem;
            border-radius: 4px;
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
                        <a class="nav-link active" href="/seller/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/seller/sales.php">Estate Sales</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/seller/classifieds.php">Classifieds</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/seller/profile.php">Profile</a>
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
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-12">
                <h1>Welcome back, <?= htmlspecialchars($seller['company_name']) ?>!</h1>
                <p class="text-muted">Manage your estate sales and classified listings from one dashboard.</p>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-5">
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['sales_count'] ?></div>
                    <div class="stat-label">Estate Sales</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['estate_items'] ?></div>
                    <div class="stat-label">Estate Items</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['classified_items'] ?></div>
                    <div class="stat-label">Classified Ads</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card">
                    <div class="stat-number"><?= $stats['total_offers'] ?></div>
                    <div class="stat-label">Total Offers</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-5">
            <div class="col-12">
                <h3 class="mb-4">Quick Actions</h3>
            </div>
            <div class="col-md-4 mb-3">
                <div class="action-card">
                    <i class="bi bi-calendar-plus"></i>
                    <h5>Create Estate Sale</h5>
                    <p class="text-muted">Set up a new estate sale event</p>
                    <a href="/seller/sales/create.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> New Sale
                    </a>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="action-card">
                    <i class="bi bi-megaphone"></i>
                    <h5>Post Classified Ad</h5>
                    <p class="text-muted">List an item for direct sale</p>
                    <a href="/seller/classifieds/create.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> New Ad
                    </a>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="action-card">
                    <i class="bi bi-graph-up"></i>
                    <h5>View Analytics</h5>
                    <p class="text-muted">Track your sales performance</p>
                    <a href="/seller/analytics.php" class="btn btn-outline-primary">
                        <i class="bi bi-bar-chart"></i> Analytics
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Items -->
        <div class="row">
            <div class="col-12">
                <h3 class="mb-4">Recent Items</h3>
                <?php if (empty($recentItems)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> No items yet. Start by creating an estate sale or posting a classified ad!
                    </div>
                <?php else: ?>
                    <?php foreach ($recentItems as $item): ?>
                        <div class="recent-item">
                            <?php if ($item['primary_photo']): ?>
                                <img src="<?= htmlspecialchars($item['primary_photo']) ?>" alt="" class="item-image">
                            <?php else: ?>
                                <div class="item-placeholder">
                                    <i class="bi bi-image"></i>
                                </div>
                            <?php endif; ?>
                            <div class="item-info">
                                <div class="item-title">
                                    <?= htmlspecialchars($item['title']) ?>
                                    <span class="listing-badge <?= $item['listing_type'] === 'estate_sale' ? 'badge-estate' : 'badge-classified' ?>">
                                        <?= $item['listing_type'] === 'estate_sale' ? 'Estate Sale' : 'Classified' ?>
                                    </span>
                                </div>
                                <div class="item-meta">
                                    <?= htmlspecialchars($item['sale_name']) ?> • 
                                    $<?= number_format($item['price'] ?: $item['starting_price'], 2) ?> • 
                                    <?= htmlspecialchars($item['category_name'] ?: 'Uncategorized') ?>
                                </div>
                            </div>
                            <div>
                                <a href="/seller/items/<?= $item['id'] ?>/edit" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-4">
                        <a href="/seller/items.php" class="btn btn-outline-primary">
                            View All Items <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>