<?php
/**
 * Listele de articole: categorii, etichete, autori, arhive, căutare și pagina blogului.
 */
defined( 'ABSPATH' ) || exit;
get_header();

if ( is_search() ) {
	$titlu = 'Rezultate pentru „' . get_search_query() . '”';
	$desc  = '';
} elseif ( is_archive() ) {
	$titlu = wp_strip_all_tags( get_the_archive_title() );
	$desc  = get_the_archive_description();
} else {
	$titlu = 'Toate articolele';
	$desc  = '';
}
?>
<div class="eg-doua-coloane">
	<div class="eg-continut">
		<header class="eg-antet-lista">
			<h1 class="eg-titlu-sectiune"><?php echo esc_html( $titlu ); ?></h1>
			<?php if ( $desc ) : ?>
				<div class="eg-descriere"><?php echo wp_kses_post( $desc ); ?></div>
			<?php endif; ?>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="eg-grila-2">
				<?php
				$i = 0;
				while ( have_posts() ) {
					the_post();
					// Pe prima pagină a listei, primele două articole au carduri mari.
					( $i < 2 && ! is_paged() ) ? eg_card_mare( get_post() ) : eg_card_mic( get_post() );
					$i++;
				}
				?>
			</div>
			<?php
			the_posts_pagination(
				array(
					'mid_size'           => 1,
					'prev_text'          => '‹ Înapoi',
					'next_text'          => 'Înainte ›',
					'screen_reader_text' => 'Paginile listei',
				)
			);
			?>
		<?php else : ?>
			<div class="eg-bloc eg-gol">
				<p><?php echo is_search() ? 'N-am găsit nimic. Încearcă alte cuvinte:' : 'Nu sunt încă articole aici.'; ?></p>
				<?php
				if ( is_search() ) {
					get_search_form();
				}
				?>
			</div>
		<?php endif; ?>
	</div>
	<?php get_sidebar(); ?>
</div>
<?php
get_footer();
