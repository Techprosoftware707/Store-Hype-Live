<?php
/**
 * Product data: eligibility, public fields, real sale pricing, popularity.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Product utilities.
 */
final class Products {

	/**
	 * Memo of public product data (false = not featurable).
	 *
	 * @var array<int,array|false>
	 */
	private static $memo = array();

	/**
	 * Reset request memo (used after settings change and in tests).
	 */
	public static function flush(): void {
		self::$memo = array();
	}

	/**
	 * Whether a product may be featured in a public notification.
	 *
	 * Status is checked explicitly rather than through is_visible(), because
	 * is_visible() returns true for private products when the current user is
	 * an administrator — and the cached dataset may be built during an admin
	 * request but is served to everyone.
	 *
	 * @param \WC_Product $product Product.
	 * @return bool
	 */
	public static function is_featurable( \WC_Product $product ): bool {
		if ( $product->is_type( 'variation' ) || 0 !== (int) $product->get_parent_id() ) {
			return false;
		}
		if ( 'publish' !== $product->get_status() ) {
			return false;
		}
		if ( '' !== (string) $product->get_post_password() ) {
			return false;
		}
		if ( 'hidden' === $product->get_catalog_visibility() ) {
			return false;
		}
		if ( ! $product->is_in_stock() && ( Settings::get( 'skip_out_of_stock' ) || 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) ) {
			return false;
		}

		$id = $product->get_id();
		if ( in_array( $id, array_map( 'intval', (array) Settings::get( 'hide_products' ) ), true ) ) {
			return false;
		}

		$cats   = self::category_ids( $product );
		$hidden = array_map( 'intval', (array) Settings::get( 'hide_categories' ) );
		if ( $hidden && array_intersect( $cats, $hidden ) ) {
			return false;
		}
		$only = array_map( 'intval', (array) Settings::get( 'only_categories' ) );
		if ( $only && ! array_intersect( $cats, $only ) ) {
			return false;
		}

		/**
		 * Filter whether a product may appear in social-proof notifications.
		 *
		 * @param bool        $featurable Whether the product may be featured.
		 * @param \WC_Product $product    Product.
		 */
		return (bool) apply_filters( 'wplh_product_is_featurable', true, $product );
	}

	/**
	 * Sanitized public data for a product, or null if it must not be featured.
	 *
	 * @param int $product_id Product ID (parent ID for variable products).
	 * @return array|null
	 */
	public static function public_data( int $product_id ): ?array {
		if ( $product_id <= 0 ) {
			return null;
		}
		if ( isset( self::$memo[ $product_id ] ) ) {
			return false === self::$memo[ $product_id ] ? null : self::$memo[ $product_id ];
		}

		self::$memo[ $product_id ] = false;
		$product                   = wc_get_product( $product_id );
		if ( ! $product instanceof \WC_Product || ! self::is_featurable( $product ) ) {
			return null;
		}

		$name = Security::clean_text( (string) $product->get_name(), 120 );
		$url  = (string) $product->get_permalink();
		if ( '' === $name || '' === $url ) {
			return null;
		}

		$image = self::image( $product );

		self::$memo[ $product_id ] = array(
			'id'    => $product_id,
			'name'  => $name,
			'url'   => esc_url_raw( $url ),
			'image' => $image['url'],
			'img_w' => $image['w'],
			'img_h' => $image['h'],
			'cats'  => self::category_ids( $product ),
		);

		return self::$memo[ $product_id ];
	}

	/**
	 * Product thumbnail (no WooCommerce placeholder — the UI shows an icon).
	 *
	 * @param \WC_Product $product Product.
	 * @return array{url:string,w:int,h:int}
	 */
	public static function image( \WC_Product $product ): array {
		$out      = array(
			'url' => '',
			'w'   => 0,
			'h'   => 0,
		);
		$image_id = (int) $product->get_image_id();
		if ( $image_id <= 0 ) {
			return $out;
		}
		$src = wp_get_attachment_image_src( $image_id, 'woocommerce_gallery_thumbnail' );
		if ( ! $src ) {
			$src = wp_get_attachment_image_src( $image_id, 'thumbnail' );
		}
		if ( is_array( $src ) && ! empty( $src[0] ) ) {
			$out['url'] = esc_url_raw( (string) $src[0] );
			$out['w']   = (int) $src[1];
			$out['h']   = (int) $src[2];
		}
		return $out;
	}

	/**
	 * Category IDs of a product including ancestors.
	 *
	 * @param \WC_Product $product Product.
	 * @return int[]
	 */
	public static function category_ids( \WC_Product $product ): array {
		$ids = array_map( 'intval', (array) $product->get_category_ids() );
		foreach ( $ids as $id ) {
			$ids = array_merge( $ids, array_map( 'intval', get_ancestors( $id, 'product_cat', 'taxonomy' ) ) );
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Real sale information for a product, or null when it is not on sale.
	 *
	 * Uses WooCommerce's own sale state (including scheduled sale dates) and
	 * display prices (honouring the store's tax display setting). Discounts
	 * are rounded down so they are never overstated.
	 *
	 * @param \WC_Product $product Product.
	 * @return array|null
	 */
	public static function sale_info( \WC_Product $product ): ?array {
		if ( ! $product->is_on_sale( 'view' ) ) {
			return null;
		}

		$decimals = wc_get_price_decimals();
		$pairs    = array(); // Each: [ sale, regular, until ].

		if ( $product instanceof \WC_Product_Variable ) {
			$prices = $product->get_variation_prices( true );
			if ( empty( $prices['regular_price'] ) ) {
				return null;
			}
			$candidates = array();
			foreach ( $prices['regular_price'] as $variation_id => $regular ) {
				$sale    = $prices['sale_price'][ $variation_id ] ?? '';
				$current = $prices['price'][ $variation_id ] ?? '';
				if ( '' === $sale || '' === $regular || '' === $current ) {
					continue;
				}
				$regular = round( (float) $regular, $decimals );
				$sale    = round( (float) $sale, $decimals );
				$current = round( (float) $current, $decimals );
				// A variation is on sale only when its sale price is lower and is the active price.
				if ( $regular <= 0 || $sale < 0 || $sale >= $regular || abs( $current - $sale ) > 0.000001 ) {
					continue;
				}
				$candidates[ (int) $variation_id ] = array( $sale, $regular );
			}
			if ( empty( $candidates ) ) {
				return null;
			}
			// Sale end dates: check a bounded number of variations.
			foreach ( array_slice( $candidates, 0, 50, true ) as $variation_id => $pair ) {
				$variation = wc_get_product( $variation_id );
				$until     = 0;
				if ( $variation instanceof \WC_Product && $variation->get_date_on_sale_to() ) {
					$until = (int) $variation->get_date_on_sale_to()->getTimestamp();
				}
				$pairs[] = array( $pair[0], $pair[1], $until );
			}
		} elseif ( ! $product->is_type( 'grouped' ) ) {
			$sale_raw    = $product->get_sale_price();
			$regular_raw = $product->get_regular_price();
			$current_raw = $product->get_price();
			if ( '' === (string) $sale_raw || '' === (string) $regular_raw || '' === (string) $current_raw ) {
				return null;
			}
			$sale    = round( (float) wc_get_price_to_display( $product, array( 'price' => (float) $sale_raw ) ), $decimals );
			$regular = round( (float) wc_get_price_to_display( $product, array( 'price' => (float) $regular_raw ) ), $decimals );
			if ( abs( (float) $current_raw - (float) $sale_raw ) > 0.000001 || $regular <= 0 || $sale < 0 || $sale >= $regular ) {
				return null;
			}
			$until   = $product->get_date_on_sale_to() ? (int) $product->get_date_on_sale_to()->getTimestamp() : 0;
			$pairs[] = array( $sale, $regular, $until );
		}

		if ( empty( $pairs ) ) {
			return null;
		}

		$min_sale    = null;
		$paired_reg  = 0.0;
		$sale_values = array();
		$percents    = array();
		$until       = 0;
		foreach ( $pairs as $pair ) {
			list( $sale, $regular, $end ) = $pair;
			$sale_values[]                = $sale;
			$percents[]                   = (int) floor( ( ( $regular - $sale ) / $regular ) * 100 + 1e-9 );
			if ( null === $min_sale || $sale < $min_sale ) {
				$min_sale   = $sale;
				$paired_reg = $regular;
			}
			if ( $end > 0 && ( 0 === $until || $end < $until ) ) {
				$until = $end;
			}
		}

		return array(
			'sale'    => (float) $min_sale,
			'regular' => (float) $paired_reg,
			'range'   => count( array_unique( array_map( 'strval', $sale_values ) ) ) > 1,
			'pct_max' => max( $percents ),
			'pct_min' => min( $percents ),
			'until'   => $until,
		);
	}

	/**
	 * Token values derived from sale info.
	 *
	 * @param array $sale Sale info from sale_info().
	 * @return array{sale_price:string,regular_price:string,discount_percent:string}
	 */
	public static function sale_tokens( array $sale ): array {
		$sale_text    = self::format_price( (float) $sale['sale'] );
		$regular_text = self::format_price( (float) $sale['regular'] );
		if ( ! empty( $sale['range'] ) ) {
			/* translators: %s: lowest sale price of a variable product. */
			$sale_text = sprintf( __( 'from %s', 'wp-live-hype' ), $sale_text );
		}

		$percent = '';
		if ( (int) $sale['pct_max'] >= 1 ) {
			/* translators: %d: discount percentage. */
			$percent = sprintf( _x( '%d%%', 'discount percentage', 'wp-live-hype' ), (int) $sale['pct_max'] );
			if ( (int) $sale['pct_min'] !== (int) $sale['pct_max'] ) {
				/* translators: %s: maximum discount such as 25%. */
				$percent = sprintf( __( 'up to %s', 'wp-live-hype' ), $percent );
			}
		}

		return array(
			'sale_price'       => $sale_text,
			'regular_price'    => $regular_text,
			'discount_percent' => $percent,
		);
	}

	/**
	 * Format a price as plain text using the store's currency settings.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function format_price( float $amount ): string {
		return Security::clean_text( html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 40 );
	}

	/**
	 * Featurable products that are genuinely on sale right now.
	 *
	 * @param int $limit Maximum number of products.
	 * @return array<int,array> product ID => sale info.
	 */
	public static function sales( int $limit ): array {
		$ids = array_map( 'intval', (array) wc_get_product_ids_on_sale() );
		if ( empty( $ids ) ) {
			return array();
		}
		shuffle( $ids );
		$ids = array_slice( $ids, 0, max( $limit * 4, 40 ) );
		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $ids, false, true );
		}

		$out = array();
		foreach ( $ids as $id ) {
			if ( count( $out ) >= $limit ) {
				break;
			}
			$product = wc_get_product( $id );
			if ( ! $product instanceof \WC_Product || $product->is_type( 'variation' ) || null === self::public_data( $id ) ) {
				continue;
			}
			$info = self::sale_info( $product );
			if ( null !== $info ) {
				$out[ $id ] = $info;
			}
		}
		return $out;
	}

	/**
	 * Popular products from real order counts.
	 *
	 * @param array<int,int> $counts product ID => number of qualifying orders.
	 * @param int            $min    Minimum orders for a product to count as popular.
	 * @param int            $limit  Maximum products.
	 * @return int[] Product IDs, most popular first.
	 */
	public static function popular_from_counts( array $counts, int $min, int $limit ): array {
		$manual = array_map( 'intval', (array) Settings::get( 'popular_products' ) );
		$counts = array_filter(
			$counts,
			static function ( $count ) use ( $min ) {
				return (int) $count >= $min;
			}
		);
		if ( $manual ) {
			$counts = array_intersect_key( $counts, array_flip( $manual ) );
		}
		arsort( $counts, SORT_NUMERIC );

		$out = array();
		foreach ( array_keys( $counts ) as $id ) {
			if ( null !== self::public_data( (int) $id ) ) {
				$out[] = (int) $id;
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Lifetime best sellers from WooCommerce's own `total_sales` counter.
	 *
	 * This counter is store-wide (all countries), so callers must only use it
	 * when no geographic restriction applies.
	 *
	 * @param int $min   Minimum lifetime sales.
	 * @param int $limit Maximum products.
	 * @return int[]
	 */
	public static function lifetime_bestsellers( int $min, int $limit ): array {
		$manual = array_map( 'intval', (array) Settings::get( 'popular_products' ) );
		$args   = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => $limit * 3,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'orderby'                => 'meta_value_num',
			'meta_key'               => 'total_sales', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'order'                  => 'DESC',
			'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'     => 'total_sales',
					'value'   => $min,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
			),
		);
		if ( $manual ) {
			$args['post__in'] = $manual;
		}
		$query = new \WP_Query( $args );

		$out = array();
		foreach ( (array) $query->posts as $id ) {
			if ( null !== self::public_data( (int) $id ) ) {
				$out[] = (int) $id;
			}
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return $out;
	}
}
