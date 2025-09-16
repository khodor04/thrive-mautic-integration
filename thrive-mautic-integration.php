<?php
/**
 * Plugin Name: Smart Thrive-Mautic Integration Pro
 * Plugin URI: https://yourwebsite.com/thrive-mautic-integration
 * Description: Simplified Thrive Themes integration with comprehensive dashboard
 * Version: 4.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: thrive-mautic-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('THRIVE_MAUTIC_VERSION', '4.0.0');
define('THRIVE_MAUTIC_PLUGIN_FILE', __FILE__);
define('THRIVE_MAUTIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('THRIVE_MAUTIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('THRIVE_MAUTIC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader for classes
spl_autoload_register(function ($class) {
    $prefix = 'ThriveMautic\\';
    $base_dir = THRIVE_MAUTIC_PLUGIN_DIR . 'includes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
add_action('plugins_loaded', function() {
    if (class_exists('ThriveMautic\\Plugin')) {
        new ThriveMautic\Plugin();
    }
});

// Auto-update from GitHub
add_action('init', function() {
    if (is_admin()) {
        new ThriveMautic\GitHubUpdater();
    }
});

// Activation and deactivation hooks
register_activation_hook(__FILE__, function() {
    if (class_exists('ThriveMautic\\Plugin')) {
        ThriveMautic\Plugin::activate();
    }
});

register_deactivation_hook(__FILE__, function() {
    if (class_exists('ThriveMautic\\Plugin')) {
        ThriveMautic\Plugin::deactivate();
    }
});
