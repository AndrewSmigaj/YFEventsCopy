<?php
// YFClaim - Public Interface
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

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
    <title>YFClaim - Estate Sale Claiming Platform | Browse & Claim Estate Sale Items</title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="Browse and claim items from estate sales across the Yakima Valley. Fair claiming system for antiques, furniture, and collectibles. Current sales: <?= count($currentSales) ?> active, <?= count($upcomingSales) ?> upcoming.">
    <meta name="keywords" content="estate sales, Yakima Valley estate sales, claim estate sale items, antiques, furniture, collectibles, Washington estate sales, online estate sales">
    <meta name="robots" content="index, follow">
    <meta name="author" content="YFClaim Estate Sales">
    <meta name="geo.region" content="US-WA">
    <meta name="geo.placename" content="Yakima Valley, Washington">
    
    <!-- Open Graph Meta Tags for Facebook -->
    <meta property="og:title" content="YFClaim Estate Sales - Browse & Claim Items">
    <meta property="og:description" content="Fair claiming system for estate sale items. Browse antiques, furniture, and collectibles from estate sales across the Yakima Valley.">
    <meta property="og:image" content="https://<?= $_SERVER['HTTP_HOST'] ?>/modules/yfclaim/www/assets/yfclaim-banner.jpg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="https://<?= $_SERVER['HTTP_HOST'] ?><?= $_SERVER['REQUEST_URI'] ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="YFClaim Estate Sales">
    <meta property="og:locale" content="en_US">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="YFClaim Estate Sales - Browse & Claim Items">
    <meta name="twitter:description" content="Fair claiming system for estate sale items across the Yakima Valley.">
    <meta name="twitter:image" content="https://<?= $_SERVER['HTTP_HOST'] ?>/modules/yfclaim/www/assets/yfclaim-banner.jpg">
    
    <!-- Schema.org Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "WebSite",
        "name": "YFClaim Estate Sales",
        "description": "Estate sale claiming platform for the Yakima Valley",
        "url": "https://<?= $_SERVER['HTTP_HOST'] ?>/modules/yfclaim/www/",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "https://<?= $_SERVER['HTTP_HOST'] ?>/modules/yfclaim/www/?search={search_term_string}",
            "query-input": "required name=search_term_string"
        },
        "publisher": {
            "@type": "Organization",
            "name": "YFClaim",
            "logo": {
                "@type": "ImageObject",
                "url": "https://<?= $_SERVER['HTTP_HOST'] ?>/modules/yfclaim/www/assets/logo.png"
            }
        }
    }
    </script>
    
    <?php if (!empty($currentSales)): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "ItemList",
        "name": "Current Estate Sales",
        "description": "Currently active estate sales available for claiming",
        "numberOfItems": <?= count($currentSales) ?>,
        "itemListElement": [
            <?php foreach ($currentSales as $index => $sale): ?>
            {
                "@type": "Event",
                "position": <?= $index + 1 ?>,
                "name": "<?= htmlspecialchars($sale['title']) ?>",
                "url": "https://<?= $_SERVER['HTTP_HOST'] ?>/modules/yfclaim/www/sale.php?id=<?= $sale['id'] ?>",
                "startDate": "<?= date('c', strtotime($sale['claim_start'])) ?>",
                "endDate": "<?= date('c', strtotime($sale['claim_end'])) ?>",
                "location": {
                    "@type": "Place",
                    "address": {
                        "@type": "PostalAddress",
                        "addressLocality": "<?= htmlspecialchars($sale['city']) ?>",
                        "addressRegion": "<?= htmlspecialchars($sale['state']) ?>"
                    }
                }
            }<?= $index < count($currentSales) - 1 ? ',' : '' ?>
            <?php endforeach; ?>
        ]
    }
    </script>
    <?php endif; ?>
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
        
        .sales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .sale-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .sale-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .sale-header {
            background: #34495e;
            color: white;
            padding: 1rem;
        }
        
        .sale-header h3 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        
        .sale-company {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .sale-body {
            padding: 1rem;
        }
        
        .sale-info {
            margin-bottom: 1rem;
        }
        
        .sale-info p {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .sale-info .icon {
            width: 20px;
            text-align: center;
        }
        
        .sale-stats {
            display: flex;
            justify-content: space-around;
            padding: 1rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        
        .stat {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #7f8c8d;
            text-transform: uppercase;
        }
        
        .sale-actions {
            padding: 1rem;
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            flex: 1;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
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
            background: #27ae60;
            color: white;
        }
        
        .status-preview {
            background: #f39c12;
            color: white;
        }
        
        .status-upcoming {
            background: #3498db;
            color: white;
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
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .sales-grid {
                grid-template-columns: 1fr;
            }
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
                <a href="/modules/yfclaim/www/admin/login.php">Seller Login</a>
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
                <h2>Current Sales</h2>
                <span class="status-badge status-active">Claiming Open</span>
            </div>
            
            <?php if (empty($currentSales)): ?>
                <div class="empty-state">
                    <h3>No Current Sales</h3>
                    <p>Check back soon for new estate sales!</p>
                </div>
            <?php else: ?>
                <div class="sales-grid">
                    <?php foreach ($currentSales as $sale): ?>
                        <?php 
                        $stats = $saleModel->getStats($sale['id']);
                        $now = time();
                        $claimEnd = strtotime($sale['claim_end']);
                        $hoursLeft = round(($claimEnd - $now) / 3600);
                        ?>
                        <div class="sale-card">
                            <div class="sale-header">
                                <h3><?= htmlspecialchars($sale['title']) ?></h3>
                                <p class="sale-company">by <?= htmlspecialchars($sale['company_name']) ?></p>
                            </div>
                            <div class="sale-body">
                                <div class="sale-info">
                                    <p>
                                        <span class="icon">📍</span>
                                        <?= htmlspecialchars($sale['city']) ?>, <?= htmlspecialchars($sale['state']) ?>
                                    </p>
                                    <p>
                                        <span class="icon">⏰</span>
                                        <?= $hoursLeft > 24 ? round($hoursLeft / 24) . ' days' : $hoursLeft . ' hours' ?> left
                                    </p>
                                    <p>
                                        <span class="icon">📅</span>
                                        Pickup: <?= date('M j', strtotime($sale['pickup_start'])) ?> - <?= date('M j', strtotime($sale['pickup_end'])) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="sale-stats">
                                <div class="stat">
                                    <div class="stat-value"><?= $stats['total_items'] ?></div>
                                    <div class="stat-label">Items</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?= $stats['total_offers'] ?></div>
                                    <div class="stat-label">Offers</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?= $stats['unique_buyers'] ?></div>
                                    <div class="stat-label">Buyers</div>
                                </div>
                            </div>
                            <div class="sale-actions">
                                <a href="/modules/yfclaim/www/sale.php?id=<?= $sale['id'] ?>" class="btn btn-primary">
                                    Browse Items
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
        
        <!-- Upcoming Sales -->
        <section id="upcoming" class="section">
            <div class="section-header">
                <h2>Upcoming Sales</h2>
                <span class="status-badge status-upcoming">Preview Available</span>
            </div>
            
            <?php if (empty($upcomingSales)): ?>
                <div class="empty-state">
                    <h3>No Upcoming Sales</h3>
                    <p>New sales will be posted here when available.</p>
                </div>
            <?php else: ?>
                <div class="sales-grid">
                    <?php foreach ($upcomingSales as $sale): ?>
                        <?php 
                        $stats = $saleModel->getStats($sale['id']);
                        $claimStart = strtotime($sale['claim_start']);
                        $daysUntil = round(($claimStart - time()) / 86400);
                        ?>
                        <div class="sale-card">
                            <div class="sale-header">
                                <h3><?= htmlspecialchars($sale['title']) ?></h3>
                                <p class="sale-company">by <?= htmlspecialchars($sale['company_name']) ?></p>
                            </div>
                            <div class="sale-body">
                                <div class="sale-info">
                                    <p>
                                        <span class="icon">📍</span>
                                        <?= htmlspecialchars($sale['city']) ?>, <?= htmlspecialchars($sale['state']) ?>
                                    </p>
                                    <p>
                                        <span class="icon">🔓</span>
                                        Claims open in <?= $daysUntil ?> day<?= $daysUntil != 1 ? 's' : '' ?>
                                    </p>
                                    <p>
                                        <span class="icon">📅</span>
                                        <?= date('M j, g:i A', strtotime($sale['claim_start'])) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="sale-stats">
                                <div class="stat">
                                    <div class="stat-value"><?= $stats['total_items'] ?></div>
                                    <div class="stat-label">Items</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?= date('M j', strtotime($sale['pickup_start'])) ?></div>
                                    <div class="stat-label">Pickup</div>
                                </div>
                                <div class="stat">
                                    <div class="stat-value"><?= $daysUntil ?></div>
                                    <div class="stat-label">Days</div>
                                </div>
                            </div>
                            <div class="sale-actions">
                                <a href="/modules/yfclaim/www/sale.php?id=<?= $sale['id'] ?>&preview=1" class="btn btn-secondary">
                                    Preview Items
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
    
    <footer class="footer">
        <p>&copy; <?= date('Y') ?> YFClaim. Part of <a href="/">YFEvents</a></p>
    </footer>
</body>
</html>