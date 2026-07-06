<?php
/**
 * Plugin Name: SKU 9015 Combo Extras for WooCommerce
 * Plugin URI: https://3dmarket.mx/
 * Description: Crea relaciones por SKU entre un producto principal y productos extra, con selector visual, admin tipo acordeón/tabla y descuentos escalonados por cantidad.
 * Version: 1.1.0
 * Author: 3D Market
 * Text Domain: sku-9015-combo-extras
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SKU_9015_VERSION', '1.1.0' );
define( 'SKU_9015_FILE', __FILE__ );
define( 'SKU_9015_PATH', plugin_dir_path( __FILE__ ) );
define( 'SKU_9015_URL', plugin_dir_url( __FILE__ ) );
define( 'SKU_9015_OPTION_MAPS', 'sku_9015_combo_maps' );
define( 'SKU_9015_LEGACY_OPTION_MAPS', 'efc_sku_combo_maps' );

require_once SKU_9015_PATH . 'includes/class-sku-9015-helper.php';
require_once SKU_9015_PATH . 'includes/class-sku-9015-admin.php';
require_once SKU_9015_PATH . 'includes/class-sku-9015-frontend.php';

final class SKU_9015_Combo_Extras {
    public static function init() {
        add_action( 'plugins_loaded', array( __CLASS__, 'boot' ) );
        register_activation_hook( SKU_9015_FILE, array( __CLASS__, 'activate' ) );
    }

    public static function boot() {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( __CLASS__, 'woocommerce_missing_notice' ) );
            return;
        }

        self::maybe_migrate_legacy_maps();

        SKU_9015_Admin::init();
        SKU_9015_Frontend::init();
    }

    public static function activate() {
        self::maybe_migrate_legacy_maps();

        if ( false === get_option( SKU_9015_OPTION_MAPS, false ) ) {
            add_option( SKU_9015_OPTION_MAPS, array() );
        }
    }

    private static function maybe_migrate_legacy_maps() {
        $current = get_option( SKU_9015_OPTION_MAPS, null );

        if ( null !== $current && false !== $current && is_array( $current ) && ! empty( $current ) ) {
            return;
        }

        $legacy = get_option( SKU_9015_LEGACY_OPTION_MAPS, null );

        if ( is_array( $legacy ) && ! empty( $legacy ) ) {
            update_option( SKU_9015_OPTION_MAPS, $legacy, false );
        } elseif ( false === get_option( SKU_9015_OPTION_MAPS, false ) ) {
            add_option( SKU_9015_OPTION_MAPS, array() );
        }
    }

    public static function woocommerce_missing_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>SKU 9015 Combo Extras</strong> necesita WooCommerce activo para funcionar.</p></div>';
    }
}

SKU_9015_Combo_Extras::init();
