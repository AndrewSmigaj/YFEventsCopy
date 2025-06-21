<?php
// Admin Claims Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /refactor/admin/login');
    exit;
}

// Set correct base path for refactor admin
$basePath = '/refactor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Claims Management - YFEvents Admin</title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        /* Page-specific styles for claims page */
        .claims-section {
            margin-bottom: 3rem;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .sales-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .sale-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sale-header {
            background: #667eea;
            color: white;
            padding: 1rem;
        }
        
        .sale-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .sale-company {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .sale-body {
            padding: 1rem;
        }
        
        .sale-info {
            margin-bottom: 1rem;
        }
        
        .sale-info-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .sale-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin: 1rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 1.2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            font-size: 0.75rem;
            color: #6c757d;
            text-transform: uppercase;
        }
        
        .sale-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .btn-manage {
            flex: 1;
            background: #28a745;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
            text-align: center;
        }
        
        .btn-manage:hover {
            background: #218838;
            color: white;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }
        
        .empty-state h3 {
            margin-bottom: 1rem;
            color: #2c3e50;
        }
        
        @media (max-width: 768px) {
            .sales-grid {
                grid-template-columns: 1fr;
            }
            
            .sale-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>🛠️ YFEvents Admin</h1>
            <nav class="nav-links">
                <a href="<?= $basePath ?>/admin/index.php">Dashboard</a>
                <a href="<?= $basePath ?>/admin/events.php">Events</a>
                <a href="<?= $basePath ?>/admin/shops.php">Shops</a>
                <a href="<?= $basePath ?>/admin/claims.php" class="active">Claims</a>
                <a href="<?= $basePath ?>/admin/scrapers.php">Scrapers</a>
                <a href="<?= $basePath ?>/admin/email-events.php">Email Events</a>
                <a href="<?= $basePath ?>/admin/email-config.php">Email Config</a>
                <a href="<?= $basePath ?>/admin/users.php">Users</a>
                <a href="<?= $basePath ?>/admin/settings.php">Settings</a>
                <a href="<?= $basePath ?>/admin/theme.php">Theme</a>
                <a href="#" onclick="logout()">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <h2 class="page-title">YFClaim Estate Sales Management</h2>
            <div style="display: flex; gap: 1rem;">
                <button class="btn btn-success" onclick="showCreateSaleModal()">
                    <span>+</span> Create Estate Sale
                </button>
                <a href="<?= $basePath ?>/claims" class="btn btn-secondary" target="_blank">
                    <span>👁️</span> View Public Page
                </a>
            </div>
        </div>
        
        <!-- Statistics Overview -->
        <div class="stats-grid" id="claimsStatsRow">
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Total Sales</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Active Sales</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Total Items</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Pending Claims</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Registered Sellers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Active Buyers</div>
            </div>
        </div>
        
        <!-- Active Estate Sales -->
        <div class="claims-section">
            <div class="section-header">
                <h3 class="section-title">Active Estate Sales</h3>
                <button class="btn btn-primary" onclick="refreshData()">
                    <span>🔄</span> Refresh
                </button>
            </div>
            <div id="activeSalesContainer">
                <div class="loading">Loading active sales...</div>
            </div>
        </div>
        
        <!-- Upcoming Estate Sales -->
        <div class="claims-section">
            <div class="section-header">
                <h3 class="section-title">Upcoming Estate Sales</h3>
            </div>
            <div id="upcomingSalesContainer">
                <div class="loading">Loading upcoming sales...</div>
            </div>
        </div>
        
        <!-- Completed Estate Sales -->
        <div class="claims-section">
            <div class="section-header">
                <h3 class="section-title">Recently Completed Sales</h3>
            </div>
            <div id="completedSalesContainer">
                <div class="loading">Loading completed sales...</div>
            </div>
        </div>
        
        <!-- Sellers Management -->
        <div class="claims-section">
            <div class="section-header">
                <h3 class="section-title">Estate Sale Companies</h3>
                <button class="btn btn-warning" onclick="showPendingSellers()">
                    <span>⏳</span> Review Pending
                </button>
            </div>
            <div id="sellersContainer">
                <div class="loading">Loading sellers...</div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>
    
    <script>
        const basePath = '<?php echo $basePath; ?>' || '/refactor';
        
        // Load initial data
        document.addEventListener('DOMContentLoaded', () => {
            loadStatistics();
            loadActiveSales();
            loadUpcomingSales();
            loadCompletedSales();
            loadSellers();
        });
        
        async function loadStatistics() {
            try {
                // Simulate loading claims statistics
                // In a real implementation, this would call an API endpoint
                const stats = {
                    total_sales: 15,
                    active_sales: 3,
                    total_items: 247,
                    pending_claims: 18,
                    registered_sellers: 8,
                    active_buyers: 45
                };
                
                const statsRow = document.getElementById('claimsStatsRow');
                statsRow.innerHTML = `
                    <div class="stat-card">
                        <div class="stat-value">${stats.total_sales}</div>
                        <div class="stat-label">Total Sales</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.active_sales}</div>
                        <div class="stat-label">Active Sales</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.total_items}</div>
                        <div class="stat-label">Total Items</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.pending_claims}</div>
                        <div class="stat-label">Pending Claims</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.registered_sellers}</div>
                        <div class="stat-label">Registered Sellers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.active_buyers}</div>
                        <div class="stat-label">Active Buyers</div>
                    </div>
                `;
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }
        
        async function loadActiveSales() {
            try {
                // Get active sales from database
                const response = await fetch(`${basePath}/api/claims/sales/active`);
                const data = await response.json();
                
                const container = document.getElementById('activeSalesContainer');
                
                if (!data.success || data.data.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <h3>No Active Estate Sales</h3>
                            <p>There are currently no estate sales accepting claims.</p>
                            <button class="btn btn-primary" onclick="showCreateSaleModal()">
                                Create First Sale
                            </button>
                        </div>
                    `;
                    return;
                }
                
                renderSales(container, data.data, 'active');
                
            } catch (error) {
                console.error('Error loading active sales:', error);
                document.getElementById('activeSalesContainer').innerHTML = 
                    '<div class="empty-state"><h3>Error loading active sales</h3></div>';
            }
        }
        
        async function loadUpcomingSales() {
            try {
                const response = await fetch(`${basePath}/api/claims/sales/upcoming`);
                const data = await response.json();
                
                const container = document.getElementById('upcomingSalesContainer');
                
                if (!data.success || data.data.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <h3>No Upcoming Sales</h3>
                            <p>No estate sales scheduled for the future.</p>
                        </div>
                    `;
                    return;
                }
                
                renderSales(container, data.data, 'upcoming');
                
            } catch (error) {
                console.error('Error loading upcoming sales:', error);
                document.getElementById('upcomingSalesContainer').innerHTML = 
                    '<div class="empty-state"><h3>Error loading upcoming sales</h3></div>';
            }
        }
        
        async function loadCompletedSales() {
            try {
                const response = await fetch(`${basePath}/api/claims/sales/completed`);
                const data = await response.json();
                
                const container = document.getElementById('completedSalesContainer');
                
                if (!data.success || data.data.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <h3>No Completed Sales</h3>
                            <p>No estate sales have been completed yet.</p>
                        </div>
                    `;
                    return;
                }
                
                renderSales(container, data.data.slice(0, 6), 'completed'); // Show last 6
                
            } catch (error) {
                console.error('Error loading completed sales:', error);
                document.getElementById('completedSalesContainer').innerHTML = 
                    '<div class="empty-state"><h3>Error loading completed sales</h3></div>';
            }
        }
        
        async function loadSellers() {
            try {
                const response = await fetch(`${basePath}/api/claims/sellers`);
                const data = await response.json();
                
                const container = document.getElementById('sellersContainer');
                
                if (!data.success || data.data.length === 0) {
                    container.innerHTML = `
                        <div class="empty-state">
                            <h3>No Sellers Registered</h3>
                            <p>No estate sale companies have registered yet.</p>
                        </div>
                    `;
                    return;
                }
                
                let html = '<div class="sales-grid">';
                data.data.forEach(seller => {
                    const statusClass = seller.status === 'approved' ? 'success' : 
                                       seller.status === 'pending' ? 'warning' : 'secondary';
                    
                    html += `
                        <div class="sale-card">
                            <div class="sale-header">
                                <div class="sale-title">${escapeHtml(seller.company_name)}</div>
                                <div class="sale-company">Contact: ${escapeHtml(seller.contact_name)}</div>
                            </div>
                            <div class="sale-body">
                                <div class="sale-info">
                                    <div class="sale-info-item">📧 ${escapeHtml(seller.contact_email)}</div>
                                    <div class="sale-info-item">📞 ${escapeHtml(seller.contact_phone || 'No phone')}</div>
                                    <div class="sale-info-item">📍 ${escapeHtml(seller.address || 'No address')}</div>
                                    <div class="sale-info-item">
                                        <span class="badge badge-${statusClass}">${seller.status}</span>
                                    </div>
                                </div>
                                <div class="sale-actions">
                                    <button class="btn-manage" onclick="manageSeller(${seller.id})">
                                        Manage Seller
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                
                container.innerHTML = html;
                
            } catch (error) {
                console.error('Error loading sellers:', error);
                document.getElementById('sellersContainer').innerHTML = 
                    '<div class="empty-state"><h3>Error loading sellers</h3></div>';
            }
        }
        
        function renderSales(container, sales, type) {
            let html = '<div class="sales-grid">';
            
            sales.forEach(sale => {
                const startDate = new Date(sale.claim_start);
                const endDate = new Date(sale.claim_end);
                const saleDate = new Date(sale.sale_date);
                
                html += `
                    <div class="sale-card">
                        <div class="sale-header">
                            <div class="sale-title">${escapeHtml(sale.title)}</div>
                            <div class="sale-company">by ${escapeHtml(sale.company_name || 'Unknown Company')}</div>
                        </div>
                        <div class="sale-body">
                            <div class="sale-info">
                                <div class="sale-info-item">📍 ${escapeHtml(sale.location || 'Location TBD')}</div>
                                <div class="sale-info-item">📅 Claims: ${startDate.toLocaleDateString()} - ${endDate.toLocaleDateString()}</div>
                                <div class="sale-info-item">🏠 Sale: ${saleDate.toLocaleDateString()}</div>
                                <div class="sale-info-item">📊 Status: ${sale.status}</div>
                            </div>
                            
                            <div class="sale-stats">
                                <div class="stat-item">
                                    <div class="stat-value">${sale.item_count || 0}</div>
                                    <div class="stat-label">Items</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">${sale.buyer_count || 0}</div>
                                    <div class="stat-label">Buyers</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">${sale.offer_count || 0}</div>
                                    <div class="stat-label">Claims</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">${sale.approved_count || 0}</div>
                                    <div class="stat-label">Approved</div>
                                </div>
                            </div>
                            
                            <div class="sale-actions">
                                <a href="${basePath}/admin/claims/sale?id=${sale.id}" class="btn-manage">
                                    Manage Sale
                                </a>
                                <a href="${basePath}/claims/sale?id=${sale.id}" class="btn-manage" target="_blank">
                                    View Public
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function refreshData() {
            loadStatistics();
            loadActiveSales();
            loadUpcomingSales();
            loadCompletedSales();
            loadSellers();
            showToast('Data refreshed successfully', 'success');
        }
        
        function showCreateSaleModal() {
            showToast('Create sale functionality coming soon', 'info');
        }
        
        function showPendingSellers() {
            showToast('Pending sellers review coming soon', 'info');
        }
        
        function manageSeller(sellerId) {
            showToast('Seller management functionality coming soon', 'info');
        }
        
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast ${type} show`;
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        async function logout() {
            try {
                const response = await fetch(`${basePath}/admin/logout`, { method: 'POST' });
                const data = await response.json();
                
                if (data.success) {
                    window.location.href = `${basePath}/admin/login`;
                }
            } catch (error) {
                console.error('Logout error:', error);
                window.location.href = `${basePath}/admin/login`;
            }
        }
    </script>
</body>
</html>