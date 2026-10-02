<?php
/**
 * Conversion funnel report: store-wide, direct and attributed metrics.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var int   $wplh_period      Days.
 * @var array $wplh_conv_report Conversion::report() result.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_e      = $wplh_conv_report['events'];
$wplh_orders = Conversion::order_report( $wplh_period );
$wplh_rate   = static function ( int $x, int $n ): string {
	return $n > 0 ? number_format_i18n( $x / $n * 100, 1 ) . '%' : '—';
};
$wplh_steps  = array(
	array( __( 'Sessions', 'wp-live-hype' ), (int) $wplh_e['session'], null ),
	array( __( 'Product views', 'wp-live-hype' ), (int) $wplh_e['product_view'], (int) $wplh_e['product_view_attr'] ),
	array( __( 'Add to cart', 'wp-live-hype' ), (int) $wplh_e['atc'], (int) $wplh_e['atc_attr'] ),
	array( __( 'Cart views', 'wp-live-hype' ), (int) $wplh_e['cart'], null ),
	array( __( 'Checkout starts', 'wp-live-hype' ), (int) $wplh_e['checkout'], (int) $wplh_e['checkout_attr'] ),
	// All paid WooCommerce orders, including visits without the script: shown without a funnel bar.
	array( __( 'Orders (all WooCommerce orders)', 'wp-live-hype' ), (int) $wplh_orders['store_orders'], (int) $wplh_orders['attributed'], false ),
);
$wplh_top_value = max( 1, (int) $wplh_e['session'], (int) $wplh_e['product_view'] );

Admin::card_start( __( 'Conversion funnel', 'wp-live-hype' ), __( 'Store-wide counts come from every visit where WP Live Hype runs (orders come from WooCommerce). "After a click" counts the same steps for visitors who clicked a WP Live Hype message within the attribution window. That is an association: it does not show that the message caused the action.', 'wp-live-hype' ) );
?>
<table class="widefat wplh-table wplh-funnel">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Step', 'wp-live-hype' ); ?></th>
			<th scope="col" class="num"><?php esc_html_e( 'Store-wide', 'wp-live-hype' ); ?></th>
			<th scope="col" class="wplh-funnel__barcol"><span class="screen-reader-text"><?php esc_html_e( 'Relative volume', 'wp-live-hype' ); ?></span></th>
			<th scope="col" class="num"><?php esc_html_e( 'After a click', 'wp-live-hype' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $wplh_steps as $wplh_step ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( $wplh_step[0] ); ?></th>
				<td class="num"><?php echo esc_html( number_format_i18n( $wplh_step[1] ) ); ?></td>
				<td class="wplh-funnel__barcol">
					<?php if ( ! isset( $wplh_step[3] ) ) : ?>
						<span class="wplh-funnel__bar" aria-hidden="true"><span style="width:<?php echo esc_attr( (string) min( 100, round( $wplh_step[1] / $wplh_top_value * 100, 1 ) ) ); ?>%"></span></span>
					<?php endif; ?>
				</td>
				<td class="num"><?php echo null === $wplh_step[2] ? '—' : esc_html( number_format_i18n( $wplh_step[2] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php
if ( Settings::get( 'attribution' ) ) {
	echo '<p class="wplh-meta-row">' . esc_html(
		sprintf(
			/* translators: 1: attributed revenue, 2: number of attributed orders. */
			__( 'Attributed order revenue: %1$s across %2$s orders (orders placed within the attribution window after a click).', 'wp-live-hype' ),
			Products::format_price( (float) $wplh_orders['revenue'] ),
			number_format_i18n( (int) $wplh_orders['attributed'] )
		)
	) . '</p>';
} else {
	echo '<p class="wplh-meta-row">' . esc_html__( 'Attribution is off (Conversion tab), so "after a click" columns stay empty.', 'wp-live-hype' ) . '</p>';
}
Admin::card_end();
?>
<div class="wplh-grid wplh-grid--2">
	<?php
	Admin::card_start( __( 'Direct notification metrics', 'wp-live-hype' ) );
	$wplh_imp = (int) $wplh_e['impression'];
	?>
	<table class="widefat striped wplh-table">
		<tbody>
			<tr><th scope="row"><?php esc_html_e( 'Impressions', 'wp-live-hype' ); ?></th><td class="num"><?php echo esc_html( number_format_i18n( $wplh_imp ) ); ?></td><td class="num"></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Context-relevant impressions', 'wp-live-hype' ); ?></th><td class="num"><?php echo esc_html( number_format_i18n( (int) $wplh_e['relevant'] ) ); ?></td><td class="num"><?php echo esc_html( $wplh_rate( (int) $wplh_e['relevant'], $wplh_imp ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Message clicks', 'wp-live-hype' ); ?></th><td class="num"><?php echo esc_html( number_format_i18n( (int) $wplh_e['click'] ) ); ?></td><td class="num"><?php echo esc_html( $wplh_rate( (int) $wplh_e['click'], $wplh_imp ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Button clicks', 'wp-live-hype' ); ?></th><td class="num"><?php echo esc_html( number_format_i18n( (int) $wplh_e['cta'] ) ); ?></td><td class="num"><?php echo esc_html( $wplh_rate( (int) $wplh_e['cta'], $wplh_imp ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Dismissals', 'wp-live-hype' ); ?></th><td class="num"><?php echo esc_html( number_format_i18n( (int) $wplh_e['dismiss'] ) ); ?></td><td class="num"><?php echo esc_html( $wplh_rate( (int) $wplh_e['dismiss'], $wplh_imp ) ); ?></td></tr>
		</tbody>
	</table>
	<?php
	Admin::card_end();

	Admin::card_start( __( 'Mobile vs desktop', 'wp-live-hype' ) );
	$wplh_dev = $wplh_conv_report['devices'];
	?>
	<table class="widefat striped wplh-table">
		<thead><tr><th scope="col"></th><th scope="col" class="num"><?php esc_html_e( 'Mobile', 'wp-live-hype' ); ?></th><th scope="col" class="num"><?php esc_html_e( 'Desktop', 'wp-live-hype' ); ?></th></tr></thead>
		<tbody>
			<tr><th scope="row"><?php esc_html_e( 'Impressions', 'wp-live-hype' ); ?></th><td class="num"><?php echo esc_html( number_format_i18n( (int) $wplh_dev['m']['impression'] ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( (int) $wplh_dev['d']['impression'] ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Interaction rate', 'wp-live-hype' ); ?></th><td class="num"><?php echo esc_html( $wplh_rate( (int) ( $wplh_dev['m']['click'] + $wplh_dev['m']['cta'] ), (int) $wplh_dev['m']['impression'] ) ); ?></td><td class="num"><?php echo esc_html( $wplh_rate( (int) ( $wplh_dev['d']['click'] + $wplh_dev['d']['cta'] ), (int) $wplh_dev['d']['impression'] ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Dismiss rate', 'wp-live-hype' ); ?></th><td class="num"><?php echo esc_html( $wplh_rate( (int) $wplh_dev['m']['dismiss'], (int) $wplh_dev['m']['impression'] ) ); ?></td><td class="num"><?php echo esc_html( $wplh_rate( (int) $wplh_dev['d']['dismiss'], (int) $wplh_dev['d']['impression'] ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Add to cart (store-wide)', 'wp-live-hype' ); ?></th><td class="num"><?php echo esc_html( number_format_i18n( (int) $wplh_dev['m']['atc'] ) ); ?></td><td class="num"><?php echo esc_html( number_format_i18n( (int) $wplh_dev['d']['atc'] ) ); ?></td></tr>
		</tbody>
	</table>
	<?php Admin::card_end(); ?>
</div>
<?php
Admin::card_start( __( 'Conversion health', 'wp-live-hype' ), __( 'Observations computed only from the counters above. There is no composite score.', 'wp-live-hype' ) );
require __DIR__ . '/part-health.php';
Admin::card_end();
