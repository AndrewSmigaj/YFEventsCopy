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
        return 'chat_participants';
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
        // Map conversation_id to channel_id for the Participant entity
        if (isset($data['conversation_id'])) {
            $data['channel_id'] = $data['conversation_id'];
            unset($data['conversation_id']);
        }
        
        // Set defaults for fields we don't use
        $data['is_muted'] = false;
        $data['notification_preference'] = 'all';
        $data['email_digest_frequency'] = 'never';
        $data['last_read_at'] = $data['last_seen'] ?? null;
        
        return $data;
    }
    
    /**
     * Map entity fields to database fields
     */
    protected function mapEntityToDb(Participant $participant): array
    {
        return [
            'id' => $participant->getId(),
            'conversation_id' => $participant->getChannelId(), // Map channel_id to conversation_id
            'user_id' => $participant->getUserId(),
            'role' => $participant->getRole(),
            'joined_at' => $participant->getJoinedAt()->format('Y-m-d H:i:s'),
            'is_active' => true,
            'last_read_message_id' => $participant->getLastReadMessageId(),
            'last_seen' => $participant->getLastReadAt()?->format('Y-m-d H:i:s')
        ];
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
                WHERE conversation_id = :conversation_id AND user_id = :user_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'conversation_id' => $channelId,
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
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE conversation_id = :conversation_id 
                ORDER BY joined_at ASC 
                LIMIT :limit OFFSET :offset";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->bindValue(':conversation_id', $channelId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function findByUserId(int $userId, bool $includeMuted = false): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE user_id = :user_id";
        
        // Note: is_muted field doesn't exist in our schema, always include all
        $sql .= " ORDER BY joined_at DESC";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    /**
     * Save a participant
     */
    public function save(Participant $participant): Participant
    {
        $data = $this->mapEntityToDb($participant);
        
        if ($participant->getId() === null) {
            // Insert new participant
            unset($data['id']);
            $sql = "INSERT INTO {$this->getTableName()} 
                    (conversation_id, user_id, role, joined_at, is_active, last_read_message_id, last_seen) 
                    VALUES (:conversation_id, :user_id, :role, :joined_at, :is_active, :last_read_message_id, :last_seen)";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($data);
            
            $id = (int) $this->connection->lastInsertId();
            return $this->findById($id);
        } else {
            // Update existing participant
            $sql = "UPDATE {$this->getTableName()} 
                    SET role = :role, is_active = :is_active, 
                        last_read_message_id = :last_read_message_id, last_seen = :last_seen 
                    WHERE id = :id";
            
            $stmt = $this->connection->prepare($sql);
            $stmt->execute([
                'role' => $data['role'],
                'is_active' => $data['is_active'],
                'last_read_message_id' => $data['last_read_message_id'],
                'last_seen' => $data['last_seen'],
                'id' => $data['id']
            ]);
            
            return $participant;
        }
    }
    
    public function deleteByChannelIdAndUserId(int $channelId, int $userId): bool
    {
        $sql = "DELETE FROM {$this->getTableName()} 
                WHERE conversation_id = :conversation_id AND user_id = :user_id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'conversation_id' => $channelId,
            'user_id' => $userId
        ]);
    }
    
    public function countByChannelId(int $channelId): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()} 
                WHERE conversation_id = :conversation_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['conversation_id' => $channelId]);
        return (int) $stmt->fetchColumn();
    }
    
    public function findChannelAdmins(int $channelId): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE conversation_id = :conversation_id AND role = :role 
                ORDER BY joined_at ASC";
                
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'conversation_id' => $channelId,
            'role' => Participant::ROLE_ADMIN
        ]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
    
    public function isUserInChannel(int $channelId, int $userId): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->getTableName()} 
                WHERE conversation_id = :conversation_id AND user_id = :user_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([
            'conversation_id' => $channelId,
            'user_id' => $userId
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }
    
    public function updateLastRead(int $channelId, int $userId, int $messageId): bool
    {
        $sql = "UPDATE {$this->getTableName()} 
                SET last_read_message_id = :message_id, last_seen = NOW() 
                WHERE conversation_id = :conversation_id AND user_id = :user_id";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            'message_id' => $messageId,
            'conversation_id' => $channelId,
            'user_id' => $userId
        ]);
    }
    
    public function findForDigest(string $frequency): array
    {
        // Email digest not supported in simplified chat
        return [];
    }
    
    public function findUsersToNotify(int $channelId, string $notificationType = 'all'): array
    {
        // In our simplified schema, all active participants get notifications
        $sql = "SELECT * FROM {$this->getTableName()} 
                WHERE conversation_id = :conversation_id 
                AND is_active = 1";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute(['conversation_id' => $channelId]);
        
        $results = [];
        while ($data = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $results[] = Participant::fromArray($this->mapDbToEntity($data));
        }
        
        return $results;
    }
}