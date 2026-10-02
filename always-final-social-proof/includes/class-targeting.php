<?php
/**
 * Page targeting: where notifications may appear.
 *
 * Decided on the server before any asset is enqueued, so pages where the
 * plugin is inactive carry no extra CSS or JavaScript.
 *
 * @package AlwaysFinal\SocialProof
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

/**
 * Targeting rules.
 */
final class Targeting {

	/**
	 * Memo of the current context.
	 *
	 * @var array|null
	 */
	private static $context = null;

	/**
	 * Current page context.
	 *
	 * @return array{type:string,types:string[],product_id:int,term_id:int}
	 */
	public static function context(): array {
		if ( null !== self::$context ) {
			return self::$context;
		}

		$types      = array();
		$product_id = 0;
		$term_id    = 0;

		if ( function_exists( 'is_product' ) && is_product() ) {
			$types[]    = 'product';
			$product_id = (int) get_queried_object_id();
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$types[] = 'category';
			$term_id = (int) get_queried_object_id();
		} elseif ( function_exists( 'is_product_tag' ) && is_product_tag() ) {
			$types[] = 'tag';
		} elseif ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() ) {
			$types[] = 'category';
		}

		if ( function_exists( 'is_shop' ) && is_shop() ) {
			$types[] = 'shop';
		}
		if ( function_exists( 'is_cart' ) && is_cart() ) {
			$types[] = 'cart';
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			$types[] = 'checkout';
		}
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			$types[] = 'account';
		}
		if ( is_front_page() ) {
			$types[] = 'home';
		}
		if ( empty( $types ) ) {
			if ( is_home() || is_singular( 'post' ) || is_category() || is_tag() || is_author() || is_date() ) {
				$types[] = 'post';
			} elseif ( is_page() ) {
				$types[] = 'page';
			} else {
				$types[] = 'other';
			}
		} elseif ( in_array( 'account', $types, true ) || in_array( 'cart', $types, true ) || in_array( 'checkout', $types, true ) ) {
			// WooCommerce endpoints are regular WordPress pages too.
			$types[] = 'page';
		}

		self::$context = array(
			'type'       => $types[0],
			'types'      => array_values( array_unique( $types ) ),
			'product_id' => $product_id,
			'term_id'    => $term_id,
		);
		return self::$context;
	}

	/**
	 * Whether notifications should load on the current frontend request.
	 *
	 * @return bool
	 */
	public static function should_display(): bool {
		$display = self::evaluate();
		/**
		 * Filter whether social-proof notifications load on this page.
		 *
		 * @param bool  $display Whether to display.
		 * @param array $context Page context.
		 */
		return (bool) apply_filters( 'afsp_should_display', $display, self::context() );
	}

	/**
	 * Evaluate the configured rules.
	 *
	 * @return bool
	 */
	private static function evaluate(): bool {
		if ( ! Settings::get( 'enabled' ) ) {
			return false;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_embed() || is_robots() || is_trackback() || is_404() ) {
			return false;
		}
		if ( Settings::get( 'admin_only' ) && ! Security::can_manage() ) {
			return false;
		}
		// The store is not open to visitors yet ("Coming soon" mode): product links would be dead ends.
		if ( self::store_coming_soon() && ! Security::can_manage() ) {
			return false;
		}

		$context = self::context();
		$types   = $context['types'];

		// Exclusions always win.
		if ( Settings::get( 'exclude_checkout' ) && in_array( 'checkout', $types, true ) ) {
			return false;
		}
		if ( Settings::get( 'exclude_cart' ) && in_array( 'cart', $types, true ) ) {
			return false;
		}
		if ( Settings::get( 'exclude_account' ) && in_array( 'account', $types, true ) ) {
			return false;
		}
		if ( Settings::get( 'exclude_login' ) && self::is_login_screen( $types ) ) {
			return false;
		}

		if ( $context['product_id'] > 0 ) {
			if ( in_array( $context['product_id'], array_map( 'intval', (array) Settings::get( 'exclude_on_products' ) ), true ) ) {
				return false;
			}
			$excluded_cats = array_map( 'intval', (array) Settings::get( 'exclude_on_categories' ) );
			if ( $excluded_cats ) {
				$product = wc_get_product( $context['product_id'] );
				if ( $product && array_intersect( Products::category_ids( $product ), $excluded_cats ) ) {
					return false;
				}
			}
		}
		if ( $context['term_id'] > 0 ) {
			$excluded_cats = array_map( 'intval', (array) Settings::get( 'exclude_on_categories' ) );
			if ( $excluded_cats ) {
				$lineage = array_merge( array( $context['term_id'] ), array_map( 'intval', get_ancestors( $context['term_id'], 'product_cat', 'taxonomy' ) ) );
				if ( array_intersect( $lineage, $excluded_cats ) ) {
					return false;
				}
			}
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		if ( Security::uri_matches( $request_uri, (string) Settings::get( 'exclude_urls' ) ) ) {
			return false;
		}

		if ( 'selected' === Settings::get( 'display_on' ) ) {
			$allowed = (array) Settings::get( 'pages' );
			return (bool) array_intersect( $types, $allowed );
		}

		return true;
	}

	/**
	 * Whether WooCommerce "Coming soon" mode hides the store from visitors.
	 *
	 * @return bool
	 */
	public static function store_coming_soon(): bool {
		return 'yes' === get_option( 'woocommerce_coming_soon' );
	}

	/**
	 * Whether the current page is a login / registration screen.
	 *
	 * @param string[] $types Context types.
	 * @return bool
	 */
	private static function is_login_screen( array $types ): bool {
		if ( in_array( 'account', $types, true ) && ! is_user_logged_in() ) {
			return true;
		}
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'lost-password' ) ) {
			return true;
		}
		$login_path = (string) wp_parse_url( wp_login_url(), PHP_URL_PATH );
		$request    = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '';
		return '' !== $login_path && '' !== $request && untrailingslashit( $login_path ) === untrailingslashit( $request );
	}
}
