<?php
/**
 * Fallback WordPress template.
 *
 * @package SafeContracts_OnePage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<main id="main-content" class="sc-content-page">
	<div class="sc-container">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<header class="sc-section-heading">
						<h1><?php the_title(); ?></h1>
					</header>
					<div class="entry-content"><?php the_content(); ?></div>
				</article>
			<?php endwhile; ?>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
