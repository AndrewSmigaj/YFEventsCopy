<?php
// YFClaim Offers Management
require_once '../../../../config/database.php';
require_once '../../src/Models/OfferModel.php';
require_once '../../src/Models/ItemModel.php';
require_once '../../src/Models/BuyerModel.php';

// Authentication check
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$isAdmin = true;

// Initialize models
$offerModel = new OfferModel($pdo);
$itemModel = new ItemModel($pdo);
$buyerModel = new BuyerModel($pdo);

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'update_offer_status':
                    $offerModel->update($_POST['offer_id'], ['status' => $_POST['status']]);
                    $message = "Offer status updated!";
                    break;
                    
                case 'delete_offer':
                    $pdo->prepare("DELETE FROM yfclaim_offers WHERE id = ?")->execute([$_POST['offer_id']]);
                    $message = "Offer deleted!";
                    break;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Get filter parameters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$query = "SELECT o.*, 
          i.title as item_title, i.price as item_price, i.status as item_status,
          s.title as sale_title, s.id as sale_id,
          b.name as buyer_name, b.email as buyer_email, b.phone as buyer_phone,
          sel.name as seller_name
          FROM yfclaim_offers o
          LEFT JOIN yfclaim_items i ON o.item_id = i.id
          LEFT JOIN yfclaim_sales s ON i.sale_id = s.id
          LEFT JOIN yfclaim_buyers b ON o.buyer_id = b.id
          LEFT JOIN yfclaim_sellers sel ON s.seller_id = sel.id
          WHERE 1=1";
$params = [];

if ($status) {
    $query .= " AND o.status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND (i.title LIKE ? OR b.name LIKE ? OR s.title LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$offers = $stmt->fetchAll();

// Get statistics
$stats = [];
$stats['total_offers'] = $pdo->query("SELECT COUNT(*) FROM yfclaim_offers")->fetchColumn();
$stats['pending_offers'] = $pdo->query("SELECT COUNT(*) FROM yfclaim_offers WHERE status = 'pending'")->fetchColumn();
$stats['accepted_offers'] = $pdo->query("SELECT COUNT(*) FROM yfclaim_offers WHERE status = 'accepted'")->fetchColumn();
$stats['rejected_offers'] = $pdo->query("SELECT COUNT(*) FROM yfclaim_offers WHERE status = 'rejected'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Offers - YFClaim Admin</title>
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-box {
            background: white;
            padding: 1.5rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            color: #666;
            font-size: 0.875rem;
            text-transform: uppercase;
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
            align-items: center;
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
        .price {
            font-weight: bold;
            color: #28a745;
        }
        .offer-info {
            font-size: 0.875rem;
            color: #666;
        }
        select.inline {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border: 1px solid #ddd;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Manage Offers</h1>
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
            <a href="/modules/yfclaim/www/admin/sales.php">Manage Sales</a>
            <a href="/modules/yfclaim/www/admin/offers.php" class="active">Manage Offers</a>
            <a href="/modules/yfclaim/www/admin/buyers.php">Manage Buyers</a>
            <a href="/modules/yfclaim/www/admin/reports.php">Reports</a>
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_offers'] ?></div>
                <div class="stat-label">Total Offers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['pending_offers'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['accepted_offers'] ?></div>
                <div class="stat-label">Accepted</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['rejected_offers'] ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-header">
                <h2>Offers</h2>
            </div>
            
            <form method="get" class="filters">
                <input type="text" name="search" placeholder="Search offers..." value="<?= htmlspecialchars($search) ?>">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="accepted" <?= $status === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="/modules/yfclaim/www/admin/offers.php" class="btn">Clear</a>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Item</th>
                        <th>Sale</th>
                        <th>Buyer</th>
                        <th>Offer Amount</th>
                        <th>Item Price</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offers as $offer): ?>
                    <tr>
                        <td><?= $offer['id'] ?></td>
                        <td>
                            <?= htmlspecialchars($offer['item_title']) ?>
                            <?php if ($offer['item_status'] === 'sold'): ?>
                                <span class="status rejected" style="font-size: 0.75rem; margin-left: 0.5rem;">SOLD</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/modules/yfclaim/www/admin/sales.php?id=<?= $offer['sale_id'] ?>" style="color: #007bff;">
                                <?= htmlspecialchars($offer['sale_title']) ?>
                            </a>
                            <div class="offer-info">by <?= htmlspecialchars($offer['seller_name']) ?></div>
                        </td>
                        <td>
                            <?= htmlspecialchars($offer['buyer_name']) ?>
                            <div class="offer-info"><?= htmlspecialchars($offer['buyer_email']) ?></div>
                            <?php if ($offer['buyer_phone']): ?>
                                <div class="offer-info"><?= htmlspecialchars($offer['buyer_phone']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="price">$<?= number_format($offer['amount'], 2) ?></td>
                        <td>$<?= number_format($offer['item_price'], 2) ?></td>
                        <td>
                            <span class="status <?= $offer['status'] ?>">
                                <?= ucfirst($offer['status']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y g:i A', strtotime($offer['created_at'])) ?></td>
                        <td>
                            <form method="post" class="actions" style="margin: 0;">
                                <input type="hidden" name="offer_id" value="<?= $offer['id'] ?>">
                                <select name="status" class="inline" onchange="this.form.submit()">
                                    <option value="">Change Status...</option>
                                    <option value="pending">Pending</option>
                                    <option value="accepted">Accepted</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                                <input type="hidden" name="action" value="update_offer_status">
                                <button type="submit" name="action" value="delete_offer" class="btn btn-danger" 
                                        style="padding: 0.25rem 0.75rem; font-size: 0.875rem;"
                                        onclick="return confirm('Delete this offer?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>