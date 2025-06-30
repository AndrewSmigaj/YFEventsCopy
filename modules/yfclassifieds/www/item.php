<?php
/**
 * YF Classifieds - Item Detail Page
 * 
 * Shows individual classified item with photos and details
 */

require_once __DIR__ . '/../../../config/database.php';

$itemId = intval($_GET['id'] ?? 0);

if ($itemId <= 0) {
    header('Location: index.php');
    exit;
}

// Track view (simple update instead of stored procedure)
try {
    $pdo->exec("UPDATE yfc_items SET views = views + 1 WHERE id = $itemId");
} catch (Exception $e) {
    // Views column might not exist, ignore
}

// Get item details
$stmt = $pdo->prepare("
    SELECT i.*, c.name as category_name
    FROM yfc_items i
    LEFT JOIN yfc_categories c ON i.category_id = c.id
    WHERE i.id = ? AND i.listing_type = 'classified' AND i.status = 'active'
");
$stmt->execute([$itemId]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    header('Location: index.php');
    exit;
}

// Get photos
$photoStmt = $pdo->prepare("SELECT * FROM yfc_item_photos WHERE item_id = ? ORDER BY photo_order");
$photoStmt->execute([$itemId]);
$photos = $photoStmt->fetchAll(PDO::FETCH_ASSOC);

// Get seller info (if available)
$seller = null;
try {
    $sellerStmt = $pdo->prepare("SELECT s.* FROM yfc_sales sale JOIN yfc_sellers s ON sale.seller_id = s.id WHERE sale.id = ?");
    $sellerStmt->execute([$item['sale_id']]);
    $seller = $sellerStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore if tables don't exist
}

$price = $item['price'] ?: $item['starting_price'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($item['title']) ?> - YF Classifieds</title>
    
    <!-- Open Graph tags for social sharing -->
    <meta property="og:title" content="<?= htmlspecialchars($item['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars(substr($item['description'], 0, 150)) ?>">
    <meta property="og:type" content="product">
    <meta property="og:url" content="<?= htmlspecialchars("https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}") ?>">
    <?php if (!empty($photos)): ?>
        <meta property="og:image" content="https://<?= $_SERVER['HTTP_HOST'] ?><?= htmlspecialchars($photos[0]['photo_url']) ?>">
    <?php endif; ?>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
        }
        
        .item-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        
        .photo-carousel {
            background: #f8f9fa;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .carousel-item img {
            width: 100%;
            height: 400px;
            object-fit: cover;
        }
        
        .no-photo {
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            color: #6c757d;
            font-size: 4rem;
        }
        
        .price-display {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--success-color);
            margin-bottom: 1rem;
        }
        
        .item-meta {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .contact-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 2rem;
        }
        
        .share-buttons {
            display: flex;
            gap: 10px;
            margin-top: 2rem;
        }
        
        .share-btn {
            flex: 1;
            padding: 10px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background: white;
            color: #495057;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s;
        }
        
        .share-btn:hover {
            background: #f8f9fa;
            border-color: var(--secondary-color);
            color: var(--secondary-color);
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
        }
        
        .views-counter {
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="item-header">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white">All Items</a></li>
                    <?php if ($item['category_name']): ?>
                        <li class="breadcrumb-item"><span class="text-white-50"><?= htmlspecialchars($item['category_name']) ?></span></li>
                    <?php endif; ?>
                    <li class="breadcrumb-item active text-white"><?= htmlspecialchars($item['title']) ?></li>
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="container">
        <div class="row">
            <!-- Photos -->
            <div class="col-lg-8">
                <?php if (!empty($photos)): ?>
                    <div class="photo-carousel">
                        <div id="photoCarousel" class="carousel slide" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php foreach ($photos as $index => $photo): ?>
                                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>">
                                        <img src="<?= htmlspecialchars($photo['photo_url']) ?>" 
                                             class="d-block w-100" alt="<?= htmlspecialchars($item['title']) ?>">
                                        <?php if ($photo['caption']): ?>
                                            <div class="carousel-caption d-none d-md-block">
                                                <p><?= htmlspecialchars($photo['caption']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (count($photos) > 1): ?>
                                <button class="carousel-control-prev" type="button" data-bs-target="#photoCarousel" data-bs-slide="prev">
                                    <span class="carousel-control-prev-icon"></span>
                                    <span class="visually-hidden">Previous</span>
                                </button>
                                <button class="carousel-control-next" type="button" data-bs-target="#photoCarousel" data-bs-slide="next">
                                    <span class="carousel-control-next-icon"></span>
                                    <span class="visually-hidden">Next</span>
                                </button>
                                
                                <!-- Photo indicators -->
                                <div class="carousel-indicators">
                                    <?php foreach ($photos as $index => $photo): ?>
                                        <button type="button" data-bs-target="#photoCarousel" 
                                                data-bs-slide-to="<?= $index ?>" 
                                                <?= $index === 0 ? 'class="active"' : '' ?>></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="photo-carousel">
                        <div class="no-photo">
                            <i class="bi bi-image"></i>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Description -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Description</h5>
                        <p class="card-text"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
                        
                        <?php if ($item['condition_notes']): ?>
                            <h6 class="mt-4">Condition Notes</h6>
                            <p class="text-muted"><?= nl2br(htmlspecialchars($item['condition_notes'])) ?></p>
                        <?php endif; ?>
                        
                        <?php if ($item['measurements']): ?>
                            <h6 class="mt-4">Measurements</h6>
                            <p class="text-muted"><?= nl2br(htmlspecialchars($item['measurements'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="contact-section">
                    <h1 class="h4 mb-3"><?= htmlspecialchars($item['title']) ?></h1>
                    
                    <div class="price-display">
                        $<?= number_format($price, 2) ?>
                    </div>
                    
                    <?php if ($item['category_name']): ?>
                        <p class="text-muted mb-3">
                            <i class="bi bi-tag"></i> <?= htmlspecialchars($item['category_name']) ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if (isset($item['views']) && $item['views'] > 0): ?>
                        <p class="views-counter mb-3">
                            <i class="bi bi-eye"></i> <?= number_format($item['views']) ?> views
                        </p>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <!-- Contact Information -->
                    <h6>Contact for Pickup</h6>
                    <?php if ($seller): ?>
                        <p class="mb-2"><strong><?= htmlspecialchars($seller['company_name']) ?></strong></p>
                        <?php if ($seller['phone']): ?>
                            <p class="mb-2">
                                <i class="bi bi-telephone"></i> 
                                <a href="tel:<?= htmlspecialchars($seller['phone']) ?>"><?= htmlspecialchars($seller['phone']) ?></a>
                            </p>
                        <?php endif; ?>
                        <?php if ($seller['email']): ?>
                            <p class="mb-2">
                                <i class="bi bi-envelope"></i> 
                                <a href="mailto:<?= htmlspecialchars($seller['email']) ?>"><?= htmlspecialchars($seller['email']) ?></a>
                            </p>
                        <?php endif; ?>
                        <?php if ($seller['address']): ?>
                            <p class="mb-3">
                                <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($seller['address']) ?>
                                <?php if ($seller['city'] && $seller['state']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($seller['city']) ?>, <?= htmlspecialchars($seller['state']) ?></small>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="mb-3">
                            <i class="bi bi-geo-alt"></i> Available for pickup in Yakima
                        </p>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <?php if ($seller && $seller['phone']): ?>
                            <a href="tel:<?= htmlspecialchars($seller['phone']) ?>" class="btn btn-success btn-lg">
                                <i class="bi bi-telephone"></i> Call Now
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($seller && $seller['email']): ?>
                            <a href="mailto:<?= htmlspecialchars($seller['email']) ?>?subject=Interested in <?= urlencode($item['title']) ?>" 
                               class="btn btn-primary">
                                <i class="bi bi-envelope"></i> Send Email
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Share Buttons -->
                    <div class="share-buttons">
                        <a href="#" class="share-btn" onclick="shareItem('facebook'); return false;" title="Share on Facebook">
                            <i class="bi bi-facebook"></i>
                        </a>
                        <a href="#" class="share-btn" onclick="shareItem('twitter'); return false;" title="Share on Twitter">
                            <i class="bi bi-twitter"></i>
                        </a>
                        <a href="#" class="share-btn" onclick="shareItem('whatsapp'); return false;" title="Share on WhatsApp">
                            <i class="bi bi-whatsapp"></i>
                        </a>
                        <a href="#" class="share-btn" onclick="shareItem('email'); return false;" title="Share via Email">
                            <i class="bi bi-envelope"></i>
                        </a>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-clock"></i> Listed <?= date('M j, Y', strtotime($item['created_at'])) ?>
                        </small>
                    </div>
                </div>
                
                <!-- Similar Items -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0">More Items</h6>
                    </div>
                    <div class="card-body">
                        <a href="index.php" class="btn btn-outline-primary btn-sm w-100">
                            <i class="bi bi-grid"></i> Browse All Items
                        </a>
                        <?php if ($item['category_name']): ?>
                            <a href="index.php?category=<?= urlencode(strtolower(str_replace(' ', '-', $item['category_name']))) ?>" 
                               class="btn btn-outline-secondary btn-sm w-100 mt-2">
                                <i class="bi bi-tag"></i> More in <?= htmlspecialchars($item['category_name']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="mt-5 py-4 bg-light">
        <div class="container text-center">
            <p class="text-muted mb-0">© <?= date('Y') ?> YF Classifieds - Part of YakimaFinds.com</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function shareItem(platform) {
            const url = encodeURIComponent(window.location.href);
            const title = encodeURIComponent('<?= addslashes($item['title']) ?> - $<?= number_format($price, 2) ?>');
            let shareUrl = '';
            
            switch(platform) {
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?url=${url}&text=${title}`;
                    break;
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${title}%20${url}`;
                    break;
                case 'email':
                    shareUrl = `mailto:?subject=${title}&body=Check out this item: ${url}`;
                    break;
            }
            
            if (platform === 'email') {
                window.location.href = shareUrl;
            } else {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
            
            // Track share
            fetch(`api/track-share.php?item_id=<?= $itemId ?>&platform=${platform}`, { 
                method: 'POST' 
            }).catch(() => {}); // Ignore errors
        }
    </script>
</body>
</html>