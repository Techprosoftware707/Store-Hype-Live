<?php
/**
 * Admin page shell.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var string $tab      Current tab.
 * @var array  $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_tabs    = Admin::tabs();
$wplh_enabled = (bool) $settings['enabled'];
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only message key.
$wplh_msg = isset( $_GET['wplh_msg'] ) ? sanitize_key( wp_unslash( $_GET['wplh_msg'] ) ) : '';
?>
<div class="wrap wplh-wrap">
	<div class="wplh-header">
		<div class="wplh-header__brand">
			<?php if ( Branding::show() ) : ?>
				<img class="wplh-header__logo" src="<?php echo esc_url( Branding::logo_url() ); ?>" alt="<?php echo esc_attr( Branding::developer_name() ); ?>" width="40" height="40" />
			<?php endif; ?>
			<div>
				<h1 class="wplh-header__title"><?php echo esc_html( Settings::plugin_label() ); ?></h1>
				<p class="wplh-header__subtitle">
					<?php esc_html_e( 'A live activity layer for your store — privacy-safe by design.', 'wp-live-hype' ); ?>
					<?php if ( Settings::get( 'branding_show_version' ) ) : ?>
						<span class="wplh-version">v<?php echo esc_html( WPLH_VERSION ); ?></span>
					<?php endif; ?>
				</p>
			</div>
		</div>
		<div class="wplh-header__status">
			<?php if ( ! $wplh_enabled ) : ?>
				<span class="wplh-pill wplh-pill--off"><?php esc_html_e( 'Notifications off', 'wp-live-hype' ); ?></span>
			<?php elseif ( $settings['admin_only'] ) : ?>
				<span class="wplh-pill wplh-pill--warn"><?php esc_html_e( 'Test mode — administrators only', 'wp-live-hype' ); ?></span>
			<?php else : ?>
				<span class="wplh-pill wplh-pill--on"><?php esc_html_e( 'Live', 'wp-live-hype' ); ?></span>
			<?php endif; ?>
		</div>
	</div>

	<nav class="nav-tab-wrapper wplh-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'wp-live-hype' ); ?>">
		<?php foreach ( $wplh_tabs as $wplh_slug => $wplh_label ) : ?>
			<a href="<?php echo esc_url( Admin::url( $wplh_slug ) ); ?>" class="nav-tab <?php echo $tab === $wplh_slug ? 'nav-tab-active' : ''; ?>" <?php echo $tab === $wplh_slug ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $wplh_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="wplh-notices">
		<?php settings_errors(); ?>
		<?php if ( 'refreshed' === $wplh_msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Notification data was rebuilt from current store activity.', 'wp-live-hype' ); ?></p></div>
		<?php elseif ( 'analytics_reset' === $wplh_msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Analytics data was deleted.', 'wp-live-hype' ); ?></p></div>
		<?php endif; ?>
	</div>

	<div class="wplh-main wplh-tab-<?php echo esc_attr( $tab ); ?>">
		<?php require WPLH_PATH . 'admin/views/tab-' . $tab . '.php'; ?>
	</div>

</div>
