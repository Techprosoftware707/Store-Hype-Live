<?php
/**
 * Router for PHP's built-in web server (pretty permalinks), test use only.
 *
 * @package AlwaysFinal\LiveHype
 */

$wplh_path = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ); // phpcs:ignore
if ( '/' !== $wplh_path && is_file( __DIR__ . $wplh_path ) ) {
	return false;
}
if ( is_dir( __DIR__ . $wplh_path ) && is_file( rtrim( __DIR__ . $wplh_path, '/' ) . '/index.php' ) && '/' !== $wplh_path ) {
	$_SERVER['SCRIPT_NAME'] = rtrim( $wplh_path, '/' ) . '/index.php';
	require rtrim( __DIR__ . $wplh_path, '/' ) . '/index.php';
	return;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/index.php';
