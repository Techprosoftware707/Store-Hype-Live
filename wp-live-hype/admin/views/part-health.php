<?php
/**
 * Conversion Health panel (measured metrics only).
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $wplh_conv_report Conversion::report() result.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_status_labels = array(
	'good' => __( 'Healthy', 'wp-live-hype' ),
	'warn' => __( 'Needs attention', 'wp-live-hype' ),
	'info' => __( 'Measured', 'wp-live-hype' ),
);
?>
<ul class="wplh-health">
	<?php foreach ( Conversion::health( $wplh_conv_report ) as $wplh_line ) : ?>
		<li class="wplh-health__item wplh-health__item--<?php echo esc_attr( $wplh_line['status'] ); ?>">
			<span class="wplh-health__status"><?php echo esc_html( $wplh_status_labels[ $wplh_line['status'] ] ?? '' ); ?></span>
			<span class="wplh-health__label"><?php echo esc_html( $wplh_line['label'] ); ?></span>
			<span class="wplh-health__value"><?php echo esc_html( $wplh_line['value'] ); ?></span>
			<span class="wplh-health__note"><?php echo esc_html( $wplh_line['note'] ); ?></span>
		</li>
	<?php endforeach; ?>
</ul>
