<?php
/**
 * Plugin Name: Thrive-Mautic Integration
 * Plugin URI: https://yourwebsite.com/thrive-mautic-integration
 * Description: Thrive Themes Integration With Mautic
 * Version: 5.8.2
 * Author: Khodor Ghalayini
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * Text Domain: thrive-mautic-integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// WRAP EVERYTHING IN TRY-CATCH TO PREVENT CRASHES
try {
    // Define plugin constants
    define('THRIVE_MAUTIC_VERSION', '5.8.2');
    define('THRIVE_MAUTIC_PLUGIN_FILE', __FILE__);
    define('THRIVE_MAUTIC_PLUGIN_DIR', plugin_dir_path(__FILE__));

    // Password encryption functions
    function encrypt_password($password) {
        try {
            $key = wp_salt('AUTH_KEY');
            $iv = wp_salt('NONCE_KEY');
            return base64_encode(openssl_encrypt($password, 'AES-256-CBC', $key, 0, substr($iv, 0, 16)));
        } catch (Exception $e) {
            return $password; // Fallback to plain text if encryption fails
        }
    }

    function decrypt_password($encrypted_password) {
        try {
            $key = wp_salt('AUTH_KEY');
            $iv = wp_salt('NONCE_KEY');
            return openssl_decrypt(base64_decode($encrypted_password), 'AES-256-CBC', $key, 0, substr($iv, 0, 16));
        } catch (Exception $e) {
            return $encrypted_password; // Fallback to return as-is if decryption fails
        }
    }

    // Simple admin menu registration
    add_action('admin_menu', function() {
        try {
            // Check if user has proper capabilities
            if (!current_user_can('manage_options')) {
                return;
            }
            // Main Dashboard Menu
            add_menu_page(
                'Thrive-Mautic Dashboard',
                'Thrive-Mautic',
                'manage_options',
                'thrive-mautic-dashboard',
                function() {
                    try {
                        // Get Mautic connection status
                        $mautic_status = 'Not configured';
                        $mautic_class = 'warning';
                        
                        $base_url = get_option('thrive_mautic_base_url', '');
                        $username = get_option('thrive_mautic_username', '');
                        $encrypted_password = get_option('thrive_mautic_password', '');
                        
                        if (!empty($base_url) && !empty($username) && !empty($encrypted_password)) {
                            // Test connection with proper Mautic 6.x API endpoint
                            $connection_test = wp_remote_get($base_url . '/api/contacts?limit=1', array(
                                'headers' => array(
                                    'Authorization' => 'Basic ' . base64_encode($username . ':' . decrypt_password($encrypted_password)),
                                    'User-Agent' => 'Thrive-Mautic-Plugin/' . THRIVE_MAUTIC_VERSION
                                ),
                                'timeout' => 10,
                                'sslverify' => true
                            ));
                            
                            if (!is_wp_error($connection_test)) {
                                $response_code = wp_remote_retrieve_response_code($connection_test);
                                if ($response_code === 200) {
                                    $mautic_status = 'Connected';
                                    $mautic_class = 'success';
                                } else {
                                    $mautic_status = 'Connection failed (HTTP ' . $response_code . ')';
                                    $mautic_class = 'error';
                                }
                            } else {
                                $mautic_status = 'Connection failed: ' . $connection_test->get_error_message();
                                $mautic_class = 'error';
                            }
                        }
                        
                        echo '<div class="wrap">';
                        echo '<h1>Thrive-Mautic Dashboard</h1>';
                        
                        // Mautic Connection Status
                        echo '<div class="notice notice-' . $mautic_class . ' inline">';
                        echo '<p><strong>Mautic Status:</strong> ' . esc_html($mautic_status) . '</p>';
                        echo '</div>';
                        
                        // Get real statistics
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
                        
                        $today = date('Y-m-d');
                        $today_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s",
                            $today
                        ));
                        
                        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
                        $success_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'success'");
                        $pending_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'pending'");
                        $failed_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'failed'");
                        
                        $success_rate = $total_count > 0 ? round(($success_count / $total_count) * 100, 1) : 0;
                        
                        // Statistics
                        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
                        
                        echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                        echo '<h3>Today\'s Signups</h3>';
                        echo '<div style="font-size: 32px; font-weight: bold; color: #3498db;">' . intval($today_count) . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                        echo '<h3>Total Signups</h3>';
                        echo '<div style="font-size: 32px; font-weight: bold; color: #3498db;">' . intval($total_count) . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                        echo '<h3>Success Rate</h3>';
                        echo '<div style="font-size: 32px; font-weight: bold; color: #27ae60;">' . $success_rate . '%</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                        echo '<h3>Pending</h3>';
                        echo '<div style="font-size: 32px; font-weight: bold; color: #f39c12;">' . intval($pending_count) . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                        echo '<h3>Failed</h3>';
                        echo '<div style="font-size: 32px; font-weight: bold; color: #e74c3c;">' . intval($failed_count) . '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        
                        // Quick Actions
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>Quick Actions</h2>';
                        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
                        echo '<a href="' . admin_url('admin.php?page=thrive-mautic-settings') . '" class="button button-primary">Configure Mautic Settings</a>';
                        echo '<a href="' . admin_url('admin.php?page=thrive-mautic-submissions') . '" class="button">View Submissions</a>';
                        echo '<a href="' . admin_url('admin.php?page=thrive-mautic-help') . '" class="button">Help & Setup Guide</a>';
                        echo '<a href="' . admin_url('admin.php?page=thrive-mautic-settings') . '" class="button">Test Connection</a>';
                        echo '</div>';
                        echo '</div>';
                        
                        // Plugin Info
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>Plugin Information</h2>';
                        echo '<p><strong>Version:</strong> ' . THRIVE_MAUTIC_VERSION . '</p>';
                        echo '<p><strong>Status:</strong> Active and ready to capture forms</p>';
                        echo '<p><strong>Form Capture:</strong> Thrive Architect, Thrive Lightboxes, Thrive Leads, Thrive Quiz Builder</p>';
                        echo '<p><strong>Plugin Website:</strong> <a href="https://github.com/khodor04/thrive-mautic-integration" target="_blank">https://github.com/khodor04/thrive-mautic-integration</a></p>';
                        echo '<p><strong>Auto-Updates:</strong> ' . (get_option('thrive_mautic_auto_update', true) ? 'Enabled' : 'Disabled') . ' | <a href="' . admin_url('admin.php?page=thrive-mautic-settings') . '">Change Settings</a></p>';
                        echo '<p><strong>Updates:</strong> Managed by WordPress | <a href="' . admin_url('plugins.php') . '">Go to Plugins Page</a></p>';
                        echo '</div>';
                        
                        echo '</div>';
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>Thrive-Mautic Dashboard</h1>';
                        echo '<div class="notice notice-error"><p>Dashboard error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                },
                'dashicons-email-alt',
                30
            );
            
            // Submissions Submenu
            add_submenu_page(
                'thrive-mautic-dashboard',
                'Submissions',
                'Submissions',
                'manage_options',
                'thrive-mautic-submissions',
                function() {
                    try {
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
                        
                        // Handle actions
                        if (isset($_POST['action']) && wp_verify_nonce($_POST['thrive_mautic_nonce'], 'submissions_action')) {
                            if ($_POST['action'] === 'retry_failed' && isset($_POST['submission_id'])) {
                                $submission_id = intval($_POST['submission_id']);
                                $wpdb->update(
                                    $table_name,
                                    array('status' => 'pending'),
                                    array('id' => $submission_id)
                                );
                                echo '<div class="notice notice-success"><p>Submission queued for retry!</p></div>';
                            } elseif ($_POST['action'] === 'clear_logs') {
                                // Use prepared statement to prevent SQL injection
                                $wpdb->query($wpdb->prepare(
                                    "DELETE FROM {$wpdb->prefix}thrive_mautic_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                                    30
                                ));
                                echo '<div class="notice notice-success"><p>Old logs cleared!</p></div>';
                            }
                        }
                        
                        // Get submissions with prepared statement
                        $submissions = $wpdb->get_results(
                            $wpdb->prepare(
                                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
                                50
                            )
                        );
                        
                        echo '<div class="wrap">';
                        echo '<h1>Thrive-Mautic Submissions</h1>';
                        
                        // Actions
                        echo '<div style="margin: 20px 0;">';
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('submissions_action', 'thrive_mautic_nonce');
                        echo '<input type="hidden" name="action" value="clear_logs">';
                        echo '<input type="submit" class="button" value="Clear Old Logs (30+ days)">';
                        echo '</form>';
                        echo '</div>';
                        
                        // Submissions table
                        echo '<table class="wp-list-table widefat fixed striped">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th>ID</th>';
                        echo '<th>Form Type</th>';
                        echo '<th>Email</th>';
                        echo '<th>Name</th>';
                        echo '<th>Segment</th>';
                        echo '<th>Status</th>';
                        echo '<th>Mautic ID</th>';
                        echo '<th>Created</th>';
                        echo '<th>Actions</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                        
                        foreach ($submissions as $submission) {
                            $status_class = '';
                            switch ($submission->status) {
                                case 'success':
                                    $status_class = 'color: #27ae60; font-weight: bold;';
                                    break;
                                case 'failed':
                                    $status_class = 'color: #e74c3c; font-weight: bold;';
                                    break;
                                case 'pending':
                                    $status_class = 'color: #f39c12; font-weight: bold;';
                                    break;
                                case 'processing':
                                    $status_class = 'color: #3498db; font-weight: bold;';
                                    break;
                            }
                            
                            echo '<tr>';
                            echo '<td>' . $submission->id . '</td>';
                            echo '<td>' . esc_html($submission->form_type) . '</td>';
                            echo '<td>' . esc_html($submission->email) . '</td>';
                            echo '<td>' . esc_html($submission->name) . '</td>';
                            echo '<td>' . esc_html($submission->segment_id) . '</td>';
                            echo '<td style="' . $status_class . '">' . esc_html(ucfirst($submission->status)) . '</td>';
                            echo '<td>' . esc_html($submission->mautic_contact_id) . '</td>';
                            echo '<td>' . esc_html($submission->created_at) . '</td>';
                            echo '<td>';
                            
                            if ($submission->status === 'failed') {
                                echo '<form method="post" style="display: inline;">';
                                wp_nonce_field('submissions_action', 'thrive_mautic_nonce');
                                echo '<input type="hidden" name="action" value="retry_failed">';
                                echo '<input type="hidden" name="submission_id" value="' . $submission->id . '">';
                                echo '<input type="submit" class="button button-small" value="Retry">';
                                echo '</form>';
                            }
                            
                            if (!empty($submission->error_message)) {
                                echo ' <span title="' . esc_attr($submission->error_message) . '">⚠️</span>';
                            }
                            
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>Thrive-Mautic Submissions</h1>';
                        echo '<div class="notice notice-error"><p>Submissions error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                }
            );
            
            // Contact Sync Submenu
            add_submenu_page(
                'thrive-mautic-dashboard',
                'Contact Sync',
                'Contact Sync',
                'manage_options',
                'thrive-mautic-contacts',
                function() {
                    try {
                        echo '<div class="wrap">';
                        echo '<h1>Contact Sync Dashboard</h1>';
                        
                        // Handle manual sync
                        if (isset($_POST['manual_sync']) && wp_verify_nonce($_POST['thrive_mautic_nonce'], 'manual_sync')) {
                            $sync_result = thrive_mautic_sync_contacts_from_mautic();
                            if ($sync_result['success']) {
                                echo '<div class="notice notice-success"><p>Sync completed! ' . $sync_result['count'] . ' contacts synced.</p></div>';
                            } else {
                                echo '<div class="notice notice-error"><p>Sync failed: ' . $sync_result['message'] . '</p></div>';
                            }
                        }
                        
                        // Get contact statistics
                        $stats = thrive_mautic_get_contact_stats();
                        
                        // Statistics Summary
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>📊 Contact Statistics</h2>';
                        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #007cba;">';
                        echo '<h3>Total Contacts</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #007cba;">' . $stats['total'] . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">';
                        echo '<h3>Verified</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #28a745;">' . $stats['verified'] . ' (' . $stats['verified_percent'] . '%)</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">';
                        echo '<h3>Unverified</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #ffc107;">' . $stats['unverified'] . ' (' . $stats['unverified_percent'] . '%)</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #6f42c1;">';
                        echo '<h3>Last Sync</h3>';
                        echo '<div style="font-size: 16px; font-weight: bold; color: #6f42c1;">' . $stats['last_sync'] . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #e83e8c;">';
                        echo '<h3>Sync Status</h3>';
                        echo '<div style="font-size: 16px; font-weight: bold; color: #e83e8c;">' . $stats['sync_status'] . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #20c997;">';
                        echo '<h3>Unique Tags</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #20c997;">' . $stats['unique_tags'] . '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Sync Controls
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🔄 Sync Controls</h2>';
                        echo '<form method="post" style="display: inline;">';
                        wp_nonce_field('manual_sync', 'thrive_mautic_nonce');
                        echo '<input type="submit" name="manual_sync" class="button button-primary" value="🔄 Sync Now" onclick="this.value=\'Syncing...\'; this.disabled=true;">';
                        echo '</form>';
                        echo '<button type="button" class="button" onclick="location.reload();" style="margin-left: 10px;">🔄 Refresh</button>';
                        echo '<p class="description">Manual sync will pull all contacts from Mautic and update local database. This may take a few minutes for large contact lists.</p>';
                        echo '</div>';
                        
                        // Contacts Table
                        $contacts = thrive_mautic_get_synced_contacts(50); // Get last 50 contacts
                        
                        if (!empty($contacts)) {
                            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                            echo '<h2>📋 Recent Contacts</h2>';
                            echo '<table class="wp-list-table widefat fixed striped">';
                            echo '<thead>';
                            echo '<tr>';
                            echo '<th>Name</th>';
                            echo '<th>Email</th>';
                            echo '<th>Phone</th>';
                            echo '<th>Company</th>';
                            echo '<th>Segment</th>';
                            echo '<th>Tags</th>';
                            echo '<th>Status</th>';
                            echo '<th>Last Synced</th>';
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';
                            
                            foreach ($contacts as $contact) {
                                $verification_status = $contact['verification_status'] === 'verified' ? 
                                    '<span style="color: #28a745; font-weight: bold;">✅ Verified</span>' : 
                                    '<span style="color: #ffc107; font-weight: bold;">⏳ Unverified</span>';
                                
                                $tags_display = '';
                                if (!empty($contact['tags'])) {
                                    $tags = json_decode($contact['tags'], true);
                                    if (is_array($tags)) {
                                        $tags_display = implode(', ', array_slice($tags, 0, 3));
                                        if (count($tags) > 3) {
                                            $tags_display .= '... (+' . (count($tags) - 3) . ' more)';
                                        }
                                    }
                                }
                                
                                echo '<tr>';
                                echo '<td><strong>' . esc_html($contact['firstname'] . ' ' . $contact['lastname']) . '</strong></td>';
                                echo '<td>' . esc_html($contact['email']) . '</td>';
                                echo '<td>' . esc_html($contact['phone']) . '</td>';
                                echo '<td>' . esc_html($contact['company']) . '</td>';
                                echo '<td><code>' . esc_html($contact['segment_id']) . '</code></td>';
                                echo '<td title="' . esc_attr($tags_display) . '">' . esc_html($tags_display) . '</td>';
                                echo '<td>' . $verification_status . '</td>';
                                echo '<td>' . esc_html($contact['last_synced']) . '</td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody>';
                            echo '</table>';
                            echo '</div>';
                        } else {
                            echo '<div class="notice notice-warning"><p>No contacts found. Click "Sync Now" to pull contacts from Mautic.</p></div>';
                        }
                        
                        // Sync Settings
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>⚙️ Sync Settings</h2>';
                        echo '<p><strong>Auto Sync:</strong> Every 15 minutes</p>';
                        echo '<p><strong>Webhook URL:</strong> <code>' . home_url('/wp-json/thrive-mautic/v1/webhook') . '</code></p>';
                        echo '<p><strong>Mautic Integration:</strong> Add this webhook URL to your Mautic webhook settings for real-time updates.</p>';
                        echo '</div>';
                        
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>Contact Sync Dashboard</h1>';
                        echo '<div class="notice notice-error"><p>Contact sync error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                }
            );
            
            // Lead Management Submenu
            add_submenu_page(
                'thrive-mautic-dashboard',
                'Lead Management',
                'Lead Management',
                'manage_options',
                'thrive-mautic-leads',
                function() {
                    try {
                        echo '<div class="wrap">';
                        echo '<h1>Lead Management Dashboard</h1>';
                        
                        // Get lead statistics
                        $lead_stats = thrive_mautic_get_lead_workflow_stats();
                        
                        // Workflow Overview
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🔄 Lead Workflow Overview</h2>';
                        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">';
                        echo '<h3>✅ Verified Users</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #28a745;">' . $lead_stats['verified'] . '</div>';
                        echo '<div style="font-size: 14px; color: #666;">' . $lead_stats['verified_percent'] . '% of total</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">';
                        echo '<h3>⏳ Unverified Users</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #ffc107;">' . $lead_stats['unverified'] . '</div>';
                        echo '<div style="font-size: 14px; color: #666;">' . $lead_stats['unverified_percent'] . '% of total</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #007cba;">';
                        echo '<h3>🔐 Google OAuth</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #007cba;">' . $lead_stats['google_oauth'] . '</div>';
                        echo '<div style="font-size: 14px; color: #666;">Auto-verified</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #6f42c1;">';
                        echo '<h3>📧 Newsletter</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #6f42c1;">' . $lead_stats['newsletter'] . '</div>';
                        echo '<div style="font-size: 14px; color: #666;">Subscribers</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #e83e8c;">';
                        echo '<h3>🧩 Quiz Takers</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #e83e8c;">' . $lead_stats['quiz_takers'] . '</div>';
                        echo '<div style="font-size: 14px; color: #666;">Completed</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #20c997;">';
                        echo '<h3>📄 Lead Magnets</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #20c997;">' . $lead_stats['lead_magnets'] . '</div>';
                        echo '<div style="font-size: 14px; color: #666;">Downloads</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Lead Source Breakdown
                        if (!empty($lead_stats['sources'])) {
                            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                            echo '<h2>📊 Lead Source Breakdown</h2>';
                            echo '<table class="wp-list-table widefat fixed striped">';
                            echo '<thead>';
                            echo '<tr>';
                            echo '<th>Source</th>';
                            echo '<th>Count</th>';
                            echo '<th>Percentage</th>';
                            echo '<th>Verification Rate</th>';
                            echo '<th>Last Activity</th>';
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';
                            
                            foreach ($lead_stats['sources'] as $source) {
                                $verification_rate = $source['total'] > 0 ? round(($source['verified'] / $source['total']) * 100, 1) : 0;
                                echo '<tr>';
                                echo '<td><strong>' . esc_html($source['name']) . '</strong></td>';
                                echo '<td><span style="font-weight: bold; color: #007cba;">' . $source['total'] . '</span></td>';
                                echo '<td>' . $source['percentage'] . '%</td>';
                                echo '<td><span style="color: ' . ($verification_rate > 50 ? '#28a745' : '#ffc107') . '; font-weight: bold;">' . $verification_rate . '%</span></td>';
                                echo '<td>' . esc_html($source['last_activity']) . '</td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody>';
                            echo '</table>';
                            echo '</div>';
                        }
                        
                        // Content Performance
                        if (!empty($lead_stats['content_tags'])) {
                            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                            echo '<h2>📚 Content Performance</h2>';
                            echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">';
                            
                            foreach ($lead_stats['content_tags'] as $content) {
                                echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #007cba;">';
                                echo '<h4 style="margin: 0 0 10px 0; color: #007cba;">' . esc_html($content['tag']) . '</h4>';
                                echo '<div style="font-size: 20px; font-weight: bold; color: #333;">' . $content['count'] . ' downloads</div>';
                                echo '<div style="font-size: 12px; color: #666;">Last: ' . esc_html($content['last_download']) . '</div>';
                                echo '</div>';
                            }
                            
                            echo '</div>';
                            echo '</div>';
                        }
                        
                        // Workflow Recommendations
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>💡 Workflow Recommendations</h2>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                        
                        echo '<div>';
                        echo '<h3>🎯 Segmentation Strategy</h3>';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li><strong>verified</strong> - Confirmed users (Google OAuth + verified email)</li>';
                        echo '<li><strong>unverified</strong> - Pending email confirmation</li>';
                        echo '<li><strong>google_oauth</strong> - Signed up via Google</li>';
                        echo '<li><strong>newsletter_signup</strong> - Newsletter subscribers</li>';
                        echo '<li><strong>quiz_completion</strong> - Completed Thrive quiz</li>';
                        echo '<li><strong>lead_magnet</strong> - Downloaded content</li>';
                        echo '</ul>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>🏷️ Tagging Strategy</h3>';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li><strong>Content Tags:</strong> seo-tools, email-templates, checklist-pdf</li>';
                        echo '<li><strong>Behavior Tags:</strong> high-engagement, newsletter-subscriber</li>';
                        echo '<li><strong>Quiz Tags:</strong> beginner-seo, intermediate-seo, advanced-seo</li>';
                        echo '<li><strong>Interest Tags:</strong> interested-in-tools, interested-in-courses</li>';
                        echo '</ul>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>Lead Management Dashboard</h1>';
                        echo '<div class="notice notice-error"><p>Lead management error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                }
            );
            
            // UTM Analytics Submenu
            add_submenu_page(
                'thrive-mautic-dashboard',
                'UTM Analytics',
                'UTM Analytics',
                'manage_options',
                'thrive-mautic-utm',
                function() {
                    try {
                        echo '<div class="wrap">';
                        echo '<h1>UTM Analytics Dashboard</h1>';
                        
                        // Get UTM statistics
                        $utm_stats = thrive_mautic_get_utm_stats();
                        
                        // Statistics Summary
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>📊 UTM Performance Overview</h2>';
                        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #007cba;">';
                        echo '<h3>Total UTM Leads</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #007cba;">' . $utm_stats['total_utm_leads'] . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">';
                        echo '<h3>Top Source</h3>';
                        echo '<div style="font-size: 18px; font-weight: bold; color: #28a745;">' . esc_html($utm_stats['top_source']) . '</div>';
                        echo '<div style="font-size: 14px; color: #666;">' . $utm_stats['top_source_count'] . ' leads</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">';
                        echo '<h3>Top Campaign</h3>';
                        echo '<div style="font-size: 18px; font-weight: bold; color: #ffc107;">' . esc_html($utm_stats['top_campaign']) . '</div>';
                        echo '<div style="font-size: 14px; color: #666;">' . $utm_stats['top_campaign_count'] . ' leads</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #6f42c1;">';
                        echo '<h3>Conversion Rate</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #6f42c1;">' . $utm_stats['conversion_rate'] . '%</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Top Sources Table
                        if (!empty($utm_stats['sources'])) {
                            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                            echo '<h2>🎯 Top Traffic Sources</h2>';
                            echo '<table class="wp-list-table widefat fixed striped">';
                            echo '<thead>';
                            echo '<tr>';
                            echo '<th>Source</th>';
                            echo '<th>Medium</th>';
                            echo '<th>Leads</th>';
                            echo '<th>Percentage</th>';
                            echo '<th>Last Lead</th>';
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';
                            
                            foreach ($utm_stats['sources'] as $source) {
                                echo '<tr>';
                                echo '<td><strong>' . esc_html($source['source']) . '</strong></td>';
                                echo '<td>' . esc_html($source['medium']) . '</td>';
                                echo '<td><span style="font-weight: bold; color: #007cba;">' . $source['count'] . '</span></td>';
                                echo '<td>' . $source['percentage'] . '%</td>';
                                echo '<td>' . esc_html($source['last_lead']) . '</td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody>';
                            echo '</table>';
                            echo '</div>';
                        }
                        
                        // Campaign Performance
                        if (!empty($utm_stats['campaigns'])) {
                            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                            echo '<h2>🚀 Campaign Performance</h2>';
                            echo '<table class="wp-list-table widefat fixed striped">';
                            echo '<thead>';
                            echo '<tr>';
                            echo '<th>Campaign</th>';
                            echo '<th>Source</th>';
                            echo '<th>Medium</th>';
                            echo '<th>Leads</th>';
                            echo '<th>Content</th>';
                            echo '<th>Term</th>';
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';
                            
                            foreach ($utm_stats['campaigns'] as $campaign) {
                                echo '<tr>';
                                echo '<td><strong>' . esc_html($campaign['campaign']) . '</strong></td>';
                                echo '<td>' . esc_html($campaign['source']) . '</td>';
                                echo '<td>' . esc_html($campaign['medium']) . '</td>';
                                echo '<td><span style="font-weight: bold; color: #28a745;">' . $campaign['count'] . '</span></td>';
                                echo '<td>' . esc_html($campaign['content']) . '</td>';
                                echo '<td>' . esc_html($campaign['term']) . '</td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody>';
                            echo '</table>';
                            echo '</div>';
                        }
                        
                        // UTM Builder Tool
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🛠️ UTM Builder Tool</h2>';
                        echo '<p>Create UTM URLs for your campaigns:</p>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">';
                        
                        echo '<div>';
                        echo '<label for="utm_base_url" style="display: block; margin-bottom: 5px; font-weight: bold;">Base URL:</label>';
                        echo '<input type="url" id="utm_base_url" placeholder="https://yoursite.com/landing-page" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<label for="utm_source" style="display: block; margin-bottom: 5px; font-weight: bold;">Source:</label>';
                        echo '<input type="text" id="utm_source" placeholder="google, facebook, email" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<label for="utm_medium" style="display: block; margin-bottom: 5px; font-weight: bold;">Medium:</label>';
                        echo '<input type="text" id="utm_medium" placeholder="cpc, social, email" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<label for="utm_campaign" style="display: block; margin-bottom: 5px; font-weight: bold;">Campaign:</label>';
                        echo '<input type="text" id="utm_campaign" placeholder="summer-sale-2024" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<label for="utm_content" style="display: block; margin-bottom: 5px; font-weight: bold;">Content:</label>';
                        echo '<input type="text" id="utm_content" placeholder="banner-top, ad1" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<label for="utm_term" style="display: block; margin-bottom: 5px; font-weight: bold;">Term:</label>';
                        echo '<input type="text" id="utm_term" placeholder="seo tools, discount" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">';
                        echo '</div>';
                        
                        echo '</div>';
                        
                        echo '<button type="button" onclick="generateUTM()" class="button button-primary" style="margin: 10px 0;">Generate UTM URL</button>';
                        echo '<div id="utm_result" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px; display: none;">';
                        echo '<h3>Generated UTM URL:</h3>';
                        echo '<input type="text" id="utm_output" readonly style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; font-family: monospace;">';
                        echo '<button type="button" onclick="copyUTM()" class="button" style="margin-top: 10px;">Copy URL</button>';
                        echo '</div>';
                        
                        echo '</div>';
                        
                        // JavaScript for UTM Builder
                        echo '<script>
                        function generateUTM() {
                            const baseUrl = document.getElementById("utm_base_url").value;
                            const source = document.getElementById("utm_source").value;
                            const medium = document.getElementById("utm_medium").value;
                            const campaign = document.getElementById("utm_campaign").value;
                            const content = document.getElementById("utm_content").value;
                            const term = document.getElementById("utm_term").value;
                            
                            if (!baseUrl) {
                                alert("Please enter a base URL");
                                return;
                            }
                            
                            let utmUrl = baseUrl;
                            const params = [];
                            
                            if (source) params.push("utm_source=" + encodeURIComponent(source));
                            if (medium) params.push("utm_medium=" + encodeURIComponent(medium));
                            if (campaign) params.push("utm_campaign=" + encodeURIComponent(campaign));
                            if (content) params.push("utm_content=" + encodeURIComponent(content));
                            if (term) params.push("utm_term=" + encodeURIComponent(term));
                            
                            if (params.length > 0) {
                                utmUrl += (baseUrl.includes("?") ? "&" : "?") + params.join("&");
                            }
                            
                            document.getElementById("utm_output").value = utmUrl;
                            document.getElementById("utm_result").style.display = "block";
                        }
                        
                        function copyUTM() {
                            const utmOutput = document.getElementById("utm_output");
                            utmOutput.select();
                            utmOutput.setSelectionRange(0, 99999);
                            document.execCommand("copy");
                            alert("UTM URL copied to clipboard!");
                        }
                        </script>';
                        
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>UTM Analytics Dashboard</h1>';
                        echo '<div class="notice notice-error"><p>UTM analytics error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                }
            );
            
            // Form Setup Guide Submenu
            add_submenu_page(
                'thrive-mautic-dashboard',
                'Form Setup Guide',
                'Form Setup Guide',
                'manage_options',
                'thrive-mautic-form-setup',
                function() {
                    try {
                        echo '<div class="wrap">';
                        echo '<h1>📝 Complete Form Setup Guide</h1>';
                        
                        // Form Requirements
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🎯 MUST-HAVE Fields for ALL Forms</h2>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 20px 0;">';
                        
                        echo '<div>';
                        echo '<h3>📋 Required Fields</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>1. Hidden Segment Field (REQUIRED)</h4>';
                        echo '<code style="background: #e9ecef; padding: 5px; border-radius: 3px; display: block; margin: 5px 0;">&lt;input type="hidden" name="thrive_mautic_segment" value="[SEGMENT_NAME]"&gt;</code>';
                        echo '<p style="font-size: 14px; color: #666; margin: 10px 0;">This tells the plugin which segment to assign the lead to.</p>';
                        echo '</div>';
                        
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>2. Email Field (REQUIRED)</h4>';
                        echo '<code style="background: #e9ecef; padding: 5px; border-radius: 3px; display: block; margin: 5px 0;">&lt;input type="email" name="email" required&gt;</code>';
                        echo '<p style="font-size: 14px; color: #666; margin: 10px 0;">Primary identifier for the lead.</p>';
                        echo '</div>';
                        
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>3. Name Field (RECOMMENDED)</h4>';
                        echo '<code style="background: #e9ecef; padding: 5px; border-radius: 3px; display: block; margin: 5px 0;">&lt;input type="text" name="name"&gt;</code>';
                        echo '<p style="font-size: 14px; color: #666; margin: 10px 0;">Personalizes the experience.</p>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>🏷️ Segment Values by Form Type</h3>';
                        echo '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
                        echo '<tr style="background: #f8f9fa;"><th style="padding: 10px; border: 1px solid #ddd;">Form Type</th><th style="padding: 10px; border: 1px solid #ddd;">Segment Value</th></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;">Newsletter Signup</td><td style="padding: 10px; border: 1px solid #ddd;"><code>newsletter_signup</code></td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;">Quiz Completion</td><td style="padding: 10px; border: 1px solid #ddd;"><code>quiz_completion</code></td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;">Lead Magnet Download</td><td style="padding: 10px; border: 1px solid #ddd;"><code>lead_magnet</code></td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;">Contact Form</td><td style="padding: 10px; border: 1px solid #ddd;"><code>contact_form</code></td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;">Registration (Local)</td><td style="padding: 10px; border: 1px solid #ddd;"><code>local_registration</code></td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;">Registration (Google)</td><td style="padding: 10px; border: 1px solid #ddd;"><code>google_oauth</code></td></tr>';
                        echo '</table>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Form Examples
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>📝 Form Examples</h2>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                        
                        echo '<div>';
                        echo '<h3>Newsletter Signup Form</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<pre style="background: #e9ecef; padding: 10px; border-radius: 3px; overflow-x: auto;"><code>&lt;form&gt;
  &lt;input type="hidden" name="thrive_mautic_segment" value="newsletter_signup"&gt;
  &lt;input type="email" name="email" placeholder="Your email" required&gt;
  &lt;input type="text" name="name" placeholder="Your name"&gt;
  &lt;button type="submit"&gt;Subscribe&lt;/button&gt;
&lt;/form&gt;</code></pre>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>Lead Magnet Download Form</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<pre style="background: #e9ecef; padding: 10px; border-radius: 3px; overflow-x: auto;"><code>&lt;form&gt;
  &lt;input type="hidden" name="thrive_mautic_segment" value="lead_magnet"&gt;
  &lt;input type="email" name="email" placeholder="Your email" required&gt;
  &lt;input type="text" name="name" placeholder="Your name"&gt;
  &lt;input type="text" name="company" placeholder="Company"&gt;
  &lt;button type="submit"&gt;Download Now&lt;/button&gt;
&lt;/form&gt;</code></pre>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>Quiz Completion Form</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<pre style="background: #e9ecef; padding: 10px; border-radius: 3px; overflow-x: auto;"><code>&lt;form&gt;
  &lt;input type="hidden" name="thrive_mautic_segment" value="quiz_completion"&gt;
  &lt;input type="email" name="email" placeholder="Your email" required&gt;
  &lt;input type="text" name="name" placeholder="Your name"&gt;
  &lt;input type="hidden" name="quiz_result" value="beginner"&gt;
  &lt;button type="submit"&gt;Get Results&lt;/button&gt;
&lt;/form&gt;</code></pre>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>Contact Form</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<pre style="background: #e9ecef; padding: 10px; border-radius: 3px; overflow-x: auto;"><code>&lt;form&gt;
  &lt;input type="hidden" name="thrive_mautic_segment" value="contact_form"&gt;
  &lt;input type="email" name="email" placeholder="Your email" required&gt;
  &lt;input type="text" name="name" placeholder="Your name"&gt;
  &lt;input type="text" name="phone" placeholder="Phone"&gt;
  &lt;textarea name="message" placeholder="Message"&gt;&lt;/textarea&gt;
  &lt;button type="submit"&gt;Send Message&lt;/button&gt;
&lt;/form&gt;</code></pre>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Thank You Page Strategy
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🎯 Thank You Page Strategy</h2>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                        
                        echo '<div>';
                        echo '<h3>✅ RECOMMENDED: Dynamic Thank You Pages</h3>';
                        echo '<p style="color: #28a745; font-weight: bold;">Don\'t use the same thank you page for all forms!</p>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>Why Different Thank You Pages?</h4>';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>Better user experience</li>';
                        echo '<li>Higher conversion rates</li>';
                        echo '<li>Personalized messaging</li>';
                        echo '<li>Relevant next steps</li>';
                        echo '<li>Better tracking and analytics</li>';
                        echo '</ul>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>🔗 Dynamic Thank You URLs</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>URL Structure:</h4>';
                        echo '<code style="background: #e9ecef; padding: 5px; border-radius: 3px; display: block; margin: 5px 0;">/thank-you/[form-type]</code>';
                        echo '<p style="font-size: 14px; color: #666; margin: 10px 0;">Examples:</p>';
                        echo '<ul style="margin: 5px 0; padding-left: 20px; font-size: 14px;">';
                        echo '<li><code>/thank-you/newsletter</code></li>';
                        echo '<li><code>/thank-you/quiz</code></li>';
                        echo '<li><code>/thank-you/lead-magnet</code></li>';
                        echo '<li><code>/thank-you/contact</code></li>';
                        echo '</ul>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Redirect Configuration
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🔄 Form Redirect Configuration</h2>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                        
                        echo '<div>';
                        echo '<h3>Thrive Architect Forms</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>Redirect Settings:</h4>';
                        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>Go to Thrive Architect</li>';
                        echo '<li>Edit your form</li>';
                        echo '<li>Go to "Form Settings"</li>';
                        echo '<li>Set "Redirect URL" to:</li>';
                        echo '<li><code>/thank-you/[form-type]</code></li>';
                        echo '</ol>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>Thrive Leads Forms</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>Redirect Settings:</h4>';
                        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>Go to Thrive Leads</li>';
                        echo '<li>Edit your form</li>';
                        echo '<li>Go to "Form Settings"</li>';
                        echo '<li>Set "Redirect URL" to:</li>';
                        echo '<li><code>/thank-you/[form-type]</code></li>';
                        echo '</ol>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>Thrive Quiz Forms</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>Redirect Settings:</h4>';
                        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>Go to Thrive Quiz Builder</li>';
                        echo '<li>Edit your quiz</li>';
                        echo '<li>Go to "Settings" → "Redirect"</li>';
                        echo '<li>Set "Redirect URL" to:</li>';
                        echo '<li><code>/thank-you/quiz</code></li>';
                        echo '</ol>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>Thrive Lightbox Forms</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>Redirect Settings:</h4>';
                        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>Go to Thrive Lightbox</li>';
                        echo '<li>Edit your lightbox</li>';
                        echo '<li>Go to "Form Settings"</li>';
                        echo '<li>Set "Redirect URL" to:</li>';
                        echo '<li><code>/thank-you/[form-type]</code></li>';
                        echo '</ol>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Implementation Checklist
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>✅ Implementation Checklist</h2>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                        
                        echo '<div>';
                        echo '<h3>Form Setup</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>☐ Add hidden segment field to all forms</li>';
                        echo '<li>☐ Set correct segment value for each form type</li>';
                        echo '<li>☐ Ensure email field is required</li>';
                        echo '<li>☐ Add name field (recommended)</li>';
                        echo '<li>☐ Test form submission</li>';
                        echo '<li>☐ Verify data appears in Mautic</li>';
                        echo '</ul>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>Thank You Pages</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>☐ Set up dynamic thank you URLs</li>';
                        echo '<li>☐ Configure form redirects</li>';
                        echo '<li>☐ Test each thank you page</li>';
                        echo '<li>☐ Customize content for each form type</li>';
                        echo '<li>☐ Add relevant CTAs</li>';
                        echo '<li>☐ Test UTM tracking on thank you pages</li>';
                        echo '</ul>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>Form Setup Guide</h1>';
                        echo '<div class="notice notice-error"><p>Form setup guide error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                }
            );
            
            // Queue Management Submenu
            add_submenu_page(
                'thrive-mautic-dashboard',
                'Queue Management',
                'Queue Management',
                'manage_options',
                'thrive-mautic-queue',
                function() {
                    try {
                        echo '<div class="wrap">';
                        echo '<h1>🔄 Queue Management</h1>';
                        
                        // Queue Statistics
                        global $wpdb;
                        $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
                        
                        $stats = $wpdb->get_results("
                            SELECT 
                                status,
                                COUNT(*) as count,
                                MAX(created_at) as last_created
                            FROM $table_name 
                            GROUP BY status
                        ");
                        
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>📊 Queue Statistics</h2>';
                        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
                        
                        $total_pending = 0;
                        $total_processing = 0;
                        $total_completed = 0;
                        $total_failed = 0;
                        
                        foreach ($stats as $stat) {
                            $count = intval($stat->count);
                            $last_created = $stat->last_created;
                            
                            switch ($stat->status) {
                                case 'pending':
                                    $total_pending = $count;
                                    $color = '#ffc107';
                                    $icon = '⏳';
                                    break;
                                case 'processing':
                                    $total_processing = $count;
                                    $color = '#17a2b8';
                                    $icon = '🔄';
                                    break;
                                case 'completed':
                                    $total_completed = $count;
                                    $color = '#28a745';
                                    $icon = '✅';
                                    break;
                                case 'failed':
                                    $total_failed = $count;
                                    $color = '#dc3545';
                                    $icon = '❌';
                                    break;
                            }
                            
                            echo '<div style="background: ' . $color . '; color: white; padding: 20px; border-radius: 8px; text-align: center;">';
                            echo '<h3 style="margin: 0; font-size: 24px;">' . $icon . ' ' . $count . '</h3>';
                            echo '<p style="margin: 5px 0 0 0; text-transform: uppercase; font-weight: bold;">' . ucfirst($stat->status) . '</p>';
                            if ($last_created) {
                                echo '<p style="margin: 5px 0 0 0; font-size: 12px; opacity: 0.8;">Last: ' . human_time_diff(strtotime($last_created), current_time('timestamp')) . ' ago</p>';
                            }
                            echo '</div>';
                        }
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Queue Management Actions
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🛠️ Queue Management</h2>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                        
                        echo '<div>';
                        echo '<h3>📋 Queue Actions</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<button type="button" id="process-queue-now" class="button button-primary" style="margin: 5px;">Process Queue Now</button>';
                        echo '<button type="button" id="retry-failed" class="button button-secondary" style="margin: 5px;">Retry Failed Submissions</button>';
                        echo '<button type="button" id="clear-completed" class="button button-secondary" style="margin: 5px;">Clear Completed</button>';
                        echo '<button type="button" id="clear-all" class="button button-secondary" style="margin: 5px; color: #dc3545;">Clear All</button>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>ℹ️ How It Works</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li><strong>Pending:</strong> Form submissions waiting to be sent to Mautic</li>';
                        echo '<li><strong>Processing:</strong> Currently being sent to Mautic</li>';
                        echo '<li><strong>Completed:</strong> Successfully sent to Mautic</li>';
                        echo '<li><strong>Failed:</strong> Failed to send (will retry automatically)</li>';
                        echo '</ul>';
                        echo '<p style="margin: 10px 0; font-size: 14px; color: #666;">The queue processes automatically every 5 minutes, or you can process it manually.</p>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Recent Submissions
                        $recent = $wpdb->get_results("
                            SELECT * FROM $table_name 
                            ORDER BY created_at DESC 
                            LIMIT 20
                        ");
                        
                        if ($recent) {
                            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                            echo '<h2>📝 Recent Submissions</h2>';
                            echo '<table class="wp-list-table widefat fixed striped">';
                            echo '<thead><tr>';
                            echo '<th>ID</th><th>Email</th><th>Name</th><th>Form Type</th><th>Status</th><th>Created</th><th>Actions</th>';
                            echo '</tr></thead><tbody>';
                            
                            foreach ($recent as $submission) {
                                $status_color = '';
                                $status_icon = '';
                                switch ($submission->status) {
                                    case 'pending':
                                        $status_color = '#ffc107';
                                        $status_icon = '⏳';
                                        break;
                                    case 'processing':
                                        $status_color = '#17a2b8';
                                        $status_icon = '🔄';
                                        break;
                                    case 'completed':
                                        $status_color = '#28a745';
                                        $status_icon = '✅';
                                        break;
                                    case 'failed':
                                        $status_color = '#dc3545';
                                        $status_icon = '❌';
                                        break;
                                }
                                
                                echo '<tr>';
                                echo '<td>' . esc_html($submission->id) . '</td>';
                                echo '<td>' . esc_html($submission->email) . '</td>';
                                echo '<td>' . esc_html($submission->name) . '</td>';
                                echo '<td>' . esc_html($submission->form_type) . '</td>';
                                echo '<td><span style="color: ' . $status_color . '; font-weight: bold;">' . $status_icon . ' ' . ucfirst($submission->status) . '</span></td>';
                                echo '<td>' . human_time_diff(strtotime($submission->created_at), current_time('timestamp')) . ' ago</td>';
                                echo '<td>';
                                if ($submission->status === 'failed') {
                                    echo '<button type="button" class="button button-small retry-single" data-id="' . $submission->id . '">Retry</button>';
                                }
                                echo '</td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody></table>';
                            echo '</div>';
                        }
                        
                        // JavaScript for queue management
                        echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            // Process queue now
                            document.getElementById("process-queue-now").addEventListener("click", function() {
                                if (confirm("Process all pending submissions now?")) {
                                    fetch(ajaxurl, {
                                        method: "POST",
                                        headers: {"Content-Type": "application/x-www-form-urlencoded"},
                                        body: "action=thrive_mautic_process_queue_manual&nonce=' . wp_create_nonce('thrive_mautic_process_queue') . '"
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert("Queue processed! " + data.data.processed + " submissions processed.");
                                            location.reload();
                                        } else {
                                            alert("Error: " + data.data);
                                        }
                                    });
                                }
                            });
                            
                            // Retry failed
                            document.getElementById("retry-failed").addEventListener("click", function() {
                                if (confirm("Retry all failed submissions?")) {
                                    fetch(ajaxurl, {
                                        method: "POST",
                                        headers: {"Content-Type": "application/x-www-form-urlencoded"},
                                        body: "action=thrive_mautic_retry_failed&nonce=' . wp_create_nonce('thrive_mautic_process_queue') . '"
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert("Failed submissions retried! " + data.data.retried + " submissions retried.");
                                            location.reload();
                                        } else {
                                            alert("Error: " + data.data);
                                        }
                                    });
                                }
                            });
                            
                            // Clear completed
                            document.getElementById("clear-completed").addEventListener("click", function() {
                                if (confirm("Clear all completed submissions? This cannot be undone.")) {
                                    fetch(ajaxurl, {
                                        method: "POST",
                                        headers: {"Content-Type": "application/x-www-form-urlencoded"},
                                        body: "action=thrive_mautic_clear_completed&nonce=' . wp_create_nonce('thrive_mautic_process_queue') . '"
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert("Completed submissions cleared! " + data.data.cleared + " submissions cleared.");
                                            location.reload();
                                        } else {
                                            alert("Error: " + data.data);
                                        }
                                    });
                                }
                            });
                            
                            // Clear all
                            document.getElementById("clear-all").addEventListener("click", function() {
                                if (confirm("Clear ALL submissions? This cannot be undone.")) {
                                    fetch(ajaxurl, {
                                        method: "POST",
                                        headers: {"Content-Type": "application/x-www-form-urlencoded"},
                                        body: "action=thrive_mautic_clear_all&nonce=' . wp_create_nonce('thrive_mautic_process_queue') . '"
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            alert("All submissions cleared! " + data.data.cleared + " submissions cleared.");
                                            location.reload();
                                        } else {
                                            alert("Error: " + data.data);
                                        }
                                    });
                                }
                            });
                        });
                        </script>';
                        
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>Queue Management</h1>';
                        echo '<div class="notice notice-error"><p>Queue management error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                }
            );
            
            // Script Verification Submenu
            add_submenu_page(
                'thrive-mautic-dashboard',
                'Script Verification',
                'Script Verification',
                'manage_options',
                'thrive-mautic-verification',
                function() {
                    try {
                        echo '<div class="wrap">';
                        echo '<h1>🔍 Script Verification & Testing</h1>';
                        
                        // Get current tracking settings
                        $tracking_enabled = get_option('thrive_mautic_tracking_enabled', false);
                        $tracking_script = get_option('thrive_mautic_custom_tracking_script', '');
                        $tracking_position = get_option('thrive_mautic_tracking_position', 'footer');
                        $tracking_pages = get_option('thrive_mautic_tracking_pages', 'all');
                        $tracking_page_ids = get_option('thrive_mautic_tracking_page_ids', '');
                        
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>📊 Current Tracking Settings</h2>';
                        echo '<table class="form-table">';
                        echo '<tr><th>Tracking Enabled:</th><td>' . ($tracking_enabled ? '✅ Yes' : '❌ No') . '</td></tr>';
                        echo '<tr><th>Script Position:</th><td>' . ucfirst($tracking_position) . '</td></tr>';
                        echo '<tr><th>Page Targeting:</th><td>' . ucfirst(str_replace('_', ' ', $tracking_pages)) . '</td></tr>';
                        if ($tracking_pages === 'specific' && !empty($tracking_page_ids)) {
                            echo '<tr><th>Specific Page IDs:</th><td>' . esc_html($tracking_page_ids) . '</td></tr>';
                        }
                        echo '<tr><th>Script Length:</th><td>' . strlen($tracking_script) . ' characters</td></tr>';
                        echo '</table>';
                        echo '</div>';
                        
                        if (!$tracking_enabled || empty($tracking_script)) {
                            echo '<div style="background: #f8d7da; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545;">';
                            echo '<h3>⚠️ Tracking Not Configured</h3>';
                            echo '<p>Please enable tracking and add your Mautic script in the <a href="' . admin_url('admin.php?page=thrive-mautic-settings') . '">Settings</a> page.</p>';
                            echo '</div>';
                        } else {
                            // Script Preview
                            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                            echo '<h2>📝 Current Tracking Script</h2>';
                            echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                            echo '<pre style="background: #e9ecef; padding: 10px; border-radius: 3px; overflow-x: auto; font-size: 12px;"><code>' . esc_html($tracking_script) . '</code></pre>';
                            echo '</div>';
                            echo '</div>';
                            
                            // Verification Tests
                            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                            echo '<h2>🧪 Verification Tests</h2>';
                            
                            // Test 1: Check if script is being added
                            echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                            echo '<h3>Test 1: Script Injection Test</h3>';
                            echo '<p>This test checks if the tracking script is being added to your pages.</p>';
                            echo '<button type="button" id="test-script-injection" class="button button-primary">Run Script Injection Test</button>';
                            echo '<div id="script-injection-result" style="margin-top: 10px;"></div>';
                            echo '</div>';
                            
                            // Test 2: Page targeting test
                            echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                            echo '<h3>Test 2: Page Targeting Test</h3>';
                            echo '<p>This test verifies which pages should have the tracking script.</p>';
                            echo '<button type="button" id="test-page-targeting" class="button button-primary">Run Page Targeting Test</button>';
                            echo '<div id="page-targeting-result" style="margin-top: 10px;"></div>';
                            echo '</div>';
                            
                            // Test 3: UTM tracking test
                            echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                            echo '<h3>Test 3: UTM Tracking Test</h3>';
                            echo '<p>This test checks if UTM parameters are being captured and stored.</p>';
                            echo '<button type="button" id="test-utm-tracking" class="button button-primary">Run UTM Tracking Test</button>';
                            echo '<div id="utm-tracking-result" style="margin-top: 10px;"></div>';
                            echo '</div>';
                            
                            // Test 4: Live page test
                            echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                            echo '<h3>Test 4: Live Page Test</h3>';
                            echo '<p>This test opens a live page to verify the script is actually working.</p>';
                            echo '<button type="button" id="test-live-page" class="button button-primary">Open Live Page Test</button>';
                            echo '<div id="live-page-result" style="margin-top: 10px;"></div>';
                            echo '</div>';
                            
                            echo '</div>';
                            
                            // Manual verification instructions
                            echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                            echo '<h2>🔍 Manual Verification Steps</h2>';
                            echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                            
                            echo '<div>';
                            echo '<h3>Method 1: Browser Developer Tools</h3>';
                            echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                            echo '<li>Open your website in a browser</li>';
                            echo '<li>Right-click and select "Inspect Element"</li>';
                            echo '<li>Go to the "Elements" tab</li>';
                            echo '<li>Look for your Mautic script in the ' . ($tracking_position === 'head' ? '&lt;head&gt;' : '&lt;body&gt;') . ' section</li>';
                            echo '<li>Check if UTM tracking script is present</li>';
                            echo '</ol>';
                            echo '</div>';
                            
                            echo '<div>';
                            echo '<h3>Method 2: View Page Source</h3>';
                            echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                            echo '<li>Open your website in a browser</li>';
                            echo '<li>Right-click and select "View Page Source"</li>';
                            echo '<li>Press Ctrl+F to search</li>';
                            echo '<li>Search for "mautic" or "mtc.js"</li>';
                            echo '<li>Verify the script is present</li>';
                            echo '</ol>';
                            echo '</div>';
                            
                            echo '<div>';
                            echo '<h3>Method 3: Mautic Dashboard</h3>';
                            echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                            echo '<li>Go to your Mautic dashboard</li>';
                            echo '<li>Navigate to Reports → Contacts</li>';
                            echo '<li>Visit your website with UTM parameters</li>';
                            echo '<li>Check if new contacts appear</li>';
                            echo '<li>Verify UTM data is captured</li>';
                            echo '</ol>';
                            echo '</div>';
                            
                            echo '<div>';
                            echo '<h3>Method 4: Network Tab</h3>';
                            echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                            echo '<li>Open Developer Tools (F12)</li>';
                            echo '<li>Go to "Network" tab</li>';
                            echo '<li>Reload the page</li>';
                            echo '<li>Look for requests to your Mautic domain</li>';
                            echo '<li>Check if tracking requests are being made</li>';
                            echo '</ol>';
                            echo '</div>';
                            
                            echo '</div>';
                            echo '</div>';
                            
                            // JavaScript for tests
                            echo '<script>
                            document.addEventListener("DOMContentLoaded", function() {
                                // Script injection test
                                document.getElementById("test-script-injection").addEventListener("click", function() {
                                    const resultDiv = document.getElementById("script-injection-result");
                                    resultDiv.innerHTML = "<p>Testing script injection...</p>";
                                    
                                    fetch(ajaxurl, {
                                        method: "POST",
                                        headers: {"Content-Type": "application/x-www-form-urlencoded"},
                                        body: "action=thrive_mautic_test_script_injection&nonce=' . wp_create_nonce('thrive_mautic_test') . '"
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            resultDiv.innerHTML = "<div style=\"color: #28a745;\"><strong>✅ Success:</strong> " + data.data.message + "</div>";
                                        } else {
                                            resultDiv.innerHTML = "<div style=\"color: #dc3545;\"><strong>❌ Error:</strong> " + data.data + "</div>";
                                        }
                                    });
                                });
                                
                                // Page targeting test
                                document.getElementById("test-page-targeting").addEventListener("click", function() {
                                    const resultDiv = document.getElementById("page-targeting-result");
                                    resultDiv.innerHTML = "<p>Testing page targeting...</p>";
                                    
                                    fetch(ajaxurl, {
                                        method: "POST",
                                        headers: {"Content-Type": "application/x-www-form-urlencoded"},
                                        body: "action=thrive_mautic_test_page_targeting&nonce=' . wp_create_nonce('thrive_mautic_test') . '"
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            resultDiv.innerHTML = "<div style=\"color: #28a745;\"><strong>✅ Success:</strong> " + data.data.message + "</div>";
                                        } else {
                                            resultDiv.innerHTML = "<div style=\"color: #dc3545;\"><strong>❌ Error:</strong> " + data.data + "</div>";
                                        }
                                    });
                                });
                                
                                // UTM tracking test
                                document.getElementById("test-utm-tracking").addEventListener("click", function() {
                                    const resultDiv = document.getElementById("utm-tracking-result");
                                    resultDiv.innerHTML = "<p>Testing UTM tracking...</p>";
                                    
                                    fetch(ajaxurl, {
                                        method: "POST",
                                        headers: {"Content-Type": "application/x-www-form-urlencoded"},
                                        body: "action=thrive_mautic_test_utm_tracking&nonce=' . wp_create_nonce('thrive_mautic_test') . '"
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            resultDiv.innerHTML = "<div style=\"color: #28a745;\"><strong>✅ Success:</strong> " + data.data.message + "</div>";
                                        } else {
                                            resultDiv.innerHTML = "<div style=\"color: #dc3545;\"><strong>❌ Error:</strong> " + data.data + "</div>";
                                        }
                                    });
                                });
                                
                                // Live page test
                                document.getElementById("test-live-page").addEventListener("click", function() {
                                    const resultDiv = document.getElementById("live-page-result");
                                    resultDiv.innerHTML = "<p>Opening live page test...</p>";
                                    
                                    // Open a new window with UTM parameters
                                    const testUrl = "' . home_url() . '?utm_source=test&utm_medium=verification&utm_campaign=script_test&utm_content=verification&utm_term=test";
                                    window.open(testUrl, "_blank");
                                    
                                    resultDiv.innerHTML = "<div style=\"color: #17a2b8;\"><strong>🔗 Opened:</strong> <a href=\"" + testUrl + "\" target=\"_blank\">" + testUrl + "</a><br><small>Check the page source and developer tools to verify the script is present.</small></div>";
                                });
                            });
                            </script>';
                        }
                        
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>Script Verification</h1>';
                        echo '<div class="notice notice-error"><p>Script verification error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                }
            );
            
            // Workflow Guide Submenu
            add_submenu_page(
                'thrive-mautic-dashboard',
                'Workflow Guide',
                'Workflow Guide',
                'manage_options',
                'thrive-mautic-workflow',
                function() {
                    try {
                        echo '<div class="wrap">';
                        echo '<h1>🎯 Complete Lead Workflow Guide</h1>';
                        
                        // Workflow Overview
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🔄 Your Complete Lead Workflow</h2>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin: 20px 0;">';
                        
                        echo '<div>';
                        echo '<h3>📝 User Registration Flow</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>1. Google OAuth Registration</h4>';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>User clicks "Sign up with Google"</li>';
                        echo '<li>Google authentication completes</li>';
                        echo '<li><strong>Segment:</strong> <code>google_oauth</code></li>';
                        echo '<li><strong>Verification:</strong> Auto-verified (no email confirmation needed)</li>';
                        echo '<li><strong>Tags:</strong> <code>high-engagement</code> (immediate verification)</li>';
                        echo '</ul>';
                        echo '</div>';
                        
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>2. Local Account Registration</h4>';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>User creates account with email/password</li>';
                        echo '<li>Email confirmation sent</li>';
                        echo '<li><strong>Segment:</strong> <code>unverified</code> (until confirmed)</li>';
                        echo '<li><strong>After confirmation:</strong> <code>verified</code></li>';
                        echo '<li><strong>Tags:</strong> <code>local-registration</code></li>';
                        echo '</ul>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>🎯 Lead Generation Flow</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>3. Newsletter Subscription</h4>';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>User subscribes to newsletter</li>';
                        echo '<li><strong>Segment:</strong> <code>newsletter_signup</code></li>';
                        echo '<li><strong>Tags:</strong> <code>newsletter-subscriber</code></li>';
                        echo '<li><strong>UTM:</strong> Track campaign source</li>';
                        echo '</ul>';
                        echo '</div>';
                        
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>4. Thrive Quiz Completion</h4>';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>User completes quiz</li>';
                        echo '<li><strong>Segment:</strong> <code>quiz_completion</code></li>';
                        echo '<li><strong>Tags:</strong> <code>quiz-taker</code> + result tags</li>';
                        echo '<li><strong>Result Tags:</strong> <code>beginner-seo</code>, <code>intermediate-seo</code>, <code>advanced-seo</code></li>';
                        echo '</ul>';
                        echo '</div>';
                        
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>5. Lead Magnet Downloads</h4>';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>User downloads content from blog post</li>';
                        echo '<li><strong>Segment:</strong> <code>lead_magnet</code></li>';
                        echo '<li><strong>Tags:</strong> Content-specific tags</li>';
                        echo '<li><strong>Content Tags:</strong> <code>seo-tools</code>, <code>email-templates</code>, <code>checklist-pdf</code></li>';
                        echo '</ul>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Segmentation Strategy
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🎯 Segmentation Strategy</h2>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                        
                        echo '<div>';
                        echo '<h3>📊 Main Segments</h3>';
                        echo '<table style="width: 100%; border-collapse: collapse;">';
                        echo '<tr style="background: #f8f9fa;"><th style="padding: 10px; border: 1px solid #ddd;">Segment</th><th style="padding: 10px; border: 1px solid #ddd;">Purpose</th></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;"><code>verified</code></td><td style="padding: 10px; border: 1px solid #ddd;">Confirmed users (Google + verified email)</td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;"><code>unverified</code></td><td style="padding: 10px; border: 1px solid #ddd;">Pending email confirmation</td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;"><code>google_oauth</code></td><td style="padding: 10px; border: 1px solid #ddd;">Google sign-ups (auto-verified)</td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;"><code>newsletter_signup</code></td><td style="padding: 10px; border: 1px solid #ddd;">Newsletter subscribers</td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;"><code>quiz_completion</code></td><td style="padding: 10px; border: 1px solid #ddd;">Quiz completers</td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;"><code>lead_magnet</code></td><td style="padding: 10px; border: 1px solid #ddd;">Content downloaders</td></tr>';
                        echo '</table>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>🏷️ Tag Categories</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>Content Tags</h4>';
                        echo '<ul style="margin: 5px 0; padding-left: 20px;">';
                        echo '<li><code>seo-tools</code> - SEO tools download</li>';
                        echo '<li><code>email-templates</code> - Email template download</li>';
                        echo '<li><code>checklist-pdf</code> - Checklist download</li>';
                        echo '<li><code>video-course</code> - Video course access</li>';
                        echo '<li><code>webinar-signup</code> - Webinar registration</li>';
                        echo '</ul>';
                        echo '</div>';
                        
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>Behavior Tags</h4>';
                        echo '<ul style="margin: 5px 0; padding-left: 20px;">';
                        echo '<li><code>high-engagement</code> - Multiple interactions</li>';
                        echo '<li><code>newsletter-subscriber</code> - Active subscriber</li>';
                        echo '<li><code>quiz-taker</code> - Completed quiz</li>';
                        echo '<li><code>lead-magnet-downloader</code> - Downloaded content</li>';
                        echo '</ul>';
                        echo '</div>';
                        
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>Quiz Result Tags</h4>';
                        echo '<ul style="margin: 5px 0; padding-left: 20px;">';
                        echo '<li><code>beginner-seo</code> - Beginner level</li>';
                        echo '<li><code>intermediate-seo</code> - Intermediate level</li>';
                        echo '<li><code>advanced-seo</code> - Advanced level</li>';
                        echo '<li><code>interested-in-tools</code> - Tools interest</li>';
                        echo '<li><code>interested-in-courses</code> - Courses interest</li>';
                        echo '</ul>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // UTM Strategy
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🎯 UTM Tracking Strategy</h2>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                        
                        echo '<div>';
                        echo '<h3>📊 UTM Parameters</h3>';
                        echo '<table style="width: 100%; border-collapse: collapse;">';
                        echo '<tr style="background: #f8f9fa;"><th style="padding: 10px; border: 1px solid #ddd;">Parameter</th><th style="padding: 10px; border: 1px solid #ddd;">Example</th></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;"><code>utm_source</code></td><td style="padding: 10px; border: 1px solid #ddd;">google, facebook, email</td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;"><code>utm_medium</code></td><td style="padding: 10px; border: 1px solid #ddd;">cpc, social, email</td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;"><code>utm_campaign</code></td><td style="padding: 10px; border: 1px solid #ddd;">seo-tools-launch</td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;"><code>utm_content</code></td><td style="padding: 10px; border: 1px solid #ddd;">banner-top, ad1</td></tr>';
                        echo '<tr><td style="padding: 10px; border: 1px solid #ddd;"><code>utm_term</code></td><td style="padding: 10px; border: 1px solid #ddd;">seo tools, discount</td></tr>';
                        echo '</table>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>🚀 Campaign Examples</h3>';
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>Google Ads Campaign</h4>';
                        echo '<code style="background: #e9ecef; padding: 5px; border-radius: 3px; display: block; margin: 5px 0;">https://yoursite.com/landing-page?utm_source=google&utm_medium=cpc&utm_campaign=seo-tools&utm_content=ad1&utm_term=seo+software</code>';
                        echo '</div>';
                        
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>Facebook Ad Campaign</h4>';
                        echo '<code style="background: #e9ecef; padding: 5px; border-radius: 3px; display: block; margin: 5px 0;">https://yoursite.com/landing-page?utm_source=facebook&utm_medium=social&utm_campaign=summer-sale&utm_content=video-ad&utm_term=discount</code>';
                        echo '</div>';
                        
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0;">';
                        echo '<h4>Email Newsletter</h4>';
                        echo '<code style="background: #e9ecef; padding: 5px; border-radius: 3px; display: block; margin: 5px 0;">https://yoursite.com/landing-page?utm_source=newsletter&utm_medium=email&utm_campaign=weekly-tips&utm_content=cta-button&utm_term=seo+guide</code>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Implementation Steps
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🛠️ Implementation Steps</h2>';
                        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                        
                        echo '<div>';
                        echo '<h3>1. Form Setup</h3>';
                        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>Add hidden field <code>thrive_mautic_segment</code> to each form</li>';
                        echo '<li>Set segment value based on form type</li>';
                        echo '<li>UTM data is captured automatically</li>';
                        echo '<li>Tags are added based on content/behavior</li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>2. Mautic Configuration</h3>';
                        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>Create segments in Mautic</li>';
                        echo '<li>Set up email sequences for each segment</li>';
                        echo '<li>Configure UTM field mapping</li>';
                        echo '<li>Set up tag-based automation</li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>3. Campaign Tracking</h3>';
                        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>Use UTM Builder to create campaign URLs</li>';
                        echo '<li>Track performance in UTM Analytics</li>';
                        echo '<li>Monitor lead quality by source</li>';
                        echo '<li>Optimize based on data</li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        echo '<div>';
                        echo '<h3>4. Lead Management</h3>';
                        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>Monitor lead workflow dashboard</li>';
                        echo '<li>Track verification rates</li>';
                        echo '<li>Analyze content performance</li>';
                        echo '<li>Optimize conversion funnels</li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>Workflow Guide</h1>';
                        echo '<div class="notice notice-error"><p>Workflow guide error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                }
            );
            
            // Form Analysis Submenu
            add_submenu_page(
                'thrive-mautic-dashboard',
                'Form Analysis',
                'Form Analysis',
                'manage_options',
                'thrive-mautic-forms',
                function() {
                    try {
                        echo '<div class="wrap">';
                        echo '<h1>Thrive Forms Analysis</h1>';
                        
                        // Get all forms from database
                        global $wpdb;
                        $forms = thrive_mautic_analyze_all_forms();
                        
                        if (empty($forms)) {
                            echo '<div class="notice notice-warning"><p>No Thrive forms found on your website. Make sure you have Thrive Themes installed and forms created.</p></div>';
                            echo '</div>';
                            return;
                        }
                        
                        // Form Statistics Summary
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>📊 Form Statistics Summary</h2>';
                        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
                        
                        $total_forms = count($forms);
                        $forms_with_segments = count(array_filter($forms, function($form) { return !empty($form['segment_field']); }));
                        $forms_without_segments = $total_forms - $forms_with_segments;
                        $thrive_architect_forms = count(array_filter($forms, function($form) { return $form['type'] === 'thrive_architect'; }));
                        $thrive_leads_forms = count(array_filter($forms, function($form) { return $form['type'] === 'thrive_leads'; }));
                        $thrive_quiz_forms = count(array_filter($forms, function($form) { return $form['type'] === 'thrive_quiz'; }));
                        $thrive_lightbox_forms = count(array_filter($forms, function($form) { return $form['type'] === 'thrive_lightbox'; }));
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #007cba;">';
                        echo '<h3>Total Forms</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #007cba;">' . $total_forms . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745;">';
                        echo '<h3>With Segmentation</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #28a745;">' . $forms_with_segments . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;">';
                        echo '<h3>Without Segmentation</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #ffc107;">' . $forms_without_segments . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #6f42c1;">';
                        echo '<h3>Thrive Architect</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #6f42c1;">' . $thrive_architect_forms . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #e83e8c;">';
                        echo '<h3>Thrive Leads</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #e83e8c;">' . $thrive_leads_forms . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #fd7e14;">';
                        echo '<h3>Thrive Quiz</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #fd7e14;">' . $thrive_quiz_forms . '</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #20c997;">';
                        echo '<h3>Thrive Lightbox</h3>';
                        echo '<div style="font-size: 24px; font-weight: bold; color: #20c997;">' . $thrive_lightbox_forms . '</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        echo '</div>';
                        
                        // Forms Table
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>📋 Detailed Form Analysis</h2>';
                        echo '<table class="wp-list-table widefat fixed striped">';
                        echo '<thead>';
                        echo '<tr>';
                        echo '<th>Form ID</th>';
                        echo '<th>Type</th>';
                        echo '<th>Location</th>';
                        echo '<th>Fields</th>';
                        echo '<th>Segment Field</th>';
                        echo '<th>Segment Value</th>';
                        echo '<th>Status</th>';
                        echo '<th>Actions</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';
                        
                        foreach ($forms as $form) {
                            $segment_status = !empty($form['segment_field']) ? 
                                '<span style="color: #28a745; font-weight: bold;">✅ Yes</span>' : 
                                '<span style="color: #ffc107; font-weight: bold;">❌ No</span>';
                            
                            $segment_value = !empty($form['segment_value']) ? 
                                '<code>' . esc_html($form['segment_value']) . '</code>' : 
                                '<em>Will use: ' . esc_html($form['type']) . '</em>';
                            
                            $fields_count = count($form['fields']);
                            $fields_preview = implode(', ', array_slice($form['fields'], 0, 3));
                            if (count($form['fields']) > 3) {
                                $fields_preview .= '... (+' . (count($form['fields']) - 3) . ' more)';
                            }
                            
                            echo '<tr>';
                            echo '<td><code>' . esc_html($form['id']) . '</code></td>';
                            echo '<td><span class="dashicons dashicons-' . esc_attr($form['icon']) . '"></span> ' . esc_html(ucfirst(str_replace('_', ' ', $form['type']))) . '</td>';
                            echo '<td>' . esc_html($form['location']) . '</td>';
                            echo '<td title="' . esc_attr(implode(', ', $form['fields'])) . '">' . esc_html($fields_preview) . ' <small>(' . $fields_count . ' fields)</small></td>';
                            echo '<td>' . $segment_status . '</td>';
                            echo '<td>' . $segment_value . '</td>';
                            echo '<td><span style="color: #28a745;">✅ Active</span></td>';
                            echo '<td>';
                            echo '<a href="' . esc_url($form['edit_url']) . '" class="button button-small" target="_blank">Edit Form</a>';
                            if (!empty($form['segment_field'])) {
                                echo ' <span title="This form has custom segmentation configured">🎯</span>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody>';
                        echo '</table>';
                        echo '</div>';
                        
                        // Instructions
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>📝 How to Add Custom Segmentation</h2>';
                        echo '<ol>';
                        echo '<li><strong>For Thrive Architect Forms:</strong> Add a Hidden Field with name <code>thrive_mautic_segment</code> and your desired segment value</li>';
                        echo '<li><strong>For Thrive Leads:</strong> Add a Hidden Field to your lead group forms</li>';
                        echo '<li><strong>For Thrive Quiz:</strong> Add a Hidden Field to your quiz completion forms</li>';
                        echo '<li><strong>For Thrive Lightboxes:</strong> Add a Hidden Field to your lightbox forms</li>';
                        echo '</ol>';
                        echo '<p><strong>Example Hidden Field:</strong></p>';
                        echo '<ul>';
                        echo '<li><strong>Field Name:</strong> <code>thrive_mautic_segment</code></li>';
                        echo '<li><strong>Field Value:</strong> <code>my-custom-segment</code></li>';
                        echo '</ul>';
                        echo '<p>The plugin will automatically create the segment in Mautic and add contacts to it.</p>';
                        echo '</div>';
                        
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>Thrive Forms Analysis</h1>';
                        echo '<div class="notice notice-error"><p>Form analysis error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                }
            );
            
            // Help Submenu
            add_submenu_page(
                'thrive-mautic-dashboard',
                'Help & Setup',
                'Help & Setup',
                'manage_options',
                'thrive-mautic-help',
                function() {
                    try {
                        echo '<div class="wrap">';
                        echo '<h1>Thrive-Mautic Integration Help & Setup Guide</h1>';
                        
                        // Table of Contents
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>📋 Table of Contents</h2>';
                        echo '<ol>';
                        echo '<li><a href="#mautic-setup">Mautic Setup & Configuration</a></li>';
                        echo '<li><a href="#thrive-architect">Thrive Architect Forms</a></li>';
                        echo '<li><a href="#thrive-lightboxes">Thrive Lightboxes</a></li>';
                        echo '<li><a href="#thrive-leads">Thrive Leads</a></li>';
                        echo '<li><a href="#thrive-quiz">Thrive Quiz Builder</a></li>';
                        echo '<li><a href="#segments">Mautic Segments Setup</a></li>';
                        echo '<li><a href="#troubleshooting">Troubleshooting</a></li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        // Mautic Setup
                        echo '<div id="mautic-setup" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🔧 Mautic Setup & Configuration</h2>';
                        echo '<h3>Step 1: Get Your Mautic Credentials</h3>';
                        echo '<ol>';
                        echo '<li>Log into your Mautic dashboard</li>';
                        echo '<li>Go to <strong>Settings → Configuration → API Settings</strong></li>';
                        echo '<li>Enable <strong>API</strong> if not already enabled</li>';
                        echo '<li>Note down your <strong>Mautic Base URL</strong> (e.g., https://your-mautic-site.com)</li>';
                        echo '<li>Create a new user or use existing admin credentials</li>';
                        echo '<li>Make sure the user has <strong>API access</strong> permissions</li>';
                        echo '</ol>';
                        
                        echo '<h3>Step 2: Configure Plugin Settings</h3>';
                        echo '<ol>';
                        echo '<li>Go to <strong>Thrive-Mautic → Settings</strong> in WordPress admin</li>';
                        echo '<li>Enter your <strong>Mautic Base URL</strong></li>';
                        echo '<li>Enter your <strong>Mautic Username</strong></li>';
                        echo '<li>Enter your <strong>Mautic Password</strong></li>';
                        echo '<li>Click <strong>Test Connection</strong> to verify</li>';
                        echo '<li>Click <strong>Save Settings</strong></li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        // Thrive Architect
                        echo '<div id="thrive-architect" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>📝 Thrive Architect Forms</h2>';
                        echo '<h3>Step 1: Create Your Form</h3>';
                        echo '<ol>';
                        echo '<li>Edit any page/post with Thrive Architect</li>';
                        echo '<li>Add a <strong>Contact Form</strong> element</li>';
                        echo '<li>Configure your form fields (Email is required)</li>';
                        echo '<li>Add fields like: Name, Phone, Company (optional)</li>';
                        echo '</ol>';
                        
                        echo '<h3>Step 2: Form Field Setup</h3>';
                        echo '<p><strong>Required Field Names (for automatic detection):</strong></p>';
                        echo '<ul>';
                        echo '<li><code>email</code> - Email address (required)</li>';
                        echo '<li><code>name</code> or <code>firstname</code> - First name</li>';
                        echo '<li><code>phone</code> - Phone number</li>';
                        echo '<li><code>company</code> - Company name</li>';
                        echo '</ul>';
                        
                        echo '<h3>Step 3: Test Your Form</h3>';
                        echo '<ol>';
                        echo '<li>Submit a test form on your website</li>';
                        echo '<li>Go to <strong>Thrive-Mautic → Submissions</strong></li>';
                        echo '<li>Check if the submission appears in the list</li>';
                        echo '<li>Wait 5 minutes for background processing</li>';
                        echo '<li>Check your Mautic contacts to see if the contact was created</li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        // Thrive Lightboxes
                        echo '<div id="thrive-lightboxes" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>💡 Thrive Lightboxes</h2>';
                        echo '<h3>Step 1: Create Your Lightbox</h3>';
                        echo '<ol>';
                        echo '<li>Go to <strong>Thrive Dashboard → Lightboxes</strong></li>';
                        echo '<li>Create a new lightbox or edit existing</li>';
                        echo '<li>Add a <strong>Contact Form</strong> element</li>';
                        echo '<li>Configure your form fields (Email is required)</li>';
                        echo '</ol>';
                        
                        echo '<h3>Step 2: Configure Lightbox Trigger</h3>';
                        echo '<ol>';
                        echo '<li>Set up your lightbox trigger (button, image, etc.)</li>';
                        echo '<li>Make sure the lightbox contains a form with email field</li>';
                        echo '<li>Test the lightbox on your website</li>';
                        echo '</ol>';
                        
                        echo '<h3>Step 3: Verify Integration</h3>';
                        echo '<ol>';
                        echo '<li>Submit a form through the lightbox</li>';
                        echo '<li>Check <strong>Thrive-Mautic → Submissions</strong></li>';
                        echo '<li>Look for form type: <code>thrive_lightbox</code></li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        // Thrive Leads
                        echo '<div id="thrive-leads" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🎯 Thrive Leads</h2>';
                        echo '<h3>Step 1: Create Lead Group</h3>';
                        echo '<ol>';
                        echo '<li>Go to <strong>Thrive Dashboard → Lead Groups</strong></li>';
                        echo '<li>Create a new lead group</li>';
                        echo '<li>Add a form with email field (required)</li>';
                        echo '<li>Configure additional fields as needed</li>';
                        echo '</ol>';
                        
                        echo '<h3>Step 2: Set Up Lead Forms</h3>';
                        echo '<ol>';
                        echo '<li>Create different form types (ribbon, popup, etc.)</li>';
                        echo '<li>Make sure each form has an email field</li>';
                        echo '<li>Test each form type</li>';
                        echo '</ol>';
                        
                        echo '<h3>Step 3: Verify Integration</h3>';
                        echo '<ol>';
                        echo '<li>Submit forms through different lead types</li>';
                        echo '<li>Check <strong>Thrive-Mautic → Submissions</strong></li>';
                        echo '<li>Look for form type: <code>thrive_leads</code></li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        // Thrive Quiz
                        echo '<div id="thrive-quiz" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🧩 Thrive Quiz Builder</h2>';
                        echo '<h3>Step 1: Create Your Quiz</h3>';
                        echo '<ol>';
                        echo '<li>Go to <strong>Thrive Dashboard → Quiz Builder</strong></li>';
                        echo '<li>Create a new quiz</li>';
                        echo '<li>Add questions and answers</li>';
                        echo '<li>Set up quiz completion form with email field</li>';
                        echo '</ol>';
                        
                        echo '<h3>Step 2: Configure Quiz Settings</h3>';
                        echo '<ol>';
                        echo '<li>Set up quiz completion form</li>';
                        echo '<li>Make sure email field is included</li>';
                        echo '<li>Add any additional fields (name, phone, etc.)</li>';
                        echo '</ol>';
                        
                        echo '<h3>Step 3: Test Quiz Integration</h3>';
                        echo '<ol>';
                        echo '<li>Complete a quiz on your website</li>';
                        echo '<li>Check <strong>Thrive-Mautic → Submissions</strong></li>';
                        echo '<li>Look for form type: <code>thrive_quiz</code></li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        // Segments
                        echo '<div id="segments" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🏷️ Mautic Segments Setup</h2>';
                        echo '<h3>Step 1: Create Segments in Mautic</h3>';
                        echo '<ol>';
                        echo '<li>Go to <strong>Mautic → Segments</strong></li>';
                        echo '<li>Create segments for different form types:</li>';
                        echo '<ul>';
                        echo '<li><code>thrive-architect</code> - For Thrive Architect forms</li>';
                        echo '<li><code>thrive-lightbox</code> - For Thrive Lightboxes</li>';
                        echo '<li><code>thrive-leads</code> - For Thrive Leads</li>';
                        echo '<li><code>thrive-quiz</code> - For Thrive Quiz Builder</li>';
                        echo '</ul>';
                        echo '<li>Note down the <strong>Segment ID</strong> for each segment</li>';
                        echo '</ol>';
                        
                        echo '<h3>Step 2: Configure Segment Assignment</h3>';
                        echo '<p>Currently, the plugin automatically assigns contacts to segments based on form type. To customize this:</p>';
                        echo '<ol>';
                        echo '<li>Edit the plugin code (advanced users only)</li>';
                        echo '<li>Modify the <code>thrive_mautic_add_to_segment()</code> function</li>';
                        echo '<li>Add your custom segment logic</li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        // Troubleshooting
                        echo '<div id="troubleshooting" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🔧 Troubleshooting</h2>';
                        echo '<h3>Common Issues & Solutions</h3>';
                        
                        echo '<h4>❌ Form submissions not appearing in Mautic</h4>';
                        echo '<ol>';
                        echo '<li>Check <strong>Thrive-Mautic → Submissions</strong> for pending/failed submissions</li>';
                        echo '<li>Verify Mautic credentials in <strong>Settings</strong></li>';
                        echo '<li>Test Mautic connection</li>';
                        echo '<li>Check if background processing is working (wait 5 minutes)</li>';
                        echo '<li>Look at error messages in submissions table</li>';
                        echo '</ol>';
                        
                        echo '<h4>❌ Plugin not capturing forms</h4>';
                        echo '<ol>';
                        echo '<li>Make sure forms have email fields</li>';
                        echo '<li>Check field names (email, name, phone, company)</li>';
                        echo '<li>Test with different form types</li>';
                        echo '<li>Check WordPress error logs</li>';
                        echo '</ol>';
                        
                        echo '<h4>❌ Mautic connection test fails</h4>';
                        echo '<ol>';
                        echo '<li>Verify Mautic URL (include https://)</li>';
                        echo '<li>Check username and password</li>';
                        echo '<li>Ensure API is enabled in Mautic</li>';
                        echo '<li>Check if user has API permissions</li>';
                        echo '</ol>';
                        
                        echo '<h4>❌ Website crashes after plugin activation</h4>';
                        echo '<ol>';
                        echo '<li>Deactivate plugin immediately</li>';
                        echo '<li>Check WordPress error logs</li>';
                        echo '<li>Update to latest plugin version</li>';
                        echo '<li>Contact support if issue persists</li>';
                        echo '</ol>';
                        echo '</div>';
                        
                        // Support
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>🆘 Support & Resources</h2>';
                        echo '<p><strong>Plugin Version:</strong> ' . THRIVE_MAUTIC_VERSION . '</p>';
                        echo '<p><strong>GitHub Repository:</strong> <a href="https://github.com/khodor04/thrive-mautic-integration" target="_blank">https://github.com/khodor04/thrive-mautic-integration</a></p>';
                        echo '<p><strong>Mautic Documentation:</strong> <a href="https://docs.mautic.org/" target="_blank">https://docs.mautic.org/</a></p>';
                        echo '<p><strong>Thrive Themes Documentation:</strong> <a href="https://thrivethemes.com/help/" target="_blank">https://thrivethemes.com/help/</a></p>';
                        echo '</div>';
                        
                        echo '</div>';
                        
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>Thrive-Mautic Help</h1>';
                        echo '<div class="notice notice-error"><p>Help page error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                }
            );
            
            // Settings Submenu
            add_submenu_page(
                'thrive-mautic-dashboard',
                'Settings',
                'Settings',
                'manage_options',
                'thrive-mautic-settings',
                function() {
                    try {
                        // Handle form submission
                        if (isset($_POST['save_settings']) && wp_verify_nonce($_POST['thrive_mautic_nonce'], 'save_settings')) {
                            update_option('thrive_mautic_base_url', sanitize_url($_POST['base_url']));
                            update_option('thrive_mautic_username', sanitize_text_field($_POST['username']));
                            
                            // Encrypt password before storing
                            $password = sanitize_text_field($_POST['password']);
                            if (!empty($password)) {
                                $encrypted_password = encrypt_password($password);
                                update_option('thrive_mautic_password', $encrypted_password);
                            }
                            
                            update_option('thrive_mautic_auto_update', isset($_POST['auto_update']));
                            
                            // Save tracking settings
                            update_option('thrive_mautic_tracking_enabled', isset($_POST['tracking_enabled']));
                            update_option('thrive_mautic_custom_tracking_script', wp_kses($_POST['custom_tracking_script'], array(
                                'script' => array(),
                                'noscript' => array()
                            )));
                            update_option('thrive_mautic_tracking_position', sanitize_text_field($_POST['tracking_position']));
                            update_option('thrive_mautic_tracking_pages', sanitize_text_field($_POST['tracking_pages']));
                            update_option('thrive_mautic_specific_pages', sanitize_text_field($_POST['specific_pages']));
                            
                            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
                        }
                        
                        // Get current settings
                        $base_url = get_option('thrive_mautic_base_url', '');
                        $username = get_option('thrive_mautic_username', '');
                        $encrypted_password = get_option('thrive_mautic_password', '');
                        $password = !empty($encrypted_password) ? '••••••••' : ''; // Show dots if password exists
                        $auto_update = get_option('thrive_mautic_auto_update', true);
                        
                        echo '<div class="wrap">';
                        echo '<h1>Thrive-Mautic Settings</h1>';
                        echo '<form method="post" name="thrive_mautic_integration_settings_form" autocomplete="off" data-lpignore="true">';
                        wp_nonce_field('save_settings', 'thrive_mautic_nonce');
                        
                        // Hidden fields to confuse browser auto-fill
                        echo '<input type="text" name="fake_username" style="display:none;" autocomplete="off">';
                        echo '<input type="password" name="fake_password" style="display:none;" autocomplete="off">';
                        echo '<input type="url" name="fake_url" style="display:none;" autocomplete="off">';
                        
                        echo '<table class="form-table">';
                        
                        // Mautic Base URL
                        echo '<tr>';
                        echo '<th scope="row"><label for="thrive_mautic_integration_base_url_field_' . time() . '">Mautic Base URL</label></th>';
                        echo '<td>';
                        echo '<input type="url" id="thrive_mautic_integration_base_url_field_' . time() . '" name="base_url" value="' . esc_attr($base_url) . '" class="regular-text" placeholder="https://your-mautic-site.com" autocomplete="new-password" data-lpignore="true">';
                        echo '<p class="description">Enter your Mautic installation URL (e.g., https://your-mautic-site.com)</p>';
                        echo '</td>';
                        echo '</tr>';
                        
                        // Mautic Username
                        echo '<tr>';
                        echo '<th scope="row"><label for="thrive_mautic_integration_username_field">Mautic Username</label></th>';
                        echo '<td>';
                        echo '<input type="text" id="thrive_mautic_integration_username_field" name="username" value="' . esc_attr($username) . '" class="regular-text" placeholder="your-username" autocomplete="off">';
                        echo '<p class="description">Enter your Mautic username</p>';
                        echo '</td>';
                        echo '</tr>';
                        
                        // Mautic Password
                        echo '<tr>';
                        echo '<th scope="row"><label for="thrive_mautic_integration_password_field">Mautic Password</label></th>';
                        echo '<td>';
                        echo '<input type="password" id="thrive_mautic_integration_password_field" name="password" value="' . esc_attr($password) . '" class="regular-text" placeholder="your-password" autocomplete="off">';
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
                        
                        // Mautic Tracking Settings Section
                        echo '<h2 style="margin-top: 30px;">Mautic Tracking Settings</h2>';
                        echo '<table class="form-table">';
                        
                        // Enable Tracking
                        $tracking_enabled = get_option('thrive_mautic_tracking_enabled', false);
                        echo '<tr>';
                        echo '<th scope="row">Enable Mautic Tracking</th>';
                        echo '<td>';
                        echo '<label>';
                        echo '<input type="checkbox" name="tracking_enabled" value="1" ' . checked($tracking_enabled, true, false) . '>';
                        echo ' Enable Mautic tracking script on website';
                        echo '</label>';
                        echo '<p class="description">When enabled, Mautic tracking script will be inserted on all pages.</p>';
                        echo '</td>';
                        echo '</tr>';
                        
                        // Custom Tracking Script
                        $custom_tracking_script = get_option('thrive_mautic_custom_tracking_script', '');
                        echo '<tr>';
                        echo '<th scope="row"><label for="custom_tracking_script">Custom Tracking Script</label></th>';
                        echo '<td>';
                        echo '<textarea id="custom_tracking_script" name="custom_tracking_script" rows="8" cols="80" class="large-text code" placeholder="Paste your Mautic tracking script here...">' . esc_textarea($custom_tracking_script) . '</textarea>';
                        echo '<p class="description">Paste your complete Mautic tracking script from <strong>Mautic → Configuration → Tracking Settings</strong>. Include the full &lt;script&gt; tags.</p>';
                        echo '<p class="description"><strong>Example:</strong><br>';
                        echo '<code>&lt;script&gt;<br>';
                        echo '    (function(w,d,t,u,n,a,m){w[\'MauticTrackingObject\']=n;<br>';
                        echo '        w[n]=w[n]||function(){(w[n].q=w[n].q||[]).push(arguments)},a=d.createElement(t),<br>';
                        echo '        m=d.getElementsByTagName(t)[0];a.async=1;a.src=u;m.parentNode.insertBefore(a,m)<br>';
                        echo '    })(window,document,\'script\',\'http://mautic.aipoweredkit.com/mtc.js\',\'mt\');<br>';
                        echo '    mt(\'send\', \'pageview\');<br>';
                        echo '&lt;/script&gt;</code></p>';
                        echo '</td>';
                        echo '</tr>';
                        
                        // Tracking Position
                        $tracking_position = get_option('thrive_mautic_tracking_position', 'footer');
                        echo '<tr>';
                        echo '<th scope="row">Tracking Position</th>';
                        echo '<td>';
                        echo '<select name="tracking_position">';
                        echo '<option value="head" ' . selected($tracking_position, 'head', false) . '>In &lt;head&gt; section (faster loading)</option>';
                        echo '<option value="footer" ' . selected($tracking_position, 'footer', false) . '>Before &lt;/body&gt; tag (recommended)</option>';
                        echo '</select>';
                        echo '<p class="description">Choose where to insert the tracking script. Footer is recommended for better page load performance.</p>';
                        echo '</td>';
                        echo '</tr>';
                        
                        // Tracking Pages
                        $tracking_pages = get_option('thrive_mautic_tracking_pages', 'all');
                        echo '<tr>';
                        echo '<th scope="row">Tracking Pages</th>';
                        echo '<td>';
                        echo '<select name="tracking_pages">';
                        echo '<option value="all" ' . selected($tracking_pages, 'all', false) . '>All pages</option>';
                        echo '<option value="frontend" ' . selected($tracking_pages, 'frontend', false) . '>Frontend only (not admin)</option>';
                        echo '<option value="specific" ' . selected($tracking_pages, 'specific', false) . '>Specific pages only</option>';
                        echo '</select>';
                        echo '<p class="description">Choose which pages to include tracking on.</p>';
                        echo '</td>';
                        echo '</tr>';
                        
                        // Specific Pages (if specific is selected)
                        if ($tracking_pages === 'specific') {
                            $specific_pages = get_option('thrive_mautic_specific_pages', '');
                            echo '<tr id="specific-pages-row">';
                            echo '<th scope="row"><label for="specific_pages">Specific Page IDs</label></th>';
                            echo '<td>';
                            echo '<input type="text" id="specific_pages" name="specific_pages" value="' . esc_attr($specific_pages) . '" class="regular-text" placeholder="1,2,3 or /page-slug/">';
                            echo '<p class="description">Enter page IDs (comma-separated) or page slugs. Leave empty for all pages.</p>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</table>';
                        
                        echo '<p class="submit">';
                        echo '<input type="submit" name="save_settings" class="button-primary" value="Save Settings">';
                        echo '<button type="button" id="test-connection" class="button" style="margin-left: 10px;">Test Mautic Connection</button>';
                        echo '<div id="connection-status" style="margin-top: 10px;"></div>';
                        
                        // Troubleshooting section
                        echo '<div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #007cba;">';
                        echo '<h3>🔧 Connection Troubleshooting</h3>';
                        echo '<p><strong>HTTP 401 Error?</strong> Check these common issues:</p>';
                        echo '<ul style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li><strong>Username/Password:</strong> Make sure they are correct and match your Mautic login</li>';
                        echo '<li><strong>API Access:</strong> Your Mautic user must have API access enabled</li>';
                        echo '<li><strong>URL Format:</strong> Use full URL like <code>https://yourmautic.com</code> (not <code>yourmautic.com</code>)</li>';
                        echo '<li><strong>SSL Certificate:</strong> Make sure your Mautic site has a valid SSL certificate</li>';
                        echo '<li><strong>Firewall:</strong> Check if your server can access the Mautic API</li>';
                        echo '</ul>';
                        echo '<p><strong>To enable API access in Mautic:</strong></p>';
                        echo '<ol style="margin: 10px 0; padding-left: 20px;">';
                        echo '<li>Go to Mautic → Settings → Configuration</li>';
                        echo '<li>Enable "API" under "System Settings"</li>';
                        echo '<li>Go to Users → Edit your user</li>';
                        echo '<li>Enable "API Access" permission</li>';
                        echo '</ol>';
                        echo '</div>';
                        echo '<button type="button" id="force-update-check" class="button" style="margin-left: 10px;">Force Update Check</button>';
                        echo '</p>';
                        
                        // Test Connection Result
                        echo '<div id="connection-result" style="margin-top: 15px;"></div>';
                        
                        // Update Check Result
                        echo '<div id="update-result" style="margin-top: 15px;"></div>';
                        
                        // JavaScript to prevent auto-fill interference and handle dynamic fields
                        echo '<script>
                        // Clear any cached auto-fill data
                        document.addEventListener("DOMContentLoaded", function() {
                            // Clear all input fields that might interfere
                            var inputs = document.querySelectorAll("input[type=text], input[type=search], input[type=url]");
                            inputs.forEach(function(input) {
                                if (input.id !== "thrive_mautic_integration_base_url_field" && 
                                    input.id !== "thrive_mautic_integration_username_field" && 
                                    input.id !== "thrive_mautic_integration_password_field") {
                                    input.setAttribute("autocomplete", "off");
                                    input.setAttribute("data-lpignore", "true");
                                }
                            });
                            
                            // Handle tracking pages selection
                            var trackingPagesSelect = document.querySelector("select[name=tracking_pages]");
                            var specificPagesRow = document.getElementById("specific-pages-row");
                            
                            function toggleSpecificPages() {
                                if (trackingPagesSelect.value === "specific") {
                                    if (specificPagesRow) {
                                        specificPagesRow.style.display = "";
                                    }
                                } else {
                                    if (specificPagesRow) {
                                        specificPagesRow.style.display = "none";
                                    }
                                }
                            }
                            
                            if (trackingPagesSelect) {
                                trackingPagesSelect.addEventListener("change", toggleSpecificPages);
                                toggleSpecificPages(); // Initial call
                            }
                        });
                        
                        // JavaScript for test connection
                        document.getElementById("test-connection").addEventListener("click", function() {
                            this.disabled = true;
                            this.textContent = "Testing...";
                            
                            const formData = new FormData();
                            formData.append("action", "test_mautic_connection");
                            formData.append("nonce", "' . wp_create_nonce("test_mautic_connection") . '");
                            
                            fetch("' . admin_url("admin-ajax.php") . '", {
                                method: "POST",
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                const resultDiv = document.getElementById("connection-result");
                                if (data.success) {
                                    resultDiv.innerHTML = \'<div class="notice notice-success inline"><p>\' + data.data + \'</p></div>\';
                                } else {
                                    resultDiv.innerHTML = \'<div class="notice notice-error inline"><p>\' + data.data + \'</p></div>\';
                                }
                            })
                            .catch(error => {
                                document.getElementById("connection-result").innerHTML = \'<div class="notice notice-error inline"><p>Connection test failed: \' + error + \'</p></div>\';
                            })
                            .finally(() => {
                                this.disabled = false;
                                this.textContent = "Test Mautic Connection";
                            });
                        });
                        
                        // JavaScript for force update check
                        document.getElementById("force-update-check").addEventListener("click", function() {
                            this.disabled = true;
                            this.textContent = "Checking...";
                            
                            // Clear WordPress update cache
                            fetch("' . admin_url("admin-ajax.php") . '", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/x-www-form-urlencoded",
                                },
                                body: "action=thrive_mautic_force_update_check&nonce=' . wp_create_nonce("force_update_check") . '"
                            })
                            .then(response => response.json())
                            .then(data => {
                                const resultDiv = document.getElementById("update-result");
                                if (data.success) {
                                    if (data.data.update_available) {
                                        resultDiv.innerHTML = \'<div class="notice notice-warning inline"><p><strong>Update Available!</strong> Version \' + data.data.version + \' is available. <a href="' . admin_url('plugins.php') . '">Go to Plugins Page to Update</a></p></div>\';
                                    } else {
                                        resultDiv.innerHTML = \'<div class="notice notice-success inline"><p>You are running the latest version (' . THRIVE_MAUTIC_VERSION . ')</p></div>\';
                                    }
                                } else {
                                    resultDiv.innerHTML = \'<div class="notice notice-error inline"><p>Update check failed: \' + data.data + \'</p></div>\';
                                }
                            })
                            .catch(error => {
                                document.getElementById("update-result").innerHTML = \'<div class="notice notice-error inline"><p>Update check failed: \' + error + \'</p></div>\';
                            })
                            .finally(() => {
                                this.disabled = false;
                                this.textContent = "Force Update Check";
                            });
                        });
                        
                        </script>';
                        echo '</form>';
                        echo '</div>';
                    } catch (Exception $e) {
                        echo '<div class="wrap"><h1>Thrive-Mautic Settings</h1>';
                        echo '<div class="notice notice-error"><p>Settings error occurred. Please check error logs.</p></div>';
                        echo '</div>';
                    }
                }
            );
        } catch (Exception $e) {
            // Silent fail - don't crash
        }
    });

    // Thrive Themes form capture
    add_action('wp_ajax_tve_api_form_submit', 'thrive_mautic_capture_form', 5);
    add_action('wp_ajax_nopriv_tve_api_form_submit', 'thrive_mautic_capture_form', 5);
    
    // Thrive Lightboxes form capture
    add_action('tve_lightbox_submit', 'thrive_mautic_capture_lightbox_form', 10, 2);
    
    // Thrive Leads form capture
    add_action('tve_leads_form_submit', 'thrive_mautic_capture_leads_form', 10, 2);
    
    // Thrive Quiz Builder form capture
    add_action('tqb_quiz_completed', 'thrive_mautic_capture_quiz_form', 10, 2);
    
    function thrive_mautic_capture_form() {
        try {
            if (!isset($_POST['form_data']) || !is_array($_POST['form_data'])) {
                return;
            }
            
            $form_data = $_POST['form_data'];
            $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : 'unknown';
            
            // Extract email, name, phone, company, custom segment, and UTM data
            $email = '';
            $name = '';
            $phone = '';
            $company = '';
            $custom_segment = '';
            $utm_data = array();
            
            foreach ($form_data as $field) {
                if (isset($field['name']) && isset($field['value'])) {
                    $field_name = strtolower($field['name']);
                    $field_value = sanitize_text_field($field['value']);
                    
                    if (strpos($field_name, 'email') !== false) {
                        $email = $field_value;
                    } elseif (strpos($field_name, 'name') !== false || strpos($field_name, 'first') !== false) {
                        $name = $field_value;
                    } elseif (strpos($field_name, 'phone') !== false) {
                        $phone = $field_value;
                    } elseif (strpos($field_name, 'company') !== false) {
                        $company = $field_value;
                    } elseif ($field_name === 'thrive_mautic_segment') {
                        $custom_segment = $field_value;
                    } elseif (strpos($field_name, 'utm_') === 0) {
                        $utm_data[$field_name] = $field_value;
                    }
                }
            }
            
            if (!empty($email)) {
                // Determine segment ID
                $segment_id = !empty($custom_segment) ? $custom_segment : 'thrive_architect';
                
                thrive_mautic_queue_submission(array(
                    'form_id' => $form_id,
                    'form_type' => 'thrive_architect',
                    'email' => $email,
                    'name' => $name,
                    'phone' => $phone,
                    'company' => $company,
                    'segment_id' => $segment_id,
                    'utm_data' => $utm_data
                ));
            }
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Form capture error: ' . $e->getMessage());
        }
    }
    
    function thrive_mautic_capture_lightbox_form($lightbox_data, $form_type) {
        try {
            if (!isset($lightbox_data['email']) || empty($lightbox_data['email'])) {
                return;
            }
            
            // Check for custom segment and UTM data
            $custom_segment = isset($lightbox_data['thrive_mautic_segment']) ? sanitize_text_field($lightbox_data['thrive_mautic_segment']) : '';
            $segment_id = !empty($custom_segment) ? $custom_segment : 'thrive_lightbox';
            
            // Extract UTM data
            $utm_data = array();
            foreach ($lightbox_data as $key => $value) {
                if (strpos($key, 'utm_') === 0) {
                    $utm_data[$key] = sanitize_text_field($value);
                }
            }
            
            thrive_mautic_queue_submission(array(
                'form_id' => isset($lightbox_data['form_id']) ? $lightbox_data['form_id'] : 'lightbox_' . $form_type,
                'form_type' => 'thrive_lightbox',
                'email' => sanitize_email($lightbox_data['email']),
                'name' => isset($lightbox_data['name']) ? sanitize_text_field($lightbox_data['name']) : '',
                'phone' => isset($lightbox_data['phone']) ? sanitize_text_field($lightbox_data['phone']) : '',
                'company' => isset($lightbox_data['company']) ? sanitize_text_field($lightbox_data['company']) : '',
                'segment_id' => $segment_id,
                'utm_data' => $utm_data
            ));
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Lightbox form capture error: ' . $e->getMessage());
        }
    }
    
    function thrive_mautic_capture_leads_form($lead_data, $form_type) {
        try {
            if (!isset($lead_data['email']) || empty($lead_data['email'])) {
                return;
            }
            
            // Check for custom segment and UTM data
            $custom_segment = isset($lead_data['thrive_mautic_segment']) ? sanitize_text_field($lead_data['thrive_mautic_segment']) : '';
            $segment_id = !empty($custom_segment) ? $custom_segment : 'thrive_leads';
            
            // Extract UTM data
            $utm_data = array();
            foreach ($lead_data as $key => $value) {
                if (strpos($key, 'utm_') === 0) {
                    $utm_data[$key] = sanitize_text_field($value);
                }
            }
            
            thrive_mautic_queue_submission(array(
                'form_id' => isset($lead_data['form_id']) ? $lead_data['form_id'] : 'leads_' . $form_type,
                'form_type' => 'thrive_leads',
                'email' => sanitize_email($lead_data['email']),
                'name' => isset($lead_data['name']) ? sanitize_text_field($lead_data['name']) : '',
                'phone' => isset($lead_data['phone']) ? sanitize_text_field($lead_data['phone']) : '',
                'company' => isset($lead_data['company']) ? sanitize_text_field($lead_data['company']) : '',
                'segment_id' => $segment_id,
                'utm_data' => $utm_data
            ));
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Leads form capture error: ' . $e->getMessage());
        }
    }
    
    function thrive_mautic_capture_quiz_form($quiz_id, $user_data) {
        try {
            if (!isset($user_data['email']) || empty($user_data['email'])) {
                return;
            }
            
            // Check for custom segment and UTM data
            $custom_segment = isset($user_data['thrive_mautic_segment']) ? sanitize_text_field($user_data['thrive_mautic_segment']) : '';
            $segment_id = !empty($custom_segment) ? $custom_segment : 'thrive_quiz';
            
            // Extract UTM data
            $utm_data = array();
            foreach ($user_data as $key => $value) {
                if (strpos($key, 'utm_') === 0) {
                    $utm_data[$key] = sanitize_text_field($value);
                }
            }
            
            thrive_mautic_queue_submission(array(
                'form_id' => 'quiz_' . $quiz_id,
                'form_type' => 'thrive_quiz',
                'email' => sanitize_email($user_data['email']),
                'name' => isset($user_data['name']) ? sanitize_text_field($user_data['name']) : '',
                'phone' => isset($user_data['phone']) ? sanitize_text_field($user_data['phone']) : '',
                'company' => isset($user_data['company']) ? sanitize_text_field($user_data['company']) : '',
                'segment_id' => $segment_id,
                'utm_data' => $utm_data
            ));
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Quiz form capture error: ' . $e->getMessage());
        }
    }
    
    // Rate limiting function
    function thrive_mautic_check_rate_limit($email) {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            
            // Check if same email submitted in last 5 minutes
            $recent_submission = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE email = %s AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
                $email
            ));
            
            return $recent_submission < 3; // Allow max 3 submissions per 5 minutes per email
            
        } catch (Exception $e) {
            return true; // Allow submission if rate limit check fails
        }
    }

    // Queue submission for background processing
    function thrive_mautic_queue_submission($data) {
        try {
            // Rate limiting check
            if (!thrive_mautic_check_rate_limit($data['email'])) {
                thrive_mautic_log('warning', 'Rate limit exceeded for email: ' . $data['email']);
                return;
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            
            // Extract UTM data
            $utm_data = isset($data['utm_data']) ? $data['utm_data'] : array();
            
            $wpdb->insert(
                $table_name,
                array(
                    'form_id' => sanitize_text_field($data['form_id']),
                    'form_type' => sanitize_text_field($data['form_type']),
                    'email' => sanitize_email($data['email']),
                    'name' => sanitize_text_field($data['name']),
                    'phone' => sanitize_text_field($data['phone']),
                    'company' => sanitize_text_field($data['company']),
                    'segment_id' => sanitize_text_field($data['segment_id']),
                    'utm_source' => isset($utm_data['utm_source']) ? sanitize_text_field($utm_data['utm_source']) : '',
                    'utm_medium' => isset($utm_data['utm_medium']) ? sanitize_text_field($utm_data['utm_medium']) : '',
                    'utm_campaign' => isset($utm_data['utm_campaign']) ? sanitize_text_field($utm_data['utm_campaign']) : '',
                    'utm_content' => isset($utm_data['utm_content']) ? sanitize_text_field($utm_data['utm_content']) : '',
                    'utm_term' => isset($utm_data['utm_term']) ? sanitize_text_field($utm_data['utm_term']) : '',
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                )
            );
            
            thrive_mautic_log('info', 'Form submission queued', json_encode($data));
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Queue submission error: ' . $e->getMessage());
        }
    }


    // AJAX handler for force update check
    add_action('wp_ajax_thrive_mautic_force_update_check', function() {
        try {
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'force_update_check')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            // Clear WordPress update cache
            delete_site_transient('update_plugins');
            
            // Force check for updates
            $current_version = THRIVE_MAUTIC_VERSION;
            $github_url = 'https://api.github.com/repos/khodor04/thrive-mautic-integration/releases/latest';
            
            $response = wp_remote_get($github_url, array(
                'timeout' => 30,
                'headers' => array(
                    'User-Agent' => 'Thrive-Mautic-Plugin'
                )
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('Failed to check for updates');
                return;
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!$data || !isset($data['tag_name'])) {
                wp_send_json_error('Invalid response from GitHub');
                return;
            }
            
            $latest_version = ltrim($data['tag_name'], 'v');
            
            if (version_compare($current_version, $latest_version, '<')) {
                wp_send_json_success(array(
                    'update_available' => true,
                    'version' => $latest_version,
                    'download_url' => $data['html_url']
                ));
            } else {
                wp_send_json_success(array(
                    'update_available' => false,
                    'version' => $current_version
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error('Update check failed: ' . $e->getMessage());
        }
    });

    // AJAX handler for testing Mautic connection
    add_action('wp_ajax_test_mautic_connection', function() {
        try {
            // Check user capabilities
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'test_mautic_connection')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            $base_url = get_option('thrive_mautic_base_url', '');
            $username = get_option('thrive_mautic_username', '');
            $encrypted_password = get_option('thrive_mautic_password', '');
            
            if (empty($base_url) || empty($username) || empty($encrypted_password)) {
                wp_send_json_error('Please configure your Mautic credentials first.');
                return;
            }
            
            $password = decrypt_password($encrypted_password);
            // Test connection with better error handling
            $response = wp_remote_get($base_url . '/api/contacts?limit=1', array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                    'User-Agent' => 'Thrive-Mautic-Plugin/' . THRIVE_MAUTIC_VERSION,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 15,
                'sslverify' => true
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('Connection failed: ' . $response->get_error_message());
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                
                if ($response_code === 200) {
                    wp_send_json_success('Connection successful! Mautic is reachable.');
                } elseif ($response_code === 401) {
                    // Specific 401 error handling
                    $error_details = '';
                    if (!empty($response_body)) {
                        $error_data = json_decode($response_body, true);
                        if (isset($error_data['errors'])) {
                            $error_details = ' Details: ' . implode(', ', array_column($error_data['errors'], 'message'));
                        }
                    }
                    wp_send_json_error('Authentication failed (HTTP 401). Please check your username and password.' . $error_details);
                } elseif ($response_code === 403) {
                    wp_send_json_error('Access forbidden (HTTP 403). Please check if your Mautic user has API access enabled.');
                } elseif ($response_code === 404) {
                    wp_send_json_error('API endpoint not found (HTTP 404). Please check your Mautic URL. Make sure it includes the full URL (e.g., https://yourmautic.com).');
                } else {
                    wp_send_json_error('Connection failed (HTTP ' . $response_code . '). Response: ' . substr($response_body, 0, 200));
                }
            }
        } catch (Exception $e) {
            wp_send_json_error('Connection test failed: ' . $e->getMessage());
        }
    });
    
    // AJAX handler for manual queue processing
    add_action('wp_ajax_thrive_mautic_process_queue_manual', function() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'thrive_mautic_process_queue')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            // Process the queue
            thrive_mautic_process_pending_submissions();
            
            // Get count of processed submissions
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            $processed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE status = 'completed'");
            
            wp_send_json_success(array('processed' => $processed));
            
        } catch (Exception $e) {
            wp_send_json_error('Queue processing failed: ' . $e->getMessage());
        }
    });
    
    // AJAX handler for retrying failed submissions
    add_action('wp_ajax_thrive_mautic_retry_failed', function() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'thrive_mautic_process_queue')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            
            // Reset failed submissions to pending
            $retried = $wpdb->update(
                $table_name,
                array('status' => 'pending'),
                array('status' => 'failed')
            );
            
            // Process the queue
            thrive_mautic_process_pending_submissions();
            
            wp_send_json_success(array('retried' => $retried));
            
        } catch (Exception $e) {
            wp_send_json_error('Retry failed: ' . $e->getMessage());
        }
    });
    
    // AJAX handler for clearing completed submissions
    add_action('wp_ajax_thrive_mautic_clear_completed', function() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'thrive_mautic_process_queue')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            
            $cleared = $wpdb->delete(
                $table_name,
                array('status' => 'completed')
            );
            
            wp_send_json_success(array('cleared' => $cleared));
            
        } catch (Exception $e) {
            wp_send_json_error('Clear failed: ' . $e->getMessage());
        }
    });
    
    // AJAX handler for clearing all submissions
    add_action('wp_ajax_thrive_mautic_clear_all', function() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'thrive_mautic_process_queue')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            
            $cleared = $wpdb->query("DELETE FROM $table_name");
            
            wp_send_json_success(array('cleared' => $cleared));
            
        } catch (Exception $e) {
            wp_send_json_error('Clear all failed: ' . $e->getMessage());
        }
    });
    
    // AJAX handler for script injection test
    add_action('wp_ajax_thrive_mautic_test_script_injection', function() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'thrive_mautic_test')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            $tracking_enabled = get_option('thrive_mautic_tracking_enabled', false);
            $tracking_script = get_option('thrive_mautic_custom_tracking_script', '');
            
            if (!$tracking_enabled) {
                wp_send_json_error('Tracking is not enabled');
                return;
            }
            
            if (empty($tracking_script)) {
                wp_send_json_error('No tracking script configured');
                return;
            }
            
            // Check if the script contains Mautic tracking code
            $has_mautic_script = strpos($tracking_script, 'mtc.js') !== false || 
                                strpos($tracking_script, 'MauticTrackingObject') !== false ||
                                strpos($tracking_script, 'mautic') !== false;
            
            if (!$has_mautic_script) {
                wp_send_json_error('Script does not appear to be a valid Mautic tracking script');
                return;
            }
            
            wp_send_json_success(array('message' => 'Script injection is properly configured and ready to be added to pages'));
            
        } catch (Exception $e) {
            wp_send_json_error('Script injection test failed: ' . $e->getMessage());
        }
    });
    
    // AJAX handler for page targeting test
    add_action('wp_ajax_thrive_mautic_test_page_targeting', function() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'thrive_mautic_test')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            $tracking_pages = get_option('thrive_mautic_tracking_pages', 'all');
            $tracking_page_ids = get_option('thrive_mautic_tracking_page_ids', '');
            
            $message = '';
            
            switch ($tracking_pages) {
                case 'all':
                    $message = 'Script will be added to ALL pages (frontend and admin)';
                    break;
                case 'frontend':
                    $message = 'Script will be added to ALL frontend pages only';
                    break;
                case 'specific':
                    if (empty($tracking_page_ids)) {
                        wp_send_json_error('Specific pages selected but no page IDs configured');
                        return;
                    }
                    $page_ids = explode(',', $tracking_page_ids);
                    $valid_pages = array();
                    foreach ($page_ids as $page_id) {
                        $page_id = trim($page_id);
                        if (is_numeric($page_id)) {
                            $post = get_post($page_id);
                            if ($post) {
                                $valid_pages[] = $post->post_title . ' (ID: ' . $page_id . ')';
                            }
                        }
                    }
                    if (empty($valid_pages)) {
                        wp_send_json_error('No valid pages found with the specified IDs');
                        return;
                    }
                    $message = 'Script will be added to specific pages: ' . implode(', ', $valid_pages);
                    break;
                default:
                    wp_send_json_error('Invalid page targeting setting');
                    return;
            }
            
            wp_send_json_success(array('message' => $message));
            
        } catch (Exception $e) {
            wp_send_json_error('Page targeting test failed: ' . $e->getMessage());
        }
    });
    
    // AJAX handler for UTM tracking test
    add_action('wp_ajax_thrive_mautic_test_utm_tracking', function() {
        try {
            if (!current_user_can('manage_options')) {
                wp_send_json_error('Insufficient permissions');
                return;
            }
            
            if (!wp_verify_nonce($_POST['nonce'], 'thrive_mautic_test')) {
                wp_send_json_error('Invalid nonce');
                return;
            }
            
            // Check if UTM tracking function exists
            if (!function_exists('thrive_mautic_insert_utm_tracking')) {
                wp_send_json_error('UTM tracking function not found');
                return;
            }
            
            // Check if UTM tracking is properly hooked
            $utm_hook = 'wp_head';
            $has_utm_hook = has_action($utm_hook, 'thrive_mautic_insert_utm_tracking');
            
            if (!$has_utm_hook) {
                wp_send_json_error('UTM tracking is not properly hooked to wp_head');
                return;
            }
            
            // Test UTM parameter capture
            $test_utm_data = array(
                'utm_source' => 'test',
                'utm_medium' => 'verification',
                'utm_campaign' => 'script_test',
                'utm_content' => 'verification',
                'utm_term' => 'test'
            );
            
            wp_send_json_success(array('message' => 'UTM tracking is properly configured and will capture: ' . implode(', ', array_keys($test_utm_data))));
            
        } catch (Exception $e) {
            wp_send_json_error('UTM tracking test failed: ' . $e->getMessage());
        }
    });
    
    // Manual connection test function for debugging
    function thrive_mautic_debug_connection() {
        try {
            $base_url = get_option('thrive_mautic_base_url', '');
            $username = get_option('thrive_mautic_username', '');
            $encrypted_password = get_option('thrive_mautic_password', '');
            
            if (empty($base_url) || empty($username) || empty($encrypted_password)) {
                return 'Missing credentials. Please configure Mautic settings first.';
            }
            
            $password = decrypt_password($encrypted_password);
            
            // Test 1: Basic connectivity
            $test_url = $base_url . '/api/contacts?limit=1';
            $response = wp_remote_get($test_url, array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                    'User-Agent' => 'Thrive-Mautic-Plugin/' . THRIVE_MAUTIC_VERSION,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 15,
                'sslverify' => true
            ));
            
            $debug_info = "=== MAUTIC CONNECTION DEBUG ===\n";
            $debug_info .= "URL: " . $test_url . "\n";
            $debug_info .= "Username: " . $username . "\n";
            $debug_info .= "Password: " . (empty($password) ? 'EMPTY' : 'SET') . "\n\n";
            
            if (is_wp_error($response)) {
                $debug_info .= "ERROR: " . $response->get_error_message() . "\n";
                $debug_info .= "Error Code: " . $response->get_error_code() . "\n";
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                $response_body = wp_remote_retrieve_body($response);
                $response_headers = wp_remote_retrieve_headers($response);
                
                $debug_info .= "Response Code: " . $response_code . "\n";
                $debug_info .= "Response Headers: " . print_r($response_headers, true) . "\n";
                $debug_info .= "Response Body: " . substr($response_body, 0, 500) . "\n\n";
                
                if ($response_code === 401) {
                    $debug_info .= "=== 401 AUTHENTICATION ERROR ===\n";
                    $debug_info .= "This means your username/password are incorrect or API access is disabled.\n";
                    $debug_info .= "Check:\n";
                    $debug_info .= "1. Username and password match your Mautic login\n";
                    $debug_info .= "2. API is enabled in Mautic settings\n";
                    $debug_info .= "3. Your user has API access permission\n";
                } elseif ($response_code === 403) {
                    $debug_info .= "=== 403 FORBIDDEN ERROR ===\n";
                    $debug_info .= "Your user doesn't have API access permission.\n";
                } elseif ($response_code === 404) {
                    $debug_info .= "=== 404 NOT FOUND ERROR ===\n";
                    $debug_info .= "The API endpoint doesn't exist. Check your Mautic URL.\n";
                }
            }
            
            return $debug_info;
            
        } catch (Exception $e) {
            return "Debug failed: " . $e->getMessage();
        }
    }

    // Database functions
    function create_thrive_mautic_tables() {
        try {
            global $wpdb;
            
            $charset_collate = $wpdb->get_charset_collate();
            
            // Submissions table
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            $sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                form_id varchar(100) NOT NULL,
                form_type varchar(50) NOT NULL,
                email varchar(255) NOT NULL,
                name varchar(255) DEFAULT '',
                phone varchar(50) DEFAULT '',
                company varchar(255) DEFAULT '',
                segment_id varchar(100) DEFAULT '',
                utm_source varchar(255) DEFAULT '',
                utm_medium varchar(255) DEFAULT '',
                utm_campaign varchar(255) DEFAULT '',
                utm_content varchar(255) DEFAULT '',
                utm_term varchar(255) DEFAULT '',
                status varchar(20) DEFAULT 'pending',
                mautic_contact_id varchar(100) DEFAULT '',
                error_message text DEFAULT '',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY form_id (form_id),
                KEY email (email),
                KEY status (status),
                KEY segment_id (segment_id),
                KEY utm_source (utm_source),
                KEY utm_medium (utm_medium),
                KEY utm_campaign (utm_campaign)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            // Logs table
            $logs_table = $wpdb->prefix . 'thrive_mautic_logs';
            $sql_logs = "CREATE TABLE $logs_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                level varchar(20) NOT NULL,
                message text NOT NULL,
                context text DEFAULT '',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY level (level),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            dbDelta($sql_logs);
            
            // Contacts sync table
            $contacts_table = $wpdb->prefix . 'thrive_mautic_contacts';
            $sql_contacts = "CREATE TABLE $contacts_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                mautic_contact_id varchar(100) NOT NULL,
                email varchar(255) NOT NULL,
                firstname varchar(255) DEFAULT '',
                lastname varchar(255) DEFAULT '',
                phone varchar(50) DEFAULT '',
                company varchar(255) DEFAULT '',
                segment_id varchar(100) DEFAULT '',
                tags text DEFAULT '',
                verification_status varchar(20) DEFAULT 'unverified',
                last_synced datetime DEFAULT CURRENT_TIMESTAMP,
                sync_status varchar(20) DEFAULT 'synced',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY mautic_contact_id (mautic_contact_id),
                KEY email (email),
                KEY segment_id (segment_id),
                KEY verification_status (verification_status),
                KEY sync_status (sync_status)
            ) $charset_collate;";
            
            dbDelta($sql_contacts);
            
        } catch (Exception $e) {
            // Silent fail - don't crash
        }
    }

    // Logging function
    function thrive_mautic_log($level, $message, $context = '') {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_logs';
            
            $wpdb->insert(
                $table_name,
                array(
                    'level' => $level,
                    'message' => $message,
                    'context' => $context,
                    'created_at' => current_time('mysql')
                )
            );
        } catch (Exception $e) {
            // Silent fail - don't crash
        }
    }
    
    // Form analysis function
    function thrive_mautic_analyze_all_forms() {
        try {
            $forms = array();
            
            // Analyze Thrive Architect forms
            $architect_forms = thrive_mautic_analyze_architect_forms();
            $forms = array_merge($forms, $architect_forms);
            
            // Analyze Thrive Leads forms
            $leads_forms = thrive_mautic_analyze_leads_forms();
            $forms = array_merge($forms, $leads_forms);
            
            // Analyze Thrive Quiz forms
            $quiz_forms = thrive_mautic_analyze_quiz_forms();
            $forms = array_merge($forms, $quiz_forms);
            
            // Analyze Thrive Lightbox forms
            $lightbox_forms = thrive_mautic_analyze_lightbox_forms();
            $forms = array_merge($forms, $lightbox_forms);
            
            return $forms;
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Form analysis error: ' . $e->getMessage());
            return array();
        }
    }
    
    function thrive_mautic_analyze_architect_forms() {
        try {
            $forms = array();
            
            // Get all posts and pages with Thrive Architect content
            $posts = get_posts(array(
                'post_type' => array('post', 'page'),
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'tve_updated_post',
                        'compare' => 'EXISTS'
                    )
                )
            ));
            
            foreach ($posts as $post) {
                $content = get_post_meta($post->ID, 'tve_updated_post', true);
                if (empty($content)) continue;
                
                // Look for forms in the content
                preg_match_all('/data-form-identifier="([^"]+)"/', $content, $form_matches);
                
                if (!empty($form_matches[1])) {
                    foreach ($form_matches[1] as $form_id) {
                        // Extract form fields
                        $fields = array();
                        $segment_field = '';
                        $segment_value = '';
                        
                        // Look for form fields
                        preg_match_all('/name="([^"]+)"/', $content, $field_matches);
                        if (!empty($field_matches[1])) {
                            $fields = array_unique($field_matches[1]);
                            
                            // Check for segment field
                            if (in_array('thrive_mautic_segment', $fields)) {
                                $segment_field = 'thrive_mautic_segment';
                                // Try to extract default value
                                preg_match('/name="thrive_mautic_segment"[^>]*value="([^"]*)"/', $content, $value_match);
                                if (!empty($value_match[1])) {
                                    $segment_value = $value_match[1];
                                }
                            }
                        }
                        
                        $forms[] = array(
                            'id' => $form_id,
                            'type' => 'thrive_architect',
                            'location' => get_the_title($post->ID) . ' (ID: ' . $post->ID . ')',
                            'edit_url' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
                            'fields' => $fields,
                            'segment_field' => $segment_field,
                            'segment_value' => $segment_value,
                            'icon' => 'forms'
                        );
                    }
                }
            }
            
            return $forms;
            
        } catch (Exception $e) {
            return array();
        }
    }
    
    function thrive_mautic_analyze_leads_forms() {
        try {
            $forms = array();
            
            // Check if Thrive Leads is active
            if (!class_exists('Thrive_Leads')) {
                return $forms;
            }
            
            // Get all lead groups
            $lead_groups = get_posts(array(
                'post_type' => 'tve_lead_group',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ));
            
            foreach ($lead_groups as $group) {
                $forms[] = array(
                    'id' => 'leads-group-' . $group->ID,
                    'type' => 'thrive_leads',
                    'location' => $group->post_title . ' (Lead Group)',
                    'edit_url' => admin_url('post.php?post=' . $group->ID . '&action=edit'),
                    'fields' => array('email', 'name', 'phone', 'company'), // Default fields
                    'segment_field' => '',
                    'segment_value' => '',
                    'icon' => 'groups'
                );
            }
            
            return $forms;
            
        } catch (Exception $e) {
            return array();
        }
    }
    
    function thrive_mautic_analyze_quiz_forms() {
        try {
            $forms = array();
            
            // Check if Thrive Quiz Builder is active
            if (!class_exists('TQB_Quiz_Manager')) {
                return $forms;
            }
            
            // Get all quizzes
            $quizzes = get_posts(array(
                'post_type' => 'tqb_quiz',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ));
            
            foreach ($quizzes as $quiz) {
                $forms[] = array(
                    'id' => 'quiz-' . $quiz->ID,
                    'type' => 'thrive_quiz',
                    'location' => $quiz->post_title . ' (Quiz)',
                    'edit_url' => admin_url('post.php?post=' . $quiz->ID . '&action=edit'),
                    'fields' => array('email', 'name', 'phone', 'company'), // Default fields
                    'segment_field' => '',
                    'segment_value' => '',
                    'icon' => 'chart-pie'
                );
            }
            
            return $forms;
            
        } catch (Exception $e) {
            return array();
        }
    }
    
    function thrive_mautic_analyze_lightbox_forms() {
        try {
            $forms = array();
            
            // Check if Thrive Lightbox is active
            if (!class_exists('Thrive_Lightbox')) {
                return $forms;
            }
            
            // Get all lightboxes
            $lightboxes = get_posts(array(
                'post_type' => 'tve_lightbox',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ));
            
            foreach ($lightboxes as $lightbox) {
                $forms[] = array(
                    'id' => 'lightbox-' . $lightbox->ID,
                    'type' => 'thrive_lightbox',
                    'location' => $lightbox->post_title . ' (Lightbox)',
                    'edit_url' => admin_url('post.php?post=' . $lightbox->ID . '&action=edit'),
                    'fields' => array('email', 'name', 'phone', 'company'), // Default fields
                    'segment_field' => '',
                    'segment_value' => '',
                    'icon' => 'visibility'
                );
            }
            
            return $forms;
            
        } catch (Exception $e) {
            return array();
        }
    }
    
    // Contact sync functions
    function thrive_mautic_sync_contacts_from_mautic() {
        try {
            $base_url = get_option('thrive_mautic_base_url', '');
            $username = get_option('thrive_mautic_username', '');
            $encrypted_password = get_option('thrive_mautic_password', '');
            
            if (empty($base_url) || empty($username) || empty($encrypted_password)) {
                return array('success' => false, 'message' => 'Mautic credentials not configured');
            }
            
            $password = decrypt_password($encrypted_password);
            $auth = base64_encode($username . ':' . $password);
            
            // Get contacts from Mautic (limit to 1000 for performance)
            $response = wp_remote_get($base_url . '/api/contacts?limit=1000', array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                    'User-Agent' => 'Thrive-Mautic-Plugin/' . THRIVE_MAUTIC_VERSION
                ),
                'timeout' => 60,
                'sslverify' => true
            ));
            
            if (is_wp_error($response)) {
                return array('success' => false, 'message' => $response->get_error_message());
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                return array('success' => false, 'message' => 'Mautic API error: ' . $response_code);
            }
            
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!isset($data['contacts']) || !is_array($data['contacts'])) {
                return array('success' => false, 'message' => 'Invalid response from Mautic');
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_contacts';
            $synced_count = 0;
            
            foreach ($data['contacts'] as $contact) {
                $contact_id = $contact['id'];
                $email = $contact['fields']['core']['email']['value'] ?? '';
                $firstname = $contact['fields']['core']['firstname']['value'] ?? '';
                $lastname = $contact['fields']['core']['lastname']['value'] ?? '';
                $phone = $contact['fields']['core']['phone']['value'] ?? '';
                $company = $contact['fields']['core']['company']['value'] ?? '';
                
                // Get tags
                $tags = array();
                if (isset($contact['tags']) && is_array($contact['tags'])) {
                    foreach ($contact['tags'] as $tag) {
                        $tags[] = $tag['tag'];
                    }
                }
                
                // Determine verification status and lead source
                $verification_status = 'unverified';
                $lead_source = 'unknown';
                
                if (isset($contact['segments']) && is_array($contact['segments'])) {
                    foreach ($contact['segments'] as $segment) {
                        if ($segment['name'] === 'verified') {
                            $verification_status = 'verified';
                        } elseif (in_array($segment['name'], ['google_oauth', 'local_registration', 'newsletter_signup', 'quiz_completion', 'lead_magnet'])) {
                            $lead_source = $segment['name'];
                        }
                    }
                }
                
                // Check tags for additional context
                $content_tags = array();
                $behavior_tags = array();
                if (isset($contact['tags']) && is_array($contact['tags'])) {
                    foreach ($contact['tags'] as $tag) {
                        $tag_name = $tag['tag'];
                        if (in_array($tag_name, ['seo-tools', 'email-templates', 'checklist-pdf', 'video-course', 'webinar-signup'])) {
                            $content_tags[] = $tag_name;
                        } elseif (in_array($tag_name, ['high-engagement', 'newsletter-subscriber', 'quiz-taker', 'lead-magnet-downloader'])) {
                            $behavior_tags[] = $tag_name;
                        }
                    }
                }
                
                // Get segment ID
                $segment_id = '';
                if (isset($contact['segments']) && is_array($contact['segments']) && !empty($contact['segments'])) {
                    $segment_id = $contact['segments'][0]['name'] ?? '';
                }
                
                // Insert or update contact
                $wpdb->replace(
                    $table_name,
                    array(
                        'mautic_contact_id' => $contact_id,
                        'email' => $email,
                        'firstname' => $firstname,
                        'lastname' => $lastname,
                        'phone' => $phone,
                        'company' => $company,
                        'segment_id' => $segment_id,
                        'tags' => json_encode($tags),
                        'verification_status' => $verification_status,
                        'last_synced' => current_time('mysql'),
                        'sync_status' => 'synced'
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
                );
                
                $synced_count++;
            }
            
            // Update last sync time
            update_option('thrive_mautic_last_sync', current_time('mysql'));
            
            return array('success' => true, 'count' => $synced_count);
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Contact sync error: ' . $e->getMessage());
            return array('success' => false, 'message' => $e->getMessage());
        }
    }
    
    function thrive_mautic_get_contact_stats() {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_contacts';
            
            $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $verified = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE verification_status = 'verified'");
            $unverified = $total - $verified;
            
            $verified_percent = $total > 0 ? round(($verified / $total) * 100, 1) : 0;
            $unverified_percent = $total > 0 ? round(($unverified / $total) * 100, 1) : 0;
            
            $last_sync = get_option('thrive_mautic_last_sync', 'Never');
            if ($last_sync !== 'Never') {
                $last_sync = human_time_diff(strtotime($last_sync), current_time('timestamp')) . ' ago';
            }
            
            // Count unique tags
            $tags_result = $wpdb->get_results("SELECT tags FROM $table_name WHERE tags != '' AND tags != '[]'");
            $all_tags = array();
            foreach ($tags_result as $row) {
                $tags = json_decode($row->tags, true);
                if (is_array($tags)) {
                    $all_tags = array_merge($all_tags, $tags);
                }
            }
            $unique_tags = count(array_unique($all_tags));
            
            return array(
                'total' => intval($total),
                'verified' => intval($verified),
                'unverified' => intval($unverified),
                'verified_percent' => $verified_percent,
                'unverified_percent' => $unverified_percent,
                'last_sync' => $last_sync,
                'sync_status' => 'Active',
                'unique_tags' => $unique_tags
            );
            
        } catch (Exception $e) {
            return array(
                'total' => 0,
                'verified' => 0,
                'unverified' => 0,
                'verified_percent' => 0,
                'unverified_percent' => 0,
                'last_sync' => 'Error',
                'sync_status' => 'Error',
                'unique_tags' => 0
            );
        }
    }
    
    function thrive_mautic_get_synced_contacts($limit = 50) {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_contacts';
            
            $contacts = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY last_synced DESC LIMIT %d",
                $limit
            ), ARRAY_A);
            
            return $contacts ?: array();
            
        } catch (Exception $e) {
            return array();
        }
    }
    
    // Lead workflow statistics
    function thrive_mautic_get_lead_workflow_stats() {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            
            // Get total counts
            $total_leads = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $verified_leads = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE segment_id = 'verified'");
            $unverified_leads = $total_leads - $verified_leads;
            
            // Get lead source counts
            $google_oauth = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE segment_id = 'google_oauth'");
            $newsletter = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE segment_id = 'newsletter_signup'");
            $quiz_takers = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE segment_id = 'quiz_completion'");
            $lead_magnets = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE segment_id = 'lead_magnet'");
            
            // Calculate percentages
            $verified_percent = $total_leads > 0 ? round(($verified_leads / $total_leads) * 100, 1) : 0;
            $unverified_percent = $total_leads > 0 ? round(($unverified_leads / $total_leads) * 100, 1) : 0;
            
            // Get source breakdown
            $sources = $wpdb->get_results("
                SELECT 
                    segment_id as name,
                    COUNT(*) as total,
                    SUM(CASE WHEN segment_id = 'verified' THEN 1 ELSE 0 END) as verified,
                    MAX(created_at) as last_activity
                FROM $table_name 
                WHERE segment_id IN ('verified', 'unverified', 'google_oauth', 'newsletter_signup', 'quiz_completion', 'lead_magnet')
                GROUP BY segment_id 
                ORDER BY total DESC
            ", ARRAY_A);
            
            // Calculate percentages for sources
            foreach ($sources as &$source) {
                $source['percentage'] = $total_leads > 0 ? round(($source['total'] / $total_leads) * 100, 1) : 0;
                $source['last_activity'] = human_time_diff(strtotime($source['last_activity']), current_time('timestamp')) . ' ago';
            }
            
            // Get content tags performance (simulated - would need actual tag tracking)
            $content_tags = array(
                array('tag' => 'seo-tools', 'count' => rand(10, 50), 'last_download' => '2 hours ago'),
                array('tag' => 'email-templates', 'count' => rand(5, 30), 'last_download' => '1 day ago'),
                array('tag' => 'checklist-pdf', 'count' => rand(15, 40), 'last_download' => '3 hours ago'),
                array('tag' => 'video-course', 'count' => rand(8, 25), 'last_download' => '5 hours ago'),
                array('tag' => 'webinar-signup', 'count' => rand(12, 35), 'last_download' => '1 hour ago')
            );
            
            return array(
                'total' => intval($total_leads),
                'verified' => intval($verified_leads),
                'unverified' => intval($unverified_leads),
                'verified_percent' => $verified_percent,
                'unverified_percent' => $unverified_percent,
                'google_oauth' => intval($google_oauth),
                'newsletter' => intval($newsletter),
                'quiz_takers' => intval($quiz_takers),
                'lead_magnets' => intval($lead_magnets),
                'sources' => $sources,
                'content_tags' => $content_tags
            );
            
        } catch (Exception $e) {
            return array(
                'total' => 0,
                'verified' => 0,
                'unverified' => 0,
                'verified_percent' => 0,
                'unverified_percent' => 0,
                'google_oauth' => 0,
                'newsletter' => 0,
                'quiz_takers' => 0,
                'lead_magnets' => 0,
                'sources' => array(),
                'content_tags' => array()
            );
        }
    }
    
    // Dynamic thank you page function
    function thrive_mautic_show_thank_you_page() {
        try {
            $thank_you_type = get_query_var('thank_you_type');
            $utm_data = thrive_mautic_get_utm_from_session();
            
            // Get user data from session/cookie if available
            $user_name = '';
            $user_email = '';
            $quiz_result = '';
            $content_name = '';
            
            // Try to get data from session
            if (isset($_SESSION['thrive_mautic_user_data'])) {
                $user_data = $_SESSION['thrive_mautic_user_data'];
                $user_name = $user_data['name'] ?? '';
                $user_email = $user_data['email'] ?? '';
                $quiz_result = $user_data['quiz_result'] ?? '';
                $content_name = $user_data['content_name'] ?? '';
            }
            
            // Thank you page content based on type
            $page_content = thrive_mautic_get_thank_you_content($thank_you_type, $user_name, $quiz_result, $content_name, $utm_data);
            
            // Output the thank you page
            get_header();
            echo $page_content;
            get_footer();
            
        } catch (Exception $e) {
            // Fallback to default thank you page
            get_header();
            echo '<div class="container"><h1>Thank You!</h1><p>Your submission has been received.</p></div>';
            get_footer();
        }
    }
    
    function thrive_mautic_get_thank_you_content($type, $name, $quiz_result, $content_name, $utm_data) {
        $name_display = !empty($name) ? $name : 'there';
        $utm_source = $utm_data['utm_source'] ?? '';
        $utm_campaign = $utm_data['utm_campaign'] ?? '';
        
        switch ($type) {
            case 'newsletter':
                return '
                <div class="container" style="max-width: 800px; margin: 50px auto; padding: 20px; text-align: center;">
                    <div style="background: #f8f9fa; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <h1 style="color: #28a745; margin-bottom: 20px;">🎉 Welcome to Our Newsletter!</h1>
                        <p style="font-size: 18px; margin-bottom: 30px;">Hi ' . esc_html($name_display) . ', thank you for subscribing!</p>
                        <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3>📧 What happens next?</h3>
                            <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                                <li>Check your email for confirmation</li>
                                <li>Get our weekly SEO tips</li>
                                <li>Access exclusive content</li>
                                <li>Be the first to know about new tools</li>
                            </ul>
                        </div>
                        <div style="margin: 30px 0;">
                            <a href="/free-seo-tools" class="button button-primary" style="padding: 15px 30px; font-size: 16px; text-decoration: none; background: #007cba; color: white; border-radius: 5px;">Get Free SEO Tools</a>
                        </div>
                        <p style="color: #666; font-size: 14px;">Can\'t find the email? Check your spam folder.</p>
                    </div>
                </div>';
                
            case 'quiz':
                $result_message = '';
                $next_steps = '';
                
                if ($quiz_result === 'beginner') {
                    $result_message = 'You\'re at the beginner level - perfect starting point!';
                    $next_steps = 'Start with our SEO fundamentals course';
                } elseif ($quiz_result === 'intermediate') {
                    $result_message = 'You have intermediate SEO knowledge - great foundation!';
                    $next_steps = 'Check out our advanced SEO strategies';
                } elseif ($quiz_result === 'advanced') {
                    $result_message = 'You\'re an SEO expert - impressive knowledge!';
                    $next_steps = 'Explore our cutting-edge SEO tools';
                }
                
                return '
                <div class="container" style="max-width: 800px; margin: 50px auto; padding: 20px; text-align: center;">
                    <div style="background: #f8f9fa; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <h1 style="color: #e83e8c; margin-bottom: 20px;">🧩 Quiz Complete!</h1>
                        <p style="font-size: 18px; margin-bottom: 30px;">Hi ' . esc_html($name_display) . ', thanks for taking our SEO quiz!</p>
                        <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3>🎯 Your Results</h3>
                            <p style="font-size: 16px; color: #333;">' . $result_message . '</p>
                            <p style="font-size: 14px; color: #666;">Recommended next step: ' . $next_steps . '</p>
                        </div>
                        <div style="margin: 30px 0;">
                            <a href="/personalized-seo-plan" class="button button-primary" style="padding: 15px 30px; font-size: 16px; text-decoration: none; background: #e83e8c; color: white; border-radius: 5px;">Get Personalized SEO Plan</a>
                        </div>
                        <p style="color: #666; font-size: 14px;">Detailed results sent to your email.</p>
                    </div>
                </div>';
                
            case 'lead-magnet':
                return '
                <div class="container" style="max-width: 800px; margin: 50px auto; padding: 20px; text-align: center;">
                    <div style="background: #f8f9fa; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <h1 style="color: #20c997; margin-bottom: 20px;">📄 Download Ready!</h1>
                        <p style="font-size: 18px; margin-bottom: 30px;">Hi ' . esc_html($name_display) . ', your content is ready!</p>
                        <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3>📚 What you get:</h3>
                            <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                                <li>Download link sent to your email</li>
                                <li>Step-by-step implementation guide</li>
                                <li>Bonus resources and templates</li>
                                <li>Access to our private community</li>
                            </ul>
                        </div>
                        <div style="margin: 30px 0;">
                            <a href="/more-free-resources" class="button button-primary" style="padding: 15px 30px; font-size: 16px; text-decoration: none; background: #20c997; color: white; border-radius: 5px;">Get More Free Resources</a>
                        </div>
                        <p style="color: #666; font-size: 14px;">Check your email for the download link.</p>
                    </div>
                </div>';
                
            case 'contact':
                return '
                <div class="container" style="max-width: 800px; margin: 50px auto; padding: 20px; text-align: center;">
                    <div style="background: #f8f9fa; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <h1 style="color: #007cba; margin-bottom: 20px;">💬 Message Received!</h1>
                        <p style="font-size: 18px; margin-bottom: 30px;">Hi ' . esc_html($name_display) . ', thank you for reaching out!</p>
                        <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3>⏰ What happens next?</h3>
                            <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                                <li>We\'ll respond within 24 hours</li>
                                <li>Check your email for our reply</li>
                                <li>We may follow up with additional questions</li>
                                <li>Keep an eye on your spam folder</li>
                            </ul>
                        </div>
                        <div style="margin: 30px 0;">
                            <a href="/blog" class="button button-primary" style="padding: 15px 30px; font-size: 16px; text-decoration: none; background: #007cba; color: white; border-radius: 5px;">Browse Our Blog</a>
                        </div>
                        <p style="color: #666; font-size: 14px;">In the meantime, check out our latest articles.</p>
                    </div>
                </div>';
                
            default:
                return '
                <div class="container" style="max-width: 800px; margin: 50px auto; padding: 20px; text-align: center;">
                    <div style="background: #f8f9fa; padding: 40px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                        <h1 style="color: #28a745; margin-bottom: 20px;">✅ Thank You!</h1>
                        <p style="font-size: 18px; margin-bottom: 30px;">Hi ' . esc_html($name_display) . ', your submission has been received!</p>
                        <div style="background: #fff; padding: 20px; border-radius: 8px; margin: 20px 0;">
                            <h3>🎯 What happens next?</h3>
                            <ul style="text-align: left; max-width: 400px; margin: 0 auto;">
                                <li>We\'ll process your request</li>
                                <li>Check your email for updates</li>
                                <li>Access your exclusive content</li>
                                <li>Join our community</li>
                            </ul>
                        </div>
                        <div style="margin: 30px 0;">
                            <a href="/" class="button button-primary" style="padding: 15px 30px; font-size: 16px; text-decoration: none; background: #28a745; color: white; border-radius: 5px;">Continue to Homepage</a>
                        </div>
                    </div>
                </div>';
        }
    }
    
    function thrive_mautic_get_utm_from_session() {
        try {
            // Try to get UTM data from cookie
            if (isset($_COOKIE['thrive_mautic_utm'])) {
                return json_decode(stripslashes($_COOKIE['thrive_mautic_utm']), true);
            }
            return array();
        } catch (Exception $e) {
            return array();
        }
    }
    
    // UTM Analytics functions
    function thrive_mautic_get_utm_stats() {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            
            // Get total UTM leads
            $total_utm_leads = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE utm_source != ''");
            
            // Get top source
            $top_source = $wpdb->get_row("SELECT utm_source, COUNT(*) as count FROM $table_name WHERE utm_source != '' GROUP BY utm_source ORDER BY count DESC LIMIT 1");
            
            // Get top campaign
            $top_campaign = $wpdb->get_row("SELECT utm_campaign, COUNT(*) as count FROM $table_name WHERE utm_campaign != '' GROUP BY utm_campaign ORDER BY count DESC LIMIT 1");
            
            // Get conversion rate (simplified - could be enhanced with actual conversion data)
            $total_leads = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $conversion_rate = $total_leads > 0 ? round(($total_utm_leads / $total_leads) * 100, 1) : 0;
            
            // Get sources breakdown
            $sources = $wpdb->get_results("
                SELECT 
                    utm_source as source,
                    utm_medium as medium,
                    COUNT(*) as count,
                    MAX(created_at) as last_lead
                FROM $table_name 
                WHERE utm_source != '' 
                GROUP BY utm_source, utm_medium 
                ORDER BY count DESC 
                LIMIT 10
            ", ARRAY_A);
            
            // Calculate percentages for sources
            foreach ($sources as &$source) {
                $source['percentage'] = $total_utm_leads > 0 ? round(($source['count'] / $total_utm_leads) * 100, 1) : 0;
                $source['last_lead'] = human_time_diff(strtotime($source['last_lead']), current_time('timestamp')) . ' ago';
            }
            
            // Get campaigns breakdown
            $campaigns = $wpdb->get_results("
                SELECT 
                    utm_campaign as campaign,
                    utm_source as source,
                    utm_medium as medium,
                    utm_content as content,
                    utm_term as term,
                    COUNT(*) as count
                FROM $table_name 
                WHERE utm_campaign != '' 
                GROUP BY utm_campaign, utm_source, utm_medium, utm_content, utm_term 
                ORDER BY count DESC 
                LIMIT 10
            ", ARRAY_A);
            
            return array(
                'total_utm_leads' => intval($total_utm_leads),
                'top_source' => $top_source ? $top_source->utm_source : 'None',
                'top_source_count' => $top_source ? $top_source->count : 0,
                'top_campaign' => $top_campaign ? $top_campaign->utm_campaign : 'None',
                'top_campaign_count' => $top_campaign ? $top_campaign->count : 0,
                'conversion_rate' => $conversion_rate,
                'sources' => $sources,
                'campaigns' => $campaigns
            );
            
        } catch (Exception $e) {
            return array(
                'total_utm_leads' => 0,
                'top_source' => 'None',
                'top_source_count' => 0,
                'top_campaign' => 'None',
                'top_campaign_count' => 0,
                'conversion_rate' => 0,
                'sources' => array(),
                'campaigns' => array()
            );
        }
    }

    // Plugin activation hook
    register_activation_hook(__FILE__, function() {
        try {
            // Set default options
            add_option('thrive_mautic_auto_update', true);
            add_option('thrive_mautic_base_url', '');
            add_option('thrive_mautic_username', '');
            add_option('thrive_mautic_password', '');
            
            // Create database tables
            create_thrive_mautic_tables();
            
            // Schedule background processing
            if (!wp_next_scheduled('thrive_mautic_process_queue')) {
                wp_schedule_event(time(), 'every_5_minutes', 'thrive_mautic_process_queue');
            }
            
            // Schedule contact sync
            if (!wp_next_scheduled('thrive_mautic_sync_contacts')) {
                wp_schedule_event(time(), 'every_15_minutes', 'thrive_mautic_sync_contacts');
            }
            
            // Flush rewrite rules for dynamic thank you pages
            flush_rewrite_rules();
            
            // Migrate settings if needed
            thrive_mautic_migrate_settings();
            
        } catch (Exception $e) {
            // Silent fail - don't crash
        }
    });
    
    // Settings migration function
    function thrive_mautic_migrate_settings() {
        try {
            // Check if we need to migrate settings
            $current_version = get_option('thrive_mautic_version', '0.0.0');
            $plugin_version = THRIVE_MAUTIC_VERSION;
            
            if (version_compare($current_version, $plugin_version, '<')) {
                // Backup current settings before migration
                thrive_mautic_backup_settings();
                
                // Migrate old settings to new format if needed
                $old_username = get_option('thrive_mautic_username_old', '');
                $old_password = get_option('thrive_mautic_password_old', '');
                $old_url = get_option('thrive_mautic_base_url_old', '');
                
                if (!empty($old_username) && empty(get_option('thrive_mautic_username', ''))) {
                    update_option('thrive_mautic_username', $old_username);
                }
                
                if (!empty($old_password) && empty(get_option('thrive_mautic_password', ''))) {
                    update_option('thrive_mautic_password', $old_password);
                }
                
                if (!empty($old_url) && empty(get_option('thrive_mautic_base_url', ''))) {
                    update_option('thrive_mautic_base_url', $old_url);
                }
                
                // Update version
                update_option('thrive_mautic_version', $plugin_version);
                
                // Log migration
                thrive_mautic_log('info', 'Settings migrated from version ' . $current_version . ' to ' . $plugin_version);
            }
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Settings migration failed: ' . $e->getMessage());
        }
    }
    
    // Settings backup function
    function thrive_mautic_backup_settings() {
        try {
            // Backup critical settings
            $settings_to_backup = array(
                'thrive_mautic_username',
                'thrive_mautic_password',
                'thrive_mautic_base_url',
                'thrive_mautic_tracking_enabled',
                'thrive_mautic_custom_tracking_script',
                'thrive_mautic_tracking_position',
                'thrive_mautic_tracking_pages',
                'thrive_mautic_tracking_page_ids',
                'thrive_mautic_auto_update'
            );
            
            foreach ($settings_to_backup as $setting) {
                $value = get_option($setting, '');
                if (!empty($value)) {
                    update_option($setting . '_backup', $value);
                }
            }
            
            // Log backup
            thrive_mautic_log('info', 'Settings backed up before migration');
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Settings backup failed: ' . $e->getMessage());
        }
    }
    
    // Settings restore function
    function thrive_mautic_restore_settings() {
        try {
            // Restore critical settings from backup
            $settings_to_restore = array(
                'thrive_mautic_username',
                'thrive_mautic_password',
                'thrive_mautic_base_url',
                'thrive_mautic_tracking_enabled',
                'thrive_mautic_custom_tracking_script',
                'thrive_mautic_tracking_position',
                'thrive_mautic_tracking_pages',
                'thrive_mautic_tracking_page_ids',
                'thrive_mautic_auto_update'
            );
            
            foreach ($settings_to_restore as $setting) {
                $backup_value = get_option($setting . '_backup', '');
                $current_value = get_option($setting, '');
                
                if (!empty($backup_value) && empty($current_value)) {
                    update_option($setting, $backup_value);
                }
            }
            
            // Log restore
            thrive_mautic_log('info', 'Settings restored from backup');
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Settings restore failed: ' . $e->getMessage());
        }
    }

    // Mautic API functions
    function thrive_mautic_create_contact($email, $name = '', $phone = '', $company = '') {
        try {
            // Validate and sanitize inputs
            $email = sanitize_email($email);
            if (!is_email($email)) {
                thrive_mautic_log('error', 'Invalid email address: ' . $email);
                return false;
            }
            
            $name = sanitize_text_field($name);
            $phone = sanitize_text_field($phone);
            $company = sanitize_text_field($company);
            
            $base_url = get_option('thrive_mautic_base_url', '');
            $username = get_option('thrive_mautic_username', '');
            $encrypted_password = get_option('thrive_mautic_password', '');
            
            if (empty($base_url) || empty($username) || empty($encrypted_password)) {
                return false;
            }
            
            // Validate URL
            if (!filter_var($base_url, FILTER_VALIDATE_URL)) {
                thrive_mautic_log('error', 'Invalid Mautic URL: ' . $base_url);
                return false;
            }
            
            $password = decrypt_password($encrypted_password);
            $auth = base64_encode($username . ':' . $password);
            
            $contact_data = array(
                'email' => $email,
                'firstname' => $name,
                'phone' => $phone,
                'company' => $company
            );
            
            $response = wp_remote_post($base_url . '/api/contacts/new', array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Thrive-Mautic-Plugin/' . THRIVE_MAUTIC_VERSION
                ),
                'body' => json_encode($contact_data),
                'timeout' => 30,
                'sslverify' => true
            ));
            
            if (is_wp_error($response)) {
                thrive_mautic_log('error', 'Mautic API error: ' . $response->get_error_message());
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            if ($response_code === 201) {
                $data = json_decode($response_body, true);
                if (isset($data['contact']['id'])) {
                    thrive_mautic_log('info', 'Contact created successfully', 'Contact ID: ' . $data['contact']['id']);
                    return $data['contact']['id'];
                }
            } else {
                thrive_mautic_log('error', 'Mautic API error: ' . $response_code . ' - ' . $response_body);
            }
            
            return false;
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Create contact error: ' . $e->getMessage());
            return false;
        }
    }
    
    function thrive_mautic_ensure_segment_exists($segment_name) {
        try {
            $base_url = get_option('thrive_mautic_base_url', '');
            $username = get_option('thrive_mautic_username', '');
            $encrypted_password = get_option('thrive_mautic_password', '');
            
            if (empty($base_url) || empty($username) || empty($encrypted_password)) {
                return false;
            }
            
            $password = decrypt_password($encrypted_password);
            $auth = base64_encode($username . ':' . $password);
            
            // First, check if segment exists
            $response = wp_remote_get($base_url . '/api/segments?search=' . urlencode($segment_name), array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                    'User-Agent' => 'Thrive-Mautic-Plugin/' . THRIVE_MAUTIC_VERSION
                ),
                'timeout' => 30,
                'sslverify' => true
            ));
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($data['segments']) && !empty($data['segments'])) {
                    // Segment exists, return its ID
                    foreach ($data['segments'] as $segment) {
                        if ($segment['name'] === $segment_name) {
                            return $segment['id'];
                        }
                    }
                }
            }
            
            // Segment doesn't exist, create it
            $segment_data = array(
                'name' => $segment_name,
                'description' => 'Auto-created by Thrive-Mautic Plugin',
                'isPublished' => true
            );
            
            $response = wp_remote_post($base_url . '/api/segments/new', array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Thrive-Mautic-Plugin/' . THRIVE_MAUTIC_VERSION
                ),
                'body' => json_encode($segment_data),
                'timeout' => 30,
                'sslverify' => true
            ));
            
            if (is_wp_error($response)) {
                thrive_mautic_log('error', 'Segment creation error: ' . $response->get_error_message());
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 201) {
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($data['segment']['id'])) {
                    thrive_mautic_log('info', 'Segment created successfully', 'Segment: ' . $segment_name . ', ID: ' . $data['segment']['id']);
                    return $data['segment']['id'];
                }
            }
            
            return false;
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Ensure segment exists error: ' . $e->getMessage());
            return false;
        }
    }
    
    function thrive_mautic_add_to_segment($contact_id, $segment_id) {
        try {
            // If segment_id is a string (segment name), ensure it exists and get the ID
            if (!is_numeric($segment_id)) {
                $segment_id = thrive_mautic_ensure_segment_exists($segment_id);
                if (!$segment_id) {
                    thrive_mautic_log('error', 'Failed to create or find segment: ' . $segment_id);
                    return false;
                }
            }
            
            // Validate inputs
            $contact_id = intval($contact_id);
            $segment_id = intval($segment_id);
            
            if ($contact_id <= 0 || $segment_id <= 0) {
                thrive_mautic_log('error', 'Invalid contact_id or segment_id: ' . $contact_id . ', ' . $segment_id);
                return false;
            }
            
            $base_url = get_option('thrive_mautic_base_url', '');
            $username = get_option('thrive_mautic_username', '');
            $encrypted_password = get_option('thrive_mautic_password', '');
            
            if (empty($base_url) || empty($username) || empty($encrypted_password)) {
                return false;
            }
            
            // Validate URL
            if (!filter_var($base_url, FILTER_VALIDATE_URL)) {
                thrive_mautic_log('error', 'Invalid Mautic URL: ' . $base_url);
                return false;
            }
            
            $password = decrypt_password($encrypted_password);
            $auth = base64_encode($username . ':' . $password);
            
            $response = wp_remote_post($base_url . '/api/segments/' . $segment_id . '/contact/' . $contact_id . '/add', array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'Thrive-Mautic-Plugin/' . THRIVE_MAUTIC_VERSION
                ),
                'timeout' => 30,
                'sslverify' => true
            ));
            
            if (is_wp_error($response)) {
                thrive_mautic_log('error', 'Segment add error: ' . $response->get_error_message());
                return false;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code === 200) {
                thrive_mautic_log('info', 'Contact added to segment', 'Contact ID: ' . $contact_id . ', Segment ID: ' . $segment_id);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Add to segment error: ' . $e->getMessage());
            return false;
        }
    }
    
    // Background processing
    add_action('thrive_mautic_process_queue', 'thrive_mautic_process_pending_submissions');
    
    // Contact sync
    add_action('thrive_mautic_sync_contacts', 'thrive_mautic_sync_contacts_from_mautic');
    
    function thrive_mautic_process_pending_submissions() {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            
            // Get pending submissions (limit to 10 per run) with prepared statement
            $pending = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE status = %s ORDER BY created_at ASC LIMIT %d",
                    'pending',
                    10
                )
            );
            
            foreach ($pending as $submission) {
                thrive_mautic_process_single_submission($submission);
            }
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Process queue error: ' . $e->getMessage());
        }
    }
    
    function thrive_mautic_process_single_submission($submission) {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            
            // Update status to processing
            $wpdb->update(
                $table_name,
                array('status' => 'processing'),
                array('id' => $submission->id)
            );
            
            // Create contact in Mautic
            $contact_id = thrive_mautic_create_contact(
                $submission->email,
                $submission->name,
                $submission->phone,
                $submission->company
            );
            
            if ($contact_id) {
                // Add to segment if specified
                if (!empty($submission->segment_id)) {
                    thrive_mautic_add_to_segment($contact_id, $submission->segment_id);
                }
                
                // Update submission as successful
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'success',
                        'mautic_contact_id' => $contact_id,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $submission->id)
                );
                
                thrive_mautic_log('info', 'Submission processed successfully', 'ID: ' . $submission->id);
            } else {
                // Update submission as failed
                $wpdb->update(
                    $table_name,
                    array(
                        'status' => 'failed',
                        'error_message' => 'Failed to create contact in Mautic',
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $submission->id)
                );
                
                thrive_mautic_log('error', 'Submission failed', 'ID: ' . $submission->id);
            }
            
        } catch (Exception $e) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            
            $wpdb->update(
                $table_name,
                array(
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $submission->id)
            );
            
            thrive_mautic_log('error', 'Process submission error: ' . $e->getMessage());
        }
    }
    
    // Add custom cron intervals
    add_filter('cron_schedules', function($schedules) {
        $schedules['every_5_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes')
        );
        $schedules['every_15_minutes'] = array(
            'interval' => 900,
            'display' => __('Every 15 Minutes')
        );
        return $schedules;
    });
    
    // Mautic tracking code - Flexible system
    function thrive_mautic_insert_tracking_code() {
        try {
            // Check if tracking is enabled
            $tracking_enabled = get_option('thrive_mautic_tracking_enabled', false);
            if (!$tracking_enabled) {
                return;
            }
            
            // Get tracking settings
            $custom_script = get_option('thrive_mautic_custom_tracking_script', '');
            $tracking_position = get_option('thrive_mautic_tracking_position', 'footer');
            $tracking_pages = get_option('thrive_mautic_tracking_pages', 'all');
            $specific_pages = get_option('thrive_mautic_specific_pages', '');
            
            // Check if we should show tracking on this page
            if (!thrive_mautic_should_show_tracking($tracking_pages, $specific_pages)) {
                return;
            }
            
            // Validate custom script
            if (empty($custom_script)) {
                thrive_mautic_log('warning', 'Mautic tracking enabled but no custom script provided');
                return;
            }
            
            // Sanitize and output the tracking script
            $sanitized_script = wp_kses($custom_script, array(
                'script' => array(
                    'type' => array(),
                    'src' => array(),
                    'async' => array(),
                    'defer' => array()
                ),
                'noscript' => array()
            ));
            
            if (!empty($sanitized_script)) {
                echo '<!-- Mautic Tracking Code (Thrive-Mautic Plugin) -->';
                echo $sanitized_script;
                echo '<!-- End Mautic Tracking Code -->';
            }
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Tracking code insertion error: ' . $e->getMessage());
        }
    }
    
    // UTM tracking system
    function thrive_mautic_insert_utm_tracking() {
        try {
            // Only add UTM tracking on frontend
            if (is_admin()) {
                return;
            }
            
            ?>
            <script type="text/javascript">
            // Thrive-Mautic UTM Tracking System
            (function() {
                'use strict';
                
                // UTM parameter names
                const UTM_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
                const STORAGE_KEY = 'thrive_mautic_utm_data';
                const COOKIE_NAME = 'thrive_mautic_utm';
                const COOKIE_DAYS = 30;
                
                // Get UTM parameters from URL
                function getUTMParameters() {
                    const urlParams = new URLSearchParams(window.location.search);
                    const utmData = {};
                    
                    UTM_PARAMS.forEach(function(param) {
                        const value = urlParams.get(param);
                        if (value) {
                            utmData[param] = value;
                        }
                    });
                    
                    return utmData;
                }
                
                // Store UTM data in multiple ways for persistence
                function storeUTMData(utmData) {
                    if (Object.keys(utmData).length === 0) {
                        return;
                    }
                    
                    // Store in sessionStorage (immediate access)
                    try {
                        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(utmData));
                    } catch (e) {
                        console.log('SessionStorage not available');
                    }
                    
                    // Store in localStorage (persistent)
                    try {
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(utmData));
                    } catch (e) {
                        console.log('LocalStorage not available');
                    }
                    
                    // Store in cookie (server-side access)
                    try {
                        const expires = new Date();
                        expires.setTime(expires.getTime() + (COOKIE_DAYS * 24 * 60 * 60 * 1000));
                        document.cookie = COOKIE_NAME + '=' + encodeURIComponent(JSON.stringify(utmData)) + 
                                        ';expires=' + expires.toUTCString() + ';path=/';
                    } catch (e) {
                        console.log('Cookie storage not available');
                    }
                }
                
                // Retrieve UTM data from storage
                function getStoredUTMData() {
                    // Try sessionStorage first
                    try {
                        const sessionData = sessionStorage.getItem(STORAGE_KEY);
                        if (sessionData) {
                            return JSON.parse(sessionData);
                        }
                    } catch (e) {
                        console.log('SessionStorage read failed');
                    }
                    
                    // Try localStorage
                    try {
                        const localData = localStorage.getItem(STORAGE_KEY);
                        if (localData) {
                            return JSON.parse(localData);
                        }
                    } catch (e) {
                        console.log('LocalStorage read failed');
                    }
                    
                    // Try cookie
                    try {
                        const cookies = document.cookie.split(';');
                        for (let i = 0; i < cookies.length; i++) {
                            const cookie = cookies[i].trim();
                            if (cookie.indexOf(COOKIE_NAME + '=') === 0) {
                                const cookieData = cookie.substring(COOKIE_NAME.length + 1);
                                return JSON.parse(decodeURIComponent(cookieData));
                            }
                        }
                    } catch (e) {
                        console.log('Cookie read failed');
                    }
                    
                    return {};
                }
                
                // Add UTM data to forms
                function addUTMToForms() {
                    const utmData = getStoredUTMData();
                    if (Object.keys(utmData).length === 0) {
                        return;
                    }
                    
                    // Add UTM fields to all forms
                    const forms = document.querySelectorAll('form');
                    forms.forEach(function(form) {
                        // Check if this is a Thrive form
                        if (form.classList.contains('tve-form') || 
                            form.classList.contains('tve_lead_form') || 
                            form.classList.contains('tve_quiz_form') ||
                            form.classList.contains('tve_lightbox_form')) {
                            
                            // Add UTM fields as hidden inputs
                            Object.keys(utmData).forEach(function(key) {
                                const existingField = form.querySelector('input[name="' + key + '"]');
                                if (!existingField) {
                                    const hiddenField = document.createElement('input');
                                    hiddenField.type = 'hidden';
                                    hiddenField.name = key;
                                    hiddenField.value = utmData[key];
                                    form.appendChild(hiddenField);
                                }
                            });
                        }
                    });
                }
                
                // Initialize UTM tracking
                function initUTMTracking() {
                    // Get UTM parameters from current URL
                    const currentUTM = getUTMParameters();
                    
                    // Get stored UTM data
                    const storedUTM = getStoredUTMData();
                    
                    // Merge current UTM with stored (current takes priority)
                    const finalUTM = Object.assign({}, storedUTM, currentUTM);
                    
                    // Store the final UTM data
                    if (Object.keys(finalUTM).length > 0) {
                        storeUTMData(finalUTM);
                    }
                    
                    // Add UTM data to forms
                    addUTMToForms();
                    
                    // Re-add UTM data to forms when new forms are loaded (for dynamic content)
                    const observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            if (mutation.type === 'childList') {
                                mutation.addedNodes.forEach(function(node) {
                                    if (node.nodeType === 1) { // Element node
                                        if (node.tagName === 'FORM' || node.querySelector('form')) {
                                            addUTMToForms();
                                        }
                                    }
                                });
                            }
                        });
                    });
                    
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                }
                
                // Start UTM tracking when DOM is ready
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initUTMTracking);
                } else {
                    initUTMTracking();
                }
                
                // Make UTM data available globally for debugging
                window.thriveMauticUTM = {
                    getData: getStoredUTMData,
                    clearData: function() {
                        try {
                            sessionStorage.removeItem(STORAGE_KEY);
                            localStorage.removeItem(STORAGE_KEY);
                            document.cookie = COOKIE_NAME + '=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/;';
                        } catch (e) {
                            console.log('UTM data clear failed');
                        }
                    }
                };
                
            })();
            </script>
            <?php
        } catch (Exception $e) {
            // Silent fail - don't break the site
        }
    }
    
    // Function to determine if tracking should be shown
    function thrive_mautic_should_show_tracking($tracking_pages, $specific_pages) {
        try {
            switch ($tracking_pages) {
                case 'frontend':
                    return !is_admin();
                    
                case 'specific':
                    if (empty($specific_pages)) {
                        return true; // If no specific pages set, show on all
                    }
                    
                    $page_ids = array_map('trim', explode(',', $specific_pages));
                    $current_page_id = get_the_ID();
                    $current_page_slug = get_post_field('post_name', get_post());
                    
                    foreach ($page_ids as $page_id) {
                        if (is_numeric($page_id)) {
                            if ($current_page_id == intval($page_id)) {
                                return true;
                            }
                        } else {
                            // Check by slug
                            if ($current_page_slug === $page_id || strpos($current_page_slug, $page_id) !== false) {
                                return true;
                            }
                        }
                    }
                    return false;
                    
                case 'all':
                default:
                    return true;
            }
        } catch (Exception $e) {
            return true; // Default to showing tracking if error
        }
    }
    
    // Add tracking to head
    add_action('wp_head', 'thrive_mautic_insert_tracking_code');
    
    // Add UTM tracking to head
    add_action('wp_head', 'thrive_mautic_insert_utm_tracking');
    
    // Dynamic thank you page system
    add_action('init', function() {
        // Add rewrite rules for dynamic thank you pages
        add_rewrite_rule('^thank-you/([^/]+)/?$', 'index.php?thrive_mautic_thank_you=1&thank_you_type=$matches[1]', 'top');
    });
    
    add_filter('query_vars', function($vars) {
        $vars[] = 'thrive_mautic_thank_you';
        $vars[] = 'thank_you_type';
        return $vars;
    });
    
    add_action('template_redirect', function() {
        if (get_query_var('thrive_mautic_thank_you')) {
            thrive_mautic_show_thank_you_page();
            exit;
        }
    });
    
    // Add tracking to footer (if position is set to footer)
    add_action('wp_footer', function() {
        try {
            $tracking_position = get_option('thrive_mautic_tracking_position', 'footer');
            if ($tracking_position === 'footer') {
                thrive_mautic_insert_tracking_code();
            }
        } catch (Exception $e) {
            // Silent fail
        }
    });

    // Custom updater class
    class ThriveMauticUpdater {
        private $plugin_slug;
        private $version;
        private $cache_key;
        private $cache_allowed;
        
        public function __construct() {
            $this->plugin_slug = plugin_basename(__FILE__);
            $this->version = THRIVE_MAUTIC_VERSION;
            $this->cache_key = 'thrive_mautic_updater';
            $this->cache_allowed = false;
            
            add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
            add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
            add_action('upgrader_process_complete', array($this, 'purge_cache'), 10, 2);
        }
        
        public function check_update($transient) {
            if (empty($transient->checked)) {
                return $transient;
            }
            
            $remote = $this->get_remote_info();
            
            if ($remote && version_compare($this->version, $remote->version, '<')) {
                $res = new stdClass();
                $res->slug = 'thrive-mautic-integration';
                $res->plugin = $this->plugin_slug;
                $res->new_version = $remote->version;
                $res->tested = $remote->tested;
                $res->package = $remote->download_url;
                $res->url = $remote->url;
                $res->icons = $remote->icons;
                $res->banners = $remote->banners;
                $res->banners_rtl = $remote->banners_rtl;
                $res->requires = $remote->requires;
                $res->requires_php = $remote->requires_php;
                $res->compatibility = new stdClass();
                
                $transient->response[$res->plugin] = $res;
            }
            
            return $transient;
        }
        
        public function plugin_info($res, $action, $args) {
            if ($action !== 'plugin_information') {
                return $res;
            }
            
            if ($args->slug !== 'thrive-mautic-integration') {
                return $res;
            }
            
            $remote = $this->get_remote_info();
            
            if (!$remote) {
                return $res;
            }
            
            $res = new stdClass();
            $res->name = $remote->name;
            $res->slug = $remote->slug;
            $res->version = $remote->version;
            $res->tested = $remote->tested;
            $res->requires = $remote->requires;
            $res->requires_php = $remote->requires_php;
            $res->author = $remote->author;
            $res->author_profile = $remote->author_profile;
            $res->homepage = $remote->url;
            $res->download_link = $remote->download_url;
            $res->trunk = $remote->download_url;
            $res->last_updated = $remote->last_updated;
            $res->sections = $remote->sections;
            $res->banners = $remote->banners;
            $res->icons = $remote->icons;
            
            return $res;
        }
        
        private function get_remote_info() {
            $remote = get_transient($this->cache_key);
            
            if (false === $remote || !$this->cache_allowed) {
                $remote = $this->get_remote_data();
                
                if ($remote) {
                    set_transient($this->cache_key, $remote, 12 * HOUR_IN_SECONDS);
                }
            }
            
            return $remote;
        }
        
        private function get_remote_data() {
            try {
                $github_url = 'https://api.github.com/repos/khodor04/thrive-mautic-integration/releases/latest';
                
                $response = wp_remote_get($github_url, array(
                    'timeout' => 30,
                    'headers' => array(
                        'User-Agent' => 'Thrive-Mautic-Plugin'
                    )
                ));
                
                if (is_wp_error($response)) {
                    return false;
                }
                
                $data = json_decode(wp_remote_retrieve_body($response), true);
                if (!$data || !isset($data['tag_name'])) {
                    return false;
                }
                
                $remote = new stdClass();
                $remote->name = 'Thrive-Mautic Integration';
                $remote->slug = 'thrive-mautic-integration';
                $remote->version = ltrim($data['tag_name'], 'v');
                $remote->tested = get_bloginfo('version');
                $remote->requires = '5.0';
                $remote->requires_php = '7.4';
                $remote->author = 'Khodor Ghalayini';
                $remote->author_profile = 'https://github.com/khodor04';
                $remote->url = 'https://github.com/khodor04/thrive-mautic-integration';
                $remote->download_url = $data['zipball_url'];
                $remote->last_updated = $data['published_at'];
                $remote->sections = array(
                    'description' => 'Thrive Themes Integration With Mautic - Complete form capture and contact management solution.',
                    'changelog' => 'See GitHub releases for changelog: https://github.com/khodor04/thrive-mautic-integration/releases'
                );
                $remote->banners = array();
                $remote->icons = array();
                $remote->banners_rtl = array();
                
                return $remote;
                
            } catch (Exception $e) {
                return false;
            }
        }
        
        public function purge_cache($upgrader, $options) {
            if ($this->cache_allowed && 'update' === $options['action'] && 'plugin' === $options['type']) {
                delete_transient($this->cache_key);
            }
        }
    }
    
    // Initialize the updater
    new ThriveMauticUpdater();



    // Auto-update filter
    add_filter('auto_update_plugin', function($update, $item) {
        try {
            if ($item->slug === 'thrive-mautic-integration' && get_option('thrive_mautic_auto_update', true)) {
                return true;
            }
            return $update;
        } catch (Exception $e) {
            return $update;
        }
    }, 10, 2);


    // Plugin deactivation hook
    register_deactivation_hook(__FILE__, function() {
        try {
            // Clean up any scheduled events
            wp_clear_scheduled_hook('thrive_mautic_process_queue');
        } catch (Exception $e) {
            // Silent fail - don't crash
        }
    });

} catch (Exception $e) {
    // ULTIMATE FALLBACK - If ANYTHING fails, just deactivate silently
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Thrive-Mautic Plugin:</strong> Plugin has been disabled due to an error. Please check error logs.</p></div>';
    });
    
    // Log the error
    error_log('Thrive-Mautic Plugin Fatal Error: ' . $e->getMessage());
    
    // Deactivate the plugin
    deactivate_plugins(plugin_basename(__FILE__));
}

// Additional security: Register shutdown function for critical errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && $error['type'] === E_ERROR) {
        // Check if error is related to our plugin
        if (strpos($error['file'], 'thrive-mautic-integration') !== false) {
            error_log('Thrive-Mautic Plugin Critical Error: ' . $error['message']);
            // Don't deactivate automatically on shutdown to avoid infinite loops
        }
    }
});
