<?php

declare(strict_types=1);

namespace YFEvents\Domain\Shops;

use YFEvents\Domain\Common\EntityInterface;
use DateTime;
use DateTimeInterface;

/**
 * Shop domain entity
 */
class Shop implements EntityInterface
{
    public function __construct(
        private ?int $id = null,
        private string $name = '',
        private ?string $description = null,
        private string $address = '',
        private ?float $latitude = null,
        private ?float $longitude = null,
        private ?string $phone = null,
        private ?string $email = null,
        private ?string $website = null,
        private ?string $imageUrl = null,
        private ?int $categoryId = null,
        private array $hours = [],
        private array $operatingHours = [],
        private array $paymentMethods = [],
        private array $amenities = [],
        private bool $featured = false,
        private bool $verified = false,
        private ?int $ownerId = null,
        private string $status = 'pending',
        private ?DateTimeInterface $createdAt = null,
        private ?DateTimeInterface $updatedAt = null
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function getCategoryId(): ?int
    {
        return $this->categoryId;
    }

    public function getHours(): array
    {
        return $this->hours;
    }

    public function getOperatingHours(): array
    {
        return $this->operatingHours;
    }

    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    public function getAmenities(): array
    {
        return $this->amenities;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function getOwnerId(): ?int
    {
        return $this->ownerId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * Business logic methods
     */

    public function approve(): void
    {
        $this->status = 'active';
        $this->updatedAt = new DateTime();
    }

    public function reject(): void
    {
        $this->status = 'inactive';
        $this->updatedAt = new DateTime();
    }

    public function verify(): void
    {
        $this->verified = true;
        $this->updatedAt = new DateTime();
    }

    public function unverify(): void
    {
        $this->verified = false;
        $this->updatedAt = new DateTime();
    }

    public function feature(): void
    {
        $this->featured = true;
        $this->updatedAt = new DateTime();
    }

    public function unfeature(): void
    {
        $this->featured = false;
        $this->updatedAt = new DateTime();
    }

    public function deactivate(): void
    {
        $this->status = 'inactive';
        $this->updatedAt = new DateTime();
    }

    public function activate(): void
    {
        $this->status = 'active';
        $this->updatedAt = new DateTime();
    }

    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function isOpen(): bool
    {
        return $this->status === 'active';
    }

    public function acceptsPaymentMethod(string $method): bool
    {
        return in_array(strtolower($method), array_map('strtolower', $this->paymentMethods));
    }

    public function hasAmenity(string $amenity): bool
    {
        return in_array(strtolower($amenity), array_map('strtolower', $this->amenities));
    }

    public function isOpenOnDay(string $day): bool
    {
        $dayLower = strtolower($day);
        
        // Check operating hours first
        if (!empty($this->operatingHours) && isset($this->operatingHours[$dayLower])) {
            $dayHours = $this->operatingHours[$dayLower];
            return !empty($dayHours) && $dayHours !== 'closed';
        }

        // Fallback to legacy hours format
        if (!empty($this->hours) && isset($this->hours[$dayLower])) {
            $dayHours = $this->hours[$dayLower];
            return !empty($dayHours) && $dayHours !== 'closed';
        }

        return false;
    }

    public function getFormattedHours(): array
    {
        $formatted = [];
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($daysOfWeek as $day) {
            $hours = $this->operatingHours[$day] ?? $this->hours[$day] ?? 'closed';
            $formatted[ucfirst($day)] = $hours;
        }

        return $formatted;
    }

    /**
     * Update shop properties
     */
    public function update(
        ?string $name = null,
        ?string $description = null,
        ?string $address = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $website = null,
        ?string $imageUrl = null,
        ?int $categoryId = null,
        ?array $hours = null,
        ?array $operatingHours = null,
        ?array $paymentMethods = null,
        ?array $amenities = null,
        ?bool $featured = null,
        ?bool $verified = null,
        ?int $ownerId = null,
        ?string $status = null
    ): void {
        if ($name !== null) $this->name = $name;
        if ($description !== null) $this->description = $description;
        if ($address !== null) $this->address = $address;
        if ($latitude !== null) $this->latitude = $latitude;
        if ($longitude !== null) $this->longitude = $longitude;
        if ($phone !== null) $this->phone = $phone;
        if ($email !== null) $this->email = $email;
        if ($website !== null) $this->website = $website;
        if ($imageUrl !== null) $this->imageUrl = $imageUrl;
        if ($categoryId !== null) $this->categoryId = $categoryId;
        if ($hours !== null) $this->hours = $hours;
        if ($operatingHours !== null) $this->operatingHours = $operatingHours;
        if ($paymentMethods !== null) $this->paymentMethods = $paymentMethods;
        if ($amenities !== null) $this->amenities = $amenities;
        if ($featured !== null) $this->featured = $featured;
        if ($verified !== null) $this->verified = $verified;
        if ($ownerId !== null) $this->ownerId = $ownerId;
        if ($status !== null) $this->status = $status;

        $this->updatedAt = new DateTime();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'image_url' => $this->imageUrl,
            'category_id' => $this->categoryId,
            'hours' => json_encode($this->hours),
            'operating_hours' => json_encode($this->operatingHours),
            'payment_methods' => json_encode($this->paymentMethods),
            'amenities' => json_encode($this->amenities),
            'featured' => $this->featured,
            'verified' => $this->verified,
            'owner_id' => $this->ownerId,
            'status' => $this->status,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Parse JSON field from database
     */
    private static function parseJsonField($value): array
    {
        if (empty($value)) {
            return [];
        }
        
        if (is_array($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        
        return [];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            id: $data['id'] ?? null,
            name: $data['name'] ?? '',
            description: $data['description'] ?? null,
            address: $data['address'] ?? '',
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            website: $data['website'] ?? null,
            imageUrl: $data['image_url'] ?? null,
            categoryId: isset($data['category_id']) ? (int) $data['category_id'] : null,
            hours: self::parseJsonField($data['hours'] ?? null),
            operatingHours: self::parseJsonField($data['operating_hours'] ?? null),
            paymentMethods: self::parseJsonField($data['payment_methods'] ?? null),
            amenities: self::parseJsonField($data['amenities'] ?? null),
            featured: (bool) ($data['featured'] ?? false),
            verified: (bool) ($data['verified'] ?? false),
            ownerId: isset($data['owner_id']) ? (int) $data['owner_id'] : null,
            status: $data['status'] ?? 'pending',
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
    }
}