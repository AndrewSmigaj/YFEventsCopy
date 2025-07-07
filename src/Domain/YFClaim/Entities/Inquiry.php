<?php

declare(strict_types=1);

namespace YFEvents\Domain\YFClaim\Entities;

use YFEvents\Domain\Common\EntityInterface;

/**
 * Inquiry entity for buyer contact forms
 */
class Inquiry implements EntityInterface
{
    // Status constants
    public const STATUS_NEW = 'new';
    public const STATUS_READ = 'read';
    public const STATUS_RESPONDED = 'responded';
    public const STATUS_CLOSED = 'closed';
    
    private ?int $id;
    private ?int $saleId;
    private ?int $itemId;
    private int $sellerUserId;
    private string $buyerName;
    private string $buyerEmail;
    private ?string $buyerPhone;
    private ?string $subject;
    private string $message;
    private string $status;
    private ?string $ipAddress;
    private ?string $userAgent;
    private ?string $adminNotes;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $respondedAt;
    
    public function __construct(
        ?int $id,
        ?int $saleId,
        ?int $itemId,
        int $sellerUserId,
        string $buyerName,
        string $buyerEmail,
        string $message,
        ?string $buyerPhone = null,
        ?string $subject = null,
        string $status = self::STATUS_NEW,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $adminNotes = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null,
        ?\DateTimeImmutable $respondedAt = null
    ) {
        $this->id = $id;
        $this->saleId = $saleId;
        $this->itemId = $itemId;
        $this->sellerUserId = $sellerUserId;
        $this->buyerName = $buyerName;
        $this->buyerEmail = $buyerEmail;
        $this->buyerPhone = $buyerPhone;
        $this->subject = $subject;
        $this->message = $message;
        $this->status = $status;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->adminNotes = $adminNotes;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
        $this->respondedAt = $respondedAt;
        
        $this->validate();
    }
    
    private function validate(): void
    {
        if (empty($this->buyerName)) {
            throw new \InvalidArgumentException('Buyer name is required');
        }
        
        if (empty($this->buyerEmail)) {
            throw new \InvalidArgumentException('Buyer email is required');
        }
        
        if (!filter_var($this->buyerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
        
        if (empty($this->message)) {
            throw new \InvalidArgumentException('Message is required');
        }
        
        if ($this->sellerUserId <= 0) {
            throw new \InvalidArgumentException('Invalid seller user ID');
        }
        
        if (!in_array($this->status, [self::STATUS_NEW, self::STATUS_READ, self::STATUS_RESPONDED, self::STATUS_CLOSED])) {
            throw new \InvalidArgumentException('Invalid status');
        }
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }
    
    public function getSaleId(): ?int
    {
        return $this->saleId;
    }
    
    public function getItemId(): ?int
    {
        return $this->itemId;
    }
    
    public function getSellerUserId(): int
    {
        return $this->sellerUserId;
    }
    
    public function getBuyerName(): string
    {
        return $this->buyerName;
    }
    
    public function getBuyerEmail(): string
    {
        return $this->buyerEmail;
    }
    
    public function getBuyerPhone(): ?string
    {
        return $this->buyerPhone;
    }
    
    public function getSubject(): ?string
    {
        return $this->subject;
    }
    
    public function getMessage(): string
    {
        return $this->message;
    }
    
    public function getStatus(): string
    {
        return $this->status;
    }
    
    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }
    
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
    
    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }
    
    public function setAdminNotes(?string $notes): void
    {
        $this->adminNotes = $notes;
        $this->touch();
    }
    
    public function isNew(): bool
    {
        return $this->status === self::STATUS_NEW;
    }
    
    public function isRead(): bool
    {
        return $this->status === self::STATUS_READ;
    }
    
    public function isResponded(): bool
    {
        return $this->status === self::STATUS_RESPONDED;
    }
    
    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
    
    public function markAsRead(): void
    {
        if ($this->status === self::STATUS_NEW) {
            $this->status = self::STATUS_READ;
            $this->touch();
        }
    }
    
    public function markAsResponded(): void
    {
        $this->status = self::STATUS_RESPONDED;
        $this->respondedAt = new \DateTimeImmutable();
        $this->touch();
    }
    
    public function markAsClosed(): void
    {
        $this->status = self::STATUS_CLOSED;
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
    
    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }
    
    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->saleId,
            'item_id' => $this->itemId,
            'seller_user_id' => $this->sellerUserId,
            'buyer_name' => $this->buyerName,
            'buyer_email' => $this->buyerEmail,
            'buyer_phone' => $this->buyerPhone,
            'subject' => $this->subject,
            'message' => $this->message,
            'status' => $this->status,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'admin_notes' => $this->adminNotes,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'responded_at' => $this->respondedAt?->format('Y-m-d H:i:s'),
        ];
    }
    
    public static function fromArray(array $data): static
    {
        return new self(
            isset($data['id']) ? (int)$data['id'] : null,
            isset($data['sale_id']) ? (int)$data['sale_id'] : null,
            isset($data['item_id']) ? (int)$data['item_id'] : null,
            (int)$data['seller_user_id'],
            $data['buyer_name'],
            $data['buyer_email'],
            $data['message'],
            $data['buyer_phone'] ?? null,
            $data['subject'] ?? null,
            $data['status'] ?? self::STATUS_NEW,
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null,
            $data['admin_notes'] ?? null,
            isset($data['created_at']) ? new \DateTimeImmutable($data['created_at']) : null,
            isset($data['updated_at']) ? new \DateTimeImmutable($data['updated_at']) : null,
            isset($data['responded_at']) && $data['responded_at'] ? new \DateTimeImmutable($data['responded_at']) : null
        );
    }
}