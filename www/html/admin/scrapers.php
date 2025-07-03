<?php
// Scraper Management Interface
require_once '../../../config/database.php';

// Authentication check
require_once dirname(__DIR__, 3) . '/includes/admin_auth_required.php';

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_source':
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO calendar_sources (name, url, scrape_type, scrape_config, active) 
                        VALUES (:name, :url, :type, :config, 1)
                    ");
                    $stmt->execute([
                        'name' => $_POST['name'],
                        'url' => $_POST['url'],
                        'type' => $_POST['type'],
                        'config' => $_POST['config']
                    ]);
                    $message = "Source added successfully!";
                } catch (Exception $e) {
                    $error = "Error adding source: " . $e->getMessage();
                }
                break;
                
            case 'toggle_source':
                $stmt = $pdo->prepare("UPDATE calendar_sources SET active = NOT active WHERE id = ?");
                $stmt->execute([$_POST['source_id']]);
                $message = "Source toggled!";
                break;
                
            case 'delete_source':
                $stmt = $pdo->prepare("DELETE FROM calendar_sources WHERE id = ?");
                $stmt->execute([$_POST['source_id']]);
                $message = "Source deleted!";
                break;
                
            case 'scrape_now':
                // Handled via AJAX now
                break;
        }
    }
}

// Get all sources
$sources = $pdo->query("
    SELECT s.*, 
           (SELECT COUNT(*) FROM events WHERE source_id = s.id) as event_count,
           (SELECT MAX(created_at) FROM events WHERE source_id = s.id) as last_event
    FROM calendar_sources s 
    ORDER BY s.created_at DESC
")->fetchAll();

// Get recent scraping logs
$logs = $pdo->query("
    SELECT l.*, s.name as source_name 
    FROM scraping_logs l 
    JOIN calendar_sources s ON l.source_id = s.id 
    ORDER BY l.start_time DESC 
    LIMIT 20
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scraper Management - YFEvents Admin</title>
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
        }
        .status {
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 0.875rem;
        }
        .active {
            background: #28a745;
            color: white;
        }
        .inactive {
            background: #dc3545;
            color: white;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        input, select, textarea {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 0.5rem;
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
        .message {
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
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
        pre {
            background: #f4f4f4;
            padding: 10px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Scraper Management</h1>
    </div>
    
    <div class="container">
        <div class="nav">
            <a href="./">← Back to Dashboard</a>
            <a href="#sources">Sources</a>
            <a href="intelligent-scraper.php">AI Scraper</a>
            <a href="#add-source">Add Source</a>
            <a href="#logs">Recent Logs</a>
            <a href="logout.php" style="margin-left: auto; color: #dc3545;">Logout</a>
        </div>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="section" id="sources">
            <h2>Event Sources</h2>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>URL</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Events</th>
                        <th>Last Scraped</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sources as $source): ?>
                    <tr>
                        <td><?= htmlspecialchars($source['name']) ?></td>
                        <td><a href="<?= htmlspecialchars($source['url']) ?>" target="_blank"><?= htmlspecialchars(substr($source['url'], 0, 50)) ?>...</a></td>
                        <td><?= htmlspecialchars($source['scrape_type']) ?></td>
                        <td>
                            <span class="status <?= $source['active'] ? 'active' : 'inactive' ?>">
                                <?= $source['active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td><?= $source['event_count'] ?></td>
                        <td><?= $source['last_scraped'] ? date('M d, Y', strtotime($source['last_scraped'])) : 'Never' ?></td>
                        <td>
                            <button onclick="scrapeNow(<?= $source['id'] ?>, '<?= htmlspecialchars($source['name']) ?>')" 
                                    class="btn-success" title="Scrape Now">🔄</button>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="source_id" value="<?= $source['id'] ?>">
                                <button type="submit" name="action" value="toggle_source" class="btn-primary">
                                    <?= $source['active'] ? 'Disable' : 'Enable' ?>
                                </button>
                                <button type="submit" name="action" value="delete_source" class="btn-danger" 
                                        onclick="return confirm('Delete this source?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section" id="add-source">
            <h2>Add New Source</h2>
            <form method="post">
                <input type="hidden" name="action" value="add_source">
                
                <div class="form-group">
                    <label for="name">Source Name</label>
                    <input type="text" id="name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="url">Source URL</label>
                    <input type="url" id="url" name="url" required>
                </div>
                
                <div class="form-group">
                    <label for="type">Scrape Type</label>
                    <select id="type" name="type" required onchange="updateConfigExample()">
                        <option value="">Select Type</option>
                        <option value="ical">iCal Feed</option>
                        <option value="html">HTML Scraping</option>
                        <option value="yakima_valley">Yakima Valley Events</option>
                        <option value="json">JSON API</option>
                        <option value="eventbrite">Eventbrite</option>
                        <option value="facebook">Facebook</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="config">Configuration (JSON)</label>
                    <textarea id="config" name="config" rows="10" required>{}</textarea>
                    <div id="config-example" style="margin-top: 10px;"></div>
                </div>
                
                <button type="submit" class="btn-primary">Add Source</button>
            </form>
        </div>
        
        <div class="section" id="logs">
            <h2>Recent Scraping Logs</h2>
            <table>
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>Start Time</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Found</th>
                        <th>Added</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= htmlspecialchars($log['source_name']) ?></td>
                        <td><?= date('M d, Y g:i A', strtotime($log['start_time'])) ?></td>
                        <td>
                            <?php 
                            if ($log['end_time']) {
                                $duration = strtotime($log['end_time']) - strtotime($log['start_time']);
                                echo $duration . 's';
                            } else {
                                echo 'Running...';
                            }
                            ?>
                        </td>
                        <td>
                            <span class="status <?= $log['status'] === 'success' ? 'active' : 'inactive' ?>">
                                <?= ucfirst($log['status']) ?>
                            </span>
                        </td>
                        <td><?= $log['events_found'] ?></td>
                        <td><?= $log['events_added'] ?></td>
                        <td><?= htmlspecialchars($log['error_message'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="section">
            <h2>Cron Job Setup</h2>
            <p>Add this line to your crontab to run the scraper every day at 2 AM:</p>
            <pre>0 2 * * * php <?= dirname(dirname(dirname(__DIR__))) ?>/cron/scrape-events.php >> <?= dirname(dirname(dirname(__DIR__))) ?>/logs/cron.log 2>&1</pre>
            
            <p>To run the scraper manually:</p>
            <pre>php <?= dirname(dirname(dirname(__DIR__))) ?>/cron/scrape-events.php</pre>
        </div>
    </div>
    
    <script>
    function updateConfigExample() {
        const type = document.getElementById('type').value;
        const exampleDiv = document.getElementById('config-example');
        const configField = document.getElementById('config');
        
        let example = '';
        let config = {};
        
        switch(type) {
            case 'ical':
                config = {
                    "url": "https://example.com/events.ics"
                };
                example = '<p>For iCal feeds, just provide the feed URL in the configuration.</p>';
                break;
                
            case 'html':
                config = {
                    "selectors": {
                        "event_container": ".event-item",
                        "title": ".event-title",
                        "datetime": ".event-date",
                        "location": ".event-venue",
                        "description": ".event-description"
                    }
                };
                example = '<p>For HTML scraping, provide CSS selectors for each event field.</p>';
                break;
                
            case 'yakima_valley':
                config = {
                    "base_url": "https://visityakima.com",
                    "year": new Date().getFullYear()
                };
                example = '<p>For Yakima Valley events format. Automatically parses events with date ranges and categories.</p>';
                break;
                
            case 'json':
                config = {
                    "events_path": "data.events",
                    "field_mapping": {
                        "title": "name",
                        "start_datetime": "start_time",
                        "location": "venue.name",
                        "description": "details"
                    }
                };
                example = '<p>For JSON APIs, map the API fields to our event fields.</p>';
                break;
                
            case 'eventbrite':
                config = {
                    "organization_id": "123456789",
                    "token": "your-api-token"
                };
                example = '<p>For Eventbrite, provide your organization ID and API token.</p>';
                break;
                
            case 'facebook':
                config = {
                    "page_id": "YourPageID",
                    "access_token": "your-access-token"
                };
                example = '<p>For Facebook, provide the page ID and access token.</p>';
                break;
        }
        
        configField.value = JSON.stringify(config, null, 2);
        exampleDiv.innerHTML = example;
    }
    
    function scrapeNow(sourceId, sourceName) {
        if (!confirm('Start scraping ' + sourceName + ' now?')) return;
        
        const button = event.target;
        button.disabled = true;
        button.textContent = '⏳';
        
        fetch('/admin/scrape-now.php?source_id=' + sourceId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Success: ' + data.message);
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Network error: ' + error.message);
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = '🔄';
            });
    }
    </script>
</body>
</html>