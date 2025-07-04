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
        return 'chat_conversations';
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
        // Map chat_conversations fields to Channel entity
        $data['name'] = $data['title'] ?? '';
        $data['slug'] = $data['type'] ?? 'direct'; // Use type as slug for simplicity
        $data['created_by_user_id'] = $data['created_by'] ?? 1;
        $data['is_archived'] = !($data['is_active'] ?? true);
        $data['settings'] = isset($data['settings']) ? json_decode($data['settings'], true) : [];
        $data['message_count'] = 0;
        $data['participant_count'] = 0;
        $data['last_activity_at'] = $data['last_activity'] ?? null;
        
        // Set defaults for fields not in our schema
        $data['event_id'] = null;
        $data['shop_id'] = null;
        
        return $data;
    }
    
    /**
     * Map entity fields to database fields
     */
    protected function mapEntityToDb(Channel $channel): array
    {
        return [
            'id' => $channel->getId(),
            'type' => $channel->getType()->getValue(),
            'title' => $channel->getName(),
            'description' => $channel->getDescription(),
            'created_by' => $channel->getCreatedByUserId(),
            'is_active' => !$channel->isArchived(),
            'last_message_id' => null,
            'last_activity' => $channel->getLastActivityAt()?->format('Y-m-d H:i:s'),
            'created_at' => $channel->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $channel->getUpdatedAt()->format('Y-m-d H:i:s')
        ];
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
        // In our simplified schema, we use type as slug
        $sql = "SELECT * FROM {$this->getTableName()} WHERE type = :type";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['type' => $slug]);
        
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
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
                INNER JOIN chat_participants p ON c.id = p.conversation_id
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
        $sql = "SELECT COUNT(*) FROM chat_participants WHERE conversation_id = :conversation_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['conversation_id' => $channelId]);
        
        return (int) $stmt->fetchColumn();
    }
    
    public function isUserParticipant(int $channelId, int $userId): bool
    {
        $sql = "SELECT COUNT(*) FROM chat_participants 
                WHERE conversation_id = :conversation_id AND user_id = :user_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'conversation_id' => $channelId,
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