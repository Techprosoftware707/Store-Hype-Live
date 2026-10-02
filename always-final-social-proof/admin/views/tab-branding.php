<?php
/**
 * Branding / white-label tab.
 *
 * @package AlwaysFinal\SocialProof
 *
 * @var array $settings Settings.
 */

namespace AlwaysFinal\SocialProof;

defined( 'ABSPATH' ) || exit;

Admin::form_start( 'branding' );

Admin::card_start( __( 'Admin branding', 'always-final-social-proof' ), __( 'ALWAYS FINAL deploys this plugin across many stores. Control how it is presented to each store\'s team.', 'always-final-social-proof' ) );
Admin::toggle( 'branding_show', __( 'Show ALWAYS FINAL branding', 'always-final-social-proof' ), __( 'Logo in the page header and a subtle "Developed by" credit in the admin footer. Turn off to hide developer branding.', 'always-final-social-proof' ) );
Admin::text( 'branding_label', __( 'Custom plugin label', 'always-final-social-proof' ), __( 'Replaces the menu and page title. Leave empty for "ALWAYS FINAL Social Proof".', 'always-final-social-proof' ), __( 'ALWAYS FINAL Social Proof', 'always-final-social-proof' ) );
Admin::text( 'branding_developer', __( 'Developer name', 'always-final-social-proof' ), '', Branding::DEVELOPER );
Admin::text( 'branding_support_url', __( 'Support URL', 'always-final-social-proof' ), __( 'Linked from the developer credit.', 'always-final-social-proof' ), 'https://', 'url' );
Admin::text( 'branding_logo_url', __( 'Logo URL', 'always-final-social-proof' ), __( 'Leave empty to use the bundled ALWAYS FINAL logo.', 'always-final-social-proof' ), 'https://', 'url' );
Admin::toggle( 'branding_show_version', __( 'Show plugin version', 'always-final-social-proof' ) );
Admin::card_end();

Admin::card_start( __( 'Storefront attribution', 'always-final-social-proof' ) );
Admin::toggle( 'frontend_attribution', __( 'Show a small developer credit on notifications', 'always-final-social-proof' ), __( 'Off by default. Customer-facing notifications carry no developer branding unless you enable this.', 'always-final-social-proof' ) );
Admin::card_end();

Admin::form_end();
