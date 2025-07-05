<?php

declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Communication;

use YFEvents\Infrastructure\Database\AbstractRepository;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use YFEvents\Domain\Communication\Entities\Message;
use YFEvents\Domain\Communication\Repositories\MessageRepositoryInterface;

/**
 * Message repository implementation
 */
class MessageRepository extends AbstractRepository implements MessageRepositoryInterface
{
    public function __construct(ConnectionInterface $connection)
    {
        parent::__construct($connection);
    }
    
    protected function getTableName(): string
    {
        return 'communication_messages';
    }
    
    protected function getEntityClass(): string
    {
        return Message::class;
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
    protected function mapEntityToDb(Message $message): array
    {
        // Use entity's toArray method which already has correct field names
        return $message->toArray();
    }
    
    /**
     * Save a message
     */
    public function save(Message $message): Message
    {
        $data = $this->mapEntityToDb($message);
        
        if ($message->getId() === null) {
            // Insert new message
            unset($data['id']);
            $sql = "INSERT INTO {$this->getTableName()} 
                    (channel_id, user_id, parent_message_id, content, content_type, 
                     is_pinned, is_edited, is_deleted, yfclaim_item_id, metadata, 
                     email_message_id, reply_count, reaction_count, created_at, updated_at) 
                    VALUES (:channel_id, :user_id, :parent_message_id, :content, :content_type,
                            :is_pinned, :is_edited, :is_deleted, :yfclaim_item_id, :metadata,
                            :email_message_id, :reply_count, :reaction_count, :created_at, :updated_at)";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            
            $id = (int) $this->connection->lastInsertId();
            return $this->findById($id);
        } else {
            // Update existing message
            $sql = "UPDATE {$this->getTableName()} 
                    SET content = :content, is_edited = :is_edited, is_deleted = :is_deleted, 
                        deleted_at = :deleted_at, updated_at = :updated_at
                    WHERE id = :id";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                'content' => $data['content'],
                'is_edited' => $data['is_edited'],
                'is_deleted' => $data['is_deleted'],
                'deleted_at' => $data['deleted_at'],
                'updated_at' => $data['updated_at'],
                'id' => $data['id']
            ]);
            
            return $message;
        }
    }
    
    public function findById(int $id): ?Message
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }
        
        return Message::fromArray($this->mapDbToEntity($data));
    }
    
    public function findByChannelId(int $channelId, int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id AND is_deleted = 0 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':channel_id', $channelId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Message::fromArray($this->mapDbToEntity($data));
        }
        
        // Reverse to show oldest first
        return array_reverse($results);
    }
    
    public function findByParentMessageId(int $parentMessageId, int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE parent_message_id = :parent_message_id AND is_deleted = 0 
                ORDER BY created_at ASC 
                LIMIT :limit";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':parent_message_id', $parentMessageId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Message::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function findByEmailMessageId(string $emailMessageId): ?Message
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE email_message_id = :email_message_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['email_message_id' => $emailMessageId]);
        
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }
        
        return Message::fromArray($this->mapDbToEntity($data));
    }
    
    public function findPinnedMessages(int $channelId): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id AND is_pinned = 1 AND is_deleted = 0 
                ORDER BY created_at DESC";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['channel_id' => $channelId]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Message::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function countReplies(int $messageId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()} 
                WHERE parent_message_id = :message_id AND is_deleted = 0";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['message_id' => $messageId]);
        
        return (int) $stmt->fetchColumn();
    }
    
    public function searchMessages(int $channelId, string $query, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id 
                AND is_deleted = 0 
                AND content LIKE :query
                ORDER BY created_at DESC 
                LIMIT :limit";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':channel_id', $channelId, \PDO::PARAM_INT);
        $stmt->bindValue(':query', '%' . $query . '%');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Message::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function findMentionsForUser(int $userId, int $limit = 20): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE is_deleted = 0 
                AND content LIKE :mention
                ORDER BY created_at DESC 
                LIMIT :limit";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':mention', '%@user' . $userId . '%');
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Message::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function getUnreadCount(int $channelId, int $userId, int $lastReadMessageId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id 
                AND id > :last_read_id 
                AND user_id != :user_id 
                AND is_deleted = 0";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'channel_id' => $channelId,
            'last_read_id' => $lastReadMessageId,
            'user_id' => $userId
        ]);
        
        return (int) $stmt->fetchColumn();
    }
    
    public function findMessagesAfter(int $channelId, int $messageId, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id 
                AND id > :message_id 
                AND is_deleted = 0 
                ORDER BY id ASC 
                LIMIT :limit";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':channel_id', $channelId, \PDO::PARAM_INT);
        $stmt->bindValue(':message_id', $messageId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Message::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function findMessagesBefore(int $channelId, int $messageId, int $limit = 50): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE channel_id = :channel_id 
                AND id < :message_id 
                AND is_deleted = 0 
                ORDER BY id DESC 
                LIMIT :limit";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':channel_id', $channelId, \PDO::PARAM_INT);
        $stmt->bindValue(':message_id', $messageId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Message::fromArray($this->mapDbToEntity($data));
        }
        
        // Reverse to maintain chronological order
        return array_reverse($results);
    }
    
    public function markAsDeleted(int $messageId): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET is_deleted = 1, deleted_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute(['id' => $messageId]);
    }
    
    public function updateReplyCount(int $messageId, int $count): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET reply_count = :count 
                WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute(['count' => $count, 'id' => $messageId]);
    }
    
    public function updateReactionCount(int $messageId, int $count): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET reaction_count = :count 
                WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute(['count' => $count, 'id' => $messageId]);
    }
}