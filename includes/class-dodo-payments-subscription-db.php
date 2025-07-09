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
   * Creates the database table for mapping WooCommerce subscription IDs to Dodo Payments subscription IDs.
   *
   * The table includes unique constraints on both subscription ID columns and a timestamp for record creation.
   *
   * @return void
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
   * Inserts or updates the mapping between a WooCommerce subscription ID and a Dodo Payments subscription ID.
   *
   * If a mapping for the given WooCommerce subscription ID already exists, it is updated; otherwise, a new mapping is created.
   *
   * @param int $wc_subscription_id The WooCommerce subscription ID to map.
   * @param string $dodo_subscription_id The corresponding Dodo Payments subscription ID.
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
   * Retrieves the Dodo Payments subscription ID associated with a given WooCommerce subscription ID.
   *
   * @param int $wc_subscription_id The WooCommerce subscription ID to look up.
   * @return string|null The corresponding Dodo Payments subscription ID, or null if no mapping exists.
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
   * Retrieves the WooCommerce subscription ID associated with a given Dodo Payments subscription ID.
   *
   * @param string $dodo_subscription_id The Dodo Payments subscription ID to look up.
   * @return int|null The corresponding WooCommerce subscription ID, or null if no mapping exists.
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

  /****
   * Removes the mapping entry for the specified WooCommerce subscription ID from the database.
   *
   * @param int $wc_subscription_id The WooCommerce subscription ID whose mapping should be deleted.
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