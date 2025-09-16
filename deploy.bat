@echo off
REM Thrive-Mautic Integration Deployment Script for Windows

echo 🚀 Starting deployment process...

REM Check if we're in a git repository
if not exist ".git" (
    echo ❌ Error: Not in a git repository
    pause
    exit /b 1
)

REM Get current version (this is a simplified version for Windows)
echo 📦 Current version: Check thrive-mautic-integration.php

REM Ask for new version
set /p NEW_VERSION="Enter new version: "

if "%NEW_VERSION%"=="" (
    echo ❌ Error: Version cannot be empty
    pause
    exit /b 1
)

echo ✅ Version will be updated to %NEW_VERSION%

REM Add and commit changes
git add .
git commit -m "Release version %NEW_VERSION%"

REM Create and push tag
git tag "v%NEW_VERSION%"
git push origin main
git push origin "v%NEW_VERSION%"

echo 🎉 Release %NEW_VERSION% created and pushed to GitHub!
echo 📋 Next steps:
echo 1. Check GitHub Actions for automatic release creation
echo 2. Verify the release includes the plugin zip file
echo 3. Test auto-update on your WordPress site

pause
