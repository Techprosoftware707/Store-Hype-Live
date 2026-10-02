<?php
/**
 * Display tab with live preview.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

Admin::form_start( 'display' );
?>
<div class="wplh-split">
	<div class="wplh-split__main">
		<?php
		Admin::card_start( __( 'Placement & motion', 'wp-live-hype' ) );
		Admin::select(
			'position_desktop',
			__( 'Desktop position', 'wp-live-hype' ),
			array(
				'bottom-left'  => __( 'Bottom left', 'wp-live-hype' ),
				'bottom-right' => __( 'Bottom right', 'wp-live-hype' ),
				'top-left'     => __( 'Top left', 'wp-live-hype' ),
				'top-right'    => __( 'Top right', 'wp-live-hype' ),
			)
		);
		Admin::select(
			'position_mobile',
			__( 'Mobile position', 'wp-live-hype' ),
			array(
				'bottom-center' => __( 'Bottom center', 'wp-live-hype' ),
				'bottom-left'   => __( 'Bottom left', 'wp-live-hype' ),
				'top-center'    => __( 'Top center', 'wp-live-hype' ),
				'hidden'        => __( 'Hidden on mobile', 'wp-live-hype' ),
			),
			__( 'Applies to screens 600px wide or less.', 'wp-live-hype' )
		);
		Admin::select(
			'animation',
			__( 'Animation', 'wp-live-hype' ),
			array(
				'slidefade' => __( 'Slide + fade (recommended)', 'wp-live-hype' ),
				'slide'     => __( 'Slide', 'wp-live-hype' ),
				'fade'      => __( 'Fade', 'wp-live-hype' ),
				'scale'     => __( 'Scale', 'wp-live-hype' ),
				'none'      => __( 'None', 'wp-live-hype' ),
			),
			__( 'Visitors who prefer reduced motion always get a simple fade.', 'wp-live-hype' )
		);
		Admin::range( 'offset_x', __( 'Horizontal offset', 'wp-live-hype' ) );
		Admin::range( 'offset_y', __( 'Vertical offset', 'wp-live-hype' ) );
		Admin::number( 'z_index', __( 'Stacking order (z-index)', 'wp-live-hype' ), __( 'Raise if a sticky header or chat widget covers notifications.', 'wp-live-hype' ) );
		Admin::card_end();

		Admin::card_start( __( 'Appearance', 'wp-live-hype' ) );
		Admin::select(
			'theme',
			__( 'Theme', 'wp-live-hype' ),
			array(
				'light'  => __( 'Light', 'wp-live-hype' ),
				'dark'   => __( 'Dark', 'wp-live-hype' ),
				'auto'   => __( 'Automatic (follows the visitor\'s light/dark preference)', 'wp-live-hype' ),
				'custom' => __( 'Custom colors', 'wp-live-hype' ),
			)
		);
		Admin::color( 'accent_color', __( 'Accent color', 'wp-live-hype' ) );
		echo '<div data-show-when-value="theme=custom">';
		Admin::color( 'bg_color', __( 'Background color', 'wp-live-hype' ) );
		Admin::color( 'text_color', __( 'Text color', 'wp-live-hype' ) );
		echo '</div>';
		Admin::range( 'radius', __( 'Corner radius', 'wp-live-hype' ) );
		Admin::range( 'width', __( 'Width (desktop)', 'wp-live-hype' ) );
		Admin::select(
			'shadow',
			__( 'Shadow', 'wp-live-hype' ),
			array(
				'soft'   => __( 'Soft', 'wp-live-hype' ),
				'medium' => __( 'Medium', 'wp-live-hype' ),
				'strong' => __( 'Strong', 'wp-live-hype' ),
				'none'   => __( 'None', 'wp-live-hype' ),
			)
		);
		Admin::select(
			'font',
			__( 'Font', 'wp-live-hype' ),
			array(
				'inherit' => __( 'Use my theme\'s font', 'wp-live-hype' ),
				'system'  => __( 'System font', 'wp-live-hype' ),
			)
		);
		Admin::card_end();

		Admin::card_start( __( 'Content', 'wp-live-hype' ) );
		Admin::toggle( 'show_image', __( 'Product image', 'wp-live-hype' ), __( 'Products without an image show a neutral icon.', 'wp-live-hype' ) );
		Admin::select(
			'image_shape',
			__( 'Image shape', 'wp-live-hype' ),
			array(
				'rounded' => __( 'Rounded', 'wp-live-hype' ),
				'square'  => __( 'Square', 'wp-live-hype' ),
				'circle'  => __( 'Circle', 'wp-live-hype' ),
			)
		);
		Admin::toggle( 'show_label', __( 'Type label', 'wp-live-hype' ), __( 'e.g. "Recent purchase" above the message.', 'wp-live-hype' ) );
		Admin::toggle( 'show_time', __( 'Time of purchase', 'wp-live-hype' ), __( 'Real purchases only (Hybrid/Aggregate). Synthetic events never show a time.', 'wp-live-hype' ) );
		Admin::select(
			'time_display',
			__( 'Time format', 'wp-live-hype' ),
			array(
				'relative'    => __( 'Relative — "2 hours ago"', 'wp-live-hype' ),
				'approximate' => __( 'Approximate — "In the last few hours"', 'wp-live-hype' ),
				'recently'    => __( 'Simply "Recently"', 'wp-live-hype' ),
			),
			__( 'Order times are rounded down to 5 minutes before leaving the server. "Recently" sends no time at all.', 'wp-live-hype' )
		);
		Admin::toggle( 'show_verified', __( 'Verification badge on purchases', 'wp-live-hype' ), __( 'Shows "Verified purchase" — accurate because every purchase notification comes from a real order.', 'wp-live-hype' ) );
		Admin::toggle( 'show_sale_price', __( 'Sale price & discount on sale notifications', 'wp-live-hype' ) );
		Admin::toggle( 'show_close', __( 'Close button', 'wp-live-hype' ) );
		Admin::toggle( 'link_to_product', __( 'Link notifications to the product', 'wp-live-hype' ) );
		Admin::toggle( 'a11y_announce', __( 'Announce politely to screen readers', 'wp-live-hype' ), __( 'Uses a polite live region so assistive technology users get the same information without interruption.', 'wp-live-hype' ) );
		Admin::card_end();
		?>
	</div>
	<aside class="wplh-split__side">
		<div class="wplh-sticky">
			<?php Admin::card_start( __( 'Live preview', 'wp-live-hype' ), __( 'Updates as you change settings. Save to apply.', 'wp-live-hype' ) ); ?>
			<?php Admin::preview_panel( 'wplh-preview-display', true ); ?>
			<?php Admin::card_end(); ?>
		</div>
	</aside>
</div>
<?php
Admin::form_end();
