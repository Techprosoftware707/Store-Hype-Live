<?php
/**
 * Analytics tab: settings + anonymous aggregate report.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filter.
$wplh_period = isset( $_GET['period'] ) ? absint( $_GET['period'] ) : 30;
$wplh_period = in_array( $wplh_period, array( 7, 30, 90 ), true ) ? $wplh_period : 30;
$wplh_totals = Analytics::totals( $wplh_period );
$wplh_types  = Analytics::by_type( $wplh_period );
$wplh_top    = Analytics::top_products( $wplh_period, 10 );
$wplh_daily  = Analytics::daily( $wplh_period );
$wplh_names  = array(
	'product_purchase' => __( 'Recent product purchase', 'wp-live-hype' ),
	'purchase'         => __( 'Recent purchase', 'wp-live-hype' ),
	'sale'             => __( 'Active sale', 'wp-live-hype' ),
	'popular'          => __( 'Popular product', 'wp-live-hype' ),
	'featured'         => __( 'Featured product (synthetic)', 'wp-live-hype' ),
	'explore'          => __( 'Spotlight (synthetic)', 'wp-live-hype' ),
	'location'         => __( 'Shipping region (synthetic)', 'wp-live-hype' ),
	'sale_promo'       => __( 'Sale (synthetic rotation)', 'wp-live-hype' ),
);
$wplh_ctr    = static function ( $clicks, $views ) {
	return $views > 0 ? number_format_i18n( $clicks / $views * 100, 1 ) . '%' : '—';
};

/**
 * Render a single-series daily column chart (HTML/CSS, no library).
 *
 * @param array  $daily  Y-m-d => counts.
 * @param string $metric view|click.
 * @param string $title  Chart title.
 * @param string $unit   Tooltip unit label.
 */
$wplh_chart = static function ( array $daily, string $metric, string $title, string $unit ) {
	$max = 0;
	foreach ( $daily as $counts ) {
		$max = max( $max, (int) $counts[ $metric ] );
	}
	// Clean axis maximum: 1, 2, 5 × 10^n.
	$axis = 1;
	if ( $max > 0 ) {
		$magnitude = pow( 10, floor( log10( $max ) ) );
		foreach ( array( 1, 2, 5, 10 ) as $step ) {
			if ( $step * $magnitude >= $max ) {
				$axis = (int) ( $step * $magnitude );
				break;
			}
		}
	}
	$dates    = array_keys( $daily );
	$count    = count( $dates );
	$label_at = array( 0, (int) floor( ( $count - 1 ) / 2 ), $count - 1 );
	?>
	<figure class="wplh-chart" aria-label="<?php echo esc_attr( $title ); ?>">
		<figcaption class="wplh-chart__title"><?php echo esc_html( $title ); ?></figcaption>
		<div class="wplh-chart__body">
			<div class="wplh-chart__axis" aria-hidden="true">
				<span><?php echo esc_html( number_format_i18n( $axis ) ); ?></span>
				<span>0</span>
			</div>
			<div class="wplh-chart__plot">
				<div class="wplh-chart__bars">
					<?php
					foreach ( $daily as $date => $counts ) :
						$value  = (int) $counts[ $metric ];
						$height = $axis > 0 ? round( $value / $axis * 100, 2 ) : 0;
						$label  = date_i18n( get_option( 'date_format' ), strtotime( $date ) ) . ': ' . number_format_i18n( $value ) . ' ' . $unit;
						?>
						<span class="wplh-chart__col" tabindex="0" role="img" aria-label="<?php echo esc_attr( $label ); ?>" data-tip="<?php echo esc_attr( $label ); ?>">
							<span class="wplh-chart__bar" style="height:<?php echo esc_attr( (string) $height ); ?>%"></span>
						</span>
					<?php endforeach; ?>
				</div>
				<div class="wplh-chart__x" aria-hidden="true">
					<?php foreach ( array_unique( $label_at ) as $index ) : ?>
						<span style="left:<?php echo esc_attr( (string) ( $count > 1 ? round( ( $index + 0.5 ) / $count * 100, 2 ) : 50 ) ); ?>%"><?php echo esc_html( date_i18n( 'M j', strtotime( $dates[ $index ] ) ) ); ?></span>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</figure>
	<?php
};
?>
<form method="post" action="options.php" class="wplh-form wplh-form--inline">
	<?php settings_fields( Settings::GROUP ); ?>
	<input type="hidden" name="<?php echo esc_attr( Admin::name( '_tab' ) ); ?>" value="analytics" />
	<?php
	Admin::card_start( __( 'Anonymous analytics', 'wp-live-hype' ), __( 'Counts notification views, clicks and dismissals per day, by type and product. No IP addresses, cookies, user IDs or personal data are collected, and nothing is sent to third parties.', 'wp-live-hype' ) );
	Admin::toggle( 'analytics_enabled', __( 'Collect anonymous analytics', 'wp-live-hype' ) );
	Admin::select(
		'analytics_retention',
		__( 'Keep data for', 'wp-live-hype' ),
		array(
			30  => __( '30 days', 'wp-live-hype' ),
			90  => __( '90 days', 'wp-live-hype' ),
			180 => __( '180 days', 'wp-live-hype' ),
			365 => __( '1 year', 'wp-live-hype' ),
		)
	);
	submit_button( __( 'Save changes', 'wp-live-hype' ), 'primary', 'submit', false );
	Admin::card_end();
	?>
</form>

<div class="wplh-filter-row" role="group" aria-label="<?php esc_attr_e( 'Reporting period', 'wp-live-hype' ); ?>">
	<?php foreach ( array( 7, 30, 90 ) as $wplh_days ) : ?>
		<a class="wplh-seg <?php echo $wplh_days === $wplh_period ? 'is-active' : ''; ?>" href="<?php echo esc_url( Admin::url( 'analytics', array( 'period' => $wplh_days ) ) ); ?>" <?php echo $wplh_days === $wplh_period ? 'aria-current="true"' : ''; ?>>
			<?php
			/* translators: %d: number of days. */
			echo esc_html( sprintf( __( 'Last %d days', 'wp-live-hype' ), $wplh_days ) );
			?>
		</a>
	<?php endforeach; ?>
</div>

<div class="wplh-kpis" role="list">
	<div class="wplh-kpi" role="listitem"><span class="wplh-kpi__label"><?php esc_html_e( 'Notifications shown', 'wp-live-hype' ); ?></span><span class="wplh-kpi__value"><?php echo esc_html( number_format_i18n( $wplh_totals['view'] ) ); ?></span></div>
	<div class="wplh-kpi" role="listitem"><span class="wplh-kpi__label"><?php esc_html_e( 'Clicks', 'wp-live-hype' ); ?></span><span class="wplh-kpi__value"><?php echo esc_html( number_format_i18n( $wplh_totals['click'] ) ); ?></span></div>
	<div class="wplh-kpi" role="listitem"><span class="wplh-kpi__label"><?php esc_html_e( 'Click-through rate', 'wp-live-hype' ); ?></span><span class="wplh-kpi__value"><?php echo esc_html( $wplh_ctr( $wplh_totals['click'], $wplh_totals['view'] ) ); ?></span></div>
	<div class="wplh-kpi" role="listitem"><span class="wplh-kpi__label"><?php esc_html_e( 'Dismissed', 'wp-live-hype' ); ?></span><span class="wplh-kpi__value"><?php echo esc_html( number_format_i18n( $wplh_totals['dismiss'] ) ); ?></span></div>
</div>

<div class="wplh-grid wplh-grid--2">
	<?php Admin::card_start( '' ); ?>
	<?php $wplh_chart( $wplh_daily, 'view', __( 'Notifications shown per day', 'wp-live-hype' ), __( 'shown', 'wp-live-hype' ) ); ?>
	<?php Admin::card_end(); ?>
	<?php Admin::card_start( '' ); ?>
	<?php $wplh_chart( $wplh_daily, 'click', __( 'Clicks per day', 'wp-live-hype' ), __( 'clicks', 'wp-live-hype' ) ); ?>
	<?php Admin::card_end(); ?>
</div>

<?php Admin::card_start( '' ); ?>
<details class="wplh-details">
	<summary><?php esc_html_e( 'View daily data as a table', 'wp-live-hype' ); ?></summary>
	<table class="widefat striped wplh-table">
		<thead><tr><th scope="col"><?php esc_html_e( 'Date', 'wp-live-hype' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Shown', 'wp-live-hype' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Clicks', 'wp-live-hype' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'CTR', 'wp-live-hype' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( array_reverse( $wplh_daily, true ) as $wplh_date => $wplh_counts ) : ?>
				<tr>
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $wplh_date ) ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $wplh_counts['view'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $wplh_counts['click'] ) ); ?></td>
					<td class="num"><?php echo esc_html( $wplh_ctr( $wplh_counts['click'], $wplh_counts['view'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</details>
<?php Admin::card_end(); ?>

<div class="wplh-grid wplh-grid--2">
	<?php Admin::card_start( __( 'By notification type', 'wp-live-hype' ) ); ?>
	<table class="widefat striped wplh-table">
		<thead><tr><th scope="col"><?php esc_html_e( 'Type', 'wp-live-hype' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Shown', 'wp-live-hype' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Clicks', 'wp-live-hype' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'CTR', 'wp-live-hype' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Dismissed', 'wp-live-hype' ); ?></th></tr></thead>
		<tbody>
			<?php
			uasort(
				$wplh_types,
				static function ( $a, $b ) {
					return $b['view'] <=> $a['view'];
				}
			);
			foreach ( $wplh_types as $wplh_type => $wplh_counts ) :
				?>
				<tr>
					<td><?php echo esc_html( $wplh_names[ $wplh_type ] ?? $wplh_type ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $wplh_counts['view'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $wplh_counts['click'] ) ); ?></td>
					<td class="num"><?php echo esc_html( $wplh_ctr( $wplh_counts['click'], $wplh_counts['view'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $wplh_counts['dismiss'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php Admin::card_end(); ?>

	<?php Admin::card_start( __( 'Most clicked products', 'wp-live-hype' ) ); ?>
	<?php if ( empty( $wplh_top ) ) : ?>
		<p class="wplh-empty"><?php esc_html_e( 'No product activity recorded in this period yet.', 'wp-live-hype' ); ?></p>
	<?php else : ?>
		<table class="widefat striped wplh-table">
			<thead><tr><th scope="col"><?php esc_html_e( 'Product', 'wp-live-hype' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Clicks', 'wp-live-hype' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Shown', 'wp-live-hype' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'CTR', 'wp-live-hype' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $wplh_top as $wplh_row ) : ?>
					<?php
					$wplh_title = 'product' === get_post_type( $wplh_row['product_id'] ) ? wp_strip_all_tags( get_the_title( $wplh_row['product_id'] ) ) : '';
					/* translators: %d: product ID. */
					$wplh_title = '' !== $wplh_title ? $wplh_title : sprintf( __( 'Deleted product #%d', 'wp-live-hype' ), $wplh_row['product_id'] );
					?>
					<tr>
						<td>
							<?php if ( 'product' === get_post_type( $wplh_row['product_id'] ) && current_user_can( 'edit_post', $wplh_row['product_id'] ) ) : ?>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $wplh_row['product_id'] ) ); ?>"><?php echo esc_html( $wplh_title ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $wplh_title ); ?>
							<?php endif; ?>
						</td>
						<td class="num"><?php echo esc_html( number_format_i18n( $wplh_row['clicks'] ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $wplh_row['views'] ) ); ?></td>
						<td class="num"><?php echo esc_html( $wplh_ctr( $wplh_row['clicks'], $wplh_row['views'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php Admin::card_end(); ?>
</div>

<?php Admin::card_start( __( 'Reset', 'wp-live-hype' ) ); ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wplh-reset-analytics">
	<?php wp_nonce_field( 'wplh_reset_analytics' ); ?>
	<input type="hidden" name="action" value="wplh_reset_analytics" />
	<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete all analytics data', 'wp-live-hype' ); ?></button>
</form>
<?php Admin::card_end(); ?>
