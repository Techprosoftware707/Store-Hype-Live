<?php
/**
 * Products & page targeting tab.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

Admin::form_start( 'products' );

Admin::card_start( __( 'Where notifications appear', 'always-final-social-proof' ) );
Admin::radios(
	'display_on',
	__( 'Show notifications on', 'always-final-social-proof' ),
	array(
		'everywhere' => array( __( 'Entire website', 'always-final-social-proof' ), __( 'Every front-end page, minus the exclusions below.', 'always-final-social-proof' ) ),
		'selected'   => array( __( 'Selected page types only', 'always-final-social-proof' ), '' ),
	)
);
echo '<div data-show-when-value="display_on=selected">';
Admin::checkboxes(
	'pages',
	__( 'Page types', 'always-final-social-proof' ),
	array(
		'home'     => __( 'Homepage', 'always-final-social-proof' ),
		'shop'     => __( 'Shop page', 'always-final-social-proof' ),
		'product'  => __( 'Product pages', 'always-final-social-proof' ),
		'category' => __( 'Product category pages', 'always-final-social-proof' ),
		'tag'      => __( 'Product tag pages', 'always-final-social-proof' ),
		'cart'     => __( 'Cart', 'always-final-social-proof' ),
		'checkout' => __( 'Checkout', 'always-final-social-proof' ),
		'page'     => __( 'Other pages', 'always-final-social-proof' ),
		'post'     => __( 'Blog posts & archives', 'always-final-social-proof' ),
		'other'    => __( 'Everything else (search, custom templates…)', 'always-final-social-proof' ),
	)
);
echo '</div>';
Admin::card_end();

Admin::card_start( __( 'Exclusions', 'always-final-social-proof' ), __( 'Exclusions always win over the rules above.', 'always-final-social-proof' ) );
Admin::toggle( 'exclude_checkout', __( 'Never on checkout', 'always-final-social-proof' ), __( 'Recommended: keeps the payment step free of distractions.', 'always-final-social-proof' ) );
Admin::toggle( 'exclude_cart', __( 'Never on the cart', 'always-final-social-proof' ) );
Admin::toggle( 'exclude_account', __( 'Never on account pages', 'always-final-social-proof' ) );
Admin::toggle( 'exclude_login', __( 'Never on login, registration or password-reset screens', 'always-final-social-proof' ) );
Admin::products( 'exclude_on_products', __( 'Do not show on these product pages', 'always-final-social-proof' ) );
Admin::categories( 'exclude_on_categories', __( 'Do not show on these categories', 'always-final-social-proof' ), __( 'Applies to the category archive and to product pages in that category (including sub-categories).', 'always-final-social-proof' ) );
Admin::textarea(
	'exclude_urls',
	__( 'Do not show on these URLs', 'always-final-social-proof' ),
	__( 'One path per line. Use * as a wildcard, e.g. /landing/* or /promo?ref=*', 'always-final-social-proof' ),
	(string) $settings['exclude_urls'],
	4,
	"/landing-page/\n/blog/*"
);
Admin::card_end();

Admin::card_start( __( 'Which products may be featured', 'always-final-social-proof' ), __( 'Private, draft, password-protected and catalog-hidden products are never featured.', 'always-final-social-proof' ) );
Admin::products( 'hide_products', __( 'Never feature these products', 'always-final-social-proof' ) );
Admin::categories( 'hide_categories', __( 'Never feature products from these categories', 'always-final-social-proof' ) );
Admin::categories( 'only_categories', __( 'Only feature products from these categories', 'always-final-social-proof' ), __( 'Leave empty to allow all categories.', 'always-final-social-proof' ) );
Admin::toggle( 'skip_out_of_stock', __( 'Skip out-of-stock products', 'always-final-social-proof' ) );
Admin::card_end();

Admin::form_end();
