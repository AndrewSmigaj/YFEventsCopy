<?php
/**
 * Simple YF Classifieds Admin Dashboard
 */

require_once __DIR__ . '/../../../../admin/auth_check.php';
require_once __DIR__ . '/../../../../config/database.php';

// Get basic statistics
$totalItems = 0;
$activeItems = 0;
$soldItems = 0;
$pendingItems = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM yfc_items WHERE listing_type = 'classified'");
    $totalItems = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM yfc_items WHERE listing_type = 'classified' AND status = 'active'");
    $activeItems = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM yfc_items WHERE listing_type = 'classified' AND status = 'sold'");
    $soldItems = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM yfc_items WHERE listing_type = 'classified' AND status = 'pending'");
    $pendingItems = $stmt->fetchColumn();
} catch (Exception $e) {
    // If columns don't exist, just show 0
}

// Get recent items
$recentItems = [];
try {
    $sql = "SELECT id, title, price, status, created_at FROM yfc_items WHERE listing_type = 'classified' ORDER BY created_at DESC LIMIT 10";
    $stmt = $pdo->query($sql);
    $recentItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not have all columns
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YF Classifieds Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <h1>🛍️ YF Classifieds Admin</h1>
            <p class="mb-0">Manage classified listings</p>
        </div>
    </div>
    
    <div class="container">
        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col">
                <a href="create.php" class="btn btn-primary me-2">
                    <i class="bi bi-plus"></i> Add New Item
                </a>
                <a href="upload.php" class="btn btn-success me-2">
                    <i class="bi bi-upload"></i> Upload Photos
                </a>
                <a href="../index.php" class="btn btn-info me-2" target="_blank">
                    <i class="bi bi-eye"></i> View Gallery
                </a>
                <a href="/refactor/admin/dashboard" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Main Admin
                </a>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row">
            <div class="col-md-3">
                <a href="items.php?status=all" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-number"><?= $totalItems ?></div>
                        <div>Total Items</div>
                        <small class="mt-2 d-block opacity-75">Click to view all items</small>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="items.php?status=active" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-number"><?= $activeItems ?></div>
                        <div>Active Items</div>
                        <small class="mt-2 d-block opacity-75">Click to view active items</small>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="items.php?status=sold" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-number"><?= $soldItems ?></div>
                        <div>Sold Items</div>
                        <small class="mt-2 d-block opacity-75">Click to view sold items</small>
                    </div>
                </a>
            </div>
            <div class="col-md-3">
                <a href="items.php?status=pending" class="text-decoration-none">
                    <div class="stat-card">
                        <div class="stat-number"><?= $pendingItems ?></div>
                        <div>Pending Review</div>
                        <small class="mt-2 d-block opacity-75">Click to view pending items</small>
                    </div>
                </a>
            </div>
        </div>
        
        <!-- Recent Items -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Items</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentItems)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No items found. <a href="create.php">Add your first item</a></p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentItems as $item): ?>
                                    <tr class="clickable-row" onclick="window.open('../item.php?id=<?= $item['id'] ?>', '_blank')" style="cursor: pointer;">
                                        <td><?= $item['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($item['title']) ?></strong>
                                            <br><small class="text-muted">Click to view item</small>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">
                                                $<?= number_format($item['price'], 2) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $item['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= ucfirst($item['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($item['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../item.php?id=<?= $item['id'] ?>" class="btn btn-outline-primary btn-sm" 
                                                   target="_blank" onclick="event.stopPropagation();">
                                                    <i class="bi bi-eye"></i> View
                                                </a>
                                                <a href="items.php?search=<?= urlencode($item['title']) ?>" class="btn btn-outline-secondary btn-sm"
                                                   onclick="event.stopPropagation();">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Setup -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">🚀 Quick Setup</h5>
                <p>To get started with YF Classifieds:</p>
                <ol>
                    <li><strong>Add your first item</strong> using the "Add New Item" button</li>
                    <li><strong>Upload photos</strong> for better presentation</li>
                    <li><strong>View the public gallery</strong> to see how it looks</li>
                    <li><strong>Share items</strong> on social media to increase visibility</li>
                </ol>
                
                <div class="alert alert-info mt-3">
                    <strong>📌 Pro Tip:</strong> Items with photos get 3x more views than those without!
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>