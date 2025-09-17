<?php

namespace ThriveMautic;

/**
 * Settings management class
 */
class Settings {
    
    /**
     * Mautic API instance
     */
    private $mautic_api;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->mautic_api = new MauticAPI();
    }
    
    /**
     * Initialize settings
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_test_mautic_connection', array($this, 'ajax_test_connection'));
    }
    
    /**
     * Add settings page
     */
    public function add_settings_page() {
        add_submenu_page(
            'thrive-mautic-dashboard',
            __('Settings', 'thrive-mautic-integration'),
            __('Settings', 'thrive-mautic-integration'),
            'manage_options',
            'thrive-mautic-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('thrive_mautic_settings', 'thrive_mautic_mautic_url');
        register_setting('thrive_mautic_settings', 'thrive_mautic_mautic_username');
        register_setting('thrive_mautic_settings', 'thrive_mautic_mautic_password');
        register_setting('thrive_mautic_settings', 'thrive_mautic_default_segment');
        register_setting('thrive_mautic_settings', 'thrive_mautic_retention_days');
        
        // Tracking settings
        register_setting('thrive_mautic_settings', 'thrive_mautic_tracking_enabled');
        register_setting('thrive_mautic_settings', 'thrive_mautic_tracking_code');
        register_setting('thrive_mautic_settings', 'thrive_mautic_tracking_position');
        register_setting('thrive_mautic_settings', 'thrive_mautic_tracking_image');
        register_setting('thrive_mautic_settings', 'thrive_mautic_track_logged_users');
    }
    
    /**
     * Set default options
     */
    public function set_default_options() {
        $defaults = array(
            'mautic_url' => 'https://mautic.aipoweredkit.com',
            'retention_days' => 90,
            'tracking_enabled' => false,
            'tracking_position' => 'wp_head',
            'tracking_image' => false,
            'track_logged_users' => false
        );
        
        foreach ($defaults as $key => $value) {
            if (Plugin::get_option($key) === false) {
                Plugin::update_option($key, $value);
            }
        }
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $mautic_url = Plugin::get_option('mautic_url', 'https://mautic.aipoweredkit.com');
        $username = Plugin::get_option('mautic_username', '');
        $password = Plugin::get_option('mautic_password', '');
        $default_segment = Plugin::get_option('default_segment', '');
        $retention_days = Plugin::get_option('retention_days', 90);
        
        // Tracking settings
        $tracking_enabled = Plugin::get_option('tracking_enabled', false);
        $tracking_code = Plugin::get_option('tracking_code', '');
        $tracking_position = Plugin::get_option('tracking_position', 'wp_head');
        $tracking_image = Plugin::get_option('tracking_image', false);
        $track_logged_users = Plugin::get_option('track_logged_users', false);
        ?>
        <div class="wrap">
            <h1><?php _e('Thrive-Mautic Settings', 'thrive-mautic-integration'); ?></h1>
            
            <div style="background: #e7f3ff; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">
                <h3><?php _e('How This Plugin Works:', 'thrive-mautic-integration'); ?></h3>
                <ol>
                    <li><strong><?php _e('Creates segments automatically', 'thrive-mautic-integration'); ?></strong> <?php _e('when you set up lead magnets', 'thrive-mautic-integration'); ?></li>
                    <li><strong><?php _e('Captures form submissions', 'thrive-mautic-integration'); ?></strong> <?php _e('from all Thrive Themes forms', 'thrive-mautic-integration'); ?></li>
                    <li><strong><?php _e('Sends contacts to Mautic', 'thrive-mautic-integration'); ?></strong> <?php _e('with proper segment assignment', 'thrive-mautic-integration'); ?></li>
                    <li><strong><?php _e('You manually create campaigns', 'thrive-mautic-integration'); ?></strong> <?php _e('in Mautic for maximum flexibility', 'thrive-mautic-integration'); ?></li>
                    <li><strong><?php _e('Provides detailed tracking', 'thrive-mautic-integration'); ?></strong> <?php _e('and error monitoring', 'thrive-mautic-integration'); ?></li>
                </ol>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('thrive_mautic_settings', 'thrive_mautic_settings_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Mautic URL', 'thrive-mautic-integration'); ?></th>
                        <td>
                            <input type="url" name="mautic_url" value="<?php echo esc_attr($mautic_url); ?>" class="regular-text" required />
                            <p class="description"><?php _e('Your Mautic installation URL', 'thrive-mautic-integration'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Mautic Username', 'thrive-mautic-integration'); ?></th>
                        <td>
                            <input type="text" name="mautic_username" value="<?php echo esc_attr($username); ?>" class="regular-text" required />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Mautic Password', 'thrive-mautic-integration'); ?></th>
                        <td>
                            <input type="password" name="mautic_password" value="" class="regular-text" />
                            <p class="description">
                                <?php _e('Leave blank to keep current password. Make sure API access is enabled in Mautic Configuration → API Settings', 'thrive-mautic-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Default Segment ID', 'thrive-mautic-integration'); ?></th>
                        <td>
                            <input type="text" name="default_segment" value="<?php echo esc_attr($default_segment); ?>" class="regular-text" />
                            <p class="description"><?php _e('Used only when no specific segment is configured for a post', 'thrive-mautic-integration'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Data Retention', 'thrive-mautic-integration'); ?></th>
                        <td>
                            <select name="retention_days">
                                <option value="30" <?php selected($retention_days, 30); ?>><?php _e('30 days', 'thrive-mautic-integration'); ?></option>
                                <option value="60" <?php selected($retention_days, 60); ?>><?php _e('60 days', 'thrive-mautic-integration'); ?></option>
                                <option value="90" <?php selected($retention_days, 90); ?>><?php _e('90 days', 'thrive-mautic-integration'); ?></option>
                                <option value="180" <?php selected($retention_days, 180); ?>><?php _e('180 days', 'thrive-mautic-integration'); ?></option>
                                <option value="365" <?php selected($retention_days, 365); ?>><?php _e('1 year', 'thrive-mautic-integration'); ?></option>
                                <option value="0" <?php selected($retention_days, 0); ?>><?php _e('Keep forever', 'thrive-mautic-integration'); ?></option>
                            </select>
                            <p class="description"><?php _e('How long to keep submission logs and error data', 'thrive-mautic-integration'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2><?php _e('Mautic Tracking Settings', 'thrive-mautic-integration'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Tracking', 'thrive-mautic-integration'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="tracking_enabled" value="1" <?php checked($tracking_enabled, 1); ?> />
                                <?php _e('Enable Mautic tracking on your website', 'thrive-mautic-integration'); ?>
                            </label>
                            <p class="description"><?php _e('This will add Mautic tracking code to track visitor behavior', 'thrive-mautic-integration'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Tracking Code', 'thrive-mautic-integration'); ?></th>
                        <td>
                            <input type="text" name="tracking_code" value="<?php echo esc_attr($tracking_code); ?>" class="regular-text" />
                            <p class="description">
                                <?php _e('Get this from Mautic → Settings → Configuration → Tracking. Leave empty to auto-detect.', 'thrive-mautic-integration'); ?>
                                <button type="button" id="get-tracking-code" class="button button-small"><?php _e('Auto-detect', 'thrive-mautic-integration'); ?></button>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Tracking Script Location', 'thrive-mautic-integration'); ?></th>
                        <td>
                            <label style="display: block; margin: 5px 0;">
                                <input type="radio" name="tracking_position" value="wp_head" <?php checked($tracking_position, 'wp_head'); ?> />
                                <?php _e('Added in the wp_head action', 'thrive-mautic-integration'); ?>
                            </label>
                            <p class="description" style="margin-left: 20px;"><?php _e('Inserts the tracking code before the &lt;/head&gt; tag; can be slightly slower since page load is delayed until all scripts are loaded and processed.', 'thrive-mautic-integration'); ?></p>
                            
                            <label style="display: block; margin: 5px 0;">
                                <input type="radio" name="tracking_position" value="wp_footer" <?php checked($tracking_position, 'wp_footer'); ?> />
                                <?php _e('Embedded within the wp_footer action', 'thrive-mautic-integration'); ?>
                            </label>
                            <p class="description" style="margin-left: 20px;"><?php _e('Inserts the tracking code before the &lt;/body&gt; tag; slightly better for performance but may track less reliably if users close the page before the script has loaded.', 'thrive-mautic-integration'); ?></p>
                            
                            <label style="display: block; margin: 5px 0;">
                                <input type="radio" name="tracking_position" value="disabled" <?php checked($tracking_position, 'disabled'); ?> />
                                <?php _e('Visitor will not be tracked when rendering the page', 'thrive-mautic-integration'); ?>
                            </label>
                            <p class="description" style="margin-left: 20px;"><?php _e('Use this option to comply with GDPR regulations. If the visitor accepts cookies you must execute the wpmautic_send() JavaScript function to start tracking.', 'thrive-mautic-integration'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Tracking Image', 'thrive-mautic-integration'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="tracking_image" value="1" <?php checked($tracking_image, 1); ?> />
                                <?php _e('Activate the tracking image when JavaScript is disabled', 'thrive-mautic-integration'); ?>
                            </label>
                            <p class="description" style="color: #d63638;">
                                <strong><?php _e('Warning:', 'thrive-mautic-integration'); ?></strong> 
                                <?php _e('The tracking image will always generate a cookie on the user browser side. If you want to control cookies and comply to GDPR, you must use JavaScript instead.', 'thrive-mautic-integration'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Logged Users', 'thrive-mautic-integration'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="track_logged_users" value="1" <?php checked($track_logged_users, 1); ?> />
                                <?php _e('Track user information for logged-in users', 'thrive-mautic-integration'); ?>
                            </label>
                            <p class="description"><?php _e('Enable this to track logged-in WordPress users in Mautic', 'thrive-mautic-integration'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">
                    <h3><?php _e('Test Connection', 'thrive-mautic-integration'); ?></h3>
                    <p><?php _e('Test your Mautic connection before saving settings:', 'thrive-mautic-integration'); ?></p>
                    <button type="button" id="test-connection" class="button button-primary">
                        <?php _e('Test Mautic Connection', 'thrive-mautic-integration'); ?>
                    </button>
                    <div id="test-result" style="margin-top: 10px;"></div>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">
                    <h2><?php _e('Shortcode Examples', 'thrive-mautic-integration'); ?></h2>
                    <p><?php _e('Use these shortcodes to embed Mautic forms and dynamic content:', 'thrive-mautic-integration'); ?></p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Mautic Form Embed', 'thrive-mautic-integration'); ?></th>
                            <td><code>[mautic type="form" id="1"]</code></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Mautic Dynamic Content', 'thrive-mautic-integration'); ?></th>
                            <td><code>[mautic type="content" slot="slot_name"]Default Text[/mautic]</code></td>
                        </tr>
                    </table>
                </div>
                
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">
                    <h2><?php _e('Setup Instructions', 'thrive-mautic-integration'); ?></h2>
                <ol>
                    <li><strong><?php _e('Configure Mautic API:', 'thrive-mautic-integration'); ?></strong>
                        <ul>
                            <li><?php _e('Go to Mautic → Settings → Configuration → API Settings', 'thrive-mautic-integration'); ?></li>
                            <li><?php _e('Enable "API enabled" and "Enable HTTP basic auth"', 'thrive-mautic-integration'); ?></li>
                            <li><?php _e('Save configuration', 'thrive-mautic-integration'); ?></li>
                        </ul>
                    </li>
                    <li><strong><?php _e('Create lead magnets:', 'thrive-mautic-integration'); ?></strong>
                        <ul>
                            <li><?php _e('Edit any post/page with Thrive forms', 'thrive-mautic-integration'); ?></li>
                            <li><?php _e('Use the "Smart Mautic Setup" meta box', 'thrive-mautic-integration'); ?></li>
                            <li><?php _e('Plugin creates segments automatically', 'thrive-mautic-integration'); ?></li>
                        </ul>
                    </li>
                    <li><strong><?php _e('Set up campaigns in Mautic:', 'thrive-mautic-integration'); ?></strong>
                        <ul>
                            <li><?php _e('Follow the instructions provided in the meta box', 'thrive-mautic-integration'); ?></li>
                            <li><?php _e('Create confirmation emails and automations', 'thrive-mautic-integration'); ?></li>
                            <li><?php _e('Use the segment IDs provided by the plugin', 'thrive-mautic-integration'); ?></li>
                        </ul>
                    </li>
                </ol>
            </div>
        </div>
        
        <script>
        document.getElementById('test-connection').addEventListener('click', function() {
            testConnection(this);
        });
        
        function testConnection(button) {
            button.disabled = true;
            button.textContent = '<?php _e('Testing...', 'thrive-mautic-integration'); ?>';
            
            const formData = new FormData();
            formData.append('action', 'test_mautic_connection');
            formData.append('nonce', '<?php echo wp_create_nonce("test_mautic_connection"); ?>');
            formData.append('mautic_url', document.querySelector('input[name="mautic_url"]').value);
            formData.append('mautic_username', document.querySelector('input[name="mautic_username"]').value);
            formData.append('mautic_password', document.querySelector('input[name="mautic_password"]').value);
            
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('test-result');
                if (data.success) {
                    resultDiv.innerHTML = '<div class="notice notice-success"><p>' + data.data + '</p></div>';
                } else {
                    resultDiv.innerHTML = '<div class="notice notice-error"><p>' + data.data + '</p></div>';
                }
                button.disabled = false;
                button.textContent = '<?php _e('Test Mautic Connection', 'thrive-mautic-integration'); ?>';
            })
            .catch(error => {
                document.getElementById('test-result').innerHTML = '<div class="notice notice-error"><p>Error: ' + error.message + '</p></div>';
                button.disabled = false;
                button.textContent = '<?php _e('Test Mautic Connection', 'thrive-mautic-integration'); ?>';
            });
        }
        </script>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!Plugin::verify_nonce($_POST['thrive_mautic_settings_nonce'], 'thrive_mautic_settings')) {
            wp_die(__('Security check failed', 'thrive-mautic-integration'));
        }
        
        if (!Plugin::check_capability()) {
            wp_die(__('You do not have permission to save settings', 'thrive-mautic-integration'));
        }
        
        $mautic_url = Plugin::sanitize_input($_POST['mautic_url'], 'url');
        $username = Plugin::sanitize_input($_POST['mautic_username'], 'text');
        $password = $_POST['mautic_password'];
        $default_segment = Plugin::sanitize_input($_POST['default_segment'], 'text');
        $retention_days = Plugin::sanitize_input($_POST['retention_days'], 'int');
        
        // Tracking settings
        $tracking_enabled = isset($_POST['tracking_enabled']) ? 1 : 0;
        $tracking_code = Plugin::sanitize_input($_POST['tracking_code'], 'text');
        $tracking_position = Plugin::sanitize_input($_POST['tracking_position'], 'text');
        $tracking_image = isset($_POST['tracking_image']) ? 1 : 0;
        $track_logged_users = isset($_POST['track_logged_users']) ? 1 : 0;
        
        Plugin::update_option('mautic_url', $mautic_url);
        Plugin::update_option('mautic_username', $username);
        
        // Only update password if provided
        if (!empty($password)) {
            $encrypted_password = $this->mautic_api->encrypt_password($password);
            Plugin::update_option('mautic_password', $encrypted_password);
        }
        
        Plugin::update_option('default_segment', $default_segment);
        Plugin::update_option('retention_days', $retention_days);
        
        // Save tracking settings
        Plugin::update_option('tracking_enabled', $tracking_enabled);
        Plugin::update_option('tracking_code', $tracking_code);
        Plugin::update_option('tracking_position', $tracking_position);
        Plugin::update_option('tracking_image', $tracking_image);
        Plugin::update_option('track_logged_users', $track_logged_users);
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'thrive-mautic-integration') . '</p></div>';
    }
    
    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        if (!Plugin::verify_nonce($_POST['nonce'], 'test_mautic_connection')) {
            wp_send_json_error(__('Security check failed', 'thrive-mautic-integration'));
        }
        
        if (!Plugin::check_capability()) {
            wp_send_json_error(__('You do not have permission to test connection', 'thrive-mautic-integration'));
        }
        
        // Temporarily update settings for test
        $original_url = Plugin::get_option('mautic_url');
        $original_username = Plugin::get_option('mautic_username');
        $original_password = Plugin::get_option('mautic_password');
        
        Plugin::update_option('mautic_url', Plugin::sanitize_input($_POST['mautic_url'], 'url'));
        Plugin::update_option('mautic_username', Plugin::sanitize_input($_POST['mautic_username'], 'text'));
        
        if (!empty($_POST['mautic_password'])) {
            $encrypted_password = $this->mautic_api->encrypt_password($_POST['mautic_password']);
            Plugin::update_option('mautic_password', $encrypted_password);
        }
        
        // Test connection
        $result = $this->mautic_api->test_connection();
        
        // Restore original settings
        Plugin::update_option('mautic_url', $original_url);
        Plugin::update_option('mautic_username', $original_username);
        Plugin::update_option('mautic_password', $original_password);
        
        if ($result['success']) {
            wp_send_json_success($result['message']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
}
