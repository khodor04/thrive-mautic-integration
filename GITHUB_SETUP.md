# 🚀 GitHub + Auto-Update Setup Guide

This guide will help you set up GitHub hosting with automatic updates for your Thrive-Mautic plugin.

## 📋 Prerequisites

- GitHub account
- Git installed on your computer
- Your plugin code ready

## 🔧 Step-by-Step Setup

### 1. Create GitHub Repository

1. Go to [GitHub.com](https://github.com) and sign in
2. Click **"New repository"** (green button)
3. **Repository name**: `thrive-mautic-integration`
4. **Description**: `WordPress plugin for Thrive Themes and Mautic integration`
5. **Visibility**: Public (required for auto-updates)
6. **Initialize**: Check "Add a README file"
7. Click **"Create repository"**

### 2. Upload Your Code

#### Option A: Using Git (Recommended)

```bash
# Clone your repository
git clone https://github.com/YOURUSERNAME/thrive-mautic-integration.git
cd thrive-mautic-integration

# Copy all your plugin files here
# (Copy all files from your current plugin folder)

# Add and commit
git add .
git commit -m "Initial release v4.0.0"
git push origin main
```

#### Option B: Using GitHub Web Interface

1. Go to your repository on GitHub
2. Click **"uploading an existing file"**
3. Drag and drop all your plugin files
4. Add commit message: "Initial release v4.0.0"
5. Click **"Commit changes"**

### 3. Configure Auto-Update

1. **Edit `includes/GitHubUpdater.php`**
2. **Change these lines**:
   ```php
   private $github_username = 'yourusername'; // Change to your GitHub username
   private $github_repo = 'thrive-mautic-integration'; // Change to your repo name
   ```

3. **Commit the changes**:
   ```bash
   git add .
   git commit -m "Configure auto-update settings"
   git push origin main
   ```

### 4. Create Your First Release

#### Option A: Using the Deploy Script (Windows)

1. **Double-click `deploy.bat`**
2. **Enter version**: `4.0.0`
3. **Press Enter** and wait for completion

#### Option B: Manual Release

1. **Go to GitHub → Releases**
2. **Click "Create a new release"**
3. **Tag version**: `v4.0.0`
4. **Release title**: `Version 4.0.0`
5. **Description**: `Initial release with full Thrive-Mautic integration`
6. **Attach files**: Upload `thrive-mautic-integration.zip`
7. **Click "Publish release"**

### 5. Test Auto-Update

1. **Install the plugin** on your WordPress site
2. **Go to Plugins page**
3. **Look for update notification** (if available)
4. **Click "Update now"** to test

## 🔄 How Updates Work

### Automatic Process

1. **You push code** to GitHub
2. **Create a new release** with version tag (e.g., `v4.0.1`)
3. **GitHub Actions** automatically creates a zip file
4. **WordPress detects** the new version
5. **Users can update** with one click

### Manual Update Process

1. **Make code changes**
2. **Update version** in `thrive-mautic-integration.php`
3. **Run deploy script** or create release manually
4. **WordPress will show** update notification

## 📁 File Structure

```
thrive-mautic-integration/
├── .github/
│   └── workflows/
│       └── release.yml          # Auto-release workflow
├── includes/
│   ├── GitHubUpdater.php        # Auto-update functionality
│   └── ...                      # Other plugin files
├── assets/
│   └── ...                      # CSS/JS files
├── deploy.bat                   # Windows deployment script
├── .gitignore                   # Git ignore rules
├── README.md                    # Plugin documentation
└── GITHUB_SETUP.md             # This file
```

## 🛠️ Troubleshooting

### Auto-Update Not Working

1. **Check GitHub username/repo** in `GitHubUpdater.php`
2. **Verify release** has a zip file attached
3. **Check WordPress error logs** for issues
4. **Ensure repository is public**

### Release Not Created

1. **Check GitHub Actions** tab for errors
2. **Verify tag format** (must start with 'v')
3. **Check file permissions** on workflow

### Update Not Detected

1. **Clear WordPress cache**
2. **Check plugin version** in main file
3. **Verify release tag** format
4. **Wait 24 hours** (WordPress checks daily)

## 🎯 Best Practices

### Version Numbering

- **Format**: `MAJOR.MINOR.PATCH` (e.g., 4.0.1)
- **Major**: Breaking changes
- **Minor**: New features
- **Patch**: Bug fixes

### Release Notes

Always include:
- **What's new** in this version
- **Bug fixes** included
- **Breaking changes** (if any)
- **Upgrade instructions** (if needed)

### Testing

Before releasing:
1. **Test on staging site**
2. **Check all functionality**
3. **Verify auto-update works**
4. **Update documentation**

## 🚀 Quick Commands

### Create New Release

```bash
# Windows
deploy.bat

# Or manually
git tag v4.0.1
git push origin v4.0.1
```

### Check Status

```bash
git status
git log --oneline
git tag
```

## 📞 Support

If you encounter issues:

1. **Check GitHub Actions** logs
2. **Review WordPress error logs**
3. **Verify file permissions**
4. **Test on fresh WordPress install**

---

**🎉 You're all set!** Your plugin now has automatic updates from GitHub! 🚀
