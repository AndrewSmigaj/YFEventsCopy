<?php

declare(strict_types=1);

namespace YFEvents\Application\Validation;

use YFEvents\Domain\Users\UserRepositoryInterface;

class UserValidator extends BaseValidator
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * Validate user creation
     */
    public function validateCreate(array $data): array
    {
        $errors = [];

        // Email validation
        if (empty($data['email'])) {
            $errors['email'] = 'Email is required';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        } elseif ($this->userRepository->emailExists($data['email'])) {
            $errors['email'] = 'Email already exists';
        }

        // Name validation
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        } elseif (strlen($data['name']) < 2) {
            $errors['name'] = 'Name must be at least 2 characters';
        } elseif (strlen($data['name']) > 100) {
            $errors['name'] = 'Name cannot exceed 100 characters';
        }

        // Password validation
        if (empty($data['password'])) {
            $errors['password'] = 'Password is required';
        } elseif (strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        } elseif (!$this->isStrongPassword($data['password'])) {
            $errors['password'] = 'Password must contain uppercase, lowercase, number, and special character';
        }

        // Password confirmation
        if (empty($data['password_confirmation'])) {
            $errors['password_confirmation'] = 'Password confirmation is required';
        } elseif ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = 'Passwords do not match';
        }

        // Role validation
        if (empty($data['role'])) {
            $errors['role'] = 'Role is required';
        } elseif (!in_array($data['role'], ['admin', 'editor', 'user'])) {
            $errors['role'] = 'Invalid role';
        }

        return $errors;
    }

    /**
     * Validate user update
     */
    public function validateUpdate(array $data, int $userId): array
    {
        $errors = [];

        // Email validation
        if (isset($data['email'])) {
            if (empty($data['email'])) {
                $errors['email'] = 'Email cannot be empty';
            } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            } elseif ($this->userRepository->emailExists($data['email'], $userId)) {
                $errors['email'] = 'Email already exists';
            }
        }

        // Name validation
        if (isset($data['name'])) {
            if (empty($data['name'])) {
                $errors['name'] = 'Name cannot be empty';
            } elseif (strlen($data['name']) < 2) {
                $errors['name'] = 'Name must be at least 2 characters';
            } elseif (strlen($data['name']) > 100) {
                $errors['name'] = 'Name cannot exceed 100 characters';
            }
        }

        // Password validation (only if provided)
        if (!empty($data['password'])) {
            if (strlen($data['password']) < 8) {
                $errors['password'] = 'Password must be at least 8 characters';
            } elseif (!$this->isStrongPassword($data['password'])) {
                $errors['password'] = 'Password must contain uppercase, lowercase, number, and special character';
            }

            // Password confirmation
            if (empty($data['password_confirmation'])) {
                $errors['password_confirmation'] = 'Password confirmation is required';
            } elseif ($data['password'] !== $data['password_confirmation']) {
                $errors['password_confirmation'] = 'Passwords do not match';
            }
        }

        // Role validation
        if (isset($data['role']) && !in_array($data['role'], ['admin', 'editor', 'user'])) {
            $errors['role'] = 'Invalid role';
        }

        // Status validation
        if (isset($data['status']) && !in_array($data['status'], ['active', 'inactive', 'suspended'])) {
            $errors['status'] = 'Invalid status';
        }

        return $errors;
    }

    /**
     * Validate bulk action
     */
    public function validateBulkAction(array $data): array
    {
        $errors = [];

        if (empty($data['action'])) {
            $errors['action'] = 'Action is required';
        } elseif (!in_array($data['action'], ['activate', 'deactivate', 'delete', 'change_role'])) {
            $errors['action'] = 'Invalid action';
        }

        if (empty($data['user_ids']) || !is_array($data['user_ids'])) {
            $errors['user_ids'] = 'No users selected';
        } else {
            foreach ($data['user_ids'] as $userId) {
                if (!is_numeric($userId) || $userId <= 0) {
                    $errors['user_ids'] = 'Invalid user ID in selection';
                    break;
                }
            }
        }

        if ($data['action'] === 'change_role' && empty($data['role'])) {
            $errors['role'] = 'Role is required for change role action';
        }

        return $errors;
    }

    /**
     * Validate suspension
     */
    public function validateSuspension(array $data): array
    {
        $errors = [];

        if (empty($data['reason'])) {
            $errors['reason'] = 'Suspension reason is required';
        } elseif (strlen($data['reason']) < 10) {
            $errors['reason'] = 'Suspension reason must be at least 10 characters';
        }

        if (isset($data['duration']) && !is_numeric($data['duration'])) {
            $errors['duration'] = 'Duration must be a number';
        } elseif (isset($data['duration']) && $data['duration'] < 1) {
            $errors['duration'] = 'Duration must be at least 1 day';
        }

        return $errors;
    }

    /**
     * Check if password is strong
     */
    private function isStrongPassword(string $password): bool
    {
        // Must contain at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        // Must contain at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        // Must contain at least one number
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        // Must contain at least one special character
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            return false;
        }

        return true;
    }
}