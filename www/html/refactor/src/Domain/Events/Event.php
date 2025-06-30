<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Events;

use YakimaFinds\Domain\Common\EntityInterface;
use DateTimeInterface;
use DateTime;

/**
 * Event domain entity
 */
class Event implements EntityInterface
{
    public function __construct(
        private ?int $id,
        private string $title,
        private ?string $description,
        private DateTimeInterface $startDateTime,
        private ?DateTimeInterface $endDateTime,
        private ?string $location,
        private ?string $address,
        private ?float $latitude,
        private ?float $longitude,
        private ?array $contactInfo,
        private ?string $externalUrl,
        private ?int $sourceId,
        private ?int $cmsUserId,
        private string $status = 'pending',
        private bool $featured = false,
        private ?string $externalEventId = null,
        private ?DateTimeInterface $createdAt = null,
        private ?DateTimeInterface $updatedAt = null,
        private ?DateTimeInterface $scrapedAt = null
    ) {
        $this->createdAt = $this->createdAt ?? new DateTime();
        $this->updatedAt = $this->updatedAt ?? new DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getStartDateTime(): DateTimeInterface
    {
        return $this->startDateTime;
    }

    public function getEndDateTime(): ?DateTimeInterface
    {
        return $this->endDateTime;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getAddress(): ?string
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

    public function getContactInfo(): ?array
    {
        return $this->contactInfo;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function getCmsUserId(): ?int
    {
        return $this->cmsUserId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function getExternalEventId(): ?string
    {
        return $this->externalEventId;
    }

    public function getCreatedAt(): ?DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getScrapedAt(): ?DateTimeInterface
    {
        return $this->scrapedAt;
    }

    /**
     * Update event details
     */
    public function update(
        ?string $title = null,
        ?string $description = null,
        ?DateTimeInterface $startDateTime = null,
        ?DateTimeInterface $endDateTime = null,
        ?string $location = null,
        ?string $address = null,
        ?float $latitude = null,
        ?float $longitude = null,
        ?array $contactInfo = null,
        ?string $externalUrl = null,
        ?string $status = null,
        ?bool $featured = null
    ): void {
        if ($title !== null) $this->title = $title;
        if ($description !== null) $this->description = $description;
        if ($startDateTime !== null) $this->startDateTime = $startDateTime;
        if ($endDateTime !== null) $this->endDateTime = $endDateTime;
        if ($location !== null) $this->location = $location;
        if ($address !== null) $this->address = $address;
        if ($latitude !== null) $this->latitude = $latitude;
        if ($longitude !== null) $this->longitude = $longitude;
        if ($contactInfo !== null) $this->contactInfo = $contactInfo;
        if ($externalUrl !== null) $this->externalUrl = $externalUrl;
        if ($status !== null) $this->status = $status;
        if ($featured !== null) $this->featured = $featured;
        
        $this->updatedAt = new DateTime();
    }

    /**
     * Mark event as approved
     */
    public function approve(): void
    {
        $this->status = 'approved';
        $this->updatedAt = new DateTime();
    }

    /**
     * Mark event as rejected
     */
    public function reject(): void
    {
        $this->status = 'rejected';
        $this->updatedAt = new DateTime();
    }

    /**
     * Check if event is upcoming
     */
    public function isUpcoming(): bool
    {
        return $this->startDateTime > new DateTime();
    }

    /**
     * Check if event is currently happening
     */
    public function isHappening(): bool
    {
        $now = new DateTime();
        $end = $this->endDateTime ?? $this->startDateTime;
        
        return $this->startDateTime <= $now && $end >= $now;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'start_datetime' => $this->startDateTime->format('Y-m-d H:i:s'),
            'end_datetime' => $this->endDateTime?->format('Y-m-d H:i:s'),
            'location' => $this->location,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'contact_info' => $this->contactInfo ? json_encode($this->contactInfo) : null,
            'external_url' => $this->externalUrl,
            'source_id' => $this->sourceId,
            'cms_user_id' => $this->cmsUserId,
            'status' => $this->status,
            'featured' => $this->featured ? 1 : 0,
            'external_event_id' => $this->externalEventId,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'scraped_at' => $this->scrapedAt?->format('Y-m-d H:i:s'),
        ];
    }

    public static function fromArray(array $data): static
    {
        return new static(
            id: $data['id'] ?? null,
            title: $data['title'],
            description: $data['description'] ?? null,
            startDateTime: new DateTime($data['start_datetime']),
            endDateTime: isset($data['end_datetime']) ? new DateTime($data['end_datetime']) : null,
            location: $data['location'] ?? null,
            address: $data['address'] ?? null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            contactInfo: isset($data['contact_info']) ? json_decode($data['contact_info'], true) : null,
            externalUrl: $data['external_url'] ?? null,
            sourceId: isset($data['source_id']) ? (int) $data['source_id'] : null,
            cmsUserId: isset($data['cms_user_id']) ? (int) $data['cms_user_id'] : null,
            status: $data['status'] ?? 'pending',
            featured: (bool) ($data['featured'] ?? false),
            externalEventId: $data['external_event_id'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null,
            scrapedAt: isset($data['scraped_at']) ? new DateTime($data['scraped_at']) : null
        );
    }
}