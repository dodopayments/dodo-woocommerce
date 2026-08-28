<?php

/**
 * Store-level configuration for the Dodo Payments Checkout Sessions API.
 *
 * @package Dodo_Payments_For_WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Declarative schema for the Checkout Session options exposed on the settings page.
 *
 * A single definition per option drives three things that would otherwise drift
 * apart: the WooCommerce form field, the sanitization applied on save, and the
 * position the saved value takes in the `POST /checkouts` request body.
 *
 * The guiding rule for every option here is that an unconfigured store must send
 * the exact request it sent before these settings existed. Values that are empty
 * (or left at "Default") are pruned from the payload rather than sent as null, so
 * the Dodo Payments API keeps ownership of its own defaults.
 *
 * @since 0.6.0
 */
class Dodo_Payments_Checkout_Settings
{
    /**
     * Prefix applied to every option key owned by this class.
     *
     * Keeps the new options clearly separated from the gateway's original
     * settings and from the `feature_flag_` keys added in 0.5.0.
     */
    const PREFIX = 'checkout_';

    /**
     * Maximum number of `custom_fields` entries the API accepts per session.
     */
    const MAX_CUSTOM_FIELDS = 5;

    /**
     * Maximum length of `theme_config.pay_button_text` per the API schema.
     */
    const MAX_PAY_BUTTON_TEXT = 100;

    /**
     * Colour tokens accepted by `theme_config.light` / `theme_config.dark`.
     *
     * Keyed by API field name; the value is the admin-facing label. Rendered as a
     * single grid of colour pickers per mode rather than 16 separate form rows,
     * which would make the settings page unusable.
     *
     * @return array<string, string>
     */
    public static function color_tokens()
    {
        return array(
            'bg_primary' => __('Background', 'dodo-payments-for-woocommerce'),
            'bg_secondary' => __('Background (secondary)', 'dodo-payments-for-woocommerce'),
            'text_primary' => __('Text', 'dodo-payments-for-woocommerce'),
            'text_secondary' => __('Text (secondary)', 'dodo-payments-for-woocommerce'),
            'text_placeholder' => __('Placeholder text', 'dodo-payments-for-woocommerce'),
            'text_error' => __('Error text', 'dodo-payments-for-woocommerce'),
            'text_success' => __('Success text', 'dodo-payments-for-woocommerce'),
            'button_primary' => __('Primary button', 'dodo-payments-for-woocommerce'),
            'button_primary_hover' => __('Primary button (hover)', 'dodo-payments-for-woocommerce'),
            'button_text_primary' => __('Primary button text', 'dodo-payments-for-woocommerce'),
            'button_secondary' => __('Secondary button', 'dodo-payments-for-woocommerce'),
            'button_secondary_hover' => __('Secondary button (hover)', 'dodo-payments-for-woocommerce'),
            'button_text_secondary' => __('Secondary button text', 'dodo-payments-for-woocommerce'),
            'border_primary' => __('Border', 'dodo-payments-for-woocommerce'),
            'border_secondary' => __('Border (secondary)', 'dodo-payments-for-woocommerce'),
            'input_focus_border' => __('Input focus border', 'dodo-payments-for-woocommerce'),
        );
    }

    /**
     * Field types accepted by a `custom_fields` entry.
     *
     * @return array<string, string>
     */
    public static function custom_field_types()
    {
        return array(
            'text' => __('Text', 'dodo-payments-for-woocommerce'),
            'number' => __('Number', 'dodo-payments-for-woocommerce'),
            'email' => __('Email', 'dodo-payments-for-woocommerce'),
            'url' => __('URL', 'dodo-payments-for-woocommerce'),
            'date' => __('Date', 'dodo-payments-for-woocommerce'),
            'dropdown' => __('Dropdown', 'dodo-payments-for-woocommerce'),
            'boolean' => __('Yes / No', 'dodo-payments-for-woocommerce'),
        );
    }

    /**
     * Payment method identifiers accepted by `allowed_payment_method_types`.
     *
     * These are protocol and brand identifiers rather than prose, so they are
     * presented as-is (lightly humanized) instead of being run through the
     * translation layer.
     *
     * @return string[]
     */
    public static function payment_method_types()
    {
        return array(
            'ach', 'affirm', 'afterpay_clearpay', 'alfamart', 'ali_pay', 'ali_pay_hk', 'alma',
            'amazon_pay', 'apple_pay', 'atome', 'bacs', 'bancontact_card', 'becs', 'benefit',
            'billie', 'bizum', 'blik', 'bca_bank_transfer', 'bni_va', 'boleto', 'bri_va',
            'card_redirect', 'cashapp', 'cimb_va', 'classic', 'credit', 'crypto_currency',
            'dana', 'danamon_va', 'debit', 'direct_carrier_billing', 'duit_now', 'efecty',
            'eft', 'eps', 'evoucher', 'family_mart', 'fps', 'gcash', 'giropay', 'givex',
            'go_pay', 'google_pay', 'ideal', 'indomaret', 'instant_bank_transfer', 'interac',
            'kakao_pay', 'klarna', 'knet', 'lawson', 'local_bank_redirect',
            'local_bank_transfer', 'mandiri_va', 'mb_way', 'mifinity', 'mini_stop',
            'mobile_pay', 'momo', 'momo_atm', 'multibanco', 'naver_pay',
            'online_banking_czech_republic', 'online_banking_finland', 'online_banking_fpx',
            'online_banking_poland', 'online_banking_slovakia', 'online_banking_thailand',
            'open_banking_pis', 'open_banking_uk', 'oxxo', 'pago_efectivo', 'pay_bright',
            'pay_easy', 'pay_safe_card', 'payco', 'paypal', 'paze', 'permata_bank_transfer',
            'pix', 'prompt_pay', 'przelewy24', 'pse', 'red_compra', 'red_pagos', 'revolut_pay',
            'samsung_pay', 'satispay', 'seicomart', 'sepa', 'sepa_bank_transfer',
            'seven_eleven', 'sofort', 'swish', 'touch_n_go', 'trustly', 'twint', 'upi_collect',
            'upi_intent', 'venmo', 'viet_qr', 'vipps', 'walley', 'we_chat_pay', 'zip',
        );
    }

    /**
     * Currency codes accepted by `billing_currency`.
     *
     * @return string[]
     */
    public static function currencies()
    {
        return array(
            'AED', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT',
            'BGN', 'BHD', 'BIF', 'BMD', 'BND', 'BOB', 'BRL', 'BSD', 'BWP', 'BYN', 'BZD', 'CAD',
            'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CUP', 'CVE', 'CZK', 'DJF', 'DKK', 'DOP', 'DZD',
            'EGP', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GHS', 'GIP', 'GMD', 'GNF', 'GTQ',
            'GYD', 'HKD', 'HNL', 'HRK', 'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'IQD', 'JMD', 'JOD',
            'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KRW', 'KWD', 'KYD', 'KZT', 'LAK', 'LBP', 'LKR',
            'LRD', 'LSL', 'LYD', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRU', 'MUR',
            'MVR', 'MWK', 'MXN', 'MYR', 'MZN', 'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'OMR',
            'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB', 'RWF',
            'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLE', 'SLL', 'SOS', 'SRD', 'SSP', 'STN',
            'SVC', 'SZL', 'THB', 'TND', 'TOP', 'TRY', 'TTD', 'TWD', 'TZS', 'UAH', 'UGX', 'USD',
            'UYU', 'UZS', 'VES', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XOF', 'XPF', 'YER', 'ZAR',
            'ZMW',
        );
    }

    /**
     * Human-readable label for a payment method identifier.
     *
     * @param string $slug Payment method identifier.
     * @return string
     */
    private static function payment_method_label($slug)
    {
        $acronyms = array(
            'ach' => 'ACH',
            'becs' => 'BECS',
            'eft' => 'EFT',
            'eps' => 'EPS',
            'fps' => 'FPS',
            'knet' => 'KNET',
            'pix' => 'PIX',
            'pse' => 'PSE',
            'sepa' => 'SEPA',
            'upi' => 'UPI',
            'va' => 'VA',
            'bca' => 'BCA',
            'bni' => 'BNI',
            'bri' => 'BRI',
            'cimb' => 'CIMB',
            'uk' => 'UK',
            'hk' => 'HK',
            'pis' => 'PIS',
            'qr' => 'QR',
        );

        $words = array();
        foreach (explode('_', $slug) as $word) {
            $words[] = isset($acronyms[$word]) ? $acronyms[$word] : ucfirst($word);
        }

        return implode(' ', $words);
    }

    /**
     * Tri-state options shared by every boolean Checkout Session field.
     *
     * "Default" saves an empty string, which the payload builder prunes, leaving
     * the API to apply its own default.
     *
     * @return array<string, string>
     */
    private static function tristate_options()
    {
        return array(
            '' => __('Default', 'dodo-payments-for-woocommerce'),
            'yes' => __('Enabled', 'dodo-payments-for-woocommerce'),
            'no' => __('Disabled', 'dodo-payments-for-woocommerce'),
        );
    }

    /**
     * Appends the API's own default to a field description.
     *
     * @param string $description Base description.
     * @param bool   $enabled     Whether the API defaults the field to enabled.
     * @return string
     */
    private static function with_api_default($description, $enabled)
    {
        return $description . ' ' . sprintf(
            /* translators: %s: "Enabled" or "Disabled" */
            __('Default: %s.', 'dodo-payments-for-woocommerce'),
            $enabled
                ? __('Enabled', 'dodo-payments-for-woocommerce')
                : __('Disabled', 'dodo-payments-for-woocommerce')
        );
    }

    /**
     * The complete option schema.
     *
     * Each entry carries the WooCommerce form field definition under `form`, plus
     * the metadata the payload builder needs:
     *
     *   - `path` -- dot-delimited position in the request body. Absent for options
     *     that change plugin behaviour rather than mapping to a request field.
     *   - `cast` -- how the stored value converts for the request: `bool`, `int`,
     *     `list`, `map` or (by default) `string`.
     *
     * Section headings are plain `title` fields with neither `path` nor `cast`.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function schema()
    {
        $tristate = self::tristate_options();

        $payment_methods = array();
        foreach (self::payment_method_types() as $slug) {
            $payment_methods[$slug] = self::payment_method_label($slug);
        }

        $currencies = array('' => __('Default', 'dodo-payments-for-woocommerce'));
        foreach (self::currencies() as $code) {
            $currencies[$code] = $code;
        }

        $schema = array();

        // -----------------------------------------------------------------
        // Redirects
        // -----------------------------------------------------------------
        $schema['section_redirects'] = array(
            'form' => array(
                'title' => __('Redirects', 'dodo-payments-for-woocommerce'),
                'type' => 'title',
                'description' => __('Where customers are sent when they finish or abandon the hosted Dodo Payments checkout.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['return_url'] = array(
            'form' => array(
                'title' => __('Return URL', 'dodo-payments-for-woocommerce'),
                'type' => 'url',
                'default' => '',
                'sanitize_callback' => array(__CLASS__, 'sanitize_url'),
                'desc_tip' => false,
                'placeholder' => __('Order received page (default)', 'dodo-payments-for-woocommerce'),
                'description' => __('Where customers land after paying. Leave empty to use the WooCommerce order received page. You may use the placeholders <code>{order_id}</code>, <code>{order_key}</code> and <code>{order_number}</code>.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['cancel_url_mode'] = array(
            'form' => array(
                'title' => __('If the customer cancels', 'dodo-payments-for-woocommerce'),
                'type' => 'select',
                'default' => 'pay_page',
                'desc_tip' => false,
                'options' => array(
                    'pay_page' => __('Return to the order pay page', 'dodo-payments-for-woocommerce'),
                    'cancel_order' => __('Cancel the order and return to the cart', 'dodo-payments-for-woocommerce'),
                    'custom' => __('Send a custom URL', 'dodo-payments-for-woocommerce'),
                    'none' => __('Do not send a cancel URL', 'dodo-payments-for-woocommerce'),
                ),
                'description' => __('Customers who abandon the hosted checkout are sent here. The order pay page lets them retry payment on the same order.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['cancel_url_custom'] = array(
            'form' => array(
                'title' => __('Custom Cancel URL', 'dodo-payments-for-woocommerce'),
                'type' => 'url',
                'default' => '',
                'sanitize_callback' => array(__CLASS__, 'sanitize_url'),
                'desc_tip' => false,
                'description' => __('Only used when "Send a custom URL" is selected above. Supports the same placeholders as the return URL.', 'dodo-payments-for-woocommerce'),
            ),
        );

        // -----------------------------------------------------------------
        // Checkout appearance
        // -----------------------------------------------------------------
        $schema['section_appearance'] = array(
            'form' => array(
                'title' => __('Checkout Appearance', 'dodo-payments-for-woocommerce'),
                'type' => 'title',
                'description' => __('Presentation options for the hosted checkout page. Anything left at "Default" is not sent, so Dodo Payments decides.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['theme'] = array(
            'path' => 'customization.theme',
            'form' => array(
                'title' => __('Theme', 'dodo-payments-for-woocommerce'),
                'type' => 'select',
                'default' => '',
                'desc_tip' => false,
                'options' => array(
                    '' => __('Default', 'dodo-payments-for-woocommerce'),
                    'light' => __('Light', 'dodo-payments-for-woocommerce'),
                    'dark' => __('Dark', 'dodo-payments-for-woocommerce'),
                    'system' => __('Match the customer\'s device', 'dodo-payments-for-woocommerce'),
                ),
                'description' => __('Colour scheme the hosted checkout renders in.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['force_language'] = array(
            'path' => 'customization.force_language',
            'form' => array(
                'title' => __('Force Language', 'dodo-payments-for-woocommerce'),
                'type' => 'text',
                'default' => '',
                'sanitize_callback' => array(__CLASS__, 'sanitize_text'),
                'desc_tip' => false,
                'placeholder' => __('Customer\'s own language', 'dodo-payments-for-woocommerce'),
                'description' => __('A language tag such as <code>de</code> or <code>pt-BR</code>. Leave empty to let Dodo Payments choose.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['show_order_details'] = array(
            'path' => 'customization.show_order_details',
            'cast' => 'bool',
            'form' => array(
                'title' => __('Show Order Details', 'dodo-payments-for-woocommerce'),
                'type' => 'select',
                'default' => '',
                'desc_tip' => false,
                'options' => $tristate,
                'description' => self::with_api_default(
                    __('Show the order summary alongside the payment form.', 'dodo-payments-for-woocommerce'),
                    true
                ),
            ),
        );

        $schema['show_on_demand_tag'] = array(
            'path' => 'customization.show_on_demand_tag',
            'cast' => 'bool',
            'form' => array(
                'title' => __('Show On-Demand Tag', 'dodo-payments-for-woocommerce'),
                'type' => 'select',
                'default' => '',
                'desc_tip' => false,
                'options' => $tristate,
                'description' => self::with_api_default(
                    __('Show the on-demand badge on mandate-based subscriptions.', 'dodo-payments-for-woocommerce'),
                    true
                ),
            ),
        );

        // -----------------------------------------------------------------
        // Branding
        // -----------------------------------------------------------------
        $schema['section_branding'] = array(
            'form' => array(
                'title' => __('Branding', 'dodo-payments-for-woocommerce'),
                'type' => 'title',
                'description' => __('Match the hosted checkout to your storefront. Every field here is optional; empty fields fall back to the Dodo Payments look.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['pay_button_text'] = array(
            'path' => 'customization.theme_config.pay_button_text',
            'form' => array(
                'title' => __('Pay Button Text', 'dodo-payments-for-woocommerce'),
                'type' => 'text',
                'default' => '',
                'sanitize_callback' => array(__CLASS__, 'sanitize_pay_button_text'),
                'desc_tip' => false,
                'custom_attributes' => array('maxlength' => self::MAX_PAY_BUTTON_TEXT),
                'description' => sprintf(
                    /* translators: %d: maximum number of characters */
                    __('Label on the checkout\'s pay button. Up to %d characters.', 'dodo-payments-for-woocommerce'),
                    self::MAX_PAY_BUTTON_TEXT
                ),
            ),
        );

        $schema['radius'] = array(
            'path' => 'customization.theme_config.radius',
            'form' => array(
                'title' => __('Corner Radius', 'dodo-payments-for-woocommerce'),
                'type' => 'text',
                'default' => '',
                'sanitize_callback' => array(__CLASS__, 'sanitize_text'),
                'desc_tip' => false,
                'placeholder' => '8px',
                'description' => __('Corner rounding applied to buttons and inputs, as a CSS length.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['font_size'] = array(
            'path' => 'customization.theme_config.font_size',
            'form' => array(
                'title' => __('Font Size', 'dodo-payments-for-woocommerce'),
                'type' => 'select',
                'default' => '',
                'desc_tip' => false,
                'options' => array(
                    '' => __('Default', 'dodo-payments-for-woocommerce'),
                    'xs' => __('Extra small', 'dodo-payments-for-woocommerce'),
                    'sm' => __('Small', 'dodo-payments-for-woocommerce'),
                    'md' => __('Medium', 'dodo-payments-for-woocommerce'),
                    'lg' => __('Large', 'dodo-payments-for-woocommerce'),
                    'xl' => __('Extra large', 'dodo-payments-for-woocommerce'),
                    '2xl' => __('Extra extra large', 'dodo-payments-for-woocommerce'),
                ),
            ),
        );

        $schema['font_weight'] = array(
            'path' => 'customization.theme_config.font_weight',
            'form' => array(
                'title' => __('Font Weight', 'dodo-payments-for-woocommerce'),
                'type' => 'select',
                'default' => '',
                'desc_tip' => false,
                'options' => array(
                    '' => __('Default', 'dodo-payments-for-woocommerce'),
                    'normal' => __('Normal', 'dodo-payments-for-woocommerce'),
                    'medium' => __('Medium', 'dodo-payments-for-woocommerce'),
                    'bold' => __('Bold', 'dodo-payments-for-woocommerce'),
                    'extraBold' => __('Extra bold', 'dodo-payments-for-woocommerce'),
                ),
            ),
        );

        $schema['font_primary_url'] = array(
            'path' => 'customization.theme_config.font_primary_url',
            'form' => array(
                'title' => __('Primary Font URL', 'dodo-payments-for-woocommerce'),
                'type' => 'url',
                'default' => '',
                'sanitize_callback' => array(__CLASS__, 'sanitize_url'),
                'desc_tip' => false,
                'description' => __('Web font used for headings and body text on the hosted checkout.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['font_secondary_url'] = array(
            'path' => 'customization.theme_config.font_secondary_url',
            'form' => array(
                'title' => __('Secondary Font URL', 'dodo-payments-for-woocommerce'),
                'type' => 'url',
                'default' => '',
                'sanitize_callback' => array(__CLASS__, 'sanitize_url'),
                'desc_tip' => false,
                'description' => __('Web font used for supporting text on the hosted checkout.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['theme_colors_light'] = array(
            'path' => 'customization.theme_config.light',
            'cast' => 'map',
            'form' => array(
                'title' => __('Light Mode Colours', 'dodo-payments-for-woocommerce'),
                'type' => 'dodo_colors',
                'default' => array(),
                'sanitize_callback' => array(__CLASS__, 'sanitize_colors'),
                'desc_tip' => false,
                'description' => __('Applied when the hosted checkout renders in light mode. Colours left empty keep the Dodo Payments default.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['theme_colors_dark'] = array(
            'path' => 'customization.theme_config.dark',
            'cast' => 'map',
            'form' => array(
                'title' => __('Dark Mode Colours', 'dodo-payments-for-woocommerce'),
                'type' => 'dodo_colors',
                'default' => array(),
                'sanitize_callback' => array(__CLASS__, 'sanitize_colors'),
                'desc_tip' => false,
                'description' => __('Applied when the hosted checkout renders in dark mode. Colours left empty keep the Dodo Payments default.', 'dodo-payments-for-woocommerce'),
            ),
        );

        // -----------------------------------------------------------------
        // Payment methods and currency
        // -----------------------------------------------------------------
        $schema['section_payments'] = array(
            'form' => array(
                'title' => __('Payment Methods & Currency', 'dodo-payments-for-woocommerce'),
                'type' => 'title',
                'description' => __('Restrict or adjust how customers are allowed to pay.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['allowed_payment_method_types'] = array(
            'path' => 'allowed_payment_method_types',
            'cast' => 'list',
            'form' => array(
                'title' => __('Accepted Payment Methods', 'dodo-payments-for-woocommerce'),
                'type' => 'multiselect',
                'default' => array(),
                'desc_tip' => false,
                'class' => 'wc-enhanced-select',
                'options' => $payment_methods,
                'description' => __('Select none to accept every method enabled on your Dodo Payments account. Selecting a method your account does not support will cause checkout to fail.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['billing_currency'] = array(
            'path' => 'billing_currency',
            'form' => array(
                'title' => __('Billing Currency', 'dodo-payments-for-woocommerce'),
                'type' => 'select',
                'default' => '',
                'desc_tip' => false,
                'class' => 'wc-enhanced-select',
                'options' => $currencies,
                'description' => __('Charge every order in a fixed currency. Ignored unless adaptive pricing is enabled on your Dodo Payments account.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['force_3ds'] = array(
            'path' => 'force_3ds',
            'cast' => 'bool',
            'form' => array(
                'title' => __('Force 3-D Secure', 'dodo-payments-for-woocommerce'),
                'type' => 'select',
                'default' => '',
                'desc_tip' => false,
                'options' => $tristate,
                'description' => __('Override your account\'s 3-D Secure setting for orders placed through WooCommerce.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['show_saved_payment_methods'] = array(
            'path' => 'show_saved_payment_methods',
            'cast' => 'bool',
            'form' => array(
                'title' => __('Show Saved Payment Methods', 'dodo-payments-for-woocommerce'),
                'type' => 'select',
                'default' => '',
                'desc_tip' => false,
                'options' => $tristate,
                'description' => self::with_api_default(
                    __('Offer returning customers the payment methods they used before.', 'dodo-payments-for-woocommerce'),
                    false
                ),
            ),
        );

        $schema['mandate_min_amount_inr_paise'] = array(
            'path' => 'mandate_min_amount_inr_paise',
            'cast' => 'int',
            'form' => array(
                'title' => __('INR Mandate Minimum', 'dodo-payments-for-woocommerce'),
                'type' => 'number',
                'default' => '',
                'sanitize_callback' => array(__CLASS__, 'sanitize_int'),
                'desc_tip' => false,
                'custom_attributes' => array('min' => 0, 'step' => 1),
                'description' => __('Floor for Indian e-mandate authorisations, in paise. Leave empty to use the Dodo Payments default.', 'dodo-payments-for-woocommerce'),
            ),
        );

        // -----------------------------------------------------------------
        // Checkout behaviour
        // -----------------------------------------------------------------
        $schema['section_behaviour'] = array(
            'form' => array(
                'title' => __('Checkout Behaviour', 'dodo-payments-for-woocommerce'),
                'type' => 'title',
                'description' => __('How the hosted checkout session itself behaves.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['short_link'] = array(
            'path' => 'short_link',
            'cast' => 'bool',
            'form' => array(
                'title' => __('Shortened Checkout Link', 'dodo-payments-for-woocommerce'),
                'type' => 'select',
                'default' => '',
                'desc_tip' => false,
                'options' => $tristate,
                'description' => self::with_api_default(
                    __('Send customers to a shortened checkout URL.', 'dodo-payments-for-woocommerce'),
                    false
                ),
            ),
        );

        $schema['minimal_address'] = array(
            'path' => 'minimal_address',
            'cast' => 'bool',
            'form' => array(
                'title' => __('Minimal Address', 'dodo-payments-for-woocommerce'),
                'type' => 'select',
                'default' => '',
                'desc_tip' => false,
                'options' => $tristate,
                'description' => self::with_api_default(
                    __('Per the Dodo Payments API: "Only zipcode required if confirm = true".', 'dodo-payments-for-woocommerce'),
                    false
                ),
            ),
        );

        // -----------------------------------------------------------------
        // Custom fields
        // -----------------------------------------------------------------
        $schema['section_custom_fields'] = array(
            'form' => array(
                'title' => __('Extra Checkout Questions', 'dodo-payments-for-woocommerce'),
                'type' => 'title',
                'description' => sprintf(
                    /* translators: %d: maximum number of custom fields */
                    __('Ask customers up to %d additional questions on the hosted checkout. Answers are recorded against the checkout session in your Dodo Payments dashboard; they are not currently copied onto the WooCommerce order.', 'dodo-payments-for-woocommerce'),
                    self::MAX_CUSTOM_FIELDS
                ),
            ),
        );

        $schema['custom_fields'] = array(
            'path' => 'custom_fields',
            'cast' => 'list',
            'form' => array(
                'title' => __('Questions', 'dodo-payments-for-woocommerce'),
                'type' => 'dodo_custom_fields',
                'default' => array(),
                'sanitize_callback' => array(__CLASS__, 'sanitize_custom_fields'),
                'desc_tip' => false,
            ),
        );

        // -----------------------------------------------------------------
        // Customer details
        // -----------------------------------------------------------------
        $schema['section_customer'] = array(
            'form' => array(
                'title' => __('Customer Details Sent To Checkout', 'dodo-payments-for-woocommerce'),
                'type' => 'title',
                'description' => __('Billing details WooCommerce already holds. Switch these on to save customers re-entering the same information on the hosted checkout.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['send_phone'] = array(
            'form' => array(
                'title' => __('Billing Phone Number', 'dodo-payments-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
                'desc_tip' => false,
                'label' => __('Send the customer\'s phone number', 'dodo-payments-for-woocommerce'),
                'description' => __('Sent as the checkout session customer\'s phone number when WooCommerce has one on the order, so customers are not asked for it twice. Off by default.', 'dodo-payments-for-woocommerce'),
            ),
        );

        $schema['send_business_name'] = array(
            'form' => array(
                'title' => __('Company Name', 'dodo-payments-for-woocommerce'),
                'type' => 'checkbox',
                'default' => 'no',
                'desc_tip' => false,
                'label' => __('Send the billing company as the business name', 'dodo-payments-for-woocommerce'),
                'description' => __('Used by Dodo Payments for B2B tax identification when the customer supplies a tax ID. Off by default.', 'dodo-payments-for-woocommerce'),
            ),
        );

        return $schema;
    }

    /**
     * WooCommerce form field definitions for every option in the schema.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function form_fields()
    {
        $fields = array();

        foreach (self::schema() as $key => $definition) {
            $fields[self::PREFIX . $key] = $definition['form'];
        }

        return $fields;
    }

    /**
     * Builds the Checkout Session request fragment from the saved settings.
     *
     * Walks the schema, reads each saved value through `$read`, prunes anything
     * empty and writes what survives to its position in the request body. An
     * unconfigured store therefore contributes an empty array and its request is
     * byte-for-byte what it was before these settings existed.
     *
     * `return_url` and `cancel_url` are resolved separately by the caller because
     * they depend on the order.
     *
     * @param callable $read Reads a saved option value: `fn(string $key): mixed`.
     * @return array<string, mixed> Fragment to merge into the request body.
     */
    public static function build_request_options($read)
    {
        $request = array();

        foreach (self::schema() as $key => $definition) {
            if (empty($definition['path'])) {
                continue;
            }

            $value = call_user_func($read, self::PREFIX . $key);
            $cast = isset($definition['cast']) ? $definition['cast'] : 'string';
            $value = self::cast_for_request($value, $cast);

            if (null === $value) {
                continue;
            }

            self::set_path($request, $definition['path'], $value);
        }

        return $request;
    }

    /**
     * Converts a saved setting to its request representation.
     *
     * @param mixed  $value Saved value.
     * @param string $cast  One of `bool`, `int`, `list`, `map`, `string`.
     * @return mixed|null Null when the value should be omitted from the request.
     */
    private static function cast_for_request($value, $cast)
    {
        switch ($cast) {
            case 'bool':
                if ('yes' === $value) {
                    return true;
                }
                if ('no' === $value) {
                    return false;
                }
                return null;

            case 'int':
                if (!is_numeric($value)) {
                    return null;
                }
                return (int) $value;

            case 'list':
                if (!is_array($value)) {
                    return null;
                }
                $value = array_values(array_filter($value));
                return empty($value) ? null : $value;

            case 'map':
                if (!is_array($value)) {
                    return null;
                }
                $value = array_filter(
                    $value,
                    function ($entry) {
                        return is_string($entry) && '' !== trim($entry);
                    }
                );
                return empty($value) ? null : $value;

            default:
                if (!is_string($value)) {
                    return null;
                }
                $value = trim($value);
                return '' === $value ? null : $value;
        }
    }

    /**
     * Writes a value into a nested array at a dot-delimited path.
     *
     * @param array<string, mixed> $target Array to write into, by reference.
     * @param string               $path   Dot-delimited path, e.g. `customization.theme`.
     * @param mixed                $value  Value to write.
     * @return void
     */
    private static function set_path(&$target, $path, $value)
    {
        $segments = explode('.', $path);
        $cursor = &$target;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $cursor[$segment] = $value;
                break;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = array();
            }

            $cursor = &$cursor[$segment];
        }

        unset($cursor);
    }

    /**
     * Substitutes order placeholders into a configured URL.
     *
     * @param string   $url   Configured URL, possibly containing placeholders.
     * @param WC_Order $order Order the checkout session is for.
     * @return string
     */
    public static function apply_url_placeholders($url, $order)
    {
        return strtr(
            $url,
            array(
                '{order_id}' => (string) $order->get_id(),
                '{order_key}' => (string) $order->get_order_key(),
                '{order_number}' => (string) $order->get_order_number(),
            )
        );
    }

    /**
     * Resolves the URL customers return to after paying.
     *
     * @param string   $configured Configured return URL; empty falls back to WooCommerce.
     * @param string   $fallback   URL the gateway would have used before this setting existed.
     * @param WC_Order $order      Order the checkout session is for.
     * @return string
     */
    public static function resolve_return_url($configured, $fallback, $order)
    {
        $configured = is_string($configured) ? trim($configured) : '';

        if ('' === $configured) {
            return $fallback;
        }

        return self::apply_url_placeholders($configured, $order);
    }

    /**
     * Resolves the URL customers are sent to when they abandon the hosted checkout.
     *
     * `get_cancel_order_url_raw()` is used deliberately in preference to
     * `get_cancel_order_url()`: the latter runs its output through `esc_url()`,
     * which encodes the query-string ampersands into `&#038;` entities. That is
     * correct for an HTML attribute and wrong for a URL handed to an API.
     *
     * @param string   $mode   Configured mode.
     * @param string   $custom Custom URL, used when `$mode` is `custom`.
     * @param WC_Order $order  Order the checkout session is for.
     * @return string|null Resolved URL, or null when no cancel URL should be sent.
     */
    public static function resolve_cancel_url($mode, $custom, $order)
    {
        switch ($mode) {
            case 'pay_page':
                return $order->get_checkout_payment_url();

            case 'cancel_order':
                return $order->get_cancel_order_url_raw(wc_get_cart_url());

            case 'custom':
                $custom = is_string($custom) ? trim($custom) : '';
                return '' === $custom ? null : self::apply_url_placeholders($custom, $order);

            case 'none':
            default:
                return null;
        }
    }

    // ---------------------------------------------------------------------
    // Sanitization
    // ---------------------------------------------------------------------

    /**
     * Sanitizes a plain text option.
     *
     * @param mixed $value Raw posted value.
     * @return string
     */
    public static function sanitize_text($value)
    {
        if (!is_string($value)) {
            return '';
        }

        return trim(sanitize_text_field(wp_unslash($value)));
    }

    /**
     * Sanitizes the pay button label, enforcing the API's length limit.
     *
     * @param mixed $value Raw posted value.
     * @return string
     */
    public static function sanitize_pay_button_text($value)
    {
        $value = self::sanitize_text($value);

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, self::MAX_PAY_BUTTON_TEXT);
        }

        return substr($value, 0, self::MAX_PAY_BUTTON_TEXT);
    }

    /**
     * Sanitizes a URL option.
     *
     * Placeholder braces survive `esc_url_raw`, so `{order_id}` and friends are
     * preserved without needing to be stripped and restored.
     *
     * @param mixed $value Raw posted value.
     * @return string
     */
    public static function sanitize_url($value)
    {
        if (!is_string($value)) {
            return '';
        }

        $value = trim(wp_unslash($value));

        return '' === $value ? '' : esc_url_raw($value);
    }

    /**
     * Sanitizes an optional non-negative integer option.
     *
     * Stored as a string so that "not configured" stays distinguishable from a
     * deliberate zero.
     *
     * @param mixed $value Raw posted value.
     * @return string
     */
    public static function sanitize_int($value)
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = trim(wp_unslash((string) $value));

        if ('' === $value || !is_numeric($value)) {
            return '';
        }

        return (string) max(0, (int) $value);
    }

    /**
     * Sanitizes the colour grid posted for one theme mode.
     *
     * Values are passed to the API verbatim, so the accepted formats are
     * constrained here rather than trusted: hex (3, 4, 6 or 8 digits), the
     * functional `rgb()`/`rgba()`/`hsl()`/`hsla()` notations, and CSS named
     * colours. Anything else is discarded.
     *
     * @param mixed $value Raw posted value.
     * @return array<string, string>
     */
    public static function sanitize_colors($value)
    {
        if (!is_array($value)) {
            return array();
        }

        $tokens = self::color_tokens();
        $clean = array();

        foreach ($tokens as $token => $unused_label) {
            if (!isset($value[$token]) || !is_string($value[$token])) {
                continue;
            }

            $color = trim(sanitize_text_field(wp_unslash($value[$token])));

            if ('' === $color) {
                continue;
            }

            $is_hex = (bool) preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color);
            $is_functional = (bool) preg_match('/^(?:rgb|hsl)a?\(\s*[0-9a-z.,%\s\/-]+\s*\)$/i', $color);
            $is_named = (bool) preg_match('/^[a-z]{3,32}$/i', $color);

            if ($is_hex || $is_functional || $is_named) {
                $clean[$token] = $color;
            }
        }

        return $clean;
    }

    /**
     * Sanitizes the posted custom field rows.
     *
     * Rows without a key or label are dropped rather than rejected -- the repeater
     * always posts one blank row when the admin adds and then abandons an entry.
     * Duplicate keys are collapsed to the first occurrence, and the whole list is
     * truncated to the API's maximum.
     *
     * @param mixed $value Raw posted value.
     * @return array<int, array<string, mixed>>
     */
    public static function sanitize_custom_fields($value)
    {
        if (!is_array($value)) {
            return array();
        }

        $types = self::custom_field_types();
        $rows = array();
        $seen = array();

        foreach ($value as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = isset($row['key']) ? sanitize_key(wp_unslash($row['key'])) : '';
            $label = isset($row['label']) ? sanitize_text_field(wp_unslash($row['label'])) : '';

            if ('' === $key || '' === $label || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;

            $type = isset($row['field_type']) ? sanitize_text_field(wp_unslash($row['field_type'])) : 'text';
            if (!isset($types[$type])) {
                $type = 'text';
            }

            $field = array(
                'key' => $key,
                'label' => $label,
                'field_type' => $type,
                'required' => !empty($row['required']),
            );

            $placeholder = isset($row['placeholder']) ? sanitize_text_field(wp_unslash($row['placeholder'])) : '';
            if ('' !== $placeholder) {
                $field['placeholder'] = $placeholder;
            }

            if ('dropdown' === $type) {
                $options = isset($row['options']) ? sanitize_text_field(wp_unslash($row['options'])) : '';
                $options = array_values(array_filter(array_map('trim', explode(',', $options))));

                if (!empty($options)) {
                    $field['options'] = $options;
                }
            }

            $rows[] = $field;

            if (count($rows) >= self::MAX_CUSTOM_FIELDS) {
                break;
            }
        }

        return $rows;
    }

    // ---------------------------------------------------------------------
    // Field rendering
    // ---------------------------------------------------------------------

    /**
     * Renders the description paragraph shared by the custom field renderers.
     *
     * @param array<string, mixed> $data Form field definition.
     * @return string
     */
    private static function description_html($data)
    {
        if (empty($data['description'])) {
            return '';
        }

        return '<p class="description">' . wp_kses_post($data['description']) . '</p>';
    }

    /**
     * Renders one theme mode's colour grid as a single settings row.
     *
     * Sixteen colours per mode across two modes would be thirty-two separate rows
     * on an already long settings page, so each mode is rendered as one row
     * holding a grid of pickers. Inputs post as `field_key[token]`, which
     * WooCommerce hands to the sanitizer as an array.
     *
     * @param string               $field_key Fully qualified input name.
     * @param array<string, mixed> $data      Form field definition.
     * @param array<string, mixed> $value     Saved colours, keyed by token.
     * @return string
     */
    public static function render_colors($field_key, $data, $value)
    {
        $value = is_array($value) ? $value : array();

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php echo esc_html($data['title']); ?>
            </th>
            <td class="forminp">
                <fieldset class="dodo-colors">
                    <legend class="screen-reader-text"><span><?php echo esc_html($data['title']); ?></span></legend>
                    <div class="dodo-colors__grid">
                        <?php foreach (self::color_tokens() as $token => $label) : ?>
                            <?php $input_id = $field_key . '_' . $token; ?>
                            <div class="dodo-colors__item">
                                <label for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html($label); ?></label>
                                <input
                                    type="text"
                                    class="dodo-color-input"
                                    id="<?php echo esc_attr($input_id); ?>"
                                    name="<?php echo esc_attr($field_key); ?>[<?php echo esc_attr($token); ?>]"
                                    value="<?php echo esc_attr(isset($value[$token]) ? $value[$token] : ''); ?>"
                                    data-default-color=""
                                />
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php echo wp_kses_post(self::description_html($data)); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders the repeatable extra-questions table as a single settings row.
     *
     * A hidden template row is emitted alongside the saved rows; the admin script
     * clones it when adding a question, so the markup for a new row never has to
     * be duplicated in JavaScript.
     *
     * @param string                          $field_key Fully qualified input name.
     * @param array<string, mixed>            $data      Form field definition.
     * @param array<int, array<string, mixed>> $value    Saved rows.
     * @return string
     */
    public static function render_custom_fields($field_key, $data, $value)
    {
        $rows = is_array($value) ? array_values($value) : array();

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <?php echo esc_html($data['title']); ?>
            </th>
            <td class="forminp">
                <fieldset
                    class="dodo-questions"
                    data-field-key="<?php echo esc_attr($field_key); ?>"
                    data-max="<?php echo esc_attr(self::MAX_CUSTOM_FIELDS); ?>"
                >
                    <legend class="screen-reader-text"><span><?php echo esc_html($data['title']); ?></span></legend>

                    <div class="dodo-questions__head">
                        <span><?php esc_html_e('Key', 'dodo-payments-for-woocommerce'); ?></span>
                        <span><?php esc_html_e('Label', 'dodo-payments-for-woocommerce'); ?></span>
                        <span><?php esc_html_e('Type', 'dodo-payments-for-woocommerce'); ?></span>
                        <span><?php esc_html_e('Placeholder', 'dodo-payments-for-woocommerce'); ?></span>
                        <span><?php esc_html_e('Required', 'dodo-payments-for-woocommerce'); ?></span>
                        <span class="screen-reader-text"><?php esc_html_e('Remove', 'dodo-payments-for-woocommerce'); ?></span>
                    </div>

                    <div class="dodo-questions__rows">
                        <?php foreach ($rows as $index => $row) : ?>
                            <?php
                            echo wp_kses(
                                self::render_question_row($field_key, (int) $index, $row),
                                self::allowed_row_html()
                            );
                            ?>
                        <?php endforeach; ?>
                    </div>

                    <p class="dodo-questions__empty" <?php echo empty($rows) ? '' : 'hidden'; ?>>
                        <?php esc_html_e('No extra questions. The hosted checkout asks only for what Dodo Payments requires.', 'dodo-payments-for-woocommerce'); ?>
                    </p>

                    <p>
                        <button type="button" class="button dodo-questions__add">
                            <?php esc_html_e('Add question', 'dodo-payments-for-woocommerce'); ?>
                        </button>
                        <span class="dodo-questions__limit" hidden>
                            <?php
                            printf(
                                /* translators: %d: maximum number of custom fields */
                                esc_html__('Maximum of %d questions reached.', 'dodo-payments-for-woocommerce'),
                                (int) self::MAX_CUSTOM_FIELDS
                            );
                            ?>
                        </span>
                    </p>

                    <script type="text/html" class="dodo-questions__template">
                        <?php
                        echo wp_kses(
                            self::render_question_row($field_key, 0, array(), true),
                            self::allowed_row_html()
                        );
                        ?>
                    </script>

                    <?php echo wp_kses_post(self::description_html($data)); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders a single extra-question row.
     *
     * @param string               $field_key   Fully qualified input name.
     * @param int                  $index       Row index used in the input names.
     * @param array<string, mixed> $row         Saved row values.
     * @param bool                 $is_template Whether this is the clone template.
     * @return string
     */
    private static function render_question_row($field_key, $index, $row, $is_template = false)
    {
        $placeholder_index = $is_template ? '__INDEX__' : (string) $index;
        $name = $field_key . '[' . $placeholder_index . ']';

        $key = isset($row['key']) ? $row['key'] : '';
        $label = isset($row['label']) ? $row['label'] : '';
        $type = isset($row['field_type']) ? $row['field_type'] : 'text';
        $placeholder = isset($row['placeholder']) ? $row['placeholder'] : '';
        $options = isset($row['options']) && is_array($row['options']) ? implode(', ', $row['options']) : '';
        $required = !empty($row['required']);

        ob_start();
        ?>
        <div class="dodo-questions__row">
            <input type="text" class="dodo-questions__key" name="<?php echo esc_attr($name); ?>[key]" value="<?php echo esc_attr($key); ?>" placeholder="gift_note" />
            <input type="text" name="<?php echo esc_attr($name); ?>[label]" value="<?php echo esc_attr($label); ?>" placeholder="<?php esc_attr_e('Gift message', 'dodo-payments-for-woocommerce'); ?>" />
            <select class="dodo-questions__type" name="<?php echo esc_attr($name); ?>[field_type]">
                <?php foreach (self::custom_field_types() as $type_key => $type_label) : ?>
                    <option value="<?php echo esc_attr($type_key); ?>" <?php selected($type, $type_key); ?>>
                        <?php echo esc_html($type_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="<?php echo esc_attr($name); ?>[placeholder]" value="<?php echo esc_attr($placeholder); ?>" />
            <label class="dodo-questions__required">
                <input type="checkbox" name="<?php echo esc_attr($name); ?>[required]" value="1" <?php checked($required); ?> />
                <span class="screen-reader-text"><?php esc_html_e('Required', 'dodo-payments-for-woocommerce'); ?></span>
            </label>
            <button type="button" class="button-link dodo-questions__remove" aria-label="<?php esc_attr_e('Remove question', 'dodo-payments-for-woocommerce'); ?>">&times;</button>
            <div class="dodo-questions__options" <?php echo 'dropdown' === $type ? '' : 'hidden'; ?>>
                <label>
                    <?php esc_html_e('Dropdown choices, comma separated', 'dodo-payments-for-woocommerce'); ?>
                    <input type="text" name="<?php echo esc_attr($name); ?>[options]" value="<?php echo esc_attr($options); ?>" placeholder="<?php esc_attr_e('Small, Medium, Large', 'dodo-payments-for-woocommerce'); ?>" />
                </label>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * HTML permitted inside a rendered question row.
     *
     * @return array<string, array<string, bool>>
     */
    private static function allowed_row_html()
    {
        $attributes = array(
            'class' => true,
            'id' => true,
            'name' => true,
            'value' => true,
            'type' => true,
            'placeholder' => true,
            'hidden' => true,
            'checked' => true,
            'selected' => true,
            'aria-label' => true,
            'for' => true,
        );

        return array(
            'div' => $attributes,
            'label' => $attributes,
            'span' => $attributes,
            'input' => $attributes,
            'select' => $attributes,
            'option' => $attributes,
            'button' => $attributes,
        );
    }
}
