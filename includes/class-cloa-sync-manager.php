<?php
/**
 * CLOA Sync Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class Cloa_Sync_Manager {
    
    private $api;
    private $product_mapper;
    
    public function __construct() {
        $this->api = new Cloa_API();
        $this->product_mapper = new Cloa_Product_Mapper();
        
        // Hook into scheduled events
        add_action('cloa_sync_products', array($this, 'sync_products'));
        
        // Hook into product changes
        add_action('woocommerce_update_product', array($this, 'sync_single_product'));
        add_action('woocommerce_new_product', array($this, 'sync_single_product'));
        add_action('wp_trash_post', array($this, 'delete_single_product'));
    }
    
    /**
     * Sync all products
     */
    public function sync_products() {
        if (!get_option('cloa_sync_enabled', false)) {
            return new WP_Error('sync_disabled', __('Sync is disabled', 'cloa-sync'));
        }
        
        update_option('cloa_sync_status', __('Syncing...', 'cloa-sync'));
        
        try {
            $products = $this->get_products_to_sync();
            
            if (empty($products)) {
                update_option('cloa_sync_status', __('No products to sync', 'cloa-sync'));
                return 0;
            }
            
            // Process products in batches of 50
            $batch_size = 50;
            $batches = array_chunk($products, $batch_size);
            $total_synced = 0;
            
            foreach ($batches as $batch) {
                $mapped_products = array();
                
                foreach ($batch as $product) {
                    $mapped_product = $this->product_mapper->map_product($product);
                    if ($mapped_product) {
                        $mapped_products[] = $mapped_product;
                    }
                }
                
                if (!empty($mapped_products)) {
                    $result = $this->api->sync_products($mapped_products);
                    
                    if (is_wp_error($result)) {
                        update_option('cloa_sync_status', sprintf(__('Sync failed: %s', 'cloa-sync'), $result->get_error_message()));
                        return $result;
                    }
                    
                    $total_synced += count($mapped_products);
                    
                    // Update last sync timestamp for each product
                    foreach ($batch as $product) {
                        update_post_meta($product->get_id(), '_cloa_last_sync', current_time('mysql'));
                    }
                }
                
                // Small delay between batches to avoid overwhelming the API
                sleep(1);
            }
            
            update_option('cloa_last_sync', current_time('mysql'));
            update_option('cloa_sync_status', sprintf(__('Synced %d products', 'cloa-sync'), $total_synced));
            
            return $total_synced;
            
        } catch (Exception $e) {
            $error_message = sprintf(__('Sync error: %s', 'cloa-sync'), $e->getMessage());
            update_option('cloa_sync_status', $error_message);
            return new WP_Error('sync_error', $error_message);
        }
    }
    
    /**
     * Sync single product when updated
     */
    public function sync_single_product($product_id) {
        if (!get_option('cloa_sync_enabled', false)) {
            return;
        }
        
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        
        // Check if product should be synced based on categories
        if (!$this->should_sync_product($product)) {
            return;
        }
        
        $mapped_product = $this->product_mapper->map_product($product);
        if (!$mapped_product) {
            return;
        }
        
        $result = $this->api->sync_product($mapped_product);
        
        if (!is_wp_error($result)) {
            update_post_meta($product_id, '_cloa_last_sync', current_time('mysql'));
        }
    }
    
    /**
     * Delete single product
     */
    public function delete_single_product($post_id) {
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        if (!get_option('cloa_sync_enabled', false)) {
            return;
        }
        
        $external_id = 'wp_' . get_current_blog_id() . '_' . $post_id;
        $this->api->delete_product($external_id);
    }
    
    /**
     * Get products that need to be synced
     */
    private function get_products_to_sync() {
        $args = array(
            'status' => 'publish',
            'limit' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_cloa_last_sync',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => '_cloa_last_sync',
                    'value' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                    'compare' => '<',
                    'type' => 'DATETIME'
                )
            )
        );
        
        // Filter by categories if specified
        $sync_categories = get_option('cloa_sync_categories', array());
        if (!empty($sync_categories)) {
            $args['category'] = $sync_categories;
        }
        
        $products = wc_get_products($args);
        
        // Filter products that should be synced
        $products = array_filter($products, array($this, 'should_sync_product'));
        
        return $products;
    }
    
    /**
     * Check if product should be synced
     */
    private function should_sync_product($product) {
        // Skip if not published
        if ($product->get_status() !== 'publish') {
            return false;
        }
        
        // Skip if no price (optional - might want to sync free products too)
        // if (!$product->get_price()) {
        //     return false;
        // }
        
        // Check category filters
        $sync_categories = get_option('cloa_sync_categories', array());
        if (!empty($sync_categories)) {
            $product_categories = $product->get_category_ids();
            if (empty(array_intersect($product_categories, $sync_categories))) {
                return false;
            }
        }
        
        // Allow filtering by other plugins
        return apply_filters('cloa_should_sync_product', true, $product);
    }
    
    /**
     * Get sync statistics
     */
    public function get_sync_stats() {
        $total_products = wp_count_posts('product')->publish;
        
        // Count synced products
        $synced_products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_cloa_last_sync',
                    'compare' => 'EXISTS'
                )
            ),
            'fields' => 'ids',
            'numberposts' => -1
        ));
        
        return array(
            'total' => $total_products,
            'synced' => count($synced_products),
            'pending' => $total_products - count($synced_products),
            'last_sync' => get_option('cloa_last_sync', ''),
            'status' => get_option('cloa_sync_status', '')
        );
    }
}