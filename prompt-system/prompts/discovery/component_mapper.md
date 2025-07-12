---
name: component_mapper
purpose: Map system components and their relationships
good_for:
  - Understanding system architecture
  - Identifying integration points
  - Planning structural changes
uncertainty_reduction:
  - System structure
  - Component responsibilities
  - Interaction patterns
cost_estimate: medium
---

# Component Mapper

I'm mapping the system's components to understand the architecture, responsibilities, and relationships.

**System**: [Project/module name]
**Focus area**: [Specific subsystem if applicable]

## 1. High-Level Architecture

### System Type
- **Pattern**: [MVC/Clean/Hexagonal/Microservices]
- **Style**: [Monolith/Modular/Distributed]
- **Framework**: [Express/Laravel/Django/None]

### Layer Structure
```
┌─────────────────────────────────────┐
│      Presentation Layer             │
│  (Controllers/Routes/Views)         │
├─────────────────────────────────────┤
│      Application Layer              │
│  (Services/Use Cases/Logic)         │
├─────────────────────────────────────┤
│      Domain Layer                   │
│  (Models/Entities/Rules)            │
├─────────────────────────────────────┤
│      Infrastructure Layer           │
│  (Database/External/Files)          │
└─────────────────────────────────────┘
```

## 2. Component Inventory

### Core Components

**Presentation Components**:
```
routes/
├── api/
│   ├── auth.js         - Authentication endpoints
│   ├── users.js        - User management
│   └── products.js     - Product CRUD
└── web/
    ├── pages.js        - Static pages
    └── dashboard.js    - User dashboard
```

**Business Logic Components**:
```
services/
├── AuthService.js      - Authentication logic
├── UserService.js      - User business rules
├── ProductService.js   - Product operations
└── EmailService.js     - Notification handling
```

**Data Access Components**:
```
repositories/
├── UserRepository.js   - User data access
├── ProductRepository.js - Product data access
└── BaseRepository.js   - Common operations

models/
├── User.js            - User entity
├── Product.js         - Product entity
└── Order.js          - Order entity
```

**Infrastructure Components**:
```
infrastructure/
├── database/
│   ├── connection.js  - DB configuration
│   └── migrations/    - Schema changes
├── cache/
│   └── redis.js      - Cache client
└── external/
    ├── payment.js    - Payment gateway
    └── storage.js    - File storage
```

## 3. Component Relationships

### Dependency Graph
```
Controller
    ↓ uses
Service (orchestrates)
    ↓ uses          ↓ uses
Repository      External API
    ↓ uses          ↓ returns
Database         Response
```

### Component Interactions

**AuthController → AuthService**:
- Purpose: Handle authentication requests
- Contract: `authenticate(credentials): Promise<User>`
- Data flow: HTTP request → Validated input → User object

**AuthService → UserRepository**:
- Purpose: Verify user credentials
- Contract: `findByEmail(email): Promise<User>`
- Data flow: Email → Database query → User record

**AuthService → TokenService**:
- Purpose: Generate auth tokens
- Contract: `generateToken(user): string`
- Data flow: User object → JWT token

## 4. Shared Components

### Utilities
```
utils/
├── validators/      - Input validation
├── formatters/      - Data formatting
├── helpers/         - Common functions
└── constants/       - Shared constants
```

### Middleware
```
middleware/
├── auth.js         - Authentication check
├── validate.js     - Request validation
├── errorHandler.js - Error formatting
└── logger.js       - Request logging
```

### Cross-Cutting Concerns
- **Logging**: Used by all layers
- **Error Handling**: Centralized in middleware
- **Validation**: Shared validation rules
- **Authentication**: Guards multiple routes

## 5. External Dependencies

### Third-Party Services
**Payment Gateway**:
- Component: `services/PaymentService.js`
- Integration: REST API
- Used by: Order processing

**Email Service**:
- Component: `services/EmailService.js`
- Integration: SMTP/API
- Used by: Notifications

**Storage Service**:
- Component: `services/StorageService.js`
- Integration: S3/Local
- Used by: File uploads

### Package Dependencies
**Core Dependencies**:
- Framework: [Express/Fastify/etc]
- Database: [MySQL/PostgreSQL/MongoDB]
- Authentication: [Passport/JWT]
- Validation: [Joi/Yup]

## 6. Data Flow Patterns

### Request Processing
```
1. Route receives request
2. Middleware validates/authenticates
3. Controller orchestrates
4. Service implements logic
5. Repository accesses data
6. Response formatted and sent
```

### Event Patterns
```
EventEmitter
├── UserCreated      → EmailService
├── OrderCompleted   → InventoryService
└── PaymentReceived → OrderService
```

### State Management
- **Session**: Server-side sessions
- **Cache**: Redis for performance
- **Database**: Source of truth

## 7. Module Boundaries

### Well-Defined Modules
**Auth Module**:
- Clear boundaries ✅
- Single responsibility ✅
- Minimal dependencies ✅

**Payment Module**:
- Clear boundaries ✅
- External dependency ⚠️
- Good abstraction ✅

### Problematic Areas
**User Module**:
- Mixed concerns ❌
- Circular dependencies ⚠️
- Needs refactoring

## 8. Configuration Management

### Configuration Sources
```
config/
├── default.js      - Base configuration
├── development.js  - Dev overrides
├── production.js   - Prod settings
└── test.js        - Test configuration
```

### Environment Dependencies
- Database connection
- API keys
- Feature flags
- Service URLs

## 9. Entry Points

### API Endpoints
- `POST /api/auth/login` - User login
- `GET /api/users/:id` - User details
- `POST /api/products` - Create product

### Background Jobs
- `jobs/emailQueue.js` - Process emails
- `jobs/reportGenerator.js` - Daily reports

### Event Listeners
- Database changes
- File uploads
- Webhook receivers

## 10. Component Health

### Well-Designed Components
✅ **AuthService**:
- Clear purpose
- Good tests
- Low coupling

✅ **ProductRepository**:
- Clean interface
- Follows patterns
- Reusable

### Components Needing Attention
⚠️ **UserController**:
- Too many responsibilities
- Needs splitting
- High complexity

❌ **LegacyAdapter**:
- Poor documentation
- High coupling
- Technical debt

## 11. Extension Points

### Easy to Extend
- **Middleware pipeline**: Add new middleware
- **Service layer**: Add new services
- **Repository pattern**: Add new repos

### Plugin Architecture
- **Hooks**: `beforeSave`, `afterCreate`
- **Events**: Subscribe to system events
- **Strategies**: Payment, storage, auth

## 12. Key Insights

### Strengths
- Clear layer separation
- Good use of patterns
- Testable components

### Weaknesses
- Some circular dependencies
- Inconsistent error handling
- Mixed abstraction levels

### Improvement Opportunities
1. Extract user profile to separate service
2. Standardize error responses
3. Add dependency injection
4. Improve configuration management

## Component Understanding

**Overall understanding**: [X]%
- Structure clarity: [X]%
- Relationships: [X]%
- Responsibilities: [X]%
- Extension points: [X]%

**Ready for modifications?**
- ✅ Yes: Clear component map
- ⚠️ Partial: Need to explore [areas]
- ❌ No: Major gaps in [components]

**Next steps**: [Further investigation needed]