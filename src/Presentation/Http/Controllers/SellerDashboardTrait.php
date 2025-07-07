<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

/**
 * Trait for rendering seller dashboard using output buffering
 */
trait SellerDashboardTrait
{
    private function renderSellerDashboard(array $data): string
    {
        // Extract data with defaults
        $seller = $data['seller'] ?? [];
        $stats = $data['stats'] ?? [
            'total_sales' => 0,
            'active_sales' => 0,
            'total_items' => 0,
            'sold_items' => 0,
            'total_offers' => 0
        ];
        $activeSales = $data['activeSales'] ?? [];
        $upcomingSales = $data['upcomingSales'] ?? [];
        $recentSales = $data['recentSales'] ?? [];
        $recentInquiries = $data['recentInquiries'] ?? [];
        $unreadInquiryCount = $data['unreadInquiryCount'] ?? 0;
        
        // Start output buffering
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Dashboard - YFClaim</title>
    <style>
        /* CSS styles from enhanced dashboard */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .seller-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .nav-menu {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 1rem 0;
        }
        
        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            gap: 2rem;
        }
        
        .nav-item {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: all 0.2s;
            position: relative;
        }
        
        .nav-item:hover,
        .nav-item.active {
            background: #667eea;
            color: white;
        }
        
        .badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #dc3545;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-left: 4px solid #667eea;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }
        
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        .section {
            display: none;
        }
        
        .section:first-of-type {
            display: block;
        }
        
        .sales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .sale-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .sale-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .sale-header {
            background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
            color: white;
            padding: 1rem;
        }
        
        .sale-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .sale-content {
            padding: 1rem;
        }
        
        .sale-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .sale-stat {
            text-align: center;
        }
        
        .sale-stat-value {
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .sale-stat-label {
            font-size: 0.8rem;
            color: #666;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #666;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .nav-content {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .dashboard-grid,
            .sales-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                flex-direction: column;
            }
        }
        
        /* Inquiry specific styles */
        .inquiry-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .inquiry-item {
            background: white;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .inquiry-item:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);
        }
        
        .inquiry-item.unread {
            background: #f0f4ff;
            border-color: #667eea;
        }
        
        .inquiry-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }
        
        .inquiry-badge {
            background: #10b981;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .inquiry-badge.read {
            background: #6b7280;
        }
        
        .inquiry-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .inquiry-detail {
            font-size: 0.875rem;
        }
        
        .inquiry-detail-label {
            color: #666;
            font-weight: 500;
        }
        
        .inquiry-message {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 0.75rem;
            font-size: 0.875rem;
            line-height: 1.6;
        }
        
        .inquiry-notes {
            background: #fef3c7;
            padding: 1rem;
            border-radius: 6px;
            margin-top: 0.75rem;
            font-size: 0.875rem;
        }
        
        .inquiry-notes-label {
            font-weight: bold;
            color: #92400e;
            margin-bottom: 0.25rem;
        }
        
        .inquiry-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .filter-bar {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .filter-bar select,
        .filter-bar input {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="logo">
                🏷️ YFClaim Seller Portal
            </div>
            <div class="seller-info">
                <span>Welcome, <?= htmlspecialchars($seller['company_name'] ?? 'Seller') ?></span>
                <a href="/seller/logout" class="btn btn-sm">Logout</a>
            </div>
        </div>
    </header>

    <nav class="nav-menu">
        <div class="nav-content">
            <a href="#dashboard" class="nav-item active" onclick="showSection('dashboard', event)">📊 Dashboard</a>
            <a href="#sales" class="nav-item" onclick="showSection('sales', event)">🏪 My Sales</a>
            <a href="#inquiries" class="nav-item" onclick="showSection('inquiries', event)">
                📧 Inquiries
                <span id="inquiries-badge" class="badge" style="<?= $unreadInquiryCount > 0 ? '' : 'display: none;' ?>"><?= $unreadInquiryCount ?></span>
            </a>
            <a href="#analytics" class="nav-item" onclick="showSection('analytics', event)">📈 Analytics</a>
            <a href="#chat" class="nav-item" onclick="showSection('chat', event)">
                💬 Messages
                <span id="unread-badge" class="badge" style="display: none;">0</span>
            </a>
        </div>
    </nav>

    <div class="container">
        <!-- Dashboard Section -->
        <div id="dashboard-section" class="section">
            <div class="quick-actions">
                <a href="/seller/sale/new" class="btn">➕ Create New Sale</a>
                <a href="/seller/sales" class="btn">📋 Manage Sales</a>
            </div>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_sales'] ?></div>
                    <div class="stat-label">Total Sales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['active_sales'] ?></div>
                    <div class="stat-label">Active Sales</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_items'] ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="unread-inquiries"><?= $unreadInquiryCount ?></div>
                    <div class="stat-label">Unread Inquiries</div>
                </div>
            </div>

            <!-- Active Sales -->
            <?php if (!empty($activeSales)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🔥 Active Sales</h3>
                    </div>
                    <div class="sales-grid">
                        <?php foreach ($activeSales as $sale): ?>
                            <div class="sale-card">
                                <div class="sale-header">
                                    <div class="sale-title"><?= htmlspecialchars($sale['title']) ?></div>
                                    <div class="sale-dates">
                                        Claims: <?= date('M j, g:i A', strtotime($sale['claim_start_date'])) ?> - 
                                        <?= date('M j, g:i A', strtotime($sale['claim_end_date'])) ?>
                                    </div>
                                </div>
                                <div class="sale-content">
                                    <div class="sale-stats">
                                        <div class="sale-stat">
                                            <div class="sale-stat-value"><?= $sale['item_count'] ?? 0 ?></div>
                                            <div class="sale-stat-label">Items</div>
                                        </div>
                                        <div class="sale-stat">
                                            <div class="sale-stat-value"><?= $sale['inquiry_count'] ?? 0 ?></div>
                                            <div class="sale-stat-label">Inquiries</div>
                                        </div>
                                    </div>
                                    <div style="margin-top: 1rem;">
                                        <label style="font-size: 0.875rem; color: #666;">Status:</label>
                                        <select onchange="updateSaleStatus(<?= $sale['id'] ?>, this.value)" 
                                                class="status-select" 
                                                data-sale-id="<?= $sale['id'] ?>"
                                                style="padding: 0.25rem 0.5rem; border-radius: 4px; border: 1px solid #ddd;">
                                            <option value="draft" <?= $sale['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                            <option value="active" <?= $sale['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="closed" <?= $sale['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                            <option value="cancelled" <?= $sale['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem;">
                                        <a href="/seller/sale/<?= $sale['id'] ?>/edit" class="btn btn-sm">Manage</a>
                                        <a href="/claims/sale?id=<?= $sale['id'] ?>" class="btn btn-sm">View Public</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <div class="empty-state-icon">🏪</div>
                        <h3>No Active Sales</h3>
                        <p>Create your first estate sale to get started.</p>
                        <a href="/seller/sale/new" class="btn">Create New Sale</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sales Section -->
        <div id="sales-section" class="section" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">🏪 All My Sales</h3>
                    <a href="/seller/sale/new" class="btn">Create New Sale</a>
                </div>
                
                <?php if (!empty($recentSales)): ?>
                    <div class="sales-grid">
                        <?php foreach ($recentSales as $sale): ?>
                            <div class="sale-card">
                                <div class="sale-header">
                                    <div class="sale-title"><?= htmlspecialchars($sale['title']) ?></div>
                                </div>
                                <div class="sale-content">
                                    <p><?= htmlspecialchars($sale['city']) ?>, <?= htmlspecialchars($sale['state']) ?></p>
                                    <p>Created: <?= date('M j, Y', strtotime($sale['created_at'])) ?></p>
                                    <div style="margin-top: 1rem;">
                                        <label style="font-size: 0.875rem; color: #666;">Status:</label>
                                        <select onchange="updateSaleStatus(<?= $sale['id'] ?>, this.value)" 
                                                class="status-select" 
                                                data-sale-id="<?= $sale['id'] ?>"
                                                style="padding: 0.25rem 0.5rem; border-radius: 4px; border: 1px solid #ddd;">
                                            <option value="draft" <?= $sale['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                            <option value="active" <?= $sale['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                            <option value="closed" <?= $sale['status'] === 'closed' ? 'selected' : '' ?>>Closed</option>
                                            <option value="cancelled" <?= $sale['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                        </select>
                                    </div>
                                    <div style="margin-top: 1rem;">
                                        <a href="/seller/sale/<?= $sale['id'] ?>/edit" class="btn btn-sm">Manage</a>
                                        <a href="/claims/sale?id=<?= $sale['id'] ?>" class="btn btn-sm">View Public</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🏪</div>
                        <h3>No Sales Yet</h3>
                        <p>Create your first estate sale to get started.</p>
                        <a href="/seller/sale/new" class="btn">Create New Sale</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Analytics Section -->
        <div id="analytics-section" class="section" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📈 Analytics</h3>
                </div>
                <div class="empty-state">
                    <div class="empty-state-icon">📊</div>
                    <h3>Analytics Coming Soon</h3>
                    <p>We're working on detailed analytics for your sales performance.</p>
                </div>
            </div>
        </div>
        
        <!-- Inquiries Section -->
        <div id="inquiries-section" class="section" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">📧 Buyer Inquiries</h3>
                </div>
                
                <div class="filter-bar">
                    <select id="inquiry-status-filter" onchange="filterInquiries()">
                        <option value="">All Status</option>
                        <option value="new">New</option>
                        <option value="read">Read</option>
                        <option value="responded">Responded</option>
                        <option value="closed">Closed</option>
                    </select>
                    <input type="date" id="inquiry-date-from" onchange="filterInquiries()" placeholder="From Date">
                    <input type="date" id="inquiry-date-to" onchange="filterInquiries()" placeholder="To Date">
                </div>
                
                <div id="inquiry-list" class="inquiry-list">
                    <?php if (!empty($recentInquiries)): ?>
                        <?php foreach ($recentInquiries as $inquiry): ?>
                            <div class="inquiry-item <?= $inquiry['status'] === 'new' ? 'unread' : '' ?>" 
                                 onclick="viewInquiry(<?= $inquiry['id'] ?>)"
                                 data-inquiry-id="<?= $inquiry['id'] ?>"
                                 data-status="<?= htmlspecialchars($inquiry['status']) ?>">
                                <div class="inquiry-header">
                                    <div>
                                        <h4>Inquiry #<?= str_pad((string)$inquiry['id'], 6, '0', STR_PAD_LEFT) ?></h4>
                                        <div style="color: #666; font-size: 0.875rem;">
                                            <?= date('M j, Y g:i A', strtotime($inquiry['created_at'])) ?>
                                        </div>
                                    </div>
                                    <span class="inquiry-badge <?= $inquiry['status'] !== 'new' ? 'read' : '' ?>">
                                        <?= htmlspecialchars($inquiry['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="inquiry-details">
                                    <div class="inquiry-detail">
                                        <span class="inquiry-detail-label">From:</span>
                                        <?= htmlspecialchars($inquiry['buyer_name']) ?>
                                    </div>
                                    <div class="inquiry-detail">
                                        <span class="inquiry-detail-label">Email:</span>
                                        <?= htmlspecialchars($inquiry['buyer_email']) ?>
                                    </div>
                                    <div class="inquiry-detail">
                                        <span class="inquiry-detail-label">Item:</span>
                                        <?= htmlspecialchars($inquiry['item_title'] ?? 'General Inquiry') ?>
                                    </div>
                                    <?php if (!empty($inquiry['buyer_phone'])): ?>
                                    <div class="inquiry-detail">
                                        <span class="inquiry-detail-label">Phone:</span>
                                        <?= htmlspecialchars($inquiry['buyer_phone']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="inquiry-message">
                                    <?= nl2br(htmlspecialchars($inquiry['message'])) ?>
                                </div>
                                
                                <?php if (!empty($inquiry['admin_notes'])): ?>
                                <div class="inquiry-notes">
                                    <div class="inquiry-notes-label">Your Notes:</div>
                                    <?= nl2br(htmlspecialchars($inquiry['admin_notes'])) ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="inquiry-actions">
                                    <?php if ($inquiry['status'] === 'new'): ?>
                                        <button class="btn btn-sm" onclick="markInquiryRead(<?= $inquiry['id'] ?>); event.stopPropagation();">
                                            Mark as Read
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm" onclick="addNotesToInquiry(<?= $inquiry['id'] ?>); event.stopPropagation();">
                                        Add Notes
                                    </button>
                                    <a href="mailto:<?= htmlspecialchars($inquiry['buyer_email']) ?>?subject=Re: Inquiry about <?= htmlspecialchars($inquiry['item_title'] ?? 'your estate sale') ?>" 
                                       class="btn btn-sm" 
                                       onclick="event.stopPropagation();">
                                        Reply via Email
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">📧</div>
                            <h3>No Inquiries Yet</h3>
                            <p>Buyer inquiries will appear here when people contact you about your items.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Chat Section -->
        <div id="chat-section" class="section" style="display: none;">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">💬 Communication Hub</h3>
                        <p style="color: #666; font-size: 0.9rem; margin-top: 0.25rem;">Connect with admins and get support</p>
                    </div>
                    <button class="btn btn-sm" onclick="openChatInNewWindow()">
                        Open in New Window
                    </button>
                </div>
                <div style="background: #f8f9fa; border-radius: 8px; overflow: hidden;">
                    <iframe id="chat-iframe" 
                            src="/communication/embedded?seller_id=<?= htmlspecialchars((string)($seller['id'] ?? '')) ?>" 
                            style="width: 100%; height: calc(100vh - 300px); min-height: 500px; border: none;"
                            allow="camera; microphone">
                    </iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showSection(sectionName, event) {
            // Prevent default anchor behavior
            if (event) {
                event.preventDefault();
            }
            
            // Hide all sections
            document.querySelectorAll('.section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            const section = document.getElementById(sectionName + '-section');
            if (section) {
                section.style.display = 'block';
            } else {
                console.warn(`Section not found: ${sectionName}-section`);
            }
            
            // Update navigation
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Add active class to clicked item
            if (event && event.currentTarget) {
                event.currentTarget.classList.add('active');
            }
        }
        
        // Function to open chat in new window
        function openChatInNewWindow() {
            window.open('/communication/', 'YFEventsChat', 'width=800,height=600');
        }
        
        // Check for unread messages
        async function checkUnreadMessages() {
            try {
                const response = await fetch('/api/communication/unread-count', {
                    credentials: 'include'
                });
                const data = await response.json();
                
                const badge = document.getElementById('unread-badge');
                if (data.unread > 0) {
                    badge.textContent = data.unread;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            } catch (error) {
                console.error('Failed to fetch unread count:', error);
            }
        }
        
        // Check unread messages every 30 seconds
        setInterval(checkUnreadMessages, 30000);
        checkUnreadMessages(); // Initial check
        
        // Listen for messages from iframe
        window.addEventListener('message', function(event) {
            // Verify origin for security
            if (event.origin !== window.location.origin) return;
            
            if (event.data.type === 'unread-count') {
                const badge = document.getElementById('unread-badge');
                if (event.data.count > 0) {
                    badge.textContent = event.data.count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }
        });
        
        // Function to update sale status
        async function updateSaleStatus(saleId, newStatus) {
            try {
                const formData = new FormData();
                formData.append('sale_id', saleId);
                formData.append('status', newStatus);
                
                const response = await fetch('/seller/sale/update-status', {
                    method: 'POST',
                    body: formData,
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Show success message
                    alert('Sale status updated successfully');
                    
                    // Reload page to reflect changes in categorization
                    setTimeout(() => location.reload(), 500);
                } else {
                    alert('Failed to update status: ' + (result.error || 'Unknown error'));
                    // Reset the select to previous value
                    location.reload();
                }
            } catch (error) {
                console.error('Error updating sale status:', error);
                alert('Network error. Please try again.');
                location.reload();
            }
        }
        
        // Check for unread inquiries
        async function checkUnreadInquiries() {
            try {
                const response = await fetch('/api/yfclaim/seller/inquiries/unread-count', {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    const badge = document.getElementById('inquiries-badge');
                    const counter = document.getElementById('unread-inquiries');
                    const count = data.data.unread_count;
                    
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'inline-block';
                        counter.textContent = count;
                    } else {
                        badge.style.display = 'none';
                        counter.textContent = '0';
                    }
                }
            } catch (error) {
                console.error('Failed to fetch unread inquiry count:', error);
            }
        }
        
        // Mark inquiry as read
        async function markInquiryRead(inquiryId) {
            try {
                const response = await fetch(`/api/yfclaim/seller/inquiries/${inquiryId}/read`, {
                    method: 'PUT',
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Update UI
                    const inquiryItem = document.querySelector(`[data-inquiry-id="${inquiryId}"]`);
                    if (inquiryItem) {
                        inquiryItem.classList.remove('unread');
                        const badge = inquiryItem.querySelector('.inquiry-badge');
                        if (badge) {
                            badge.textContent = 'read';
                            badge.classList.add('read');
                        }
                        // Remove mark as read button
                        const button = inquiryItem.querySelector('button[onclick*="markInquiryRead"]');
                        if (button) {
                            button.remove();
                        }
                    }
                    // Update counts
                    checkUnreadInquiries();
                } else {
                    alert('Failed to mark inquiry as read');
                }
            } catch (error) {
                console.error('Error marking inquiry as read:', error);
                alert('Network error. Please try again.');
            }
        }
        
        // Add notes to inquiry
        async function addNotesToInquiry(inquiryId) {
            const notes = prompt('Add notes for this inquiry:');
            if (notes === null) return;
            
            try {
                const response = await fetch(`/api/yfclaim/seller/inquiries/${inquiryId}/notes`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ notes: notes }),
                    credentials: 'include'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Notes added successfully');
                    // Reload to show updated notes
                    location.reload();
                } else {
                    alert('Failed to add notes: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error adding notes:', error);
                alert('Network error. Please try again.');
            }
        }
        
        // Filter inquiries
        function filterInquiries() {
            const status = document.getElementById('inquiry-status-filter').value;
            const dateFrom = document.getElementById('inquiry-date-from').value;
            const dateTo = document.getElementById('inquiry-date-to').value;
            
            document.querySelectorAll('.inquiry-item').forEach(item => {
                let show = true;
                
                // Filter by status
                if (status && item.dataset.status !== status) {
                    show = false;
                }
                
                // Add date filtering logic here if needed
                
                item.style.display = show ? 'block' : 'none';
            });
        }
        
        // View inquiry details (for future modal implementation)
        function viewInquiry(inquiryId) {
            // For now, just mark as read
            const inquiryItem = document.querySelector(`[data-inquiry-id="${inquiryId}"]`);
            if (inquiryItem && inquiryItem.classList.contains('unread')) {
                markInquiryRead(inquiryId);
            }
        }
        
        // Check unread inquiries every 60 seconds
        setInterval(checkUnreadInquiries, 60000);
        checkUnreadInquiries(); // Initial check
    </script>
</body>
</html>
        <?php
        return ob_get_clean();
    }
}