<?php
/**
 * Prima pagină: blocul de sus (1 articol mare + 4, apoi încă 4), apoi secțiunile din Personalizare, cu coloana „Pe scurt” în dreapta.
 * Folosită pentru pagina statică „Home” (Setări → Citire), creată de temă: secțiuni pe competiții, nu ultimele articole amestecate.
 */
defined( 'ABSPATH' ) || exit;
get_header();

$sus  = new WP_Query(
	array(
		'posts_per_page'      => 9,
		'category__not_in'    => eg_povesti_cat() ? array( eg_povesti_cat() ) : array(), // poveștile au rubrica lor
		'ignore_sticky_posts' => false,
		'no_found_rows'       => true,
	)
);
$vazute = wp_list_pluck( $sus->posts, 'ID' );
?>

<?php if ( $sus->have_posts() ) : ?>
	<section class="eg-sus" aria-label="Cele mai noi articole">
		<?php
		foreach ( array_slice( $sus->posts, 0, 5 ) as $i => $p ) {
			eg_card_coperta( $p, 0 === $i );
		}
		?>
	</section>
	<?php if ( count( $sus->posts ) > 5 ) : ?>
		<section class="eg-sus-extra" aria-label="Alte articole noi">
			<?php
			foreach ( array_slice( $sus->posts, 5 ) as $p ) {
				eg_card_coperta( $p );
			}
			?>
		</section>
	<?php endif; ?>
<?php endif; ?>

<?php eg_sectiune_video( 8 ); ?>

<div class="eg-doua-coloane">
	<div class="eg-continut">
		<?php
		$povesti = null;
		foreach ( eg_sectiuni() as $k => $s ) {
			if ( 'povesti' === trim( $s[0] ) ) {
				// O singură poveste pe prima pagină; restul în coloana laterală. Pe telefon, jos de tot (vezi mai jos).
				$povesti = $s;
				$vazute  = array_merge( $vazute, eg_sectiune( $s[0], $s[1], $vazute, 1, 'eg-ascuns-mobil' ) );
				continue;
			}
			$vazute = array_merge( $vazute, eg_sectiune( $s[0], $s[1], $vazute ) );
			if ( 0 === $k ) {
				eg_pe_scurt( 6, 'eg-doar-mobil' ); // pe telefon, după prima secțiune
			}
		}

		// La final, tot ce n-a apărut încă: cele mai noi articole, din toate categoriile.
		$rest = new WP_Query(
			array(
				'posts_per_page'      => 12,
				'post__not_in'        => $vazute,
				'category__not_in'    => eg_povesti_cat() ? array( eg_povesti_cat() ) : array(),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
		if ( $rest->have_posts() ) :
			?>
			<section class="eg-sectiune">
				<h2 class="eg-titlu-sectiune">Toate articolele</h2>
				<?php eg_grila_sectiune( $rest->posts ); ?>
			</section>
		<?php endif; ?>
		<?php if ( ! $sus->have_posts() ) : ?>
			<p class="eg-gol">Primele articole apar aici imediat ce sunt publicate.</p>
		<?php endif; ?>
	</div>
	<?php get_sidebar(); ?>
	<?php if ( $povesti ) : ?>
		<div class="eg-doar-mobil eg-povesti-jos">
			<?php
			eg_sectiune( $povesti[0], $povesti[1], array() );
			?>
		</div>
	<?php endif; ?>
</div>

<?php
get_footer();
