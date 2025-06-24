<?php
// YFClaim Sale Details Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = '/refactor';
$saleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$saleId) {
    header('Location: ' . $basePath . '/claims');
    exit;
}

// Bootstrap the application
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';
$container = require dirname(dirname(__DIR__)) . '/config/bootstrap.php';

// Get database connection
$pdo = $container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class)->getConnection();

// Get sale details
$stmt = $pdo->prepare("
    SELECT s.*, sel.company_name, sel.email as contact_email, sel.phone as contact_phone
    FROM yfc_sales s
    LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
    WHERE s.id = ?
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    header('Location: ' . $basePath . '/claims');
    exit;
}

// Get sale items
$stmt = $pdo->prepare("
    SELECT i.*, 
           COUNT(o.id) as offer_count,
           MAX(o.offer_amount) as highest_offer
    FROM yfc_items i
    LEFT JOIN yfc_offers o ON i.id = o.item_id
    WHERE i.sale_id = ?
    AND i.status != 'removed'
    GROUP BY i.id
    ORDER BY i.featured DESC, i.sort_order ASC, i.id ASC
");
$stmt->execute([$saleId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if user is authenticated as buyer
$isBuyerAuthenticated = isset($_SESSION['buyer_id']);
$buyerId = $_SESSION['buyer_id'] ?? null;

// If buyer is authenticated, get their offers
$buyerOffers = [];
if ($isBuyerAuthenticated) {
    $stmt = $pdo->prepare("
        SELECT item_id, offer_amount, status
        FROM yfc_offers
        WHERE buyer_id = ? AND item_id IN (SELECT id FROM yfc_items WHERE sale_id = ?)
    ");
    $stmt->execute([$buyerId, $saleId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $buyerOffers[$row['item_id']] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($sale['title']) ?> - YFClaim Estate Sales</title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
        
        .sale-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0;
        }
        
        .sale-header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .sale-title {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .sale-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            opacity: 0.9;
        }
        
        .sale-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .auth-prompt {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .auth-prompt h3 {
            color: #0c5460;
            margin-bottom: 0.5rem;
        }
        
        .auth-prompt p {
            color: #495057;
            margin-bottom: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-block;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
            color: white;
        }
        
        .filters-bar {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            min-width: 200px;
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            background: white;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        
        .item-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
        }
        
        .item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .item-card.featured {
            border: 2px solid #ffc107;
        }
        
        .featured-badge {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            background: #ffc107;
            color: #000;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 10;
        }
        
        .item-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #e9ecef;
        }
        
        .item-content {
            padding: 1.5rem;
        }
        
        .item-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .item-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
            margin-bottom: 0.5rem;
        }
        
        .item-stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .item-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            color: white;
        }
        
        .offer-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .offer-active {
            background: #cce5ff;
            color: #004085;
        }
        
        .offer-winning {
            background: #d4edda;
            color: #155724;
        }
        
        .offer-outbid {
            background: #fff3cd;
            color: #856404;
        }
        
        .info-section {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .info-item h4 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .info-item p {
            color: #495057;
            margin: 0.25rem 0;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            padding: 2rem;
            position: relative;
        }
        
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .sale-title {
                font-size: 1.75rem;
            }
            
            .items-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1rem;
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
                <a href="<?= $basePath ?>/claims">← Back to Sales</a>
                <?php if ($isBuyerAuthenticated): ?>
                <a href="<?= $basePath ?>/buyer/offers">My Offers</a>
                <a href="<?= $basePath ?>/buyer/logout">Logout</a>
                <?php else: ?>
                <a href="<?= $basePath ?>/buyer/auth">Sign In</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Sale Header -->
    <header class="sale-header">
        <div class="sale-header-content">
            <h1 class="sale-title"><?= htmlspecialchars($sale['title']) ?></h1>
            <div class="sale-meta">
                <div class="sale-meta-item">
                    <span>🏪</span> <?= htmlspecialchars($sale['company_name']) ?>
                </div>
                <div class="sale-meta-item">
                    <span>📍</span> <?= htmlspecialchars(($sale['city'] ?? 'Unknown') . ', ' . ($sale['state'] ?? 'WA')) ?>
                </div>
                <div class="sale-meta-item">
                    <span>📦</span> <?= count($items) ?> Items Available
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <!-- Authentication Prompt -->
        <?php if (!$isBuyerAuthenticated): ?>
        <div class="auth-prompt">
            <h3>Ready to claim items?</h3>
            <p>Sign in to submit offers on items you're interested in</p>
            <a href="<?= $basePath ?>/buyer/auth" class="btn btn-primary">Sign In / Register</a>
        </div>
        <?php endif; ?>
        
        <!-- Sale Information -->
        <div class="info-section">
            <h3>Sale Information</h3>
            <div class="info-grid">
                <div class="info-item">
                    <h4>📅 Sale Dates</h4>
                    <p><?= date('F j', strtotime($sale['start_date'])) ?> - <?= date('F j, Y', strtotime($sale['end_date'])) ?></p>
                </div>
                <?php if ($sale['claim_start'] && $sale['claim_end']): ?>
                <div class="info-item">
                    <h4>⏰ Claiming Period</h4>
                    <p><?= date('M j g:ia', strtotime($sale['claim_start'])) ?> - <?= date('M j g:ia', strtotime($sale['claim_end'])) ?></p>
                </div>
                <?php endif; ?>
                <?php if ($sale['pickup_start']): ?>
                <div class="info-item">
                    <h4>🚚 Pickup Time</h4>
                    <p><?= date('M j g:ia', strtotime($sale['pickup_start'])) ?>
                    <?php if ($sale['pickup_end']): ?>
                        - <?= date('g:ia', strtotime($sale['pickup_end'])) ?>
                    <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <h4>📍 Location</h4>
                    <p><?= htmlspecialchars($sale['address'] ?? 'Address provided after claim acceptance') ?></p>
                    <p><?= htmlspecialchars(($sale['city'] ?? '') . ', ' . ($sale['state'] ?? 'WA') . ' ' . ($sale['zip'] ?? '')) ?></p>
                </div>
            </div>
            <?php if ($sale['description']): ?>
            <div style="margin-top: 2rem;">
                <h4>Description</h4>
                <p><?= nl2br(htmlspecialchars($sale['description'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Filters -->
        <div class="filters-bar">
            <div class="search-box">
                <input type="text" class="search-input" id="searchInput" placeholder="Search items...">
            </div>
            <select class="filter-select" id="sortSelect">
                <option value="featured">Featured First</option>
                <option value="price-low">Price: Low to High</option>
                <option value="price-high">Price: High to Low</option>
                <option value="offers">Most Offers</option>
            </select>
        </div>
        
        <!-- Items Grid -->
        <div class="items-grid" id="itemsGrid">
            <?php foreach ($items as $item): ?>
            <?php 
                $hasOffer = isset($buyerOffers[$item['id']]);
                $offerStatus = $hasOffer ? $buyerOffers[$item['id']]['status'] : '';
            ?>
            <div class="item-card <?= $item['featured'] ? 'featured' : '' ?>" 
                 data-price="<?= $item['starting_price'] ?>" 
                 data-offers="<?= $item['offer_count'] ?>"
                 data-featured="<?= $item['featured'] ?>">
                
                <?php if ($item['featured']): ?>
                <span class="featured-badge">⭐ Featured</span>
                <?php endif; ?>
                
                <?php if ($item['primary_image']): ?>
                <img src="<?= htmlspecialchars($item['primary_image']) ?>" alt="Item" class="item-image">
                <?php else: ?>
                <div class="item-image" style="display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #dee2e6;">📦</div>
                <?php endif; ?>
                
                <div class="item-content">
                    <h3 class="item-title"><?= htmlspecialchars($item['title']) ?></h3>
                    
                    <div class="item-price">$<?= number_format($item['starting_price'], 2) ?></div>
                    
                    <div class="item-stats">
                        <span>👥 <?= $item['offer_count'] ?> offers</span>
                        <?php if ($item['highest_offer'] > 0): ?>
                        <span>💰 High: $<?= number_format($item['highest_offer'], 2) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($hasOffer): ?>
                        <div style="margin-bottom: 1rem;">
                            <span class="offer-status offer-<?= $offerStatus ?>">
                                Your offer: $<?= number_format($buyerOffers[$item['id']]['offer_amount'], 2) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="item-actions">
                        <?php if ($isBuyerAuthenticated): ?>
                            <?php if ($item['status'] === 'sold'): ?>
                                <button class="btn btn-secondary btn-sm" disabled>Sold</button>
                            <?php elseif ($hasOffer): ?>
                                <button class="btn btn-secondary btn-sm" onclick="updateOffer(<?= $item['id'] ?>, <?= $buyerOffers[$item['id']]['offer_amount'] ?>)">
                                    Update Offer
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success btn-sm" onclick="makeOffer(<?= $item['id'] ?>, <?= $item['starting_price'] ?>)">
                                    Make Offer
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <a href="<?= $basePath ?>/buyer/auth" class="btn btn-primary btn-sm">Sign In to Offer</a>
                        <?php endif; ?>
                        <button class="btn btn-secondary btn-sm" onclick="viewDetails(<?= $item['id'] ?>)">Details</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Offer Modal -->
    <div class="modal" id="offerModal">
        <div class="modal-content">
            <button class="modal-close" onclick="closeModal()">&times;</button>
            <h2 id="modalTitle">Make an Offer</h2>
            <form id="offerForm">
                <input type="hidden" id="itemId">
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Your Offer Amount</label>
                    <input type="number" id="offerAmount" step="0.01" min="0.01" required
                           style="width: 100%; padding: 0.75rem; border: 2px solid #e9ecef; border-radius: 6px; font-size: 1.25rem;">
                    <p style="color: #6c757d; font-size: 0.875rem; margin-top: 0.5rem;">
                        Minimum: $<span id="minPrice">0.00</span>
                    </p>
                </div>
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">Message (Optional)</label>
                    <textarea id="offerMessage" rows="3"
                              style="width: 100%; padding: 0.75rem; border: 2px solid #e9ecef; border-radius: 6px;"
                              placeholder="Add a note to the seller..."></textarea>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">Submit Offer</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            const items = document.querySelectorAll('.item-card');
            
            items.forEach(item => {
                const title = item.querySelector('.item-title').textContent.toLowerCase();
                if (title.includes(searchTerm)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Sort functionality
        document.getElementById('sortSelect').addEventListener('change', (e) => {
            const sortBy = e.target.value;
            const grid = document.getElementById('itemsGrid');
            const items = Array.from(grid.children);
            
            items.sort((a, b) => {
                switch (sortBy) {
                    case 'price-low':
                        return parseFloat(a.dataset.price) - parseFloat(b.dataset.price);
                    case 'price-high':
                        return parseFloat(b.dataset.price) - parseFloat(a.dataset.price);
                    case 'offers':
                        return parseInt(b.dataset.offers) - parseInt(a.dataset.offers);
                    case 'featured':
                    default:
                        return parseInt(b.dataset.featured) - parseInt(a.dataset.featured);
                }
            });
            
            items.forEach(item => grid.appendChild(item));
        });
        
        // Offer modal functions
        function makeOffer(itemId, minPrice) {
            document.getElementById('modalTitle').textContent = 'Make an Offer';
            document.getElementById('itemId').value = itemId;
            document.getElementById('minPrice').textContent = minPrice.toFixed(2);
            document.getElementById('offerAmount').min = minPrice;
            document.getElementById('offerAmount').value = minPrice;
            document.getElementById('offerMessage').value = '';
            document.getElementById('offerModal').classList.add('active');
        }
        
        function updateOffer(itemId, currentAmount) {
            document.getElementById('modalTitle').textContent = 'Update Your Offer';
            document.getElementById('itemId').value = itemId;
            document.getElementById('offerAmount').value = currentAmount;
            document.getElementById('offerModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('offerModal').classList.remove('active');
        }
        
        function viewDetails(itemId) {
            // In a real implementation, show item details modal
            alert('Item details functionality coming soon!');
        }
        
        // Handle offer submission
        document.getElementById('offerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const itemId = document.getElementById('itemId').value;
            const amount = document.getElementById('offerAmount').value;
            const message = document.getElementById('offerMessage').value;
            
            try {
                const response = await fetch(`${basePath}/api/claims/offer`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        item_id: itemId,
                        offer_amount: amount,
                        message: message
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeModal();
                    alert('Offer submitted successfully!');
                    location.reload();
                } else {
                    alert(result.message || 'Failed to submit offer');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            }
        });
        
        // Close modal on outside click
        document.getElementById('offerModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                closeModal();
            }
        });
    </script>
</body>
</html>