<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../../../../config/database.php';

// Get shops with categories directly from database
$query = "SELECT s.*, c.name as category_name,
          (SELECT COUNT(*) FROM shop_images WHERE shop_id = s.id) as image_count
          FROM local_shops s
          LEFT JOIN shop_categories c ON s.category_id = c.id
          ORDER BY s.created_at DESC";

$stmt = $db->query($query);
$shops = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop Management - Advanced Admin</title>
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
        .shop-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .shop-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .shop-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
        }
        .shop-name {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .shop-category {
            color: #666;
            font-size: 14px;
        }
        .shop-details {
            padding: 20px;
        }
        .detail-row {
            margin-bottom: 10px;
            display: flex;
            align-items: start;
        }
        .detail-icon {
            width: 20px;
            color: #666;
            margin-right: 10px;
        }
        .shop-stats {
            display: flex;
            justify-content: space-around;
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        .shop-actions {
            display: flex;
            padding: 15px;
            gap: 10px;
            border-top: 1px solid #eee;
        }
        .shop-actions button {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-edit { background: #007bff; color: white; }
        .btn-images { background: #28a745; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .add-shop-btn {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
        }
        .status-active { color: #28a745; }
        .status-inactive { color: #dc3545; }
        .no-data { color: #999; font-style: italic; }
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
            <h1>Shop Management</h1>
            
            <button class="add-shop-btn" onclick="window.location.href='/admin/shops.php?action=add'">
                <i class="fas fa-plus"></i> Add New Shop
            </button>
            
            <div class="shop-grid">
                <?php foreach ($shops as $shop): ?>
                <div class="shop-card" data-shop-id="<?= $shop['id'] ?>">
                    <div class="shop-header">
                        <div class="shop-name"><?= htmlspecialchars($shop['name']) ?></div>
                        <div class="shop-category">
                            <?= htmlspecialchars($shop['category_name'] ?? 'Uncategorized') ?>
                            <i class="fas fa-circle status-<?= $shop['is_active'] ? 'active' : 'inactive' ?>" 
                               style="font-size: 10px; margin-left: 10px;"
                               title="<?= $shop['is_active'] ? 'Active' : 'Inactive' ?>"></i>
                        </div>
                    </div>
                    
                    <div class="shop-details">
                        <div class="detail-row">
                            <i class="fas fa-map-marker-alt detail-icon"></i>
                            <div>
                                <?= htmlspecialchars($shop['address']) ?><br>
                                <?= htmlspecialchars($shop['city']) ?>, <?= htmlspecialchars($shop['state']) ?> <?= htmlspecialchars($shop['zip']) ?>
                            </div>
                        </div>
                        
                        <?php if ($shop['phone']): ?>
                        <div class="detail-row">
                            <i class="fas fa-phone detail-icon"></i>
                            <div><?= htmlspecialchars($shop['phone']) ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($shop['website']): ?>
                        <div class="detail-row">
                            <i class="fas fa-globe detail-icon"></i>
                            <div>
                                <a href="<?= htmlspecialchars($shop['website']) ?>" target="_blank">
                                    <?= parse_url($shop['website'], PHP_URL_HOST) ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($shop['hours']): ?>
                        <div class="detail-row">
                            <i class="fas fa-clock detail-icon"></i>
                            <div><?= nl2br(htmlspecialchars(substr($shop['hours'], 0, 50))) ?>...</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="shop-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?= $shop['image_count'] ?></div>
                            <div class="stat-label">Images</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">
                                <?= $shop['latitude'] && $shop['longitude'] ? 
                                    '<i class="fas fa-check" style="color: #28a745;"></i>' : 
                                    '<i class="fas fa-times" style="color: #dc3545;"></i>' ?>
                            </div>
                            <div class="stat-label">Geocoded</div>
                        </div>
                    </div>
                    
                    <div class="shop-actions">
                        <button class="btn-edit" onclick="editShop(<?= $shop['id'] ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn-images" onclick="manageImages(<?= $shop['id'] ?>)">
                            <i class="fas fa-images"></i> Images
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($shops)): ?>
            <p class="no-data" style="text-align: center; margin-top: 50px;">
                No shops found. Click "Add New Shop" to create one.
            </p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function editShop(shopId) {
            window.location.href = `/admin/shops.php?action=edit&id=${shopId}`;
        }
        
        function manageImages(shopId) {
            window.location.href = `/admin/shops.php?action=images&id=${shopId}`;
        }
    </script>
</body>
</html>