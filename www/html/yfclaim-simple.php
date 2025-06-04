<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFClaim - Estate Sales</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background: #f5f5f5;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 2rem;
            text-align: center;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        .card {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 5px;
            margin: 0.5rem;
        }
        .btn:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏠 YFClaim Estate Sales</h1>
        <p>Browse items, make offers, and claim your treasures from local estate sales</p>
    </div>
    
    <div class="card">
        <h2>Welcome to YFClaim!</h2>
        <p>This is the estate sale claiming platform integrated with YFEvents.</p>
        
        <h3>Available Actions:</h3>
        <a href="/yfclaim-demo.php" class="btn">🎯 Live Demo</a>
        <a href="/admin/" class="btn">🔧 Admin Dashboard</a>
        <a href="/" class="btn">📅 Back to Events Calendar</a>
        
        <h3>Sample Data Created:</h3>
        <ul>
            <li>✅ 1 Estate Sale Company (Smith Family Estate Sales)</li>
            <li>✅ 1 Active Sale (Vintage Furniture & Collectibles)</li>
            <li>✅ 8 Sample Items with QR codes</li>
            <li>✅ Admin interfaces for managing everything</li>
        </ul>
        
        <h3>How to Access YFClaim:</h3>
        <ol>
            <li><strong>Step 1:</strong> <a href="/admin/">Login to Admin Dashboard</a> first</li>
            <li><strong>Step 2:</strong> Look for "YFClaim Estate Sales" section with statistics</li>
            <li><strong>Step 3:</strong> Click "YFClaim Sales" link in the navigation</li>
            <li><strong>Alternative:</strong> Direct links (requires admin login):
                <ul style="margin-top: 0.5rem;">
                    <li><a href="/modules/yfclaim/www/admin/">YFClaim Dashboard</a></li>
                    <li><a href="/modules/yfclaim/www/admin/sellers.php">Manage Sellers</a></li>
                    <li><a href="/modules/yfclaim/www/admin/sales.php">Manage Sales</a></li>
                    <li><a href="/modules/yfclaim/www/admin/offers.php">View Offers</a></li>
                    <li><a href="/modules/yfclaim/www/admin/buyers.php">Manage Buyers</a></li>
                </ul>
            </li>
        </ol>
        
        <h3>Database Status:</h3>
        <?php
        try {
            require_once dirname(__DIR__, 2) . '/config/database.php';
            
            $saleCount = $pdo->query("SELECT COUNT(*) FROM yfc_sales")->fetchColumn();
            $itemCount = $pdo->query("SELECT COUNT(*) FROM yfc_items")->fetchColumn();
            $sellerCount = $pdo->query("SELECT COUNT(*) FROM yfc_sellers")->fetchColumn();
            
            echo "<ul>";
            echo "<li>✅ Database Connected</li>";
            echo "<li>📊 {$sellerCount} Sellers</li>";
            echo "<li>🏠 {$saleCount} Sales</li>";
            echo "<li>📦 {$itemCount} Items</li>";
            echo "</ul>";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Database Error: " . $e->getMessage() . "</p>";
        }
        ?>
    </div>
    
    <div class="card">
        <h2>🎯 YFClaim Features Completed:</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            <div>
                <h3>✅ Admin Management</h3>
                <ul>
                    <li>Seller Management</li>
                    <li>Sale Management</li>
                    <li>Item Management</li>
                    <li>Offer Management</li>
                    <li>Buyer Management</li>
                </ul>
            </div>
            <div>
                <h3>✅ Public Interface</h3>
                <ul>
                    <li>Sale Browsing</li>
                    <li>Item Viewing</li>
                    <li>Buyer Authentication</li>
                    <li>Offer Submission</li>
                    <li>Offer Tracking</li>
                </ul>
            </div>
            <div>
                <h3>✅ Advanced Features</h3>
                <ul>
                    <li>QR Code Generation</li>
                    <li>Real-time Statistics</li>
                    <li>Email/SMS Authentication</li>
                    <li>Responsive Design</li>
                    <li>Complete CRUD Operations</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>