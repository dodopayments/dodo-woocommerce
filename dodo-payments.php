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
 * Requires at least: 6.1
 * Tested up to: 6.1
 * Requires PHP: 7.4
 * WC requires at least: 7.9
 * WC tested up to: 9.6 
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'), ))) {
  return;
}

// Include database class
require_once plugin_dir_path(__FILE__) . 'includes/class-dodo-payments-db.php';

// Create database table on plugin activation
register_activation_hook(__FILE__, 'Dodo_Payments_DB::create_table');

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
      public $instructions;

      public $testmode;
      public $api_key;
      public $webhook_key;

      public function __construct()
      {
        $this->id = 'dodo_payments';
        $this->icon = 'https://framerusercontent.com/images/PRLIEke3MNmMB0UurlKMzNTi8qk.png';
        $this->has_fields = false; // todo: change to true later

        $this->method_title = __('Dodo Payments', 'dodo-payments');
        $this->method_description = __('Accept payments via Dodo Payments.', 'dodo-payments');

        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');

        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('live_api_key');
        $this->webhook_key = $this->get_option('webhook_key');

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thank_you_page'));
      }

      public function init_form_fields()
      {
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
            'default' => __('Dodo Payments Gateway', 'dodo-payments'),
            'desc_tip' => true,
            'description' => __('Custom title for Dodo Payments Gateway that the customer will see on the checkout page.', 'dodo-payments'),
          ),
          'description' => array(
            'title' => __('Description', 'dodo-payments'),
            'type' => 'textarea',
            'default' => __('Pay via Dodo Payments', 'dodo-payments'),
            'desc_tip' => true,
            'description' => __('Custom description for Dodo Payments Gateway that the customer will see on the checkout page.', 'dodo-payments'),
          ),
          'instructions' => array(
            'title' => __('Instructions', 'dodo-payments'),
            'type' => 'textarea',
            'default' => __('Default Instructions', 'dodo-payments'),
            'desc_tip' => true,
            'description' => __('Instructions that will be added to the thank you page and emails.', 'dodo-payments'),
          ),
          'testmode' => array(
            'title' => __('Test Mode', 'dodo-payments'),
            'type' => 'checkbox',
            'label' => __('Enable Test Mode', 'dodo-payments'),
            'default' => 'yes'
          ),
          'test_api_key' => array(
            'title' => __('Test API Key', 'dodo-payments'),
            'type' => 'text',
            'default' => '',
            'desc_tip' => true,
            'description' => __('Enter your Test API Key here.', 'dodo-payments'),
          ),
          'live_api_key' => array(
            'title' => __('Live API Key', 'dodo-payments'),
            'type' => 'text',
            'default' => '',
            'desc_tip' => true,
            'description' => __('Enter your Live API Key here.', 'dodo-payments'),
          ),
          'webhook_key' => array(
            'title' => __('Webhook Key', 'dodo-payments'),
            'type' => 'text',
            'default' => '',
            'desc_tip' => true,
            'description' => __('Enter your Webhook Key here.', 'dodo-payments'),
          ),
        );
      }

      public function process_payment($order_id)
      {
        $order = wc_get_order($order_id);

        $order->update_status('pending-payment', __('Awaiting Dodo Payments payment', 'dodo-payments'));

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
        $mapped_products = $this->sync_products($order);

        $request = array(
          'billing' => array(
            'city' => $order->get_billing_city(),
            'country' => $order->get_billing_country(),
            'state' => $order->get_billing_state(),
            'street' => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
            'zipcode' => $order->get_billing_postcode(),
          ),
          'customer' => array(
            'email' => $order->get_billing_email(),
            'name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
          ),
          'product_cart' => $mapped_products,
          'payment_link' => true,
          'return_url' => $this->get_return_url($order),
        );

        $response = wp_remote_post(
          $this->get_base_url() . '/payments',
          array(
            'headers' => array(
              'Authorization' => 'Bearer ' . $this->api_key,
              'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request),
          )
        );

        if (is_wp_error($response)) {
          $order->add_order_note(
            sprintf(
              __('Failed to create payment in Dodo Payments: %s', 'dodo-payments'),
              $response->get_error_message()
            )
          );
          return array('result' => 'failure');
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['payment_link'])) {
          $order->add_order_note(
            __('Payment created in Dodo Payments: %s', 'dodo-payments'),
            $response_body['payment_link']
          );

          return array(
            'result' => 'success',
            'redirect' => $response_body['payment_link']
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
       * @return array<array|array{amount: mixed, product_id: string, quantity: mixed}>
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



          if (!$dodo_product_id) {
            $body = array(
              'name' => $product->get_name(),
              'price' => array(
                'type' => 'one_time_price',
                'currency' => get_woocommerce_currency(),
                'price' => (int) $product->get_price() * 100,
                'discount' => 0, // todo: update defaults
                'purchasing_power_parity' => false // todo: update defaults
              ),
              'tax_category' => 'digital_products' // todo: update default
            );

            // Create product in Dodo Payments
            $response = wp_remote_post(
              $this->get_base_url() . '/products',
              array(
                'headers' => array(
                  'Authorization' => 'Bearer ' . $this->api_key,
                  'Content-Type' => 'application/json',
                ),
                'body' => json_encode($body),
              )
            );

            if (is_wp_error($response)) {
              $order->add_order_note(
                sprintf(
                  __('Failed to create product in Dodo Payments: %s', 'dodo-payments'),
                  $response->get_error_message()
                )
              );
              continue;
            }

            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($response_body['product_id'])) {
              $dodo_product_id = $response_body['product_id'];
              // Save the mapping
              Dodo_Payments_DB::save_mapping($local_product_id, $dodo_product_id);
            } else {
              $order->add_order_note(
                sprintf(
                  __('Failed to create product in Dodo Payments: Invalid response %s', 'dodo-payments'),
                  json_encode($response_body)
                )
              );
              continue;
            }
          }

          $mapped_products[] = array(
            'product_id' => $dodo_product_id,
            'quantity' => $item->get_quantity(),
            'amount' => (int) $product->get_price() * 100
          );
        }

        // TODO: Use the mapped_products array to create the payment in Dodo Payments
        return $mapped_products;
      }

      private function get_base_url()
      {
        return $this->testmode ? 'https://test.dodopayments.com' : 'https://live.dodopayments.com';
      }

      public function webhook_handler()
      {

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
