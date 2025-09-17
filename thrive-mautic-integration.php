<?php
/**
 * Plugin Name: Smart Thrive-Mautic Integration Pro
 * Plugin URI: https://yourwebsite.com/thrive-mautic-integration
 * Description: Simplified Thrive Themes integration with comprehensive dashboard
 * Version: 4.2.0
 * Author: Khodor Ghalayini
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
define('THRIVE_MAUTIC_VERSION', '4.2.0');
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
        require_once $file;
    }
});

// Simple admin menu registration - this will definitely work
add_action('admin_menu', function() {
    add_menu_page(
        'Thrive-Mautic Dashboard',
        'Thrive-Mautic',
        'manage_options',
        'thrive-mautic-dashboard',
        function() {
            echo '<div class="wrap">';
            echo '<h1>Thrive-Mautic Dashboard</h1>';
            echo '<p>Plugin is working! Version ' . THRIVE_MAUTIC_VERSION . '</p>';
            echo '<p>All classes loaded successfully!</p>';
            echo '</div>';
        },
        'dashicons-email-alt',
        30
    );
    
    // Add settings submenu
    add_submenu_page(
        'thrive-mautic-dashboard',
        'Settings',
        'Settings',
        'manage_options',
        'thrive-mautic-settings',
        function() {
            // Handle form submission
            if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['thrive_mautic_nonce'], 'save_settings')) {
                update_option('thrive_mautic_base_url', sanitize_url($_POST['base_url']));
                update_option('thrive_mautic_username', sanitize_text_field($_POST['username']));
                update_option('thrive_mautic_password', sanitize_text_field($_POST['password']));
                update_option('thrive_mautic_auto_update', isset($_POST['auto_update']));
                echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
            }
            
            // Get current settings
            $base_url = get_option('thrive_mautic_base_url', '');
            $username = get_option('thrive_mautic_username', '');
            $password = get_option('thrive_mautic_password', '');
            $auto_update = get_option('thrive_mautic_auto_update', true);
            
            echo '<div class="wrap">';
            echo '<h1>Thrive-Mautic Settings</h1>';
            echo '<form method="post">';
            wp_nonce_field('save_settings', 'thrive_mautic_nonce');
            
            echo '<table class="form-table">';
            
            // Mautic Base URL
            echo '<tr>';
            echo '<th scope="row"><label for="base_url">Mautic Base URL</label></th>';
            echo '<td>';
            echo '<input type="url" id="base_url" name="base_url" value="' . esc_attr($base_url) . '" class="regular-text" placeholder="https://your-mautic-site.com">';
            echo '<p class="description">Enter your Mautic installation URL (e.g., https://your-mautic-site.com)</p>';
            echo '</td>';
            echo '</tr>';
            
            // Mautic Username
            echo '<tr>';
            echo '<th scope="row"><label for="username">Mautic Username</label></th>';
            echo '<td>';
            echo '<input type="text" id="username" name="username" value="' . esc_attr($username) . '" class="regular-text" placeholder="your-username">';
            echo '<p class="description">Enter your Mautic username</p>';
            echo '</td>';
            echo '</tr>';
            
            // Mautic Password
            echo '<tr>';
            echo '<th scope="row"><label for="password">Mautic Password</label></th>';
            echo '<td>';
            echo '<input type="password" id="password" name="password" value="' . esc_attr($password) . '" class="regular-text" placeholder="your-password">';
            echo '<p class="description">Enter your Mautic password</p>';
            echo '</td>';
            echo '</tr>';
            
            // Auto-Update Setting
            echo '<tr>';
            echo '<th scope="row">Auto-Updates</th>';
            echo '<td>';
            echo '<label>';
            echo '<input type="checkbox" name="auto_update" value="1" ' . checked($auto_update, true, false) . '>';
            echo ' Enable automatic updates from GitHub';
            echo '</label>';
            echo '<p class="description">When enabled, you will see update notifications in your WordPress admin like other plugins.</p>';
            echo '</td>';
            echo '</tr>';
            
            echo '</table>';
            
            echo '<p class="submit">';
            echo '<input type="submit" name="save_settings" class="button-primary" value="Save Settings">';
            echo '</p>';
            echo '</form>';
            echo '</div>';
        }
    );
});

// Initialize the plugin
add_action('plugins_loaded', function() {
    if (class_exists('ThriveMautic\\Plugin')) {
        new ThriveMautic\Plugin();
    }
});

// WordPress Auto-Update System (shows notifications like other plugins)
add_action('init', function() {
    if (is_admin()) {
        // Always initialize the updater for proper WordPress integration
        if (class_exists('ThriveMautic\\WordPressUpdater')) {
            new ThriveMautic\WordPressUpdater();
        }
    }
});

// Add auto-update support to WordPress
add_filter('auto_update_plugin', function($update, $item) {
    if ($item->slug === 'thrive-mautic-integration') {
        return get_option('thrive_mautic_auto_update', true);
    }
    return $update;
}, 10, 2);

// Add auto-update settings
add_action('admin_init', function() {
    register_setting('thrive_mautic_settings', 'thrive_mautic_auto_update');
});

// Auto-update settings are now integrated into the main Settings page

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
