# YFEvents Project State Documentation
**Source**: Project Owner  
**Date Received**: June 30, 2025  
**Document Type**: Current State and Implementation Guide

## Key Features for Public Marketplace

### For Buyers (Public)
- Browse all active listings
- Submit offers with contact info
- View sold items for price comparison
- Share interesting finds on social media
- Filter by category, price, location

### For Sellers (Registered Users)
- Create estate sales or classified listings
- Receive instant offer notifications
- Accept/decline offers
- Mark items as sold with final price
- Track listing performance

### Notification System
- Email alerts for new offers
- SMS option for urgent offers
- In-platform messaging
- Offer tracking dashboard

### Internal-Only Module

#### YFCommunication Hub
- YF Staff, Vendors, Associates only
- Internal coordination and strategy
- Not accessible to public

### Unified User Roles
- **Public**: Can browse, make offers (no account needed)
- **Registered User**: Can become seller
- **Seller**: List items, receive offers
- **Business Owner**: Claim directory listing
- **YF Vendor/Staff/Associate**: Internal communication access
- **Moderator**: Content oversight
- **Admin**: Full control

### Growth Mechanisms
1. **Social Sharing**: Every listing has share buttons
2. **Sold Price Transparency**: Builds trust and repeat usage
3. **Offer Notifications**: Quick response = more sales
4. **Public Access**: No barriers to browsing/buying
5. **Market Insights**: Sold prices help pricing decisions

This creates a complete ecosystem where public marketplace activity drives growth while internal tools coordinate YF's market expansion.

---

# YFEvents - Event Platform & Business Directory

## 🚀 Quick Start (30-second overview)
- **Project**: Strategic platform suite for Yakima Finds - events calendar + business directory + marketplace
- **Status**: Production system working ✅, Modern refactor 70% complete 🚧
- **Current Sprint**: Complete YFClaim estate sale module (4-5 hours remaining)
- **⚠️ CRITICAL**: NEVER modify production system at /www/html/ - work only in refactor directory

## 🎯 Current Sprint Tasks

### Priority 1: YFClaim Controllers (Est: 2-3 hours)
- [ ] Public sale browsing interface (/src/Controller/ClaimPublicController.php)
- [ ] Buyer portal for offers (/src/Controller/ClaimBuyerController.php)
- [ ] Seller dashboard management (/src/Controller/ClaimSellerController.php)

### Priority 2: Repository Implementations (Est: 1-2 hours)
- [ ] ClaimSaleRepository.php - Database operations for sales
- [ ] ClaimItemRepository.php - Item management and search
- [ ] ClaimOfferRepository.php - Offer system with buyer notifications

### Reference Files:
- **Entities**: /src/Domain/Claims/ (✅ Complete - use as models)
- **Business Logic**: /src/Service/ClaimService.php (✅ Complete)
- **Controller Pattern**: /src/Controller/EventController.php (follow this structure)
- **Database Schema**: See admin interface at /refactor/admin/claims.php

## 📁 Project Structure

```
/home/robug/YFEvents/www/html/           # 🚨 PRODUCTION - DO NOT TOUCH
/home/robug/YFEvents/www/html/refactor/  # 🧪 Test deployment (work here)
/home/robug/YFEvents-refactor/           # 💻 Main development repo
```

### Live URLs:
- **Production**: https://backoffice.yakimafinds.com/ (original working system)
- **Refactor**: https://backoffice.yakimafinds.com/refactor/ (modern architecture)
- **Admin**: https://backoffice.yakimafinds.com/refactor/admin/ (management interface)

## 🛠 Common Commands

### Development Workflow
```bash
# Navigate to development directory
cd /home/robug/YFEvents-refactor

# Start local development server
php -S localhost:8000

# Run comprehensive tests
php background_test_runner.php --resume --verbose

# Check current progress
cat test_progress.json | jq '.current_phase, .overall_progress'

# Deploy to test environment
rsync -av . /home/robug/YFEvents/www/html/refactor/ --exclude='.git'
```

### Testing & Monitoring
```bash
# Monitor test progress
tail -f test_runner.log

# Check specific module status
php tests/domain_test.php Claims

# View recent errors
jq '.test_results.errors[-5:]' test_progress.json
```

## 🔒 Security Requirements
- **BEFORE ANY COMMIT**: Run `./PRE_COMMIT_SECURITY_CHECK.sh`
- Never commit: API keys (AIza...), passwords, real credentials
- Pre-commit hook blocks secrets automatically

## 📊 Module Status

| Module        | Database | Domain | Services | Controllers | Views | Complete |
|---------------|----------|--------|----------|-------------|-------|----------|
| Events        | ✅       | ✅     | ✅       | ✅          | ✅    | 100%     |
| Shops         | ✅       | ✅     | ✅       | ✅          | ✅    | 100%     |
| Users         | ✅       | ✅     | ✅       | ✅          | ✅    | 100%     |
| Communication | ✅       | ✅     | ✅       | ✅          | ✅    | 100%     |
| Claims        | ✅       | ✅     | ✅       | 🚧          | 🚧    | 70%      |

## 🌍 Environment Configuration

```bash
# Database (from existing config)
DB_HOST=localhost
DB_NAME=yfevents
DB_USER=root
DB_PASS=[check /config/database.php]

# APIs (already configured)
GOOGLE_MAPS_API_KEY=[from production system]
```

## 🏗 Architecture Overview
- **Technology Stack**: PHP 8.2+, MySQL, Vanilla JS, Domain-Driven Design
- **Pattern**: Clean Architecture with Repository Pattern, PSR-4 autoloading
- **Dependencies**: Composer only, no build process required

### Key Directories:
```
src/
├── Domain/           # Business entities and interfaces
├── Service/          # Business logic layer
├── Controller/       # HTTP request handling
├── Repository/       # Data access (interfaces + implementations)
├── Infrastructure/   # DI container, config, utilities
public/              # Web-accessible files
config/              # Environment configuration
tests/               # Validation and testing scripts
```

## 🔧 Common Issues & Solutions

### "Cannot connect to database"
- Check MySQL service: `sudo systemctl status mysql`
- Verify credentials in `/config/database.php`

### "Refactor site not loading"
- Check permissions: `chmod -R 755 /home/robug/YFEvents/www/html/refactor/`
- View Apache errors: `tail -f /var/log/apache2/error.log`

### "Tests failing"
- Clear cache: `rm -rf /tmp/yfevents`
- Reset test state: `rm test_progress.json && php background_test_runner.php`

### "YFClaim module not working"
- Verify database tables exist: Check admin interface `/refactor/admin/claims.php`
- Ensure sample data loaded: `php scripts/install_claim_data.php`

## 🎯 YFClaim Implementation Guide

### Controller Implementation Pattern:
1. Extend BaseController
2. Use dependency injection for services
3. Follow RESTful conventions
4. Return JSON responses for API endpoints
5. Handle validation errors gracefully

### Example Controller Structure:
```php
class ClaimPublicController extends BaseController {
    public function __construct(private ClaimService $claimService) {}
    
    public function listSales(): JsonResponse {
        // Implementation following EventController pattern
    }
}
```

### Database Integration:
- Use existing ClaimService for business logic
- Implement repository interfaces in `/src/Repository/Claims/`
- Follow existing patterns from Events/Shops domains

## 📈 Success Metrics
- **API Endpoints**: 90+ working (target: 100+)
- **Test Coverage**: Core domains complete
- **Performance**: <200ms response times
- **Mobile**: PWA-enabled with offline support

## 🚨 Deployment Safety Rules
1. NEVER modify anything in /www/html/ (production)
2. ALWAYS work in /refactor/ subdirectory
3. TEST all changes in refactor environment first
4. VERIFY production system remains functional
5. BACKUP before any major changes

---

**Last Updated**: 2024-12-20  
**Version**: 1.2.0  
**Next Milestone**: YFClaim completion (ETA: 4-5 hours)