<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Claims;

use YakimaFinds\Domain\Common\EntityInterface;
use DateTimeInterface;
use DateTime;

class Buyer implements EntityInterface
{
    private array $offers = [];

    public function __construct(
        private ?int $id,
        private ?string $name,
        private ?string $email,
        private ?string $phone,
        private string $authToken,
        private string $authMethod,
        private bool $isVerified,
        private ?DateTimeInterface $verifiedAt,
        private ?string $ipAddress,
        private ?string $userAgent,
        private ?DateTimeInterface $lastActiveAt,
        private DateTimeInterface $createdAt,
        private ?DateTimeInterface $updatedAt = null
    ) {}

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getAuthMethod(): string
    {
        return $this->authMethod;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function getVerifiedAt(): ?DateTimeInterface
    {
        return $this->verifiedAt;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getLastActiveAt(): ?DateTimeInterface
    {
        return $this->lastActiveAt;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getOffers(): array
    {
        return $this->offers;
    }

    /**
     * Set buyer's offers
     */
    public function setOffers(array $offers): void
    {
        $this->offers = $offers;
    }

    /**
     * Get display name
     */
    public function getDisplayName(): string
    {
        if ($this->name) {
            return $this->name;
        }
        
        if ($this->email) {
            return substr($this->email, 0, strpos($this->email, '@'));
        }
        
        if ($this->phone) {
            return 'Buyer ' . substr($this->phone, -4);
        }
        
        return 'Anonymous Buyer';
    }

    /**
     * Get masked contact info for display
     */
    public function getMaskedEmail(): ?string
    {
        if (!$this->email) {
            return null;
        }
        
        $parts = explode('@', $this->email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';
        
        $maskedName = substr($name, 0, 2) . str_repeat('*', max(0, strlen($name) - 2));
        
        return $maskedName . '@' . $domain;
    }

    /**
     * Get masked phone for display
     */
    public function getMaskedPhone(): ?string
    {
        if (!$this->phone) {
            return null;
        }
        
        $phone = preg_replace('/[^0-9]/', '', $this->phone);
        
        if (strlen($phone) === 10) {
            return '(***) ***-' . substr($phone, -4);
        }
        
        return str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -4);
    }

    /**
     * Verify buyer
     */
    public function verify(): self
    {
        return $this->update([
            'is_verified' => true,
            'verified_at' => (new DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update last activity
     */
    public function updateLastActivity(?string $ipAddress = null, ?string $userAgent = null): self
    {
        return $this->update([
            'last_active_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'ip_address' => $ipAddress ?? $this->ipAddress,
            'user_agent' => $userAgent ?? $this->userAgent
        ]);
    }

    /**
     * Update contact info
     */
    public function updateContactInfo(array $info): self
    {
        return $this->update([
            'name' => $info['name'] ?? $this->name,
            'email' => $info['email'] ?? $this->email,
            'phone' => $info['phone'] ?? $this->phone
        ]);
    }

    /**
     * Check if token is still valid (24 hours)
     */
    public function isTokenValid(): bool
    {
        $expiryTime = clone $this->createdAt;
        $expiryTime->modify('+24 hours');
        
        return new DateTime() < $expiryTime;
    }

    /**
     * Generate new auth token
     */
    public function regenerateToken(): self
    {
        return $this->update([
            'auth_token' => bin2hex(random_bytes(32))
        ]);
    }

    /**
     * Update buyer data
     */
    public function update(array $data): self
    {
        return new self(
            id: $this->id,
            name: $data['name'] ?? $this->name,
            email: $data['email'] ?? $this->email,
            phone: $data['phone'] ?? $this->phone,
            authToken: $data['auth_token'] ?? $this->authToken,
            authMethod: $data['auth_method'] ?? $this->authMethod,
            isVerified: $data['is_verified'] ?? $this->isVerified,
            verifiedAt: isset($data['verified_at']) ? new DateTime($data['verified_at']) : $this->verifiedAt,
            ipAddress: $data['ip_address'] ?? $this->ipAddress,
            userAgent: $data['user_agent'] ?? $this->userAgent,
            lastActiveAt: isset($data['last_active_at']) ? new DateTime($data['last_active_at']) : $this->lastActiveAt,
            createdAt: $this->createdAt,
            updatedAt: new DateTime()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'auth_token' => $this->authToken,
            'auth_method' => $this->authMethod,
            'is_verified' => $this->isVerified,
            'verified_at' => $this->verifiedAt?->format('Y-m-d H:i:s'),
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'last_active_at' => $this->lastActiveAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'display_name' => $this->getDisplayName(),
            'masked_email' => $this->getMaskedEmail(),
            'masked_phone' => $this->getMaskedPhone(),
            'token_valid' => $this->isTokenValid()
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            authToken: $data['auth_token'] ?? bin2hex(random_bytes(32)),
            authMethod: $data['auth_method'] ?? 'email',
            isVerified: $data['is_verified'] ?? false,
            verifiedAt: isset($data['verified_at']) ? new DateTime($data['verified_at']) : null,
            ipAddress: $data['ip_address'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            lastActiveAt: isset($data['last_active_at']) ? new DateTime($data['last_active_at']) : null,
            createdAt: isset($data['created_at']) ? new DateTime($data['created_at']) : new DateTime(),
            updatedAt: isset($data['updated_at']) ? new DateTime($data['updated_at']) : null
        );
    }
}