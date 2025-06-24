<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Application\Services\UserService;
use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use Exception;

/**
 * Admin controller for user management
 */
class AdminUsersController extends BaseController
{
    private UserService $userService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        $this->userService = $container->resolve(UserService::class);
    }

    /**
     * Show users management page
     */
    public function index(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        if ($basePath === '/') {
            $basePath = '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo $this->renderUsersPage($basePath);
    }

    /**
     * Get paginated list of users (API)
     */
    public function getUsers(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();
            $page = max(1, (int)($input['page'] ?? 1));
            $perPage = min(100, max(10, (int)($input['per_page'] ?? 20)));
            
            $filters = [];
            if (!empty($input['search'])) {
                $filters['search'] = $input['search'];
            }
            if (!empty($input['role'])) {
                $filters['role'] = $input['role'];
            }
            if (!empty($input['status'])) {
                $filters['status'] = $input['status'];
            }

            $result = $this->userService->getUsersPaginated($page, $perPage, $filters);

            $this->successResponse([
                'users' => array_map(fn($user) => $user->toArray(), $result->getItems()),
                'pagination' => [
                    'total' => $result->getTotal(),
                    'page' => $result->getPage(),
                    'per_page' => $result->getPerPage(),
                    'total_pages' => $result->getTotalPages()
                ]
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load users: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get single user details
     */
    public function getUser(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $userId = (int)($_GET['id'] ?? 0);
            
            if ($userId <= 0) {
                $this->errorResponse('Invalid user ID');
                return;
            }

            $user = $this->userService->getUserById($userId);
            
            if (!$user) {
                $this->errorResponse('User not found', 404);
                return;
            }

            $this->successResponse([
                'user' => $user->toArray()
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create new user
     */
    public function createUser(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = $this->getInput();

            $result = $this->userService->createUser([
                'username' => $input['username'] ?? '',
                'email' => $input['email'] ?? '',
                'password' => $input['password'] ?? '',
                'role' => $input['role'] ?? 'user',
                'status' => $input['status'] ?? 'active'
            ]);

            if ($result['success']) {
                $this->successResponse([
                    'message' => 'User created successfully',
                    'user' => $result['user']->toArray()
                ]);
            } else {
                $this->errorResponse($result['error'] ?? 'Failed to create user');
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to create user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update user
     */
    public function updateUser(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $userId = (int)($_GET['id'] ?? 0);
            
            if ($userId <= 0) {
                $this->errorResponse('Invalid user ID');
                return;
            }

            $input = $this->getInput();

            $result = $this->userService->updateUser($userId, [
                'username' => $input['username'] ?? null,
                'email' => $input['email'] ?? null,
                'role' => $input['role'] ?? null,
                'status' => $input['status'] ?? null
            ]);

            if ($result['success']) {
                $this->successResponse([
                    'message' => 'User updated successfully',
                    'user' => $result['user']->toArray()
                ]);
            } else {
                $this->errorResponse($result['error'] ?? 'Failed to update user');
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to update user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete user
     */
    public function deleteUser(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $userId = (int)($_GET['id'] ?? 0);
            
            if ($userId <= 0) {
                $this->errorResponse('Invalid user ID');
                return;
            }

            // Prevent deleting self
            if ($userId === $_SESSION['admin_user_id']) {
                $this->errorResponse('Cannot delete your own account');
                return;
            }

            $result = $this->userService->deleteUser($userId);

            if ($result['success']) {
                $this->successResponse([
                    'message' => 'User deleted successfully'
                ]);
            } else {
                $this->errorResponse($result['error'] ?? 'Failed to delete user');
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to delete user: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $userId = (int)($_GET['id'] ?? 0);
            
            if ($userId <= 0) {
                $this->errorResponse('Invalid user ID');
                return;
            }

            $input = $this->getInput();
            $newPassword = $input['password'] ?? '';

            if (empty($newPassword)) {
                $this->errorResponse('Password is required');
                return;
            }

            $result = $this->userService->resetUserPassword($userId, $newPassword);

            if ($result['success']) {
                $this->successResponse([
                    'message' => 'Password reset successfully'
                ]);
            } else {
                $this->errorResponse($result['error'] ?? 'Failed to reset password');
            }

        } catch (Exception $e) {
            $this->errorResponse('Failed to reset password: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $stats = $this->userService->getUserStatistics();

            $this->successResponse([
                'statistics' => $stats
            ]);

        } catch (Exception $e) {
            $this->errorResponse('Failed to load statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Render users management page
     */
    private function renderUsersPage(string $basePath): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - YFEvents Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .admin-header {
            background: linear-gradient(135deg, #dc3545 0%, #6f42c1 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        .user-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        .role-badge {
            font-size: 0.875rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
        }
        .role-admin {
            background: #dc3545;
            color: white;
        }
        .role-editor {
            background: #fd7e14;
            color: white;
        }
        .role-user {
            background: #6c757d;
            color: white;
        }
        .status-active {
            color: #198754;
        }
        .status-inactive {
            color: #dc3545;
        }
        .action-buttons {
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">User Management</h1>
                <a href="{$basePath}/admin/dashboard" class="btn btn-light">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Controls -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Search Users</label>
                        <input type="text" class="form-control" id="search" placeholder="Search by name or email...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Role</label>
                        <select class="form-select" id="role-filter">
                            <option value="">All Roles</option>
                            <option value="admin">Admin</option>
                            <option value="editor">Editor</option>
                            <option value="user">User</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="status-filter">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-primary" onclick="showCreateUserModal()">
                            <i class="bi bi-plus-circle"></i> Add New User
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users List -->
        <div id="users-container">
            <div class="text-center p-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <nav id="pagination-container"></nav>
    </div>

    <!-- Create/Edit User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalTitle">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="userForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" required>
                        </div>
                        <div class="mb-3" id="password-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password">
                            <small class="text-muted">Leave blank to keep existing password</small>
                        </div>
                        <div class="mb-3">
                            <label for="user-role" class="form-label">Role</label>
                            <select class="form-select" id="user-role" required>
                                <option value="user">User</option>
                                <option value="editor">Editor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="user-status" class="form-label">Status</label>
                            <select class="form-select" id="user-status" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this user?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete User</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const basePath = '{$basePath}';
        let currentPage = 1;
        let currentUserId = null;
        let isEditMode = false;

        // Load users on page load
        document.addEventListener('DOMContentLoaded', () => {
            loadUsers();
            
            // Bind search and filters
            document.getElementById('search').addEventListener('input', debounce(loadUsers, 300));
            document.getElementById('role-filter').addEventListener('change', loadUsers);
            document.getElementById('status-filter').addEventListener('change', loadUsers);
            
            // Bind form submit
            document.getElementById('userForm').addEventListener('submit', handleUserSubmit);
            document.getElementById('confirmDelete').addEventListener('click', handleDelete);
        });

        async function loadUsers(page = 1) {
            currentPage = page;
            const search = document.getElementById('search').value;
            const role = document.getElementById('role-filter').value;
            const status = document.getElementById('status-filter').value;

            const params = new URLSearchParams({
                page: page,
                per_page: 20,
                ...(search && { search }),
                ...(role && { role }),
                ...(status && { status })
            });

            try {
                const response = await fetch(`\${basePath}/admin/users/list?\${params}`);
                const data = await response.json();

                if (data.success) {
                    renderUsers(data.users);
                    renderPagination(data.pagination);
                } else {
                    showError(data.error || 'Failed to load users');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        function renderUsers(users) {
            const container = document.getElementById('users-container');
            
            if (users.length === 0) {
                container.innerHTML = '<div class="alert alert-info">No users found</div>';
                return;
            }

            container.innerHTML = users.map(user => `
                <div class="user-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar me-3">
                                \${user.username.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <h5 class="mb-1">\${escapeHtml(user.username)}</h5>
                                <p class="mb-1 text-muted">\${escapeHtml(user.email)}</p>
                                <div>
                                    <span class="role-badge role-\${user.role}">\${user.role}</span>
                                    <span class="ms-2 status-\${user.status}">
                                        <i class="bi bi-circle-fill"></i> \${user.status}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="action-buttons d-flex">
                            <button class="btn btn-sm btn-outline-primary" onclick="editUser(\${user.id})">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(\${user.id})">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function renderPagination(pagination) {
            const container = document.getElementById('pagination-container');
            const totalPages = pagination.total_pages;
            
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '<ul class="pagination justify-content-center">';
            
            // Previous button
            html += `<li class="page-item \${pagination.page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadUsers(\${pagination.page - 1}); return false;">Previous</a>
            </li>`;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= pagination.page - 2 && i <= pagination.page + 2)) {
                    html += `<li class="page-item \${i === pagination.page ? 'active' : ''}">
                        <a class="page-link" href="#" onclick="loadUsers(\${i}); return false;">\${i}</a>
                    </li>`;
                } else if (i === pagination.page - 3 || i === pagination.page + 3) {
                    html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            // Next button
            html += `<li class="page-item \${pagination.page === totalPages ? 'disabled' : ''}">
                <a class="page-link" href="#" onclick="loadUsers(\${pagination.page + 1}); return false;">Next</a>
            </li>`;
            
            html += '</ul>';
            container.innerHTML = html;
        }

        function showCreateUserModal() {
            isEditMode = false;
            currentUserId = null;
            document.getElementById('userModalTitle').textContent = 'Create New User';
            document.getElementById('userForm').reset();
            document.getElementById('password').required = true;
            document.getElementById('password-group').querySelector('small').style.display = 'none';
            
            const modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
        }

        async function editUser(userId) {
            try {
                const response = await fetch(`\${basePath}/admin/users/\${userId}`);
                const data = await response.json();

                if (data.success) {
                    isEditMode = true;
                    currentUserId = userId;
                    
                    document.getElementById('userModalTitle').textContent = 'Edit User';
                    document.getElementById('username').value = data.user.username;
                    document.getElementById('email').value = data.user.email;
                    document.getElementById('user-role').value = data.user.role;
                    document.getElementById('user-status').value = data.user.status;
                    document.getElementById('password').required = false;
                    document.getElementById('password').value = '';
                    document.getElementById('password-group').querySelector('small').style.display = 'block';
                    
                    const modal = new bootstrap.Modal(document.getElementById('userModal'));
                    modal.show();
                } else {
                    showError(data.error || 'Failed to load user');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        async function handleUserSubmit(e) {
            e.preventDefault();

            const userData = {
                username: document.getElementById('username').value,
                email: document.getElementById('email').value,
                role: document.getElementById('user-role').value,
                status: document.getElementById('user-status').value
            };

            const password = document.getElementById('password').value;
            if (password || !isEditMode) {
                userData.password = password;
            }

            try {
                const url = isEditMode 
                    ? `\${basePath}/admin/users/\${currentUserId}/update`
                    : `\${basePath}/admin/users/create`;
                    
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(userData)
                });

                const data = await response.json();

                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('userModal')).hide();
                    showSuccess(data.message);
                    loadUsers(currentPage);
                } else {
                    showError(data.error || 'Failed to save user');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        function confirmDelete(userId) {
            currentUserId = userId;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        async function handleDelete() {
            try {
                const response = await fetch(`\${basePath}/admin/users/\${currentUserId}/delete`, {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
                    showSuccess('User deleted successfully');
                    loadUsers(currentPage);
                } else {
                    showError(data.error || 'Failed to delete user');
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        // Utility functions
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showSuccess(message) {
            // TODO: Implement toast notifications
            alert(message);
        }

        function showError(message) {
            // TODO: Implement toast notifications
            alert('Error: ' + message);
        }
    </script>
</body>
</html>
HTML;
    }
}