<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../config/database.php';

// Get sources directly from database
$sources = $db->query("SELECT * FROM calendar_sources ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get last scrape info for each source
$scrapeStats = [];
foreach ($sources as $source) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as event_count, MAX(last_scraped) as last_scraped 
        FROM events 
        WHERE source_id = ?
    ");
    $stmt->execute([$source['id']]);
    $scrapeStats[$source['id']] = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Sources - Advanced Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/css/admin.css">
    <style>
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        .admin-sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px;
        }
        .admin-content {
            flex: 1;
            padding: 20px;
            background: #f5f5f5;
        }
        .source-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .source-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .source-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .source-title {
            font-size: 18px;
            font-weight: bold;
        }
        .source-type {
            background: #007bff;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .source-url {
            color: #666;
            font-size: 14px;
            word-break: break-all;
            margin-bottom: 10px;
        }
        .source-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
        }
        .source-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .source-actions button {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-test { background: #28a745; color: white; }
        .btn-scrape { background: #007bff; color: white; }
        .btn-edit { background: #ffc107; color: #000; }
        .btn-delete { background: #dc3545; color: white; }
        .status-active { color: #28a745; }
        .status-inactive { color: #dc3545; }
        .add-source-btn {
            background: #28a745;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-sidebar">
            <h2>Advanced Admin</h2>
            <nav>
                <ul style="list-style: none; padding: 0;">
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/calendar/" style="color: white; text-decoration: none;">
                            <i class="fas fa-dashboard"></i> Dashboard
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/calendar/events.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-calendar"></i> Events
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/calendar/sources.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-rss"></i> Sources
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/calendar/shops.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-store"></i> Shops
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/scraper-info.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-info-circle"></i> Scraper Info
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/system-checkup.php" style="color: white; text-decoration: none;">
                            <i class="fas fa-heartbeat"></i> System Checkup
                        </a>
                    </li>
                    <li style="margin-bottom: 10px;">
                        <a href="/admin/" style="color: white; text-decoration: none;">
                            <i class="fas fa-arrow-left"></i> Back to Main
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        
        <div class="admin-content">
            <h1>Event Sources</h1>
            
            <button class="add-source-btn" onclick="window.location.href='/admin/scrapers.php'">
                <i class="fas fa-plus"></i> Add New Source
            </button>
            
            <div class="source-grid">
                <?php foreach ($sources as $source): ?>
                <div class="source-card" data-source-id="<?= $source['id'] ?>">
                    <div class="source-header">
                        <div class="source-title"><?= htmlspecialchars($source['name']) ?></div>
                        <div>
                            <span class="source-type"><?= strtoupper($source['scrape_type']) ?></span>
                            <i class="fas fa-circle status-<?= $source['active'] ? 'active' : 'inactive' ?>" 
                               title="<?= $source['active'] ? 'Active' : 'Inactive' ?>"></i>
                        </div>
                    </div>
                    
                    <div class="source-url"><?= htmlspecialchars($source['url']) ?></div>
                    
                    <div class="source-stats">
                        <div class="stat-item">
                            <div class="stat-value">
                                <?= number_format($scrapeStats[$source['id']]['event_count'] ?? 0) ?>
                            </div>
                            <div class="stat-label">Total Events</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value">
                                <?php 
                                $lastScraped = $scrapeStats[$source['id']]['last_scraped'] ?? null;
                                if ($lastScraped) {
                                    $diff = time() - strtotime($lastScraped);
                                    if ($diff < 3600) {
                                        echo round($diff / 60) . 'm';
                                    } elseif ($diff < 86400) {
                                        echo round($diff / 3600) . 'h';
                                    } else {
                                        echo round($diff / 86400) . 'd';
                                    }
                                    echo ' ago';
                                } else {
                                    echo 'Never';
                                }
                                ?>
                            </div>
                            <div class="stat-label">Last Scraped</div>
                        </div>
                    </div>
                    
                    <div class="source-actions">
                        <button class="btn-test" onclick="testSource(<?= $source['id'] ?>)">
                            <i class="fas fa-flask"></i> Test
                        </button>
                        <button class="btn-scrape" onclick="scrapeSource(<?= $source['id'] ?>)">
                            <i class="fas fa-sync"></i> Scrape Now
                        </button>
                        <button class="btn-edit" onclick="editSource(<?= $source['id'] ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
        function testSource(sourceId) {
            const card = document.querySelector(`[data-source-id="${sourceId}"]`);
            const testBtn = card.querySelector('.btn-test');
            testBtn.disabled = true;
            testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
            
            fetch('/admin/calendar/ajax/test-source.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `source_id=${sourceId}`
            })
            .then(r => r.json())
            .then(data => {
                testBtn.disabled = false;
                testBtn.innerHTML = '<i class="fas fa-flask"></i> Test';
                
                if (data.success) {
                    alert(`Test successful!\n\nFound ${data.results.event_count} events.\n\nSample:\n` + 
                          data.results.sample_events.map(e => '• ' + e.title).join('\n'));
                } else {
                    alert('Test failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                testBtn.disabled = false;
                testBtn.innerHTML = '<i class="fas fa-flask"></i> Test';
                alert('Test failed: Network error');
                console.error(err);
            });
        }
        
        function scrapeSource(sourceId) {
            if (!confirm('Start scraping this source now?')) return;
            
            window.location.href = `/admin/scrape-now.php?source_id=${sourceId}`;
        }
        
        function editSource(sourceId) {
            window.location.href = `/admin/scrapers.php?action=edit&id=${sourceId}`;
        }
    </script>
</body>
</html>