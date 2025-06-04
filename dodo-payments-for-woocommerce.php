<?php

/**
 * Plugin Name: Dodo Payments for WooCommerce
 * Plugin URI: https://dodopayments.com
 * Description: Dodo Payments plugin for WooCommerce. Accept payments from your customers using Dodo Payments.
 * Version: 0.2.4
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

                if ($order->get_total() == 0) {
                    $order->payment_complete();

                    WC()->cart->empty_cart();
                    return array(
                        'result' => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                }

                $res = $this->do_payment($order);
                WC()->cart->empty_cart();
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

                    $payment = $this->dodo_payments_api->create_payment(
                        $order,
                        $synced_products,
                        $dodo_discount_code,
                        $this->get_return_url($order)
                    );
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
        }
    }
}

add_filter('woocommerce_payment_gateways', 'dodo_payments_add_gateway_class_to_woo');
function dodo_payments_add_gateway_class_to_woo($gateways)
{
    $gateways[] = 'Dodo_Payments_WC_Gateway';
    return $gateways;
}
