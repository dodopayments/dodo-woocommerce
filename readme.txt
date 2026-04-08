=== Dodo Payments for WooCommerce ===
Contributors: ayushdodopayments
Tags: payments, woocommerce, dodo payments, merchant of record, subscriptions
Requires at least: 6.1
Tested up to: 6.9
Stable tag: 0.3.4
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

== Description ==

Dodo Payments for WooCommerce is a complete payment and billing solution for digital product businesses. As a [Merchant of Record (MoR)](https://dodopayments.com), Dodo Payments becomes the legal seller on your behalf — handling payment processing, global tax compliance (VAT, GST, Sales Tax), fraud prevention, and financial regulations so you can focus on building your product.

With 40+ payment methods, 80+ currencies, and coverage across 220+ countries, Dodo Payments makes it easy to sell globally while staying compliant with local tax laws. No additional contracts, integrations, or merchant accounts needed — all payment methods activate automatically once your account is verified.

Learn more at [dodopayments.com](https://dodopayments.com).

= Key Features =

- **Merchant of Record**: Dodo assumes legal responsibility for transactions, handling VAT, GST, and sales tax worldwide
- **Seamless Checkout**: Redirect customers to a secure, optimized checkout page
- **Real-time Status Updates**: Instant order status synchronization via webhooks
- **Multi-currency Support**: Accept payments in 80+ currencies
- **Automatic Tax Compliance**: Tax calculation, collection, filing, and remittance handled by Dodo in 150+ countries
- **Fraud Prevention**: Built-in PCI DSS Level 1 certified fraud protection
- **B2B VAT Reverse Charge**: Automatic VAT ID validation and reverse charge for EU B2B sales

= Supported Product Types =

- Digital Products, Ebooks, Ed-Tech and SaaS products
- Subscription products and recurring payments
- One-time payments
- Percentage based coupon codes

= Supported Payment Methods =

**Cards** — All major global and regional card networks:

- Visa
- Mastercard
- American Express
- Discover
- JCB
- UnionPay
- Diners Club
- Interac (Canada)
- Cartes Bancaires (France)
- Rupay (India)

**Digital Wallets**

- Apple Pay (Global, excl. India)
- Google Pay (Global, excl. India)
- Amazon Pay (Global, excl. India — USD only)
- Cash App Pay (US — USD only)

**Buy Now, Pay Later**

- Klarna (US, Europe)
- Afterpay (US, UK)

**Bank & Regional Payment Methods**

- UPI (India — INR, supports subscriptions)
- Pix (Brazil — BRL)
- iDEAL (Netherlands — EUR)
- Bancontact (Belgium — EUR)
- EPS (Austria — EUR)
- Multibanco (Portugal — EUR)
- RevolutPay (Global — GBP)

**Other Methods**

- Crypto & Stablecoins (Global, excl. India — USD)
- WeChat Pay (Global — USD, CNY)

All payment methods are automatically presented to customers based on their location, currency, and device. No additional configuration required.

= Does Not Support =

- Block based checkout
- Fixed amount or custom discount codes
- Physical product shipping

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/dodo-payments-for-woocommerce`
  directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->Dodo Payments screen to configure the plugin
4. Sign up for an account at [dodopayments.com](https://dodopayments.com) if you don't have one

== Frequently Asked Questions ==

= What payment methods are supported? =

Dodo Payments supports 40+ payment methods including credit/debit cards (Visa, Mastercard, Amex, Discover, JCB, UnionPay), digital wallets (Apple Pay, Google Pay, Amazon Pay), UPI, bank transfers (iDEAL, Bancontact, EPS, Pix), Buy Now Pay Later (Klarna, Afterpay), crypto, and more. Payment methods are automatically shown to customers based on their location and device.

= What is a Merchant of Record? =

A Merchant of Record (MoR) is the legal entity that appears on your customer's bank statement and assumes responsibility for the transaction. Dodo Payments acts as the MoR so you don't have to deal with tax registration, collection, filing, chargebacks, or PCI compliance. You build the product, Dodo handles the back office.

= Which countries can I sell to? =

Dodo Payments accepts payments from 220+ countries and regions worldwide.

= What currencies are supported? =

Dodo Payments supports 80+ currencies. Product pricing is set in your chosen currency (e.g., USD, EUR, INR) and customers pay in the currency supported by their selected payment method.

= How does tax compliance work? =

Dodo Payments automatically detects the customer's location, calculates the correct tax rate (VAT, GST, Sales Tax), collects it at checkout, and files returns with tax authorities on your behalf. You never see a tax form. For EU B2B sales, VAT reverse charge is applied automatically when a valid VAT number is provided.

= Do I still own the customer relationship? =

Yes. You control pricing, branding, product delivery, and direct communication. Dodo handles billing mechanics, but customers know they're buying from you. Your brand appears prominently in checkout, emails, and invoices.

= How do refunds work? =

Initiate refunds from your Dodo Payments dashboard. Refunds are processed in the customer's original payment method and currency. Tax amounts are automatically adjusted and reconciled.

= Does the plugin support subscriptions? =

Yes. The plugin supports full subscription lifecycle management including creation, cancellation, suspension, and reactivation. Recurring payments are handled automatically through Dodo Payments.

= What appears on my customer's credit card statement? =

Dodo Payments appears as the merchant. Your product or brand reference is included where character limits allow, and customers receive detailed receipts showing your product information.

= How do I get support? =

Contact the Dodo Payments support team at [support@dodopayments.com](mailto:support@dodopayments.com). You can also access support through the "Get Support" icon on the [Dodo Payments Dashboard](https://app.dodopayments.com). For more information, visit [dodopayments.com](https://dodopayments.com).

== Changelog ==

= 0.3.4 =
* docs: update readme with latest Dodo Payments info, payment methods, and FAQs

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
