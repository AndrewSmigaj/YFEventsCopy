<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Users;

use YakimaFinds\Domain\Common\EntityInterface;
use DateTimeInterface;
use DateTime;

class User implements EntityInterface
{
    public function __construct(
        private ?int $id,
        private string $email,
        private string $name,
        private string $password,
        private string $role,
        private string $status,
        private ?DateTimeInterface $lastLogin,
        private ?DateTimeInterface $suspendedUntil,
        private ?string $suspensionReason,
        private bool $passwordResetRequired,
        private DateTimeInterface $createdAt,
        private ?DateTimeInterface $updatedAt = null
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getLastLogin(): ?DateTimeInterface
    {
        return $this->lastLogin;
    }

    public function getSuspendedUntil(): ?DateTimeInterface
    {
        return $this->suspendedUntil;
    }

    public function getSuspensionReason(): ?string
    {
        return $this->suspensionReason;
    }

    public function isPasswordResetRequired(): bool
    {
        return $this->passwordResetRequired;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->suspendedUntil && $this->suspendedUntil > new DateTime()) {
            return false;
        }

        return true;
    }

    /**
     * Check if user is suspended
     */
    public function isSuspended(): bool
    {
        return $this->suspendedUntil !== null && $this->suspendedUntil > new DateTime();
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(string $permission): bool
    {
        // Simple role-based permissions
        $rolePermissions = [
            'admin' => ['*'], // Admin has all permissions
            'editor' => [
                'events.view',
                'events.create',
                'events.edit',
                'events.delete',
                'shops.view',
                'shops.create',
                'shops.edit'
            ],
            'user' => [
                'events.view',
                'shops.view'
            ]
        ];

        $permissions = $rolePermissions[$this->role] ?? [];
        
        return in_array('*', $permissions) || in_array($permission, $permissions);
    }

    /**
     * Update user data
     */
    public function update(array $data): self
    {
        return new self(
            id: $this->id,
            email: $data['email'] ?? $this->email,
            name: $data['name'] ?? $this->name,
            password: $data['password'] ?? $this->password,
            role: $data['role'] ?? $this->role,
            status: $data['status'] ?? $this->status,
            lastLogin: isset($data['last_login']) ? new DateTime($data['last_login']) : $this->lastLogin,
            suspendedUntil: isset($data['suspended_until']) ? new DateTime($data['suspended_until']) : $this->suspendedUntil,
            suspensionReason: $data['suspension_reason'] ?? $this->suspensionReason,
            passwordResetRequired: $data['password_reset_required'] ?? $this->passwordResetRequired,
            createdAt: $this->createdAt,
            updatedAt: new DateTime()
        );
    }

    /**
     * Suspend user
     */
    public function suspend(string $reason, ?DateTimeInterface $until = null): self
    {
        return $this->update([
            'status' => 'suspended',
            'suspension_reason' => $reason,
            'suspended_until' => $until?->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Reactivate user
     */
    public function reactivate(): self
    {
        return $this->update([
            'status' => 'active',
            'suspension_reason' => null,
            'suspended_until' => null
        ]);
    }

    /**
     * Record login
     */
    public function recordLogin(): self
    {
        return $this->update([
            'last_login' => (new DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role,
            'status' => $this->status,
            'last_login' => $this->lastLogin?->format('Y-m-d H:i:s'),
            'suspended_until' => $this->suspendedUntil?->format('Y-m-d H:i:s'),
            'suspension_reason' => $this->suspensionReason,
            'password_reset_required' => $this->passwordResetRequired,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'] ?? null,
            email: $data['email'],
            name: $data['name'],
            password: $data['password'],
            role: $data['role'],
            status: $data['status'] ?? 'active',
            lastLogin: isset($data['last_login']) ? new DateTime($data['last_login']) : null,
            suspendedUntil: isset($data['suspended_until']) ? new DateTime($data['suspended_until']) : null,
            suspensionReason: $data['suspension_reason'] ?? null,
            passwordResetRequired: $data['password_reset_required'] ?? false,
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : new DateTime(),
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
    }
}