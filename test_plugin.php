<?php
// Test file for Thrive-Mautic Plugin
// Run this with: php test_plugin.php

// Mock WordPress functions
function get_option($option, $default = false) {
    return $default;
}

function add_action($hook, $callback) {
    echo "Action registered: $hook\n";
}

function add_menu_page($page_title, $menu_title, $capability, $menu_slug, $function, $icon_url = '', $position = null) {
    echo "Menu page added: $menu_title\n";
}

function add_submenu_page($parent_slug, $page_title, $menu_title, $capability, $menu_slug, $function) {
    echo "Submenu page added: $menu_title\n";
}

function register_setting($option_group, $option_name) {
    echo "Setting registered: $option_name\n";
}

function error_log($message) {
    echo "LOG: $message\n";
}

function wp_remote_get($url, $args = array()) {
    return array('response' => array('code' => 200));
}

function is_wp_error($response) {
    return false;
}

function wp_remote_retrieve_response_code($response) {
    return 200;
}

function wp_remote_retrieve_response_message($response) {
    return 'OK';
}

function wp_remote_retrieve_body($response) {
    return '{"success": true}';
}

function add_filter($hook, $callback) {
    echo "Filter registered: $hook\n";
}

function register_activation_hook($file, $callback) {
    echo "Activation hook registered\n";
}

function register_deactivation_hook($file, $callback) {
    echo "Deactivation hook registered\n";
}

function wp_schedule_event($timestamp, $recurrence, $hook) {
    echo "Cron event scheduled: $hook\n";
}

function wp_next_scheduled($hook) {
    return false;
}

function wp_clear_scheduled_hook($hook) {
    echo "Cron event cleared: $hook\n";
}

function current_time($type) {
    return date('Y-m-d H:i:s');
}

function get_current_user_id() {
    return 1;
}

function sanitize_email($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

function esc_url_raw($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

function sanitize_text_field($text) {
    return trim(strip_tags($text));
}

function intval($value) {
    return (int) $value;
}

function load_plugin_textdomain($domain, $deprecated, $plugin_rel_path) {
    echo "Text domain loaded: $domain\n";
}

function wp_verify_nonce($nonce, $action) {
    return true;
}

function current_user_can($capability) {
    return true;
}

// Mock global $wpdb
$wpdb = (object) array(
    'prefix' => 'wp_',
    'get_charset_collate' => function() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
);

// Mock WordPress constants
define('ABSPATH', '/var/www/html/');
define('THRIVE_MAUTIC_VERSION', '4.1.0');
define('THRIVE_MAUTIC_PLUGIN_DIR', '/var/www/html/wp-content/plugins/thrive-mautic-integration/');
define('THRIVE_MAUTIC_PLUGIN_URL', 'http://localhost/wp-content/plugins/thrive-mautic-integration/');
define('THRIVE_MAUTIC_PLUGIN_BASENAME', 'thrive-mautic-integration/thrive-mautic-integration.php');
define('THRIVE_MAUTIC_PLUGIN_FILE', __FILE__);

// Mock WordPress functions
function plugin_dir_path($file) {
    return '/var/www/html/wp-content/plugins/thrive-mautic-integration/';
}

function plugin_dir_url($file) {
    return 'http://localhost/wp-content/plugins/thrive-mautic-integration/';
}

function plugin_basename($file) {
    return 'thrive-mautic-integration/thrive-mautic-integration.php';
}

function is_admin() {
    return true;
}

// Include the plugin
require_once 'thrive-mautic-integration.php';

echo "\n=== PLUGIN TEST COMPLETE ===\n";
echo "All classes loaded successfully!\n";
