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
            grid-template-columns: 65% 35%;
            gap: 30px;
            margin-top: 30px;
        }
        
        /* Lower Grid for Sales and Events */
        .lower-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
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
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.3);
        }
        
        .weekend-box h3 {
            margin-bottom: 10px;
            font-size: 1.5rem;
        }
        
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .seller-portal-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 15px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .seller-portal-btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-icon {
            font-size: 1.3rem;
        }
        
        .quick-links {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .quick-links a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.95rem;
            padding: 8px 0;
            transition: color 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .quick-links a:hover {
            color: #667eea;
        }
        
        /* Section Styling */
        .active-sales,
        .upcoming-events {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .active-sales h2,
        .upcoming-events h2 {
            margin-bottom: 20px;
            color: #333;
            font-size: 1.5rem;
        }
        
        /* Sales and Events Lists */
        .sales-list,
        .events-list {
            margin-bottom: 20px;
        }
        
        .sale-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .event-item {
            padding: 15px 0;
            border-bottom: 1px solid #e0e0e0;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .sale-item:hover {
            background-color: #f8f9fa;
            margin: 0 -30px;
            padding: 20px 30px;
        }
        
        .event-item:hover {
            background-color: #f8f9fa;
            margin: 0 -30px;
            padding: 15px 30px;
        }
        
        .sale-info {
            flex: 1;
        }
        
        /* Preview grid */
        .sale-previews {
            margin-left: 20px;
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(2, 50px);
            grid-template-rows: repeat(2, 50px);
            gap: 5px;
        }
        
        .preview-item {
            width: 50px;
            height: 50px;
            overflow: hidden;
            border-radius: 4px;
            background: #f5f5f5;
        }
        
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s ease;
        }
        
        .sale-item:hover .preview-item img {
            transform: scale(1.05);
        }
        
        .preview-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e9ecef;
            color: #6c757d;
            font-size: 1.5rem;
        }
        
        .sale-item:last-child,
        .event-item:last-child {
            border-bottom: none;
        }
        
        .sale-company,
        .event-title {
            font-weight: 600;
            color: #1B2951;
            margin-bottom: 5px;
        }
        
        .sale-details,
        .event-details {
            font-size: 0.9rem;
            color: #6c757d;
            line-height: 1.4;
        }
        
        .section-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        .view-all-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .view-all-link:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        /* Tools Bar */
        .tools-bar {
            background: #f8f9fa;
            padding: 30px 0;
            margin-top: 60px;
        }
        
        .tools-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .tools-content a {
            color: #6c757d;
            text-decoration: none;
            font-size: 0.95rem;
            transition: color 0.2s ease;
        }
        
        .tools-content a:hover {
            color: #667eea;
        }
        
        /* Footer */
        .footer {
            background: #1B2951;
            color: white;
            padding: 30px 0;
            margin-top: 0;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .footer-links {
            display: flex;
            gap: 20px;
        }
        
        .footer-links a {
            color: #a0aec0;
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }
        
        .footer-links a:hover {
            color: white;
        }
        
        /* Weekend Stats */
        .weekend-stats {
            margin-top: 10px;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-grid,
            .lower-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-bar {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .tools-content {
                gap: 15px;
            }
            
            .sale-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .sale-previews {
                margin-left: 0;
                margin-top: 15px;
            }
            
            .footer-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
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
                
                <!-- Quick Actions -->
                <div class="quick-actions">
                    <a href="/seller/dashboard" class="seller-portal-btn">
                        <span class="btn-icon">🏪</span>
                        Seller Portal
                    </a>
                    <div class="quick-links">
                        <a href="/alerts/signup">📧 Get Email Alerts</a>
                        <a href="/print/weekend">🖨️ Print Weekend List</a>
                    </div>
                </div>
            </aside>
        </div>
        
        <!-- Lower Grid for Sales and Events -->
        <div class="lower-grid">
            <!-- Active Sales Section -->
            <section class="active-sales">
                <h2>Active Estate Sales</h2>
                <?php if (!empty($currentSales)): ?>
                    <div class="sales-list">
                        <?php foreach (array_slice($currentSales, 0, 5) as $sale): ?>
                            <?php
                            $saleId = $sale['id'] ?? '';
                            $companyName = htmlspecialchars($sale['company_name'] ?? 'Estate Sale Company');
                            $startDate = !empty($sale['start_date']) ? date('M j', strtotime($sale['start_date'])) : '';
                            $endDate = !empty($sale['end_date']) ? date('M j', strtotime($sale['end_date'])) : '';
                            $dateRange = ($startDate && $endDate) ? "$startDate-$endDate" : 'Dates TBD';
                            $hours = htmlspecialchars($sale['hours'] ?? '9am-5pm');
                            $address = htmlspecialchars($sale['address'] ?? '');
                            $city = htmlspecialchars($sale['city'] ?? 'Yakima');
                            $itemCount = $sale['item_count'] ?? 0;
                            ?>
                            <div class="sale-item" onclick="window.location.href='/claims/sale/<?= $saleId ?>'">
                                <div class="sale-info">
                                    <div class="sale-company"><?= $companyName ?></div>
                                    <div class="sale-details">
                                        <?= $dateRange ?> • <?= $hours ?><br>
                                        <?= $address ?>, <?= $city ?> • <?= $itemCount ?> items
                                    </div>
                                </div>
                                <?php if (!empty($sale['item_previews'])): ?>
                                    <div class="sale-previews">
                                        <div class="preview-grid">
                                            <?php foreach (array_slice($sale['item_previews'], 0, 4) as $item): ?>
                                                <div class="preview-item">
                                                    <?php if (!empty($item['primary_image'])): ?>
                                                        <img src="/uploads/yfclaim/items/<?= htmlspecialchars($item['primary_image']) ?>" 
                                                             alt="<?= htmlspecialchars($item['title'] ?? '') ?>">
                                                    <?php else: ?>
                                                        <div class="preview-placeholder">
                                                            <span>📦</span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="section-footer">
                        <a href="/claims" class="view-all-link">View All Active Sales →</a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No active sales at this time.</p>
                        <p>Check back soon!</p>
                    </div>
                <?php endif; ?>
            </section>
            
            <!-- Upcoming Events Section -->
            <section class="upcoming-events">
                <h2>This Week's Events</h2>
                <?php if (!empty($upcomingEvents)): ?>
                    <div class="events-list">
                        <?php foreach (array_slice($upcomingEvents, 0, 6) as $event): ?>
                            <?php
                            $eventTitle = htmlspecialchars($event['title'] ?? 'Untitled Event');
                            $eventDate = !empty($event['event_date']) ? date('l, M j', strtotime($event['event_date'])) : 'Date TBD';
                            $eventTime = htmlspecialchars($event['start_time'] ?? '');
                            $endTime = htmlspecialchars($event['end_time'] ?? '');
                            $timeRange = $eventTime ? "$eventTime" . ($endTime ? "-$endTime" : '') : 'Time TBD';
                            ?>
                            <div class="event-item" onclick="window.location.href='/calendar#event-<?= $event['id'] ?? '' ?>'">
                                <div class="event-title"><?= $eventTitle ?></div>
                                <div class="event-details">
                                    <?= $eventDate ?> • <?= $timeRange ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="section-footer">
                        <a href="/calendar" class="view-all-link">View Full Calendar →</a>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <p>No upcoming events scheduled.</p>
                        <p>Check the full calendar for more!</p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
        
        <!-- Tools & Integration Bar -->
        <div class="tools-bar">
            <div class="tools-content">
                <a href="/api-docs">Integration & APIs</a>
                <a href="/calendar#shops">Business Directory & Map</a>
                <a href="/seller/resources">Seller Resources</a>
                <a href="/submit">Submit Event/Sale</a>
                <a href="/help">Help & Support</a>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?= date('Y') ?> YakimaFinds. All rights reserved.</p>
            <div class="footer-links">
                <a href="/admin/login">Admin Login</a>
                <a href="/health">System Status</a>
            </div>
        </div>
    </footer>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}