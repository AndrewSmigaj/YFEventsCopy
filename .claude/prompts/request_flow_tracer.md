# Trace Request Flow from Entry to Response

Systematically trace how requests flow through the application to identify which components are actually being used.

## Tracing Strategy

### 1. Start from Confirmed Entry Point
Using the production entry point identified earlier:
1. Open the main index.php
2. Follow the execution path line by line
3. Document every include, require, or class instantiation

### 2. Bootstrap Analysis
Track the initialization sequence:
```php
// Example flow to trace:
require 'vendor/autoload.php';  // -> Note autoloader location
require 'bootstrap/app.php';     // -> Follow this file
$app = new Application();        // -> What Application class?
$app->run();                     // -> How does run() work?
```

### 3. Router Discovery
Identify how routes are loaded:
- Look for route registration (e.g., `$router->get()`, `Route::get()`)
- Find route definition files (routes/*.php)
- Check for route caching mechanisms
- Identify middleware pipeline

### 4. Controller Resolution
For a sample route (e.g., /seller/login):
1. Find which controller handles it
2. Trace from route to controller instantiation
3. Check dependency injection/service container
4. Verify the controller file actually exists and is used

### 5. Service Layer Tracing
Track service usage:
- Which services are injected into controllers?
- What service provider registers them?
- Are there multiple versions of the same service?

### 6. Database Layer
Trace database interactions:
- Which models/repositories are used?
- What database connection is established?
- Are there multiple database configurations?

## Trace Documentation Format

Document each significant step:

```yaml
request_flow:
  entry: /public/index.php
  
  bootstrap_sequence:
    - file: /vendor/autoload.php
      purpose: "PSR-4 autoloading"
    - file: /bootstrap/app.php
      purpose: "Application initialization"
      creates: "YFEvents\\Application\\Bootstrap"
    
  routing:
    loader: /routes/web.php
    router_class: "YFEvents\\Infrastructure\\Http\\Router"
    route_example:
      uri: "/seller/login"
      controller: "YFEvents\\Presentation\\Http\\Controllers\\ClaimsController"
      method: "showSellerLogin"
    
  service_container:
    class: "YFEvents\\Infrastructure\\Container\\Container"
    providers:
      - "YFEvents\\Infrastructure\\Providers\\ServiceProvider"
      - "YFEvents\\Infrastructure\\Providers\\DatabaseProvider"
    
  database:
    connection_file: /config/database.php
    connection_class: "PDO"
    models_namespace: "YFEvents\\Domain\\*"
    
  authentication:
    service: "YFEvents\\Application\\Services\\AuthService"
    underlying: "YFEvents\\Modules\\YFAuth\\Services\\AuthService"
    session_handler: "PHP native sessions"

suspicious_findings:
  - "Multiple Application classes found"
  - "Two different routers (old MVC vs new)"
  - "Legacy authentication in /includes/auth.php"
```

## Key Patterns to Identify

### Modern Clean Architecture Pattern:
- Entry → Bootstrap → Router → Controller → Service → Repository → Model

### Legacy MVC Pattern:
- Entry → Include files → Direct controller files → Direct model files

### Mixed Pattern (Problematic):
- Some routes use new system, others use legacy
- Need to identify which is which

## Critical Questions to Answer
1. Is there ONE routing system or multiple?
2. Do all requests go through the same bootstrap?
3. Are there backdoor entry points bypassing the main system?
4. Which authentication system is actually validating users?
5. Are there multiple database connections/configs being used?

Remember: Follow the CODE EXECUTION, not what the documentation claims.