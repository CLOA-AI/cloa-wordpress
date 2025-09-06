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
        add_action('cloa_process_sync_batch', array($this, 'process_sync_batch'));
        
        // Hook into product changes
        add_action('woocommerce_update_product', array($this, 'sync_single_product'));
        add_action('woocommerce_new_product', array($this, 'sync_single_product'));
        add_action('wp_trash_post', array($this, 'delete_single_product'));
    }
    
    /**
     * Sync all products (starts background process for large datasets)
     */
    public function sync_products() {
        if (!get_option('cloa_sync_enabled', false)) {
            return new WP_Error('sync_disabled', __('Sync is disabled', 'cloa-sync'));
        }
        
        // Validate API configuration
        $api_key = get_option('cloa_api_key', '');
        $api_url = get_option('cloa_api_url', '');
        
        if (empty($api_key)) {
            $error_msg = __('API key is not configured. Please configure it in WooCommerce > CLOA Sync.', 'cloa-sync');
            update_option('cloa_sync_status', $error_msg);
            return new WP_Error('no_api_key', $error_msg);
        }
        
        if (empty($api_url)) {
            $error_msg = __('API URL is not configured. Please configure it in WooCommerce > CLOA Sync.', 'cloa-sync');
            update_option('cloa_sync_status', $error_msg);
            return new WP_Error('no_api_url', $error_msg);
        }
        
        // Check total number of products to sync
        $total_products = $this->count_products_to_sync();
        
        if ($total_products == 0) {
            update_option('cloa_sync_status', __('No products to sync', 'cloa-sync'));
            return 0;
        }
        
        // For small datasets (<1000), sync directly
        if ($total_products < 1000) {
            return $this->sync_products_direct();
        }
        
        // For large datasets, use background processing
        return $this->start_background_sync($total_products);
    }
    
    /**
     * Count products that need syncing without loading them
     */
    private function count_products_to_sync() {
        $args = array(
            'status' => 'publish',
            'limit' => -1,
            'return' => 'ids', // Only return IDs for counting
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
        
        $product_ids = wc_get_products($args);
        return is_array($product_ids) ? count($product_ids) : 0;
    }
    
    /**
     * Start background sync process
     */
    private function start_background_sync($total_products) {
        // Initialize sync progress
        update_option('cloa_sync_progress', array(
            'total' => $total_products,
            'processed' => 0,
            'batch' => 0,
            'status' => 'running',
            'started' => current_time('mysql')
        ));
        
        update_option('cloa_sync_status', sprintf(__('Starting background sync for %d products...', 'cloa-sync'), $total_products));
        
        // Schedule first batch
        wp_schedule_single_event(time() + 5, 'cloa_process_sync_batch');
        
        return $total_products;
    }
    
    /**
     * Direct sync for small datasets (legacy method)
     */
    private function sync_products_direct() {
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
     * Process a single batch in the background
     */
    public function process_sync_batch() {
        $progress = get_option('cloa_sync_progress', array());
        
        if (empty($progress) || $progress['status'] !== 'running') {
            return; // No sync in progress
        }
        
        $batch_size = 50;
        $current_batch = $progress['batch'];
        $offset = $current_batch * $batch_size;
        
        try {
            // Get products for this batch
            $products = $this->get_products_batch($offset, $batch_size);
            
            if (empty($products)) {
                // No more products, finish sync
                $this->finish_background_sync($progress);
                return;
            }
            
            $mapped_products = array();
            $processed_count = 0;
            
            foreach ($products as $product) {
                $mapped_product = $this->product_mapper->map_product($product);
                if ($mapped_product) {
                    $mapped_products[] = $mapped_product;
                }
                $processed_count++;
            }
            
            // Send batch to API
            if (!empty($mapped_products)) {
                $result = $this->api->sync_products($mapped_products);
                
                if (is_wp_error($result)) {
                    $this->handle_sync_error($progress, $result->get_error_message());
                    return;
                }
                
                // Update last sync timestamp for processed products
                foreach ($products as $product) {
                    update_post_meta($product->get_id(), '_cloa_last_sync', current_time('mysql'));
                }
            }
            
            // Update progress
            $progress['processed'] += $processed_count;
            $progress['batch']++;
            
            $percentage = round(($progress['processed'] / $progress['total']) * 100);
            update_option('cloa_sync_status', sprintf(__('Background sync in progress: %d%% (%d/%d products)', 'cloa-sync'), $percentage, $progress['processed'], $progress['total']));
            update_option('cloa_sync_progress', $progress);
            
            // Schedule next batch if more products remain
            if ($progress['processed'] < $progress['total']) {
                wp_schedule_single_event(time() + 2, 'cloa_process_sync_batch');
            } else {
                $this->finish_background_sync($progress);
            }
            
        } catch (Exception $e) {
            $this->handle_sync_error($progress, $e->getMessage());
        }
    }
    
    /**
     * Get a batch of products for background processing
     */
    private function get_products_batch($offset, $limit) {
        $args = array(
            'status' => 'publish',
            'limit' => $limit,
            'offset' => $offset,
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
        return array_filter($products, array($this, 'should_sync_product'));
    }
    
    /**
     * Finish background sync
     */
    private function finish_background_sync($progress) {
        update_option('cloa_last_sync', current_time('mysql'));
        update_option('cloa_sync_status', sprintf(__('Background sync completed! Synced %d products in %s', 'cloa-sync'), 
            $progress['processed'], 
            human_time_diff(strtotime($progress['started']), current_time('timestamp'))
        ));
        
        // Clean up progress tracking
        delete_option('cloa_sync_progress');
    }
    
    /**
     * Handle sync errors during background processing
     */
    private function handle_sync_error($progress, $error_message) {
        update_option('cloa_sync_status', sprintf(__('Background sync failed at %d%% progress: %s', 'cloa-sync'), 
            round(($progress['processed'] / $progress['total']) * 100), 
            $error_message
        ));
        
        // Mark sync as failed
        $progress['status'] = 'failed';
        update_option('cloa_sync_progress', $progress);
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