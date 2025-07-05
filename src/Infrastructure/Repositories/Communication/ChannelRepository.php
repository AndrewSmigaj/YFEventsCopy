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
    
    /**
     * Map database fields to entity fields
     */
    protected function mapDbToEntity(array $data): array
    {
        // Database fields now match entity fields, just handle JSON
        if (isset($data['settings']) && is_string($data['settings'])) {
            $data['settings'] = json_decode($data['settings'], true) ?? [];
        }
        
        return $data;
    }
    
    /**
     * Map entity fields to database fields
     */
    protected function mapEntityToDb(Channel $channel): array
    {
        // Use entity's toArray method which already has correct field names
        return $channel->toArray();
    }
    
    public function findById(int $id): ?Channel
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }
        
        return Channel::fromArray($this->mapDbToEntity($data));
    }
    
    public function findBySlug(string $slug): ?Channel
    {
        // Try to find by generated slug (from title)
        $sql = "SELECT * FROM {$this->getTableName()} WHERE LOWER(REPLACE(title, ' ', '-')) = :slug";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['slug' => strtolower($slug)]);
        
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            // Fallback: try to find by type for backward compatibility
            $sql = "SELECT * FROM {$this->getTableName()} WHERE type = :type";
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['type' => $slug]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        }
        
        if (!$data) {
            return null;
        }
        
        return Channel::fromArray($this->mapDbToEntity($data));
    }
    
    public function findByEventId(int $eventId): array
    {
        // Events not supported in simplified chat
        return [];
    }
    
    public function findByShopId(int $shopId): array
    {
        // Shops not supported in simplified chat
        return [];
    }
    
    public function findPublicChannels(int $limit = 50, int $offset = 0): array
    {
        // In our schema, 'support' and 'tips' are the public channels
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE type IN ('support', 'tips') AND is_active = 1 
                ORDER BY last_activity DESC, created_at DESC 
                LIMIT :limit OFFSET :offset";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Channel::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function findUserChannels(int $userId, bool $includeArchived = false): array
    {
        $archivedCondition = $includeArchived ? '' : 'AND c.is_archived = 0';
        
        $sql = "SELECT c.* FROM {$this->getTableName()} c
                INNER JOIN communication_participants p ON c.id = p.channel_id
                WHERE p.user_id = :user_id {$archivedCondition}
                ORDER BY c.last_activity DESC, c.created_at DESC";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Channel::fromArray($this->mapDbToEntity($data));
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
    
    // Message count not tracked in simplified schema
    public function updateMessageCount(int $channelId, int $count): bool
    {
        return true;
    }
    
    // Participant count not tracked in simplified schema
    public function updateParticipantCount(int $channelId, int $count): bool
    {
        return true;
    }
    
    public function updateLastActivity(int $channelId): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET last_activity = CURRENT_TIMESTAMP 
                WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute(['id' => $channelId]);
    }
    
    public function searchChannels(string $query, int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE (title LIKE :query OR description LIKE :query) 
                AND is_active = 1 
                ORDER BY 
                    CASE WHEN title LIKE :exact THEN 0 ELSE 1 END,
                    last_activity DESC 
                LIMIT :limit";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':query', '%' . $query . '%');
        $stmt->bindValue(':exact', $query);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Channel::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    /**
     * Save a channel
     */
    public function save(Channel $channel): Channel
    {
        $data = $this->mapEntityToDb($channel);
        
        if ($channel->getId() === null) {
            // Insert new channel
            unset($data['id']);
            $sql = "INSERT INTO {$this->getTableName()} 
                    (type, title, description, created_by, is_active, created_at, updated_at) 
                    VALUES (:type, :title, :description, :created_by, :is_active, :created_at, :updated_at)";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            
            $id = (int) $this->connection->lastInsertId();
            return $this->findById($id);
        } else {
            // Update existing channel
            $sql = "UPDATE {$this->getTableName()} 
                    SET title = :title, description = :description, is_active = :is_active, 
                        updated_at = :updated_at 
                    WHERE id = :id";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                'title' => $data['title'],
                'description' => $data['description'],
                'is_active' => $data['is_active'],
                'updated_at' => $data['updated_at'],
                'id' => $data['id']
            ]);
            
            return $channel;
        }
    }
}