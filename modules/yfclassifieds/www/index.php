<?php
/**
 * YF Classifieds - Main Gallery View
 * 
 * Displays classified items in a responsive gallery format with social sharing
 */

require_once __DIR__ . '/../../../config/database.php';

// Get filter parameters
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build query
$params = [];
$where = ["i.listing_type = 'classified'", "(i.available_until IS NULL OR i.available_until >= CURDATE())"];

if ($category) {
    $where[] = "c.slug = :category";
    $params[':category'] = $category;
}

if ($search) {
    $where[] = "(i.title LIKE :search OR i.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$whereClause = implode(' AND ', $where);

// Determine sort order
$orderBy = match($sort) {
    'price_low' => 'i.price ASC',
    'price_high' => 'i.price DESC',
    'popular' => 'i.views DESC',
    default => 'i.created_at DESC'
};

// Get total count (simplified)
$countSql = "SELECT COUNT(*) FROM yfc_items WHERE listing_type = 'classified' AND status = 'active'";
$countStmt = $pdo->query($countSql);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $perPage);

// Get items (simplified for existing schema)
$sql = "SELECT i.id, i.title, i.description, 
        COALESCE(i.price, i.starting_price) as price,
        i.status, i.created_at,
        c.name as category_name,
        (SELECT photo_url FROM yfc_item_photos WHERE item_id = i.id AND is_primary = TRUE LIMIT 1) as primary_photo,
        (SELECT COUNT(*) FROM yfc_item_photos WHERE item_id = i.id) as photo_count
        FROM yfc_items i
        LEFT JOIN yfc_categories c ON i.category_id = c.id
        WHERE i.listing_type = 'classified' AND i.status = 'active'
        ORDER BY i.created_at DESC
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter (simplified)
$categorySql = "SELECT c.*, COUNT(i.id) as item_count 
                FROM yfc_categories c
                LEFT JOIN yfc_items i ON c.id = i.category_id AND i.listing_type = 'classified' AND i.status = 'active'
                GROUP BY c.id
                HAVING item_count > 0
                ORDER BY c.name";
$categoryStmt = $pdo->query($categorySql);
$categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YF Classifieds - Local Items for Sale</title>
    <meta name="description" content="Browse local classified ads in Yakima. Find great deals on electronics, furniture, tools, and more.">
    
    <!-- Open Graph tags for social sharing -->
    <meta property="og:title" content="YF Classifieds - Local Items for Sale">
    <meta property="og:description" content="Browse local classified ads in Yakima. Find great deals on electronics, furniture, tools, and more.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= htmlspecialchars("https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}") ?>">
    <meta property="og:image" content="https://<?= $_SERVER['HTTP_HOST'] ?>/modules/yfclassifieds/www/assets/og-image.jpg">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        .header-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        .header-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .filter-section {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .item-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .item-image {
            position: relative;
            padding-top: 75%; /* 4:3 aspect ratio */
            overflow: hidden;
            background: #f0f0f0;
        }
        
        .item-image img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .item-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 0.85rem;
        }
        
        .item-content {
            padding: 1.25rem;
        }
        
        .item-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
            text-decoration: none;
            display: block;
        }
        
        .item-title:hover {
            color: var(--secondary-color);
        }
        
        .item-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: 0.5rem;
        }
        
        .item-meta {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .item-location {
            font-size: 0.9rem;
            color: #495057;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .share-buttons {
            display: flex;
            gap: 10px;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .share-btn {
            flex: 1;
            padding: 5px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background: white;
            color: #495057;
            text-decoration: none;
            text-align: center;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        
        .share-btn:hover {
            background: #f8f9fa;
            border-color: var(--secondary-color);
            color: var(--secondary-color);
        }
        
        .category-pills {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }
        
        .category-pill {
            padding: 5px 15px;
            background: #e9ecef;
            border-radius: 20px;
            text-decoration: none;
            color: #495057;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .category-pill:hover,
        .category-pill.active {
            background: var(--secondary-color);
            color: white;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 3rem;
        }
        
        .no-photo {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: #f0f0f0;
            color: #6c757d;
            font-size: 3rem;
        }
    </style>
</head>
<body>
    <div class="header-section">
        <div class="container">
            <h1>🛍️ YF Classifieds</h1>
            <p class="lead mb-0">Find great local deals - Pickup in store!</p>
        </div>
    </div>
    
    <div class="container">
        <!-- Filters Section -->
        <div class="filter-section">
            <div class="row">
                <div class="col-md-6">
                    <form method="get" class="d-flex gap-2">
                        <input type="search" name="search" class="form-control" 
                               placeholder="Search items..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                        </button>
                    </form>
                </div>
                <div class="col-md-6">
                    <select name="sort" class="form-select" onchange="this.form.submit()">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                    </select>
                </div>
            </div>
            
            <?php if (!empty($categories)): ?>
            <div class="category-pills mt-3">
                <a href="?" class="category-pill <?= !$category ? 'active' : '' ?>">
                    All Categories
                </a>
                <?php foreach ($categories as $cat): ?>
                    <a href="?category=<?= urlencode($cat['slug']) ?>" 
                       class="category-pill <?= $category === $cat['slug'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($cat['name']) ?> (<?= $cat['item_count'] ?>)
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Results Info -->
        <div class="mb-3">
            <p class="text-muted">
                Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalItems) ?> of <?= $totalItems ?> items
                <?php if ($search): ?>
                    for "<strong><?= htmlspecialchars($search) ?></strong>"
                <?php endif; ?>
            </p>
        </div>
        
        <!-- Items Grid -->
        <div class="row g-4">
            <?php if (empty($items)): ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-inbox" style="font-size: 4rem; color: #dee2e6;"></i>
                    <p class="mt-3 text-muted">No items found matching your criteria.</p>
                    <a href="?" class="btn btn-primary">View All Items</a>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="item-card">
                            <a href="item.php?id=<?= $item['id'] ?>" class="text-decoration-none">
                                <div class="item-image">
                                    <?php if ($item['primary_photo']): ?>
                                        <img src="<?= htmlspecialchars($item['primary_photo']) ?>" 
                                             alt="<?= htmlspecialchars($item['title']) ?>">
                                    <?php else: ?>
                                        <div class="no-photo">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($item['photo_count'] > 1): ?>
                                        <span class="item-badge">
                                            <i class="bi bi-images"></i> <?= $item['photo_count'] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </a>
                            
                            <div class="item-content">
                                <a href="item.php?id=<?= $item['id'] ?>" class="item-title">
                                    <?= htmlspecialchars($item['title']) ?>
                                </a>
                                
                                <div class="item-price">
                                    $<?= number_format($item['price'], 2) ?>
                                </div>
                                
                                <?php if ($item['category_name']): ?>
                                    <div class="item-meta">
                                        <i class="bi bi-tag"></i> <?= htmlspecialchars($item['category_name']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="item-location">
                                    <i class="bi bi-geo-alt"></i> Available for pickup
                                </div>
                                
                                <div class="share-buttons">
                                    <a href="#" class="share-btn" onclick="shareItem('facebook', <?= $item['id'] ?>); return false;">
                                        <i class="bi bi-facebook"></i> Share
                                    </a>
                                    <a href="#" class="share-btn" onclick="shareItem('twitter', <?= $item['id'] ?>); return false;">
                                        <i class="bi bi-twitter"></i> Tweet
                                    </a>
                                    <a href="#" class="share-btn" onclick="shareItem('whatsapp', <?= $item['id'] ?>); return false;">
                                        <i class="bi bi-whatsapp"></i> Send
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-5">
                <ul class="pagination">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query(array_filter(['category' => $category, 'search' => $search, 'sort' => $sort])) ?>">
                            Previous
                        </a>
                    </li>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query(array_filter(['category' => $category, 'search' => $search, 'sort' => $sort])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query(array_filter(['category' => $category, 'search' => $search, 'sort' => $sort])) ?>">
                            Next
                        </a>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
    
    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center">
            <p class="text-muted mb-0">© <?= date('Y') ?> YF Classifieds - Part of YakimaFinds.com</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function shareItem(platform, itemId) {
            const url = encodeURIComponent(window.location.origin + '/modules/yfclassifieds/www/item.php?id=' + itemId);
            const title = encodeURIComponent('Check out this item on YF Classifieds!');
            let shareUrl = '';
            
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                    break;
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${title}%20${url}`;
                    break;
            }
            
            // Track share
            fetch(`api/track-share.php?item_id=${itemId}&platform=${platform}`, { method: 'POST' });
            
            // Open share window
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }
    </script>
</body>
</html>