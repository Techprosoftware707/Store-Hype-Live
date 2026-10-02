<?php
/**
 * Notifications tab: types, priority and templates.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

$afsp_tokens = array(
	'product'          => __( 'Product name', 'always-final-social-proof' ),
	'location'         => __( 'Location at the configured level', 'always-final-social-proof' ),
	'country'          => __( 'Country', 'always-final-social-proof' ),
	'region'           => __( 'Province / state / region', 'always-final-social-proof' ),
	'province'         => __( 'Alias of {region}', 'always-final-social-proof' ),
	'state'            => __( 'Alias of {region}', 'always-final-social-proof' ),
	'city'             => __( 'City', 'always-final-social-proof' ),
	'time_ago'         => __( 'Elapsed time, e.g. "2 hours ago"', 'always-final-social-proof' ),
	'sale_price'       => __( 'Current sale price ("from" for variable products)', 'always-final-social-proof' ),
	'regular_price'    => __( 'Regular price', 'always-final-social-proof' ),
	'discount_percent' => __( 'Real discount, e.g. "25%" or "up to 30%"', 'always-final-social-proof' ),
	'period'           => __( 'Popularity window, e.g. "in the last 7 days"', 'always-final-social-proof' ),
);

$afsp_types = array(
	'purchase'   => array( __( 'Recent purchase templates', 'always-final-social-proof' ), 'tpl_purchase' ),
	'sale'       => array( __( 'Sale templates', 'always-final-social-proof' ), 'tpl_sale' ),
	'popular'    => array( __( 'Popular product templates (time-based)', 'always-final-social-proof' ), 'tpl_popular' ),
	'bestseller' => array( __( 'Best-seller templates (lifetime sales)', 'always-final-social-proof' ), 'tpl_bestseller' ),
);

Admin::form_start( 'notifications' );

Admin::card_start( __( 'Notification types', 'always-final-social-proof' ), __( 'Every type is built from real store data. Types with no qualifying data simply stay silent.', 'always-final-social-proof' ) );
?>
<div class="afsp-type-grid">
	<?php
	$afsp_type_rows = array(
		array( 'type_product_purchase', 'weight_product_purchase', __( 'Recent product purchase', 'always-final-social-proof' ), __( 'On product and category pages, real purchases of the product (or category) being viewed are shown first.', 'always-final-social-proof' ) ),
		array( 'type_purchase', 'weight_purchase', __( 'Recent purchase', 'always-final-social-proof' ), __( 'Real qualifying orders from your geographic scope.', 'always-final-social-proof' ) ),
		array( 'type_sale', 'weight_sale', __( 'Active sale', 'always-final-social-proof' ), __( 'Products with a genuine, currently active WooCommerce sale price.', 'always-final-social-proof' ) ),
		array( 'type_popular', 'weight_popular', __( 'Popular product', 'always-final-social-proof' ), __( 'Only when real sales substantiate it (see the Data tab).', 'always-final-social-proof' ) ),
	);
	foreach ( $afsp_type_rows as $afsp_row ) :
		?>
		<div class="afsp-type">
			<?php Admin::toggle( $afsp_row[0], $afsp_row[2], $afsp_row[3] ); ?>
			<div class="afsp-type__weight" data-show-when-value="priority_mode=weighted">
				<label for="afsp-<?php echo esc_attr( $afsp_row[1] ); ?>"><?php esc_html_e( 'Weight', 'always-final-social-proof' ); ?></label>
				<?php Admin::number_control( $afsp_row[1] ); ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
<?php
Admin::radios(
	'priority_mode',
	__( 'Ordering', 'always-final-social-proof' ),
	array(
		'priority' => array( __( 'Strict priority', 'always-final-social-proof' ), __( 'Product-specific purchase → recent purchase → sale → popular product.', 'always-final-social-proof' ) ),
		'weighted' => array( __( 'Weighted mix', 'always-final-social-proof' ), __( 'Interleave types randomly in proportion to their weights (1–10).', 'always-final-social-proof' ) ),
	)
);
Admin::card_end();

Admin::card_start( __( 'Message templates', 'always-final-social-proof' ), __( 'One template per line. A template is only used when every token in it has real data — otherwise the next line is tried, ending with a product-only message. Leave a box empty to restore the defaults.', 'always-final-social-proof' ) );
Admin::select(
	'template_rotation',
	__( 'Template selection', 'always-final-social-proof' ),
	array(
		'first'  => __( 'First eligible template (most informative first)', 'always-final-social-proof' ),
		'random' => __( 'Random eligible template (more variety)', 'always-final-social-proof' ),
	)
);

$afsp_allowed_tokens = Templates::allowed_tokens();
foreach ( $afsp_types as $afsp_type => $afsp_meta ) :
	$afsp_value = implode( "\n", Templates::templates( $afsp_type ) );
	Admin::row_start( $afsp_meta[1], $afsp_meta[0] );
	?>
	<textarea class="large-text code afsp-template" id="afsp-<?php echo esc_attr( $afsp_meta[1] ); ?>" name="<?php echo esc_attr( Admin::name( $afsp_meta[1] ) ); ?>" rows="3" data-tokens="<?php echo esc_attr( implode( ',', $afsp_allowed_tokens[ $afsp_type ] ) ); ?>"><?php echo esc_textarea( $afsp_value ); ?></textarea>
	<div class="afsp-tokens" role="group" aria-label="<?php esc_attr_e( 'Insert token', 'always-final-social-proof' ); ?>">
		<?php foreach ( $afsp_allowed_tokens[ $afsp_type ] as $afsp_token ) : ?>
			<button type="button" class="afsp-token" data-target="afsp-<?php echo esc_attr( $afsp_meta[1] ); ?>" data-token="{<?php echo esc_attr( $afsp_token ); ?>}" title="<?php echo esc_attr( $afsp_tokens[ $afsp_token ] ?? '' ); ?>">{<?php echo esc_html( $afsp_token ); ?>}</button>
		<?php endforeach; ?>
	</div>
	<p class="afsp-template-warning" hidden></p>
	<?php
	Admin::row_end();
endforeach;
Admin::card_end();

Admin::card_start( __( 'Labels', 'always-final-social-proof' ), __( 'The small heading above each message. Leave empty to use the translated default.', 'always-final-social-proof' ) );
Admin::text( 'label_purchase', __( 'Purchase label', 'always-final-social-proof' ), '', Templates::default_label( 'purchase' ) );
Admin::text( 'label_sale', __( 'Sale label', 'always-final-social-proof' ), '', Templates::default_label( 'sale' ) );
Admin::text( 'label_popular', __( 'Popular label', 'always-final-social-proof' ), '', Templates::default_label( 'popular' ) );
Admin::card_end();

Admin::form_end();
