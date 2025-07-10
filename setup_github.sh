#!/bin/bash

# GitHub Setup Script for YFEvents
echo "========================================"
echo "GitHub Setup for YFEvents"
echo "========================================"
echo ""
echo "This script will help you set up Git and prepare to push changes."
echo ""

# Check if git is configured
if ! git config --get user.name > /dev/null 2>&1; then
    echo "Setting up Git configuration..."
    git config --global user.name "YFEvents Developer"
    git config --global user.email "developer@yakimafinds.com"
fi

# Initialize git if not already done
cd /home/robug/YFEvents

if [ ! -d ".git" ]; then
    echo "Initializing Git repository..."
    git init
    git branch -M main
fi

# Add GitHub remote if not exists
if ! git remote get-url origin > /dev/null 2>&1; then
    echo "Adding GitHub remote..."
    git remote add origin git@github.com:r0bug/yfevents.git
fi

echo ""
echo "Current Git status:"
git status --short

echo ""
echo "========================================"
echo "SSH Key Setup Instructions"
echo "========================================"
echo ""
echo "The SSH key fingerprint you provided is:"
echo "SHA256:AJ3ghxerE6+bY0jWWxOjbQeuHMklZm7HBen7XwdyyMU"
echo ""
echo "To set up SSH access:"
echo ""
echo "1. Create your private key file:"
echo "   nano ~/.ssh/id_rsa"
echo "   (Paste your private key and save)"
echo ""
echo "2. Set proper permissions:"
echo "   chmod 600 ~/.ssh/id_rsa"
echo ""
echo "3. Add GitHub to known hosts:"
echo "   ssh-keyscan github.com >> ~/.ssh/known_hosts"
echo ""
echo "4. Test the connection:"
echo "   ssh -T git@github.com"
echo ""
echo "========================================"
echo "Files Ready to Commit"
echo "========================================"
echo ""

# Show new and modified files
echo "New files to add:"
git ls-files --others --exclude-standard | grep -E "\.(php|js|html|md|json|sql)$" | head -20

echo ""
echo "Modified files:"
git diff --name-only | head -10

echo ""
echo "========================================"
echo "Next Steps"
echo "========================================"
echo ""
echo "After setting up your SSH key:"
echo ""
echo "1. Stage all changes:"
echo "   git add -A"
echo ""
echo "2. Commit changes:"
echo '   git commit -m "Add YFEvents calendar application with map integration"'
echo ""
echo "3. Push to GitHub:"
echo "   git push -u origin main"
echo ""
echo "========================================"