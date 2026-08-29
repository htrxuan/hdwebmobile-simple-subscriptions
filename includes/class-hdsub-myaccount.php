<?php

namespace htrxuan\hdsub;

if (!defined('ABSPATH')) {
    exit;
}

final class HDSUB_MyAccount
{

    const ENDPOINT     = 'subscriptions';
    const NONCE_ACTION = 'hdsub_cancel';

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
        add_action('init', array($this, 'add_endpoint'));
        add_filter('query_vars', array($this, 'add_query_var'));
        add_filter('woocommerce_account_menu_items', array($this, 'add_menu_item'));
        add_action('woocommerce_account_' . self::ENDPOINT . '_endpoint', array($this, 'render_endpoint_content'));
        add_action('template_redirect', array($this, 'handle_cancel'));
    }

    public function add_endpoint()
    {
        add_rewrite_endpoint(self::ENDPOINT, EP_ROOT | EP_PAGES);
    }

    public function add_query_var($vars)
    {
        $vars[] = self::ENDPOINT;
        return $vars;
    }

    public function add_menu_item($items)
    {
        $new_items = array();
        foreach ($items as $key => $label) {
            $new_items[$key] = $label;
            if ('orders' === $key) {
                $new_items[self::ENDPOINT] = __('Subscriptions', 'hdwebmobile-simple-subscriptions');
            }
        }
        if (!isset($new_items[self::ENDPOINT])) {
            $new_items[self::ENDPOINT] = __('Subscriptions', 'hdwebmobile-simple-subscriptions');
        }
        return $new_items;
    }

    public function handle_cancel()
    {
        if (!is_account_page() || !isset($_GET['hdsub_cancel'])) {
            return;
        }

        if (!is_user_logged_in()) {
            return;
        }

        $subscription_id = absint($_GET['hdsub_cancel']);
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION . '_' . $subscription_id)) {
            return;
        }

        $subscription = HDSUB_Repository::get($subscription_id);
        if (!$subscription || (int) $subscription->user_id !== get_current_user_id()) {
            return; // Never let a customer cancel a subscription that isn't theirs.
        }

        HDSUB_Repository::set_status($subscription_id, HDSUB_Repository::STATUS_CANCELLED);

        wp_safe_redirect(wc_get_account_endpoint_url(self::ENDPOINT));
        exit;
    }

    public function render_endpoint_content()
    {
        $subscriptions = HDSUB_Repository::get_for_user(get_current_user_id());

        echo '<h2>' . esc_html__('Subscriptions', 'hdwebmobile-simple-subscriptions') . '</h2>';

        if (empty($subscriptions)) {
            echo '<p>' . esc_html__('You have no subscriptions yet.', 'hdwebmobile-simple-subscriptions') . '</p>';
            return;
        }

        echo '<table class="woocommerce-table shop_table hdsub-subscriptions-table">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__('Product', 'hdwebmobile-simple-subscriptions') . '</th>';
        echo '<th>' . esc_html__('Next renewal', 'hdwebmobile-simple-subscriptions') . '</th>';
        echo '<th>' . esc_html__('Status', 'hdwebmobile-simple-subscriptions') . '</th>';
        echo '<th>' . esc_html__('Action', 'hdwebmobile-simple-subscriptions') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($subscriptions as $subscription) {
            $product = wc_get_product($subscription->product_id);
            $cancel_url = wp_nonce_url(
                add_query_arg('hdsub_cancel', $subscription->id, wc_get_account_endpoint_url(self::ENDPOINT)),
                self::NONCE_ACTION . '_' . $subscription->id
            );

            echo '<tr>';
            printf('<td>%s</td>', $product ? esc_html($product->get_name()) : esc_html__('(deleted product)', 'hdwebmobile-simple-subscriptions'));
            printf('<td>%s</td>', esc_html(date_i18n(get_option('date_format'), strtotime($subscription->next_renewal_date))));
            printf('<td>%s</td>', esc_html(ucfirst($subscription->status)));
            if (HDSUB_Repository::STATUS_ACTIVE === $subscription->status) {
                printf('<td><a class="button" href="%s">%s</a></td>', esc_url($cancel_url), esc_html__('Cancel', 'hdwebmobile-simple-subscriptions'));
            } else {
                echo '<td>-</td>';
            }
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
