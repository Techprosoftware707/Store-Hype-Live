<?php
/**
 * A/B testing tab: experiment setup and results.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_days       = (int) Settings::get( 'analytics_retention' );
$wplh_report     = Conversion::report( $wplh_days );
$wplh_experiment = Conversion::experiment();
$wplh_positions  = array(
	'bottom-left'  => __( 'Bottom left', 'wp-live-hype' ),
	'bottom-right' => __( 'Bottom right', 'wp-live-hype' ),
	'top-left'     => __( 'Top left', 'wp-live-hype' ),
	'top-right'    => __( 'Top right', 'wp-live-hype' ),
);
$wplh_animations = array(
	'none'      => __( 'None', 'wp-live-hype' ),
	'fade'      => __( 'Fade', 'wp-live-hype' ),
	'slide'     => __( 'Slide', 'wp-live-hype' ),
	'slidefade' => __( 'Slide + fade', 'wp-live-hype' ),
	'scale'     => __( 'Scale', 'wp-live-hype' ),
);

Admin::form_start( 'abtest' );

Admin::card_start( __( 'Experiment', 'wp-live-hype' ), __( 'Each browser is randomly and anonymously assigned to variant A (your current settings) or B (the change below), 50/50, and keeps its variant. Test one variable at a time; changing the experiment starts fresh results.', 'wp-live-hype' ) );
Admin::toggle( 'ab_enabled', __( 'Run an A/B test', 'wp-live-hype' ) );
echo '<div data-show-when="ab_enabled">';
Admin::radios(
	'ab_variable',
	__( 'Variable to test', 'wp-live-hype' ),
	array(
		'cta'       => array( __( 'Button text', 'wp-live-hype' ), __( 'B uses different text on product buttons (View product / Explore / See offer).', 'wp-live-hype' ) ),
		'copy'      => array( __( 'Message copy', 'wp-live-hype' ), __( 'B uses different wording for featured and spotlight messages.', 'wp-live-hype' ) ),
		'position'  => array( __( 'Position', 'wp-live-hype' ), __( 'B appears in a different desktop corner.', 'wp-live-hype' ) ),
		'animation' => array( __( 'Animation', 'wp-live-hype' ), __( 'B uses a different entrance animation.', 'wp-live-hype' ) ),
		'frequency' => array( __( 'Frequency', 'wp-live-hype' ), __( 'B uses a different intensity preset.', 'wp-live-hype' ) ),
		'sound'     => array( __( 'Sound', 'wp-live-hype' ), __( 'B reverses the desktop sound setting (on ↔ off).', 'wp-live-hype' ) ),
		'image'     => array( __( 'Product image', 'wp-live-hype' ), __( 'B reverses the "show product image" setting.', 'wp-live-hype' ) ),
	)
);
echo '<div data-show-when-value="ab_variable=cta">';
Admin::text( 'ab_cta_b', __( 'Variant B button text', 'wp-live-hype' ), __( 'Required for this test. Keep it truthful, e.g. "See details".', 'wp-live-hype' ), __( 'See details', 'wp-live-hype' ) );
echo '</div><div data-show-when-value="ab_variable=copy">';
Admin::template_editor( 'ab_copy', 'ab_copy_b', __( 'Variant B wording', 'wp-live-hype' ), (string) Settings::get( 'ab_copy_b' ), __( 'Required for this test. One line per template, each with {product}.', 'wp-live-hype' ) );
echo '</div><div data-show-when-value="ab_variable=position">';
Admin::select( 'ab_position_b', __( 'Variant B position', 'wp-live-hype' ), $wplh_positions );
echo '</div><div data-show-when-value="ab_variable=animation">';
Admin::select( 'ab_animation_b', __( 'Variant B animation', 'wp-live-hype' ), $wplh_animations );
echo '</div><div data-show-when-value="ab_variable=frequency">';
Admin::select(
	'ab_frequency_b',
	__( 'Variant B preset', 'wp-live-hype' ),
	array(
		'soft'       => __( 'Soft', 'wp-live-hype' ),
		'balanced'   => __( 'Balanced', 'wp-live-hype' ),
		'aggressive' => __( 'Aggressive', 'wp-live-hype' ),
	)
);
echo '</div>';
if ( Settings::get( 'ab_enabled' ) && ! $wplh_experiment ) {
	echo '<div class="wplh-callout wplh-callout--warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span><p>' . esc_html__( 'The test is not running: variant B has no value for the selected variable.', 'wp-live-hype' ) . '</p></div>';
}
echo '</div>';
Admin::card_end();

Admin::form_end();

Admin::card_start( __( 'Results', 'wp-live-hype' ), __( 'Rates are per notification impression. A difference is marked significant only when both variants have at least 100 impressions and a two-proportion z-test gives p < 0.05. Results show association during the test period; other factors can also play a part.', 'wp-live-hype' ) );
if ( ! $wplh_experiment || '' === $wplh_report['ab_started'] ) {
	echo '<p class="wplh-empty">' . esc_html__( 'No experiment is running.', 'wp-live-hype' ) . '</p>';
} else {
	$wplh_a    = $wplh_report['variants']['a'];
	$wplh_b    = $wplh_report['variants']['b'];
	$wplh_rows = array(
		'interaction' => array( __( 'Clicks + button clicks', 'wp-live-hype' ), $wplh_a['click'] + $wplh_a['cta'], $wplh_b['click'] + $wplh_b['cta'] ),
		'cta'         => array( __( 'Button clicks', 'wp-live-hype' ), $wplh_a['cta'], $wplh_b['cta'] ),
		'dismiss'     => array( __( 'Dismissals', 'wp-live-hype' ), $wplh_a['dismiss'], $wplh_b['dismiss'] ),
		'atc'         => array( __( 'Add to cart after a click', 'wp-live-hype' ), $wplh_a['atc_attr'], $wplh_b['atc_attr'] ),
		'checkout'    => array( __( 'Checkout after a click', 'wp-live-hype' ), $wplh_a['checkout_attr'], $wplh_b['checkout_attr'] ),
		'purchase'    => array( __( 'Attributed orders', 'wp-live-hype' ), $wplh_a['purchase_attr'], $wplh_b['purchase_attr'] ),
	);
	$wplh_pct  = static function ( int $x, int $n ): string {
		return $n > 0 ? number_format_i18n( $x / $n * 100, 2 ) . '%' : '—';
	};
	echo '<p class="wplh-meta-row">' . esc_html(
		sprintf(
			/* translators: 1: start date, 2: impressions A, 3: impressions B. */
			__( 'Since %1$s · Impressions: A %2$s, B %3$s', 'wp-live-hype' ),
			date_i18n( get_option( 'date_format' ), strtotime( $wplh_report['ab_started'] ) ),
			number_format_i18n( $wplh_a['impression'] ),
			number_format_i18n( $wplh_b['impression'] )
		)
	) . '</p>';
	?>
	<table class="widefat wplh-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Metric', 'wp-live-hype' ); ?></th>
				<th scope="col"><?php esc_html_e( 'A (current)', 'wp-live-hype' ); ?></th>
				<th scope="col"><?php esc_html_e( 'B (variant)', 'wp-live-hype' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Result', 'wp-live-hype' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $wplh_rows as $wplh_row ) :
				$wplh_test = Conversion::ztest( (int) $wplh_row[1], (int) $wplh_a['impression'], (int) $wplh_row[2], (int) $wplh_b['impression'] );
				if ( ! $wplh_test['enough'] ) {
					$wplh_verdict = __( 'Collecting data', 'wp-live-hype' );
					$wplh_class   = '';
				} elseif ( ! $wplh_test['significant'] ) {
					$wplh_verdict = __( 'No significant difference yet', 'wp-live-hype' );
					$wplh_class   = '';
				} else {
					$wplh_verdict = $wplh_test['z'] > 0 ? __( 'B higher (significant)', 'wp-live-hype' ) : __( 'A higher (significant)', 'wp-live-hype' );
					$wplh_class   = 'wplh-pill wplh-pill--on';
				}
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $wplh_row[0] ); ?></th>
					<td><?php echo esc_html( number_format_i18n( $wplh_row[1] ) . ' · ' . $wplh_pct( (int) $wplh_row[1], (int) $wplh_a['impression'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $wplh_row[2] ) . ' · ' . $wplh_pct( (int) $wplh_row[2], (int) $wplh_b['impression'] ) ); ?></td>
					<td><span class="<?php echo esc_attr( $wplh_class ); ?>"><?php echo esc_html( $wplh_verdict ); ?></span></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
Admin::card_end();
