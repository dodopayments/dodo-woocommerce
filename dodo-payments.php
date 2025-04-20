<?php

// TODO: Update the versions for 'tested up to' and 'WC requires at least'
/**
 * Plugin Name: Dodo Payments for WooCommerce
 * Plugin URI: https://dodopayments.com
 * Description: Dodo Payments plugin for WooCommerce. Accept payments from your customers using Dodo Payments.
 * Version: 0.1.0
 * Author: Dodo Payments
 * Developer: Dodo Payments
 * Text Domain: dodo-payments
 * Domain Path: /languages
 * 
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html 
 * 
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Tested up to: 6.1
 * WC requires at least: 7.9
 * WC tested up to: 9.6 
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'), ))) {
  return;
}

// Include database classes
require_once plugin_dir_path(__FILE__) . 'includes/class-dodo-payments-db.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-dodo-payments-payment-db.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-standard-webhook.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-dodo-payments-api.php';

// Create database tables on plugin activation
register_activation_hook(__FILE__, function () {
  Dodo_Payments_DB::create_table();
  Dodo_Payments_Payment_DB::create_table();
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
    class WC_Dodo_Payments_Gateway extends WC_Payment_Gateway
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
        $this->icon = 'https://framerusercontent.com/images/PRLIEke3MNmMB0UurlKMzNTi8qk.png';
        $this->has_fields = false;

        $this->method_title = __('Dodo Payments', 'dodo-payments');
        $this->method_description = __('Accept payments via Dodo Payments.', 'dodo-payments');

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

        // webhook to http://localhost:8080/wc-api/dodo_payments
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
          __('Webhook endpoint for Dodo Payments. Use the below URL when generating a webhook signing key on Dodo Payments Dashboard.', 'dodo-payments')
          . '</p><p><code>' . home_url("/wc-api/{$this->id}/") . '</code></p>';
        ;

        $this->form_fields = array(
          'enabled' => array(
            'title' => __('Enable/Disable', 'dodo-payments'),
            'type' => 'checkbox',
            'label' => __('Enable Dodo Payments', 'dodo-payments'),
            'default' => 'no'
          ),
          'title' => array(
            'title' => __('Title', 'dodo-payments'),
            'type' => 'text',
            'default' => __('Dodo Payments', 'dodo-payments'),
            'desc_tip' => false,
            'description' => __('Title for our payment method that the user will see on the checkout page.', 'dodo-payments'),
          ),
          'description' => array(
            'title' => __('Description', 'dodo-payments'),
            'type' => 'textarea',
            'default' => __('Pay via Dodo Payments', 'dodo-payments'),
            'desc_tip' => false,
            'description' => __('Description for our payment method that the user will see on the checkout page.', 'dodo-payments'),
          ),
          'instructions' => array(
            'title' => __('Instructions', 'dodo-payments'),
            'type' => 'textarea',
            'default' => __('', 'dodo-payments'),
            'desc_tip' => false,
            'description' => __('Instructions that will be added to the thank you page and emails.', 'dodo-payments'),
          ),
          'testmode' => array(
            'title' => __('Test Mode', 'dodo-payments'),
            'type' => 'checkbox',
            'label' => __('Enable Test Mode, <b>No actual payments will be made, always remember to disable this when you are ready to go live</b>', 'dodo-payments'),
            'default' => 'no'
          ),
          'live_api_key' => array(
            'title' => __('Live API Key', 'dodo-payments'),
            'type' => 'text',
            'default' => '',
            'desc_tip' => false,
            'description' => __('Your Live API Key. Required to receive payments. Generate one from <b>Dodo Payments (Live Mode) &gt; Developer &gt; API Keys</b>', 'dodo-payments'),
          ),
          'live_webhook_key' => array(
            'title' => __('Live Webhook Signing Key', 'dodo-payments'),
            'type' => 'text',
            'default' => '',
            'desc_tip' => false,
            'description' => __('Your Live Webhook Signing Key. Required to sync status for payments, recommended for setup. Generate one from <b>Dodo Payments (Live Mode) &gt; Developer &gt; Webhooks</b>, use the URL at the bottom of this page as the webhook URL.', 'dodo-payments'),
          ),
          'test_api_key' => array(
            'title' => __('Test API Key', 'dodo-payments'),
            'type' => 'text',
            'default' => '',
            'desc_tip' => false,
            'description' => __('Your Test API Key. Optional, only required if you want to receive test payments. Generate one from <b>Dodo Payments (Test Mode) &gt; Developer &gt; API Keys</b>', 'dodo-payments'),
          ),
          'test_webhook_key' => array(
            'title' => __('Test Webhook Signing Key', 'dodo-payments'),
            'type' => 'text',
            'default' => '',
            'desc_tip' => false,
            'description' => __('Your Test Webhook Signing Key. Optional, only required if you want to receive test payments. Generate one from <b>Dodo Payments (Test Mode) &gt; Developer &gt; Webhooks</b>, use the URL at the bottom of this page as the webhook URL.', 'dodo-payments'),
          ),
          'global_tax_category' => array(
            'title' => __('Global Tax Category', 'dodo-payments'),
            'type' => 'select',
            'options' => array(
              'digital_products' => __('Digital Products', 'dodo-payments'),
              'saas' => __('SaaS', 'dodo-payments'),
              'e_book' => __('E-Book', 'dodo-payments'),
              'edtech' => __('EdTech', 'dodo-payments'),
            ),
            'default' => 'digital_products',
            'desc_tip' => false,
            'description' => __('Select the tax category for all products. You can override this on a per-product basis on Dodo Payments Dashboard.', 'dodo-payments'),
          ),
          'global_tax_inclusive' => array(
            'title' => __('All Prices are Tax Inclusive', 'dodo-payments'),
            'type' => 'checkbox',
            'default' => 'no',
            'desc_tip' => false,
            'description' => __('Select if tax is included on all product prices. You can override this on a per-product basis on Dodo Payments Dashboard.', 'dodo-payments'),
          ),
          'webhook_endpoint' => array(
            'title' => __('Webhook Endpoint', 'dodo-payments'),
            'type' => 'title',
            'description' => $webhook_help_description,
          )
        );
      }

      public function process_payment($order_id)
      {
        $order = wc_get_order($order_id);

        $order->update_status('pending-payment', __('Awaiting payment via Dodo Payments', 'dodo-payments'));

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
          $payment = $this->dodo_payments_api->create_payment($order, $synced_products, $this->get_return_url($order));
        } catch (Exception $e) {
          $order->add_order_note(__($e->getMessage(), 'dodo-payments'));

          return array('result' => 'failure');
        }

        if (isset($payment['payment_link']) && isset($payment['payment_id'])) {
          // Save the payment mapping
          Dodo_Payments_Payment_DB::save_mapping($order->get_id(), $payment['payment_id']);

          $order->add_order_note(
            sprintf(
              // translators: %1$s: Payment ID
              __('Payment created in Dodo Payments: %1$s', 'dodo-payments'),
              $payment['payment_id']
            )
          );

          return array(
            'result' => 'success',
            'redirect' => $payment['payment_link']
          );
        } else {
          $order->add_order_note(
            __('Failed to create payment in Dodo Payments: Invalid response', 'dodo-payments')
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
          $dodo_product_id = Dodo_Payments_DB::get_dodo_product_id($local_product_id);
          $dodo_product = null;

          if ($dodo_product_id) {
            $dodo_product = $this->dodo_payments_api->get_product($dodo_product_id);

            if (!!$dodo_product) {
              try {
                $this->dodo_payments_api->update_product($dodo_product['product_id'], $product);
              } catch (Exception $e) {
                $order->add_order_note(
                  sprintf(
                    __('Failed to update product in Dodo Payments: %s', 'dodo-payments'),
                    $e->getMessage(),
                  )
                );
                error_log($e->getMessage());
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
                  __('Dodo Payments Error: %s', 'dodo-payments'),
                  $e->getMessage(),
                )
              );
              error_log($e->getMessage());
              continue;
            }

            $dodo_product_id = $response_body['product_id'];
            // Save the mapping
            Dodo_Payments_DB::save_mapping($local_product_id, $dodo_product_id);

            // sync image to dodo payments
            try {
              $this->dodo_payments_api->sync_image_for_product($product, $dodo_product_id);
            } catch (Exception $e) {
              $order->add_order_note(
                sprintf(
                  __('Failed to sync image for product in Dodo Payments: %s', 'dodo-payments'),
                  $e->getMessage(),
                )
              );
              error_log($e->getMessage());
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

      private function get_base_url()
      {
        return $this->testmode ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';
      }

      public function webhook()
      {
        $headers = [
          'webhook-signature' => $_SERVER['HTTP_WEBHOOK_SIGNATURE'],
          'webhook-id' => $_SERVER['HTTP_WEBHOOK_ID'],
          'webhook-timestamp' => $_SERVER['HTTP_WEBHOOK_TIMESTAMP'],
        ];

        $body = file_get_contents('php://input');

        try {
          $webhook = new StandardWebhook($this->webhook_key);
        } catch (\Exception $e) {
          error_log('Invalid webhook key: ' . $e->getMessage());

          // Silently consume the webhook event
          status_header(200);
          return;
        }

        try {
          $payload = $webhook->verify($body, $headers);
        } catch (Exception $e) {
          error_log('Could not verify webhook event: ' . $e->getMessage());

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
            error_log('Could not find order_id for payment: ' . $payment_id);

            // Silently consume the webhook event
            status_header(200);
            return;
          }

          $order = wc_get_order($order_id);

          if (!$order) {
            error_log('Could not find order: ' . $order_id);

            // Silently consume the webhook event
            status_header(200);
            return;
          }

          $order->payment_complete($payment_id);

          switch ($type) {
            case 'payment.succeeded':
              $order->update_status('completed', __('Payment completed by Dodo Payments', 'dodo-payments'));
              break;


            case 'payment.failed':
              $order->update_status('failed', __('Payment failed by Dodo Payments', 'dodo-payments'));
              wc_increase_stock_levels($order_id);
              break;

            case 'payment.cancelled':
              $order->update_status('cancelled', __('Payment cancelled by Dodo Payments', 'dodo-payments'));
              wc_increase_stock_levels($order_id);
              break;

            case 'payment.processing':
            default:
              $order->update_status('processing', __('Payment processing by Dodo Payments', 'dodo-payments'));
              break;
          }
        }

        if (substr($type, 0, 6) === 'refund') {
          $payment_id = $payload['data']['payment_id'];
          $order_id = Dodo_Payments_Payment_DB::get_order_id($payment_id);

          if (!$order_id) {
            error_log('Could not find order for payment: ' . $payment_id);

            // Silently consume the webhook event
            status_header(200);
            return;
          }

          $order = wc_get_order($order_id);

          if (!$order) {
            error_log('Could not find order: ' . $order_id);

            // Silently consume the webhook event
            status_header(200);
            return;
          }

          switch ($type) {
            case 'refund.succeeded':
              $order->update_status('refunded', __('Payment refunded by Dodo Payments', 'dodo-payments'));

              $order->add_order_note(
                sprintf(
                  // translators: %1$s: Payment ID, %2$s: Refund ID
                  __('Refunded payment in Dodo Payments. Payment ID: %$1s, Refund ID: %2$s', 'dodo-payments'),
                  $payment_id,
                  $payload['data']['refund_id']
                )
              );
              break;

            case 'refund.failed':
              $order->add_order_note(
                sprintf(
                  // translators: %1$s: Payment ID, %2$s: Refund ID
                  __('Refund failed in Dodo Payments. Payment ID: %$1s, Refund ID: %2$s', 'dodo-payments'),
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

add_filter('woocommerce_payment_gateways', 'add_to_woo_dodo_payments_gateway_class');
function add_to_woo_dodo_payments_gateway_class($gateways)
{
  $gateways[] = 'WC_Dodo_Payments_Gateway';
  return $gateways;
}
