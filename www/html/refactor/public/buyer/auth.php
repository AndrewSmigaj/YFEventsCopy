<?php
// YFClaim Buyer Authentication Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if already authenticated
if (isset($_SESSION['buyer_id'])) {
    header('Location: /refactor/buyer/offers');
    exit;
}

$basePath = '/refactor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buyer Portal - YFClaim Estate Sales</title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem;
        }
        
        .auth-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 2rem;
            width: 100%;
            max-width: 450px;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-title {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .auth-subtitle {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            background: white;
            transition: border-color 0.3s;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-primary {
            width: 100%;
            background: #667eea;
            color: white;
            border: none;
            padding: 0.875rem;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .btn-primary:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .auth-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid #e9ecef;
        }
        
        .auth-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
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
        
        .alert-info {
            background: #e7f3ff;
            color: #0c5460;
            border: 1px solid #b8daff;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            display: flex;
            align-items: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .step.active {
            color: #667eea;
            font-weight: 600;
        }
        
        .step-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            font-size: 0.8rem;
            margin-right: 0.5rem;
        }
        
        .step.active .step-number {
            background: #667eea;
            color: white;
        }
        
        .step:not(:last-child)::after {
            content: '→';
            margin: 0 1rem;
            color: #e9ecef;
        }
        
        .verification-step {
            display: none;
        }
        
        .verification-step.active {
            display: block;
        }
        
        .verification-code {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin: 1rem 0;
        }
        
        .code-input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            border: 2px solid #e9ecef;
            border-radius: 6px;
        }
        
        .code-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .resend-link {
            color: #667eea;
            cursor: pointer;
            text-decoration: underline;
            font-size: 0.9rem;
        }
        
        .resend-link:hover {
            color: #5a6fd8;
        }
        
        .resend-link.disabled {
            color: #6c757d;
            cursor: not-allowed;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title">🛒 Buyer Portal</h1>
                <p class="auth-subtitle">Quick authentication to submit item claims</p>
            </div>
            
            <div class="step-indicator">
                <div class="step active" id="step1">
                    <span class="step-number">1</span>
                    Contact Info
                </div>
                <div class="step" id="step2">
                    <span class="step-number">2</span>
                    Verification
                </div>
            </div>
            
            <div id="alerts"></div>
            
            <!-- Step 1: Contact Information -->
            <div id="contactStep" class="verification-step active">
                <form id="contactForm">
                    <div class="form-group">
                        <label for="fullName" class="form-label">Full Name *</label>
                        <input type="text" id="fullName" name="full_name" class="form-input" required
                               placeholder="Enter your full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="preferredMethod" class="form-label">Verification Method *</label>
                        <select id="preferredMethod" name="preferred_method" class="form-select" required>
                            <option value="">Choose verification method</option>
                            <option value="email">Email</option>
                            <option value="sms">SMS (Text Message)</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="emailGroup">
                        <label for="email" class="form-label">Email Address *</label>
                        <input type="email" id="email" name="email" class="form-input"
                               placeholder="Enter your email address">
                    </div>
                    
                    <div class="form-group" id="phoneGroup" style="display: none;">
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" class="form-input"
                               placeholder="(555) 123-4567">
                    </div>
                    
                    <button type="submit" class="btn-primary" id="sendCodeBtn">
                        Send Verification Code
                    </button>
                </form>
            </div>
            
            <!-- Step 2: Verification Code -->
            <div id="verificationStep" class="verification-step">
                <div class="alert-info">
                    <p id="verificationMessage">We've sent a verification code to your contact method.</p>
                </div>
                
                <form id="verificationForm">
                    <div class="form-group">
                        <label class="form-label">Enter 6-digit verification code</label>
                        <div class="verification-code">
                            <input type="text" class="code-input" maxlength="1" data-index="0">
                            <input type="text" class="code-input" maxlength="1" data-index="1">
                            <input type="text" class="code-input" maxlength="1" data-index="2">
                            <input type="text" class="code-input" maxlength="1" data-index="3">
                            <input type="text" class="code-input" maxlength="1" data-index="4">
                            <input type="text" class="code-input" maxlength="1" data-index="5">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-primary" id="verifyBtn">
                        Verify & Continue
                    </button>
                    
                    <div style="text-align: center; margin-top: 1rem;">
                        <span class="resend-link" id="resendLink">Resend code</span>
                        <span id="resendTimer"></span>
                    </div>
                </form>
            </div>
            
            <div class="auth-links">
                <p><a href="<?= $basePath ?>/claims">← Back to Estate Sales</a></p>
            </div>
        </div>
    </div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        let resendTimeout = 0;
        let contactData = {};
        
        // Handle preferred method change
        document.getElementById('preferredMethod').addEventListener('change', (e) => {
            const method = e.target.value;
            const emailGroup = document.getElementById('emailGroup');
            const phoneGroup = document.getElementById('phoneGroup');
            
            if (method === 'email') {
                emailGroup.style.display = 'block';
                phoneGroup.style.display = 'none';
                document.getElementById('email').required = true;
                document.getElementById('phone').required = false;
            } else if (method === 'sms') {
                emailGroup.style.display = 'none';
                phoneGroup.style.display = 'block';
                document.getElementById('email').required = false;
                document.getElementById('phone').required = true;
            } else {
                emailGroup.style.display = 'block';
                phoneGroup.style.display = 'none';
                document.getElementById('email').required = false;
                document.getElementById('phone').required = false;
            }
        });
        
        // Handle contact form submission
        document.getElementById('contactForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            contactData = Object.fromEntries(formData);
            
            const sendCodeBtn = document.getElementById('sendCodeBtn');
            sendCodeBtn.disabled = true;
            sendCodeBtn.textContent = 'Sending Code...';
            
            try {
                const response = await fetch(`${basePath}/buyer/auth/send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(contactData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Verification code sent successfully!', 'success');
                    switchToVerificationStep();
                } else {
                    showAlert(result.message || 'Failed to send verification code.', 'error');
                }
            } catch (error) {
                console.error('Send code error:', error);
                showAlert('An error occurred. Please try again.', 'error');
            } finally {
                sendCodeBtn.disabled = false;
                sendCodeBtn.textContent = 'Send Verification Code';
            }
        });
        
        // Handle verification form submission
        document.getElementById('verificationForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const codeInputs = document.querySelectorAll('.code-input');
            const code = Array.from(codeInputs).map(input => input.value).join('');
            
            if (code.length !== 6) {
                showAlert('Please enter the complete 6-digit code.', 'error');
                return;
            }
            
            const verifyBtn = document.getElementById('verifyBtn');
            verifyBtn.disabled = true;
            verifyBtn.textContent = 'Verifying...';
            
            try {
                const response = await fetch(`${basePath}/buyer/auth/verify`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        ...contactData,
                        verification_code: code
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Verification successful! Redirecting...', 'success');
                    
                    // Redirect to buyer offers page
                    setTimeout(() => {
                        window.location.href = `${basePath}/buyer/offers`;
                    }, 1000);
                } else {
                    showAlert(result.message || 'Invalid verification code.', 'error');
                }
            } catch (error) {
                console.error('Verification error:', error);
                showAlert('An error occurred during verification.', 'error');
            } finally {
                verifyBtn.disabled = false;
                verifyBtn.textContent = 'Verify & Continue';
            }
        });
        
        // Switch to verification step
        function switchToVerificationStep() {
            document.getElementById('contactStep').classList.remove('active');
            document.getElementById('verificationStep').classList.add('active');
            document.getElementById('step1').classList.remove('active');
            document.getElementById('step2').classList.add('active');
            
            const method = contactData.preferred_method;
            const contact = method === 'email' ? contactData.email : contactData.phone;
            document.getElementById('verificationMessage').textContent = 
                `We've sent a 6-digit verification code to ${contact}. Enter it below to continue.`;
            
            // Start resend timer
            startResendTimer();
            
            // Focus first code input
            document.querySelector('.code-input').focus();
        }
        
        // Handle code input navigation
        document.querySelectorAll('.code-input').forEach((input, index) => {
            input.addEventListener('input', (e) => {
                if (e.target.value && index < 5) {
                    document.querySelector(`[data-index="${index + 1}"]`).focus();
                }
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    document.querySelector(`[data-index="${index - 1}"]`).focus();
                }
            });
        });
        
        // Resend functionality
        document.getElementById('resendLink').addEventListener('click', async () => {
            if (resendTimeout > 0) return;
            
            try {
                const response = await fetch(`${basePath}/buyer/auth/send`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(contactData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('New verification code sent!', 'success');
                    startResendTimer();
                } else {
                    showAlert('Failed to resend code.', 'error');
                }
            } catch (error) {
                showAlert('Error resending code.', 'error');
            }
        });
        
        function startResendTimer() {
            resendTimeout = 60;
            const resendLink = document.getElementById('resendLink');
            const resendTimer = document.getElementById('resendTimer');
            
            resendLink.classList.add('disabled');
            
            const timer = setInterval(() => {
                resendTimer.textContent = `(${resendTimeout}s)`;
                resendTimeout--;
                
                if (resendTimeout < 0) {
                    clearInterval(timer);
                    resendLink.classList.remove('disabled');
                    resendTimer.textContent = '';
                    resendTimeout = 0;
                }
            }, 1000);
        }
        
        function showAlert(message, type) {
            const alertsContainer = document.getElementById('alerts');
            const alertClass = type === 'success' ? 'alert-success' : 
                              type === 'info' ? 'alert-info' : 'alert-error';
            
            alertsContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    ${message}
                </div>
            `;
            
            // Auto-hide success messages after 3 seconds
            if (type === 'success') {
                setTimeout(() => {
                    alertsContainer.innerHTML = '';
                }, 3000);
            }
        }
    </script>
</body>
</html>