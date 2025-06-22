<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use YFEvents\Infrastructure\Services\EmailEventProcessor;

// Get database connection
$db = $GLOBALS['db'] ?? null;
$pdo = $db; // EmailEventProcessor expects a PDO instance

$basePath = '/refactor';

$emailConfig = require dirname(__DIR__) . '/config/email.php';
$processor = new EmailEventProcessor($pdo, $emailConfig);

// Handle manual processing request
if (isset($_POST['process_emails'])) {
    try {
        $events = $processor->processIncomingEmails();
        $message = count($events) . " events processed from emails.";
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Error processing emails: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Get statistics
$stats = $processor->getProcessingStats();

// Get recent email-submitted events
$sql = "
    SELECT id, title, start_datetime, location, status, external_event_id, created_at
    FROM events 
    WHERE external_event_id LIKE 'facebook_email_%'
    ORDER BY created_at DESC 
    LIMIT 20
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$recentEvents = $stmt->fetchAll();

// Get processing logs
$logFile = dirname(__DIR__) . '/logs/email_processing.log';
$recentLogs = [];
if (file_exists($logFile)) {
    $logs = file($logFile);
    $recentLogs = array_slice($logs, -20); // Last 20 lines
    $recentLogs = array_reverse($recentLogs);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Event Processing - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/admin-styles.css">
    <style>
        .log-viewer {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            max-height: 400px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .email-instructions {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 1rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar Navigation -->
            <nav class="col-md-2 d-md-block bg-light sidebar">
                <div class="sidebar-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/events.php">Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/shops.php">Shops</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/scrapers.php">Scrapers</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="<?= $basePath ?>/admin/email-events.php">Email Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/email-config.php">Email Config</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/theme.php">Theme</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/settings.php">Settings</a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-10 ml-sm-auto col-lg-10 px-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1>📧 Email Event Processing</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <form method="post" class="d-inline">
                            <button type="submit" name="process_emails" class="btn btn-primary">
                                🔄 Process Emails Now
                            </button>
                        </form>
                    </div>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="stat-card">
                            <h5>📊 Total Events (30 days)</h5>
                            <h2><?= $stats['total_events'] ?? 0 ?></h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <h5>📧 Email Events</h5>
                            <h2><?= $stats['email_events'] ?? 0 ?></h2>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card">
                            <h5>⏳ Pending Review</h5>
                            <h2><?= $stats['pending_email_events'] ?? 0 ?></h2>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="email-instructions">
                    <h5>📋 How to Submit Facebook Events via Email</h5>
                    <p><strong>For Businesses:</strong></p>
                    <ol>
                        <li><strong>Invite Method:</strong> When creating a Facebook event, invite: <code>events@yakimafinds.com</code></li>
                        <li><strong>Forward Method:</strong> Forward Facebook event emails to: <code>events@yakimafinds.com</code></li>
                        <li><strong>Link Method:</strong> Email the Facebook event URL to: <code>events@yakimafinds.com</code></li>
                    </ol>
                    <p><em>Events are automatically processed and added to the community calendar after admin approval.</em></p>
                </div>

                <!-- Recent Events -->
                <div class="row">
                    <div class="col-md-8">
                        <h3>📅 Recent Email Events</h3>
                        <?php if (empty($recentEvents)): ?>
                            <div class="alert alert-info">
                                No events have been submitted via email yet. Share the submission instructions with local businesses!
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Location</th>
                                            <th>Source ID</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentEvents as $event): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($event['title']) ?></strong>
                                                </td>
                                                <td>
                                                    <?= $event['start_datetime'] ? date('M j, Y g:i A', strtotime($event['start_datetime'])) : 'TBD' ?>
                                                </td>
                                                <td><?= htmlspecialchars($event['location']) ?></td>
                                                <td>
                                                    <small><?= htmlspecialchars($event['external_event_id']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= $event['status'] === 'approved' ? 'success' : ($event['status'] === 'pending' ? 'warning' : 'secondary') ?>">
                                                        <?= ucfirst($event['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?= date('M j, g:i A', strtotime($event['created_at'])) ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <h3>📋 Processing Logs</h3>
                        <?php if (empty($recentLogs)): ?>
                            <div class="alert alert-info">
                                No processing logs available yet.
                            </div>
                        <?php else: ?>
                            <div class="log-viewer">
                                <?php foreach ($recentLogs as $log): ?>
                                    <div><?= htmlspecialchars(trim($log)) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-3">
                            <h5>⚙️ System Status</h5>
                            <ul class="list-unstyled">
                                <li>
                                    <strong>IMAP Extension:</strong> 
                                    <span class="badge bg-<?= extension_loaded('imap') ? 'success' : 'danger' ?>">
                                        <?= extension_loaded('imap') ? 'Loaded' : 'Missing' ?>
                                    </span>
                                </li>
                                <li>
                                    <strong>Log File:</strong>
                                    <span class="badge bg-<?= file_exists($logFile) ? 'success' : 'warning' ?>">
                                        <?= file_exists($logFile) ? 'Available' : 'Not Found' ?>
                                    </span>
                                </li>
                                <li>
                                    <strong>Email Config:</strong>
                                    <span class="badge bg-<?= !empty($emailConfig['email']['username']) ? 'success' : 'warning' ?>">
                                        <?= !empty($emailConfig['email']['username']) ? 'Configured' : 'Needs Setup' ?>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000);
    </script>
</body>
</html>