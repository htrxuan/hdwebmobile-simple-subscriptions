<?php

namespace htrxuan\hdsub;

if (!defined('ABSPATH')) {
    exit;
}

class HDSUB_Activator
{

    public static function activate()
    {
        if (!self::is_woocommerce_active()) {
            deactivate_plugins(plugin_basename(HDSUB_PLUGIN_FILE));
            set_transient('hdsub_wc_missing_notice', true, 30);
            return;
        }

        self::maybe_upgrade_db();
    }

    public static function is_woocommerce_active()
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('woocommerce/woocommerce.php') || class_exists('WooCommerce');
    }

    public static function maybe_upgrade_db()
    {
        if (get_option('hdsub_db_version') === HDSUB_DB_VERSION) {
            return;
        }

        require_once HDSUB_PLUGIN_DIR . 'includes/class-hdsub-repository.php';
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta(HDSUB_Repository::get_schema_sql());

        update_option('hdsub_db_version', HDSUB_DB_VERSION);
    }
}
