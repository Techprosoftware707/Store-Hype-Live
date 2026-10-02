<?php
/**
 * Public REST API.
 *
 * GET  /wp-json/always-final-social-proof/v1/notifications
 * POST /wp-json/always-final-social-proof/v1/events
 *
 * The notifications endpoint returns only sanitized public data: product
 * name/link/image, a rendered message, coarse location (to the configured
 * level) and a rounded timestamp. It never returns customer identity,
 * contact details, addresses, postcodes, order numbers/IDs, payment data or
 * order metadata. The response is identical for every visitor with the same
 * page context, so it is safe to cache publicly.
 *
 * @package AlwaysFinal\SocialProof
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller.
 */
final class Rest_Api {

	const NAMESPACE_V1 = 'always-final-social-proof/v1';

	/**
	 * Page context types the client may send.
	 */
	const CONTEXTS = array( 'home', 'shop', 'product', 'category', 'tag', 'cart', 'checkout', 'account', 'page', 'post', 'other' );

	/**
	 * Register routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/notifications',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_notifications' ),
				'permission_callback' => array( __CLASS__, 'can_read' ),
				'args'                => array(
					'ctx' => array(
						'type'              => 'string',
						'enum'              => self::CONTEXTS,
						'default'           => 'other',
						'sanitize_callback' => 'sanitize_key',
					),
					'pid' => array(
						'type'              => 'integer',
						'minimum'           => 0,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'tid' => array(
						'type'              => 'integer',
						'minimum'           => 0,
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/events',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'post_events' ),
				// Anonymous aggregate counters only; no user data is read or changed.
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Permission check for the notifications endpoint.
	 *
	 * Public by design. In "administrators only" test mode the request must be
	 * authenticated (REST nonce) by a user who can manage the plugin.
	 *
	 * @return bool|\WP_Error
	 */
	public static function can_read() {
		if ( Settings::get( 'admin_only' ) && ! Security::can_manage() ) {
			return new \WP_Error( 'afsp_forbidden', __( 'Notifications are currently limited to administrators.', 'always-final-social-proof' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

	/**
	 * GET notifications.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function get_notifications( \WP_REST_Request $request ): \WP_REST_Response {
		$items = array();
		try {
			if ( Settings::get( 'enabled' ) ) {
				$items = Notifications::queue(
					array(
						'type'       => (string) $request->get_param( 'ctx' ),
						'product_id' => (int) $request->get_param( 'pid' ),
						'term_id'    => (int) $request->get_param( 'tid' ),
					)
				);
			}
		} catch ( \Throwable $e ) {
			Logger::error( 'REST notifications error: ' . get_class( $e ) . ' — ' . $e->getMessage() );
			$items = array();
		}

		$response = new \WP_REST_Response(
			array(
				'items'     => array_map( array( __CLASS__, 'public_fields' ), $items ),
				'generated' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			),
			200
		);

		if ( ! is_user_logged_in() ) {
			$max_age = (int) min( 120, Cache::ttl() );
			$response->header( 'Cache-Control', 'public, max-age=' . $max_age . ', s-maxage=' . $max_age );
		}
		$response->header( 'X-Robots-Tag', 'noindex' );

		return $response;
	}

	/**
	 * Whitelist the fields of a public notification object. Anything not
	 * listed here can never reach the browser, even if a filter adds it.
	 *
	 * @param array $item Notification.
	 * @return array
	 */
	public static function public_fields( $item ): array {
		$item    = is_array( $item ) ? $item : array();
		$strings = array( 'id', 'type', 'product', 'url', 'image', 'message', 'label', 'location', 'country', 'region', 'city', 'timestamp', 'time_ago' );
		$out     = array();
		foreach ( $strings as $key ) {
			if ( isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) && '' !== (string) $item[ $key ] ) {
				$out[ $key ] = in_array( $key, array( 'url', 'image' ), true ) ? esc_url_raw( (string) $item[ $key ] ) : Security::clean_text( (string) $item[ $key ], 300 );
			}
		}
		$out['product_id'] = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
		if ( ! empty( $item['image_w'] ) && ! empty( $item['image_h'] ) ) {
			$out['image_w'] = absint( $item['image_w'] );
			$out['image_h'] = absint( $item['image_h'] );
		}
		if ( isset( $item['verified'] ) ) {
			$out['verified'] = (bool) $item['verified'];
		}
		if ( isset( $item['price'] ) && is_array( $item['price'] ) ) {
			$out['price'] = array();
			foreach ( array( 'sale', 'regular', 'discount' ) as $key ) {
				if ( ! empty( $item['price'][ $key ] ) && is_scalar( $item['price'][ $key ] ) ) {
					$out['price'][ $key ] = Security::clean_text( (string) $item['price'][ $key ], 60 );
				}
			}
		}
		return $out;
	}

	/**
	 * POST analytics events.
	 *
	 * Accepts a small JSON payload (also as text/plain so it can be sent with
	 * navigator.sendBeacon) of the form {"events":[{"e":"view","t":"sale","p":12}]}.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function post_events( \WP_REST_Request $request ) {
		if ( ! Settings::get( 'enabled' ) || ! Settings::get( 'analytics_enabled' ) ) {
			return new \WP_REST_Response( null, 204 );
		}

		$body = (string) $request->get_body();
		if ( strlen( $body ) > 8192 ) {
			return new \WP_Error( 'afsp_payload_too_large', __( 'Payload too large.', 'always-final-social-proof' ), array( 'status' => 413 ) );
		}

		$payload = json_decode( $body, true );
		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'afsp_invalid_payload', __( 'Invalid payload.', 'always-final-social-proof' ), array( 'status' => 400 ) );
		}

		$aggregated = Analytics::validate_batch( $payload );
		if ( empty( $aggregated ) ) {
			return new \WP_REST_Response( null, 204 );
		}

		if ( ! Analytics::within_rate_limit( (int) array_sum( $aggregated ) ) ) {
			return new \WP_Error( 'afsp_rate_limited', __( 'Too many events.', 'always-final-social-proof' ), array( 'status' => 429 ) );
		}

		try {
			Analytics::record( $aggregated );
		} catch ( \Throwable $e ) {
			Logger::error( 'REST events error: ' . get_class( $e ) );
		}

		return new \WP_REST_Response( null, 204 );
	}
}
