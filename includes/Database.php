<?php

namespace ThriveMautic;

/**
 * Database management class
 */
class Database {
    
    /**
     * Queue table name
     */
    private $queue_table;
    
    /**
     * Campaigns table name
     */
    private $campaigns_table;
    
    /**
     * Error logs table name
     */
    private $error_logs_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->queue_table = $wpdb->prefix . 'thrive_mautic_queue';
        $this->campaigns_table = $wpdb->prefix . 'thrive_mautic_campaigns';
        $this->error_logs_table = $wpdb->prefix . 'thrive_mautic_error_logs';
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Main queue table with enhanced tracking
        $sql1 = "CREATE TABLE IF NOT EXISTS {$this->queue_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            name varchar(255) DEFAULT '',
            segment_id varchar(50) DEFAULT '',
            segment_name varchar(255) DEFAULT '',
            form_type varchar(50) DEFAULT '',
            form_id varchar(50) DEFAULT '',
            post_id int(11) DEFAULT 0,
            post_title varchar(255) DEFAULT '',
            status enum('pending','success','failed') DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            last_error text DEFAULT '',
            user_agent text DEFAULT '',
            ip_address varchar(45) DEFAULT '',
            referrer varchar(500) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY email (email),
            KEY status (status),
            KEY post_id (post_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Campaigns table
        $sql2 = "CREATE TABLE IF NOT EXISTS {$this->campaigns_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id int(11) NOT NULL,
            lead_magnet_name varchar(255) NOT NULL,
            optin_segment_id varchar(50) DEFAULT '',
            optin_segment_name varchar(255) DEFAULT '',
            marketing_segment_id varchar(50) DEFAULT '',
            marketing_segment_name varchar(255) DEFAULT '',
            lead_magnet_url varchar(500) DEFAULT '',
            instructions text DEFAULT '',
            status enum('active','inactive') DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY post_id (post_id)
        ) $charset_collate;";
        
        // Error logs table
        $sql3 = "CREATE TABLE IF NOT EXISTS {$this->error_logs_table} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            error_type varchar(50) DEFAULT '',
            message text NOT NULL,
            context longtext DEFAULT '',
            user_id int(11) DEFAULT 0,
            ip_address varchar(45) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY error_type (error_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);
        
        // Update database version
        update_option('thrive_mautic_db_version', THRIVE_MAUTIC_VERSION);
    }
    
    /**
     * Queue a submission
     */
    public function queue_submission($data) {
        global $wpdb;
        
        $result = $wpdb->insert(
            $this->queue_table,
            array(
                'email' => $data['email'],
                'name' => $data['name'],
                'segment_id' => $data['segment_id'],
                'segment_name' => $data['segment_name'],
                'form_type' => $data['form_type'],
                'form_id' => $data['form_id'],
                'post_id' => $data['post_id'],
                'post_title' => $data['post_title'],
                'status' => 'pending',
                'user_agent' => $data['user_agent'] ?? '',
                'ip_address' => $data['ip_address'] ?? '',
                'referrer' => $data['referrer'] ?? ''
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Get submission by ID
     */
    public function get_submission($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->queue_table} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Update submission status
     */
    public function update_submission_status($id, $status, $error = '') {
        global $wpdb;
        
        return $wpdb->update(
            $this->queue_table,
            array(
                'status' => $status,
                'last_error' => $error,
                'attempts' => $wpdb->get_var($wpdb->prepare(
                    "SELECT attempts FROM {$this->queue_table} WHERE id = %d",
                    $id
                )) + 1
            ),
            array('id' => $id),
            array('%s', '%s', '%d'),
            array('%d')
        );
    }
    
    /**
     * Get pending submissions
     */
    public function get_pending_submissions($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->queue_table} WHERE status = 'pending' AND attempts < 5 ORDER BY created_at ASC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats() {
        global $wpdb;
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $last_24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
        
        // Today's signups
        $today_signups = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->queue_table} WHERE DATE(created_at) = %s AND status = 'success'",
            $today
        ));
        
        $yesterday_signups = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->queue_table} WHERE DATE(created_at) = %s AND status = 'success'",
            $yesterday
        ));
        
        // Success rate (last 24 hours)
        $total_24h = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->queue_table} WHERE created_at >= %s",
            $last_24h
        ));
        
        $success_24h = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->queue_table} WHERE created_at >= %s AND status = 'success'",
            $last_24h
        ));
        
        $success_rate = $total_24h > 0 ? round(($success_24h / $total_24h) * 100, 1) : 100;
        
        // Pending and failed counts
        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->queue_table} WHERE status = 'pending'");
        $failed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$this->queue_table} WHERE status = 'failed'");
        
        return array(
            'today_signups' => $today_signups,
            'yesterday_signups' => $yesterday_signups,
            'success_rate' => $success_rate,
            'pending_count' => $pending_count,
            'failed_count' => $failed_count
        );
    }
    
    /**
     * Get recent activity
     */
    public function get_recent_activity($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->queue_table} ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Get top performing posts
     */
    public function get_top_posts($days = 7, $limit = 5) {
        global $wpdb;
        
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT post_title, post_id, COUNT(*) as signup_count
            FROM {$this->queue_table} 
            WHERE created_at >= %s AND status = 'success' AND post_id > 0
            GROUP BY post_id 
            ORDER BY signup_count DESC 
            LIMIT %d",
            $since,
            $limit
        ));
    }
    
    /**
     * Get campaign setup by post ID
     */
    public function get_campaign_setup($post_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->campaigns_table} WHERE post_id = %d",
            $post_id
        ));
    }
    
    /**
     * Save campaign setup
     */
    public function save_campaign_setup($data) {
        global $wpdb;
        
        return $wpdb->replace(
            $this->campaigns_table,
            array(
                'post_id' => $data['post_id'],
                'lead_magnet_name' => $data['lead_magnet_name'],
                'optin_segment_id' => $data['optin_segment_id'],
                'optin_segment_name' => $data['optin_segment_name'],
                'marketing_segment_id' => $data['marketing_segment_id'],
                'marketing_segment_name' => $data['marketing_segment_name'],
                'lead_magnet_url' => $data['lead_magnet_url'],
                'instructions' => $data['instructions'],
                'status' => 'active'
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Store error log
     */
    public function store_error_log($log_entry) {
        global $wpdb;
        
        return $wpdb->insert(
            $this->error_logs_table,
            array(
                'error_type' => $log_entry['context']['type'] ?? 'general',
                'message' => $log_entry['message'],
                'context' => json_encode($log_entry['context']),
                'user_id' => $log_entry['user_id'],
                'ip_address' => $log_entry['ip']
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Get error logs
     */
    public function get_error_logs($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->error_logs_table} ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * Clean old logs
     */
    public function clean_old_logs($days = 30) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted_queue = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->queue_table} WHERE created_at < %s",
            $cutoff_date
        ));
        
        $deleted_errors = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->error_logs_table} WHERE created_at < %s",
            $cutoff_date
        ));
        
        return $deleted_queue + $deleted_errors;
    }
}
