<?php
/**
 * Branding / white-label tab.
 *
 * @package AlwaysFinal\LiveHype
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

Admin::form_start( 'branding' );

Admin::card_start( __( 'Admin branding', 'wp-live-hype' ), __( 'ALWAYS FINAL deploys this plugin across many stores. Control how it is presented to each store\'s team.', 'wp-live-hype' ) );
Admin::toggle( 'branding_show', __( 'Show ALWAYS FINAL branding', 'wp-live-hype' ), __( 'Logo in the page header and a subtle "Developed by" credit in the admin footer. Turn off to hide developer branding.', 'wp-live-hype' ) );
Admin::text( 'branding_label', __( 'Custom plugin label', 'wp-live-hype' ), __( 'Replaces the menu and page title. Leave empty for "WP Live Hype".', 'wp-live-hype' ), __( 'WP Live Hype', 'wp-live-hype' ) );
Admin::text( 'branding_developer', __( 'Developer name', 'wp-live-hype' ), '', Branding::DEVELOPER );
Admin::text( 'branding_support_url', __( 'Support URL', 'wp-live-hype' ), __( 'Linked from the developer credit.', 'wp-live-hype' ), 'https://', 'url' );
Admin::text( 'branding_logo_url', __( 'Logo URL', 'wp-live-hype' ), __( 'Leave empty to use the bundled ALWAYS FINAL logo.', 'wp-live-hype' ), 'https://', 'url' );
Admin::toggle( 'branding_show_version', __( 'Show plugin version', 'wp-live-hype' ) );
Admin::card_end();

Admin::card_start( __( 'Storefront attribution', 'wp-live-hype' ) );
Admin::toggle( 'frontend_attribution', __( 'Show a small developer credit on notifications', 'wp-live-hype' ), __( 'Off by default. Customer-facing notifications carry no developer branding unless you enable this.', 'wp-live-hype' ) );
Admin::card_end();

Admin::form_end();
