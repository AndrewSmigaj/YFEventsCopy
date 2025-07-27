# Identify Production Entry Points

You need to systematically identify which entry points are actually being used in production when multiple versions exist.

## Critical Discovery Goals
1. Find the ACTUAL production web root (not test/dev directories)
2. Identify all PHP entry points accessible from the web
3. Determine which ones are actively used vs obsolete

## Discovery Strategy

### 1. Web Server Configuration
First check Apache/Nginx configuration:
- Look for DocumentRoot directives
- Check for VirtualHost configurations
- Identify rewrite rules that affect routing

### 2. File System Analysis
Starting from potential web roots:
- `/public` - Modern frameworks often use this
- `/www/html` - Traditional Apache location
- `/web` - Some frameworks use this
- Root directory - Legacy applications

For each potential web root:
1. List all .php files
2. Check for index.php (primary entry point)
3. Look for .htaccess files (routing rules)
4. Check file modification times (recent = likely active)

### 3. Entry Point Validation
For each potential entry point:
1. Check if it includes autoloader (vendor/autoload.php)
2. Look for bootstrap/initialization code
3. Check if it sets up routing
4. See if it connects to the database

### 4. Cross-Reference Checks
- Check git history - which files are frequently modified?
- Look for environment files (.env) near entry points
- Check for configuration files that reference paths
- Look for deployment scripts that might reveal production paths

### 5. Request Flow Test
For suspected production entry points:
1. Trace the include/require chain
2. Identify which router/dispatcher is loaded
3. Check which controllers can be reached
4. Verify database connections are established

## Output Format

Provide findings in this structure:

```yaml
production_entry_points:
  confirmed:
    primary: /path/to/main/index.php
    secondary:
      - /path/to/admin/index.php
      - /path/to/api/index.php
  
  suspicious_duplicates:
    - path: /other/index.php
      reason: "Old version - uses deprecated autoloader"
    - path: /legacy/index.php  
      reason: "No recent modifications, different DB config"

  web_root:
    production: /actual/production/web/root
    evidence:
      - "DocumentRoot in Apache config"
      - ".env file present"
      - "Recent git commits"

  routing_system:
    type: "front-controller|file-based|mixed"
    router_file: /path/to/router.php
    routes_definition: /path/to/routes/web.php
```

## Red Flags to Watch For
- Multiple index.php files in different directories
- Different autoloader paths (vendor location)
- Conflicting route definitions
- Multiple .env files
- Different database configurations
- Commented out or old deployment scripts

Remember: The goal is to identify what's ACTUALLY being used, not what documentation says should be used.