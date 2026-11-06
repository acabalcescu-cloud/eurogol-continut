<?php
/**
 * Listele de articole: categorii, etichete, autori, arhive, căutare și pagina blogului.
 */
defined( 'ABSPATH' ) || exit;
get_header();

if ( is_search() ) {
	$titlu = 'Rezultate pentru „' . get_search_query() . '”';
	$desc  = '';
} elseif ( is_author() ) {
	$titlu = get_the_author_meta( 'display_name', get_queried_object_id() );
	$dep   = get_user_meta( get_queried_object_id(), 'eg_departament', true );
	$desc  = get_the_author_meta( 'description', get_queried_object_id() );
	if ( $dep ) {
		$titlu .= ' · ' . $dep;
		$desc   = preg_replace( '/^' . preg_quote( $dep, '/' ) . '\.\s*/u', '', $desc );
	}
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
				while ( have_posts() ) {
					the_post();
					eg_card_mic( get_post() ); // toate articolele listei pe orizontală: imagine în stânga, titlu în dreapta
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
