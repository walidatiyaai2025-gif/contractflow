<?php
/**
 * Public one-page landing template.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$copy               = safecontracts_copy();
$hero_primary_url   = safecontracts_button_link( 'hero_primary' );
$hero_secondary_url = safecontracts_button_link( 'hero_secondary' );
$final_cta_url      = safecontracts_button_link( 'final_cta' );
?>
<main id="main-content">
	<section id="home" class="sc-hero sc-section-anchor">
		<div class="sc-hero-glow sc-hero-glow-one" aria-hidden="true"></div>
		<div class="sc-hero-glow sc-hero-glow-two" aria-hidden="true"></div>
		<div class="sc-container sc-hero-grid">
			<div class="sc-hero-copy">
				<span class="sc-eyebrow"><?php echo esc_html( $copy['hero']['eyebrow'] ); ?></span>
				<h1><?php echo esc_html( $copy['hero']['title'] ); ?></h1>
				<p class="sc-lead"><?php echo esc_html( $copy['hero']['text'] ); ?></p>
				<div class="sc-hero-actions">
					<a class="sc-btn sc-btn-solid sc-btn-large" href="<?php echo esc_url( $hero_primary_url ); ?>"><?php echo esc_html( $copy['actions']['get_started'] ); ?></a>
					<a class="sc-btn sc-btn-outline sc-btn-large" href="<?php echo esc_url( $hero_secondary_url ); ?>"><?php echo esc_html( $copy['actions']['explore'] ); ?></a>
				</div>
				<ul class="sc-trust-row" role="list">
					<?php foreach ( $copy['hero']['points'] as $point ) : ?>
						<li><span class="sc-mini-check"><?php echo safecontracts_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="sc-hero-visual">
				<div class="sc-product-halo" aria-hidden="true"></div>
				<img src="<?php echo esc_url( get_theme_file_uri( '/assets/images/hero-devices.svg' ) ); ?>" width="600" height="450" alt="<?php echo esc_attr( 'ar' === safecontracts_current_lang() ? 'واجهة SafeContracts على الكمبيوتر والجوال' : 'SafeContracts dashboard on desktop and mobile devices' ); ?>" fetchpriority="high">
			</div>
		</div>
	</section>

	<section id="benefits" class="sc-section sc-section-anchor">
		<div class="sc-container">
			<header class="sc-section-heading">
				<span class="sc-section-kicker">SafeContracts</span>
				<h2><?php echo esc_html( $copy['benefits_title'] ); ?></h2>
				<p><?php echo esc_html( $copy['benefits_intro'] ); ?></p>
			</header>
			<div class="sc-feature-grid">
				<?php foreach ( $copy['benefits'] as $benefit ) : ?>
					<article class="sc-feature-card sc-reveal">
						<span class="sc-icon-box"><?php echo safecontracts_icon( $benefit['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<h3><?php echo esc_html( $benefit['title'] ); ?></h3>
						<p><?php echo esc_html( $benefit['text'] ); ?></p>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section id="use-cases" class="sc-section sc-section-soft sc-section-anchor">
		<div class="sc-container">
			<header class="sc-section-heading">
				<span class="sc-section-kicker">360°</span>
				<h2><?php echo esc_html( $copy['use_cases_title'] ); ?></h2>
				<p><?php echo esc_html( $copy['use_cases_intro'] ); ?></p>
			</header>
			<div class="sc-use-case-grid">
				<?php foreach ( $copy['use_cases'] as $use_case ) : ?>
					<article class="sc-use-card sc-reveal">
						<div class="sc-use-image">
							<img src="<?php echo esc_url( get_theme_file_uri( '/assets/images/' . $use_case['image'] ) ); ?>" loading="lazy" width="420" height="315" alt="">
						</div>
						<div class="sc-use-copy">
							<h3><?php echo esc_html( $use_case['title'] ); ?></h3>
							<p><?php echo esc_html( $use_case['text'] ); ?></p>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="sc-section sc-workflow-section">
		<div class="sc-container">
			<header class="sc-section-heading sc-heading-compact">
				<h2><?php echo esc_html( $copy['workflow_title'] ); ?></h2>
			</header>
			<ol class="sc-workflow" role="list">
				<?php foreach ( $copy['workflow'] as $index => $step ) : ?>
					<li class="sc-workflow-step sc-reveal">
						<span class="sc-step-number"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
						<span class="sc-step-icon"><?php echo safecontracts_icon( $step['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<h3><?php echo esc_html( $step['title'] ); ?></h3>
						<p><?php echo esc_html( $step['text'] ); ?></p>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	</section>

	<section id="dashboards" class="sc-section sc-dashboard-section sc-section-anchor">
		<div class="sc-container sc-dashboard-grid">
			<div class="sc-dashboard-copy">
				<span class="sc-section-kicker">Dashboard</span>
				<h2><?php echo esc_html( $copy['dashboard_title'] ); ?></h2>
				<p><?php echo esc_html( $copy['dashboard_text'] ); ?></p>
				<ul class="sc-check-list" role="list">
					<?php foreach ( $copy['dashboard_points'] as $point ) : ?>
						<li><span><?php echo safecontracts_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span><?php echo esc_html( $point ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="sc-dashboard-visual sc-reveal">
				<img src="<?php echo esc_url( get_theme_file_uri( '/assets/images/hero-devices.svg' ) ); ?>" loading="lazy" width="600" height="450" alt="<?php echo esc_attr( 'ar' === safecontracts_current_lang() ? 'لوحات تحكم SafeContracts المتجاوبة' : 'Responsive SafeContracts dashboards' ); ?>">
				<div class="sc-dashboard-chip sc-chip-one">256 <small><?php echo esc_html( 'ar' === safecontracts_current_lang() ? 'عقد' : 'contracts' ); ?></small></div>
				<div class="sc-dashboard-chip sc-chip-two">142 <small><?php echo esc_html( 'ar' === safecontracts_current_lang() ? 'نشط' : 'active' ); ?></small></div>
			</div>
		</div>
	</section>

	<section class="sc-section sc-security-section">
		<div class="sc-container">
			<div class="sc-security-shell">
				<div class="sc-security-visual sc-reveal">
					<img src="<?php echo esc_url( get_theme_file_uri( '/assets/images/security.svg' ) ); ?>" loading="lazy" width="420" height="315" alt="">
				</div>
				<div class="sc-security-copy">
					<span class="sc-section-kicker">Security</span>
					<h2><?php echo esc_html( $copy['security_title'] ); ?></h2>
					<p><?php echo esc_html( $copy['security_intro'] ); ?></p>
					<div class="sc-security-grid">
						<?php foreach ( $copy['security'] as $security ) : ?>
							<article class="sc-security-card">
								<span class="sc-security-check"><?php echo safecontracts_icon( 'shield' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
								<div><h3><?php echo esc_html( $security['title'] ); ?></h3><p><?php echo esc_html( $security['text'] ); ?></p></div>
							</article>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>
	</section>

	<section id="faq" class="sc-section sc-faq-section sc-section-anchor">
		<div class="sc-container sc-faq-shell">
			<header class="sc-section-heading sc-heading-compact">
				<h2><?php echo esc_html( $copy['faq_title'] ); ?></h2>
			</header>
			<div class="sc-faq-list">
				<?php foreach ( $copy['faq'] as $index => $item ) : ?>
					<details class="sc-faq-item" <?php echo 0 === $index ? 'open' : ''; ?>>
						<summary><?php echo esc_html( $item['q'] ); ?><span aria-hidden="true">+</span></summary>
						<div class="sc-faq-answer"><p><?php echo esc_html( $item['a'] ); ?></p></div>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section id="contact" class="sc-cta-section sc-section-anchor">
		<div class="sc-container">
			<div class="sc-cta-shell">
				<div class="sc-cta-art sc-reveal" aria-hidden="true">
					<img src="<?php echo esc_url( get_theme_file_uri( '/assets/images/security.svg' ) ); ?>" loading="lazy" width="420" height="315" alt="">
				</div>
				<div class="sc-cta-copy">
					<h2><?php echo esc_html( $copy['cta']['title'] ); ?></h2>
					<p><?php echo esc_html( $copy['cta']['text'] ); ?></p>
					<a class="sc-btn sc-btn-teal sc-btn-large" href="<?php echo esc_url( $final_cta_url ); ?>"><?php echo esc_html( $copy['cta']['button'] ); ?></a>
				</div>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
