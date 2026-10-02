<?php
/**
 * Cached, sanitized notification dataset.
 *
 * Orders are never queried on a page load. A sanitized dataset is built at
 * most once per refresh interval (by WP-Cron, or lazily under a lock when the
 * cache is cold) and stored as a transient — which transparently uses Redis /
 * Memcached when a persistent object cache is installed.
 *
 * @package AlwaysFinal\SocialProof
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

/**
 * Dataset cache.
 */
final class Cache {

	const VERSION_OPTION = 'afsp_cache_version';
	const LOCK_NAME      = 'afsp_build_lock';
	const CRON_HOOK      = 'afsp_refresh_dataset';
	const SOON_HOOK      = 'afsp_refresh_dataset_soon';
	const GROUP          = 'afsp';
	const LOCK_TIMEOUT   = 120;
	const DATASET_FORMAT = 2;

	/**
	 * Request memo.
	 *
	 * @var array|null
	 */
	private static $memo = null;

	/**
	 * Transient key for the current cache version.
	 *
	 * @return string
	 */
	public static function key(): string {
		return 'afsp_ds_' . (int) get_option( self::VERSION_OPTION, 1 );
	}

	/**
	 * Refresh interval in seconds.
	 *
	 * @return int
	 */
	public static function ttl(): int {
		return max( 1, (int) Settings::get( 'cache_ttl' ) ) * MINUTE_IN_SECONDS;
	}

	/**
	 * An empty dataset.
	 *
	 * @return array
	 */
	public static function empty_dataset(): array {
		return array(
			'format'    => self::DATASET_FORMAT,
			'generated' => 0,
			'purchases' => array(),
			'sales'     => array(),
			'popular'   => array(
				'mode' => '',
				'ids'  => array(),
			),
			'products'  => array(),
			'stats'     => array(),
		);
	}

	/**
	 * Get the dataset, rebuilding it if it is missing or stale.
	 *
	 * Stale data keeps being served while another process rebuilds, so a cold
	 * cache never causes a stampede of concurrent order queries.
	 *
	 * @param bool $allow_build Whether this request may rebuild the cache.
	 * @return array
	 */
	public static function get_dataset( bool $allow_build = true ): array {
		if ( null !== self::$memo ) {
			return self::$memo;
		}

		$data  = get_transient( self::key() );
		$valid = is_array( $data ) && isset( $data['format'], $data['generated'] ) && self::DATASET_FORMAT === (int) $data['format'];
		if ( $valid && (int) $data['generated'] + self::ttl() > time() ) {
			Logger::debug( 'Dataset cache hit.' );
			self::$memo = $data;
			return $data;
		}

		if ( $allow_build && self::acquire_lock() ) {
			try {
				Logger::debug( $valid ? 'Dataset cache stale; rebuilding.' : 'Dataset cache miss; building.' );
				$fresh = self::build();
				self::store( $fresh );
			} finally {
				self::release_lock();
			}
			self::$memo = $fresh;
			return $fresh;
		}

		Logger::debug( $valid ? 'Serving stale dataset while a rebuild is in progress.' : 'Dataset unavailable while a rebuild is in progress.' );
		self::$memo = $valid ? $data : self::empty_dataset();
		return self::$memo;
	}

	/**
	 * Cron callback: rebuild the dataset proactively.
	 */
	public static function refresh(): void {
		if ( ! Settings::get( 'enabled' ) ) {
			return;
		}
		if ( ! self::acquire_lock() ) {
			Logger::debug( 'Scheduled refresh skipped: rebuild already running.' );
			return;
		}
		try {
			$data = self::build();
			self::store( $data );
			self::$memo = $data;
		} finally {
			self::release_lock();
		}
	}

	/**
	 * Force a rebuild now (admin "Refresh data" action).
	 *
	 * @return array
	 */
	public static function rebuild_now(): array {
		self::release_lock();
		self::$memo = null;
		Products::flush();
		$data = self::build();
		self::store( $data );
		self::$memo = $data;
		return $data;
	}

	/**
	 * Persist a dataset. The transient outlives the refresh interval so that
	 * slightly stale data can be served while a rebuild is in progress.
	 *
	 * @param array $data Dataset.
	 */
	private static function store( array $data ): void {
		set_transient( self::key(), $data, max( 10 * MINUTE_IN_SECONDS, self::ttl() * 6 ) );
	}

	/**
	 * Invalidate all cached data (settings changed, uninstall, etc.).
	 */
	public static function invalidate(): void {
		delete_transient( self::key() );
		$version = (int) get_option( self::VERSION_OPTION, 1 ) + 1;
		update_option( self::VERSION_OPTION, $version, true );
		self::$memo = null;
		Products::flush();
		Logger::debug( 'Dataset cache invalidated.', array( 'version' => $version ) );
	}

	/**
	 * Ask WP-Cron for a refresh shortly (e.g. after an order is cancelled or
	 * refunded, so it stops being shown promptly). De-duplicated.
	 */
	public static function schedule_soon(): void {
		if ( ! wp_next_scheduled( self::SOON_HOOK ) ) {
			wp_schedule_single_event( time() + 30, self::SOON_HOOK );
		}
	}

	/**
	 * Soon-hook callback: drop the cached data and rebuild.
	 */
	public static function refresh_soon(): void {
		delete_transient( self::key() );
		self::$memo = null;
		self::refresh();
	}

	/**
	 * Build the dataset from real store data.
	 *
	 * @return array
	 */
	public static function build(): array {
		$started           = microtime( true );
		$data              = self::empty_dataset();
		$data['generated'] = time();

		try {
			$orders            = Orders::collect();
			$data['purchases'] = $orders['purchases'];
			$data['stats']     = $orders['stats'];

			if ( Settings::get( 'type_sale' ) ) {
				$data['sales'] = Products::sales( 30 );
			}

			if ( Settings::get( 'type_popular' ) ) {
				$source = (string) Settings::get( 'popular_source' );
				$min    = (int) Settings::get( 'popular_min_sales' );
				$limit  = (int) Settings::get( 'popular_count' );
				if ( 'lifetime' === $source ) {
					// Store-wide counter: only valid when no geographic restriction applies.
					if ( null === Country::allowed_countries() ) {
						$data['popular'] = array(
							'mode' => 'lifetime',
							'ids'  => Products::lifetime_bestsellers( $min, $limit ),
						);
					} else {
						$data['stats']['popular_unavailable'] = 'geo';
					}
				} else {
					$data['popular'] = array(
						'mode' => $source,
						'ids'  => Products::popular_from_counts( $orders['counts'], $min, $limit ),
					);
				}
			}

			// Public product data for everything referenced by the dataset.
			$ids = array_keys( $data['sales'] );
			$ids = array_merge( $ids, $data['popular']['ids'] );
			foreach ( $data['purchases'] as $purchase ) {
				$ids = array_merge( $ids, $purchase['products'] );
			}
			foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
				$public = Products::public_data( $id );
				if ( null !== $public ) {
					$data['products'][ $id ] = $public;
				}
			}
		} catch ( \Throwable $e ) {
			$data                   = self::empty_dataset();
			$data['generated']      = time();
			$data['stats']['error'] = 'build_failed';
			Logger::error( 'Dataset build failed: ' . get_class( $e ) . ' — ' . $e->getMessage() );
		}

		$data['stats']['sales']       = count( $data['sales'] );
		$data['stats']['popular']     = count( $data['popular']['ids'] );
		$data['stats']['purchases']   = count( $data['purchases'] );
		$data['stats']['duration_ms'] = (int) round( ( microtime( true ) - $started ) * 1000 );

		Logger::debug(
			'Dataset built.',
			array(
				'purchases'   => $data['stats']['purchases'],
				'sales'       => $data['stats']['sales'],
				'popular'     => $data['stats']['popular'],
				'products'    => count( $data['products'] ),
				'duration_ms' => $data['stats']['duration_ms'],
			)
		);

		return $data;
	}

	/**
	 * Acquire the rebuild lock atomically.
	 *
	 * Uses an atomic object-cache add() when a persistent object cache is
	 * available; otherwise an INSERT IGNORE on the options table (add_option()
	 * is not atomic). Stale locks expire after LOCK_TIMEOUT seconds.
	 *
	 * @return bool
	 */
	private static function acquire_lock(): bool {
		global $wpdb;

		if ( wp_using_ext_object_cache() ) {
			return (bool) wp_cache_add( self::LOCK_NAME, time(), self::GROUP, self::LOCK_TIMEOUT );
		}

		$now = time();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock; must bypass caches.
		$inserted = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", self::LOCK_NAME, (string) $now ) );
		if ( 1 === (int) $inserted ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock; must bypass caches.
		$held_since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::LOCK_NAME ) );
		if ( $held_since > 0 && $held_since < $now - self::LOCK_TIMEOUT ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic lock takeover.
			$taken = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) $now, self::LOCK_NAME, (string) $held_since ) );
			return 1 === (int) $taken;
		}
		return false;
	}

	/**
	 * Release the rebuild lock.
	 */
	private static function release_lock(): void {
		global $wpdb;
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( self::LOCK_NAME, self::GROUP );
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Lock row is never read through the options API.
		$wpdb->delete( $wpdb->options, array( 'option_name' => self::LOCK_NAME ) );
	}

	/**
	 * Register custom cron intervals.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ): array {
		$schedules = is_array( $schedules ) ? $schedules : array();
		foreach ( array( 1, 5, 10, 30 ) as $minutes ) {
			$schedules[ 'afsp_' . $minutes . 'min' ] = array(
				'interval' => $minutes * MINUTE_IN_SECONDS,
				/* translators: %d: number of minutes. */
				'display'  => sprintf( _n( 'Every %d minute (ALWAYS FINAL Social Proof)', 'Every %d minutes (ALWAYS FINAL Social Proof)', $minutes, 'always-final-social-proof' ), $minutes ),
			);
		}
		return $schedules;
	}

	/**
	 * Ensure the recurring refresh is scheduled with the configured interval.
	 *
	 * @param bool $force Reschedule even if an event exists.
	 */
	public static function ensure_schedule( bool $force = false ): void {
		$recurrence = 'afsp_' . (int) Settings::get( 'cache_ttl' ) . 'min';
		$next       = wp_next_scheduled( self::CRON_HOOK );
		if ( $next && ! $force && wp_get_schedule( self::CRON_HOOK ) === $recurrence ) {
			return;
		}
		wp_clear_scheduled_hook( self::CRON_HOOK );
		wp_schedule_event( time() + MINUTE_IN_SECONDS, $recurrence, self::CRON_HOOK );
	}

	/**
	 * Order status transition handler: when an order stops qualifying
	 * (cancelled, refunded, failed…), refresh soon so it disappears promptly.
	 *
	 * @param int    $order_id Order ID (unused beyond the signature).
	 * @param string $from     Previous status.
	 * @param string $to       New status.
	 */
	public static function on_order_status_changed( $order_id, $from, $to ): void {
		unset( $order_id );
		$qualifying = Settings::qualifying_statuses();
		if ( in_array( (string) $from, $qualifying, true ) && ! in_array( (string) $to, $qualifying, true ) ) {
			self::schedule_soon();
		}
	}
}
