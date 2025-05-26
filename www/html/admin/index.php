<?php
// Simple Admin Dashboard
require_once '../../../config/database.php';

// Authentication check
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

$isAdmin = true;

// Handle actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_event':
                $stmt = $pdo->prepare("UPDATE events SET status = 'approved' WHERE id = ?");
                $stmt->execute([$_POST['event_id']]);
                $message = "Event approved!";
                break;
                
            case 'delete_event':
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                $stmt->execute([$_POST['event_id']]);
                $message = "Event deleted!";
                break;
                
            case 'delete_shop':
                $stmt = $pdo->prepare("DELETE FROM local_shops WHERE id = ?");
                $stmt->execute([$_POST['shop_id']]);
                $message = "Shop deleted!";
                break;
        }
    }
}

// Get statistics
$stats = [];
$stats['total_events'] = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$stats['pending_events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'pending'")->fetchColumn();
$stats['approved_events'] = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'approved'")->fetchColumn();
$stats['total_shops'] = $pdo->query("SELECT COUNT(*) FROM local_shops")->fetchColumn();

// Get recent events
$events = $pdo->query("SELECT * FROM events ORDER BY created_at DESC LIMIT 20")->fetchAll();

// Get shops
$shops = $pdo->query("SELECT * FROM local_shops ORDER BY created_at DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents Admin Dashboard</title>
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
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
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
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #007bff;
        }
        .section {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
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
        }
        .status {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.875rem;
        }
        .status.pending {
            background: #ffc107;
            color: #000;
        }
        .status.approved {
            background: #28a745;
            color: white;
        }
        .actions {
            display: flex;
            gap: 0.5rem;
        }
        button {
            padding: 0.25rem 0.75rem;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.875rem;
        }
        .btn-approve {
            background: #28a745;
            color: white;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        .message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .nav {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .nav a {
            color: #007bff;
            text-decoration: none;
        }
        .nav a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>YFEvents Admin Dashboard</h1>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <div class="nav">
            <a href="/">← Back to Calendar</a>
            <a href="/admin/calendar/">Advanced Admin</a>
            <a href="/admin/scrapers.php">Manage Scrapers</a>
            <a href="/admin/intelligent-scraper.php">AI Scraper</a>
            <a href="/admin/validate-urls.php">URL Validator</a>
            <a href="#events">Events</a>
            <a href="#shops">Shops</a>
            <a href="/admin/logout.php" style="float: right; color: #dc3545;">Logout</a>
        </div>
        
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_events'] ?></div>
                <div>Total Events</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['pending_events'] ?></div>
                <div>Pending Approval</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['approved_events'] ?></div>
                <div>Approved Events</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_shops'] ?></div>
                <div>Local Shops</div>
            </div>
        </div>
        
        <div class="section" id="events">
            <h2>Recent Events</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Date/Time</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= $event['id'] ?></td>
                        <td><?= htmlspecialchars($event['title']) ?></td>
                        <td><?= date('M d, Y g:i A', strtotime($event['start_datetime'])) ?></td>
                        <td><?= htmlspecialchars($event['location']) ?></td>
                        <td>
                            <span class="status <?= $event['status'] ?>">
                                <?= ucfirst($event['status']) ?>
                            </span>
                        </td>
                        <td>
                            <form method="post" class="actions" style="display: inline;">
                                <input type="hidden" name="event_id" value="<?= $event['id'] ?>">
                                <?php if ($event['status'] === 'pending'): ?>
                                    <button type="submit" name="action" value="approve_event" class="btn-approve">Approve</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="delete_event" class="btn-delete" 
                                        onclick="return confirm('Delete this event?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section" id="shops">
            <h2>Local Shops</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shops as $shop): ?>
                    <tr>
                        <td><?= $shop['id'] ?></td>
                        <td><?= htmlspecialchars($shop['name']) ?></td>
                        <td><?= htmlspecialchars($shop['address']) ?></td>
                        <td><?= htmlspecialchars($shop['phone'] ?? 'N/A') ?></td>
                        <td>
                            <span class="status approved">Active</span>
                        </td>
                        <td>
                            <form method="post" class="actions" style="display: inline;">
                                <input type="hidden" name="shop_id" value="<?= $shop['id'] ?>">
                                <button type="submit" name="action" value="delete_shop" class="btn-delete" 
                                        onclick="return confirm('Delete this shop?')">Delete</button>
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