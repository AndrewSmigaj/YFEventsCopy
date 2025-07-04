<?php
// YFClaim - Item Detail & Contact Seller
require_once '../../../vendor/autoload.php';
require_once '../../../config/db_connection.php';

use YFEvents\Modules\YFClaim\Models\ItemModel;
use YFEvents\Modules\YFClaim\Models\SaleModel;

// Initialize models
$itemModel = new ItemModel($pdo);
$saleModel = new SaleModel($pdo);

// Get parameters
$itemId = $_GET['id'] ?? 0;

if (!$itemId) {
    header('Location: /claims');
    exit;
}

// Get item details with images
$item = $itemModel->getWithImages($itemId);
if (!$item) {
    header('Location: /claims');
    exit;
}

// Get sale details
$sale = $saleModel->getWithSeller($item['sale_id']);
if (!$sale) {
    header('Location: /claims');
    exit;
}

// Check if sale is active
$isActive = $saleModel->isActive($sale['id']);
$price = isset($item['price']) ? $item['price'] : $item['starting_price'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['title']) ?> - YFClaim</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; color: #333; line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .breadcrumb { margin-bottom: 20px; }
        .breadcrumb a { color: #667eea; text-decoration: none; }
        .breadcrumb a:hover { text-decoration: underline; }
        .item-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .item-images { position: relative; }
        .main-image { width: 100%; height: 400px; object-fit: contain; background: #f9f9f9; border-radius: 8px; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; }
        .main-image img { max-width: 100%; max-height: 100%; }
        .no-image { color: #999; font-size: 48px; }
        .image-thumbnails { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .thumbnail { width: 100%; height: 80px; object-fit: cover; border-radius: 4px; cursor: pointer; border: 2px solid transparent; transition: border-color 0.3s; }
        .thumbnail:hover { border-color: #667eea; }
        .item-details h1 { font-size: 2rem; margin-bottom: 15px; color: #2c3e50; }
        .price { font-size: 2.5rem; color: #27ae60; font-weight: bold; margin-bottom: 20px; }
        .item-meta { margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid #e9ecef; }
        .meta-item { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f5f5f5; }
        .meta-label { color: #6c757d; font-weight: 500; }
        .description { margin-bottom: 30px; }
        .description h3 { margin-bottom: 15px; color: #2c3e50; }
        .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600; }
        .status-available { background: #d4edda; color: #155724; }
        .status-sold { background: #f8d7da; color: #721c24; }
        .contact-section { background: #f8f9fa; padding: 30px; border-radius: 8px; }
        .contact-section h3 { margin-bottom: 20px; color: #2c3e50; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: #495057; }
        .form-input { width: 100%; padding: 12px 15px; border: 1px solid #ced4da; border-radius: 6px; font-size: 16px; transition: border-color 0.3s; }
        .form-input:focus { outline: none; border-color: #667eea; }
        .form-input[type="textarea"] { resize: vertical; min-height: 120px; }
        .btn { padding: 12px 30px; border: none; border-radius: 6px; font-size: 16px; font-weight: 500; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5a67d8; }
        .btn-secondary { background: #6c757d; color: white; margin-right: 10px; }
        .btn-secondary:hover { background: #5a6268; }
        .sale-info { background: #e9ecef; padding: 20px; border-radius: 8px; margin-top: 30px; }
        .sale-info h4 { margin-bottom: 10px; }
        .loading { display: none; text-align: center; padding: 20px; }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        @media (max-width: 768px) {
            .item-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="/claims">Sales</a> /
            <a href="/claims/sale?id=<?= $sale['id'] ?>"><?= htmlspecialchars($sale['title']) ?></a> /
            <?= htmlspecialchars($item['title']) ?>
        </div>

        <div class="item-grid">
            <div class="item-images">
                <div class="main-image">
                    <?php if (!empty($item['images'])): ?>
                        <img src="/uploads/yfclaim/items/<?= htmlspecialchars($item['images'][0]['filename']) ?>" alt="<?= htmlspecialchars($item['title']) ?>">
                    <?php else: ?>
                        <div class="no-image">📦</div>
                    <?php endif; ?>
                </div>
                <?php if (count($item['images']) > 1): ?>
                <div class="image-thumbnails">
                    <?php foreach ($item['images'] as $image): ?>
                        <img src="/uploads/yfclaim/items/<?= htmlspecialchars($image['filename']) ?>" class="thumbnail" alt="">
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="item-details">
                <h1><?= htmlspecialchars($item['title']) ?></h1>
                <div class="price">$<?= number_format($price, 2) ?></div>
                
                <div class="item-meta">
                    <div class="meta-item">
                        <span class="meta-label">Item Number:</span>
                        <span><?= htmlspecialchars($item['item_number'] ?: 'N/A') ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Category:</span>
                        <span><?= htmlspecialchars($item['category'] ?: 'General') ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Condition:</span>
                        <span><?= htmlspecialchars($item['condition_rating'] ?: 'Used') ?></span>
                    </div>
                    <div class="meta-item">
                        <span class="meta-label">Status:</span>
                        <span class="status-badge status-<?= $item['status'] ?>">
                            <?= $item['status'] === 'available' ? 'Available' : 'Sold' ?>
                        </span>
                    </div>
                </div>

                <?php if ($item['description']): ?>
                <div class="description">
                    <h3>Description</h3>
                    <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($item['status'] === 'available' && $isActive): ?>
                <div class="contact-section">
                    <h3>Contact Seller About This Item</h3>
                    <div id="alert-container"></div>
                    <form id="contact-form">
                        <input type="hidden" id="item-id" value="<?= $item['id'] ?>">
                        <div class="form-group">
                            <label class="form-label" for="buyer-name">Your Name *</label>
                            <input type="text" id="buyer-name" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="buyer-email">Your Email *</label>
                            <input type="email" id="buyer-email" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="buyer-phone">Phone Number (optional)</label>
                            <input type="tel" id="buyer-phone" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="message">Message *</label>
                            <textarea id="message" class="form-input" rows="4" required placeholder="I'm interested in this item..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                    <div id="loading" class="loading">Sending your message...</div>
                </div>
                <?php elseif ($item['status'] !== 'available'): ?>
                <div class="alert alert-error">
                    This item is no longer available.
                </div>
                <?php elseif (!$isActive): ?>
                <div class="alert alert-error">
                    This sale has ended.
                </div>
                <?php endif; ?>

                <div class="sale-info">
                    <h4>Sale Information</h4>
                    <p><strong><?= htmlspecialchars($sale['title']) ?></strong></p>
                    <p>by <?= htmlspecialchars($sale['company_name']) ?></p>
                    <p>Pickup: <?= date('M j, g:i A', strtotime($sale['pickup_start'])) ?> - <?= date('M j, g:i A', strtotime($sale['pickup_end'])) ?></p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('contact-form')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const alertContainer = document.getElementById('alert-container');
            const loading = document.getElementById('loading');
            
            alertContainer.innerHTML = '';
            loading.style.display = 'block';
            
            try {
                const response = await fetch('/api/claims/contact', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        item_id: document.getElementById('item-id').value,
                        buyer_name: document.getElementById('buyer-name').value,
                        buyer_email: document.getElementById('buyer-email').value,
                        buyer_phone: document.getElementById('buyer-phone').value,
                        message: document.getElementById('message').value
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alertContainer.innerHTML = '<div class="alert alert-success">' + result.message + '</div>';
                    this.reset();
                } else {
                    alertContainer.innerHTML = '<div class="alert alert-error">' + (result.error || 'Failed to send message') + '</div>';
                }
            } catch (error) {
                alertContainer.innerHTML = '<div class="alert alert-error">Network error. Please try again.</div>';
            } finally {
                loading.style.display = 'none';
            }
        });
        
        // Image gallery
        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.addEventListener('click', function() {
                document.querySelector('.main-image img').src = this.src;
            });
        });
    </script>
</body>
</html>