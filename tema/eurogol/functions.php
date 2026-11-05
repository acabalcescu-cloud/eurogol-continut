<?php
/**
 * Eurogol – tema site-ului eurogol.ro.
 * Fotbal românesc, european și sud-american, spus ca o poveste.
 */

defined( 'ABSPATH' ) || exit;

define( 'EG_VERSIUNE', '2.6.8' );
define( 'EG_DIR', get_template_directory() );
define( 'EG_URI', get_template_directory_uri() );

require EG_DIR . '/inc/setari.php';
require EG_DIR . '/inc/carduri.php';
require EG_DIR . '/inc/redactori.php';
require EG_DIR . '/inc/import.php';
require EG_DIR . '/inc/actualizare.php';
require EG_DIR . '/inc/clasament.php';
require EG_DIR . '/inc/persoane.php';
require EG_DIR . '/inc/seo.php';
require EG_DIR . '/inc/facebook.php';
require EG_DIR . '/inc/instagram.php';

add_action( 'after_setup_theme', 'eg_setup' );
function eg_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array( 'height' => 60, 'width' => 240, 'flex-width' => true, 'flex-height' => true ) );

	register_nav_menus(
		array(
			'principal' => 'Meniul principal (sus)',
			'subsol'    => 'Meniul din subsol',
		)
	);

	// Coperțile din depozit sunt 1200×630; mărimile păstrează aceeași proporție.
	add_image_size( 'eg-mare', 1200, 630, true );
	add_image_size( 'eg-mediu', 760, 400, true );
	add_image_size( 'eg-mic', 320, 168, true );
}

/**
 * O singură dată: pagina statică „Home” ca prima pagină a site-ului (Setări → Citire → O pagină statică).
 * front-page.php o afișează cu secțiunile Eurogol. Dacă setarea e schimbată ulterior de mână, nu se mai atinge.
 */
add_action( 'init', 'eg_pagina_home', 20 );
function eg_pagina_home() {
	if ( get_option( 'eg_home_setat' ) ) {
		return;
	}
	$pagina = get_page_by_path( 'home' );
	if ( ! $pagina ) {
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Home',
				'post_name'    => 'home',
				'post_content' => '',
			)
		);
	} else {
		$id = $pagina->ID;
	}
	if ( $id && ! is_wp_error( $id ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', (int) $id );
		update_option( 'eg_home_setat', 1 );
		if ( function_exists( 'eg_goleste_cache' ) ) {
			eg_goleste_cache();
		}
	}
}

/**
 * O singură dată: paginile cerute de Facebook (și de lege) – Politica de confidențialitate și Ștergerea datelor.
 * Dacă există deja (după adresă), nu se ating.
 */
add_action( 'init', 'eg_pagini_legale', 21 );
function eg_pagini_legale() {
	if ( get_option( 'eg_pagini_legale' ) ) {
		return;
	}
	$email   = get_option( 'admin_email' );
	$pagini  = array(
		'politica-de-confidentialitate' => array(
			'Politica de confidențialitate',
			'<p>Această politică explică ce date prelucrează eurogol.ro („Eurogol”) și cum.</p>'
			. '<h2>Ce date colectăm</h2><p>Eurogol nu cere cont și nu colectează date personale de la cititori prin formulare. Serverul înregistrează, ca orice site, date tehnice (adresa IP, tipul de browser, paginile vizitate), folosite pentru securitate și statistici agregate. Dacă ne scrii pe email, folosim adresa ta doar ca să-ți răspundem.</p>'
			. '<h2>Cookie-uri și servicii terțe</h2><p>Site-ul poate folosi cookie-uri tehnice și servicii de statistică (Google Analytics / Google Site Kit). Rezumatele video se încarcă de pe YouTube în modul fără cookie-uri, doar când apeși pe ele.</p>'
			. '<h2>Facebook</h2><p>Eurogol folosește o aplicație Meta doar pentru a publica automat articolele site-ului pe pagina noastră de Facebook (EuroGol.ro). Aplicația nu citește și nu stochează date despre utilizatorii Facebook și nu se autentifică în numele cititorilor.</p>'
			. '<h2>Drepturile tale</h2><p>Conform GDPR, ai dreptul de acces, rectificare, ștergere și opoziție. Pentru orice cerere, scrie-ne la <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>.</p>',
		),
		'stergerea-datelor'             => array(
			'Ștergerea datelor',
			'<p>Aplicația Eurogol pentru Facebook nu stochează date despre utilizatorii Facebook: ea doar publică articolele site-ului pe pagina noastră.</p>'
			. '<p>Dacă dorești totuși ștergerea oricăror date care te privesc, trimite o cerere la <a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>, cu subiectul „Ștergerea datelor”. Răspundem și confirmăm ștergerea în cel mult 30 de zile.</p>'
			. '<p>Poți elimina oricând accesul aplicației din Facebook: Setări → Securitate și autentificare → Aplicații și site-uri web.</p>',
		),
	);
	foreach ( $pagini as $slug => $x ) {
		if ( ! get_page_by_path( $slug ) ) {
			$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $x[0], 'post_name' => $slug, 'post_content' => $x[1] ) );
			if ( $id && ! is_wp_error( $id ) && 'politica-de-confidentialitate' === $slug ) {
				update_option( 'wp_page_for_privacy_policy', (int) $id );
			}
		}
	}
	update_option( 'eg_pagini_legale', 1 );
}

add_action( 'widgets_init', 'eg_widgets' );
function eg_widgets() {
	register_sidebar(
		array(
			'name'          => 'Coloana din dreapta',
			'id'            => 'coloana',
			'description'   => 'Apare sub „Pe scurt”, pe toate paginile.',
			'before_widget' => '<section id="%1$s" class="eg-bloc %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="eg-titlu-sectiune">',
			'after_title'   => '</h2>',
		)
	);
}

add_action( 'wp_enqueue_scripts', 'eg_scripturi' );
function eg_scripturi() {
	wp_enqueue_style( 'eg-fonturi', 'https://fonts.googleapis.com/css2?family=Noto+Sans:ital,wght@0,400;0,600;0,700;1,400&family=Sofia+Sans+Extra+Condensed:wght@800&display=swap', array(), null );
	wp_enqueue_style( 'eg-stil', get_stylesheet_uri(), array( 'eg-fonturi' ), EG_VERSIUNE );
	wp_add_inline_style( 'eg-stil', eg_css_culori() );
	wp_enqueue_script( 'eg-main', EG_URI . '/assets/js/main.js', array(), EG_VERSIUNE, array( 'strategy' => 'defer', 'in_footer' => true ) );
}

add_filter( 'wp_resource_hints', 'eg_preconnect', 10, 2 );
function eg_preconnect( $urls, $tip ) {
	if ( 'preconnect' === $tip ) {
		$urls[] = 'https://fonts.googleapis.com';
		$urls[] = array( 'href' => 'https://fonts.gstatic.com', 'crossorigin' );
	}
	return $urls;
}

add_filter( 'excerpt_length', fn() => 26 );
add_filter( 'excerpt_more', fn() => '…' );

/** Meniul de rezervă, cât timp nu e ales un meniu în Aspect → Meniuri: categoriile principale. */
function eg_meniu_rezerva() {
	$cats = get_categories( array( 'parent' => 0, 'orderby' => 'count', 'order' => 'DESC', 'number' => 7, 'hide_empty' => true ) );
	echo '<ul class="eg-meniu">';
	echo '<li><a href="' . esc_url( home_url( '/' ) ) . '">Acasă</a></li>';
	foreach ( $cats as $c ) {
		echo '<li><a href="' . esc_url( get_category_link( $c ) ) . '">' . esc_html( $c->name ) . '</a></li>';
	}
	echo '</ul>';
}

/**
 * Datele în română, indiferent de limba instalată în WordPress.
 * Formate: 'lung' = „2 noiembrie 2026, 20:14”, 'zi' = „2 noiembrie 2026”, 'antet' = „luni, 2 noiembrie 2026”, 'scurt' = „31 oct.”.
 */
function eg_data_ro( $timestamp, $format = 'lung' ) {
	static $luni = array( 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie' );
	static $scurt = array( 'ian.', 'feb.', 'mar.', 'apr.', 'mai', 'iun.', 'iul.', 'aug.', 'sept.', 'oct.', 'nov.', 'dec.' );
	static $zile = array( 'duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă' );
	$z = (int) wp_date( 'j', $timestamp );
	$l = (int) wp_date( 'n', $timestamp ) - 1;
	$a = wp_date( 'Y', $timestamp );
	switch ( $format ) {
		case 'scurt':
			return $z . ' ' . $scurt[ $l ];
		case 'zi':
			return "{$z} {$luni[ $l ]} {$a}";
		case 'antet':
			return $zile[ (int) wp_date( 'w', $timestamp ) ] . ", {$z} {$luni[ $l ]} {$a}";
		default:
			return "{$z} {$luni[ $l ]} {$a}, " . wp_date( 'H:i', $timestamp );
	}
}

/** Data articolului: „2 noiembrie 2026, 20:14”. */
function eg_data( $post = null, $cu_ora = true ) {
	return eg_data_ro( get_post_timestamp( $post ), $cu_ora ? 'lung' : 'zi' );
}

/** Ora pentru azi („17:30”), altfel ziua și luna scurt („31 oct.”). */
function eg_ora_sau_zi( $post = null ) {
	$t = get_post_timestamp( $post );
	if ( wp_date( 'Y-m-d', $t ) === wp_date( 'Y-m-d' ) ) {
		return wp_date( 'H:i', $t );
	}
	return eg_data_ro( $t, 'scurt' );
}

/** Iconițele rețelelor sociale (SVG, fără biblioteci externe). */
function eg_icon( $nume ) {
	$p = array(
		'facebook'  => 'M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7A10 10 0 0 0 22 12z',
		'instagram' => 'M12 7.3a4.7 4.7 0 1 0 0 9.4 4.7 4.7 0 0 0 0-9.4zm0 7.7a3 3 0 1 1 0-6 3 3 0 0 1 0 6zm6-7.9a1.1 1.1 0 1 1-2.2 0 1.1 1.1 0 0 1 2.2 0zM21.9 8c-.1-1.6-.4-3-1.6-4.2S17.6 2.2 16 2.1C14.4 2 9.6 2 8 2.1c-1.6.1-3 .4-4.2 1.6S2.2 6.4 2.1 8C2 9.6 2 14.4 2.1 16c.1 1.6.4 3 1.6 4.2s2.6 1.5 4.2 1.6c1.6.1 6.4.1 8 0 1.6-.1 3-.4 4.2-1.6s1.5-2.6 1.6-4.2c.1-1.6.1-6.4 0-8zm-2.1 9.9a3.3 3.3 0 0 1-1.9 1.9c-1.3.5-4.3.4-5.9.4s-4.6.1-5.9-.4a3.3 3.3 0 0 1-1.9-1.9c-.5-1.3-.4-4.3-.4-5.9s-.1-4.6.4-5.9a3.3 3.3 0 0 1 1.9-1.9C7.4 3.7 10.4 3.8 12 3.8s4.6-.1 5.9.4a3.3 3.3 0 0 1 1.9 1.9c.5 1.3.4 4.3.4 5.9s.1 4.6-.4 5.9z',
		'x'         => 'M17.8 3h3.1l-6.8 7.7 8 10.3h-6.2l-4.9-6.3L5.4 21H2.3l7.2-8.3L1.8 3h6.4l4.4 5.8L17.8 3zm-1.1 16.2h1.7L7.4 4.7H5.5l11.2 14.5z',
		'tiktok'    => 'M16.6 5.8A4.3 4.3 0 0 1 15.5 3h-3.1v12.4a2.6 2.6 0 1 1-1.8-2.5V9.7a5.7 5.7 0 1 0 4.9 5.7V9a7.3 7.3 0 0 0 4.3 1.4V7.3a4.3 4.3 0 0 1-3.2-1.5z',
		'youtube'   => 'M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12a31 31 0 0 0 .5 4.8 3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-4.8 31 31 0 0 0-.5-4.8zM9.8 15V9l5.8 3-5.8 3z',
		'cauta'     => 'M10 2a8 8 0 0 1 6.3 12.9l5.4 5.4-1.4 1.4-5.4-5.4A8 8 0 1 1 10 2zm0 2a6 6 0 1 0 0 12 6 6 0 0 0 0-12z',
		'meniu'     => 'M3 5h18v2H3V5zm0 6h18v2H3v-2zm0 6h18v2H3v-2z',
		'inchide'   => 'M18.3 4.3 19.7 5.7 13.4 12l6.3 6.3-1.4 1.4-6.3-6.3-6.3 6.3-1.4-1.4L10.6 12 4.3 5.7l1.4-1.4 6.3 6.3z',
		'sageata'   => 'M9 5.6 15.4 12 9 18.4 7.6 17l5-5-5-5z',
		'sus'       => 'M12 7.6 18.4 14 17 15.4l-5-5-5 5L5.6 14z',
	);
	if ( empty( $p[ $nume ] ) ) {
		return '';
	}
	return '<svg class="eg-icon" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill="currentColor" d="' . $p[ $nume ] . '"/></svg>';
}

/** Rețelele sociale completate în Personalizare. */
function eg_retele() {
	$out = '';
	foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X', 'tiktok' => 'TikTok', 'youtube' => 'YouTube' ) as $cheie => $nume ) {
		$url = get_theme_mod( 'eg_' . $cheie );
		if ( $url ) {
			$out .= '<a href="' . esc_url( $url ) . '" rel="noopener" target="_blank" aria-label="' . esc_attr( $nume ) . '">' . eg_icon( $cheie ) . '</a>';
		}
	}
	return $out ? '<div class="eg-retele">' . $out . '</div>' : '';
}

/** Iconița site-ului (mingea din logo), cât timp nu e aleasă alta în Personalizează → Identitatea site-ului. */
add_action( 'wp_head', 'eg_iconite', 2 );
add_action( 'admin_head', 'eg_iconite', 2 );
function eg_iconite() {
	if ( has_site_icon() ) {
		return;
	}
	$u = EG_URI . '/assets/img/';
	printf( '<link rel="icon" href="%s" sizes="32x32">' . "\n", esc_url( $u . 'icon-32.png' ) );
	printf( '<link rel="icon" href="%s" sizes="192x192">' . "\n", esc_url( $u . 'icon-192.png' ) );
	printf( '<link rel="apple-touch-icon" href="%s">' . "\n", esc_url( $u . 'icon-180.png' ) );
}

