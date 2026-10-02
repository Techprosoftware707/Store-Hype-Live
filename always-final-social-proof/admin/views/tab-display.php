<?php
/**
 * Display tab with live preview.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

Admin::form_start( 'display' );
?>
<div class="afsp-split">
	<div class="afsp-split__main">
		<?php
		Admin::card_start( __( 'Placement & motion', 'always-final-social-proof' ) );
		Admin::select(
			'position_desktop',
			__( 'Desktop position', 'always-final-social-proof' ),
			array(
				'bottom-left'  => __( 'Bottom left', 'always-final-social-proof' ),
				'bottom-right' => __( 'Bottom right', 'always-final-social-proof' ),
				'top-left'     => __( 'Top left', 'always-final-social-proof' ),
				'top-right'    => __( 'Top right', 'always-final-social-proof' ),
			)
		);
		Admin::select(
			'position_mobile',
			__( 'Mobile position', 'always-final-social-proof' ),
			array(
				'bottom-center' => __( 'Bottom center', 'always-final-social-proof' ),
				'bottom-left'   => __( 'Bottom left', 'always-final-social-proof' ),
				'top-center'    => __( 'Top center', 'always-final-social-proof' ),
				'hidden'        => __( 'Hidden on mobile', 'always-final-social-proof' ),
			),
			__( 'Applies to screens 600px wide or less.', 'always-final-social-proof' )
		);
		Admin::select(
			'animation',
			__( 'Animation', 'always-final-social-proof' ),
			array(
				'slide' => __( 'Slide', 'always-final-social-proof' ),
				'fade'  => __( 'Fade', 'always-final-social-proof' ),
				'scale' => __( 'Scale', 'always-final-social-proof' ),
				'none'  => __( 'None', 'always-final-social-proof' ),
			),
			__( 'Visitors who prefer reduced motion always get a simple fade.', 'always-final-social-proof' )
		);
		Admin::range( 'offset_x', __( 'Horizontal offset', 'always-final-social-proof' ) );
		Admin::range( 'offset_y', __( 'Vertical offset', 'always-final-social-proof' ) );
		Admin::number( 'z_index', __( 'Stacking order (z-index)', 'always-final-social-proof' ), __( 'Raise if a sticky header or chat widget covers notifications.', 'always-final-social-proof' ) );
		Admin::card_end();

		Admin::card_start( __( 'Appearance', 'always-final-social-proof' ) );
		Admin::select(
			'theme',
			__( 'Theme', 'always-final-social-proof' ),
			array(
				'light'  => __( 'Light', 'always-final-social-proof' ),
				'dark'   => __( 'Dark', 'always-final-social-proof' ),
				'auto'   => __( 'Automatic (follows the visitor\'s light/dark preference)', 'always-final-social-proof' ),
				'custom' => __( 'Custom colors', 'always-final-social-proof' ),
			)
		);
		Admin::color( 'accent_color', __( 'Accent color', 'always-final-social-proof' ) );
		echo '<div data-show-when-value="theme=custom">';
		Admin::color( 'bg_color', __( 'Background color', 'always-final-social-proof' ) );
		Admin::color( 'text_color', __( 'Text color', 'always-final-social-proof' ) );
		echo '</div>';
		Admin::range( 'radius', __( 'Corner radius', 'always-final-social-proof' ) );
		Admin::range( 'width', __( 'Width (desktop)', 'always-final-social-proof' ) );
		Admin::select(
			'shadow',
			__( 'Shadow', 'always-final-social-proof' ),
			array(
				'soft'   => __( 'Soft', 'always-final-social-proof' ),
				'medium' => __( 'Medium', 'always-final-social-proof' ),
				'strong' => __( 'Strong', 'always-final-social-proof' ),
				'none'   => __( 'None', 'always-final-social-proof' ),
			)
		);
		Admin::select(
			'font',
			__( 'Font', 'always-final-social-proof' ),
			array(
				'inherit' => __( 'Use my theme\'s font', 'always-final-social-proof' ),
				'system'  => __( 'System font', 'always-final-social-proof' ),
			)
		);
		Admin::card_end();

		Admin::card_start( __( 'Content', 'always-final-social-proof' ) );
		Admin::toggle( 'show_image', __( 'Product image', 'always-final-social-proof' ), __( 'Products without an image show a neutral icon.', 'always-final-social-proof' ) );
		Admin::select(
			'image_shape',
			__( 'Image shape', 'always-final-social-proof' ),
			array(
				'rounded' => __( 'Rounded', 'always-final-social-proof' ),
				'square'  => __( 'Square', 'always-final-social-proof' ),
				'circle'  => __( 'Circle', 'always-final-social-proof' ),
			)
		);
		Admin::toggle( 'show_label', __( 'Type label', 'always-final-social-proof' ), __( 'e.g. "Recent purchase" above the message.', 'always-final-social-proof' ) );
		Admin::toggle( 'show_time', __( 'Time of purchase', 'always-final-social-proof' ) );
		Admin::select(
			'time_display',
			__( 'Time format', 'always-final-social-proof' ),
			array(
				'relative'    => __( 'Relative — "2 hours ago"', 'always-final-social-proof' ),
				'approximate' => __( 'Approximate — "In the last few hours"', 'always-final-social-proof' ),
				'recently'    => __( 'Simply "Recently"', 'always-final-social-proof' ),
			),
			__( 'Order times are rounded down to 5 minutes before leaving the server. "Recently" sends no time at all.', 'always-final-social-proof' )
		);
		Admin::toggle( 'show_verified', __( 'Verification badge on purchases', 'always-final-social-proof' ), __( 'Shows "Verified purchase" — accurate because every purchase notification comes from a real order.', 'always-final-social-proof' ) );
		Admin::toggle( 'show_sale_price', __( 'Sale price & discount on sale notifications', 'always-final-social-proof' ) );
		Admin::toggle( 'show_close', __( 'Close button', 'always-final-social-proof' ) );
		Admin::toggle( 'link_to_product', __( 'Link notifications to the product', 'always-final-social-proof' ) );
		Admin::toggle( 'a11y_announce', __( 'Announce politely to screen readers', 'always-final-social-proof' ), __( 'Uses a polite live region so assistive technology users get the same information without interruption.', 'always-final-social-proof' ) );
		Admin::card_end();
		?>
	</div>
	<aside class="afsp-split__side">
		<div class="afsp-sticky">
			<?php Admin::card_start( __( 'Live preview', 'always-final-social-proof' ), __( 'Updates as you change settings. Save to apply.', 'always-final-social-proof' ) ); ?>
			<?php Admin::preview_panel( 'afsp-preview-display', true ); ?>
			<?php Admin::card_end(); ?>
		</div>
	</aside>
</div>
<?php
Admin::form_end();
