<?php
/**
 * WP Live Hype engine: builds a varied, weighted, per-visitor stream.
 *
 * Modes:
 *  - synthetic (default): a promotional rotation built from the catalogue —
 *    featured products, "explore" spotlights, shipping-region messages and
 *    genuine WooCommerce sales. These events never claim that anyone bought
 *    or viewed anything and carry no invented times, so a brand-new store can
 *    run the layer from day one without misleading shoppers.
 *  - aggregate: only activity backed by real store data (see Notifications).
 *  - hybrid: real activity is the primary stream; promotional events fill the
 *    quiet periods between real events.
 *
 * The engine never reads individual customer records. Aggregate sales
 * counts (WooCommerce's `total_sales`) may optionally influence weighting
 * internally; they are never sent to the browser.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Engine.
 */
final class Synthetic_Engine {

	const PROMO_TYPES = array( 'featured', 'explore', 'location', 'sale_promo' );

	/**
	 * Base frequency of each promotional event type.
	 */
	const TYPE_WEIGHTS = array(
		'featured'   => 3,
		'explore'    => 2,
		'location'   => 2,
		'sale_promo' => 3,
	);

	const BATCH = 20;

	/**
	 * Every event type the frontend can receive.
	 *
	 * @return string[]
	 */
	public static function types(): array {
		return array_merge( Notifications::TYPES, self::PROMO_TYPES );
	}

	/**
	 * Product pool for the promotional rotation (cached with the dataset).
	 *
	 * @return array{products:array<int,array{level:int,sales:int}>,max_sales:int}
	 */
	public static function build_pool(): array {
		$weights = array_map( 'intval', (array) Settings::get( 'product_weights' ) );
		$ids     = wc_get_products(
			array(
				'status'  => 'publish',
				'limit'   => 80,
				'orderby' => array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				),
				'return'  => 'ids',
			)
		);
		$ids     = array_unique( array_merge( array_map( 'intval', (array) $ids ), array_map( 'intval', array_keys( $weights ) ) ) );

		$use_aggregate = (bool) Settings::get( 'use_aggregate' );
		$products      = array();
		$max_sales     = 0;
		foreach ( $ids as $id ) {
			if ( null === Products::public_data( $id ) ) {
				continue;
			}
			// Aggregate store-wide counter only; never individual orders.
			$sales           = $use_aggregate ? max( 0, (int) get_post_meta( $id, 'total_sales', true ) ) : 0;
			$max_sales       = max( $max_sales, $sales );
			$products[ $id ] = array(
				'level' => isset( $weights[ $id ] ) ? max( 1, min( 4, $weights[ $id ] ) ) : 2,
				'sales' => $sales,
			);
			if ( count( $products ) >= 100 ) {
				break;
			}
		}

		return array(
			'products'  => $products,
			'max_sales' => $max_sales,
		);
	}

	/**
	 * Generate the event stream for one visitor.
	 *
	 * @param array      $context Page context { type, product_id, term_id }.
	 * @param int        $seed    Visitor seed.
	 * @param array|null $dataset Dataset (defaults to the cached one).
	 * @return array[] Public events.
	 */
	public static function generate( array $context, int $seed, ?array $dataset = null ): array {
		if ( ! Settings::get( 'enabled' ) ) {
			return array();
		}
		$dataset = null === $dataset ? Cache::get_dataset() : $dataset;
		$mode    = (string) Settings::get( 'activity_mode' );

		$real = 'synthetic' === $mode ? array() : Notifications::queue( $context, $dataset );
		if ( 'aggregate' === $mode ) {
			return $real;
		}

		$rng = new Rng(
			crc32(
				implode(
					'|',
					array(
						$seed,
						$context['type'] ?? '',
						(int) ( $context['product_id'] ?? 0 ),
						(int) ( $context['term_id'] ?? 0 ),
						(int) floor( time() / 600 ),
						(int) get_option( Cache::VERSION_OPTION, 1 ),
					)
				)
			)
		);

		// In hybrid mode real sales already come through the real queue.
		$include_sale = 'synthetic' === $mode || ! Settings::get( 'type_sale' );
		$promos       = self::promos( $context, $rng, $dataset, self::BATCH, $include_sale );

		if ( 'synthetic' === $mode ) {
			return $promos;
		}

		// Hybrid: real events lead; promotional events fill quiet periods.
		$ratio = max( 1, (int) Settings::get( 'promo_ratio' ) );
		$out   = array();
		$made  = 0;
		while ( $made < self::BATCH && ( $real || $promos ) ) {
			for ( $i = 0; $i < $ratio && $real; $i++ ) {
				$out[] = array_shift( $real );
				++$made;
			}
			if ( $promos ) {
				$out[] = array_shift( $promos );
				++$made;
			}
		}
		return array_slice( $out, 0, self::BATCH );
	}

	/**
	 * Promotional rotation.
	 *
	 * @param array $context      Page context.
	 * @param Rng   $rng          Random source.
	 * @param array $dataset      Dataset.
	 * @param int   $count        Events to produce.
	 * @param bool  $include_sale Whether sale events may be produced here.
	 * @return array[]
	 */
	public static function promos( array $context, Rng $rng, array $dataset, int $count, bool $include_sale ): array {
		$products = (array) ( $dataset['products'] ?? array() );
		$pool     = (array) ( $dataset['pool']['products'] ?? array() );
		$pool     = array_intersect_key( $pool, $products );
		if ( empty( $pool ) ) {
			return array(); // No featurable products: stay silent.
		}

		$sales = array();
		foreach ( (array) ( $dataset['sales'] ?? array() ) as $id => $sale ) {
			if ( isset( $products[ (int) $id ] ) && ( empty( $sale['until'] ) || (int) $sale['until'] > time() ) ) {
				$sales[ (int) $id ] = $sale;
			}
		}

		$country = Locations::engine_country( $rng );
		$types   = array();
		if ( Settings::get( 'promo_featured' ) ) {
			$types['featured'] = self::TYPE_WEIGHTS['featured'];
		}
		if ( Settings::get( 'promo_explore' ) ) {
			$types['explore'] = self::TYPE_WEIGHTS['explore'];
		}
		if ( Settings::get( 'promo_location' ) && Locations::store_ships_to( $country ) ) {
			$types['location'] = self::TYPE_WEIGHTS['location'];
		}
		if ( $include_sale && Settings::get( 'promo_sale' ) && $sales ) {
			$types['sale_promo'] = self::TYPE_WEIGHTS['sale_promo'];
		}
		if ( empty( $types ) ) {
			return array();
		}

		$max_sales  = max( 1, (int) ( $dataset['pool']['max_sales'] ?? 0 ) );
		$aggregate  = (bool) Settings::get( 'use_aggregate' );
		$product_id = (int) ( $context['product_id'] ?? 0 );
		$term_id    = (int) ( $context['term_id'] ?? 0 );
		$level      = (string) Settings::get( 'location_level' );
		$level      = 'none' === $level ? 'country' : $level;

		$base = array();
		foreach ( $pool as $id => $row ) {
			$weight = Locations::multiplier( (int) $row['level'] );
			if ( $aggregate ) {
				$weight *= 1 + ( (int) $row['sales'] / $max_sales );
			}
			if ( $id === $product_id ) {
				$weight *= 4; // Relevance: the product being viewed.
			} elseif ( $term_id > 0 && in_array( $term_id, (array) $products[ $id ]['cats'], true ) ) {
				$weight *= 2;
			}
			$base[ $id ] = $weight;
		}

		$out            = array();
		$used           = array();
		$recent_product = array();
		$recent_loc     = array();
		$last_type      = '';
		$attempts       = 0;
		$made           = 0;
		while ( $made < $count && $attempts++ < $count * 6 ) {
			$type_options = $types;
			if ( count( $type_options ) > 1 ) {
				unset( $type_options[ $last_type ] );
			}
			$type = (string) $rng->weighted( $type_options );

			$candidates = 'sale_promo' === $type ? array_intersect_key( $base, $sales ) : $base;
			if ( 'featured' === $type ) {
				$featured   = array_filter(
					$candidates,
					static function ( $id ) use ( $pool ) {
						return $pool[ $id ]['level'] >= 3;
					},
					ARRAY_FILTER_USE_KEY
				);
				$candidates = $featured ? $featured : $candidates;
			}
			// Anti-repetition: avoid the last few products when alternatives exist.
			$fresh = array_diff_key( $candidates, array_flip( $recent_product ) );
			$id    = (int) $rng->weighted( $fresh ? $fresh : $candidates );
			if ( ! isset( $products[ $id ] ) ) {
				continue;
			}

			$item = null;
			if ( 'location' === $type ) {
				$location = Locations::pick( $rng, $country, $level, $recent_loc );
				if ( $location ) {
					$item = self::location_item( $location, $level, $products[ $id ] );
					array_unshift( $recent_loc, $location['key'] );
					$recent_loc = array_slice( $recent_loc, 0, 4 );
				}
			} elseif ( 'sale_promo' === $type ) {
				$item         = Notifications::sale_item( $products[ $id ], $sales[ $id ] );
				$item['type'] = 'sale_promo';
			} else {
				$item = self::product_item( $type, $products[ $id ] );
			}
			if ( null === $item || '' === $item['message'] ) {
				continue;
			}

			$item['synthetic'] = true;
			$item['id']        = 'syn_' . Security::opaque_id( $type . '|' . $item['product_id'] . '|' . ( $item['location'] ?? '' ) . '|' . $item['message'] );
			if ( isset( $used[ $item['id'] ] ) ) {
				continue;
			}
			$used[ $item['id'] ] = true;
			$out[]               = $item;
			++$made;
			$last_type = $type;
			array_unshift( $recent_product, $id );
			$recent_product = array_slice( $recent_product, 0, min( 3, max( 0, count( $base ) - 1 ) ) );
		}

		return $out;
	}

	/**
	 * Featured / explore event.
	 *
	 * @param string $type    featured|explore.
	 * @param array  $product Public product data.
	 * @return array
	 */
	private static function product_item( string $type, array $product ): array {
		$item            = Notifications::base_item( $product, $type, '' );
		$item['label']   = Templates::label( $type );
		$item['message'] = Templates::render_for( $type, array( 'product' => $product['name'] ) );
		return $item;
	}

	/**
	 * Shipping-region event. Only produced for countries the store ships to.
	 *
	 * @param array  $location Picked location.
	 * @param string $level    Display level.
	 * @param array  $product  Public product data.
	 * @return array
	 */
	private static function location_item( array $location, string $level, array $product ): array {
		$label            = Locations::label( $location, $level );
		$message          = Templates::render_for(
			'location',
			array(
				'product'  => $product['name'],
				'location' => $label,
				'country'  => Country::name( $location['country'] ),
				'region'   => $location['region'],
				'province' => $location['region'],
				'state'    => $location['region'],
				'city'     => $location['city'],
			)
		);
		$mentions_product = '' !== $product['name'] && false !== strpos( $message, $product['name'] );
		$item             = Notifications::base_item( $product, 'location', '' );
		if ( ! $mentions_product ) {
			$item['product']    = '';
			$item['product_id'] = 0;
			$item['image']      = '';
			$item['image_w']    = 0;
			$item['image_h']    = 0;
			$item['url']        = Settings::get( 'link_to_product' ) && function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : '';
		}
		$item['label']    = Templates::label( 'location' );
		$item['message']  = $message;
		$item['location'] = $label;
		return $item;
	}

	/**
	 * Sample promotional events for admin previews.
	 *
	 * @return array<string,array|null>
	 */
	public static function preview_items(): array {
		$dataset = Cache::get_dataset(); // Admin/preview only: may build a cold cache.
		if ( empty( $dataset['pool']['products'] ) ) {
			return array();
		}
		$rng = new Rng( 12345 );
		$out = array();
		foreach ( array( 'featured', 'explore', 'location' ) as $type ) {
			$out[ $type ] = null;
		}
		$events = self::promos( array( 'type' => 'home' ), $rng, $dataset, 12, false );
		foreach ( $events as $event ) {
			if ( array_key_exists( $event['type'], $out ) && null === $out[ $event['type'] ] ) {
				$out[ $event['type'] ] = $event;
			}
		}
		return $out;
	}
}
