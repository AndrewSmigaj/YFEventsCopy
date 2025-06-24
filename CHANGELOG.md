# Changelog

All notable changes to the YFEvents project will be documented in this file.

## [2.0.0] - 2024-12-20

### Added

#### Communication Module Enhancements
- **Picks Feature**: New location-based sharing for estate and yard sales
  - Dedicated picks page with dual forum/map view
  - Google Maps integration with address autocomplete
  - Location metadata storage (coordinates, address, event times)
  - Interactive map markers with info windows
  - Mobile-responsive design

#### Claims Module Implementation
- **Repository Layer**: Complete implementation of all domain repositories
  - BuyerRepository: Buyer authentication and management
  - ItemRepository: Estate sale items with categories
  - OfferRepository: Buyer offers on items
  - SaleRepository: Estate sale management
  - SellerRepository: Estate sale company management

- **Public Interfaces**: User-facing pages for estate sales
  - Public browsing of active sales
  - Individual sale detail pages with items
  - Buyer authentication with SMS/email verification
  - Buyer offers management dashboard
  - Seller dashboard with statistics
  - Sale creation workflow (in progress)

- **Admin Controllers**: Comprehensive admin management
  - AdminClaimsController: Estate sale administration
  - AdminCommunicationController: Messaging system management
  - AdminEmailController: Email event processing
  - AdminModulesController: Module management interface
  - AdminScraperController: Event scraper configuration
  - AdminSettingsController: System configuration
  - AdminThemeController: Theme and appearance settings
  - AdminUsersController: User administration

### Changed

#### Infrastructure Improvements
- **Router**: Updated to use proper dependency injection container resolution
- **Namespace**: Fixed inconsistencies (YakimaFinds → YFEvents) across 84 files
- **Session Handling**: Custom session directory configuration for better security
- **Repository Pattern**: Fixed method signatures for AbstractRepository compatibility
  - Changed `delete(int $id)` to `deleteById(int $id)` to avoid conflicts
  - Updated `save()` methods to accept EntityInterface with runtime type checking

#### Domain Model Fixes
- **User Entity**: Updated to match actual database schema
  - Changed single `name` to `firstName`/`lastName`
  - Renamed `password` to `passwordHash`
  - Added `username`, `emailVerified`, `failedLoginAttempts`
  - Removed non-existent fields
  
- **Shop Entity**: Removed properties not in database
  - Removed `imageUrl`, `active`, duplicate `hours`
  - Kept only `operatingHours` for hours data

#### Communication Module Updates
- **MessageService**: Added metadata support for location fields
- **MessageApiController**: 
  - Added location field handling in store() method
  - Updated formatMessage() to include location data
  - Fixed authentication error handling
- **Message Display**: Added special rendering for picks in channel view

### Fixed
- Session authentication between pages and API endpoints
- Channel ID parameter extraction in router
- Metadata storage in communication messages
- Repository method signature conflicts with interfaces
- Missing View infrastructure for template rendering

### Security
- Added custom session directory with proper permissions
- Updated .gitignore to exclude session files
- Removed test and debug files from repository

## [1.1.0] - 2024-12-19

### Added
- Complete YFCommunication module implementation
- PWA support with mobile theme detection
- Real-time messaging interface
- Mobile-optimized responsive design
- Service worker for offline support
- Theme switcher for mobile/desktop preference

### Database
- Created 7 communication database tables
- Added communication_messages, communication_channels, etc.

## [1.0.0] - 2024-12-01

### Initial Refactor Release
- Domain-Driven Design architecture
- Modern PHP 8.1+ implementation
- PSR-4 autoloading
- Dependency injection container
- Repository pattern implementation
- Clean architecture with layer separation