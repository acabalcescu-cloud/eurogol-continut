<?php
/**
 * Prima pagină: blocul de sus (1 articol mare + 4), apoi secțiunile din Personalizare, cu coloana „Pe scurt” în dreapta.
 * Dacă în Setări → Citire e aleasă o pagină statică, aceasta rămâne totuși prima pagină a site-ului.
 */
defined( 'ABSPATH' ) || exit;
get_header();

$sus  = new WP_Query(
	array(
		'posts_per_page'      => 5,
		'ignore_sticky_posts' => false,
		'no_found_rows'       => true,
	)
);
$vazute = wp_list_pluck( $sus->posts, 'ID' );
?>

<?php if ( $sus->have_posts() ) : ?>
	<section class="eg-sus" aria-label="Cele mai noi articole">
		<?php
		foreach ( $sus->posts as $i => $p ) {
			eg_card_coperta( $p, 0 === $i );
		}
		?>
	</section>
<?php endif; ?>

<div class="eg-doua-coloane">
	<div class="eg-continut">
		<?php
		foreach ( eg_sectiuni() as $s ) {
			$vazute = array_merge( $vazute, eg_sectiune( $s[0], $s[1], $vazute ) );
		}

		// La final, tot ce n-a apărut încă: cele mai noi articole, din toate categoriile.
		$rest = new WP_Query(
			array(
				'posts_per_page'      => 6,
				'post__not_in'        => $vazute,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
		if ( $rest->have_posts() ) :
			?>
			<section class="eg-sectiune">
				<h2 class="eg-titlu-sectiune">Toate articolele</h2>
				<div class="eg-grila-2">
					<?php
					foreach ( $rest->posts as $i => $p ) {
						$i < 2 ? eg_card_mare( $p ) : eg_card_mic( $p );
					}
					?>
				</div>
			</section>
		<?php endif; ?>
		<?php if ( ! $sus->have_posts() ) : ?>
			<p class="eg-gol">Primele articole apar aici imediat ce sunt publicate.</p>
		<?php endif; ?>
	</div>
	<?php get_sidebar(); ?>
</div>

<?php
get_footer();
