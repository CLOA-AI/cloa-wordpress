<?php
/**
 * CLOA Product Mapper - Maps WooCommerce products to CLOA format
 */

if (!defined('ABSPATH')) {
    exit;
}

class Cloa_Product_Mapper {
    
    /**
     * Map WooCommerce product to CLOA format
     */
    public function map_product($product) {
        if (!$product || !is_a($product, 'WC_Product')) {
            return false;
        }
        
        // Generate unique external ID
        $external_id = 'wp_' . get_current_blog_id() . '_' . $product->get_id();
        
        // Get product images
        $images = $this->get_product_images($product);
        
        // Get product categories
        $categories = $this->get_product_categories($product);
        
        // Get product attributes
        $attributes = $this->get_product_attributes($product);
        
        // Map basic product data - only fields expected by CLOA API
        $mapped_product = array(
            'externalId' => $external_id,
            'name' => $product->get_name(),
            'description' => $this->clean_description($product->get_description()),
            'price' => floatval($product->get_price()),
            'currency' => get_woocommerce_currency(),
            'sku' => $product->get_sku() ?: $external_id, // Use external ID as SKU fallback
            'categories' => $this->format_categories_for_api($categories),
            'images' => $images,
            'attributes' => $this->format_attributes_for_api($attributes),
            'stock' => $product->get_stock_quantity(),
            'status' => $product->get_status() === 'publish' ? 'published' : 'draft',
        );
        
        // Add custom metadata with extra product info
        $mapped_product['metadata'] = $this->get_product_metadata($product);
        
        // Allow other plugins to modify the mapped product
        $mapped_product = apply_filters('cloa_mapped_product', $mapped_product, $product);
        
        return $mapped_product;
    }
    
    /**
     * Get product images
     */
    private function get_product_images($product) {
        $images = array();
        
        // Featured image
        $featured_image_id = $product->get_image_id();
        if ($featured_image_id) {
            $image_url = wp_get_attachment_image_url($featured_image_id, 'large');
            if ($image_url) {
                $images[] = array(
                    'url' => $image_url,
                    'alt' => get_post_meta($featured_image_id, '_wp_attachment_image_alt', true),
                    'featured' => true
                );
            }
        }
        
        // Gallery images
        $gallery_ids = $product->get_gallery_image_ids();
        foreach ($gallery_ids as $image_id) {
            $image_url = wp_get_attachment_image_url($image_id, 'large');
            if ($image_url) {
                $images[] = array(
                    'url' => $image_url,
                    'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true),
                    'featured' => false
                );
            }
        }
        
        return $images;
    }
    
    /**
     * Get product categories
     */
    private function get_product_categories($product) {
        $categories = array();
        $category_ids = $product->get_category_ids();
        
        foreach ($category_ids as $category_id) {
            $category = get_term($category_id, 'product_cat');
            if ($category && !is_wp_error($category)) {
                $categories[] = array(
                    'id' => $category->term_id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'parent' => $category->parent
                );
            }
        }
        
        return $categories;
    }
    
    /**
     * Get product attributes
     */
    private function get_product_attributes($product) {
        $attributes = array();
        $product_attributes = $product->get_attributes();
        
        foreach ($product_attributes as $attribute) {
            $attribute_data = array(
                'name' => $attribute->get_name(),
                'visible' => $attribute->get_visible(),
                'variation' => $attribute->get_variation(),
            );
            
            if ($attribute->is_taxonomy()) {
                $terms = $attribute->get_terms();
                $attribute_data['values'] = array();
                foreach ($terms as $term) {
                    $attribute_data['values'][] = $term->name;
                }
            } else {
                $attribute_data['values'] = $attribute->get_options();
            }
            
            $attributes[] = $attribute_data;
        }
        
        return $attributes;
    }
    
    /**
     * Get product variations (for variable products)
     */
    private function get_product_variations($product) {
        $variations = array();
        
        if (!$product->is_type('variable')) {
            return $variations;
        }
        
        $variation_ids = $product->get_children();
        
        foreach ($variation_ids as $variation_id) {
            $variation = wc_get_product($variation_id);
            if (!$variation) {
                continue;
            }
            
            $variation_data = array(
                'id' => $variation->get_id(),
                'sku' => $variation->get_sku(),
                'price' => floatval($variation->get_price()),
                'regularPrice' => floatval($variation->get_regular_price()),
                'salePrice' => $variation->get_sale_price() ? floatval($variation->get_sale_price()) : null,
                'stock' => $variation->get_stock_quantity(),
                'inStock' => $variation->is_in_stock(),
                'attributes' => array(),
                'image' => null
            );
            
            // Variation attributes
            $variation_attributes = $variation->get_variation_attributes();
            foreach ($variation_attributes as $attr_name => $attr_value) {
                $variation_data['attributes'][] = array(
                    'name' => str_replace('attribute_', '', $attr_name),
                    'value' => $attr_value
                );
            }
            
            // Variation image
            $variation_image_id = $variation->get_image_id();
            if ($variation_image_id) {
                $image_url = wp_get_attachment_image_url($variation_image_id, 'large');
                if ($image_url) {
                    $variation_data['image'] = array(
                        'url' => $image_url,
                        'alt' => get_post_meta($variation_image_id, '_wp_attachment_image_alt', true)
                    );
                }
            }
            
            $variations[] = $variation_data;
        }
        
        return $variations;
    }
    
    /**
     * Get grouped products (for grouped products)
     */
    private function get_grouped_products($product) {
        $grouped_products = array();
        
        if (!$product->is_type('grouped')) {
            return $grouped_products;
        }
        
        $children = $product->get_children();
        
        foreach ($children as $child_id) {
            $child_product = wc_get_product($child_id);
            if ($child_product) {
                $grouped_products[] = array(
                    'id' => $child_product->get_id(),
                    'name' => $child_product->get_name(),
                    'price' => floatval($child_product->get_price()),
                    'sku' => $child_product->get_sku(),
                );
            }
        }
        
        return $grouped_products;
    }
    
    /**
     * Get product metadata
     */
    private function get_product_metadata($product) {
        $metadata = array();
        
        // Add some useful WooCommerce specific metadata
        $metadata['woocommerce'] = array(
            'id' => $product->get_id(),
            'type' => $product->get_type(),
            'featured' => $product->is_featured(),
            'catalog_visibility' => $product->get_catalog_visibility(),
            'virtual' => $product->is_virtual(),
            'downloadable' => $product->is_downloadable(),
            'sold_individually' => $product->is_sold_individually(),
            'manage_stock' => $product->get_manage_stock(),
            'backorders' => $product->get_backorders(),
            'low_stock_amount' => $product->get_low_stock_amount(),
            'rating_count' => $product->get_rating_count(),
            'average_rating' => $product->get_average_rating(),
            'review_count' => $product->get_review_count(),
        );
        
        // Add custom fields (if any)
        $custom_fields = get_post_meta($product->get_id());
        $allowed_custom_fields = apply_filters('cloa_sync_custom_fields', array(
            '_featured',
            '_visibility',
            '_product_version'
        ));
        
        foreach ($allowed_custom_fields as $field) {
            if (isset($custom_fields[$field])) {
                $metadata['custom'][$field] = $custom_fields[$field][0];
            }
        }
        
        return $metadata;
    }
    
    /**
     * Clean product description
     */
    private function clean_description($description) {
        if (empty($description)) {
            return '';
        }
        
        // Strip HTML tags but preserve line breaks
        $description = wp_strip_all_tags($description);
        
        // Remove extra whitespace
        $description = preg_replace('/\s+/', ' ', $description);
        
        // Trim
        $description = trim($description);
        
        // Limit length if needed (CLOA might have limits)
        if (strlen($description) > 5000) {
            $description = substr($description, 0, 5000) . '...';
        }
        
        return $description;
    }
}