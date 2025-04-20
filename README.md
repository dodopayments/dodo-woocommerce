# Dodo Payments for WooCommerce

A WooCommerce payment gateway plugin that allows you to accept payments through Dodo Payments.

## Description

Dodo Payments for WooCommerce enables you to accept payments from your customers using Dodo Payments as a payment method. The plugin integrates seamlessly with your WooCommerce store and provides a secure, reliable merchant of record solution.

## Features

- Easy integration with WooCommerce
- Support for both live and test modes
- Secure payment processing
- Webhook support for payment status updates
- Tax category configuration
- Support for digital products, SaaS, e-books, and EdTech products

## Requirements

- WordPress 6.1 or higher
- PHP 7.4 or higher
- WooCommerce 7.9 or higher
- SSL certificate (for secure payment processing)

## Installation

1. Download the plugin zip file
2. Go to WordPress admin panel > Plugins > Add New
3. Click "Upload Plugin" and select the downloaded zip file
4. Click "Install Now" and then "Activate"

## Configuration

1. Go to WooCommerce > Settings > Payments
2. Find "Dodo Payments" and click "Manage"
3. Configure the following settings:
   - Enable/Disable the payment method
   - Set the payment method title and description
   - Configure test/live mode
   - Add your API keys and webhook signing keys
   - Set global tax category and tax inclusive settings

### API Keys

- **Live API Key**: Required for receiving live payments. Generate from Dodo Payments (Live Mode) > Developer > API Keys
- **Live Webhook Signing Key**: Required for payment status sync. Generate from Dodo Payments (Live Mode) > Developer > Webhooks
- **Test API Key**: Optional, for testing payments. Generate from Dodo Payments (Test Mode) > Developer > API Keys
- **Test Webhook Signing Key**: Optional, for testing payment status sync. Generate from Dodo Payments (Test Mode) > Developer > Webhooks

## Webhook Setup

The plugin provides a webhook endpoint for payment status updates. Use the URL at the end of the same page when setting up webhooks in your Dodo Payments dashboard.

## Support

For support, please contact Dodo Payments support team or visit the [Dodo Payments website](https://dodopayments.com).

## License

This plugin is licensed under the GPL v2 or later.
