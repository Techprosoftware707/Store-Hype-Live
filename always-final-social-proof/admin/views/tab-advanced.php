<?php
/**
 * Advanced tab.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

$afsp_hpos     = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
$afsp_data     = Cache::get_dataset( false );
$afsp_logs_url = admin_url( 'admin.php?page=wc-status&tab=logs&source=' . Logger::SOURCE );

Admin::form_start( 'advanced' );

Admin::card_start( __( 'Caching & performance', 'always-final-social-proof' ), __( 'Orders are never queried on page load. A sanitized dataset is rebuilt in the background at this interval and served from cache (Redis/Memcached when available).', 'always-final-social-proof' ) );
Admin::select(
	'cache_ttl',
	__( 'Refresh interval', 'always-final-social-proof' ),
	array(
		1  => __( '1 minute', 'always-final-social-proof' ),
		5  => __( '5 minutes', 'always-final-social-proof' ),
		10 => __( '10 minutes', 'always-final-social-proof' ),
		30 => __( '30 minutes', 'always-final-social-proof' ),
	),
	__( 'Cancelled or refunded orders are removed promptly regardless of this interval.', 'always-final-social-proof' )
);
Admin::number( 'scan_limit', __( 'Maximum orders scanned per refresh', 'always-final-social-proof' ), __( 'Caps database work on very busy stores. The most recent orders are always scanned first.', 'always-final-social-proof' ) );
Admin::toggle( 'client_cache', __( 'Cache notification data in the visitor\'s browser session', 'always-final-social-proof' ), __( 'Avoids repeat requests while a visitor browses (up to 5 minutes).', 'always-final-social-proof' ) );
Admin::card_end();

Admin::card_start( __( 'Debugging', 'always-final-social-proof' ) );
Admin::toggle( 'debug', __( 'Enable debug logging', 'always-final-social-proof' ), __( 'Logs startup, configuration, query counts, cache status and errors to WooCommerce → Status → Logs. Customer data is never logged.', 'always-final-social-proof' ) );
echo '<p><a href="' . esc_url( $afsp_logs_url ) . '">' . esc_html__( 'View logs', 'always-final-social-proof' ) . '</a></p>';
Admin::card_end();

Admin::card_start( __( 'Uninstall', 'always-final-social-proof' ), __( 'Choose what is removed when the plugin is deleted. WooCommerce orders, customers, products and product data are never touched.', 'always-final-social-proof' ) );
Admin::toggle( 'uninstall_delete_settings', __( 'Delete plugin settings', 'always-final-social-proof' ) );
Admin::toggle( 'uninstall_delete_cache', __( 'Delete cached data', 'always-final-social-proof' ) );
Admin::toggle( 'uninstall_delete_analytics', __( 'Delete analytics data', 'always-final-social-proof' ) );
Admin::card_end();

Admin::form_end();

Admin::card_start( __( 'System information', 'always-final-social-proof' ) );
?>
<table class="widefat striped afsp-table afsp-sysinfo">
	<tbody>
		<tr><th scope="row"><?php esc_html_e( 'Plugin version', 'always-final-social-proof' ); ?></th><td><?php echo esc_html( AFSP_VERSION ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'WordPress', 'always-final-social-proof' ); ?></th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'WooCommerce', 'always-final-social-proof' ); ?></th><td><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : '—' ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'PHP', 'always-final-social-proof' ); ?></th><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Order storage', 'always-final-social-proof' ); ?></th><td><?php echo $afsp_hpos ? esc_html__( 'High-Performance Order Storage', 'always-final-social-proof' ) : esc_html__( 'WordPress posts (legacy)', 'always-final-social-proof' ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Object cache', 'always-final-social-proof' ); ?></th><td><?php echo wp_using_ext_object_cache() ? esc_html__( 'Persistent', 'always-final-social-proof' ) : esc_html__( 'Not persistent (database transients)', 'always-final-social-proof' ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Last data build', 'always-final-social-proof' ); ?></th><td>
			<?php
			if ( ! empty( $afsp_data['generated'] ) ) {
				/* translators: 1: time ago, 2: duration in milliseconds. */
				echo esc_html( sprintf( __( '%1$s ago, took %2$d ms', 'always-final-social-proof' ), human_time_diff( (int) $afsp_data['generated'] ), (int) ( $afsp_data['stats']['duration_ms'] ?? 0 ) ) );
			} else {
				esc_html_e( 'Not built yet', 'always-final-social-proof' );
			}
			?>
		</td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Orders scanned in last build', 'always-final-social-proof' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) ( $afsp_data['stats']['scanned'] ?? 0 ) ) ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'REST endpoint', 'always-final-social-proof' ); ?></th><td><code><?php echo esc_html( rest_url( Rest_Api::NAMESPACE_V1 . '/notifications' ) ); ?></code></td></tr>
	</tbody>
</table>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="afsp-inline-form">
	<?php wp_nonce_field( 'afsp_refresh' ); ?>
	<input type="hidden" name="action" value="afsp_refresh" />
	<input type="hidden" name="tab" value="advanced" />
	<button type="submit" class="button"><?php esc_html_e( 'Clear cache and rebuild now', 'always-final-social-proof' ); ?></button>
</form>
<?php
Admin::card_end();
