# YFEvents Framework Developer Specification

## Table of Contents
1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Directory Structure](#directory-structure)
4. [Core Components](#core-components)
5. [Development Guidelines](#development-guidelines)
6. [API Development](#api-development)
7. [Database Schema](#database-schema)
8. [Authentication & Security](#authentication--security)
9. [Frontend Development](#frontend-development)
10. [Testing & Deployment](#testing--deployment)

## Overview

YFEvents is a PHP-based event management and local business directory system built with Domain-Driven Design (DDD) principles. The framework provides a robust foundation for building web applications in the Yakima Finds ecosystem.

### Key Features
- Event calendar with multi-source scraping
- Local business directory with geocoding
- Admin panel with user management
- RESTful API architecture
- Domain-driven design implementation
- PSR-4 autoloading standard

### Technology Stack
- **Backend**: PHP 8.1+
- **Database**: MySQL/MariaDB
- **Frontend**: Vanilla JavaScript, Bootstrap 5.1.3
- **Architecture**: Domain-Driven Design, Repository Pattern
- **API**: RESTful JSON APIs
- **Dependencies**: Composer (no build process required)

## Architecture

### Domain-Driven Design Structure

```
YFEvents/
├── src/
│   ├── Domain/              # Business logic and entities
│   │   ├── Event/
│   │   ├── Shop/
│   │   ├── User/
│   │   └── Claims/
│   ├── Application/         # Use cases and services
│   │   ├── Services/
│   │   └── Controllers/
│   ├── Infrastructure/      # Framework and external services
│   │   ├── Database/
│   │   ├── Http/
│   │   └── Config/
│   └── Presentation/        # UI and API controllers
│       ├── Http/
│       └── Api/
```

### Core Design Principles

1. **Separation of Concerns**: Clear boundaries between layers
2. **Dependency Inversion**: Depend on abstractions, not concretions
3. **Interface-Based Programming**: All major components use interfaces
4. **Repository Pattern**: Data access abstraction
5. **Service Layer**: Business logic encapsulation

## Directory Structure

```
/home/robug/YFEvents/www/html/refactor/
├── admin/                   # Admin panel PHP files
│   ├── assets/             # Admin CSS/JS
│   └── includes/           # Shared components
├── api/                    # API endpoints (being phased out)
├── config/                 # Configuration files
├── css/                    # Global stylesheets
├── public/                 # Public-facing pages
│   ├── buyer/
│   └── seller/
├── routes/                 # Route definitions
│   ├── api.php            # API routes
│   └── web.php            # Web routes
├── src/                    # Core application code
├── vendor/                 # Composer dependencies
├── .htaccess              # Apache routing rules
├── bootstrap.php          # Application bootstrap
└── index.php              # Entry point
```

## Core Components

### 1. Domain Entities

All entities follow this pattern:

```php
namespace YakimaFinds\Domain\Event\Entities;

class Event
{
    public function __construct(
        private ?int $id,
        private string $title,
        private string $description,
        private \DateTime $startDateTime,
        private \DateTime $endDateTime,
        private string $location,
        // ... other properties
    ) {}
    
    // Getters and business logic methods
}
```

### 2. Repository Interfaces

```php
namespace YakimaFinds\Domain\Event\Repositories;

interface EventRepositoryInterface
{
    public function find(int $id): ?Event;
    public function findAll(array $criteria = []): array;
    public function save(Event $event): bool;
    public function delete(int $id): bool;
}
```

### 3. Service Layer

```php
namespace YakimaFinds\Application\Services;

class EventService
{
    public function __construct(
        private EventRepositoryInterface $repository,
        private CacheInterface $cache
    ) {}
    
    public function createEvent(array $data): Event
    {
        // Business logic here
    }
}
```

### 4. Controllers

```php
namespace YakimaFinds\Presentation\Http\Controllers;

class EventController extends BaseController
{
    public function index(): void
    {
        $this->render('events/index', [
            'events' => $this->eventService->getUpcomingEvents()
        ]);
    }
}
```

## Development Guidelines

### 1. Creating a New Module

To add a new module to YFEvents:

```bash
# 1. Create domain structure
src/Domain/YourModule/
├── Entities/
├── Repositories/
├── ValueObjects/
└── Services/

# 2. Create application layer
src/Application/Services/YourModuleService.php

# 3. Create infrastructure
src/Infrastructure/Persistence/YourModuleRepository.php

# 4. Create presentation layer
src/Presentation/Http/Controllers/YourModuleController.php
```

### 2. Coding Standards

- **PHP Version**: 8.1+ with strict typing
- **Namespace**: `YakimaFinds\{Layer}\{Module}`
- **PSR-4**: Autoloading standard
- **PSR-12**: Coding style standard
- **Type Declarations**: Always use parameter and return types

### 3. Dependency Injection

The framework uses a PSR-11 compatible container:

```php
// Register a service
$container->register(YourInterface::class, function($container) {
    return new YourImplementation(
        $container->resolve(DependencyInterface::class)
    );
});

// Resolve a service
$service = $container->resolve(YourInterface::class);
```

## API Development

### 1. Route Definition

Define routes in `routes/api.php`:

```php
// GET endpoint
$router->get('/api/your-module', YourController::class, 'index');

// POST endpoint
$router->post('/api/your-module', YourController::class, 'store');

// PUT endpoint with parameter
$router->put('/api/your-module/{id}', YourController::class, 'update');
```

### 2. API Response Format

All API responses follow this structure:

```json
{
    "success": true,
    "data": {
        // Your data here
    },
    "message": "Optional message",
    "pagination": {
        "current_page": 1,
        "total_pages": 10,
        "per_page": 20,
        "total_items": 200
    }
}
```

Error responses:

```json
{
    "error": true,
    "message": "Error description",
    "details": {
        "field": "Specific error message"
    }
}
```

### 3. API Controller Example

```php
namespace YakimaFinds\Presentation\Api\Controllers;

class YourApiController extends BaseApiController
{
    public function index(): void
    {
        try {
            $data = $this->service->getData();
            $this->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            $this->error($e->getMessage(), 500);
        }
    }
}
```

## Database Schema

### 1. Naming Conventions

- **Tables**: Plural, snake_case (e.g., `events`, `calendar_sources`)
- **Columns**: Singular, snake_case (e.g., `user_id`, `created_at`)
- **Foreign Keys**: `{table}_id` (e.g., `user_id`, `event_id`)
- **Indexes**: `idx_{table}_{columns}` (e.g., `idx_events_start_date`)

### 2. Required Columns

All tables should include:

```sql
CREATE TABLE your_table (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- your columns here
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### 3. Database Access

Always use PDO with prepared statements:

```php
$stmt = $this->db->prepare("
    SELECT * FROM events 
    WHERE status = :status 
    AND start_datetime > :date
");
$stmt->execute([
    'status' => 'approved',
    'date' => date('Y-m-d H:i:s')
]);
```

## Authentication & Security

### 1. Session-Based Auth

Admin authentication uses PHP sessions:

```php
// Check authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Redirect to login
}

// Set authentication
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_username'] = $username;
$_SESSION['admin_id'] = $userId;
```

### 2. API Authentication

API endpoints require session authentication:

```php
public function requireAuth(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['admin_logged_in'])) {
        $this->error('Authentication required', 401);
        return false;
    }
    
    return true;
}
```

### 3. Security Best Practices

- **SQL Injection**: Always use prepared statements
- **XSS Prevention**: Escape output with `htmlspecialchars()`
- **CSRF Protection**: Use tokens for state-changing operations
- **Input Validation**: Validate all user input
- **Password Hashing**: Use `password_hash()` and `password_verify()`

## Frontend Development

### 1. JavaScript Standards

```javascript
// Use const for base paths
const basePath = '<?php echo $basePath; ?>' || '/refactor';

// Async/await for API calls
async function loadData() {
    try {
        const response = await fetch(`${basePath}/api/endpoint`, {
            credentials: 'include'
        });
        const data = await response.json();
        
        if (data.success) {
            // Handle success
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('Error loading data', 'error');
    }
}
```

### 2. UI Components

Standard UI patterns:

```html
<!-- Toast notifications -->
<div id="toast" class="toast"></div>

<!-- Modal dialogs -->
<div id="modal" class="modal">
    <div class="modal-content">
        <button class="close-btn" onclick="closeModal()">&times;</button>
        <!-- Modal content -->
    </div>
</div>

<!-- Loading states -->
<div class="loading">Loading...</div>
```

### 3. CSS Structure

```css
/* Use CSS variables for theming */
:root {
    --primary-color: #007bff;
    --secondary-color: #6c757d;
    --success-color: #28a745;
    --danger-color: #dc3545;
}

/* Component-based styling */
.component-name {
    /* Component styles */
}

.component-name__element {
    /* Element styles */
}

.component-name--modifier {
    /* Modifier styles */
}
```

## Testing & Deployment

### 1. Local Development

```bash
# Install dependencies
composer install

# Set up environment
cp .env.example .env
# Edit .env with your database credentials

# Run migrations
php migrate.php

# Start development server
php -S localhost:8080 -t www/html/refactor
```

### 2. Testing

```bash
# Run validation tests
php tests/validate_structure.php

# Test specific endpoints
curl -X GET http://localhost:8080/api/events
```

### 3. Deployment

**Important**: Follow the Strangler Fig pattern:

```
Production URL: https://backoffice.yakimafinds.com/
Refactor URL: https://backoffice.yakimafinds.com/refactor/
```

**Never replace the main system directly!**

### 4. Environment Configuration

Create `.env` file:

```env
# Database
DB_HOST=localhost
DB_NAME=yakimafinds
DB_USER=your_user
DB_PASS=your_password

# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=https://backoffice.yakimafinds.com/refactor

# External Services
GOOGLE_MAPS_API_KEY=your_key
FIRECRAWL_API_KEY=your_key
```

## Best Practices Summary

1. **Always use interfaces** for major components
2. **Follow DDD principles** - keep business logic in the domain layer
3. **Use dependency injection** - don't instantiate dependencies directly
4. **Write defensive code** - validate input, handle errors gracefully
5. **Keep controllers thin** - business logic belongs in services
6. **Use repository pattern** - abstract database access
7. **Follow PSR standards** - consistency is key
8. **Document your code** - especially complex business logic
9. **Test your changes** - use the validation scripts provided
10. **Respect the existing system** - incremental improvements over rewrites

## Common Patterns

### 1. CRUD Operations

```php
// Controller
public function store(): void
{
    $data = $this->getJsonInput();
    
    if (!$this->validate($data, [
        'title' => 'required|string|min:3',
        'description' => 'required|string'
    ])) {
        return;
    }
    
    try {
        $entity = $this->service->create($data);
        $this->json(['success' => true, 'data' => $entity]);
    } catch (\Exception $e) {
        $this->error($e->getMessage());
    }
}
```

### 2. Pagination

```php
// Service
public function paginate(int $page = 1, int $perPage = 20): array
{
    $offset = ($page - 1) * $perPage;
    $total = $this->repository->count();
    $items = $this->repository->findAll(['limit' => $perPage, 'offset' => $offset]);
    
    return [
        'items' => $items,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
            'total_items' => $total
        ]
    ];
}
```

### 3. Error Handling

```php
// Global error handler
set_exception_handler(function($exception) {
    error_log($exception->getMessage());
    
    if ($this->isApiRequest()) {
        $this->json([
            'error' => true,
            'message' => 'Internal server error'
        ], 500);
    } else {
        $this->render('error/500');
    }
});
```

## Support & Resources

- **Documentation**: `/home/robug/YFEvents/docs/`
- **Example Code**: Look at existing modules (Event, Shop, User)
- **Database Schema**: `/home/robug/YFEvents/database/schema.sql`
- **API Examples**: `/home/robug/YFEvents/docs/api-examples.md`

---

*Last Updated: June 2025*
*Version: 2.0.0*