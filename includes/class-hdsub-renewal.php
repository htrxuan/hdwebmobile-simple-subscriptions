<?php

namespace htrxuan\hdsub;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scans for subscriptions due for renewal and creates a new "pending payment" order
 * for each, emailing the customer a link to pay it. Deliberately does not attempt to
 * auto-charge a stored payment method -- this plugin never stores card/token data at
 * all, which is a far larger attack surface than a v1 subscriptions plugin needs.
 */
final class HDSUB_Renewal
{

    const CRON_HOOK = 'hdsub_process_renewals';

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
        add_action('init', array($this, 'maybe_schedule'));
        add_action(self::CRON_HOOK, array($this, 'process_due_renewals'));
    }

    public function maybe_schedule()
    {
        if (!function_exists('as_next_scheduled_action') || !function_exists('as_schedule_recurring_action')) {
            return;
        }

        if (as_next_scheduled_action(self::CRON_HOOK)) {
            return;
        }

        as_schedule_recurring_action(time(), DAY_IN_SECONDS, self::CRON_HOOK, array(), 'hdsub');
    }

    public function process_due_renewals()
    {
        $now = current_time('mysql');
        $due = HDSUB_Repository::get_due_for_renewal($now);

        foreach ($due as $subscription) {
            $this->renew_one($subscription, $now);
        }
    }

    private function renew_one($subscription, $now)
    {
        $product = wc_get_product($subscription->product_id);
        if (!$product) {
            HDSUB_Repository::set_status($subscription->id, HDSUB_Repository::STATUS_CANCELLED);
            return;
        }

        $renewal_order = wc_create_order(array('customer_id' => $subscription->user_id));
        if (is_wp_error($renewal_order)) {
            return;
        }

        $renewal_order->add_product($product, 1);

        $parent_order = wc_get_order($subscription->parent_order_id);
        if ($parent_order) {
            $renewal_order->set_address($parent_order->get_address('billing'), 'billing');
            $renewal_order->set_address($parent_order->get_address('shipping'), 'shipping');
        }

        $renewal_order->calculate_totals();
        $renewal_order->update_status('pending', __('Renewal order for an active subscription.', 'hdwebmobile-simple-subscriptions'));
        $renewal_order->save();

        $next_renewal_date = gmdate('Y-m-d H:i:s', strtotime($subscription->next_renewal_date . " +{$subscription->interval_days} days"));

        $advanced = HDSUB_Repository::try_advance_renewal_date(
            $subscription->id,
            $subscription->next_renewal_date,
            $next_renewal_date,
            $renewal_order->get_id()
        );

        if (!$advanced) {
            // Another process already advanced this subscription's due date first --
            // this renewal order would be a duplicate, so discard it rather than send
            // the customer two payment requests for the same billing cycle.
            $renewal_order->delete(true);
            return;
        }

        $this->email_renewal_notice($renewal_order);
    }

    private function email_renewal_notice($order)
    {
        $customer = $order->get_user();
        if (!$customer) {
            return;
        }

        $subject = sprintf(
            /* translators: %s: order number */
            __('Your subscription renewal is ready -- order #%s', 'hdwebmobile-simple-subscriptions'),
            $order->get_order_number()
        );

        $message = sprintf(
            /* translators: 1: customer first name, 2: pay-now link */
            __("Hi %1\$s,\n\nYour subscription has renewed. Please complete payment for your new order here:\n\n%2\$s\n\nThank you!", 'hdwebmobile-simple-subscriptions'),
            $customer->first_name ?: $customer->display_name,
            $order->get_checkout_payment_url()
        );

        wp_mail($customer->user_email, $subject, $message);
    }
}
