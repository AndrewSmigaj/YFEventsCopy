# YFEvents Refactor → Production Migration Checklist

## Pre-Migration Verification ✓

### 1. Backup Everything
- [ ] Full database backup: `mysqldump -u yfevents -p yakima_finds > backup-$(date +%Y%m%d).sql`
- [ ] Full file backup: `tar -czf backup-files-$(date +%Y%m%d).tar.gz /home/robug/YFEvents/www/html/`
- [ ] Document current working URLs

### 2. Code Preparation
- [ ] All hardcoded `/refactor/` paths removed
- [ ] PathHelper class implemented
- [ ] Config files use dynamic paths
- [ ] No absolute internal URLs

### 3. Current Status Check
```bash
# Check for remaining hardcoded paths
grep -r "/refactor/" . --include="*.php" | grep -v ".git" | wc -l

# Should return 0 or only necessary references
```

## Migration Steps 🚀

### Phase 1: Test in Subdirectory (Current State)
1. **Verify everything works at**: https://backoffice.yakimafinds.com/refactor/
   - [ ] Admin login works
   - [ ] Navigation works (no 404s)
   - [ ] Events page loads
   - [ ] Shops page loads
   - [ ] API endpoints respond
   - [ ] Assets load (CSS/JS)

### Phase 2: Prepare for Root Deployment
1. **Update configuration**:
   ```bash
   # The config/app.php will automatically detect root vs subdirectory
   # No changes needed - it's already dynamic!
   ```

2. **Test critical paths**:
   - [ ] `/admin/dashboard` → Admin dashboard
   - [ ] `/api/events` → Events API
   - [ ] `/api/shops` → Shops API
   - [ ] `/events` → Public events page
   - [ ] `/shops` → Public shops page

### Phase 3: Deployment
1. **Put site in maintenance mode**:
   ```bash
   echo "Site under maintenance. Back in 10 minutes!" > /home/robug/YFEvents/www/html/maintenance.html
   # Configure Apache/Nginx to show maintenance page
   ```

2. **Move files**:
   ```bash
   # Backup current production
   mv /home/robug/YFEvents/www/html /home/robug/YFEvents/www/html-old
   
   # Move refactor to root
   mv /home/robug/YFEvents/www/html-old/refactor /home/robug/YFEvents/www/html
   
   # Copy modules directory (if not in refactor)
   cp -r /home/robug/YFEvents/www/html-old/modules /home/robug/YFEvents/www/html/
   
   # Copy communication directory (if not in refactor)
   cp -r /home/robug/YFEvents/www/html-old/communication /home/robug/YFEvents/www/html/
   ```

3. **Update permissions**:
   ```bash
   chown -R www-data:www-data /home/robug/YFEvents/www/html/
   chmod -R 755 /home/robug/YFEvents/www/html/
   chmod -R 777 /home/robug/YFEvents/www/html/cache/
   chmod -R 777 /home/robug/YFEvents/www/html/logs/
   ```

### Phase 4: Post-Migration Testing
1. **Critical Functions**:
   - [ ] Admin login: https://backoffice.yakimafinds.com/admin/
   - [ ] Create test event
   - [ ] Edit test shop
   - [ ] Check API: https://backoffice.yakimafinds.com/api/events

2. **Navigation Check**:
   - [ ] All admin menu items work
   - [ ] Breadcrumbs work
   - [ ] "View Site" links work
   - [ ] Module links work

3. **External Integration**:
   - [ ] YFClassifieds module accessible
   - [ ] Communication hub works
   - [ ] Email processing works

## Rollback Plan 🔄

If anything goes wrong:

```bash
# Quick rollback
mv /home/robug/YFEvents/www/html /home/robug/YFEvents/www/html-failed
mv /home/robug/YFEvents/www/html-old /home/robug/YFEvents/www/html

# Remove maintenance mode
rm /home/robug/YFEvents/www/html/maintenance.html
```

## Post-Migration Cleanup

1. **After 24 hours of stable operation**:
   ```bash
   # Archive old files
   tar -czf html-old-archive.tar.gz /home/robug/YFEvents/www/html-old/
   
   # Remove old directory
   rm -rf /home/robug/YFEvents/www/html-old/
   ```

2. **Update documentation**:
   - [ ] Update README with new structure
   - [ ] Update API documentation
   - [ ] Update deployment guides

3. **Monitor**:
   - [ ] Check error logs for 404s
   - [ ] Monitor database for issues
   - [ ] Check user feedback

## Configuration Files to Update

### Apache/Nginx
- Remove any `/refactor` specific rules
- Update DocumentRoot if needed
- Check .htaccess rules

### Cron Jobs
- Update any paths in cron scripts
- Test email processing cron
- Test scraper cron

### External Services
- Update any webhooks
- Update API documentation
- Notify any API consumers

## Success Criteria ✅

The migration is successful when:
1. All pages load without 404 errors
2. All functionality works as before
3. No hardcoded paths remain
4. Site works at root domain
5. Can be moved to any path without code changes

## Emergency Contacts

- Server Admin: [Contact info]
- Database Admin: [Contact info]
- Domain/DNS: [Contact info]

---

**Estimated Time**: 30-60 minutes
**Risk Level**: Low (with proper backup)
**Rollback Time**: 5 minutes