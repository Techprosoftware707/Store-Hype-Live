<?php
/**
 * Country targeting tab.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_data     = Cache::get_dataset( false );
$wplh_in_scope = (int) ( $wplh_data['stats']['in_lookback'] ?? 0 );
$wplh_target   = (string) $settings['target_country'];
$wplh_base     = Country::base_country();
$wplh_eligible = $settings['country_filter']
	? ( Country::is_valid( $wplh_target ) ? sprintf( /* translators: %s: country name. */ __( '%s orders only', 'wp-live-hype' ), Country::name( $wplh_target ) ) : __( 'None — select a target country', 'wp-live-hype' ) )
	: Country::scope_description();

Admin::form_start( 'country' );
?>
<section class="wplh-card wplh-country-hero">
	<div class="wplh-country-hero__head">
		<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
		<div>
			<h2 class="wplh-card__title"><?php esc_html_e( 'Country targeting', 'wp-live-hype' ); ?></h2>
			<p class="wplh-card__intro"><?php esc_html_e( 'Choose which country\'s real orders may power purchase notifications. Deploy the same plugin anywhere — nothing is hard-coded.', 'wp-live-hype' ); ?></p>
		</div>
	</div>

	<?php Admin::toggle( 'country_filter', __( 'Enable Country Filtering', 'wp-live-hype' ), __( 'When ON, only qualifying WooCommerce orders from the target country are used.', 'wp-live-hype' ) ); ?>

	<div class="wplh-country-on" data-show-when="country_filter">
		<?php Admin::country( 'target_country', __( 'Target country', 'wp-live-hype' ), __( 'Countries come from WooCommerce\'s own country list (ISO codes).', 'wp-live-hype' ) ); ?>
	</div>

	<div class="wplh-country-off" data-hide-when="country_filter">
		<?php
		Admin::radios(
			'unfiltered_scope',
			__( 'When country filtering is OFF, use…', 'wp-live-hype' ),
			array(
				'all'       => array( __( 'All valid store orders (any country)', 'wp-live-hype' ), __( 'Each notification shows the customer\'s own country/region.', 'wp-live-hype' ) ),
				/* translators: %s: store base country name. */
				'base'      => array( sprintf( __( 'Only orders from the store\'s base country (%s)', 'wp-live-hype' ), '' !== $wplh_base ? Country::name( $wplh_base ) : __( 'not set', 'wp-live-hype' ) ), __( 'Follows WooCommerce → Settings → General → Store address.', 'wp-live-hype' ) ),
				'countries' => array( __( 'Only orders from specific countries', 'wp-live-hype' ), __( 'Select one or more countries below.', 'wp-live-hype' ) ),
				'none'      => array( __( 'Require a geographic configuration', 'wp-live-hype' ), __( 'Purchase notifications stay inactive until country filtering is configured.', 'wp-live-hype' ) ),
			)
		);
		?>
		<div data-show-when-value="unfiltered_scope=countries">
			<?php Admin::countries( 'allowed_countries', __( 'Allowed countries', 'wp-live-hype' ) ); ?>
		</div>
	</div>

	<?php
	Admin::select(
		'location_level',
		__( 'Location display', 'wp-live-hype' ),
		array(
			'country'      => __( 'Country only', 'wp-live-hype' ),
			'region'       => __( 'Province / state / region', 'wp-live-hype' ),
			'city'         => __( 'City', 'wp-live-hype' ),
			'city_region'  => __( 'City + province / state', 'wp-live-hype' ),
			'city_country' => __( 'City + country', 'wp-live-hype' ),
			'none'         => __( 'No location', 'wp-live-hype' ),
		),
		__( 'Falls back gracefully when data is missing: City + region → Region → Country → No location. A location is never invented.', 'wp-live-hype' )
	);

	Admin::select(
		'country_source',
		__( 'Match the country using', 'wp-live-hype' ),
		array(
			'billing'          => __( 'Billing address', 'wp-live-hype' ),
			'shipping'         => __( 'Shipping address', 'wp-live-hype' ),
			'shipping_billing' => __( 'Shipping address, or billing when there is no shipping address', 'wp-live-hype' ),
		),
		__( 'The same address is used for matching and for the displayed location, so the location shown is always inside the permitted country.', 'wp-live-hype' )
	);
	?>

	<div class="wplh-eligible" aria-live="polite">
		<span class="wplh-eligible__label"><?php esc_html_e( 'Eligible orders', 'wp-live-hype' ); ?></span>
		<strong class="wplh-eligible__value" id="wplh-eligible-value"><?php echo esc_html( $wplh_eligible ); ?></strong>
	</div>

	<div class="wplh-callout wplh-callout--info">
		<span class="dashicons dashicons-shield" aria-hidden="true"></span>
		<p>
			<strong><?php esc_html_e( 'Only qualifying WooCommerce orders from the selected country will be used for purchase notifications.', 'wp-live-hype' ); ?></strong>
			<?php esc_html_e( 'Orders from other countries are never used as a fallback, and the rule is enforced on the server — not in the browser.', 'wp-live-hype' ); ?>
		</p>
	</div>

	<?php if ( 0 === $wplh_in_scope && ( $settings['type_purchase'] || $settings['type_product_purchase'] ) ) : ?>
		<div class="wplh-callout wplh-callout--warn">
			<span class="dashicons dashicons-warning" aria-hidden="true"></span>
			<p><?php esc_html_e( 'There is currently no qualifying order activity in scope. Purchase notifications will remain inactive until qualifying order activity exists.', 'wp-live-hype' ); ?></p>
		</div>
	<?php endif; ?>
</section>
<?php Admin::form_end(); ?>

<?php Admin::card_start( __( 'Country preview', 'wp-live-hype' ), __( 'See how a purchase notification would read for any country. Sample data only.', 'wp-live-hype' ) ); ?>
<div class="wplh-country-preview" id="wplh-country-preview">
	<div class="wplh-country-preview__controls">
		<label for="wplh-preview-country"><?php esc_html_e( 'Preview country', 'wp-live-hype' ); ?></label>
		<select id="wplh-preview-country" class="wc-enhanced-select" style="min-width:260px">
			<?php
			$wplh_preview_country = Country::is_valid( $wplh_target ) ? $wplh_target : $wplh_base;
			foreach ( Country::countries() as $wplh_code => $wplh_name ) :
				?>
				<option value="<?php echo esc_attr( $wplh_code ); ?>" <?php selected( $wplh_preview_country, $wplh_code ); ?>><?php echo esc_html( $wplh_name ); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="wplh-preview__banner" role="note"><?php esc_html_e( 'SYNTHETIC PREVIEW — NOT REAL CUSTOMER ACTIVITY', 'wp-live-hype' ); ?></div>
	<p class="wplh-country-preview__text" id="wplh-country-preview-text" aria-live="polite"></p>
	<div class="wplh-country-preview__toast" id="wplh-country-preview-toast"></div>
</div>
<?php Admin::card_end(); ?>
