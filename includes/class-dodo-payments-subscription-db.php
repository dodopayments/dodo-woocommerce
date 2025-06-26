<?php

/**
 * Database operations for Dodo Payments Subscription mappings
 *
 * @since 0.3.0
 */
class Dodo_Payments_Subscription_DB
{
  private static string $table_name = 'dodo_payments_subscription_mappings';

  /**
   * Creates the subscription mappings table
   *
   * @return void
   * @since 0.3.0
   */
  public static function create_table()
  {
    global $wpdb;

    $table_name = $wpdb->prefix . self::$table_name;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            wc_subscription_id bigint(20) NOT NULL,
            dodo_subscription_id varchar(255) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY wc_subscription_id (wc_subscription_id),
            UNIQUE KEY dodo_subscription_id (dodo_subscription_id)
        ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  }

  /**
   * Saves the mapping between WooCommerce subscription and Dodo Payments subscription
   *
   * @param int $wc_subscription_id WooCommerce subscription ID
   * @param string $dodo_subscription_id Dodo Payments subscription ID
   * @return void
   * @since 0.3.0
   */
  public static function save_mapping($wc_subscription_id, $dodo_subscription_id)
  {
    global $wpdb;

    $table_name = $wpdb->prefix . self::$table_name;

    $wpdb->replace(
      $table_name,
      array(
        'wc_subscription_id' => $wc_subscription_id,
        'dodo_subscription_id' => $dodo_subscription_id,
      ),
      array(
        '%d',
        '%s',
      )
    );
  }

  /**
   * Gets the Dodo Payments subscription ID for a WooCommerce subscription
   *
   * @param int $wc_subscription_id WooCommerce subscription ID
   * @return string|null Dodo Payments subscription ID or null if not found
   * @since 0.3.0
   */
  public static function get_dodo_subscription_id($wc_subscription_id)
  {
    global $wpdb;

    $table_name = $wpdb->prefix . self::$table_name;

    $result = $wpdb->get_var($wpdb->prepare(
      "SELECT dodo_subscription_id FROM $table_name WHERE wc_subscription_id = %d",
      $wc_subscription_id
    ));

    return $result;
  }

  /**
   * Gets the WooCommerce subscription ID for a Dodo Payments subscription
   *
   * @param string $dodo_subscription_id Dodo Payments subscription ID
   * @return int|null WooCommerce subscription ID or null if not found
   * @since 0.3.0
   */
  public static function get_wc_subscription_id($dodo_subscription_id)
  {
    global $wpdb;

    $table_name = $wpdb->prefix . self::$table_name;

    $result = $wpdb->get_var($wpdb->prepare(
      "SELECT wc_subscription_id FROM $table_name WHERE dodo_subscription_id = %s",
      $dodo_subscription_id
    ));

    return $result ? (int) $result : null;
  }

  /**
   * Deletes the mapping for a WooCommerce subscription
   *
   * @param int $wc_subscription_id WooCommerce subscription ID
   * @return void
   * @since 0.3.0
   */
  public static function delete_mapping($wc_subscription_id)
  {
    global $wpdb;

    $table_name = $wpdb->prefix . self::$table_name;

    $wpdb->delete(
      $table_name,
      array('wc_subscription_id' => $wc_subscription_id),
      array('%d')
    );
  }
}