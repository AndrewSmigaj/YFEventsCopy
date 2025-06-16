<?php
declare(strict_types=1);

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$basePath = '/refactor';

// Load current email configuration
$emailConfigFile = dirname(__DIR__) . '/config/email.php';
$emailConfig = require $emailConfigFile;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    try {
        // Update configuration
        $emailConfig['email']['imap_server'] = $_POST['imap_server'] ?? '';
        $emailConfig['email']['username'] = $_POST['username'] ?? '';
        
        // Only update password if provided
        if (!empty($_POST['password'])) {
            $emailConfig['email']['password'] = $_POST['password'];
        }
        
        // Update SMTP settings
        $emailConfig['email']['smtp']['host'] = $_POST['smtp_host'] ?? 'localhost';
        $emailConfig['email']['smtp']['port'] = (int)($_POST['smtp_port'] ?? 587);
        $emailConfig['email']['smtp']['username'] = $_POST['smtp_username'] ?? '';
        if (!empty($_POST['smtp_password'])) {
            $emailConfig['email']['smtp']['password'] = $_POST['smtp_password'];
        }
        $emailConfig['email']['smtp']['encryption'] = $_POST['smtp_encryption'] ?? 'tls';
        
        // Update processing settings
        $emailConfig['email']['processing']['batch_size'] = (int)($_POST['batch_size'] ?? 50);
        $emailConfig['email']['processing']['mark_as_read'] = isset($_POST['mark_as_read']);
        $emailConfig['email']['processing']['delete_processed'] = isset($_POST['delete_processed']);
        
        // Update confirmation settings
        $emailConfig['email']['confirmation']['enabled'] = isset($_POST['confirmation_enabled']);
        $emailConfig['email']['confirmation']['from_email'] = $_POST['from_email'] ?? '';
        $emailConfig['email']['confirmation']['from_name'] = $_POST['from_name'] ?? '';
        $emailConfig['email']['confirmation']['reply_to'] = $_POST['reply_to'] ?? '';
        
        // Write configuration back to file
        $configContent = "<?php\n\nreturn " . var_export($emailConfig, true) . ";";
        file_put_contents($emailConfigFile, $configContent);
        
        $message = "Email configuration saved successfully!";
        $messageType = 'success';
        
    } catch (Exception $e) {
        $message = "Error saving configuration: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Test IMAP connection
if (isset($_POST['test_connection'])) {
    try {
        if (!extension_loaded('imap')) {
            // Use alternative file-based processor
            require_once dirname(__DIR__) . '/src/Infrastructure/Email/CurlEmailProcessor.php';
            $processor = new \YakimaFinds\Infrastructure\Email\CurlEmailProcessor($emailConfig);
            $result = $processor->testConnection();
            
            $testMessage = "IMAP extension not installed. Using file-based email processing instead.\n\n";
            $testMessage .= $result['message'] . "\n\n";
            $testMessage .= "Instructions:\n";
            foreach ($result['instructions'] as $instruction) {
                $testMessage .= "• " . $instruction . "\n";
            }
            $testMessage .= "\nTo install IMAP extension, run: sudo apt install php8.3-imap";
            $testMessageType = 'warning';
        } else {
            $server = $emailConfig['email']['imap_server'];
            $username = $emailConfig['email']['username'];
            $password = $emailConfig['email']['password'];
            
            if (empty($server) || empty($username) || empty($password)) {
                throw new Exception("Please fill in all IMAP connection details");
            }
            
            $imap = @imap_open($server, $username, $password);
            
            if ($imap) {
                $check = imap_check($imap);
                $testMessage = "Connection successful! Mailbox has {$check->Nmsgs} messages.";
                $testMessageType = 'success';
                imap_close($imap);
            } else {
                throw new Exception(imap_last_error());
            }
        }
        
    } catch (Exception $e) {
        $testMessage = "Connection failed: " . $e->getMessage();
        if (!extension_loaded('imap')) {
            $testMessage .= "\n\nTo install IMAP extension:\nsudo apt install php8.3-imap\nsudo systemctl restart apache2";
        }
        $testMessageType = 'error';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Configuration - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        .config-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .config-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }
        .form-helper {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .test-button {
            margin-top: 10px;
        }
        .password-field {
            position: relative;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 25px;
        }
        .nav-tabs .nav-link {
            color: #495057;
            border: none;
            padding: 10px 20px;
            margin-right: 10px;
            border-radius: 10px 10px 0 0;
        }
        .nav-tabs .nav-link.active {
            background: #667eea;
            color: white;
        }
        .nav-tabs .nav-link:hover {
            background: #f8f9fa;
        }
        .nav-tabs .nav-link.active:hover {
            background: #5a67d8;
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
                            <a class="nav-link" href="<?= $basePath ?>/admin/email-events.php">Email Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="<?= $basePath ?>/admin/email-config.php">Email Config</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/shops.php">Shops</a>
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
                    <h1>📧 Email Configuration</h1>
                </div>

                <?php if (isset($message)): ?>
                    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($testMessage)): ?>
                    <div class="alert alert-<?= $testMessageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <?= htmlspecialchars($testMessage) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <!-- IMAP Configuration -->
                    <div class="config-section">
                        <h3>📥 IMAP Configuration (Receiving Emails)</h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="imap_server" class="form-label">IMAP Server</label>
                                    <input type="text" class="form-control" id="imap_server" name="imap_server" 
                                           value="<?= htmlspecialchars($emailConfig['email']['imap_server'] ?? '') ?>" 
                                           placeholder="{mail.example.com:993/imap/ssl}INBOX">
                                    <div class="form-helper">
                                        Examples:<br>
                                        Gmail: <code>{imap.gmail.com:993/imap/ssl}INBOX</code><br>
                                        cPanel: <code>{mail.yakimafinds.com:993/imap/ssl}INBOX</code>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="username" name="username" 
                                           value="<?= htmlspecialchars($emailConfig['email']['username'] ?? '') ?>" 
                                           placeholder="events@yakimafinds.com">
                                    <div class="form-helper">The email address to receive Facebook event invitations</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Email Password</label>
                                    <div class="password-field">
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="<?= empty($emailConfig['email']['password']) ? 'Enter password' : '••••••••' ?>">
                                        <span class="password-toggle" onclick="togglePassword('password')">👁️</span>
                                    </div>
                                    <div class="form-helper">Leave blank to keep existing password</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 d-flex align-items-end">
                                <button type="submit" name="test_connection" class="btn btn-secondary test-button">
                                    🔌 Test Connection
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP Configuration -->
                    <div class="config-section">
                        <h3>📤 SMTP Configuration (Sending Confirmations)</h3>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="smtp_host" class="form-label">SMTP Host</label>
                                    <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                           value="<?= htmlspecialchars($emailConfig['email']['smtp']['host'] ?? 'localhost') ?>" 
                                           placeholder="smtp.example.com">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="smtp_port" class="form-label">SMTP Port</label>
                                    <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                           value="<?= $emailConfig['email']['smtp']['port'] ?? 587 ?>" 
                                           placeholder="587">
                                    <div class="form-helper">Common: 587 (TLS), 465 (SSL), 25 (None)</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="smtp_encryption" class="form-label">Encryption</label>
                                    <select class="form-control" id="smtp_encryption" name="smtp_encryption">
                                        <option value="tls" <?= ($emailConfig['email']['smtp']['encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                        <option value="ssl" <?= ($emailConfig['email']['smtp']['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                        <option value="" <?= ($emailConfig['email']['smtp']['encryption'] ?? '') === '' ? 'selected' : '' ?>>None</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtp_username" class="form-label">SMTP Username</label>
                                    <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                           value="<?= htmlspecialchars($emailConfig['email']['smtp']['username'] ?? '') ?>" 
                                           placeholder="noreply@yakimafinds.com">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="smtp_password" class="form-label">SMTP Password</label>
                                    <div class="password-field">
                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                               placeholder="<?= empty($emailConfig['email']['smtp']['password']) ? 'Enter password' : '••••••••' ?>">
                                        <span class="password-toggle" onclick="togglePassword('smtp_password')">👁️</span>
                                    </div>
                                    <div class="form-helper">Leave blank to keep existing password</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Processing Settings -->
                    <div class="config-section">
                        <h3>⚙️ Email Processing Settings</h3>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="batch_size" class="form-label">Batch Size</label>
                                    <input type="number" class="form-control" id="batch_size" name="batch_size" 
                                           value="<?= $emailConfig['email']['processing']['batch_size'] ?? 50 ?>" 
                                           min="1" max="100">
                                    <div class="form-helper">Number of emails to process per run</div>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="mark_as_read" name="mark_as_read" 
                                           <?= ($emailConfig['email']['processing']['mark_as_read'] ?? true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="mark_as_read">
                                        Mark emails as read after processing
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="delete_processed" name="delete_processed" 
                                           <?= ($emailConfig['email']['processing']['delete_processed'] ?? false) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="delete_processed">
                                        Delete emails after processing
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Confirmation Email Settings -->
                    <div class="config-section">
                        <h3>✉️ Confirmation Email Settings</h3>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="confirmation_enabled" name="confirmation_enabled" 
                                           <?= ($emailConfig['email']['confirmation']['enabled'] ?? true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="confirmation_enabled">
                                        Send confirmation emails when events are processed
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="from_email" class="form-label">From Email</label>
                                    <input type="email" class="form-control" id="from_email" name="from_email" 
                                           value="<?= htmlspecialchars($emailConfig['email']['confirmation']['from_email'] ?? '') ?>" 
                                           placeholder="noreply@yakimafinds.com">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="from_name" class="form-label">From Name</label>
                                    <input type="text" class="form-control" id="from_name" name="from_name" 
                                           value="<?= htmlspecialchars($emailConfig['email']['confirmation']['from_name'] ?? '') ?>" 
                                           placeholder="YakimaFinds Community Calendar">
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="reply_to" class="form-label">Reply-To Email</label>
                                    <input type="email" class="form-control" id="reply_to" name="reply_to" 
                                           value="<?= htmlspecialchars($emailConfig['email']['confirmation']['reply_to'] ?? '') ?>" 
                                           placeholder="events@yakimafinds.com">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submission Addresses -->
                    <div class="config-section">
                        <h3>📨 Event Submission Addresses</h3>
                        <p class="text-muted">Share these email addresses with local businesses for event submissions:</p>
                        
                        <div class="row">
                            <?php foreach ($emailConfig['email']['submission_addresses'] ?? [] as $address): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="alert alert-info">
                                        <strong><?= htmlspecialchars($address) ?></strong>
                                        <?php if (strpos($address, '@gmail.com') !== false): ?>
                                            <small class="text-muted d-block">Main receiving address</small>
                                        <?php else: ?>
                                            <small class="text-muted d-block">Alias (forwards to Gmail)</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="form-helper mt-2">
                            <strong>Tell local businesses:</strong><br>
                            • <strong>Invite events@yakimafinds.com to your Facebook events</strong> (most professional)<br>
                            • Forward Facebook event emails to any of these addresses<br>
                            • Email event links or details directly<br>
                            • System automatically processes and adds events to calendar
                        </div>
                        
                        <div class="alert alert-success mt-3">
                            <strong>💡 Marketing Tips:</strong><br>
                            • <strong>Business cards:</strong> "Submit events: events@yakimafinds.com"<br>
                            • <strong>Website:</strong> "Add your event to our calendar - invite events@yakimafinds.com to your Facebook event"<br>
                            • <strong>Directory listing:</strong> "Event submissions: calendar@yakimafinds.com"
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <strong>⚠️ Setup Required:</strong> Create email aliases in your domain hosting that forward to yakimafinds@gmail.com
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="text-end mb-4">
                        <button type="submit" name="save_config" class="btn btn-primary btn-lg">
                            💾 Save Configuration
                        </button>
                    </div>
                </form>

                <!-- Instructions -->
                <div class="config-section">
                    <h3>📋 Setup Instructions</h3>
                    
                    <h5>Gmail Setup (Updated 2024):</h5>
                    <div class="alert alert-warning">
                        <strong>⚠️ Gmail App Password Issues:</strong> Google has tightened security restrictions. If you can't create app passwords, try these solutions:
                    </div>
                    <ol>
                        <li><strong>Enable 2-Step Verification:</strong>
                            <ul>
                                <li>Go to <a href="https://myaccount.google.com/security" target="_blank">Google Account Security</a></li>
                                <li>Turn on 2-Step Verification if not already enabled</li>
                            </ul>
                        </li>
                        <li><strong>Create App Password:</strong>
                            <ul>
                                <li>Go to <a href="https://myaccount.google.com/apppasswords" target="_blank">App Passwords</a></li>
                                <li>If you don't see "App passwords" option, your account may not support them</li>
                                <li>Select "Mail" and your device type</li>
                                <li>Copy the 16-character password (no spaces)</li>
                            </ul>
                        </li>
                        <li><strong>If App Passwords Don't Work:</strong>
                            <ul>
                                <li>Try different Gmail account (some accounts have restrictions)</li>
                                <li>Use Yahoo Mail or Outlook instead (easier setup)</li>
                                <li>Consider OAuth2 setup (more complex but more secure)</li>
                            </ul>
                        </li>
                        <li>IMAP Server: <code>{imap.gmail.com:993/imap/ssl}INBOX</code></li>
                    </ol>
                    
                    <h5>Alternative: Yahoo Mail Setup (Recommended):</h5>
                    <ol>
                        <li>Create Yahoo Mail account</li>
                        <li>Go to Account Security → Generate app password</li>
                        <li>Select "Mail" category</li>
                        <li>IMAP Server: <code>{imap.mail.yahoo.com:993/imap/ssl}INBOX</code></li>
                        <li>Use Yahoo email and app password</li>
                    </ol>
                    
                    <h5>Alternative: Outlook/Hotmail Setup:</h5>
                    <ol>
                        <li>Enable 2-factor authentication</li>
                        <li>Generate app password in Security settings</li>
                        <li>IMAP Server: <code>{outlook.office365.com:993/imap/ssl}INBOX</code></li>
                    </ol>
                    
                    <h5>cPanel Email Setup:</h5>
                    <ol>
                        <li>Create email account in cPanel</li>
                        <li>Enable IMAP in email settings</li>
                        <li>IMAP Server: <code>{mail.yourdomain.com:993/imap/ssl}INBOX</code></li>
                        <li>Use full email address as username</li>
                    </ol>
                    
                    <h5>Cron Job Setup:</h5>
                    <pre class="bg-light p-3 rounded"><code>*/15 * * * * /usr/bin/php <?= dirname(__DIR__) ?>/scripts/process_event_emails.php</code></pre>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
            field.setAttribute('type', type);
        }
    </script>
</body>
</html>