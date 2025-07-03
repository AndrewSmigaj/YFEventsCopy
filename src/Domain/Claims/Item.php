<?php

declare(strict_types=1);

namespace YFEvents\Domain\Claims;

use YFEvents\Domain\Common\EntityInterface;
use DateTimeInterface;
use DateTime;

class Item implements EntityInterface
{
    private array $images = [];

    public function __construct(
        private ?int $id,
        private int $saleId,
        private int $categoryId,
        private string $title,
        private ?string $description,
        private float $price,
        private ?float $buyNowPrice,
        private string $condition,
        private ?string $dimensions,
        private string $status,
        private int $viewCount,
        private DateTimeInterface $createdAt,
        private ?DateTimeInterface $updatedAt = null
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaleId(): int
    {
        return $this->saleId;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getBuyNowPrice(): ?float
    {
        return $this->buyNowPrice;
    }

    public function getCondition(): string
    {
        return $this->condition;
    }

    public function getDimensions(): ?string
    {
        return $this->dimensions;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }


    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getImages(): array
    {
        return $this->images;
    }


    /**
     * Set item images
     */
    public function setImages(array $images): void
    {
        $this->images = $images;
    }

    /**
     * Add an image
     */
    public function addImage(array $image): void
    {
        $this->images[] = $image;
    }


    /**
     * Check if item is available for offers
     */
    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Check if item has buy now price
     */
    public function hasBuyNowPrice(): bool
    {
        return $this->buyNowPrice !== null && $this->buyNowPrice > 0;
    }


    /**
     * Activate item
     */
    public function activate(): self
    {
        return $this->update(['status' => 'active']);
    }

    /**
     * Deactivate item
     */
    public function deactivate(): self
    {
        return $this->update(['status' => 'inactive']);
    }

    /**
     * Mark as sold
     */
    public function markAsSold(): self
    {
        return $this->update(['status' => 'sold']);
    }

    /**
     * Increment view count
     */
    public function incrementViewCount(): self
    {
        return $this->update(['view_count' => $this->viewCount + 1]);
    }

    /**
     * Update item data
     */
    public function update(array $data): self
    {
        return new self(
            id: $this->id,
            saleId: $data['sale_id'] ?? $this->saleId,
            categoryId: $data['category_id'] ?? $this->categoryId,
            title: $data['title'] ?? $this->title,
            description: $data['description'] ?? $this->description,
            price: $data['price'] ?? $this->price,
            buyNowPrice: $data['buy_now_price'] ?? $this->buyNowPrice,
            condition: $data['condition'] ?? $this->condition,
            dimensions: $data['dimensions'] ?? $this->dimensions,
            status: $data['status'] ?? $this->status,
            viewCount: $data['view_count'] ?? $this->viewCount,
            createdAt: $this->createdAt,
            updatedAt: new DateTime()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->saleId,
            'category_id' => $this->categoryId,
            'title' => $this->title,
            'description' => $this->description,
            'price' => $this->price,
            'buy_now_price' => $this->buyNowPrice,
            'condition' => $this->condition,
            'dimensions' => $this->dimensions,
            'status' => $this->status,
            'view_count' => $this->viewCount,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'images' => $this->images
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'] ?? null,
            saleId: $data['sale_id'],
            categoryId: $data['category_id'],
            title: $data['title'],
            description: $data['description'] ?? null,
            price: (float)($data['price'] ?? $data['starting_price'] ?? 0),
            buyNowPrice: isset($data['buy_now_price']) ? (float)$data['buy_now_price'] : null,
            condition: $data['condition'] ?? 'used',
            dimensions: $data['dimensions'] ?? null,
            status: $data['status'] ?? 'active',
            viewCount: $data['view_count'] ?? 0,
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : new DateTime(),
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
    }
}