<?php
/**
 * Plugin Name:          ALWAYS FINAL Social Proof
 * Description:          Tasteful, real-time social-proof notifications built exclusively from genuine WooCommerce store activity — with server-side country targeting, privacy-first data handling and anonymous analytics.
 * Version:              1.0.0
 * Requires at least:    6.2
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * Author:               ALWAYS FINAL
 * Developer:            ALWAYS FINAL
 * Text Domain:          always-final-social-proof
 * Domain Path:          /languages
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least: 7.6
 * WC tested up to:      11.1
 *
 * @package AlwaysFinal\SocialProof
 */

defined( 'ABSPATH' ) || exit;

define( 'AFSP_VERSION', '1.0.0' );
define( 'AFSP_DB_VERSION', '1' );
define( 'AFSP_FILE', __FILE__ );
define( 'AFSP_PATH', plugin_dir_path( __FILE__ ) );
define( 'AFSP_URL', plugin_dir_url( __FILE__ ) );
define( 'AFSP_BASENAME', plugin_basename( __FILE__ ) );
define( 'AFSP_MIN_WC_VERSION', '7.6' );
define( 'AFSP_SLUG', 'always-final-social-proof' );

require_once AFSP_PATH . 'includes/class-settings.php';
require_once AFSP_PATH . 'includes/class-security.php';
require_once AFSP_PATH . 'includes/class-logger.php';
require_once AFSP_PATH . 'includes/class-country.php';
require_once AFSP_PATH . 'includes/class-products.php';
require_once AFSP_PATH . 'includes/class-orders.php';
require_once AFSP_PATH . 'includes/class-cache.php';
require_once AFSP_PATH . 'includes/class-templates.php';
require_once AFSP_PATH . 'includes/class-notifications.php';
require_once AFSP_PATH . 'includes/class-analytics.php';
require_once AFSP_PATH . 'includes/class-targeting.php';
require_once AFSP_PATH . 'includes/class-rest-api.php';
require_once AFSP_PATH . 'includes/class-frontend.php';
require_once AFSP_PATH . 'includes/class-branding.php';
require_once AFSP_PATH . 'includes/class-installer.php';
require_once AFSP_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'AlwaysFinal\\SocialProof\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AlwaysFinal\\SocialProof\\Installer', 'deactivate' ) );

/*
 * Declare compatibility with WooCommerce High-Performance Order Storage and the
 * Cart/Checkout blocks. All order access goes through the WooCommerce CRUD API.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', AFSP_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', AFSP_FILE, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'AlwaysFinal\\SocialProof\\Plugin', 'bootstrap' ), 20 );
