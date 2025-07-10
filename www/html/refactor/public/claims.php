<?php
// YFClaim Estate Sales Public Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the autoloader and config
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use YakimaFinds\Presentation\Http\Controllers\ClaimsController;

// Create controller instance
$controller = new ClaimsController();

// Get current sales
$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Get current active sales
$stmt = $pdo->prepare("
    SELECT s.*, sel.company_name, sel.contact_email, sel.contact_phone,
           COUNT(i.id) as item_count,
           COUNT(DISTINCT o.buyer_id) as buyer_count,
           COUNT(o.id) as offer_count
    FROM yfc_sales s
    LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
    LEFT JOIN yfc_items i ON s.id = i.sale_id
    LEFT JOIN yfc_offers o ON i.id = o.item_id
    WHERE s.status = 'active' 
    AND s.claim_start <= NOW() 
    AND s.claim_end >= NOW()
    GROUP BY s.id
    ORDER BY s.claim_end ASC
");
$stmt->execute();
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$basePath = '/refactor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFClaim Estate Sales - Yakima Finds</title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 0;
            text-align: center;
        }
        
        .hero-title {
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: bold;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .sales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }
        
        .sale-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .sale-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .sale-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .sale-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .sale-company {
            color: #6c757d;
            font-size: 0.95rem;
        }
        
        .sale-body {
            padding: 1.5rem;
        }
        
        .sale-info {
            margin-bottom: 1rem;
        }
        
        .sale-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .sale-info-icon {
            width: 20px;
            margin-right: 0.5rem;
            color: #667eea;
        }
        
        .sale-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin: 1rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #6c757d;
            text-transform: uppercase;
        }
        
        .sale-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-view-sale {
            flex: 1;
            background: #667eea;
            color: white;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            text-align: center;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-view-sale:hover {
            background: #5a6fd8;
            color: white;
        }
        
        .time-remaining {
            background: #fff3cd;
            color: #856404;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .time-remaining.urgent {
            background: #f8d7da;
            color: #721c24;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }
        
        .nav-header {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .nav-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .nav-brand {
            font-size: 1.5rem;
            font-weight: bold;
            color: #2c3e50;
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .nav-links a {
            color: #6c757d;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: #667eea;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .sales-grid {
                grid-template-columns: 1fr;
            }
            
            .sale-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="nav-header">
        <div class="nav-content">
            <a href="<?= $basePath ?>/" class="nav-brand">🏛️ YFClaim</a>
            <div class="nav-links">
                <a href="<?= $basePath ?>/">Home</a>
                <a href="<?= $basePath ?>/events">Events</a>
                <a href="<?= $basePath ?>/shops">Shops</a>
                <a href="<?= $basePath ?>/claims">Estate Sales</a>
                <a href="<?= $basePath ?>/seller/login">Seller Login</a>
                <a href="<?= $basePath ?>/buyer/auth">Buyer Portal</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title">Estate Sale Claims</h1>
            <p class="hero-subtitle">
                Browse current estate sales, view items, and submit claims. 
                Join thousands of buyers finding great deals on quality items.
            </p>
        </div>
    </section>

    <!-- Main Content -->
    <div class="container">
        <?php if (empty($sales)): ?>
            <div class="empty-state">
                <h2>No Active Estate Sales</h2>
                <p>There are currently no estate sales accepting claims.</p>
                <p>Check back soon for new sales!</p>
                <div style="margin-top: 2rem;">
                    <a href="<?= $basePath ?>/seller/register" class="btn-view-sale" style="display: inline-block; max-width: 200px;">
                        Register as Seller
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="page-header">
                <h2>Current Estate Sales (<?= count($sales) ?>)</h2>
                <p>Active sales accepting item claims</p>
            </div>

            <div class="sales-grid">
                <?php foreach ($sales as $sale): ?>
                    <?php 
                    $endTime = new DateTime($sale['claim_end']);
                    $now = new DateTime();
                    $timeLeft = $now->diff($endTime);
                    $hoursLeft = ($timeLeft->days * 24) + $timeLeft->h;
                    $isUrgent = $hoursLeft < 24;
                    ?>
                    <div class="sale-card">
                        <div class="sale-header">
                            <div class="sale-title"><?= htmlspecialchars($sale['title']) ?></div>
                            <div class="sale-company">by <?= htmlspecialchars($sale['company_name'] ?? 'Estate Sale Company') ?></div>
                        </div>
                        
                        <div class="sale-body">
                            <?php if ($hoursLeft > 0): ?>
                                <div class="time-remaining <?= $isUrgent ? 'urgent' : '' ?>">
                                    ⏰ <?= $timeLeft->days > 0 ? $timeLeft->days . 'd ' : '' ?><?= $timeLeft->h ?>h <?= $timeLeft->i ?>m remaining
                                </div>
                            <?php endif; ?>
                            
                            <div class="sale-info">
                                <div class="sale-info-item">
                                    <span class="sale-info-icon">📍</span>
                                    <?= htmlspecialchars($sale['location'] ?? 'Location TBD') ?>
                                </div>
                                <div class="sale-info-item">
                                    <span class="sale-info-icon">📅</span>
                                    Claims: <?= date('M j', strtotime($sale['claim_start'])) ?> - <?= date('M j, Y', strtotime($sale['claim_end'])) ?>
                                </div>
                                <div class="sale-info-item">
                                    <span class="sale-info-icon">🏠</span>
                                    Sale: <?= date('M j, Y', strtotime($sale['sale_date'])) ?>
                                </div>
                            </div>
                            
                            <?php if ($sale['description']): ?>
                                <p style="color: #6c757d; margin-bottom: 1rem;">
                                    <?= htmlspecialchars(substr($sale['description'], 0, 150)) ?><?= strlen($sale['description']) > 150 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="sale-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?= $sale['item_count'] ?></div>
                                    <div class="stat-label">Items</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= $sale['buyer_count'] ?></div>
                                    <div class="stat-label">Buyers</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= $sale['offer_count'] ?></div>
                                    <div class="stat-label">Claims</div>
                                </div>
                            </div>
                            
                            <div class="sale-actions">
                                <a href="<?= $basePath ?>/claims/sale?id=<?= $sale['id'] ?>" class="btn-view-sale">
                                    View Items & Claim
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Info Section -->
        <div style="background: #f8f9fa; padding: 2rem; border-radius: 12px; margin: 3rem 0;">
            <h3 style="color: #2c3e50; margin-bottom: 1rem;">How It Works</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                <div>
                    <h4 style="color: #667eea;">1. Browse Sales</h4>
                    <p style="color: #6c757d;">View current estate sales and browse available items with photos and descriptions.</p>
                </div>
                <div>
                    <h4 style="color: #667eea;">2. Submit Claims</h4>
                    <p style="color: #6c757d;">Register as a buyer and submit claims on items you're interested in purchasing.</p>
                </div>
                <div>
                    <h4 style="color: #667eea;">3. Get Selected</h4>
                    <p style="color: #6c757d;">Sellers review claims and select buyers. You'll be notified if your claim is accepted.</p>
                </div>
                <div>
                    <h4 style="color: #667eea;">4. Complete Purchase</h4>
                    <p style="color: #6c757d;">Coordinate with the seller to complete your purchase at the estate sale.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh the page every 5 minutes to update time remaining
        setInterval(() => {
            window.location.reload();
        }, 300000);
        
        // Update time remaining every minute
        setInterval(() => {
            const timeElements = document.querySelectorAll('.time-remaining');
            timeElements.forEach(el => {
                // This would need more complex logic to update in real-time
                // For now, we just refresh the page every 5 minutes
            });
        }, 60000);
    </script>
</body>
</html>