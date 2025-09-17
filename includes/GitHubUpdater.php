<?php

namespace ThriveMautic;

/**
 * GitHub Auto-Updater Class
 */
class GitHubUpdater {
    
    private $github_username;
    private $github_repo;
    private $plugin_slug;
    private $plugin_file;
    
    public function __construct() {
        $this->github_username = 'yourusername'; // Change this to your GitHub username
        $this->github_repo = 'thrive-mautic-integration'; // Change this to your repo name
        $this->plugin_slug = 'thrive-mautic-integration';
        $this->plugin_file = THRIVE_MAUTIC_PLUGIN_BASENAME;
        
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_post_install', array($this, 'post_install'), 10, 3);
    }
    
    /**
     * Check for updates
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $remote_version = $this->get_remote_version();
        $local_version = THRIVE_MAUTIC_VERSION;
        
        if (version_compare($local_version, $remote_version, '<')) {
            $obj = new \stdClass();
            $obj->slug = $this->plugin_slug;
            $obj->new_version = $remote_version;
            $obj->url = $this->get_remote_info()->homepage;
            $obj->package = $this->get_download_url();
            
            $transient->response[$this->plugin_file] = $obj;
        }
        
        return $transient;
    }
    
    /**
     * Get plugin info
     */
    public function plugin_info($false, $action, $response) {
        if ($response->slug !== $this->plugin_slug) {
            return $false;
        }
        
        $response = $this->get_remote_info();
        return $response;
    }
    
    /**
     * Post install
     */
    public function post_install($true, $hook_extra, $result) {
        global $wp_filesystem;
        
        $plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname($this->plugin_file);
        $wp_filesystem->move($result['destination'], $plugin_folder);
        $result['destination'] = $plugin_folder;
        
        if (is_plugin_active($this->plugin_file)) {
            activate_plugin($this->plugin_file);
        }
        
        return $result;
    }
    
    /**
     * Get remote version
     */
    private function get_remote_version() {
        $response = wp_remote_get("https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest");
        
        if (is_wp_error($response)) {
            return THRIVE_MAUTIC_VERSION;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response));
        return isset($data->tag_name) ? ltrim($data->tag_name, 'v') : THRIVE_MAUTIC_VERSION;
    }
    
    /**
     * Get remote info
     */
    private function get_remote_info() {
        $response = wp_remote_get("https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest");
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response));
        
        $obj = new \stdClass();
        $obj->slug = $this->plugin_slug;
        $obj->name = 'Smart Thrive-Mautic Integration Pro';
        $obj->version = ltrim($data->tag_name, 'v');
        $obj->author = 'Your Name';
        $obj->homepage = $data->html_url;
        $obj->requires = '5.0';
        $obj->tested = '6.4';
        $obj->requires_php = '7.4';
        $obj->last_updated = $data->published_at;
        $obj->sections = array(
            'description' => 'A comprehensive WordPress plugin that seamlessly integrates Thrive Themes with Mautic marketing automation platform.',
            'changelog' => $data->body
        );
        
        return $obj;
    }
    
    /**
     * Get download URL
     */
    private function get_download_url() {
        $response = wp_remote_get("https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest");
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response));
        
        foreach ($data->assets as $asset) {
            if (strpos($asset->name, '.zip') !== false) {
                return $asset->browser_download_url;
            }
        }
        
        return false;
    }
}
