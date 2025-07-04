<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Repositories;

use YFEvents\Domain\Communication\Entities\Notification;

/**
 * Repository interface for chat notifications
 */
interface NotificationRepositoryInterface
{
    /**
     * Save a notification
     */
    public function save(Notification $notification): Notification;
    
    /**
     * Find notification by ID
     */
    public function findById(int $id): ?Notification;
    
    /**
     * Find notifications for a user
     */
    public function findByUserId(int $userId, bool $unreadOnly = false, int $limit = 50): array;
    
    /**
     * Find notifications for a channel and user
     */
    public function findByChannelIdAndUserId(int $channelId, int $userId, int $limit = 20): array;
    
    /**
     * Find unread notifications for a user
     */
    public function findUnreadByUserId(int $userId): array;
    
    /**
     * Count unread notifications for a user
     */
    public function countUnreadByUserId(int $userId): int;
    
    /**
     * Mark a notification as read
     */
    public function markAsRead(int $notificationId): bool;
    
    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsReadForUser(int $userId): bool;
    
    /**
     * Mark all channel notifications as read for a user
     */
    public function markChannelNotificationsAsRead(int $channelId, int $userId): bool;
    
    /**
     * Delete old notifications
     */
    public function deleteOldNotifications(int $daysToKeep = 30): int;
    
    /**
     * Create batch notifications for multiple users
     * Used when a message is sent to a channel with multiple participants
     */
    public function createBatchNotifications(int $messageId, int $channelId, array $userIds): bool;
}