<?php

namespace htrxuan\hdsub;

if (!defined('ABSPATH')) {
    exit;
}

final class HDSUB_Admin
{

    const NONCE_ACTION = 'hdsub_admin_action';

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
        require_once HDSUB_PLUGIN_DIR . 'includes/class-hdsub-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
        add_action('admin_post_hdsub_toggle_status', array($this, 'handle_toggle_status'));
    }

    public function register_hub_tabs($tabs)
    {
        $tabs['subscriptions'] = array(
            'label'  => __('Subscriptions', 'hdwebmobile-simple-subscriptions'),
            'order'  => 95,
            'render' => array($this, 'render_settings_page'),
        );
        return $tabs;
    }

    public function handle_toggle_status()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-simple-subscriptions'));
        }

        check_admin_referer(self::NONCE_ACTION);

        $subscription_id = isset($_POST['subscription_id']) ? absint($_POST['subscription_id']) : 0;
        $new_status       = isset($_POST['new_status']) ? sanitize_text_field(wp_unslash($_POST['new_status'])) : '';

        if ($subscription_id && in_array($new_status, array(HDSUB_Repository::STATUS_ACTIVE, HDSUB_Repository::STATUS_CANCELLED), true)) {
            HDSUB_Repository::set_status($subscription_id, $new_status);
        }

        wp_safe_redirect(admin_url('admin.php?page=hdwebmobile&tab=subscriptions&updated=1'));
        exit;
    }

    public function render_settings_page()
    {
        if (isset($_GET['updated'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, the actual status change already passed nonce verification in handle_toggle_status().
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Subscription updated.', 'hdwebmobile-simple-subscriptions') . '</p></div>';
        }

        echo '<p>' . esc_html__('Customers subscribe to a product marked "Subscription" in its product data. This page lists every subscription so you can cancel or reactivate one for customer service.', 'hdwebmobile-simple-subscriptions') . '</p>';

        $this->render_subscriptions_table();
    }

    private function render_subscriptions_table()
    {
        $subscriptions = HDSUB_Repository::get_all(100);

        if (empty($subscriptions)) {
            echo '<p>' . esc_html__('No subscriptions yet.', 'hdwebmobile-simple-subscriptions') . '</p>';
            return;
        }

        echo '<h2>' . esc_html__('All Subscriptions', 'hdwebmobile-simple-subscriptions') . '</h2>';
        echo '<table class="widefat striped" style="max-width:1000px;">';
        echo '<thead><tr><th>' . esc_html__('ID', 'hdwebmobile-simple-subscriptions') . '</th><th>' . esc_html__('Customer', 'hdwebmobile-simple-subscriptions') . '</th><th>' . esc_html__('Product', 'hdwebmobile-simple-subscriptions') . '</th><th>' . esc_html__('Next Renewal', 'hdwebmobile-simple-subscriptions') . '</th><th>' . esc_html__('Status', 'hdwebmobile-simple-subscriptions') . '</th><th>' . esc_html__('Action', 'hdwebmobile-simple-subscriptions') . '</th></tr></thead><tbody>';

        foreach ($subscriptions as $subscription) {
            $user    = get_user_by('id', $subscription->user_id);
            $product = wc_get_product($subscription->product_id);
            $toggle_to = HDSUB_Repository::STATUS_ACTIVE === $subscription->status ? HDSUB_Repository::STATUS_CANCELLED : HDSUB_Repository::STATUS_ACTIVE;
            $toggle_label = HDSUB_Repository::STATUS_ACTIVE === $subscription->status ? __('Cancel', 'hdwebmobile-simple-subscriptions') : __('Reactivate', 'hdwebmobile-simple-subscriptions');

            echo '<tr>';
            printf('<td>%d</td>', (int) $subscription->id);
            printf('<td>%s</td>', $user ? esc_html($user->user_email) : esc_html__('(deleted user)', 'hdwebmobile-simple-subscriptions'));
            printf('<td>%s</td>', $product ? esc_html($product->get_name()) : esc_html__('(deleted product)', 'hdwebmobile-simple-subscriptions'));
            printf('<td>%s</td>', esc_html($subscription->next_renewal_date));
            printf('<td>%s</td>', esc_html(ucfirst($subscription->status)));
            echo '<td>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;">';
            wp_nonce_field(self::NONCE_ACTION);
            echo '<input type="hidden" name="action" value="hdsub_toggle_status" />';
            echo '<input type="hidden" name="subscription_id" value="' . esc_attr($subscription->id) . '" />';
            echo '<input type="hidden" name="new_status" value="' . esc_attr($toggle_to) . '" />';
            echo '<button type="submit" class="button">' . esc_html($toggle_label) . '</button>';
            echo '</form>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }
}
