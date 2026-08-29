<?php

namespace htrxuan\hdsub;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Every subscription's state lives in plain scalar columns -- an integer interval,
 * a DATETIME, a short status string. Nothing here is ever stored as a PHP-serialized
 * blob, and this class never calls serialize()/unserialize() on anything. That is a
 * deliberate design choice, not an incidental one: it closes the exact vulnerability
 * class in CVE-2026-18391, where a competing subscriptions plugin unserialized
 * untrusted data and an attacker supplied a crafted payload that instantiated a
 * gadget-chain object from a bundled dependency, achieving remote code execution.
 * A renewal date advancing from 100 to 107 can never be turned into an object.
 */
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
class HDSUB_Repository
{

    const STATUS_ACTIVE   = 'active';
    const STATUS_CANCELLED = 'cancelled';

    public static function get_table_name()
    {
        global $wpdb;
        return $wpdb->prefix . 'hdsub_subscriptions';
    }

    public static function get_schema_sql()
    {
        global $wpdb;
        $table           = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        return "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            parent_order_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            interval_days INT UNSIGNED NOT NULL,
            next_renewal_date DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL,
            last_renewal_order_id BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY status_next_renewal (status, next_renewal_date)
        ) {$charset_collate};";
    }

    public static function create($parent_order_id, $product_id, $user_id, $interval_days, $next_renewal_date)
    {
        global $wpdb;
        $now = current_time('mysql');
        $wpdb->insert(
            self::get_table_name(),
            array(
                'parent_order_id'    => $parent_order_id,
                'product_id'         => $product_id,
                'user_id'            => $user_id,
                'interval_days'      => $interval_days,
                'next_renewal_date'  => $next_renewal_date,
                'status'             => self::STATUS_ACTIVE,
                'created_at'         => $now,
                'updated_at'         => $now,
            ),
            array('%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );
        return (int) $wpdb->insert_id;
    }

    public static function get($id)
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id = %d', self::get_table_name(), $id));
    }

    public static function get_for_user($user_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM %i WHERE user_id = %d ORDER BY id DESC',
            self::get_table_name(),
            $user_id
        ));
    }

    public static function get_all($limit = 100)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM %i ORDER BY id DESC LIMIT %d',
            self::get_table_name(),
            $limit
        ));
    }

    public static function get_due_for_renewal($before_datetime, $limit = 50)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM %i WHERE status = %s AND next_renewal_date <= %s ORDER BY next_renewal_date ASC LIMIT %d',
            self::get_table_name(),
            self::STATUS_ACTIVE,
            $before_datetime,
            $limit
        ));
    }

    /**
     * Compare-and-swap advance of the renewal date. Only one caller can ever win this
     * update for a given subscription's current due date, so if the renewal scan
     * somehow runs twice concurrently (or is retried), only one of them will see
     * affected rows > 0 and go on to actually create the renewal order -- the other
     * silently does nothing. This is the idempotency guarantee for renewals, the same
     * role the order-meta flags play for Loyalty Points' award/redeem/reverse.
     */
    public static function try_advance_renewal_date($id, $old_next_renewal_date, $new_next_renewal_date, $last_renewal_order_id)
    {
        global $wpdb;
        $affected = $wpdb->query($wpdb->prepare(
            'UPDATE %i SET next_renewal_date = %s, last_renewal_order_id = %d, updated_at = %s WHERE id = %d AND next_renewal_date = %s AND status = %s',
            self::get_table_name(),
            $new_next_renewal_date,
            $last_renewal_order_id,
            current_time('mysql'),
            $id,
            $old_next_renewal_date,
            self::STATUS_ACTIVE
        ));
        return false !== $affected && $affected > 0;
    }

    public static function set_status($id, $status)
    {
        global $wpdb;
        return false !== $wpdb->update(
            self::get_table_name(),
            array('status' => $status, 'updated_at' => current_time('mysql')),
            array('id' => $id),
            array('%s', '%s'),
            array('%d')
        );
    }
}
