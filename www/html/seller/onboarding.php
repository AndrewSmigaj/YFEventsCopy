<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /refactor/login.php');
    exit;
}

// Check if user is a vendor
if (!isset($_SESSION['is_vendor'])) {
    header('Location: /refactor/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to YFClaim - Seller Onboarding</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .onboarding-container {
            max-width: 800px;
            margin: 50px auto;
        }
        .onboarding-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .onboarding-header h1 {
            color: #667eea;
            margin-bottom: 1rem;
        }
        .step-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            margin-right: 1rem;
            font-weight: bold;
        }
        .cta-section {
            text-align: center;
            margin-top: 3rem;
        }
        .status-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #fff3cd;
            color: #856404;
            border-radius: 5px;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="onboarding-container">
        <div class="onboarding-header">
            <h1>🎉 Welcome to YFClaim, <?php echo htmlspecialchars($_SESSION['company_name'] ?? $_SESSION['user_name']); ?>!</h1>
            <p class="lead">Your estate sale company account has been created successfully.</p>
            <div class="status-badge">
                <i class="fas fa-clock"></i> Account Status: Pending Approval
            </div>
        </div>
        
        <div class="step-card">
            <h3><span class="step-number">1</span>Account Under Review</h3>
            <p>Our team is reviewing your seller account application. This typically takes 1-2 business days. You'll receive an email notification once your account is approved.</p>
        </div>
        
        <div class="step-card">
            <h3><span class="step-number">2</span>What Happens Next?</h3>
            <p>Once approved, you'll be able to:</p>
            <ul>
                <li>Create and manage estate sale listings</li>
                <li>Upload photos and descriptions of items</li>
                <li>Track buyer offers and manage sales</li>
                <li>Generate QR codes for in-person sales</li>
                <li>Access detailed analytics and reports</li>
            </ul>
        </div>
        
        <div class="step-card">
            <h3><span class="step-number">3</span>While You Wait</h3>
            <p>You can start exploring the platform:</p>
            <ul>
                <li>Browse existing estate sales to see how other sellers list their items</li>
                <li>Join the community discussion forums</li>
                <li>Read our seller guidelines and best practices</li>
            </ul>
        </div>
        
        <div class="cta-section">
            <a href="/refactor/claims" class="btn btn-primary btn-lg me-3">Browse Estate Sales</a>
            <a href="/refactor/communication/" class="btn btn-outline-primary btn-lg">Join Community</a>
            <div class="mt-3">
                <a href="/refactor/" class="btn btn-link">Return to Home</a>
            </div>
        </div>
    </div>
</body>
</html>