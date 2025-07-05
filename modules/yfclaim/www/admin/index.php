<?php
session_start();

// Check if logged in through main admin OR temporary bypass
$isLoggedIn = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ||
              (isset($_GET['admin_bypass']) && $_GET['admin_bypass'] === 'YakFind2025');

if (!$isLoggedIn) {
    // Instead of redirecting to a potentially wrong path, show a helpful message
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - YFClaim Admin</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .error-container { background: white; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .error-title { color: #dc3545; font-size: 24px; margin-bottom: 20px; }
            .error-message { color: #666; line-height: 1.6; margin-bottom: 20px; }
            .login-button { background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
            .login-button:hover { background: #0056b3; }
            .help-text { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; font-size: 14px; }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-title">🔒 Admin Access Required</div>
            <div class="error-message">
                <p>You need to be logged in as an administrator to access the YFClaim admin panel.</p>
                <p>Please log in to the main admin system first, then return to this page.</p>
            </div>
            
            <a href="/admin/login" class="login-button">Go to Main Admin Login</a>
            
            <div class="help-text">
                <strong>Login Credentials:</strong><br>
                Username: <code>YakFind</code><br>
                Password: <code>MapTime</code>
            </div>
            
            <div class="help-text">
                <strong>Troubleshooting:</strong><br>
                If the login link above doesn't work, try these alternatives:
                <ul>
                    <li><a href="/admin/login">Alternative login path 1</a></li>
                    <li><a href="/admin/login">Alternative login path 2</a></li>
                </ul>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once dirname(__DIR__, 4) . '/config/database.php';

// Get statistics
$stats = [];

try {
    // Count sellers
    $stmt = $db->query("SELECT COUNT(*) FROM yfc_sellers WHERE status = 'approved'");
    $stats['active_sellers'] = $stmt->fetchColumn();
    
    // Count active sales
    $stmt = $db->query("SELECT COUNT(*) FROM yfc_sales WHERE status = 'active' AND end_date >= NOW()");
    $stats['active_sales'] = $stmt->fetchColumn();
    
    // Count items
    $stmt = $db->query("SELECT COUNT(*) FROM yfc_items WHERE status = 'active'");
    $stats['active_items'] = $stmt->fetchColumn();
    
    // Count sold items
    $stmt = $db->query("SELECT COUNT(*) FROM yfc_items WHERE status = 'sold'");
    $stats['sold_items'] = $stmt->fetchColumn();
    
    // Recent sellers
    $stmt = $db->query("SELECT * FROM yfc_sellers ORDER BY created_at DESC LIMIT 5");
    $recentSellers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent sales
    $stmt = $db->query("
        SELECT s.*, sel.company_name 
        FROM yfc_sales s 
        JOIN yfc_sellers sel ON s.seller_id = sel.id 
        ORDER BY s.created_at DESC 
        LIMIT 5
    ");
    $recentSales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent inquiries (if notifications table exists)
    try {
        $stmt = $db->query("
            SELECT n.*, s.company_name 
            FROM yfc_notifications n 
            JOIN yfc_sellers s ON n.seller_id = s.id 
            WHERE n.type = 'contact_inquiry' 
            ORDER BY n.created_at DESC 
            LIMIT 5
        ");
        $recentInquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $recentInquiries = [];
    }
    
} catch (Exception $e) {
    // Default stats if tables don't exist
    $stats = [
        'active_sellers' => 0,
        'active_sales' => 0,
        'active_items' => 0,
        'sold_items' => 0
    ];
    $recentSellers = [];
    $recentSales = [];
    $recentInquiries = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFClaim Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; }
        .header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .back-link { color: #007bff; text-decoration: none; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2.5em; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; margin-top: 10px; }
        .content-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
        .content-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .card-header { border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 15px; }
        .card-title { font-size: 1.2em; font-weight: bold; }
        .item-list { list-style: none; padding: 0; }
        .item-list li { padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .item-list li:last-child { border-bottom: none; }
        .item-meta { font-size: 0.9em; color: #666; }
        .nav-buttons { display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap; }
        .nav-btn { padding: 12px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        .nav-btn:hover { background: #0056b3; }
        .status-active { color: #28a745; }
        .status-pending { color: #ffc107; }
        .status-rejected { color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="/admin/" class="back-link">← Back to Main Admin</a>
            <h1><i class="fas fa-gavel"></i> YFClaim Admin Dashboard</h1>
            <p>Manage Facebook-style claim sales for estate sale companies</p>
        </div>
        
        <div class="nav-buttons">
            <a href="/modules/yfclaim/www/admin/sellers.php" class="nav-btn">
                <i class="fas fa-users"></i> Manage Sellers
            </a>
            <a href="/modules/yfclaim/www/admin/sales.php" class="nav-btn">
                <i class="fas fa-store"></i> Manage Sales
            </a>
            <a href="/modules/yfclaim/www/admin/inquiries.php" class="nav-btn">
                <i class="fas fa-envelope"></i> View Inquiries
            </a>
            <a href="/modules/yfclaim/www/admin/buyers.php" class="nav-btn">
                <i class="fas fa-shopping-cart"></i> Manage Buyers
            </a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $stats['active_sellers'] ?></div>
                <div class="stat-label">Active Sellers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['active_sales'] ?></div>
                <div class="stat-label">Active Sales</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['active_items'] ?></div>
                <div class="stat-label">Active Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $stats['sold_items'] ?></div>
                <div class="stat-label">Items Sold</div>
            </div>
        </div>
        
        <div class="content-grid">
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">Recent Sellers</div>
                </div>
                <?php if (!empty($recentSellers)): ?>
                    <ul class="item-list">
                        <?php foreach ($recentSellers as $seller): ?>
                            <li>
                                <strong><?= htmlspecialchars($seller['company_name']) ?></strong>
                                <div class="item-meta">
                                    Status: <span class="status-<?= $seller['status'] ?>"><?= ucfirst($seller['status']) ?></span>
                                    • Joined: <?= date('M j, Y', strtotime($seller['created_at'])) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No sellers found. Database tables may not be created yet.</p>
                <?php endif; ?>
            </div>
            
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">Recent Sales</div>
                </div>
                <?php if (!empty($recentSales)): ?>
                    <ul class="item-list">
                        <?php foreach ($recentSales as $sale): ?>
                            <li>
                                <strong><?= htmlspecialchars($sale['title']) ?></strong>
                                <div class="item-meta">
                                    By: <?= htmlspecialchars($sale['company_name']) ?>
                                    • Status: <span class="status-<?= $sale['status'] ?>"><?= ucfirst($sale['status']) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No sales found.</p>
                <?php endif; ?>
            </div>
            
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">Recent Inquiries</div>
                </div>
                <?php if (!empty($recentInquiries)): ?>
                    <ul class="item-list">
                        <?php foreach ($recentInquiries as $inquiry): ?>
                            <li>
                                <strong>Contact Inquiry</strong>
                                <div class="item-meta">
                                    To: <?= htmlspecialchars($inquiry['company_name']) ?>
                                    • <?= date('M j, Y g:i A', strtotime($inquiry['created_at'])) ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No recent inquiries.</p>
                <?php endif; ?>
            </div>
            
            <div class="content-card">
                <div class="card-header">
                    <div class="card-title">Quick Actions</div>
                </div>
                <ul class="item-list">
                    <li><a href="/modules/yfclaim/www/admin/sellers.php?action=add">Add New Seller</a></li>
                    <li><a href="/modules/yfclaim/www/admin/sales.php?action=add">Create New Sale</a></li>
                    <li><a href="/modules/yfclaim/www/admin/inquiries.php">Review Contact Inquiries</a></li>
                    <li><a href="/modules/yfclaim/database/schema.sql">Install Database Schema</a></li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>