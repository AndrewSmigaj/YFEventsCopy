<?php
// YFAuth - Module Home Page
session_start();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['yfa_session_id']);
$user = $_SESSION['yfa_user'] ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFAuth - Authentication System</title>
    
    <meta name="description" content="YFAuth - Centralized authentication and authorization system for YFEvents">
    
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
            color: #333;
        }
        
        .header {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .nav-links {
            display: flex;
            gap: 1.5rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .btn-primary {
            background: white;
            color: #667eea;
        }
        
        .btn-primary:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .btn-secondary:hover {
            background: white;
            color: #667eea;
        }
        
        .hero {
            text-align: center;
            padding: 6rem 2rem;
            color: white;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 3rem;
            opacity: 0.9;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .hero-actions {
            display: flex;
            gap: 1.5rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .features {
            background: white;
            padding: 6rem 2rem;
        }
        
        .features-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .features-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .features-title {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .features-subtitle {
            font-size: 1.1rem;
            color: #7f8c8d;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 3rem;
        }
        
        .feature-card {
            text-align: center;
            padding: 2rem;
            border-radius: 12px;
            transition: transform 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
        }
        
        .feature-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
        }
        
        .feature-title {
            font-size: 1.3rem;
            color: #2c3e50;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .feature-description {
            color: #7f8c8d;
            line-height: 1.6;
        }
        
        .modules {
            background: #f8f9fa;
            padding: 6rem 2rem;
        }
        
        .modules-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .modules-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .modules-title {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 1rem;
        }
        
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }
        
        .module-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .module-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        
        .module-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .module-icon {
            font-size: 2.5rem;
        }
        
        .module-title {
            font-size: 1.4rem;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .module-description {
            color: #7f8c8d;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .module-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 3rem 2rem;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .footer-links a {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        
        .footer-links a:hover {
            opacity: 1;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .modules-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-links {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <a href="/modules/yfauth/www/" class="logo">
                🔐 YFAuth
            </a>
            <nav class="nav-links">
                <a href="/">Home</a>
                <a href="/refactor/">Events</a>
                <a href="/modules/yfclaim/www/">Estate Sales</a>
                <?php if ($isLoggedIn): ?>
                    <a href="/modules/yfauth/www/dashboard.php" class="btn btn-primary">Dashboard</a>
                <?php else: ?>
                    <a href="/modules/yfauth/www/login.php" class="btn btn-secondary">Login</a>
                    <a href="/modules/yfauth/www/register.php" class="btn btn-primary">Sign Up</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    
    <section class="hero">
        <h1 class="hero-title">YFAuth</h1>
        <p class="hero-subtitle">
            Centralized authentication and authorization system for YFEvents. 
            Secure, scalable, and seamlessly integrated across all modules.
        </p>
        <div class="hero-actions">
            <?php if ($isLoggedIn): ?>
                <a href="/modules/yfauth/www/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                <a href="/modules/yfauth/www/admin/" class="btn btn-secondary">Admin Panel</a>
            <?php else: ?>
                <a href="/modules/yfauth/www/register.php" class="btn btn-primary">Get Started</a>
                <a href="/modules/yfauth/www/login.php" class="btn btn-secondary">Sign In</a>
            <?php endif; ?>
        </div>
    </section>
    
    <section class="features">
        <div class="features-container">
            <div class="features-header">
                <h2 class="features-title">Security Features</h2>
                <p class="features-subtitle">
                    Built with modern security practices and enterprise-grade features
                </p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🛡️</div>
                    <h3 class="feature-title">Secure Authentication</h3>
                    <p class="feature-description">
                        Password hashing, rate limiting, and protection against common attacks
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3 class="feature-title">Role-Based Access</h3>
                    <p class="feature-description">
                        Granular permissions system with flexible role assignments
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔒</div>
                    <h3 class="feature-title">Session Management</h3>
                    <p class="feature-description">
                        Secure session handling with configurable timeouts and multi-device support
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📧</div>
                    <h3 class="feature-title">Email Verification</h3>
                    <p class="feature-description">
                        Account verification and password reset via secure email tokens
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🔐</div>
                    <h3 class="feature-title">Two-Factor Auth</h3>
                    <p class="feature-description">
                        Optional 2FA support for enhanced account security
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3 class="feature-title">Activity Logging</h3>
                    <p class="feature-description">
                        Comprehensive audit trails and security monitoring
                    </p>
                </div>
            </div>
        </div>
    </section>
    
    <section class="modules">
        <div class="modules-container">
            <div class="modules-header">
                <h2 class="modules-title">Integrated Modules</h2>
            </div>
            
            <div class="modules-grid">
                <div class="module-card">
                    <div class="module-header">
                        <div class="module-icon">📅</div>
                        <h3 class="module-title">YFEvents</h3>
                    </div>
                    <p class="module-description">
                        Main event calendar system with scraping, categorization, and map integration.
                        Discover local events and activities in the Yakima Valley.
                    </p>
                    <div class="module-actions">
                        <a href="/refactor/" class="btn btn-primary">Browse Events</a>
                        <a href="/refactor/admin/" class="btn btn-secondary">Admin</a>
                    </div>
                </div>
                
                <div class="module-card">
                    <div class="module-header">
                        <div class="module-icon">🏪</div>
                        <h3 class="module-title">Local Shops</h3>
                    </div>
                    <p class="module-description">
                        Business directory with profiles, locations, and contact information.
                        Support local businesses in the Yakima Valley community.
                    </p>
                    <div class="module-actions">
                        <a href="/refactor/shops/" class="btn btn-primary">Browse Shops</a>
                        <a href="/refactor/shops/submit/" class="btn btn-secondary">Add Shop</a>
                    </div>
                </div>
                
                <div class="module-card">
                    <div class="module-header">
                        <div class="module-icon">🏠</div>
                        <h3 class="module-title">YFClaim</h3>
                    </div>
                    <p class="module-description">
                        Estate sale platform with item claiming, offer management, and QR code integration.
                        Find unique items and make offers on estate sale goods.
                    </p>
                    <div class="module-actions">
                        <a href="/modules/yfclaim/www/" class="btn btn-primary">Browse Sales</a>
                        <a href="/modules/yfclaim/www/admin/" class="btn btn-secondary">Seller Portal</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="/">Home</a>
                <a href="/refactor/">Events</a>
                <a href="/refactor/shops/">Shops</a>
                <a href="/modules/yfclaim/www/">Estate Sales</a>
                <a href="/modules/yfauth/www/admin/">Admin</a>
            </div>
            <p>&copy; 2025 YFEvents. Yakima Valley's premier event and business discovery platform.</p>
        </div>
    </footer>
</body>
</html>