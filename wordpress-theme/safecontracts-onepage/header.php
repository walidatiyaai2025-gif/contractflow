<?php
/**
 * Public theme header.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$copy      = safecontracts_copy();
$lang      = safecontracts_current_lang();
$direction = safecontracts_direction();
$demo_url  = safecontracts_button_link( 'header_demo' );
$login_url = safecontracts_button_link( 'header_login' );
?>
<!doctype html>
<html lang="<?php echo esc_attr( $lang ); ?>" dir="<?php echo esc_attr( $direction ); ?>">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="theme-color" content="#062d5b">
	<link rel="alternate" hreflang="ar" href="<?php echo safecontracts_language_url( 'ar' ); ?>">
	<link rel="alternate" hreflang="en" href="<?php echo safecontracts_language_url( 'en' ); ?>">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="sc-skip-link" href="#main-content"><?php echo esc_html( 'ar' === $lang ? 'انتقل إلى المحتوى' : 'Skip to content' ); ?></a>
<header class="sc-site-header" data-site-header>
	<div class="sc-container sc-header-inner">
		<a class="sc-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="SafeContracts">
			<span class="sc-brand-mark" aria-hidden="true">
				<svg viewBox="0 0 48 48"><path d="M24 4 40 10v10c0 10.8-6.1 18.6-16 23-9.9-4.4-16-12.2-16-23V10z"/><path d="m16 23 5 5 11-12"/></svg>
			</span>
			<span class="sc-brand-copy">
				<strong>SafeContracts</strong>
				<small><?php echo esc_html( $copy['brand_tagline'] ); ?></small>
			</span>
		</a>

		<button class="sc-menu-toggle" type="button" aria-expanded="false" aria-controls="sc-primary-nav" data-menu-toggle>
			<span class="sc-menu-open"><?php echo safecontracts_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="sc-menu-close"><?php echo safecontracts_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="screen-reader-text"><?php echo esc_html( 'ar' === $lang ? 'فتح القائمة' : 'Open menu' ); ?></span>
		</button>

		<nav id="sc-primary-nav" class="sc-primary-nav" aria-label="<?php echo esc_attr( 'ar' === $lang ? 'التنقل الرئيسي' : 'Primary navigation' ); ?>" data-primary-nav>
			<a class="is-active" href="#home"><?php echo esc_html( $copy['nav']['home'] ); ?></a>
			<a href="#benefits"><?php echo esc_html( $copy['nav']['benefits'] ); ?></a>
			<a href="#use-cases"><?php echo esc_html( $copy['nav']['use_cases'] ); ?></a>
			<a href="#dashboards"><?php echo esc_html( $copy['nav']['dashboards'] ); ?></a>
			<a href="#faq"><?php echo esc_html( $copy['nav']['faq'] ); ?></a>
			<a href="#contact"><?php echo esc_html( $copy['nav']['contact'] ); ?></a>
		</nav>

		<div class="sc-header-actions">
			<div class="sc-language-switch" aria-label="<?php echo esc_attr( 'ar' === $lang ? 'اختيار اللغة' : 'Choose language' ); ?>">
				<a class="<?php echo 'ar' === $lang ? 'is-current' : ''; ?>" href="<?php echo safecontracts_language_url( 'ar' ); ?>" lang="ar">AR</a>
				<span aria-hidden="true">/</span>
				<a class="<?php echo 'en' === $lang ? 'is-current' : ''; ?>" href="<?php echo safecontracts_language_url( 'en' ); ?>" lang="en">EN</a>
			</div>
			<a class="sc-btn sc-btn-ghost sc-header-demo" href="<?php echo esc_url( $demo_url ); ?>"><?php echo esc_html( $copy['actions']['demo'] ); ?></a>
			<a class="sc-btn sc-btn-solid sc-header-login" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( $copy['actions']['login'] ); ?></a>
		</div>
	</div>
</header>
