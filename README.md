# CLOA Product Sync for WordPress

Automatically sync your WooCommerce products with CLOA AI recommendation engine.

## Features

- **Smart Sync**: Handles large catalogs (500k+ products) without slowing your website
- **Real-time Updates**: Products sync automatically when created or updated
- **Category Filtering**: Choose which product categories to sync
- **Background Processing**: Large syncs run in background with progress tracking
- **Connection Testing**: Test your API connection with one click
- **Easy Setup**: Configure with just your API key

## Installation

1. Upload plugin to `/wp-content/plugins/cloa-wordpress/`
2. Activate through 'Plugins' menu in WordPress
3. Go to **WooCommerce > CLOA Sync** to configure

## Setup

1. **Get API Key**: Get your API key from CLOA dashboard
2. **Enter API Key**: Paste it in WooCommerce > CLOA Sync
3. **Test Connection**: Click "Test Connection" to verify setup
4. **Start Sync**: Click "Sync Now" to begin syncing products

## How It Works

- **Small stores** (<1,000 products): Syncs immediately
- **Large stores** (1,000+ products): Uses background processing
- **Real-time**: New/updated products sync automatically
- **Safe**: Never affects your website performance

## Settings

- **API Key**: Your CLOA API key
- **Enable Sync**: Turn automatic syncing on/off
- **Sync Frequency**: How often to sync (hourly, twice daily, daily)
- **Categories**: Select which product categories to sync

## Troubleshooting

- **Connection Failed**: Check your API key
- **Products Not Syncing**: Ensure products are published and in selected categories
- **For detailed debugging**: Enable WP_DEBUG in wp-config.php

## Requirements

- WordPress 5.0+
- WooCommerce 5.0+
- PHP 7.4+

## Support

For issues or questions, contact CLOA support.

## Uninstall

Deactivating the plugin stops syncing. Deleting the plugin removes all sync data and settings.