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
    private ?string $address = null;
    private ?string $city = null;
    private ?string $state = null;
    private ?string $zip = null;

    public function __construct(
        private ?int $id,
        private int $sellerId,
        private string $title,
        private ?string $description,
        private string $location,
        private ?float $latitude,
        private ?float $longitude,
        private ?DateTimeInterface $previewStartDate,
        private ?DateTimeInterface $previewEndDate,
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

    public function getPreviewStartDate(): ?DateTimeInterface
    {
        return $this->previewStartDate;
    }

    public function getPreviewEndDate(): ?DateTimeInterface
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function getZip(): ?string
    {
        return $this->zip;
    }

    public function getSellerName(): ?string
    {
        return $this->stats['seller_name'] ?? null;
    }

    public function getItemCount(): int
    {
        return $this->stats['item_count'] ?? 0;
    }

    /**
     * Check if sale is in preview phase
     */
    public function isInPreview(): bool
    {
        if (!$this->previewStartDate || !$this->previewEndDate) {
            return false;
        }
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
        
        if ($this->previewStartDate && $now < $this->previewStartDate) {
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
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'preview_start_date' => $this->previewStartDate ? $this->previewStartDate->format('Y-m-d H:i:s') : null,
            'preview_end_date' => $this->previewEndDate ? $this->previewEndDate->format('Y-m-d H:i:s') : null,
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
        // Build location from address components if not provided
        $location = $data['location'] ?? 
                   ($data['address'] ?? '') . ', ' . 
                   ($data['city'] ?? '') . ', ' . 
                   ($data['state'] ?? '') . ' ' . 
                   ($data['zip'] ?? '');
        
        // Handle different date column names from database
        $previewStart = $data['preview_start_date'] ?? $data['preview_start'] ?? null;
        $previewEnd = $data['preview_end_date'] ?? $data['preview_end'] ?? null;
        $claimStart = $data['claim_start_date'] ?? $data['claim_start'] ?? null;
        $claimEnd = $data['claim_end_date'] ?? $data['claim_end'] ?? null;
        $pickupDate = $data['pickup_date'] ?? $data['pickup_start'] ?? $claimEnd ?? null;
        
        $sale = new self(
            id: $data['id'] ?? null,
            sellerId: (int)($data['seller_id'] ?? 0),
            title: $data['title'] ?? '',
            description: $data['description'] ?? null,
            location: $location,
            latitude: $data['latitude'] !== null ? (float)$data['latitude'] : null,
            longitude: $data['longitude'] !== null ? (float)$data['longitude'] : null,
            previewStartDate: $previewStart ? new DateTime($previewStart) : null,
            previewEndDate: $previewEnd ? new DateTime($previewEnd) : null,
            claimStartDate: $claimStart ? new DateTime($claimStart) : new DateTime(),
            claimEndDate: $claimEnd ? new DateTime($claimEnd) : new DateTime(),
            pickupDate: $pickupDate ? new DateTime($pickupDate) : new DateTime(),
            pickupInstructions: $data['pickup_instructions'] ?? null,
            status: $data['status'] ?? 'draft',
            qrCode: $data['qr_code'] ?? null,
            accessCode: $data['access_code'] ?? null,
            requireAuth: (bool)($data['require_auth'] ?? true),
            settings: isset($data['settings']) && is_string($data['settings']) ? json_decode($data['settings'], true) : [],
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : new DateTime(),
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
        
        // Set the separate address components
        $sale->address = $data['address'] ?? null;
        $sale->city = $data['city'] ?? null;
        $sale->state = $data['state'] ?? null;
        $sale->zip = $data['zip'] ?? null;
        
        return $sale;
    }
}