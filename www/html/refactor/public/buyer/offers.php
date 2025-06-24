<?php
// YFClaim Buyer Offers Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if authenticated
if (!isset($_SESSION['buyer_id'])) {
    header('Location: /refactor/buyer/auth');
    exit;
}

$basePath = '/refactor';

// Bootstrap the application
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
$container = require dirname(dirname(__DIR__)) . '/config/bootstrap.php';

// Get database connection
$pdo = $container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class)->getConnection();

// Get buyer info
$buyerId = $_SESSION['buyer_id'];
$buyerName = $_SESSION['buyer_name'] ?? 'Buyer';

// Get buyer's offers with item and sale details
$stmt = $pdo->prepare("
    SELECT 
        o.*,
        i.title as item_title,
        i.primary_image,
        i.starting_price,
        i.current_high_offer,
        i.status as item_status,
        s.title as sale_title,
        s.pickup_start,
        s.pickup_end,
        s.address as pickup_address,
        s.city as pickup_city,
        sel.company_name as seller_name,
        sel.phone as seller_phone
    FROM yfc_offers o
    JOIN yfc_items i ON o.item_id = i.item_id
    JOIN yfc_sales s ON i.sale_id = s.id
    JOIN yfc_sellers sel ON s.seller_id = sel.id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC
");
$stmt->execute([$buyerId]);
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group offers by status
$pendingOffers = [];
$winningOffers = [];
$outbidOffers = [];
$rejectedOffers = [];

foreach ($offers as $offer) {
    switch ($offer['status']) {
        case 'winning':
            $winningOffers[] = $offer;
            break;
        case 'active':
            $pendingOffers[] = $offer;
            break;
        case 'outbid':
            $outbidOffers[] = $offer;
            break;
        case 'rejected':
        case 'expired':
            $rejectedOffers[] = $offer;
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Offers - YFClaim Estate Sales</title>
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2rem;
        }
        
        .header-info {
            text-align: right;
        }
        
        .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s;
            display: inline-block;
            margin-top: 0.5rem;
        }
        
        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
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
            text-align: center;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.95rem;
        }
        
        .offers-section {
            margin-bottom: 3rem;
        }
        
        .section-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            margin: 0;
            flex: 1;
        }
        
        .section-count {
            background: #e9ecef;
            color: #495057;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
        }
        
        .offers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .offer-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .offer-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .offer-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #e9ecef;
        }
        
        .offer-content {
            padding: 1.5rem;
        }
        
        .offer-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .offer-sale {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
        
        .offer-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .offer-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
        
        .offer-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-winning {
            background: #d4edda;
            color: #155724;
        }
        
        .status-active {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-outbid {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .pickup-info {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .pickup-info h4 {
            margin: 0 0 0.5rem 0;
            color: #0c5460;
            font-size: 0.9rem;
        }
        
        .pickup-info p {
            margin: 0.25rem 0;
            font-size: 0.875rem;
            color: #495057;
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
        
        .btn-primary {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            font-weight: 600;
            transition: background 0.3s;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .header-info {
                text-align: center;
                margin-top: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div>
                <h1>🛒 My Offers</h1>
                <p>Track your estate sale item claims</p>
            </div>
            <div class="header-info">
                <p><strong><?= htmlspecialchars($buyerName) ?></strong></p>
                <a href="<?= $basePath ?>/buyer/logout" class="logout-btn">Logout</a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= count($offers) ?></div>
                <div class="stat-label">Total Offers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($winningOffers) ?></div>
                <div class="stat-label">Winning Offers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($pendingOffers) ?></div>
                <div class="stat-label">Pending Offers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">$<?= number_format(array_sum(array_column($winningOffers, 'offer_amount')), 2) ?></div>
                <div class="stat-label">Total Won</div>
            </div>
        </div>
        
        <!-- Winning Offers -->
        <?php if (!empty($winningOffers)): ?>
        <div class="offers-section">
            <div class="section-header">
                <h2 class="section-title">🏆 Winning Offers</h2>
                <span class="section-count"><?= count($winningOffers) ?></span>
            </div>
            <div class="offers-grid">
                <?php foreach ($winningOffers as $offer): ?>
                <div class="offer-card">
                    <?php if ($offer['primary_image']): ?>
                    <img src="<?= htmlspecialchars($offer['primary_image']) ?>" alt="Item" class="offer-image">
                    <?php else: ?>
                    <div class="offer-image" style="display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #dee2e6;">📦</div>
                    <?php endif; ?>
                    
                    <div class="offer-content">
                        <h3 class="offer-title"><?= htmlspecialchars($offer['item_title']) ?></h3>
                        <p class="offer-sale"><?= htmlspecialchars($offer['sale_title']) ?></p>
                        
                        <div class="offer-details">
                            <div>
                                <span class="offer-amount">$<?= number_format($offer['offer_amount'], 2) ?></span>
                            </div>
                            <span class="offer-status status-winning">Won</span>
                        </div>
                        
                        <div class="pickup-info">
                            <h4>📍 Pickup Information</h4>
                            <p><strong>When:</strong> <?= date('M j, Y g:i A', strtotime($offer['pickup_start'])) ?></p>
                            <p><strong>Where:</strong> <?= htmlspecialchars($offer['pickup_address'] . ', ' . $offer['pickup_city']) ?></p>
                            <p><strong>Seller:</strong> <?= htmlspecialchars($offer['seller_name']) ?> - <?= htmlspecialchars($offer['seller_phone']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Pending Offers -->
        <?php if (!empty($pendingOffers)): ?>
        <div class="offers-section">
            <div class="section-header">
                <h2 class="section-title">⏳ Pending Offers</h2>
                <span class="section-count"><?= count($pendingOffers) ?></span>
            </div>
            <div class="offers-grid">
                <?php foreach ($pendingOffers as $offer): ?>
                <div class="offer-card">
                    <?php if ($offer['primary_image']): ?>
                    <img src="<?= htmlspecialchars($offer['primary_image']) ?>" alt="Item" class="offer-image">
                    <?php else: ?>
                    <div class="offer-image" style="display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #dee2e6;">📦</div>
                    <?php endif; ?>
                    
                    <div class="offer-content">
                        <h3 class="offer-title"><?= htmlspecialchars($offer['item_title']) ?></h3>
                        <p class="offer-sale"><?= htmlspecialchars($offer['sale_title']) ?></p>
                        
                        <div class="offer-details">
                            <div>
                                <span class="offer-amount">$<?= number_format($offer['offer_amount'], 2) ?></span>
                                <?php if ($offer['current_high_offer'] && $offer['current_high_offer'] > $offer['offer_amount']): ?>
                                <div style="font-size: 0.875rem; color: #dc3545;">
                                    Current high: $<?= number_format($offer['current_high_offer'], 2) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <span class="offer-status status-active">Active</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Outbid Offers -->
        <?php if (!empty($outbidOffers)): ?>
        <div class="offers-section">
            <div class="section-header">
                <h2 class="section-title">📉 Outbid Offers</h2>
                <span class="section-count"><?= count($outbidOffers) ?></span>
            </div>
            <div class="offers-grid">
                <?php foreach ($outbidOffers as $offer): ?>
                <div class="offer-card" style="opacity: 0.7;">
                    <?php if ($offer['primary_image']): ?>
                    <img src="<?= htmlspecialchars($offer['primary_image']) ?>" alt="Item" class="offer-image">
                    <?php else: ?>
                    <div class="offer-image" style="display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #dee2e6;">📦</div>
                    <?php endif; ?>
                    
                    <div class="offer-content">
                        <h3 class="offer-title"><?= htmlspecialchars($offer['item_title']) ?></h3>
                        <p class="offer-sale"><?= htmlspecialchars($offer['sale_title']) ?></p>
                        
                        <div class="offer-details">
                            <div>
                                <span class="offer-amount" style="text-decoration: line-through; color: #6c757d;">
                                    $<?= number_format($offer['offer_amount'], 2) ?>
                                </span>
                                <?php if ($offer['current_high_offer']): ?>
                                <div style="font-size: 0.875rem; color: #dc3545;">
                                    Outbid at: $<?= number_format($offer['current_high_offer'], 2) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <span class="offer-status status-outbid">Outbid</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Empty State -->
        <?php if (empty($offers)): ?>
        <div class="empty-state">
            <h3>No offers yet</h3>
            <p>Start browsing estate sales and make offers on items you're interested in!</p>
            <a href="<?= $basePath ?>/claims" class="btn-primary">Browse Estate Sales</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>