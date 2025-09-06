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
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Try to decode JSON response
        $decoded_response = json_decode($response_body, true);
        
        if ($response_code >= 200 && $response_code < 300) {
            return $decoded_response;
        } else {
            $error_message = __('API request failed', 'cloa-sync');
            
            if ($decoded_response && isset($decoded_response['message'])) {
                $error_message = $decoded_response['message'];
            } elseif ($decoded_response && isset($decoded_response['error'])) {
                $error_message = $decoded_response['error'];
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
}