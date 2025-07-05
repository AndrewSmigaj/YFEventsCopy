# Homepage Overhaul Implementation Document

## Overview
This document outlines the implementation plan for transforming the YFEvents homepage from a static service card layout to a dynamic, modern interface that immediately showcases value to visitors.

## Architecture Compliance
Following YFEvents Clean Architecture (architecture.yaml v2.2.0):
- **Domain Layer**: Leverage existing entities (Event, Shop, Sale, Item)
- **Application Layer**: Reuse existing services (EventService, ShopService, ClaimService)
- **Infrastructure Layer**: Use existing repositories and database connections
- **Presentation Layer**: Update HomeController and create ItemGalleryController

## Implementation Plan

### Phase 1: Container Registration and Service Setup

#### 1.1 Register Missing Services in ServiceProvider
```php
// src/Infrastructure/Providers/ServiceProvider.php
// Add to registerRepositories() method:
$this->container->bind(SaleRepositoryInterface::class, function ($container) {
    return new SaleRepository($container->resolve(ConnectionInterface::class));
});

$this->container->bind(ItemRepositoryInterface::class, function ($container) {
    return new ItemRepository($container->resolve(ConnectionInterface::class));
});

// Add to registerServices() method:
$this->container->bind(ClaimService::class, function ($container) {
    return new ClaimService(
        $container->resolve(SaleRepositoryInterface::class),
        $container->resolve(ItemRepositoryInterface::class),
        $container->resolve(OfferRepositoryInterface::class),
        $container->resolve(QRCodeService::class)
    );
});
```

#### 1.2 Add Required Imports to ServiceProvider
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

### Phase 2: Update HomeController for Dynamic Content

#### 2.1 Modify HomeController Dependencies
```php
// src/Presentation/Http/Controllers/HomeController.php
use YFEvents\Domain\Events\EventServiceInterface;
use YFEvents\Domain\Shops\ShopServiceInterface;
use YFEvents\Application\Services\ClaimService;
use YFEvents\Domain\Claims\SaleRepositoryInterface;
use YFEvents\Domain\Claims\ItemRepositoryInterface;
```

#### 2.2 Add Service Properties
```php
private EventServiceInterface $eventService;
private ShopServiceInterface $shopService;
private ClaimService $claimService;
private SaleRepositoryInterface $saleRepository;
private ItemRepositoryInterface $itemRepository;
```

#### 2.3 Update Constructor
Inject services through dependency injection container:
```php
public function __construct(ContainerInterface $container, ConfigInterface $config)
{
    parent::__construct($container, $config);
    $this->eventService = $container->resolve(EventServiceInterface::class);
    $this->shopService = $container->resolve(ShopServiceInterface::class);
    $this->claimService = $container->resolve(ClaimService::class);
    $this->saleRepository = $container->resolve(SaleRepositoryInterface::class);
    $this->itemRepository = $container->resolve(ItemRepositoryInterface::class);
}
```

#### 2.4 Add Data Fetching Methods
```php
private function getFeaturedItems(int $limit = 8): array
{
    // Use ItemRepository->getPopular() for each active sale
    // Or create a new method in ClaimService to get featured items across all sales
    // Filter to only items with primary_image NOT NULL
    $activeSales = $this->claimService->getActiveSales(1, 100)->getItems();
    $featuredItems = [];
    foreach ($activeSales as $sale) {
        $popularItems = $this->container->resolve(ItemRepositoryInterface::class)
            ->getPopular($sale->getId(), 4);
        $featuredItems = array_merge($featuredItems, $popularItems);
    }
    return array_slice($featuredItems, 0, $limit);
}

private function getUpcomingSales(int $limit = 3): array
{
    // Use SaleRepository->findUpcoming()
    $saleRepository = $this->container->resolve(SaleRepositoryInterface::class);
    return $saleRepository->findUpcoming(7); // Next 7 days
}

private function getUpcomingEvents(int $limit = 5): array
{
    // Use existing EventService->getUpcomingEvents()
    return $this->eventService->getUpcomingEvents($limit);
}

private function getCurrentSales(): array
{
    // Use ClaimService->getActiveSales()
    return $this->claimService->getActiveSales(1, 20)->getItems();
}

private function getFeaturedShops(int $limit = 4): array
{
    // Use existing ShopService->getFeaturedShops()
    return $this->shopService->getFeaturedShops($limit);
}
```

### Phase 3: Create Item Gallery Feature

#### 3.1 Create ItemGalleryController
```php
// src/Presentation/Http/Controllers/ItemGalleryController.php
namespace YFEvents\Presentation\Http\Controllers;

class ItemGalleryController extends BaseController
{
    // Display all items across all active sales
    public function showItemGallery(): void
    
    // API endpoint for filtering/pagination
    public function getFilteredItems(): void
}
```

#### 3.2 Add Routes
```php
// routes/web.php
$router->get('/claims/items', [ItemGalleryController::class, 'showItemGallery']);
$router->get('/api/claims/items', [ItemGalleryController::class, 'getFilteredItems']);
```

#### 3.3 Item Gallery Features
- **Filters**: Category, Price Range, Condition, Sale Location, Status
- **Sorting**: Newest, Price (Low/High), Ending Soon
- **Pagination**: 24 items per page with infinite scroll
- **Search**: Full-text search across title and description

### Phase 4: Homepage Layout Transformation

#### 4.1 Remove Static Elements
- Remove seller hub card (lines 499-513 in HomeController)
- Remove hardcoded stats (lines 431-448)
- Remove developer resources card (lines 517-528)

#### 4.2 New Homepage Sections

##### Hero Section (Enhanced)
- Keep existing hero with estate sales/events buttons
- Add dynamic rotating tagline based on current activity
- Include real-time counts: "X active sales • Y upcoming events"

##### Featured Items Carousel
```html
<section class="featured-items">
    <h2>🌟 Featured Estate Sale Items</h2>
    <div class="items-carousel">
        <!-- 8 featured items with images -->
        <!-- Click through to /claims/item/{id} -->
    </div>
    <a href="/claims/items" class="view-all">Browse All Items →</a>
</section>
```

##### Active Sales Grid
```html
<section class="active-sales">
    <h2>🏛️ Current Estate Sales</h2>
    <div class="sales-grid">
        <!-- 3-6 active sales with preview -->
        <!-- Show: Title, Location, End Time, Item Count -->
    </div>
</section>
```

##### Upcoming Events Timeline
```html
<section class="upcoming-events">
    <h2>📅 Upcoming Events This Week</h2>
    <div class="events-timeline">
        <!-- 5 upcoming events -->
        <!-- Show: Date, Title, Location, Category -->
    </div>
    <a href="/events" class="view-calendar">View Full Calendar →</a>
</section>
```

##### Local Shops Section
```html
<section class="local-shops">
    <h2>🏪 Discover Local Shops</h2>
    <div class="shops-features">
        <div class="feature-card">
            <span class="icon">🗺️</span>
            <h3>Interactive Map</h3>
            <p>Find shops near you with our interactive map</p>
        </div>
        <div class="feature-card">
            <span class="icon">📍</span>
            <h3><?= count($featuredShops) ?> Local Businesses</h3>
            <p>Antique stores, vintage shops, and specialty retailers</p>
        </div>
    </div>
    <div class="shops-actions">
        <a href="/map" class="btn btn-primary">View Business Map →</a>
        <a href="/shops" class="btn btn-secondary">Browse Directory →</a>
    </div>
</section>
```

### Phase 5: Database Queries

#### 5.1 Featured Items Query
```sql
SELECT i.*, 
       s.title as sale_title,
       s.city as sale_city,
       (SELECT file_path FROM yfc_item_images 
        WHERE item_id = i.id 
        ORDER BY is_primary DESC, created_at ASC 
        LIMIT 1) as primary_image
FROM yfc_items i
JOIN yfc_sales s ON i.sale_id = s.id
WHERE s.status = 'active' 
  AND s.claim_start <= NOW() 
  AND s.claim_end >= NOW()
  AND i.status = 'available'
  AND EXISTS (SELECT 1 FROM yfc_item_images WHERE item_id = i.id)
ORDER BY i.created_at DESC
LIMIT 8
```

#### 5.2 Upcoming Sales Query
```sql
SELECT s.*, 
       sel.company_name,
       COUNT(i.id) as item_count,
       MIN(i.price) as min_price,
       MAX(i.price) as max_price
FROM yfc_sales s
LEFT JOIN yfc_sellers sel ON s.seller_id = sel.id
LEFT JOIN yfc_items i ON s.id = i.sale_id
WHERE s.status = 'active' 
  AND s.claim_start > NOW()
GROUP BY s.id
ORDER BY s.claim_start ASC
LIMIT 3
```

### Phase 6: Modern Design Implementation

#### 6.1 CSS Framework
- Keep existing vanilla CSS approach
- Add CSS Grid for responsive layouts
- Implement smooth transitions and hover effects
- Use CSS variables for theming

#### 6.2 JavaScript Enhancements
- Lazy loading for images
- Infinite scroll for item gallery
- Live countdown timers for sales
- Interactive map preview
- No external dependencies (vanilla JS)

#### 6.3 Performance Optimizations
- Cache dynamic content for 5 minutes
- Implement database query optimization
- Use srcset for responsive images
- Minimize CSS/JS inline

### Phase 7: Integration Points

#### 7.1 Existing Services to Leverage
- **EventService**: Use existing getUpcomingEvents(), getFeaturedEvents()
- **ShopService**: Use existing getFeaturedShops(), getShopsNearLocation()
- **ClaimService**: Use existing getActiveSales(), extend for cross-sale featured items
- **SaleRepository**: Use existing findUpcoming(), findActive()
- **ItemRepository**: Use existing getPopular(), search(), findByCategory()

#### 7.2 Existing Routes to Preserve
- `/claims` - Estate sales listing (keep as-is)
- `/claims/sale/{id}` - Individual sale page (keep as-is)
- `/claims/item/{id}` - Individual item page (keep as-is)
- `/events` - Events calendar (keep as-is)
- `/shops` - Shops directory (keep as-is)
- `/map` - Combined events/shops map (keep as-is)

#### 7.3 New Routes to Add
- `/claims/items` - All items gallery with filters
- `/api/claims/items` - AJAX endpoint for filtering
- `/api/home/stats` - Real-time stats for hero section

### Phase 8: Implementation Steps

1. **Update Container Bindings** (config/container.php)
   - Ensure ClaimService is properly bound
   - Add any new service dependencies

2. **Modify HomeController** (1-2 hours)
   - Add service injections
   - Implement data fetching methods
   - Update index() method to pass dynamic data
   - Modify renderHomePage() with new layout

3. **Create ItemGalleryController** (2-3 hours)
   - Implement gallery view method
   - Add filtering/pagination logic
   - Create responsive gallery template

4. **Update Routes** (15 minutes)
   - Add new routes to web.php
   - Ensure proper controller resolution

5. **Database Optimization** (30 minutes)
   - Add indexes if needed
   - Optimize queries for performance

6. **Testing** (1 hour)
   - Test all dynamic content loading
   - Verify responsive design
   - Check performance metrics

### Phase 9: Rollback Plan

If issues arise:
1. Git revert to previous HomeController
2. Remove new routes from web.php
3. No database changes required (read-only operations)

## Success Metrics

1. **Page Load Time**: < 2 seconds for homepage
2. **Dynamic Content**: All sections populate with real data
3. **Mobile Responsive**: Works on all device sizes
4. **SEO Maintained**: Keep existing meta tags, add structured data
5. **User Engagement**: Click-through to items/sales/events

## Notes

- **NO NEW DEPENDENCIES**: Use existing services and infrastructure
- **NO REIMPLEMENTATION**: Leverage existing ClaimService, EventService, ShopService
- **BACKWARDS COMPATIBLE**: All existing routes continue to work
- **CLEAN ARCHITECTURE**: Maintain separation of concerns
- **DATABASE INTEGRITY**: Read-only operations, no schema changes

## Security Considerations

1. **SQL Injection**: Use prepared statements (already in place)
2. **XSS Prevention**: htmlspecialchars() for all output
3. **Rate Limiting**: Consider for API endpoints
4. **Caching**: Implement to reduce database load

## Timeline

- **Phase 1-2**: 4-5 hours (Controllers and routes)
- **Phase 3-4**: 3-4 hours (Layout and queries)
- **Phase 5-6**: 2-3 hours (Design and integration)
- **Phase 7**: 2-3 hours (Implementation and testing)
- **Total**: 11-15 hours of development time

## Conclusion

This implementation plan transforms the YFEvents homepage into a dynamic, engaging portal while:
- Maintaining Clean Architecture principles
- Reusing existing services and infrastructure
- Preserving all current functionality
- Adding immediate value for visitors
- Following modern web design practices

The approach is incremental, testable, and fully reversible if needed.