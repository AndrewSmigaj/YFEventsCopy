<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Claims;

use YakimaFinds\Domain\Common\EntityInterface;
use DateTimeInterface;
use DateTime;

class Seller implements EntityInterface
{
    private array $sales = [];
    private array $stats = [];

    public function __construct(
        private ?int $id,
        private int $userId,
        private string $companyName,
        private ?string $contactName,
        private string $email,
        private string $phone,
        private ?string $address,
        private ?string $city,
        private ?string $state,
        private ?string $zipCode,
        private ?string $website,
        private ?string $description,
        private ?string $logo,
        private string $status,
        private array $settings,
        private array $paymentMethods,
        private ?DateTimeInterface $verifiedAt,
        private DateTimeInterface $createdAt,
        private ?DateTimeInterface $updatedAt = null
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getCompanyName(): string
    {
        return $this->companyName;
    }

    public function getContactName(): ?string
    {
        return $this->contactName;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): string
    {
        return $this->phone;
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

    public function getZipCode(): ?string
    {
        return $this->zipCode;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getSettings(): array
    {
        return $this->settings;
    }

    public function getPaymentMethods(): array
    {
        return $this->paymentMethods;
    }

    public function getVerifiedAt(): ?DateTimeInterface
    {
        return $this->verifiedAt;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getSales(): array
    {
        return $this->sales;
    }

    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Set seller's sales
     */
    public function setSales(array $sales): void
    {
        $this->sales = $sales;
    }

    /**
     * Set seller statistics
     */
    public function setStats(array $stats): void
    {
        $this->stats = $stats;
    }

    /**
     * Check if seller is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if seller is verified
     */
    public function isVerified(): bool
    {
        return $this->verifiedAt !== null;
    }

    /**
     * Check if seller is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    /**
     * Get full address
     */
    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->zipCode
        ]);
        
        return implode(', ', $parts);
    }

    /**
     * Activate seller
     */
    public function activate(): self
    {
        return $this->update(['status' => 'active']);
    }

    /**
     * Suspend seller
     */
    public function suspend(): self
    {
        return $this->update(['status' => 'suspended']);
    }

    /**
     * Verify seller
     */
    public function verify(): self
    {
        return $this->update([
            'verified_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update settings
     */
    public function updateSettings(array $settings): self
    {
        return $this->update([
            'settings' => array_merge($this->settings, $settings)
        ]);
    }

    /**
     * Update payment methods
     */
    public function updatePaymentMethods(array $methods): self
    {
        return $this->update(['payment_methods' => $methods]);
    }

    /**
     * Check if has payment method
     */
    public function hasPaymentMethod(string $method): bool
    {
        return in_array($method, $this->paymentMethods);
    }

    /**
     * Update seller data
     */
    public function update(array $data): self
    {
        return new self(
            id: $this->id,
            userId: $data['user_id'] ?? $this->userId,
            companyName: $data['company_name'] ?? $this->companyName,
            contactName: $data['contact_name'] ?? $this->contactName,
            email: $data['email'] ?? $this->email,
            phone: $data['phone'] ?? $this->phone,
            address: $data['address'] ?? $this->address,
            city: $data['city'] ?? $this->city,
            state: $data['state'] ?? $this->state,
            zipCode: $data['zip_code'] ?? $this->zipCode,
            website: $data['website'] ?? $this->website,
            description: $data['description'] ?? $this->description,
            logo: $data['logo'] ?? $this->logo,
            status: $data['status'] ?? $this->status,
            settings: $data['settings'] ?? $this->settings,
            paymentMethods: $data['payment_methods'] ?? $this->paymentMethods,
            verifiedAt: isset($data['verified_at']) ? new DateTime($data['verified_at']) : $this->verifiedAt,
            createdAt: $this->createdAt,
            updatedAt: new DateTime()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'company_name' => $this->companyName,
            'contact_name' => $this->contactName,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zipCode,
            'website' => $this->website,
            'description' => $this->description,
            'logo' => $this->logo,
            'status' => $this->status,
            'settings' => $this->settings,
            'payment_methods' => $this->paymentMethods,
            'verified_at' => $this->verifiedAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'full_address' => $this->getFullAddress(),
            'is_verified' => $this->isVerified(),
            'stats' => $this->stats
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'] ?? null,
            userId: $data['user_id'],
            companyName: $data['company_name'],
            contactName: $data['contact_name'] ?? null,
            email: $data['email'],
            phone: $data['phone'],
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            state: $data['state'] ?? null,
            zipCode: $data['zip_code'] ?? null,
            website: $data['website'] ?? null,
            description: $data['description'] ?? null,
            logo: $data['logo'] ?? null,
            status: $data['status'] ?? 'active',
            settings: $data['settings'] ?? [],
            paymentMethods: $data['payment_methods'] ?? [],
            verifiedAt: isset($data['verified_at']) ? new DateTime($data['verified_at']) : null,
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : new DateTime(),
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
    }
}