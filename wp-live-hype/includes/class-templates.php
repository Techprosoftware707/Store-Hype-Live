<?php
/**
 * Notification message templates: defaults, validation and rendering.
 *
 * A template is only used when every token it contains has real data. For
 * example "A customer in {city} recently ordered {product}" is skipped when
 * the order has no usable city, and the next template is tried. Nothing is
 * ever filled with invented values.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Template utilities.
 */
final class Templates {

	const MAX_LINES  = 10;
	const MAX_LENGTH = 160;

	/**
	 * Tokens allowed per template type.
	 *
	 * @return array<string,string[]>
	 */
	public static function allowed_tokens(): array {
		return array(
			'purchase'   => array( 'product', 'location', 'country', 'region', 'province', 'state', 'city', 'time_ago' ),
			'sale'       => array( 'product', 'sale_price', 'regular_price', 'discount_percent' ),
			'popular'    => array( 'product', 'period' ),
			'bestseller' => array( 'product' ),
			'featured'   => array( 'product' ),
			'explore'    => array( 'product' ),
			'location'   => array( 'product', 'location', 'country', 'region', 'province', 'state', 'city' ),
		);
	}

	/**
	 * Default templates (translated).
	 *
	 * @param string $type Template type.
	 * @return string[]
	 */
	public static function default_templates( string $type ): array {
		switch ( $type ) {
			case 'purchase':
				return array(
					__( 'Someone in {location} recently purchased {product}', 'wp-live-hype' ),
					__( 'A customer in {city} recently ordered {product}', 'wp-live-hype' ),
					__( 'Recently purchased: {product}', 'wp-live-hype' ),
				);
			case 'sale':
				return array(
					__( '{product} is currently on sale', 'wp-live-hype' ),
					__( 'Sale available on {product}', 'wp-live-hype' ),
					__( '{product} — now {sale_price}', 'wp-live-hype' ),
				);
			case 'popular':
				return array(
					__( '{product} has been popular recently', 'wp-live-hype' ),
				);
			case 'bestseller':
				return array(
					__( '{product} is one of our best sellers', 'wp-live-hype' ),
				);
			case 'featured':
				return array(
					__( 'Featured — {product}', 'wp-live-hype' ),
					__( 'Spotlight: {product}', 'wp-live-hype' ),
					__( '{product} — featured in our store', 'wp-live-hype' ),
				);
			case 'explore':
				return array(
					__( 'Explore {product}', 'wp-live-hype' ),
					__( 'Take a closer look at {product}', 'wp-live-hype' ),
					__( 'Discover {product}', 'wp-live-hype' ),
				);
			case 'location':
				return array(
					__( 'Ships across {region}', 'wp-live-hype' ),
					__( 'Now shipping to {city}', 'wp-live-hype' ),
					__( 'Available across {country}', 'wp-live-hype' ),
				);
		}
		return array();
	}

	/**
	 * Last-resort template that only needs the product name.
	 *
	 * @param string $type Template type.
	 * @return string
	 */
	public static function fallback_template( string $type ): string {
		switch ( $type ) {
			case 'purchase':
				return __( 'Recently purchased: {product}', 'wp-live-hype' );
			case 'sale':
				return __( '{product} is currently on sale', 'wp-live-hype' );
			case 'popular':
				return __( '{product} has been popular recently', 'wp-live-hype' );
			case 'bestseller':
				return __( '{product} is one of our best sellers', 'wp-live-hype' );
			case 'featured':
				return __( 'Featured — {product}', 'wp-live-hype' );
			case 'explore':
				return __( 'Explore {product}', 'wp-live-hype' );
			case 'location':
				return __( 'Now shipping across {location}', 'wp-live-hype' );
		}
		return '{product}';
	}

	/**
	 * Default eyebrow label for a notification type.
	 *
	 * @param string $type purchase|sale|popular.
	 * @return string
	 */
	public static function default_label( string $type ): string {
		switch ( $type ) {
			case 'purchase':
				return __( 'Recent purchase', 'wp-live-hype' );
			case 'sale':
				return __( 'On sale', 'wp-live-hype' );
			case 'popular':
				return __( 'Popular', 'wp-live-hype' );
			case 'featured':
				return __( 'Featured', 'wp-live-hype' );
			case 'explore':
				return __( 'Spotlight', 'wp-live-hype' );
			case 'location':
				return __( 'Now shipping', 'wp-live-hype' );
		}
		return '';
	}

	/**
	 * Effective label for a type (custom or translated default).
	 *
	 * @param string $type purchase|sale|popular.
	 * @return string
	 */
	public static function label( string $type ): string {
		$custom = (string) Settings::get( 'label_' . $type );
		return '' !== $custom ? $custom : self::default_label( $type );
	}

	/**
	 * Effective template lines for a type (custom or translated defaults).
	 *
	 * @param string $type Template type.
	 * @return string[]
	 */
	public static function templates( string $type ): array {
		$stored = (string) Settings::get( 'tpl_' . $type );
		if ( '' === trim( $stored ) ) {
			return self::default_templates( $type );
		}
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $stored ) ), 'strlen' ) );
		return $lines ? $lines : self::default_templates( $type );
	}

	/**
	 * Validate a single template line.
	 *
	 * @param string $type Template type.
	 * @param string $line Template line.
	 * @return true|string True when valid, otherwise a translated reason.
	 */
	public static function validate_line( string $type, string $line ) {
		$allowed = self::allowed_tokens()[ $type ] ?? array();

		if ( 'location' === $type ) {
			if ( ! preg_match( '/\{(location|country|region|province|state|city)\}/', $line ) ) {
				return __( 'Shipping templates must include a location token such as {region}.', 'wp-live-hype' );
			}
		} elseif ( false === strpos( $line, '{product}' ) ) {
			return __( 'Every template must include the {product} token.', 'wp-live-hype' );
		}

		preg_match_all( '/\{([a-z_]+)\}/', $line, $matches );
		foreach ( array_unique( $matches[1] ) as $token ) {
			if ( ! in_array( $token, $allowed, true ) ) {
				/* translators: %s: token name such as {city}. */
				return sprintf( __( 'The token {%s} is not available for this notification type.', 'wp-live-hype' ), $token );
			}
		}

		$stripped = (string) preg_replace( '/\{[a-z_]+\}/', '', $line );
		if ( false !== strpos( $stripped, '{' ) || false !== strpos( $stripped, '}' ) ) {
			return __( 'The template contains an unrecognised or malformed token. Tokens look like {product}.', 'wp-live-hype' );
		}

		return true;
	}

	/**
	 * Sanitize a template setting submitted from the admin.
	 *
	 * Invalid lines are dropped with an admin notice. When nothing valid
	 * remains the previous value is kept. Saving the translated defaults
	 * stores an empty string so they keep following the site language.
	 *
	 * @param string $type     Template type.
	 * @param string $raw      Raw textarea value.
	 * @param string $previous Previous stored value.
	 * @return string
	 */
	public static function sanitize_setting( string $type, string $raw, string $previous ): string {
		$lines = preg_split( '/\r\n|\r|\n/', $raw );
		$valid = array();
		$had   = false;

		foreach ( (array) $lines as $line ) {
			$line = Security::clean_text( (string) $line, self::MAX_LENGTH );
			if ( '' === $line ) {
				continue;
			}
			$had    = true;
			$result = self::validate_line( $type, $line );
			if ( true === $result ) {
				$valid[] = $line;
			} else {
				add_settings_error(
					Settings::OPTION,
					'wplh_tpl_' . $type . '_' . count( $valid ),
					/* translators: 1: template text, 2: reason it was rejected. */
					sprintf( __( 'Template "%1$s" was not saved: %2$s', 'wp-live-hype' ), esc_html( $line ), esc_html( $result ) )
				);
			}
		}

		if ( ! $had ) {
			return '';
		}
		if ( empty( $valid ) ) {
			return $previous;
		}

		$valid = array_slice( array_values( array_unique( $valid ) ), 0, self::MAX_LINES );
		if ( self::default_templates( $type ) === $valid ) {
			return '';
		}
		return implode( "\n", $valid );
	}

	/**
	 * Render a template with real values.
	 *
	 * Returns null when any token in the template lacks data, so the caller
	 * can try the next template. `{time_ago}` is left in place: the browser
	 * replaces it at display time so the elapsed time is always current.
	 *
	 * @param string $template Template line.
	 * @param array  $values   token => plain-text value.
	 * @return string|null Plain text message.
	 */
	public static function render( string $template, array $values ): ?string {
		$template = str_replace( '{discount_percent}%', '{discount_percent}', $template );

		if ( ! preg_match_all( '/\{([a-z_]+)\}/', $template, $matches ) ) {
			return null;
		}

		$replace = array();
		foreach ( array_unique( $matches[1] ) as $token ) {
			if ( 'time_ago' === $token ) {
				continue;
			}
			$value = isset( $values[ $token ] ) ? (string) $values[ $token ] : '';
			if ( '' === $value ) {
				return null;
			}
			$replace[ '{' . $token . '}' ] = $value;
		}

		$message = strtr( $template, $replace );
		$message = trim( (string) preg_replace( '/\s+/u', ' ', $message ) );
		return '' !== $message ? $message : null;
	}

	/**
	 * Render the first (or a random) eligible template for a type.
	 *
	 * @param string $type   Template type.
	 * @param array  $values Token values.
	 * @return string Rendered message (falls back to a product-only template).
	 */
	public static function render_for( string $type, array $values ): string {
		$templates = self::templates( $type );
		// Promotional types always rotate wording so the stream never feels canned.
		if ( 'random' === Settings::get( 'template_rotation' ) || in_array( $type, array( 'featured', 'explore', 'location' ), true ) ) {
			shuffle( $templates );
		}
		foreach ( $templates as $template ) {
			$message = self::render( $template, $values );
			if ( null !== $message ) {
				return $message;
			}
		}
		return (string) self::render( self::fallback_template( $type ), $values );
	}
}
