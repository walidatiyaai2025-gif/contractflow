<?php
/**
 * Public theme footer.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$copy = safecontracts_copy();
?>
<footer class="sc-site-footer">
	<div class="sc-container sc-footer-grid">
		<div class="sc-footer-brand">
			<div class="sc-brand sc-brand-inverse">
				<span class="sc-brand-mark" aria-hidden="true">
					<svg viewBox="0 0 48 48"><path d="M24 4 40 10v10c0 10.8-6.1 18.6-16 23-9.9-4.4-16-12.2-16-23V10z"/><path d="m16 23 5 5 11-12"/></svg>
				</span>
				<span class="sc-brand-copy"><strong>SafeContracts</strong><small><?php echo esc_html( $copy['brand_tagline'] ); ?></small></span>
			</div>
		</div>
		<nav class="sc-footer-nav" aria-label="<?php echo esc_attr( 'ar' === safecontracts_current_lang() ? 'روابط التذييل' : 'Footer links' ); ?>">
			<a href="#benefits"><?php echo esc_html( $copy['footer']['about'] ); ?></a>
			<a href="#faq"><?php echo esc_html( $copy['footer']['support'] ); ?></a>
			<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>"><?php echo esc_html( $copy['footer']['privacy'] ); ?></a>
			<a href="#contact"><?php echo esc_html( $copy['footer']['contact'] ); ?></a>
		</nav>
		<p class="sc-copyright">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> SafeContracts. <?php echo esc_html( 'ar' === safecontracts_current_lang() ? 'جميع الحقوق محفوظة.' : 'All rights reserved.' ); ?></p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
