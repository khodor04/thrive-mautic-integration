<?php

namespace ThriveMautic;

/**
 * Mautic Tracking Class
 */
class MauticTracking {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_head', array($this, 'add_tracking_script'), 1);
        add_action('wp_footer', array($this, 'add_tracking_script_footer'), 1);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_tracking_script'));
        
        // Shortcodes
        add_shortcode('mautic', array($this, 'mautic_shortcode'));
        
        // AJAX for GDPR compliance
        add_action('wp_ajax_wpmautic_send', array($this, 'ajax_send_tracking'));
        add_action('wp_ajax_nopriv_wpmautic_send', array($this, 'ajax_send_tracking'));
    }
    
    /**
     * Initialize tracking
     */
    public function init() {
        // Check if tracking is enabled
        if (!Plugin::get_option('tracking_enabled', false)) {
            return;
        }
        
        // Add tracking based on position setting
        $position = Plugin::get_option('tracking_position', 'wp_head');
        
        if ($position === 'wp_footer') {
            add_action('wp_footer', array($this, 'add_tracking_script'), 1);
        } elseif ($position === 'wp_head') {
            add_action('wp_head', array($this, 'add_tracking_script'), 1);
        }
        // If 'disabled', tracking will only work via shortcodes or manual trigger
    }
    
    /**
     * Add tracking script to head
     */
    public function add_tracking_script() {
        if (!$this->should_track()) {
            return;
        }
        
        $mautic_url = Plugin::get_option('mautic_url', '');
        $tracking_code = Plugin::get_option('tracking_code', '');
        
        if (empty($mautic_url) || empty($tracking_code)) {
            return;
        }
        
        $position = Plugin::get_option('tracking_position', 'wp_head');
        
        // Only add to head if position is wp_head
        if ($position !== 'wp_head') {
            return;
        }
        
        $this->output_tracking_script($mautic_url, $tracking_code);
    }
    
    /**
     * Add tracking script to footer
     */
    public function add_tracking_script_footer() {
        if (!$this->should_track()) {
            return;
        }
        
        $mautic_url = Plugin::get_option('mautic_url', '');
        $tracking_code = Plugin::get_option('tracking_code', '');
        
        if (empty($mautic_url) || empty($tracking_code)) {
            return;
        }
        
        $position = Plugin::get_option('tracking_position', 'wp_head');
        
        // Only add to footer if position is wp_footer
        if ($position !== 'wp_footer') {
            return;
        }
        
        $this->output_tracking_script($mautic_url, $tracking_code);
    }
    
    /**
     * Output tracking script
     */
    private function output_tracking_script($mautic_url, $tracking_code) {
        $tracking_image = Plugin::get_option('tracking_image', false);
        $track_logged_users = Plugin::get_option('track_logged_users', false);
        
        ?>
        <script type="text/javascript">
        (function(w,d,t,u,n,a,m){w['MauticTrackingObject']=n;
            w[n]=w[n]||function(){(w[n].q=w[n].q||[]).push(arguments)},a=d.createElement(t),
            m=d.getElementsByTagName(t)[0];a.async=1;a.src=u;m.parentNode.insertBefore(a,m)
        })(window,document,'script','<?php echo esc_url($mautic_url); ?>/mtc.js','mt');
        
        <?php if ($track_logged_users || !is_user_logged_in()): ?>
        mt('send', 'pageview');
        <?php endif; ?>
        
        // GDPR compliance function
        function wpmautic_send() {
            mt('send', 'pageview');
        }
        </script>
        
        <?php if ($tracking_image): ?>
        <noscript>
            <img src="<?php echo esc_url($mautic_url); ?>/mtracking/<?php echo esc_attr($tracking_code); ?>" 
                 style="display:none;" alt="" width="1" height="1" />
        </noscript>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Enqueue tracking script for AJAX
     */
    public function enqueue_tracking_script() {
        if (!Plugin::get_option('tracking_enabled', false)) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'wpmautic', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpmautic_tracking'),
            'tracking_enabled' => Plugin::get_option('tracking_enabled', false),
            'position' => Plugin::get_option('tracking_position', 'wp_head')
        ));
    }
    
    /**
     * Check if should track
     */
    private function should_track() {
        if (!Plugin::get_option('tracking_enabled', false)) {
            return false;
        }
        
        // Don't track logged users if setting is disabled
        if (is_user_logged_in() && !Plugin::get_option('track_logged_users', false)) {
            return false;
        }
        
        // Don't track in admin
        if (is_admin()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Mautic shortcode handler
     */
    public function mautic_shortcode($atts, $content = '') {
        $atts = shortcode_atts(array(
            'type' => 'form',
            'id' => '',
            'slot' => ''
        ), $atts);
        
        $mautic_url = Plugin::get_option('mautic_url', '');
        
        if (empty($mautic_url)) {
            return $content;
        }
        
        switch ($atts['type']) {
            case 'form':
                return $this->render_mautic_form($mautic_url, $atts['id']);
                
            case 'content':
                return $this->render_mautic_content($mautic_url, $atts['slot'], $content);
                
            default:
                return $content;
        }
    }
    
    /**
     * Render Mautic form
     */
    private function render_mautic_form($mautic_url, $form_id) {
        if (empty($form_id)) {
            return '';
        }
        
        return '<script type="text/javascript" src="' . esc_url($mautic_url) . '/form/generate.js?id=' . esc_attr($form_id) . '"></script>';
    }
    
    /**
     * Render Mautic dynamic content
     */
    private function render_mautic_content($mautic_url, $slot, $default_content) {
        if (empty($slot)) {
            return $default_content;
        }
        
        return '<div data-slot="' . esc_attr($slot) . '" data-mautic-content="' . esc_attr($slot) . '">' . $default_content . '</div>';
    }
    
    /**
     * AJAX send tracking (for GDPR compliance)
     */
    public function ajax_send_tracking() {
        if (!Plugin::verify_nonce($_POST['nonce'], 'wpmautic_tracking')) {
            wp_die('Security check failed');
        }
        
        // This would trigger the tracking script
        wp_send_json_success('Tracking sent');
    }
    
    /**
     * Get tracking code from Mautic
     */
    public function get_tracking_code_from_mautic() {
        $mautic_url = Plugin::get_option('mautic_url', '');
        $username = Plugin::get_option('mautic_username', '');
        $password = $this->get_decrypted_password();
        
        if (empty($mautic_url) || empty($username) || empty($password)) {
            return false;
        }
        
        $headers = array(
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
            'Content-Type' => 'application/json'
        );
        
        $args = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => $headers
        );
        
        $response = wp_remote_get($mautic_url . '/api/config', $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code >= 200 && $status_code < 300) {
            $data = json_decode($body, true);
            return $data['site_url'] ?? false;
        }
        
        return false;
    }
    
    /**
     * Get decrypted password
     */
    private function get_decrypted_password() {
        $encrypted_password = Plugin::get_option('mautic_password');
        
        if (empty($encrypted_password)) {
            return '';
        }
        
        // Try to decrypt
        if (function_exists('openssl_decrypt')) {
            $decrypted = openssl_decrypt($encrypted_password, 'AES-256-CBC', wp_salt());
            if ($decrypted !== false) {
                return $decrypted;
            }
        }
        
        // Fallback to base64 decode
        return base64_decode($encrypted_password);
    }
}
