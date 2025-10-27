=== Dodo Payments for WooCommerce ===
Contributors: ayushdodopayments
Tags: payments, woocommerce, dodo
Requires at least: 6.1
Tested up to: 6.8
Stable tag: 0.3.3
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

== Description ==

Dodo Payments for WooCommerce is a comprehensive payment solution that handles all your payment processing needs. As a Merchant of Record (MoR) service, we take care of payment processing, tax compliance, and financial regulations, allowing you to focus on growing your business. With support for multiple payment methods and automated tax calculations, Dodo Payments makes it easy to sell globally while staying compliant with local tax laws and regulations.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/dodo-payments-for-woocommerce`
  directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->Dodo Payments screen to configure the plugin

== Supports ==

- Digital Products, Ebooks, Ed-Tech and SaaS products
- Subscription products and recurring payments
- Percentage based coupon codes
- Classic Checkout Flow
- Payments from multiple countries
- Product pricing in USD or INR

== Does not Support ==

- Block based checkout
- Fixed amount or custom discount codes

== Frequently Asked Questions ==

= What payment methods are supported? =

Currently, we support credit card payments through Dodo Payment Gateway.

== Changelog ==

= 0.3.3 =
* fix: change subscription status to 'on_hold' instead of invalid state of 'paused' when a subscription is paused in woocommerce.

= 0.3.2 =
* fix: add missing import for cart exceptions which prevented cart errors from being displayed properly

= 0.3.1 =
* fix: use more widely used format for webhook url

= 0.3.0 =
* Feature: Add comprehensive subscription support
* Feature: Subscription product management and synchronization
* Feature: Subscription lifecycle management (cancel, suspend, reactivate)

= 0.2.5 =
* Fix: remove unsupported syntax for PHP 7

= 0.2.4 =
* Fix: clear cart only if the payment link is created

= 0.2.1 =
* Fix product prices getting rounded off

= 0.2.0 =
* Feature: Add support for coupon codes(Fixed percentage type only).

= 0.1.9 =
* Fixed a bug where products with descriptions longer than 1000 characters would fail to process

= 0.1.3 =
* Initial release
