<?php
session_start();

// Check if seller is logged in
if (!isset($_SESSION['claim_seller_logged_in']) || $_SESSION['claim_seller_logged_in'] !== true) {
    header('Location: /modules/yfclaim/www/admin/login.php');
    exit;
}

// Load dependencies
require_once __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../config/db_connection.php';
$db = $pdo; // Dashboard files expect $db variable

use YFEvents\Modules\YFClaim\Models\SellerModel;
use YFEvents\Modules\YFClaim\Models\NotificationModel;

$sellerModel = new SellerModel($db);
$notificationModel = new NotificationModel($db);

$sellerId = $_SESSION['claim_seller_id'];
$seller = $sellerModel->find($sellerId);

if (!$seller) {
    header('Location: /modules/yfclaim/admin/login.php');
    exit;
}

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        $notificationModel->markAsRead($_POST['notification_id']);
        header('Location: notifications.php');
        exit;
    } elseif ($_POST['action'] === 'mark_all_read') {
        // Get all unread notifications for this seller
        $unreadNotifications = $notificationModel->getUnreadBySeller($sellerId, 1000);
        $notificationIds = array_column($unreadNotifications, 'id');
        if (!empty($notificationIds)) {
            $notificationModel->markMultipleAsRead($notificationIds);
        }
        header('Location: notifications.php');
        exit;
    }
}

// Get pagination params
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get notifications
$notifications = $notificationModel->getBySellerWithPagination($sellerId, $limit, $offset);

// Get total count for pagination
$totalCount = $notificationModel->count(['seller_id' => $sellerId]);
$totalPages = ceil($totalCount / $limit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - YFClaim</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            line-height: 1.6;
        }
        
        .header {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 1rem 0;
            margin-bottom: 2rem;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: #666;
            text-decoration: none;
            font-weight: 500;
        }
        
        .nav-links a:hover {
            color: #667eea;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .page-header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            color: #333;
            font-size: 1.8rem;
        }
        
        .mark-all-btn {
            background: #667eea;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .mark-all-btn:hover {
            background: #5a6fd8;
        }
        
        .notification-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .notification-item {
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            gap: 1rem;
            align-items: start;
        }
        
        .notification-item:last-child {
            border-bottom: none;
        }
        
        .notification-item.unread {
            background: #f8f9ff;
        }
        
        .notification-icon {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        
        .notification-icon.new_offer {
            background: #e3f2fd;
            color: #1976d2;
        }
        
        .notification-icon.sale_ending {
            background: #fff3e0;
            color: #f57c00;
        }
        
        .notification-icon.item_claimed {
            background: #e8f5e9;
            color: #388e3c;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.25rem;
        }
        
        .notification-message {
            color: #666;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
        }
        
        .notification-meta {
            display: flex;
            gap: 1rem;
            align-items: center;
            font-size: 0.85rem;
            color: #999;
        }
        
        .notification-time {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .notification-sale {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .mark-read-form {
            display: inline;
        }
        
        .mark-read-btn {
            background: none;
            border: 1px solid #ddd;
            color: #666;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        
        .mark-read-btn:hover {
            background: #f8f9fa;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding-bottom: 2rem;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #666;
        }
        
        .pagination a:hover {
            background: #f8f9fa;
        }
        
        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">YFClaim Seller Portal</div>
            <div class="nav-links">
                <a href="/modules/yfclaim/www/dashboard/">Dashboard</a>
                <a href="/modules/yfclaim/www/dashboard/sales.php">My Sales</a>
                <a href="/modules/yfclaim/www/dashboard/notifications.php">Notifications</a>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="page-header">
            <h1>Notifications</h1>
            <?php if (!empty($notifications)): ?>
            <form method="POST" class="mark-all-form">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="mark-all-btn">Mark All Read</button>
            </form>
            <?php endif; ?>
        </div>
        
        <?php if (empty($notifications)): ?>
        <div class="notification-list">
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No notifications yet</h3>
                <p>You'll see notifications here when you receive new offers or important updates.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="notification-list">
            <?php foreach ($notifications as $notification): 
                $timeAgo = human_time_diff(strtotime($notification['created_at']));
                $iconClass = $notification['type'];
                $iconSymbol = match($notification['type']) {
                    'new_offer' => '💰',
                    'sale_ending' => '⏰',
                    'item_claimed' => '✅',
                    default => '📢'
                };
            ?>
            <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                <div class="notification-icon <?= $iconClass ?>">
                    <?= $iconSymbol ?>
                </div>
                <div class="notification-content">
                    <div class="notification-title"><?= htmlspecialchars($notification['title']) ?></div>
                    <div class="notification-message"><?= htmlspecialchars($notification['message']) ?></div>
                    <div class="notification-meta">
                        <span class="notification-time">🕒 <?= $timeAgo ?> ago</span>
                        <?php if ($notification['sale_title']): ?>
                        <span class="notification-sale">📦 <?= htmlspecialchars($notification['sale_title']) ?></span>
                        <?php endif; ?>
                        <?php if (!$notification['is_read']): ?>
                        <form method="POST" class="mark-read-form">
                            <input type="hidden" name="action" value="mark_read">
                            <input type="hidden" name="notification_id" value="<?= $notification['id'] ?>">
                            <button type="submit" class="mark-read-btn">Mark as read</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page - 1 ?>">Previous</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == $page): ?>
                <span class="current"><?= $i ?></span>
                <?php else: ?>
                <a href="?page=<?= $i ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?>">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>

<?php
// Helper function for human-readable time
function human_time_diff($timestamp) {
    $time_diff = time() - $timestamp;
    
    if ($time_diff < 60) {
        return $time_diff . ' seconds';
    } elseif ($time_diff < 3600) {
        return round($time_diff / 60) . ' minutes';
    } elseif ($time_diff < 86400) {
        return round($time_diff / 3600) . ' hours';
    } elseif ($time_diff < 604800) {
        return round($time_diff / 86400) . ' days';
    } else {
        return round($time_diff / 604800) . ' weeks';
    }
}
?>