<?php
/**
 * Canonical visible Safe Contracts identity for the public theme and WordPress
 * presentation surfaces. Internal SafeContracts slugs remain unchanged.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visible product name.
 *
 * @return string
 */
function safecontracts_brand_name() {
	return 'Safe Contracts';
}

/**
 * Return the packaged user-approved clipboard/check artwork as a data URI.
 * Keeping a real JPEG in the theme package avoids runtime/CDN dependencies and
 * keeps the public site, wp-admin and login surfaces on one canonical asset.
 *
 * @return string
 */
function safecontracts_brand_icon_data_uri() {
	$path  = dirname( __DIR__ ) . '/assets/images/safe-contracts-identity.jpg';
	$bytes = @file_get_contents( $path );

	if ( ! is_string( $bytes ) || '' === $bytes ) {
		return '';
	}

	return 'data:image/jpeg;base64,' . base64_encode( $bytes );
}

/**
 * Add the canonical brand artwork as the site favicon without requiring an
 * operator to configure a separate Site Icon in WordPress.
 *
 * @return void
 */
function safecontracts_brand_favicon() {
	$icon = safecontracts_brand_icon_data_uri();
	if ( '' === $icon ) {
		return;
	}

	printf(
		'<link rel="icon" type="image/jpeg" href="%s">' . "\n",
		esc_attr( $icon )
	);
}

add_action( 'wp_head', 'safecontracts_brand_favicon', 1 );
add_action( 'admin_head', 'safecontracts_brand_favicon', 1 );
add_action( 'login_head', 'safecontracts_brand_favicon', 1 );
