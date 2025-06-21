<?php
// Admin Users Management Page
require_once __DIR__ . '/bootstrap.php';

// Get database connection
$db = $GLOBALS['db'] ?? null;

// Set correct base path for refactor admin
$basePath = '/refactor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/admin-styles.css">
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
    <div class="admin-layout">
        <?php include 'includes/admin-navigation.php'; ?>
        
        <div class="admin-content">
            <div class="admin-header">
                <div class="container-fluid">
                    <h1><i class="bi bi-palette"></i> Users</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                            <li class="breadcrumb-item active">Users</li>
                        </ol>
                    </nav>
                </div>
            </div>
            
            <div class="main-content">
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
    
    <!-- Permissions Management Modal -->
    <div id="permissionsModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2 class="modal-title">Manage User Permissions</h2>
                <button class="close-btn" onclick="closePermissionsModal()">&times;</button>
            </div>
            <div id="permissionsContent">
                <div class="loading">Loading permissions...</div>
            </div>
        </div>
    </div>
    
    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>
    
    <script>
        const basePath = '<?php echo $basePath; ?>' || '/refactor';
        const apiBasePath = '<?php echo $basePath; ?>' || '/refactor'; // API calls should use same base path
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
                            <div><strong>Roles:</strong> ${user.roles_display || 'No roles assigned'}</div>
                            <div><strong>Permissions:</strong> ${user.permissions ? user.permissions.length : 0} permissions</div>
                        </div>
                        
                        <div class="user-actions">
                            <button class="btn btn-sm btn-primary" onclick="editUser(${user.id})">
                                <span>✏️</span> Edit
                            </button>
                            <button class="btn btn-sm btn-secondary" onclick="viewUser(${user.id})">
                                <span>👁️</span> View
                            </button>
                            <button class="btn btn-sm btn-info" onclick="resetUserPassword(${user.id})">
                                <span>🔑</span> Reset Password
                            </button>
                            <button class="btn btn-sm btn-success" onclick="manageUserPermissions(${user.id})">
                                <span>🛡️</span> Permissions
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
            document.getElementById('userPassword').required = true;
            document.getElementById('userPassword').placeholder = 'Enter password';
            document.getElementById('userActive').checked = true;
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
        
        // User management functions
        async function editUser(userId) {
            try {
                const response = await fetch(`${apiBasePath}/api/users/show?id=${userId}`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    const user = data.data;
                    
                    // Populate form
                    document.getElementById('modalTitle').textContent = 'Edit User';
                    document.getElementById('userId').value = user.id;
                    document.getElementById('userName').value = user.username;
                    document.getElementById('userEmail').value = user.email;
                    document.getElementById('userPassword').required = false;
                    document.getElementById('userPassword').placeholder = 'Leave blank to keep current password';
                    document.getElementById('userRole').value = user.role;
                    document.getElementById('userFullName').value = user.full_name || '';
                    // Split full name into first and last for the display
                    const nameParts = (user.full_name || '').split(' ');
                    // Store original first/last names for editing
                    document.getElementById('userForm').dataset.firstName = user.first_name || '';
                    document.getElementById('userForm').dataset.lastName = user.last_name || '';
                    document.getElementById('userActive').checked = user.status === 'active';
                    document.getElementById('userEmailVerified').checked = user.email_verified == 1;
                    
                    document.getElementById('userModal').classList.add('show');
                } else {
                    showToast(data.message || 'Failed to load user', 'error');
                }
            } catch (error) {
                console.error('Error loading user:', error);
                showToast('Error loading user', 'error');
            }
        }
        
        async function viewUser(userId) {
            try {
                const response = await fetch(`${apiBasePath}/api/users/show?id=${userId}`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    const user = data.data;
                    const lastLogin = user.last_login ? new Date(user.last_login).toLocaleString() : 'Never';
                    const created = new Date(user.created_at).toLocaleString();
                    
                    const details = `
                        <strong>Username:</strong> ${escapeHtml(user.username)}<br>
                        <strong>Email:</strong> ${escapeHtml(user.email)}<br>
                        <strong>Full Name:</strong> ${escapeHtml(user.full_name || 'Not set')}<br>
                        <strong>Role:</strong> ${user.role}<br>
                        <strong>Status:</strong> ${user.status}<br>
                        <strong>Email Verified:</strong> ${user.email_verified ? 'Yes' : 'No'}<br>
                        <strong>Last Login:</strong> ${lastLogin}<br>
                        <strong>Login Attempts:</strong> ${user.login_attempts || 0}<br>
                        <strong>Created:</strong> ${created}<br>
                        <strong>User ID:</strong> ${user.id}
                    `;
                    
                    if (confirm(`User Details:\n\n${details.replace(/<[^>]*>/g, '')}\n\nWould you like to view activity logs?`)) {
                        viewUserActivity(userId);
                    }
                } else {
                    showToast(data.message || 'Failed to load user', 'error');
                }
            } catch (error) {
                console.error('Error loading user:', error);
                showToast('Error loading user', 'error');
            }
        }
        
        async function toggleUserStatus(userId, newStatus) {
            if (!confirm(`Are you sure you want to ${newStatus} this user?`)) return;
            
            try {
                const response = await fetch(`${apiBasePath}/api/users/toggle-status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({ id: userId, status: newStatus })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    loadUsers();
                    loadStatistics();
                } else {
                    showToast(data.message || 'Failed to update user status', 'error');
                }
            } catch (error) {
                console.error('Error updating user status:', error);
                showToast('Error updating user status', 'error');
            }
        }
        
        async function deleteUser(userId) {
            if (!confirm('Are you sure you want to DELETE this user? This action cannot be undone!')) return;
            if (!confirm('This will permanently delete the user and all associated data. Are you absolutely sure?')) return;
            
            try {
                const response = await fetch(`${apiBasePath}/api/users`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({ id: userId })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    loadUsers();
                    loadStatistics();
                } else {
                    showToast(data.message || 'Failed to delete user', 'error');
                }
            } catch (error) {
                console.error('Error deleting user:', error);
                showToast('Error deleting user', 'error');
            }
        }
        
        // Form submission
        document.getElementById('userForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const userId = formData.get('id');
            const isEdit = !!userId;
            
            const fullName = formData.get('full_name') || '';
            const nameParts = fullName.trim().split(' ');
            
            const userData = {
                username: formData.get('username'),
                email: formData.get('email'),
                role: formData.get('role'),
                first_name: nameParts[0] || '',
                last_name: nameParts.slice(1).join(' ') || '',
                status: formData.get('active') ? 'active' : 'inactive',
                email_verified: !!formData.get('email_verified')
            };
            
            if (formData.get('password')) {
                userData.password = formData.get('password');
            }
            
            if (isEdit) {
                userData.id = parseInt(userId);
            }
            
            try {
                const url = isEdit ? `${apiBasePath}/api/users` : `${apiBasePath}/api/users`;
                const method = isEdit ? 'PUT' : 'POST';
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify(userData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message || (isEdit ? 'User updated successfully' : 'User created successfully'), 'success');
                    closeModal();
                    loadUsers();
                    loadStatistics();
                } else {
                    showToast(data.message || 'Operation failed', 'error');
                }
            } catch (error) {
                console.error('Error saving user:', error);
                showToast('Error saving user', 'error');
            }
        });
        
        // Additional functions
        async function resetUserPassword(userId) {
            const newPassword = prompt('Enter new password (minimum 6 characters):');
            if (!newPassword) return;
            
            if (newPassword.length < 6) {
                showToast('Password must be at least 6 characters long', 'error');
                return;
            }
            
            try {
                const response = await fetch(`${apiBasePath}/api/users/reset-password`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'include',
                    body: JSON.stringify({ id: userId, password: newPassword })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                } else {
                    showToast(data.message || 'Failed to reset password', 'error');
                }
            } catch (error) {
                console.error('Error resetting password:', error);
                showToast('Error resetting password', 'error');
            }
        }
        
        async function viewUserActivity(userId) {
            try {
                const response = await fetch(`${apiBasePath}/api/users/activity-logs?user_id=${userId}&limit=10`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success && data.data.length > 0) {
                    let logText = 'Recent Activity:\n\n';
                    data.data.forEach(log => {
                        const date = new Date(log.created_at).toLocaleString();
                        logText += `${date}: ${log.action}\n`;
                    });
                    alert(logText);
                } else {
                    showToast('No activity logs found', 'info');
                }
            } catch (error) {
                console.error('Error loading activity logs:', error);
                showToast('Error loading activity logs', 'error');
            }
        }
        
        async function manageUserPermissions(userId) {
            try {
                document.getElementById('permissionsModal').classList.add('show');
                
                const response = await fetch(`${apiBasePath}/api/users/permissions?user_id=${userId}`, {
                    credentials: 'include'
                });
                const data = await response.json();
                
                if (data.success) {
                    renderPermissionsInterface(userId, data.data);
                } else {
                    showToast(data.message || 'Failed to load permissions', 'error');
                    closePermissionsModal();
                }
            } catch (error) {
                console.error('Error loading permissions:', error);
                showToast('Error loading permissions', 'error');
                closePermissionsModal();
            }
        }
        
        function renderPermissionsInterface(userId, data) {
            const { user_roles, all_roles, user_permissions, all_permissions } = data;
            const userRoleIds = user_roles.map(r => r.id);
            
            let html = `
                <div style="padding: 1rem;">
                    <h3>Role Assignments</h3>
                    <p>Assign roles to give users groups of permissions:</p>
                    <form id="rolesForm" style="margin-bottom: 2rem;">
                        <input type="hidden" name="user_id" value="${userId}">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin: 1rem 0;">
            `;
            
            all_roles.forEach(role => {
                const isChecked = userRoleIds.includes(role.id);
                html += `
                    <label class="checkbox-label" style="display: flex; align-items: flex-start; gap: 0.5rem; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px;">
                        <input type="checkbox" name="role_ids[]" value="${role.id}" ${isChecked ? 'checked' : ''}>
                        <div>
                            <strong>${escapeHtml(role.display_name)}</strong>
                            <div style="font-size: 0.85rem; color: #666;">${escapeHtml(role.description || '')}</div>
                        </div>
                    </label>
                `;
            });
            
            html += `
                        </div>
                        <button type="submit" class="btn btn-primary">Update Roles</button>
                    </form>
                    
                    <h3>Current Permissions</h3>
                    <p>Permissions granted through assigned roles:</p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            `;
            
            // Group permissions by module
            const permissionsByModule = {};
            user_permissions.forEach(perm => {
                // Find the permission details
                for (const module in all_permissions) {
                    const found = all_permissions[module].find(p => p.name === perm);
                    if (found) {
                        if (!permissionsByModule[module]) {
                            permissionsByModule[module] = [];
                        }
                        permissionsByModule[module].push(found);
                        break;
                    }
                }
            });
            
            for (const [module, permissions] of Object.entries(permissionsByModule)) {
                html += `
                    <div style="border: 1px solid #ddd; border-radius: 4px; padding: 1rem;">
                        <h4 style="margin: 0 0 0.5rem 0; color: #333; text-transform: capitalize;">${module}</h4>
                        <ul style="margin: 0; padding-left: 1rem; font-size: 0.9rem;">
                `;
                
                permissions.forEach(perm => {
                    html += `<li>${escapeHtml(perm.display_name)}</li>`;
                });
                
                html += '</ul></div>';
            }
            
            if (Object.keys(permissionsByModule).length === 0) {
                html += '<div style="text-align: center; color: #666; padding: 2rem;">No permissions assigned</div>';
            }
            
            html += `
                    </div>
                    <div style="margin-top: 2rem; text-align: right;">
                        <button class="btn btn-secondary" onclick="closePermissionsModal()">Close</button>
                    </div>
                </div>
            `;
            
            document.getElementById('permissionsContent').innerHTML = html;
            
            // Handle form submission
            document.getElementById('rolesForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const roleIds = formData.getAll('role_ids[]').map(id => parseInt(id));
                
                try {
                    const response = await fetch(`${apiBasePath}/api/users/roles`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            user_id: userId,
                            role_ids: roleIds
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showToast(result.message, 'success');
                        closePermissionsModal();
                        loadUsers(); // Refresh user list
                    } else {
                        showToast(result.message || 'Failed to update roles', 'error');
                    }
                } catch (error) {
                    console.error('Error updating roles:', error);
                    showToast('Error updating roles', 'error');
                }
            });
        }
        
        function closePermissionsModal() {
            document.getElementById('permissionsModal').classList.remove('show');
        }
    </script>
</body>
</html>