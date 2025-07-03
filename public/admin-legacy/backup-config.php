<?php
declare(strict_types=1);

session_start();

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: /admin/login.php');
    exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';

$basePath = '';

// Load current backup configuration
$backupConfigFile = dirname(__DIR__) . '/config/backup.php';
$backupConfig = [];

if (file_exists($backupConfigFile)) {
    $backupConfig = require $backupConfigFile;
} else {
    // Default configuration
    $backupConfig = [
        'automated_backups' => [
            'enabled' => false,
            'email_address' => '',
            'frequency' => 'daily', // daily, weekly, monthly
            'include_database' => true,
            'include_config' => true,
            'include_logs' => false,
            'max_backups' => 30,
            'compression' => true
        ],
        'recovery' => [
            'install_email' => '',
            'emergency_contacts' => [],
            'recovery_instructions' => '',
            'server_details' => [
                'hostname' => $_SERVER['SERVER_NAME'] ?? '',
                'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
                'php_version' => PHP_VERSION,
                'database_host' => 'localhost'
            ]
        ]
    ];
}

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_backup_config'])) {
    try {
        // Update automated backup settings
        $backupConfig['automated_backups']['enabled'] = isset($_POST['backup_enabled']);
        $backupConfig['automated_backups']['email_address'] = $_POST['backup_email'] ?? '';
        $backupConfig['automated_backups']['frequency'] = $_POST['backup_frequency'] ?? 'daily';
        $backupConfig['automated_backups']['include_database'] = isset($_POST['include_database']);
        $backupConfig['automated_backups']['include_config'] = isset($_POST['include_config']);
        $backupConfig['automated_backups']['include_logs'] = isset($_POST['include_logs']);
        $backupConfig['automated_backups']['max_backups'] = (int)($_POST['max_backups'] ?? 30);
        $backupConfig['automated_backups']['compression'] = isset($_POST['compression']);
        
        // Update recovery settings
        $backupConfig['recovery']['install_email'] = $_POST['install_email'] ?? '';
        $backupConfig['recovery']['recovery_instructions'] = $_POST['recovery_instructions'] ?? '';
        
        // Update emergency contacts
        $emergencyContacts = [];
        if (!empty($_POST['emergency_contacts'])) {
            $contacts = explode("\n", $_POST['emergency_contacts']);
            foreach ($contacts as $contact) {
                $contact = trim($contact);
                if (!empty($contact)) {
                    $emergencyContacts[] = $contact;
                }
            }
        }
        $backupConfig['recovery']['emergency_contacts'] = $emergencyContacts;
        
        // Update server details
        $backupConfig['recovery']['server_details']['hostname'] = $_POST['hostname'] ?? $_SERVER['SERVER_NAME'] ?? '';
        $backupConfig['recovery']['server_details']['document_root'] = $_POST['document_root'] ?? $_SERVER['DOCUMENT_ROOT'] ?? '';
        $backupConfig['recovery']['server_details']['database_host'] = $_POST['database_host'] ?? 'localhost';
        
        // Write configuration back to file
        $configContent = "<?php\n\nreturn " . var_export($backupConfig, true) . ";";
        file_put_contents($backupConfigFile, $configContent);
        
        $message = "Backup configuration saved successfully!";
        $messageType = 'success';
        
        // Create cron job if enabled
        if ($backupConfig['automated_backups']['enabled']) {
            $cronFile = dirname(__DIR__) . '/scripts/setup_backup_cron.sh';
            $cronContent = createBackupCronScript($backupConfig['automated_backups']['frequency']);
            file_put_contents($cronFile, $cronContent);
            chmod($cronFile, 0755);
            
            $message .= "\n\nCron setup script created at: scripts/setup_backup_cron.sh";
            $message .= "\nRun: sudo bash scripts/setup_backup_cron.sh";
        }
        
    } catch (Exception $e) {
        $message = "Error saving backup configuration: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle test backup
if (isset($_POST['test_backup'])) {
    try {
        $testBackupResult = createTestBackup($backupConfig);
        $message = "Test backup created successfully!\n\n" . $testBackupResult;
        $messageType = 'success';
    } catch (Exception $e) {
        $message = "Test backup failed: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Handle send recovery email
if (isset($_POST['send_recovery_info'])) {
    try {
        $recoveryInfo = generateRecoveryInfo($backupConfig);
        $emailSent = sendRecoveryEmail($backupConfig['recovery']['install_email'], $recoveryInfo);
        
        if ($emailSent) {
            $message = "Recovery information sent successfully to " . $backupConfig['recovery']['install_email'];
            $messageType = 'success';
        } else {
            $message = "Failed to send recovery email. Please check email configuration.";
            $messageType = 'error';
        }
    } catch (Exception $e) {
        $message = "Error sending recovery info: " . $e->getMessage();
        $messageType = 'error';
    }
}

function createBackupCronScript($frequency): string
{
    $cronExpression = match($frequency) {
        'hourly' => '0 * * * *',
        'daily' => '0 2 * * *',      // 2 AM daily
        'weekly' => '0 2 * * 0',     // 2 AM Sunday
        'monthly' => '0 2 1 * *',    // 2 AM 1st of month
        default => '0 2 * * *'
    };
    
    $scriptPath = dirname(__DIR__) . '/scripts/automated_backup.php';
    
    return <<<BASH
#!/bin/bash
# Setup automated backup cron job

# Add to crontab
(crontab -l 2>/dev/null; echo "{$cronExpression} /usr/bin/php {$scriptPath}") | crontab -

echo "Backup cron job added: {$frequency} at 2 AM"
echo "Cron expression: {$cronExpression}"
echo "Script: {$scriptPath}"
BASH;
}

function createTestBackup($config): string
{
    $backupDir = dirname(__DIR__) . '/storage/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . "/test_backup_{$timestamp}.tar.gz";
    
    // Create backup archive
    $sourceDir = dirname(__DIR__);
    $excludes = '--exclude=storage/backups --exclude=vendor --exclude=.git --exclude=node_modules';
    
    $command = "cd {$sourceDir} && tar {$excludes} -czf {$backupFile} .";
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($backupFile)) {
        $size = number_format(filesize($backupFile) / (1024 * 1024), 2);
        return "Backup created: " . basename($backupFile) . " ({$size} MB)";
    } else {
        throw new Exception("Failed to create backup archive");
    }
}

function generateRecoveryInfo($config): string
{
    $info = "=== YFEvents System Recovery Information ===\n\n";
    $info .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";
    
    $info .= "SERVER DETAILS:\n";
    $info .= "- Hostname: " . $config['recovery']['server_details']['hostname'] . "\n";
    $info .= "- Document Root: " . $config['recovery']['server_details']['document_root'] . "\n";
    $info .= "- PHP Version: " . $config['recovery']['server_details']['php_version'] . "\n";
    $info .= "- Database Host: " . $config['recovery']['server_details']['database_host'] . "\n\n";
    
    $info .= "BACKUP CONFIGURATION:\n";
    $info .= "- Enabled: " . ($config['automated_backups']['enabled'] ? 'Yes' : 'No') . "\n";
    $info .= "- Frequency: " . $config['automated_backups']['frequency'] . "\n";
    $info .= "- Backup Email: " . $config['automated_backups']['email_address'] . "\n";
    $info .= "- Max Backups: " . $config['automated_backups']['max_backups'] . "\n\n";
    
    $info .= "EMERGENCY CONTACTS:\n";
    foreach ($config['recovery']['emergency_contacts'] as $contact) {
        $info .= "- " . $contact . "\n";
    }
    $info .= "\n";
    
    $info .= "RECOVERY INSTRUCTIONS:\n";
    $info .= $config['recovery']['recovery_instructions'] . "\n\n";
    
    $info .= "QUICK RECOVERY STEPS:\n";
    $info .= "1. Download latest backup from email\n";
    $info .= "2. Extract to server: tar -xzf backup_file.tar.gz\n";
    $info .= "3. Restore database: mysql -u user -p database < backup.sql\n";
    $info .= "4. Update config files with current server details\n";
    $info .= "5. Set proper permissions: chmod 755 directories, 644 files\n";
    $info .= "6. Test functionality\n\n";
    
    $info .= "IMPORTANT FILES:\n";
    $info .= "- config/database.php (database connection)\n";
    $info .= "- config/email.php (email settings)\n";
    $info .= "- config/api_keys.php (API keys)\n";
    $info .= "- .htaccess (URL rewriting)\n\n";
    
    return $info;
}

function sendRecoveryEmail($email, $info): bool
{
    if (empty($email)) {
        return false;
    }
    
    $subject = "YFEvents Recovery Information - " . date('Y-m-d');
    $headers = "From: yakimafinds@gmail.com\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    return mail($email, $subject, $info, $headers);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Configuration - Admin</title>
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
        .backup-status {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .backup-enabled {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .backup-disabled {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .recovery-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
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
                            <a class="nav-link" href="<?= $basePath ?>/admin/dashboard">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/events.php">Events</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $basePath ?>/admin/email-config.php">Email Config</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="<?= $basePath ?>/admin/backup-config.php">Backup Config</a>
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
                    <h1>🔒 Backup & Recovery Configuration</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                        <pre style="margin: 0; white-space: pre-wrap;"><?= htmlspecialchars($message) ?></pre>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Backup Status -->
                <div class="backup-status <?= $backupConfig['automated_backups']['enabled'] ? 'backup-enabled' : 'backup-disabled' ?>">
                    <h4>
                        <?php if ($backupConfig['automated_backups']['enabled']): ?>
                            ✅ Automated Backups: ENABLED
                        <?php else: ?>
                            ❌ Automated Backups: DISABLED
                        <?php endif; ?>
                    </h4>
                    <p>
                        <?php if ($backupConfig['automated_backups']['enabled']): ?>
                            Backups running <?= $backupConfig['automated_backups']['frequency'] ?> to: <?= $backupConfig['automated_backups']['email_address'] ?>
                        <?php else: ?>
                            Configure automated backups below for system protection
                        <?php endif; ?>
                    </p>
                </div>

                <form method="post">
                    <!-- Automated Backup Settings -->
                    <div class="config-section">
                        <h3>📧 Automated Backup Settings</h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="backup_enabled" name="backup_enabled" 
                                           <?= $backupConfig['automated_backups']['enabled'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="backup_enabled">
                                        <strong>Enable Automated Backups</strong>
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="backup_email" class="form-label">Backup Delivery Email</label>
                                    <input type="email" class="form-control" id="backup_email" name="backup_email" 
                                           value="<?= htmlspecialchars($backupConfig['automated_backups']['email_address']) ?>" 
                                           placeholder="admin@yakimafinds.com">
                                    <div class="form-text">Backup files will be emailed to this address</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="backup_frequency" class="form-label">Backup Frequency</label>
                                    <select class="form-control" id="backup_frequency" name="backup_frequency">
                                        <option value="daily" <?= $backupConfig['automated_backups']['frequency'] === 'daily' ? 'selected' : '' ?>>Daily</option>
                                        <option value="weekly" <?= $backupConfig['automated_backups']['frequency'] === 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                        <option value="monthly" <?= $backupConfig['automated_backups']['frequency'] === 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">What to Include</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_database" name="include_database" 
                                               <?= $backupConfig['automated_backups']['include_database'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="include_database">Database</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_config" name="include_config" 
                                               <?= $backupConfig['automated_backups']['include_config'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="include_config">Configuration Files</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="include_logs" name="include_logs" 
                                               <?= $backupConfig['automated_backups']['include_logs'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="include_logs">Log Files</label>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="max_backups" class="form-label">Max Backups to Keep</label>
                                    <input type="number" class="form-control" id="max_backups" name="max_backups" 
                                           value="<?= $backupConfig['automated_backups']['max_backups'] ?>" min="1" max="100">
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="compression" name="compression" 
                                           <?= $backupConfig['automated_backups']['compression'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="compression">Compress Backups</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recovery Information -->
                    <div class="config-section">
                        <h3>🆘 Recovery Information</h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="install_email" class="form-label">Install Tool Email</label>
                                    <input type="email" class="form-control" id="install_email" name="install_email" 
                                           value="<?= htmlspecialchars($backupConfig['recovery']['install_email']) ?>" 
                                           placeholder="installer@company.com">
                                    <div class="form-text">Email for rapid recovery instructions</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="emergency_contacts" class="form-label">Emergency Contacts</label>
                                    <textarea class="form-control" id="emergency_contacts" name="emergency_contacts" rows="4" 
                                              placeholder="admin@yakimafinds.com&#10;tech@company.com&#10;+1-555-123-4567"><?= htmlspecialchars(implode("\n", $backupConfig['recovery']['emergency_contacts'])) ?></textarea>
                                    <div class="form-text">One contact per line (emails, phone numbers)</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="hostname" class="form-label">Server Hostname</label>
                                    <input type="text" class="form-control" id="hostname" name="hostname" 
                                           value="<?= htmlspecialchars($backupConfig['recovery']['server_details']['hostname']) ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="document_root" class="form-label">Document Root</label>
                                    <input type="text" class="form-control" id="document_root" name="document_root" 
                                           value="<?= htmlspecialchars($backupConfig['recovery']['server_details']['document_root']) ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="database_host" class="form-label">Database Host</label>
                                    <input type="text" class="form-control" id="database_host" name="database_host" 
                                           value="<?= htmlspecialchars($backupConfig['recovery']['server_details']['database_host']) ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="recovery_instructions" class="form-label">Custom Recovery Instructions</label>
                            <textarea class="form-control" id="recovery_instructions" name="recovery_instructions" rows="4" 
                                      placeholder="Special instructions for recovering this system..."><?= htmlspecialchars($backupConfig['recovery']['recovery_instructions']) ?></textarea>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div class="text-end mb-4">
                        <button type="submit" name="save_backup_config" class="btn btn-primary btn-lg">
                            💾 Save Backup Configuration
                        </button>
                    </div>
                </form>

                <!-- Test & Recovery Actions -->
                <div class="config-section">
                    <h3>🧪 Test & Recovery Actions</h3>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <form method="post" style="display: inline;">
                                <button type="submit" name="test_backup" class="btn btn-warning btn-lg w-100 mb-2">
                                    🧪 Create Test Backup
                                </button>
                            </form>
                            <small class="text-muted">Creates a one-time backup to test the process</small>
                        </div>
                        
                        <div class="col-md-4">
                            <form method="post" style="display: inline;">
                                <button type="submit" name="send_recovery_info" class="btn btn-info btn-lg w-100 mb-2"
                                        <?= empty($backupConfig['recovery']['install_email']) ? 'disabled' : '' ?>>
                                    📧 Send Recovery Info
                                </button>
                            </form>
                            <small class="text-muted">Emails complete recovery information</small>
                        </div>
                        
                        <div class="col-md-4">
                            <a href="<?= $basePath ?>/storage/backups" class="btn btn-secondary btn-lg w-100 mb-2">
                                📁 View Backups
                            </a>
                            <small class="text-muted">Browse existing backup files</small>
                        </div>
                    </div>
                </div>

                <!-- Recovery Information Preview -->
                <?php if (!empty($backupConfig['recovery']['install_email'])): ?>
                <div class="config-section">
                    <h3>📋 Recovery Information Preview</h3>
                    <div class="recovery-preview"><?= htmlspecialchars(generateRecoveryInfo($backupConfig)) ?></div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>