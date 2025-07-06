<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

/**
 * Fixed version of renderHomePage using output buffering instead of heredoc
 */
trait HomeControllerFixed
{
    private function renderHomePageFixed(array $data = []): string
    {
        // Extract data with defaults
        $stats = $data['stats'] ?? [
            'active_sales' => 47,
            'upcoming_events' => 156,
            'total_items' => 2341,
            'local_shops' => 89
        ];
        
        $featuredItems = $data['featuredItems'] ?? [];
        $currentSales = $data['currentSales'] ?? [];
        $upcomingEvents = $data['upcomingEvents'] ?? [];
        $hotItem = $data['hotItem'] ?? null;
        
        // Format numbers
        $formattedItems = number_format($stats['total_items']);
        
        // Start output buffering
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yakima Valley Estate Sales & Events | YFEvents</title>
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
        }
        
        /* Header Styles */
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }
        
        /* Container */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Stats Bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat {
            background: white;
            padding: 25px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 1rem;
        }
        
        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
            margin-top: 30px;
        }
        
        /* Featured Items */
        .featured-items {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .featured-items h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .item-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .item-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .item-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .no-image-placeholder {
            width: 100%;
            height: 200px;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        
        .item-details {
            padding: 15px;
        }
        
        .item-title {
            font-size: 1rem;
            margin-bottom: 5px;
            color: #1B2951;
        }
        
        .item-price {
            font-size: 1.2rem;
            color: #B87333;
            font-weight: bold;
        }
        
        /* Spotlight Sidebar */
        .spotlight-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .weekend-box {
            background: #764ba2;
            color: white;
            padding: 25px;
            border-radius: 10px;
        }
        
        .map-preview {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-bar {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-content">
            <h1>YakimaFinds</h1>
            <p>Your Hub for Estate Sales, Local Events & Hidden Treasures</p>
        </div>
    </header>
    
    <!-- Container -->
    <div class="container">
        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat">
                <div class="stat-number"><?= $stats['active_sales'] ?></div>
                <div class="stat-label">Active Sales</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?= $stats['upcoming_events'] ?></div>
                <div class="stat-label">Upcoming Events</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?= $formattedItems ?></div>
                <div class="stat-label">Items Listed</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?= $stats['local_shops'] ?></div>
                <div class="stat-label">Local Shops</div>
            </div>
        </div>
        
        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Featured Items Section -->
            <section class="featured-items">
                <h2>Featured Estate Sale Finds</h2>
                <div class="items-grid">
                    <?php if (!empty($featuredItems)): ?>
                        <?php foreach (array_slice($featuredItems, 0, 12) as $item): ?>
                            <?php
                            $itemId = $item['id'] ?? '';
                            $title = htmlspecialchars($item['title'] ?? 'Untitled Item');
                            $price = isset($item['price']) ? '$' . number_format($item['price'], 2) : 'Price TBD';
                            ?>
                            <div class="item-card" onclick="window.location.href='/claims/item/<?= $itemId ?>'">
                                <?php if (!empty($item['primary_image'])): ?>
                                    <img src="/uploads/yfclaim/items/<?= htmlspecialchars($item['primary_image']) ?>" 
                                         alt="<?= $title ?>" 
                                         class="item-image">
                                <?php else: ?>
                                    <div class="no-image-placeholder">No Image Available</div>
                                <?php endif; ?>
                                <div class="item-details">
                                    <div class="item-title"><?= $title ?></div>
                                    <div class="item-price"><?= $price ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #6c757d;">
                            <p>No featured items available at this time.</p>
                            <p>Check back soon for new estate sale finds!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- Spotlight Sidebar -->
            <aside class="spotlight-sidebar">
                <!-- Weekend Box -->
                <div class="weekend-box">
                    <h3>This Weekend</h3>
                    <div class="weekend-stats">
                        <?= $stats['active_sales'] ?> Active Sales • <?= $stats['upcoming_events'] ?> Upcoming Events
                    </div>
                </div>
                
                <!-- Map Preview -->
                <div class="map-preview">
                    <h3>Events Near You</h3>
                    <p>Interactive map coming soon!</p>
                </div>
            </aside>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}