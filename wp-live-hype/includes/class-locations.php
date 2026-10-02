<?php
/**
 * Country-aware location catalogue with per-region and per-city weighting.
 *
 * Regions always come from WooCommerce; cities come from the bundled list in
 * includes/data/cities.php. A location is only ever picked from the country
 * passed in, so promotional events can never mention another country.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Locations.
 */
final class Locations {

	const OFF    = 0;
	const LOW    = 1;
	const NORMAL = 2;
	const HIGH   = 3;

	/**
	 * Raw city data memo.
	 *
	 * @var array|null
	 */
	private static $data = null;

	/**
	 * Selection multiplier for a weight level.
	 *
	 * @param int $level Level 0–4.
	 * @return int
	 */
	public static function multiplier( int $level ): int {
		$map = array( 0, 1, 3, 6, 10 );
		return $map[ max( 0, min( 4, $level ) ) ];
	}

	/**
	 * Countries that have a bundled city list.
	 *
	 * @return string[]
	 */
	public static function countries_with_cities(): array {
		return array_keys( self::data() );
	}

	/**
	 * Bundled data.
	 *
	 * @return array
	 */
	private static function data(): array {
		if ( null === self::$data ) {
			$data       = include __DIR__ . '/data/cities.php';
			self::$data = is_array( $data ) ? $data : array();
		}
		return self::$data;
	}

	/**
	 * Cities of a country, validated against WooCommerce regions.
	 *
	 * @param string $country Country code.
	 * @return array<string,array{name:string,region:string,region_name:string}> slug => city.
	 */
	public static function cities( string $country ): array {
		$country = strtoupper( $country );
		$data    = self::data();
		if ( empty( $data[ $country ] ) || ! Country::is_valid( $country ) ) {
			return array();
		}
		$states = Country::states( $country );
		$out    = array();
		foreach ( $data[ $country ] as $row ) {
			$name   = Security::clean_text( (string) $row[0], 60 );
			$region = strtoupper( (string) $row[1] );
			if ( '' === $name ) {
				continue;
			}
			// Unknown region codes are dropped rather than guessed.
			if ( '' !== $region && ! isset( $states[ $region ] ) ) {
				$region = '';
			}
			$out[ sanitize_title( $name ) ] = array(
				'name'        => $name,
				'region'      => $region,
				'region_name' => '' !== $region ? $states[ $region ] : '',
			);
		}
		return $out;
	}

	/**
	 * Weight level of a region (default Normal).
	 *
	 * @param string $country Country.
	 * @param string $code    Region code.
	 * @return int
	 */
	public static function region_weight( string $country, string $code ): int {
		$map = (array) Settings::get( 'region_weights' );
		$key = strtoupper( $country ) . ':' . strtoupper( $code );
		return isset( $map[ $key ] ) ? max( 0, min( 3, (int) $map[ $key ] ) ) : self::NORMAL;
	}

	/**
	 * Weight level of a city (default Normal). A disabled region disables its cities.
	 *
	 * @param string $country Country.
	 * @param string $slug    City slug.
	 * @param string $region  City region code.
	 * @return int
	 */
	public static function city_weight( string $country, string $slug, string $region ): int {
		if ( '' !== $region && self::OFF === self::region_weight( $country, $region ) ) {
			return self::OFF;
		}
		$map = (array) Settings::get( 'city_weights' );
		$key = strtoupper( $country ) . ':' . $slug;
		return isset( $map[ $key ] ) ? max( 0, min( 3, (int) $map[ $key ] ) ) : self::NORMAL;
	}

	/**
	 * Pick a weighted location inside one country.
	 *
	 * Falls back gracefully: city → region → country. When every configured
	 * region/city is disabled the country itself is used; nothing outside the
	 * country is ever returned.
	 *
	 * @param Rng    $rng     Random source.
	 * @param string $country Country code.
	 * @param string $level   Display level.
	 * @param array  $avoid   Location keys to avoid (recently used).
	 * @return array{key:string,country:string,region:string,city:string}|null
	 */
	public static function pick( Rng $rng, string $country, string $level, array $avoid = array() ): ?array {
		$country = strtoupper( $country );
		if ( ! Country::is_valid( $country ) ) {
			return null;
		}
		$wants_city = in_array( $level, array( 'city', 'city_region', 'city_country' ), true );

		if ( $wants_city ) {
			$options = array();
			foreach ( self::cities( $country ) as $slug => $city ) {
				$weight = self::multiplier( self::city_weight( $country, $slug, $city['region'] ) );
				if ( $weight > 0 && ! in_array( 'c:' . $slug, $avoid, true ) ) {
					$options[ $slug ] = $weight;
				}
			}
			$slug = $rng->weighted( $options );
			if ( null !== $slug ) {
				$city = self::cities( $country )[ $slug ];
				return array(
					'key'     => 'c:' . $slug,
					'country' => $country,
					'region'  => $city['region_name'],
					'city'    => $city['name'],
				);
			}
		}

		if ( 'country' !== $level ) {
			$options = array();
			foreach ( Country::states( $country ) as $code => $name ) {
				$weight = self::multiplier( self::region_weight( $country, (string) $code ) );
				if ( $weight > 0 && ! in_array( 'r:' . $code, $avoid, true ) ) {
					$options[ (string) $code ] = $weight;
				}
			}
			$code = $rng->weighted( $options );
			if ( null !== $code ) {
				return array(
					'key'     => 'r:' . $code,
					'country' => $country,
					'region'  => Country::states( $country )[ $code ],
					'city'    => '',
				);
			}
		}

		return array(
			'key'     => 'k:' . $country,
			'country' => $country,
			'region'  => '',
			'city'    => '',
		);
	}

	/**
	 * Human label for a picked location at a display level.
	 *
	 * @param array  $location Picked location.
	 * @param string $level    Display level.
	 * @return string
	 */
	public static function label( array $location, string $level ): string {
		$country = Country::name( $location['country'] );
		$region  = $location['region'];
		$city    = $location['city'];
		switch ( $level ) {
			case 'city_region':
				if ( '' !== $city && '' !== $region ) {
					/* translators: 1: city, 2: region/state/province. */
					return sprintf( _x( '%1$s, %2$s', 'city, region', 'wp-live-hype' ), $city, $region );
				}
				break;
			case 'city_country':
				if ( '' !== $city ) {
					/* translators: 1: city, 2: country. */
					return sprintf( _x( '%1$s, %2$s', 'city, country', 'wp-live-hype' ), $city, $country );
				}
				break;
			case 'city':
				if ( '' !== $city ) {
					return $city;
				}
				break;
		}
		if ( 'country' !== $level && '' !== $region ) {
			return $region;
		}
		if ( '' !== $city ) {
			return $city;
		}
		return Country::name_in_sentence( $location['country'] );
	}

	/**
	 * Country the promotional engine works in, from the country settings.
	 *
	 * @param Rng $rng Random source.
	 * @return string Empty when location promotions are unavailable.
	 */
	public static function engine_country( Rng $rng ): string {
		$allowed = Country::allowed_countries();
		if ( false === $allowed ) {
			return '';
		}
		if ( null === $allowed ) {
			return Country::base_country();
		}
		$options = array_fill_keys( $allowed, 1 );
		return (string) $rng->weighted( $options );
	}

	/**
	 * Whether the store actually ships to a country — location promotions
	 * ("Ships across Ontario") are only made when this is true.
	 *
	 * @param string $country Country code.
	 * @return bool
	 */
	public static function store_ships_to( string $country ): bool {
		if ( '' === $country || ! function_exists( 'WC' ) || ! WC()->countries ) {
			return false;
		}
		if ( 'disabled' === get_option( 'woocommerce_ship_to_countries' ) ) {
			return false;
		}
		return array_key_exists( strtoupper( $country ), (array) WC()->countries->get_shipping_countries() );
	}
}
