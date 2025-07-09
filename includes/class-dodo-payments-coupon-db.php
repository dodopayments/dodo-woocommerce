<?php
/**
 * Database operations for Dodo Payments Coupon Mappings
 *
 * @package Dodo_Payments
 */

/**
 * Class Dodo_Payments_Coupon_DB
 */
class Dodo_Payments_Coupon_DB
{
  /**
   * Table name
   *
   * @var string
   */
  private static $table_name = 'dodo_payments_coupon_mapping';

  /**
   * Create the coupon mapping table
   *
   * @return void
   */
  public static function create_table()
  {
    global $wpdb;

    $table_name = $wpdb->prefix . self::$table_name;
    $charset_collate = $wpdb->get_charset_collate();

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            local_coupon_id bigint(20) NOT NULL,
            dodo_coupon_id varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY local_coupon_id (local_coupon_id),
            UNIQUE KEY dodo_coupon_id (dodo_coupon_id)
        ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
  }

  /**
   * Get Dodo coupon ID for a local coupon
   *
   * @param int $local_coupon_id WooCommerce coupon ID.
   * @return string|null Dodo coupon ID or null if not found.
   */
  public static function get_dodo_coupon_id($local_coupon_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->get_var(
      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
      $wpdb->prepare(
        "SELECT dodo_coupon_id FROM $table_name WHERE local_coupon_id = %d",
        $local_coupon_id
      )
    );
  }

  /**
   * Get local coupon ID for a Dodo coupon
   *
   * @param string $dodo_coupon_id Dodo coupon ID.
   * @return int|null Local coupon ID or null if not found.
   */
  public static function get_local_coupon_id($dodo_coupon_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->get_var(
      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
      $wpdb->prepare(
        "SELECT local_coupon_id FROM $table_name WHERE dodo_coupon_id = %s",
        $dodo_coupon_id
      )
    );
  }

  /**
   * Save coupon mapping
   *
   * @param int    $local_coupon_id WooCommerce coupon ID.
   * @param string $dodo_coupon_id Dodo coupon ID.
   * @return bool|int False on failure, number of rows affected on success.
   */
  public static function save_mapping($local_coupon_id, $dodo_coupon_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->replace(
      $table_name,
      array(
        'local_coupon_id' => $local_coupon_id,
        'dodo_coupon_id' => $dodo_coupon_id,
      ),
      array('%d', '%s')
    );
  }

  /**
   * Delete coupon mapping
   *
   * @param int $local_coupon_id WooCommerce coupon ID.
   * @return bool|int False on failure, number of rows affected on success.
   */
  public static function delete_mapping($local_coupon_id)
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_name;

    return $wpdb->delete(
      $table_name,
      array('local_coupon_id' => $local_coupon_id),
      array('%d')
    );
  }
}
