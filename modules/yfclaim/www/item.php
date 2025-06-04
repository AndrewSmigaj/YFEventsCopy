<?php
// YFClaim - Item Detail & Offer Submission
require_once '../../../config/database.php';
require_once '../../../vendor/autoload.php';

use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;
use YFEvents\Modules\YFClaim\Models\OfferModel;
use YFEvents\Modules\YFClaim\Models\BuyerModel;

// Initialize models
$itemModel = new ItemModel($pdo);
$saleModel = new SaleModel($pdo);
$offerModel = new OfferModel($pdo);
$buyerModel = new BuyerModel($pdo);

// Start session
session_start();

// Get parameters
$itemId = $_GET['id'] ?? 0;
$saleId = $_GET['sale'] ?? 0;

// Handle authentication first
$currentBuyer = null;
if (isset($_SESSION['buyer_token'])) {
    $currentBuyer = $buyerModel->validateSession($_SESSION['buyer_token']);
}

// Get item details
$item = null;
$sale = null;

if ($itemId) {
    $item = $itemModel->getWithImages($itemId);
    if ($item) {
        $sale = $saleModel->getWithSeller($item['sale_id']);
    }
} elseif ($saleId) {
    // Just showing auth form for a sale
    $sale = $saleModel->getWithSeller($saleId);
}

if (!$sale) {
    header('Location: /modules/yfclaim/www/');
    exit;
}

// Check if sale is active
$isActive = $saleModel->isActive($sale['id']);

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle authentication
        if (isset($_POST['action']) && $_POST['action'] === 'authenticate') {
            $name = $_POST['name'];
            $contact = $_POST['contact'];
            $authMethod = $_POST['auth_method'];
            
            // Check if buyer exists
            $existingBuyer = $buyerModel->findByContact($sale['id'], $contact, $authMethod);
            
            if ($existingBuyer) {
                // Resend auth code
                $authInfo = $buyerModel->resendAuthCode($existingBuyer['id']);
                $_SESSION['pending_buyer_id'] = $existingBuyer['id'];
            } else {
                // Create new buyer
                $authInfo = $buyerModel->createWithAuth($sale['id'], $name, $contact, $authMethod);
                $_SESSION['pending_buyer_id'] = $authInfo['buyer_id'];
            }
            
            $_SESSION['auth_method'] = $authMethod;
            $_SESSION['auth_contact'] = $contact;
            
            // In real implementation, send code via email/SMS
            // For demo, we'll show it
            $message = "Authentication code sent to your {$authMethod}. Code: {$authInfo['auth_code']}";
            
        } elseif (isset($_POST['action']) && $_POST['action'] === 'verify') {
            $buyerId = $_SESSION['pending_buyer_id'] ?? 0;
            $code = $_POST['auth_code'];
            
            $result = $buyerModel->verifyAuthCode($buyerId, $code);
            
            if ($result) {
                $_SESSION['buyer_token'] = $result['session_token'];
                $currentBuyer = $result['buyer'];
                unset($_SESSION['pending_buyer_id']);
                
                if ($itemId) {
                    header("Location: /modules/yfclaim/www/item.php?id={$itemId}");
                } else {
                    header("Location: /modules/yfclaim/www/sale.php?id={$saleId}");
                }
                exit;
            } else {
                $error = "Invalid or expired code. Please try again.";
            }
            
        } elseif (isset($_POST['action']) && $_POST['action'] === 'make_offer' && $currentBuyer && $item) {
            if (!$isActive) {
                $error = "This sale is not currently accepting offers.";
            } elseif ($item['status'] === 'claimed') {
                $error = "This item has already been claimed.";
            } else {
                $offerAmount = floatval($_POST['offer_amount']);
                $maxOffer = isset($_POST['max_offer']) ? floatval($_POST['max_offer']) : $offerAmount;
                
                // Validate offer amount
                if ($offerAmount < $item['starting_price']) {
                    $error = "Offer must be at least $" . number_format($item['starting_price'], 2);
                } else {
                    // Check if buyer already has an offer
                    $existingOffer = $offerModel->getBuyerOffer($item['id'], $currentBuyer['id']);
                    
                    if ($existingOffer) {
                        // Update existing offer
                        if ($offerAmount <= $existingOffer['offer_amount']) {
                            $error = "New offer must be higher than your current offer of $" . number_format($existingOffer['offer_amount'], 2);
                        } else {
                            $offerModel->updateAmount($existingOffer['id'], $offerAmount);
                            $message = "Your offer has been updated successfully!";
                        }
                    } else {
                        // Create new offer
                        $offerData = [
                            'item_id' => $item['id'],
                            'buyer_id' => $currentBuyer['id'],
                            'offer_amount' => $offerAmount,
                            'max_offer' => $maxOffer,
                            'status' => 'active',
                            'ip_address' => $_SERVER['REMOTE_ADDR'],
                            'user_agent' => $_SERVER['HTTP_USER_AGENT']
                        ];
                        
                        $offerModel->createOffer($offerData);
                        $message = "Your offer has been submitted successfully!";
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        $error = "An error occurred: " . $e->getMessage();
    }
}

// Get item statistics if viewing an item
$itemStats = null;
$offers = [];
$buyerOffer = null;

if ($item) {
    $itemStats = $offerModel->getItemStats($item['id']);
    $offers = $offerModel->getHistory($item['id']);
    
    if ($currentBuyer) {
        $buyerOffer = $offerModel->getBuyerOffer($item['id'], $currentBuyer['id']);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $item ? htmlspecialchars($item['title']) : 'Sign In' ?> - YFClaim</title>
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
        
        .back-link {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-link:hover {
            opacity: 0.8;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .auth-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .item-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .item-images {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .main-image {
            width: 100%;
            height: 400px;
            object-fit: contain;
            background: #f0f0f0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        
        .thumbnail-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
        }
        
        .thumbnail {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .thumbnail:hover,
        .thumbnail.active {
            border-color: #3498db;
        }
        
        .item-info h1 {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .item-meta {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .meta-item {
            display: flex;
            flex-direction: column;
        }
        
        .meta-label {
            font-size: 0.9rem;
            color: #7f8c8d;
        }
        
        .meta-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .price-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .current-price {
            font-size: 2rem;
            font-weight: bold;
            color: #27ae60;
            margin-bottom: 0.5rem;
        }
        
        .offer-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .stat-box {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 5px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3498db;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: #7f8c8d;
            text-transform: uppercase;
        }
        
        .description {
            margin-bottom: 2rem;
        }
        
        .description h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .offer-form {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 8px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .form-help {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-top: 0.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
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
        
        .btn-block {
            display: block;
            width: 100%;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .current-offer {
            background: #e3f2fd;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #90caf9;
        }
        
        .offer-history {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 2px solid #f0f0f0;
        }
        
        .history-item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .condition-stars {
            color: #f39c12;
            font-size: 1.2rem;
        }
        
        @media (max-width: 768px) {
            .item-detail {
                grid-template-columns: 1fr;
            }
            
            .offer-stats {
                grid-template-columns: 1fr;
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="/modules/yfclaim/www/sale.php?id=<?= $sale['id'] ?>" class="back-link">
                ← Back to <?= htmlspecialchars($sale['title']) ?>
            </a>
            <?php if ($currentBuyer): ?>
                <div>
                    Welcome, <?= htmlspecialchars($currentBuyer['name']) ?>
                    <a href="/modules/yfclaim/www/logout.php" style="color: white; margin-left: 1rem;">Logout</a>
                </div>
            <?php endif; ?>
        </div>
    </header>
    
    <main class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <?php if (!$currentBuyer): ?>
            <!-- Authentication Form -->
            <div class="auth-container">
                <h2>Sign In to Make Offers</h2>
                <p style="margin-bottom: 2rem;">Please provide your information to start making offers on items.</p>
                
                <?php if (!isset($_SESSION['pending_buyer_id'])): ?>
                    <form method="post">
                        <input type="hidden" name="action" value="authenticate">
                        
                        <div class="form-group">
                            <label>Your Name</label>
                            <input type="text" name="name" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Authentication Method</label>
                            <select name="auth_method" id="authMethod" onchange="updateContactField()">
                                <option value="email">Email</option>
                                <option value="sms">SMS/Text</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label id="contactLabel">Email Address</label>
                            <input type="text" name="contact" id="contactField" required>
                            <div class="form-help" id="contactHelp">We'll send you a verification code</div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Send Verification Code</button>
                    </form>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="action" value="verify">
                        
                        <div class="form-group">
                            <label>Enter Verification Code</label>
                            <input type="text" name="auth_code" maxlength="6" required autofocus>
                            <div class="form-help">
                                Code sent to <?= htmlspecialchars($_SESSION['auth_contact']) ?> via <?= $_SESSION['auth_method'] ?>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">Verify Code</button>
                    </form>
                    
                    <form method="post" style="margin-top: 1rem;">
                        <input type="hidden" name="action" value="authenticate">
                        <input type="hidden" name="name" value="Resend">
                        <input type="hidden" name="contact" value="<?= htmlspecialchars($_SESSION['auth_contact']) ?>">
                        <input type="hidden" name="auth_method" value="<?= $_SESSION['auth_method'] ?>">
                        <button type="submit" class="btn btn-secondary btn-block">Resend Code</button>
                    </form>
                <?php endif; ?>
            </div>
            
        <?php elseif ($item): ?>
            <!-- Item Detail -->
            <div class="item-detail">
                <div class="item-images">
                    <?php if (!empty($item['images'])): ?>
                        <img id="mainImage" 
                             src="/uploads/yfclaim/items/<?= htmlspecialchars($item['images'][0]['filename']) ?>" 
                             alt="<?= htmlspecialchars($item['title']) ?>" 
                             class="main-image">
                        
                        <?php if (count($item['images']) > 1): ?>
                            <div class="thumbnail-grid">
                                <?php foreach ($item['images'] as $index => $image): ?>
                                    <img src="/uploads/yfclaim/items/<?= htmlspecialchars($image['filename']) ?>" 
                                         alt="Image <?= $index + 1 ?>"
                                         class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                                         onclick="changeMainImage(this)">
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="main-image">No Image Available</div>
                    <?php endif; ?>
                </div>
                
                <div class="item-info">
                    <h1><?= htmlspecialchars($item['title']) ?></h1>
                    
                    <div class="item-meta">
                        <div class="meta-item">
                            <span class="meta-label">Item Number</span>
                            <span class="meta-value">#<?= htmlspecialchars($item['item_number']) ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Category</span>
                            <span class="meta-value"><?= htmlspecialchars($item['category'] ?: 'Uncategorized') ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Condition</span>
                            <span class="meta-value condition-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?= $i <= $item['condition_rating'] ? '★' : '☆' ?>
                                <?php endfor; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($item['status'] === 'claimed'): ?>
                        <div class="alert alert-error">
                            This item has been claimed and is no longer available for offers.
                        </div>
                    <?php else: ?>
                        <div class="price-section">
                            <div class="current-price">
                                Starting at $<?= number_format($item['starting_price'], 2) ?>
                            </div>
                            <?php if ($item['offer_increment'] > 0): ?>
                                <p>Minimum increment: $<?= number_format($item['offer_increment'], 2) ?></p>
                            <?php endif; ?>
                            <?php if ($item['buy_now_price'] > 0): ?>
                                <p>Buy now price: $<?= number_format($item['buy_now_price'], 2) ?></p>
                            <?php endif; ?>
                            
                            <?php if ($itemStats): ?>
                                <div class="offer-stats">
                                    <div class="stat-box">
                                        <div class="stat-value"><?= $itemStats['total_offers'] ?></div>
                                        <div class="stat-label">Total Offers</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-value">$<?= number_format($itemStats['max_offer'] ?: 0, 2) ?></div>
                                        <div class="stat-label">Highest Offer</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-value"><?= $itemStats['unique_buyers'] ?></div>
                                        <div class="stat-label">Bidders</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($item['description']): ?>
                        <div class="description">
                            <h3>Description</h3>
                            <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="details-grid">
                        <?php if ($item['dimensions']): ?>
                            <div class="detail-item">
                                <span>Dimensions:</span>
                                <span><?= htmlspecialchars($item['dimensions']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($item['weight']): ?>
                            <div class="detail-item">
                                <span>Weight:</span>
                                <span><?= htmlspecialchars($item['weight']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($isActive && $item['status'] !== 'claimed'): ?>
                        <?php if ($buyerOffer): ?>
                            <div class="current-offer">
                                <h3>Your Current Offer</h3>
                                <p>Amount: $<?= number_format($buyerOffer['offer_amount'], 2) ?></p>
                                <p>Status: <?= ucfirst($buyerOffer['status']) ?></p>
                                <p style="margin-top: 1rem;">You can increase your offer below:</p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" class="offer-form">
                            <input type="hidden" name="action" value="make_offer">
                            
                            <h3><?= $buyerOffer ? 'Update Your Offer' : 'Make an Offer' ?></h3>
                            
                            <div class="form-group">
                                <label>Offer Amount</label>
                                <input type="number" 
                                       name="offer_amount" 
                                       min="<?= $buyerOffer ? $buyerOffer['offer_amount'] + ($item['offer_increment'] ?: 0.01) : $item['starting_price'] ?>" 
                                       step="0.01" 
                                       required>
                                <div class="form-help">
                                    <?php if ($buyerOffer): ?>
                                        Must be higher than your current offer
                                    <?php else: ?>
                                        Minimum: $<?= number_format($item['starting_price'], 2) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                <?= $buyerOffer ? 'Update Offer' : 'Submit Offer' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if (!empty($offers) && $sale['show_price_ranges']): ?>
                        <div class="offer-history">
                            <h3>Offer Activity</h3>
                            <?php foreach ($offers as $offer): ?>
                                <div class="history-item">
                                    <span><?= $offer['buyer_name'] === $currentBuyer['name'] ? 'You' : 'Another buyer' ?></span>
                                    <span>$<?= number_format($offer['offer_amount'], 2) ?></span>
                                    <span><?= date('M j, g:i A', strtotime($offer['created_at'])) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        function updateContactField() {
            const method = document.getElementById('authMethod').value;
            const label = document.getElementById('contactLabel');
            const field = document.getElementById('contactField');
            const help = document.getElementById('contactHelp');
            
            if (method === 'sms') {
                label.textContent = 'Phone Number';
                field.type = 'tel';
                field.placeholder = '(555) 123-4567';
                help.textContent = "We'll send you a verification code via SMS";
            } else {
                label.textContent = 'Email Address';
                field.type = 'email';
                field.placeholder = '';
                help.textContent = "We'll send you a verification code";
            }
        }
        
        function changeMainImage(thumbnail) {
            const mainImage = document.getElementById('mainImage');
            mainImage.src = thumbnail.src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            thumbnail.classList.add('active');
        }
    </script>
</body>
</html>