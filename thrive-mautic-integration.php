<?php
/**
 * Plugin Name: Thrive-Mautic Integration
 * Plugin URI: https://yourwebsite.com/thrive-mautic-integration
 * Description: Thrive Themes Integration With Mautic
 * Version: 5.7.1
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
    define('THRIVE_MAUTIC_VERSION', '5.7.1');
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
                            // Test connection
                            $connection_test = wp_remote_get($base_url . '/api/contacts', array(
                                'headers' => array(
                                    'Authorization' => 'Basic ' . base64_encode($username . ':' . decrypt_password($encrypted_password))
                                ),
                                'timeout' => 10
                            ));
                            
                            if (!is_wp_error($connection_test) && wp_remote_retrieve_response_code($connection_test) === 200) {
                                $mautic_status = 'Connected';
                                $mautic_class = 'success';
                            } else {
                                $mautic_status = 'Connection failed';
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
                        
                        echo '<p class="submit">';
                        echo '<input type="submit" name="save_settings" class="button-primary" value="Save Settings">';
                        echo '<button type="button" id="test-connection" class="button" style="margin-left: 10px;">Test Mautic Connection</button>';
                        echo '<button type="button" id="force-update-check" class="button" style="margin-left: 10px;">Force Update Check</button>';
                        echo '</p>';
                        
                        // Test Connection Result
                        echo '<div id="connection-result" style="margin-top: 15px;"></div>';
                        
                        // Update Check Result
                        echo '<div id="update-result" style="margin-top: 15px;"></div>';
                        
                        // JavaScript to prevent auto-fill interference
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
            
            // Extract email and name
            $email = '';
            $name = '';
            $phone = '';
            $company = '';
            
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
                    }
                }
            }
            
            if (!empty($email)) {
                thrive_mautic_queue_submission(array(
                    'form_id' => $form_id,
                    'form_type' => 'thrive_architect',
                    'email' => $email,
                    'name' => $name,
                    'phone' => $phone,
                    'company' => $company
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
            
            thrive_mautic_queue_submission(array(
                'form_id' => isset($lightbox_data['form_id']) ? $lightbox_data['form_id'] : 'lightbox_' . $form_type,
                'form_type' => 'thrive_lightbox',
                'email' => sanitize_email($lightbox_data['email']),
                'name' => isset($lightbox_data['name']) ? sanitize_text_field($lightbox_data['name']) : '',
                'phone' => isset($lightbox_data['phone']) ? sanitize_text_field($lightbox_data['phone']) : '',
                'company' => isset($lightbox_data['company']) ? sanitize_text_field($lightbox_data['company']) : ''
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
            
            thrive_mautic_queue_submission(array(
                'form_id' => isset($lead_data['form_id']) ? $lead_data['form_id'] : 'leads_' . $form_type,
                'form_type' => 'thrive_leads',
                'email' => sanitize_email($lead_data['email']),
                'name' => isset($lead_data['name']) ? sanitize_text_field($lead_data['name']) : '',
                'phone' => isset($lead_data['phone']) ? sanitize_text_field($lead_data['phone']) : '',
                'company' => isset($lead_data['company']) ? sanitize_text_field($lead_data['company']) : ''
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
            
            thrive_mautic_queue_submission(array(
                'form_id' => 'quiz_' . $quiz_id,
                'form_type' => 'thrive_quiz',
                'email' => sanitize_email($user_data['email']),
                'name' => isset($user_data['name']) ? sanitize_text_field($user_data['name']) : '',
                'phone' => isset($user_data['phone']) ? sanitize_text_field($user_data['phone']) : '',
                'company' => isset($user_data['company']) ? sanitize_text_field($user_data['company']) : ''
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
            
            $wpdb->insert(
                $table_name,
                array(
                    'form_id' => sanitize_text_field($data['form_id']),
                    'form_type' => sanitize_text_field($data['form_type']),
                    'email' => sanitize_email($data['email']),
                    'name' => sanitize_text_field($data['name']),
                    'phone' => sanitize_text_field($data['phone']),
                    'company' => sanitize_text_field($data['company']),
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
            $response = wp_remote_get($base_url . '/api/contacts', array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
                ),
                'timeout' => 10
            ));
            
            if (is_wp_error($response)) {
                wp_send_json_error('Connection failed: ' . $response->get_error_message());
            } elseif (wp_remote_retrieve_response_code($response) === 200) {
                wp_send_json_success('Connection successful! Mautic is reachable.');
            } else {
                wp_send_json_error('Connection failed. Please check your credentials and URL.');
            }
        } catch (Exception $e) {
            wp_send_json_error('Connection test failed: ' . $e->getMessage());
        }
    });

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
                status varchar(20) DEFAULT 'pending',
                mautic_contact_id varchar(100) DEFAULT '',
                error_message text DEFAULT '',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY form_id (form_id),
                KEY email (email),
                KEY status (status)
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
            
        } catch (Exception $e) {
            // Silent fail - don't crash
        }
    });

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
    
    function thrive_mautic_add_to_segment($contact_id, $segment_id) {
        try {
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
    
    // Add custom cron interval
    add_filter('cron_schedules', function($schedules) {
        $schedules['every_5_minutes'] = array(
            'interval' => 300,
            'display' => __('Every 5 Minutes')
        );
        return $schedules;
    });
    
    // Mautic tracking code
    add_action('wp_head', function() {
        try {
            $base_url = get_option('thrive_mautic_base_url', '');
            if (empty($base_url)) {
                return;
            }
            
            echo '<!-- Mautic Tracking Code -->';
            echo '<script type="text/javascript">';
            echo '(function(w,d,t,u,n,a,m){w["MauticTrackingObject"]=n;';
            echo 'w[n]=w[n]||function(){(w[n].q=w[n].q||[]).push(arguments)},a=d.createElement(t),';
            echo 'm=d.getElementsByTagName(t)[0];a.async=1;a.src=u;m.parentNode.insertBefore(a,m)';
            echo '})(window,document,"script","' . esc_url($base_url) . '/mtc.js","mt");';
            echo '</script>';
            echo '<!-- End Mautic Tracking Code -->';
            
        } catch (Exception $e) {
            // Silent fail - don't crash
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
