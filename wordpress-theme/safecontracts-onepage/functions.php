<?php
/**
 * Theme bootstrap for SafeContracts One Page.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_theme_file_path( '/inc/i18n.php' );
require_once get_theme_file_path( '/inc/admin-settings.php' );
require_once get_theme_file_path( '/inc/translation-overrides.php' );
require_once get_theme_file_path( '/inc/button-links.php' );
require_once get_theme_file_path( '/inc/admin-branding.php' );

/**
 * Register theme capabilities.
 *
 * @return void
 */
function safecontracts_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'custom-logo', array( 'height' => 64, 'width' => 220, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
}
add_action( 'after_setup_theme', 'safecontracts_theme_setup' );

/**
 * Front-end assets.
 *
 * @return void
 */
function safecontracts_enqueue_assets() {
	$version = wp_get_theme()->get( 'Version' );
	wp_enqueue_style( 'safecontracts-style', get_stylesheet_uri(), array(), $version );
	wp_enqueue_style( 'safecontracts-theme', get_theme_file_uri( '/assets/css/theme.css' ), array( 'safecontracts-style' ), $version );
	wp_enqueue_script( 'safecontracts-theme', get_theme_file_uri( '/assets/js/theme.js' ), array(), $version, true );
}
add_action( 'wp_enqueue_scripts', 'safecontracts_enqueue_assets' );

/**
 * Add body classes that describe the theme-local public language.
 *
 * @param string[] $classes Existing classes.
 * @return string[]
 */
function safecontracts_body_classes( $classes ) {
	$classes[] = 'safecontracts-public';
	$classes[] = 'lang-' . safecontracts_current_lang();
	$classes[] = 'dir-' . safecontracts_direction();
	return $classes;
}
add_filter( 'body_class', 'safecontracts_body_classes' );

/**
 * Resolve a safe configured URL.
 *
 * @param string $key     Theme mod key.
 * @param string $default Default URL.
 * @return string
 */
function safecontracts_theme_url( $key, $default ) {
	$value = get_theme_mod( $key, $default );
	return $value ? esc_url( $value ) : esc_url( $default );
}

/**
 * Register public landing-page settings.
 *
 * @param WP_Customize_Manager $wp_customize Customizer instance.
 * @return void
 */
function safecontracts_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'safecontracts_public_cta',
		array(
			'title'    => __( 'SafeContracts Public Page', 'safecontracts-onepage' ),
			'priority' => 30,
		)
	);

	$wp_customize->add_setting(
		'safecontracts_demo_url',
		array(
			'default'           => '#contact',
			'sanitize_callback' => 'esc_url_raw',
		)
	);
	$wp_customize->add_control(
		'safecontracts_demo_url',
		array(
			'label'   => __( 'Demo / primary CTA URL', 'safecontracts-onepage' ),
			'section' => 'safecontracts_public_cta',
			'type'    => 'url',
		)
	);

	$wp_customize->add_setting(
		'safecontracts_login_url',
		array(
			'default'           => wp_login_url(),
			'sanitize_callback' => 'esc_url_raw',
		)
	);
	$wp_customize->add_control(
		'safecontracts_login_url',
		array(
			'label'   => __( 'Login URL', 'safecontracts-onepage' ),
			'section' => 'safecontracts_public_cta',
			'type'    => 'url',
		)
	);
}
add_action( 'customize_register', 'safecontracts_customize_register' );

/**
 * Build a language-switch URL without changing plugin/admin locale.
 *
 * @param string $lang Target language.
 * @return string
 */
function safecontracts_language_url( $lang ) {
	return esc_url( add_query_arg( 'lang', $lang, home_url( '/' ) ) );
}

/**
 * Small inline icon set used by theme cards.
 *
 * @param string $name Icon key.
 * @return string Safe inline SVG.
 */
function safecontracts_icon( $name ) {
	$icons = array(
		'folder'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.5h6l2 2H21v9.5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 9h18"/></svg>',
		'users'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3.5 19c.8-4 3-6 5.5-6s4.7 2 5.5 6"/><circle cx="17" cy="9" r="2.3"/><path d="M15.7 14c2.8.3 4.5 2 5 5"/></svg>',
		'bell'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 17h12l-1.5-2.5V10a4.5 4.5 0 0 0-9 0v4.5z"/><path d="M10 20h4"/></svg>',
		'chart'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 19V9"/><path d="M10 19V5"/><path d="M16 19v-7"/><path d="M22 19V3"/></svg>',
		'workflow' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="6" cy="6" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="12" cy="18" r="2"/><path d="M8 6h8M7 8l4 8M17 8l-4 8"/></svg>',
		'search'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 5 5"/></svg>',
		'document' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5M9 13h6M9 17h6"/></svg>',
		'shield'   => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 20 6v5c0 5-3 8.4-8 10-5-1.6-8-5-8-10V6z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>',
		'refresh'  => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5"/><path d="M4 17v-5h5"/><path d="M6.2 8a7 7 0 0 1 11.8-1l2 2M18 16a7 7 0 0 1-11.8 1l-2-2"/></svg>',
		'check'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
		'menu'     => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>',
		'close'    => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>',
	);

	return isset( $icons[ $name ] ) ? $icons[ $name ] : $icons['check'];
}
