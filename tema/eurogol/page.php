<?php
/**
 * Paginile simple (Despre noi, Contact, Politica de confidențialitate).
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="eg-doua-coloane">
	<div class="eg-continut">
		<?php
		while ( have_posts() ) :
			the_post();
			?>
			<article <?php post_class( 'eg-articol' ); ?>>
				<header class="eg-articol-antet">
					<h1 class="eg-articol-titlu"><?php the_title(); ?></h1>
				</header>
				<div class="eg-text"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
