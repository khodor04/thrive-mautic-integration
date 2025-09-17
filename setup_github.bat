@echo off
echo Setting up GitHub repository for Thrive-Mautic Plugin...
echo.

echo Step 1: Create a new repository on GitHub
echo - Go to https://github.com/new
echo - Repository name: thrive-mautic-integration
echo - Description: Smart Thrive Themes integration with Mautic
echo - Make it PUBLIC (required for auto-updates)
echo - Don't initialize with README (we already have files)
echo.

echo Step 2: Get your repository URL
echo - Copy the repository URL (e.g., https://github.com/yourusername/thrive-mautic-integration.git)
echo - Paste it when prompted below
echo.

set /p REPO_URL="Enter your GitHub repository URL: "

if "%REPO_URL%"=="" (
    echo No URL provided. Please run this script again with the repository URL.
    pause
    exit /b 1
)

echo.
echo Step 3: Adding remote origin...
git remote add origin %REPO_URL%

echo.
echo Step 4: Pushing to GitHub...
git branch -M main
git push -u origin main

echo.
echo Step 5: Creating release...
echo Now you need to create a release on GitHub:
echo 1. Go to your repository on GitHub
echo 2. Click "Releases" on the right side
echo 3. Click "Create a new release"
echo 4. Tag version: v4.1.0
echo 5. Release title: Version 4.1.0 - Fixed Menu Registration
echo 6. Description: Fixed admin menu registration issue and updated to version 4.1.0
echo 7. Click "Publish release"
echo.

echo Step 6: Test auto-update
echo After creating the release, your WordPress site should automatically detect the update!
echo.

echo Setup complete!
pause
