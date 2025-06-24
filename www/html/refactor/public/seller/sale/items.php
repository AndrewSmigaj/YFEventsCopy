<?php
// YFClaim Sale Items Management
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if authenticated
if (!isset($_SESSION['yfclaim_seller_id'])) {
    header('Location: /refactor/seller/login');
    exit;
}

$basePath = '/refactor';
$sellerId = $_SESSION['yfclaim_seller_id'];
$sellerName = $_SESSION['yfclaim_seller_name'] ?? 'Seller';

// Get sale ID from URL path
$pathParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$saleId = 0;

// Look for sale ID in URL path (e.g., /seller/sale/123/items)
foreach ($pathParts as $i => $part) {
    if ($part === 'sale' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
        $saleId = (int)$pathParts[$i + 1];
        break;
    }
}

if (!$saleId) {
    header('Location: ' . $basePath . '/seller/dashboard');
    exit;
}

// Bootstrap the application
require_once dirname(dirname(dirname(__DIR__))) . '/vendor/autoload.php';
$container = require dirname(dirname(dirname(__DIR__))) . '/config/bootstrap.php';

// Get database connection
$pdo = $container->resolve(\YFEvents\Infrastructure\Database\ConnectionInterface::class)->getConnection();

// Verify sale belongs to seller
$stmt = $pdo->prepare("SELECT * FROM yfc_sales WHERE id = ? AND seller_id = ?");
$stmt->execute([$saleId, $sellerId]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    header('Location: ' . $basePath . '/seller/dashboard');
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
    GROUP BY i.id
    ORDER BY i.sort_order ASC, i.id ASC
");
$stmt->execute([$saleId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$stmt = $pdo->prepare("SELECT * FROM yfc_categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Items - <?= htmlspecialchars($sale['title']) ?></title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .header h1 {
            margin: 0;
            font-size: 1.75rem;
        }
        
        .header-subtitle {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .stats-bar {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
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
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .search-box {
            flex: 1;
            max-width: 400px;
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
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
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
        
        .item-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #e9ecef;
        }
        
        .item-content {
            padding: 1.5rem;
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }
        
        .item-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
            flex: 1;
        }
        
        .item-price {
            font-size: 1.25rem;
            font-weight: bold;
            color: #28a745;
        }
        
        .item-meta {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
            font-size: 0.875rem;
            color: #6c757d;
        }
        
        .item-actions {
            display: flex;
            gap: 0.5rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .status-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            background: rgba(255,255,255,0.9);
        }
        
        .status-active {
            color: #155724;
            border: 1px solid #28a745;
        }
        
        .status-sold {
            color: #721c24;
            border: 1px solid #dc3545;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: #6c757d;
            margin-bottom: 2rem;
        }
        
        /* Modal Styles */
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
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #6c757d;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-hint {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .items-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-bar {
                flex-direction: column;
                text-align: center;
            }
            
            .actions-bar {
                flex-direction: column;
            }
            
            .search-box {
                max-width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="header-top">
                <div>
                    <h1>📦 Manage Items</h1>
                    <p class="header-subtitle"><?= htmlspecialchars($sale['title']) ?></p>
                </div>
                <a href="<?= $basePath ?>/seller/dashboard" class="btn-back">← Back to Dashboard</a>
            </div>
        </div>
    </header>
    
    <div class="container">
        <!-- Statistics Bar -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-value"><?= count($items) ?></div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= array_sum(array_column($items, 'offer_count')) ?></div>
                <div class="stat-label">Total Offers</div>
            </div>
            <div class="stat-item">
                <div class="stat-value">$<?= number_format(array_sum(array_column($items, 'highest_offer')), 2) ?></div>
                <div class="stat-label">Potential Revenue</div>
            </div>
            <div class="stat-item">
                <div class="stat-value"><?= count(array_filter($items, fn($i) => $i['status'] === 'sold')) ?></div>
                <div class="stat-label">Items Sold</div>
            </div>
        </div>
        
        <!-- Actions Bar -->
        <div class="actions-bar">
            <div class="search-box">
                <input type="text" class="search-input" placeholder="Search items..." id="searchInput">
            </div>
            <button class="btn btn-primary" onclick="showAddItemModal()">+ Add Item</button>
        </div>
        
        <!-- Items Grid -->
        <?php if (!empty($items)): ?>
        <div class="items-grid" id="itemsGrid">
            <?php foreach ($items as $item): ?>
            <div class="item-card" data-item-id="<?= $item['id'] ?>">
                <?php if ($item['status'] === 'sold'): ?>
                <span class="status-badge status-sold">Sold</span>
                <?php else: ?>
                <span class="status-badge status-active">Active</span>
                <?php endif; ?>
                
                <?php if ($item['primary_image']): ?>
                <img src="<?= htmlspecialchars($item['primary_image']) ?>" alt="Item" class="item-image">
                <?php else: ?>
                <div class="item-image" style="display: flex; align-items: center; justify-content: center; font-size: 3rem; color: #dee2e6;">📦</div>
                <?php endif; ?>
                
                <div class="item-content">
                    <div class="item-header">
                        <h3 class="item-title"><?= htmlspecialchars($item['title']) ?></h3>
                    </div>
                    
                    <div class="item-price">$<?= number_format($item['starting_price'], 2) ?></div>
                    
                    <div class="item-meta">
                        <span>📊 <?= $item['offer_count'] ?> offers</span>
                        <?php if ($item['highest_offer'] > 0): ?>
                        <span>💰 High: $<?= number_format($item['highest_offer'], 2) ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="item-actions">
                        <button class="btn btn-secondary btn-sm" onclick="editItem(<?= $item['id'] ?>)">Edit</button>
                        <button class="btn btn-secondary btn-sm" onclick="viewOffers(<?= $item['id'] ?>)">View Offers</button>
                        <?php if ($item['status'] !== 'sold'): ?>
                        <button class="btn btn-danger btn-sm" onclick="deleteItem(<?= $item['id'] ?>)">Delete</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <h3>No items yet</h3>
            <p>Add items to your sale to start receiving offers!</p>
            <button class="btn btn-primary" onclick="showAddItemModal()">+ Add Your First Item</button>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Add/Edit Item Modal -->
    <div class="modal" id="itemModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add Item</h2>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="itemForm">
                <div class="modal-body">
                    <input type="hidden" id="itemId" name="item_id">
                    <input type="hidden" name="sale_id" value="<?= $saleId ?>">
                    
                    <div class="form-group">
                        <label for="title" class="form-label">Item Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-textarea"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="category_id" class="form-label">Category</label>
                        <select id="category_id" name="category_id" class="form-select">
                            <option value="">Select a category</option>
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="starting_price" class="form-label">Starting Price <span class="required">*</span></label>
                        <input type="number" id="starting_price" name="starting_price" class="form-input" 
                               step="0.01" min="0.01" required>
                        <p class="form-hint">Minimum price you'll accept for this item</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="condition_notes" class="form-label">Condition Notes</label>
                        <textarea id="condition_notes" name="condition_notes" class="form-textarea" 
                                  placeholder="Describe the item's condition..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="measurements" class="form-label">Measurements/Dimensions</label>
                        <input type="text" id="measurements" name="measurements" class="form-input" 
                               placeholder="e.g., 24\" x 36\" x 12\"">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Item</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        const saleId = <?= $saleId ?>;
        
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
        
        // Modal functions
        function showAddItemModal() {
            document.getElementById('modalTitle').textContent = 'Add Item';
            document.getElementById('itemForm').reset();
            document.getElementById('itemId').value = '';
            document.getElementById('itemModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('itemModal').classList.remove('active');
        }
        
        // Handle item form submission
        document.getElementById('itemForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            const itemId = data.item_id;
            
            try {
                const url = itemId 
                    ? `${basePath}/api/claims/seller/items/${itemId}/update`
                    : `${basePath}/api/claims/seller/items/add`;
                    
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert(result.message || 'Failed to save item');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            }
        });
        
        async function editItem(itemId) {
            // In a real implementation, fetch item details
            alert('Edit functionality coming soon!');
        }
        
        async function viewOffers(itemId) {
            // In a real implementation, show offers modal
            alert('View offers functionality coming soon!');
        }
        
        async function deleteItem(itemId) {
            if (!confirm('Are you sure you want to delete this item?')) {
                return;
            }
            
            try {
                const response = await fetch(`${basePath}/api/claims/seller/items/${itemId}/delete`, {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.querySelector(`[data-item-id="${itemId}"]`).remove();
                } else {
                    alert(result.message || 'Failed to delete item');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            }
        }
        
        // Close modal on click outside
        document.getElementById('itemModal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                closeModal();
            }
        });
    </script>
</body>
</html>