<?php
require_once __DIR__ . '/../../config/database.php';

$success = false;
$error = '';
$step = $_GET['step'] ?? '1';
$shop_id = $_GET['shop_id'] ?? null;
$claim_type = $_GET['type'] ?? 'existing_shop';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'submit_claim') {
            // Validate required fields
            $required_fields = ['requester_name', 'requester_email', 'business_name', 'relationship_to_business'];
            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    throw new Exception("Please fill in all required fields.");
                }
            }

            // Prepare data for insertion
            $data = [
                'shop_id' => $claim_type === 'existing_shop' ? $shop_id : null,
                'requester_name' => trim($_POST['requester_name']),
                'requester_email' => trim($_POST['requester_email']),
                'requester_phone' => trim($_POST['requester_phone'] ?? ''),
                'business_name' => trim($_POST['business_name']),
                'business_address' => trim($_POST['business_address'] ?? ''),
                'business_description' => trim($_POST['business_description'] ?? ''),
                'business_website' => trim($_POST['business_website'] ?? ''),
                'business_phone' => trim($_POST['business_phone'] ?? ''),
                'claim_type' => $claim_type,
                'ownership_proof' => trim($_POST['ownership_proof'] ?? ''),
                'relationship_to_business' => $_POST['relationship_to_business'],
                'applicant_notes' => trim($_POST['applicant_notes'] ?? ''),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'referrer_url' => $_SERVER['HTTP_REFERER'] ?? ''
            ];

            // Insert claim request
            $sql = "INSERT INTO shop_claim_requests (
                shop_id, requester_name, requester_email, requester_phone,
                business_name, business_address, business_description,
                business_website, business_phone, claim_type, ownership_proof,
                relationship_to_business, applicant_notes, ip_address,
                user_agent, referrer_url
            ) VALUES (
                :shop_id, :requester_name, :requester_email, :requester_phone,
                :business_name, :business_address, :business_description,
                :business_website, :business_phone, :claim_type, :ownership_proof,
                :relationship_to_business, :applicant_notes, :ip_address,
                :user_agent, :referrer_url
            )";

            $stmt = $db->prepare($sql);
            $stmt->execute($data);
            
            $claim_id = $db->lastInsertId();
            
            // Log the claim submission
            $log_sql = "INSERT INTO shop_claim_activity_log (claim_request_id, action, details) 
                       VALUES (?, 'submitted', 'Claim request submitted by applicant')";
            $log_stmt = $db->prepare($log_sql);
            $log_stmt->execute([$claim_id]);

            $success = true;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get existing shop info if claiming existing shop
$existing_shop = null;
if ($claim_type === 'existing_shop' && $shop_id) {
    $stmt = $db->prepare("SELECT * FROM local_shops WHERE id = ?");
    $stmt->execute([$shop_id]);
    $existing_shop = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get list of shops for selection
$shops = [];
if ($step === '1' && $claim_type === 'existing_shop') {
    $stmt = $db->query("SELECT id, name, address FROM local_shops WHERE status = 'active' ORDER BY name");
    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claim Your Shop - Yakima Finds</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; line-height: 1.6; }
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); padding: 40px; margin-bottom: 20px; }
        .header { text-align: center; margin-bottom: 40px; }
        .header h1 { color: #333; margin-bottom: 10px; }
        .header p { color: #666; font-size: 16px; }
        .steps { display: flex; justify-content: center; margin-bottom: 40px; }
        .step { display: flex; align-items: center; margin: 0 10px; }
        .step-number { width: 30px; height: 30px; border-radius: 50%; background: #ddd; color: white; display: flex; align-items: center; justify-content: center; margin-right: 10px; }
        .step.active .step-number { background: #007bff; }
        .step.completed .step-number { background: #28a745; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        .form-group textarea { height: 100px; resize: vertical; }
        .form-group.required label:after { content: " *"; color: red; }
        .radio-group { display: flex; gap: 20px; margin-top: 10px; }
        .radio-group label { font-weight: normal; display: flex; align-items: center; }
        .radio-group input[type="radio"] { width: auto; margin-right: 8px; }
        .btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #0056b3; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
        .btn-success { background: #28a745; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-danger { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .shop-list { max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; }
        .shop-item { padding: 15px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.3s; }
        .shop-item:hover { background: #f8f9fa; }
        .shop-item:last-child { border-bottom: none; }
        .shop-name { font-weight: bold; color: #333; margin-bottom: 5px; }
        .shop-address { color: #666; font-size: 14px; }
        .claim-type-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .claim-type-option { border: 2px solid #ddd; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.3s; }
        .claim-type-option:hover { border-color: #007bff; }
        .claim-type-option.selected { border-color: #007bff; background: #f8f9fa; }
        .claim-type-option i { font-size: 48px; color: #007bff; margin-bottom: 15px; }
        .back-link { color: #007bff; text-decoration: none; margin-bottom: 20px; display: inline-block; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <a href="/calendar.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Calendar
        </a>

        <div class="card">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <h3><i class="fas fa-check-circle"></i> Claim Request Submitted Successfully!</h3>
                    <p>Thank you for your claim request. We have received your submission and will review it within 2-3 business days.</p>
                    <p>You will receive an email confirmation at <strong><?= htmlspecialchars($_POST['requester_email'] ?? '') ?></strong> with further instructions.</p>
                    <br>
                    <a href="/calendar.php" class="btn btn-success">Return to Calendar</a>
                    <a href="/claim-shop.php" class="btn btn-secondary">Submit Another Claim</a>
                </div>
            <?php else: ?>
                <div class="header">
                    <h1><i class="fas fa-store"></i> Claim Your Shop</h1>
                    <p>Are you a business owner? Claim your shop listing to manage your information and connect with customers.</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($step === '1'): ?>
                    <!-- Step 1: Choose claim type -->
                    <div class="claim-type-selector">
                        <div class="claim-type-option <?= $claim_type === 'existing_shop' ? 'selected' : '' ?>"
                             onclick="window.location.href='?type=existing_shop&step=2'">
                            <i class="fas fa-search"></i>
                            <h3>Claim Existing Shop</h3>
                            <p>Your business is already listed on Yakima Finds and you want to claim ownership</p>
                        </div>
                        <div class="claim-type-option <?= $claim_type === 'new_shop' ? 'selected' : '' ?>"
                             onclick="window.location.href='?type=new_shop&step=3'">
                            <i class="fas fa-plus-circle"></i>
                            <h3>Add New Shop</h3>
                            <p>Your business is not listed yet and you want to add it to Yakima Finds</p>
                        </div>
                    </div>

                <?php elseif ($step === '2' && $claim_type === 'existing_shop'): ?>
                    <!-- Step 2: Select existing shop -->
                    <div class="header">
                        <h2>Select Your Shop</h2>
                        <p>Find and select your business from the list below:</p>
                    </div>

                    <div class="form-group">
                        <input type="text" id="shopSearch" placeholder="Search for your shop..." 
                               onkeyup="filterShops()" style="margin-bottom: 15px;">
                    </div>

                    <div class="shop-list" id="shopList">
                        <?php foreach ($shops as $shop): ?>
                            <div class="shop-item" onclick="selectShop(<?= $shop['id'] ?>)">
                                <div class="shop-name"><?= htmlspecialchars($shop['name']) ?></div>
                                <div class="shop-address"><?= htmlspecialchars($shop['address']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="margin-top: 20px; text-align: center;">
                        <p>Don't see your shop? <a href="?type=new_shop&step=3">Add it as a new listing</a></p>
                    </div>

                <?php else: ?>
                    <!-- Step 3: Claim form -->
                    <div class="steps">
                        <div class="step completed">
                            <div class="step-number">1</div>
                            <span>Choose Type</span>
                        </div>
                        <div class="step completed">
                            <div class="step-number">2</div>
                            <span>Select Shop</span>
                        </div>
                        <div class="step active">
                            <div class="step-number">3</div>
                            <span>Submit Claim</span>
                        </div>
                    </div>

                    <?php if ($existing_shop): ?>
                        <div class="alert alert-success">
                            <strong>Claiming:</strong> <?= htmlspecialchars($existing_shop['name']) ?><br>
                            <small><?= htmlspecialchars($existing_shop['address']) ?></small>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <input type="hidden" name="action" value="submit_claim">

                        <h3>Contact Information</h3>
                        <div class="form-group required">
                            <label>Your Full Name</label>
                            <input type="text" name="requester_name" required>
                        </div>

                        <div class="form-group required">
                            <label>Email Address</label>
                            <input type="email" name="requester_email" required>
                        </div>

                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="requester_phone">
                        </div>

                        <h3>Business Information</h3>
                        <div class="form-group required">
                            <label>Business Name</label>
                            <input type="text" name="business_name" 
                                   value="<?= $existing_shop ? htmlspecialchars($existing_shop['name']) : '' ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Business Address</label>
                            <textarea name="business_address"><?= $existing_shop ? htmlspecialchars($existing_shop['address']) : '' ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Business Description</label>
                            <textarea name="business_description"><?= $existing_shop ? htmlspecialchars($existing_shop['description']) : '' ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Business Website</label>
                            <input type="url" name="business_website" 
                                   value="<?= $existing_shop ? htmlspecialchars($existing_shop['website']) : '' ?>">
                        </div>

                        <div class="form-group">
                            <label>Business Phone</label>
                            <input type="tel" name="business_phone" 
                                   value="<?= $existing_shop ? htmlspecialchars($existing_shop['phone']) : '' ?>">
                        </div>

                        <h3>Ownership Verification</h3>
                        <div class="form-group required">
                            <label>Your Relationship to This Business</label>
                            <div class="radio-group">
                                <label><input type="radio" name="relationship_to_business" value="owner" required> Owner</label>
                                <label><input type="radio" name="relationship_to_business" value="manager" required> Manager</label>
                                <label><input type="radio" name="relationship_to_business" value="employee" required> Employee</label>
                                <label><input type="radio" name="relationship_to_business" value="authorized_rep" required> Authorized Representative</label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>How can you prove ownership/authorization?</label>
                            <textarea name="ownership_proof" 
                                      placeholder="e.g., I have business license, utility bills, lease agreement, etc."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Additional Information</label>
                            <textarea name="applicant_notes" 
                                      placeholder="Any additional information that might help us process your claim..."></textarea>
                        </div>

                        <div style="text-align: center; margin-top: 30px;">
                            <button type="submit" class="btn">
                                <i class="fas fa-paper-plane"></i> Submit Claim Request
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function filterShops() {
            const searchTerm = document.getElementById('shopSearch').value.toLowerCase();
            const shopItems = document.querySelectorAll('.shop-item');
            
            shopItems.forEach(item => {
                const shopName = item.querySelector('.shop-name').textContent.toLowerCase();
                const shopAddress = item.querySelector('.shop-address').textContent.toLowerCase();
                
                if (shopName.includes(searchTerm) || shopAddress.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function selectShop(shopId) {
            window.location.href = `?type=existing_shop&step=3&shop_id=${shopId}`;
        }
    </script>
</body>
</html>