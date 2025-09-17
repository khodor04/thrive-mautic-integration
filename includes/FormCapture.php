<?php

namespace ThriveMautic;

/**
 * Form capture and processing class
 */
class FormCapture {
    
    /**
     * Database instance
     */
    private $database;
    
    /**
     * Mautic API instance
     */
    private $mautic_api;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->database = new Database();
        $this->mautic_api = new MauticAPI();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Form capture hooks
        add_action('wp_ajax_tve_leads_ajax_conversion', array($this, 'capture_ajax_submission'), 5);
        add_action('wp_ajax_nopriv_tve_leads_ajax_conversion', array($this, 'capture_ajax_submission'), 5);
        add_action('wp_ajax_tqb_quiz_submit', array($this, 'capture_ajax_submission'), 5);
        add_action('wp_ajax_nopriv_tqb_quiz_submit', array($this, 'capture_ajax_submission'), 5);
        add_action('wp_ajax_tcb_api_form_submit', array($this, 'capture_ajax_submission'), 5);
        add_action('wp_ajax_nopriv_tcb_api_form_submit', array($this, 'capture_ajax_submission'), 5);
        
        add_action('wp_loaded', array($this, 'capture_post_submission'), 999);
        
        // Cron for retries
        add_action('thrive_mautic_retry_hook', array($this, 'retry_failed_submissions'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
    }
    
    /**
     * Capture AJAX form submission
     */
    public function capture_ajax_submission() {
        $this->process_form_data($_POST, 'ajax_submission');
    }
    
    /**
     * Capture POST form submission
     */
    public function capture_post_submission() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
            $is_thrive = $this->is_thrive_form($_POST);
            
            if ($is_thrive && (isset($_POST['email']) || isset($_POST['tve_name']))) {
                $this->process_form_data($_POST, 'post_submission');
            }
        }
    }
    
    /**
     * Check if form is from Thrive Themes
     */
    private function is_thrive_form($data) {
        foreach ($data as $key => $value) {
            if (strpos($key, 'tve') !== false || 
                strpos($key, 'tcb') !== false || 
                strpos($key, 'tqb') !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Process form data
     */
    public function process_form_data($data, $source = 'unknown') {
        try {
            $email = $this->extract_email($data);
            $name = $this->extract_name($data);
            
            if (empty($email)) {
                Plugin::log_error('No email found in form submission', array(
                    'source' => $source,
                    'data_keys' => array_keys($data)
                ));
                return;
            }
            
            $form_type = $this->determine_form_type($data, $source);
            $form_id = $this->extract_form_id($data);
            
            $post_id = get_the_ID() ?: (isset($_POST['post_id']) ? intval($_POST['post_id']) : 0);
            $post_title = $post_id ? get_the_title($post_id) : __('Unknown Post', 'thrive-mautic-integration');
            $segment_info = $this->get_post_segment_info($post_id);
            
            // Enhanced tracking data
            $tracking_data = array(
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'ip_address' => Plugin::get_client_ip(),
                'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
            );
            
            $this->queue_submission(
                $email,
                $name,
                $segment_info['segment_id'],
                $segment_info['segment_name'],
                $form_type,
                $form_id,
                $post_id,
                $post_title,
                $tracking_data
            );
            
        } catch (Exception $e) {
            Plugin::log_error('Exception in process_form_data', array(
                'error' => $e->getMessage(),
                'source' => $source
            ));
        }
    }
    
    /**
     * Extract email from form data
     */
    private function extract_email($data) {
        if (isset($data['email'])) {
            return Plugin::sanitize_input($data['email'], 'email');
        }
        
        // Look for email in nested arrays
        foreach ($data as $key => $value) {
            if (is_array($value) && isset($value['email'])) {
                return Plugin::sanitize_input($value['email'], 'email');
            }
        }
        
        return '';
    }
    
    /**
     * Extract name from form data
     */
    private function extract_name($data) {
        if (isset($data['tve_name'])) {
            return Plugin::sanitize_input($data['tve_name'], 'text');
        }
        
        if (isset($data['name'])) {
            return Plugin::sanitize_input($data['name'], 'text');
        }
        
        // Look for name in nested arrays
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (isset($value['tve_name'])) {
                    return Plugin::sanitize_input($value['tve_name'], 'text');
                }
                if (isset($value['name'])) {
                    return Plugin::sanitize_input($value['name'], 'text');
                }
            }
        }
        
        return '';
    }
    
    /**
     * Determine form type
     */
    private function determine_form_type($data, $source) {
        $action = isset($data['action']) ? $data['action'] : '';
        
        if (strpos($action, 'quiz') !== false || strpos($action, 'tqb') !== false) {
            return 'thrive_quiz';
        } elseif (strpos($action, 'leads') !== false || strpos($action, 'tve_leads') !== false) {
            return 'thrive_leads';
        } elseif (strpos($action, 'tcb') !== false || strpos($action, 'architect') !== false) {
            return 'thrive_architect';
        }
        
        return 'unknown';
    }
    
    /**
     * Extract form ID
     */
    private function extract_form_id($data) {
        $possible_ids = array('form_id', 'tve_form_id', 'quiz_id', 'tcb_form_id', 'variation_key');
        
        foreach ($possible_ids as $field) {
            if (isset($data[$field])) {
                return Plugin::sanitize_input($data[$field], 'text');
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Get segment info for post
     */
    private function get_post_segment_info($post_id) {
        if ($post_id > 0) {
            $setup = $this->database->get_campaign_setup($post_id);
            
            if ($setup && !empty($setup->optin_segment_id)) {
                return array(
                    'segment_id' => $setup->optin_segment_id,
                    'segment_name' => $setup->optin_segment_name
                );
            }
        }
        
        // Fall back to default
        return array(
            'segment_id' => Plugin::get_option('default_segment', ''),
            'segment_name' => __('Default Segment', 'thrive-mautic-integration')
        );
    }
    
    /**
     * Queue submission
     */
    public function queue_submission($email, $name, $segment_id = '', $segment_name = '', $form_type = '', $form_id = '', $post_id = 0, $post_title = '', $tracking_data = array()) {
        $data = array(
            'email' => $email,
            'name' => $name,
            'segment_id' => $segment_id,
            'segment_name' => $segment_name,
            'form_type' => $form_type,
            'form_id' => $form_id,
            'post_id' => $post_id,
            'post_title' => $post_title,
            'user_agent' => $tracking_data['user_agent'] ?? '',
            'ip_address' => $tracking_data['ip_address'] ?? '',
            'referrer' => $tracking_data['referrer'] ?? ''
        );
        
        $queue_id = $this->database->queue_submission($data);
        
        if ($queue_id) {
            // Process immediately
            $this->process_single_submission($queue_id);
        }
    }
    
    /**
     * Process single submission
     */
    public function process_single_submission($queue_id) {
        $submission = $this->database->get_submission($queue_id);
        
        if (!$submission || $submission->status === 'success') {
            return;
        }
        
        $response = $this->mautic_api->process_submission(
            $submission->email,
            $submission->name,
            $submission->segment_id,
            $submission->form_type
        );
        
        if ($response['success']) {
            $this->database->update_submission_status($queue_id, 'success');
        } else {
            $status = $submission->attempts >= 5 ? 'failed' : 'pending';
            $this->database->update_submission_status($queue_id, $status, $response['error']);
        }
    }
    
    /**
     * Retry failed submissions
     */
    public function retry_failed_submissions() {
        $pending_submissions = $this->database->get_pending_submissions(10);
        
        foreach ($pending_submissions as $submission) {
            $this->process_single_submission($submission->id);
        }
    }
    
    /**
     * Add cron intervals
     */
    public function add_cron_intervals($schedules) {
        $schedules['quarterhourly'] = array(
            'interval' => 900,
            'display' => __('Every 15 Minutes', 'thrive-mautic-integration')
        );
        return $schedules;
    }
}
