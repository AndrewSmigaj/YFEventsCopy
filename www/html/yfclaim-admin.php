<?php
// YFClaim Admin Entry Point
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;
use YFEvents\Modules\YFClaim\Models\BuyerModel;

// Authentication check
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

// Initialize models
$sellerModel = new SellerModel($pdo);
$saleModel = new SaleModel($pdo);
$itemModel = new ItemModel($pdo);
$offerModel = new OfferModel($pdo);
$buyerModel = new BuyerModel($pdo);

// Get statistics
$stats = [];
try {
    $stats['sellers'] = $sellerModel->count();
    $stats['sales'] = $saleModel->count();
    $stats['items'] = $itemModel->count();
    $stats['offers'] = $offerModel->count();
    $stats['buyers'] = $buyerModel->count();
} catch (Exception $e) {
    $stats = ['sellers' => 0, 'sales' => 0, 'items' => 0, 'offers' => 0, 'buyers' => 0];
}

// Get recent data
$recentSales = $saleModel->getAllSales(5, 0);
$recentOffers = $offerModel->getAllOffers(10, 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFClaim Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: #2c3e50;
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }
        
        .nav-links a:hover {
            opacity: 0.8;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #7f8c8d;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .action-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .action-card h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn-success {
            background: #27ae60;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-warning {
            background: #f39c12;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .section h2 {
            margin-bottom: 1.5rem;
            color: #2c3e50;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #cce5ff;
            color: #004085;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">YFClaim Admin</div>
            <nav class="nav-links">
                <a href="/admin/">Main Admin</a>
                <a href="/">YFEvents</a>
                <a href="/admin/logout.php">Logout</a>
            </nav>
        </div>
    </header>
    
    <main class="container">
        <h1 style="margin-bottom: 2rem; color: #2c3e50;">Estate Sales Management Dashboard</h1>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card" onclick="window.location.href='#sellers'">
                <div class="stat-value" style="color: #3498db;"><?= $stats['sellers'] ?></div>
                <div class="stat-label">Sellers</div>
            </div>
            <div class="stat-card" onclick="window.location.href='#sales'">
                <div class="stat-value" style="color: #27ae60;"><?= $stats['sales'] ?></div>
                <div class="stat-label">Sales</div>
            </div>
            <div class="stat-card" onclick="window.location.href='#items'">
                <div class="stat-value" style="color: #f39c12;"><?= $stats['items'] ?></div>
                <div class="stat-label">Items</div>
            </div>
            <div class="stat-card" onclick="window.location.href='#offers'">
                <div class="stat-value" style="color: #e74c3c;"><?= $stats['offers'] ?></div>
                <div class="stat-label">Offers</div>
            </div>
            <div class="stat-card" onclick="window.location.href='#buyers'">
                <div class="stat-value" style="color: #9b59b6;"><?= $stats['buyers'] ?></div>
                <div class="stat-label">Buyers</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-card">
                <h3>🏢 Seller Management</h3>
                <p>Manage estate sale companies and their contact information.</p>
                <a href="/modules/yfclaim/www/admin/sellers.php" class="btn">Manage Sellers</a>
            </div>
            
            <div class="action-card">
                <h3>🏠 Sales Management</h3>
                <p>Create and manage estate sales, set dates, and configure settings.</p>
                <a href="/modules/yfclaim/www/admin/sales.php" class="btn btn-success">Manage Sales</a>
            </div>
            
            <div class="action-card">
                <h3>📦 Items & Inventory</h3>
                <p>Add items to sales, manage pricing, and track inventory.</p>
                <a href="/modules/yfclaim/www/admin/sales.php" class="btn btn-warning">View Items</a>
            </div>
            
            <div class="action-card">
                <h3>💰 Offers & Bids</h3>
                <p>Review offers from buyers and manage the bidding process.</p>
                <a href="/modules/yfclaim/www/admin/offers.php" class="btn">View Offers</a>
            </div>
            
            <div class="action-card">
                <h3>👥 Buyer Management</h3>
                <p>Manage buyer accounts and authentication.</p>
                <a href="/modules/yfclaim/www/admin/buyers.php" class="btn">Manage Buyers</a>
            </div>
            
            <div class="action-card">
                <h3>📱 QR Codes</h3>
                <p>Generate and print QR codes for sales and items.</p>
                <?php if (!empty($recentSales)): ?>
                <a href="/modules/yfclaim/www/admin/qr-codes.php?sale_id=<?= $recentSales[0]['id'] ?>" class="btn btn-success">View QR Codes</a>
                <?php else: ?>
                <span style="color: #7f8c8d;">Create a sale first</span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Sales -->
        <?php if (!empty($recentSales)): ?>
        <div class="section">
            <h2>Recent Sales</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Seller</th>
                        <th>Status</th>
                        <th>Start Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentSales as $sale): ?>
                    <tr>
                        <td><?= $sale['id'] ?></td>
                        <td><?= htmlspecialchars($sale['title']) ?></td>
                        <td><?= htmlspecialchars($sale['company_name'] ?? 'Unknown') ?></td>
                        <td><span class="status-badge status-<?= $sale['status'] ?>"><?= ucfirst($sale['status']) ?></span></td>
                        <td><?= $sale['start_date'] ? date('M j, Y', strtotime($sale['start_date'])) : 'Not set' ?></td>
                        <td>
                            <a href="/modules/yfclaim/www/admin/sales.php?edit=<?= $sale['id'] ?>" class="btn" style="padding: 0.25rem 0.75rem; font-size: 0.8rem;">Edit</a>
                            <a href="/modules/yfclaim/www/admin/qr-codes.php?sale_id=<?= $sale['id'] ?>" class="btn btn-success" style="padding: 0.25rem 0.75rem; font-size: 0.8rem;">QR Codes</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Recent Offers -->
        <?php if (!empty($recentOffers)): ?>
        <div class="section">
            <h2>Recent Offers</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item</th>
                        <th>Buyer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($recentOffers, 0, 5) as $offer): ?>
                    <tr>
                        <td><?= $offer['id'] ?></td>
                        <td><?= htmlspecialchars($offer['item_title'] ?? 'Unknown Item') ?></td>
                        <td><?= htmlspecialchars($offer['buyer_name'] ?? 'Unknown Buyer') ?></td>
                        <td>$<?= number_format($offer['offer_amount'], 2) ?></td>
                        <td><span class="status-badge status-<?= $offer['status'] ?>"><?= ucfirst($offer['status']) ?></span></td>
                        <td><?= date('M j, Y', strtotime($offer['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Getting Started -->
        <?php if ($stats['sales'] == 0): ?>
        <div class="section">
            <h2>🚀 Getting Started</h2>
            <p>Welcome to YFClaim! Here's how to get started:</p>
            <ol style="margin: 1rem 0; padding-left: 2rem;">
                <li>First, <a href="/modules/yfclaim/www/admin/sellers.php">create a seller account</a> for the estate sale company</li>
                <li>Then, <a href="/modules/yfclaim/www/admin/sales.php">create a new sale</a> with dates and location</li>
                <li>Add items to the sale with photos and starting prices</li>
                <li>Generate QR codes for easy buyer access</li>
                <li>Monitor offers and manage the claiming process</li>
            </ol>
            <p><strong>Sample data is already loaded!</strong> You can explore the existing sale to see how everything works.</p>
        </div>
        <?php endif; ?>
    </main>
</body>
</html>