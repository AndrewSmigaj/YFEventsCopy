<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Communication;

use YFEvents\Infrastructure\Database\AbstractRepository;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use YFEvents\Domain\Communication\Entities\Participant;
use YFEvents\Domain\Communication\Repositories\ParticipantRepositoryInterface;

/**
 * Participant repository implementation
 */
class ParticipantRepository extends AbstractRepository implements ParticipantRepositoryInterface
{
    public function __construct(ConnectionInterface $connection)
    {
        parent::__construct($connection);
    }
    
    protected function getTableName(): string
    {
        return 'communication_participants';
    }
    
    protected function getEntityClass(): string
    {
        return Participant::class;
    }
    
    /**
     * Map database fields to entity fields
     */
    protected function mapDbToEntity(array $data): array
    {
        // Database fields now match entity fields
        return $data;
    }
    
    /**
     * Map entity fields to database fields
     */
    protected function mapEntityToDb(Participant $participant): array
    {
        // Use entity's toArray method which already has correct field names
        return $participant->toArray();
    }
    
    public function findById(int $id): ?Participant
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }
        
        return Participant::fromArray($this->mapDbToEntity($data));
    }
    
    public function findByChannelIdAndUserId(int $channelId, int $userId): ?Participant
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id AND user_id = :user_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
        
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }
        
        return Participant::fromArray($this->mapDbToEntity($data));
    }
    
    public function findByChannelId(int $channelId, int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE channel_id = :channel_id";
        $sql .= " AND is_active = 1";
        $sql .= " ORDER BY joined_at ASC";
        $sql .= " LIMIT :limit OFFSET :offset";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':channel_id', $channelId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function findByUserId(int $userId, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE user_id = :user_id";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY joined_at DESC";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function save(\YFEvents\Domain\Common\EntityInterface $participant): \YFEvents\Domain\Common\EntityInterface
    {
        $data = $this->mapEntityToDb($participant);
        
        if ($participant->getId() === null) {
            // Insert new participant
            unset($data['id']);
            $sql = "INSERT INTO {$this->getTableName()} 
                    (channel_id, user_id, role, joined_at, last_read_message_id, last_read_at,
                     notification_preference, email_digest_frequency, is_muted) 
                    VALUES (:channel_id, :user_id, :role, :joined_at, :last_read_message_id, :last_read_at,
                            :notification_preference, :email_digest_frequency, :is_muted)";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            
            $id = (int) $this->connection->lastInsertId();
            return $this->findById($id);
        } else {
            // Update existing participant
            $sql = "UPDATE {$this->getTableName()} 
                    SET role = :role, last_read_message_id = :last_read_message_id, 
                        last_read_at = :last_read_at, notification_preference = :notification_preference,
                        email_digest_frequency = :email_digest_frequency, is_muted = :is_muted
                    WHERE id = :id";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                'role' => $data['role'],
                'last_read_message_id' => $data['last_read_message_id'],
                'last_read_at' => $data['last_read_at'],
                'notification_preference' => $data['notification_preference'],
                'email_digest_frequency' => $data['email_digest_frequency'],
                'is_muted' => $data['is_muted'],
                'id' => $data['id']
            ]);
            
            return $participant;
        }
    }
    
    public function isUserInChannel(int $channelId, int $userId): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id AND user_id = :user_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
        
        return (int) $stmt->fetchColumn() > 0;
    }
    
    public function countByChannelId(int $channelId, bool $activeOnly = true): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()} WHERE channel_id = :channel_id";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['channel_id' => $channelId]);
        
        return (int) $stmt->fetchColumn();
    }
    
    public function updateLastReadMessage(int $channelId, int $userId, int $messageId): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET last_read_message_id = :message_id, last_read_at = CURRENT_TIMESTAMP 
                WHERE channel_id = :channel_id AND user_id = :user_id";
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'message_id' => $messageId,
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
    }
    
    // Alias for interface compatibility
    public function updateLastRead(int $channelId, int $userId, int $messageId): bool
    {
        return $this->updateLastReadMessage($channelId, $userId, $messageId);
    }
    
    public function updateNotificationPreference(int $channelId, int $userId, string $preference): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET notification_preference = :preference 
                WHERE channel_id = :channel_id AND user_id = :user_id";
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'preference' => $preference,
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
    }
    
    public function findAdmins(int $channelId): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id AND role = 'admin' 
                ORDER BY joined_at ASC";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['channel_id' => $channelId]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function removeFromChannel(int $channelId, int $userId): bool
    {
        $sql = "DELETE FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id AND user_id = :user_id";
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
    }
    
    public function updateRole(int $channelId, int $userId, string $role): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET role = :role 
                WHERE channel_id = :channel_id AND user_id = :user_id";
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'role' => $role,
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
    }
    
    public function muteChannel(int $channelId, int $userId): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET is_muted = 1 
                WHERE channel_id = :channel_id AND user_id = :user_id";
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
    }
    
    public function unmuteChannel(int $channelId, int $userId): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET is_muted = 0 
                WHERE channel_id = :channel_id AND user_id = :user_id";
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
    }
    
    public function deleteByChannelIdAndUserId(int $channelId, int $userId): bool
    {
        $sql = "DELETE FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id AND user_id = :user_id";
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
    }
    
    public function findChannelAdmins(int $channelId): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id 
                AND role = 'admin' 
                AND is_active = 1
                ORDER BY joined_at ASC";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['channel_id' => $channelId]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function findForDigest(string $frequency): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE email_digest_frequency = :frequency 
                AND is_active = 1
                AND last_digest_at < DATE_SUB(NOW(), INTERVAL 1 DAY)";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['frequency' => $frequency]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function findUsersToNotify(int $channelId, string $notificationType = 'all'): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id 
                AND is_active = 1
                AND notification_preference IN (:type, 'all')
                AND is_muted = 0";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'channel_id' => $channelId,
            'type' => $notificationType
        ]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
}