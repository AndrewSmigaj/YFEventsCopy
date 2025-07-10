# YFEvents Fresh Deployment - Super Easy Version

## Quick Start (3 Commands!)

On your fresh Ubuntu server, run:

```bash
# 1. Download the deployment script
wget https://raw.githubusercontent.com/AndrewSmigaj/YFEventsCopy/main/DEPLOY_FRESH.sh

# 2. Make it executable
chmod +x DEPLOY_FRESH.sh

# 3. Run it!
sudo ./DEPLOY_FRESH.sh
```

That's it! The script handles EVERYTHING.

## What the Script Does

1. **Installs all software** - PHP 8.1, MySQL, Apache, Composer
2. **Creates secure database** - Generates random password automatically
3. **Downloads Clean Architecture code** - From GitHub main branch
4. **Configures everything** - Database, permissions, Apache
5. **Creates admin user** - Interactive setup
6. **Shows success page** - With all your access URLs

## After Deployment

The script will show you:
- Your site URL (http://YOUR-IP/)
- Admin panel URL (http://YOUR-IP/admin/)
- Database credentials location
- Next steps

## Requirements

- Fresh Ubuntu 20.04 or 22.04 server
- Root access (sudo)
- Internet connection

## Time Required

About 5-10 minutes total

## If Something Goes Wrong

The script saves database credentials in `/root/.yfevents_db_credentials`

To start over:
```bash
sudo rm -rf /var/www/yfevents
sudo mysql -e "DROP DATABASE IF EXISTS yakima_finds;"
sudo mysql -e "DROP USER IF EXISTS 'yfevents'@'localhost';"
```

Then run the script again.

## Features

- ✅ No manual configuration needed
- ✅ Handles all dependencies automatically  
- ✅ Correct database schema order
- ✅ Clean Architecture only (no legacy confusion)
- ✅ Safe - no dangerous git operations
- ✅ Clear progress indicators
- ✅ Automatic error handling

This is the EASIEST way to deploy YFEvents!