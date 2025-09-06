# CLOA Product Sync WordPress Plugin

Automatically sync your WooCommerce products with the CLOA AI recommendation engine.

## Description

The CLOA Product Sync plugin seamlessly integrates your WooCommerce store with CLOA's AI-powered recommendation system. It automatically syncs your product catalog, enabling CLOA to provide intelligent product recommendations to your customers.

## Features

- **Automatic Sync**: Products are automatically synced when created, updated, or deleted
- **Bulk Sync**: Sync all products at once or on a schedule (hourly, daily, etc.)
- **Category Filtering**: Choose which product categories to sync
- **Real-time Updates**: Products are updated in CLOA whenever they're modified in WooCommerce
- **Connection Testing**: Built-in API connection testing
- **Sync Status**: Monitor sync status and view sync history

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- CLOA account with API key

## Installation

1. Download the plugin files
2. Upload the `cloa-wordpress` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Go to WooCommerce > CLOA Sync to configure the plugin

## Configuration

1. **Get your API Key**:
   - Log in to your CLOA dashboard
   - Go to Settings > API Keys
   - Click "Generate New Key"
   - Copy the generated API key

2. **Configure the Plugin**:
   - Go to WooCommerce > CLOA Sync in your WordPress admin
   - Enter your CLOA API key
   - Test the connection
   - Enable automatic sync
   - Choose sync frequency and categories (optional)
   - Save settings

3. **Initial Sync**:
   - Click "Sync Now" to perform an initial sync of all your products
   - Monitor the sync status in the sidebar

## Usage

Once configured, the plugin works automatically:

- **New Products**: Automatically synced when published
- **Updated Products**: Changes are synced immediately
- **Deleted Products**: Automatically removed from CLOA
- **Scheduled Sync**: Runs at your chosen frequency to catch any missed updates

### Manual Operations

- **Test Connection**: Verify your API key and connection status
- **Sync Now**: Manually trigger a full product sync
- **Category Filtering**: Sync only products from selected categories

## Troubleshooting

### Common Issues

1. **Connection Failed**
   - Verify your API key is correct
   - Check that your server can make outbound HTTPS requests
   - Ensure CLOA API URL is correct

2. **Products Not Syncing**
   - Check that WooCommerce products are published
   - Verify category filters (if enabled)
   - Check sync status for error messages

3. **Sync Errors**
   - Review error messages in the sync status
   - Ensure products have required fields (name, price)
   - Check server logs for detailed error information

### Debug Mode

To enable debug logging, add this to your `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Debug logs will be written to `/wp-content/debug.log`

## API Integration

The plugin uses CLOA's sync API endpoints:

- `POST /api/v1/sync/product` - Sync single product
- `POST /api/v1/sync/products` - Bulk sync products
- `POST /api/v1/sync/products/delete` - Delete products

## Data Mapping

WooCommerce product data is mapped to CLOA format:

| WooCommerce Field | CLOA Field | Notes |
|------------------|------------|-------|
| Product ID | externalId | Prefixed with `wp_{blog_id}_` |
| Name | name | Product title |
| Description | description | HTML stripped |
| Price | price | Current price |
| SKU | sku | Product SKU |
| Images | images | All product images |
| Categories | categories | Product categories |
| Attributes | attributes | Product attributes |
| Stock | stock | Stock quantity |
| Status | status | Published = active |

## Hooks and Filters

### Actions

- `cloa_sync_products` - Scheduled sync event
- `cloa_before_sync_product` - Before syncing a product
- `cloa_after_sync_product` - After syncing a product

### Filters

- `cloa_should_sync_product` - Control whether a product should be synced
- `cloa_mapped_product` - Modify mapped product data before sync
- `cloa_sync_custom_fields` - Add custom fields to sync

### Example Usage

```php
// Don't sync products under $10
add_filter('cloa_should_sync_product', function($should_sync, $product) {
    if ($product->get_price() < 10) {
        return false;
    }
    return $should_sync;
}, 10, 2);

// Add custom field to sync
add_filter('cloa_sync_custom_fields', function($fields) {
    $fields[] = '_my_custom_field';
    return $fields;
});
```

## Support

For support and documentation:
- Visit [https://cloa.ai](https://cloa.ai)
- Contact support through your CLOA dashboard

## Changelog

### 1.0.0
- Initial release
- WooCommerce product sync
- Admin interface
- Scheduled sync
- Category filtering
- Connection testing

## License

GPL v2 or later