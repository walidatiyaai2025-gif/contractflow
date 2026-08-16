<?php
/**
 * Normalize user-visible theme gettext to the approved Safe Contracts name.
 * Internal identifiers deliberately keep the historic SafeContracts prefix.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $translation Translated text.
 * @param string $text        Source text.
 * @param string $domain      Text domain.
 * @return string
 */
function safecontracts_normalize_theme_brand_gettext( $translation, $text, $domain ) {
	unset( $text );
	if ( 'safecontracts-onepage' !== $domain ) {
		return $translation;
	}
	return str_replace( 'SafeContracts', 'Safe Contracts', $translation );
}
add_filter( 'gettext', 'safecontracts_normalize_theme_brand_gettext', 10, 3 );
