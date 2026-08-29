<?php

/**
 * Plugin Name: HDWebmobile Simple Subscriptions
 * Plugin URI: https://hdwebmobile.com/plugins/hdwebmobile-simple-subscriptions/
 * Description: Sell recurring/subscription products in WooCommerce. Subscription state is always plain scalar database columns -- this plugin never calls PHP's serialize()/unserialize() on any data, closing the exact object-injection vulnerability class found in a competing subscriptions plugin.
 * Version: 1.0.0
 * Author: htrxuan - Han Tran
 * Author URI: https://hdwebmobile.com/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: hdwebmobile-simple-subscriptions
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.9
 */

namespace htrxuan\hdsub;

if (!defined('ABSPATH')) {
    exit;
}

define('HDSUB_VERSION', '1.0.0');
define('HDSUB_DB_VERSION', '1.0.0');
define('HDSUB_PLUGIN_FILE', __FILE__);
define('HDSUB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HDSUB_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once HDSUB_PLUGIN_DIR . 'includes/class-hdsub-activator.php';

register_activation_hook(__FILE__, array(HDSUB_Activator::class, 'activate'));

add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', HDSUB_PLUGIN_FILE, true);
    }
});

add_action('plugins_loaded', function () {
    require_once HDSUB_PLUGIN_DIR . 'includes/class-hdsub-core.php';
    HDSUB_Core::get_instance();
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $donate_link = '<a href="https://paypal.me/htrxuan/20" target="_blank" rel="noopener noreferrer">' . esc_html__('Donate', 'hdwebmobile-simple-subscriptions') . '</a>';
    array_unshift($links, $donate_link);
    return $links;
});
