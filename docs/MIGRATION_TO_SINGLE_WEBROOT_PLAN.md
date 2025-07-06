# Complete Migration to Single Web Root - Best Design Plan

## Current Understanding (As of 2025-07-05)

### Two Web Roots Exist
1. **`/www/html/`** - Legacy system with some working features
   - Static homepage with module cards
   - Legacy admin panel in `/admin/` subdirectory
   - Direct file access pattern

2. **`/public/`** - Clean Architecture implementation (mostly complete)
   - Router-based system through `index.php`
   - Controllers handle all logic
   - Module views are just templates

### Working Features Are Actually in Clean Architecture
- **Seller dashboard**: Fully implemented in `ClaimsController`
- **Authentication**: Unified through YFAuth via `ClaimAuthService`
- **Routing**: Working through `/public/index.php` and `/routes/web.php`
- **Homepage**: Implemented in `HomeController` with dashboard grid layout

### Key Discovery
The confusion arose because:
- Web server might be pointing to `/www/html/` instead of `/public/`
- This caused us to see the old static homepage instead of the new dynamic one
- All the "working" features (seller login, estate sales) are actually implemented in the Clean Architecture controllers

## Best Design Approach

### Step 1: Verify Current Web Server Configuration
- Check where Apache/web server DocumentRoot is actually pointing
- Determine if it's serving from `/www/html/` or `/public/`
- The architecture.yaml says it should be `/public/`

### Step 2: Complete Admin Migration
The only major gap is admin functionality:
- `AdminDashboardController` exists but only returns JSON for API
- Need to add HTML rendering methods to match legacy admin features:
  - Dashboard view
  - Events management interface
  - Shops management interface
  - Scrapers management interface
  - System utilities interface
- This maintains Clean Architecture while providing full functionality

### Step 3: Consolidate to /public/
1. Ensure all routes in `web.php` cover legacy URLs
2. Move any missing static assets from `/www/html/` to `/public/`
3. Update Apache DocumentRoot to `/public/` (if not already)
4. Test all functionality:
   - Homepage (already implemented)
   - Calendar
   - Admin panels
   - Seller dashboard
   - Estate sales

### Step 4: Clean Architecture Patterns
1. **Single entry point** through `/public/index.php`
2. **All routes** defined in `/routes/web.php`
3. **Controllers** handle logic and render HTML
4. **Module `/www/` directories** just contain view templates
5. **No direct file access** - everything goes through router

### Step 5: Remove Legacy System
1. Archive `/www/html/` for reference
2. Delete legacy files
3. Single source of truth achieved
4. Clean, maintainable, AI-friendly architecture

## Why This is Good Design

1. **Single Entry Point**: Everything routes through one `index.php`
   - Predictable request flow
   - Centralized error handling
   - Easy to add middleware

2. **Predictable Structure**: Routes → Controllers → Views
   - Want to know what handles `/admin/events`? Check `routes/web.php`
   - Find the controller and method immediately
   - No hunting through directories

3. **Maintainable**: Clear separation of concerns
   - Controllers handle business logic
   - Views handle presentation
   - Models handle data

4. **Testable**: Each component isolated
   - Can test controllers without web server
   - Can test business logic without UI
   - Can mock dependencies

5. **AI-Friendly**: Clear patterns, easy to understand
   - Consistent naming conventions
   - Predictable file locations
   - Self-documenting route definitions

## What We're NOT Reimplementing

✓ **Seller features**: Already complete in `ClaimsController`
✓ **Authentication**: Already unified through YFAuth  
✓ **Homepage**: Already implemented in `HomeController`
✓ **Routing**: Already working through central router
✓ **Module structure**: Already integrated properly

We just need to:
- Complete admin HTML rendering in controllers
- Point web server to correct directory
- Clean up legacy files

## Implementation Order

1. First verify where web server is pointing
2. Complete admin interface HTML in controllers
3. Test everything works through `/public/`
4. Archive and remove `/www/html/`

This completes the half-finished migration properly rather than maintaining two confusing systems.