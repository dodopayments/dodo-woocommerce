<?php
/**
 * Database operations for Dodo Payments
 *
 * @package Dodo_Payments
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

/**
 * Class Dodo_Payments_DB
 */
class Dodo_Payments_DB
{
  /**
   * Table name
   *
   * @var string
   */
  private static $table_name = 'dodo_payments_product_mapping';

  /**
   * Create the product mapping table
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
            local_product_id bigint(20) NOT NULL,
            dodo_product_id varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY local_product_id (local_product_id),
            UNIQUE KEY dodo_product_id (dodo_product_id)
        ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
  }

  /**
   * Get Dodo product ID for a local product
   *
   * @param int $local_product_id WooCommerce product ID.
   * @return string|null Dodo product ID or null if not found.
   */
  public static function get_dodo_product_id($local_product_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->get_var(
      $wpdb->prepare(
        "SELECT dodo_product_id FROM $table_name WHERE local_product_id = %d",
        $local_product_id
      )
    );
  }

  /**
   * Get local product ID for a Dodo product
   *
   * @param string $dodo_product_id Dodo product ID.
   * @return int|null Local product ID or null if not found.
   */
  public static function get_local_product_id($dodo_product_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->get_var(
      $wpdb->prepare(
        "SELECT local_product_id FROM $table_name WHERE dodo_product_id = %s",
        $dodo_product_id
      )
    );
  }

  /**
   * Save product mapping
   *
   * @param int    $local_product_id WooCommerce product ID.
   * @param string $dodo_product_id Dodo product ID.
   * @return bool|int False on failure, number of rows affected on success.
   */
  public static function save_mapping($local_product_id, $dodo_product_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->replace(
      $table_name,
      array(
        'local_product_id' => $local_product_id,
        'dodo_product_id' => $dodo_product_id,
      ),
      array('%d', '%s')
    );
  }

  /**
   * Delete product mapping
   *
   * @param int $local_product_id WooCommerce product ID.
   * @return bool|int False on failure, number of rows affected on success.
   */
  public static function delete_mapping($local_product_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->delete(
      $table_name,
      array('local_product_id' => $local_product_id),
      array('%d')
    );
  }
}