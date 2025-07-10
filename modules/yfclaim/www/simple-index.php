<?php
// Simple YFClaim Index Page - Testing Version
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use YFEvents\Modules\YFClaim\Models\SaleModel;

$saleModel = new SaleModel($pdo);

// Get all sales instead of filtering by date
$allSales = $saleModel->getAllSales(10, 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFClaim - Estate Sale Platform</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .sale-card { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .sale-title { font-size: 1.5rem; color: #333; margin-bottom: 10px; }
        .sale-info { color: #666; margin-bottom: 10px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🏷️ YFClaim - Estate Sale Platform</h1>
    <p>Browse and bid on estate sale items</p>

    <h2>Available Sales</h2>

    <?php if (empty($allSales)): ?>
        <div class="sale-card">
            <h3>No Sales Available</h3>
            <p>Check back soon for upcoming estate sales!</p>
            <a href="/modules/yfclaim/www/admin/" class="btn">Admin Panel</a>
        </div>
    <?php else: ?>
        <?php foreach ($allSales as $sale): ?>
            <div class="sale-card">
                <div class="sale-title"><?= htmlspecialchars($sale['title']) ?></div>
                <div class="sale-info">
                    📍 <?= htmlspecialchars($sale['address']) ?>, <?= htmlspecialchars($sale['city']) ?>
                </div>
                <div class="sale-info">
                    📅 <?= date('M j, Y', strtotime($sale['start_date'])) ?> - <?= date('M j, Y', strtotime($sale['end_date'])) ?>
                </div>
                <?php if ($sale['status'] === 'active'): ?>
                    <a href="sale.php?id=<?= $sale['id'] ?>" class="btn">View Items</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="sale-card">
        <h3>Quick Links</h3>
        <a href="/modules/yfclaim/www/admin/index.php?admin_bypass=YakFind2025" class="btn">Admin Dashboard</a>
        <a href="/modules/yfclaim/www/buyer-portal.php" class="btn" style="background: #28a745;">Buyer Portal</a>
    </div>
</body>
</html>