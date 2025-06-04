<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../config/database.php';

use YakimaFinds\Utils\SystemSettings;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = true;
    $errors = [];
    
    try {
        // Update show unapproved events setting
        if (isset($_POST['show_unapproved_events'])) {
            SystemSettings::set('show_unapproved_events', $_POST['show_unapproved_events'] === '1' ? '1' : '0', 'Show unapproved events on public calendar with disclaimer', $db);
        }
        
        // Update disclaimer text
        if (isset($_POST['unapproved_events_disclaimer'])) {
            SystemSettings::set('unapproved_events_disclaimer', trim($_POST['unapproved_events_disclaimer']), 'Disclaimer text for unapproved events', $db);
        }
        
        SystemSettings::clearCache();
        
        if ($success) {
            $successMessage = "Settings updated successfully!";
        }
        
    } catch (Exception $e) {
        $errors[] = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
$showUnapproved = SystemSettings::showUnapprovedEvents($db);
$disclaimerText = SystemSettings::getUnapprovedEventsDisclaimer($db);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Yakima Finds Admin</title>
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Navigation -->
        <nav class="admin-nav">
            <div class="nav-brand">
                <h2><i class="fas fa-calendar-alt"></i> Yakima Finds Admin</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php"><i class="fas fa-dashboard"></i> Dashboard</a></li>
                <li><a href="calendar/"><i class="fas fa-calendar"></i> Calendar</a></li>
                <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1><i class="fas fa-cog"></i> System Settings</h1>
                <p>Configure system-wide settings and preferences</p>
            </div>

            <?php if (isset($successMessage)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="admin-content">
                <form method="POST" class="settings-form">
                    <div class="settings-section">
                        <h2>Event Display Settings</h2>
                        <p class="section-description">Control how events are displayed on the public calendar</p>
                        
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="show_unapproved_events" value="1" <?php echo $showUnapproved ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                Show unapproved events on public calendar
                            </label>
                            <div class="field-help">
                                When enabled, events that haven't been approved will be displayed on the public calendar with a disclaimer. 
                                This allows visitors to see newly scraped events immediately while still maintaining quality control.
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="unapproved_events_disclaimer">Disclaimer Text for Unapproved Events</label>
                            <textarea 
                                id="unapproved_events_disclaimer" 
                                name="unapproved_events_disclaimer" 
                                rows="3" 
                                class="form-control"
                                placeholder="Enter disclaimer text..."
                            ><?php echo htmlspecialchars($disclaimerText); ?></textarea>
                            <div class="field-help">
                                This text will be displayed with unapproved events to inform users that the information may not be verified.
                            </div>
                        </div>
                    </div>

                    <div class="settings-section">
                        <h2>Preview</h2>
                        <p class="section-description">Preview how unapproved events will appear</p>
                        
                        <div class="preview-container">
                            <div class="event-item event-unapproved">
                                <div class="event-date">
                                    <div class="event-month">Jun</div>
                                    <div class="event-day">15</div>
                                </div>
                                <div class="event-content">
                                    <h3 class="event-title">
                                        Sample Unverified Event
                                        <span class="unapproved-badge">Unverified</span>
                                    </h3>
                                    <div class="event-meta">
                                        <span><i class="fas fa-clock"></i> 7:00 PM</span>
                                        <span><i class="fas fa-map-marker-alt"></i> Sample Venue</span>
                                    </div>
                                    <div class="event-disclaimer">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <span id="disclaimer-preview"><?php echo htmlspecialchars($disclaimerText); ?></span>
                                    </div>
                                    <div class="event-description">This is how an unapproved event will appear on the calendar...</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Settings
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Update preview when disclaimer text changes
        document.getElementById('unapproved_events_disclaimer').addEventListener('input', function() {
            document.getElementById('disclaimer-preview').textContent = this.value;
        });
    </script>

    <style>
        .settings-section {
            background: white;
            padding: 2rem;
            margin-bottom: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .settings-section h2 {
            color: #2c3e50;
            margin-bottom: 0.5rem;
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.5rem;
        }

        .section-description {
            color: #7f8c8d;
            margin-bottom: 1.5rem;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .checkbox-label input[type="checkbox"] {
            margin-right: 0.75rem;
            transform: scale(1.2);
        }

        .field-help {
            font-size: 0.9rem;
            color: #95a5a6;
            margin-top: 0.5rem;
            line-height: 1.4;
        }

        .preview-container {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }

        .form-actions {
            text-align: right;
            padding-top: 2rem;
            border-top: 1px solid #dee2e6;
        }

        .form-actions .btn {
            margin-left: 1rem;
        }

        /* Include unapproved event styles here too */
        .event-unapproved {
            border-left: 4px solid #f39c12 !important;
            background-color: #fff9e6;
            position: relative;
        }

        .unapproved-badge {
            display: inline-block;
            background-color: #f39c12;
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            margin-left: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .event-disclaimer {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 0.5rem;
            margin: 0.5rem 0;
            font-size: 0.8rem;
            color: #856404;
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .event-disclaimer i {
            color: #f39c12;
            margin-top: 0.1rem;
            flex-shrink: 0;
        }
    </style>
</body>
</html>