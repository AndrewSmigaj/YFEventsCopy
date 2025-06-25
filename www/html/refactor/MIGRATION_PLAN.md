# Migration Plan: Refactor → Production
## Moving from /refactor/ to root domain backoffice.yakimafinds.com

### Current State Analysis
- **Current URL**: https://backoffice.yakimafinds.com/refactor/
- **Target URL**: https://backoffice.yakimafinds.com/
- **Current Path**: /home/robug/YFEvents/www/html/refactor/
- **Target Path**: /home/robug/YFEvents/www/html/

### Phase 1: Code Preparation (Pre-Migration)

#### 1.1 Create Configuration System
```php
// config/app.php
return [
    'base_path' => env('APP_BASE_PATH', ''),
    'base_url' => env('APP_BASE_URL', 'https://backoffice.yakimafinds.com'),
    'admin_path' => env('APP_ADMIN_PATH', '/admin'),
];
```

#### 1.2 Update All Hardcoded Paths
- Replace `/refactor/` with config-based paths
- Use relative paths for internal links
- Create helper functions for URL generation

#### 1.3 Database Configuration
- Ensure no hardcoded paths in database
- Update any stored URLs to be relative

### Phase 2: File Structure Changes

#### 2.1 Identify Path Dependencies
```bash
# Find all references to /refactor/
grep -r "/refactor/" . --include="*.php" --include="*.js" --include="*.css"

# Find all references to refactor in configs
grep -r "refactor" config/ --include="*.php"
```

#### 2.2 Create Path Abstraction Layer
```php
// src/Helpers/PathHelper.php
class PathHelper {
    public static function url($path = '') {
        $basePath = config('app.base_path', '');
        return rtrim($basePath, '/') . '/' . ltrim($path, '/');
    }
    
    public static function adminUrl($path = '') {
        return self::url('admin/' . ltrim($path, '/'));
    }
    
    public static function assetUrl($path = '') {
        return self::url('assets/' . ltrim($path, '/'));
    }
}
```

### Phase 3: Update Critical Files

#### 3.1 Router Updates
- Update index.php to use dynamic base paths
- Modify route definitions to be path-agnostic

#### 3.2 Admin Navigation
- Convert all links to use PathHelper
- Ensure no hardcoded /refactor/ paths

#### 3.3 API Endpoints
- Update API base URLs
- Ensure CORS headers are correct

### Phase 4: Testing Strategy

#### 4.1 Create Test Environment
1. Copy refactor to a test directory
2. Update configs for test environment
3. Run comprehensive tests

#### 4.2 Test Checklist
- [ ] All admin pages load correctly
- [ ] Navigation works without 404s
- [ ] API endpoints respond correctly
- [ ] Assets (CSS/JS/images) load
- [ ] Database connections work
- [ ] Email functionality works
- [ ] File uploads work
- [ ] Authentication works

### Phase 5: Migration Steps

#### 5.1 Pre-Migration Backup
```bash
# Backup current production
tar -czf backup-production-$(date +%Y%m%d-%H%M%S).tar.gz /home/robug/YFEvents/www/html/

# Backup database
mysqldump -u yfevents -p yakima_finds > backup-db-$(date +%Y%m%d-%H%M%S).sql
```

#### 5.2 Migration Process
1. **Maintenance Mode**
   ```bash
   # Create maintenance page
   echo "Site under maintenance. Back soon!" > /home/robug/YFEvents/www/html/maintenance.html
   ```

2. **Move Files**
   ```bash
   # Rename current production
   mv /home/robug/YFEvents/www/html /home/robug/YFEvents/www/html-old
   
   # Move refactor to production
   mv /home/robug/YFEvents/www/html/refactor /home/robug/YFEvents/www/html
   ```

3. **Update Configurations**
   - Remove /refactor/ from all config files
   - Update .htaccess if needed
   - Update any cron jobs

4. **Test Critical Functions**
   - Admin login
   - Event creation
   - Shop management
   - API endpoints

#### 5.3 Rollback Plan
```bash
# If issues occur
mv /home/robug/YFEvents/www/html /home/robug/YFEvents/www/html-failed
mv /home/robug/YFEvents/www/html-old /home/robug/YFEvents/www/html
```

### Phase 6: Post-Migration Tasks

#### 6.1 Update External References
- Update any external services pointing to /refactor/
- Update documentation
- Update API documentation

#### 6.2 Monitor and Verify
- Check error logs
- Monitor 404 errors
- Verify all functionality

### Configuration Changes Needed

#### 1. Create Environment File
```env
# .env
APP_BASE_PATH=
APP_BASE_URL=https://backoffice.yakimafinds.com
APP_ADMIN_PATH=/admin
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=yakima_finds
DB_USERNAME=yfevents
DB_PASSWORD=yfevents_pass
```

#### 2. Update Bootstrap Files
```php
// bootstrap.php
$basePath = $_ENV['APP_BASE_PATH'] ?? '';
define('BASE_PATH', $basePath);
define('BASE_URL', $_ENV['APP_BASE_URL'] ?? 'https://backoffice.yakimafinds.com');
```

### Risk Mitigation

1. **Gradual Migration**
   - Test with a subdomain first
   - Migrate during low-traffic hours
   - Have rollback ready

2. **Path Consistency**
   - Use helper functions everywhere
   - No hardcoded paths
   - Relative paths for internal links

3. **Testing**
   - Automated tests for all routes
   - Manual testing of critical paths
   - Load testing after migration

### Timeline Estimate

- **Phase 1-3**: 2-3 hours (Code preparation)
- **Phase 4**: 1-2 hours (Testing)
- **Phase 5**: 30 minutes (Actual migration)
- **Phase 6**: 1 hour (Verification)

**Total**: 4-6 hours with testing

### Next Steps

1. Create the PathHelper class
2. Update all PHP files to use dynamic paths
3. Create test script to verify all links
4. Schedule migration window
5. Execute migration plan

This plan ensures a smooth transition from /refactor/ to production root with minimal downtime and maximum safety.