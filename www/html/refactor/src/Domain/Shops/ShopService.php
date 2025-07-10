<?php

declare(strict_types=1);

namespace YakimaFinds\Domain\Shops;

use YakimaFinds\Infrastructure\Database\ConnectionInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Shop service implementation
 */
class ShopService implements ShopServiceInterface
{
    public function __construct(
        private ShopRepositoryInterface $shopRepository,
        private ConnectionInterface $connection
    ) {}

    public function createShop(array $shopData): Shop
    {
        $validationErrors = $this->validateShopData($shopData);
        if (!empty($validationErrors)) {
            throw new InvalidArgumentException('Validation failed: ' . implode(', ', $validationErrors));
        }

        // Create shop entity
        $shop = new Shop(
            id: null,
            name: $shopData['name'],
            description: $shopData['description'] ?? null,
            address: $shopData['address'],
            latitude: isset($shopData['latitude']) ? (float) $shopData['latitude'] : null,
            longitude: isset($shopData['longitude']) ? (float) $shopData['longitude'] : null,
            phone: $shopData['phone'] ?? null,
            email: $shopData['email'] ?? null,
            website: $shopData['website'] ?? null,
            imageUrl: $shopData['image_url'] ?? null,
            categoryId: isset($shopData['category_id']) ? (int) $shopData['category_id'] : null,
            hours: $shopData['hours'] ?? [],
            operatingHours: $shopData['operating_hours'] ?? [],
            paymentMethods: $shopData['payment_methods'] ?? [],
            amenities: $shopData['amenities'] ?? [],
            featured: (bool) ($shopData['featured'] ?? false),
            verified: (bool) ($shopData['verified'] ?? false),
            ownerId: isset($shopData['owner_id']) ? (int) $shopData['owner_id'] : null,
            status: $shopData['status'] ?? 'pending',
            active: (bool) ($shopData['active'] ?? true)
        );

        return $this->shopRepository->save($shop);
    }

    public function updateShop(int $shopId, array $updateData): Shop
    {
        $shop = $this->shopRepository->findById($shopId);
        if (!$shop instanceof Shop) {
            throw new RuntimeException("Shop not found: {$shopId}");
        }

        $validationErrors = $this->validateShopData($updateData, false);
        if (!empty($validationErrors)) {
            throw new InvalidArgumentException('Validation failed: ' . implode(', ', $validationErrors));
        }

        // Update shop
        $shop->update(
            name: $updateData['name'] ?? null,
            description: $updateData['description'] ?? null,
            address: $updateData['address'] ?? null,
            latitude: isset($updateData['latitude']) ? (float) $updateData['latitude'] : null,
            longitude: isset($updateData['longitude']) ? (float) $updateData['longitude'] : null,
            phone: $updateData['phone'] ?? null,
            email: $updateData['email'] ?? null,
            website: $updateData['website'] ?? null,
            imageUrl: $updateData['image_url'] ?? null,
            categoryId: isset($updateData['category_id']) ? (int) $updateData['category_id'] : null,
            hours: $updateData['hours'] ?? null,
            operatingHours: $updateData['operating_hours'] ?? null,
            paymentMethods: $updateData['payment_methods'] ?? null,
            amenities: $updateData['amenities'] ?? null,
            featured: isset($updateData['featured']) ? (bool) $updateData['featured'] : null,
            verified: isset($updateData['verified']) ? (bool) $updateData['verified'] : null,
            ownerId: isset($updateData['owner_id']) ? (int) $updateData['owner_id'] : null,
            status: $updateData['status'] ?? null,
            active: isset($updateData['active']) ? (bool) $updateData['active'] : null
        );

        return $this->shopRepository->save($shop);
    }

    public function deleteShop(int $shopId): bool
    {
        $shop = $this->shopRepository->findById($shopId);
        if (!$shop instanceof Shop) {
            return false;
        }

        return $this->shopRepository->delete($shop);
    }

    public function getShopById(int $shopId): ?Shop
    {
        $result = $this->shopRepository->findById($shopId);
        return $result instanceof Shop ? $result : null;
    }

    public function getShopsForDirectory(array $filters = []): array
    {
        // Default to active shops for public directory
        if (!isset($filters['status'])) {
            $filters['status'] = 'active';
        }
        if (!isset($filters['active'])) {
            $filters['active'] = true;
        }

        return $this->shopRepository->search('', $filters);
    }

    public function searchShops(string $query, array $filters = []): array
    {
        return $this->shopRepository->search($query, $filters);
    }

    public function getFeaturedShops(int $limit = 10): array
    {
        return $this->shopRepository->findFeatured($limit);
    }

    public function getShopsNearLocation(float $latitude, float $longitude, float $radiusMiles = 10): array
    {
        return $this->shopRepository->findNearLocation($latitude, $longitude, $radiusMiles);
    }

    public function getShopsForMap(array $filters = []): array
    {
        return $this->shopRepository->getShopsForMap($filters);
    }

    public function approveShop(int $shopId): Shop
    {
        $shop = $this->shopRepository->findById($shopId);
        if (!$shop instanceof Shop) {
            throw new RuntimeException("Shop not found: {$shopId}");
        }

        $shop->approve();
        return $this->shopRepository->save($shop);
    }

    public function rejectShop(int $shopId): Shop
    {
        $shop = $this->shopRepository->findById($shopId);
        if (!$shop instanceof Shop) {
            throw new RuntimeException("Shop not found: {$shopId}");
        }

        $shop->reject();
        return $this->shopRepository->save($shop);
    }

    public function verifyShop(int $shopId): Shop
    {
        $shop = $this->shopRepository->findById($shopId);
        if (!$shop instanceof Shop) {
            throw new RuntimeException("Shop not found: {$shopId}");
        }

        $shop->verify();
        return $this->shopRepository->save($shop);
    }

    public function featureShop(int $shopId, bool $featured = true): Shop
    {
        $shop = $this->shopRepository->findById($shopId);
        if (!$shop instanceof Shop) {
            throw new RuntimeException("Shop not found: {$shopId}");
        }

        if ($featured) {
            $shop->feature();
        } else {
            $shop->unfeature();
        }

        return $this->shopRepository->save($shop);
    }

    public function getShopsByCategory(int $categoryId): array
    {
        return $this->shopRepository->findByCategory($categoryId);
    }

    public function getShopStatistics(): array
    {
        return $this->shopRepository->getStatistics();
    }

    public function validateShopData(array $shopData, bool $requireRequired = true): array
    {
        $errors = [];

        // Required fields for creation
        if ($requireRequired) {
            if (empty($shopData['name'])) {
                $errors[] = 'Name is required';
            }
            
            if (empty($shopData['address'])) {
                $errors[] = 'Address is required';
            }
        }

        // Validate name length
        if (isset($shopData['name']) && strlen($shopData['name']) > 255) {
            $errors[] = 'Name must be 255 characters or less';
        }

        // Validate email format
        if (isset($shopData['email']) && !empty($shopData['email'])) {
            if (!filter_var($shopData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Invalid email format';
            }
        }

        // Validate website URL
        if (isset($shopData['website']) && !empty($shopData['website'])) {
            if (!filter_var($shopData['website'], FILTER_VALIDATE_URL)) {
                $errors[] = 'Invalid website URL';
            }
        }

        // Validate coordinates
        if (isset($shopData['latitude'])) {
            $lat = (float) $shopData['latitude'];
            if ($lat < -90 || $lat > 90) {
                $errors[] = 'Latitude must be between -90 and 90';
            }
        }

        if (isset($shopData['longitude'])) {
            $lng = (float) $shopData['longitude'];
            if ($lng < -180 || $lng > 180) {
                $errors[] = 'Longitude must be between -180 and 180';
            }
        }

        // Validate status
        if (isset($shopData['status'])) {
            $validStatuses = ['active', 'pending', 'inactive'];
            if (!in_array($shopData['status'], $validStatuses)) {
                $errors[] = 'Status must be one of: ' . implode(', ', $validStatuses);
            }
        }

        // Validate phone number format (basic)
        if (isset($shopData['phone']) && !empty($shopData['phone'])) {
            $phone = preg_replace('/[^\d]/', '', $shopData['phone']);
            if (strlen($phone) < 10) {
                $errors[] = 'Phone number must be at least 10 digits';
            }
        }

        // Validate JSON fields
        if (isset($shopData['hours']) && !is_array($shopData['hours'])) {
            $errors[] = 'Hours must be an array';
        }

        if (isset($shopData['operating_hours']) && !is_array($shopData['operating_hours'])) {
            $errors[] = 'Operating hours must be an array';
        }

        if (isset($shopData['payment_methods']) && !is_array($shopData['payment_methods'])) {
            $errors[] = 'Payment methods must be an array';
        }

        if (isset($shopData['amenities']) && !is_array($shopData['amenities'])) {
            $errors[] = 'Amenities must be an array';
        }

        return $errors;
    }

    public function bulkApproveShops(array $shopIds): array
    {
        $results = [];
        
        $this->connection->beginTransaction();
        try {
            foreach ($shopIds as $shopId) {
                $results[] = $this->approveShop($shopId);
            }
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }

        return $results;
    }

    public function bulkRejectShops(array $shopIds): array
    {
        $results = [];
        
        $this->connection->beginTransaction();
        try {
            foreach ($shopIds as $shopId) {
                $results[] = $this->rejectShop($shopId);
            }
            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }

        return $results;
    }
}