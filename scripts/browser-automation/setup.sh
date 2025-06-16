#!/bin/bash
# Browser Automation Scraper Setup Script
# ========================================

set -e

echo "🚀 Setting up YFEvents Browser Automation Scraper..."

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo "❌ Node.js is not installed. Please install Node.js 16+ first."
    echo "   Ubuntu/Debian: sudo apt install nodejs npm"
    echo "   CentOS/RHEL: sudo yum install nodejs npm"
    exit 1
fi

# Check Node.js version
NODE_VERSION=$(node --version | cut -d'v' -f2 | cut -d'.' -f1)
if [ "$NODE_VERSION" -lt 16 ]; then
    echo "❌ Node.js version 16+ required. Current version: $(node --version)"
    exit 1
fi

echo "✅ Node.js version $(node --version) detected"

# Install dependencies
echo "📦 Installing Node.js dependencies..."
npm install

# Create output directory
mkdir -p output
mkdir -p logs

# Set up database bridge symlink (if web server is configured)
if [ -d "/var/www/html" ]; then
    echo "🔗 Setting up web server symlink for database bridge..."
    sudo ln -sf "$(pwd)/database-bridge.php" "/var/www/html/scripts/browser-automation/database-bridge.php" 2>/dev/null || true
fi

# Test database connection
echo "🧪 Testing database connection..."
if node -e "
const { BrowserEventScraper } = require('./scraper.js');
const scraper = new BrowserEventScraper({ csvOnly: false, debug: false });
scraper.testDatabaseConnection().then(result => {
    if (result) {
        console.log('✅ Database connection successful');
        process.exit(0);
    } else {
        console.log('❌ Database connection failed');
        process.exit(1);
    }
}).catch(err => {
    console.log('❌ Database test error:', err.message);
    process.exit(1);
});
" 2>/dev/null; then
    echo "✅ Database bridge is working"
else
    echo "⚠️  Database bridge test failed - scraper will run in CSV-only mode"
fi

# Create example environment file
if [ ! -f ".env.example" ]; then
    cat > .env.example << 'EOF'
# Browser Automation Configuration
# Copy to .env and customize

# Default location for searches
DEFAULT_LOCATION="Yakima, WA"

# Browser settings
HEADLESS=true
DEBUG=false

# Database settings (optional - uses PHP bridge by default)
DB_HOST=localhost
DB_USER=yfevents
DB_PASS=yfevents_pass
DB_NAME=yakima_finds

# Output settings
OUTPUT_DIR=./output
MAX_PAGES=10
EOF
    echo "📝 Created .env.example file"
fi

echo ""
echo "🎉 Setup complete!"
echo ""
echo "📋 Available commands:"
echo "   npm run eventbrite    # Scrape Eventbrite events"
echo "   npm run meetup        # Scrape Meetup events"
echo "   npm run facebook      # Scrape Facebook events"
echo "   npm test             # Run in test mode (single page, visible browser)"
echo ""
echo "📖 Custom usage:"
echo "   node scraper.js --config=eventbrite --location=\"Seattle, WA\" --pages=5"
echo "   node scraper.js --config=meetup --debug --csv-only"
echo "   node scraper.js --test --no-headless"
echo ""
echo "📁 Output files will be saved to:"
echo "   ./output/            # CSV files and logs"
echo "   Database             # Events saved to your YFEvents refactored system"
echo ""
echo "🔧 Configuration files:"
echo "   ./configs/eventbrite.json       # Eventbrite scraper config"
echo "   ./configs/meetup.json           # Meetup scraper config"
echo "   ./configs/facebook-events.json  # Facebook Events config"
echo ""
echo "💡 To get started:"
echo "   npm run eventbrite"
echo ""