# Runtime Discovery System

This system helps discover what code actually runs in your application by instrumenting key execution points and logging to the SystemLogger.

## Setup

1. **Enable Runtime Discovery**
   ```bash
   php scripts/enable_runtime_discovery.php
   ```
   This will:
   - Set ENABLE_RUNTIME_DISCOVERY=true in your .env file
   - Clear existing logs to start fresh
   - Truncate the system_logs database table

2. **Walk Through Your Site**
   - Visit every page that works
   - Submit forms
   - Test all functionality
   - The system will log:
     - Routes matched
     - Controllers instantiated
     - Services resolved
     - Database queries
     - 404 errors

3. **Analyze the Results**
   ```bash
   php scripts/analyze_runtime_discovery.php
   ```
   This will show:
   - Active routes and their controllers
   - Active services and their implementations
   - Active namespaces
   - Module detection
   - Database table usage
   - Any 404 errors

## What Gets Logged

### Request Flow
- `REQUEST_START` - When a request begins
- `BOOTSTRAP_COMPLETE` - After application bootstrap
- `SERVICE_PROVIDER_COMPLETE` - After all services registered

### Routing
- `ROUTE_DISPATCH_START` - When routing begins
- `ROUTE_MATCHED` - When a route is matched
- `ROUTE_NOT_FOUND` - For 404 errors
- `CONTROLLER_INSTANTIATED` - When controller is created
- `CONTROLLER_ACTION_COMPLETE` - After action executes

### Dependency Injection
- `SERVICE_RESOLVED` - When a service is resolved from container
  - Tracks abstract → concrete mappings
  - Records namespaces used

### Database (if SystemLogger configured)
- Database queries are automatically logged

## Coverage Verification

If you visit a page and it works but no logs appear:
1. Check if logging is enabled in that execution path
2. Some areas that might need additional instrumentation:
   - Direct PHP file access (bypassing router)
   - AJAX endpoints
   - Legacy code paths
   - Middleware execution
   - View rendering

## Disabling Runtime Discovery

To disable after analysis:
1. Set `ENABLE_RUNTIME_DISCOVERY=false` in .env
2. Or remove the environment variable entirely

## Architecture Documentation

Use the analysis results to:
1. Document active routes in architecture.yaml
2. List actually used namespaces
3. Identify active vs inactive modules
4. Map the real dependency graph
5. Document database tables in use

This gives you ground truth about what's actually deployed and working.