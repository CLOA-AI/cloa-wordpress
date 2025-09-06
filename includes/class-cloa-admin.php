<?php
/**
 * CLOA Admin Interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class Cloa_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_cloa_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_cloa_sync_now', array($this, 'ajax_sync_now'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('CLOA Sync', 'cloa-sync'),
            __('CLOA Sync', 'cloa-sync'),
            'manage_woocommerce',
            'cloa-sync',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('cloa_sync_settings', 'cloa_api_key');
        register_setting('cloa_sync_settings', 'cloa_api_url');
        register_setting('cloa_sync_settings', 'cloa_sync_enabled');
        register_setting('cloa_sync_settings', 'cloa_sync_frequency');
        register_setting('cloa_sync_settings', 'cloa_sync_categories');
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $api_key = get_option('cloa_api_key', '');
        $api_url = get_option('cloa_api_url', 'https://api.cloa.ai/api/v1');
        $sync_enabled = get_option('cloa_sync_enabled', false);
        $sync_frequency = get_option('cloa_sync_frequency', 'hourly');
        $sync_categories = get_option('cloa_sync_categories', array());
        
        $product_count = wp_count_posts('product');
        $last_sync = get_option('cloa_last_sync', '');
        $sync_status = get_option('cloa_sync_status', '');
        ?>
        <div class="wrap">
            <h1><?php _e('CLOA Product Sync', 'cloa-sync'); ?></h1>
            
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('Settings saved successfully!', 'cloa-sync'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="cloa-admin-wrap">
                <div class="cloa-main-content">
                    <form method="post" action="">
                        <?php wp_nonce_field('cloa_sync_settings'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('API Key', 'cloa-sync'); ?></th>
                                <td>
                                    <input type="text" name="cloa_api_key" value="<?php echo esc_attr($api_key); ?>" 
                                           class="regular-text" placeholder="cloa_api_key_..." />
                                    <p class="description">
                                        <?php _e('Enter your CLOA API key. You can generate one in your CLOA dashboard under Settings > API Keys.', 'cloa-sync'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('API URL', 'cloa-sync'); ?></th>
                                <td>
                                    <input type="url" name="cloa_api_url" value="<?php echo esc_attr($api_url); ?>" 
                                           class="regular-text" />
                                    <p class="description">
                                        <?php _e('CLOA API endpoint URL. Use default unless instructed otherwise.', 'cloa-sync'); ?>
                                    </p>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Enable Sync', 'cloa-sync'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="cloa_sync_enabled" value="1" 
                                               <?php checked($sync_enabled, true); ?> />
                                        <?php _e('Automatically sync products with CLOA', 'cloa-sync'); ?>
                                    </label>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Sync Frequency', 'cloa-sync'); ?></th>
                                <td>
                                    <select name="cloa_sync_frequency">
                                        <option value="hourly" <?php selected($sync_frequency, 'hourly'); ?>><?php _e('Hourly', 'cloa-sync'); ?></option>
                                        <option value="twicedaily" <?php selected($sync_frequency, 'twicedaily'); ?>><?php _e('Twice Daily', 'cloa-sync'); ?></option>
                                        <option value="daily" <?php selected($sync_frequency, 'daily'); ?>><?php _e('Daily', 'cloa-sync'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            
                            <tr>
                                <th scope="row"><?php _e('Categories to Sync', 'cloa-sync'); ?></th>
                                <td>
                                    <?php
                                    $categories = get_terms(array(
                                        'taxonomy' => 'product_cat',
                                        'hide_empty' => false,
                                    ));
                                    
                                    if (!empty($categories)):
                                        foreach ($categories as $category):
                                            $checked = in_array($category->term_id, (array) $sync_categories);
                                            ?>
                                            <label style="display: block; margin-bottom: 5px;">
                                                <input type="checkbox" name="cloa_sync_categories[]" 
                                                       value="<?php echo $category->term_id; ?>" 
                                                       <?php checked($checked, true); ?> />
                                                <?php echo esc_html($category->name); ?>
                                                <span style="color: #666;">(<?php echo $category->count; ?> products)</span>
                                            </label>
                                            <?php
                                        endforeach;
                                    else:
                                        ?>
                                        <p><?php _e('No product categories found.', 'cloa-sync'); ?></p>
                                        <?php
                                    endif;
                                    ?>
                                    <p class="description">
                                        <?php _e('Select which product categories to sync. Leave empty to sync all products.', 'cloa-sync'); ?>
                                    </p>
                                </td>
                            </tr>
                        </table>
                        
                        <p class="submit">
                            <input type="submit" name="submit" class="button-primary" 
                                   value="<?php _e('Save Settings', 'cloa-sync'); ?>" />
                            
                            <button type="button" id="cloa-test-connection" class="button" 
                                    style="margin-left: 10px;">
                                <?php _e('Test Connection', 'cloa-sync'); ?>
                            </button>
                            
                            <button type="button" id="cloa-sync-now" class="button" 
                                    style="margin-left: 10px;">
                                <?php _e('Sync Now', 'cloa-sync'); ?>
                            </button>
                        </p>
                    </form>
                </div>
                
                <div class="cloa-sidebar">
                    <div class="cloa-card">
                        <h3><?php _e('Sync Status', 'cloa-sync'); ?></h3>
                        <p><strong><?php _e('Total Products:', 'cloa-sync'); ?></strong> <?php echo $product_count->publish; ?></p>
                        <p><strong><?php _e('Last Sync:', 'cloa-sync'); ?></strong> 
                           <?php echo $last_sync ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_sync)) : __('Never', 'cloa-sync'); ?>
                        </p>
                        <p><strong><?php _e('Status:', 'cloa-sync'); ?></strong> 
                           <span id="cloa-status"><?php echo $sync_status ? esc_html($sync_status) : __('Ready', 'cloa-sync'); ?></span>
                        </p>
                    </div>
                    
                    <div class="cloa-card">
                        <h3><?php _e('About CLOA', 'cloa-sync'); ?></h3>
                        <p><?php _e('CLOA is an AI-powered product recommendation engine that helps increase your sales by showing the right products to the right customers.', 'cloa-sync'); ?></p>
                        <p>
                            <a href="https://cloa.ai" target="_blank" class="button">
                                <?php _e('Learn More', 'cloa-sync'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .cloa-admin-wrap {
                display: flex;
                gap: 20px;
            }
            .cloa-main-content {
                flex: 2;
            }
            .cloa-sidebar {
                flex: 1;
                max-width: 300px;
            }
            .cloa-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 20px;
                margin-bottom: 20px;
            }
            .cloa-card h3 {
                margin-top: 0;
            }
            #cloa-status.success {
                color: #46b450;
            }
            #cloa-status.error {
                color: #dc3232;
            }
            #cloa-status.processing {
                color: #ffb900;
            }
        </style>
        
        <script>
            jQuery(document).ready(function($) {
                $('#cloa-test-connection').click(function() {
                    var button = $(this);
                    var status = $('#cloa-status');
                    
                    button.prop('disabled', true).text('<?php _e('Testing...', 'cloa-sync'); ?>');
                    status.removeClass('success error').addClass('processing').text('<?php _e('Testing connection...', 'cloa-sync'); ?>');
                    
                    $.post(ajaxurl, {
                        action: 'cloa_test_connection',
                        api_key: $('input[name="cloa_api_key"]').val(),
                        api_url: $('input[name="cloa_api_url"]').val(),
                        _wpnonce: '<?php echo wp_create_nonce('cloa_test_connection'); ?>'
                    }, function(response) {
                        if (response.success) {
                            status.removeClass('processing error').addClass('success').text('<?php _e('Connection successful!', 'cloa-sync'); ?>');
                        } else {
                            status.removeClass('processing success').addClass('error').text(response.data.message || '<?php _e('Connection failed', 'cloa-sync'); ?>');
                        }
                    }).always(function() {
                        button.prop('disabled', false).text('<?php _e('Test Connection', 'cloa-sync'); ?>');
                    });
                });
                
                $('#cloa-sync-now').click(function() {
                    var button = $(this);
                    var status = $('#cloa-status');
                    
                    button.prop('disabled', true).text('<?php _e('Syncing...', 'cloa-sync'); ?>');
                    status.removeClass('success error').addClass('processing').text('<?php _e('Syncing products...', 'cloa-sync'); ?>');
                    
                    $.post(ajaxurl, {
                        action: 'cloa_sync_now',
                        _wpnonce: '<?php echo wp_create_nonce('cloa_sync_now'); ?>'
                    }, function(response) {
                        if (response.success) {
                            status.removeClass('processing error').addClass('success').text(response.data.message || '<?php _e('Sync completed!', 'cloa-sync'); ?>');
                        } else {
                            status.removeClass('processing success').addClass('error').text(response.data.message || '<?php _e('Sync failed', 'cloa-sync'); ?>');
                        }
                    }).always(function() {
                        button.prop('disabled', false).text('<?php _e('Sync Now', 'cloa-sync'); ?>');
                    });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'cloa_sync_settings')) {
            return;
        }
        
        $api_key = sanitize_text_field($_POST['cloa_api_key']);
        $api_url = esc_url_raw($_POST['cloa_api_url']);
        $sync_enabled = isset($_POST['cloa_sync_enabled']) ? 1 : 0;
        $sync_frequency = sanitize_text_field($_POST['cloa_sync_frequency']);
        $sync_categories = isset($_POST['cloa_sync_categories']) ? array_map('intval', $_POST['cloa_sync_categories']) : array();
        
        update_option('cloa_api_key', $api_key);
        update_option('cloa_api_url', $api_url);
        update_option('cloa_sync_enabled', $sync_enabled);
        update_option('cloa_sync_frequency', $sync_frequency);
        update_option('cloa_sync_categories', $sync_categories);
        
        // Update scheduled event frequency
        wp_clear_scheduled_hook('cloa_sync_products');
        if ($sync_enabled) {
            wp_schedule_event(time(), $sync_frequency, 'cloa_sync_products');
        }
        
        wp_redirect(admin_url('admin.php?page=cloa-sync&settings-updated=1'));
        exit;
    }
    
    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'cloa_test_connection')) {
            wp_die();
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        $api_url = esc_url_raw($_POST['api_url']);
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => __('API key is required', 'cloa-sync')));
        }
        
        // Temporarily update settings for test
        $api = new Cloa_API();
        $api->update_settings($api_key, $api_url);
        
        $result = $api->test_connection();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array('message' => __('Connection successful!', 'cloa-sync')));
        }
    }
    
    /**
     * AJAX sync now
     */
    public function ajax_sync_now() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'cloa_sync_now')) {
            wp_die();
        }
        
        $sync_manager = new Cloa_Sync_Manager();
        $result = $sync_manager->sync_products();
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success(array('message' => sprintf(__('Synced %d products successfully!', 'cloa-sync'), $result)));
        }
    }
}