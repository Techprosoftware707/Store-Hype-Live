<?php
/**
 * General tab.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

Admin::form_start( 'general' );

Admin::card_start( __( 'Main switches', 'wp-live-hype' ) );
Admin::toggle( 'enabled', __( 'Enable social-proof notifications', 'wp-live-hype' ), __( 'Master switch. When off, nothing is loaded on your storefront.', 'wp-live-hype' ) );
Admin::toggle( 'admin_only', __( 'Test mode — show to administrators only', 'wp-live-hype' ), __( 'Useful while configuring: notifications appear only for logged-in store managers.', 'wp-live-hype' ) );
Admin::card_end();

Admin::card_start( __( 'Our promise to your customers', 'wp-live-hype' ), '', 'wplh-card--note' );
?>
<ul class="wplh-bullets">
	<li><?php esc_html_e( 'Purchase notifications are generated only from real WooCommerce orders with qualifying statuses.', 'wp-live-hype' ); ?></li>
	<li><?php esc_html_e( 'Sale notifications appear only for products with a genuine, active WooCommerce sale price.', 'wp-live-hype' ); ?></li>
	<li><?php esc_html_e( 'Popularity claims are made only when real sales data substantiates them. No visitor counts, scarcity or invented numbers.', 'wp-live-hype' ); ?></li>
	<li><?php esc_html_e( 'Customers stay anonymous: names, emails, phone numbers, street addresses, postcodes, order numbers and payment details never leave your server.', 'wp-live-hype' ); ?></li>
	<li><?php esc_html_e( 'Country targeting is enforced on the server and never falls back to another country.', 'wp-live-hype' ); ?></li>
</ul>
<?php
Admin::card_end();

Admin::form_end();
