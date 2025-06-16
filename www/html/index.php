<?php
/**
 * YFEvents - Simple Landing Page
 * Clean portal for YFEvents modules
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents - Yakima Valley Event Management</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 600px;
            padding: 40px;
            text-align: center;
        }
        
        h1 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .subtitle {
            font-size: 1.2rem;
            color: #7f8c8d;
            margin-bottom: 3rem;
        }
        
        .modules {
            display: grid;
            gap: 20px;
            margin-bottom: 3rem;
        }
        
        .module-link {
            display: block;
            background: white;
            color: #2c3e50;
            text-decoration: none;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 4px solid #3498db;
        }
        
        .module-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
            border-left-color: #e74c3c;
        }
        
        .module-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .module-desc {
            color: #7f8c8d;
            font-size: 0.95rem;
        }
        
        .admin-link {
            display: inline-block;
            background: #34495e;
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 0.9rem;
            transition: background 0.3s ease;
        }
        
        .admin-link:hover {
            background: #2c3e50;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .module-link {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>YFEvents</h1>
        <p class="subtitle">Yakima Valley Event Management System</p>
        
        <div class="modules">
            <a href="/calendar.php" class="module-link">
                <div class="module-title">Event Calendar</div>
                <div class="module-desc">Browse local events and business directory</div>
            </a>
            
            <a href="/modules/yfclaim/www/" class="module-link">
                <div class="module-title">YFClaim Estate Sales</div>
                <div class="module-desc">Browse and claim items from estate sales</div>
            </a>
            
            <a href="/modules/yfauth/www/" class="module-link">
                <div class="module-title">YFAuth User Portal</div>
                <div class="module-desc">User registration and account management</div>
            </a>
            
            <a href="/modules/yftheme/www/" class="module-link">
                <div class="module-title">YFTheme Browser</div>
                <div class="module-desc">Browse and preview available themes</div>
            </a>
        </div>
        
        <a href="/admin/" class="admin-link">Administration</a>
    </div>
</body>
</html>