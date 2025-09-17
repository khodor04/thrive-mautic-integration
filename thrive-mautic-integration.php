<?php
/**
 * Plugin Name: Thrive-Mautic Integration
 * Plugin URI: https://yourwebsite.com/thrive-mautic-integration
 * Description: Simplified Thrive Themes integration with comprehensive dashboard
 * Version: 4.5.1
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
    define('THRIVE_MAUTIC_VERSION', '4.5.1');
    define('THRIVE_MAUTIC_PLUGIN_FILE', __FILE__);
    define('THRIVE_MAUTIC_PLUGIN_DIR', plugin_dir_path(__FILE__));

    // Simple autoloader
    spl_autoload_register(function($class) {
        try {
            if (strpos($class, 'ThriveMautic\\') === 0) {
                $class_file = str_replace('ThriveMautic\\', '', $class);
                $class_file = str_replace('\\', '/', $class_file);
                $file_path = THRIVE_MAUTIC_PLUGIN_DIR . 'includes/' . $class_file . '.php';
                
                if (file_exists($file_path)) {
                    require_once $file_path;
                }
            }
        } catch (Exception $e) {
            // Silent fail - don't crash
        }
    });

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
                        
                        // Statistics
                        echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
                        
                        echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                        echo '<h3>Today\'s Signups</h3>';
                        echo '<div style="font-size: 32px; font-weight: bold; color: #3498db;">0</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                        echo '<h3>Total Signups</h3>';
                        echo '<div style="font-size: 32px; font-weight: bold; color: #3498db;">0</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                        echo '<h3>Success Rate</h3>';
                        echo '<div style="font-size: 32px; font-weight: bold; color: #27ae60;">0%</div>';
                        echo '</div>';
                        
                        echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
                        echo '<h3>Pending</h3>';
                        echo '<div style="font-size: 32px; font-weight: bold; color: #f39c12;">0</div>';
                        echo '</div>';
                        
                        echo '</div>';
                        
                        // Quick Actions
                        echo '<div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">';
                        echo '<h2>Quick Actions</h2>';
                        echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
                        echo '<a href="' . admin_url('admin.php?page=thrive-mautic-settings') . '" class="button button-primary">Configure Mautic Settings</a>';
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
                        echo '<button type="button" id="test-connection" class="button" style="margin-left: 10px;">Test Mautic Connection</button>';
                        echo '</p>';
                        
                        // Test Connection Result
                        echo '<div id="connection-result" style="margin-top: 15px;"></div>';
                        
                        // JavaScript for test connection
                        echo '<script>
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

    // Initialize the plugin with error handling
    add_action('plugins_loaded', function() {
        try {
            if (class_exists('ThriveMautic\\Plugin')) {
                new ThriveMautic\Plugin();
            }
        } catch (Exception $e) {
            // Silent fail - don't crash
        }
    });

    // WordPress updater with error handling
    add_action('init', function() {
        try {
            if (class_exists('ThriveMautic\\WordPressUpdater')) {
                new ThriveMautic\WordPressUpdater();
            }
        } catch (Exception $e) {
            // Silent fail - don't crash
        }
    });

    // Force WordPress to check for updates
    add_action('admin_init', function() {
        try {
            delete_transient('thrive_mautic_updater');
            delete_site_transient('update_plugins');
        } catch (Exception $e) {
            // Silent fail - don't crash
        }
    });

    // Fallback admin notice
    add_action('admin_notices', function() {
        try {
            if (current_user_can('manage_options')) {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p><strong>Thrive-Mautic Plugin</strong> is active! <a href="' . admin_url('admin.php?page=thrive-mautic-dashboard') . '">Go to Dashboard</a> | <a href="' . admin_url('admin.php?page=thrive-mautic-settings') . '">Settings</a></p>';
                echo '</div>';
            }
        } catch (Exception $e) {
            // Silent fail - don't crash
        }
    });

    // Plugin activation hook
    register_activation_hook(__FILE__, function() {
        try {
            // Set default options
            add_option('thrive_mautic_auto_update', true);
            add_option('thrive_mautic_base_url', '');
            add_option('thrive_mautic_username', '');
            add_option('thrive_mautic_password', '');
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
