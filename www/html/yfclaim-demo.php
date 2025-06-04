<?php
// YFClaim Demo Interface (No Authentication Required)
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;

// Initialize models
$sellerModel = new SellerModel($pdo);
$saleModel = new SaleModel($pdo);
$itemModel = new ItemModel($pdo);

// Get sample data
$sellers = $sellerModel->getAllSellers(10, 0);
$sales = $saleModel->getAllSales(10, 0);
$items = $itemModel->getAllItems(20, 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFClaim Demo - Estate Sales</title>
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
            padding: 2rem 0;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .nav {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .nav a {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 0.25rem;
            transition: background-color 0.3s;
        }
        
        .nav a:hover {
            background: #2980b9;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #3498db;
        }
        
        .stat-label {
            color: #7f8c8d;
            text-transform: uppercase;
            font-size: 0.9rem;
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
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 0.5rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
        }
        
        .table tr:hover {
            background: #f8f9fa;
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
        
        .status-draft {
            background: #fff3cd;
            color: #856404;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        
        .card h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .highlight {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 5px;
            margin: 1rem 0;
            border-left: 4px solid #2196f3;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1>🏠 YFClaim Estate Sales Demo</h1>
        <p>See how the estate sale claiming platform works with real data</p>
    </header>
    
    <div class="container">
        <div class="nav">
            <a href="/">← Back to YFEvents</a>
            <a href="/yfclaim-simple.php">📋 System Overview</a>
            <a href="/admin/">🔧 Admin Login</a>
        </div>
        
        <div class="highlight">
            <strong>🎯 This is a live demo of YFClaim!</strong> 
            The data below is real and comes from the sample estate sale we created. 
            To manage this data, login to the admin dashboard.
        </div>
        
        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value"><?= count($sellers) ?></div>
                <div class="stat-label">Estate Sale Companies</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($sales) ?></div>
                <div class="stat-label">Active Sales</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($items) ?></div>
                <div class="stat-label">Items Available</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">0</div>
                <div class="stat-label">Active Offers</div>
            </div>
        </div>
        
        <!-- Estate Sale Companies -->
        <?php if (!empty($sellers)): ?>
        <div class="section">
            <h2>🏢 Estate Sale Companies</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Company Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sellers as $seller): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($seller['company_name']) ?></strong></td>
                        <td><?= htmlspecialchars($seller['contact_name']) ?></td>
                        <td><?= htmlspecialchars($seller['email']) ?></td>
                        <td><?= htmlspecialchars($seller['phone'] ?? 'Not provided') ?></td>
                        <td><?= htmlspecialchars($seller['city'] ?? '') ?>, <?= htmlspecialchars($seller['state'] ?? '') ?></td>
                        <td><span class="status-badge status-<?= $seller['status'] ?>"><?= ucfirst($seller['status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- Active Sales -->
        <?php if (!empty($sales)): ?>
        <div class="section">
            <h2>🏠 Current Estate Sales</h2>
            <div class="grid">
                <?php foreach ($sales as $sale): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($sale['title']) ?></h3>
                    <p><strong>Company:</strong> <?= htmlspecialchars($sale['company_name'] ?? 'Unknown') ?></p>
                    <p><strong>Location:</strong> <?= htmlspecialchars($sale['city'] ?? '') ?>, <?= htmlspecialchars($sale['state'] ?? '') ?></p>
                    <p><strong>Start Date:</strong> <?= $sale['start_date'] ? date('M j, Y', strtotime($sale['start_date'])) : 'Not set' ?></p>
                    <p><strong>Status:</strong> <span class="status-badge status-<?= $sale['status'] ?>"><?= ucfirst($sale['status']) ?></span></p>
                    <?php if ($sale['description']): ?>
                    <p style="margin-top: 1rem; font-style: italic;"><?= htmlspecialchars(substr($sale['description'], 0, 150)) ?>...</p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Sample Items -->
        <?php if (!empty($items)): ?>
        <div class="section">
            <h2>📦 Sample Items Available</h2>
            <div class="grid">
                <?php foreach (array_slice($items, 0, 6) as $item): ?>
                <div class="card">
                    <h3><?= htmlspecialchars($item['title']) ?></h3>
                    <p><strong>Starting Price:</strong> $<?= number_format($item['starting_price'], 2) ?></p>
                    <?php if ($item['measurements']): ?>
                    <p><strong>Size:</strong> <?= htmlspecialchars($item['measurements']) ?></p>
                    <?php endif; ?>
                    <?php if ($item['condition_notes']): ?>
                    <p><strong>Condition:</strong> <?= htmlspecialchars($item['condition_notes']) ?></p>
                    <?php endif; ?>
                    <p style="margin-top: 1rem; font-style: italic;"><?= htmlspecialchars(substr($item['description'], 0, 100)) ?>...</p>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($items) > 6): ?>
            <p style="text-align: center; margin-top: 2rem; color: #7f8c8d;">
                And <?= count($items) - 6 ?> more items available...
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- How It Works -->
        <div class="section">
            <h2>🎯 How YFClaim Works</h2>
            <div class="grid">
                <div class="card">
                    <h3>1. 🏢 Sellers Setup</h3>
                    <p>Estate sale companies register and create their sales with items, photos, and pricing.</p>
                </div>
                <div class="card">
                    <h3>2. 👥 Buyers Browse</h3>
                    <p>Buyers can browse items online, see photos and descriptions, and check claiming status.</p>
                </div>
                <div class="card">
                    <h3>3. 💰 Make Offers</h3>
                    <p>Buyers submit offers on items they want, with email/SMS verification for security.</p>
                </div>
                <div class="card">
                    <h3>4. ✅ Claiming Process</h3>
                    <p>Sellers review offers and select winners. Buyers get notified and arrange pickup.</p>
                </div>
                <div class="card">
                    <h3>5. 📱 QR Codes</h3>
                    <p>Each item gets a QR code for easy access. Buyers can scan to view details and make offers.</p>
                </div>
                <div class="card">
                    <h3>6. 📊 Analytics</h3>
                    <p>Complete reporting on offers, sales, and buyer activity for better management.</p>
                </div>
            </div>
        </div>
        
        <div class="highlight">
            <h3>🚀 Ready to try YFClaim?</h3>
            <p>
                <strong>For Administrators:</strong> <a href="/admin/">Login to the admin dashboard</a> to manage sellers, sales, items, and offers.<br>
                <strong>For Estate Sale Companies:</strong> Contact an administrator to get your seller account set up.<br>
                <strong>For Buyers:</strong> Visit active sales to browse items and make offers (coming soon).
            </p>
        </div>
    </div>
</body>
</html>