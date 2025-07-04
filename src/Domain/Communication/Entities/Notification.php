<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Entities;

use YFEvents\Domain\Common\EntityInterface;

/**
 * Notification entity for tracking unread messages in chat
 * Simplified version without notification types - just tracks what messages users haven't read
 */
class Notification implements EntityInterface
{
    private ?int $id;
    private int $userId;
    private int $channelId;
    private int $messageId;
    private bool $isRead;
    private ?\DateTimeImmutable $readAt;
    private \DateTimeImmutable $createdAt;
    
    public function __construct(
        ?int $id,
        int $userId,
        int $channelId,
        int $messageId,
        bool $isRead = false,
        ?\DateTimeImmutable $readAt = null,
        ?\DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->channelId = $channelId;
        $this->messageId = $messageId;
        $this->isRead = $isRead;
        $this->readAt = $readAt;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        
        $this->validate();
    }
    
    private function validate(): void
    {
        if ($this->userId <= 0) {
            throw new \InvalidArgumentException('Invalid user ID');
        }
        
        if ($this->channelId <= 0) {
            throw new \InvalidArgumentException('Invalid channel ID');
        }
        
        if ($this->messageId <= 0) {
            throw new \InvalidArgumentException('Invalid message ID');
        }
        
        if ($this->isRead && !$this->readAt) {
            $this->readAt = new \DateTimeImmutable();
        }
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getUserId(): int
    {
        return $this->userId;
    }
    
    public function getChannelId(): int
    {
        return $this->channelId;
    }
    
    public function getMessageId(): int
    {
        return $this->messageId;
    }
    
    public function isRead(): bool
    {
        return $this->isRead;
    }
    
    public function markAsRead(): void
    {
        if (!$this->isRead) {
            $this->isRead = true;
            $this->readAt = new \DateTimeImmutable();
        }
    }
    
    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }
    
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    
    /**
     * Get notification type - always returns 'message' for our simplified system
     * This method exists for compatibility with NotificationRepository
     */
    public function getType(): string
    {
        return 'message';
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'channel_id' => $this->channelId,
            'message_id' => $this->messageId,
            'is_read' => $this->isRead ? 1 : 0,
            'read_at' => $this->readAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
    
    public static function fromArray(array $data): static
    {
        return new self(
            isset($data['id']) ? (int)$data['id'] : null,
            (int)$data['user_id'],
            (int)$data['channel_id'],
            (int)$data['message_id'],
            (bool)($data['is_read'] ?? false),
            isset($data['read_at']) && $data['read_at'] ? new \DateTimeImmutable($data['read_at']) : null,
            isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null
        );
    }
}