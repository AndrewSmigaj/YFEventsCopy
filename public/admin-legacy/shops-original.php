<?php
// Admin Shops Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /admin/login');
    exit;
}

$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath === '/') {
    $basePath = '';
}
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
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #555;
            font-size: 0.9rem;
        }
        
        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a67d8;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #229954;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .btn-warning {
            background: #f39c12;
            color: white;
        }
        
        .btn-warning:hover {
            background: #e67e22;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        
        .shops-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .shop-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .shop-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .shop-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: #f0f0f0;
        }
        
        .shop-content {
            padding: 1.5rem;
        }
        
        .shop-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .shop-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .shop-category {
            font-size: 0.85rem;
            color: #7f8c8d;
        }
        
        .shop-status {
            display: flex;
            gap: 0.5rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-featured {
            background: #cce5ff;
            color: #004085;
        }
        
        .status-verified {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .shop-info {
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #555;
        }
        
        .shop-info p {
            margin-bottom: 0.25rem;
        }
        
        .shop-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            border-radius: 4px;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            max-width: 800px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .modal-title {
            font-size: 1.5rem;
            color: #2c3e50;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #7f8c8d;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #555;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .loading {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #7f8c8d;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #555;
        }
        
        .toast {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: #333;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s;
            z-index: 2000;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast.success {
            background: #27ae60;
        }
        
        .toast.error {
            background: #e74c3c;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .pagination button {
            padding: 0.5rem 1rem;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .pagination button:hover:not(:disabled) {
            background: #f8f9fa;
        }
        
        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .amenity-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-row {
                grid-template-columns: 1fr 1fr;
            }
            
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
                <a href="<?= $basePath ?>/admin">Dashboard</a>
                <a href="<?= $basePath ?>/admin/events">Events</a>
                <a href="<?= $basePath ?>/admin/shops" class="active">Shops</a>
                <a href="<?= $basePath ?>/admin/scrapers">Scrapers</a>
                <a href="<?= $basePath ?>/admin/users">Users</a>
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
        <div class="stats-row" id="statsRow">
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
        <div class="filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="searchInput">Search</label>
                    <input type="text" id="searchInput" placeholder="Search shops...">
                </div>
                <div class="filter-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="pending">Pending</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="categoryFilter">Category</label>
                    <select id="categoryFilter">
                        <option value="all">All Categories</option>
                        <option value="restaurant">Restaurant</option>
                        <option value="retail">Retail</option>
                        <option value="service">Service</option>
                        <option value="entertainment">Entertainment</option>
                        <option value="health">Health & Wellness</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="verifiedFilter">Verification</label>
                    <select id="verifiedFilter">
                        <option value="all">All Shops</option>
                        <option value="verified">Verified Only</option>
                        <option value="unverified">Unverified Only</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
                <button class="btn btn-secondary" onclick="resetFilters()">Reset</button>
                <button class="btn btn-warning" onclick="geocodeUnmapped()">
                    <span>📍</span> Geocode Unmapped
                </button>
            </div>
        </div>
        
        <!-- Shops Grid -->
        <div id="shopsContainer">
            <div class="loading">Loading shops...</div>
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
                            <option value="health">Health & Wellness</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="shopStatus">Status</label>
                        <select id="shopStatus" name="status">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="shopDescription">Description</label>
                    <textarea id="shopDescription" name="description"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="shopAddress">Address *</label>
                    <input type="text" id="shopAddress" name="address" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="shopCity">City *</label>
                        <input type="text" id="shopCity" name="city" value="Yakima" required>
                    </div>
                    <div class="form-group">
                        <label for="shopState">State *</label>
                        <input type="text" id="shopState" name="state" value="WA" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="shopZip">ZIP Code</label>
                        <input type="text" id="shopZip" name="zip_code">
                    </div>
                    <div class="form-group">
                        <label for="shopPhone">Phone</label>
                        <input type="tel" id="shopPhone" name="phone">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="shopEmail">Email</label>
                        <input type="email" id="shopEmail" name="email">
                    </div>
                    <div class="form-group">
                        <label for="shopWebsite">Website</label>
                        <input type="url" id="shopWebsite" name="website">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="shopLatitude">Latitude</label>
                        <input type="number" step="any" id="shopLatitude" name="latitude">
                    </div>
                    <div class="form-group">
                        <label for="shopLongitude">Longitude</label>
                        <input type="number" step="any" id="shopLongitude" name="longitude">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Features</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                        <label class="checkbox-label">
                            <input type="checkbox" name="featured" id="shopFeatured">
                            Featured Shop
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="verified" id="shopVerified">
                            Verified Business
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Amenities</label>
                    <div class="amenity-grid">
                        <label class="checkbox-label">
                            <input type="checkbox" name="amenities[]" value="wheelchair_accessible">
                            Wheelchair Accessible
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="amenities[]" value="parking">
                            Parking Available
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="amenities[]" value="wifi">
                            Free WiFi
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="amenities[]" value="outdoor_seating">
                            Outdoor Seating
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="amenities[]" value="delivery">
                            Delivery Available
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="amenities[]" value="takeout">
                            Takeout Available
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="amenities[]" value="reservations">
                            Accepts Reservations
                        </label>
                        <label class="checkbox-label">
                            <input type="checkbox" name="amenities[]" value="credit_cards">
                            Accepts Credit Cards
                        </label>
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 2rem;">
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
        let currentPage = 1;
        let currentFilters = {};
        let shopsData = [];
        
        // Load initial data
        document.addEventListener('DOMContentLoaded', () => {
            loadStatistics();
            loadShops();
        });
        
        async function loadStatistics() {
            try {
                const response = await fetch(`${basePath}/admin/shops/statistics`);
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
        
        async function loadShops(page = 1) {
            try {
                currentPage = page;
                const params = new URLSearchParams({
                    page: page,
                    limit: 12,
                    ...currentFilters
                });
                
                const response = await fetch(`${basePath}/admin/shops?${params}`);
                const data = await response.json();
                
                if (data.success) {
                    shopsData = data.data.shops;
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
            
            if (shopsData.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <h3>No shops found</h3>
                        <p>Try adjusting your filters or add a new shop.</p>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="shops-grid">';
            
            shopsData.forEach(shop => {
                const badges = [];
                if (shop.featured) badges.push('<span class="status-badge status-featured">Featured</span>');
                if (shop.verified) badges.push('<span class="status-badge status-verified">Verified</span>');
                
                html += `
                    <div class="shop-card">
                        ${shop.image_url ? 
                            `<img src="${shop.image_url}" alt="${escapeHtml(shop.name)}" class="shop-image">` : 
                            `<div class="shop-image" style="display: flex; align-items: center; justify-content: center; color: #999;">
                                <span style="font-size: 3rem;">🏪</span>
                            </div>`
                        }
                        <div class="shop-content">
                            <div class="shop-header">
                                <div>
                                    <h3 class="shop-title">${escapeHtml(shop.name)}</h3>
                                    <p class="shop-category">${shop.category}</p>
                                </div>
                                <div class="shop-status">
                                    <span class="status-badge status-${shop.status}">${shop.status}</span>
                                </div>
                            </div>
                            ${badges.length > 0 ? `<div style="margin-bottom: 0.5rem;">${badges.join(' ')}</div>` : ''}
                            <div class="shop-info">
                                <p>📍 ${escapeHtml(shop.address)}</p>
                                ${shop.phone ? `<p>📞 ${escapeHtml(shop.phone)}</p>` : ''}
                                ${shop.website ? `<p>🌐 <a href="${escapeHtml(shop.website)}" target="_blank" style="color: #667eea;">Visit Website</a></p>` : ''}
                            </div>
                            <div class="shop-actions">
                                <button class="btn btn-primary action-btn" onclick="editShop(${shop.id})">Edit</button>
                                <button class="btn btn-warning action-btn" onclick="toggleFeatured(${shop.id}, ${shop.featured})">
                                    ${shop.featured ? 'Unfeature' : 'Feature'}
                                </button>
                                ${shop.status === 'pending' ? 
                                    `<button class="btn btn-success action-btn" onclick="approveShop(${shop.id})">Approve</button>` : 
                                    ''
                                }
                                <button class="btn btn-danger action-btn" onclick="deleteShop(${shop.id})">Delete</button>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            
            // Add pagination
            html += `
                <div class="pagination">
                    <button onclick="loadShops(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>
                    <span>Page ${currentPage}</span>
                    <button onclick="loadShops(${currentPage + 1})" ${shopsData.length < 12 ? 'disabled' : ''}>Next</button>
                </div>
            `;
            
            container.innerHTML = html;
        }
        
        function applyFilters() {
            currentFilters = {
                search: document.getElementById('searchInput').value,
                status: document.getElementById('statusFilter').value,
                category: document.getElementById('categoryFilter').value,
                verified: document.getElementById('verifiedFilter').value
            };
            
            // Remove 'all' values
            Object.keys(currentFilters).forEach(key => {
                if (currentFilters[key] === 'all') {
                    delete currentFilters[key];
                }
            });
            
            loadShops(1);
        }
        
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('categoryFilter').value = 'all';
            document.getElementById('verifiedFilter').value = 'all';
            currentFilters = {};
            loadShops(1);
        }
        
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add Shop';
            document.getElementById('shopForm').reset();
            document.getElementById('shopId').value = '';
            document.getElementById('shopModal').classList.add('show');
        }
        
        async function editShop(shopId) {
            const shop = shopsData.find(s => s.id === shopId);
            if (!shop) return;
            
            document.getElementById('modalTitle').textContent = 'Edit Shop';
            document.getElementById('shopId').value = shop.id;
            document.getElementById('shopName').value = shop.name;
            document.getElementById('shopCategory').value = shop.category;
            document.getElementById('shopStatus').value = shop.status;
            document.getElementById('shopDescription').value = shop.description || '';
            document.getElementById('shopAddress').value = shop.address;
            document.getElementById('shopCity').value = shop.city;
            document.getElementById('shopState').value = shop.state;
            document.getElementById('shopZip').value = shop.zip_code || '';
            document.getElementById('shopPhone').value = shop.phone || '';
            document.getElementById('shopEmail').value = shop.email || '';
            document.getElementById('shopWebsite').value = shop.website || '';
            document.getElementById('shopLatitude').value = shop.latitude || '';
            document.getElementById('shopLongitude').value = shop.longitude || '';
            document.getElementById('shopFeatured').checked = shop.featured;
            document.getElementById('shopVerified').checked = shop.verified;
            
            // Set amenities
            const amenities = shop.amenities ? JSON.parse(shop.amenities) : [];
            document.querySelectorAll('input[name="amenities[]"]').forEach(checkbox => {
                checkbox.checked = amenities.includes(checkbox.value);
            });
            
            document.getElementById('shopModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('shopModal').classList.remove('show');
        }
        
        document.getElementById('shopForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            // Handle checkboxes
            data.featured = document.getElementById('shopFeatured').checked;
            data.verified = document.getElementById('shopVerified').checked;
            
            // Handle amenities
            const amenities = [];
            document.querySelectorAll('input[name="amenities[]"]:checked').forEach(checkbox => {
                amenities.push(checkbox.value);
            });
            data.amenities = JSON.stringify(amenities);
            
            const isEdit = !!data.id;
            const url = isEdit ? `${basePath}/admin/shops/update` : `${basePath}/admin/shops/create`;
            
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(result.message || `Shop ${isEdit ? 'updated' : 'created'} successfully`, 'success');
                    closeModal();
                    loadShops(currentPage);
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to save shop', 'error');
                }
            } catch (error) {
                console.error('Error saving shop:', error);
                showToast('Error saving shop', 'error');
            }
        });
        
        async function toggleFeatured(shopId, currentStatus) {
            try {
                const response = await fetch(`${basePath}/admin/shops/update`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ 
                        id: shopId, 
                        featured: !currentStatus 
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`Shop ${!currentStatus ? 'featured' : 'unfeatured'} successfully`, 'success');
                    loadShops(currentPage);
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to update shop', 'error');
                }
            } catch (error) {
                console.error('Error updating shop:', error);
                showToast('Error updating shop', 'error');
            }
        }
        
        async function approveShop(shopId) {
            if (!confirm('Are you sure you want to approve this shop?')) return;
            
            try {
                const response = await fetch(`${basePath}/admin/shops/approve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: shopId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Shop approved successfully', 'success');
                    loadShops(currentPage);
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to approve shop', 'error');
                }
            } catch (error) {
                console.error('Error approving shop:', error);
                showToast('Error approving shop', 'error');
            }
        }
        
        async function deleteShop(shopId) {
            if (!confirm('Are you sure you want to delete this shop?')) return;
            
            try {
                const response = await fetch(`${basePath}/admin/shops/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id: shopId })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('Shop deleted successfully', 'success');
                    loadShops(currentPage);
                    loadStatistics();
                } else {
                    showToast(result.message || 'Failed to delete shop', 'error');
                }
            } catch (error) {
                console.error('Error deleting shop:', error);
                showToast('Error deleting shop', 'error');
            }
        }
        
        async function geocodeUnmapped() {
            if (!confirm('This will geocode all shops without coordinates. Continue?')) return;
            
            showToast('Geocoding unmapped shops...', 'success');
            
            try {
                const response = await fetch(`${basePath}/admin/shops/geocode`, {
                    method: 'POST'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast(`Geocoded ${result.data.count} shops successfully`, 'success');
                    loadShops(currentPage);
                } else {
                    showToast(result.message || 'Failed to geocode shops', 'error');
                }
            } catch (error) {
                console.error('Error geocoding shops:', error);
                showToast('Error geocoding shops', 'error');
            }
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