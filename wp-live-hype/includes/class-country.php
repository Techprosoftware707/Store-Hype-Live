<?php
/**
 * Country targeting and location normalization.
 *
 * Country and region names come from WooCommerce's own data (WC_Countries),
 * so the list is complete, translated and maintained upstream.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Country utilities.
 */
final class Country {

	/**
	 * Memo of clean country names.
	 *
	 * @var array|null
	 */
	private static $countries = null;

	/**
	 * Memo of state lists per country.
	 *
	 * @var array
	 */
	private static $states = array();

	/**
	 * All countries known to WooCommerce, as code => display name.
	 *
	 * @return array<string,string>
	 */
	public static function countries(): array {
		if ( null !== self::$countries ) {
			return self::$countries;
		}
		self::$countries = array();
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return self::$countries;
		}
		foreach ( (array) WC()->countries->get_countries() as $code => $name ) {
			self::$countries[ (string) $code ] = self::clean_country_name( (string) $code, (string) $name );
		}
		return self::$countries;
	}

	/**
	 * Whether a country code is known to WooCommerce.
	 *
	 * @param string $code ISO 3166-1 alpha-2 code.
	 * @return bool
	 */
	public static function is_valid( string $code ): bool {
		return '' !== $code && isset( self::countries()[ strtoupper( $code ) ] );
	}

	/**
	 * Display name for a country code.
	 *
	 * @param string $code Country code.
	 * @return string Empty string for unknown codes.
	 */
	public static function name( string $code ): string {
		$countries = self::countries();
		$code      = strtoupper( $code );
		return $countries[ $code ] ?? '';
	}

	/**
	 * Country name suitable for the middle of a sentence ("the United States").
	 *
	 * @param string $code Country code.
	 * @return string
	 */
	public static function name_in_sentence( string $code ): string {
		$name = self::name( $code );
		if ( '' === $name || ! function_exists( 'WC' ) || ! WC()->countries ) {
			return $name;
		}
		$prefix = (string) WC()->countries->estimated_for_prefix( strtoupper( $code ) );
		return Security::clean_text( $prefix . $name, 120 );
	}

	/**
	 * Store base country configured in WooCommerce.
	 *
	 * @return string
	 */
	public static function base_country(): string {
		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return '';
		}
		$code = (string) WC()->countries->get_base_country();
		return self::is_valid( $code ) ? $code : '';
	}

	/**
	 * Predefined regions (states/provinces) for a country.
	 *
	 * @param string $code Country code.
	 * @return array<string,string> region code => display name. Empty when the
	 *                              country has no predefined list.
	 */
	public static function states( string $code ): array {
		$code = strtoupper( $code );
		if ( isset( self::$states[ $code ] ) ) {
			return self::$states[ $code ];
		}
		$states = array();
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$raw = WC()->countries->get_states( $code );
			if ( is_array( $raw ) ) {
				foreach ( $raw as $state_code => $name ) {
					$states[ strtoupper( (string) $state_code ) ] = self::region_display_name( $code, strtoupper( (string) $state_code ), Security::clean_text( (string) $name, 80 ) );
				}
			}
		}
		self::$states[ $code ] = $states;
		return $states;
	}

	/**
	 * Normalize a WooCommerce state value into a readable region name.
	 *
	 * Handles region codes ("ON" → "Ontario"), ISO 3166-2 style codes
	 * ("CA-ON"), and values stored as full names. For countries without a
	 * predefined list the free-text value is accepted only if it looks like a
	 * place name. Unknown values return an empty string — a region is never
	 * guessed.
	 *
	 * @param string $country Country code.
	 * @param string $state   Raw state value from the order.
	 * @return string
	 */
	public static function region_name( string $country, string $state ): string {
		$state = Security::clean_text( $state, 80 );
		if ( '' === $state || '' === $country ) {
			return '';
		}

		$states = self::states( $country );
		if ( ! empty( $states ) ) {
			$upper = strtoupper( $state );
			if ( isset( $states[ $upper ] ) ) {
				return $states[ $upper ];
			}
			// Some countries key regions with an ISO 3166-2 prefix (e.g. DE-BE).
			$prefixed = strtoupper( $country ) . '-' . $upper;
			if ( isset( $states[ $prefixed ] ) ) {
				return $states[ $prefixed ];
			}
			if ( preg_match( '/^([A-Z]{2})[\-_ ]([A-Z0-9]{1,3})$/', $upper, $m ) && strtoupper( $country ) === $m[1] && isset( $states[ $m[2] ] ) ) {
				return $states[ $m[2] ];
			}
			foreach ( $states as $name ) {
				if ( 0 === strcasecmp( remove_accents( $name ), remove_accents( $state ) ) ) {
					return $name;
				}
			}
			return '';
		}

		// Country uses free-text regions (e.g. United Kingdom counties).
		return Security::clean_place_name( $state );
	}

	/**
	 * Preferred display name overrides for regions whose WooCommerce label is
	 * not the name customers expect to read.
	 *
	 * @param string $country Country code.
	 * @param string $code    Region code.
	 * @param string $name    WooCommerce name.
	 * @return string
	 */
	private static function region_display_name( string $country, string $code, string $name ): string {
		if ( 'CA' === $country && 'YT' === $code ) {
			$name = __( 'Yukon', 'wp-live-hype' );
		}
		/**
		 * Filter the readable name of a region.
		 *
		 * @param string $name    Region name.
		 * @param string $country Country code.
		 * @param string $code    Region code.
		 */
		return (string) apply_filters( 'wplh_region_name', $name, $country, $code );
	}

	/**
	 * Strip redundant abbreviations from WooCommerce country names.
	 *
	 * WooCommerce labels some countries as "United States (US)" or
	 * "United Kingdom (UK)" for disambiguation in dropdowns; in a sentence the
	 * suffix reads poorly.
	 *
	 * @param string $code Country code.
	 * @param string $name WooCommerce name.
	 * @return string
	 */
	private static function clean_country_name( string $code, string $name ): string {
		$name = Security::clean_text( $name, 120 );
		if ( in_array( $code, array( 'US', 'GB' ), true ) ) {
			$name = trim( (string) preg_replace( '/\s*\((?:US|UK)\)$/', '', $name ) );
		}
		return $name;
	}

	/**
	 * Geographic scope that order data must satisfy, resolved from settings.
	 *
	 * Returns:
	 *  - `null`  when every country is allowed (filtering OFF + "all orders"),
	 *  - array of country codes that orders must match,
	 *  - `false` when purchase data is disabled (e.g. filtering ON without a
	 *    configured country, or the admin requires another configuration).
	 *
	 * This is the single place the server-side country rule is decided. There
	 * is deliberately no fallback to other countries.
	 *
	 * @return array|null|false
	 */
	public static function allowed_countries() {
		if ( Settings::get( 'country_filter' ) ) {
			$target = (string) Settings::get( 'target_country' );
			return self::is_valid( $target ) ? array( $target ) : false;
		}

		switch ( Settings::get( 'unfiltered_scope' ) ) {
			case 'all':
				return null;
			case 'base':
				$base = self::base_country();
				return '' !== $base ? array( $base ) : false;
			case 'countries':
				$list = array_values( array_filter( (array) Settings::get( 'allowed_countries' ), array( __CLASS__, 'is_valid' ) ) );
				return ! empty( $list ) ? $list : false;
			case 'none':
			default:
				return false;
		}
	}

	/**
	 * Human readable description of the current geographic scope.
	 *
	 * @return string
	 */
	public static function scope_description(): string {
		$allowed = self::allowed_countries();
		if ( false === $allowed ) {
			return __( 'None — purchase notifications are inactive until a geographic configuration is completed.', 'wp-live-hype' );
		}
		if ( null === $allowed ) {
			return __( 'All countries', 'wp-live-hype' );
		}
		$names = array_map( array( __CLASS__, 'name' ), $allowed );
		/* translators: %s: comma separated list of country names. */
		return sprintf( __( '%s only', 'wp-live-hype' ), implode( ', ', $names ) );
	}

	/**
	 * Build the public location fields for a purchase record, honouring the
	 * configured location level and falling back gracefully:
	 * City + region → Region → Country → No location. Nothing is invented.
	 *
	 * Only the fields required by the configured level are returned, so a more
	 * precise location never reaches the browser when a coarser one is chosen.
	 *
	 * @param string $country Country code.
	 * @param string $region  Region name.
	 * @param string $city    City.
	 * @param string $level   Location level setting.
	 * @return array{location:string,country:string,region:string,city:string}
	 */
	public static function public_location( string $country, string $region, string $city, string $level ): array {
		$out = array(
			'location' => '',
			'country'  => '',
			'region'   => '',
			'city'     => '',
		);

		if ( 'none' === $level || ! self::is_valid( $country ) ) {
			return $out;
		}

		$out['country'] = self::name( $country );

		if ( in_array( $level, array( 'region', 'city', 'city_region' ), true ) ) {
			$out['region'] = $region;
		}
		if ( in_array( $level, array( 'city', 'city_region' ), true ) ) {
			$out['city'] = $city;
		}

		switch ( $level ) {
			case 'city_region':
				if ( '' !== $out['city'] && '' !== $out['region'] && $out['city'] !== $out['region'] ) {
					/* translators: 1: city, 2: region/state/province. */
					$out['location'] = sprintf( _x( '%1$s, %2$s', 'city, region', 'wp-live-hype' ), $out['city'], $out['region'] );
				} elseif ( '' !== $out['city'] ) {
					$out['location'] = $out['city'];
				}
				break;
			case 'city':
				$out['location'] = $out['city'];
				break;
		}

		if ( '' === $out['location'] && '' !== $out['region'] ) {
			$out['location'] = $out['region'];
		}
		if ( '' === $out['location'] ) {
			$out['location'] = self::name_in_sentence( $country );
		}

		return $out;
	}

	/**
	 * Sample location used by admin previews only. Clearly labelled as a
	 * preview in the UI; never used for real notifications.
	 *
	 * @param string $code Country code.
	 * @return array{region:string,city:string}
	 */
	public static function preview_location( string $code ): array {
		$code    = strtoupper( $code );
		$samples = array(
			'CA' => array( 'ON', __( 'Toronto', 'wp-live-hype' ) ),
			'US' => array( 'CA', __( 'Los Angeles', 'wp-live-hype' ) ),
			'GB' => array( '', __( 'London', 'wp-live-hype' ) ),
			'AU' => array( 'NSW', __( 'Sydney', 'wp-live-hype' ) ),
			'NZ' => array( 'AUK', __( 'Auckland', 'wp-live-hype' ) ),
			'DE' => array( 'DE-BE', __( 'Berlin', 'wp-live-hype' ) ),
			'FR' => array( '', __( 'Paris', 'wp-live-hype' ) ),
			'IE' => array( 'D', __( 'Dublin', 'wp-live-hype' ) ),
		);

		$states = self::states( $code );
		$region = '';
		$city   = '';
		if ( isset( $samples[ $code ] ) ) {
			$region = '' !== $samples[ $code ][0] && isset( $states[ $samples[ $code ][0] ] ) ? $states[ $samples[ $code ][0] ] : '';
			$city   = $samples[ $code ][1];
		} elseif ( ! empty( $states ) ) {
			$region = (string) reset( $states );
		}

		return array(
			'region' => $region,
			'city'   => $city,
		);
	}
}
