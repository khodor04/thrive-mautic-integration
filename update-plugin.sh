#!/bin/bash

# Thrive-Mautic Plugin Update Script
# Run this on your server via SSH

echo "🚀 Updating Thrive-Mautic Plugin..."

# Get the latest version from GitHub API
LATEST_VERSION=$(curl -s https://api.github.com/repos/khodor04/thrive-mautic-integration/releases/latest | grep -o '"tag_name": "[^"]*' | grep -o '[^"]*$')

if [ -z "$LATEST_VERSION" ]; then
    echo "❌ Failed to get latest version"
    exit 1
fi

echo "📦 Latest version: $LATEST_VERSION"

# Navigate to plugins directory
cd /var/www/html/wp-content/plugins

# Backup current plugin
if [ -d "thrive-mautic-integration" ]; then
    echo "💾 Backing up current plugin..."
    cp -r thrive-mautic-integration thrive-mautic-integration-backup-$(date +%Y%m%d-%H%M%S)
fi

# Download latest release
echo "⬇️ Downloading $LATEST_VERSION..."
wget -q https://github.com/khodor04/thrive-mautic-integration/archive/refs/tags/${LATEST_VERSION}.zip

if [ $? -ne 0 ]; then
    echo "❌ Failed to download plugin"
    exit 1
fi

# Remove old version
echo "🗑️ Removing old version..."
rm -rf thrive-mautic-integration

# Extract new version
echo "📂 Extracting new version..."
unzip -q ${LATEST_VERSION}.zip

# Rename extracted folder
mv thrive-mautic-integration-${LATEST_VERSION#v} thrive-mautic-integration

# Set proper permissions
echo "🔐 Setting permissions..."
chown -R www-data:www-data thrive-mautic-integration
chmod -R 755 thrive-mautic-integration

# Clean up
echo "🧹 Cleaning up..."
rm ${LATEST_VERSION}.zip

echo "✅ Plugin updated successfully to $LATEST_VERSION!"
echo "🔄 Please refresh your WordPress admin to see the changes."
