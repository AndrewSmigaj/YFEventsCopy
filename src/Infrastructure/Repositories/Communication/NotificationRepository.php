<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Communication;

use YFEvents\Infrastructure\Database\AbstractRepository;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use YFEvents\Domain\Communication\Entities\Notification;
use YFEvents\Domain\Communication\Repositories\NotificationRepositoryInterface;

/**
 * Notification repository implementation for chat notifications
 */
class NotificationRepository extends AbstractRepository implements NotificationRepositoryInterface
{
    public function __construct(ConnectionInterface $connection)
    {
        parent::__construct($connection);
    }
    
    protected function getTableName(): string
    {
        return 'communication_notifications';
    }
    
    protected function getEntityClass(): string
    {
        return Notification::class;
    }
    
    /**
     * Map database fields to entity fields
     */
    protected function mapDbToEntity(array $data): array
    {
        // Database fields now match entity fields, just handle JSON
        if (isset($data['metadata']) && is_string($data['metadata'])) {
            $data['metadata'] = json_decode($data['metadata'], true) ?? [];
        }
        
        return $data;
    }
    
    /**
     * Map entity fields to database fields
     */
    protected function mapEntityToDb(Notification $notification): array
    {
        // Use entity's toArray method which already has correct field names
        return $notification->toArray();
    }
    
    /**
     * Save a notification
     */
    public function save(Notification $notification): Notification
    {
        $data = $this->mapEntityToDb($notification);
        
        if ($notification->getId() === null) {
            // Insert new notification
            unset($data['id']);
            $sql = "INSERT INTO {$this->getTableName()} 
                    (user_id, channel_id, message_id, is_read, created_at) 
                    VALUES (:user_id, :channel_id, :message_id, :is_read, :created_at)";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            
            $id = (int) $this->connection->lastInsertId();
            return $this->findById($id);
        } else {
            // Update existing notification
            $sql = "UPDATE {$this->getTableName()} 
                    SET is_read = :is_read, read_at = :read_at 
                    WHERE id = :id";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                'is_read' => $data['is_read'],
                'read_at' => $data['read_at'],
                'id' => $data['id']
            ]);
            
            return $notification;
        }
    }
    
    public function findById(int $id): ?Notification
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }
        
        return Notification::fromArray($this->mapDbToEntity($data));
    }
    
    public function findByUserId(int $userId, bool $unreadOnly = false, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE user_id = :user_id";
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT :limit";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Notification::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function findByChannelIdAndUserId(int $channelId, int $userId, int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id AND user_id = :user_id 
                ORDER BY created_at DESC 
                LIMIT :limit";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':channel_id', $channelId, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Notification::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function findUnreadByUserId(int $userId): array
    {
        return $this->findByUserId($userId, true);
    }
    
    public function countUnreadByUserId(int $userId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()} 
                WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        return (int) $stmt->fetchColumn();
    }
    
    public function markAsRead(int $notificationId): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET is_read = 1, read_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute(['id' => $notificationId]);
    }
    
    public function markAllAsReadForUser(int $userId): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET is_read = 1, read_at = CURRENT_TIMESTAMP 
                WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute(['user_id' => $userId]);
    }
    
    public function markChannelNotificationsAsRead(int $channelId, int $userId): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET is_read = 1, read_at = CURRENT_TIMESTAMP 
                WHERE channel_id = :channel_id 
                AND user_id = :user_id 
                AND is_read = 0";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
    }
    
    public function deleteOldNotifications(int $daysToKeep = 30): int
    {
        $sql = "DELETE FROM {$this->getTableName()} 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['days' => $daysToKeep]);
        
        return $stmt->rowCount();
    }
    
    public function createBatchNotifications(int $messageId, int $channelId, array $userIds): bool
    {
        if (empty($userIds)) {
            return true;
        }
        
        $sql = "INSERT INTO {$this->getTableName()} 
                (user_id, channel_id, message_id, is_read, created_at) VALUES ";
        
        $placeholders = [];
        $values = [];
        $now = date('Y-m-d H:i:s');
        
        foreach ($userIds as $i => $userId) {
            $placeholders[] = "(:user_id_{$i}, :channel_id, :message_id, 0, :created_at)";
            $values["user_id_{$i}"] = $userId;
        }
        
        $sql .= implode(', ', $placeholders);
        
        $stmt = $this->connection->prepare($sql);
        $values['channel_id'] = $channelId;
        $values['message_id'] = $messageId;
        $values['created_at'] = $now;
        
        return $stmt->execute($values);
    }
}