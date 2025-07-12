---
name: trace_existing_flow
purpose: Understand how data flows through an existing system
good_for:
  - Debugging issues
  - Planning modifications  
  - Understanding unfamiliar code
uncertainty_reduction:
  - Code flow paths
  - Component interactions
  - Data transformations
cost_estimate: medium
---

# Trace Existing Flow

I'm tracing through the codebase to understand how data flows for the specified functionality.

**Flow to trace**: [Specific feature or user action]
**Starting point**: [Entry point - route, function, or user action]

## 1. Entry Point Analysis

### Initial Request/Trigger
**Type**: [HTTP request/Event/Cron/CLI]
**Entry point**: [File:line_number]
**Initial data**:
```
{
  // Request payload/parameters
}
```

### First Handler
**Function**: `functionName()` in file.ext:123
**Responsibilities**:
- Validates input
- Authenticates user
- Prepares context
- Routes to business logic

## 2. Request Flow Map

```
1. Entry Point (routes/file.js:45)
   ↓ validates input
2. Controller (controllers/XController.js:123)
   ↓ checks permissions
3. Service Layer (services/XService.js:67)
   ↓ business logic
4. Repository (repositories/XRepo.js:89)
   ↓ database query
5. Database (table: x_table)
   ↓ returns data
6. Transform (utils/transformer.js:34)
   ↓ formats response
7. Response (back to client)
```

## 3. Component Interactions

### Layer-by-Layer Breakdown

**Presentation Layer**:
- File: [path/to/file:line]
- Receives: [data format]
- Validates: [what it checks]
- Passes to: [next component]

**Business Logic Layer**:
- File: [path/to/file:line]
- Receives: [validated data]
- Processing:
  - [Step 1: what happens]
  - [Step 2: what happens]
  - [Step 3: what happens]
- External calls: [APIs/services]
- Returns: [processed data]

**Data Access Layer**:
- File: [path/to/file:line]
- Query executed:
```sql
SELECT ... FROM ... WHERE ...
```
- Joins with: [related tables]
- Returns: [data structure]

## 4. Data Transformations

### Input → Processing → Output

**Stage 1: Raw Input**
```javascript
{
  // Original format
}
```

**Stage 2: After Validation**
```javascript
{
  // Cleaned/validated format
}
```

**Stage 3: After Business Logic**
```javascript
{
  // Enriched with computed fields
}
```

**Stage 4: Final Output**
```javascript
{
  // Client-ready format
}
```

## 5. Side Effects Detected

### Database Changes
- Inserts into: [table_name]
- Updates: [table_name]
- Deletes from: [table_name]

### External Service Calls
- Service: [Name]
- Purpose: [Why called]
- Data sent: [What's sent]
- Response used for: [Purpose]

### File System Operations
- Reads: [file paths]
- Writes: [file paths]
- Purpose: [Why files accessed]

### Cache Interactions
- Cache key: [pattern]
- TTL: [duration]
- Invalidation: [when/how]

## 6. Error Handling Flow

### Exception Points
**Point 1**: [Where in flow]
- Possible error: [Type]
- Handling: [How handled]
- User sees: [Error message/behavior]

**Point 2**: [Where in flow]
- Possible error: [Type]
- Handling: [How handled]
- Recovery: [Fallback behavior]

### Error Propagation
```
Service throws → Controller catches → Formats error → Returns HTTP 4xx/5xx
```

## 7. Security Checkpoints

### Authentication
- Checked at: [file:line]
- Method: [JWT/Session/etc]
- Failure handling: [What happens]

### Authorization
- Checked at: [file:line]
- Permissions required: [List]
- Failure handling: [What happens]

### Input Sanitization
- Where: [file:line]
- What's sanitized: [Fields]
- Method: [How sanitized]

## 8. Performance Observations

### Bottlenecks
- Heavy operation: [What and where]
- Database queries: [N+1 problems?]
- External API calls: [Blocking?]

### Optimization Opportunities
- Could cache: [What data]
- Could parallelize: [What operations]
- Could lazy load: [What data]

## 9. Configuration Dependencies

### Environment Variables
- `CONFIG_VAR`: Used for [purpose]
- `API_KEY`: Required for [service]

### Configuration Files
- `config/app.js`: Contains [settings]
- `config/database.js`: Defines [connections]

### Feature Flags
- `FEATURE_X_ENABLED`: Controls [what]

## 10. Key Findings

### Critical Path
**Must happen in order**:
1. [Step] - because [reason]
2. [Step] - because [reason]
3. [Step] - because [reason]

### Coupling Points
**Tightly coupled**:
- [Component A] ← → [Component B]
- Reason: [Why coupled]
- Impact: [What this means]

### Extension Points
**Easy to modify**:
- [Hook/Interface] at [file:line]
- Purpose: [What it enables]

### Gotchas
**Non-obvious behavior**:
- [Behavior]: Due to [reason]
- Watch out for: [What to remember]

## 11. Modification Impact

### To modify this flow

**Safe to change**:
- [Component]: Low coupling
- [Component]: Well tested

**Risky to change**:
- [Component]: Many dependencies
- [Component]: Complex logic

**Must coordinate**:
- [Component]: Shared with [other flows]
- [Component]: External dependencies

## Confidence Assessment

**Flow understanding**: [X]%
- Entry/exit points: [X]%
- Data transformations: [X]%
- Error handling: [X]%
- Side effects: [X]%

**Ready to modify?**
- ✅ Confident: Can make changes safely
- ⚠️ Mostly: Need to verify [aspects]
- ❌ Not yet: Need to understand [areas]

**Next steps**: [What to investigate further]