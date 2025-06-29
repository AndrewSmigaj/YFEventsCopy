# YFEvents Database Backup

**Created**: 2025-06-29 07:23:15

## Overview

This directory contains database backup scripts and backup files for the YFEvents system.

## Files

- `backup_database.sh` - Main backup script that exports all YFEvents related tables
- `restore_database.sh` - Script to restore the database from backup
- `database_backup.sql` - The actual database backup file (created after running backup script)

## Database Information

- **Host**: localhost
- **Database**: yakima_finds
- **User**: yfevents

## Tables Included

The backup includes all tables with the following prefixes:
- `yfc_*` - YFClaim module tables (estate sales, items, offers, etc.)
- `auth_*` - Authentication and authorization tables
- `shop_*` - Shop related tables
- `communication_*` - Communication module tables

And these specific tables:
- `events` - Main events table
- `users` - User accounts table
- `shops` - Business directory table

## Usage

### Creating a Backup

```bash
./backup_database.sh
```

This will create `database_backup.sql` with all the table structures and data.

### Restoring from Backup

```bash
./restore_database.sh
```

**WARNING**: This will overwrite existing data in the database!

## Security Notes

- Database credentials are stored in the scripts (from .env file)
- Make sure to keep backup files secure as they contain sensitive data
- Consider encrypting backups for long-term storage
- Do not commit backup files containing real data to version control

## Backup Strategy

It's recommended to:
1. Run backups before major updates
2. Store backups in multiple locations
3. Test restore process periodically
4. Keep multiple versions of backups (daily, weekly, monthly)

## Troubleshooting

If backup fails:
1. Check database credentials in the script
2. Verify MySQL/MariaDB is running
3. Ensure the database user has SELECT permissions
4. Check available disk space

If restore fails:
1. Verify the backup file exists and is not corrupted
2. Check database credentials
3. Ensure the database user has CREATE/DROP/INSERT permissions
4. Check MySQL max_allowed_packet setting for large backups