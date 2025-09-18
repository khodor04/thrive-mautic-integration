<?php
/**
 * Plugin Name: Thrive-Mautic Integration
 * Plugin URI: https://yourwebsite.com/thrive-mautic-integration
 * Description: Simplified Thrive Themes integration with comprehensive dashboard
 * Version: 5.0.2
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
    define('THRIVE_MAUTIC_VERSION', '5.0.2');
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
                        echo '<a href="' . admin_url('admin.php?page=thrive-mautic-settings') . '" class="button">Test Connection</a>';
                        echo '</div>';
                        echo '</div>';
                        
                        // Plugin Info
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>Plugin Information</h2>';
                        echo '<p><strong>Version:</strong> ' . THRIVE_MAUTIC_VERSION . '</p>';
                        echo '<p><strong>Status:</strong> Active and ready to capture forms</p>';
                        echo '<p><strong>Form Capture:</strong> Thrive Architect, Thrive Leads, Thrive Quiz Builder</p>';
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
                                $wpdb->query("DELETE FROM {$wpdb->prefix}thrive_mautic_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                                echo '<div class="notice notice-success"><p>Old logs cleared!</p></div>';
                            }
                        }
                        
                        // Get submissions
                        $submissions = $wpdb->get_results(
                            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 50"
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
                        echo '</p>';
                        
                        // Test Connection Result
                        echo '<div id="connection-result" style="margin-top: 15px;"></div>';
                        
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
    
    // Queue submission for background processing
    function thrive_mautic_queue_submission($data) {
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'thrive_mautic_submissions';
            
            $wpdb->insert(
                $table_name,
                array(
                    'form_id' => $data['form_id'],
                    'form_type' => $data['form_type'],
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'company' => $data['company'],
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                )
            );
            
            thrive_mautic_log('info', 'Form submission queued', json_encode($data));
            
        } catch (Exception $e) {
            thrive_mautic_log('error', 'Queue submission error: ' . $e->getMessage());
        }
    }

    // AJAX handler for testing Mautic connection
    add_action('wp_ajax_test_mautic_connection', function() {
        try {
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
            $base_url = get_option('thrive_mautic_base_url', '');
            $username = get_option('thrive_mautic_username', '');
            $encrypted_password = get_option('thrive_mautic_password', '');
            
            if (empty($base_url) || empty($username) || empty($encrypted_password)) {
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
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($contact_data),
                'timeout' => 30
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
            $base_url = get_option('thrive_mautic_base_url', '');
            $username = get_option('thrive_mautic_username', '');
            $encrypted_password = get_option('thrive_mautic_password', '');
            
            if (empty($base_url) || empty($username) || empty($encrypted_password) || empty($segment_id)) {
                return false;
            }
            
            $password = decrypt_password($encrypted_password);
            $auth = base64_encode($username . ':' . $password);
            
            $response = wp_remote_post($base_url . '/api/segments/' . $segment_id . '/contact/' . $contact_id . '/add', array(
                'headers' => array(
                    'Authorization' => 'Basic ' . $auth,
                    'Content-Type' => 'application/json'
                ),
                'timeout' => 30
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
            
            // Get pending submissions (limit to 10 per run)
            $pending = $wpdb->get_results(
                "SELECT * FROM $table_name WHERE status = 'pending' ORDER BY created_at ASC LIMIT 10"
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
        echo '<div class="notice notice-error"><p><strong>Thrive-Mautic Plugin:</strong> Plugin has been disabled due to an error.</p></div>';
    });
    
    // Deactivate the plugin
    deactivate_plugins(plugin_basename(__FILE__));
}
