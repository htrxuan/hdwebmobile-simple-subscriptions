<?php

namespace htrxuan\hdsub;

if (!defined('ABSPATH')) {
    exit;
}

final class HDSUB_Core
{

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
        $this->includes();
        $this->init_hooks();
    }

    private function __clone()
    {
    }

    private function includes()
    {
        require_once HDSUB_PLUGIN_DIR . 'includes/class-hdsub-repository.php';
        require_once HDSUB_PLUGIN_DIR . 'includes/class-hdsub-product.php';
        require_once HDSUB_PLUGIN_DIR . 'includes/class-hdsub-order.php';
        require_once HDSUB_PLUGIN_DIR . 'includes/class-hdsub-renewal.php';
        require_once HDSUB_PLUGIN_DIR . 'includes/class-hdsub-myaccount.php';
        require_once HDSUB_PLUGIN_DIR . 'includes/class-hdsub-admin.php';
    }

    private function init_hooks()
    {
        add_action('admin_notices', array($this, 'render_missing_woocommerce_notice'));

        if (!class_exists('WooCommerce')) {
            return;
        }

        HDSUB_Product::get_instance();
        HDSUB_Order::get_instance();
        HDSUB_Renewal::get_instance();
        HDSUB_MyAccount::get_instance();

        if (is_admin()) {
            HDSUB_Admin::get_instance();
        }
    }

    public function render_missing_woocommerce_notice()
    {
        $screen = get_current_screen();
        if (!$screen || 'plugins' !== $screen->id) {
            return;
        }

        if (!get_transient('hdsub_wc_missing_notice')) {
            return;
        }
        delete_transient('hdsub_wc_missing_notice');
        ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php esc_html_e('HDWebmobile Simple Subscriptions requires WooCommerce to be installed and active. The plugin has been deactivated.', 'hdwebmobile-simple-subscriptions'); ?>
            </p>
        </div>
        <?php
    }
}
