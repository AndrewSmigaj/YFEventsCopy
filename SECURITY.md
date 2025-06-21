# YFEvents Security Configuration

## Overview

All sensitive credentials have been removed from the codebase and replaced with environment variables. This ensures that passwords, API keys, and other sensitive information are never committed to version control.

## Initial Setup

1. **Run the setup script:**
   ```bash
   php scripts/setup_production.php
   ```
   This interactive script will help you create a secure `.env` file with all necessary credentials.

2. **Manual setup (alternative):**
   ```bash
   cp .env.example .env
   # Edit .env with your credentials
   chmod 600 .env
   ```

## Environment Variables

### Required Variables

- `DB_HOST` - Database server hostname
- `DB_NAME` - Database name
- `DB_USER` - Database username
- `DB_PASSWORD` - Database password
- `ADMIN_USERNAME` - Admin panel username
- `ADMIN_PASSWORD_HASH` - Bcrypt hash of admin password

### Optional Variables

- `SMTP_HOST` - SMTP server for sending emails
- `SMTP_PORT` - SMTP port (usually 587 for TLS)
- `SMTP_USERNAME` - SMTP authentication username
- `SMTP_PASSWORD` - SMTP authentication password
- `SESSION_SECRET` - Random string for session security
- `ENCRYPTION_KEY` - Random string for encryption

## Generating Password Hashes

To generate a bcrypt hash for the admin password:

```bash
php scripts/generate_password_hash.php
```

## Security Best Practices

1. **File Permissions:**
   - Set `.env` file permissions to `600` (read/write for owner only)
   - Ensure web server user can read the file

2. **Backup:**
   - Keep a secure backup of your `.env` file
   - Store backups encrypted and off-server

3. **Version Control:**
   - Never commit `.env` to version control
   - The `.gitignore` file already excludes it

4. **Password Policy:**
   - Use strong passwords (minimum 12 characters)
   - Include uppercase, lowercase, numbers, and symbols
   - Change passwords regularly

5. **Environment Isolation:**
   - Use different credentials for development/staging/production
   - Never use production credentials in development

## Updating Credentials

To update credentials:

1. Edit the `.env` file directly, or
2. Re-run `php scripts/setup_production.php`
3. For password changes, generate a new hash using `php scripts/generate_password_hash.php`

## Troubleshooting

### "Admin password not set" error
- Ensure `ADMIN_PASSWORD_HASH` is set in `.env`
- Verify the hash was generated correctly

### Database connection errors
- Check all `DB_*` variables in `.env`
- Verify database server is accessible
- Confirm credentials are correct

### Email sending failures
- Verify all `SMTP_*` variables
- For Gmail, use an App Password, not your regular password
- Check firewall rules for SMTP ports

## Removed Hardcoded Credentials

The following hardcoded credentials have been removed:

1. **Admin Login:** Previously "YakFind/MapTime" - now uses environment variables
2. **Database Password:** Now loaded from `DB_PASSWORD` environment variable
3. **Email Password:** Now loaded from `SMTP_PASSWORD` environment variable

## Support

For security-related questions or concerns, please contact your system administrator.