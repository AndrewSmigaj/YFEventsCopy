<?php
// YFClaim Sales Management
require_once '../../../../config/database.php';
require_once '../../src/Models/SaleModel.php';
require_once '../../src/Models/SellerModel.php';
require_once '../../src/Models/ItemModel.php';
require_once '../../src/Models/OfferModel.php';

// Authentication check
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$isAdmin = true;

// Initialize models
$saleModel = new SaleModel($pdo);
$sellerModel = new SellerModel($pdo);
$itemModel = new ItemModel($pdo);
$offerModel = new OfferModel($pdo);

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'create_sale':
                    $data = [
                        'seller_id' => $_POST['seller_id'],
                        'title' => $_POST['title'],
                        'description' => $_POST['description'],
                        'pickup_location' => $_POST['pickup_location'],
                        'pickup_times' => $_POST['pickup_times'],
                        'contact_method' => $_POST['contact_method'],
                        'status' => 'active'
                    ];
                    $saleId = $saleModel->create($data);
                    $message = "Sale created successfully! Sale ID: $saleId";
                    header("Location: /modules/yfclaim/www/admin/sales.php?id=$saleId");
                    exit;
                    break;
                    
                case 'update_sale':
                    $data = [
                        'title' => $_POST['title'],
                        'description' => $_POST['description'],
                        'pickup_location' => $_POST['pickup_location'],
                        'pickup_times' => $_POST['pickup_times'],
                        'contact_method' => $_POST['contact_method'],
                        'status' => $_POST['status']
                    ];
                    $saleModel->update($_POST['sale_id'], $data);
                    $message = "Sale updated successfully!";
                    break;
                    
                case 'add_item':
                    $data = [
                        'sale_id' => $_POST['sale_id'],
                        'title' => $_POST['title'],
                        'description' => $_POST['description'],
                        'price' => $_POST['price'],
                        'quantity' => $_POST['quantity'] ?? 1,
                        'condition' => $_POST['condition'] ?? 'good',
                        'status' => 'available'
                    ];
                    $itemModel->create($data);
                    $message = "Item added successfully!";
                    break;
                    
                case 'update_item':
                    $data = [
                        'title' => $_POST['title'],
                        'description' => $_POST['description'],
                        'price' => $_POST['price'],
                        'quantity' => $_POST['quantity'],
                        'condition' => $_POST['condition'],
                        'status' => $_POST['item_status']
                    ];
                    $itemModel->update($_POST['item_id'], $data);
                    $message = "Item updated successfully!";
                    break;
                    
                case 'delete_item':
                    $pdo->prepare("DELETE FROM yfclaim_items WHERE id = ?")->execute([$_POST['item_id']]);
                    $message = "Item deleted successfully!";
                    break;
                    
                case 'delete_sale':
                    // Delete all items and offers first
                    $pdo->prepare("DELETE FROM yfclaim_offers WHERE item_id IN (SELECT id FROM yfclaim_items WHERE sale_id = ?)")->execute([$_POST['sale_id']]);
                    $pdo->prepare("DELETE FROM yfclaim_items WHERE sale_id = ?")->execute([$_POST['sale_id']]);
                    $pdo->prepare("DELETE FROM yfclaim_sales WHERE id = ?")->execute([$_POST['sale_id']]);
                    $message = "Sale deleted successfully!";
                    header("Location: /modules/yfclaim/www/admin/sales.php");
                    exit;
                    break;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get specific sale if ID provided
$sale = null;
$items = [];
$offers = [];
if (isset($_GET['id'])) {
    $sale = $saleModel->findById($_GET['id']);
    if ($sale) {
        // Get seller info
        $sale['seller'] = $sellerModel->findById($sale['seller_id']);
        
        // Get items
        $stmt = $pdo->prepare("SELECT * FROM yfclaim_items WHERE sale_id = ? ORDER BY created_at DESC");
        $stmt->execute([$sale['id']]);
        $items = $stmt->fetchAll();
        
        // Get offers for each item
        foreach ($items as &$item) {
            $stmt = $pdo->prepare("
                SELECT o.*, b.name as buyer_name, b.email as buyer_email 
                FROM yfclaim_offers o 
                LEFT JOIN yfclaim_buyers b ON o.buyer_id = b.id 
                WHERE o.item_id = ? 
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([$item['id']]);
            $item['offers'] = $stmt->fetchAll();
        }
    }
} else {
    // Get list of sales
    $status = $_GET['status'] ?? '';
    $search = $_GET['search'] ?? '';
    
    $query = "SELECT s.*, sel.name as seller_name, sel.fb_name,
              (SELECT COUNT(*) FROM yfclaim_items WHERE sale_id = s.id) as item_count,
              (SELECT COUNT(*) FROM yfclaim_offers o JOIN yfclaim_items i ON o.item_id = i.id WHERE i.sale_id = s.id) as offer_count
              FROM yfclaim_sales s 
              LEFT JOIN yfclaim_sellers sel ON s.seller_id = sel.id 
              WHERE 1=1";
    $params = [];
    
    if ($status) {
        $query .= " AND s.status = ?";
        $params[] = $status;
    }
    
    if ($search) {
        $query .= " AND (s.title LIKE ? OR s.description LIKE ? OR sel.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $query .= " ORDER BY s.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();
}

// Get active sellers for dropdown
$activeSellers = $pdo->query("SELECT id, name, email FROM yfclaim_sellers WHERE status = 'active' ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $sale ? 'Sale Details' : 'Manage Sales' ?> - YFClaim Admin</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #f5f5f5;
        }
        .header {
            background: #333;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            margin: 0;
        }
        .header-nav {
            display: flex;
            gap: 1rem;
        }
        .header-nav a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
        }
        .header-nav a:hover {
            background: rgba(255,255,255,0.2);
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        .nav {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .nav a {
            color: #007bff;
            text-decoration: none;
            padding: 0.5rem 1rem;
            background: white;
            border-radius: 3px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .nav a:hover, .nav a.active {
            background: #007bff;
            color: white;
        }
        .section {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }
        .section h2 {
            margin: 0;
            color: #333;
        }
        .filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        .filters input, .filters select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 1rem;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-warning {
            background: #ffc107;
            color: #000;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn:hover {
            opacity: 0.8;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #666;
            text-transform: uppercase;
            font-size: 0.875rem;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 3px;
            font-size: 0.875rem;
            font-weight: bold;
            display: inline-block;
        }
        .status.active {
            background: #28a745;
            color: white;
        }
        .status.closed {
            background: #6c757d;
            color: white;
        }
        .status.available {
            background: #28a745;
            color: white;
        }
        .status.sold {
            background: #dc3545;
            color: white;
        }
        .status.pending {
            background: #ffc107;
            color: #000;
        }
        .status.accepted {
            background: #28a745;
            color: white;
        }
        .status.rejected {
            background: #dc3545;
            color: white;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            border: 1px solid #f5c6cb;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 5px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .modal-header h3 {
            margin: 0;
        }
        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .badge {
            background: #6c757d;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.75rem;
            margin-left: 0.5rem;
        }
        .price {
            font-weight: bold;
            color: #28a745;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .info-item {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 3px;
        }
        .info-label {
            font-size: 0.875rem;
            color: #666;
            margin-bottom: 0.25rem;
        }
        .info-value {
            font-weight: bold;
            color: #333;
        }
        .item-card {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        .item-offers {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
        .offer-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 3px;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><?= $sale ? 'Sale Details' : 'Manage Sales' ?></h1>
        <div class="header-nav">
            <a href="/modules/yfclaim/www/admin/">Dashboard</a>
            <a href="/admin/">YFEvents Admin</a>
            <a href="/admin/logout.php">Logout</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="nav">
            <a href="/modules/yfclaim/www/admin/index.php">Dashboard</a>
            <a href="/modules/yfclaim/www/admin/sellers.php">Manage Sellers</a>
            <a href="/modules/yfclaim/www/admin/sales.php" class="active">Manage Sales</a>
            <a href="/modules/yfclaim/www/admin/offers.php">Manage Offers</a>
            <a href="/modules/yfclaim/www/admin/buyers.php">Manage Buyers</a>
            <a href="/modules/yfclaim/www/admin/reports.php">Reports</a>
        </div>
        
        <?php if ($sale): ?>
            <!-- Sale Details View -->
            <div class="section">
                <div class="section-header">
                    <h2><?= htmlspecialchars($sale['title']) ?></h2>
                    <div class="actions">
                        <button onclick="showEditSaleModal()" class="btn btn-primary">Edit Sale</button>
                        <form method="post" style="margin: 0;">
                            <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
                            <button type="submit" name="action" value="delete_sale" class="btn btn-danger" 
                                    onclick="return confirm('Delete this sale and all its items? This cannot be undone.')">Delete Sale</button>
                        </form>
                    </div>
                </div>
                
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Sale ID</div>
                        <div class="info-value">#<?= $sale['id'] ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Seller</div>
                        <div class="info-value"><?= htmlspecialchars($sale['seller']['name']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value">
                            <span class="status <?= $sale['status'] ?>"><?= ucfirst($sale['status']) ?></span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Created</div>
                        <div class="info-value"><?= date('M d, Y g:i A', strtotime($sale['created_at'])) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Pickup Location</div>
                        <div class="info-value"><?= htmlspecialchars($sale['pickup_location']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Pickup Times</div>
                        <div class="info-value"><?= htmlspecialchars($sale['pickup_times']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Contact Method</div>
                        <div class="info-value"><?= htmlspecialchars($sale['contact_method']) ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total Items</div>
                        <div class="info-value"><?= count($items) ?></div>
                    </div>
                </div>
                
                <?php if ($sale['description']): ?>
                    <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($sale['description'])) ?></p>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <div class="section-header">
                    <h2>Items</h2>
                    <button onclick="showAddItemModal()" class="btn btn-primary">+ Add Item</button>
                </div>
                
                <?php if (empty($items)): ?>
                    <p style="text-align: center; color: #666;">No items added yet</p>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                        <div class="item-card">
                            <div class="item-header">
                                <div>
                                    <h3 style="margin: 0 0 0.5rem 0;"><?= htmlspecialchars($item['title']) ?></h3>
                                    <div class="price">$<?= number_format($item['price'], 2) ?></div>
                                    <div style="margin-top: 0.5rem;">
                                        <span class="status <?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span>
                                        <span class="badge">Qty: <?= $item['quantity'] ?></span>
                                        <span class="badge">Condition: <?= ucfirst($item['condition']) ?></span>
                                    </div>
                                </div>
                                <div class="actions">
                                    <button onclick='editItem(<?= json_encode($item) ?>)' class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">Edit</button>
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
                                        <button type="submit" name="action" value="delete_item" class="btn btn-danger" 
                                                style="padding: 0.25rem 0.75rem; font-size: 0.875rem;"
                                                onclick="return confirm('Delete this item?')">Delete</button>
                                    </form>
                                </div>
                            </div>
                            
                            <?php if ($item['description']): ?>
                                <p style="margin: 1rem 0;"><?= nl2br(htmlspecialchars($item['description'])) ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['offers'])): ?>
                                <div class="item-offers">
                                    <h4 style="margin: 0 0 0.5rem 0;">Offers (<?= count($item['offers']) ?>)</h4>
                                    <?php foreach ($item['offers'] as $offer): ?>
                                        <div class="offer-item">
                                            <div>
                                                <strong><?= htmlspecialchars($offer['buyer_name']) ?></strong>
                                                <span style="color: #666; font-size: 0.875rem;">
                                                    <?= date('M d g:i A', strtotime($offer['created_at'])) ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span class="price">$<?= number_format($offer['amount'], 2) ?></span>
                                                <span class="status <?= $offer['status'] ?>" style="margin-left: 0.5rem;">
                                                    <?= ucfirst($offer['status']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center;">
                <a href="/modules/yfclaim/www/admin/sales.php" class="btn">← Back to Sales List</a>
            </div>
            
        <?php else: ?>
            <!-- Sales List View -->
            <div class="section">
                <div class="section-header">
                    <h2>Sales</h2>
                    <button class="btn btn-primary" onclick="showCreateSaleModal()">+ Create New Sale</button>
                </div>
                
                <form method="get" class="filters">
                    <input type="text" name="search" placeholder="Search sales..." value="<?= htmlspecialchars($search) ?>">
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="/modules/yfclaim/www/admin/sales.php" class="btn">Clear</a>
                </form>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Seller</th>
                            <th>Items</th>
                            <th>Offers</th>
                            <th>Pickup Location</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td><?= $sale['id'] ?></td>
                            <td><?= htmlspecialchars($sale['title']) ?></td>
                            <td><?= htmlspecialchars($sale['seller_name'] ?? $sale['fb_name'] ?? 'Unknown') ?></td>
                            <td>
                                <?= $sale['item_count'] ?>
                                <?php if ($sale['item_count'] > 0): ?>
                                    <span class="badge">items</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $sale['offer_count'] ?>
                                <?php if ($sale['offer_count'] > 0): ?>
                                    <span class="badge">offers</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($sale['pickup_location']) ?></td>
                            <td>
                                <span class="status <?= $sale['status'] ?>">
                                    <?= ucfirst($sale['status']) ?>
                                </span>
                            </td>
                            <td><?= date('M d, Y', strtotime($sale['created_at'])) ?></td>
                            <td>
                                <div class="actions">
                                    <a href="?id=<?= $sale['id'] ?>" class="btn btn-primary" 
                                       style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">View</a>
                                    <form method="post" style="margin: 0;">
                                        <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
                                        <button type="submit" name="action" value="delete_sale" class="btn btn-danger" 
                                                style="padding: 0.25rem 0.75rem; font-size: 0.875rem;"
                                                onclick="return confirm('Delete this sale and all its items?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Create/Edit Sale Modal -->
    <div id="saleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="saleModalTitle">Create New Sale</h3>
                <span class="close" onclick="closeSaleModal()">&times;</span>
            </div>
            <form method="post" id="saleForm">
                <input type="hidden" name="action" id="saleAction" value="create_sale">
                <input type="hidden" name="sale_id" id="saleId" value="">
                
                <?php if (!$sale): ?>
                <div class="form-group">
                    <label>Seller *</label>
                    <select name="seller_id" required>
                        <option value="">Select a seller...</option>
                        <?php foreach ($activeSellers as $seller): ?>
                            <option value="<?= $seller['id'] ?>"><?= htmlspecialchars($seller['name']) ?> (<?= htmlspecialchars($seller['email']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="saleTitle" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="saleDescription"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Pickup Location *</label>
                    <input type="text" name="pickup_location" id="salePickupLocation" required>
                </div>
                
                <div class="form-group">
                    <label>Pickup Times *</label>
                    <input type="text" name="pickup_times" id="salePickupTimes" placeholder="e.g., Weekdays 5-7pm, Weekends anytime" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Method *</label>
                    <select name="contact_method" id="saleContactMethod" required>
                        <option value="messenger">Facebook Messenger</option>
                        <option value="email">Email</option>
                        <option value="phone">Phone</option>
                        <option value="text">Text Message</option>
                    </select>
                </div>
                
                <?php if ($sale): ?>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="saleStatus">
                        <option value="active">Active</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span id="saleSubmitText">Create Sale</span>
                    </button>
                    <button type="button" class="btn" onclick="closeSaleModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add/Edit Item Modal -->
    <div id="itemModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="itemModalTitle">Add Item</h3>
                <span class="close" onclick="closeItemModal()">&times;</span>
            </div>
            <form method="post" id="itemForm">
                <input type="hidden" name="action" id="itemAction" value="add_item">
                <input type="hidden" name="sale_id" value="<?= $sale['id'] ?? '' ?>">
                <input type="hidden" name="item_id" id="itemId" value="">
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="itemTitle" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="itemDescription"></textarea>
                </div>
                
                <div class="form-group">
                    <label>Price *</label>
                    <input type="number" name="price" id="itemPrice" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" id="itemQuantity" min="1" value="1">
                </div>
                
                <div class="form-group">
                    <label>Condition</label>
                    <select name="condition" id="itemCondition">
                        <option value="new">New</option>
                        <option value="like_new">Like New</option>
                        <option value="good" selected>Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                    </select>
                </div>
                
                <div class="form-group" id="itemStatusGroup" style="display: none;">
                    <label>Status</label>
                    <select name="item_status" id="itemStatus">
                        <option value="available">Available</option>
                        <option value="sold">Sold</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <span id="itemSubmitText">Add Item</span>
                    </button>
                    <button type="button" class="btn" onclick="closeItemModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        <?php if ($sale): ?>
        const saleData = <?= json_encode($sale) ?>;
        
        function showEditSaleModal() {
            document.getElementById('saleModalTitle').textContent = 'Edit Sale';
            document.getElementById('saleAction').value = 'update_sale';
            document.getElementById('saleId').value = saleData.id;
            document.getElementById('saleTitle').value = saleData.title;
            document.getElementById('saleDescription').value = saleData.description || '';
            document.getElementById('salePickupLocation').value = saleData.pickup_location;
            document.getElementById('salePickupTimes').value = saleData.pickup_times;
            document.getElementById('saleContactMethod').value = saleData.contact_method;
            document.getElementById('saleStatus').value = saleData.status;
            document.getElementById('saleSubmitText').textContent = 'Update Sale';
            document.getElementById('saleModal').classList.add('active');
        }
        
        function showAddItemModal() {
            document.getElementById('itemModalTitle').textContent = 'Add Item';
            document.getElementById('itemForm').reset();
            document.getElementById('itemAction').value = 'add_item';
            document.getElementById('itemId').value = '';
            document.getElementById('itemStatusGroup').style.display = 'none';
            document.getElementById('itemSubmitText').textContent = 'Add Item';
            document.getElementById('itemModal').classList.add('active');
        }
        
        function editItem(item) {
            document.getElementById('itemModalTitle').textContent = 'Edit Item';
            document.getElementById('itemAction').value = 'update_item';
            document.getElementById('itemId').value = item.id;
            document.getElementById('itemTitle').value = item.title;
            document.getElementById('itemDescription').value = item.description || '';
            document.getElementById('itemPrice').value = item.price;
            document.getElementById('itemQuantity').value = item.quantity;
            document.getElementById('itemCondition').value = item.condition;
            document.getElementById('itemStatus').value = item.status;
            document.getElementById('itemStatusGroup').style.display = 'block';
            document.getElementById('itemSubmitText').textContent = 'Update Item';
            document.getElementById('itemModal').classList.add('active');
        }
        <?php else: ?>
        function showCreateSaleModal() {
            document.getElementById('saleModalTitle').textContent = 'Create New Sale';
            document.getElementById('saleForm').reset();
            document.getElementById('saleAction').value = 'create_sale';
            document.getElementById('saleSubmitText').textContent = 'Create Sale';
            document.getElementById('saleModal').classList.add('active');
        }
        <?php endif; ?>
        
        function closeSaleModal() {
            document.getElementById('saleModal').classList.remove('active');
        }
        
        function closeItemModal() {
            document.getElementById('itemModal').classList.remove('active');
        }
        
        // Close modals on outside click
        document.getElementById('saleModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeSaleModal();
            }
        });
        
        document.getElementById('itemModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeItemModal();
            }
        });
    </script>
</body>
</html>