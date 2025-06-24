<?php
// YFClaim Seller Dashboard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if authenticated
if (!isset($_SESSION['yfclaim_seller_id'])) {
    header('Location: /refactor/seller/login');
    exit;
}

$basePath = '/refactor';

// Bootstrap the application
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
$container = require dirname(dirname(__DIR__)) . '/config/bootstrap.php';

// Get database connection
$pdo = $container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class)->getConnection();

// Get seller info
$sellerId = $_SESSION['yfclaim_seller_id'];
$sellerName = $_SESSION['yfclaim_seller_name'] ?? 'Seller';

// Get seller statistics
$stats = [];

// Total sales
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM yfc_sales WHERE seller_id = ?");
$stmt->execute([$sellerId]);
$stats['total_sales'] = $stmt->fetchColumn();

// Active sales
$stmt = $pdo->prepare("SELECT COUNT(*) as active FROM yfc_sales WHERE seller_id = ? AND status = 'active'");
$stmt->execute([$sellerId]);
$stats['active_sales'] = $stmt->fetchColumn();

// Total items
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM yfc_items i 
    JOIN yfc_sales s ON i.sale_id = s.id 
    WHERE s.seller_id = ?
");
$stmt->execute([$sellerId]);
$stats['total_items'] = $stmt->fetchColumn();

// Total revenue
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(o.offer_amount), 0) as revenue
    FROM yfc_offers o
    JOIN yfc_items i ON o.item_id = i.id
    JOIN yfc_sales s ON i.sale_id = s.id
    WHERE s.seller_id = ? AND o.status = 'winning'
");
$stmt->execute([$sellerId]);
$stats['total_revenue'] = $stmt->fetchColumn();

// Get recent sales
$stmt = $pdo->prepare("
    SELECT s.*, 
        COUNT(DISTINCT i.id) as item_count,
        COUNT(DISTINCT o.id) as offer_count,
        COALESCE(SUM(CASE WHEN o.status = 'winning' THEN o.offer_amount ELSE 0 END), 0) as revenue
    FROM yfc_sales s
    LEFT JOIN yfc_items i ON s.id = i.sale_id
    LEFT JOIN yfc_offers o ON i.id = o.item_id
    WHERE s.seller_id = ?
    GROUP BY s.id
    ORDER BY s.created_at DESC
    LIMIT 10
");
$stmt->execute([$sellerId]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - YFClaim Estate Sales</title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-light {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .btn-light:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .btn-primary {
            background: white;
            color: #667eea;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: #f8f9fa;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.blue { background: #e7f3ff; color: #0056b3; }
        .stat-icon.green { background: #d4edda; color: #155724; }
        .stat-icon.purple { background: #f0e7ff; color: #6f42c1; }
        .stat-icon.orange { background: #fff3cd; color: #856404; }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin: 0;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
            margin: 0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin: 0;
        }
        
        .sales-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #495057;
            border-bottom: 2px solid #dee2e6;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-scheduled {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-completed {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.25rem 0.75rem;
            font-size: 0.875rem;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            margin-bottom: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            table {
                font-size: 0.875rem;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
            }
            
            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="header-top">
                <div>
                    <h1>🏪 Seller Dashboard</h1>
                    <p>Welcome back, <?= htmlspecialchars($sellerName) ?>!</p>
                </div>
                <div class="header-actions">
                    <a href="<?= $basePath ?>/seller/sale/new" class="btn btn-primary">+ Create New Sale</a>
                    <a href="<?= $basePath ?>/seller/logout" class="btn btn-light">Logout</a>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">📊</div>
                <div class="stat-content">
                    <p class="stat-value"><?= $stats['total_sales'] ?></p>
                    <p class="stat-label">Total Sales</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">✅</div>
                <div class="stat-content">
                    <p class="stat-value"><?= $stats['active_sales'] ?></p>
                    <p class="stat-label">Active Sales</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">📦</div>
                <div class="stat-content">
                    <p class="stat-value"><?= $stats['total_items'] ?></p>
                    <p class="stat-label">Total Items</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">💰</div>
                <div class="stat-content">
                    <p class="stat-value">$<?= number_format($stats['total_revenue'], 2) ?></p>
                    <p class="stat-label">Total Revenue</p>
                </div>
            </div>
        </div>
        
        <!-- Sales Table -->
        <div class="section-header">
            <h2 class="section-title">Recent Sales</h2>
            <a href="<?= $basePath ?>/seller/sales" class="btn btn-secondary btn-sm">View All</a>
        </div>
        
        <?php if (!empty($sales)): ?>
        <div class="sales-table">
            <table>
                <thead>
                    <tr>
                        <th>Sale Title</th>
                        <th>Status</th>
                        <th>Dates</th>
                        <th>Items</th>
                        <th>Offers</th>
                        <th>Revenue</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($sale['title']) ?></strong>
                            <br>
                            <small class="text-muted"><?= htmlspecialchars($sale['city'] ?? 'Unknown Location') ?></small>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $sale['status'] ?? 'scheduled' ?>">
                                <?= $sale['status'] ?? 'scheduled' ?>
                            </span>
                        </td>
                        <td>
                            <?= date('M j', strtotime($sale['start_date'])) ?> - 
                            <?= date('M j, Y', strtotime($sale['end_date'])) ?>
                        </td>
                        <td><?= $sale['item_count'] ?></td>
                        <td><?= $sale['offer_count'] ?></td>
                        <td>$<?= number_format($sale['revenue'], 2) ?></td>
                        <td>
                            <div class="actions">
                                <a href="<?= $basePath ?>/seller/sale/<?= $sale['id'] ?>/edit" 
                                   class="btn btn-secondary btn-sm">Edit</a>
                                <a href="<?= $basePath ?>/seller/sale/<?= $sale['id'] ?>/items" 
                                   class="btn btn-secondary btn-sm">Items</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <h3>No sales yet</h3>
            <p>Create your first estate sale to start accepting offers!</p>
            <a href="<?= $basePath ?>/seller/sale/new" class="btn btn-primary">Create Your First Sale</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>