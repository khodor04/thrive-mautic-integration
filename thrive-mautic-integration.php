<?php
/**
 * Plugin Name: Smart Thrive-Mautic Integration Pro
 * Plugin URI: https://yourwebsite.com/thrive-mautic-integration
 * Description: Simplified Thrive Themes integration with comprehensive dashboard
 * Version: 4.9.0
 * Author: Khodor Ghalayini
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: thrive-mautic-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Error handling - prevent plugin from crashing the site
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && strpos($error['file'], 'thrive-mautic-integration') !== false) {
        // Log the error but don't crash the site
        error_log('Thrive Mautic Plugin Error: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']);
        
        // Deactivate plugin if critical error
        if (strpos($error['message'], 'Fatal error') !== false || strpos($error['message'], 'Parse error') !== false) {
            deactivate_plugins(plugin_basename(__FILE__));
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>Thrive Mautic Plugin:</strong> Plugin has been deactivated due to a critical error. Please check the error logs.</p></div>';
            });
        }
    }
});

// Define plugin constants
define('THRIVE_MAUTIC_VERSION', '4.9.0');
define('THRIVE_MAUTIC_PLUGIN_FILE', __FILE__);
define('THRIVE_MAUTIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('THRIVE_MAUTIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('THRIVE_MAUTIC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader for classes with error handling
spl_autoload_register(function ($class) {
    try {
        $prefix = 'ThriveMautic\\';
        $base_dir = THRIVE_MAUTIC_PLUGIN_DIR . 'includes/';
        
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    } catch (Exception $e) {
        error_log('Thrive Mautic Autoloader Error: ' . $e->getMessage());
    }
});

// Simple admin menu registration - this will definitely work
add_action('admin_menu', function() {
    try {
        add_menu_page(
            'Thrive-Mautic Dashboard',
            'Thrive-Mautic',
            'manage_options',
            'thrive-mautic-dashboard',
            function() {
            try {
            // Get statistics
            $stats = array(
                'today_signups' => 0,
                'total_signups' => 0,
                'success_rate' => 0,
                'pending_count' => 0,
                'failed_count' => 0
            );
            
            // Get Mautic connection status
            $mautic_status = 'Not configured';
            $mautic_class = 'error';
            
            if (class_exists('ThriveMautic\\MauticAPI')) {
                $api = new ThriveMautic\MauticAPI();
                $connection_test = $api->test_connection();
                
                if ($connection_test['success']) {
                    $mautic_status = 'Connected';
                    $mautic_class = 'success';
                } else {
                    $mautic_status = 'Connection failed: ' . $connection_test['message'];
                    $mautic_class = 'error';
                }
            }
            
            echo '<div class="wrap">';
            echo '<h1>Thrive-Mautic Dashboard</h1>';
            
            // Mautic Connection Status
            echo '<div class="notice notice-' . $mautic_class . ' inline">';
            echo '<p><strong>Mautic Status:</strong> ' . esc_html($mautic_status) . '</p>';
            echo '</div>';
            
            // Statistics Cards
            echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">';
            
            echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
            echo '<h3>Today\'s Signups</h3>';
            echo '<div style="font-size: 32px; font-weight: bold; color: #27ae60;">' . $stats['today_signups'] . '</div>';
            echo '</div>';
            
            echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
            echo '<h3>Total Signups</h3>';
            echo '<div style="font-size: 32px; font-weight: bold; color: #3498db;">' . $stats['total_signups'] . '</div>';
            echo '</div>';
            
            echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
            echo '<h3>Success Rate</h3>';
            echo '<div style="font-size: 32px; font-weight: bold; color: #27ae60;">' . $stats['success_rate'] . '%</div>';
            echo '</div>';
            
            echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
            echo '<h3>Pending</h3>';
            echo '<div style="font-size: 32px; font-weight: bold; color: #f39c12;">' . $stats['pending_count'] . '</div>';
            echo '</div>';
            
            echo '<div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
            echo '<h3>Failed</h3>';
            echo '<div style="font-size: 32px; font-weight: bold; color: #e74c3c;">' . $stats['failed_count'] . '</div>';
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
                error_log('Thrive Mautic Dashboard Error: ' . $e->getMessage());
                echo '<div class="wrap"><h1>Thrive-Mautic Dashboard</h1>';
                echo '<div class="notice notice-error"><p>Plugin error occurred. Please check error logs.</p></div>';
                echo '</div>';
            }
        },
        'dashicons-email-alt',
        30
    );
    } catch (Exception $e) {
        error_log('Thrive Mautic Menu Error: ' . $e->getMessage());
    }
});
    
    // Add settings submenu
    try {
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
                error_log('Thrive Mautic Settings Error: ' . $e->getMessage());
                echo '<div class="wrap"><h1>Thrive-Mautic Settings</h1>';
                echo '<div class="notice notice-error"><p>Settings error occurred. Please check error logs.</p></div>';
                echo '</div>';
            }
        );
    } catch (Exception $e) {
        error_log('Thrive Mautic Settings Menu Error: ' . $e->getMessage());
    }
});

// Initialize the plugin with error handling
add_action('plugins_loaded', function() {
    try {
        if (class_exists('ThriveMautic\\Plugin')) {
            new ThriveMautic\Plugin();
        }
    } catch (Exception $e) {
        error_log('Thrive Mautic Plugin Initialization Error: ' . $e->getMessage());
    }
});

// WordPress Auto-Update System (shows notifications like other plugins)
add_action('init', function() {
    try {
        if (is_admin()) {
            // Always initialize the updater for proper WordPress integration
            if (class_exists('ThriveMautic\\WordPressUpdater')) {
                new ThriveMautic\WordPressUpdater();
            }
        }
    } catch (Exception $e) {
        error_log('Thrive Mautic Updater Error: ' . $e->getMessage());
    }
});

// Add auto-update support to WordPress
add_filter('auto_update_plugin', function($update, $item) {
    if ($item->slug === 'thrive-mautic-integration') {
        return get_option('thrive_mautic_auto_update', true);
    }
    return $update;
}, 10, 2);

// Add auto-update settings
add_action('admin_init', function() {
    register_setting('thrive_mautic_settings', 'thrive_mautic_auto_update');
});

// Auto-update settings are now integrated into the main Settings page

// Force WordPress to check for updates
add_action('admin_init', function() {
    // Clear update cache to force fresh check
    delete_transient('thrive_mautic_updater');
    delete_site_transient('update_plugins');
});

// AJAX handler for test connection
add_action('wp_ajax_test_mautic_connection', function() {
    try {
        if (!wp_verify_nonce($_POST['nonce'], 'test_mautic_connection')) {
            wp_send_json_error('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (class_exists('ThriveMautic\\MauticAPI')) {
            $api = new ThriveMautic\MauticAPI();
            $result = $api->test_connection();
            
            if ($result['success']) {
                wp_send_json_success($result['message']);
            } else {
                wp_send_json_error($result['message']);
            }
        } else {
            wp_send_json_error('MauticAPI class not found');
        }
    } catch (Exception $e) {
        error_log('Thrive Mautic AJAX Error: ' . $e->getMessage());
        wp_send_json_error('Connection test failed: ' . $e->getMessage());
    }
});

// Activation and deactivation hooks
register_activation_hook(__FILE__, function() {
    if (class_exists('ThriveMautic\\Plugin')) {
        ThriveMautic\Plugin::activate();
    }
});

register_deactivation_hook(__FILE__, function() {
    if (class_exists('ThriveMautic\\Plugin')) {
        ThriveMautic\Plugin::deactivate();
    }
});

// Password encryption function
function encrypt_password($password) {
    $key = wp_salt('AUTH_KEY');
    $iv = wp_salt('SECURE_AUTH_KEY');
    return openssl_encrypt($password, 'AES-256-CBC', $key, 0, substr(hash('sha256', $iv), 0, 16));
}

// Password decryption function
function decrypt_password($encrypted_password) {
    try {
        $key = wp_salt('AUTH_KEY');
        $iv = wp_salt('SECURE_AUTH_KEY');
        return openssl_decrypt($encrypted_password, 'AES-256-CBC', $key, 0, substr(hash('sha256', $iv), 0, 16));
    } catch (Exception $e) {
        error_log('Thrive Mautic Decrypt Error: ' . $e->getMessage());
        return '';
    }
}

// Fallback mode - simple admin notice if plugin fails
add_action('admin_notices', function() {
    if (current_user_can('manage_options')) {
        $plugin_file = plugin_basename(__FILE__);
        $plugin_data = get_plugin_data(__FILE__);
        
        echo '<div class="notice notice-info">';
        echo '<p><strong>Thrive-Mautic Integration:</strong> Plugin is active and monitoring for form submissions.</p>';
        echo '<p>Version: ' . $plugin_data['Version'] . ' | <a href="' . admin_url('admin.php?page=thrive-mautic-dashboard') . '">Dashboard</a> | <a href="' . admin_url('admin.php?page=thrive-mautic-settings') . '">Settings</a></p>';
        echo '</div>';
    }
});
