<?php
/**
 * Sound tab.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

Admin::form_start( 'sound' );

Admin::card_start( __( 'Notification sound', 'wp-live-hype' ), __( 'A subtle sound as each notification appears. Browsers only allow audio after the visitor has interacted with the page; until then notifications simply stay silent.', 'wp-live-hype' ) );
Admin::toggle( 'sound_desktop', __( 'Play sound on desktop', 'wp-live-hype' ) );
Admin::toggle( 'sound_mobile', __( 'Play sound on mobile', 'wp-live-hype' ) );
Admin::range( 'sound_volume', __( 'Volume', 'wp-live-hype' ), '%' );
Admin::row_start( 'sound_choice', __( 'Sound', 'wp-live-hype' ) );
$wplh_sounds = array(
	'chime'  => __( 'Soft Chime', 'wp-live-hype' ),
	'modern' => __( 'Modern Notification', 'wp-live-hype' ),
	'pop'    => __( 'Subtle Pop', 'wp-live-hype' ),
	'clean'  => __( 'Clean Alert', 'wp-live-hype' ),
);
Admin::select_control( 'sound_choice', $wplh_sounds );
?>
<div class="wplh-sound-tests">
	<?php foreach ( $wplh_sounds as $wplh_key => $wplh_label ) : ?>
		<button type="button" class="button wplh-play" data-sound="<?php echo esc_attr( $wplh_key ); ?>">
			<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
			<?php
			/* translators: %s: sound name. */
			echo esc_html( sprintf( __( 'Play %s', 'wp-live-hype' ), $wplh_label ) );
			?>
		</button>
	<?php endforeach; ?>
</div>
<?php
Admin::row_end();
Admin::card_end();

Admin::form_end();
