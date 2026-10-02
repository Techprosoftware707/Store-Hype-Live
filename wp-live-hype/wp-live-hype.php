<?php
/**
 * Plugin Name:          WP Live Hype
 * Description:          WP Live Hype — a polished, privacy-safe live activity layer and conversion assistant for WooCommerce: a weighted synthetic promotional rotation, real sale and aggregate activity, context-aware calls to action, genuine free-shipping progress, A/B testing and an anonymous conversion funnel. Developed by ALWAYS FINAL.
 * Version:              1.3.0
 * Requires at least:    6.2
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               ALWAYS FINAL
 * Developer:            ALWAYS FINAL
 * Text Domain:          wp-live-hype
 * Domain Path:          /languages
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 7.6
 * WC tested up to:      11.1
 *
 * @package AlwaysFinal\LiveHype
 */

defined( 'ABSPATH' ) || exit;

define( 'WPLH_VERSION', '1.3.0' );
define( 'WPLH_DB_VERSION', '2' );
define( 'WPLH_FILE', __FILE__ );
define( 'WPLH_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPLH_URL', plugin_dir_url( __FILE__ ) );
define( 'WPLH_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPLH_MIN_WC_VERSION', '7.6' );
define( 'WPLH_SLUG', 'wp-live-hype' );

require_once WPLH_PATH . 'includes/class-settings.php';
require_once WPLH_PATH . 'includes/class-security.php';
require_once WPLH_PATH . 'includes/class-logger.php';
require_once WPLH_PATH . 'includes/class-country.php';
require_once WPLH_PATH . 'includes/class-products.php';
require_once WPLH_PATH . 'includes/class-orders.php';
require_once WPLH_PATH . 'includes/class-cache.php';
require_once WPLH_PATH . 'includes/class-templates.php';
require_once WPLH_PATH . 'includes/class-rng.php';
require_once WPLH_PATH . 'includes/class-locations.php';
require_once WPLH_PATH . 'includes/class-synthetic-engine.php';
require_once WPLH_PATH . 'includes/class-notifications.php';
require_once WPLH_PATH . 'includes/class-analytics.php';
require_once WPLH_PATH . 'includes/class-conversion.php';
require_once WPLH_PATH . 'includes/class-targeting.php';
require_once WPLH_PATH . 'includes/class-rest-api.php';
require_once WPLH_PATH . 'includes/class-frontend.php';
require_once WPLH_PATH . 'includes/class-branding.php';
require_once WPLH_PATH . 'includes/class-installer.php';
require_once WPLH_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'AlwaysFinal\\LiveHype\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AlwaysFinal\\LiveHype\\Installer', 'deactivate' ) );
add_action( 'wp_initialize_site', array( 'AlwaysFinal\\LiveHype\\Installer', 'on_new_site' ), 200 );

/*
 * Declare compatibility with WooCommerce High-Performance Order Storage and the
 * Cart/Checkout blocks. All order access goes through the WooCommerce CRUD API.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPLH_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WPLH_FILE, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'AlwaysFinal\\LiveHype\\Plugin', 'bootstrap' ), 20 );
