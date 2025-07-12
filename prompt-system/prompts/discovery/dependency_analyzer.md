---
name: dependency_analyzer
purpose: Analyze dependencies between components and external systems
good_for:
  - Understanding coupling
  - Planning refactoring
  - Assessing change impact
uncertainty_reduction:
  - Dependency relationships
  - Coupling strength
  - Change propagation
cost_estimate: medium
---

# Dependency Analyzer

I'm analyzing dependencies to understand how components are connected and what the impact of changes might be.

**Focus**: [Component/module/system to analyze]
**Scope**: [Internal only/Include external/Full system]

## 1. Dependency Overview

### Dependency Types Found
- **Import/Require**: Direct code dependencies
- **Database**: Shared schema/data dependencies  
- **Configuration**: Shared config dependencies
- **Runtime**: Dynamic/runtime dependencies
- **External**: Third-party service dependencies

### Dependency Metrics
- Total components: [N]
- Total dependencies: [M]
- Average dependencies per component: [X]
- Circular dependencies found: [Y]
- External dependencies: [Z]

## 2. Direct Dependencies

### Import/Module Dependencies

**Component: UserService**
```javascript
// Dependencies IN (what depends on this)
├── UserController.js
├── AdminController.js
└── AuthMiddleware.js

// Dependencies OUT (what this depends on)
├── UserRepository.js
├── EmailService.js
├── CacheService.js
└── validators/userValidator.js
```

**Coupling Analysis**:
- Coupling type: [Loose/Tight]
- Reason: [Imports interface/Imports implementation]
- Risk: [Low/Medium/High]

### Dependency Tree
```
app.js
├── routes/index.js
│   ├── controllers/UserController.js
│   │   ├── services/UserService.js
│   │   │   ├── repositories/UserRepository.js
│   │   │   │   └── models/User.js
│   │   │   └── services/EmailService.js
│   │   └── validators/userValidator.js
│   └── middleware/auth.js
│       └── services/AuthService.js
└── config/database.js
```

## 3. Data Dependencies

### Database Schema Dependencies

**Table: users**
```sql
Referenced by:
- orders (user_id FK)
- sessions (user_id FK)
- user_profiles (user_id FK)
- audit_logs (entity_id when type='user')

References:
- roles (role_id FK)
- organizations (org_id FK)
```

**Impact of schema changes**:
- Adding column: [Low impact - backward compatible]
- Removing column: [High impact - breaks queries]
- Changing types: [Medium impact - may break code]

### Shared Data Patterns
**Cache Dependencies**:
- Key pattern: `user:{id}`
- Used by: UserService, AuthService
- Invalidated by: UserService.update()
- TTL: 3600 seconds

## 4. Configuration Dependencies

### Shared Configuration
**Database Config**:
```javascript
Used by:
- All repository classes
- Migration scripts
- Seed scripts
- Health checks
```

**API Keys/Secrets**:
```javascript
EMAIL_API_KEY:
- EmailService
- NotificationService

PAYMENT_API_KEY:
- PaymentService
- RefundService
```

### Feature Flags
```javascript
FEATURE_NEW_UI:
- UserController
- DashboardController
- NavigationComponent

FEATURE_ADVANCED_SEARCH:
- SearchService
- SearchController
```

## 5. Runtime Dependencies

### Service Discovery
**Dynamic Dependencies**:
- Service A discovers Service B via [Method]
- Load balancer routes to [Instances]
- Circuit breaker protects [Services]

### Event-Based Dependencies
**Event: UserCreated**
```
Publisher: UserService
Subscribers:
├── EmailService (welcome email)
├── AnalyticsService (track signup)
└── BillingService (create account)
```

**Event: OrderCompleted**
```
Publisher: OrderService
Subscribers:
├── InventoryService (update stock)
├── EmailService (confirmation)
├── ShippingService (create label)
└── AnalyticsService (track sale)
```

## 6. External Dependencies

### Third-Party Services

**Payment Gateway (Stripe)**:
- Used by: PaymentService
- Criticality: HIGH
- Fallback: None
- SLA: 99.95%

**Email Service (SendGrid)**:
- Used by: EmailService
- Criticality: MEDIUM
- Fallback: SMTP
- SLA: 99.9%

**Storage Service (AWS S3)**:
- Used by: FileService
- Criticality: HIGH
- Fallback: Local storage
- SLA: 99.99%

### Package Dependencies
**Production Dependencies**:
```json
{
  "express": "^4.18.0",     // Core framework
  "mongoose": "^6.0.0",     // Database ORM
  "jsonwebtoken": "^8.5.1", // Authentication
  "axios": "^0.27.0"        // HTTP client
}
```

**Security Vulnerabilities**:
- Package X: CVE-2023-XXX (High)
- Package Y: CVE-2023-YYY (Medium)

## 7. Circular Dependencies

### Detected Circular Dependencies

**Cycle 1**:
```
UserService → OrderService → PaymentService → UserService
```
- Problem: Tight coupling
- Solution: Extract shared interface

**Cycle 2**:
```
AuthController → AuthService → UserService → AuthMiddleware → AuthController
```
- Problem: Middleware coupling
- Solution: Dependency injection

## 8. Dependency Strength Analysis

### Strong Dependencies (Tight Coupling)
**UserService ←→ UserRepository**:
- Reason: Direct implementation dependency
- Impact: Cannot change independently
- Refactor: Interface abstraction

### Weak Dependencies (Loose Coupling)
**EmailService ← → EmailProvider**:
- Reason: Interface-based
- Impact: Easy to swap providers
- Pattern: Strategy pattern

### Hidden Dependencies
**Implicit through database**:
- OrderService reads user data directly
- Should go through UserService

**Implicit through cache**:
- Services share cache keys
- No clear ownership

## 9. Change Impact Analysis

### If UserService Changes

**Direct Impact**:
- UserController (must update calls)
- AdminController (may need updates)
- Tests (will need updates)

**Indirect Impact**:
- OrderService (through events)
- CacheService (key patterns)
- EmailService (data format)

**Cascade Risk**: MEDIUM
- 3 direct dependencies
- 5 indirect dependencies
- 2 database impacts

### If Database Schema Changes

**users table modification**:
- Repositories affected: 3
- Services affected: 5
- Controllers affected: 4
- Risk: HIGH

## 10. Dependency Patterns

### Good Patterns Found
✅ **Dependency Injection**:
- Services receive dependencies
- Easy to test and mock
- Found in: [Components]

✅ **Interface Segregation**:
- Small, focused interfaces
- Minimal coupling
- Found in: [Components]

### Anti-Patterns Found
❌ **God Object**:
- UserService knows too much
- Too many dependencies
- Needs splitting

❌ **Chatty Interface**:
- Multiple calls between services
- Performance impact
- Found in: [Components]

## 11. Refactoring Opportunities

### High-Value Refactoring

**1. Extract User Profile Service**:
- Current: Part of UserService
- Benefit: Reduce UserService complexity
- Impact: 3 components need updates

**2. Introduce Message Queue**:
- Current: Direct service calls
- Benefit: Decouple event handling
- Impact: Better scalability

**3. Add Repository Interfaces**:
- Current: Direct implementation dependency
- Benefit: Easier testing, flexibility
- Impact: All services need updates

## 12. Dependency Health Score

### Overall Health: [X/10]

**Positive Factors**:
- Clear layer separation: +2
- Interface usage: +1
- Documented dependencies: +1

**Negative Factors**:
- Circular dependencies: -2
- God objects: -1
- Hidden dependencies: -1

### Risk Assessment

**High Risk Dependencies**:
1. External payment service (no fallback)
2. Circular dependency in auth flow
3. Tight database coupling

**Mitigation Strategies**:
1. Add payment service abstraction
2. Break circular dependencies
3. Introduce repository pattern

## Summary

**Dependency Understanding**: [X]%
- Direct dependencies: [X]%
- Data dependencies: [X]%
- External dependencies: [X]%
- Impact analysis: [X]%

**Key Findings**:
1. [Most important discovery]
2. [Second important discovery]
3. [Third important discovery]

**Recommended Actions**:
1. [Highest priority fix]
2. [Second priority]
3. [Third priority]

**Next Investigation**: [What to analyze next]