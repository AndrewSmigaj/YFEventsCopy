<?php

declare(strict_types=1);

namespace YFEvents\Domain\Communication\Entities;

use YakimaFinds\Domain\Common\EntityInterface;

/**
 * Participant entity for channel membership
 */
class Participant implements EntityInterface
{
    private ?int $id;
    private int $channelId;
    private int $userId;
    private string $role;
    private \DateTimeImmutable $joinedAt;
    private ?int $lastReadMessageId;
    private ?\DateTimeImmutable $lastReadAt;
    private string $notificationPreference;
    private string $emailDigestFrequency;
    private bool $isMuted;
    
    public const ROLE_MEMBER = 'member';
    public const ROLE_ADMIN = 'admin';
    
    public const NOTIFICATION_ALL = 'all';
    public const NOTIFICATION_MENTIONS = 'mentions';
    public const NOTIFICATION_NONE = 'none';
    
    public const DIGEST_REALTIME = 'real-time';
    public const DIGEST_DAILY = 'daily';
    public const DIGEST_WEEKLY = 'weekly';
    public const DIGEST_NONE = 'none';
    
    public function __construct(
        ?int $id,
        int $channelId,
        int $userId,
        string $role = self::ROLE_MEMBER,
        ?\DateTimeImmutable $joinedAt = null,
        ?int $lastReadMessageId = null,
        ?\DateTimeImmutable $lastReadAt = null,
        string $notificationPreference = self::NOTIFICATION_ALL,
        string $emailDigestFrequency = self::DIGEST_DAILY,
        bool $isMuted = false
    ) {
        $this->id = $id;
        $this->channelId = $channelId;
        $this->userId = $userId;
        $this->role = $role;
        $this->joinedAt = $joinedAt ?? new \DateTimeImmutable();
        $this->lastReadMessageId = $lastReadMessageId;
        $this->lastReadAt = $lastReadAt;
        $this->notificationPreference = $notificationPreference;
        $this->emailDigestFrequency = $emailDigestFrequency;
        $this->isMuted = $isMuted;
        
        $this->validate();
    }
    
    private function validate(): void
    {
        if ($this->channelId <= 0) {
            throw new \InvalidArgumentException('Invalid channel ID');
        }
        
        if ($this->userId <= 0) {
            throw new \InvalidArgumentException('Invalid user ID');
        }
        
        if (!in_array($this->role, [self::ROLE_MEMBER, self::ROLE_ADMIN], true)) {
            throw new \InvalidArgumentException('Invalid participant role');
        }
        
        if (!in_array($this->notificationPreference, [
            self::NOTIFICATION_ALL,
            self::NOTIFICATION_MENTIONS,
            self::NOTIFICATION_NONE
        ], true)) {
            throw new \InvalidArgumentException('Invalid notification preference');
        }
        
        if (!in_array($this->emailDigestFrequency, [
            self::DIGEST_REALTIME,
            self::DIGEST_DAILY,
            self::DIGEST_WEEKLY,
            self::DIGEST_NONE
        ], true)) {
            throw new \InvalidArgumentException('Invalid email digest frequency');
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
    
    public function getRole(): string
    {
        return $this->role;
    }
    
    public function setRole(string $role): void
    {
        if (!in_array($role, [self::ROLE_MEMBER, self::ROLE_ADMIN], true)) {
            throw new \InvalidArgumentException('Invalid participant role');
        }
        
        $this->role = $role;
    }
    
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
    
    public function getJoinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }
    
    public function getLastReadMessageId(): ?int
    {
        return $this->lastReadMessageId;
    }
    
    public function markAsRead(int $messageId): void
    {
        $this->lastReadMessageId = $messageId;
        $this->lastReadAt = new \DateTimeImmutable();
    }
    
    public function getLastReadAt(): ?\DateTimeImmutable
    {
        return $this->lastReadAt;
    }
    
    public function getNotificationPreference(): string
    {
        return $this->notificationPreference;
    }
    
    public function setNotificationPreference(string $preference): void
    {
        if (!in_array($preference, [
            self::NOTIFICATION_ALL,
            self::NOTIFICATION_MENTIONS,
            self::NOTIFICATION_NONE
        ], true)) {
            throw new \InvalidArgumentException('Invalid notification preference');
        }
        
        $this->notificationPreference = $preference;
    }
    
    public function shouldNotifyAll(): bool
    {
        return $this->notificationPreference === self::NOTIFICATION_ALL && !$this->isMuted;
    }
    
    public function shouldNotifyMentions(): bool
    {
        return in_array($this->notificationPreference, [
            self::NOTIFICATION_ALL,
            self::NOTIFICATION_MENTIONS
        ], true) && !$this->isMuted;
    }
    
    public function getEmailDigestFrequency(): string
    {
        return $this->emailDigestFrequency;
    }
    
    public function setEmailDigestFrequency(string $frequency): void
    {
        if (!in_array($frequency, [
            self::DIGEST_REALTIME,
            self::DIGEST_DAILY,
            self::DIGEST_WEEKLY,
            self::DIGEST_NONE
        ], true)) {
            throw new \InvalidArgumentException('Invalid email digest frequency');
        }
        
        $this->emailDigestFrequency = $frequency;
    }
    
    public function wantsRealtimeEmails(): bool
    {
        return $this->emailDigestFrequency === self::DIGEST_REALTIME && !$this->isMuted;
    }
    
    public function wantsDailyDigest(): bool
    {
        return $this->emailDigestFrequency === self::DIGEST_DAILY && !$this->isMuted;
    }
    
    public function wantsWeeklyDigest(): bool
    {
        return $this->emailDigestFrequency === self::DIGEST_WEEKLY && !$this->isMuted;
    }
    
    public function isMuted(): bool
    {
        return $this->isMuted;
    }
    
    public function mute(): void
    {
        $this->isMuted = true;
    }
    
    public function unmute(): void
    {
        $this->isMuted = false;
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'channel_id' => $this->channelId,
            'user_id' => $this->userId,
            'role' => $this->role,
            'joined_at' => $this->joinedAt->format('Y-m-d H:i:s'),
            'last_read_message_id' => $this->lastReadMessageId,
            'last_read_at' => $this->lastReadAt?->format('Y-m-d H:i:s'),
            'notification_preference' => $this->notificationPreference,
            'email_digest_frequency' => $this->emailDigestFrequency,
            'is_muted' => $this->isMuted ? 1 : 0,
        ];
    }
    
    public static function fromArray(array $data): static
    {
        return new self(
            isset($data['id']) ? (int)$data['id'] : null,
            (int)$data['channel_id'],
            (int)$data['user_id'],
            $data['role'] ?? self::ROLE_MEMBER,
            isset($data['joined_at']) ? new \DateTimeImmutable($data['joined_at']) : null,
            isset($data['last_read_message_id']) ? (int)$data['last_read_message_id'] : null,
            isset($data['last_read_at']) ? new \DateTimeImmutable($data['last_read_at']) : null,
            $data['notification_preference'] ?? self::NOTIFICATION_ALL,
            $data['email_digest_frequency'] ?? self::DIGEST_DAILY,
            (bool)($data['is_muted'] ?? false)
        );
    }
}