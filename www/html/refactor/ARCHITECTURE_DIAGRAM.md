# YFEvents Refactor Architecture Diagram

## Clean Architecture Layers

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              PRESENTATION LAYER                              │
│  ┌─────────────────────────────┐  ┌─────────────────────────────────────┐  │
│  │      Web Controllers        │  │         API Controllers             │  │
│  │  ┌─────────────────────┐   │  │  ┌─────────────────────────────┐   │  │
│  │  │  HomeController     │   │  │  │  EventApiController       │   │  │
│  │  │  EventController    │   │  │  │  ShopApiController        │   │  │
│  │  │  ShopController     │   │  │  │  ClaimApiController       │   │  │
│  │  │  AdminControllers   │   │  │  │  UserApiController        │   │  │
│  │  └─────────────────────┘   │  │  └─────────────────────────────┘   │  │
│  └─────────────────────────────┘  └─────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                             APPLICATION LAYER                                │
│  ┌─────────────────────────────┐  ┌─────────────────────────────────────┐  │
│  │    Application Services     │  │          Validation                 │  │
│  │  ┌─────────────────────┐   │  │  ┌─────────────────────────────┐   │  │
│  │  │  AdminService       │   │  │  │  ConfigValidator          │   │  │
│  │  │  ClaimService       │   │  │  │  UserValidator            │   │  │
│  │  │  ConfigService      │   │  │  │  EventValidator           │   │  │
│  │  │  UserService        │   │  │  │  ShopValidator            │   │  │
│  │  └─────────────────────┘   │  │  └─────────────────────────────┘   │  │
│  └─────────────────────────────┘  └─────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                               DOMAIN LAYER                                   │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌─────────────────┐   │
│  │   Events     │ │    Shops     │ │    Users     │ │     Claims      │   │
│  │  ┌────────┐ │ │  ┌────────┐  │ │  ┌────────┐  │ │  ┌───────────┐  │   │
│  │  │ Event  │ │ │  │  Shop  │  │ │  │  User  │  │ │  │  Seller   │  │   │
│  │  │Category│ │ │  │Category│  │ │  │  Role  │  │ │  │  Buyer    │  │   │
│  │  └────────┘ │ │  └────────┘  │ │  │ Perm.  │  │ │  │  Item     │  │   │
│  │             │ │              │ │  └────────┘  │ │  │  Offer    │  │   │
│  │ Services:   │ │ Services:    │ │ Services:     │ │  │  Sale     │  │   │
│  │ EventService│ │ ShopService  │ │ UserService   │ │  └───────────┘  │   │
│  └──────────────┘ └──────────────┘ └──────────────┘ └─────────────────┘   │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────────────────┐  │
│  │                        Common Interfaces                             │  │
│  │  EntityInterface | RepositoryInterface | ServiceInterface           │  │
│  └─────────────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                           INFRASTRUCTURE LAYER                               │
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────────────────┐   │
│  │   Repositories  │  │    Database     │  │   External Services      │   │
│  │ ┌─────────────┐│  │ ┌─────────────┐ │  │ ┌──────────────────────┐ │   │
│  │ │EventRepo    ││  │ │ Connection  │ │  │ │ EmailService         │ │   │
│  │ │ShopRepo     ││  │ │ PDO MySQL   │ │  │ │ SMSService           │ │   │
│  │ │UserRepo     ││  │ │ Migrations  │ │  │ │ QRCodeService        │ │   │
│  │ │ClaimRepos   ││  │ └─────────────┘ │  │ │ PermissionService    │ │   │
│  │ └─────────────┘│  └─────────────────┘  │ │ SEOService           │ │   │
│  └─────────────────┘                       │ └──────────────────────┘ │   │
│                                            └──────────────────────────┘   │
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────────────────┐   │
│  │      HTTP       │  │  Configuration  │  │        Container         │   │
│  │ ┌─────────────┐│  │ ┌─────────────┐ │  │ ┌──────────────────────┐ │   │
│  │ │   Router    ││  │ │   Config    │ │  │ │  DI Container        │ │   │
│  │ │ErrorHandler ││  │ │   .env      │ │  │ │  Service Provider    │ │   │
│  │ └─────────────┘│  │ └─────────────┘ │  │ └──────────────────────┘ │   │
│  └─────────────────┘  └─────────────────┘  └──────────────────────────┘   │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Data Flow

```
Request Flow:
============

    [HTTP Request]
         │
         ▼
    [Router] ──────────────┐
         │                 │
         ▼                 ▼
    [Controller]     [ErrorHandler]
         │                 │
         ▼                 │
    [Service Layer] ◄──────┘
         │
         ▼
    [Domain Logic]
         │
         ▼
    [Repository]
         │
         ▼
    [Database]
```

## Module Structure

```
┌─────────────────────────────────────────────────────────────┐
│                      YFEvents Core                          │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐    │
│  │   Events    │  │    Shops    │  │   Admin Panel   │    │
│  │  Calendar   │  │  Directory  │  │   Management    │    │
│  │  Scraping   │  │  Geocoding  │  │   Reports       │    │
│  └─────────────┘  └─────────────┘  └─────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                        Modules                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────┐    │
│  │   YFAuth    │  │   YFClaim   │  │YFClassifieds   │    │
│  │   - Login   │  │ - Sellers   │  │  - Listings    │    │
│  │   - RBAC    │  │ - Buyers    │  │  - Categories  │    │
│  │   - JWT     │  │ - Offers    │  │  - Search      │    │
│  └─────────────┘  └─────────────┘  └─────────────────┘    │
│  ┌─────────────┐  ┌─────────────┐                         │
│  │   YFTheme   │  │YFCommunication│                        │
│  │  - Themes   │  │ - Internal   │                         │
│  │  - Styles   │  │ - Chat       │                         │
│  └─────────────┘  └─────────────┘                         │
└─────────────────────────────────────────────────────────────┘
```

## Database Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Database: yakima_finds                   │
├─────────────────────────────────────────────────────────────┤
│  Core Tables           │  Module Tables                     │
│  ┌─────────────────┐  │  ┌──────────────────────────────┐ │
│  │ events          │  │  │ yfclaim_* (6 tables)         │ │
│  │ calendar_sources│  │  │ yfauth_* (5 tables)          │ │
│  │ local_shops     │  │  │ communication_* (5 tables)   │ │
│  │ shop_categories │  │  │ yfclassifieds_* (planned)    │ │
│  │ shop_owners     │  │  │ yftheme_* (planned)          │ │
│  └─────────────────┘  │  └──────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

## Deployment Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Production Environment                    │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │          Legacy System (/www/html/)                  │   │
│  │  - Active production                                 │   │
│  │  - Traditional PHP architecture                      │   │
│  │  - Direct file routing                              │   │
│  └─────────────────────────────────────────────────────┘   │
│                           │                                 │
│                           ▼ Shared Database                 │
│                           │                                 │
│  ┌─────────────────────────────────────────────────────┐   │
│  │       Refactor System (/www/html/refactor/)         │   │
│  │  - 70% complete                                     │   │
│  │  - Clean Architecture                               │   │
│  │  - Gradual migration                                │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  URLs:                                                      │
│  - Production: https://backoffice.yakimafinds.com/         │
│  - Refactor: https://backoffice.yakimafinds.com/refactor/  │
└─────────────────────────────────────────────────────────────┘
```

## Key Architectural Decisions

1. **Clean Architecture**: Ensures business logic is independent of frameworks and external agencies
2. **Repository Pattern**: Allows switching data sources without affecting business logic
3. **Dependency Injection**: Enables loose coupling and better testability
4. **Domain-Driven Design**: Aligns code structure with business domains
5. **API-First Approach**: Prepares for future mobile apps and third-party integrations
6. **Modular System**: Allows independent feature development and deployment

## Current Status (70% Complete)

### ✅ Complete
- Core domain models
- Repository implementations
- Service layer
- Basic controllers
- Database structure
- Configuration system

### 🚧 In Progress
- YFClaim controllers (4-5 hours remaining)
- Authentication enhancements
- API documentation

### ❌ TODO
- View layer (if needed)
- Comprehensive testing
- Caching implementation
- Background job queues
- Performance optimization
- Complete migration from legacy