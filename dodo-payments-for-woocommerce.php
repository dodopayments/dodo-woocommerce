<?php

/**
 * Plugin Name: Dodo Payments for WooCommerce
 * Plugin URI: https://dodopayments.com
 * Description: Dodo Payments plugin for WooCommerce. Accept payments from your customers using Dodo Payments.
 * Version: 0.2.3
 * Author: Dodo Payments
 * Developer: Dodo Payments
 * Text Domain: dodo-payments-for-woocommerce
 *
 * License:           GPL v3 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Requires PHP: 7.4
 * Requires at least: 6.1
 * Requires Plugins: woocommerce
 * Tested up to: 6.8
 * WC requires at least: 7.9
 * WC tested up to: 9.6
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// NOTE: Order of inclusion is important here. We want to include the DB classes before the API class.
require_once plugin_dir_path(__FILE__) . 'includes/class-dodo-payments-product-db.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-dodo-payments-payment-db.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-dodo-payments-coupon-db.php';

require_once plugin_dir_path(__FILE__) . 'includes/class-dodo-payments-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-dodo-standard-webhook.php';
// Create database tables on plugin activation
register_activation_hook(__FILE__, function () {
    Dodo_Payments_Product_DB::create_table();
    Dodo_Payments_Payment_DB::create_table();
    Dodo_Payments_Coupon_DB::create_table();
});

// Make the plugin HPOS compatible
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', 'dodo_payments_init');

function dodo_payments_init()
{
    if (class_exists('WC_Payment_Gateway')) {
        class Dodo_Payments_WC_Gateway extends WC_Payment_Gateway
        {
            public null|string $instructions;

            private bool $testmode;
            private string $api_key;
            private string $webhook_key;

            protected Dodo_Payments_API $dodo_payments_api;

            private string $global_tax_category;
            private bool $global_tax_inclusive;

            public function __construct()
            {
                $this->id = 'dodo_payments';
                $this->icon = plugins_url('/assets/logo.png', __FILE__);
                $this->has_fields = false;

                $this->method_title = __('Dodo Payments', 'dodo-payments-for-woocommerce');
                $this->method_description = __('Accept payments via Dodo Payments.', 'dodo-payments-for-woocommerce');

                $this->enabled = $this->get_option('enabled');
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->instructions = $this->get_option('instructions');

                $this->testmode = 'yes' === $this->get_option('testmode');
                $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('live_api_key');
                $this->webhook_key = $this->testmode ? $this->get_option('test_webhook_key') : $this->get_option('live_webhook_key');

                $this->global_tax_category = $this->get_option('global_tax_category');
                $this->global_tax_inclusive = 'yes' === $this->get_option('global_tax_inclusive');

                $this->init_form_fields();
                $this->init_settings();

                $this->init_dodo_payments_api();

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

                add_action('woocommerce_thankyou_' . $this->id, array($this, 'thank_you_page'));

                // webhook to http://<site-host>/wc-api/dodo_payments
                add_action('woocommerce_api_' . $this->id, array($this, 'webhook'));
            }

            private function init_dodo_payments_api()
            {
                $this->dodo_payments_api = new Dodo_Payments_API(array(
                    'testmode' => $this->testmode,
                    'api_key' => $this->api_key,
                    'global_tax_category' => $this->global_tax_category,
                    'global_tax_inclusive' => $this->global_tax_inclusive,
                ));
            }

            /**
             * Initializes the form fields for Dodo Payments settings page
             *
             * @return void
             *
             * @since 0.1.0
             */
            public function init_form_fields()
            {
                $webhook_help_description = '<p>' .
                    __('Webhook endpoint for Dodo Payments. Use the below URL when generating a webhook signing key on Dodo Payments Dashboard.', 'dodo-payments-for-woocommerce')
                    . '</p><p><code>' . home_url("/wc-api/{$this->id}/") . '</code></p>';
                ;

                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'dodo-payments-for-woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Enable Dodo Payments', 'dodo-payments-for-woocommerce'),
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => __('Title', 'dodo-payments-for-woocommerce'),
                        'type' => 'text',
                        'default' => __('Dodo Payments', 'dodo-payments-for-woocommerce'),
                        'desc_tip' => false,
                        'description' => __('Title for our payment method that the user will see on the checkout page.', 'dodo-payments-for-woocommerce'),
                    ),
                    'description' => array(
                        'title' => __('Description', 'dodo-payments-for-woocommerce'),
                        'type' => 'textarea',
                        'default' => __('Pay via Dodo Payments', 'dodo-payments-for-woocommerce'),
                        'desc_tip' => false,
                        'description' => __('Description for our payment method that the user will see on the checkout page.', 'dodo-payments-for-woocommerce'),
                    ),
                    'instructions' => array(
                        'title' => __('Instructions', 'dodo-payments-for-woocommerce'),
                        'type' => 'textarea',
                        'default' => '',
                        'desc_tip' => false,
                        'description' => __('Instructions that will be added to the thank you page and emails.', 'dodo-payments-for-woocommerce'),
                    ),
                    'testmode' => array(
                        'title' => __('Test Mode', 'dodo-payments-for-woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Enable Test Mode, <b>No actual payments will be made, always remember to disable this when you are ready to go live</b>', 'dodo-payments-for-woocommerce'),
                        'default' => 'no'
                    ),
                    'live_api_key' => array(
                        'title' => __('Live API Key', 'dodo-payments-for-woocommerce'),
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => false,
                        'description' => __('Your Live API Key. Required to receive payments. Generate one from <b>Dodo Payments (Live Mode) &gt; Developer &gt; API Keys</b>', 'dodo-payments-for-woocommerce'),
                    ),
                    'live_webhook_key' => array(
                        'title' => __('Live Webhook Signing Key', 'dodo-payments-for-woocommerce'),
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => false,
                        'description' => __('Your Live Webhook Signing Key. Required to sync status for payments, recommended for setup. Generate one from <b>Dodo Payments (Live Mode) &gt; Developer &gt; Webhooks</b>, use the URL at the bottom of this page as the webhook URL.', 'dodo-payments-for-woocommerce'),
                    ),
                    'test_api_key' => array(
                        'title' => __('Test API Key', 'dodo-payments-for-woocommerce'),
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => false,
                        'description' => __('Your Test API Key. Optional, only required if you want to receive test payments. Generate one from <b>Dodo Payments (Test Mode) &gt; Developer &gt; API Keys</b>', 'dodo-payments-for-woocommerce'),
                    ),
                    'test_webhook_key' => array(
                        'title' => __('Test Webhook Signing Key', 'dodo-payments-for-woocommerce'),
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => false,
                        'description' => __('Your Test Webhook Signing Key. Optional, only required if you want to receive test payments. Generate one from <b>Dodo Payments (Test Mode) &gt; Developer &gt; Webhooks</b>, use the URL at the bottom of this page as the webhook URL.', 'dodo-payments-for-woocommerce'),
                    ),
                    'api_connection_test' => array(
                        'title' => __('Test API Connection', 'dodo-payments-for-woocommerce'),
                        'type' => 'title',
                        'description' => '<button type="button" class="button-secondary" id="dodo_test_api_connection">' . __('Test API Connection', 'dodo-payments-for-woocommerce') . '</button><span id="dodo_api_connection_result"></span>',
                    ),
                    'diagnostics' => array(
                        'title' => __('Gateway Diagnostics', 'dodo-payments-for-woocommerce'),
                        'type' => 'title',
                        'description' => '<button type="button" class="button-secondary" id="dodo_run_diagnostics">' . __('Run Diagnostics', 'dodo-payments-for-woocommerce') . '</button><div id="dodo_diagnostics_result" style="margin-top: 10px;"></div>',
                    ),
                    'global_tax_category' => array(
                        'title' => __('Global Tax Category', 'dodo-payments-for-woocommerce'),
                        'type' => 'select',
                        'options' => array(
                            'digital_products' => __('Digital Products', 'dodo-payments-for-woocommerce'),
                            'saas' => __('SaaS', 'dodo-payments-for-woocommerce'),
                            'e_book' => __('E-Book', 'dodo-payments-for-woocommerce'),
                            'edtech' => __('EdTech', 'dodo-payments-for-woocommerce'),
                        ),
                        'default' => 'digital_products',
                        'desc_tip' => false,
                        'description' => __('Select the tax category for all products. You can override this on a per-product basis on Dodo Payments Dashboard.', 'dodo-payments-for-woocommerce'),
                    ),
                    'global_tax_inclusive' => array(
                        'title' => __('All Prices are Tax Inclusive', 'dodo-payments-for-woocommerce'),
                        'type' => 'checkbox',
                        'default' => 'no',
                        'desc_tip' => false,
                        'description' => __('Select if tax is included on all product prices. You can override this on a per-product basis on Dodo Payments Dashboard.', 'dodo-payments-for-woocommerce'),
                    ),
                    'webhook_endpoint' => array(
                        'title' => __('Webhook Endpoint', 'dodo-payments-for-woocommerce'),
                        'type' => 'title',
                        'description' => $webhook_help_description,
                    )
                );
            }

            public function process_payment($order_id)
            {
                $order = wc_get_order($order_id);

                $order->update_status('pending-payment', __('Awaiting payment via Dodo Payments', 'dodo-payments-for-woocommerce'));
                wc_reduce_stock_levels($order_id);

                WC()->cart->empty_cart();

                if ($order->get_total() == 0) {
                    $order->payment_complete();
                }

                $res = $this->do_payment($order);
                return $res;
            }

            public function thank_you_page()
            {
                if ($this->instructions) {
                    echo wp_kses_post(wpautop(wptexturize($this->instructions)));
                }
            }

            public function do_payment($order)
            {
                try {
                    $synced_products = $this->sync_products($order);

                    /** @var string[] */
                    $coupons = $order->get_coupon_codes();
                    $dodo_discount_code = null;

                    if (count($coupons) > 1) {
                        $message = __('Dodo Payments: Multiple Coupon codes are not supported.', 'dodo-payments-for-woocommerce');
                        $order->add_order_note($message);
                        wc_add_notice($message, 'error');

                        return array('result' => 'failure');
                    }

                    if (count($coupons) == 1) {
                        $coupon_code = $coupons[0];

                        try {
                            $dodo_discount_code = $this->sync_coupon($coupon_code);
                        } catch (Dodo_Payments_Cart_Exception $e) {
                            wc_add_notice($e->getMessage(), 'error');

                            return array('result' => 'failure');
                        } catch (Exception $e) {
                            $order->add_order_note(
                                sprintf(
                                    // translators: %1$s: Error message
                                    __('Dodo Payments Error: %1$s', 'dodo-payments-for-woocommerce'),
                                    $e->getMessage()
                                )
                            );
                            wc_add_notice(__('Dodo Payments: an unexpected error occured.', 'dodo-payments-for-woocommerce'), 'error');

                            return array('result' => 'failure');
                        }
                    }

                    $payment = $this->dodo_payments_api->create_payment($order, $synced_products, $dodo_discount_code, $this->get_return_url($order));
                } catch (Exception $e) {
                    $order->add_order_note(
                        sprintf(
                            // translators: %1$s: Error message
                            __('Dodo Payments Error: %1$s', 'dodo-payments-for-woocommerce'),
                            $e->getMessage()
                        )
                    );

                    return array('result' => 'failure');
                }

                if (isset($payment['payment_link']) && isset($payment['payment_id'])) {
                    // Save the payment mapping
                    Dodo_Payments_Payment_DB::save_mapping($order->get_id(), $payment['payment_id']);

                    $order->add_order_note(
                        sprintf(
                            // translators: %1$s: Payment ID
                            __('Payment created in Dodo Payments: %1$s', 'dodo-payments-for-woocommerce'),
                            $payment['payment_id']
                        )
                    );

                    return array(
                        'result' => 'success',
                        'redirect' => $payment['payment_link']
                    );
                } else {
                    $order->add_order_note(
                        __('Failed to create payment in Dodo Payments: Invalid response', 'dodo-payments-for-woocommerce')
                    );
                    return array('result' => 'failure');
                }
            }

            /**
             * Syncs products from WooCommerce to Dodo Payments
             *
             * @param \WC_Order $order
             * @return array{amount: mixed, product_id: string, quantity: mixed}[]
             *
             * @since 0.1.0
             */
            private function sync_products($order)
            {
                $items = $order->get_items();
                $mapped_products = array();

                foreach ($items as $item) {
                    $product = $item->get_product();
                    $local_product_id = $product->get_id();

                    // Check if product is already mapped
                    $dodo_product_id = Dodo_Payments_Product_DB::get_dodo_product_id($local_product_id);
                    $dodo_product = null;

                    if ($dodo_product_id) {
                        $dodo_product = $this->dodo_payments_api->get_product($dodo_product_id);

                        if (!!$dodo_product) {
                            try {
                                $this->dodo_payments_api->update_product($dodo_product['product_id'], $product);
                            } catch (Exception $e) {
                                $order->add_order_note(
                                    sprintf(
                                        // translators: %1$s: Error message
                                        __('Failed to update product in Dodo Payments: %1$s', 'dodo-payments-for-woocommerce'),
                                        $e->getMessage(),
                                    )
                                );

                                continue;
                            }
                        }
                    }

                    if (!$dodo_product_id || !$dodo_product) {
                        try {
                            $response_body = $this->dodo_payments_api->create_product($product);
                        } catch (Exception $e) {
                            $order->add_order_note(
                                sprintf(
                                    // translators: %1$s: Error message
                                    __('Dodo Payments Error: %1$s', 'dodo-payments-for-woocommerce'),
                                    $e->getMessage(),
                                )
                            );

                            continue;
                        }

                        $dodo_product_id = $response_body['product_id'];
                        // Save the mapping
                        Dodo_Payments_Product_DB::save_mapping($local_product_id, $dodo_product_id);

                        // sync image to dodo payments
                        try {
                            $this->dodo_payments_api->sync_image_for_product($product, $dodo_product_id);
                        } catch (Exception $e) {
                            $order->add_order_note(
                                sprintf(
                                    // translators: %1$s: Error message
                                    __('Failed to sync image for product in Dodo Payments: %1$s', 'dodo-payments-for-woocommerce'),
                                    $e->getMessage(),
                                )
                            );
                        }
                    }

                    $mapped_products[] = array(
                        'product_id' => $dodo_product_id,
                        'quantity' => $item->get_quantity(),
                        'amount' => (int) $product->get_price() * 100
                    );
                }

                return $mapped_products;
            }

            /**
             * Syncs a coupon from WooCommerce to Dodo Payments
             *
             * @param string $coupon_code
             * @return string Dodo Payments discount code
             * @throws Dodo_Payments_Cart_Exception If the coupon is not a percentage discount code
             * @throws Exception If the coupon could not be synced
             *
             * @since 0.2.0
             */
            private function sync_coupon($coupon_code)
            {
                $coupon = new WC_Coupon($coupon_code);
                $coupon_type = $coupon->get_discount_type();

                // TODO: support more discount types later on
                if ($coupon_type !== 'percent') {
                    throw new Dodo_Payments_Cart_Exception('Dodo Payments: Only percentage discount codes are supported.');
                }

                $dodo_discount_id = Dodo_Payments_Coupon_DB::get_dodo_coupon_id($coupon->get_id());
                $dodo_discount = null;

                $dodo_discount_code = null;

                $dodo_discount_req_body = self::wc_coupon_to_dodo_discount_body($coupon);

                if ($dodo_discount_id) {
                    $dodo_discount = $this->dodo_payments_api->get_discount_code($dodo_discount_id);

                    if (!!$dodo_discount) {
                        $dodo_discount = $this->dodo_payments_api->update_discount_code($dodo_discount_id, $dodo_discount_req_body);
                        $dodo_discount_code = $dodo_discount['code'];
                    }
                }

                if (!$dodo_discount_id || !$dodo_discount) {
                    // FIXME: This will not work if the discount code already exists with a different id
                    // need to find a way to get the id of the existing discount code
                    $dodo_discount = $this->dodo_payments_api->create_discount_code($dodo_discount_req_body);

                    $dodo_discount_id = $dodo_discount['discount_id'];
                    $dodo_discount_code = $dodo_discount['code'];

                    // Save the mapping
                    Dodo_Payments_Coupon_DB::save_mapping($coupon->get_id(), $dodo_discount_id);
                }

                return $dodo_discount_code;
            }

            private static function wc_coupon_to_dodo_discount_body($coupon)
            {
                $coupon_amount = (int) $coupon->get_amount() * 100;
                /** @var int|null */
                $usage_limit = $coupon->get_usage_limit() > 0 ? (int) $coupon->get_usage_limit() : null;

                /** @var string[] */
                $product_ids = $coupon->get_product_ids();

                $dodo_product_ids = array();
                foreach ($product_ids as $product_id) {
                    $dodo_product_id = Dodo_Payments_Product_DB::get_dodo_product_id($product_id);

                    if ($dodo_product_id) {
                        array_push($dodo_product_ids, $dodo_product_id);
                    }
                }

                /** @var string[]|null */
                $restricted_to = count($dodo_product_ids) > 0 ? $dodo_product_ids : null;
                /** @var string|null */
                $expires_at = $coupon->get_date_expires() ? (string) $coupon->get_date_expires() : null;

                return array(
                    'type' => 'percentage',
                    'code' => $coupon->get_code(),
                    'amount' => $coupon_amount,
                    'expires_at' => $expires_at,
                    'usage_limit' => $usage_limit,
                    'restricted_to' => $restricted_to,
                );
            }

            private function get_base_url()
            {
                return $this->testmode ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';
            }

            public function webhook()
            {
                $headers = [
                    'webhook-signature' => isset($_SERVER['HTTP_WEBHOOK_SIGNATURE']) ? sanitize_text_field($_SERVER['HTTP_WEBHOOK_SIGNATURE']) : '',
                    'webhook-id' => isset($_SERVER['HTTP_WEBHOOK_ID']) ? sanitize_text_field($_SERVER['HTTP_WEBHOOK_ID']) : '',
                    'webhook-timestamp' => isset($_SERVER['HTTP_WEBHOOK_TIMESTAMP']) ? sanitize_text_field($_SERVER['HTTP_WEBHOOK_TIMESTAMP']) : '',
                ];

                $body = sanitize_text_field(file_get_contents('php://input'));

                try {
                    $webhook = new Dodo_Payments_Standard_Webhook($this->webhook_key);
                } catch (\Exception $e) {
                    error_log('Dodo Payments: Invalid webhook key: ' . $e->getMessage());

                    // Silently consume the webhook event
                    status_header(200);
                    return;
                }

                try {
                    $payload = $webhook->verify($body, $headers);
                } catch (Exception $e) {
                    error_log('Dodo Payments: Could not verify webhook event: ' . $e->getMessage());

                    // Silently consume the webhook event
                    status_header(200);
                    return;
                }

                // Can be
                // payment.succeeded, payment.failed, payment.processing, payment.cancelled,
                // refund.succeeded, refund.failed,
                // dispute.opened, dispute.expired, dispute.accepted, dispute.cancelled,
                // dispute.challenged, dispute.won, dispute.lost,
                // subscription.active, subscription.renewed, subscription.on_hold,
                // subscription.paused, subscription.cancelled, subscription.failed,
                // subscription.expired,
                // license_key.created
                $type = $payload['type'];

                if (substr($type, 0, 7) === 'payment') {
                    $payment_id = $payload['data']['payment_id'];
                    $order_id = Dodo_Payments_Payment_DB::get_order_id($payment_id);

                    if (!$order_id) {
                        error_log('Dodo Payments: Could not find order_id for payment: ' . $payment_id);

                        // Silently consume the webhook event
                        status_header(200);
                        return;
                    }

                    $order = wc_get_order($order_id);

                    if (!$order) {
                        error_log('Dodo Payments: Could not find order: ' . $order_id);

                        // Silently consume the webhook event
                        status_header(200);
                        return;
                    }

                    $order->payment_complete($payment_id);

                    switch ($type) {
                        case 'payment.succeeded':
                            $order->update_status('completed', __('Payment completed by Dodo Payments', 'dodo-payments-for-woocommerce'));
                            break;


                        case 'payment.failed':
                            $order->update_status('failed', __('Payment failed by Dodo Payments', 'dodo-payments-for-woocommerce'));
                            wc_increase_stock_levels($order_id);
                            break;

                        case 'payment.cancelled':
                            $order->update_status('cancelled', __('Payment cancelled by Dodo Payments', 'dodo-payments-for-woocommerce'));
                            wc_increase_stock_levels($order_id);
                            break;

                        case 'payment.processing':
                        default:
                            $order->update_status('processing', __('Payment processing by Dodo Payments', 'dodo-payments-for-woocommerce'));
                            break;
                    }
                }

                if (substr($type, 0, 6) === 'refund') {
                    $payment_id = $payload['data']['payment_id'];
                    $order_id = Dodo_Payments_Payment_DB::get_order_id($payment_id);

                    if (!$order_id) {
                        error_log('Dodo Payments: Could not find order for payment: ' . $payment_id);

                        // Silently consume the webhook event
                        status_header(200);
                        return;
                    }

                    $order = wc_get_order($order_id);

                    if (!$order) {
                        error_log('Dodo Payments: Could not find order: ' . $order_id);

                        // Silently consume the webhook event
                        status_header(200);
                        return;
                    }

                    switch ($type) {
                        case 'refund.succeeded':
                            $order->update_status('refunded', __('Payment refunded by Dodo Payments', 'dodo-payments-for-woocommerce'));

                            $order->add_order_note(
                                sprintf(
                                    // translators: %1$s: Payment ID, %2$s: Refund ID
                                    __('Refunded payment in Dodo Payments. Payment ID: %$1s, Refund ID: %2$s', 'dodo-payments-for-woocommerce'),
                                    $payment_id,
                                    $payload['data']['refund_id']
                                )
                            );
                            break;

                        case 'refund.failed':
                            $order->add_order_note(
                                sprintf(
                                    // translators: %1$s: Payment ID, %2$s: Refund ID
                                    __('Refund failed in Dodo Payments. Payment ID: %$1s, Refund ID: %2$s', 'dodo-payments-for-woocommerce'),
                                    $payment_id,
                                    $payload['data']['refund_id']
                                )
                            );
                            break;
                    }
                }

                status_header(200);
                return;
            }

            /**
             * Tests the API connection to ensure credentials are working
             */
            public function test_api_connection() {
                // Verify nonce
                check_ajax_referer('dodo_test_api_connection', 'security');

                // Refresh settings from POST data
                $this->init_settings();
                $testmode = isset($_POST['testmode']) ? 'yes' === $_POST['testmode'] : ('yes' === $this->get_option('testmode'));
                $api_key = $testmode ? 
                    (isset($_POST['test_api_key']) ? $_POST['test_api_key'] : $this->get_option('test_api_key')) : 
                    (isset($_POST['live_api_key']) ? $_POST['live_api_key'] : $this->get_option('live_api_key'));

                if (empty($api_key)) {
                    wp_send_json_error(array(
                        'message' => __('API key is empty. Please enter an API key.', 'dodo-payments-for-woocommerce')
                    ));
                    return;
                }

                // Set up API with current or form settings
                $api = new Dodo_Payments_API(array(
                    'testmode' => $testmode,
                    'api_key' => $api_key,
                    'global_tax_category' => isset($_POST['global_tax_category']) ? $_POST['global_tax_category'] : $this->get_option('global_tax_category'),
                    'global_tax_inclusive' => isset($_POST['global_tax_inclusive']) ? 'yes' === $_POST['global_tax_inclusive'] : ('yes' === $this->get_option('global_tax_inclusive')),
                ));

                // Test the connection
                $result = $api->test_connection();
                
                if (is_wp_error($result)) {
                    wp_send_json_error(array(
                        'message' => sprintf(
                            __('API connection failed: %s', 'dodo-payments-for-woocommerce'),
                            $result->get_error_message()
                        )
                    ));
                    return;
                }

                wp_send_json_success(array(
                    'message' => __('API connection successful! Your Dodo Payments API key is working correctly.', 'dodo-payments-for-woocommerce')
                ));
            }
        }
    }
}

add_filter('woocommerce_payment_gateways', 'dodo_payments_add_gateway_class_to_woo');
function dodo_payments_add_gateway_class_to_woo($gateways)
{
    $gateways[] = 'Dodo_Payments_WC_Gateway';
    return $gateways;
}

// Add the API test AJAX handler
add_action('wp_ajax_dodo_test_api_connection', 'dodo_test_api_connection_callback');
function dodo_test_api_connection_callback() {
    $gateway = new Dodo_Payments_WC_Gateway();
    $gateway->test_api_connection();
}

// Add JavaScript to the admin footer to handle the test button
add_action('admin_footer', 'dodo_payments_admin_footer_js');
function dodo_payments_admin_footer_js() {
    // Only add to the payment methods settings page
    if (!isset($_GET['page']) || $_GET['page'] !== 'wc-settings' || 
        !isset($_GET['tab']) || $_GET['tab'] !== 'checkout' ||
        !isset($_GET['section']) || $_GET['section'] !== 'dodo_payments') {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(function($) {
        $('#dodo_test_api_connection').on('click', function() {
            var $button = $(this);
            var $result = $('#dodo_api_connection_result');
            
            $button.prop('disabled', true);
            $result.html('<span style="margin-left: 10px; color: #777;"><?php echo esc_js(__('Testing connection...', 'dodo-payments-for-woocommerce')); ?></span>');
            
            var data = {
                action: 'dodo_test_api_connection',
                security: '<?php echo wp_create_nonce('dodo_test_api_connection'); ?>',
                testmode: $('#woocommerce_dodo_payments_testmode').is(':checked') ? 'yes' : 'no',
                test_api_key: $('#woocommerce_dodo_payments_test_api_key').val(),
                live_api_key: $('#woocommerce_dodo_payments_live_api_key').val(),
                global_tax_category: $('#woocommerce_dodo_payments_global_tax_category').val(),
                global_tax_inclusive: $('#woocommerce_dodo_payments_global_tax_inclusive').is(':checked') ? 'yes' : 'no'
            };
            
            $.post(ajaxurl, data, function(response) {
                $button.prop('disabled', false);
                
                if (response.success) {
                    $result.html('<span style="margin-left: 10px; color: green;">' + response.data.message + '</span>');
                } else {
                    $result.html('<span style="margin-left: 10px; color: red;">' + response.data.message + '</span>');
                }
            }).fail(function() {
                $button.prop('disabled', false);
                $result.html('<span style="margin-left: 10px; color: red;"><?php echo esc_js(__('Connection test failed. Please try again.', 'dodo-payments-for-woocommerce')); ?></span>');
            });
        });

        $('#dodo_run_diagnostics').on('click', function() {
            var $button = $(this);
            var $result = $('#dodo_diagnostics_result');
            
            $button.prop('disabled', true);
            $result.html('<div style="color: #777;"><?php echo esc_js(__('Running diagnostics...', 'dodo-payments-for-woocommerce')); ?></div>');
            
            var data = {
                action: 'dodo_run_diagnostics',
                security: '<?php echo wp_create_nonce('dodo_run_diagnostics'); ?>',
                testmode: $('#woocommerce_dodo_payments_testmode').is(':checked') ? 'yes' : 'no',
                test_api_key: $('#woocommerce_dodo_payments_test_api_key').val(),
                live_api_key: $('#woocommerce_dodo_payments_live_api_key').val()
            };
            
            $.post(ajaxurl, data, function(response) {
                $button.prop('disabled', false);
                
                if (response.success) {
                    var html = '<div style="border: 1px solid #ddd; padding: 15px; background: #f9f9f9;">';
                    html += '<h3 style="margin-top: 0;"><?php echo esc_js(__('Diagnostics Results', 'dodo-payments-for-woocommerce')); ?></h3>';
                    
                    // Add results
                    $.each(response.data.checks, function(index, check) {
                        var status_color = check.status === 'pass' ? 'green' : (check.status === 'warn' ? 'orange' : 'red');
                        html += '<div style="margin-bottom: 10px;">';
                        html += '<div style="font-weight: bold;"><span style="display: inline-block; width: 20px; color: ' + status_color + ';">' + (check.status === 'pass' ? '✓' : (check.status === 'warn' ? '⚠' : '✗')) + '</span> ' + check.title + '</div>';
                        if (check.message) {
                            html += '<div style="padding-left: 20px;">' + check.message + '</div>';
                        }
                        html += '</div>';
                    });
                    
                    // Add overall status
                    html += '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">';
                    html += '<strong><?php echo esc_js(__('Summary:', 'dodo-payments-for-woocommerce')); ?></strong> ' + response.data.summary;
                    html += '</div>';
                    
                    html += '</div>';
                    $result.html(html);
                } else {
                    $result.html('<div style="color: red;">' + response.data.message + '</div>');
                }
            }).fail(function() {
                $button.prop('disabled', false);
                $result.html('<div style="color: red;"><?php echo esc_js(__('Diagnostics failed. Please try again.', 'dodo-payments-for-woocommerce')); ?></div>');
            });
        });
    });
    </script>
    <?php
}

// Add diagnostics AJAX handler
add_action('wp_ajax_dodo_run_diagnostics', 'dodo_run_diagnostics_callback');
function dodo_run_diagnostics_callback() {
    try {
        // Verify nonce
        check_ajax_referer('dodo_run_diagnostics', 'security');
        
        $checks = array();
        $failed = 0;
        $warnings = 0;
        
        // Check 1: Is the gateway enabled?
        $gateway_settings = get_option('woocommerce_dodo_payments_settings', array());
        $is_enabled = isset($gateway_settings['enabled']) && $gateway_settings['enabled'] === 'yes';
        
        if ($is_enabled) {
            $checks[] = array(
                'title' => __('Gateway Enabled', 'dodo-payments-for-woocommerce'),
                'status' => 'pass',
                'message' => __('The Dodo Payments gateway is enabled.', 'dodo-payments-for-woocommerce')
            );
        } else {
            $checks[] = array(
                'title' => __('Gateway Enabled', 'dodo-payments-for-woocommerce'),
                'status' => 'fail',
                'message' => __('The Dodo Payments gateway is disabled. Enable it to make it appear at checkout.', 'dodo-payments-for-woocommerce')
            );
            $failed++;
        }
        
        // Check 2: API Keys
        $testmode = isset($_POST['testmode']) ? $_POST['testmode'] === 'yes' : (isset($gateway_settings['testmode']) && $gateway_settings['testmode'] === 'yes');
        $api_key = $testmode ? 
            (isset($_POST['test_api_key']) && !empty($_POST['test_api_key']) ? $_POST['test_api_key'] : (isset($gateway_settings['test_api_key']) ? $gateway_settings['test_api_key'] : '')) : 
            (isset($_POST['live_api_key']) && !empty($_POST['live_api_key']) ? $_POST['live_api_key'] : (isset($gateway_settings['live_api_key']) ? $gateway_settings['live_api_key'] : ''));
        
        if (!empty($api_key)) {
            $checks[] = array(
                'title' => __('API Key', 'dodo-payments-for-woocommerce'),
                'status' => 'pass',
                'message' => $testmode ? 
                    __('Test API Key is set.', 'dodo-payments-for-woocommerce') : 
                    __('Live API Key is set.', 'dodo-payments-for-woocommerce')
            );
        } else {
            $checks[] = array(
                'title' => __('API Key', 'dodo-payments-for-woocommerce'),
                'status' => 'fail',
                'message' => $testmode ? 
                    __('Test API Key is empty. The gateway will not work without a valid API key.', 'dodo-payments-for-woocommerce') : 
                    __('Live API Key is empty. The gateway will not work without a valid API key.', 'dodo-payments-for-woocommerce')
            );
            $failed++;
        }

        // Check 2b: Webhook Key
        $webhook_key = $testmode ? 
            (isset($gateway_settings['test_webhook_key']) ? $gateway_settings['test_webhook_key'] : '') : 
            (isset($gateway_settings['live_webhook_key']) ? $gateway_settings['live_webhook_key'] : '');
        
        if (!empty($webhook_key)) {
            $checks[] = array(
                'title' => __('Webhook Key', 'dodo-payments-for-woocommerce'),
                'status' => 'pass',
                'message' => $testmode ? 
                    __('Test Webhook Key is set.', 'dodo-payments-for-woocommerce') : 
                    __('Live Webhook Key is set.', 'dodo-payments-for-woocommerce')
            );
        } else {
            $checks[] = array(
                'title' => __('Webhook Key', 'dodo-payments-for-woocommerce'),
                'status' => 'warn',
                'message' => $testmode ? 
                    __('Test Webhook Key is empty. While not required for the gateway to appear, it is recommended for proper payment status updates.', 'dodo-payments-for-woocommerce') : 
                    __('Live Webhook Key is empty. While not required for the gateway to appear, it is recommended for proper payment status updates.', 'dodo-payments-for-woocommerce')
            );
            $warnings++;
        }
        
        // Check 3: Currency
        $woocommerce_currency = get_woocommerce_currency();
        $supported_currencies = array('USD', 'INR', 'EUR', 'GBP', 'CAD', 'AUD'); // Add all currencies supported by Dodo Payments
        
        if (in_array($woocommerce_currency, $supported_currencies)) {
            $checks[] = array(
                'title' => __('Currency Support', 'dodo-payments-for-woocommerce'),
                'status' => 'pass',
                'message' => sprintf(__('Your store currency (%s) is supported by Dodo Payments.', 'dodo-payments-for-woocommerce'), $woocommerce_currency)
            );
        } else {
            $checks[] = array(
                'title' => __('Currency Support', 'dodo-payments-for-woocommerce'),
                'status' => 'warn',
                'message' => sprintf(__('Your store currency (%s) may not be fully supported by Dodo Payments. This could cause the gateway to not appear at checkout.', 'dodo-payments-for-woocommerce'), $woocommerce_currency)
            );
            $warnings++;
        }
        
        // Check 4: SSL (recommended for production)
        $is_ssl = is_ssl();
        if ($is_ssl || $testmode) {
            $status = $is_ssl ? 'pass' : 'warn';
            $message = $is_ssl ? 
                __('Your site is using SSL/HTTPS, which is recommended for processing payments.', 'dodo-payments-for-woocommerce') : 
                __('Your site is not using SSL/HTTPS. This is acceptable in test mode, but SSL is strongly recommended for live payments.', 'dodo-payments-for-woocommerce');
            
            $checks[] = array(
                'title' => __('SSL/HTTPS', 'dodo-payments-for-woocommerce'),
                'status' => $status,
                'message' => $message
            );
            
            if ($status === 'warn') {
                $warnings++;
            }
        } else {
            $checks[] = array(
                'title' => __('SSL/HTTPS', 'dodo-payments-for-woocommerce'),
                'status' => 'fail',
                'message' => __('Your site is not using SSL/HTTPS, which is required for processing live payments securely. This could cause the gateway to not appear at checkout.', 'dodo-payments-for-woocommerce')
            );
            $failed++;
        }
        
        // Check 5: WooCommerce Version
        $wc_version = WC()->version;
        $min_wc_version = '7.9'; // Minimum required WC version from plugin header
        
        if (version_compare($wc_version, $min_wc_version, '>=')) {
            $checks[] = array(
                'title' => __('WooCommerce Version', 'dodo-payments-for-woocommerce'),
                'status' => 'pass',
                'message' => sprintf(__('WooCommerce version %s meets the minimum required version %s.', 'dodo-payments-for-woocommerce'), $wc_version, $min_wc_version)
            );
        } else {
            $checks[] = array(
                'title' => __('WooCommerce Version', 'dodo-payments-for-woocommerce'),
                'status' => 'fail',
                'message' => sprintf(__('WooCommerce version %s does not meet the minimum required version %s. Please update WooCommerce.', 'dodo-payments-for-woocommerce'), $wc_version, $min_wc_version)
            );
            $failed++;
        }
        
        // Check 6: Is HPOS (COT) enabled and compatible?
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            // Check if the is_feature_enabled method exists (WooCommerce 6.5+)
            $is_hpos_enabled = false;
            $is_plugin_compatible = true; // This plugin declares compatibility
            
            if (method_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil', 'is_feature_enabled')) {
                $is_hpos_enabled = \Automattic\WooCommerce\Utilities\FeaturesUtil::is_feature_enabled('custom_order_tables');
            } else {
                // For older WooCommerce versions, HPOS is not available
                $checks[] = array(
                    'title' => __('HPOS Compatibility', 'dodo-payments-for-woocommerce'),
                    'status' => 'pass',
                    'message' => __('Your WooCommerce version doesn\'t support High-Performance Order Storage yet. No compatibility issues expected.', 'dodo-payments-for-woocommerce')
                );
                // Skip the rest of this check
                goto skip_hpos_check;
            }
            
            if ($is_hpos_enabled) {
                if ($is_plugin_compatible) {
                    $checks[] = array(
                        'title' => __('HPOS Compatibility', 'dodo-payments-for-woocommerce'),
                        'status' => 'pass',
                        'message' => __('High-Performance Order Storage is enabled and this plugin is compatible.', 'dodo-payments-for-woocommerce')
                    );
                } else {
                    $checks[] = array(
                        'title' => __('HPOS Compatibility', 'dodo-payments-for-woocommerce'),
                        'status' => 'warn',
                        'message' => __('High-Performance Order Storage is enabled but this plugin has not explicitly declared compatibility. This might cause issues.', 'dodo-payments-for-woocommerce')
                    );
                    $warnings++;
                }
            } else {
                $checks[] = array(
                    'title' => __('HPOS Compatibility', 'dodo-payments-for-woocommerce'),
                    'status' => 'pass',
                    'message' => __('High-Performance Order Storage is not enabled. No compatibility issues expected.', 'dodo-payments-for-woocommerce')
                );
            }
        } else {
            $checks[] = array(
                'title' => __('HPOS Compatibility', 'dodo-payments-for-woocommerce'),
                'status' => 'pass',
                'message' => __('Your WooCommerce version doesn\'t support High-Performance Order Storage. No compatibility issues expected.', 'dodo-payments-for-woocommerce')
            );
        }
        
        skip_hpos_check:
        
        // Check 7: PHP Version
        $php_version = phpversion();
        $min_php_version = '7.4'; // From plugin header
        
        if (version_compare($php_version, $min_php_version, '>=')) {
            $checks[] = array(
                'title' => __('PHP Version', 'dodo-payments-for-woocommerce'),
                'status' => 'pass',
                'message' => sprintf(__('PHP version %s meets the minimum required version %s.', 'dodo-payments-for-woocommerce'), $php_version, $min_php_version)
            );
        } else {
            $checks[] = array(
                'title' => __('PHP Version', 'dodo-payments-for-woocommerce'),
                'status' => 'fail',
                'message' => sprintf(__('PHP version %s does not meet the minimum required version %s. Please update PHP.', 'dodo-payments-for-woocommerce'), $php_version, $min_php_version)
            );
            $failed++;
        }

        // Check 8: Active Payment Gateways
        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
        $is_dodo_registered = false;
        $gateway_list = '';
        
        foreach (WC()->payment_gateways->payment_gateways() as $gateway) {
            if ($gateway->id === 'dodo_payments') {
                $is_dodo_registered = true;
                break;
            }
        }
        
        if ($is_dodo_registered) {
            $checks[] = array(
                'title' => __('Gateway Registration', 'dodo-payments-for-woocommerce'),
                'status' => 'pass',
                'message' => __('Dodo Payments gateway is properly registered with WooCommerce.', 'dodo-payments-for-woocommerce')
            );
            
            // Now check if it's available at checkout
            $is_available = isset($available_gateways['dodo_payments']);
            
            if ($is_available) {
                $checks[] = array(
                    'title' => __('Gateway Availability', 'dodo-payments-for-woocommerce'),
                    'status' => 'pass',
                    'message' => __('Dodo Payments gateway is available for checkout.', 'dodo-payments-for-woocommerce')
                );
            } else {
                $checks[] = array(
                    'title' => __('Gateway Availability', 'dodo-payments-for-woocommerce'),
                    'status' => 'fail',
                    'message' => __('Dodo Payments gateway is registered but not available at checkout. This could be due to payment gateway restrictions or conditions.', 'dodo-payments-for-woocommerce')
                );
                $failed++;
                
                // Add additional diagnostic info about available gateways
                $available_gateway_ids = array_keys($available_gateways);
                if (!empty($available_gateway_ids)) {
                    $gateway_list = implode(', ', $available_gateway_ids);
                    $checks[] = array(
                        'title' => __('Available Gateways', 'dodo-payments-for-woocommerce'),
                        'status' => 'warn',
                        'message' => sprintf(__('Currently available gateways: %s', 'dodo-payments-for-woocommerce'), $gateway_list)
                    );
                    $warnings++;
                } else {
                    $checks[] = array(
                        'title' => __('Available Gateways', 'dodo-payments-for-woocommerce'),
                        'status' => 'warn',
                        'message' => __('No payment gateways are currently available at checkout. This might indicate a more general issue with WooCommerce payments.', 'dodo-payments-for-woocommerce')
                    );
                    $warnings++;
                }
            }
        } else {
            $checks[] = array(
                'title' => __('Gateway Registration', 'dodo-payments-for-woocommerce'),
                'status' => 'fail',
                'message' => __('Dodo Payments gateway is not properly registered with WooCommerce. This is a critical issue.', 'dodo-payments-for-woocommerce')
            );
            $failed++;
        }

        // Check 9: Check if the current cart contains products that might affect payment gateway availability
        if (WC()->cart && !WC()->cart->is_empty()) {
            $has_virtual_products = false;
            $has_subscription_products = false;
            $has_physical_products = false;
            
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product = $cart_item['data'];
                
                if ($product->is_virtual()) {
                    $has_virtual_products = true;
                } else {
                    $has_physical_products = true;
                }
                
                // Check for subscription products if WooCommerce Subscriptions is active
                if (class_exists('WC_Subscriptions') && $product->is_type(array('subscription', 'subscription_variation', 'variable-subscription'))) {
                    $has_subscription_products = true;
                }
            }
            
            $cart_info = array();
            if ($has_virtual_products) $cart_info[] = __('virtual products', 'dodo-payments-for-woocommerce');
            if ($has_physical_products) $cart_info[] = __('physical products', 'dodo-payments-for-woocommerce');
            if ($has_subscription_products) $cart_info[] = __('subscription products', 'dodo-payments-for-woocommerce');
            
            $cart_info_str = implode(', ', $cart_info);
            
            $checks[] = array(
                'title' => __('Cart Contents', 'dodo-payments-for-woocommerce'),
                'status' => 'info',
                'message' => sprintf(__('Your cart contains: %s', 'dodo-payments-for-woocommerce'), $cart_info_str)
            );
            
            if ($has_virtual_products && !$has_physical_products) {
                $checks[] = array(
                    'title' => __('Virtual Products', 'dodo-payments-for-woocommerce'),
                    'status' => 'info',
                    'message' => __('Your cart contains only virtual products. Some payment gateways behave differently with virtual-only orders.', 'dodo-payments-for-woocommerce')
                );
            }
            
            if ($has_subscription_products) {
                $checks[] = array(
                    'title' => __('Subscription Products', 'dodo-payments-for-woocommerce'),
                    'status' => 'warn',
                    'message' => __('Your cart contains subscription products. Dodo Payments needs to be explicitly configured to support subscriptions.', 'dodo-payments-for-woocommerce')
                );
                $warnings++;
            }
        } else {
            $checks[] = array(
                'title' => __('Cart Contents', 'dodo-payments-for-woocommerce'),
                'status' => 'warn',
                'message' => __('Your cart is empty. Add products to your cart to test gateway availability at checkout.', 'dodo-payments-for-woocommerce')
            );
            $warnings++;
        }
        
        // Generate summary
        $summary = '';
        if ($failed > 0) {
            $summary = sprintf(
                _n(
                    '%d critical issue found that will prevent the gateway from working properly.', 
                    '%d critical issues found that will prevent the gateway from working properly.', 
                    $failed, 
                    'dodo-payments-for-woocommerce'
                ),
                $failed
            );
        } else if ($warnings > 0) {
            $summary = sprintf(
                _n(
                    '%d warning found. The gateway should work but there might be issues.', 
                    '%d warnings found. The gateway should work but there might be issues.', 
                    $warnings, 
                    'dodo-payments-for-woocommerce'
                ),
                $warnings
            );
        } else {
            $summary = __('All checks passed! The gateway should be working correctly.', 'dodo-payments-for-woocommerce');
        }

        // Add troubleshooting tips
        $troubleshooting_tips = array(
            __('1. Make sure the gateway is enabled in WooCommerce → Settings → Payments.', 'dodo-payments-for-woocommerce'),
            __('2. Verify you have entered valid API keys for the selected mode (Test/Live).', 'dodo-payments-for-woocommerce'),
            __('3. Check if your store currency is supported by Dodo Payments.', 'dodo-payments-for-woocommerce'),
            __('4. Try adding a simple physical product to your cart to test checkout.', 'dodo-payments-for-woocommerce'),
            __('5. Disable other payment gateways temporarily to see if there are conflicts.', 'dodo-payments-for-woocommerce'),
            __('6. Clear your browser cache and cookies, then try again.', 'dodo-payments-for-woocommerce'),
            __('7. Temporarily deactivate all other plugins to check for conflicts.', 'dodo-payments-for-woocommerce'),
        );
        
        wp_send_json_success(array(
            'checks' => $checks,
            'summary' => $summary,
            'failed' => $failed,
            'warnings' => $warnings,
            'troubleshooting_tips' => $troubleshooting_tips
        ));
    } catch (Exception $e) {
        // Log the error for debugging
        error_log('Dodo Payments Diagnostics Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        
        wp_send_json_error(array(
            'message' => 'Diagnostics encountered an error: ' . $e->getMessage()
        ));
    } catch (Error $e) {
        // For PHP 7+ to catch fatal errors
        error_log('Dodo Payments Diagnostics Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
        
        wp_send_json_error(array(
            'message' => 'Diagnostics encountered a fatal error: ' . $e->getMessage()
        ));
    }
}

// Add a hook to debug why the gateway isn't showing at checkout
add_filter('woocommerce_available_payment_gateways', 'dodo_debug_available_payment_gateways', 999);
function dodo_debug_available_payment_gateways($available_gateways) {
    // Only log when WP_DEBUG is enabled
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $all_gateways = WC()->payment_gateways->payment_gateways();
        $dodo_gateway = isset($all_gateways['dodo_payments']) ? $all_gateways['dodo_payments'] : null;
        
        if (!$dodo_gateway) {
            error_log('Dodo Payments Debug: Gateway not registered properly');
            return $available_gateways;
        }
        
        if (!isset($available_gateways['dodo_payments'])) {
            $is_enabled = $dodo_gateway->enabled === 'yes';
            $api_key = $dodo_gateway->testmode ? $dodo_gateway->get_option('test_api_key') : $dodo_gateway->get_option('live_api_key');
            $has_api_key = !empty($api_key);
            
            error_log(sprintf(
                'Dodo Payments Debug: Gateway not available at checkout. Enabled: %s, Has API Key: %s',
                $is_enabled ? 'Yes' : 'No',
                $has_api_key ? 'Yes' : 'No'
            ));
            
            // Log additional information that might be helpful
            if (WC()->cart) {
                $cart_total = WC()->cart->get_total('');
                $is_cart_empty = WC()->cart->is_empty();
                $has_virtual_only = WC()->cart->needs_shipping() === false && !$is_cart_empty;
                
                error_log(sprintf(
                    'Dodo Payments Debug: Cart info - Total: %s, Empty: %s, Virtual Only: %s',
                    $cart_total,
                    $is_cart_empty ? 'Yes' : 'No',
                    $has_virtual_only ? 'Yes' : 'No'
                ));
            }
        }
    }
    
    return $available_gateways;
}
