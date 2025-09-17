<?php
/**
 * Plugin Name: Smart Thrive-Mautic Integration Pro
 * Plugin URI: https://yourwebsite.com/thrive-mautic-integration
 * Description: Simplified Thrive Themes integration with comprehensive dashboard
 * Version: 4.1.0
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
define('THRIVE_MAUTIC_VERSION', '4.1.0');
define('THRIVE_MAUTIC_PLUGIN_FILE', __FILE__);
define('THRIVE_MAUTIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('THRIVE_MAUTIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('THRIVE_MAUTIC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Simple initialization
add_action('plugins_loaded', function() {
    if (class_exists('ThriveMautic\\Plugin')) {
        new ThriveMautic\Plugin();
    }
});

// Activation hook
register_activation_hook(__FILE__, function() {
    // Simple activation - just create tables
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Main queue table
    $table_name = $wpdb->prefix . 'thrive_mautic_queue';
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        email varchar(255) NOT NULL,
        name varchar(255) DEFAULT '',
        segment_id varchar(50) DEFAULT '',
        status enum('pending','success','failed') DEFAULT 'pending',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clean up if needed
});
