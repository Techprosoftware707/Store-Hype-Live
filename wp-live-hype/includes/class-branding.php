<?php
/**
 * Branding and white-label controls.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Branding helpers.
 */
final class Branding {

	const DEVELOPER = 'ALWAYS FINAL';

	/**
	 * Whether ALWAYS FINAL / developer branding is shown in the admin.
	 *
	 * @return bool
	 */
	public static function show(): bool {
		return (bool) Settings::get( 'branding_show' );
	}

	/**
	 * Whether the Branding tab is available. Agencies can lock branding for
	 * client sites with `define( 'WPLH_LOCK_BRANDING', true );`.
	 *
	 * @return bool
	 */
	public static function tab_available(): bool {
		return ! ( defined( 'WPLH_LOCK_BRANDING' ) && WPLH_LOCK_BRANDING );
	}

	/**
	 * Developer name.
	 *
	 * @return string
	 */
	public static function developer_name(): string {
		$name = (string) Settings::get( 'branding_developer' );
		return '' !== $name ? $name : self::DEVELOPER;
	}

	/**
	 * Logo URL (custom or bundled ALWAYS FINAL mark).
	 *
	 * @return string
	 */
	public static function logo_url(): string {
		$custom = (string) Settings::get( 'branding_logo_url' );
		return '' !== $custom ? $custom : WPLH_URL . 'assets/images/always-final-logo.svg';
	}

	/**
	 * Support URL (may be empty).
	 *
	 * @return string
	 */
	public static function support_url(): string {
		return (string) Settings::get( 'branding_support_url' );
	}
}
