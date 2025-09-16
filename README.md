# Smart Thrive-Mautic Integration Pro

A comprehensive WordPress plugin that seamlessly integrates Thrive Themes with Mautic marketing automation platform.

## 🚀 Features

- **Automatic Form Capture**: Captures submissions from all Thrive Themes forms (Architect, Leads, Quiz Builder)
- **Smart Segment Management**: Automatically creates and manages Mautic segments for lead magnets
- **Real-time Dashboard**: Comprehensive monitoring with live statistics and error tracking
- **Flexible Setup Options**: Create new segments, use existing ones, or mix both approaches
- **Security First**: Encrypted password storage, rate limiting, and comprehensive input validation
- **Performance Optimized**: Caching, background processing, and efficient database queries
- **Production Ready**: Follows WordPress coding standards and best practices

## 📋 Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- Thrive Themes (Architect, Leads, or Quiz Builder)
- Mautic 3.0 or higher
- MySQL 5.6 or higher

## 🔧 Installation

1. **Download the plugin** and upload to `/wp-content/plugins/` directory
2. **Activate the plugin** through the 'Plugins' menu in WordPress
3. **Configure Mautic API** in Thrive-Mautic → Settings
4. **Set up lead magnets** using the meta box on posts/pages
5. **Create campaigns** in Mautic following the provided instructions

## ⚙️ Configuration

### Mautic API Setup

1. Go to your Mautic installation
2. Navigate to **Settings → Configuration → API Settings**
3. Enable **"API enabled"** ✅
4. Enable **"Enable HTTP basic auth"** ✅
5. Save configuration

### Plugin Settings

1. Go to **Thrive-Mautic → Settings**
2. Enter your **Mautic URL** (e.g., https://your-mautic-site.com)
3. Enter your **Mautic Username**
4. Enter your **Mautic Password**
5. Set a **Default Segment ID** (optional)
6. Configure **Data Retention** settings
7. Test the connection

### Lead Magnet Setup

1. **Edit any post/page** with Thrive forms
2. **Find the meta box**: "Smart Mautic Setup"
3. **Enter lead magnet name** (e.g., "SEO Checklist")
4. **Choose setup type**:
   - **Create New**: Plugin creates segments automatically
   - **Use Existing**: Use your existing segment IDs
   - **Mixed**: Combine both approaches
5. **Click "Setup Segments"**
6. **Follow the instructions** provided to complete setup in Mautic

## 🎯 Usage

### Basic Workflow

1. **Create content** with Thrive forms
2. **Set up segments** using the meta box
3. **Configure campaigns** in Mautic
4. **Monitor performance** via the dashboard

### Advanced Features

- **Multiple forms per page**: All forms automatically use the same segment setup
- **A/B testing**: Test different form designs while keeping the same segments
- **Error monitoring**: Real-time error tracking and retry mechanisms
- **Performance analytics**: Track conversion rates and form performance

## 📊 Dashboard

The plugin includes a comprehensive dashboard with:

- **Real-time statistics**: Today's signups, success rates, pending/failed counts
- **Recent activity**: Live feed of form submissions
- **Error monitoring**: Track and resolve issues quickly
- **Quick actions**: Retry failed submissions, clear old logs
- **Performance metrics**: Top performing posts and conversion tracking

## 🔒 Security Features

- **Encrypted password storage**: Passwords are encrypted using WordPress salts
- **Rate limiting**: Prevents API abuse with intelligent rate limiting
- **Input validation**: Comprehensive sanitization of all user inputs
- **Capability checks**: Proper WordPress user permission validation
- **Nonce verification**: CSRF protection for all AJAX requests

## ⚡ Performance Features

- **Caching**: Segment information cached for improved performance
- **Background processing**: Form submissions processed asynchronously
- **Database optimization**: Efficient queries with proper indexing
- **Memory management**: Optimized for high-traffic sites

## 🛠️ Development

### File Structure

```
thrive-mautic-integration/
├── includes/
│   ├── Plugin.php              # Main plugin class
│   ├── Database.php            # Database operations
│   ├── MauticAPI.php          # Mautic API integration
│   ├── FormCapture.php        # Form capture logic
│   ├── AdminDashboard.php     # Admin interface
│   └── Settings.php           # Settings management
├── assets/
│   ├── css/admin.css          # Admin styles
│   └── js/admin.js            # Admin JavaScript
├── languages/                 # Translation files
├── thrive-mautic-integration.php  # Main plugin file
└── README.md                  # This file
```

### Hooks and Filters

The plugin provides several hooks for customization:

```php
// Modify form data before processing
add_filter('thrive_mautic_form_data', function($data) {
    // Your custom logic
    return $data;
});

// Custom segment creation logic
add_action('thrive_mautic_segment_created', function($segment_id, $segment_name) {
    // Your custom logic
}, 10, 2);

// Modify Mautic contact data
add_filter('thrive_mautic_contact_data', function($contact_data) {
    // Your custom logic
    return $contact_data;
});
```

## 🐛 Troubleshooting

### Common Issues

**Forms not being captured:**
- Check Dashboard → Recent Activity for submissions
- Verify meta box is configured on the post/page
- Test with a simple Thrive Architect form first
- Ensure form has email field (required)

**Connection failed / 401 errors:**
- Verify Mautic API is enabled (Settings → Configuration → API)
- Check username/password in plugin settings
- Try logging into Mautic directly with same credentials
- Ensure Mautic URL is correct

**Contacts created but not in segments:**
- Check segment IDs are correct in meta box
- Verify segments exist in Mautic
- Look at Dashboard → Error Log for segment assignment issues
- Check if contact already existed (may override segment assignment)

### Debug Mode

Enable WordPress debug mode for detailed error information:

```php
// Add to wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## 📈 Best Practices

### Lead Magnet Strategy
- Create specific segments for each lead magnet
- Use descriptive names (e.g., "SEO Checklist - Opt-ins")
- Plan your nurture sequence before creating segments
- Test your setup with test submissions

### Email Campaign Setup
1. **Opt-in segment**: For new, unconfirmed subscribers
2. **Confirmation email**: Double opt-in for better deliverability
3. **Marketing segment**: For confirmed subscribers
4. **Nurture sequence**: Series of valuable emails
5. **Tagging system**: Tag by interests for better targeting

### Performance Optimization
- Check dashboard daily for errors and performance
- Aim for 95%+ success rate
- Clean old data regularly using "Clear Old Logs"
- Monitor top performing content

## 🔄 Updates

The plugin includes automatic update notifications and safe update procedures. Always backup your site before updating.

## 📞 Support

For support and feature requests:
- Check the Dashboard for error logs
- Review the troubleshooting section
- Enable debug mode for detailed error information
- Check WordPress error logs

## 📄 License

This plugin is licensed under the GPL v2 or later.

## 🙏 Credits

Built with ❤️ for the WordPress and Thrive Themes community.

---

**Version**: 4.0.0  
**Last Updated**: 2024  
**Compatibility**: WordPress 5.0+, PHP 7.4+
