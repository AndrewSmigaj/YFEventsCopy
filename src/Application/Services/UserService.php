<?php

declare(strict_types=1);

namespace YFEvents\Application\Services;

use YFEvents\Domain\Users\User;
use YFEvents\Domain\Users\UserRepositoryInterface;
use YFEvents\Infrastructure\Database\Connection;
use YFEvents\Application\DTOs\PaginatedResult;
use DateTime;

class UserService
{
    private const AVAILABLE_ROLES = ['admin', 'editor', 'user'];
    
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly Connection $connection
    ) {}

    /**
     * Get paginated list of users
     */
    public function getUsersPaginated(int $page, int $perPage, array $filters = []): PaginatedResult
    {
        $offset = ($page - 1) * $perPage;
        
        $users = $this->userRepository->findWithFilters($filters, $perPage, $offset);
        $total = $this->userRepository->countWithFilters($filters);
        
        return new PaginatedResult(
            items: $users,
            total: $total,
            page: $page,
            perPage: $perPage
        );
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id): ?User
    {
        return $this->userRepository->findById($id);
    }

    /**
     * Create a new user
     */
    public function createUser(array $data): User
    {
        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Set defaults
        $data['status'] = $data['status'] ?? 'active';
        $data['created_at'] = new DateTime();
        
        $user = User::fromArray($data);
        
        return $this->userRepository->save($user);
    }

    /**
     * Update existing user
     */
    public function updateUser(int $id, array $data): User
    {
        $user = $this->userRepository->findById($id);
        
        if (!$user) {
            throw new \RuntimeException("User not found: $id");
        }

        // Update password if provided
        if (!empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']);
        }

        $updatedUser = $user->update($data);
        
        return $this->userRepository->save($updatedUser);
    }

    /**
     * Delete user
     */
    public function deleteUser(int $id): void
    {
        $user = $this->userRepository->findById($id);
        
        if (!$user) {
            throw new \RuntimeException("User not found: $id");
        }

        // Check if user can be deleted
        if ($this->hasActiveContent($id)) {
            throw new \RuntimeException("Cannot delete user with active content");
        }

        $this->userRepository->delete($id);
    }

    /**
     * Suspend user account
     */
    public function suspendUser(int $id, string $reason, ?int $durationDays = null): void
    {
        $user = $this->userRepository->findById($id);
        
        if (!$user) {
            throw new \RuntimeException("User not found: $id");
        }

        $suspendedUntil = null;
        if ($durationDays !== null) {
            $suspendedUntil = new DateTime();
            $suspendedUntil->modify("+{$durationDays} days");
        }

        $updatedUser = $user->suspend($reason, $suspendedUntil);
        $this->userRepository->save($updatedUser);
        
        // Log suspension
        $this->logUserAction($id, 'suspended', [
            'reason' => $reason,
            'duration_days' => $durationDays
        ]);
    }

    /**
     * Reactivate suspended user
     */
    public function reactivateUser(int $id): void
    {
        $user = $this->userRepository->findById($id);
        
        if (!$user) {
            throw new \RuntimeException("User not found: $id");
        }

        $updatedUser = $user->reactivate();
        $this->userRepository->save($updatedUser);
        
        // Log reactivation
        $this->logUserAction($id, 'reactivated');
    }

    /**
     * Get user activity log
     */
    public function getUserActivity(int $userId, int $limit = 50): array
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "SELECT * FROM user_activity_logs 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC 
             LIMIT :limit"
        );
        
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get paginated user activity
     */
    public function getUserActivityPaginated(int $userId, int $page, int $perPage): PaginatedResult
    {
        $offset = ($page - 1) * $perPage;
        $pdo = $this->connection->getPdo();
        
        // Get activity
        $stmt = $pdo->prepare(
            "SELECT * FROM user_activity_logs 
             WHERE user_id = :user_id 
             ORDER BY created_at DESC 
             LIMIT :limit OFFSET :offset"
        );
        
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Get total count
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM user_activity_logs WHERE user_id = :user_id"
        );
        $countStmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();
        
        return new PaginatedResult(
            items: $items,
            total: $total,
            page: $page,
            perPage: $perPage
        );
    }

    /**
     * Get user permissions
     */
    public function getUserPermissions(int $userId): array
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "SELECT p.* FROM permissions p
             JOIN user_permissions up ON p.id = up.permission_id
             WHERE up.user_id = :user_id"
        );
        
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Update user permissions
     */
    public function updateUserPermissions(int $userId, array $permissionIds): void
    {
        $pdo = $this->connection->getPdo();
        
        $pdo->beginTransaction();
        try {
            // Remove existing permissions
            $deleteStmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = :user_id");
            $deleteStmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
            $deleteStmt->execute();
            
            // Add new permissions
            if (!empty($permissionIds)) {
                $insertStmt = $pdo->prepare(
                    "INSERT INTO user_permissions (user_id, permission_id) VALUES (:user_id, :permission_id)"
                );
                
                foreach ($permissionIds as $permissionId) {
                    $insertStmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
                    $insertStmt->bindValue(':permission_id', $permissionId, \PDO::PARAM_INT);
                    $insertStmt->execute();
                }
            }
            
            $pdo->commit();
            
            // Log permission change
            $this->logUserAction($userId, 'permissions_updated', [
                'permissions' => $permissionIds
            ]);
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Reset user password
     */
    public function resetUserPassword(int $userId): string
    {
        $user = $this->userRepository->findById($userId);
        
        if (!$user) {
            throw new \RuntimeException("User not found: $userId");
        }

        // Generate temporary password
        $temporaryPassword = $this->generateTemporaryPassword();
        $hashedPassword = password_hash($temporaryPassword, PASSWORD_DEFAULT);
        
        $updatedUser = $user->update([
            'password' => $hashedPassword,
            'password_reset_required' => true
        ]);
        
        $this->userRepository->save($updatedUser);
        
        // Log password reset
        $this->logUserAction($userId, 'password_reset');
        
        return $temporaryPassword;
    }

    /**
     * Export users data
     */
    public function exportUsers(array $filters, string $format = 'csv'): array
    {
        $users = $this->userRepository->findWithFilters($filters);
        
        switch ($format) {
            case 'csv':
                return $this->exportToCsv($users);
            case 'json':
                return $this->exportToJson($users);
            default:
                throw new \InvalidArgumentException("Unsupported export format: $format");
        }
    }

    /**
     * Bulk activate users
     */
    public function bulkActivateUsers(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $user = $this->userRepository->findById($userId);
            if ($user && $user->getStatus() !== 'active') {
                $updatedUser = $user->update(['status' => 'active']);
                $this->userRepository->save($updatedUser);
            }
        }
    }

    /**
     * Bulk deactivate users
     */
    public function bulkDeactivateUsers(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $user = $this->userRepository->findById($userId);
            if ($user && $user->getStatus() === 'active') {
                $updatedUser = $user->update(['status' => 'inactive']);
                $this->userRepository->save($updatedUser);
            }
        }
    }

    /**
     * Bulk delete users
     */
    public function bulkDeleteUsers(array $userIds): void
    {
        foreach ($userIds as $userId) {
            try {
                $this->deleteUser($userId);
            } catch (\Exception $e) {
                // Log error but continue with other deletions
                error_log("Failed to delete user $userId: " . $e->getMessage());
            }
        }
    }

    /**
     * Bulk change user roles
     */
    public function bulkChangeRole(array $userIds, string $role): void
    {
        if (!in_array($role, self::AVAILABLE_ROLES)) {
            throw new \InvalidArgumentException("Invalid role: $role");
        }

        foreach ($userIds as $userId) {
            $user = $this->userRepository->findById($userId);
            if ($user) {
                $updatedUser = $user->update(['role' => $role]);
                $this->userRepository->save($updatedUser);
            }
        }
    }

    /**
     * Get available user roles
     */
    public function getAvailableRoles(): array
    {
        return self::AVAILABLE_ROLES;
    }

    /**
     * Impersonate user
     */
    public function impersonateUser(int $targetUserId, int $adminUserId): void
    {
        $targetUser = $this->userRepository->findById($targetUserId);
        
        if (!$targetUser) {
            throw new \RuntimeException("User not found: $targetUserId");
        }

        // Store impersonation info in session
        $_SESSION['impersonation'] = [
            'admin_id' => $adminUserId,
            'target_id' => $targetUserId,
            'started_at' => time()
        ];
        
        $_SESSION['user_id'] = $targetUserId;
        
        // Log impersonation
        $this->logUserAction($adminUserId, 'impersonation_started', [
            'target_user_id' => $targetUserId
        ]);
    }

    /**
     * Stop impersonation
     */
    public function stopImpersonation(): void
    {
        if (isset($_SESSION['impersonation'])) {
            $adminId = $_SESSION['impersonation']['admin_id'];
            $targetId = $_SESSION['impersonation']['target_id'];
            
            $_SESSION['user_id'] = $adminId;
            unset($_SESSION['impersonation']);
            
            // Log end of impersonation
            $this->logUserAction($adminId, 'impersonation_ended', [
                'target_user_id' => $targetId
            ]);
        }
    }

    private function hasActiveContent(int $userId): bool
    {
        $pdo = $this->connection->getPdo();
        
        // Check for active events
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM events 
             WHERE created_by = :user_id AND status = 'approved'"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchColumn() > 0;
    }

    private function logUserAction(int $userId, string $action, array $data = []): void
    {
        $pdo = $this->connection->getPdo();
        
        $stmt = $pdo->prepare(
            "INSERT INTO user_activity_logs (user_id, action, data, created_at) 
             VALUES (:user_id, :action, :data, NOW())"
        );
        
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':action', $action);
        $stmt->bindValue(':data', json_encode($data));
        $stmt->execute();
    }

    private function generateTemporaryPassword(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function exportToCsv(array $users): array
    {
        $csv = "ID,Name,Email,Role,Status,Created At\n";
        
        foreach ($users as $user) {
            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s\n",
                $user->getId(),
                $user->getName(),
                $user->getEmail(),
                $user->getRole(),
                $user->getStatus(),
                $user->getCreatedAt()->format('Y-m-d H:i:s')
            );
        }
        
        return [
            'content' => $csv,
            'filename' => 'users_export_' . date('Y-m-d') . '.csv',
            'mime_type' => 'text/csv'
        ];
    }

    private function exportToJson(array $users): array
    {
        $data = array_map(fn($user) => $user->toArray(), $users);
        
        return [
            'content' => json_encode($data, JSON_PRETTY_PRINT),
            'filename' => 'users_export_' . date('Y-m-d') . '.json',
            'mime_type' => 'application/json'
        ];
    }
}