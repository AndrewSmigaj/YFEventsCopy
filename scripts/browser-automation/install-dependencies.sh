#!/bin/bash
# Install Chrome dependencies for Puppeteer
# ========================================

echo "🔧 Installing Chrome dependencies for Puppeteer..."

# Update package list
sudo apt-get update

# Install Chrome dependencies
sudo apt-get install -y \
    ca-certificates \
    fonts-liberation \
    libappindicator3-1 \
    libasound2 \
    libatk-bridge2.0-0 \
    libatk1.0-0 \
    libc6 \
    libcairo2 \
    libcups2 \
    libdbus-1-3 \
    libexpat1 \
    libfontconfig1 \
    libgbm1 \
    libgcc1 \
    libglib2.0-0 \
    libgtk-3-0 \
    libnspr4 \
    libnss3 \
    libpango-1.0-0 \
    libpangocairo-1.0-0 \
    libstdc++6 \
    libx11-6 \
    libx11-xcb1 \
    libxcb1 \
    libxcomposite1 \
    libxcursor1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxi6 \
    libxrandr2 \
    libxrender1 \
    libxss1 \
    libxtst6 \
    lsb-release \
    wget \
    xdg-utils

echo "✅ Chrome dependencies installed!"
echo ""
echo "🧪 Testing Puppeteer..."

# Test Puppeteer
node -e "
const puppeteer = require('puppeteer');
(async () => {
  try {
    const browser = await puppeteer.launch({ headless: 'new' });
    console.log('✅ Puppeteer working correctly!');
    await browser.close();
  } catch (error) {
    console.log('❌ Puppeteer test failed:', error.message);
  }
})();
"

echo ""
echo "🎉 Setup complete!"
echo "You can now run the browser scrapers from the admin interface."