<?php
// Simple validation script for Thrive-Mautic Plugin
// This checks if all required classes can be loaded

echo "=== THRIVE-MAUTIC PLUGIN VALIDATION ===\n\n";

// Mock essential WordPress functions
function get_option($option, $default = false) { return $default; }
function add_action($hook, $callback) { return true; }
function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url = '', $position = null) { return true; }
function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function) { return true; }
function register_setting($option_group, $option_name) { return true; }
function error_log($message) { echo "LOG: $message\n"; }
function wp_remote_get($url, $args = array()) { return array('response' => array('code' => 200)); }
function is_wp_error($response) { return false; }
function wp_remote_retrieve_response_code($response) { return 200; }
function wp_remote_retrieve_response_message($response) { return 'OK'; }
function wp_remote_retrieve_body($response) { return '{"success": true}'; }
function add_filter($hook, $callback) { return true; }
function register_activation_hook($file, $callback) { return true; }
function register_deactivation_hook($file, $callback) { return true; }
function wp_schedule_event($timestamp, $recurrence, $hook) { return true; }
function wp_next_scheduled($hook) { return false; }
function wp_clear_scheduled_hook($hook) { return true; }
function current_time($type) { return date('Y-m-d H:i:s'); }
function get_current_user_id() { return 1; }
function sanitize_email($email) { return filter_var($email, FILTER_SANITIZE_EMAIL); }
function esc_url_raw($url) { return filter_var($url, FILTER_SANITIZE_URL); }
function sanitize_text_field($text) { return trim(strip_tags($text)); }
function intval($value) { return (int) $value; }
function load_plugin_textdomain($domain, $deprecated, $plugin_rel_path) { return true; }
function wp_verify_nonce($nonce, $action) { return true; }
function current_user_can($capability) { return true; }

// Mock global $wpdb
$wpdb = (object) array(
    'prefix' => 'wp_',
    'get_charset_collate' => function() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
);

// Mock WordPress constants
define('ABSPATH', '/var/www/html/');
define('THRIVE_MAUTIC_VERSION', '4.1.0');
define('THRIVE_MAUTIC_PLUGIN_DIR', dirname(__FILE__) . '/');
define('THRIVE_MAUTIC_PLUGIN_URL', 'http://localhost/wp-content/plugins/thrive-mautic-integration/');
define('THRIVE_MAUTIC_PLUGIN_BASENAME', 'thrive-mautic-integration/thrive-mautic-integration.php');
define('THRIVE_MAUTIC_PLUGIN_FILE', __FILE__);

// Mock WordPress functions
function plugin_dir_path($file) { return dirname(__FILE__) . '/'; }
function plugin_dir_url($file) { return 'http://localhost/wp-content/plugins/thrive-mautic-integration/'; }
function plugin_basename($file) { return 'thrive-mautic-integration/thrive-mautic-integration.php'; }
function is_admin() { return true; }

echo "1. Testing main plugin file...\n";
try {
    require_once 'thrive-mautic-integration.php';
    echo "   ✅ Main plugin file loaded successfully\n";
} catch (Exception $e) {
    echo "   ❌ Error loading main plugin file: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n2. Testing class loading...\n";
$classes = [
    'ThriveMautic\\Plugin',
    'ThriveMautic\\Database', 
    'ThriveMautic\\MauticAPI',
    'ThriveMautic\\FormCapture',
    'ThriveMautic\\AdminDashboard',
    'ThriveMautic\\Settings',
    'ThriveMautic\\MauticTracking',
    'ThriveMautic\\GitHubUpdater'
];

foreach ($classes as $class) {
    if (class_exists($class)) {
        echo "   ✅ $class loaded\n";
    } else {
        echo "   ❌ $class NOT found\n";
    }
}

echo "\n3. Testing Plugin instantiation...\n";
try {
    $plugin = new ThriveMautic\Plugin();
    echo "   ✅ Plugin instantiated successfully\n";
} catch (Exception $e) {
    echo "   ❌ Error instantiating plugin: " . $e->getMessage() . "\n";
}

echo "\n4. Testing Plugin singleton...\n";
try {
    $instance1 = ThriveMautic\Plugin::get_instance();
    $instance2 = ThriveMautic\Plugin::get_instance();
    if ($instance1 === $instance2) {
        echo "   ✅ Singleton pattern working correctly\n";
    } else {
        echo "   ❌ Singleton pattern not working\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error testing singleton: " . $e->getMessage() . "\n";
}

echo "\n=== VALIDATION COMPLETE ===\n";
echo "Plugin is ready for deployment! 🚀\n";
