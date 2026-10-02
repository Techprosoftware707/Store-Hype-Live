<?php
/**
 * Weighting tab: products, regions and cities.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_product_levels = array(
	1 => __( 'Low', 'wp-live-hype' ),
	2 => __( 'Normal', 'wp-live-hype' ),
	3 => __( 'High', 'wp-live-hype' ),
	4 => __( 'Featured', 'wp-live-hype' ),
);
$wplh_place_levels   = array(
	0 => __( 'Off', 'wp-live-hype' ),
	1 => __( 'Low', 'wp-live-hype' ),
	2 => __( 'Normal', 'wp-live-hype' ),
	3 => __( 'High', 'wp-live-hype' ),
);
$wplh_weights        = (array) $settings['product_weights'];

/**
 * Render a segmented weight control.
 *
 * @param string $name    Field name.
 * @param string $id      Base id.
 * @param array  $levels  value => label.
 * @param int    $current Current value.
 * @param string $label   Accessible group label.
 */
$wplh_segment = static function ( string $name, string $id, array $levels, int $current, string $label ) {
	echo '<fieldset class="wplh-weight" data-weight-group><legend class="screen-reader-text">' . esc_html( $label ) . '</legend>';
	foreach ( $levels as $value => $text ) {
		$input_id = $id . '-' . $value;
		echo '<input type="radio" id="' . esc_attr( $input_id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" data-mult="' . esc_attr( (string) Locations::multiplier( (int) $value ) ) . '" ' . checked( $current, (int) $value, false ) . ' />';
		echo '<label for="' . esc_attr( $input_id ) . '" class="wplh-weight__opt wplh-weight__opt--' . esc_attr( (string) $value ) . '">' . esc_html( $text ) . '</label>';
	}
	echo '</fieldset>';
};

$wplh_products = wc_get_products(
	array(
		'status'  => 'publish',
		'limit'   => 200,
		'orderby' => 'title',
		'order'   => 'ASC',
	)
);

// Countries whose locations the engine may use.
$wplh_allowed   = Country::allowed_countries();
$wplh_countries = false === $wplh_allowed ? array() : ( null === $wplh_allowed ? array( Country::base_country() ) : array_slice( $wplh_allowed, 0, 3 ) );
$wplh_countries = array_values( array_filter( $wplh_countries ) );

Admin::form_start( 'weighting' );

Admin::card_start( __( 'Product weighting', 'wp-live-hype' ), __( 'How often each product appears in the synthetic rotation. Featured products appear most often; the engine still alternates products so none becomes repetitive. The bar shows each product\'s approximate share.', 'wp-live-hype' ) );
if ( empty( $wplh_products ) ) {
	echo '<p class="wplh-empty">' . esc_html__( 'No published products yet. The activity layer stays silent until products are available.', 'wp-live-hype' ) . '</p>';
} else {
	?>
	<table class="widefat wplh-table wplh-weights" data-weight-table>
		<thead><tr><th scope="col"><?php esc_html_e( 'Product', 'wp-live-hype' ); ?></th><th scope="col"><?php esc_html_e( 'Weight', 'wp-live-hype' ); ?></th><th scope="col"><?php esc_html_e( 'Share', 'wp-live-hype' ); ?></th></tr></thead>
		<tbody>
			<?php
			foreach ( $wplh_products as $wplh_product ) :
				$wplh_id    = $wplh_product->get_id();
				$wplh_level = isset( $wplh_weights[ $wplh_id ] ) ? (int) $wplh_weights[ $wplh_id ] : 2;
				$wplh_ok    = null !== Products::public_data( $wplh_id );
				?>
				<tr class="<?php echo $wplh_ok ? '' : 'wplh-row-muted'; ?>">
					<td class="wplh-weights__product">
						<?php echo wp_kses_post( $wplh_product->get_image( array( 36, 36 ) ) ); ?>
						<span>
							<strong><?php echo esc_html( wp_strip_all_tags( $wplh_product->get_name() ) ); ?></strong>
							<?php if ( ! $wplh_ok ) : ?>
								<em><?php esc_html_e( 'Not eligible (hidden, out of stock or excluded)', 'wp-live-hype' ); ?></em>
							<?php endif; ?>
						</span>
					</td>
					<td><?php $wplh_segment( Admin::name( 'product_weights' ) . '[' . $wplh_id . ']', 'wplh-pw-' . $wplh_id, $wplh_product_levels, $wplh_level, wp_strip_all_tags( $wplh_product->get_name() ) ); ?></td>
					<td class="wplh-share"><span class="wplh-share__bar" data-share-bar <?php echo $wplh_ok ? '' : 'data-excluded'; ?>><span></span></span><span class="wplh-share__pct" data-share-pct></span></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
Admin::card_end();

foreach ( $wplh_countries as $wplh_cc ) :
	$wplh_states = Country::states( $wplh_cc );
	$wplh_cities = Locations::cities( $wplh_cc );
	/* translators: %s: country name. */
	Admin::card_start( sprintf( __( 'Locations — %s', 'wp-live-hype' ), Country::name( $wplh_cc ) ), __( 'Regions come from WooCommerce; cities from the bundled list. Turning a region Off also turns off its cities. Only locations in this country are ever used.', 'wp-live-hype' ) );

	if ( $wplh_states ) {
		echo '<h3 class="wplh-subhead">' . esc_html__( 'Provinces / states / regions', 'wp-live-hype' ) . '</h3>';
		echo '<table class="widefat wplh-table wplh-weights" data-weight-table><tbody>';
		foreach ( $wplh_states as $wplh_code => $wplh_name ) {
			echo '<tr><th scope="row">' . esc_html( $wplh_name ) . '</th><td>';
			$wplh_segment( Admin::name( 'region_weights' ) . '[' . $wplh_cc . ':' . $wplh_code . ']', 'wplh-rw-' . sanitize_html_class( $wplh_cc . '-' . $wplh_code ), $wplh_place_levels, Locations::region_weight( $wplh_cc, (string) $wplh_code ), $wplh_name );
			echo '</td><td class="wplh-share"><span class="wplh-share__bar" data-share-bar><span></span></span><span class="wplh-share__pct" data-share-pct></span></td></tr>';
		}
		echo '</tbody></table>';
	}

	if ( $wplh_cities ) {
		echo '<h3 class="wplh-subhead">' . esc_html__( 'Cities', 'wp-live-hype' ) . '</h3>';
		echo '<table class="widefat wplh-table wplh-weights" data-weight-table><tbody>';
		$wplh_map = (array) $settings['city_weights'];
		foreach ( $wplh_cities as $wplh_slug => $wplh_city ) {
			$wplh_key   = $wplh_cc . ':' . $wplh_slug;
			$wplh_level = isset( $wplh_map[ $wplh_key ] ) ? (int) $wplh_map[ $wplh_key ] : 2;
			$wplh_label = '' !== $wplh_city['region_name'] ? $wplh_city['name'] . ', ' . $wplh_city['region_name'] : $wplh_city['name'];
			echo '<tr><th scope="row">' . esc_html( $wplh_label ) . '</th><td>';
			$wplh_segment( Admin::name( 'city_weights' ) . '[' . $wplh_key . ']', 'wplh-cw-' . sanitize_html_class( $wplh_cc . '-' . $wplh_slug ), $wplh_place_levels, $wplh_level, $wplh_label );
			echo '</td><td class="wplh-share"><span class="wplh-share__bar" data-share-bar><span></span></span><span class="wplh-share__pct" data-share-pct></span></td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p class="wplh-help">' . esc_html__( 'No bundled city list for this country — regions are used instead. Cities are never invented.', 'wp-live-hype' ) . '</p>';
	}
	Admin::card_end();
endforeach;

if ( empty( $wplh_countries ) ) {
	Admin::card_start( __( 'Locations', 'wp-live-hype' ) );
	echo '<p class="wplh-empty">' . esc_html__( 'Configure a country on the Country tab to weight regions and cities.', 'wp-live-hype' ) . '</p>';
	Admin::card_end();
}

Admin::form_end();
