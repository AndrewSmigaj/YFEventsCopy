# Phase 4: Homepage Redesign Implementation Plan

## Overview
Transform the YFEvents homepage from static service cards to a dynamic dashboard grid layout that serves both buyers (browsing items/sales) and professional sellers (needing tools/access).

## Target Audience
- **Primary**: Buyers/treasure hunters (70-80 users) looking for items and sales
- **Secondary**: Professional estate sale companies (20-30 users) managing listings
- **Community**: Local event attendees and businesses

## Layout Design: Dashboard Grid

```
[=============== Hero Section with Stats Bar ================]

[======== Featured Items Grid (65%) ========][= Spotlight =====]
[======== (3x3 or 3x4 item grid)    ========][= Sidebar (35%) =]

[===== Active Sales List (50%) =====][==== This Week Events (50%) ====]

[=============== Tools & Integration Bar ====================]

[==================== Footer ===============================]
```

## Section Specifications

### 1. Hero Section (Keep existing with updates)
- **Current Design**: Navy gradient background, white text
- **Updates**:
  - Change primary button to "Browse All Items" → `/claims/items`
  - Keep "View Estate Sales" → `/claims`
  - Keep "Seller Login" → `/admin/login`
- **Stats Bar**: Keep dynamic stats (sales, events, items, shops)

### 2. Featured Items Grid (Main content - 65% width)
- **Purpose**: Visual showcase of best current items
- **Layout**: 3x3 grid (desktop), 2x2 (tablet), 2x6 (mobile)
- **Each Item Shows**:
  - Square image (250x250px)
  - Price overlay on image
  - Item title (truncated)
  - Sale name (small text)
- **Interaction**: Click item → `/claims/item/{id}`
- **Footer**: "Browse All Items (with filters) →" button

### 3. Spotlight Sidebar (35% width)
- **This Weekend Box**:
  - Title: "This Weekend"
  - Stats: "12 Sales • 8 Events"
  - Featured: One "Hot Item" with larger image
- **Quick Actions**:
  - "Seller Portal" button (prominent)
  - "Get Email Alerts" link
  - "Print Weekend List" link

### 4. Active Sales Section (50% width)
- **Format**: Clean list view
- **Shows**: 5 current sales
- **Each Sale**:
  ```
  Estate Sale Company Name
  Dec 6-8 • 9am-5pm
  123 Main St, Yakima • 47 items
  ```
- **Footer**: "View All Active Sales →"

### 5. This Week's Events (50% width)
- **Format**: Simple list
- **Shows**: 5-6 upcoming events
- **Each Event**:
  ```
  Farmers Market
  Saturday, Dec 7 • 9am-2pm
  ```
- **Footer**: "View Full Calendar →"

### 6. Tools & Integration Bar
- **Style**: Light gray background, full width
- **Content**: Horizontal link list
  - Integration & APIs
  - Business Directory & Map
  - Seller Resources
  - Submit Event/Sale
  - Help & Support

### 7. Footer (Keep simple)
- Copyright notice
- Admin login link
- System status link

## CSS Implementation

### Grid System
```css
.dashboard-grid {
    display: grid;
    grid-template-columns: 65% 35%;
    gap: 30px;
    margin: 60px 0;
}

.lower-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin: 40px 0;
}

@media (max-width: 768px) {
    .dashboard-grid,
    .lower-grid {
        grid-template-columns: 1fr;
    }
}
```

### Component Styling
- **Cards**: White background, subtle shadow, 8px border radius
- **Images**: Object-fit: cover, lazy loading
- **Typography**: Keep existing (Georgia headers, system fonts body)
- **Colors**: Keep existing palette (Navy #1B2951, Copper #B87333)

## Implementation Steps

### Step 1: Backend Updates (1 hour)
1. Add `getFeaturedItems($limit = 12)` method to HomeController
2. Update `index()` method to pass all data to view
3. Ensure existing methods work (getCurrentSales, getUpcomingEvents)

### Step 2: Remove Old Sections (30 min)
1. Remove service cards grid (lines ~602-665)
2. Remove developer resources card (lines ~667-679)
3. Keep footer as is

### Step 3: Implement New Layout (2 hours)
1. Add dashboard grid structure
2. Implement featured items grid
3. Create spotlight sidebar
4. Add sales/events lists
5. Create tools bar

### Step 4: Styling & Responsive (1 hour)
1. Add CSS for grid layouts
2. Style individual components
3. Test responsive breakpoints
4. Ensure mobile usability

### Step 5: Testing & Polish (30 min)
1. Test with real data
2. Verify all links work
3. Check performance (image loading)
4. Browser testing

## Technical Considerations

### Image Handling
- Thumbnails: 250x250px for grid
- Lazy loading with Intersection Observer
- Fallback for missing images

### Performance
- Cache featured items for 5 minutes
- Inline critical CSS
- Defer non-critical JavaScript

### SEO/Accessibility
- Semantic HTML5 elements
- Alt text for all images
- Proper heading hierarchy
- Schema.org markup for events

## Success Metrics
- Page loads under 2 seconds
- Featured items visible above fold
- All sections populate with real data
- Mobile-friendly (Google test pass)
- No JavaScript errors

## Rollback Plan
- Git revert to previous version
- No database changes required
- All changes contained to HomeController

## Total Timeline: ~5 hours

This plan balances the needs of both buyers and sellers while maintaining simplicity and performance for a small community platform.