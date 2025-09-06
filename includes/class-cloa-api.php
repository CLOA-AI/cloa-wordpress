<?php
/**
 * CLOA API Client
 */

if (!defined('ABSPATH')) {
    exit;
}

class Cloa_API {
    
    private $api_key;
    private $api_url;
    
    public function __construct() {
        $this->api_key = get_option('cloa_api_key', '');
        $this->api_url = get_option('cloa_api_url', 'https://api.cloa.ai/api/v1');
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key is required', 'cloa-sync'));
        }
        
        // Test with a simple endpoint - we'll use the sync/test endpoint if available
        // For now, let's test by trying to sync a single test product
        $test_data = array(
            'externalId' => 'test-connection',
            'name' => 'Test Connection Product',
            'price' => 0,
            'sku' => 'test-connection',
            'status' => 'active'
        );
        
        $response = $this->sync_product($test_data);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Clean up test product immediately
        $this->delete_product('test-connection');
        
        return true;
    }
    
    /**
     * Sync a single product
     */
    public function sync_product($product_data) {
        return $this->make_request('POST', '/sync/product', $product_data);
    }
    
    /**
     * Bulk sync products
     */
    public function sync_products($products_data) {
        $data = array('products' => $products_data);
        return $this->make_request('POST', '/sync/products', $data);
    }
    
    /**
     * Delete products
     */
    public function delete_products($external_ids) {
        $data = array('externalIds' => $external_ids);
        return $this->make_request('POST', '/sync/products/delete', $data);
    }
    
    /**
     * Delete single product
     */
    public function delete_product($external_id) {
        return $this->delete_products(array($external_id));
    }
    
    /**
     * Make API request
     */
    private function make_request($method, $endpoint, $data = null) {
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', __('API key is not configured', 'cloa-sync'));
        }
        
        $url = rtrim($this->api_url, '/') . $endpoint;
        
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->api_key,
            ),
        );
        
        if ($data && ($method === 'POST' || $method === 'PUT' || $method === 'PATCH')) {
            $args['body'] = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
        }
        
        // Add debug logging
        $this->log_debug('Making API request', array(
            'url' => $url,
            'method' => $method,
            'headers' => array_merge($args['headers'], array('X-API-Key' => substr($this->api_key, 0, 10) . '...')), // Mask API key
            'body_size' => isset($args['body']) ? strlen($args['body']) : 0
        ));
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_debug('WP Remote request failed', array(
                'error' => $response->get_error_message()
            ));
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Add debug logging for response
        $this->log_debug('API response received', array(
            'status_code' => $response_code,
            'response_size' => strlen($response_body),
            'response_preview' => substr($response_body, 0, 500) // First 500 chars
        ));
        
        // Try to decode JSON response
        $decoded_response = json_decode($response_body, true);
        
        if ($response_code >= 200 && $response_code < 300) {
            return $decoded_response;
        } else {
            $error_message = __('API request failed', 'cloa-sync');
            
            // Better error message extraction
            if ($decoded_response) {
                if (isset($decoded_response['message'])) {
                    $error_message = is_array($decoded_response['message']) ? 
                        json_encode($decoded_response['message']) : $decoded_response['message'];
                } elseif (isset($decoded_response['error'])) {
                    $error_message = is_array($decoded_response['error']) ? 
                        json_encode($decoded_response['error']) : $decoded_response['error'];
                } elseif (is_array($decoded_response)) {
                    // If entire response is an array, convert to readable format
                    $error_message = json_encode($decoded_response, JSON_PRETTY_PRINT);
                }
            } elseif (!empty($response_body)) {
                // If JSON decode failed, show raw response
                $error_message = 'Raw response: ' . $response_body;
            }
            
            return new WP_Error('api_error', $error_message . ' (HTTP ' . $response_code . ')');
        }
    }
    
    /**
     * Update API settings
     */
    public function update_settings($api_key, $api_url = '') {
        $this->api_key = sanitize_text_field($api_key);
        update_option('cloa_api_key', $this->api_key);
        
        if (!empty($api_url)) {
            $this->api_url = esc_url_raw($api_url);
            update_option('cloa_api_url', $this->api_url);
        }
    }
    
    /**
     * Get current settings
     */
    public function get_settings() {
        return array(
            'api_key' => $this->api_key,
            'api_url' => $this->api_url,
        );
    }
    
    /**
     * Debug logging
     */
    private function log_debug($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[CLOA API] ' . $message . (empty($context) ? '' : ': ' . json_encode($context)));
        }
    }
}