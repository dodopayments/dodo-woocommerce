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

if (! defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

if (! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'),))) {
  return;
}

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

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

        add_action('woocommerce_thank_you' . $this->id, array($this, 'thank_you_page'));
      }

      public function init_form_fields()
      {
        $this->form_fields =  array(
          'enabled' => array(
            'title' => __('Enable/Disable', 'dodo-payments'),
            'type' => 'checkbox',
            'label' => __('Enable Dodo Payments', 'dodo-payments'),
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
        );
      }

      public function process_payments($order_id)
      {
        $order = wc_get_order($order_id);

        $order->update_status('pending-payment', __('Awaiting Dodo Payments payment', 'dodo-payments'));

        $order->reduce_order_stock();

        $this->clear_payment();
        // TODO: make the actual payment

        WC()->cart->empty_cart();

        return array(
          'result' => 'success',
          'redirect' => $this->get_return_url($order)
        );
      }

      public function thank_you_page() {}

      private function clear_payment() {}
    }
  }
}

add_filter('woocommerce_payment_gateways',  'add_to_woo_dodo_payments_gateway_class');
function add_to_woo_dodo_payments_gateway_class($gateways)
{
  $gateways[] = 'WC_Dodo_Payments_Gateway';
  return $gateways;
}
