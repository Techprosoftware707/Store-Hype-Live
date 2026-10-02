<?php
/**
 * Dashboard tab.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_data        = Cache::get_dataset();
$wplh_stats       = (array) ( $wplh_data['stats'] ?? array() );
$wplh_allowed     = Country::allowed_countries();
$wplh_totals      = Analytics::totals( 7 );
$wplh_lookback    = (int) $settings['lookback_hours'];
$wplh_purchases   = (int) ( $wplh_stats['in_lookback'] ?? 0 );
$wplh_next        = wp_next_scheduled( Cache::CRON_HOOK );
$wplh_hpos        = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
$wplh_mode        = (string) $settings['activity_mode'];
$wplh_want_buy    = 'synthetic' !== $wplh_mode && ( $settings['type_purchase'] || $settings['type_product_purchase'] );
$wplh_pool        = count( (array) ( $wplh_data['pool']['products'] ?? array() ) );
$wplh_conv_report = Conversion::report( 7 );
$wplh_modes       = array(
	'synthetic' => __( 'Synthetic', 'wp-live-hype' ),
	'hybrid'    => __( 'Hybrid', 'wp-live-hype' ),
	'aggregate' => __( 'Aggregate', 'wp-live-hype' ),
);

$wplh_window = $wplh_lookback >= 24
	/* translators: %d: number of days. */
	? sprintf( _n( 'last %d day', 'last %d days', (int) ( $wplh_lookback / 24 ), 'wp-live-hype' ), (int) ( $wplh_lookback / 24 ) )
	/* translators: %d: number of hours. */
	: sprintf( _n( 'last %d hour', 'last %d hours', $wplh_lookback, 'wp-live-hype' ), $wplh_lookback );

// Health checks: [ status (ok|warn|info), message ].
$wplh_checks   = array();
$wplh_checks[] = array( 'ok', sprintf( /* translators: %s: WooCommerce version. */ __( 'WooCommerce %s detected.', 'wp-live-hype' ), defined( 'WC_VERSION' ) ? WC_VERSION : '?' ) );
$wplh_checks[] = array( 'ok', $wplh_hpos ? __( 'High-Performance Order Storage (HPOS) is active and fully supported.', 'wp-live-hype' ) : __( 'Legacy order storage is active and fully supported.', 'wp-live-hype' ) );

if ( Targeting::store_coming_soon() ) {
	$wplh_checks[] = array( 'warn', __( 'WooCommerce "Coming soon" mode is active, so notifications are hidden from visitors until the store launches. Store managers still see them.', 'wp-live-hype' ) );
}
if ( 'aggregate' !== $wplh_mode ) {
	if ( 0 === $wplh_pool ) {
		$wplh_checks[] = array( 'warn', __( 'No eligible products are available to the engine, so the synthetic rotation is paused. Publish in-stock products (or review exclusions on the Products tab).', 'wp-live-hype' ) );
	} else {
		/* translators: %d: number of products. */
		$wplh_checks[] = array( 'ok', sprintf( _n( 'Synthetic rotation active with %d product.', 'Synthetic rotation active with %d products.', $wplh_pool, 'wp-live-hype' ), $wplh_pool ) );
	}
	$wplh_engine_cc = Locations::engine_country( new Rng( 1 ) );
	if ( $settings['promo_location'] && ! Locations::store_ships_to( $wplh_engine_cc ) ) {
		$wplh_checks[] = array( 'info', __( 'Shipping-region messages are inactive because WooCommerce is not set to ship to the target country.', 'wp-live-hype' ) );
	}
}
if ( ! $settings['enabled'] ) {
	$wplh_checks[] = array( 'warn', __( 'Notifications are switched off (General tab).', 'wp-live-hype' ) );
}
if ( $wplh_want_buy ) {
	if ( false === $wplh_allowed ) {
		$wplh_checks[] = array( 'warn', $settings['country_filter'] ? __( 'Country filtering is ON but no target country is selected. Purchase notifications will remain inactive until a country is chosen.', 'wp-live-hype' ) : __( 'No geographic scope is configured. Purchase notifications are inactive.', 'wp-live-hype' ) );
	} elseif ( 0 === $wplh_purchases ) {
		$wplh_checks[] = array(
			'warn',
			sprintf(
				/* translators: 1: geographic scope, 2: time window. */
				__( 'No qualifying orders (%1$s) in the %2$s. Purchase notifications will remain inactive until qualifying order activity exists — other countries are never used as a fallback.', 'wp-live-hype' ),
				Country::scope_description(),
				$wplh_window
			),
		);
	} else {
		$wplh_checks[] = array(
			'ok',
			sprintf(
				/* translators: 1: number of orders, 2: geographic scope, 3: time window. */
				_n( '%1$d qualifying order (%2$s) in the %3$s.', '%1$d qualifying orders (%2$s) in the %3$s.', $wplh_purchases, 'wp-live-hype' ),
				$wplh_purchases,
				Country::scope_description(),
				$wplh_window
			),
		);
	}
}
if ( ! empty( $wplh_stats['limit_reached'] ) ) {
	$wplh_checks[] = array( 'info', sprintf( /* translators: %d: scan limit. */ __( 'The order scan limit (%d orders) was reached; only the most recent orders are used. You can raise it on the Advanced tab.', 'wp-live-hype' ), (int) $settings['scan_limit'] ) );
}
if ( ! empty( $wplh_stats['popular_unavailable'] ) ) {
	$wplh_checks[] = array( 'warn', __( 'Lifetime best sellers use WooCommerce\'s store-wide sales counter, which cannot respect country targeting. Popular notifications are inactive; choose a time-based popularity window on the Data tab.', 'wp-live-hype' ) );
}
if ( ! empty( $wplh_stats['error'] ) ) {
	$wplh_checks[] = array( 'warn', __( 'The last data refresh failed. Enable debug logging on the Advanced tab for details.', 'wp-live-hype' ) );
}
if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
	$wplh_checks[] = array( 'info', __( 'WP-Cron is disabled. Make sure a server cron runs wp-cron.php; data is otherwise refreshed on demand.', 'wp-live-hype' ) );
}
$wplh_checks[] = array( 'info', wp_using_ext_object_cache() ? __( 'Persistent object cache detected (e.g. Redis) — cached data is served from memory.', 'wp-live-hype' ) : __( 'No persistent object cache detected; cached data is stored as transients in the database.', 'wp-live-hype' ) );
if ( ! Analytics::table_exists() ) {
	$wplh_checks[] = array( 'warn', __( 'The analytics table is missing. Deactivate and reactivate the plugin to recreate it.', 'wp-live-hype' ) );
}

$wplh_icons = array(
	'ok'   => array( 'yes-alt', __( 'OK', 'wp-live-hype' ) ),
	'warn' => array( 'warning', __( 'Attention', 'wp-live-hype' ) ),
	'info' => array( 'info-outline', __( 'Info', 'wp-live-hype' ) ),
);
?>
<div class="wplh-kpis" role="list">
	<div class="wplh-kpi" role="listitem">
		<span class="wplh-kpi__label"><?php esc_html_e( 'Activity mode', 'wp-live-hype' ); ?></span>
		<span class="wplh-kpi__value wplh-kpi__value--text"><?php echo esc_html( $wplh_modes[ $wplh_mode ] ?? $wplh_mode ); ?></span>
		<span class="wplh-kpi__meta">
			<?php
			echo esc_html( false === $wplh_allowed ? __( 'No country configured', 'wp-live-hype' ) : ( null === $wplh_allowed ? __( 'All countries', 'wp-live-hype' ) : implode( ', ', array_map( array( Country::class, 'name' ), $wplh_allowed ) ) ) );
			?>
		</span>
	</div>
	<div class="wplh-kpi" role="listitem">
		<?php if ( 'synthetic' === $wplh_mode ) : ?>
			<span class="wplh-kpi__label"><?php esc_html_e( 'Products in rotation', 'wp-live-hype' ); ?></span>
			<span class="wplh-kpi__value"><?php echo esc_html( number_format_i18n( $wplh_pool ) ); ?></span>
			<span class="wplh-kpi__meta"><?php esc_html_e( 'No order data used', 'wp-live-hype' ); ?></span>
		<?php else : ?>
					<span class="wplh-kpi__label"><?php esc_html_e( 'Qualifying orders', 'wp-live-hype' ); ?></span>
			<span class="wplh-kpi__value"><?php echo esc_html( number_format_i18n( $wplh_purchases ) ); ?></span>
			<span class="wplh-kpi__meta"><?php echo esc_html( ucfirst( $wplh_window ) ); ?></span>
		<?php endif; ?>
	</div>
	<div class="wplh-kpi" role="listitem">
		<span class="wplh-kpi__label"><?php esc_html_e( 'Active sales', 'wp-live-hype' ); ?></span>
		<span class="wplh-kpi__value"><?php echo esc_html( number_format_i18n( (int) ( $wplh_stats['sales'] ?? 0 ) ) ); ?></span>
		<span class="wplh-kpi__meta"><?php echo $settings['type_sale'] ? esc_html__( 'Sale notifications on', 'wp-live-hype' ) : esc_html__( 'Sale notifications off', 'wp-live-hype' ); ?></span>
	</div>
	<div class="wplh-kpi" role="listitem">
		<span class="wplh-kpi__label"><?php esc_html_e( 'Shown (7 days)', 'wp-live-hype' ); ?></span>
		<span class="wplh-kpi__value"><?php echo esc_html( number_format_i18n( $wplh_totals['view'] ) ); ?></span>
		<span class="wplh-kpi__meta">
			<?php
			/* translators: 1: clicks, 2: click-through rate. */
			echo esc_html( sprintf( __( '%1$s clicks · %2$s%% CTR', 'wp-live-hype' ), number_format_i18n( $wplh_totals['click'] ), number_format_i18n( $wplh_totals['ctr'], 1 ) ) );
			?>
		</span>
	</div>
</div>

<div class="wplh-grid wplh-grid--2">
	<div>
		<?php Admin::card_start( __( 'Health & status', 'wp-live-hype' ) ); ?>
		<ul class="wplh-checks-list">
			<?php foreach ( $wplh_checks as $wplh_check ) : ?>
				<li class="wplh-check wplh-check--<?php echo esc_attr( $wplh_check[0] ); ?>">
					<span class="dashicons dashicons-<?php echo esc_attr( $wplh_icons[ $wplh_check[0] ][0] ); ?>" aria-hidden="true"></span>
					<span class="screen-reader-text"><?php echo esc_html( $wplh_icons[ $wplh_check[0] ][1] ); ?>:</span>
					<span><?php echo esc_html( $wplh_check[1] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<div class="wplh-meta-row">
			<span>
				<?php
				echo esc_html(
					! empty( $wplh_data['generated'] )
						/* translators: %s: human time difference. */
						? sprintf( __( 'Data refreshed %s ago', 'wp-live-hype' ), human_time_diff( (int) $wplh_data['generated'] ) )
						: __( 'Data not built yet', 'wp-live-hype' )
				);
				if ( $wplh_next ) {
					echo ' · ';
					/* translators: %s: human time difference. */
					echo esc_html( sprintf( __( 'next refresh in %s', 'wp-live-hype' ), human_time_diff( time(), (int) $wplh_next ) ) );
				}
				?>
			</span>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wplh_refresh' ); ?>
				<input type="hidden" name="action" value="wplh_refresh" />
				<input type="hidden" name="tab" value="dashboard" />
				<button type="submit" class="button"><span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e( 'Refresh data now', 'wp-live-hype' ); ?></button>
			</form>
		</div>
		<?php Admin::card_end(); ?>

		<?php Admin::card_start( __( 'Preview on your storefront', 'wp-live-hype' ), __( 'Opens your homepage with a sample notification that only you can see. It is clearly labelled as a preview and never shown to customers.', 'wp-live-hype' ) ); ?>
		<p class="wplh-actions">
			<?php
			$wplh_conv_previews = array(
				'product_cta' => __( 'Product CTA', 'wp-live-hype' ),
				'recommend'   => __( 'Recommendation', 'wp-live-hype' ),
				'promotion'   => __( 'Free shipping', 'wp-live-hype' ),
			);
			foreach ( array_merge( Admin::preview_types(), $wplh_conv_previews ) as $wplh_type => $wplh_label ) :
				?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'wplh_preview', $wplh_type, home_url( '/' ) ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $wplh_label ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'wp-live-hype' ); ?></span></a>
			<?php endforeach; ?>
		</p>
		<?php Admin::card_end(); ?>
	</div>

	<div>
		<?php Admin::card_start( __( 'Live preview', 'wp-live-hype' ), __( 'How notifications look with your saved display settings.', 'wp-live-hype' ) ); ?>
		<?php Admin::preview_panel( 'wplh-preview-dashboard' ); ?>
		<?php Admin::card_end(); ?>

		<?php Admin::card_start( __( 'Conversion health (7 days)', 'wp-live-hype' ), __( 'Measured from anonymous funnel counters only. Details on the Analytics tab.', 'wp-live-hype' ) ); ?>
		<?php require __DIR__ . '/part-health.php'; ?>
		<?php Admin::card_end(); ?>
	</div>
</div>
