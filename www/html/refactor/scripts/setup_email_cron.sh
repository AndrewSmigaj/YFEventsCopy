#!/bin/bash
# Setup automated email processing cron job

# Check if cron job already exists
if crontab -l 2>/dev/null | grep -q "process_event_emails.php"; then
    echo "Email processing cron job already exists"
    exit 0
fi

# Add cron job to check emails every 15 minutes
SCRIPT_PATH="/home/robug/YFEvents/www/html/refactor/scripts/process_event_emails.php"

echo "Setting up automated email checking every 15 minutes..."

# Add to crontab
(crontab -l 2>/dev/null; echo "*/15 * * * * /usr/bin/php $SCRIPT_PATH >> /home/robug/YFEvents/www/html/refactor/logs/cron_email.log 2>&1") | crontab -

echo "✅ Email processing cron job added!"
echo "📧 Emails will be checked every 15 minutes"
echo "📋 Logs: /home/robug/YFEvents/www/html/refactor/logs/cron_email.log"

# Test the script
echo "🧪 Testing email processing script..."
/usr/bin/php $SCRIPT_PATH

echo "✅ Setup complete!"
echo ""
echo "To view cron jobs: crontab -l"
echo "To remove cron job: crontab -e (then delete the line)"