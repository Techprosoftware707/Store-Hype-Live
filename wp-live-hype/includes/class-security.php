<?php
/**
 * Security helpers: capability checks, sanitizers and privacy filters.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Security utilities.
 */
final class Security {

	/**
	 * Capability required to manage the plugin.
	 *
	 * @return string
	 */
	public static function capability(): string {
		/**
		 * Filter the capability required to manage WP Live Hype.
		 *
		 * @param string $capability Default `manage_woocommerce`.
		 */
		$cap = apply_filters( 'wplh_capability', 'manage_woocommerce' );
		return is_string( $cap ) && '' !== $cap ? $cap : 'manage_woocommerce';
	}

	/**
	 * Whether the current user may manage the plugin.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( self::capability() );
	}

	/**
	 * Convert any string to safe, single-line plain text.
	 *
	 * HTML is stripped, entities are decoded (output is escaped at render time or
	 * inserted with textContent on the frontend) and whitespace is collapsed.
	 *
	 * @param string $value Raw value.
	 * @param int    $max   Maximum length in characters.
	 * @return string
	 */
	public static function clean_text( string $value, int $max = 200 ): string {
		$value = wp_strip_all_tags( $value, true );
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = wp_strip_all_tags( $value, true ); // Entities may have hidden markup.
		$value = (string) preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );
		$value = trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
		if ( self::strlen( $value ) > $max ) {
			$value = rtrim( self::substr( $value, 0, $max ) );
		}
		return $value;
	}

	/**
	 * Sanitize a free-text location component (city, or region for countries
	 * without a predefined region list).
	 *
	 * Customer-entered values can contain anything — street addresses, postal
	 * codes, names, phone numbers. Only values that look like a place name are
	 * accepted; anything else is discarded so it can never reach the browser.
	 *
	 * @param string $value Raw value.
	 * @return string Clean place name or empty string.
	 */
	public static function clean_place_name( string $value ): string {
		$value = self::clean_text( $value, 80 );
		if ( '' === $value ) {
			return '';
		}

		$length = self::strlen( $value );
		if ( $length < 2 || $length > 40 ) {
			return '';
		}

		// Letters (any script), combining marks, spaces, apostrophes, periods and hyphens only.
		// Digits are rejected outright: they indicate postal codes, street numbers or phone numbers.
		if ( ! preg_match( "/^[\p{L}\p{M}][\p{L}\p{M} '’.\-]*$/u", $value ) ) {
			return '';
		}

		// Normalize SHOUTING or all-lowercase input to title case.
		if ( function_exists( 'mb_strtoupper' ) && function_exists( 'mb_convert_case' ) ) {
			if ( mb_strtoupper( $value, 'UTF-8' ) === $value || mb_strtolower( $value, 'UTF-8' ) === $value ) {
				$value = mb_convert_case( mb_strtolower( $value, 'UTF-8' ), MB_CASE_TITLE, 'UTF-8' );
			}
		}

		return $value;
	}

	/**
	 * Keyed, non-reversible identifier. Used so that internal references (such as
	 * order IDs) are never exposed, while still allowing de-duplication.
	 *
	 * @param string $data Data to hash.
	 * @return string 16 hex characters.
	 */
	public static function opaque_id( string $data ): string {
		return substr( hash_hmac( 'sha256', $data, wp_salt( 'nonce' ) . 'wplh' ), 0, 16 );
	}

	/**
	 * Sanitize a newline separated list of URL patterns.
	 *
	 * @param string $raw Raw textarea value.
	 * @return string Clean newline separated list.
	 */
	public static function sanitize_url_patterns( string $raw ): string {
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$clean = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( wp_strip_all_tags( (string) $line ) );
			if ( '' === $line ) {
				continue;
			}
			// Accept full URLs by reducing them to path + query.
			if ( preg_match( '#^https?://#i', $line ) ) {
				$parts = wp_parse_url( $line );
				$line  = ( $parts['path'] ?? '/' ) . ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' );
			}
			$line = (string) preg_replace( '#[^A-Za-z0-9\-._~/*?=&%+:@!$,;]#', '', $line );
			if ( '' === $line ) {
				continue;
			}
			$clean[] = self::substr( $line, 0, 200 );
			if ( count( $clean ) >= 100 ) {
				break;
			}
		}
		return implode( "\n", array_unique( $clean ) );
	}

	/**
	 * Whether a request path matches one of the configured patterns.
	 *
	 * Patterns match against the path (and query string when the pattern has
	 * one). `*` is a wildcard. A pattern without a wildcard matches the exact
	 * path, with or without a trailing slash.
	 *
	 * @param string $request_uri Request URI (path + optional query).
	 * @param string $patterns    Newline separated patterns.
	 * @return bool
	 */
	public static function uri_matches( string $request_uri, string $patterns ): bool {
		if ( '' === trim( $patterns ) ) {
			return false;
		}
		$parts = wp_parse_url( $request_uri );
		$path  = isset( $parts['path'] ) ? rawurldecode( (string) $parts['path'] ) : '/';
		$query = isset( $parts['query'] ) ? (string) $parts['query'] : '';

		foreach ( explode( "\n", $patterns ) as $pattern ) {
			$pattern = trim( $pattern );
			if ( '' === $pattern ) {
				continue;
			}
			$subject = false !== strpos( $pattern, '?' ) ? $path . ( '' !== $query ? '?' . $query : '' ) : $path;
			$regex   = '#^' . str_replace( '\*', '.*', preg_quote( $pattern, '#' ) ) . '$#i';
			if ( preg_match( $regex, $subject ) ) {
				return true;
			}
			// Tolerate trailing-slash differences for exact paths.
			if ( false === strpos( $pattern, '*' ) && untrailingslashit( $subject ) === untrailingslashit( $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Multibyte-safe strlen.
	 *
	 * @param string $value Value.
	 * @return int
	 */
	public static function strlen( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/**
	 * Multibyte-safe substr.
	 *
	 * @param string $value  Value.
	 * @param int    $start  Start.
	 * @param int    $length Length.
	 * @return string
	 */
	public static function substr( string $value, int $start, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, $start, $length, 'UTF-8' ) : substr( $value, $start, $length );
	}
}
