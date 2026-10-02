<?php
/**
 * Dashboard tab.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

$afsp_data      = Cache::get_dataset();
$afsp_stats     = (array) ( $afsp_data['stats'] ?? array() );
$afsp_allowed   = Country::allowed_countries();
$afsp_totals    = Analytics::totals( 7 );
$afsp_lookback  = (int) $settings['lookback_hours'];
$afsp_purchases = (int) ( $afsp_stats['in_lookback'] ?? 0 );
$afsp_next      = wp_next_scheduled( Cache::CRON_HOOK );
$afsp_hpos      = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
$afsp_want_buy  = $settings['type_purchase'] || $settings['type_product_purchase'];

$afsp_window = $afsp_lookback >= 24
	/* translators: %d: number of days. */
	? sprintf( _n( 'last %d day', 'last %d days', (int) ( $afsp_lookback / 24 ), 'always-final-social-proof' ), (int) ( $afsp_lookback / 24 ) )
	/* translators: %d: number of hours. */
	: sprintf( _n( 'last %d hour', 'last %d hours', $afsp_lookback, 'always-final-social-proof' ), $afsp_lookback );

// Health checks: [ status (ok|warn|info), message ].
$afsp_checks   = array();
$afsp_checks[] = array( 'ok', sprintf( /* translators: %s: WooCommerce version. */ __( 'WooCommerce %s detected.', 'always-final-social-proof' ), defined( 'WC_VERSION' ) ? WC_VERSION : '?' ) );
$afsp_checks[] = array( 'ok', $afsp_hpos ? __( 'High-Performance Order Storage (HPOS) is active and fully supported.', 'always-final-social-proof' ) : __( 'Legacy order storage is active and fully supported.', 'always-final-social-proof' ) );

if ( Targeting::store_coming_soon() ) {
	$afsp_checks[] = array( 'warn', __( 'WooCommerce "Coming soon" mode is active, so notifications are hidden from visitors until the store launches. Store managers still see them.', 'always-final-social-proof' ) );
}
if ( ! $settings['enabled'] ) {
	$afsp_checks[] = array( 'warn', __( 'Notifications are switched off (General tab).', 'always-final-social-proof' ) );
}
if ( $afsp_want_buy ) {
	if ( false === $afsp_allowed ) {
		$afsp_checks[] = array( 'warn', $settings['country_filter'] ? __( 'Country filtering is ON but no target country is selected. Purchase notifications will remain inactive until a country is chosen.', 'always-final-social-proof' ) : __( 'No geographic scope is configured. Purchase notifications are inactive.', 'always-final-social-proof' ) );
	} elseif ( 0 === $afsp_purchases ) {
		$afsp_checks[] = array(
			'warn',
			sprintf(
				/* translators: 1: geographic scope, 2: time window. */
				__( 'No qualifying orders (%1$s) in the %2$s. Purchase notifications will remain inactive until qualifying order activity exists — other countries are never used as a fallback.', 'always-final-social-proof' ),
				Country::scope_description(),
				$afsp_window
			),
		);
	} else {
		$afsp_checks[] = array(
			'ok',
			sprintf(
				/* translators: 1: number of orders, 2: geographic scope, 3: time window. */
				_n( '%1$d qualifying order (%2$s) in the %3$s.', '%1$d qualifying orders (%2$s) in the %3$s.', $afsp_purchases, 'always-final-social-proof' ),
				$afsp_purchases,
				Country::scope_description(),
				$afsp_window
			),
		);
	}
}
if ( ! empty( $afsp_stats['limit_reached'] ) ) {
	$afsp_checks[] = array( 'info', sprintf( /* translators: %d: scan limit. */ __( 'The order scan limit (%d orders) was reached; only the most recent orders are used. You can raise it on the Advanced tab.', 'always-final-social-proof' ), (int) $settings['scan_limit'] ) );
}
if ( ! empty( $afsp_stats['popular_unavailable'] ) ) {
	$afsp_checks[] = array( 'warn', __( 'Lifetime best sellers use WooCommerce\'s store-wide sales counter, which cannot respect country targeting. Popular notifications are inactive; choose a time-based popularity window on the Data tab.', 'always-final-social-proof' ) );
}
if ( ! empty( $afsp_stats['error'] ) ) {
	$afsp_checks[] = array( 'warn', __( 'The last data refresh failed. Enable debug logging on the Advanced tab for details.', 'always-final-social-proof' ) );
}
if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
	$afsp_checks[] = array( 'info', __( 'WP-Cron is disabled. Make sure a server cron runs wp-cron.php; data is otherwise refreshed on demand.', 'always-final-social-proof' ) );
}
$afsp_checks[] = array( 'info', wp_using_ext_object_cache() ? __( 'Persistent object cache detected (e.g. Redis) — cached data is served from memory.', 'always-final-social-proof' ) : __( 'No persistent object cache detected; cached data is stored as transients in the database.', 'always-final-social-proof' ) );
if ( ! Analytics::table_exists() ) {
	$afsp_checks[] = array( 'warn', __( 'The analytics table is missing. Deactivate and reactivate the plugin to recreate it.', 'always-final-social-proof' ) );
}

$afsp_icons = array(
	'ok'   => array( 'yes-alt', __( 'OK', 'always-final-social-proof' ) ),
	'warn' => array( 'warning', __( 'Attention', 'always-final-social-proof' ) ),
	'info' => array( 'info-outline', __( 'Info', 'always-final-social-proof' ) ),
);
?>
<div class="afsp-kpis" role="list">
	<div class="afsp-kpi" role="listitem">
		<span class="afsp-kpi__label"><?php esc_html_e( 'Geographic scope', 'always-final-social-proof' ); ?></span>
		<span class="afsp-kpi__value afsp-kpi__value--text"><?php echo esc_html( false === $afsp_allowed ? __( 'Not configured', 'always-final-social-proof' ) : ( null === $afsp_allowed ? __( 'All countries', 'always-final-social-proof' ) : implode( ', ', array_map( array( Country::class, 'name' ), $afsp_allowed ) ) ) ); ?></span>
		<span class="afsp-kpi__meta"><?php echo $settings['country_filter'] ? esc_html__( 'Country filtering ON', 'always-final-social-proof' ) : esc_html__( 'Country filtering OFF', 'always-final-social-proof' ); ?></span>
	</div>
	<div class="afsp-kpi" role="listitem">
		<span class="afsp-kpi__label"><?php esc_html_e( 'Qualifying orders', 'always-final-social-proof' ); ?></span>
		<span class="afsp-kpi__value"><?php echo esc_html( number_format_i18n( $afsp_purchases ) ); ?></span>
		<span class="afsp-kpi__meta"><?php echo esc_html( ucfirst( $afsp_window ) ); ?></span>
	</div>
	<div class="afsp-kpi" role="listitem">
		<span class="afsp-kpi__label"><?php esc_html_e( 'Active sales', 'always-final-social-proof' ); ?></span>
		<span class="afsp-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $afsp_stats['sales'] ?? 0 ) ) ); ?></span>
		<span class="afsp-kpi__meta"><?php echo $settings['type_sale'] ? esc_html__( 'Sale notifications on', 'always-final-social-proof' ) : esc_html__( 'Sale notifications off', 'always-final-social-proof' ); ?></span>
	</div>
	<div class="afsp-kpi" role="listitem">
		<span class="afsp-kpi__label"><?php esc_html_e( 'Shown (7 days)', 'always-final-social-proof' ); ?></span>
		<span class="afsp-kpi__value"><?php echo esc_html( number_format_i18n( $afsp_totals['view'] ) ); ?></span>
		<span class="afsp-kpi__meta">
			<?php
			/* translators: 1: clicks, 2: click-through rate. */
			echo esc_html( sprintf( __( '%1$s clicks · %2$s%% CTR', 'always-final-social-proof' ), number_format_i18n( $afsp_totals['click'] ), number_format_i18n( $afsp_totals['ctr'], 1 ) ) );
			?>
		</span>
	</div>
</div>

<div class="afsp-grid afsp-grid--2">
	<div>
		<?php Admin::card_start( __( 'Health & status', 'always-final-social-proof' ) ); ?>
		<ul class="afsp-checks-list">
			<?php foreach ( $afsp_checks as $afsp_check ) : ?>
				<li class="afsp-check afsp-check--<?php echo esc_attr( $afsp_check[0] ); ?>">
					<span class="dashicons dashicons-<?php echo esc_attr( $afsp_icons[ $afsp_check[0] ][0] ); ?>" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php echo esc_html( $afsp_icons[ $afsp_check[0] ][1] ); ?>:</span>
					<span><?php echo esc_html( $afsp_check[1] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<div class="afsp-meta-row">
			<span>
				<?php
				echo esc_html(
					! empty( $afsp_data['generated'] )
						/* translators: %s: human time difference. */
						? sprintf( __( 'Data refreshed %s ago', 'always-final-social-proof' ), human_time_diff( (int) $afsp_data['generated'] ) )
						: __( 'Data not built yet', 'always-final-social-proof' )
				);
				if ( $afsp_next ) {
					echo ' · ';
					/* translators: %s: human time difference. */
					echo esc_html( sprintf( __( 'next refresh in %s', 'always-final-social-proof' ), human_time_diff( time(), (int) $afsp_next ) ) );
				}
				?>
			</span>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'afsp_refresh' ); ?>
				<input type="hidden" name="action" value="afsp_refresh" />
				<input type="hidden" name="tab" value="dashboard" />
				<button type="submit" class="button"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e( 'Refresh data now', 'always-final-social-proof' ); ?></button>
			</form>
		</div>
		<?php Admin::card_end(); ?>

		<?php Admin::card_start( __( 'Preview on your storefront', 'always-final-social-proof' ), __( 'Opens your homepage with a sample notification that only you can see. It is clearly labelled as a preview and never shown to customers.', 'always-final-social-proof' ) ); ?>
		<p class="afsp-actions">
			<?php
			foreach ( array(
				'purchase' => __( 'Purchase preview', 'always-final-social-proof' ),
				'sale'     => __( 'Sale preview', 'always-final-social-proof' ),
				'popular'  => __( 'Popular preview', 'always-final-social-proof' ),
			) as $afsp_type => $afsp_label ) :
				?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'afsp_preview', $afsp_type, home_url( '/' ) ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $afsp_label ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'always-final-social-proof' ); ?></span></a>
			<?php endforeach; ?>
		</p>
		<?php Admin::card_end(); ?>
	</div>

	<div>
		<?php Admin::card_start( __( 'Live preview', 'always-final-social-proof' ), __( 'How notifications look with your saved display settings.', 'always-final-social-proof' ) ); ?>
		<?php Admin::preview_panel( 'afsp-preview-dashboard' ); ?>
		<?php Admin::card_end(); ?>
	</div>
</div>
