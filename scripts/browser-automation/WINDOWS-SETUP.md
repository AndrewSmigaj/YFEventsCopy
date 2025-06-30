# Windows Setup Guide for YFEvents Browser Scraper

## Quick Start (Recommended)

### 1. Download Files to Your Windows PC

You need to copy these files from the server to your Windows computer:
- `client-scraper.js`
- `package.json` 
- `configs/` folder (contains eventbrite.json, meetup.json, etc.)
- `run-scraper.bat` (Windows batch file)

**Option A: Download via Web Interface**
1. Go to your server file manager or FTP
2. Navigate to `/home/robug/YFEvents/scripts/browser-automation/`
3. Download the files listed above

**Option B: Use SCP/SFTP (if you have SSH access)**
```cmd
scp -r user@yourserver:/home/robug/YFEvents/scripts/browser-automation/ C:\YFEvents-Scraper\
```

### 2. Install Node.js on Windows

1. Download from: https://nodejs.org/
2. Choose "LTS" version (recommended)
3. Run installer with default settings
4. **Important**: Make sure "Add to PATH" is checked during installation

### 3. Set Up the Scraper

Open Command Prompt or PowerShell and navigate to your folder:
```cmd
cd C:\YFEvents-Scraper\browser-automation
```

Install dependencies:
```cmd
npm install
```

### 4. Run the Scraper

**Easy way (using batch file):**
```cmd
run-scraper.bat
```

**Manual way:**
```cmd
node client-scraper.js --config=eventbrite --location="Yakima, WA" --pages=3
```

**Debug mode (to see browser window):**
```cmd
node client-scraper.js --config=eventbrite --location="Yakima, WA" --pages=1 --debug
```

## Detailed Setup Instructions

### Node.js Installation

1. **Download**: Go to https://nodejs.org/
2. **Version**: Choose "LTS" (Long Term Support)
3. **Install**: Run the installer
4. **Settings**: Use default settings, but ensure these are checked:
   - ✅ Add to PATH environment variable
   - ✅ Automatically install necessary tools

5. **Verify**: Open Command Prompt and run:
   ```cmd
   node --version
   npm --version
   ```

### Project Setup

1. **Create folder**: `C:\YFEvents-Scraper\`
2. **Copy files** from server to this folder
3. **Open Command Prompt** as Administrator (right-click → "Run as administrator")
4. **Navigate** to your folder:
   ```cmd
   cd C:\YFEvents-Scraper
   ```
5. **Install dependencies**:
   ```cmd
   npm install
   ```

### File Structure

Your Windows folder should look like:
```
C:\YFEvents-Scraper\
├── client-scraper.js
├── package.json
├── run-scraper.bat
├── WINDOWS-SETUP.md
├── configs\
│   ├── eventbrite.json
│   ├── meetup.json
│   └── facebook-events.json
├── node_modules\           (created after npm install)
└── output\                 (created automatically)
```

## Usage Examples

### Basic Scraping Commands

```cmd
# Scrape Eventbrite (3 pages)
node client-scraper.js --config=eventbrite --location="Yakima, WA" --pages=3

# Scrape Meetup (5 pages) 
node client-scraper.js --config=meetup --location="Yakima, WA" --pages=5

# Try different location
node client-scraper.js --config=eventbrite --location="Seattle, WA" --pages=2

# Debug mode (see browser window)
node client-scraper.js --config=eventbrite --location="Yakima, WA" --pages=1 --debug
```

### Batch File Usage

The `run-scraper.bat` file makes it easy to run with double-click:

1. **Edit settings** in the batch file if needed:
   ```batch
   set CONFIG=eventbrite
   set LOCATION=Yakima, WA
   set PAGES=3
   ```

2. **Double-click** `run-scraper.bat` to run

## Output Files

CSV files are saved to the `output\` folder:
```
output\
├── eventbrite-events-2025-06-16T10-30-00.csv
├── meetup-events-2025-06-16T10-35-00.csv
└── ...
```

## Uploading Results

### Method 1: Manual Upload (Recommended)
1. Run scraper to generate CSV files
2. Go to: `https://backoffice.yakimafinds.com/refactor/admin/browser-scrapers.php`
3. Look for "Upload CSV" section
4. Select and upload your CSV file
5. Review imported events in admin

### Method 2: Direct Integration (Advanced)
The scraper can POST data directly to your server (requires additional setup).

## Troubleshooting

### "node is not recognized as internal or external command"
- Node.js is not installed or not in PATH
- Reinstall Node.js and make sure "Add to PATH" is checked
- Restart Command Prompt after installation

### "npm install" fails
- Run Command Prompt as Administrator
- Check internet connection
- Try: `npm install --force`

### Chrome/Chromium issues
- Puppeteer automatically downloads Chrome
- If issues persist, install Chrome manually from google.com/chrome
- For corporate networks, you may need to configure proxy settings

### Permission errors
- Run Command Prompt as Administrator
- Check antivirus isn't blocking Node.js or Chrome

### Network/firewall issues
- Some corporate networks block automated browsers
- Try running from personal network first
- Configure proxy settings if needed:
  ```cmd
  npm config set proxy http://proxy.company.com:8080
  npm config set https-proxy http://proxy.company.com:8080
  ```

## Advanced Configuration

### Custom Scraper Settings

Edit the config files in `configs\` folder to customize:

```json
{
  "site_name": "Eventbrite",
  "search_url": "https://www.eventbrite.com/d/online/{location}/",
  "selectors": {
    "event_container": "[data-testid='event-card']",
    "title": "h3[data-testid='event-title']"
  },
  "anti_detection": {
    "random_delay": true,
    "human_behavior": true
  }
}
```

### Scheduled Runs

Create a Windows Task Scheduler job to run automatically:

1. Open Task Scheduler
2. Create Basic Task
3. Set trigger (daily, weekly, etc.)
4. Action: Start a program
5. Program: `C:\YFEvents-Scraper\run-scraper.bat`

## Security Notes

- The scraper only reads public event data
- No personal information is collected
- Generated CSV files are safe to share
- Browser automation respects robots.txt
- Built-in delays prevent overwhelming target sites

## Getting Help

If you encounter issues:

1. Check this guide first
2. Try debug mode: `--debug`
3. Check the console output for error messages
4. Verify internet connection and target site accessibility
5. Contact support with specific error messages