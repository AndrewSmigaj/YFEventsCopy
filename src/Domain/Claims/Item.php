<?php

declare(strict_types=1);

namespace YFEvents\Domain\Claims;

use YFEvents\Domain\Common\EntityInterface;
use DateTimeInterface;
use DateTime;

class Item implements EntityInterface
{
    private array $images = [];
    private array $offers = [];
    private ?Offer $winningOffer = null;

    public function __construct(
        private ?int $id,
        private int $saleId,
        private int $categoryId,
        private string $title,
        private ?string $description,
        private float $startingPrice,
        private ?float $buyNowPrice,
        private string $condition,
        private ?string $dimensions,
        private string $status,
        private int $viewCount,
        private int $offerCount,
        private ?int $winningOfferId,
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

    public function getStartingPrice(): float
    {
        return $this->startingPrice;
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

    public function getOfferCount(): int
    {
        return $this->offerCount;
    }

    public function getWinningOfferId(): ?int
    {
        return $this->winningOfferId;
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

    public function getOffers(): array
    {
        return $this->offers;
    }

    public function getWinningOffer(): ?Offer
    {
        return $this->winningOffer;
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
     * Set item offers
     */
    public function setOffers(array $offers): void
    {
        $this->offers = $offers;
        $this->offerCount = count($offers);
    }

    /**
     * Add an offer
     */
    public function addOffer(Offer $offer): void
    {
        $this->offers[] = $offer;
        $this->offerCount++;
    }

    /**
     * Set winning offer
     */
    public function setWinningOffer(Offer $offer): void
    {
        $this->winningOffer = $offer;
        $this->winningOfferId = $offer->getId();
    }

    /**
     * Check if item is available for offers
     */
    public function isAvailable(): bool
    {
        return $this->status === 'active' && $this->winningOfferId === null;
    }

    /**
     * Check if item has buy now price
     */
    public function hasBuyNowPrice(): bool
    {
        return $this->buyNowPrice !== null && $this->buyNowPrice > 0;
    }

    /**
     * Get current price range for display
     */
    public function getPriceRange(): array
    {
        if (empty($this->offers)) {
            return [
                'min' => $this->startingPrice,
                'max' => $this->startingPrice
            ];
        }

        $amounts = array_map(fn($offer) => $offer->getAmount(), $this->offers);
        return [
            'min' => min($amounts),
            'max' => max($amounts)
        ];
    }

    /**
     * Get highest offer amount
     */
    public function getHighestOfferAmount(): ?float
    {
        if (empty($this->offers)) {
            return null;
        }

        $amounts = array_map(fn($offer) => $offer->getAmount(), $this->offers);
        return max($amounts);
    }

    /**
     * Accept an offer
     */
    public function acceptOffer(int $offerId): self
    {
        return $this->update([
            'winning_offer_id' => $offerId,
            'status' => 'sold'
        ]);
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
            startingPrice: $data['starting_price'] ?? $this->startingPrice,
            buyNowPrice: $data['buy_now_price'] ?? $this->buyNowPrice,
            condition: $data['condition'] ?? $this->condition,
            dimensions: $data['dimensions'] ?? $this->dimensions,
            status: $data['status'] ?? $this->status,
            viewCount: $data['view_count'] ?? $this->viewCount,
            offerCount: $data['offer_count'] ?? $this->offerCount,
            winningOfferId: $data['winning_offer_id'] ?? $this->winningOfferId,
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
            'starting_price' => $this->startingPrice,
            'buy_now_price' => $this->buyNowPrice,
            'condition' => $this->condition,
            'dimensions' => $this->dimensions,
            'status' => $this->status,
            'view_count' => $this->viewCount,
            'offer_count' => $this->offerCount,
            'winning_offer_id' => $this->winningOfferId,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'images' => $this->images,
            'price_range' => $this->getPriceRange(),
            'highest_offer' => $this->getHighestOfferAmount()
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
            startingPrice: (float)$data['starting_price'],
            buyNowPrice: isset($data['buy_now_price']) ? (float)$data['buy_now_price'] : null,
            condition: $data['condition'] ?? 'used',
            dimensions: $data['dimensions'] ?? null,
            status: $data['status'] ?? 'active',
            viewCount: $data['view_count'] ?? 0,
            offerCount: $data['offer_count'] ?? 0,
            winningOfferId: $data['winning_offer_id'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : new DateTime(),
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
    }
}