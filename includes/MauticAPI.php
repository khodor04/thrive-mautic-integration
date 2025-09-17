<?php

namespace ThriveMautic;

/**
 * Mautic API integration class
 */
class MauticAPI {
    
    /**
     * Mautic URL
     */
    private $mautic_url;
    
    /**
     * Rate limiting
     */
    private $rate_limit_key = 'mautic_rate_limit_';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->mautic_url = Plugin::get_option('mautic_url', 'https://mautic.aipoweredkit.com');
    }
    
    /**
     * Test connection to Mautic
     */
    public function test_connection() {
        try {
            $this->check_rate_limit();
            
            $response = $this->make_request('GET', '/api/contacts/new');
            
            if ($response['success']) {
                return array(
                    'success' => true,
                    'message' => __('Connection successful!', 'thrive-mautic-integration')
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Connection failed: ', 'thrive-mautic-integration') . $response['error']
                );
            }
        } catch (Exception $e) {
            Plugin::log_error('Connection test failed', array('error' => $e->getMessage()));
            return array(
                'success' => false,
                'message' => __('Connection test failed: ', 'thrive-mautic-integration') . $e->getMessage()
            );
        }
    }
    
    /**
     * Create or update contact
     */
    public function create_contact($email, $name, $tags = array()) {
        try {
            $this->check_rate_limit();
            
            $contact_data = array(
                'email' => $email,
                'firstname' => $name,
                'tags' => array_merge($tags, array('wordpress-optin'))
            );
            
            $response = $this->make_request('POST', '/api/contacts/new', $contact_data);
            
            if ($response['success']) {
                return array(
                    'success' => true,
                    'contact_id' => $response['data']['contact']['id'] ?? null,
                    'data' => $response['data']
                );
            } else {
                return array(
                    'success' => false,
                    'error' => $response['error']
                );
            }
        } catch (Exception $e) {
            Plugin::log_error('Contact creation failed', array(
                'email' => $email,
                'error' => $e->getMessage()
            ));
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Add contact to segment
     */
    public function add_contact_to_segment($contact_id, $segment_id) {
        try {
            $this->check_rate_limit();
            
            $response = $this->make_request('POST', "/api/segments/{$segment_id}/contact/{$contact_id}/add");
            
            return array(
                'success' => $response['success'],
                'error' => $response['error'] ?? null
            );
        } catch (Exception $e) {
            Plugin::log_error('Segment assignment failed', array(
                'contact_id' => $contact_id,
                'segment_id' => $segment_id,
                'error' => $e->getMessage()
            ));
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Create segment
     */
    public function create_segment($name, $description = '') {
        try {
            $this->check_rate_limit();
            
            $segment_data = array(
                'name' => $name,
                'alias' => sanitize_title($name),
                'description' => $description ?: "Auto-created: {$name}",
                'isPublished' => true
            );
            
            $response = $this->make_request('POST', '/api/segments/new', $segment_data);
            
            if ($response['success']) {
                // Cache segment info
                $this->cache_segment_info($response['data']['list']['id'], array(
                    'name' => $response['data']['list']['name'],
                    'id' => $response['data']['list']['id']
                ));
                
                return array(
                    'success' => true,
                    'data' => array(
                        'id' => $response['data']['list']['id'],
                        'name' => $response['data']['list']['name']
                    )
                );
            } else {
                return array(
                    'success' => false,
                    'error' => $response['error']
                );
            }
        } catch (Exception $e) {
            Plugin::log_error('Segment creation failed', array(
                'name' => $name,
                'error' => $e->getMessage()
            ));
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get segment info (with caching)
     */
    public function get_segment_info($segment_id) {
        $cache_key = 'mautic_segment_' . $segment_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        try {
            $this->check_rate_limit();
            
            $response = $this->make_request('GET', "/api/segments/{$segment_id}");
            
            if ($response['success']) {
                $info = array(
                    'name' => $response['data']['list']['name'] ?? "Segment {$segment_id}",
                    'id' => $response['data']['list']['id'] ?? $segment_id
                );
                
                // Cache for 1 hour
                $this->cache_segment_info($segment_id, $info);
                
                return $info;
            } else {
                return array('name' => "Segment {$segment_id}", 'id' => $segment_id);
            }
        } catch (Exception $e) {
            Plugin::log_error('Segment info retrieval failed', array(
                'segment_id' => $segment_id,
                'error' => $e->getMessage()
            ));
            return array('name' => "Segment {$segment_id}", 'id' => $segment_id);
        }
    }
    
    /**
     * Process submission to Mautic
     */
    public function process_submission($email, $name, $segment_id = '', $form_type = 'unknown') {
        try {
            // Create/update contact
            $contact_result = $this->create_contact($email, $name, array($form_type));
            
            if (!$contact_result['success']) {
                return $contact_result;
            }
            
            // Add to segment if specified
            if (!empty($segment_id) && !empty($contact_result['contact_id'])) {
                $segment_result = $this->add_contact_to_segment($contact_result['contact_id'], $segment_id);
                
                if (!$segment_result['success']) {
                    Plugin::log_error('Segment assignment failed after contact creation', array(
                        'contact_id' => $contact_result['contact_id'],
                        'segment_id' => $segment_id,
                        'error' => $segment_result['error']
                    ));
                }
            }
            
            return array(
                'success' => true,
                'contact_id' => $contact_result['contact_id'],
                'message' => __('Contact processed successfully', 'thrive-mautic-integration')
            );
            
        } catch (Exception $e) {
            Plugin::log_error('Submission processing failed', array(
                'email' => $email,
                'error' => $e->getMessage()
            ));
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Make API request to Mautic
     */
    private function make_request($method, $endpoint, $data = null) {
        $username = Plugin::get_option('mautic_username');
        $password = $this->get_decrypted_password();
        
        if (empty($username) || empty($password)) {
            return array(
                'success' => false,
                'error' => __('Mautic credentials not configured', 'thrive-mautic-integration')
            );
        }
        
        $headers = array(
            'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
            'Content-Type' => 'application/json'
        );
        
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => $headers
        );
        
        if ($data && in_array($method, array('POST', 'PUT', 'PATCH'))) {
            $args['body'] = json_encode($data);
        }
        
        $url = rtrim($this->mautic_url, '/') . $endpoint;
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'error' => $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 401) {
            return array(
                'success' => false,
                'error' => __('Authentication failed. Check credentials.', 'thrive-mautic-integration')
            );
        }
        
        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'data' => json_decode($body, true)
            );
        } else {
            return array(
                'success' => false,
                'error' => "HTTP {$status_code}: {$body}"
            );
        }
    }
    
    /**
     * Check rate limiting
     */
    private function check_rate_limit() {
        $ip = Plugin::get_client_ip();
        $rate_limit_key = $this->rate_limit_key . $ip;
        $attempts = get_transient($rate_limit_key) ?: 0;
        
        if ($attempts > 10) { // Max 10 attempts per hour
            throw new Exception(__('Rate limit exceeded. Please try again later.', 'thrive-mautic-integration'));
        }
        
        set_transient($rate_limit_key, $attempts + 1, HOUR_IN_SECONDS);
    }
    
    /**
     * Cache segment info
     */
    private function cache_segment_info($segment_id, $info) {
        $cache_key = 'mautic_segment_' . $segment_id;
        set_transient($cache_key, $info, HOUR_IN_SECONDS);
    }
    
    /**
     * Get decrypted password
     */
    private function get_decrypted_password() {
        $encrypted_password = Plugin::get_option('mautic_password');
        
        if (empty($encrypted_password)) {
            return '';
        }
        
        // Try to decrypt
        if (function_exists('openssl_decrypt')) {
            $decrypted = openssl_decrypt($encrypted_password, 'AES-256-CBC', wp_salt());
            if ($decrypted !== false) {
                return $decrypted;
            }
        }
        
        // Fallback to base64 decode
        return base64_decode($encrypted_password);
    }
    
    /**
     * Encrypt password
     */
    public function encrypt_password($password) {
        if (function_exists('openssl_encrypt')) {
            return openssl_encrypt($password, 'AES-256-CBC', wp_salt());
        }
        
        // Fallback to base64 encode
        return base64_encode($password);
    }
}
