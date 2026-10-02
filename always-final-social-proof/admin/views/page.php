<?php
/**
 * Admin page shell.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var string $tab      Current tab.
 * @var array  $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

$afsp_tabs    = Admin::tabs();
$afsp_enabled = (bool) $settings['enabled'];
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only message key.
$afsp_msg = isset( $_GET['afsp_msg'] ) ? sanitize_key( wp_unslash( $_GET['afsp_msg'] ) ) : '';
?>
<div class="wrap afsp-wrap">
	<header class="afsp-header">
		<div class="afsp-header__brand">
			<?php if ( Branding::show() ) : ?>
				<img class="afsp-header__logo" src="<?php echo esc_url( Branding::logo_url() ); ?>" alt="<?php echo esc_attr( Branding::developer_name() ); ?>" width="40" height="40" />
			<?php endif; ?>
			<div>
				<h1 class="afsp-header__title"><?php echo esc_html( Settings::plugin_label() ); ?></h1>
				<p class="afsp-header__subtitle">
					<?php esc_html_e( 'Real-time social proof from genuine WooCommerce activity.', 'always-final-social-proof' ); ?>
					<?php if ( Settings::get( 'branding_show_version' ) ) : ?>
						<span class="afsp-version">v<?php echo esc_html( AFSP_VERSION ); ?></span>
					<?php endif; ?>
				</p>
			</div>
		</div>
		<div class="afsp-header__status">
			<?php if ( ! $afsp_enabled ) : ?>
				<span class="afsp-pill afsp-pill--off"><?php esc_html_e( 'Notifications off', 'always-final-social-proof' ); ?></span>
			<?php elseif ( $settings['admin_only'] ) : ?>
				<span class="afsp-pill afsp-pill--warn"><?php esc_html_e( 'Test mode — administrators only', 'always-final-social-proof' ); ?></span>
			<?php else : ?>
				<span class="afsp-pill afsp-pill--on"><?php esc_html_e( 'Live', 'always-final-social-proof' ); ?></span>
			<?php endif; ?>
		</div>
	</header>

	<nav class="nav-tab-wrapper afsp-tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'always-final-social-proof' ); ?>">
		<?php foreach ( $afsp_tabs as $afsp_slug => $afsp_label ) : ?>
			<a href="<?php echo esc_url( Admin::url( $afsp_slug ) ); ?>" class="nav-tab <?php echo $tab === $afsp_slug ? 'nav-tab-active' : ''; ?>" <?php echo $tab === $afsp_slug ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $afsp_label ); ?></a>
		<?php endforeach; ?>
	</nav>

	<div class="afsp-notices">
		<?php settings_errors(); ?>
		<?php if ( 'refreshed' === $afsp_msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Notification data was rebuilt from current store activity.', 'always-final-social-proof' ); ?></p></div>
		<?php elseif ( 'analytics_reset' === $afsp_msg ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Analytics data was deleted.', 'always-final-social-proof' ); ?></p></div>
		<?php endif; ?>
	</div>

	<main class="afsp-main afsp-tab-<?php echo esc_attr( $tab ); ?>">
		<?php include AFSP_PATH . 'admin/views/tab-' . $tab . '.php'; ?>
	</main>

</div>
