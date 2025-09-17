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
        $this->mautic_url = rtrim(Plugin::get_option('base_url', ''), '/');
    }
    
    /**
     * Get Mautic credentials
     */
    private function get_credentials() {
        $username = Plugin::get_option('username', '');
        $encrypted_password = Plugin::get_option('password', '');
        $password = !empty($encrypted_password) ? decrypt_password($encrypted_password) : '';
        
        return array(
            'username' => $username,
            'password' => $password,
            'base_url' => $this->mautic_url
        );
    }
    
    /**
     * Test connection to Mautic
     */
    public function test_connection() {
        try {
            $credentials = $this->get_credentials();
            
            if (empty($credentials['username']) || empty($credentials['password']) || empty($credentials['base_url'])) {
                return array(
                    'success' => false,
                    'message' => __('Mautic credentials not configured. Please check your settings.', 'thrive-mautic-integration')
                );
            }
            
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
     * Process form submission (main method called by FormCapture)
     */
    public function process_submission($email, $name, $segment_id = '', $form_type = '') {
        try {
            $credentials = $this->get_credentials();
            
            if (empty($credentials['username']) || empty($credentials['password']) || empty($credentials['base_url'])) {
                return array(
                    'success' => false,
                    'error' => 'Mautic credentials not configured'
                );
            }
            
            // Create contact
            $contact_result = $this->create_contact($email, $name, $form_type);
            
            if (!$contact_result['success']) {
                return $contact_result;
            }
            
            // Add to segment if specified
            if (!empty($segment_id)) {
                $segment_result = $this->add_contact_to_segment($email, $segment_id);
                if (!$segment_result['success']) {
                    Plugin::log_error('Failed to add contact to segment', array(
                        'email' => $email,
                        'segment_id' => $segment_id,
                        'error' => $segment_result['error']
                    ));
                }
            }
            
            return array(
                'success' => true,
                'message' => 'Contact created successfully in Mautic'
            );
            
        } catch (Exception $e) {
            Plugin::log_error('Submission processing failed', array(
                'email' => $email,
                'error' => $e->getMessage()
            ));
            return array(
                'success' => false,
                'error' => 'Mautic API error: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Create contact in Mautic
     */
    public function create_contact($email, $name = '', $form_type = '') {
        try {
            $this->check_rate_limit();
            
            $contact_data = array(
                'email' => $email,
                'firstname' => $name,
                'lastname' => '',
                'tags' => array('thrive-mautic-integration', $form_type)
            );
            
            // Split name if it contains both first and last name
            if (!empty($name) && strpos($name, ' ') !== false) {
                $name_parts = explode(' ', $name, 2);
                $contact_data['firstname'] = $name_parts[0];
                $contact_data['lastname'] = $name_parts[1];
            }
            
            $response = $this->make_request('POST', '/api/contacts/new', $contact_data);
            
            if ($response['success']) {
                return array(
                    'success' => true,
                    'contact_id' => $response['data']['contact']['id'] ?? null,
                    'message' => 'Contact created successfully'
                );
            } else {
                return array(
                    'success' => false,
                    'error' => $response['error']
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Contact creation failed: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Add contact to segment
     */
    public function add_contact_to_segment($email, $segment_id) {
        try {
            $this->check_rate_limit();
            
            // First, get the contact ID by email
            $contact_response = $this->make_request('GET', '/api/contacts?search=' . urlencode($email));
            
            if (!$contact_response['success'] || empty($contact_response['data']['contacts'])) {
                return array(
                    'success' => false,
                    'error' => 'Contact not found in Mautic'
                );
            }
            
            $contact_id = $contact_response['data']['contacts'][0]['id'];
            
            // Add contact to segment
            $response = $this->make_request('POST', "/api/segments/{$segment_id}/contact/{$contact_id}/add");
            
            if ($response['success']) {
                return array(
                    'success' => true,
                    'message' => 'Contact added to segment successfully'
                );
            } else {
                return array(
                    'success' => false,
                    'error' => $response['error']
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Failed to add contact to segment: ' . $e->getMessage()
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
        $credentials = $this->get_credentials();
        
        if (empty($credentials['username']) || empty($credentials['password']) || empty($credentials['base_url'])) {
            return array(
                'success' => false,
                'error' => __('Mautic credentials not configured', 'thrive-mautic-integration')
            );
        }
        
        $headers = array(
            'Authorization' => 'Basic ' . base64_encode($credentials['username'] . ':' . $credentials['password']),
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
        
        $url = rtrim($credentials['base_url'], '/') . $endpoint;
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
