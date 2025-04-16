<?php
/**
 * Database operations for Dodo Payments Payment Mappings
 *
 * @package Dodo_Payments
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Class Dodo_Payments_Payment_DB
 */
class Dodo_Payments_Payment_DB
{
  /**
   * Table name
   *
   * @var string
   */
  private static $table_name = 'dodo_payments_payment_mapping';

  /**
   * Create the payment mapping table
   *
   * @return void
   */
  public static function create_table()
  {
    global $wpdb;

    $table_name = $wpdb->prefix . self::$table_name;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            dodo_payment_id varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY order_id (order_id),
            UNIQUE KEY dodo_payment_id (dodo_payment_id)
        ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
  }

  /**
   * Get Dodo payment ID for an order
   *
   * @param int $order_id WooCommerce order ID.
   * @return string|null Dodo payment ID or null if not found.
   */
  public static function get_dodo_payment_id($order_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->get_var(
      $wpdb->prepare(
        "SELECT dodo_payment_id FROM $table_name WHERE order_id = %d",
        $order_id
      )
    );
  }

  /**
   * Get order ID for a Dodo payment
   *
   * @param string $dodo_payment_id Dodo payment ID.
   * @return int|null Order ID or null if not found.
   */
  public static function get_order_id($dodo_payment_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->get_var(
      $wpdb->prepare(
        "SELECT order_id FROM $table_name WHERE dodo_payment_id = %s",
        $dodo_payment_id
      )
    );
  }

  /**
   * Save payment mapping
   *
   * @param int    $order_id WooCommerce order ID.
   * @param string $dodo_payment_id Dodo payment ID.
   * @return bool|int False on failure, number of rows affected on success.
   */
  public static function save_mapping($order_id, $dodo_payment_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->replace(
      $table_name,
      array(
        'order_id' => $order_id,
        'dodo_payment_id' => $dodo_payment_id,
      ),
      array('%d', '%s')
    );
  }

  /**
   * Delete payment mapping
   *
   * @param int $order_id WooCommerce order ID.
   * @return bool|int False on failure, number of rows affected on success.
   */
  public static function delete_mapping($order_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->delete(
      $table_name,
      array('order_id' => $order_id),
      array('%d')
    );
  }
}