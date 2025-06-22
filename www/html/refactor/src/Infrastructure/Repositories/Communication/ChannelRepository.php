<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Communication;

use YFEvents\Infrastructure\Database\AbstractRepository;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use YFEvents\Domain\Communication\Entities\Channel;
use YFEvents\Domain\Communication\Repositories\ChannelRepositoryInterface;

/**
 * Channel repository implementation
 */
class ChannelRepository extends AbstractRepository implements ChannelRepositoryInterface
{
    public function __construct(ConnectionInterface $connection)
    {
        parent::__construct($connection);
    }
    
    protected function getTableName(): string
    {
        return 'communication_channels';
    }
    
    protected function getEntityClass(): string
    {
        return Channel::class;
    }
    
    public function findById(int $id): ?Channel
    {
        $entity = parent::findById($id);
        return $entity instanceof Channel ? $entity : null;
    }
    
    public function findBySlug(string $slug): ?Channel
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE slug = :slug";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['slug' => $slug]);
        
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }
        
        return Channel::fromArray($data);
    }
    
    public function findByEventId(int $eventId): array
    {
        return $this->findBy(['event_id' => $eventId], ['created_at' => 'DESC']);
    }
    
    public function findByShopId(int $shopId): array
    {
        return $this->findBy(['shop_id' => $shopId], ['created_at' => 'DESC']);
    }
    
    public function findPublicChannels(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE type = 'public' AND is_archived = 0 
                ORDER BY last_activity_at DESC, created_at DESC 
                LIMIT :limit OFFSET :offset";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Channel::fromArray($data);
        }
        
        return $results;
    }
    
    public function findUserChannels(int $userId, bool $includeArchived = false): array
    {
        $archivedCondition = $includeArchived ? '' : 'AND c.is_archived = 0';
        
        $sql = "SELECT c.* FROM {$this->getTableName()} c
                INNER JOIN communication_participants p ON c.id = p.channel_id
                WHERE p.user_id = :user_id {$archivedCondition}
                ORDER BY c.last_activity_at DESC, c.created_at DESC";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Channel::fromArray($data);
        }
        
        return $results;
    }
    
    
    
    public function countParticipants(int $channelId): int
    {
        $sql = "SELECT COUNT(*) FROM communication_participants WHERE channel_id = :channel_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['channel_id' => $channelId]);
        
        return (int) $stmt->fetchColumn();
    }
    
    public function isUserParticipant(int $channelId, int $userId): bool
    {
        $sql = "SELECT COUNT(*) FROM communication_participants 
                WHERE channel_id = :channel_id AND user_id = :user_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'channel_id' => $channelId,
            'user_id' => $userId
        ]);
        
        return (int) $stmt->fetchColumn() > 0;
    }
    
    public function updateMessageCount(int $channelId, int $count): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET message_count = :count 
                WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'count' => $count,
            'id' => $channelId
        ]);
    }
    
    public function updateParticipantCount(int $channelId, int $count): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET participant_count = :count 
                WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'count' => $count,
            'id' => $channelId
        ]);
    }
    
    public function updateLastActivity(int $channelId): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET last_activity_at = NOW() 
                WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute(['id' => $channelId]);
    }
    
    public function searchChannels(string $query, int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE (name LIKE :query OR description LIKE :query) 
                AND is_archived = 0 
                ORDER BY 
                    CASE WHEN name LIKE :exact THEN 0 ELSE 1 END,
                    last_activity_at DESC 
                LIMIT :limit";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':query', '%' . $query . '%');
        $stmt->bindValue(':exact', $query);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Channel::fromArray($data);
        }
        
        return $results;
    }
}