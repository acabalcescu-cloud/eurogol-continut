<?php
/**
 * Articolul: eticheta, titlul, autor și dată, coperta, textul, etichetele și „Citește și”.
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="eg-doua-coloane">
	<div class="eg-continut">
		<?php
		while ( have_posts() ) :
			the_post();
			$cats = get_the_category();
			?>
			<article <?php post_class( 'eg-articol' ); ?>>
				<header class="eg-articol-antet">
					<?php if ( $cats ) : ?>
						<a class="eg-eticheta" href="<?php echo esc_url( get_category_link( $cats[0] ) ); ?>"><?php echo esc_html( $cats[0]->name ); ?></a>
					<?php endif; ?>
					<h1 class="eg-articol-titlu"><?php the_title(); ?></h1>
					<?php if ( has_excerpt() ) : ?>
						<p class="eg-articol-rezumat"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php endif; ?>
					<?php echo eg_meta( get_post() ); // phpcs:ignore ?>
				</header>

				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="eg-articol-coperta"><?php the_post_thumbnail( 'eg-mare', array( 'loading' => 'eager', 'fetchpriority' => 'high' ) ); ?><?php echo eg_persoane_bulinele_copertii( get_post() ); // phpcs:ignore ?></figure>
				<?php endif; ?>

				<div class="eg-text">
					<?php the_content(); ?>
				</div>

				<?php echo eg_video_html( get_post() ); // phpcs:ignore -- rezumatul video al meciului ?>

				<?php echo eg_clasament_html( get_post() ); // phpcs:ignore -- clasamentul competiției, la finalul articolului ?>

				<?php $nota = get_theme_mod( 'eg_nota_articol', eg_nota_implicita() ); ?>
				<?php if ( $nota ) : ?>
					<p class="eg-nota"><?php echo esc_html( $nota ); ?></p>
				<?php endif; ?>

				<?php
				$etichete = get_the_tags();
				if ( $etichete ) :
					?>
					<footer class="eg-articol-subsol">
						<?php foreach ( $etichete as $t ) : ?>
							<a href="<?php echo esc_url( get_tag_link( $t ) ); ?>">#<?php echo esc_html( $t->name ); ?></a>
						<?php endforeach; ?>
					</footer>
				<?php endif; ?>
			</article>

			<?php
			// „Citește și”: articolele mai noi din aceeași categorie, altfel cele mai noi de pe site.
			$args = array(
				'posts_per_page'      => 4,
				'post__not_in'        => array( get_the_ID() ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			);
			if ( $cats ) {
				$args['cat'] = $cats[0]->term_id;
			}
			$legate = new WP_Query( $args );
			if ( $legate->have_posts() ) :
				?>
				<section class="eg-sectiune">
					<h2 class="eg-titlu-sectiune">Citește și</h2>
					<div class="eg-grila-2">
						<?php
						foreach ( $legate->posts as $p ) {
							eg_card_mic( $p );
						}
						?>
					</div>
				</section>
				<?php
			endif;
		endwhile;
		?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
