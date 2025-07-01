<?php

declare(strict_types=1);

namespace YFEvents\Presentation\Http\Controllers;

use YFEvents\Infrastructure\Container\ContainerInterface;
use YFEvents\Infrastructure\Config\ConfigInterface;
use YFEvents\Infrastructure\Services\PermissionService;
use PDO;
use Exception;

/**
 * User management controller for admin interface
 */
class UserController extends BaseController
{
    private PDO $pdo;
    private PermissionService $permissionService;

    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        parent::__construct($container, $config);
        
        // Get database connection
        $dbConfig = $config->get('database');
        $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        
        // Initialize permission service
        $this->permissionService = new PermissionService($this->pdo);
    }

    /**
     * Get all users with pagination and filters
     */
    public function index(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(10, min(100, (int)($_GET['limit'] ?? 25)));
            $offset = ($page - 1) * $limit;
            
            $search = $_GET['search'] ?? '';
            $role = $_GET['role'] ?? '';
            $status = $_GET['status'] ?? '';
            
            // Build WHERE clause
            $where = [];
            $params = [];
            
            if (!empty($search)) {
                $where[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
                $searchTerm = "%{$search}%";
                $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            }
            
            if (!empty($role)) {
                $where[] = "role = ?";
                $params[] = $role;
            }
            
            if (!empty($status)) {
                $where[] = "status = ?";
                $params[] = $status;
            }
            
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM users {$whereClause}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get users with their roles
            $sql = "
                SELECT 
                    u.id, u.username, u.email, u.first_name, u.last_name, u.role, u.status, 
                    u.email_verified, u.last_login, u.login_attempts, u.created_at, u.updated_at,
                    GROUP_CONCAT(r.display_name) as roles_display
                FROM users u
                LEFT JOIN yfa_user_roles ur ON u.id = ur.user_id
                LEFT JOIN yfa_roles r ON ur.role_id = r.id
                {$whereClause}
                GROUP BY u.id
                ORDER BY u.created_at DESC 
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, [$limit, $offset]));
            $users = $stmt->fetchAll();
            
            // Add computed fields and detailed roles
            foreach ($users as &$user) {
                $user['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                if (empty($user['full_name'])) {
                    $user['full_name'] = null;
                }
                
                // Get detailed roles for this user
                $user['roles'] = $this->permissionService->getUserRoles($user['id']);
                $user['permissions'] = $this->permissionService->getUserPermissions($user['id']);
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $users,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error loading users: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load users'
            ], 500);
        }
    }

    /**
     * Get user statistics
     */
    public function statistics(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $sql = "
                SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
                    SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned_users,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
                    SUM(CASE WHEN role = 'moderator' THEN 1 ELSE 0 END) as moderator_users,
                    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as regular_users,
                    SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified_users,
                    SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_logins,
                    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d
                FROM users
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch();
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'statistics' => $stats
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error loading user statistics: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load statistics'
            ], 500);
        }
    }

    /**
     * Get single user details
     */
    public function show(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $userId = (int)($_GET['id'] ?? 0);
        if (!$userId) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'User ID is required'
            ], 400);
            return;
        }

        try {
            $sql = "
                SELECT 
                    id, username, email, first_name, last_name, role, status, 
                    email_verified, verification_token, last_login, login_attempts, 
                    locked_until, created_at, updated_at
                FROM users 
                WHERE id = ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
                return;
            }
            
            // Add computed fields
            $user['full_name'] = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
            if (empty($user['full_name'])) {
                $user['full_name'] = null;
            }
            
            // Remove sensitive data
            unset($user['verification_token']);
            
            $this->jsonResponse([
                'success' => true,
                'data' => $user
            ]);
            
        } catch (Exception $e) {
            error_log("Error loading user: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load user'
            ], 500);
        }
    }

    /**
     * Create new user
     */
    public function store(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $required = ['username', 'email', 'password', 'role'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => "Field '{$field}' is required"
                    ], 400);
                    return;
                }
            }
            
            // Validate email format
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid email format'
                ], 400);
                return;
            }
            
            // Validate role
            $validRoles = ['admin', 'moderator', 'user'];
            if (!in_array($input['role'], $validRoles)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid role. Must be admin, moderator, or user'
                ], 400);
                return;
            }
            
            // Validate password strength
            if (strlen($input['password']) < 6) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Password must be at least 6 characters long'
                ], 400);
                return;
            }
            
            // Check for existing username/email
            $checkSql = "SELECT id FROM users WHERE username = ? OR email = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$input['username'], $input['email']]);
            if ($checkStmt->fetch()) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Username or email already exists'
                ], 400);
                return;
            }
            
            // Create user
            $sql = "
                INSERT INTO users (
                    username, email, password_hash, first_name, last_name, 
                    role, status, email_verified, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                $input['username'],
                $input['email'],
                password_hash($input['password'], PASSWORD_DEFAULT),
                $input['first_name'] ?? null,
                $input['last_name'] ?? null,
                $input['role'],
                $input['status'] ?? 'active',
                $input['email_verified'] ?? false ? 1 : 0
            ]);
            
            if ($success) {
                $userId = $this->pdo->lastInsertId();
                
                // Log activity
                $this->logActivity('user_created', $userId, [
                    'username' => $input['username'],
                    'role' => $input['role']
                ]);
                
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'User created successfully',
                    'data' => ['id' => $userId]
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to create user'
                ], 500);
            }
            
        } catch (Exception $e) {
            error_log("Error creating user: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create user'
            ], 500);
        }
    }

    /**
     * Update existing user
     */
    public function update(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = (int)($input['id'] ?? 0);
            
            if (!$userId) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
                return;
            }
            
            // Check if user exists
            $checkSql = "SELECT id, username, email FROM users WHERE id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$userId]);
            $existingUser = $checkStmt->fetch();
            
            if (!$existingUser) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
                return;
            }
            
            // Build update query dynamically
            $updateFields = [];
            $params = [];
            
            // Username
            if (isset($input['username']) && $input['username'] !== $existingUser['username']) {
                // Check if new username exists
                $usernameSql = "SELECT id FROM users WHERE username = ? AND id != ?";
                $usernameStmt = $this->pdo->prepare($usernameSql);
                $usernameStmt->execute([$input['username'], $userId]);
                if ($usernameStmt->fetch()) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => 'Username already exists'
                    ], 400);
                    return;
                }
                $updateFields[] = "username = ?";
                $params[] = $input['username'];
            }
            
            // Email
            if (isset($input['email']) && $input['email'] !== $existingUser['email']) {
                if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => 'Invalid email format'
                    ], 400);
                    return;
                }
                
                // Check if new email exists
                $emailSql = "SELECT id FROM users WHERE email = ? AND id != ?";
                $emailStmt = $this->pdo->prepare($emailSql);
                $emailStmt->execute([$input['email'], $userId]);
                if ($emailStmt->fetch()) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => 'Email already exists'
                    ], 400);
                    return;
                }
                $updateFields[] = "email = ?";
                $params[] = $input['email'];
            }
            
            // Other fields
            $allowedFields = [
                'first_name', 'last_name', 'role', 'status', 'email_verified'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    if ($field === 'role') {
                        $validRoles = ['admin', 'moderator', 'user'];
                        if (!in_array($input[$field], $validRoles)) {
                            $this->jsonResponse([
                                'success' => false,
                                'message' => 'Invalid role'
                            ], 400);
                            return;
                        }
                    }
                    
                    if ($field === 'status') {
                        $validStatuses = ['active', 'inactive', 'banned'];
                        if (!in_array($input[$field], $validStatuses)) {
                            $this->jsonResponse([
                                'success' => false,
                                'message' => 'Invalid status'
                            ], 400);
                            return;
                        }
                    }
                    
                    $updateFields[] = "{$field} = ?";
                    $params[] = $field === 'email_verified' ? ($input[$field] ? 1 : 0) : $input[$field];
                }
            }
            
            // Password update
            if (!empty($input['password'])) {
                if (strlen($input['password']) < 6) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => 'Password must be at least 6 characters long'
                    ], 400);
                    return;
                }
                $updateFields[] = "password_hash = ?";
                $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updateFields)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'No fields to update'
                ], 400);
                return;
            }
            
            // Update user
            $updateFields[] = "updated_at = NOW()";
            $params[] = $userId;
            
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute($params);
            
            if ($success) {
                // Log activity
                $this->logActivity('user_updated', $userId, $input);
                
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'User updated successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update user'
                ], 500);
            }
            
        } catch (Exception $e) {
            error_log("Error updating user: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update user'
            ], 500);
        }
    }

    /**
     * Delete user
     */
    public function delete(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = (int)($input['id'] ?? 0);
            
            if (!$userId) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
                return;
            }
            
            // Check if user exists and get info for logging
            $checkSql = "SELECT username, role FROM users WHERE id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$userId]);
            $user = $checkStmt->fetch();
            
            if (!$user) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
                return;
            }
            
            // Prevent deleting the last admin
            if ($user['role'] === 'admin') {
                $adminCountSql = "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'";
                $adminCountStmt = $this->pdo->prepare($adminCountSql);
                $adminCountStmt->execute();
                $adminCount = $adminCountStmt->fetch()['count'];
                
                if ($adminCount <= 1) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => 'Cannot delete the last admin user'
                    ], 400);
                    return;
                }
            }
            
            // Delete user
            $sql = "DELETE FROM users WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$userId]);
            
            if ($success) {
                // Log activity
                $this->logActivity('user_deleted', $userId, [
                    'username' => $user['username'],
                    'role' => $user['role']
                ]);
                
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'User deleted successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to delete user'
                ], 500);
            }
            
        } catch (Exception $e) {
            error_log("Error deleting user: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to delete user'
            ], 500);
        }
    }

    /**
     * Toggle user status (active/inactive)
     */
    public function toggleStatus(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = (int)($input['id'] ?? 0);
            $newStatus = $input['status'] ?? '';
            
            if (!$userId) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
                return;
            }
            
            $validStatuses = ['active', 'inactive', 'banned'];
            if (!in_array($newStatus, $validStatuses)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Invalid status'
                ], 400);
                return;
            }
            
            // Check if user exists
            $checkSql = "SELECT username, role, status FROM users WHERE id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$userId]);
            $user = $checkStmt->fetch();
            
            if (!$user) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
                return;
            }
            
            // Prevent deactivating the last admin
            if ($user['role'] === 'admin' && $newStatus !== 'active') {
                $adminCountSql = "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status = 'active'";
                $adminCountStmt = $this->pdo->prepare($adminCountSql);
                $adminCountStmt->execute();
                $adminCount = $adminCountStmt->fetch()['count'];
                
                if ($adminCount <= 1) {
                    $this->jsonResponse([
                        'success' => false,
                        'message' => 'Cannot deactivate the last admin user'
                    ], 400);
                    return;
                }
            }
            
            // Update status
            $sql = "UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$newStatus, $userId]);
            
            if ($success) {
                // Log activity
                $this->logActivity('user_status_changed', $userId, [
                    'username' => $user['username'],
                    'old_status' => $user['status'],
                    'new_status' => $newStatus
                ]);
                
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'User status updated successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update user status'
                ], 500);
            }
            
        } catch (Exception $e) {
            error_log("Error updating user status: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update user status'
            ], 500);
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
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = (int)($input['id'] ?? 0);
            $newPassword = $input['password'] ?? '';
            
            if (!$userId) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
                return;
            }
            
            if (strlen($newPassword) < 6) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Password must be at least 6 characters long'
                ], 400);
                return;
            }
            
            // Check if user exists
            $checkSql = "SELECT username FROM users WHERE id = ?";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$userId]);
            $user = $checkStmt->fetch();
            
            if (!$user) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
                return;
            }
            
            // Update password and clear reset tokens
            $sql = "
                UPDATE users 
                SET password_hash = ?, reset_token = NULL, reset_expires = NULL, 
                    login_attempts = 0, locked_until = NULL, updated_at = NOW() 
                WHERE id = ?
            ";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                password_hash($newPassword, PASSWORD_DEFAULT),
                $userId
            ]);
            
            if ($success) {
                // Log activity
                $this->logActivity('user_password_reset', $userId, [
                    'username' => $user['username']
                ]);
                
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Password reset successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to reset password'
                ], 500);
            }
            
        } catch (Exception $e) {
            error_log("Error resetting password: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to reset password'
            ], 500);
        }
    }

    /**
     * Get user activity logs
     */
    public function getActivityLogs(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $userId = (int)($_GET['user_id'] ?? 0);
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(10, min(100, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            
            $whereClause = $userId ? 'WHERE user_id = ?' : '';
            $params = $userId ? [$userId] : [];
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM user_activity_logs {$whereClause}";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch()['total'];
            
            // Get logs
            $sql = "
                SELECT 
                    ual.*, 
                    u.username, u.role
                FROM user_activity_logs ual
                LEFT JOIN users u ON ual.user_id = u.id
                {$whereClause}
                ORDER BY ual.created_at DESC 
                LIMIT ? OFFSET ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, [$limit, $offset]));
            $logs = $stmt->fetchAll();
            
            // Decode JSON data
            foreach ($logs as &$log) {
                $log['data'] = $log['data'] ? json_decode($log['data'], true) : null;
            }
            
            $this->jsonResponse([
                'success' => true,
                'data' => $logs,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error loading activity logs: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load activity logs'
            ], 500);
        }
    }

    /**
     * Check if current user is admin
     */
    protected function requireAdmin(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
            return false;
        }
        
        return true;
    }

    /**
     * Log user activity
     */
    protected function logActivity(string $action, int $userId, array $data = []): void
    {
        try {
            // Check if activity log table exists
            $tableCheckSql = "SHOW TABLES LIKE 'user_activity_logs'";
            $tableCheckStmt = $this->pdo->prepare($tableCheckSql);
            $tableCheckStmt->execute();
            
            if (!$tableCheckStmt->fetch()) {
                // Create table if it doesn't exist
                $createTableSql = "
                    CREATE TABLE user_activity_logs (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT,
                        action VARCHAR(100) NOT NULL,
                        data JSON,
                        ip_address VARCHAR(45),
                        user_agent TEXT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_user_id (user_id),
                        INDEX idx_action (action),
                        INDEX idx_created_at (created_at)
                    )
                ";
                $this->pdo->exec($createTableSql);
            }
            
            $sql = "
                INSERT INTO user_activity_logs (user_id, action, data, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $userId,
                $action,
                json_encode($data),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }

    /**
     * Get all available roles
     */
    public function getRoles(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $roles = $this->permissionService->getAllRoles();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $roles
            ]);
            
        } catch (Exception $e) {
            error_log("Error loading roles: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load roles'
            ], 500);
        }
    }

    /**
     * Get all available permissions
     */
    public function getPermissions(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $permissions = $this->permissionService->getPermissionsByModule();
            
            $this->jsonResponse([
                'success' => true,
                'data' => $permissions
            ]);
            
        } catch (Exception $e) {
            error_log("Error loading permissions: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load permissions'
            ], 500);
        }
    }

    /**
     * Update user roles
     */
    public function updateUserRoles(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = (int)($input['user_id'] ?? 0);
            $roleIds = $input['role_ids'] ?? [];
            
            if (!$userId) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
                return;
            }
            
            // Validate role IDs
            $roleIds = array_map('intval', array_filter($roleIds));
            
            // Sync user roles
            $success = $this->permissionService->syncUserRoles($userId, $roleIds);
            
            if ($success) {
                // Log activity
                $this->logActivity('user_roles_updated', $userId, [
                    'role_ids' => $roleIds
                ]);
                
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'User roles updated successfully'
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update user roles'
                ], 500);
            }
            
        } catch (Exception $e) {
            error_log("Error updating user roles: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to update user roles'
            ], 500);
        }
    }

    /**
     * Get user permissions and roles
     */
    public function getUserPermissions(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $userId = (int)($_GET['user_id'] ?? 0);
        if (!$userId) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'User ID is required'
            ], 400);
            return;
        }

        try {
            $roles = $this->permissionService->getUserRoles($userId);
            $permissions = $this->permissionService->getUserPermissions($userId);
            $allRoles = $this->permissionService->getAllRoles();
            $allPermissions = $this->permissionService->getPermissionsByModule();
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'user_roles' => $roles,
                    'user_permissions' => $permissions,
                    'all_roles' => $allRoles,
                    'all_permissions' => $allPermissions
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error loading user permissions: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load user permissions'
            ], 500);
        }
    }

    /**
     * Check specific permission for user
     */
    public function checkUserPermission(): void
    {
        if (!$this->requireAdmin()) {
            return;
        }

        $userId = (int)($_GET['user_id'] ?? 0);
        $permission = $_GET['permission'] ?? '';
        
        if (!$userId || !$permission) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'User ID and permission are required'
            ], 400);
            return;
        }

        try {
            $hasPermission = $this->permissionService->userCan($userId, $permission);
            
            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'has_permission' => $hasPermission,
                    'user_id' => $userId,
                    'permission' => $permission
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Error checking user permission: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to check permission'
            ], 500);
        }
    }
}