<?php
// Backup of configuration data - Generated: <?= date('Y-m-d H:i:s') ?>

// Email Configuration Backup
$email_config_backup = <?= var_export(require 'email.php', true) ?>;

// Database Configuration Backup  
$database_config_backup = <?= var_export(require 'database.php', true) ?>;

// API Keys Configuration Backup
<?php if (file_exists('api_keys.php')): ?>
$api_keys_backup = <?= var_export(require 'api_keys.php', true) ?>;
<?php else: ?>
$api_keys_backup = [
    'google_maps' => 'AIzaSyDuWYMutN01MWHxayMcpERvofDU6SRrL30',
    'backup_date' => '<?= date('Y-m-d H:i:s') ?>'
];
<?php endif; ?>

// System Configuration
$system_backup = [
    'backup_created' => '<?= date('Y-m-d H:i:s') ?>',
    'php_version' => '<?= PHP_VERSION ?>',
    'server_info' => '<?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?>',
    'document_root' => '<?= $_SERVER['DOCUMENT_ROOT'] ?? '' ?>',
    'project_path' => '<?= dirname(__DIR__) ?>'
];

return [
    'email' => $email_config_backup,
    'database' => $database_config_backup,
    'api_keys' => $api_keys_backup,
    'system' => $system_backup
];