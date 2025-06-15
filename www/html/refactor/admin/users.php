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

// Set correct base path for refactor admin
$basePath = '/refactor';
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
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: var(--spacing-lg);
        }
        
        .user-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: var(--spacing-lg);
            transition: var(--transition-normal);
        }
        
        .user-card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        
        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }
        
        .user-name {
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-semibold);
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .user-email {
            font-size: var(--font-size-sm);
            color: var(--gray-600);
        }
        
        .user-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-sm);
            margin: var(--spacing-md) 0;
            font-size: var(--font-size-sm);
            color: var(--gray-700);
        }
        
        .user-actions {
            display: flex;
            gap: var(--spacing-sm);
            margin-top: var(--spacing-md);
        }
        
        @media (max-width: 768px) {
            .users-grid {
                grid-template-columns: 1fr;
            }
            
            .user-meta {
                grid-template-columns: 1fr;
            }
            
            .user-actions {
                flex-direction: column;
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
                <a href="<?= $basePath ?>/admin/claims.php">Claims</a>
                <a href="<?= $basePath ?>/admin/scrapers.php">Scrapers</a>
                <a href="<?= $basePath ?>/admin/users.php" class="active">Users</a>
                <a href="<?= $basePath ?>/admin/settings.php">Settings</a>
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
        <div class="stats-grid" id="statsRow">
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
                <div class="stat-label">Admin Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">-</div>
                <div class="stat-label">Recent Logins</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="table-container">
            <div class="table-header">
                <h3>Filter Users</h3>
            </div>
            <div class="filters-grid">
                <div class="form-group">
                    <label for="searchUsers">Search</label>
                    <input type="text" id="searchUsers" placeholder="Search users..." onkeyup="filterUsers()">
                </div>
                <div class="form-group">
                    <label for="filterRole">Role</label>
                    <select id="filterRole" onchange="filterUsers()">
                        <option value="">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="user">User</option>
                        <option value="moderator">Moderator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="filterStatus">Status</label>
                    <select id="filterStatus" onchange="filterUsers()">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="banned">Banned</option>
                    </select>
                </div>
                <div class="form-group">
                    <button class="btn btn-secondary" onclick="clearFilters()">Clear Filters</button>
                    <button class="btn btn-secondary" onclick="refreshUsers()">
                        <span>🔄</span> Refresh
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Users Container -->
        <div class="table-container">
            <div class="table-header">
                <h3>System Users</h3>
            </div>
            <div id="usersContainer">
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
                        <label for="userPassword">Password *</label>
                        <input type="password" id="userPassword" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="userRole">Role *</label>
                        <select id="userRole" name="role" required>
                            <option value="">Select Role</option>
                            <option value="user">User</option>
                            <option value="moderator">Moderator</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="userFullName">Full Name</label>
                    <input type="text" id="userFullName" name="full_name">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="userActive" name="active" checked>
                            Active
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="userEmailVerified" name="email_verified">
                            Email Verified
                        </label>
                    </div>
                </div>
                
                <div class="form-actions">
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
        const apiBasePath = '<?= $basePath ?>'; // API calls should use same base path
        let usersData = [];
        let filteredUsers = [];
        
        // Load initial data
        document.addEventListener('DOMContentLoaded', () => {
            loadStatistics();
            loadUsers();
        });
        
        async function loadStatistics() {
            try {
                const response = await fetch(`${apiBasePath}/api/users/statistics`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    const stats = data.data.statistics;
                    const statsRow = document.getElementById('statsRow');
                    statsRow.innerHTML = `
                        <div class="stat-card">
                            <div class="stat-value">${stats.total_users || 0}</div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.active_users || 0}</div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.admin_users || 0}</div>
                            <div class="stat-label">Admin Users</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value">${stats.recent_logins || 0}</div>
                            <div class="stat-label">Recent Logins</div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading statistics:', error);
            }
        }
        
        async function loadUsers() {
            try {
                const response = await fetch(`${apiBasePath}/api/users`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    usersData = data.data;
                    filteredUsers = [...usersData];
                    renderUsers();
                } else {
                    showToast(data.message || 'Failed to load users', 'error');
                }
            } catch (error) {
                console.error('Error loading users:', error);
                showToast('Error loading users', 'error');
            }
        }
        
        function renderUsers() {
            const container = document.getElementById('usersContainer');
            
            if (filteredUsers.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <h3>No users found</h3>
                        <p>Add a user to get started.</p>
                        <button class="btn btn-primary" onclick="showCreateModal()" style="margin-top: 1rem;">
                            <span>+</span> Add First User
                        </button>
                    </div>
                `;
                return;
            }
            
            let html = '<div class="users-grid">';
            
            filteredUsers.forEach(user => {
                const status = user.status || 'active';
                const role = user.role || 'user';
                const lastLogin = user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never';
                
                html += `
                    <div class="user-card">
                        <div class="user-header">
                            <div>
                                <div class="user-name">${escapeHtml(user.username || user.full_name || 'Unknown')}</div>
                                <div class="user-email">${escapeHtml(user.email)}</div>
                            </div>
                            <div style="display: flex; gap: 0.5rem; flex-direction: column; align-items: flex-end;">
                                <span class="badge badge-${status === 'active' ? 'success' : status === 'banned' ? 'danger' : 'secondary'}">${status}</span>
                                <span class="badge badge-${role === 'admin' ? 'danger' : role === 'moderator' ? 'warning' : 'primary'}">${role}</span>
                            </div>
                        </div>
                        
                        <div class="user-meta">
                            <div><strong>Created:</strong> ${user.created_at ? new Date(user.created_at).toLocaleDateString() : 'Unknown'}</div>
                            <div><strong>Last Login:</strong> ${lastLogin}</div>
                            <div><strong>Email:</strong> ${user.email_verified ? '✅ Verified' : '❌ Unverified'}</div>
                            <div><strong>Full Name:</strong> ${escapeHtml(user.full_name || 'Not set')}</div>
                        </div>
                        
                        <div class="user-actions">
                            <button class="btn btn-sm btn-primary" onclick="editUser(${user.id})">
                                <span>✏️</span> Edit
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="viewUser(${user.id})">
                                <span>👁️</span> View
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="toggleUserStatus(${user.id}, '${status === 'active' ? 'inactive' : 'active'}')">
                                <span>${status === 'active' ? '🔒' : '🔓'}</span> ${status === 'active' ? 'Disable' : 'Enable'}
                            </button>
                            ${role !== 'admin' ? `<button class="btn btn-sm btn-danger" onclick="deleteUser(${user.id})">
                                <span>🗑️</span> Delete
                            </button>` : ''}
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function filterUsers() {
            const searchTerm = document.getElementById('searchUsers').value.toLowerCase();
            const roleFilter = document.getElementById('filterRole').value;
            const statusFilter = document.getElementById('filterStatus').value;
            
            filteredUsers = usersData.filter(user => {
                const matchesSearch = !searchTerm || 
                    (user.username && user.username.toLowerCase().includes(searchTerm)) ||
                    (user.email && user.email.toLowerCase().includes(searchTerm)) ||
                    (user.full_name && user.full_name.toLowerCase().includes(searchTerm));
                    
                const matchesRole = !roleFilter || user.role === roleFilter;
                const matchesStatus = !statusFilter || user.status === statusFilter;
                
                return matchesSearch && matchesRole && matchesStatus;
            });
            
            renderUsers();
        }
        
        function clearFilters() {
            document.getElementById('searchUsers').value = '';
            document.getElementById('filterRole').value = '';
            document.getElementById('filterStatus').value = '';
            filteredUsers = [...usersData];
            renderUsers();
        }
        
        function refreshUsers() {
            loadUsers();
            loadStatistics();
        }
        
        function showCreateModal() {
            document.getElementById('modalTitle').textContent = 'Add User';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('userModal').classList.add('show');
        }
        
        function closeModal() {
            document.getElementById('userModal').classList.remove('show');
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
        
        // Additional functions for user management
        async function editUser(userId) {
            showToast('Edit functionality coming soon', 'info');
        }
        
        async function viewUser(userId) {
            showToast('View functionality coming soon', 'info');
        }
        
        async function toggleUserStatus(userId, newStatus) {
            showToast('Status toggle coming soon', 'info');
        }
        
        async function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user?')) return;
            showToast('Delete functionality coming soon', 'info');
        }
    </script>
</body>
</html>