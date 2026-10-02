<?php
/**
 * Country targeting tab.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

$afsp_data     = Cache::get_dataset( false );
$afsp_in_scope = (int) ( $afsp_data['stats']['in_lookback'] ?? 0 );
$afsp_target   = (string) $settings['target_country'];
$afsp_base     = Country::base_country();
$afsp_eligible = $settings['country_filter']
	? ( Country::is_valid( $afsp_target ) ? sprintf( /* translators: %s: country name. */ __( '%s orders only', 'always-final-social-proof' ), Country::name( $afsp_target ) ) : __( 'None — select a target country', 'always-final-social-proof' ) )
	: Country::scope_description();

Admin::form_start( 'country' );
?>
<section class="afsp-card afsp-country-hero">
	<div class="afsp-country-hero__head">
		<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
		<div>
			<h2 class="afsp-card__title"><?php esc_html_e( 'Country targeting', 'always-final-social-proof' ); ?></h2>
			<p class="afsp-card__intro"><?php esc_html_e( 'Choose which country\'s real orders may power purchase notifications. Deploy the same plugin anywhere — nothing is hard-coded.', 'always-final-social-proof' ); ?></p>
		</div>
	</div>

	<?php Admin::toggle( 'country_filter', __( 'Enable Country Filtering', 'always-final-social-proof' ), __( 'When ON, only qualifying WooCommerce orders from the target country are used.', 'always-final-social-proof' ) ); ?>

	<div class="afsp-country-on" data-show-when="country_filter">
		<?php Admin::country( 'target_country', __( 'Target country', 'always-final-social-proof' ), __( 'Countries come from WooCommerce\'s own country list (ISO codes).', 'always-final-social-proof' ) ); ?>
	</div>

	<div class="afsp-country-off" data-hide-when="country_filter">
		<?php
		Admin::radios(
			'unfiltered_scope',
			__( 'When country filtering is OFF, use…', 'always-final-social-proof' ),
			array(
				'all'       => array( __( 'All valid store orders (any country)', 'always-final-social-proof' ), __( 'Each notification shows the customer\'s own country/region.', 'always-final-social-proof' ) ),
				/* translators: %s: store base country name. */
				'base'      => array( sprintf( __( 'Only orders from the store\'s base country (%s)', 'always-final-social-proof' ), '' !== $afsp_base ? Country::name( $afsp_base ) : __( 'not set', 'always-final-social-proof' ) ), __( 'Follows WooCommerce → Settings → General → Store address.', 'always-final-social-proof' ) ),
				'countries' => array( __( 'Only orders from specific countries', 'always-final-social-proof' ), __( 'Select one or more countries below.', 'always-final-social-proof' ) ),
				'none'      => array( __( 'Require a geographic configuration', 'always-final-social-proof' ), __( 'Purchase notifications stay inactive until country filtering is configured.', 'always-final-social-proof' ) ),
			)
		);
		?>
		<div data-show-when-value="unfiltered_scope=countries">
			<?php Admin::countries( 'allowed_countries', __( 'Allowed countries', 'always-final-social-proof' ) ); ?>
		</div>
	</div>

	<?php
	Admin::select(
		'location_level',
		__( 'Location display', 'always-final-social-proof' ),
		array(
			'country'     => __( 'Country only', 'always-final-social-proof' ),
			'region'      => __( 'Province / state / region', 'always-final-social-proof' ),
			'city'        => __( 'City', 'always-final-social-proof' ),
			'city_region' => __( 'City + province / state', 'always-final-social-proof' ),
			'none'        => __( 'No location', 'always-final-social-proof' ),
		),
		__( 'Falls back gracefully when data is missing: City + region → Region → Country → No location. A location is never invented.', 'always-final-social-proof' )
	);

	Admin::select(
		'country_source',
		__( 'Match the country using', 'always-final-social-proof' ),
		array(
			'billing'          => __( 'Billing address', 'always-final-social-proof' ),
			'shipping'         => __( 'Shipping address', 'always-final-social-proof' ),
			'shipping_billing' => __( 'Shipping address, or billing when there is no shipping address', 'always-final-social-proof' ),
		),
		__( 'The same address is used for matching and for the displayed location, so the location shown is always inside the permitted country.', 'always-final-social-proof' )
	);
	?>

	<div class="afsp-eligible" aria-live="polite">
		<span class="afsp-eligible__label"><?php esc_html_e( 'Eligible orders', 'always-final-social-proof' ); ?></span>
		<strong class="afsp-eligible__value" id="afsp-eligible-value"><?php echo esc_html( $afsp_eligible ); ?></strong>
	</div>

	<div class="afsp-callout afsp-callout--info">
		<span class="dashicons dashicons-shield" aria-hidden="true"></span>
		<p>
			<strong><?php esc_html_e( 'Only qualifying WooCommerce orders from the selected country will be used for purchase notifications.', 'always-final-social-proof' ); ?></strong>
			<?php esc_html_e( 'Orders from other countries are never used as a fallback, and the rule is enforced on the server — not in the browser.', 'always-final-social-proof' ); ?>
		</p>
	</div>

	<?php if ( 0 === $afsp_in_scope && ( $settings['type_purchase'] || $settings['type_product_purchase'] ) ) : ?>
		<div class="afsp-callout afsp-callout--warn">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<p><?php esc_html_e( 'There is currently no qualifying order activity in scope. Purchase notifications will remain inactive until qualifying order activity exists.', 'always-final-social-proof' ); ?></p>
		</div>
	<?php endif; ?>
</section>
<?php Admin::form_end(); ?>

<?php Admin::card_start( __( 'Country preview', 'always-final-social-proof' ), __( 'See how a purchase notification would read for any country. Sample data only.', 'always-final-social-proof' ) ); ?>
<div class="afsp-country-preview" id="afsp-country-preview">
	<div class="afsp-country-preview__controls">
		<label for="afsp-preview-country"><?php esc_html_e( 'Preview country', 'always-final-social-proof' ); ?></label>
		<select id="afsp-preview-country" class="wc-enhanced-select" style="min-width:260px">
			<?php
			$afsp_preview_country = Country::is_valid( $afsp_target ) ? $afsp_target : $afsp_base;
			foreach ( Country::countries() as $afsp_code => $afsp_name ) :
				?>
				<option value="<?php echo esc_attr( $afsp_code ); ?>" <?php selected( $afsp_preview_country, $afsp_code ); ?>><?php echo esc_html( $afsp_name ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="afsp-preview__banner" role="note"><?php esc_html_e( 'PREVIEW — NOT REAL CUSTOMER ACTIVITY', 'always-final-social-proof' ); ?></div>
	<p class="afsp-country-preview__text" id="afsp-country-preview-text" aria-live="polite"></p>
	<div class="afsp-country-preview__toast" id="afsp-country-preview-toast"></div>
</div>
<?php Admin::card_end(); ?>
