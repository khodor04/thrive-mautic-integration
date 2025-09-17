#!/bin/bash

# Thrive-Mautic Integration Deployment Script

echo "🚀 Starting deployment process..."

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo "❌ Error: Not in a git repository"
    exit 1
fi

# Get current version
CURRENT_VERSION=$(grep "Version:" thrive-mautic-integration.php | sed 's/.*Version: *//' | sed 's/ *$//')
echo "📦 Current version: $CURRENT_VERSION"

# Ask for new version
read -p "Enter new version (current: $CURRENT_VERSION): " NEW_VERSION

if [ -z "$NEW_VERSION" ]; then
    echo "❌ Error: Version cannot be empty"
    exit 1
fi

# Update version in main file
sed -i "s/Version: $CURRENT_VERSION/Version: $NEW_VERSION/" thrive-mautic-integration.php

# Update version in Plugin.php
sed -i "s/THRIVE_MAUTIC_VERSION', '$CURRENT_VERSION'/THRIVE_MAUTIC_VERSION', '$NEW_VERSION'/" includes/Plugin.php

echo "✅ Version updated to $NEW_VERSION"

# Add and commit changes
git add .
git commit -m "Release version $NEW_VERSION"

# Create and push tag
git tag "v$NEW_VERSION"
git push origin main
git push origin "v$NEW_VERSION"

echo "🎉 Release $NEW_VERSION created and pushed to GitHub!"
echo "📋 Next steps:"
echo "1. Check GitHub Actions for automatic release creation"
echo "2. Verify the release includes the plugin zip file"
echo "3. Test auto-update on your WordPress site"
