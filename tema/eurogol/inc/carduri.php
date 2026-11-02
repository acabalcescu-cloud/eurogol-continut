<?php
/**
 * Cardurile articolelor: mare cu imagine în fundal (prima pagină, sus), mare cu rezumat, mic orizontal, lista „Pe scurt”.
 */

defined( 'ABSPATH' ) || exit;

/** Imaginea articolului sau, dacă lipsește, un fundal în culoarea site-ului cu numele lui. */
function eg_imagine( $post, $marime, $clasa = '' ) {
	$post = get_post( $post );
	if ( has_post_thumbnail( $post ) ) {
		return get_the_post_thumbnail(
			$post,
			$marime,
			array(
				'class'   => trim( 'eg-img ' . $clasa ),
				'alt'     => esc_attr( get_the_title( $post ) ),
				'loading' => 'lazy',
			)
		);
	}
	return '<span class="eg-img eg-img-gol ' . esc_attr( $clasa ) . '" aria-hidden="true"><span>euro<b>gol</b></span></span>';
}

/** „Autor - 2 noiembrie 2026, 20:14” */
function eg_meta( $post, $cu_autor = true ) {
	$post  = get_post( $post );
	$autor = $cu_autor ? '<b class="eg-autor">' . esc_html( get_the_author_meta( 'display_name', $post->post_author ) ) . '</b> - ' : '';
	if ( $cu_autor && is_singular() && get_queried_object_id() === $post->ID ) {
		$dep   = get_user_meta( $post->post_author, 'eg_departament', true );
		$autor = '<a class="eg-autor" href="' . esc_url( get_author_posts_url( $post->post_author ) ) . '">' . esc_html( get_the_author_meta( 'display_name', $post->post_author ) ) . '</a>' . ( $dep ? ' <span class="eg-departament">· ' . esc_html( $dep ) . '</span>' : '' ) . ' - ';
	}
	return '<div class="eg-meta">' . $autor . '<time datetime="' . esc_attr( get_the_date( 'c', $post ) ) . '">' . esc_html( eg_data( $post ) ) . '</time></div>';
}

/** Eticheta categoriei, pentru colțul cardului. */
function eg_eticheta( $post ) {
	$cats = get_the_category( get_post( $post )->ID );
	if ( ! $cats ) {
		return '';
	}
	return '<span class="eg-eticheta">' . esc_html( $cats[0]->name ) . '</span>';
}

/** Cardul cu imaginea în fundal și titlul peste ea (blocul de sus al primei pagini). */
function eg_card_coperta( $post, $mare = false ) {
	$post   = get_post( $post );
	$marime = $mare ? 'eg-mare' : 'eg-mediu';
	?>
	<article class="eg-coperta<?php echo $mare ? ' eg-coperta-mare' : ''; ?>">
		<a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
			<span class="eg-coperta-img"><?php echo eg_imagine( $post, $marime ); // phpcs:ignore ?></span>
			<span class="eg-coperta-text">
				<?php echo eg_eticheta( $post ); // phpcs:ignore ?>
				<span class="eg-coperta-titlu"><?php echo esc_html( get_the_title( $post ) ); ?></span>
				<?php echo eg_meta( $post, $mare ); // phpcs:ignore ?>
			</span>
		</a>
	</article>
	<?php
}

/** Cardul mare: imagine sus, titlu, rezumat, autor și dată. */
function eg_card_mare( $post ) {
	$post = get_post( $post );
	?>
	<article class="eg-card eg-card-mare">
		<a class="eg-card-img" href="<?php echo esc_url( get_permalink( $post ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo eg_imagine( $post, 'eg-mediu' ); // phpcs:ignore ?></a>
		<div class="eg-card-corp">
			<h3 class="eg-card-titlu"><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
			<p class="eg-card-rezumat"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), 26, '…' ) ); ?></p>
			<?php echo eg_meta( $post ); // phpcs:ignore ?>
		</div>
	</article>
	<?php
}

/** Cardul mic: imagine în stânga, titlu și autor/dată în dreapta. */
function eg_card_mic( $post ) {
	$post = get_post( $post );
	?>
	<article class="eg-card eg-card-mic">
		<a class="eg-card-img" href="<?php echo esc_url( get_permalink( $post ) ); ?>" tabindex="-1" aria-hidden="true"><?php echo eg_imagine( $post, 'eg-mic' ); // phpcs:ignore ?></a>
		<div class="eg-card-corp">
			<h3 class="eg-card-titlu"><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
			<?php echo eg_meta( $post ); // phpcs:ignore ?>
		</div>
	</article>
	<?php
}

/** Butoanele „Citește mai mult:” cu categoriile secțiunii. */
function eg_butoane_categorii( $cats ) {
	$cats = array_filter( $cats );
	if ( ! $cats ) {
		return;
	}
	echo '<nav class="eg-butoane" aria-label="Mai multe articole"><span>Citește mai mult:</span>';
	foreach ( $cats as $c ) {
		echo '<a href="' . esc_url( get_category_link( $c ) ) . '">' . esc_html( $c->name ) . '</a>';
	}
	echo '</nav>';
}

/** O secțiune a primei pagini: titlu, 2 carduri mari, 4 mici, butoane. Nu apare dacă nu are articole. */
function eg_sectiune( $slugs, $titlu, $exclude = array() ) {
	$cats = array();
	foreach ( array_map( 'trim', explode( ',', $slugs ) ) as $s ) {
		$c = get_category_by_slug( $s );
		if ( $c ) {
			$cats[] = $c;
		}
	}
	if ( ! $cats ) {
		return array();
	}
	$q = new WP_Query(
		array(
			'cat'                 => implode( ',', wp_list_pluck( $cats, 'term_id' ) ),
			'posts_per_page'      => 6,
			'post__not_in'        => $exclude,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	if ( ! $q->have_posts() && $exclude ) {
		// Cât timp site-ul are puține articole, secțiunea le arată și pe cele din blocul de sus, ca să nu rămână goală.
		$q = new WP_Query(
			array(
				'cat'                 => implode( ',', wp_list_pluck( $cats, 'term_id' ) ),
				'posts_per_page'      => 6,
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
	}
	if ( ! $q->have_posts() ) {
		return array();
	}
	$ids = wp_list_pluck( $q->posts, 'ID' );
	?>
	<section class="eg-sectiune">
		<h2 class="eg-titlu-sectiune"><a href="<?php echo esc_url( get_category_link( $cats[0] ) ); ?>"><?php echo esc_html( $titlu ); ?></a></h2>
		<div class="eg-grila-2">
			<?php
			foreach ( array_slice( $q->posts, 0, 2 ) as $p ) {
				eg_card_mare( $p );
			}
			foreach ( array_slice( $q->posts, 2 ) as $p ) {
				eg_card_mic( $p );
			}
			?>
		</div>
		<?php eg_butoane_categorii( $cats ); ?>
	</section>
	<?php
	return $ids;
}

/** Lista „Pe scurt”: ultimele articole, cu ora (azi) sau ziua. */
function eg_pe_scurt( $numar = 8 ) {
	$q = new WP_Query(
		array(
			'posts_per_page'      => $numar,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	if ( ! $q->have_posts() ) {
		return;
	}
	echo '<section class="eg-bloc"><h2 class="eg-titlu-sectiune">Pe scurt</h2><ol class="eg-pe-scurt">';
	foreach ( $q->posts as $p ) {
		echo '<li><time datetime="' . esc_attr( get_the_date( 'c', $p ) ) . '">' . esc_html( eg_ora_sau_zi( $p ) ) . '</time><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></li>';
	}
	echo '</ol></section>';
}

/** Lista „Competiții”, cu săgeți, ca un meniu. */
function eg_lista_competitii() {
	$cats = eg_competitii();
	if ( ! $cats ) {
		return;
	}
	echo '<section class="eg-bloc"><h2 class="eg-titlu-sectiune">Competiții</h2><ul class="eg-competitii">';
	foreach ( $cats as $c ) {
		echo '<li><a href="' . esc_url( get_category_link( $c ) ) . '">' . eg_icon( 'sageata' ) . esc_html( $c->name ) . '</a></li>'; // phpcs:ignore
	}
	echo '</ul></section>';
}
