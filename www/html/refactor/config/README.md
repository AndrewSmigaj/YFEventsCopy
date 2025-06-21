# Configuration Setup

This directory contains configuration files for the YFEvents refactored system. 

## Important Security Note

The actual configuration files (`email.php`, `database.php`) are **gitignored** to prevent exposing sensitive credentials. Example files are provided with `.example` extension.

## Setup Instructions

1. **Database Configuration**
   - Copy `database.php.example` to `database.php`
   - Replace placeholder values with your actual database credentials:
     - `YOUR_DATABASE_NAME` - Your MySQL database name
     - `YOUR_DATABASE_USERNAME` - Your MySQL username
     - `YOUR_DATABASE_PASSWORD_HERE` - Your MySQL password

2. **Email Configuration**
   - Copy `email.php.example` to `email.php`
   - Replace placeholder values with your actual email credentials:
     - `YOUR_EMAIL@gmail.com` - Your Gmail address
     - `YOUR_APP_PASSWORD_HERE` - Your Gmail app-specific password
   - Update the submission addresses with your domain

## Getting Gmail App Password

1. Enable 2-factor authentication on your Gmail account
2. Go to Google Account settings > Security > 2-Step Verification > App passwords
3. Generate a new app password for "Mail"
4. Use this 16-character password in the configuration

## Security Best Practices

- Never commit actual passwords to version control
- Use environment variables for production deployments
- Regularly rotate passwords and API keys
- Keep the `.gitignore` file updated to exclude sensitive files