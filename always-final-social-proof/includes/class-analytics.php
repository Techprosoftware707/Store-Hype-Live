<?php
/**
 * Anonymous, aggregate, local analytics.
 *
 * Stores only daily counters: date, event (view/click/dismiss), notification
 * type and the public product ID. No IP addresses, user IDs, cookies, names,
 * emails, addresses or payment data are stored or processed.
 *
 * @package AlwaysFinal\SocialProof
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

/**
 * Analytics storage and reporting.
 */
final class Analytics {

	const EVENTS         = array( 'view', 'click', 'dismiss' );
	const MAX_PER_BATCH  = 25;
	const RATE_LIMIT_KEY = 'afsp_evt_rate_';

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'afsp_analytics';
	}

	/**
	 * Create or update the analytics table.
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
  event varchar(10) NOT NULL,
  ntype varchar(20) NOT NULL,
  product_id bigint(20) unsigned NOT NULL DEFAULT 0,
  hits bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (id),
  UNIQUE KEY stat (stat_date,event,ntype,product_id),
  KEY product_id (product_id)
) {$collate};"
		);
	}

	/**
	 * Whether the analytics table exists.
	 *
	 * @return bool
	 */
	public static function table_exists(): bool {
		global $wpdb;
		static $exists = array();
		$table = self::table();
		if ( ! isset( $exists[ $table ] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists[ $table ] = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		}
		return $exists[ $table ];
	}

	/**
	 * Validate and aggregate a batch of raw events from the browser.
	 *
	 * @param mixed $raw Decoded JSON payload.
	 * @return array<string,int> "event|type|product" => count.
	 */
	public static function validate_batch( $raw ): array {
		$events = is_array( $raw ) && isset( $raw['events'] ) && is_array( $raw['events'] ) ? $raw['events'] : array();
		$events = array_slice( $events, 0, self::MAX_PER_BATCH );
		$valid  = array();
		$known  = array();

		foreach ( $events as $event ) {
			if ( ! is_array( $event ) ) {
				continue;
			}
			$name    = isset( $event['e'] ) && is_string( $event['e'] ) ? $event['e'] : '';
			$type    = isset( $event['t'] ) && is_string( $event['t'] ) ? $event['t'] : '';
			$product = isset( $event['p'] ) && is_numeric( $event['p'] ) ? absint( $event['p'] ) : 0;

			if ( ! in_array( $name, self::EVENTS, true ) || ! in_array( $type, Notifications::TYPES, true ) ) {
				continue;
			}
			if ( $product > 0 ) {
				if ( ! isset( $known[ $product ] ) ) {
					$known[ $product ] = 'product' === get_post_type( $product ) && 'publish' === get_post_status( $product );
				}
				if ( ! $known[ $product ] ) {
					continue;
				}
			}
			$key           = $name . '|' . $type . '|' . $product;
			$valid[ $key ] = ( $valid[ $key ] ?? 0 ) + 1;
		}

		return $valid;
	}

	/**
	 * Soft global rate limit to keep a flood of fake events from inflating
	 * counters or loading the database. No per-visitor data is used.
	 *
	 * @param int $count Number of events in this request.
	 * @return bool True when accepted.
	 */
	public static function within_rate_limit( int $count ): bool {
		/**
		 * Maximum analytics events accepted per minute, site-wide.
		 *
		 * @param int $limit Default 1200.
		 */
		$limit = (int) apply_filters( 'afsp_analytics_rate_limit', 1200 );
		if ( $limit <= 0 ) {
			return true;
		}
		$key     = self::RATE_LIMIT_KEY . gmdate( 'YmdHi' );
		$current = (int) get_transient( $key );
		if ( $current + $count > $limit ) {
			return false;
		}
		set_transient( $key, $current + $count, 2 * MINUTE_IN_SECONDS );
		return true;
	}

	/**
	 * Store aggregated counters (one upsert for the whole batch).
	 *
	 * @param array<string,int> $aggregated From validate_batch().
	 * @return int Number of events stored.
	 */
	public static function record( array $aggregated ): int {
		global $wpdb;
		if ( empty( $aggregated ) ) {
			return 0;
		}

		$date   = wp_date( 'Y-m-d' );
		$rows   = array();
		$params = array();
		$total  = 0;
		foreach ( $aggregated as $key => $count ) {
			list( $event, $type, $product ) = explode( '|', (string) $key );
			$rows[]                         = '(%s,%s,%s,%d,%d)';
			array_push( $params, $date, $event, $type, (int) $product, (int) $count );
			$total += (int) $count;
		}

		$table = self::table();
		$sql   = "INSERT INTO {$table} (stat_date, event, ntype, product_id, hits) VALUES " . implode( ',', $rows ) . ' ON DUPLICATE KEY UPDATE hits = hits + VALUES(hits)';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Placeholders are built above; values are prepared.
		$result = $wpdb->query( $wpdb->prepare( $sql, $params ) );

		if ( false === $result ) {
			Logger::error( 'Analytics write failed.' );
			return 0;
		}
		return $total;
	}

	/**
	 * Start date (inclusive) for a reporting period.
	 *
	 * @param int $days Number of days.
	 * @return string Y-m-d.
	 */
	private static function since( int $days ): string {
		return wp_date( 'Y-m-d', time() - ( max( 1, $days ) - 1 ) * DAY_IN_SECONDS );
	}

	/**
	 * Totals per event for a period.
	 *
	 * @param int $days Days.
	 * @return array{view:int,click:int,dismiss:int,ctr:float}
	 */
	public static function totals( int $days ): array {
		global $wpdb;
		$out = array(
			'view'    => 0,
			'click'   => 0,
			'dismiss' => 0,
			'ctr'     => 0.0,
		);
		if ( ! self::table_exists() ) {
			return $out;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT event, SUM(hits) AS total FROM {$table} WHERE stat_date >= %s GROUP BY event", self::since( $days ) ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( isset( $out[ $row['event'] ] ) ) {
				$out[ $row['event'] ] = (int) $row['total'];
			}
		}
		$out['ctr'] = $out['view'] > 0 ? round( $out['click'] / $out['view'] * 100, 2 ) : 0.0;
		return $out;
	}

	/**
	 * Per notification type statistics.
	 *
	 * @param int $days Days.
	 * @return array<string,array{view:int,click:int,dismiss:int}>
	 */
	public static function by_type( int $days ): array {
		global $wpdb;
		$out = array();
		foreach ( Notifications::TYPES as $type ) {
			$out[ $type ] = array(
				'view'    => 0,
				'click'   => 0,
				'dismiss' => 0,
			);
		}
		if ( ! self::table_exists() ) {
			return $out;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ntype, event, SUM(hits) AS total FROM {$table} WHERE stat_date >= %s GROUP BY ntype, event", self::since( $days ) ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( isset( $out[ $row['ntype'] ][ $row['event'] ] ) ) {
				$out[ $row['ntype'] ][ $row['event'] ] = (int) $row['total'];
			}
		}
		return $out;
	}

	/**
	 * Most clicked products.
	 *
	 * @param int $days  Days.
	 * @param int $limit Limit.
	 * @return array<int,array{product_id:int,clicks:int,views:int}>
	 */
	public static function top_products( int $days, int $limit = 10 ): array {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return array();
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, SUM(CASE WHEN event = 'click' THEN hits ELSE 0 END) AS clicks, SUM(CASE WHEN event = 'view' THEN hits ELSE 0 END) AS views FROM {$table} WHERE stat_date >= %s AND product_id > 0 GROUP BY product_id HAVING clicks > 0 OR views > 0 ORDER BY clicks DESC, views DESC LIMIT %d", self::since( $days ), $limit ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array(
				'product_id' => (int) $row['product_id'],
				'clicks'     => (int) $row['clicks'],
				'views'      => (int) $row['views'],
			);
		}
		return $out;
	}

	/**
	 * Daily views and clicks for charting.
	 *
	 * @param int $days Days.
	 * @return array<string,array{view:int,click:int}> Y-m-d => counts, oldest first.
	 */
	public static function daily( int $days ): array {
		global $wpdb;
		$out = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$out[ wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) ] = array(
				'view'  => 0,
				'click' => 0,
			);
		}
		if ( ! self::table_exists() ) {
			return $out;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT stat_date, event, SUM(hits) AS total FROM {$table} WHERE stat_date >= %s AND event IN ('view','click') GROUP BY stat_date, event", self::since( $days ) ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( isset( $out[ $row['stat_date'] ][ $row['event'] ] ) ) {
				$out[ $row['stat_date'] ][ $row['event'] ] = (int) $row['total'];
			}
		}
		return $out;
	}

	/**
	 * Delete rows older than the retention period (daily cron).
	 */
	public static function purge_old(): void {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return;
		}
		$days  = (int) Settings::get( 'analytics_retention' );
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE stat_date < %s", self::since( $days ) ) );
		Logger::debug( 'Analytics retention purge.', array( 'deleted_rows' => (int) $deleted ) );
	}

	/**
	 * Delete all analytics data.
	 */
	public static function reset(): void {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return;
		}
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}
}
