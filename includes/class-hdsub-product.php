<?php

namespace htrxuan\hdsub;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Adds the "Sold as a subscription" fields to the Simple product data panel and
 * reads/writes them. Every value here is a plain scalar postmeta field (a 'yes'/'no'
 * string, a small integer, a short unit string) -- there is no array or object meta
 * for this plugin at all, so there is nothing here PHP's serialize() ever touches.
 */
class HDSUB_Product
{

    const META_ENABLED        = '_hdsub_enabled';
    const META_INTERVAL_NUMBER = '_hdsub_interval_number';
    const META_INTERVAL_UNIT   = '_hdsub_interval_unit';

    const UNIT_DAYS_MAP = array(
        'day'   => 1,
        'week'  => 7,
        'month' => 30,
        'year'  => 365,
    );

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
        add_action('woocommerce_product_options_general_product_data', array($this, 'render_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_fields'));
    }

    public static function is_enabled($product_id)
    {
        return 'yes' === get_post_meta($product_id, self::META_ENABLED, true);
    }

    public static function get_interval_days($product_id)
    {
        $number = (int) get_post_meta($product_id, self::META_INTERVAL_NUMBER, true);
        $unit   = (string) get_post_meta($product_id, self::META_INTERVAL_UNIT, true);
        $number = max(1, $number);
        $days_per_unit = self::UNIT_DAYS_MAP[$unit] ?? self::UNIT_DAYS_MAP['month'];
        return $number * $days_per_unit;
    }

    public function render_fields()
    {
        global $post;
        echo '<div class="options_group hdsub-fields">';

        woocommerce_wp_checkbox(array(
            'id'          => self::META_ENABLED,
            'label'       => __('Subscription', 'hdwebmobile-simple-subscriptions'),
            'description' => __('Sell this product as a recurring subscription.', 'hdwebmobile-simple-subscriptions'),
            'value'       => get_post_meta($post->ID, self::META_ENABLED, true) ?: 'no',
        ));

        woocommerce_wp_text_input(array(
            'id'                => self::META_INTERVAL_NUMBER,
            'label'             => __('Renews every', 'hdwebmobile-simple-subscriptions'),
            'type'              => 'number',
            'custom_attributes' => array('step' => '1', 'min' => '1'),
            'value'             => get_post_meta($post->ID, self::META_INTERVAL_NUMBER, true) ?: '1',
        ));

        $current_unit = get_post_meta($post->ID, self::META_INTERVAL_UNIT, true) ?: 'month';
        echo '<p class="form-field hdsub_interval_unit_field"><label for="' . esc_attr(self::META_INTERVAL_UNIT) . '">' . esc_html__('Interval unit', 'hdwebmobile-simple-subscriptions') . '</label>';
        echo '<select id="' . esc_attr(self::META_INTERVAL_UNIT) . '" name="' . esc_attr(self::META_INTERVAL_UNIT) . '">';
        foreach (array('day' => __('Day(s)', 'hdwebmobile-simple-subscriptions'), 'week' => __('Week(s)', 'hdwebmobile-simple-subscriptions'), 'month' => __('Month(s)', 'hdwebmobile-simple-subscriptions'), 'year' => __('Year(s)', 'hdwebmobile-simple-subscriptions')) as $unit => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr($unit), selected($current_unit, $unit, false), esc_html($label));
        }
        echo '</select></p>';

        echo '</div>';
    }

    public function save_fields($post_id)
    {
        if (!isset($_POST['woocommerce_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data')) {
            return;
        }

        $enabled = isset($_POST[self::META_ENABLED]) ? 'yes' : 'no';
        update_post_meta($post_id, self::META_ENABLED, $enabled);

        $number = isset($_POST[self::META_INTERVAL_NUMBER]) ? max(1, absint($_POST[self::META_INTERVAL_NUMBER])) : 1;
        update_post_meta($post_id, self::META_INTERVAL_NUMBER, $number);

        $unit = isset($_POST[self::META_INTERVAL_UNIT]) ? sanitize_text_field(wp_unslash($_POST[self::META_INTERVAL_UNIT])) : 'month';
        if (!array_key_exists($unit, self::UNIT_DAYS_MAP)) {
            $unit = 'month';
        }
        update_post_meta($post_id, self::META_INTERVAL_UNIT, $unit);
    }
}
