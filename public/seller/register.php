<?php
// YFClaim Seller Registration Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$basePath = '/refactor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Registration - YFClaim Estate Sales</title>
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
            max-width: 500px;
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
        
        .form-textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
            min-height: 100px;
            resize: vertical;
        }
        
        .form-textarea:focus {
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
        
        .info-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .info-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .info-list {
            color: #6c757d;
            line-height: 1.6;
        }
        
        .info-list li {
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title">🏛️ Seller Registration</h1>
                <p class="auth-subtitle">Join YFClaim as an estate sale company</p>
            </div>
            
            <div class="info-section">
                <div class="info-title">Benefits of Joining YFClaim:</div>
                <ul class="info-list">
                    <li>Reach more buyers through our online platform</li>
                    <li>Manage estate sales and item claims digitally</li>
                    <li>Reduce on-site crowding with pre-sale claims</li>
                    <li>Generate QR codes for easy buyer verification</li>
                    <li>Track sales performance and buyer analytics</li>
                </ul>
            </div>
            
            <div id="alerts"></div>
            
            <form id="registrationForm">
                <div class="form-group">
                    <label for="companyName" class="form-label">Company Name *</label>
                    <input type="text" id="companyName" name="company_name" class="form-input" required
                           placeholder="Your Estate Sale Company Name">
                </div>
                
                <div class="form-group">
                    <label for="contactName" class="form-label">Contact Person *</label>
                    <input type="text" id="contactName" name="contact_name" class="form-input" required
                           placeholder="Primary contact person">
                </div>
                
                <div class="form-group">
                    <label for="contactEmail" class="form-label">Email Address *</label>
                    <input type="email" id="contactEmail" name="contact_email" class="form-input" required
                           placeholder="contact@yourcompany.com">
                </div>
                
                <div class="form-group">
                    <label for="contactPhone" class="form-label">Phone Number *</label>
                    <input type="tel" id="contactPhone" name="contact_phone" class="form-input" required
                           placeholder="(555) 123-4567">
                </div>
                
                <div class="form-group">
                    <label for="address" class="form-label">Business Address</label>
                    <input type="text" id="address" name="address" class="form-input"
                           placeholder="Street address, City, State, ZIP">
                </div>
                
                <div class="form-group">
                    <label for="website" class="form-label">Website</label>
                    <input type="url" id="website" name="website" class="form-input"
                           placeholder="https://yourcompany.com">
                </div>
                
                <div class="form-group">
                    <label for="businessDescription" class="form-label">Business Description</label>
                    <textarea id="businessDescription" name="business_description" class="form-textarea"
                              placeholder="Brief description of your estate sale business, experience, and services..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password *</label>
                    <input type="password" id="password" name="password" class="form-input" required
                           placeholder="Choose a secure password">
                </div>
                
                <div class="form-group">
                    <label for="confirmPassword" class="form-label">Confirm Password *</label>
                    <input type="password" id="confirmPassword" name="confirm_password" class="form-input" required
                           placeholder="Confirm your password">
                </div>
                
                <button type="submit" class="btn-primary" id="submitBtn">
                    Register Estate Sale Company
                </button>
            </form>
            
            <div class="auth-links">
                <p>Already have an account? <a href="<?= $basePath ?>/seller/login">Login here</a></p>
                <p><a href="<?= $basePath ?>/claims">← Back to Estate Sales</a></p>
            </div>
        </div>
    </div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        
        document.getElementById('registrationForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            // Validate passwords match
            if (data.password !== data.confirm_password) {
                showAlert('Passwords do not match', 'error');
                return;
            }
            
            // Validate required fields
            const required = ['company_name', 'contact_name', 'contact_email', 'contact_phone', 'password'];
            for (const field of required) {
                if (!data[field] || data[field].trim() === '') {
                    showAlert(`${field.replace('_', ' ')} is required`, 'error');
                    return;
                }
            }
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Registering...';
            
            try {
                const response = await fetch(`${basePath}/seller/register`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Registration successful! Please wait for admin approval before you can login.', 'success');
                    e.target.reset();
                    
                    // Redirect to login after 3 seconds
                    setTimeout(() => {
                        window.location.href = `${basePath}/seller/login`;
                    }, 3000);
                } else {
                    showAlert(result.message || 'Registration failed. Please try again.', 'error');
                }
            } catch (error) {
                console.error('Registration error:', error);
                showAlert('An error occurred during registration. Please try again.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Register Estate Sale Company';
            }
        });
        
        function showAlert(message, type) {
            const alertsContainer = document.getElementById('alerts');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            
            alertsContainer.innerHTML = `
                <div class="alert ${alertClass}">
                    ${message}
                </div>
            `;
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(() => {
                    alertsContainer.innerHTML = '';
                }, 5000);
            }
        }
        
        // Real-time password validation
        document.getElementById('confirmPassword').addEventListener('input', (e) => {
            const password = document.getElementById('password').value;
            const confirmPassword = e.target.value;
            
            if (confirmPassword && password !== confirmPassword) {
                e.target.style.borderColor = '#dc3545';
            } else {
                e.target.style.borderColor = '#e9ecef';
            }
        });
    </script>
</body>
</html>