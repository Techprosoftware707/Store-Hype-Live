<?php
/**
 * Conversion layer: product context, recommendations, free-shipping
 * threshold, funnel analytics and anonymous attribution.
 *
 * Everything here is either genuine store data (prices, sale state, shipping
 * rules, cart totals read by the visitor's own browser from the Store API)
 * or anonymous aggregate counters. No personal data is stored.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Conversion features.
 */
final class Conversion {

	const COOKIE        = 'wplh_attr';
	const ORDER_META    = '_wplh_attributed';
	const AB_OPTION     = 'wplh_ab_experiment';
	const FUNNEL_EVENTS = array( 'session', 'impression', 'relevant', 'click', 'cta', 'dismiss', 'product_view', 'product_view_attr', 'atc', 'atc_attr', 'cart', 'checkout', 'checkout_attr', 'purchase_attr' );
	const CLIENT_TYPES  = array( 'product_cta', 'recommend', 'cart', 'checkout', 'promotion', 'nudge' );
	const VARIANTS      = array( 'a', 'b', '-' );
	const DEVICES       = array( 'm', 'd', 'x' );
	const PRESETS       = array(
		'soft'       => array(
			'first'       => 15,
			'interval'    => 45,
			'intervalMax' => 120,
			'maxPage'     => 2,
			'maxSession'  => 4,
		),
		'balanced'   => array(
			'first'       => 10,
			'interval'    => 25,
			'intervalMax' => 75,
			'maxPage'     => 3,
			'maxSession'  => 6,
		),
		'aggressive' => array(
			'first'       => 6,
			'interval'    => 15,
			'intervalMax' => 45,
			'maxPage'     => 4,
			'maxSession'  => 10,
		),
	);

	/**
	 * Register hooks.
	 */
	public static function init(): void {
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'attribute_order' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'attribute_order' ) );
	}

	/*
	 * Store facts
	 */

	/**
	 * Genuine free-shipping threshold for the target country, if one is
	 * configured as "minimum order amount" (or "amount or coupon").
	 *
	 * @return array{country:string,min:float,ignore_discounts:bool}|null
	 */
	public static function free_shipping(): ?array {
		if ( ! class_exists( '\WC_Shipping_Zones' ) || ! class_exists( '\WC_Shipping_Free_Shipping' ) ) {
			return null;
		}
		$country = Locations::engine_country( new Rng( 7 ) );
		if ( '' === $country ) {
			$country = Country::base_country();
		}
		$zone = \WC_Shipping_Zones::get_zone_matching_package(
			array(
				'destination' => array(
					'country'  => $country,
					'state'    => '',
					'postcode' => '',
				),
			)
		);
		$best = null;
		foreach ( (array) $zone->get_shipping_methods( true ) as $method ) {
			if ( ! $method instanceof \WC_Shipping_Free_Shipping ) {
				continue;
			}
			$min = (float) $method->get_option( 'min_amount', 0 );
			if ( $min <= 0 || ! in_array( $method->get_option( 'requires' ), array( 'min_amount', 'either' ), true ) ) {
				continue;
			}
			if ( null === $best || $min < $best['min'] ) {
				$best = array(
					'country'          => $country,
					'min'              => $min,
					'ignore_discounts' => 'yes' === $method->get_option( 'ignore_discounts' ),
				);
			}
		}
		return $best;
	}

	/**
	 * Store configuration for the frontend (no personal data).
	 *
	 * @return array
	 */
	public static function store_config(): array {
		$dataset = Cache::get_dataset( false );
		return array(
			'shop'         => function_exists( 'wc_get_page_permalink' ) ? esc_url_raw( wc_get_page_permalink( 'shop' ) ) : '',
			'cart'         => function_exists( 'wc_get_cart_url' ) ? esc_url_raw( wc_get_cart_url() ) : '',
			'checkout'     => function_exists( 'wc_get_checkout_url' ) ? esc_url_raw( wc_get_checkout_url() ) : '',
			'storeApi'     => esc_url_raw( rest_url( 'wc/store/v1/cart' ) ),
			'freeShipping' => Settings::get( 'conv_free_shipping' ) ? ( $dataset['free_shipping'] ?? null ) : null,
			'inclTax'      => 'incl' === get_option( 'woocommerce_tax_display_cart' ),
			'currency'     => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
		);
	}

	/**
	 * Effective timing (preset or the Frequency tab when set to Custom).
	 *
	 * @param string $preset Preset key.
	 * @return array{first:int,interval:int,intervalMax:int,maxPage:int,maxSession:int}
	 */
	public static function timing( string $preset ): array {
		if ( isset( self::PRESETS[ $preset ] ) ) {
			return self::PRESETS[ $preset ];
		}
		return array(
			'first'       => (int) Settings::get( 'first_delay' ),
			'interval'    => (int) Settings::get( 'interval' ),
			'intervalMax' => (int) Settings::get( 'interval_max' ),
			'maxPage'     => (int) Settings::get( 'max_per_page' ),
			'maxSession'  => (int) Settings::get( 'max_per_session' ),
		);
	}

	/**
	 * CTA button text (custom or translated default).
	 *
	 * @param string $key view|explore|shop|cart|checkout|add|options|offer|continue.
	 * @return string
	 */
	public static function cta_text( string $key ): string {
		$custom = (string) Settings::get( 'cta_' . $key );
		if ( '' !== $custom ) {
			return $custom;
		}
		$defaults = self::default_cta_texts();
		return $defaults[ $key ] ?? '';
	}

	/**
	 * Default CTA texts.
	 *
	 * @return array<string,string>
	 */
	public static function default_cta_texts(): array {
		return array(
			'view'     => __( 'View product', 'wp-live-hype' ),
			'explore'  => __( 'Explore', 'wp-live-hype' ),
			'shop'     => __( 'Shop now', 'wp-live-hype' ),
			'cart'     => __( 'View cart', 'wp-live-hype' ),
			'checkout' => __( 'Checkout', 'wp-live-hype' ),
			'add'      => __( 'Add to cart', 'wp-live-hype' ),
			'options'  => __( 'Choose options', 'wp-live-hype' ),
			'offer'    => __( 'See offer', 'wp-live-hype' ),
			'continue' => __( 'Continue shopping', 'wp-live-hype' ),
		);
	}

	/**
	 * A/B experiment configuration. The id changes whenever the experiment
	 * changes, so visitors are re-assigned and results start fresh.
	 *
	 * @return array|null
	 */
	public static function experiment(): ?array {
		if ( ! Settings::get( 'ab_enabled' ) ) {
			return null;
		}
		$variable = (string) Settings::get( 'ab_variable' );
		switch ( $variable ) {
			case 'copy':
				$value = array_values( array_filter( array_map( 'trim', explode( "\n", (string) Settings::get( 'ab_copy_b' ) ) ), 'strlen' ) );
				if ( ! $value ) {
					return null;
				}
				break;
			case 'cta':
				$value = (string) Settings::get( 'ab_cta_b' );
				if ( '' === $value ) {
					return null;
				}
				break;
			case 'position':
				$value = (string) Settings::get( 'ab_position_b' );
				break;
			case 'animation':
				$value = (string) Settings::get( 'ab_animation_b' );
				break;
			case 'frequency':
				$value = self::timing( (string) Settings::get( 'ab_frequency_b' ) );
				break;
			case 'sound':
				$value = ! Settings::get( 'sound_desktop' );
				break;
			case 'image':
				$value = ! Settings::get( 'show_image' );
				break;
			default:
				return null;
		}
		return array(
			'id'       => substr( md5( $variable . '|' . wp_json_encode( $value ) ), 0, 8 ),
			'variable' => $variable,
			'b'        => $value,
		);
	}

	/**
	 * Remember when the current A/B experiment started, so results only
	 * count data collected for this exact experiment.
	 */
	public static function track_experiment(): void {
		$experiment = self::experiment();
		$id         = $experiment ? $experiment['id'] : '';
		$stored     = get_option( self::AB_OPTION, array() );
		if ( ! is_array( $stored ) || ( $stored['id'] ?? null ) !== $id ) {
			update_option(
				self::AB_OPTION,
				array(
					'id'      => $id,
					'started' => wp_date( 'Y-m-d' ),
				),
				false
			);
		}
	}

	/**
	 * Start date (Y-m-d) of the running experiment, or '' when none runs.
	 *
	 * @return string
	 */
	public static function experiment_started(): string {
		$experiment = self::experiment();
		$stored     = get_option( self::AB_OPTION, array() );
		if ( ! $experiment || ! is_array( $stored ) || ( $stored['id'] ?? '' ) !== $experiment['id'] ) {
			return '';
		}
		return (string) ( $stored['started'] ?? '' );
	}

	/**
	 * Conversion configuration for the frontend script (no personal data).
	 *
	 * @return array
	 */
	public static function client_config(): array {
		$templates = array();
		$labels    = array();
		foreach ( Templates::CLIENT_TYPES as $type ) {
			$templates[ $type ] = Templates::templates( $type );
			$labels[ $type ]    = Templates::label( $type );
		}
		$cta = array();
		foreach ( array_keys( self::default_cta_texts() ) as $key ) {
			$cta[ $key ] = self::cta_text( $key );
		}
		return array(
			'store'       => self::store_config(),
			'goal'        => (string) Settings::get( 'conversion_goal' ),
			'timing'      => self::timing( (string) Settings::get( 'conversion_preset' ) ),
			'types'       => array(
				'productCta'   => (bool) Settings::get( 'conv_product_cta' ),
				'recommend'    => (bool) Settings::get( 'conv_recommend' ),
				'cart'         => (bool) Settings::get( 'conv_cart' ),
				'freeShipping' => (bool) Settings::get( 'conv_free_shipping' ),
				'checkout'     => (bool) Settings::get( 'conv_checkout' ),
				'nudge'        => (bool) Settings::get( 'conv_nudge' ),
			),
			'cta'         => Settings::get( 'cta_enabled' ) ? $cta : null,
			'ctaStyle'    => (string) Settings::get( 'cta_style' ),
			'suppress'    => array(
				'dismissals' => (int) Settings::get( 'suppress_dismissals' ),
				'repeat'     => (int) Settings::get( 'suppress_repeat' ),
			),
			'attribution' => Settings::get( 'attribution' ) && Settings::get( 'analytics_enabled' ) ? (int) Settings::get( 'attribution_window' ) : 0,
			'ab'          => self::experiment(),
			'templates'   => $templates,
			'labels'      => $labels,
		);
	}

	/*
	 * Product context
	 */

	/**
	 * Conversion context for the product being viewed: a product CTA event
	 * and genuine recommendations. Cached per product.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public static function product_context( int $product_id ): array {
		$empty = array(
			'product' => null,
			'related' => array(),
		);
		if ( $product_id <= 0 || null === Products::public_data( $product_id ) ) {
			return $empty;
		}

		$key    = 'wplh_ctx_' . (int) get_option( Cache::VERSION_OPTION, 1 ) . '_' . $product_id;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$public  = Products::public_data( $product_id );
		$product = wc_get_product( $product_id );
		$item    = Notifications::base_item( $public, 'product_cta', 'ctx_' . Security::opaque_id( 'ctx:' . $product_id ) );

		$tokens = array( 'product' => $public['name'] );
		$sale   = $product ? Products::sale_info( $product ) : null;
		if ( $sale ) {
			$tokens = array_merge( $tokens, Products::sale_tokens( $sale ) );
		}
		$item['label']   = Templates::label( 'product_cta' );
		$item['message'] = Templates::render_for( 'product_cta', $tokens );
		$item['onSale']  = null !== $sale;
		// A deliberate click on this link adds the product (simple, purchasable, in stock only).
		$can_add           = $product && $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() && ! $product->is_sold_individually();
		$item['addUrl']    = $can_add ? esc_url_raw( add_query_arg( 'add-to-cart', $product_id, $public['url'] ) ) : '';
		$item['synthetic'] = true;

		$context = array(
			'product' => Settings::get( 'conv_product_cta' ) ? $item : null,
			'related' => Settings::get( 'conv_recommend' ) ? self::related_items( $product_id, $public['name'] ) : array(),
		);
		set_transient( $key, $context, Cache::ttl() * 2 );
		return $context;
	}

	/**
	 * Related products: the store's own up-sells and cross-sells first, then
	 * products from the same categories/tags (WooCommerce's related logic).
	 * No claims are made about why products are related.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $current    Current product name.
	 * @return array[]
	 */
	public static function related_items( int $product_id, string $current ): array {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array();
		}
		$ids = array();
		if ( 'category' !== Settings::get( 'recommend_source' ) ) {
			$ids = array_merge( $product->get_upsell_ids(), $product->get_cross_sell_ids() );
		}
		$ids = array_merge( $ids, wc_get_related_products( $product_id, 6 ) );

		$out = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$public = $id !== $product_id ? Products::public_data( $id ) : null;
			if ( null === $public ) {
				continue;
			}
			$item              = Notifications::base_item( $public, 'recommend', 'rec_' . Security::opaque_id( 'rec:' . $product_id . ':' . $id ) );
			$item['label']     = Templates::label( 'recommend' );
			$item['message']   = Templates::render_for(
				'recommend',
				array(
					'product' => $public['name'],
					'current' => $current,
				)
			);
			$item['synthetic'] = true;
			$out[]             = $item;
			if ( count( $out ) >= 3 ) {
				break;
			}
		}
		return $out;
	}

	/*
	 * Funnel analytics
	 */

	/**
	 * Funnel table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wplh_funnel';
	}

	/**
	 * Create the funnel table.
	 */
	public static function create_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  stat_date date NOT NULL,
  event varchar(20) NOT NULL,
  variant char(1) NOT NULL DEFAULT '-',
  device char(1) NOT NULL DEFAULT 'x',
  hits bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY stat (stat_date,event,variant,device)
) {$collate};"
		);
	}

	/**
	 * Funnel events a browser may report. Purchases are never accepted from
	 * the browser: they are recorded server-side when an order is created.
	 *
	 * @return string[]
	 */
	public static function client_events(): array {
		return array_values( array_diff( self::FUNNEL_EVENTS, array( 'purchase_attr' ) ) );
	}

	/**
	 * Validate and aggregate funnel events from the browser.
	 *
	 * @param mixed $raw Decoded payload.
	 * @return array<string,int> "event|variant|device" => count.
	 */
	public static function validate_batch( $raw ): array {
		$events = is_array( $raw ) && isset( $raw['funnel'] ) && is_array( $raw['funnel'] ) ? array_slice( $raw['funnel'], 0, 25 ) : array();
		$allow  = self::client_events();
		$valid  = array();
		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}
			$name    = isset( $event['e'] ) && is_string( $event['e'] ) ? $event['e'] : '';
			$variant = isset( $event['v'] ) && is_string( $event['v'] ) && in_array( $event['v'], self::VARIANTS, true ) ? $event['v'] : '-';
			$device  = isset( $event['d'] ) && is_string( $event['d'] ) && in_array( $event['d'], self::DEVICES, true ) ? $event['d'] : 'x';
			if ( ! in_array( $name, $allow, true ) ) {
				continue;
			}
			if ( ! Settings::get( 'ab_enabled' ) ) {
				$variant = '-';
			}
			$key           = $name . '|' . $variant . '|' . $device;
			$valid[ $key ] = ( $valid[ $key ] ?? 0 ) + 1;
		}
		return $valid;
	}

	/**
	 * Record aggregated funnel events.
	 *
	 * @param array<string,int> $aggregated "event|variant|device" => count.
	 */
	public static function record( array $aggregated ): void {
		global $wpdb;
		if ( empty( $aggregated ) ) {
			return;
		}
		$date   = wp_date( 'Y-m-d' );
		$rows   = array();
		$params = array();
		foreach ( $aggregated as $key => $count ) {
			list( $event, $variant, $device ) = explode( '|', (string) $key );
			$rows[]                           = '(%s,%s,%s,%s,%d)';
			array_push( $params, $date, $event, $variant, $device, (int) $count );
		}
		$table = self::table();
		$sql   = "INSERT INTO {$table} (stat_date, event, variant, device, hits) VALUES " . implode( ',', $rows ) . ' ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Placeholders built above.
		$wpdb->query( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Funnel totals for a period.
	 *
	 * @param int $days Days.
	 * @return array{events:array<string,int>,variants:array,devices:array,ab_started:string}
	 */
	public static function report( int $days ): array {
		global $wpdb;
		$out   = array(
			'events'   => array_fill_keys( self::FUNNEL_EVENTS, 0 ),
			'variants' => array(
				'a' => array_fill_keys( self::FUNNEL_EVENTS, 0 ),
				'b' => array_fill_keys( self::FUNNEL_EVENTS, 0 ),
			),
			'devices'  => array(
				'm' => array_fill_keys( self::FUNNEL_EVENTS, 0 ),
				'd' => array_fill_keys( self::FUNNEL_EVENTS, 0 ),
			),
		);
		$table = self::table();
		$since = wp_date( 'Y-m-d', time() - ( max( 1, $days ) - 1 ) * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT stat_date, event, variant, device, SUM(hits) AS total FROM {$table} WHERE stat_date >= %s GROUP BY stat_date, event, variant, device", $since ), ARRAY_A );
		// A/B results only count days since the current experiment started.
		$ab_since          = self::experiment_started();
		$out['ab_started'] = $ab_since;
		foreach ( (array) $rows as $row ) {
			if ( ! isset( $out['events'][ $row['event'] ] ) ) {
				continue;
			}
			$total                           = (int) $row['total'];
			$out['events'][ $row['event'] ] += $total;
			if ( '' !== $ab_since && $row['stat_date'] >= $ab_since && isset( $out['variants'][ $row['variant'] ] ) ) {
				$out['variants'][ $row['variant'] ][ $row['event'] ] += $total;
			}
			if ( isset( $out['devices'][ $row['device'] ] ) ) {
				$out['devices'][ $row['device'] ][ $row['event'] ] += $total;
			}
		}
		return $out;
	}

	/**
	 * Attributed orders and revenue, plus store-wide paid orders for context.
	 *
	 * @param int $days Days.
	 * @return array{attributed:int,revenue:float,store_orders:int}
	 */
	public static function order_report( int $days ): array {
		$since    = time() - $days * DAY_IN_SECONDS;
		$statuses = array( 'wc-processing', 'wc-completed', 'wc-on-hold' );
		$ids      = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => $statuses,
				'date_created' => '>=' . $since,
				'meta_key'     => self::ORDER_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'limit'        => 500,
				'return'       => 'ids',
			)
		);
		$revenue  = 0.0;
		foreach ( (array) $ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				$revenue += (float) $order->get_total();
			}
		}
		$store = wc_get_orders(
			array(
				'type'         => 'shop_order',
				'status'       => $statuses,
				'date_created' => '>=' . $since,
				'limit'        => 1,
				'paginate'     => true,
				'return'       => 'ids',
			)
		);
		return array(
			'attributed'   => count( (array) $ids ),
			'revenue'      => $revenue,
			'store_orders' => is_object( $store ) ? (int) $store->total : 0,
		);
	}

	/**
	 * Conversion Health: observations computed only from measured counters.
	 * No composite score is invented; each line states the measured value and,
	 * when there is enough data, a factual observation with a suggestion.
	 *
	 * @param array $report From report().
	 * @return array<int,array{label:string,value:string,status:string,note:string}>
	 */
	public static function health( array $report ): array {
		$e    = $report['events'];
		$min  = 100;
		$out  = array();
		$rate = static function ( int $x, int $n ): string {
			return $n > 0 ? number_format_i18n( $x / $n * 100, 1 ) . '%' : '—';
		};
		$need = static function ( int $n ) use ( $min ): string {
			/* translators: 1: impressions collected, 2: impressions needed. */
			return sprintf( __( 'Not enough data yet (%1$s of %2$s impressions).', 'wp-live-hype' ), number_format_i18n( $n ), number_format_i18n( $min ) );
		};
		$imp  = (int) $e['impression'];
		$act  = (int) $e['click'] + (int) $e['cta'];

		// Interaction rate.
		$status = 'info';
		$note   = $need( $imp );
		if ( $imp >= $min ) {
			$ratio  = $act / $imp;
			$status = $ratio >= 0.01 ? 'good' : 'warn';
			$note   = $ratio >= 0.01 ? __( 'Visitors regularly act on messages.', 'wp-live-hype' ) : __( 'Few visitors act on messages. Consider testing button text or enabling product calls to action.', 'wp-live-hype' );
		}
		$out[] = array(
			'label'  => __( 'Interaction rate (clicks + button clicks per impression)', 'wp-live-hype' ),
			'value'  => $rate( $act, $imp ),
			'status' => $status,
			'note'   => $note,
		);

		// Fatigue.
		$dis    = (int) $e['dismiss'];
		$status = 'info';
		$note   = $need( $imp );
		if ( $imp >= $min ) {
			$ratio  = $dis / $imp;
			$status = $ratio <= 0.08 ? 'good' : 'warn';
			$note   = $ratio <= 0.08 ? __( 'Dismissals are low.', 'wp-live-hype' ) : __( 'Many messages are closed. A softer preset or longer intervals may reduce fatigue.', 'wp-live-hype' );
		}
		$out[] = array(
			'label'  => __( 'Dismiss rate', 'wp-live-hype' ),
			'value'  => $rate( $dis, $imp ),
			'status' => $status,
			'note'   => $note,
		);

		// Relevance.
		$rel    = (int) $e['relevant'];
		$status = 'info';
		$note   = $need( $imp );
		if ( $imp >= $min ) {
			$ratio  = $rel / $imp;
			$status = $ratio >= 0.2 ? 'good' : 'warn';
			$note   = $ratio >= 0.2 ? __( 'A healthy share of messages relate to what the visitor is viewing or has in their cart.', 'wp-live-hype' ) : __( 'Most messages are general. Recommendations, product calls to action and up-sells / cross-sells on your products increase relevance.', 'wp-live-hype' );
		}
		$out[] = array(
			'label'  => __( 'Context-relevant impressions', 'wp-live-hype' ),
			'value'  => $rate( $rel, $imp ),
			'status' => $status,
			'note'   => $note,
		);

		// Add to cart after interacting.
		$out[] = array(
			'label'  => __( 'Add to cart after a click (per click)', 'wp-live-hype' ),
			'value'  => $rate( (int) $e['atc_attr'], $act ),
			'status' => 'info',
			'note'   => $act > 0 ? __( 'Share of interactions followed by an add to cart within the attribution window. Association only.', 'wp-live-hype' ) : __( 'No interactions recorded yet.', 'wp-live-hype' ),
		);

		// Mobile vs desktop.
		$m      = $report['devices']['m'];
		$d      = $report['devices']['d'];
		$mi     = (int) $m['impression'];
		$di     = (int) $d['impression'];
		$mr     = $mi > 0 ? ( $m['click'] + $m['cta'] ) / $mi : 0;
		$dr     = $di > 0 ? ( $d['click'] + $d['cta'] ) / $di : 0;
		$status = 'info';
		$note   = __( 'Needs at least 100 impressions on each device type.', 'wp-live-hype' );
		if ( $mi >= $min && $di >= $min ) {
			$gap    = max( $mr, $dr ) > 0 ? min( $mr, $dr ) / max( $mr, $dr ) : 1;
			$status = $gap >= 0.5 ? 'good' : 'warn';
			$note   = $gap >= 0.5 ? __( 'Mobile and desktop perform similarly.', 'wp-live-hype' ) : ( $mr < $dr ? __( 'Mobile visitors interact much less. Check the mobile position and preview on a phone.', 'wp-live-hype' ) : __( 'Desktop visitors interact much less. Check the desktop position.', 'wp-live-hype' ) );
		}
		$out[] = array(
			'label'  => __( 'Mobile vs desktop interaction', 'wp-live-hype' ),
			'value'  => $rate( (int) ( $m['click'] + $m['cta'] ), $mi ) . ' / ' . $rate( (int) ( $d['click'] + $d['cta'] ), $di ),
			'status' => $status,
			'note'   => $note,
		);

		return $out;
	}

	/**
	 * Two-proportion z-test (two-sided) comparing rate x1/n1 with x2/n2.
	 *
	 * @param int $x1 Successes A.
	 * @param int $n1 Trials A.
	 * @param int $x2 Successes B.
	 * @param int $n2 Trials B.
	 * @return array{z:float,p:float,significant:bool,enough:bool}
	 */
	public static function ztest( int $x1, int $n1, int $x2, int $n2 ): array {
		$out = array(
			'z'           => 0.0,
			'p'           => 1.0,
			'significant' => false,
			'enough'      => $n1 >= 100 && $n2 >= 100,
		);
		if ( $n1 <= 0 || $n2 <= 0 ) {
			return $out;
		}
		$x1     = min( $x1, $n1 );
		$x2     = min( $x2, $n2 );
		$pooled = ( $x1 + $x2 ) / ( $n1 + $n2 );
		$se     = sqrt( $pooled * ( 1 - $pooled ) * ( 1 / $n1 + 1 / $n2 ) );
		if ( $se <= 0 ) {
			return $out;
		}
		$z                  = ( $x2 / $n2 - $x1 / $n1 ) / $se;
		$out['z']           = $z;
		$out['p']           = 2 * ( 1 - self::normal_cdf( abs( $z ) ) );
		$out['significant'] = $out['enough'] && $out['p'] < 0.05;
		return $out;
	}

	/**
	 * Standard normal CDF (Abramowitz–Stegun 7.1.26 approximation of erf).
	 *
	 * @param float $x Value.
	 * @return float
	 */
	private static function normal_cdf( float $x ): float {
		$t   = 1 / ( 1 + 0.3275911 * abs( $x ) / M_SQRT2 );
		$erf = 1 - ( ( ( ( ( 1.061405429 * $t - 1.453152027 ) * $t ) + 1.421413741 ) * $t - 0.284496736 ) * $t + 0.254829592 ) * $t * exp( -$x * $x / 2 );
		return 0.5 * ( 1 + ( $x < 0 ? -$erf : $erf ) );
	}

	/**
	 * Delete funnel data.
	 */
	public static function reset(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Delete funnel rows older than the retention period.
	 */
	public static function purge_old(): void {
		global $wpdb;
		$table = self::table();
		$since = wp_date( 'Y-m-d', time() - (int) Settings::get( 'analytics_retention' ) * DAY_IN_SECONDS );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE stat_date < %s", $since ) );
	}

	/*
	 * Attribution
	 */

	/**
	 * Mark a new order as attributed when the visitor interacted with a
	 * WP Live Hype notification within the attribution window. Only a flag
	 * (and the A/B variant letter) is stored — no identifiers.
	 *
	 * @param mixed $order Order.
	 */
	public static function attribute_order( $order ): void {
		if ( ! $order instanceof \WC_Order || ! Settings::get( 'attribution' ) || ! Settings::get( 'analytics_enabled' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by the strict pattern below.
		$raw = isset( $_COOKIE[ self::COOKIE ] ) ? (string) wp_unslash( $_COOKIE[ self::COOKIE ] ) : '';
		if ( ! preg_match( '/^[a-f0-9]{16}\.([ab-])\.([md])\.(\d{10,13})$/', $raw, $m ) ) {
			return;
		}
		$touched = (int) substr( $m[3], 0, 10 );
		if ( $touched < time() - (int) Settings::get( 'attribution_window' ) * MINUTE_IN_SECONDS || $touched > time() + 300 ) {
			return;
		}
		if ( $order->get_meta( self::ORDER_META ) ) {
			return;
		}
		$order->update_meta_data( self::ORDER_META, $m[1] );
		$order->save_meta_data();
		self::record( array( 'purchase_attr|' . $m[1] . '|' . $m[2] => 1 ) );
		Logger::debug( 'Order attributed to WP Live Hype interaction.', array( 'variant' => $m[1] ) );
	}

	/*
	 * Previews
	 */

	/**
	 * Sample conversion messages for admin previews. Cart amounts are sample
	 * values (previews are labelled as such); the free-shipping threshold is
	 * the store's real one when configured.
	 *
	 * @return array<string,array|null>
	 */
	public static function preview_items(): array {
		$dataset  = Cache::get_dataset(); // Admin/preview only.
		$products = array_values( (array) ( $dataset['products'] ?? array() ) );
		$out      = array_fill_keys( array( 'product_cta', 'recommend', 'cart', 'promotion', 'checkout', 'nudge' ), null );

		if ( $products ) {
			$first   = $products[0];
			$context = self::product_context( (int) $first['id'] );
			if ( $context['product'] ) {
				$out['product_cta'] = $context['product'];
			}
			if ( ! empty( $context['related'][0] ) ) {
				$out['recommend'] = $context['related'][0];
			} elseif ( isset( $products[1] ) ) {
				$item             = Notifications::base_item( $products[1], 'recommend', 'preview-recommend' );
				$item['label']    = Templates::label( 'recommend' );
				$item['message']  = Templates::render_for(
					'recommend',
					array(
						'product' => $products[1]['name'],
						'current' => $first['name'],
					)
				);
				$out['recommend'] = $item;
			}
		}

		$free      = $dataset['free_shipping'] ?? null;
		$threshold = $free ? (float) $free['min'] : 100.0;
		$subtotal  = round( $threshold * 0.7, 2 );
		$values    = array(
			'cart_count'       => '2',
			'cart_total'       => Products::format_price( $subtotal ),
			'threshold'        => Products::format_price( $threshold ),
			'amount_remaining' => Products::format_price( $threshold - $subtotal ),
		);
		foreach ( array( 'cart', 'promotion', 'checkout', 'nudge' ) as $type ) {
			$message = '';
			foreach ( Templates::templates( $type ) as $template ) {
				$message = (string) Templates::render( $template, $values );
				if ( '' !== $message ) {
					break;
				}
			}
			$out[ $type ] = array(
				'id'        => 'preview-' . $type,
				'type'      => $type,
				'product'   => '',
				'message'   => $message,
				'label'     => Templates::label( $type ),
				'synthetic' => true,
			);
		}
		foreach ( $out as $type => $item ) {
			if ( is_array( $item ) ) {
				$out[ $type ]['preview'] = true;
				$out[ $type ]['id']      = 'preview-' . $type;
			}
		}
		return $out;
	}
}
