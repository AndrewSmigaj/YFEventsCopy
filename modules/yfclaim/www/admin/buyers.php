<?php
// YFClaim Buyers Management
require_once '../../../../config/database.php';
require_once '../../src/Models/BuyerModel.php';

// Authentication check
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$isAdmin = true;

// Initialize models
$buyerModel = new BuyerModel($pdo);

// Handle actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'create_buyer':
                    $data = [
                        'name' => $_POST['name'],
                        'email' => $_POST['email'],
                        'phone' => $_POST['phone'] ?? null,
                        'fb_name' => $_POST['fb_name'] ?? null,
                        'fb_profile_url' => $_POST['fb_profile_url'] ?? null,
                        'status' => 'active'
                    ];
                    $buyerId = $buyerModel->create($data);
                    $message = "Buyer created successfully!";
                    break;
                    
                case 'update_buyer':
                    $data = [
                        'name' => $_POST['name'],
                        'email' => $_POST['email'],
                        'phone' => $_POST['phone'] ?? null,
                        'fb_name' => $_POST['fb_name'] ?? null,
                        'fb_profile_url' => $_POST['fb_profile_url'] ?? null,
                        'status' => $_POST['status']
                    ];
                    $buyerModel->update($_POST['buyer_id'], $data);
                    $message = "Buyer updated successfully!";
                    break;
                    
                case 'delete_buyer':
                    // Check if buyer has offers
                    $hasOffers = $pdo->prepare("SELECT COUNT(*) FROM yfclaim_offers WHERE buyer_id = ?");
                    $hasOffers->execute([$_POST['buyer_id']]);
                    if ($hasOffers->fetchColumn() > 0) {
                        $error = "Cannot delete buyer with existing offers!";
                    } else {
                        $pdo->prepare("DELETE FROM yfclaim_buyers WHERE id = ?")->execute([$_POST['buyer_id']]);
                        $message = "Buyer deleted successfully!";
                    }
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
$query = "SELECT b.*, 
          (SELECT COUNT(*) FROM yfclaim_offers WHERE buyer_id = b.id) as total_offers,
          (SELECT COUNT(*) FROM yfclaim_offers WHERE buyer_id = b.id AND status = 'pending') as pending_offers,
          (SELECT COUNT(*) FROM yfclaim_offers WHERE buyer_id = b.id AND status = 'accepted') as accepted_offers
          FROM yfclaim_buyers b WHERE 1=1";
$params = [];

if ($status) {
    $query .= " AND b.status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND (b.name LIKE ? OR b.email LIKE ? OR b.fb_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$buyers = $stmt->fetchAll();

// Get buyer for edit modal
$editBuyer = null;
if (isset($_GET['edit'])) {
    $editBuyer = $buyerModel->findById($_GET['edit']);
}

// Get statistics
$stats = [];
$stats['total_buyers'] = count($buyers);
$stats['active_buyers'] = count(array_filter($buyers, fn($b) => $b['status'] === 'active'));
$stats['total_offers'] = array_sum(array_column($buyers, 'total_offers'));
$stats['pending_offers'] = array_sum(array_column($buyers, 'pending_offers'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Buyers - YFClaim Admin</title>
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
        .status.suspended {
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
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 3px;
            box-sizing: border-box;
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
        .badge.pending {
            background: #ffc107;
            color: #000;
        }
        .badge.accepted {
            background: #28a745;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Manage Buyers</h1>
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
            <a href="/modules/yfclaim/www/admin/offers.php">Manage Offers</a>
            <a href="/modules/yfclaim/www/admin/buyers.php" class="active">Manage Buyers</a>
            <a href="/modules/yfclaim/www/admin/reports.php">Reports</a>
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_buyers'] ?></div>
                <div class="stat-label">Total Buyers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['active_buyers'] ?></div>
                <div class="stat-label">Active Buyers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_offers'] ?></div>
                <div class="stat-label">Total Offers</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['pending_offers'] ?></div>
                <div class="stat-label">Pending Offers</div>
            </div>
        </div>
        
        <div class="section">
            <div class="section-header">
                <h2>Buyers</h2>
                <button class="btn btn-primary" onclick="showCreateModal()">+ Add New Buyer</button>
            </div>
            
            <form method="get" class="filters">
                <input type="text" name="search" placeholder="Search buyers..." value="<?= htmlspecialchars($search) ?>">
                <select name="status">
                    <option value="">All Status</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="/modules/yfclaim/www/admin/buyers.php" class="btn">Clear</a>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>FB Name</th>
                        <th>Offers</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buyers as $buyer): ?>
                    <tr>
                        <td><?= $buyer['id'] ?></td>
                        <td>
                            <?= htmlspecialchars($buyer['name']) ?>
                            <?php if ($buyer['pending_offers'] > 0): ?>
                                <span class="badge pending"><?= $buyer['pending_offers'] ?> pending</span>
                            <?php endif; ?>
                            <?php if ($buyer['accepted_offers'] > 0): ?>
                                <span class="badge accepted"><?= $buyer['accepted_offers'] ?> accepted</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($buyer['email']) ?></td>
                        <td><?= htmlspecialchars($buyer['phone'] ?? '-') ?></td>
                        <td>
                            <?php if ($buyer['fb_name']): ?>
                                <?= htmlspecialchars($buyer['fb_name']) ?>
                                <?php if ($buyer['fb_profile_url']): ?>
                                    <a href="<?= htmlspecialchars($buyer['fb_profile_url']) ?>" target="_blank" style="color: #007bff; text-decoration: none;">↗</a>
                                <?php endif; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= $buyer['total_offers'] ?></td>
                        <td>
                            <span class="status <?= $buyer['status'] ?>">
                                <?= ucfirst($buyer['status']) ?>
                            </span>
                        </td>
                        <td><?= date('M d, Y', strtotime($buyer['created_at'])) ?></td>
                        <td>
                            <div class="actions">
                                <button onclick="editBuyer(<?= $buyer['id'] ?>)" class="btn btn-primary" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">Edit</button>
                                <form method="post" style="margin: 0;">
                                    <input type="hidden" name="buyer_id" value="<?= $buyer['id'] ?>">
                                    <button type="submit" name="action" value="delete_buyer" class="btn btn-danger" 
                                            style="padding: 0.25rem 0.75rem; font-size: 0.875rem;"
                                            onclick="return confirm('Delete this buyer? This cannot be undone.')">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Create/Edit Modal -->
    <div id="buyerModal" class="modal <?= $editBuyer ? 'active' : '' ?>">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><?= $editBuyer ? 'Edit Buyer' : 'Create New Buyer' ?></h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="post" id="buyerForm">
                <input type="hidden" name="action" value="<?= $editBuyer ? 'update_buyer' : 'create_buyer' ?>">
                <input type="hidden" name="buyer_id" value="<?= $editBuyer['id'] ?? '' ?>">
                
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($editBuyer['name'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($editBuyer['email'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($editBuyer['phone'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Facebook Name</label>
                    <input type="text" name="fb_name" value="<?= htmlspecialchars($editBuyer['fb_name'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label>Facebook Profile URL</label>
                    <input type="url" name="fb_profile_url" value="<?= htmlspecialchars($editBuyer['fb_profile_url'] ?? '') ?>">
                </div>
                
                <?php if ($editBuyer): ?>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?= $editBuyer['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="suspended" <?= $editBuyer['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <?= $editBuyer ? 'Update Buyer' : 'Create Buyer' ?>
                    </button>
                    <button type="button" class="btn" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create New Buyer';
            document.getElementById('buyerForm').reset();
            document.querySelector('[name="action"]').value = 'create_buyer';
            document.querySelector('[name="buyer_id"]').value = '';
            document.getElementById('buyerModal').classList.add('active');
        }
        
        function editBuyer(id) {
            window.location.href = '?edit=' + id;
        }
        
        function closeModal() {
            document.getElementById('buyerModal').classList.remove('active');
            if (window.location.search.includes('edit=')) {
                window.location.href = '/modules/yfclaim/www/admin/buyers.php';
            }
        }
        
        // Close modal on outside click
        document.getElementById('buyerModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>