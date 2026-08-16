<?php
/**
 * Share Safe Contracts dashboard translation overrides with the public theme.
 *
 * The public ?lang=ar|en switch remains theme-local. This file only overlays
 * Safe Contracts copy; it never changes WordPress locale or user preferences.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalize the visible brand spelling throughout public copy while leaving
 * internal SafeContracts slugs, options, APIs and PHP identifiers untouched.
 *
 * @param mixed $value Copy value/tree.
 * @return mixed
 */
function safecontracts_theme_normalize_brand_copy( $value ) {
	if ( is_string( $value ) ) {
		return str_replace( 'SafeContracts', 'Safe Contracts', $value );
	}
	if ( ! is_array( $value ) ) {
		return $value;
	}
	foreach ( $value as $key => $item ) {
		$value[ $key ] = safecontracts_theme_normalize_brand_copy( $item );
	}
	return $value;
}

/**
 * Overlay source-keyed translations onto an already localized theme copy tree.
 *
 * @param mixed                $english   English source tree.
 * @param mixed                $current   Current localized tree.
 * @param array<string,string> $overrides Source => translated text.
 * @return mixed
 */
function safecontracts_theme_translation_overlay_tree( $english, $current, $overrides ) {
	if ( is_string( $english ) ) {
		if ( isset( $overrides[ $english ] ) && is_string( $overrides[ $english ] ) && '' !== trim( $overrides[ $english ] ) ) {
			return safecontracts_theme_normalize_brand_copy( $overrides[ $english ] );
		}
		return safecontracts_theme_normalize_brand_copy( $current );
	}

	if ( ! is_array( $english ) ) {
		return $current;
	}

	$current = is_array( $current ) ? $current : array();
	foreach ( $english as $key => $english_value ) {
		$current_value   = array_key_exists( $key, $current ) ? $current[ $key ] : null;
		$current[ $key ] = safecontracts_theme_translation_overlay_tree( $english_value, $current_value, $overrides );
	}
	return safecontracts_theme_normalize_brand_copy( $current );
}

/**
 * Make the existing theme home-content reader see centralized translation
 * overrides after its own Appearance editor values have been applied.
 *
 * @param mixed $saved Existing safecontracts_home_content option.
 * @return mixed
 */
function safecontracts_theme_translation_option_overlay( $saved ) {
	$catalog = safecontracts_copy_catalog();
	if ( ! is_array( $catalog ) || ! isset( $catalog['en'], $catalog['ar'] ) ) {
		return safecontracts_theme_normalize_brand_copy( $saved );
	}

	$translation_overrides = get_option( 'safecontracts_translation_overrides', array() );
	if ( ! is_array( $translation_overrides ) ) {
		return safecontracts_theme_normalize_brand_copy( $saved );
	}
	$saved = is_array( $saved ) ? $saved : array();

	foreach ( array( 'en', 'ar' ) as $lang ) {
		$localized_defaults = isset( $catalog[ $lang ] ) && is_array( $catalog[ $lang ] ) ? $catalog[ $lang ] : array();
		$existing           = isset( $saved[ $lang ] ) && is_array( $saved[ $lang ] ) ? $saved[ $lang ] : array();
		$current            = function_exists( 'safecontracts_merge_home_copy' )
			? safecontracts_merge_home_copy( $localized_defaults, $existing )
			: $localized_defaults;
		$overrides          = isset( $translation_overrides[ $lang ] ) && is_array( $translation_overrides[ $lang ] )
			? $translation_overrides[ $lang ]
			: array();
		$saved[ $lang ]     = safecontracts_theme_translation_overlay_tree( $catalog['en'], $current, $overrides );
	}

	return safecontracts_theme_normalize_brand_copy( $saved );
}
add_filter( 'option_safecontracts_home_content', 'safecontracts_theme_translation_option_overlay', 20 );
