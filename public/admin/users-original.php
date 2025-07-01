<?php
// Admin Users Management Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /refactor/admin/login');
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
    <title>User Management - YFEvents Admin</title>
    <link rel="stylesheet" href="<?= $basePath ?>/css/admin-theme.css">
    <style>
        /* Page-specific styles for users page */
            align-items: center;
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background-color 0.3s;
        }
        
        .nav-links a:hover {
            background: rgba(255,255,255,0.1);
        }
        
        .nav-links a.active {
            background: rgba(255,255,255,0.2);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-size: 2rem;
            color: #2c3e50;
        }
        
        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
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
        
        .users-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .bulk-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .bulk-actions.hidden {
            display: none;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e9ecef;
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #667eea;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }
        
        .user-email {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .role-admin {
            background: #f8d7da;
            color: #721c24;
        }
        
        .role-moderator {
            background: #fff3cd;
            color: #856404;
        }
        
        .role-user {
            background: #d4edda;
            color: #155724;
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
        
        .status-suspended {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.5rem 0.75rem;
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
            max-width: 600px;
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
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
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
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #555;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            border-top: 1px solid #e9ecef;
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .users-table {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
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
                <a href="<?= $basePath ?>/admin/shops">Shops</a>
                <a href="<?= $basePath ?>/admin/scrapers">Scrapers</a>
                <a href="<?= $basePath ?>/admin/users" class="active">Users</a>
                <a href="#" onclick="logout()">Logout</a>
            </nav>
        </div>
    </header>
    
    <div class="container">
        <div class="page-header">
            <h2 class="page-title">User Management</h2>
            <button class="btn btn-primary" onclick="showCreateModal()">
                <span>+</span> Add User
            </button>
        </div>
        
        <!-- Statistics -->
        <div class="stats-row" id="statsRow">
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Administrators</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">New This Month</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="searchInput">Search</label>
                    <input type="text" id="searchInput" placeholder="Search users...">
                </div>
                <div class="filter-group">
                    <label for="roleFilter">Role</label>
                    <select id="roleFilter">
                        <option value="all">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="moderator">Moderator</option>
                        <option value="user">User</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter">
                        <option value="all">All Status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="pending">Pending</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="dateFilter">Registration</label>
                    <select id="dateFilter">
                        <option value="all">All Time</option>
                        <option value="today">Today</option>
                        <option value="week">This Week</option>
                        <option value="month">This Month</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
                <button class="btn btn-secondary" onclick="resetFilters()">Reset</button>
                <button class="btn btn-warning" onclick="exportUsers()">
                    <span>📊</span> Export Users
                </button>
            </div>
        </div>
        
        <!-- Users Table -->
        <div class="users-table">
            <div class="table-header">
                <h3>Users List</h3>
                <div class="bulk-actions hidden" id="bulkActions">
                    <span id="selectedCount">0 selected</span>
                    <button class="btn btn-success action-btn" onclick="bulkActivate()">Activate</button>
                    <button class="btn btn-warning action-btn" onclick="bulkSuspend()">Suspend</button>
                    <button class="btn btn-danger action-btn" onclick="bulkDelete()">Delete</button>
                </div>
            </div>
            <div id="usersTableContent">
                <div class="loading">Loading users...</div>
            </div>
        </div>
    </div>
    
    <!-- User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Add User</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="userForm">
                <input type="hidden" id="userId" name="id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="userName">Username *</label>
                        <input type="text" id="userName" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="userEmail">Email *</label>
                        <input type="email" id="userEmail" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="userFirstName">First Name</label>
                        <input type="text" id="userFirstName" name="first_name">
                    </div>
                    <div class="form-group">
                        <label for="userLastName">Last Name</label>
                        <input type="text" id="userLastName" name="last_name">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="userPassword">Password *</label>
                    <input type="password" id="userPassword" name="password" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="userRole">Role</label>
                        <select id="userRole" name="role">
                            <option value="user">User</option>
                            <option value="moderator">Moderator</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="userStatus">Status</label>
                        <select id="userStatus" name="status">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>
    
    <script>
        const basePath = '<?= $basePath ?>';
        let currentPage = 1;
        let selectedUsers = new Set();
        let currentFilters = {};
        let usersData = [];
        
        // Load initial data
        document.addEventListener('DOMContentLoaded', () => {
            loadStatistics();
            loadUsers();
        });
        
        async function loadStatistics() {
            try {
                // Simulate user statistics
                const stats = {
                    total: 156,
                    active: 142,
                    admins: 3,
                    new_this_month: 12
                };
                
                const statsRow = document.getElementById('statsRow');
                statsRow.innerHTML = `
                    <div class="stat-card">
                        <div class="stat-value">${stats.total}</div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.active}</div>
                        <div class="stat-label">Active Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.admins}</div>
                        <div class="stat-label">Administrators</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">${stats.new_this_month}</div>
                        <div class="stat-label">New This Month</div>
                    </div>
                `;
                
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }
        
        async function loadUsers(page = 1) {
            try {
                currentPage = page;
                
                // Simulate user data
                usersData = [
                    {
                        id: 1,
                        username: 'admin',
                        email: 'admin@yfevents.com',
                        first_name: 'Admin',
                        last_name: 'User',
                        role: 'admin',
                        status: 'active',
                        created_at: '2024-01-15 10:30:00',
                        last_login: '2024-12-14 09:15:00'
                    },
                    {
                        id: 2,
                        username: 'moderator1',
                        email: 'mod@yfevents.com',
                        first_name: 'Jane',
                        last_name: 'Doe',
                        role: 'moderator',
                        status: 'active',
                        created_at: '2024-02-20 14:20:00',
                        last_login: '2024-12-13 16:45:00'
                    },
                    {
                        id: 3,
                        username: 'johnsmith',
                        email: 'john@example.com',
                        first_name: 'John',
                        last_name: 'Smith',
                        role: 'user',
                        status: 'active',
                        created_at: '2024-03-10 11:00:00',
                        last_login: '2024-12-12 20:30:00'
                    },
                    {
                        id: 4,
                        username: 'newuser',
                        email: 'new@example.com',
                        first_name: 'New',
                        last_name: 'User',
                        role: 'user',
                        status: 'pending',
                        created_at: '2024-12-14 08:00:00',
                        last_login: null
                    }
                ];
                
                renderUsersTable();
                
            } catch (error) {
                console.error('Error loading users:', error);
                showToast('Error loading users', 'error');
            }
        }
        
        function renderUsersTable() {
            const container = document.getElementById('usersTableContent');
            
            if (usersData.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <h3>No users found</h3>
                        <p>Try adjusting your filters or add a new user.</p>
                    </div>
                `;
                return;
            }
            
            let html = `
                <table>
                    <thead>
                        <tr>
                            <th width="30">
                                <input type="checkbox" onchange="toggleSelectAll(this)">
                            </th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            usersData.forEach(user => {
                const initials = `${user.first_name?.[0] || ''}${user.last_name?.[0] || ''}` || user.username[0].toUpperCase();
                const registeredDate = new Date(user.created_at).toLocaleDateString();
                const lastLogin = user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never';
                
                html += `
                    <tr>
                        <td>
                            <input type="checkbox" value="${user.id}" onchange="toggleUserSelection(${user.id})">
                        </td>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">${initials}</div>
                                <div class="user-details">
                                    <div class="user-name">${escapeHtml(user.first_name || '')} ${escapeHtml(user.last_name || '')}</div>
                                    <div class="user-email">${escapeHtml(user.email)}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge role-${user.role}">${user.role}</span>
                        </td>
                        <td>
                            <span class="status-badge status-${user.status}">${user.status}</span>
                        </td>
                        <td>${registeredDate}</td>
                        <td>${lastLogin}</td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn btn-primary action-btn" onclick="editUser(${user.id})">Edit</button>
                                ${user.status === 'suspended' ? 
                                    `<button class="btn btn-success action-btn" onclick="activateUser(${user.id})">Activate</button>` : 
                                    `<button class="btn btn-warning action-btn" onclick="suspendUser(${user.id})">Suspend</button>`
                                }
                                <button class="btn btn-danger action-btn" onclick="deleteUser(${user.id})">Delete</button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
                <div class="pagination">
                    <button onclick="loadUsers(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>Previous</button>
                    <span>Page ${currentPage}</span>
                    <button onclick="loadUsers(${currentPage + 1})" ${usersData.length < 20 ? 'disabled' : ''}>Next</button>
                </div>
            `;
            
            container.innerHTML = html;
        }
        
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('tbody input[type="checkbox"]');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
                const userId = parseInt(cb.value);
                if (checkbox.checked) {
                    selectedUsers.add(userId);
                } else {
                    selectedUsers.delete(userId);
                }
            });
            updateBulkActions();
        }
        
        function toggleUserSelection(userId) {
            if (selectedUsers.has(userId)) {
                selectedUsers.delete(userId);
            } else {
                selectedUsers.add(userId);
            }
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            
            if (selectedUsers.size > 0) {
                bulkActions.classList.remove('hidden');
                selectedCount.textContent = `${selectedUsers.size} selected`;
            } else {
                bulkActions.classList.add('hidden');
            }
        }
        
        function applyFilters() {
            currentFilters = {
                search: document.getElementById('searchInput').value,
                role: document.getElementById('roleFilter').value,
                status: document.getElementById('statusFilter').value,
                date: document.getElementById('dateFilter').value
            };
            
            // Remove 'all' values
            Object.keys(currentFilters).forEach(key => {
                if (currentFilters[key] === 'all') {
                    delete currentFilters[key];
                }
            });
            
            loadUsers(1);
        }
        
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('roleFilter').value = 'all';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('dateFilter').value = 'all';
            currentFilters = {};
            loadUsers(1);
        }
        
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add User';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('userModal').classList.add('show');
        }
        
        function editUser(userId) {
            const user = usersData.find(u => u.id === userId);
            if (!user) return;
            
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('userId').value = user.id;
            document.getElementById('userName').value = user.username;
            document.getElementById('userEmail').value = user.email;
            document.getElementById('userFirstName').value = user.first_name || '';
            document.getElementById('userLastName').value = user.last_name || '';
            document.getElementById('userPassword').value = '';
            document.getElementById('userPassword').required = false;
            document.getElementById('userRole').value = user.role;
            document.getElementById('userStatus').value = user.status;
            
            document.getElementById('userModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('userModal').classList.remove('show');
            document.getElementById('userPassword').required = true;
        }
        
        function activateUser(userId) {
            if (!confirm('Are you sure you want to activate this user?')) return;
            showToast('User activated successfully', 'success');
            loadUsers(currentPage);
        }
        
        function suspendUser(userId) {
            if (!confirm('Are you sure you want to suspend this user?')) return;
            showToast('User suspended successfully', 'success');
            loadUsers(currentPage);
        }
        
        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) return;
            showToast('User deleted successfully', 'success');
            loadUsers(currentPage);
        }
        
        function bulkActivate() {
            if (!confirm(`Are you sure you want to activate ${selectedUsers.size} users?`)) return;
            showToast(`${selectedUsers.size} users activated successfully`, 'success');
            selectedUsers.clear();
            updateBulkActions();
            loadUsers(currentPage);
        }
        
        function bulkSuspend() {
            if (!confirm(`Are you sure you want to suspend ${selectedUsers.size} users?`)) return;
            showToast(`${selectedUsers.size} users suspended successfully`, 'success');
            selectedUsers.clear();
            updateBulkActions();
            loadUsers(currentPage);
        }
        
        function bulkDelete() {
            if (!confirm(`Are you sure you want to delete ${selectedUsers.size} users? This action cannot be undone.`)) return;
            showToast(`${selectedUsers.size} users deleted successfully`, 'success');
            selectedUsers.clear();
            updateBulkActions();
            loadUsers(currentPage);
        }
        
        function exportUsers() {
            showToast('Exporting users...', 'success');
            // In real implementation, this would generate and download a CSV/Excel file
        }
        
        document.getElementById('userForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            const isEdit = !!data.id;
            
            try {
                // Simulate API call
                showToast(isEdit ? 'User updated successfully' : 'User created successfully', 'success');
                closeModal();
                loadUsers(currentPage);
                loadStatistics();
            } catch (error) {
                console.error('Error saving user:', error);
                showToast('Error saving user', 'error');
            }
        });
        
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