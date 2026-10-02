<?php
/**
 * Advanced tab.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_hpos     = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
$wplh_data     = Cache::get_dataset( false );
$wplh_logs_url = admin_url( 'admin.php?page=wc-status&tab=logs&source=' . Logger::SOURCE );

Admin::form_start( 'advanced' );

Admin::card_start( __( 'Caching & performance', 'wp-live-hype' ), __( 'Orders are never queried on page load. A sanitized dataset is rebuilt in the background at this interval and served from cache (Redis/Memcached when available).', 'wp-live-hype' ) );
Admin::select(
	'cache_ttl',
	__( 'Refresh interval', 'wp-live-hype' ),
	array(
		1  => __( '1 minute', 'wp-live-hype' ),
		5  => __( '5 minutes', 'wp-live-hype' ),
		10 => __( '10 minutes', 'wp-live-hype' ),
		30 => __( '30 minutes', 'wp-live-hype' ),
	),
	__( 'Cancelled or refunded orders are removed promptly regardless of this interval.', 'wp-live-hype' )
);
Admin::number( 'scan_limit', __( 'Maximum orders scanned per refresh', 'wp-live-hype' ), __( 'Caps database work on very busy stores. The most recent orders are always scanned first.', 'wp-live-hype' ) );
Admin::toggle( 'client_cache', __( 'Cache notification data in the visitor\'s browser session', 'wp-live-hype' ), __( 'Avoids repeat requests while a visitor browses (up to 5 minutes).', 'wp-live-hype' ) );
Admin::card_end();

Admin::card_start( __( 'Debugging', 'wp-live-hype' ) );
Admin::toggle( 'debug', __( 'Enable debug logging', 'wp-live-hype' ), __( 'Logs startup, configuration, query counts, cache status and errors to WooCommerce → Status → Logs. Customer data is never logged.', 'wp-live-hype' ) );
echo '<p><a href="' . esc_url( $wplh_logs_url ) . '">' . esc_html__( 'View logs', 'wp-live-hype' ) . '</a></p>';
Admin::card_end();

Admin::card_start( __( 'Uninstall', 'wp-live-hype' ), __( 'Choose what is removed when the plugin is deleted. WooCommerce orders, customers, products and product data are never touched.', 'wp-live-hype' ) );
Admin::toggle( 'uninstall_delete_settings', __( 'Delete plugin settings', 'wp-live-hype' ) );
Admin::toggle( 'uninstall_delete_cache', __( 'Delete cached data', 'wp-live-hype' ) );
Admin::toggle( 'uninstall_delete_analytics', __( 'Delete analytics data', 'wp-live-hype' ) );
Admin::card_end();

Admin::form_end();

Admin::card_start( __( 'System information', 'wp-live-hype' ) );
?>
<table class="widefat striped wplh-table wplh-sysinfo">
	<tbody>
		<tr><th scope="row"><?php esc_html_e( 'Plugin version', 'wp-live-hype' ); ?></th><td><?php echo esc_html( WPLH_VERSION ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'WordPress', 'wp-live-hype' ); ?></th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'WooCommerce', 'wp-live-hype' ); ?></th><td><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : '—' ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'PHP', 'wp-live-hype' ); ?></th><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Order storage', 'wp-live-hype' ); ?></th><td><?php echo $wplh_hpos ? esc_html__( 'High-Performance Order Storage', 'wp-live-hype' ) : esc_html__( 'WordPress posts (legacy)', 'wp-live-hype' ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Object cache', 'wp-live-hype' ); ?></th><td><?php echo wp_using_ext_object_cache() ? esc_html__( 'Persistent', 'wp-live-hype' ) : esc_html__( 'Not persistent (database transients)', 'wp-live-hype' ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Last data build', 'wp-live-hype' ); ?></th><td>
			<?php
			if ( ! empty( $wplh_data['generated'] ) ) {
				/* translators: 1: time ago, 2: duration in milliseconds. */
				echo esc_html( sprintf( __( '%1$s ago, took %2$d ms', 'wp-live-hype' ), human_time_diff( (int) $wplh_data['generated'] ), (int) ( $wplh_data['stats']['duration_ms'] ?? 0 ) ) );
			} else {
				esc_html_e( 'Not built yet', 'wp-live-hype' );
			}
			?>
		</td></tr>
		<tr><th scope="row"><?php esc_html_e( 'Orders scanned in last build', 'wp-live-hype' ); ?></th><td><?php echo esc_html( number_format_i18n( (int) ( $wplh_data['stats']['scanned'] ?? 0 ) ) ); ?></td></tr>
		<tr><th scope="row"><?php esc_html_e( 'REST endpoint', 'wp-live-hype' ); ?></th><td><code><?php echo esc_html( rest_url( Rest_Api::NAMESPACE_V1 . '/activity' ) ); ?></code></td></tr>
	</tbody>
</table>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wplh-inline-form">
	<?php wp_nonce_field( 'wplh_refresh' ); ?>
	<input type="hidden" name="action" value="wplh_refresh" />
	<input type="hidden" name="tab" value="advanced" />
	<button type="submit" class="button"><?php esc_html_e( 'Clear cache and rebuild now', 'wp-live-hype' ); ?></button>
</form>
<?php
Admin::card_end();
