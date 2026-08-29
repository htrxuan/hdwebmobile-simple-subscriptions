<?php

namespace htrxuan\hdsub;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Creates a subscription record the first time an order containing a subscription
 * product reaches a paid status. Hooked to woocommerce_order_status_changed, which
 * is an order-object-level hook (not a template hook), so unlike some UI/checkout
 * hooks it fires identically regardless of whether the store uses the classic or
 * block-based checkout -- no separate Store API handler is needed here.
 */
final class HDSUB_Order
{

    const ITEM_META_SUBSCRIPTION_ID = '_hdsub_subscription_id';

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('woocommerce_order_status_changed', array($this, 'maybe_create_subscriptions'), 10, 3);
    }

    public function maybe_create_subscriptions($order_id, $old_status, $new_status)
    {
        if (!in_array($new_status, array('processing', 'completed'), true)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = $order->get_customer_id();
        if (!$user_id) {
            return; // Guest orders have no account to attach a recurring subscription to.
        }

        foreach ($order->get_items() as $item) {
            if ($item->get_meta(self::ITEM_META_SUBSCRIPTION_ID)) {
                continue; // Already created for this line item -- never double-create on a retried/duplicate status event.
            }

            $product_id = $item->get_product_id();
            if (!$product_id || !HDSUB_Product::is_enabled($product_id)) {
                continue;
            }

            $interval_days = HDSUB_Product::get_interval_days($product_id);
            $next_renewal  = gmdate('Y-m-d H:i:s', strtotime($order->get_date_created()->date('Y-m-d H:i:s') . " +{$interval_days} days"));

            $subscription_id = HDSUB_Repository::create($order_id, $product_id, $user_id, $interval_days, $next_renewal);

            $item->update_meta_data(self::ITEM_META_SUBSCRIPTION_ID, $subscription_id);
            $item->save();
        }
    }
}
