<?php
/**
 * Notifications tab: types, priority and templates.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;


$wplh_types = array(
	'purchase'   => array( __( 'Recent purchase templates', 'wp-live-hype' ), 'tpl_purchase' ),
	'sale'       => array( __( 'Sale templates', 'wp-live-hype' ), 'tpl_sale' ),
	'popular'    => array( __( 'Popular product templates (time-based)', 'wp-live-hype' ), 'tpl_popular' ),
	'bestseller' => array( __( 'Best-seller templates (lifetime sales)', 'wp-live-hype' ), 'tpl_bestseller' ),
	'featured'   => array( __( 'Featured product templates (synthetic)', 'wp-live-hype' ), 'tpl_featured' ),
	'explore'    => array( __( 'Spotlight templates (synthetic)', 'wp-live-hype' ), 'tpl_explore' ),
	'location'   => array( __( 'Shipping-region templates (synthetic)', 'wp-live-hype' ), 'tpl_location' ),
);

Admin::form_start( 'notifications' );

Admin::card_start( __( 'Real-data notification types', 'wp-live-hype' ), __( 'Used in Hybrid and Aggregate modes. Every type is built from real store data and stays silent when there is none. Synthetic event types are configured on the Live Hype tab.', 'wp-live-hype' ) );
?>
<div class="wplh-type-grid">
	<?php
	$wplh_type_rows = array(
		array( 'type_product_purchase', 'weight_product_purchase', __( 'Recent product purchase', 'wp-live-hype' ), __( 'On product and category pages, real purchases of the product (or category) being viewed are shown first.', 'wp-live-hype' ) ),
		array( 'type_purchase', 'weight_purchase', __( 'Recent purchase', 'wp-live-hype' ), __( 'Real qualifying orders from your geographic scope.', 'wp-live-hype' ) ),
		array( 'type_sale', 'weight_sale', __( 'Active sale', 'wp-live-hype' ), __( 'Products with a genuine, currently active WooCommerce sale price.', 'wp-live-hype' ) ),
		array( 'type_popular', 'weight_popular', __( 'Popular product', 'wp-live-hype' ), __( 'Only when real sales substantiate it (see the Data tab).', 'wp-live-hype' ) ),
	);
	foreach ( $wplh_type_rows as $wplh_row ) :
		?>
		<div class="wplh-type">
			<?php Admin::toggle( $wplh_row[0], $wplh_row[2], $wplh_row[3] ); ?>
			<div class="wplh-type__weight" data-show-when-value="priority_mode=weighted">
				<label for="wplh-<?php echo esc_attr( $wplh_row[1] ); ?>"><?php esc_html_e( 'Weight', 'wp-live-hype' ); ?></label>
				<?php Admin::number_control( $wplh_row[1] ); ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
<?php
Admin::radios(
	'priority_mode',
	__( 'Ordering', 'wp-live-hype' ),
	array(
		'priority' => array( __( 'Strict priority', 'wp-live-hype' ), __( 'Product-specific purchase → recent purchase → sale → popular product.', 'wp-live-hype' ) ),
		'weighted' => array( __( 'Weighted mix', 'wp-live-hype' ), __( 'Interleave types randomly in proportion to their weights (1–10).', 'wp-live-hype' ) ),
	)
);
Admin::card_end();

Admin::card_start( __( 'Message templates', 'wp-live-hype' ), __( 'One template per line. A template is only used when every token in it has real data — otherwise the next line is tried, ending with a product-only message. Leave a box empty to restore the defaults.', 'wp-live-hype' ) );
Admin::select(
	'template_rotation',
	__( 'Template selection', 'wp-live-hype' ),
	array(
		'first'  => __( 'First eligible template (most informative first)', 'wp-live-hype' ),
		'random' => __( 'Random eligible template (more variety)', 'wp-live-hype' ),
	)
);

foreach ( $wplh_types as $wplh_type => $wplh_meta ) {
	Admin::template_editor( $wplh_type, $wplh_meta[1], $wplh_meta[0], implode( "\n", Templates::templates( $wplh_type ) ) );
}
Admin::card_end();

Admin::card_start( __( 'Labels', 'wp-live-hype' ), __( 'The small heading above each message. Leave empty to use the translated default.', 'wp-live-hype' ) );
Admin::text( 'label_purchase', __( 'Purchase label', 'wp-live-hype' ), '', Templates::default_label( 'purchase' ) );
Admin::text( 'label_sale', __( 'Sale label', 'wp-live-hype' ), '', Templates::default_label( 'sale' ) );
Admin::text( 'label_popular', __( 'Popular label', 'wp-live-hype' ), '', Templates::default_label( 'popular' ) );
Admin::text( 'label_featured', __( 'Featured label', 'wp-live-hype' ), '', Templates::default_label( 'featured' ) );
Admin::text( 'label_explore', __( 'Spotlight label', 'wp-live-hype' ), '', Templates::default_label( 'explore' ) );
Admin::text( 'label_location', __( 'Shipping label', 'wp-live-hype' ), '', Templates::default_label( 'location' ) );
Admin::card_end();

Admin::form_end();
