<?php

declare(strict_types=1);

namespace YFEvents\Domain\Users;

interface UserRepositoryInterface
{
    /**
     * Find user by ID
     */
    public function findById(int $id): ?User;

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?User;

    /**
     * Find users with filters
     */
    public function findWithFilters(array $filters = [], ?int $limit = null, ?int $offset = null): array;

    /**
     * Count users with filters
     */
    public function countWithFilters(array $filters = []): int;

    /**
     * Save user (create or update)
     */
    public function save(User $user): User;

    /**
     * Delete user
     */
    public function delete(int $id): void;

    /**
     * Find users by role
     */
    public function findByRole(string $role): array;

    /**
     * Find active users
     */
    public function findActive(): array;

    /**
     * Find suspended users
     */
    public function findSuspended(): array;

    /**
     * Check if email exists
     */
    public function emailExists(string $email, ?int $excludeId = null): bool;
}