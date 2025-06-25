#!/bin/bash
# YFEvents Migration Script - Move from /refactor to root

echo "🚀 YFEvents Migration Script"
echo "=========================="
echo ""
echo "This will migrate the refactor site to be the main production site."
echo "Current: https://backoffice.yakimafinds.com/refactor/"
echo "Target:  https://backoffice.yakimafinds.com/"
echo ""
read -p "Are you sure you want to continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo "Migration cancelled."
    exit 1
fi

# Set paths
CURRENT_ROOT="/home/robug/YFEvents/www/html"
REFACTOR_PATH="$CURRENT_ROOT/refactor"
BACKUP_PATH="$CURRENT_ROOT-backup-$(date +%Y%m%d-%H%M%S)"

echo ""
echo "📁 Step 1: Creating backup..."
cp -r "$CURRENT_ROOT" "$BACKUP_PATH"
echo "✅ Backup created at: $BACKUP_PATH"

echo ""
echo "📝 Step 2: Creating maintenance page..."
echo "Site under maintenance. We'll be back in a few minutes!" > "$CURRENT_ROOT/maintenance.html"

echo ""
echo "🔄 Step 3: Moving files..."
# Create temporary directory
TEMP_DIR="/tmp/yfevents-migration-$(date +%s)"
mkdir -p "$TEMP_DIR"

# Copy modules and communication if they exist outside refactor
if [ -d "$CURRENT_ROOT/modules" ] && [ ! -d "$REFACTOR_PATH/modules" ]; then
    echo "   Copying modules directory..."
    cp -r "$CURRENT_ROOT/modules" "$TEMP_DIR/"
fi

if [ -d "$CURRENT_ROOT/communication" ] && [ ! -d "$REFACTOR_PATH/communication" ]; then
    echo "   Copying communication directory..."
    cp -r "$CURRENT_ROOT/communication" "$TEMP_DIR/"
fi

# Move refactor contents to root
echo "   Moving refactor to root..."
mv "$REFACTOR_PATH"/* "$CURRENT_ROOT/" 2>/dev/null
mv "$REFACTOR_PATH"/.* "$CURRENT_ROOT/" 2>/dev/null

# Copy back modules/communication if needed
if [ -d "$TEMP_DIR/modules" ]; then
    echo "   Restoring modules..."
    cp -r "$TEMP_DIR/modules" "$CURRENT_ROOT/"
fi

if [ -d "$TEMP_DIR/communication" ]; then
    echo "   Restoring communication..."
    cp -r "$TEMP_DIR/communication" "$CURRENT_ROOT/"
fi

# Remove empty refactor directory
rmdir "$REFACTOR_PATH" 2>/dev/null

# Clean up temp directory
rm -rf "$TEMP_DIR"

echo ""
echo "🔐 Step 4: Setting permissions..."
chown -R www-data:www-data "$CURRENT_ROOT"
chmod -R 755 "$CURRENT_ROOT"
chmod -R 777 "$CURRENT_ROOT/cache" 2>/dev/null
chmod -R 777 "$CURRENT_ROOT/logs" 2>/dev/null
chmod -R 777 "$CURRENT_ROOT/uploads" 2>/dev/null

echo ""
echo "🧹 Step 5: Cleaning up..."
rm -f "$CURRENT_ROOT/maintenance.html"

echo ""
echo "✅ Migration Complete!"
echo ""
echo "📋 Post-Migration Checklist:"
echo "   - Test admin login: https://backoffice.yakimafinds.com/admin/"
echo "   - Test API: https://backoffice.yakimafinds.com/api/events"
echo "   - Check all admin pages work"
echo "   - Verify no 404 errors"
echo ""
echo "🔄 Rollback Command (if needed):"
echo "   rm -rf $CURRENT_ROOT && mv $BACKUP_PATH $CURRENT_ROOT"
echo ""
echo "✨ Your site is now live at: https://backoffice.yakimafinds.com/"