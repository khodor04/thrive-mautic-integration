<?php

namespace ThriveMautic;

/**
 * Admin dashboard class
 */
class AdminDashboard {
    
    private $database;
    
    public function __construct() {
        $this->database = new Database();
    }
    
    public function init() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('wp_ajax_get_dashboard_stats', array($this, 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_retry_failed_submissions', array($this, 'ajax_retry_failed'));
        add_action('wp_ajax_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'));
    }
    
    public function add_menu_pages() {
        add_menu_page(
            __('Thrive-Mautic Dashboard', 'thrive-mautic-integration'),
            __('Thrive-Mautic', 'thrive-mautic-integration'),
            'manage_options',
            'thrive-mautic-dashboard',
            array($this, 'render_dashboard'),
            'dashicons-email-alt',
            30
        );
    }
    
    public function render_dashboard() {
        ?>
        <div class="wrap">
            <h1><?php _e('Thrive-Mautic Dashboard', 'thrive-mautic-integration'); ?></h1>
            
            <div id="dashboard-stats">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3><?php _e("Today's Signups", 'thrive-mautic-integration'); ?></h3>
                        <div id="today-signups" style="font-size: 32px; font-weight: bold; color: #27ae60;">-</div>
                    </div>
                    
                    <div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3><?php _e('Success Rate', 'thrive-mautic-integration'); ?></h3>
                        <div id="success-rate" style="font-size: 32px; font-weight: bold; color: #3498db;">-</div>
                    </div>
                    
                    <div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3><?php _e('Pending', 'thrive-mautic-integration'); ?></h3>
                        <div id="pending-count" style="font-size: 32px; font-weight: bold; color: #f39c12;">-</div>
                    </div>
                    
                    <div class="stats-card" style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3><?php _e('Failed', 'thrive-mautic-integration'); ?></h3>
                        <div id="failed-count" style="font-size: 32px; font-weight: bold; color: #e74c3c;">-</div>
                    </div>
                </div>
            </div>
            
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">
                <h2><?php _e('Quick Actions', 'thrive-mautic-integration'); ?></h2>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button id="refresh-stats" class="button"><?php _e('Refresh Stats', 'thrive-mautic-integration'); ?></button>
                    <button id="retry-now" class="button"><?php _e('Retry Failed', 'thrive-mautic-integration'); ?></button>
                    <button id="clear-logs" class="button"><?php _e('Clear Old Logs', 'thrive-mautic-integration'); ?></button>
                </div>
                <div id="action-result" style="margin-top: 10px;"></div>
            </div>
            
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 20px 0;">
                <h2><?php _e('Recent Activity', 'thrive-mautic-integration'); ?></h2>
                <div id="recent-activity">
                    <?php $this->display_recent_activity(); ?>
                </div>
            </div>
        </div>
        
        <script>
        setInterval(refreshDashboardStats, 30000);
        
        function refreshDashboardStats() {
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=get_dashboard_stats&nonce=<?php echo wp_create_nonce("dashboard_stats"); ?>'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const stats = data.data;
                    document.getElementById('today-signups').textContent = stats.today_signups;
                    document.getElementById('success-rate').textContent = stats.success_rate + '%';
                    document.getElementById('pending-count').textContent = stats.pending_count;
                    document.getElementById('failed-count').textContent = stats.failed_count;
                }
            });
        }
        
        document.getElementById('refresh-stats').addEventListener('click', refreshDashboardStats);
        document.getElementById('retry-now').addEventListener('click', retryFailed);
        document.getElementById('clear-logs').addEventListener('click', clearLogs);
        
        function retryFailed() {
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=retry_failed_submissions&nonce=<?php echo wp_create_nonce("retry_failed"); ?>'
            })
            .then(response => response.json())
            .then(data => {
                showMessage(data.data, 'success');
                refreshDashboardStats();
            });
        }
        
        function clearLogs() {
            if (!confirm('<?php _e('Clear logs older than 30 days?', 'thrive-mautic-integration'); ?>')) return;
            
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=clear_logs&nonce=<?php echo wp_create_nonce("clear_logs"); ?>'
            })
            .then(response => response.json())
            .then(data => {
                showMessage(data.data, 'success');
                setTimeout(() => location.reload(), 1000);
            });
        }
        
        function showMessage(message, type) {
            const resultDiv = document.getElementById('action-result');
            resultDiv.innerHTML = `<div class="notice notice-${type}"><p>${message}</p></div>`;
            setTimeout(() => resultDiv.innerHTML = '', 5000);
        }
        
        refreshDashboardStats();
        </script>
        <?php
    }
    
    public function ajax_get_dashboard_stats() {
        if (!Plugin::verify_nonce($_POST['nonce'], 'dashboard_stats')) {
            wp_send_json_error(__('Security check failed', 'thrive-mautic-integration'));
        }
        
        $stats = $this->database->get_dashboard_stats();
        wp_send_json_success($stats);
    }
    
    public function ajax_retry_failed() {
        if (!Plugin::verify_nonce($_POST['nonce'], 'retry_failed')) {
            wp_send_json_error(__('Security check failed', 'thrive-mautic-integration'));
        }
        
        $form_capture = new FormCapture();
        $form_capture->retry_failed_submissions();
        wp_send_json_success(__('Retry process completed', 'thrive-mautic-integration'));
    }
    
    public function ajax_clear_logs() {
        if (!Plugin::verify_nonce($_POST['nonce'], 'clear_logs')) {
            wp_send_json_error(__('Security check failed', 'thrive-mautic-integration'));
        }
        
        $retention_days = Plugin::get_option('retention_days', 90);
        $deleted = $this->database->clean_old_logs($retention_days);
        wp_send_json_success(sprintf(__('Cleared %d old log entries', 'thrive-mautic-integration'), $deleted));
    }
    
    public function add_meta_box() {
        add_meta_box(
            'thrive-mautic-setup',
            __('Smart Mautic Setup', 'thrive-mautic-integration'),
            array($this, 'render_meta_box'),
            array('post', 'page'),
            'side',
            'high'
        );
    }
    
    public function render_meta_box($post) {
        wp_nonce_field('thrive_mautic_meta', 'thrive_mautic_nonce');
        
        $setup = $this->database->get_campaign_setup($post->ID);
        ?>
        <div id="thrive-mautic-container">
            <?php if ($setup): ?>
                <div style="background: #d4edda; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
                    <strong><?php _e('Setup Complete!', 'thrive-mautic-integration'); ?></strong><br>
                    <strong><?php _e('Lead Magnet:', 'thrive-mautic-integration'); ?></strong> <?php echo esc_html($setup->lead_magnet_name); ?><br>
                    <?php if ($setup->optin_segment_id): ?>
                        <strong><?php _e('Opt-in Segment:', 'thrive-mautic-integration'); ?></strong> <code><?php echo esc_html($setup->optin_segment_id); ?></code><br>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div id="setup-form">
                <h4><?php _e('Segment Setup', 'thrive-mautic-integration'); ?></h4>
                
                <table style="width: 100%; margin-bottom: 15px;">
                    <tr>
                        <td><label><strong><?php _e('Lead Magnet Name:', 'thrive-mautic-integration'); ?></strong></label></td>
                    </tr>
                    <tr>
                        <td>
                            <input type="text" name="lead_magnet_name" 
                                   value="<?php echo esc_attr($setup->lead_magnet_name ?? ''); ?>" 
                                   placeholder="<?php _e('e.g., SEO Checklist', 'thrive-mautic-integration'); ?>" 
                                   style="width: 100%;" required>
                        </td>
                    </tr>
                </table>
                
                <button type="button" id="setup-segments" class="button button-primary" style="width: 100%;">
                    <?php echo $setup ? __('Update', 'thrive-mautic-integration') : __('Setup', 'thrive-mautic-integration'); ?> <?php _e('Segments', 'thrive-mautic-integration'); ?>
                </button>
            </div>
            
            <div id="setup-status" style="margin-top: 10px;"></div>
        </div>
        
        <script>
        document.getElementById('setup-segments').addEventListener('click', function() {
            const leadMagnetName = document.querySelector('input[name="lead_magnet_name"]').value;
            
            if (!leadMagnetName) {
                alert('<?php _e('Please enter a lead magnet name', 'thrive-mautic-integration'); ?>');
                return;
            }
            
            this.disabled = true;
            this.textContent = '<?php _e('Setting up segments...', 'thrive-mautic-integration'); ?>';
            
            const formData = new FormData();
            formData.append('action', 'create_segments_flexible');
            formData.append('post_id', '<?php echo $post->ID; ?>');
            formData.append('lead_magnet_name', leadMagnetName);
            formData.append('setup_type', 'create_new');
            formData.append('nonce', '<?php echo wp_create_nonce("create_segments_flexible"); ?>');
            
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('setup-status').innerHTML = '<div style="color: green;"><?php _e('Setup complete!', 'thrive-mautic-integration'); ?></div>';
                    setTimeout(() => location.reload(), 2000);
                } else {
                    document.getElementById('setup-status').innerHTML = '<div style="color: red;"><?php _e('Error:', 'thrive-mautic-integration'); ?> ' + data.data + '</div>';
                }
                this.disabled = false;
                this.textContent = '<?php echo $setup ? __("Update", "thrive-mautic-integration") : __("Setup", "thrive-mautic-integration"); ?> <?php _e("Segments", "thrive-mautic-integration"); ?>';
            });
        });
        </script>
        <?php
    }
    
    public function save_meta_box($post_id) {
        if (!Plugin::verify_nonce($_POST['thrive_mautic_nonce'], 'thrive_mautic_meta')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!Plugin::check_capability('edit_post', $post_id)) {
            return;
        }
    }
    
    private function display_recent_activity() {
        $results = $this->database->get_recent_activity(10);
        
        if (!empty($results)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>' . __('Email', 'thrive-mautic-integration') . '</th><th>' . __('Name', 'thrive-mautic-integration') . '</th><th>' . __('Status', 'thrive-mautic-integration') . '</th><th>' . __('Date', 'thrive-mautic-integration') . '</th></tr></thead><tbody>';
            
            foreach ($results as $row) {
                $status_icons = array(
                    'success' => '✅',
                    'failed' => '❌',
                    'pending' => '⏳'
                );
                
                $icon = $status_icons[$row->status] ?? '❓';
                
                echo "<tr>";
                echo "<td>" . esc_html($row->email) . "</td>";
                echo "<td>" . esc_html($row->name) . "</td>";
                echo "<td>{$icon} " . ucfirst($row->status) . "</td>";
                echo "<td>" . esc_html(date('M j, g:i A', strtotime($row->created_at))) . "</td>";
                echo "</tr>";
            }
            
            echo '</tbody></table>';
        } else {
            echo '<p>' . __('No activity yet.', 'thrive-mautic-integration') . '</p>';
        }
    }
}
