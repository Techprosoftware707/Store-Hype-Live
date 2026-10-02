<?php
/**
 * Analytics tab: settings + anonymous aggregate report.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only report filter.
$afsp_period  = isset( $_GET['period'] ) ? absint( $_GET['period'] ) : 30;
$afsp_period  = in_array( $afsp_period, array( 7, 30, 90 ), true ) ? $afsp_period : 30;
$afsp_totals  = Analytics::totals( $afsp_period );
$afsp_types   = Analytics::by_type( $afsp_period );
$afsp_top     = Analytics::top_products( $afsp_period, 10 );
$afsp_daily   = Analytics::daily( $afsp_period );
$afsp_names   = array(
	'product_purchase' => __( 'Recent product purchase', 'always-final-social-proof' ),
	'purchase'         => __( 'Recent purchase', 'always-final-social-proof' ),
	'sale'             => __( 'Active sale', 'always-final-social-proof' ),
	'popular'          => __( 'Popular product', 'always-final-social-proof' ),
);
$afsp_ctr = static function ( $clicks, $views ) {
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
$afsp_chart = static function ( array $daily, string $metric, string $title, string $unit ) {
	$max   = 0;
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
	$dates = array_keys( $daily );
	$count = count( $dates );
	$label_at = array( 0, (int) floor( ( $count - 1 ) / 2 ), $count - 1 );
	?>
	<figure class="afsp-chart" aria-label="<?php echo esc_attr( $title ); ?>">
		<figcaption class="afsp-chart__title"><?php echo esc_html( $title ); ?></figcaption>
		<div class="afsp-chart__body">
			<div class="afsp-chart__axis" aria-hidden="true">
				<span><?php echo esc_html( number_format_i18n( $axis ) ); ?></span>
				<span>0</span>
			</div>
			<div class="afsp-chart__plot">
				<div class="afsp-chart__bars">
					<?php
					foreach ( $daily as $date => $counts ) :
						$value  = (int) $counts[ $metric ];
						$height = $axis > 0 ? round( $value / $axis * 100, 2 ) : 0;
						$label  = date_i18n( get_option( 'date_format' ), strtotime( $date ) ) . ': ' . number_format_i18n( $value ) . ' ' . $unit;
						?>
						<span class="afsp-chart__col" tabindex="0" role="img" aria-label="<?php echo esc_attr( $label ); ?>" data-tip="<?php echo esc_attr( $label ); ?>">
							<span class="afsp-chart__bar" style="height:<?php echo esc_attr( (string) $height ); ?>%"></span>
						</span>
					<?php endforeach; ?>
				</div>
				<div class="afsp-chart__x" aria-hidden="true">
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
<form method="post" action="options.php" class="afsp-form afsp-form--inline">
	<?php settings_fields( Settings::GROUP ); ?>
	<input type="hidden" name="<?php echo esc_attr( Admin::name( '_tab' ) ); ?>" value="analytics" />
	<?php
	Admin::card_start( __( 'Anonymous analytics', 'always-final-social-proof' ), __( 'Counts notification views, clicks and dismissals per day, by type and product. No IP addresses, cookies, user IDs or personal data are collected, and nothing is sent to third parties.', 'always-final-social-proof' ) );
	Admin::toggle( 'analytics_enabled', __( 'Collect anonymous analytics', 'always-final-social-proof' ) );
	Admin::select(
		'analytics_retention',
		__( 'Keep data for', 'always-final-social-proof' ),
		array(
			30  => __( '30 days', 'always-final-social-proof' ),
			90  => __( '90 days', 'always-final-social-proof' ),
			180 => __( '180 days', 'always-final-social-proof' ),
			365 => __( '1 year', 'always-final-social-proof' ),
		)
	);
	submit_button( __( 'Save changes', 'always-final-social-proof' ), 'primary', 'submit', false );
	Admin::card_end();
	?>
</form>

<div class="afsp-filter-row" role="group" aria-label="<?php esc_attr_e( 'Reporting period', 'always-final-social-proof' ); ?>">
	<?php foreach ( array( 7, 30, 90 ) as $afsp_days ) : ?>
		<a class="afsp-seg <?php echo $afsp_days === $afsp_period ? 'is-active' : ''; ?>" href="<?php echo esc_url( Admin::url( 'analytics', array( 'period' => $afsp_days ) ) ); ?>" <?php echo $afsp_days === $afsp_period ? 'aria-current="true"' : ''; ?>>
			<?php
			/* translators: %d: number of days. */
			echo esc_html( sprintf( __( 'Last %d days', 'always-final-social-proof' ), $afsp_days ) );
			?>
		</a>
	<?php endforeach; ?>
</div>

<div class="afsp-kpis" role="list">
	<div class="afsp-kpi" role="listitem"><span class="afsp-kpi__label"><?php esc_html_e( 'Notifications shown', 'always-final-social-proof' ); ?></span><span class="afsp-kpi__value"><?php echo esc_html( number_format_i18n( $afsp_totals['view'] ) ); ?></span></div>
	<div class="afsp-kpi" role="listitem"><span class="afsp-kpi__label"><?php esc_html_e( 'Clicks', 'always-final-social-proof' ); ?></span><span class="afsp-kpi__value"><?php echo esc_html( number_format_i18n( $afsp_totals['click'] ) ); ?></span></div>
	<div class="afsp-kpi" role="listitem"><span class="afsp-kpi__label"><?php esc_html_e( 'Click-through rate', 'always-final-social-proof' ); ?></span><span class="afsp-kpi__value"><?php echo esc_html( $afsp_ctr( $afsp_totals['click'], $afsp_totals['view'] ) ); ?></span></div>
	<div class="afsp-kpi" role="listitem"><span class="afsp-kpi__label"><?php esc_html_e( 'Dismissed', 'always-final-social-proof' ); ?></span><span class="afsp-kpi__value"><?php echo esc_html( number_format_i18n( $afsp_totals['dismiss'] ) ); ?></span></div>
</div>

<div class="afsp-grid afsp-grid--2">
	<?php Admin::card_start( '' ); ?>
	<?php $afsp_chart( $afsp_daily, 'view', __( 'Notifications shown per day', 'always-final-social-proof' ), __( 'shown', 'always-final-social-proof' ) ); ?>
	<?php Admin::card_end(); ?>
	<?php Admin::card_start( '' ); ?>
	<?php $afsp_chart( $afsp_daily, 'click', __( 'Clicks per day', 'always-final-social-proof' ), __( 'clicks', 'always-final-social-proof' ) ); ?>
	<?php Admin::card_end(); ?>
</div>

<?php Admin::card_start( '' ); ?>
<details class="afsp-details">
	<summary><?php esc_html_e( 'View daily data as a table', 'always-final-social-proof' ); ?></summary>
	<table class="widefat striped afsp-table">
		<thead><tr><th scope="col"><?php esc_html_e( 'Date', 'always-final-social-proof' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Shown', 'always-final-social-proof' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Clicks', 'always-final-social-proof' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'CTR', 'always-final-social-proof' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( array_reverse( $afsp_daily, true ) as $afsp_date => $afsp_counts ) : ?>
				<tr>
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $afsp_date ) ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $afsp_counts['view'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $afsp_counts['click'] ) ); ?></td>
					<td class="num"><?php echo esc_html( $afsp_ctr( $afsp_counts['click'], $afsp_counts['view'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</details>
<?php Admin::card_end(); ?>

<div class="afsp-grid afsp-grid--2">
	<?php Admin::card_start( __( 'By notification type', 'always-final-social-proof' ) ); ?>
	<table class="widefat striped afsp-table">
		<thead><tr><th scope="col"><?php esc_html_e( 'Type', 'always-final-social-proof' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Shown', 'always-final-social-proof' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Clicks', 'always-final-social-proof' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'CTR', 'always-final-social-proof' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Dismissed', 'always-final-social-proof' ); ?></th></tr></thead>
		<tbody>
			<?php
			uasort(
				$afsp_types,
				static function ( $a, $b ) {
					return $b['view'] <=> $a['view'];
				}
			);
			foreach ( $afsp_types as $afsp_type => $afsp_counts ) :
				?>
				<tr>
					<td><?php echo esc_html( $afsp_names[ $afsp_type ] ?? $afsp_type ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $afsp_counts['view'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $afsp_counts['click'] ) ); ?></td>
					<td class="num"><?php echo esc_html( $afsp_ctr( $afsp_counts['click'], $afsp_counts['view'] ) ); ?></td>
					<td class="num"><?php echo esc_html( number_format_i18n( $afsp_counts['dismiss'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php Admin::card_end(); ?>

	<?php Admin::card_start( __( 'Most clicked products', 'always-final-social-proof' ) ); ?>
	<?php if ( empty( $afsp_top ) ) : ?>
		<p class="afsp-empty"><?php esc_html_e( 'No product activity recorded in this period yet.', 'always-final-social-proof' ); ?></p>
	<?php else : ?>
		<table class="widefat striped afsp-table">
			<thead><tr><th scope="col"><?php esc_html_e( 'Product', 'always-final-social-proof' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Clicks', 'always-final-social-proof' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Shown', 'always-final-social-proof' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'CTR', 'always-final-social-proof' ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( $afsp_top as $afsp_row ) : ?>
					<?php $afsp_title = get_the_title( $afsp_row['product_id'] ); ?>
					<tr>
						<td>
							<?php if ( current_user_can( 'edit_post', $afsp_row['product_id'] ) ) : ?>
								<a href="<?php echo esc_url( (string) get_edit_post_link( $afsp_row['product_id'] ) ); ?>"><?php echo esc_html( '' !== $afsp_title ? $afsp_title : '#' . $afsp_row['product_id'] ); ?></a>
							<?php else : ?>
								<?php echo esc_html( '' !== $afsp_title ? $afsp_title : '#' . $afsp_row['product_id'] ); ?>
							<?php endif; ?>
						</td>
						<td class="num"><?php echo esc_html( number_format_i18n( $afsp_row['clicks'] ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $afsp_row['views'] ) ); ?></td>
						<td class="num"><?php echo esc_html( $afsp_ctr( $afsp_row['clicks'], $afsp_row['views'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php Admin::card_end(); ?>
</div>

<?php Admin::card_start( __( 'Reset', 'always-final-social-proof' ) ); ?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="afsp-reset-analytics">
	<?php wp_nonce_field( 'afsp_reset_analytics' ); ?>
	<input type="hidden" name="action" value="afsp_reset_analytics" />
	<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete all analytics data', 'always-final-social-proof' ); ?></button>
</form>
<?php Admin::card_end(); ?>
