#!/bin/bash

echo "Creating new release for Thrive-Mautic Plugin..."
echo

# Get version number
read -p "Enter version number (e.g., 4.4.0): " VERSION

if [ -z "$VERSION" ]; then
    echo "No version provided. Exiting."
    exit 1
fi

echo
echo "Updating version to $VERSION..."

# Update version in main file
sed -i "s/\* Version: [0-9]\+\.[0-9]\+\.[0-9]\+/\* Version: $VERSION/" thrive-mautic-integration.php
sed -i "s/define('THRIVE_MAUTIC_VERSION', '[0-9]\+\.[0-9]\+\.[0-9]\+')/define('THRIVE_MAUTIC_VERSION', '$VERSION')/" thrive-mautic-integration.php

echo "Version updated to $VERSION"
echo

echo "Committing changes..."
git add .
git commit -m "Release v$VERSION - Automated release"

echo
echo "Creating tag v$VERSION..."
git tag v$VERSION

echo
echo "Pushing to GitHub..."
git push origin main
git push origin v$VERSION

echo
echo "✅ Release v$VERSION created successfully!"
echo
echo "GitHub Actions will now automatically:"
echo "1. Create a GitHub release"
echo "2. Generate release notes"
echo "3. Create a downloadable zip file"
echo "4. Notify your WordPress site of the update"
echo
echo "Check your GitHub repository in a few minutes!"
echo
