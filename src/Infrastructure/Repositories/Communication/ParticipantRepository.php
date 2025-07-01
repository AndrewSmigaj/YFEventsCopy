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
    
    public function findById(int $id): ?Participant
    {
        $entity = parent::findById($id);
        return $entity instanceof Participant ? $entity : null;
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
        
        return Participant::fromArray($data);
    }
    
    public function findByChannelId(int $channelId, int $limit = 100, int $offset = 0): array
    {
        return $this->findBy(
            ['channel_id' => $channelId],
            ['joined_at' => 'ASC'],
            $limit,
            $offset
        );
    }
    
    public function findByUserId(int $userId, bool $includeMuted = false): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE user_id = :user_id";
        
        if (!$includeMuted) {
            $sql .= " AND is_muted = 0";
        }
        
        $sql .= " ORDER BY joined_at DESC";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($data);
        }
        
        return $results;
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
    
    public function countByChannelId(int $channelId): int
    {
        return $this->count(['channel_id' => $channelId]);
    }
    
    public function findChannelAdmins(int $channelId): array
    {
        return $this->findBy(
            ['channel_id' => $channelId, 'role' => Participant::ROLE_ADMIN],
            ['joined_at' => 'ASC']
        );
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
        return (int)$stmt->fetchColumn() > 0;
    }
    
    public function updateLastRead(int $channelId, int $userId, int $messageId): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET last_read_message_id = :message_id, last_read_at = NOW() 
                WHERE channel_id = :channel_id AND user_id = :user_id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'message_id' => $messageId,
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
    }
    
    public function findForDigest(string $frequency): array
    {
        $sql = "SELECT p.*, c.name as channel_name, c.slug as channel_slug 
                FROM {$this->getTableName()} p
                INNER JOIN communication_channels c ON p.channel_id = c.id
                WHERE p.email_digest_frequency = :frequency 
                AND p.is_muted = 0 
                AND c.is_archived = 0
                ORDER BY p.user_id, p.channel_id";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['frequency' => $frequency]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($data);
        }
        
        return $results;
    }
    
    public function findUsersToNotify(int $channelId, string $notificationType = 'all'): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id 
                AND is_muted = 0";
        
        if ($notificationType === 'mentions') {
            $sql .= " AND notification_preference IN ('all', 'mentions')";
        } else {
            $sql .= " AND notification_preference = 'all'";
        }
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['channel_id' => $channelId]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($data);
        }
        
        return $results;
    }
}