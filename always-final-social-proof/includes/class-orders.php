<?php
/**
 * Real WooCommerce order data → anonymised purchase records.
 *
 * All access goes through wc_get_orders() / WC_Order getters, so it works
 * identically with High-Performance Order Storage and legacy post storage.
 *
 * Country rules are enforced here, on the server:
 *  - the geographic scope comes from Country::allowed_countries();
 *  - the scope is applied in the query where WooCommerce supports it AND
 *    re-checked for every order in PHP (defence in depth);
 *  - there is no fallback to other countries — an empty result stays empty.
 *
 * Only the fields needed for a notification are extracted: order date,
 * country, normalized region, sanitized city (only when the configured
 * location level uses it) and product IDs. Names, emails, phones, street
 * addresses, postcodes, order numbers and payment data are never read into
 * the dataset. Order IDs are replaced by a keyed, non-reversible hash.
 *
 * @package AlwaysFinal\SocialProof
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

/**
 * Order collector.
 */
final class Orders {

	const BATCH = 50;

	/**
	 * Timestamps are rounded down to this many seconds before leaving PHP.
	 */
	const TIME_PRECISION = 300;

	/**
	 * Popularity windows in seconds.
	 */
	const POPULAR_WINDOWS = array(
		'24h' => DAY_IN_SECONDS,
		'7d'  => WEEK_IN_SECONDS,
		'30d' => 30 * DAY_IN_SECONDS,
	);

	/**
	 * Collect purchase records and popularity counts.
	 *
	 * @return array{purchases:array,counts:array,stats:array}
	 */
	public static function collect(): array {
		$allowed = Country::allowed_countries();
		$result  = array(
			'purchases' => array(),
			'counts'    => array(),
			'stats'     => array(
				'scope'            => false === $allowed ? 'disabled' : ( null === $allowed ? 'all' : implode( ',', $allowed ) ),
				'scanned'          => 0,
				'qualifying'       => 0,
				'in_lookback'      => 0,
				'skipped_country'  => 0,
				'skipped_products' => 0,
				'limit_reached'    => false,
			),
		);

		if ( false === $allowed ) {
			Logger::debug( 'Purchase data disabled: geographic scope is not configured.' );
			return $result;
		}

		$statuses = Settings::qualifying_statuses();
		if ( empty( $statuses ) ) {
			Logger::error( 'No qualifying order statuses configured.' );
			return $result;
		}

		$need_purchases = (bool) Settings::get( 'type_purchase' ) || (bool) Settings::get( 'type_product_purchase' );
		$popular_source = (string) Settings::get( 'popular_source' );
		$need_popular   = (bool) Settings::get( 'type_popular' ) && isset( self::POPULAR_WINDOWS[ $popular_source ] );
		if ( ! $need_purchases && ! $need_popular ) {
			return $result;
		}

		$now             = time();
		$lookback_since  = $need_purchases ? $now - ( (int) Settings::get( 'lookback_hours' ) * HOUR_IN_SECONDS ) : $now;
		$popular_since   = $need_popular ? $now - self::POPULAR_WINDOWS[ $popular_source ] : $now;
		$since           = min( $lookback_since, $popular_since );
		$scan_limit      = (int) Settings::get( 'scan_limit' );
		$max_records     = (int) Settings::get( 'max_records' );
		$source          = (string) Settings::get( 'country_source' );
		$level           = (string) Settings::get( 'location_level' );
		$keep_region     = in_array( $level, array( 'region', 'city', 'city_region' ), true );
		$keep_city       = in_array( $level, array( 'city', 'city_region' ), true );
		$prefixed_status = array_map(
			static function ( $status ) {
				return 'wc-' . $status;
			},
			$statuses
		);

		$args = array(
			'type'         => 'shop_order',
			'status'       => $prefixed_status,
			'date_created' => '>=' . $since,
			'orderby'      => 'date',
			'order'        => 'DESC',
			'limit'        => self::BATCH,
			'return'       => 'objects',
		);

		// Push the country restriction into the query when it maps to a single address type.
		if ( is_array( $allowed ) ) {
			if ( 'billing' === $source ) {
				$args['billing_country'] = $allowed;
			} elseif ( 'shipping' === $source ) {
				$args['shipping_country'] = $allowed;
			}
		}

		/**
		 * Filter the wc_get_orders() arguments used to collect purchase data.
		 * The country rule is re-applied to every returned order regardless.
		 *
		 * @param array $args Query arguments.
		 */
		$args = (array) apply_filters( 'afsp_order_query_args', $args );

		$page = 1;
		do {
			$args['paged'] = $page;
			$orders        = wc_get_orders( $args );
			$orders        = is_array( $orders ) ? $orders : array();

			foreach ( $orders as $order ) {
				++$result['stats']['scanned'];

				if ( ! $order instanceof \WC_Order || $order instanceof \WC_Order_Refund ) {
					continue;
				}
				// Re-validate status in PHP; never trust a filtered query blindly.
				if ( ! in_array( $order->get_status(), $statuses, true ) ) {
					continue;
				}
				$created = $order->get_date_created();
				if ( ! $created ) {
					continue;
				}
				$timestamp = (int) $created->getTimestamp();
				if ( $timestamp < $since || $timestamp > $now + HOUR_IN_SECONDS ) {
					continue;
				}

				$address = self::location_address( $order, $source );

				// Server-side country enforcement. No fallback to any other country.
				if ( is_array( $allowed ) && ! in_array( $address['country'], $allowed, true ) ) {
					++$result['stats']['skipped_country'];
					continue;
				}

				$product_ids = self::featurable_product_ids( $order );
				if ( empty( $product_ids ) ) {
					++$result['stats']['skipped_products'];
					continue;
				}

				++$result['stats']['qualifying'];

				if ( $need_popular && $timestamp >= $popular_since ) {
					foreach ( $product_ids as $product_id ) {
						$result['counts'][ $product_id ] = ( $result['counts'][ $product_id ] ?? 0 ) + 1;
					}
				}

				if ( $need_purchases && $timestamp >= $lookback_since ) {
					++$result['stats']['in_lookback'];
					if ( count( $result['purchases'] ) < $max_records ) {
						$country                 = $address['country'];
						$result['purchases'][]   = array(
							'key'      => Security::opaque_id( 'order:' . $order->get_id() ),
							'ts'       => $timestamp - ( $timestamp % self::TIME_PRECISION ),
							'country'  => $country,
							'region'   => $keep_region && '' !== $country ? Country::region_name( $country, $address['state'] ) : '',
							'city'     => $keep_city && '' !== $country ? Security::clean_place_name( $address['city'] ) : '',
							'products' => $product_ids,
						);
					}
				}
			}

			$batch_size = count( $orders );
			unset( $orders );
			++$page;

			if ( $result['stats']['scanned'] >= $scan_limit && self::BATCH === $batch_size ) {
				$result['stats']['limit_reached'] = true;
				break;
			}
		} while ( self::BATCH === $batch_size );

		Logger::debug(
			'Order query complete.',
			array(
				'scope'           => $result['stats']['scope'],
				'scanned'         => $result['stats']['scanned'],
				'qualifying'      => $result['stats']['qualifying'],
				'in_lookback'     => $result['stats']['in_lookback'],
				'skipped_country' => $result['stats']['skipped_country'],
				'limit_reached'   => $result['stats']['limit_reached'],
			)
		);

		return $result;
	}

	/**
	 * The address used both for country matching and for display.
	 *
	 * Using the same address for both guarantees the displayed location is
	 * always inside the permitted country.
	 *
	 * @param \WC_Order $order  Order.
	 * @param string    $source billing|shipping|shipping_billing.
	 * @return array{country:string,state:string,city:string}
	 */
	private static function location_address( \WC_Order $order, string $source ): array {
		$shipping = array(
			'country' => strtoupper( trim( (string) $order->get_shipping_country() ) ),
			'state'   => (string) $order->get_shipping_state(),
			'city'    => (string) $order->get_shipping_city(),
		);
		if ( 'shipping' === $source ) {
			return $shipping;
		}
		if ( 'shipping_billing' === $source && '' !== $shipping['country'] ) {
			return $shipping;
		}
		return array(
			'country' => strtoupper( trim( (string) $order->get_billing_country() ) ),
			'state'   => (string) $order->get_billing_state(),
			'city'    => (string) $order->get_billing_city(),
		);
	}

	/**
	 * Parent product IDs in an order that may be featured publicly.
	 *
	 * @param \WC_Order $order Order.
	 * @return int[]
	 */
	private static function featurable_product_ids( \WC_Order $order ): array {
		$ids = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			// For variations get_product_id() is the parent product.
			$product_id = (int) $item->get_product_id();
			if ( $product_id <= 0 || isset( $ids[ $product_id ] ) ) {
				continue;
			}
			if ( null !== Products::public_data( $product_id ) ) {
				$ids[ $product_id ] = $product_id;
			}
			if ( count( $ids ) >= 10 ) {
				break;
			}
		}
		return array_values( $ids );
	}
}
