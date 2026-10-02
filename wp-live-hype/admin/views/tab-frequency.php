<?php
/**
 * Frequency tab.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

$wplh_sec = __( 'seconds', 'wp-live-hype' );
$wplh_s   = static function ( $n ) {
	/* translators: %d: number of seconds. */
	return sprintf( _n( '%d second', '%d seconds', $n, 'wp-live-hype' ), $n );
};

Admin::form_start( 'frequency' );

Admin::card_start( __( 'Timing', 'wp-live-hype' ), __( 'Tasteful pacing builds trust. Notifications pause while the visitor hovers or focuses them, and never run in a background tab.', 'wp-live-hype' ) );
Admin::preset(
	'first_delay',
	__( 'First notification delay', 'wp-live-hype' ),
	array(
		5  => $wplh_s( 5 ),
		10 => $wplh_s( 10 ),
		15 => $wplh_s( 15 ),
		30 => $wplh_s( 30 ),
	),
	__( 'Wait after the page loads before the first notification.', 'wp-live-hype' ),
	$wplh_sec
);
Admin::preset(
	'duration',
	__( 'Notification duration', 'wp-live-hype' ),
	array(
		5  => $wplh_s( 5 ),
		8  => $wplh_s( 8 ),
		10 => $wplh_s( 10 ),
		15 => $wplh_s( 15 ),
	),
	__( 'How long each notification stays visible.', 'wp-live-hype' ),
	$wplh_sec
);
Admin::preset(
	'interval',
	__( 'Minimum interval between notifications', 'wp-live-hype' ),
	array(
		15  => $wplh_s( 15 ),
		20  => $wplh_s( 20 ),
		30  => $wplh_s( 30 ),
		60  => $wplh_s( 60 ),
		120 => $wplh_s( 120 ),
	),
	__( 'Measured from one notification appearing to the next, across pages.', 'wp-live-hype' ),
	$wplh_sec
);
Admin::toggle( 'random_timing', __( 'Randomized timing', 'wp-live-hype' ), __( 'Each gap is chosen at random between the minimum and maximum (e.g. 27s, 41s, 18s, 73s…) so the layer never feels mechanical.', 'wp-live-hype' ) );
echo '<div data-show-when="random_timing">';
Admin::preset(
	'interval_max',
	__( 'Maximum interval between notifications', 'wp-live-hype' ),
	array(
		45  => $wplh_s( 45 ),
		60  => $wplh_s( 60 ),
		90  => $wplh_s( 90 ),
		180 => $wplh_s( 180 ),
	),
	__( 'Never shorter than the minimum interval.', 'wp-live-hype' ),
	$wplh_sec
);
echo '</div>';
Admin::toggle( 'pause_on_hover', __( 'Pause while hovered', 'wp-live-hype' ) );
Admin::card_end();

Admin::card_start( __( 'Limits', 'wp-live-hype' ), __( 'The same notification is never repeated within a browsing session.', 'wp-live-hype' ) );
Admin::preset(
	'max_per_session',
	__( 'Maximum notifications per session', 'wp-live-hype' ),
	array(
		3  => '3',
		5  => '5',
		10 => '10',
		0  => __( 'Unlimited', 'wp-live-hype' ),
	),
	__( 'A session ends after 30 minutes of inactivity. 0 = unlimited.', 'wp-live-hype' )
);
Admin::preset(
	'max_per_page',
	__( 'Maximum notifications per page', 'wp-live-hype' ),
	array(
		1 => '1',
		2 => '2',
		3 => '3',
		5 => '5',
	)
);
Admin::select(
	'dismiss_behavior',
	__( 'When a visitor closes a notification', 'wp-live-hype' ),
	array(
		'session' => __( 'Stop for the rest of the session', 'wp-live-hype' ),
		'page'    => __( 'Stop on the current page', 'wp-live-hype' ),
		'none'    => __( 'Continue as normal', 'wp-live-hype' ),
	)
);
Admin::card_end();

Admin::form_end();
