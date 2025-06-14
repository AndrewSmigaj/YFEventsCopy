<?php
// YFClaim - Buyer Authentication
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use YFEvents\Modules\YFClaim\Models\BuyerModel;

// Start session
session_start();

// Initialize models
$buyerModel = new BuyerModel($pdo);

// Get return URL
$returnUrl = $_GET['return'] ?? '/modules/yfclaim/www/';

// Handle form submissions
$message = '';
$error = '';
$step = 'contact'; // contact, verify

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'send_code') {
            $name = trim($_POST['name'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $method = $_POST['method'] ?? 'email';
            
            if (empty($name) || empty($contact)) {
                $error = 'Please fill in all required fields.';
            } else {
                // Validate contact format
                if ($method === 'email' && !filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Please enter a valid email address.';
                } elseif ($method === 'sms' && !preg_match('/^\+?[\d\s\-\(\)]{10,}$/', $contact)) {
                    $error = 'Please enter a valid phone number.';
                } else {
                    // Check if buyer already exists
                    $buyer = $buyerModel->findByContact($contact, $method);
                    
                    if ($buyer) {
                        // Send new verification code to existing buyer
                        $authCode = $buyerModel->generateAuthCode($buyer['id']);
                        $buyerId = $buyer['id'];
                        
                        // Update name if different
                        if ($buyer['name'] !== $name) {
                            $buyerModel->update($buyer['id'], ['name' => $name]);
                        }
                    } else {
                        // Create new buyer
                        $buyerId = $buyerModel->create([
                            'name' => $name,
                            'contact_method' => $method,
                            'contact_value' => $contact,
                            'status' => 'pending',
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        if (!$buyerId) {
                            throw new Exception('Failed to create buyer account');
                        }
                        
                        $authCode = $buyerModel->generateAuthCode($buyerId);
                    }
                    
                    // Store in session
                    $_SESSION['buyer_auth'] = [
                        'buyer_id' => $buyerId,
                        'method' => $method,
                        'contact' => $contact,
                        'timestamp' => time()
                    ];
                    
                    // In production, send actual email/SMS here
                    // For demo purposes, we'll show the code
                    if ($method === 'email') {
                        // TODO: Send email with auth code
                        $message = "Verification code sent to your email address. Code: <strong>{$authCode}</strong>";
                    } else {
                        // TODO: Send SMS with auth code
                        $message = "Verification code sent to your phone via SMS. Code: <strong>{$authCode}</strong>";
                    }
                    
                    $step = 'verify';
                }
            }
            
        } elseif (isset($_POST['action']) && $_POST['action'] === 'verify_code') {
            $code = trim($_POST['code'] ?? '');
            $authData = $_SESSION['buyer_auth'] ?? null;
            
            if (!$authData || (time() - $authData['timestamp']) > 1800) { // 30 minutes
                $error = 'Session expired. Please start over.';
                unset($_SESSION['buyer_auth']);
            } elseif (empty($code)) {
                $error = 'Please enter the verification code.';
                $step = 'verify';
            } else {
                // Verify the code
                $isValid = $buyerModel->verifyAuthCode($authData['buyer_id'], $code);
                
                if ($isValid) {
                    // Generate session token
                    $sessionToken = $buyerModel->createSession($authData['buyer_id']);
                    
                    if ($sessionToken) {
                        // Set session and cookie
                        $_SESSION['buyer_token'] = $sessionToken;
                        setcookie('yfclaim_buyer_token', $sessionToken, time() + (86400 * 30), '/'); // 30 days
                        
                        // Update buyer status to active
                        $buyerModel->update($authData['buyer_id'], ['status' => 'active']);
                        
                        // Clear auth session
                        unset($_SESSION['buyer_auth']);
                        
                        // Redirect to return URL
                        header('Location: ' . $returnUrl);
                        exit;
                    } else {
                        $error = 'Failed to create session. Please try again.';
                    }
                } else {
                    $error = 'Invalid or expired verification code.';
                    $step = 'verify';
                }
            }
        }
        
    } catch (Exception $e) {
        $error = 'An error occurred: ' . $e->getMessage();
    }
} elseif (isset($_SESSION['buyer_auth'])) {
    // Resume verification step if session exists
    $step = 'verify';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Login - YFClaim Estate Sales</title>
    
    <meta name="description" content="Sign in to YFClaim to place offers on estate sale items. Quick verification via email or SMS.">
    <meta name="robots" content="noindex, nofollow">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 3rem;
            width: 100%;
            max-width: 450px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: #7f8c8d;
            font-size: 1.1rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .step.active {
            background: #3498db;
            color: white;
        }
        
        .step.inactive {
            background: #ecf0f1;
            color: #7f8c8d;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-help {
            font-size: 0.875rem;
            color: #7f8c8d;
            margin-top: 0.5rem;
        }
        
        .btn {
            width: 100%;
            padding: 0.875rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
            margin-top: 1rem;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .back-link {
            text-align: center;
            margin-top: 2rem;
        }
        
        .back-link a {
            color: #7f8c8d;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .back-link a:hover {
            color: #3498db;
        }
        
        .method-selection {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .method-option {
            padding: 1rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .method-option:hover {
            border-color: #3498db;
        }
        
        .method-option.selected {
            border-color: #3498db;
            background: #f8f9fa;
        }
        
        .method-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .method-title {
            font-weight: 600;
            color: #2c3e50;
        }
        
        .method-desc {
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-top: 0.25rem;
        }
        
        .code-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            font-weight: bold;
        }
        
        .security-note {
            background: #e8f4fd;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            color: #0c5460;
        }
        
        .security-note strong {
            color: #085964;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 2rem;
            }
            
            .method-selection {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <div class="logo">YFClaim</div>
            <div class="subtitle">Secure Buyer Authentication</div>
        </div>
        
        <div class="step-indicator">
            <div class="step <?= $step === 'contact' ? 'active' : 'inactive' ?>">
                <span>1️⃣</span>
                <span>Contact Info</span>
            </div>
            <div style="width: 2rem; height: 2px; background: #e9ecef; margin: 0 0.5rem; align-self: center;"></div>
            <div class="step <?= $step === 'verify' ? 'active' : 'inactive' ?>">
                <span>2️⃣</span>
                <span>Verification</span>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if ($step === 'contact'): ?>
            <form method="POST">
                <input type="hidden" name="action" value="send_code">
                
                <div class="security-note">
                    <strong>🔒 Privacy First:</strong> We only use your contact information for verification and important sale notifications. Your information is never shared with third parties.
                </div>
                
                <div class="form-group">
                    <label for="name">Your Name</label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           required 
                           placeholder="Enter your full name"
                           value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>">
                    <div class="form-help">This name will be used for offer notifications</div>
                </div>
                
                <div class="form-group">
                    <label>Verification Method</label>
                    <div class="method-selection">
                        <label class="method-option selected" for="method-email">
                            <input type="radio" id="method-email" name="method" value="email" checked style="display: none;">
                            <div class="method-icon">📧</div>
                            <div class="method-title">Email</div>
                            <div class="method-desc">Secure & reliable</div>
                        </label>
                        <label class="method-option" for="method-sms">
                            <input type="radio" id="method-sms" name="method" value="sms" style="display: none;">
                            <div class="method-icon">📱</div>
                            <div class="method-title">SMS</div>
                            <div class="method-desc">Quick & instant</div>
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="contact" id="contact-label">Email Address</label>
                    <input type="email" 
                           id="contact" 
                           name="contact" 
                           required 
                           placeholder="your@email.com"
                           value="<?= isset($_POST['contact']) ? htmlspecialchars($_POST['contact']) : '' ?>">
                    <div class="form-help" id="contact-help">We'll send you a secure verification code</div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Send Verification Code
                </button>
            </form>
            
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="verify_code">
                
                <div class="security-note">
                    <strong>📱 Check your <?= $_SESSION['buyer_auth']['method'] === 'email' ? 'email inbox' : 'text messages' ?>:</strong>
                    We've sent a 6-digit verification code to 
                    <strong><?= htmlspecialchars($_SESSION['buyer_auth']['contact']) ?></strong>
                </div>
                
                <div class="form-group">
                    <label for="code">Verification Code</label>
                    <input type="text" 
                           id="code" 
                           name="code" 
                           class="code-input"
                           maxlength="6" 
                           required 
                           autofocus 
                           placeholder="000000"
                           pattern="[0-9]{6}"
                           autocomplete="one-time-code">
                    <div class="form-help">Enter the 6-digit code from your <?= $_SESSION['buyer_auth']['method'] === 'email' ? 'email' : 'text message' ?></div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Verify & Sign In
                </button>
                
                <form method="POST" style="margin-top: 1rem;">
                    <input type="hidden" name="action" value="send_code">
                    <input type="hidden" name="name" value="<?= htmlspecialchars($_SESSION['buyer_auth']['contact']) ?>">
                    <input type="hidden" name="contact" value="<?= htmlspecialchars($_SESSION['buyer_auth']['contact']) ?>">
                    <input type="hidden" name="method" value="<?= htmlspecialchars($_SESSION['buyer_auth']['method']) ?>">
                    <button type="submit" class="btn btn-secondary">
                        Resend Code
                    </button>
                </form>
            </form>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="<?= htmlspecialchars($returnUrl) ?>">← Return to browsing</a>
        </div>
    </div>
    
    <script>
        // Method selection
        document.querySelectorAll('.method-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.method-option').forEach(o => o.classList.remove('selected'));
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Check the radio button
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Update contact field
                updateContactField();
            });
        });
        
        function updateContactField() {
            const method = document.querySelector('input[name="method"]:checked').value;
            const contactField = document.getElementById('contact');
            const contactLabel = document.getElementById('contact-label');
            const contactHelp = document.getElementById('contact-help');
            
            if (method === 'sms') {
                contactField.type = 'tel';
                contactField.placeholder = '(555) 123-4567';
                contactLabel.textContent = 'Phone Number';
                contactHelp.textContent = "We'll send you a verification code via SMS";
            } else {
                contactField.type = 'email';
                contactField.placeholder = 'your@email.com';
                contactLabel.textContent = 'Email Address';
                contactHelp.textContent = "We'll send you a secure verification code";
            }
        }
        
        // Auto-submit code when 6 digits are entered
        const codeInput = document.getElementById('code');
        if (codeInput) {
            codeInput.addEventListener('input', function() {
                // Only allow numbers
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Auto-submit when 6 digits are entered
                if (this.value.length === 6) {
                    setTimeout(() => {
                        this.closest('form').submit();
                    }, 500);
                }
            });
        }
        
        // Set focus on page load
        document.addEventListener('DOMContentLoaded', function() {
            const focusElement = document.querySelector('<?= $step === "verify" ? "#code" : "#name" ?>');
            if (focusElement) {
                focusElement.focus();
            }
        });
    </script>
</body>
</html>