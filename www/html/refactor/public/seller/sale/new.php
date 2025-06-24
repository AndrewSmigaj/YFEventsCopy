<?php
// YFClaim Create New Sale Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if authenticated
if (!isset($_SESSION['yfclaim_seller_id'])) {
    header('Location: /refactor/seller/login');
    exit;
}

$basePath = '/refactor';
$sellerId = $_SESSION['yfclaim_seller_id'];
$sellerName = $_SESSION['yfclaim_seller_name'] ?? 'Seller';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Sale - YFClaim Estate Sales</title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 1.75rem;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .btn-back:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 2rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .form-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .section-title {
            font-size: 1.25rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-hint {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .date-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 1rem;
            }
            
            .form-card {
                padding: 1.5rem;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>📝 Create New Sale</h1>
            <a href="<?= $basePath ?>/seller/dashboard" class="btn-back">← Back to Dashboard</a>
        </div>
    </header>
    
    <div class="container">
        <form id="createSaleForm" class="form-card">
            <div id="alerts"></div>
            
            <!-- Basic Information -->
            <div class="form-section">
                <h2 class="section-title">📋 Basic Information</h2>
                <div class="form-group">
                    <label for="title" class="form-label">Sale Title <span class="required">*</span></label>
                    <input type="text" id="title" name="title" class="form-input" required
                           placeholder="e.g., Vintage Collectibles Estate Sale">
                    <p class="form-hint">Choose a descriptive title that will attract buyers</p>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description</label>
                    <textarea id="description" name="description" class="form-textarea"
                              placeholder="Describe what buyers can expect to find at your sale..."></textarea>
                </div>
            </div>
            
            <!-- Location Details -->
            <div class="form-section">
                <h2 class="section-title">📍 Location Details</h2>
                <div class="form-group">
                    <label for="address" class="form-label">Street Address <span class="required">*</span></label>
                    <input type="text" id="address" name="address" class="form-input" required
                           placeholder="123 Main Street">
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="city" class="form-label">City <span class="required">*</span></label>
                        <input type="text" id="city" name="city" class="form-input" required
                               placeholder="Yakima">
                    </div>
                    
                    <div class="form-group">
                        <label for="state" class="form-label">State</label>
                        <select id="state" name="state" class="form-select">
                            <option value="WA" selected>Washington</option>
                            <option value="OR">Oregon</option>
                            <option value="ID">Idaho</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="zip" class="form-label">ZIP Code <span class="required">*</span></label>
                        <input type="text" id="zip" name="zip" class="form-input" required
                               placeholder="98901" pattern="[0-9]{5}">
                    </div>
                </div>
            </div>
            
            <!-- Schedule -->
            <div class="form-section">
                <h2 class="section-title">📅 Schedule</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="start_date" class="form-label">Sale Start Date <span class="required">*</span></label>
                        <input type="date" id="start_date" name="start_date" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date" class="form-label">Sale End Date <span class="required">*</span></label>
                        <input type="date" id="end_date" name="end_date" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Claim Period</label>
                    <div class="date-inputs">
                        <div>
                            <input type="datetime-local" id="claim_start" name="claim_start" class="form-input">
                            <p class="form-hint">When buyers can start claiming</p>
                        </div>
                        <div>
                            <input type="datetime-local" id="claim_end" name="claim_end" class="form-input">
                            <p class="form-hint">Claiming deadline</p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Pickup Window</label>
                    <div class="date-inputs">
                        <div>
                            <input type="datetime-local" id="pickup_start" name="pickup_start" class="form-input">
                            <p class="form-hint">Pickup start time</p>
                        </div>
                        <div>
                            <input type="datetime-local" id="pickup_end" name="pickup_end" class="form-input">
                            <p class="form-hint">Pickup end time</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='<?= $basePath ?>/seller/dashboard'">
                    Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    Create Sale
                </button>
            </div>
        </form>
    </div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        
        // Set minimum dates
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').min = today;
        document.getElementById('end_date').min = today;
        
        // Handle form submission
        document.getElementById('createSaleForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            // Validate dates
            if (new Date(data.end_date) < new Date(data.start_date)) {
                showAlert('End date must be after start date', 'error');
                return;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Sale...';
            
            try {
                const response = await fetch(`${basePath}/seller/sale/create`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Sale created successfully! Redirecting...', 'success');
                    setTimeout(() => {
                        window.location.href = `${basePath}/seller/sale/${result.sale_id}/items`;
                    }, 1500);
                } else {
                    showAlert(result.message || 'Failed to create sale', 'error');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create Sale';
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Sale';
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
            
            if (type === 'success') {
                setTimeout(() => {
                    alertsContainer.innerHTML = '';
                }, 3000);
            }
        }
    </script>
</body>
</html>