<?php

declare(strict_types=1);

namespace YakimaFinds\Presentation\Http\Controllers;

use YakimaFinds\Infrastructure\Container\ContainerInterface;
use YakimaFinds\Infrastructure\Config\ConfigInterface;

/**
 * Claims controller for YFClaim estate sales
 */
class ClaimsController extends BaseController
{
    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
    }

    /**
     * Show estate sales browse page
     */
    public function showClaimsPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderClaimsPage($basePath);
    }

    /**
     * Show upcoming estate sales page
     */
    public function showUpcomingClaimsPage(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderClaimsPage($basePath, 'Upcoming Estate Sales', 'upcoming');
    }

    /**
     * Show seller registration page
     */
    public function showSellerRegistration(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderSellerRegistrationPage($basePath);
    }

    /**
     * Show buyer offers page
     */
    public function showBuyerOffers(): void
    {
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderBuyerOffersPage($basePath);
    }

    private function renderClaimsPage(string $basePath, string $pageTitle = 'Estate Sales', string $filter = 'all'): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - {$pageTitle}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .coming-soon {
            background: white;
            border-radius: 15px;
            padding: 60px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .coming-soon h2 {
            font-size: 2.5rem;
            color: #fd7e14;
            margin-bottom: 20px;
        }
        
        .coming-soon p {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .features-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .feature-preview {
            background: white;
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        
        .feature-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #343a40;
            margin-bottom: 10px;
        }
        
        .feature-desc {
            color: #6c757d;
            line-height: 1.5;
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #fd7e14;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .notification {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏛️ {$pageTitle}</h1>
        <p>Estate Sale Claiming Platform - YFClaim Module</p>
    </div>
    
    <div class="container">
        <a href="{$basePath}/" class="back-link">← Back to Home</a>
        
        <div class="notification">
            <strong>🚧 Module in Development:</strong> The YFClaim estate sales module is currently under development. The backend domain logic is complete, but the public interface is still being built.
        </div>
        
        <div class="coming-soon">
            <h2>Coming Soon!</h2>
            <p>The YFClaim module will revolutionize how estate sales are managed and accessed in the Yakima Valley. This comprehensive platform will connect estate sale companies with eager buyers through an innovative claiming system.</p>
        </div>
        
        <div class="features-preview">
            <div class="feature-preview">
                <div class="feature-icon">🏡</div>
                <div class="feature-title">Estate Sale Listings</div>
                <div class="feature-desc">Browse upcoming and current estate sales with detailed item catalogs, photos, and sale information.</div>
            </div>
            
            <div class="feature-preview">
                <div class="feature-icon">📋</div>
                <div class="feature-title">Item Claiming System</div>
                <div class="feature-desc">Claim items before the sale with our innovative digital claiming system and QR code verification.</div>
            </div>
            
            <div class="feature-preview">
                <div class="feature-icon">🏢</div>
                <div class="feature-title">Seller Dashboard</div>
                <div class="feature-desc">Estate sale companies can manage their sales, upload item catalogs, and track buyer offers.</div>
            </div>
            
            <div class="feature-preview">
                <div class="feature-icon">💰</div>
                <div class="feature-title">Offer Management</div>
                <div class="feature-desc">Submit and track offers on items, with real-time notifications and automated acceptance workflows.</div>
            </div>
            
            <div class="feature-preview">
                <div class="feature-icon">📱</div>
                <div class="feature-title">QR Code Integration</div>
                <div class="feature-desc">Seamless on-site verification using QR codes for claimed items and buyer identification.</div>
            </div>
            
            <div class="feature-preview">
                <div class="feature-icon">🔔</div>
                <div class="feature-title">Smart Notifications</div>
                <div class="feature-desc">Get notified about new sales, offer updates, and pickup reminders via email and SMS.</div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderSellerRegistrationPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - Seller Registration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .registration-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 500px;
            text-align: center;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #fd7e14, #e83e8c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .coming-soon {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #fd7e14;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="registration-container">
        <div class="logo">YFClaim</div>
        <div class="subtitle">Estate Sale Company Registration</div>
        
        <div class="coming-soon">
            <h3>🚧 Registration Coming Soon</h3>
            <p>Estate sale company registration is currently under development. This will allow estate sale companies to create accounts, manage their sales, and access our claiming platform.</p>
        </div>
        
        <a href="{$basePath}/" class="back-link">← Back to Home</a>
    </div>
</body>
</html>
HTML;
    }

    private function renderBuyerOffersPage(string $basePath): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YFEvents V2 - My Offers</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #fd7e14 0%, #e83e8c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .offers-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 500px;
            text-align: center;
        }
        
        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #fd7e14, #e83e8c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #6c757d;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .coming-soon {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .back-link {
            display: inline-block;
            margin-top: 20px;
            color: #fd7e14;
            text-decoration: none;
            font-weight: 500;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="offers-container">
        <div class="logo">YFClaim</div>
        <div class="subtitle">My Offers Dashboard</div>
        
        <div class="coming-soon">
            <h3>🚧 Buyer Dashboard Coming Soon</h3>
            <p>The buyer offers dashboard is currently under development. This will allow buyers to view their submitted offers, track offer status, and manage their estate sale interactions.</p>
        </div>
        
        <a href="{$basePath}/" class="back-link">← Back to Home</a>
    </div>
</body>
</html>
HTML;
    }
}