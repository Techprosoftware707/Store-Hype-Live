<?php
/**
 * Notification engine: turns the cached dataset into a prioritized queue of
 * sanitized, public notification objects for a given page context.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Notification engine.
 */
final class Notifications {

	const MAX_QUEUE = 20;

	/**
	 * Public notification types.
	 */
	const TYPES = array( 'product_purchase', 'purchase', 'sale', 'popular' );

	/**
	 * Build the queue of public notifications for a page context.
	 *
	 * Order of relevance on product pages:
	 *   1. real purchases of the product being viewed;
	 *   2. other real purchases;
	 *   3. real sales;
	 *   4. substantiated popular products;
	 *   5. nothing — no activity is ever invented.
	 *
	 * @param array      $context { type: string, product_id: int, term_id: int }.
	 * @param array|null $dataset Dataset (defaults to the cached one).
	 * @return array[] Public notification objects.
	 */
	public static function queue( array $context, ?array $dataset = null ): array {
		if ( ! Settings::get( 'enabled' ) ) {
			return array();
		}

		$dataset    = null === $dataset ? Cache::get_dataset() : $dataset;
		$now        = time();
		$products   = (array) ( $dataset['products'] ?? array() );
		$product_id = (int) ( $context['product_id'] ?? 0 );
		$term_id    = (int) ( $context['term_id'] ?? 0 );

		$lists = array(
			'product_purchase' => array(),
			'purchase'         => array(),
			'sale'             => array(),
			'popular'          => array(),
		);

		// Purchases.
		$want_general  = (bool) Settings::get( 'type_purchase' );
		$want_relevant = (bool) Settings::get( 'type_product_purchase' );
		$allowed       = Country::allowed_countries();
		if ( ( $want_general || $want_relevant ) && false !== $allowed ) {
			$since = $now - ( (int) Settings::get( 'lookback_hours' ) * HOUR_IN_SECONDS );
			foreach ( (array) ( $dataset['purchases'] ?? array() ) as $purchase ) {
				if ( (int) $purchase['ts'] < $since ) {
					continue;
				}
				// Serve-time re-check of the country rule (defence in depth).
				if ( is_array( $allowed ) && ! in_array( (string) $purchase['country'], $allowed, true ) ) {
					continue;
				}
				$ids = array_values(
					array_filter(
						array_map( 'intval', (array) $purchase['products'] ),
						static function ( $id ) use ( $products ) {
							return isset( $products[ $id ] );
						}
					)
				);
				if ( empty( $ids ) ) {
					continue;
				}

				$relevant_id = 0;
				if ( $want_relevant ) {
					if ( $product_id > 0 && in_array( $product_id, $ids, true ) ) {
						$relevant_id = $product_id;
					} elseif ( $term_id > 0 ) {
						foreach ( $ids as $id ) {
							if ( in_array( $term_id, (array) $products[ $id ]['cats'], true ) ) {
								$relevant_id = $id;
								break;
							}
						}
					}
				}

				if ( $relevant_id > 0 ) {
					$lists['product_purchase'][] = self::purchase_item( $purchase, $products[ $relevant_id ], 'product_purchase', $now );
				} elseif ( $want_general ) {
					$lists['purchase'][] = self::purchase_item( $purchase, $products[ $ids[0] ], 'purchase', $now );
				}
			}
		}

		// Sales.
		if ( Settings::get( 'type_sale' ) ) {
			foreach ( (array) ( $dataset['sales'] ?? array() ) as $id => $sale ) {
				$id = (int) $id;
				if ( ! isset( $products[ $id ] ) ) {
					continue;
				}
				// A sale that has ended since the cache was built is never shown.
				if ( ! empty( $sale['until'] ) && (int) $sale['until'] <= $now ) {
					continue;
				}
				$item = self::sale_item( $products[ $id ], (array) $sale );
				if ( $id === $product_id ) {
					array_unshift( $lists['sale'], $item );
				} else {
					$lists['sale'][] = $item;
				}
			}
		}

		// Popular products.
		if ( Settings::get( 'type_popular' ) && ! empty( $dataset['popular']['ids'] ) ) {
			$mode = (string) $dataset['popular']['mode'];
			if ( 'lifetime' !== $mode || null === $allowed ) {
				foreach ( (array) $dataset['popular']['ids'] as $id ) {
					$id = (int) $id;
					if ( ! isset( $products[ $id ] ) ) {
						continue;
					}
					$item = self::popular_item( $products[ $id ], $mode );
					if ( null === $item ) {
						continue;
					}
					if ( $id === $product_id ) {
						array_unshift( $lists['popular'], $item );
					} else {
						$lists['popular'][] = $item;
					}
				}
			}
		}

		$queue = self::assemble( $lists );

		/**
		 * Filter the public notification queue.
		 *
		 * Anything added here is sent to browsers; never add personal data.
		 *
		 * @param array $queue   Public notification objects.
		 * @param array $context Page context.
		 */
		return array_values( (array) apply_filters( 'wplh_notification_queue', $queue, $context ) );
	}

	/**
	 * Merge per-type lists into one queue honouring the priority mode, then
	 * avoid showing the same product twice in a row.
	 *
	 * @param array<string,array> $lists Lists keyed by type.
	 * @return array
	 */
	private static function assemble( array $lists ): array {
		$queue = array();

		if ( 'weighted' === Settings::get( 'priority_mode' ) ) {
			$weights = array(
				'product_purchase' => (int) Settings::get( 'weight_product_purchase' ),
				'purchase'         => (int) Settings::get( 'weight_purchase' ),
				'sale'             => (int) Settings::get( 'weight_sale' ),
				'popular'          => (int) Settings::get( 'weight_popular' ),
			);
			for ( $picked = 0; $picked < self::MAX_QUEUE; $picked++ ) {
				$available = array();
				$total     = 0;
				foreach ( $lists as $type => $list ) {
					if ( ! empty( $list ) && $weights[ $type ] > 0 ) {
						$available[ $type ] = $weights[ $type ];
						$total             += $weights[ $type ];
					}
				}
				if ( 0 === $total ) {
					break;
				}
				$roll = wp_rand( 1, $total );
				foreach ( $available as $type => $weight ) {
					$roll -= $weight;
					if ( $roll <= 0 ) {
						$queue[] = array_shift( $lists[ $type ] );
						break;
					}
				}
			}
		} else {
			foreach ( self::TYPES as $type ) {
				foreach ( $lists[ $type ] as $item ) {
					$queue[] = $item;
				}
			}
			$queue = array_slice( $queue, 0, self::MAX_QUEUE );
		}

		// De-duplicate by id and avoid consecutive notifications about the same product.
		$seen   = array();
		$unique = array();
		foreach ( $queue as $item ) {
			if ( isset( $seen[ $item['id'] ] ) ) {
				continue;
			}
			$seen[ $item['id'] ] = true;
			$unique[]            = $item;
		}
		$count = count( $unique );
		for ( $i = 1; $i < $count; $i++ ) {
			if ( $unique[ $i ]['product_id'] !== $unique[ $i - 1 ]['product_id'] ) {
				continue;
			}
			// Consecutive real purchases of the product being viewed are the point of the page.
			if ( 'product_purchase' === $unique[ $i ]['type'] && 'product_purchase' === $unique[ $i - 1 ]['type'] ) {
				continue;
			}
			for ( $j = $i + 1; $j < $count; $j++ ) {
				if ( $unique[ $j ]['product_id'] !== $unique[ $i - 1 ]['product_id'] ) {
					$swap         = $unique[ $i ];
					$unique[ $i ] = $unique[ $j ];
					$unique[ $j ] = $swap;
					break;
				}
			}
		}

		return $unique;
	}

	/**
	 * Fields shared by every notification type.
	 *
	 * @param array  $product Public product data.
	 * @param string $type    Notification type.
	 * @param string $id      Stable opaque id.
	 * @return array
	 */
	public static function base_item( array $product, string $type, string $id ): array {
		$show_image = (bool) Settings::get( 'show_image' );
		return array(
			'id'         => $id,
			'type'       => $type,
			'product_id' => (int) $product['id'],
			'product'    => (string) $product['name'],
			'url'        => Settings::get( 'link_to_product' ) ? (string) $product['url'] : '',
			'image'      => $show_image ? (string) $product['image'] : '',
			'image_w'    => $show_image ? (int) $product['img_w'] : 0,
			'image_h'    => $show_image ? (int) $product['img_h'] : 0,
			'label'      => Templates::label( 'sale' === $type ? 'sale' : ( 'popular' === $type ? 'popular' : 'purchase' ) ),
			'synthetic'  => false,
		);
	}

	/**
	 * Public purchase notification.
	 *
	 * @param array  $purchase Purchase record.
	 * @param array  $product  Public product data.
	 * @param string $type     purchase|product_purchase.
	 * @param int    $now      Current time.
	 * @return array
	 */
	private static function purchase_item( array $purchase, array $product, string $type, int $now ): array {
		$location = Country::public_location(
			(string) $purchase['country'],
			(string) $purchase['region'],
			(string) $purchase['city'],
			(string) Settings::get( 'location_level' )
		);

		$item            = self::base_item( $product, $type, Security::opaque_id( 'p:' . $purchase['key'] . ':' . $product['id'] ) );
		$item['message'] = Templates::render_for(
			'purchase',
			array(
				'product'  => $product['name'],
				'location' => $location['location'],
				'country'  => $location['country'],
				'region'   => $location['region'],
				'province' => $location['region'],
				'state'    => $location['region'],
				'city'     => $location['city'],
			)
		);

		// Only the location fields permitted by the configured level are included.
		foreach ( array( 'location', 'country', 'region', 'city' ) as $field ) {
			if ( '' !== $location[ $field ] ) {
				$item[ $field ] = $location[ $field ];
			}
		}

		$timestamp = (int) $purchase['ts'];
		if ( 'recently' !== Settings::get( 'time_display' ) ) {
			$item['timestamp'] = gmdate( 'Y-m-d\TH:i:s\Z', $timestamp );
		}
		$item['time_ago'] = self::time_ago( $timestamp, $now );
		$item['verified'] = (bool) Settings::get( 'show_verified' );

		return $item;
	}

	/**
	 * Public sale notification.
	 *
	 * @param array $product Public product data.
	 * @param array $sale    Sale info.
	 * @return array
	 */
	public static function sale_item( array $product, array $sale ): array {
		$tokens          = Products::sale_tokens( $sale );
		$item            = self::base_item( $product, 'sale', Security::opaque_id( 's:' . $product['id'] ) );
		$item['message'] = Templates::render_for( 'sale', array_merge( array( 'product' => $product['name'] ), $tokens ) );
		if ( Settings::get( 'show_sale_price' ) ) {
			$item['price'] = array(
				'sale'     => $tokens['sale_price'],
				'regular'  => empty( $sale['range'] ) ? $tokens['regular_price'] : '',
				'discount' => $tokens['discount_percent'],
			);
		}
		return $item;
	}

	/**
	 * Public popular-product notification.
	 *
	 * @param array  $product Public product data.
	 * @param string $mode    24h|7d|30d|lifetime.
	 * @return array|null
	 */
	private static function popular_item( array $product, string $mode ): ?array {
		$periods = array(
			'24h' => __( 'in the last 24 hours', 'wp-live-hype' ),
			'7d'  => __( 'in the last 7 days', 'wp-live-hype' ),
			'30d' => __( 'in the last 30 days', 'wp-live-hype' ),
		);
		$item    = self::base_item( $product, 'popular', Security::opaque_id( 'h:' . $product['id'] ) );
		if ( 'lifetime' === $mode ) {
			$item['message'] = Templates::render_for( 'bestseller', array( 'product' => $product['name'] ) );
		} elseif ( isset( $periods[ $mode ] ) ) {
			$item['message'] = Templates::render_for(
				'popular',
				array(
					'product' => $product['name'],
					'period'  => $periods[ $mode ],
				)
			);
		} else {
			return null;
		}
		return $item;
	}

	/**
	 * Server-rendered elapsed time (the browser recomputes it from the
	 * timestamp at display time; this is the fallback).
	 *
	 * @param int $timestamp Rounded order timestamp.
	 * @param int $now       Current time.
	 * @return string
	 */
	public static function time_ago( int $timestamp, int $now ): string {
		$mode = (string) Settings::get( 'time_display' );
		$age  = max( 0, $now - $timestamp );

		if ( 'recently' === $mode ) {
			return __( 'Recently', 'wp-live-hype' );
		}
		if ( 'approximate' === $mode ) {
			$labels = self::approximate_labels();
			if ( $age < HOUR_IN_SECONDS ) {
				return $labels['hour'];
			}
			if ( $age < 6 * HOUR_IN_SECONDS ) {
				return $labels['hours'];
			}
			if ( $age < DAY_IN_SECONDS ) {
				return $labels['day'];
			}
			if ( $age < WEEK_IN_SECONDS ) {
				return $labels['week'];
			}
			return $labels['month'];
		}
		if ( $age < MINUTE_IN_SECONDS ) {
			return __( 'Just now', 'wp-live-hype' );
		}
		/* translators: %s: human-readable time difference, e.g. "5 mins". */
		return sprintf( __( '%s ago', 'wp-live-hype' ), human_time_diff( $timestamp, $now ) );
	}

	/**
	 * Labels for the "approximate" time display.
	 *
	 * @return array<string,string>
	 */
	public static function approximate_labels(): array {
		return array(
			'hour'  => __( 'Within the last hour', 'wp-live-hype' ),
			'hours' => __( 'In the last few hours', 'wp-live-hype' ),
			'day'   => __( 'In the last 24 hours', 'wp-live-hype' ),
			'week'  => __( 'In the last week', 'wp-live-hype' ),
			'month' => __( 'In the last 30 days', 'wp-live-hype' ),
		);
	}

	/**
	 * Sample notifications for admin previews ONLY.
	 *
	 * Every preview item carries `preview: true`, and the UI renders a
	 * "SYNTHETIC PREVIEW — NOT REAL CUSTOMER ACTIVITY" label. These are never returned by
	 * the public REST endpoint.
	 *
	 * @param string $country Country code for the purchase preview.
	 * @param string $level   Location level override (empty = saved setting).
	 * @return array<string,array> type => item.
	 */
	public static function preview_items( string $country = '', string $level = '' ): array {
		$country = Country::is_valid( $country ) ? strtoupper( $country ) : (string) Settings::get( 'target_country' );
		if ( ! Country::is_valid( $country ) ) {
			$country = Country::base_country();
		}
		$level  = in_array( $level, array( 'none', 'country', 'region', 'city', 'city_region' ), true ) ? $level : (string) Settings::get( 'location_level' );
		$sample = Country::preview_location( $country );

		$sample_product = array(
			'id'    => 0,
			'name'  => __( 'Sample Product', 'wp-live-hype' ),
			'url'   => '',
			'image' => '',
			'img_w' => 0,
			'img_h' => 0,
			'cats'  => array(),
		);
		$dataset        = Cache::get_dataset(); // Admin/preview only: may build a cold cache.
		$products       = (array) ( $dataset['products'] ?? array() );

		// Purchase preview: a real product name (the location/time are sample values, labelled as such).
		$product = $sample_product;
		foreach ( (array) ( $dataset['purchases'] ?? array() ) as $record ) {
			$first = (int) ( $record['products'][0] ?? 0 );
			if ( isset( $products[ $first ] ) ) {
				$product = $products[ $first ];
				break;
			}
		}
		if ( 0 === (int) $product['id'] && ! empty( $products ) ) {
			$product = reset( $products );
		}

		$location = Country::public_location( $country, $sample['region'], $sample['city'], $level );
		$tokens   = array(
			'product'  => $product['name'],
			'location' => $location['location'],
			'country'  => $location['country'],
			'region'   => $location['region'],
			'province' => $location['region'],
			'state'    => $location['region'],
			'city'     => $location['city'],
		);

		$purchase = array_merge(
			self::base_item( $product, 'purchase', 'preview-purchase' ),
			array(
				'message'   => Templates::render_for( 'purchase', $tokens ),
				'timestamp' => 'recently' === Settings::get( 'time_display' ) ? null : gmdate( 'Y-m-d\TH:i:s\Z', time() - 2 * HOUR_IN_SECONDS ),
				'time_ago'  => self::time_ago( time() - 2 * HOUR_IN_SECONDS, time() ),
				'verified'  => (bool) Settings::get( 'show_verified' ),
				'preview'   => true,
			),
			array_filter( $location )
		);

		// Sale preview: a real sale when one exists; otherwise a generic sample
		// product, so sample prices are never attached to a real product.
		$sale_product = $sample_product;
		$sale_info    = array(
			'sale'    => 45.0,
			'regular' => 60.0,
			'range'   => false,
			'pct_max' => 25,
			'pct_min' => 25,
			'until'   => 0,
		);
		foreach ( (array) ( $dataset['sales'] ?? array() ) as $sale_id => $real_sale ) {
			if ( isset( $products[ (int) $sale_id ] ) ) {
				$sale_product = $products[ (int) $sale_id ];
				$sale_info    = (array) $real_sale;
				break;
			}
		}
		$sale            = self::sale_item( $sale_product, $sale_info );
		$sale['id']      = 'preview-sale';
		$sale['preview'] = true;

		// Popular preview: a genuinely popular product when one exists; otherwise the sample.
		$popular_product = $sample_product;
		$popular_mode    = '7d';
		foreach ( (array) ( $dataset['popular']['ids'] ?? array() ) as $popular_id ) {
			if ( isset( $products[ (int) $popular_id ] ) ) {
				$popular_product = $products[ (int) $popular_id ];
				$popular_mode    = (string) $dataset['popular']['mode'];
				break;
			}
		}
		$popular = self::popular_item( $popular_product, $popular_mode );
		if ( null !== $popular ) {
			$popular['id']      = 'preview-popular';
			$popular['preview'] = true;
		}

		return array(
			'purchase' => $purchase,
			'sale'     => $sale,
			'popular'  => $popular,
		);
	}
}
