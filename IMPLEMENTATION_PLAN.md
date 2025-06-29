# YFEvents Platform Alignment Implementation Plan

## Overview
This document breaks down the platform alignment into manageable tasks for Claude Code instances. Each task is designed to fit within context limits and produce testable results.

## Phase 1: Unified User System (High Priority)

### Task 1.1: Database Schema Migration
**Context Size**: Small - Single file creation
**Steps**:
1. Create migration script `/migrations/001_unified_user_roles.sql`
2. Add role flag columns to users table
3. Create backup script for safety
4. Document rollback procedure

### Task 1.2: User Model Update
**Context Size**: Medium - 2-3 files
**Steps**:
1. Update `/includes/User.php` class with role properties
2. Add role getter/setter methods
3. Create role checking functions (hasRole, addRole, removeRole)
4. Update user authentication to load roles

### Task 1.3: Migration Tool for Existing Users
**Context Size**: Medium - 2-3 files
**Steps**:
1. Create `/admin/tools/migrate-user-roles.php`
2. Map existing sellers from yfc_sellers to user role flags
3. Map communication hub users to YF staff/vendor flags
4. Create verification report

### Task 1.4: Admin User Management Update
**Context Size**: Large - 4-5 files
**Steps**:
1. Update `/admin/users.php` to show role checkboxes
2. Modify user edit form to manage multiple roles
3. Update user creation to assign roles
4. Add bulk role assignment tool

## Phase 2: Offer Notification System (High Priority)

### Task 2.1: Database Updates for Notifications
**Context Size**: Small - Single file
**Steps**:
1. Create `/migrations/002_notification_system.sql`
2. Add notification_preferences table
3. Add notification_queue table
4. Add email_sent tracking to offers

### Task 2.2: Email Notification Service
**Context Size**: Medium - 3-4 files
**Steps**:
1. Create `/includes/NotificationService.php`
2. Implement sendOfferNotification method
3. Create email templates for offers
4. Add queue processing logic

### Task 2.3: Offer Submission Integration
**Context Size**: Medium - 2-3 files
**Steps**:
1. Update `/api/submit-offer.php` to trigger notifications
2. Add notification preferences check
3. Queue email for sending
4. Log notification attempts

### Task 2.4: Notification Preferences UI
**Context Size**: Medium - 3-4 files
**Steps**:
1. Create `/seller/notification-settings.php`
2. Add form for email/SMS preferences
3. Add instant vs digest options
4. Create preference save endpoint

## Phase 3: Sold Price Tracking (High Priority)

### Task 3.1: Database Schema for Sold Items
**Context Size**: Small - Single file
**Steps**:
1. Create `/migrations/003_sold_price_tracking.sql`
2. Add sold_price, sold_at, final_buyer_id columns
3. Create sold_items_archive view
4. Add indexes for performance

### Task 3.2: Update Item Sold Workflow
**Context Size**: Medium - 3-4 files
**Steps**:
1. Update `/seller/item-sold.php` to capture final price
2. Modify mark as sold form to include price input
3. Record buyer information if available
4. Update item status and timestamps

### Task 3.3: Sold Items Archive Page
**Context Size**: Large - 4-5 files
**Steps**:
1. Create `/marketplace/sold-archive.php`
2. Add filtering by category, date range, price range
3. Show price statistics and trends
4. Add search functionality

### Task 3.4: Public API for Sold Data
**Context Size**: Medium - 2-3 files
**Steps**:
1. Create `/api/sold-items.php` endpoint
2. Return JSON with privacy controls
3. Add pagination and filtering
4. Cache results for performance

## Phase 4: Communication Hub Access Control (Medium Priority)

### Task 4.1: Role-Based Access Middleware
**Context Size**: Medium - 2-3 files
**Steps**:
1. Create `/includes/AccessControl.php`
2. Implement requireInternalUser() method
3. Check for YF staff/vendor/associate roles
4. Add access denied page

### Task 4.2: Update Communication Entry Points
**Context Size**: Medium - 3-4 files
**Steps**:
1. Update `/communication/index.php` with role check
2. Add "Internal Use Only" banner
3. Update all communication sub-pages
4. Test access restrictions

## Phase 5: Public Offer System (Medium Priority)

### Task 5.1: Guest Offer Database
**Context Size**: Small - Single file
**Steps**:
1. Create `/migrations/004_guest_offers.sql`
2. Add guest_offers table
3. Add email verification tokens
4. Create conversion tracking

### Task 5.2: Guest Offer Form
**Context Size**: Medium - 3-4 files
**Steps**:
1. Create `/marketplace/make-offer-guest.php`
2. Add form with name, email, phone, offer amount
3. Implement CAPTCHA for spam prevention
4. Send verification email

### Task 5.3: Guest to User Conversion
**Context Size**: Medium - 3-4 files
**Steps**:
1. Create `/auth/convert-guest.php`
2. Link guest offers to new account
3. Migrate offer history
4. Send welcome email

## Phase 6: Enhanced Marketplace Features (Low Priority)

### Task 6.1: Business Directory Enhancements
**Context Size**: Medium - 3-4 files
**Steps**:
1. Update `/admin/shops.php` to add business type
2. Add thrifting/antiquing categories
3. Create trip planner prototype
4. Add "nearby shops" feature

## Implementation Strategy

### For Each Task:
1. **Start**: Read relevant existing code
2. **Plan**: Write implementation approach
3. **Code**: Implement changes incrementally
4. **Test**: Verify functionality
5. **Document**: Update comments and docs

### Context Management:
- Each task limited to 5 files maximum
- Use focused, specific file edits
- Complete one sub-task before moving to next
- Save progress in task completion log

### Testing Approach:
- Create test script for each major feature
- Manual testing checklist for UI changes
- Log all test results

### Rollback Safety:
- Database migrations include DOWN scripts
- File backups before major changes
- Git commits after each completed task

## Execution Order:
1. Start with Phase 1 (Unified Users) - Foundation for everything
2. Then Phase 2 & 3 in parallel (Notifications & Sold Tracking)
3. Phase 4 after user roles are solid
4. Phase 5 & 6 as time permits

## Success Metrics:
- [ ] All users have proper role flags
- [ ] Sellers receive offer notifications
- [ ] Sold prices are tracked and visible
- [ ] Communication hub is internal-only
- [ ] Guests can make offers without accounts
- [ ] Business directory emphasizes thrifting

---

**Note**: Each task is designed to be completed in one Claude Code session with full context awareness. Tasks can be assigned to different instances working in parallel.