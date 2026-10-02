<?php
/**
 * General tab.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

Admin::form_start( 'general' );

Admin::card_start( __( 'Main switches', 'always-final-social-proof' ) );
Admin::toggle( 'enabled', __( 'Enable social-proof notifications', 'always-final-social-proof' ), __( 'Master switch. When off, nothing is loaded on your storefront.', 'always-final-social-proof' ) );
Admin::toggle( 'admin_only', __( 'Test mode — show to administrators only', 'always-final-social-proof' ), __( 'Useful while configuring: notifications appear only for logged-in store managers.', 'always-final-social-proof' ) );
Admin::card_end();

Admin::card_start( __( 'Our promise to your customers', 'always-final-social-proof' ), '', 'afsp-card--note' );
?>
<ul class="afsp-bullets">
	<li><?php esc_html_e( 'Purchase notifications are generated only from real WooCommerce orders with qualifying statuses.', 'always-final-social-proof' ); ?></li>
	<li><?php esc_html_e( 'Sale notifications appear only for products with a genuine, active WooCommerce sale price.', 'always-final-social-proof' ); ?></li>
	<li><?php esc_html_e( 'Popularity claims are made only when real sales data substantiates them. No visitor counts, scarcity or invented numbers.', 'always-final-social-proof' ); ?></li>
	<li><?php esc_html_e( 'Customers stay anonymous: names, emails, phone numbers, street addresses, postcodes, order numbers and payment details never leave your server.', 'always-final-social-proof' ); ?></li>
	<li><?php esc_html_e( 'Country targeting is enforced on the server and never falls back to another country.', 'always-final-social-proof' ); ?></li>
</ul>
<?php
Admin::card_end();

Admin::form_end();
