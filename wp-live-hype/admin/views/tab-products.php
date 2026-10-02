<?php
/**
 * Products & page targeting tab.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

Admin::form_start( 'products' );

Admin::card_start( __( 'Where notifications appear', 'wp-live-hype' ) );
Admin::radios(
	'display_on',
	__( 'Show notifications on', 'wp-live-hype' ),
	array(
		'everywhere' => array( __( 'Entire website', 'wp-live-hype' ), __( 'Every front-end page, minus the exclusions below.', 'wp-live-hype' ) ),
		'selected'   => array( __( 'Selected page types only', 'wp-live-hype' ), '' ),
	)
);
echo '<div data-show-when-value="display_on=selected">';
Admin::checkboxes(
	'pages',
	__( 'Page types', 'wp-live-hype' ),
	array(
		'home'     => __( 'Homepage', 'wp-live-hype' ),
		'shop'     => __( 'Shop page', 'wp-live-hype' ),
		'product'  => __( 'Product pages', 'wp-live-hype' ),
		'category' => __( 'Product category pages', 'wp-live-hype' ),
		'tag'      => __( 'Product tag pages', 'wp-live-hype' ),
		'cart'     => __( 'Cart', 'wp-live-hype' ),
		'checkout' => __( 'Checkout', 'wp-live-hype' ),
		'page'     => __( 'Other pages', 'wp-live-hype' ),
		'post'     => __( 'Blog posts & archives', 'wp-live-hype' ),
		'other'    => __( 'Everything else (search, custom templates…)', 'wp-live-hype' ),
	)
);
echo '</div>';
Admin::card_end();

Admin::card_start( __( 'Exclusions', 'wp-live-hype' ), __( 'Exclusions always win over the rules above.', 'wp-live-hype' ) );
Admin::toggle( 'exclude_checkout', __( 'Never on checkout', 'wp-live-hype' ), __( 'Recommended: keeps the payment step free of distractions.', 'wp-live-hype' ) );
Admin::toggle( 'exclude_cart', __( 'Never on the cart', 'wp-live-hype' ) );
Admin::toggle( 'exclude_account', __( 'Never on account pages', 'wp-live-hype' ) );
Admin::toggle( 'exclude_login', __( 'Never on login, registration or password-reset screens', 'wp-live-hype' ) );
Admin::products( 'exclude_on_products', __( 'Do not show on these product pages', 'wp-live-hype' ) );
Admin::categories( 'exclude_on_categories', __( 'Do not show on these categories', 'wp-live-hype' ), __( 'Applies to the category archive and to product pages in that category (including sub-categories).', 'wp-live-hype' ) );
Admin::textarea(
	'exclude_urls',
	__( 'Do not show on these URLs', 'wp-live-hype' ),
	__( 'One path per line. Use * as a wildcard, e.g. /landing/* or /promo?ref=*', 'wp-live-hype' ),
	(string) $settings['exclude_urls'],
	4,
	"/landing-page/\n/blog/*"
);
Admin::card_end();

Admin::card_start( __( 'Which products may be featured', 'wp-live-hype' ), __( 'Private, draft, password-protected and catalog-hidden products are never featured.', 'wp-live-hype' ) );
Admin::products( 'hide_products', __( 'Never feature these products', 'wp-live-hype' ) );
Admin::categories( 'hide_categories', __( 'Never feature products from these categories', 'wp-live-hype' ) );
Admin::categories( 'only_categories', __( 'Only feature products from these categories', 'wp-live-hype' ), __( 'Leave empty to allow all categories.', 'wp-live-hype' ) );
Admin::toggle( 'skip_out_of_stock', __( 'Skip out-of-stock products', 'wp-live-hype' ) );
Admin::card_end();

Admin::form_end();
