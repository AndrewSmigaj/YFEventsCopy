<?php
// YFClaim - Public Interface (Direct Copy)
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\SellerModel;

// Initialize models
$saleModel = new SaleModel($pdo);
$itemModel = new ItemModel($pdo);
$sellerModel = new SellerModel($pdo);

// Get current and upcoming sales
$currentSales = $saleModel->getCurrent();
$upcomingSales = $saleModel->getUpcoming();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFClaim - Estate Sale Claiming Platform</title>
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
        
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
            text-align: center;
        }
        
        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .section {
            margin-bottom: 3rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .section h2 {
            font-size: 1.8rem;
            color: #2c3e50;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
        }
        
        .empty-state h3 {
            margin-bottom: 1rem;
        }
        
        .footer {
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
            text-align: center;
            margin-top: 4rem;
        }
        
        .footer a {
            color: #3498db;
            text-decoration: none;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">YFClaim</div>
            <nav class="nav-links">
                <a href="#current">Current Sales</a>
                <a href="#upcoming">Upcoming Sales</a>
                <a href="/">← Back to YFEvents</a>
            </nav>
        </div>
    </header>
    
    <section class="hero">
        <div class="container">
            <h1>Estate Sale Claiming Made Simple</h1>
            <p>Browse items, make offers, and claim your treasures from local estate sales</p>
        </div>
    </section>
    
    <main class="container">
        <!-- Current Sales -->
        <section id="current" class="section">
            <div class="section-header">
                <h2>Current Sales (<?= count($currentSales) ?>)</h2>
            </div>
            
            <?php if (empty($currentSales)): ?>
                <div class="empty-state">
                    <h3>No Current Sales</h3>
                    <p>Check back soon for new estate sales!</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem;">
                    <?php foreach ($currentSales as $sale): ?>
                        <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <h3><?= htmlspecialchars($sale['title']) ?></h3>
                            <p>by <?= htmlspecialchars($sale['company_name'] ?? 'Unknown Seller') ?></p>
                            <p>📍 <?= htmlspecialchars($sale['city'] ?? '') ?>, <?= htmlspecialchars($sale['state'] ?? '') ?></p>
                            <div style="margin-top: 1rem;">
                                <a href="#" class="btn">View Items</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        
        <!-- Upcoming Sales -->
        <section id="upcoming" class="section">
            <div class="section-header">
                <h2>Upcoming Sales (<?= count($upcomingSales) ?>)</h2>
            </div>
            
            <?php if (empty($upcomingSales)): ?>
                <div class="empty-state">
                    <h3>No Upcoming Sales</h3>
                    <p>New sales will be posted here when available.</p>
                </div>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 2rem;">
                    <?php foreach ($upcomingSales as $sale): ?>
                        <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <h3><?= htmlspecialchars($sale['title']) ?></h3>
                            <p>by <?= htmlspecialchars($sale['company_name'] ?? 'Unknown Seller') ?></p>
                            <p>📍 <?= htmlspecialchars($sale['city'] ?? '') ?>, <?= htmlspecialchars($sale['state'] ?? '') ?></p>
                            <div style="margin-top: 1rem;">
                                <a href="#" class="btn">Preview Items</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        
        <section class="section">
            <div style="background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center;">
                <h2>How It Works</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-top: 2rem;">
                    <div>
                        <h3>1. Browse Sales</h3>
                        <p>View current and upcoming estate sales in your area</p>
                    </div>
                    <div>
                        <h3>2. Make Offers</h3>
                        <p>Submit offers on items you're interested in</p>
                    </div>
                    <div>
                        <h3>3. Get Selected</h3>
                        <p>Sellers choose winning offers and notify buyers</p>
                    </div>
                    <div>
                        <h3>4. Pick Up Items</h3>
                        <p>Collect your claimed items during pickup times</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <footer class="footer">
        <p>&copy; <?= date('Y') ?> YFClaim. Part of <a href="/">YFEvents</a></p>
    </footer>
</body>
</html>