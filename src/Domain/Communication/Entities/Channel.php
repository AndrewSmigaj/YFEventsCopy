<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Entities;

use YFEvents\Domain\Common\EntityInterface;
use YFEvents\Domain\Communication\ValueObjects\ChannelType;

/**
 * Channel entity for communication groups
 */
class Channel implements EntityInterface
{
    private ?int $id;
    private string $name;
    private string $slug;
    private ?string $description;
    private ChannelType $type;
    private int $createdByUserId;
    private ?int $eventId;
    private ?int $shopId;
    private bool $isArchived;
    private array $settings;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    private ?int $messageCount;
    private ?int $participantCount;
    private ?\DateTimeImmutable $lastActivityAt;
    
    public function __construct(
        ?int $id,
        string $name,
        string $slug,
        ?string $description,
        string $type,
        int $createdByUserId,
        ?int $eventId = null,
        ?int $shopId = null,
        bool $isArchived = false,
        array $settings = [],
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?int $messageCount = 0,
        ?int $participantCount = 0,
        ?\DateTimeImmutable $lastActivityAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->slug = $slug;
        $this->description = $description;
        $this->type = new ChannelType($type);
        $this->createdByUserId = $createdByUserId;
        $this->eventId = $eventId;
        $this->shopId = $shopId;
        $this->isArchived = $isArchived;
        $this->settings = $settings;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
        $this->messageCount = $messageCount;
        $this->participantCount = $participantCount;
        $this->lastActivityAt = $lastActivityAt;
        
        $this->validate();
    }
    
    private function validate(): void
    {
        if (empty($this->name)) {
            throw new \InvalidArgumentException('Channel name cannot be empty');
        }
        
        if (empty($this->slug)) {
            throw new \InvalidArgumentException('Channel slug cannot be empty');
        }
        
        if ($this->type->isEvent() && !$this->eventId) {
            throw new \InvalidArgumentException('Event channels must have an event ID');
        }
        
        if ($this->type->isVendor() && !$this->shopId) {
            throw new \InvalidArgumentException('Vendor channels must have a shop ID');
        }
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getName(): string
    {
        return $this->name;
    }
    
    public function setName(string $name): void
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Channel name cannot be empty');
        }
        $this->name = $name;
        $this->touch();
    }
    
    public function getSlug(): string
    {
        return $this->slug;
    }
    
    public function getDescription(): ?string
    {
        return $this->description;
    }
    
    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }
    
    public function getType(): ChannelType
    {
        return $this->type;
    }
    
    public function getCreatedByUserId(): int
    {
        return $this->createdByUserId;
    }
    
    public function getEventId(): ?int
    {
        return $this->eventId;
    }
    
    public function getShopId(): ?int
    {
        return $this->shopId;
    }
    
    public function isArchived(): bool
    {
        return $this->isArchived;
    }
    
    public function archive(): void
    {
        $this->isArchived = true;
        $this->touch();
    }
    
    public function unarchive(): void
    {
        $this->isArchived = false;
        $this->touch();
    }
    
    public function getSettings(): array
    {
        return $this->settings;
    }
    
    public function setSetting(string $key, $value): void
    {
        $this->settings[$key] = $value;
        $this->touch();
    }
    
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
    
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
    
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
    
    public function getMessageCount(): ?int
    {
        return $this->messageCount;
    }
    
    public function incrementMessageCount(): void
    {
        $this->messageCount = ($this->messageCount ?? 0) + 1;
        $this->lastActivityAt = new \DateTimeImmutable();
        $this->touch();
    }
    
    public function getParticipantCount(): ?int
    {
        return $this->participantCount;
    }
    
    public function setParticipantCount(int $count): void
    {
        $this->participantCount = $count;
        $this->touch();
    }
    
    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }
    
    public function recordActivity(): void
    {
        $this->lastActivityAt = new \DateTimeImmutable();
        $this->touch();
    }
    
    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'type' => $this->type->getValue(),
            'created_by_user_id' => $this->createdByUserId,
            'event_id' => $this->eventId,
            'shop_id' => $this->shopId,
            'is_archived' => $this->isArchived ? 1 : 0,
            'settings' => json_encode($this->settings),
            'message_count' => $this->messageCount,
            'participant_count' => $this->participantCount,
            'last_activity_at' => $this->lastActivityAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }
    
    public static function fromArray(array $data): static
    {
        return new self(
            isset($data['id']) ? (int)$data['id'] : null,
            $data['name'],
            $data['slug'],
            $data['description'] ?? null,
            $data['type'],
            (int)$data['created_by_user_id'],
            isset($data['event_id']) ? (int)$data['event_id'] : null,
            isset($data['shop_id']) ? (int)$data['shop_id'] : null,
            (bool)($data['is_archived'] ?? false),
            is_string($data['settings'] ?? null) ? json_decode($data['settings'], true) : ($data['settings'] ?? []),
            isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
            isset($data['message_count']) ? (int)$data['message_count'] : 0,
            isset($data['participant_count']) ? (int)$data['participant_count'] : 0,
            isset($data['last_activity_at']) ? new \DateTimeImmutable($data['last_activity_at']) : null
        );
    }
}