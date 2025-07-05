# Phase 1: Container Registration and Service Setup

## Overview
This phase sets up the necessary service registrations in the dependency injection container to support the homepage overhaul. We need to register ClaimService and its dependencies that are not currently in the container.

## Prerequisites Check
1. Verify repository classes exist:
   - `src/Infrastructure/Repositories/Claims/SaleRepository.php`
   - `src/Infrastructure/Repositories/Claims/ItemRepository.php`
   - `src/Infrastructure/Repositories/Claims/OfferRepository.php`
2. Verify service classes exist:
   - `src/Application/Services/ClaimService.php`
   - `src/Infrastructure/Services/QRCodeService.php`

## Step-by-Step Implementation

### Step 1: Check Existing Repository Implementations
```bash
# Check if Claims repositories exist
ls -la src/Infrastructure/Repositories/Claims/

# If not found, check alternative locations
find src -name "SaleRepository.php" -type f
find src -name "ItemRepository.php" -type f
```

### Step 2: Update ServiceProvider.php

#### 2.1 Add Import Statements
Add these imports at the top of `src/Infrastructure/Providers/ServiceProvider.php`:

```php
use YFEvents\Domain\Claims\SaleRepositoryInterface;
use YFEvents\Domain\Claims\ItemRepositoryInterface;
use YFEvents\Domain\Claims\OfferRepositoryInterface;
use YFEvents\Infrastructure\Repositories\Claims\SaleRepository;
use YFEvents\Infrastructure\Repositories\Claims\ItemRepository;
use YFEvents\Infrastructure\Repositories\Claims\OfferRepository;
use YFEvents\Application\Services\ClaimService;
use YFEvents\Infrastructure\Services\QRCodeService;
```

#### 2.2 Update registerRepositories() Method
Add these bindings to the `registerRepositories()` method:

```php
// Claims repositories
$this->container->bind(SaleRepositoryInterface::class, function ($container) {
    return new SaleRepository($container->resolve(ConnectionInterface::class));
});

$this->container->bind(ItemRepositoryInterface::class, function ($container) {
    return new ItemRepository($container->resolve(ConnectionInterface::class));
});

$this->container->bind(OfferRepositoryInterface::class, function ($container) {
    return new OfferRepository($container->resolve(ConnectionInterface::class));
});
```

#### 2.3 Update registerServices() Method
Add these service registrations to the `registerServices()` method:

```php
// QR Code Service (if not already registered)
$this->container->singleton(QRCodeService::class, function ($container) {
    return new QRCodeService();
});

// Claim Service
$this->container->bind(ClaimService::class, function ($container) {
    return new ClaimService(
        $container->resolve(SaleRepositoryInterface::class),
        $container->resolve(ItemRepositoryInterface::class),
        $container->resolve(OfferRepositoryInterface::class),
        $container->resolve(QRCodeService::class)
    );
});
```

### Step 3: Create Missing Repository Classes (if needed)

If any repository classes are missing, we'll need to create them. Here's the template:

#### 3.1 SaleRepository Template
```php
<?php
declare(strict_types=1);

namespace YFEvents\Infrastructure\Repositories\Claims;

use YFEvents\Domain\Claims\Sale;
use YFEvents\Domain\Claims\SaleRepositoryInterface;
use YFEvents\Infrastructure\Database\ConnectionInterface;
use PDO;

class SaleRepository implements SaleRepositoryInterface
{
    private PDO $pdo;

    public function __construct(ConnectionInterface $connection)
    {
        $this->pdo = $connection->getConnection();
    }

    public function findById(int $id): ?Sale
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM yfc_sales WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();
        
        return $data ? Sale::fromArray($data) : null;
    }

    public function findUpcoming(int $days = 7): array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.*, sel.company_name 
            FROM yfc_sales s
            LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
            WHERE s.status = 'active' 
            AND s.claim_start > NOW() 
            AND s.claim_start <= DATE_ADD(NOW(), INTERVAL :days DAY)
            ORDER BY s.claim_start ASC
        ");
        $stmt->execute(['days' => $days]);
        
        return array_map(fn($row) => Sale::fromArray($row), $stmt->fetchAll());
    }

    // Implement other required methods...
}
```

### Step 4: Test Container Registration

Create a test script to verify services resolve correctly:

```php
<?php
// test_container.php
require_once __DIR__ . '/vendor/autoload.php';

use YFEvents\Application\Bootstrap;
use YFEvents\Application\Services\ClaimService;
use YFEvents\Domain\Events\EventServiceInterface;
use YFEvents\Domain\Shops\ShopServiceInterface;

try {
    $container = Bootstrap::boot();
    
    // Test service resolution
    $eventService = $container->resolve(EventServiceInterface::class);
    echo "✓ EventService resolved\n";
    
    $shopService = $container->resolve(ShopServiceInterface::class);
    echo "✓ ShopService resolved\n";
    
    $claimService = $container->resolve(ClaimService::class);
    echo "✓ ClaimService resolved\n";
    
    echo "\nAll services registered successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
```

### Step 5: Handle Potential Issues

#### Issue 1: Missing OfferRepository
Since the offer/bidding system was removed, we might need to:
1. Create a stub OfferRepository that returns empty results
2. Or modify ClaimService to make OfferRepository optional

#### Issue 2: Missing QRCodeService
If QRCodeService doesn't exist, create a simple implementation:

```php
<?php
namespace YFEvents\Infrastructure\Services;

class QRCodeService
{
    public function generateForSale(int $saleId, string $accessCode): string
    {
        // Simple implementation or use a QR library
        return "qr_code_placeholder_{$saleId}_{$accessCode}";
    }
}
```

## Verification Checklist

- [ ] All import statements added to ServiceProvider
- [ ] Repository bindings added to registerRepositories()
- [ ] Service bindings added to registerServices()
- [ ] Test script runs without errors
- [ ] All services resolve correctly
- [ ] No circular dependencies

## Next Steps

Once Phase 1 is complete:
1. Commit changes with message: "feat: Register ClaimService and dependencies in container"
2. Proceed to Phase 2: Update HomeController
3. If any issues arise, document them for resolution

## Rollback

If needed, simply revert ServiceProvider.php to its previous state:
```bash
git checkout -- src/Infrastructure/Providers/ServiceProvider.php
```

## Time Estimate
- Implementation: 45-60 minutes
- Testing: 15 minutes
- Total: 1 hour