@echo off
echo =======================================
echo YFEvents Browser Scraper for Windows
echo =======================================
echo.

REM Check if Node.js is installed
node --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Node.js is not installed or not in PATH
    echo.
    echo Please install Node.js from: https://nodejs.org/
    echo Make sure to add it to your PATH during installation
    pause
    exit /b 1
)

echo ✅ Node.js found: 
node --version

REM Check if we're in the right directory
if not exist "client-scraper.js" (
    echo ❌ client-scraper.js not found in current directory
    echo Please navigate to the browser-automation folder first
    pause
    exit /b 1
)

REM Check if dependencies are installed
if not exist "node_modules" (
    echo 📦 Installing dependencies...
    npm install
)

echo.
echo 🚀 Starting scraper...
echo.

REM Default scraping command - you can modify these parameters
set CONFIG=eventbrite
set LOCATION=Yakima, WA
set PAGES=3

echo Running: node client-scraper.js --config=%CONFIG% --location="%LOCATION%" --pages=%PAGES%
echo.

node client-scraper.js --config=%CONFIG% --location="%LOCATION%" --pages=%PAGES%

echo.
echo ✅ Scraping completed!
echo Check the output folder for CSV files
echo.
pause