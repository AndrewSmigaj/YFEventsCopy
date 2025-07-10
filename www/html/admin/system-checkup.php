<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login.php');
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';

use YakimaFinds\Utils\SystemCheckup;
use YakimaFinds\Utils\SystemLogger;

// Handle actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'run_checkup') {
        try {
            $checkup = new SystemCheckup($db);
            $results = $checkup->runCheckup(true);
            $message = "System checkup completed successfully. Found {$results['duration_ms']}ms execution time.";
            $messageType = 'success';
        } catch (Exception $e) {
            $message = "Checkup failed: " . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($action === 'mark_recommendation_done') {
        $checkupId = intval($_POST['checkup_id']);
        $recommendationIndex = intval($_POST['recommendation_index']);
        
        // Update recommendation status
        $stmt = $db->prepare("SELECT results FROM system_checkups WHERE id = ?");
        $stmt->execute([$checkupId]);
        $results = json_decode($stmt->fetchColumn(), true);
        
        if (isset($results['recommendations'][$recommendationIndex])) {
            $results['recommendations'][$recommendationIndex]['status'] = 'completed';
            $results['recommendations'][$recommendationIndex]['completed_at'] = date('Y-m-d H:i:s');
            $results['recommendations'][$recommendationIndex]['completed_by'] = $_SESSION['admin_username'] ?? 'admin';
            
            $updateStmt = $db->prepare("UPDATE system_checkups SET results = ? WHERE id = ?");
            $updateStmt->execute([json_encode($results), $checkupId]);
            
            $message = "Recommendation marked as completed.";
            $messageType = 'success';
        }
    }
}

// Get recent checkups
$stmt = $db->query("
    SELECT * FROM system_checkups 
    ORDER BY created_at DESC 
    LIMIT 10
");
$checkups = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get latest checkup for detailed view
$latestCheckup = null;
if (!empty($checkups)) {
    $latestCheckup = $checkups[0];
    if ($latestCheckup['results']) {
        $latestCheckup['results'] = json_decode($latestCheckup['results'], true);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Checkup - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        h1 {
            color: #2c3e50;
            margin: 0;
        }
        .back-link {
            color: #3498db;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .checkup-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .checkup-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .checkup-history, .recommendations {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        .checkup-item {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 4px solid #ddd;
        }
        .checkup-item.completed {
            border-left-color: #28a745;
        }
        .checkup-item.running {
            border-left-color: #ffc107;
        }
        .checkup-item.failed {
            border-left-color: #dc3545;
        }
        .recommendation-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 4px solid #17a2b8;
            position: relative;
        }
        .recommendation-item.completed {
            opacity: 0.7;
            border-left-color: #28a745;
        }
        .recommendation-item.high-priority {
            border-left-color: #dc3545;
        }
        .recommendation-item.medium-priority {
            border-left-color: #ffc107;
        }
        .recommendation-item.low-priority {
            border-left-color: #28a745;
        }
        .recommendation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .recommendation-title {
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }
        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        .priority-high {
            background: #f8d7da;
            color: #721c24;
        }
        .priority-medium {
            background: #fff3cd;
            color: #856404;
        }
        .priority-low {
            background: #d4edda;
            color: #155724;
        }
        .recommendation-description {
            color: #666;
            margin: 10px 0;
            line-height: 1.5;
        }
        .recommendation-instructions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            margin: 15px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            border: 1px solid #e9ecef;
        }
        .recommendation-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-success:hover {
            background: #218838;
        }
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .status-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        .status-card.healthy {
            border-left: 4px solid #28a745;
        }
        .status-card.warning {
            border-left: 4px solid #ffc107;
        }
        .status-card.error {
            border-left: 4px solid #dc3545;
        }
        .status-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        .healthy .status-icon {
            color: #28a745;
        }
        .warning .status-icon {
            color: #ffc107;
        }
        .error .status-icon {
            color: #dc3545;
        }
        .completed-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        .complexity-badge {
            margin-left: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 11px;
            background: #e9ecef;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-heartbeat"></i> System Checkup</h1>
            <a href="calendar/" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Admin
            </a>
        </div>

        <?php if ($message): ?>
        <div class="message <?= $messageType ?>">
            <?= htmlspecialchars($message) ?>
        </div>
        <?php endif; ?>

        <div class="checkup-controls">
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="run_checkup">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-play"></i> Run System Checkup
                </button>
            </form>
            <a href="#recommendations" class="btn btn-secondary">
                <i class="fas fa-list"></i> View Recommendations
            </a>
        </div>

        <?php if ($latestCheckup && $latestCheckup['status'] === 'completed' && $latestCheckup['results']): ?>
        <div class="status-grid">
            <?php foreach ($latestCheckup['results']['components_checked'] as $component => $health): ?>
            <div class="status-card <?= $health['status'] ?>">
                <div class="status-icon">
                    <?php if ($health['status'] === 'healthy'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php elseif ($health['status'] === 'warning'): ?>
                        <i class="fas fa-exclamation-triangle"></i>
                    <?php else: ?>
                        <i class="fas fa-times-circle"></i>
                    <?php endif; ?>
                </div>
                <h3><?= ucfirst($component) ?></h3>
                <p><?= ucfirst($health['status']) ?></p>
                <?php if (!empty($health['issues'])): ?>
                    <small><?= count($health['issues']) ?> issue(s)</small>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="checkup-grid">
            <div class="checkup-history">
                <h3><i class="fas fa-history"></i> Recent Checkups</h3>
                <?php if (empty($checkups)): ?>
                    <p>No checkups have been run yet.</p>
                <?php else: ?>
                    <?php foreach ($checkups as $checkup): ?>
                    <div class="checkup-item <?= $checkup['status'] ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?= date('M j, Y g:i A', strtotime($checkup['created_at'])) ?></strong>
                                <span class="badge"><?= ucfirst($checkup['status']) ?></span>
                            </div>
                            <div style="text-align: right; font-size: 12px; color: #666;">
                                <?php if ($checkup['errors_found']): ?>
                                    <div><i class="fas fa-exclamation-circle" style="color: #dc3545;"></i> <?= $checkup['errors_found'] ?> errors</div>
                                <?php endif; ?>
                                <?php if ($checkup['warnings_found']): ?>
                                    <div><i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i> <?= $checkup['warnings_found'] ?> warnings</div>
                                <?php endif; ?>
                                <?php if ($checkup['recommendations_count']): ?>
                                    <div><i class="fas fa-lightbulb" style="color: #17a2b8;"></i> <?= $checkup['recommendations_count'] ?> recommendations</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="recommendations" id="recommendations">
                <h3><i class="fas fa-lightbulb"></i> AI Recommendations</h3>
                <?php if ($latestCheckup && !empty($latestCheckup['results']['recommendations'])): ?>
                    <?php foreach ($latestCheckup['results']['recommendations'] as $index => $rec): ?>
                    <div class="recommendation-item <?= $rec['priority'] ?? 'medium' ?>-priority <?= isset($rec['status']) && $rec['status'] === 'completed' ? 'completed' : '' ?>">
                        <?php if (isset($rec['status']) && $rec['status'] === 'completed'): ?>
                            <div class="completed-badge">
                                <i class="fas fa-check"></i> Done
                            </div>
                        <?php endif; ?>
                        
                        <div class="recommendation-header">
                            <h4 class="recommendation-title">
                                <?= htmlspecialchars($rec['title'] ?? 'Untitled Recommendation') ?>
                                <span class="complexity-badge"><?= $rec['complexity'] ?? 'moderate' ?></span>
                            </h4>
                            <span class="priority-badge priority-<?= $rec['priority'] ?? 'medium' ?>">
                                <?= $rec['priority'] ?? 'medium' ?>
                            </span>
                        </div>
                        
                        <div class="recommendation-description">
                            <?= nl2br(htmlspecialchars($rec['description'] ?? 'No description provided.')) ?>
                        </div>
                        
                        <?php if (!empty($rec['instructions'])): ?>
                        <div class="recommendation-instructions">
                            <strong>Instructions for Claude Code Agent:</strong><br>
                            <?= nl2br(htmlspecialchars($rec['instructions'])) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!isset($rec['status']) || $rec['status'] !== 'completed'): ?>
                        <div class="recommendation-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="mark_recommendation_done">
                                <input type="hidden" name="checkup_id" value="<?= $latestCheckup['id'] ?>">
                                <input type="hidden" name="recommendation_index" value="<?= $index ?>">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Mark as Done
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <div style="margin-top: 15px; font-size: 12px; color: #666;">
                            <i class="fas fa-check-circle" style="color: #28a745;"></i>
                            Completed by <?= htmlspecialchars($rec['completed_by'] ?? 'admin') ?> 
                            on <?= date('M j, Y g:i A', strtotime($rec['completed_at'])) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No recommendations available. Run a system checkup to generate AI-powered recommendations.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh status during checkup
        if (window.location.search.includes('running')) {
            setTimeout(() => {
                window.location.reload();
            }, 5000);
        }
        
        // Add loading state to checkup button
        document.querySelector('form').addEventListener('submit', function() {
            const btn = this.querySelector('button');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running Checkup...';
        });
    </script>
</body>
</html>