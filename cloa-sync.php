<?php
/**
 * Plugin Name: CLOA Product Sync
 * Plugin URI: https://cloa.ai
 * Description: Sync your WooCommerce products with CLOA AI recommendation engine
 * Version: 1.0.0
 * Author: CLOA AI
 * Author URI: https://cloa.ai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cloa-sync
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CLOA_SYNC_VERSION', '1.0.0');
define('CLOA_SYNC_PLUGIN_FILE', __FILE__);
define('CLOA_SYNC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('CLOA_SYNC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CLOA_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
function cloa_sync_woocommerce_missing_notice() {
    echo '<div class="notice notice-error"><p>' . 
         __('CLOA Product Sync requires WooCommerce to be installed and active.', 'cloa-sync') . 
         '</p></div>';
}

// Main plugin class
class CloaSync {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', 'cloa_sync_woocommerce_missing_notice');
            return;
        }
        
        // Load plugin components
        $this->includes();
        $this->init_hooks();
    }
    
    private function includes() {
        require_once CLOA_SYNC_PLUGIN_PATH . 'includes/class-cloa-api.php';
        require_once CLOA_SYNC_PLUGIN_PATH . 'includes/class-cloa-admin.php';
        require_once CLOA_SYNC_PLUGIN_PATH . 'includes/class-cloa-sync-manager.php';
        require_once CLOA_SYNC_PLUGIN_PATH . 'includes/class-cloa-product-mapper.php';
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        
        // Initialize admin interface
        if (is_admin()) {
            new Cloa_Admin();
        }
        
        // Initialize sync manager
        new Cloa_Sync_Manager();
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('cloa-sync', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

// Initialize the plugin
function cloa_sync() {
    return CloaSync::instance();
}

// Start the plugin
cloa_sync();

// Activation hook
register_activation_hook(__FILE__, 'cloa_sync_activate');
function cloa_sync_activate() {
    // Create necessary database tables or options
    add_option('cloa_sync_version', CLOA_SYNC_VERSION);
    
    // Schedule sync events
    if (!wp_next_scheduled('cloa_sync_products')) {
        wp_schedule_event(time(), 'hourly', 'cloa_sync_products');
    }
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'cloa_sync_deactivate');
function cloa_sync_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('cloa_sync_products');
}