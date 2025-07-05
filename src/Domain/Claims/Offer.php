<?php

declare(strict_types=1);

namespace YFEvents\Domain\Claims;

/**
 * Offer entity
 * Note: The offer/bidding system has been removed from YFClaim
 * This entity exists only for backward compatibility
 */
class Offer
{
    private ?int $id;
    private int $itemId;
    private int $buyerId;
    private float $amount;
    private ?string $message;
    private string $status;
    private ?\DateTime $createdAt;
    private ?\DateTime $updatedAt;

    public function __construct(
        ?int $id = null,
        int $itemId = 0,
        int $buyerId = 0,
        float $amount = 0.0,
        ?string $message = null,
        string $status = 'pending',
        ?\DateTime $createdAt = null,
        ?\DateTime $updatedAt = null
    ) {
        $this->id = $id;
        $this->itemId = $itemId;
        $this->buyerId = $buyerId;
        $this->amount = $amount;
        $this->message = $message;
        $this->status = $status;
        $this->createdAt = $createdAt ?? new \DateTime();
        $this->updatedAt = $updatedAt;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'] ?? null,
            itemId: $data['item_id'] ?? 0,
            buyerId: $data['buyer_id'] ?? 0,
            amount: (float)($data['amount'] ?? 0),
            message: $data['message'] ?? null,
            status: $data['status'] ?? 'pending',
            createdAt: isset($data['created_at']) ? new \DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new \DateTime($data['updated_at']) : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->itemId,
            'buyer_id' => $this->buyerId,
            'amount' => $this->amount,
            'message' => $this->message,
            'status' => $this->status,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s')
        ];
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getItemId(): int { return $this->itemId; }
    public function getBuyerId(): int { return $this->buyerId; }
    public function getAmount(): float { return $this->amount; }
    public function getMessage(): ?string { return $this->message; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }

    public function accept(?string $notes = null): self
    {
        $this->status = 'accepted';
        $this->updatedAt = new \DateTime();
        return $this;
    }

    public function reject(?string $reason = null): self
    {
        $this->status = 'rejected';
        $this->updatedAt = new \DateTime();
        return $this;
    }
}