<?php
// Admin Shops Management Page
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
    <title>Shop Management - YFEvents Admin</title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        /* Page-specific styles for shops page */
        .shops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: var(--spacing-lg);
        }
        
        .shop-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition-normal);
        }
        
        .shop-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .shop-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--gray-100);
        }
        
        .shop-title {
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-bold);
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .shop-category {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }
        
        .shop-status {
            display: flex;
            gap: var(--spacing-xs);
            margin-top: var(--spacing-sm);
        }
        
        .shop-info {
            padding: var(--spacing-lg);
            font-size: var(--font-size-sm);
            color: var(--gray-700);
        }
        
        .shop-info p {
            margin-bottom: var(--spacing-xs);
        }
        
        .shop-actions {
            padding: var(--spacing-lg);
            border-top: 1px solid var(--gray-100);
            display: flex;
            gap: var(--spacing-xs);
            flex-wrap: wrap;
        }
        
        .amenity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: var(--spacing-xs);
        }
        
        @media (max-width: 768px) {
            .shops-grid {
                grid-template-columns: 1fr;
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
                <a href="<?= $basePath ?>/admin/shops.php" class="active">Shops</a>
                <a href="<?= $basePath ?>/admin/claims.php">Claims</a>
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
            <h2 class="page-title">Shop Management</h2>
            <button class="btn btn-primary" onclick="showCreateModal()">
                <span>+</span> Add Shop
            </button>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid" id="statsRow">
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Total Shops</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Featured</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Verified</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="table-container">
            <div class="table-header">
                <h3>Filter Shops</h3>
            </div>
            <div class="filters-grid">
                <div class="form-group">
                    <label for="searchShops">Search</label>
                    <input type="text" id="searchShops" placeholder="Search shops..." onkeyup="filterShops()">
                </div>
                <div class="form-group">
                    <label for="filterCategory">Category</label>
                    <select id="filterCategory" onchange="filterShops()">
                        <option value="">All Categories</option>
                        <option value="restaurant">Restaurant</option>
                        <option value="retail">Retail</option>
                        <option value="service">Service</option>
                        <option value="entertainment">Entertainment</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filterStatus">Status</label>
                    <select id="filterStatus" onchange="filterShops()">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <button class="btn btn-secondary" onclick="clearFilters()">Clear Filters</button>
                    <button class="btn btn-secondary" onclick="refreshShops()">
                        <span>🔄</span> Refresh
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Shops Container -->
        <div class="table-container">
            <div class="table-header">
                <h3>Local Shops</h3>
            </div>
            <div id="shopsContainer">
                <div class="loading">Loading shops...</div>
            </div>
        </div>
    </div>
    
    <!-- Shop Modal -->
    <div id="shopModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add Shop</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="shopForm">
                <input type="hidden" id="shopId" name="id">
                
                <div class="form-group">
                    <label for="shopName">Shop Name *</label>
                    <input type="text" id="shopName" name="name" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="shopCategory">Category *</label>
                        <select id="shopCategory" name="category" required>
                            <option value="">Select Category</option>
                            <option value="restaurant">Restaurant</option>
                            <option value="retail">Retail</option>
                            <option value="service">Service</option>
                            <option value="entertainment">Entertainment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="shopPhone">Phone</label>
                        <input type="tel" id="shopPhone" name="phone">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="shopAddress">Address *</label>
                    <input type="text" id="shopAddress" name="address" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="shopWebsite">Website</label>
                        <input type="url" id="shopWebsite" name="website">
                    </div>
                    <div class="form-group">
                        <label for="shopEmail">Email</label>
                        <input type="email" id="shopEmail" name="email">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="shopDescription">Description</label>
                    <textarea id="shopDescription" name="description" placeholder="Brief description of the business..."></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="shopActive" name="active" checked>
                            Active
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="shopFeatured" name="featured">
                            Featured
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Shop</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        const apiBasePath = '<?= $basePath ?>'; // API calls should use same base path
        let shopsData = [];
        let filteredShops = [];
        
        // Load initial data
        document.addEventListener('DOMContentLoaded', () => {
            loadStatistics();
            loadShops();
        });
        
        async function loadStatistics() {
            try {
                const response = await fetch(`${apiBasePath}/api/admin/shops/statistics`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    const stats = data.data.statistics;
                    const statsRow = document.getElementById('statsRow');
                    statsRow.innerHTML = `
                        <div class="stat-card">
                            <div class="stat-value">${stats.total || 0}</div>
                            <div class="stat-label">Total Shops</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.active || 0}</div>
                            <div class="stat-label">Active</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.pending || 0}</div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.featured || 0}</div>
                            <div class="stat-label">Featured</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.verified || 0}</div>
                            <div class="stat-label">Verified</div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }
        
        async function loadShops() {
            try {
                const response = await fetch(`${apiBasePath}/api/admin/shops`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    shopsData = data.data.shops;
                    filteredShops = [...shopsData];
                    renderShops();
                } else {
                    showToast(data.message || 'Failed to load shops', 'error');
                }
            } catch (error) {
                console.error('Error loading shops:', error);
                showToast('Error loading shops', 'error');
            }
        }
        
        function renderShops() {
            const container = document.getElementById('shopsContainer');
            
            if (filteredShops.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <h3>No shops found</h3>
                        <p>Add a shop to get started.</p>
                        <button class="btn btn-primary" onclick="showCreateModal()" style="margin-top: 1rem;">
                            <span>+</span> Add First Shop
                        </button>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="shops-grid">';
            
            filteredShops.forEach(shop => {
                const status = shop.status || 'active';
                const category = shop.category || 'general';
                
                html += `
                    <div class="shop-card">
                        <div class="shop-header">
                            <div class="shop-title">${escapeHtml(shop.name)}</div>
                            <div class="shop-category">${category.charAt(0).toUpperCase() + category.slice(1)}</div>
                            <div class="shop-status">
                                <span class="badge badge-${status === 'active' ? 'success' : status === 'pending' ? 'warning' : 'secondary'}">${status}</span>
                                ${shop.featured ? '<span class="badge badge-primary">Featured</span>' : ''}
                                ${shop.verified ? '<span class="badge badge-info">Verified</span>' : ''}
                            </div>
                        </div>
                        
                        <div class="shop-info">
                            ${shop.address ? `<p><strong>Address:</strong> ${escapeHtml(shop.address)}</p>` : ''}
                            ${shop.phone ? `<p><strong>Phone:</strong> ${escapeHtml(shop.phone)}</p>` : ''}
                            ${shop.website ? `<p><strong>Website:</strong> <a href="${shop.website}" target="_blank">${shop.website}</a></p>` : ''}
                            ${shop.description ? `<p><strong>Description:</strong> ${escapeHtml(shop.description.substring(0, 100))}${shop.description.length > 100 ? '...' : ''}</p>` : ''}
                        </div>
                        
                        <div class="shop-actions">
                            <button class="btn btn-sm btn-primary" onclick="editShop(${shop.id})">
                                <span>✏️</span> Edit
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="viewShop(${shop.id})">
                                <span>👁️</span> View
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="toggleFeatured(${shop.id}, ${shop.featured ? 'false' : 'true'})">
                                <span>${shop.featured ? '⭐' : '☆'}</span> ${shop.featured ? 'Unfeature' : 'Feature'}
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteShop(${shop.id})">
                                <span>🗑️</span> Delete
                            </button>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function filterShops() {
            const searchTerm = document.getElementById('searchShops').value.toLowerCase();
            const categoryFilter = document.getElementById('filterCategory').value;
            const statusFilter = document.getElementById('filterStatus').value;
            
            filteredShops = shopsData.filter(shop => {
                const matchesSearch = !searchTerm || 
                    shop.name.toLowerCase().includes(searchTerm) ||
                    (shop.description && shop.description.toLowerCase().includes(searchTerm)) ||
                    (shop.address && shop.address.toLowerCase().includes(searchTerm));
                    
                const matchesCategory = !categoryFilter || shop.category === categoryFilter;
                const matchesStatus = !statusFilter || shop.status === statusFilter;
                
                return matchesSearch && matchesCategory && matchesStatus;
            });
            
            renderShops();
        }
        
        function clearFilters() {
            document.getElementById('searchShops').value = '';
            document.getElementById('filterCategory').value = '';
            document.getElementById('filterStatus').value = '';
            filteredShops = [...shopsData];
            renderShops();
        }
        
        function refreshShops() {
            loadShops();
            loadStatistics();
        }
        
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add Shop';
            document.getElementById('shopForm').reset();
            document.getElementById('shopId').value = '';
            document.getElementById('shopModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('shopModal').classList.remove('show');
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
        
        // Additional functions for shop management
        async function editShop(shopId) {
            showToast('Edit functionality coming soon', 'info');
        }
        
        async function viewShop(shopId) {
            showToast('View functionality coming soon', 'info');
        }
        
        async function toggleFeatured(shopId, featured) {
            showToast('Feature toggle coming soon', 'info');
        }
        
        async function deleteShop(shopId) {
            if (!confirm('Are you sure you want to delete this shop?')) return;
            showToast('Delete functionality coming soon', 'info');
        }
    </script>
</body>
</html>