<?php

namespace ThriveMautic;

/**
 * Main plugin class
 */
class Plugin {
    
    /**
     * Plugin instance
     */
    private static $instance = null;
    
    /**
     * Database manager
     */
    public $database;
    
    /**
     * Mautic API manager
     */
    public $mautic_api;
    
    /**
     * Form capture manager
     */
    public $form_capture;
    
    /**
     * Admin dashboard
     */
    public $admin_dashboard;
    
    /**
     * Settings manager
     */
    public $settings;
    
    /**
     * Mautic tracking manager
     */
    public $tracking;
    
    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_init', array($this, 'admin_init'));
        
        // Load text domain
        add_action('plugins_loaded', array($this, 'load_textdomain'));
    }
    
    /**
     * Initialize components
     */
    private function init_components() {
        $this->database = new Database();
        $this->mautic_api = new MauticAPI();
        $this->form_capture = new FormCapture();
        $this->admin_dashboard = new AdminDashboard();
        $this->settings = new Settings();
        $this->tracking = new MauticTracking();
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Plugin initialization
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        if (is_admin()) {
            $this->admin_dashboard->init();
            $this->settings->init();
        }
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'thrive-mautic-integration',
            false,
            dirname(THRIVE_MAUTIC_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation
     */
    public static function activate() {
        $database = new Database();
        $database->create_tables();
        
        $settings = new Settings();
        $settings->set_default_options();
        
        // Schedule cron events
        if (!wp_next_scheduled('thrive_mautic_retry_hook')) {
            wp_schedule_event(time(), 'quarterhourly', 'thrive_mautic_retry_hook');
        }
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('thrive_mautic_retry_hook');
    }
    
    /**
     * Get plugin option with default
     */
    public static function get_option($option_name, $default = false) {
        return get_option('thrive_mautic_' . $option_name, $default);
    }
    
    /**
     * Update plugin option
     */
    public static function update_option($option_name, $value) {
        return update_option('thrive_mautic_' . $option_name, $value);
    }
    
    /**
     * Log error with context
     */
    public static function log_error($message, $context = array()) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'message' => $message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'ip' => self::get_client_ip()
        );
        
        error_log('[Thrive-Mautic] ' . json_encode($log_entry));
        
        // Store in database for admin review
        $database = new Database();
        $database->store_error_log($log_entry);
    }
    
    /**
     * Get client IP address
     */
    public static function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (isset($_SERVER[$key]) && !empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Check if user has required capability
     */
    public static function check_capability($capability = 'manage_options') {
        return current_user_can($capability);
    }
    
    /**
     * Verify nonce
     */
    public static function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, $action);
    }
    
    /**
     * Sanitize input data
     */
    public static function sanitize_input($data, $type = 'text') {
        switch ($type) {
            case 'email':
                return sanitize_email($data);
            case 'url':
                return esc_url_raw($data);
            case 'int':
                return intval($data);
            case 'text':
            default:
                return sanitize_text_field($data);
        }
    }
}
