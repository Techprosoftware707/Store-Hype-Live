<?php
/**
 * Optional debug logging through the WooCommerce logger.
 *
 * Only operational facts are logged (counts, configuration codes, timings,
 * cache state). Customer data is never passed to the logger, and messages are
 * additionally scrubbed of anything resembling an email address or phone
 * number as a defence-in-depth measure.
 *
 * @package AlwaysFinal\SocialProof
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

/**
 * Debug logger.
 */
final class Logger {

	const SOURCE = 'always-final-social-proof';

	/**
	 * Whether debug logging is enabled.
	 *
	 * @return bool
	 */
	public static function enabled(): bool {
		return (bool) Settings::get( 'debug' );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string $message Message.
	 * @param array  $context Scalar context values only.
	 */
	public static function debug( string $message, array $context = array() ): void {
		self::write( 'debug', $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string $message Message.
	 * @param array  $context Scalar context values only.
	 */
	public static function error( string $message, array $context = array() ): void {
		self::write( 'error', $message, $context );
	}

	/**
	 * Write a log entry.
	 *
	 * @param string $level   Level.
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	private static function write( string $level, string $message, array $context ): void {
		if ( ! self::enabled() ) {
			return;
		}

		$safe = array();
		foreach ( $context as $key => $value ) {
			if ( is_bool( $value ) ) {
				$safe[ (string) $key ] = $value ? 'yes' : 'no';
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$safe[ (string) $key ] = $value;
			} elseif ( is_string( $value ) ) {
				$safe[ (string) $key ] = self::scrub( Security::substr( $value, 0, 200 ) );
			} elseif ( is_array( $value ) ) {
				$safe[ (string) $key ] = self::scrub( implode( ',', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) );
			}
		}

		$line = self::scrub( $message );
		if ( $safe ) {
			$line .= ' ' . wp_json_encode( $safe );
		}

		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $line, array( 'source' => self::SOURCE ) );
		} elseif ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// WooCommerce is unavailable (e.g. deactivated): fall back to the PHP error log.
			error_log( '[' . self::SOURCE . '] ' . strtoupper( $level ) . ' ' . $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Redact anything that looks like personal data.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function scrub( string $text ): string {
		$text = (string) preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[redacted-email]', $text );
		$text = (string) preg_replace( '/\+?\d[\d\s().\-]{6,}\d/', '[redacted-number]', $text );
		return $text;
	}
}
