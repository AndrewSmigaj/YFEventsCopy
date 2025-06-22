<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Entities;

use YFEvents\Domain\Common\EntityInterface;
use YFEvents\Domain\Communication\ValueObjects\MessageType;

/**
 * Message entity for channel communications
 */
class Message implements EntityInterface
{
    private ?int $id;
    private int $channelId;
    private int $userId;
    private ?int $parentMessageId;
    private string $content;
    private MessageType $contentType;
    private bool $isPinned;
    private bool $isEdited;
    private bool $isDeleted;
    private ?int $yfclaimItemId;
    private array $metadata;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $deletedAt;
    private ?string $emailMessageId;
    private int $replyCount;
    private int $reactionCount;
    
    public function __construct(
        ?int $id,
        int $channelId,
        int $userId,
        string $content,
        string $contentType = 'text',
        ?int $parentMessageId = null,
        ?int $yfclaimItemId = null,
        bool $isPinned = false,
        bool $isEdited = false,
        bool $isDeleted = false,
        array $metadata = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $deletedAt = null,
        ?string $emailMessageId = null,
        int $replyCount = 0,
        int $reactionCount = 0
    ) {
        $this->id = $id;
        $this->channelId = $channelId;
        $this->userId = $userId;
        $this->content = $content;
        $this->contentType = new MessageType($contentType);
        $this->parentMessageId = $parentMessageId;
        $this->yfclaimItemId = $yfclaimItemId;
        $this->isPinned = $isPinned;
        $this->isEdited = $isEdited;
        $this->isDeleted = $isDeleted;
        $this->metadata = $metadata;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
        $this->deletedAt = $deletedAt;
        $this->emailMessageId = $emailMessageId;
        $this->replyCount = $replyCount;
        $this->reactionCount = $reactionCount;
        
        $this->validate();
    }
    
    private function validate(): void
    {
        if (empty($this->content)) {
            throw new \InvalidArgumentException('Message content cannot be empty');
        }
        
        if ($this->channelId <= 0) {
            throw new \InvalidArgumentException('Invalid channel ID');
        }
        
        if ($this->userId <= 0) {
            throw new \InvalidArgumentException('Invalid user ID');
        }
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getChannelId(): int
    {
        return $this->channelId;
    }
    
    public function getUserId(): int
    {
        return $this->userId;
    }
    
    public function getParentMessageId(): ?int
    {
        return $this->parentMessageId;
    }
    
    public function getContent(): string
    {
        return $this->content;
    }
    
    public function setContent(string $content): void
    {
        if (empty($content)) {
            throw new \InvalidArgumentException('Message content cannot be empty');
        }
        
        if ($this->isDeleted) {
            throw new \RuntimeException('Cannot edit deleted message');
        }
        
        $this->content = $content;
        $this->isEdited = true;
        $this->touch();
    }
    
    public function getContentType(): MessageType
    {
        return $this->contentType;
    }
    
    public function isPinned(): bool
    {
        return $this->isPinned;
    }
    
    public function pin(): void
    {
        $this->isPinned = true;
        $this->touch();
    }
    
    public function unpin(): void
    {
        $this->isPinned = false;
        $this->touch();
    }
    
    public function isEdited(): bool
    {
        return $this->isEdited;
    }
    
    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }
    
    public function delete(): void
    {
        $this->isDeleted = true;
        $this->deletedAt = new \DateTimeImmutable();
        $this->touch();
    }
    
    public function getYfclaimItemId(): ?int
    {
        return $this->yfclaimItemId;
    }
    
    public function setYfclaimItemId(?int $itemId): void
    {
        $this->yfclaimItemId = $itemId;
        $this->touch();
    }
    
    public function getMetadata(): array
    {
        return $this->metadata;
    }
    
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
        $this->touch();
    }
    
    public function addMetadata(string $key, $value): void
    {
        $this->metadata[$key] = $value;
        $this->touch();
    }
    
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
    
    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }
    
    public function getEmailMessageId(): ?string
    {
        return $this->emailMessageId;
    }
    
    public function setEmailMessageId(string $emailMessageId): void
    {
        $this->emailMessageId = $emailMessageId;
        $this->touch();
    }
    
    public function getReplyCount(): int
    {
        return $this->replyCount;
    }
    
    public function incrementReplyCount(): void
    {
        $this->replyCount++;
        $this->touch();
    }
    
    public function getReactionCount(): int
    {
        return $this->reactionCount;
    }
    
    public function incrementReactionCount(): void
    {
        $this->reactionCount++;
        $this->touch();
    }
    
    public function decrementReactionCount(): void
    {
        if ($this->reactionCount > 0) {
            $this->reactionCount--;
            $this->touch();
        }
    }
    
    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channelId,
            'user_id' => $this->userId,
            'parent_message_id' => $this->parentMessageId,
            'content' => $this->content,
            'content_type' => $this->contentType->getValue(),
            'is_pinned' => $this->isPinned ? 1 : 0,
            'is_edited' => $this->isEdited ? 1 : 0,
            'is_deleted' => $this->isDeleted ? 1 : 0,
            'yfclaim_item_id' => $this->yfclaimItemId,
            'metadata' => json_encode($this->metadata),
            'email_message_id' => $this->emailMessageId,
            'reply_count' => $this->replyCount,
            'reaction_count' => $this->reactionCount,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'deleted_at' => $this->deletedAt?->format('Y-m-d H:i:s'),
        ];
    }
    
    public static function fromArray(array $data): static
    {
        return new self(
            isset($data['id']) ? (int)$data['id'] : null,
            (int)$data['channel_id'],
            (int)$data['user_id'],
            $data['content'],
            $data['content_type'] ?? 'text',
            isset($data['parent_message_id']) ? (int)$data['parent_message_id'] : null,
            isset($data['yfclaim_item_id']) ? (int)$data['yfclaim_item_id'] : null,
            (bool)($data['is_pinned'] ?? false),
            (bool)($data['is_edited'] ?? false),
            (bool)($data['is_deleted'] ?? false),
            is_string($data['metadata'] ?? null) ? json_decode($data['metadata'], true) : ($data['metadata'] ?? []),
            isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
            isset($data['deleted_at']) ? new \DateTimeImmutable($data['deleted_at']) : null,
            $data['email_message_id'] ?? null,
            (int)($data['reply_count'] ?? 0),
            (int)($data['reaction_count'] ?? 0)
        );
    }
}