<?php

declare(strict_types=1);

namespace YFEvents\Domain\Claims;

use YFEvents\Domain\Common\EntityInterface;
use DateTimeInterface;
use DateTime;

class Sale implements EntityInterface
{
    private array $items = [];
    private array $stats = [];

    public function __construct(
        private ?int $id,
        private int $sellerId,
        private string $title,
        private ?string $description,
        private string $location,
        private ?float $latitude,
        private ?float $longitude,
        private DateTimeInterface $previewStartDate,
        private DateTimeInterface $previewEndDate,
        private DateTimeInterface $claimStartDate,
        private DateTimeInterface $claimEndDate,
        private DateTimeInterface $pickupDate,
        private ?string $pickupInstructions,
        private string $status,
        private ?string $qrCode,
        private ?string $accessCode,
        private bool $requireAuth,
        private array $settings,
        private DateTimeInterface $createdAt,
        private ?DateTimeInterface $updatedAt = null
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSellerId(): int
    {
        return $this->sellerId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function getPreviewStartDate(): DateTimeInterface
    {
        return $this->previewStartDate;
    }

    public function getPreviewEndDate(): DateTimeInterface
    {
        return $this->previewEndDate;
    }

    public function getClaimStartDate(): DateTimeInterface
    {
        return $this->claimStartDate;
    }

    public function getClaimEndDate(): DateTimeInterface
    {
        return $this->claimEndDate;
    }

    public function getPickupDate(): DateTimeInterface
    {
        return $this->pickupDate;
    }

    public function getPickupInstructions(): ?string
    {
        return $this->pickupInstructions;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getQrCode(): ?string
    {
        return $this->qrCode;
    }

    public function getAccessCode(): ?string
    {
        return $this->accessCode;
    }

    public function requiresAuth(): bool
    {
        return $this->requireAuth;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Set items for the sale
     */
    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    /**
     * Set statistics for the sale
     */
    public function setStats(array $stats): void
    {
        $this->stats = $stats;
    }

    /**
     * Check if sale is in preview phase
     */
    public function isInPreview(): bool
    {
        $now = new DateTime();
        return $now >= $this->previewStartDate && $now <= $this->previewEndDate;
    }

    /**
     * Check if sale is in claim phase
     */
    public function isInClaimPhase(): bool
    {
        $now = new DateTime();
        return $now >= $this->claimStartDate && $now <= $this->claimEndDate;
    }

    /**
     * Check if sale is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && ($this->isInPreview() || $this->isInClaimPhase());
    }

    /**
     * Check if sale has ended
     */
    public function hasEnded(): bool
    {
        $now = new DateTime();
        return $now > $this->claimEndDate;
    }

    /**
     * Get current phase
     */
    public function getCurrentPhase(): string
    {
        $now = new DateTime();
        
        if ($now < $this->previewStartDate) {
            return 'upcoming';
        } elseif ($this->isInPreview()) {
            return 'preview';
        } elseif ($this->isInClaimPhase()) {
            return 'claiming';
        } elseif ($now < $this->pickupDate) {
            return 'pending_pickup';
        } else {
            return 'completed';
        }
    }

    /**
     * Activate the sale
     */
    public function activate(): self
    {
        return $this->update(['status' => 'active']);
    }

    /**
     * Pause the sale
     */
    public function pause(): self
    {
        return $this->update(['status' => 'paused']);
    }

    /**
     * Cancel the sale
     */
    public function cancel(): self
    {
        return $this->update(['status' => 'cancelled']);
    }

    /**
     * Complete the sale
     */
    public function complete(): self
    {
        return $this->update(['status' => 'completed']);
    }

    /**
     * Generate access code
     */
    public function generateAccessCode(): string
    {
        $code = strtoupper(substr(md5(uniqid()), 0, 8));
        $this->accessCode = $code;
        return $code;
    }

    /**
     * Update sale data
     */
    public function update(array $data): self
    {
        return new self(
            id: $this->id,
            sellerId: $data['seller_id'] ?? $this->sellerId,
            title: $data['title'] ?? $this->title,
            description: $data['description'] ?? $this->description,
            location: $data['location'] ?? $this->location,
            latitude: $data['latitude'] ?? $this->latitude,
            longitude: $data['longitude'] ?? $this->longitude,
            previewStartDate: isset($data['preview_start_date']) ? new DateTime($data['preview_start_date']) : $this->previewStartDate,
            previewEndDate: isset($data['preview_end_date']) ? new DateTime($data['preview_end_date']) : $this->previewEndDate,
            claimStartDate: isset($data['claim_start_date']) ? new DateTime($data['claim_start_date']) : $this->claimStartDate,
            claimEndDate: isset($data['claim_end_date']) ? new DateTime($data['claim_end_date']) : $this->claimEndDate,
            pickupDate: isset($data['pickup_date']) ? new DateTime($data['pickup_date']) : $this->pickupDate,
            pickupInstructions: $data['pickup_instructions'] ?? $this->pickupInstructions,
            status: $data['status'] ?? $this->status,
            qrCode: $data['qr_code'] ?? $this->qrCode,
            accessCode: $data['access_code'] ?? $this->accessCode,
            requireAuth: $data['require_auth'] ?? $this->requireAuth,
            settings: $data['settings'] ?? $this->settings,
            createdAt: $this->createdAt,
            updatedAt: new DateTime()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'seller_id' => $this->sellerId,
            'title' => $this->title,
            'description' => $this->description,
            'location' => $this->location,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'preview_start_date' => $this->previewStartDate->format('Y-m-d H:i:s'),
            'preview_end_date' => $this->previewEndDate->format('Y-m-d H:i:s'),
            'claim_start_date' => $this->claimStartDate->format('Y-m-d H:i:s'),
            'claim_end_date' => $this->claimEndDate->format('Y-m-d H:i:s'),
            'pickup_date' => $this->pickupDate->format('Y-m-d H:i:s'),
            'pickup_instructions' => $this->pickupInstructions,
            'status' => $this->status,
            'qr_code' => $this->qrCode,
            'access_code' => $this->accessCode,
            'require_auth' => $this->requireAuth,
            'settings' => $this->settings,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'items' => array_map(fn($item) => $item->toArray(), $this->items),
            'stats' => $this->stats,
            'current_phase' => $this->getCurrentPhase()
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'] ?? null,
            sellerId: $data['seller_id'],
            title: $data['title'],
            description: $data['description'] ?? null,
            location: $data['location'],
            latitude: $data['latitude'] ?? null,
            longitude: $data['longitude'] ?? null,
            previewStartDate: new DateTime($data['preview_start_date']),
            previewEndDate: new DateTime($data['preview_end_date']),
            claimStartDate: new DateTime($data['claim_start_date']),
            claimEndDate: new DateTime($data['claim_end_date']),
            pickupDate: new DateTime($data['pickup_date']),
            pickupInstructions: $data['pickup_instructions'] ?? null,
            status: $data['status'] ?? 'draft',
            qrCode: $data['qr_code'] ?? null,
            accessCode: $data['access_code'] ?? null,
            requireAuth: $data['require_auth'] ?? true,
            settings: $data['settings'] ?? [],
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : new DateTime(),
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
    }
}