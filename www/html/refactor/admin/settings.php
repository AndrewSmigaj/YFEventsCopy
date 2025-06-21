<?php
// Admin Settings Management Page
require_once __DIR__ . '/bootstrap.php';

// Get database connection
$db = $GLOBALS['db'] ?? null;

// Load configuration
$configFile = __DIR__ . '/../config/app.php';
$config = include $configFile;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_sms') {
        $config['sms']['enabled'] = isset($_POST['sms_enabled']);
        $config['sms']['provider'] = $_POST['sms_provider'] ?? 'twilio';
        $config['sms']['test_mode'] = isset($_POST['sms_test_mode']);
        
        // Update provider-specific settings
        if (isset($_POST['twilio_account_sid'])) {
            $config['sms']['twilio']['account_sid'] = $_POST['twilio_account_sid'];
            $config['sms']['twilio']['auth_token'] = $_POST['twilio_auth_token'];
            $config['sms']['twilio']['from_number'] = $_POST['twilio_from_number'];
        }
        
        if (isset($_POST['aws_key'])) {
            $config['sms']['aws']['key'] = $_POST['aws_key'];
            $config['sms']['aws']['secret'] = $_POST['aws_secret'];
            $config['sms']['aws']['region'] = $_POST['aws_region'];
            $config['sms']['aws']['from_number'] = $_POST['aws_from_number'];
        }
        
        if (isset($_POST['nexmo_api_key'])) {
            $config['sms']['nexmo']['api_key'] = $_POST['nexmo_api_key'];
            $config['sms']['nexmo']['api_secret'] = $_POST['nexmo_api_secret'];
            $config['sms']['nexmo']['from_number'] = $_POST['nexmo_from_number'];
        }
        
        // Save configuration
        $configContent = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($configFile, $configContent);
        $successMessage = "SMS settings updated successfully!";
    }
    
    if ($_POST['action'] === 'update_email') {
        $config['email']['enabled'] = isset($_POST['email_enabled']);
        $config['email']['driver'] = $_POST['email_driver'] ?? 'mail';
        $config['email']['from_email'] = $_POST['email_from_email'];
        $config['email']['from_name'] = $_POST['email_from_name'];
        $config['email']['test_mode'] = isset($_POST['email_test_mode']);
        
        if (isset($_POST['smtp_host'])) {
            $config['email']['smtp']['host'] = $_POST['smtp_host'];
            $config['email']['smtp']['port'] = (int)$_POST['smtp_port'];
            $config['email']['smtp']['username'] = $_POST['smtp_username'];
            $config['email']['smtp']['password'] = $_POST['smtp_password'];
            $config['email']['smtp']['encryption'] = $_POST['smtp_encryption'];
        }
        
        // Save configuration
        $configContent = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        file_put_contents($configFile, $configContent);
        $successMessage = "Email settings updated successfully!";
    }
    
    if ($_POST['action'] === 'test_sms') {
        $testNumber = $_POST['test_number'];
        $testMessage = "Test SMS from YFClaim: " . date('Y-m-d H:i:s');
        
        // This is a placeholder - in production, you'd use the actual SMS service
        $testMessage = "SMS test would be sent to: {$testNumber} with message: {$testMessage}";
        $testResult = "Test mode: SMS sending simulated successfully";
    }
    
    if ($_POST['action'] === 'test_email') {
        $testEmail = $_POST['test_email'];
        $subject = 'Test Email from YFClaim';
        $message = "This is a test email sent from YFClaim admin panel at " . date('Y-m-d H:i:s');
        
        $headers = "From: {$config['email']['from_name']} <{$config['email']['from_email']}>\r\n";
        $headers .= "Reply-To: {$config['email']['from_email']}\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        $sent = mail($testEmail, $subject, $message, $headers);
        $testResult = $sent ? "Test email sent successfully to {$testEmail}" : "Failed to send test email";
    }
}

$basePath = '/refactor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/admin-styles.css">
    <style>
        .settings-tabs {
            display: flex;
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 2rem;
        }
        
        .tab-button {
            background: none;
            border: none;
            padding: 1rem 2rem;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            font-weight: 500;
            color: #6c757d;
            transition: all 0.3s;
        }
        
        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .settings-section {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .provider-settings {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .provider-settings.active {
            display: block;
        }
        
        .test-section {
            background: #e7f3ff;
            border: 1px solid #b8daff;
            border-radius: 6px;
            padding: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #e7f3ff;
            color: #0c5460;
            border: 1px solid #b8daff;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #667eea;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .config-status {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-enabled {
            background: #d4edda;
            color: #155724;
        }
        
        .status-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-test {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/admin-navigation.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <div class="container-fluid">
                    <h1><i class="bi bi-palette"></i> Settings</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Settings</li>
                        </ol>
                    </nav>
                </div>
            </div>
            
            <div class="main-content">
        <div class="page-header">
            <h2 class="page-title">System Settings</h2>
            <div>
                <span class="config-status <?= $config['sms']['enabled'] ? 'status-enabled' : 'status-disabled' ?>">
                    SMS: <?= $config['sms']['enabled'] ? 'Enabled' : 'Disabled' ?>
                </span>
                <span class="config-status <?= $config['email']['enabled'] ? 'status-enabled' : 'status-disabled' ?>">
                    Email: <?= $config['email']['enabled'] ? 'Enabled' : 'Disabled' ?>
                </span>
            </div>
        </div>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($testResult)): ?>
            <div class="alert alert-info">
                <strong>Test Result:</strong> <?= htmlspecialchars($testResult) ?>
            </div>
        <?php endif; ?>
        
        <div class="settings-tabs">
            <button class="tab-button active" onclick="showTab('sms')">SMS Configuration</button>
            <button class="tab-button" onclick="showTab('email')">Email Configuration</button>
            <button class="tab-button" onclick="showTab('general')">General Settings</button>
        </div>
        
        <!-- SMS Configuration Tab -->
        <div id="sms-tab" class="tab-content active">
            <div class="settings-section">
                <h3 class="section-title">SMS Service Configuration</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_sms">
                    
                    <div class="form-group">
                        <label class="form-label">
                            <label class="toggle-switch">
                                <input type="checkbox" name="sms_enabled" <?= $config['sms']['enabled'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                            Enable SMS Service
                        </label>
                        <small class="form-help">Enable SMS notifications for buyer verification and offer updates</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="sms_provider" class="form-label">SMS Provider</label>
                        <select id="sms_provider" name="sms_provider" class="form-input" onchange="showProviderSettings(this.value)">
                            <option value="twilio" <?= $config['sms']['provider'] === 'twilio' ? 'selected' : '' ?>>Twilio</option>
                            <option value="aws" <?= $config['sms']['provider'] === 'aws' ? 'selected' : '' ?>>AWS SNS</option>
                            <option value="nexmo" <?= $config['sms']['provider'] === 'nexmo' ? 'selected' : '' ?>>Nexmo/Vonage</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <label class="toggle-switch">
                                <input type="checkbox" name="sms_test_mode" <?= $config['sms']['test_mode'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                            Test Mode
                        </label>
                        <small class="form-help">In test mode, SMS will only be logged, not actually sent</small>
                    </div>
                    
                    <!-- Twilio Settings -->
                    <div id="twilio-settings" class="provider-settings <?= $config['sms']['provider'] === 'twilio' ? 'active' : '' ?>">
                        <h4>Twilio Configuration</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="twilio_account_sid" class="form-label">Account SID</label>
                                <input type="text" id="twilio_account_sid" name="twilio_account_sid" 
                                       value="<?= htmlspecialchars($config['sms']['twilio']['account_sid']) ?>" 
                                       class="form-input" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                            </div>
                            <div class="form-group">
                                <label for="twilio_auth_token" class="form-label">Auth Token</label>
                                <input type="password" id="twilio_auth_token" name="twilio_auth_token" 
                                       value="<?= htmlspecialchars($config['sms']['twilio']['auth_token']) ?>" 
                                       class="form-input" placeholder="Your Twilio Auth Token">
                            </div>
                            <div class="form-group">
                                <label for="twilio_from_number" class="form-label">From Phone Number</label>
                                <input type="tel" id="twilio_from_number" name="twilio_from_number" 
                                       value="<?= htmlspecialchars($config['sms']['twilio']['from_number']) ?>" 
                                       class="form-input" placeholder="+1234567890">
                            </div>
                        </div>
                    </div>
                    
                    <!-- AWS SNS Settings -->
                    <div id="aws-settings" class="provider-settings <?= $config['sms']['provider'] === 'aws' ? 'active' : '' ?>">
                        <h4>AWS SNS Configuration</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="aws_key" class="form-label">Access Key ID</label>
                                <input type="text" id="aws_key" name="aws_key" 
                                       value="<?= htmlspecialchars($config['sms']['aws']['key']) ?>" 
                                       class="form-input" placeholder="AKIAIOSFODNN7EXAMPLE">
                            </div>
                            <div class="form-group">
                                <label for="aws_secret" class="form-label">Secret Access Key</label>
                                <input type="password" id="aws_secret" name="aws_secret" 
                                       value="<?= htmlspecialchars($config['sms']['aws']['secret']) ?>" 
                                       class="form-input" placeholder="Your AWS Secret Key">
                            </div>
                            <div class="form-group">
                                <label for="aws_region" class="form-label">AWS Region</label>
                                <select id="aws_region" name="aws_region" class="form-input">
                                    <option value="us-east-1" <?= $config['sms']['aws']['region'] === 'us-east-1' ? 'selected' : '' ?>>US East (N. Virginia)</option>
                                    <option value="us-west-2" <?= $config['sms']['aws']['region'] === 'us-west-2' ? 'selected' : '' ?>>US West (Oregon)</option>
                                    <option value="eu-west-1" <?= $config['sms']['aws']['region'] === 'eu-west-1' ? 'selected' : '' ?>>Europe (Ireland)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="aws_from_number" class="form-label">From Phone Number</label>
                                <input type="tel" id="aws_from_number" name="aws_from_number" 
                                       value="<?= htmlspecialchars($config['sms']['aws']['from_number']) ?>" 
                                       class="form-input" placeholder="+1234567890">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Nexmo Settings -->
                    <div id="nexmo-settings" class="provider-settings <?= $config['sms']['provider'] === 'nexmo' ? 'active' : '' ?>">
                        <h4>Nexmo/Vonage Configuration</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="nexmo_api_key" class="form-label">API Key</label>
                                <input type="text" id="nexmo_api_key" name="nexmo_api_key" 
                                       value="<?= htmlspecialchars($config['sms']['nexmo']['api_key']) ?>" 
                                       class="form-input" placeholder="Your Nexmo API Key">
                            </div>
                            <div class="form-group">
                                <label for="nexmo_api_secret" class="form-label">API Secret</label>
                                <input type="password" id="nexmo_api_secret" name="nexmo_api_secret" 
                                       value="<?= htmlspecialchars($config['sms']['nexmo']['api_secret']) ?>" 
                                       class="form-input" placeholder="Your Nexmo API Secret">
                            </div>
                            <div class="form-group">
                                <label for="nexmo_from_number" class="form-label">From Phone Number</label>
                                <input type="tel" id="nexmo_from_number" name="nexmo_from_number" 
                                       value="<?= htmlspecialchars($config['sms']['nexmo']['from_number']) ?>" 
                                       class="form-input" placeholder="+1234567890">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save SMS Settings</button>
                    </div>
                </form>
                
                <!-- SMS Test Section -->
                <div class="test-section">
                    <h4>Test SMS Service</h4>
                    <form method="POST" style="display: flex; gap: 1rem; align-items: end;">
                        <input type="hidden" name="action" value="test_sms">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="test_number" class="form-label">Test Phone Number</label>
                            <input type="tel" id="test_number" name="test_number" 
                                   class="form-input" placeholder="+1234567890" required>
                        </div>
                        <button type="submit" class="btn btn-secondary">Send Test SMS</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Email Configuration Tab -->
        <div id="email-tab" class="tab-content">
            <div class="settings-section">
                <h3 class="section-title">Email Service Configuration</h3>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_email">
                    
                    <div class="form-group">
                        <label class="form-label">
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_enabled" <?= $config['email']['enabled'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                            Enable Email Service
                        </label>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="email_driver" class="form-label">Email Driver</label>
                            <select id="email_driver" name="email_driver" class="form-input" onchange="showEmailSettings(this.value)">
                                <option value="mail" <?= $config['email']['driver'] === 'mail' ? 'selected' : '' ?>>PHP Mail</option>
                                <option value="smtp" <?= $config['email']['driver'] === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="email_from_email" class="form-label">From Email</label>
                            <input type="email" id="email_from_email" name="email_from_email" 
                                   value="<?= htmlspecialchars($config['email']['from_email']) ?>" 
                                   class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label for="email_from_name" class="form-label">From Name</label>
                            <input type="text" id="email_from_name" name="email_from_name" 
                                   value="<?= htmlspecialchars($config['email']['from_name']) ?>" 
                                   class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">
                                <label class="toggle-switch">
                                    <input type="checkbox" name="email_test_mode" <?= $config['email']['test_mode'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                                Test Mode
                            </label>
                        </div>
                    </div>
                    
                    <!-- SMTP Settings -->
                    <div id="smtp-settings" class="provider-settings <?= $config['email']['driver'] === 'smtp' ? 'active' : '' ?>">
                        <h4>SMTP Configuration</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="smtp_host" class="form-label">SMTP Host</label>
                                <input type="text" id="smtp_host" name="smtp_host" 
                                       value="<?= htmlspecialchars($config['email']['smtp']['host']) ?>" 
                                       class="form-input" placeholder="smtp.gmail.com">
                            </div>
                            <div class="form-group">
                                <label for="smtp_port" class="form-label">SMTP Port</label>
                                <input type="number" id="smtp_port" name="smtp_port" 
                                       value="<?= $config['email']['smtp']['port'] ?>" 
                                       class="form-input" placeholder="587">
                            </div>
                            <div class="form-group">
                                <label for="smtp_username" class="form-label">SMTP Username</label>
                                <input type="text" id="smtp_username" name="smtp_username" 
                                       value="<?= htmlspecialchars($config['email']['smtp']['username']) ?>" 
                                       class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="smtp_password" class="form-label">SMTP Password</label>
                                <input type="password" id="smtp_password" name="smtp_password" 
                                       value="<?= htmlspecialchars($config['email']['smtp']['password']) ?>" 
                                       class="form-input">
                            </div>
                            <div class="form-group">
                                <label for="smtp_encryption" class="form-label">Encryption</label>
                                <select id="smtp_encryption" name="smtp_encryption" class="form-input">
                                    <option value="tls" <?= $config['email']['smtp']['encryption'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= $config['email']['smtp']['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Email Settings</button>
                    </div>
                </form>
                
                <!-- Email Test Section -->
                <div class="test-section">
                    <h4>Test Email Service</h4>
                    <form method="POST" style="display: flex; gap: 1rem; align-items: end;">
                        <input type="hidden" name="action" value="test_email">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label for="test_email" class="form-label">Test Email Address</label>
                            <input type="email" id="test_email" name="test_email" 
                                   class="form-input" placeholder="test@example.com" required>
                        </div>
                        <button type="submit" class="btn btn-secondary">Send Test Email</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- General Settings Tab -->
        <div id="general-tab" class="tab-content">
            <div class="settings-section">
                <h3 class="section-title">General Configuration</h3>
                <p>Other system settings would go here...</p>
            </div>
        </div>
    </div>
    
    <script>
        const basePath = '<?php echo $basePath; ?>' || '/refactor';
        
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Hide all tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            event.target.classList.add('active');
        }
        
        function showProviderSettings(provider) {
            // Hide all provider settings
            document.querySelectorAll('.provider-settings').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected provider settings
            document.getElementById(provider + '-settings').classList.add('active');
        }
        
        function showEmailSettings(driver) {
            if (driver === 'smtp') {
                document.getElementById('smtp-settings').classList.add('active');
            } else {
                document.getElementById('smtp-settings').classList.remove('active');
            }
        }
        
        async function logout() {
            try {
                const response = await fetch(`${basePath}/admin/logout`, { method: 'POST' });
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = `${basePath}/admin/login`;
                }
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = `${basePath}/admin/login`;
            }
        }
    </script>
</body>
</html>