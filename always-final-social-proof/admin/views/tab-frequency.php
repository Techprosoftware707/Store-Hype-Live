<?php
/**
 * Frequency tab.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

$afsp_sec = __( 'seconds', 'always-final-social-proof' );
/* translators: %d: number of seconds. */
$afsp_s = static function ( $n ) {
	/* translators: %d: number of seconds. */
	return sprintf( _n( '%d second', '%d seconds', $n, 'always-final-social-proof' ), $n );
};

Admin::form_start( 'frequency' );

Admin::card_start( __( 'Timing', 'always-final-social-proof' ), __( 'Tasteful pacing builds trust. Notifications pause while the visitor hovers or focuses them, and never run in a background tab.', 'always-final-social-proof' ) );
Admin::preset( 'first_delay', __( 'First notification delay', 'always-final-social-proof' ), array( 5 => $afsp_s( 5 ), 10 => $afsp_s( 10 ), 15 => $afsp_s( 15 ), 30 => $afsp_s( 30 ) ), __( 'Wait after the page loads before the first notification.', 'always-final-social-proof' ), $afsp_sec );
Admin::preset( 'duration', __( 'Notification duration', 'always-final-social-proof' ), array( 5 => $afsp_s( 5 ), 8 => $afsp_s( 8 ), 10 => $afsp_s( 10 ), 15 => $afsp_s( 15 ) ), __( 'How long each notification stays visible.', 'always-final-social-proof' ), $afsp_sec );
Admin::preset( 'interval', __( 'Minimum interval between notifications', 'always-final-social-proof' ), array( 15 => $afsp_s( 15 ), 30 => $afsp_s( 30 ), 60 => $afsp_s( 60 ), 120 => $afsp_s( 120 ) ), __( 'Measured from one notification appearing to the next, across pages.', 'always-final-social-proof' ), $afsp_sec );
Admin::toggle( 'pause_on_hover', __( 'Pause while hovered', 'always-final-social-proof' ) );
Admin::card_end();

Admin::card_start( __( 'Limits', 'always-final-social-proof' ), __( 'The same notification is never repeated within a browsing session.', 'always-final-social-proof' ) );
Admin::preset(
	'max_per_session',
	__( 'Maximum notifications per session', 'always-final-social-proof' ),
	array(
		3 => '3',
		5 => '5',
		10 => '10',
		0 => __( 'Unlimited', 'always-final-social-proof' ),
	),
	__( 'A session ends after 30 minutes of inactivity. 0 = unlimited.', 'always-final-social-proof' )
);
Admin::preset(
	'max_per_page',
	__( 'Maximum notifications per page', 'always-final-social-proof' ),
	array(
		1 => '1',
		2 => '2',
		3 => '3',
	)
);
Admin::select(
	'dismiss_behavior',
	__( 'When a visitor closes a notification', 'always-final-social-proof' ),
	array(
		'session' => __( 'Stop for the rest of the session', 'always-final-social-proof' ),
		'page'    => __( 'Stop on the current page', 'always-final-social-proof' ),
		'none'    => __( 'Continue as normal', 'always-final-social-proof' ),
	)
);
Admin::card_end();

Admin::form_end();
