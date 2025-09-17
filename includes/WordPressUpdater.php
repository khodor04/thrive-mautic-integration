<?php
namespace ThriveMautic;

/**
 * WordPress Auto-Update System
 * Works like standard WordPress plugins with update notifications
 */
class WordPressUpdater {
    
    private $plugin_slug;
    private $version;
    private $cache_key;
    private $cache_allowed;
    
    public function __construct() {
        $this->plugin_slug = plugin_basename(THRIVE_MAUTIC_PLUGIN_FILE);
        $this->version = THRIVE_MAUTIC_VERSION;
        $this->cache_key = 'thrive_mautic_updater';
        $this->cache_allowed = true;
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
        add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
        
        // Add auto-update support
        add_filter('auto_update_plugin', array($this, 'auto_update_plugin'), 10, 2);
    }
    
    /**
     * Enable auto-updates for this plugin
     */
    public function auto_update_plugin($update, $item) {
        if ($item->slug === 'thrive-mautic-integration') {
            return get_option('thrive_mautic_auto_update', true);
        }
        return $update;
    }
    
    /**
     * Check for updates from GitHub
     */
    public function check_for_updates() {
        $remote = get_transient($this->cache_key);
        
        if (false === $remote || !$this->cache_allowed) {
            $remote = wp_remote_get(
                'https://api.github.com/repos/khodor04/thrive-mautic-integration/releases/latest',
                array(
                    'timeout' => 10,
                    'headers' => array(
                        'Accept' => 'application/vnd.github.v3+json',
                    )
                )
            );
            
            if (is_wp_error($remote)) {
                return false;
            }
            
            $remote = json_decode(wp_remote_retrieve_body($remote));
            
            if ($remote) {
                set_transient($this->cache_key, $remote, 12 * HOUR_IN_SECONDS);
            }
        }
        
        return $remote;
    }
    
    /**
     * Modify the update transient
     */
    public function modify_transient($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote = $this->check_for_updates();
        
        if ($remote && version_compare($this->version, $remote->tag_name, '<')) {
            $res = new \stdClass();
            $res->slug = 'thrive-mautic-integration';
            $res->plugin = $this->plugin_slug;
            $res->new_version = $remote->tag_name;
            $res->tested = '6.4';
            $res->package = $remote->zipball_url;
            
            $transient->response[$res->plugin] = $res;
        }
        
        return $transient;
    }
    
    /**
     * Plugin popup information
     */
    public function plugin_popup($result, $action, $args) {
        if (!empty($args->slug) && $args->slug === 'thrive-mautic-integration') {
            $remote = $this->check_for_updates();
            
            if ($remote) {
                $res = new \stdClass();
                $res->slug = 'thrive-mautic-integration';
                $res->plugin = $this->plugin_slug;
                $res->name = 'Smart Thrive-Mautic Integration Pro';
                $res->version = $remote->tag_name;
                $res->tested = '6.4';
                $res->requires = '5.0';
                $res->requires_php = '7.4';
                $res->last_updated = $remote->published_at;
                $res->sections = array(
                    'description' => 'Complete Thrive Themes integration with Mautic marketing automation.',
                    'changelog' => $remote->body
                );
                $res->download_link = $remote->zipball_url;
                
                return $res;
            }
        }
        
        return $result;
    }
    
    /**
     * After plugin install
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        $install_directory = plugin_dir_path(THRIVE_MAUTIC_PLUGIN_FILE);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;
        
        if (is_plugin_active($this->plugin_slug)) {
            activate_plugin($this->plugin_slug);
        }
        
        return $result;
    }
}
