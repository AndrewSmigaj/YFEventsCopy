<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Claims;

use YakimaFinds\Domain\Common\EntityInterface;
use DateTimeInterface;
use DateTime;

class Offer implements EntityInterface
{
    public function __construct(
        private ?int $id,
        private int $itemId,
        private int $buyerId,
        private float $amount,
        private string $status,
        private ?string $buyerName,
        private ?string $buyerEmail,
        private ?string $buyerPhone,
        private ?string $message,
        private ?string $sellerNotes,
        private ?DateTimeInterface $acceptedAt,
        private ?DateTimeInterface $rejectedAt,
        private DateTimeInterface $createdAt,
        private ?DateTimeInterface $updatedAt = null
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemId(): int
    {
        return $this->itemId;
    }

    public function getBuyerId(): int
    {
        return $this->buyerId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getBuyerName(): ?string
    {
        return $this->buyerName;
    }

    public function getBuyerEmail(): ?string
    {
        return $this->buyerEmail;
    }

    public function getBuyerPhone(): ?string
    {
        return $this->buyerPhone;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getSellerNotes(): ?string
    {
        return $this->sellerNotes;
    }

    public function getAcceptedAt(): ?DateTimeInterface
    {
        return $this->acceptedAt;
    }

    public function getRejectedAt(): ?DateTimeInterface
    {
        return $this->rejectedAt;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Check if offer is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if offer is accepted
     */
    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /**
     * Check if offer is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if offer is withdrawn
     */
    public function isWithdrawn(): bool
    {
        return $this->status === 'withdrawn';
    }

    /**
     * Accept the offer
     */
    public function accept(?string $sellerNotes = null): self
    {
        return $this->update([
            'status' => 'accepted',
            'accepted_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'seller_notes' => $sellerNotes
        ]);
    }

    /**
     * Reject the offer
     */
    public function reject(?string $sellerNotes = null): self
    {
        return $this->update([
            'status' => 'rejected',
            'rejected_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'seller_notes' => $sellerNotes
        ]);
    }

    /**
     * Withdraw the offer
     */
    public function withdraw(): self
    {
        if (!$this->isPending()) {
            throw new \RuntimeException('Only pending offers can be withdrawn');
        }

        return $this->update(['status' => 'withdrawn']);
    }

    /**
     * Update buyer contact info
     */
    public function updateBuyerInfo(array $info): self
    {
        return $this->update([
            'buyer_name' => $info['name'] ?? $this->buyerName,
            'buyer_email' => $info['email'] ?? $this->buyerEmail,
            'buyer_phone' => $info['phone'] ?? $this->buyerPhone
        ]);
    }

    /**
     * Get display amount (for price range display)
     */
    public function getDisplayAmount(float $increment = 5.0): string
    {
        $lower = floor($this->amount / $increment) * $increment;
        $upper = $lower + $increment;
        
        return sprintf('$%.0f - $%.0f', $lower, $upper);
    }

    /**
     * Update offer data
     */
    public function update(array $data): self
    {
        return new self(
            id: $this->id,
            itemId: $data['item_id'] ?? $this->itemId,
            buyerId: $data['buyer_id'] ?? $this->buyerId,
            amount: $data['amount'] ?? $this->amount,
            status: $data['status'] ?? $this->status,
            buyerName: $data['buyer_name'] ?? $this->buyerName,
            buyerEmail: $data['buyer_email'] ?? $this->buyerEmail,
            buyerPhone: $data['buyer_phone'] ?? $this->buyerPhone,
            message: $data['message'] ?? $this->message,
            sellerNotes: $data['seller_notes'] ?? $this->sellerNotes,
            acceptedAt: isset($data['accepted_at']) ? new DateTime($data['accepted_at']) : $this->acceptedAt,
            rejectedAt: isset($data['rejected_at']) ? new DateTime($data['rejected_at']) : $this->rejectedAt,
            createdAt: $this->createdAt,
            updatedAt: new DateTime()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->itemId,
            'buyer_id' => $this->buyerId,
            'amount' => $this->amount,
            'status' => $this->status,
            'buyer_name' => $this->buyerName,
            'buyer_email' => $this->buyerEmail,
            'buyer_phone' => $this->buyerPhone,
            'message' => $this->message,
            'seller_notes' => $this->sellerNotes,
            'accepted_at' => $this->acceptedAt?->format('Y-m-d H:i:s'),
            'rejected_at' => $this->rejectedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'display_amount' => $this->getDisplayAmount()
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'] ?? null,
            itemId: $data['item_id'],
            buyerId: $data['buyer_id'],
            amount: (float)$data['amount'],
            status: $data['status'] ?? 'pending',
            buyerName: $data['buyer_name'] ?? null,
            buyerEmail: $data['buyer_email'] ?? null,
            buyerPhone: $data['buyer_phone'] ?? null,
            message: $data['message'] ?? null,
            sellerNotes: $data['seller_notes'] ?? null,
            acceptedAt: isset($data['accepted_at']) ? new DateTime($data['accepted_at']) : null,
            rejectedAt: isset($data['rejected_at']) ? new DateTime($data['rejected_at']) : null,
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : new DateTime(),
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
    }
}