<?php
/**
 * Setările temei, în Aspect → Personalizează → „Eurogol”.
 */

defined( 'ABSPATH' ) || exit;

/** Secțiunile implicite ale primei pagini: slug-ul categoriei => titlul afișat. */
function eg_sectiuni_implicite() {
	return "superliga,cupa-romaniei,nationala | Fotbal românesc\nchampions-league,europa-league,conference-league | Champions League și cupele europene\npremier-league,laliga,serie-a,bundesliga,ligue-1 | Fotbal european\nnationalele-europei | Naționalele Europei\ncopa-libertadores,brasileirao,liga-profesional | America de Sud\npovesti | Povești";
}

/** Secțiunile primei pagini, citite din setări: [ [slug, titlu], ... ]. Slug-uri separate prin virgulă = mai multe categorii. */
function eg_sectiuni() {
	$text = get_theme_mod( 'eg_sectiuni', eg_sectiuni_implicite() );
	// Vechea listă implicită (Povești, Știri, SuperLiga…) trece la noua ordine pe competiții.
	if ( false !== strpos( (string) $text, 'stiri | Știri' ) && false !== strpos( (string) $text, 'povesti | Povești' ) ) {
		$text = eg_sectiuni_implicite();
	}
	$out  = array();
	foreach ( preg_split( '/\r?\n/', (string) $text ) as $rand ) {
		$parti = array_map( 'trim', explode( '|', $rand, 2 ) );
		if ( '' !== $parti[0] ) {
			$out[] = array( $parti[0], $parti[1] ?? $parti[0] );
		}
	}
	// Secțiunea „Naționalele Europei” (din 14.11.2026) apare și dacă lista e salvată în Personalizare fără ea: după „Fotbal european”.
	if ( ! array_filter( $out, fn( $r ) => false !== strpos( $r[0], 'nationalele-europei' ) ) ) {
		$poz = count( $out );
		foreach ( $out as $i => $r ) {
			if ( false !== strpos( $r[0], 'premier-league' ) ) {
				$poz = $i + 1;
				break;
			}
		}
		array_splice( $out, $poz, 0, array( array( 'nationalele-europei', 'Naționalele Europei' ) ) );
	}
	return $out;
}

/** Nota de la finalul articolelor: implicit nu există; se poate scrie una în Personalizează → Eurogol. */
function eg_nota_implicita() {
	return '';
}

add_action( 'customize_register', 'eg_personalizare' );
function eg_personalizare( $wp_customize ) {
	$wp_customize->add_section( 'eg', array( 'title' => 'Eurogol', 'priority' => 30 ) );

	$wp_customize->add_setting( 'eg_culoare', array( 'default' => '#1546A8', 'sanitize_callback' => 'sanitize_hex_color' ) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'eg_culoare', array( 'label' => 'Culoarea principală (fundal, linii, butoane)', 'section' => 'eg' ) ) );

	$wp_customize->add_setting( 'eg_slogan', array( 'default' => 'Fotbal românesc, european și sud-american, spus ca o poveste', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'eg_slogan', array( 'label' => 'Textul din banda de sub meniu', 'section' => 'eg' ) );

	$wp_customize->add_setting( 'eg_sectiuni', array( 'default' => eg_sectiuni_implicite(), 'sanitize_callback' => 'sanitize_textarea_field' ) );
	$wp_customize->add_control(
		'eg_sectiuni',
		array(
			'label'       => 'Secțiunile primei pagini',
			'description' => 'Câte una pe rând: slug-ul categoriei | titlul secțiunii. Pentru mai multe categorii într-o secțiune, separă slug-urile prin virgulă (ex. premier-league,laliga,serie-a | Europa).',
			'section'     => 'eg',
			'type'        => 'textarea',
		)
	);

	$wp_customize->add_setting( 'eg_competitii', array( 'default' => 'superliga,nationala,champions-league,europa-league,conference-league,premier-league,laliga,serie-a,bundesliga,ligue-1,copa-libertadores,brasileirao', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'eg_competitii', array( 'label' => 'Lista „Competiții” din coloana dreaptă și din subsol (slug-uri, separate prin virgulă)', 'section' => 'eg' ) );

	$wp_customize->add_setting( 'eg_nota_articol', array( 'default' => eg_nota_implicita(), 'sanitize_callback' => 'sanitize_textarea_field' ) );
	$wp_customize->add_control(
		'eg_nota_articol',
		array(
			'label'       => 'Nota de la finalul articolelor',
			'description' => 'Apare sub fiecare articol. Gol = nu apare.',
			'section'     => 'eg',
			'type'        => 'textarea',
		)
	);

	$wp_customize->add_setting( 'eg_google_verificare', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'eg_google_verificare', array( 'label' => 'Google Search Console: codul de verificare (meta)', 'description' => 'Doar codul din content="…". Nu e nevoie dacă site-ul e verificat prin Site Kit.', 'section' => 'eg' ) );
	$wp_customize->add_setting( 'eg_bing_verificare', array( 'default' => '', 'sanitize_callback' => 'sanitize_text_field' ) );
	$wp_customize->add_control( 'eg_bing_verificare', array( 'label' => 'Bing Webmaster: codul de verificare (msvalidate.01)', 'section' => 'eg' ) );

	foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'x' => 'X (Twitter)', 'tiktok' => 'TikTok', 'youtube' => 'YouTube' ) as $cheie => $nume ) {
		$wp_customize->add_setting( 'eg_' . $cheie, array( 'default' => '', 'sanitize_callback' => 'esc_url_raw' ) );
		$wp_customize->add_control( 'eg_' . $cheie, array( 'label' => 'Adresa ' . $nume, 'section' => 'eg', 'type' => 'url' ) );
	}
}

/** Culorile derivate din culoarea principală: varianta închisă pentru banda de sub meniu și pentru hover. */
function eg_css_culori() {
	$c = get_theme_mod( 'eg_culoare', '#1546A8' );
	if ( ! preg_match( '/^#([0-9a-f]{6})$/i', $c, $m ) ) {
		$c = '#1546A8';
		$m = array( '', '1546A8' );
	}
	$rgb    = array_map( 'hexdec', str_split( $m[1], 2 ) );
	$inchis = vsprintf( '#%02x%02x%02x', array_map( fn( $v ) => (int) round( $v * 0.62 ), $rgb ) );
	return ":root{--eg-culoare:{$c};--eg-inchis:{$inchis};}";
}

/** Lista de competiții (categoriile existente, în ordinea din setări). */
function eg_competitii() {
	$slugs = array_filter( array_map( 'trim', explode( ',', (string) get_theme_mod( 'eg_competitii', 'superliga,nationala,champions-league,europa-league,conference-league,premier-league,laliga,serie-a,bundesliga,ligue-1,copa-libertadores,brasileirao' ) ) ) );
	if ( ! in_array( 'nationalele-europei', $slugs, true ) ) {
		$i = array_search( 'nationala', $slugs, true );
		array_splice( $slugs, false === $i ? count( $slugs ) : $i + 1, 0, array( 'nationalele-europei' ) );
	}
	$out   = array();
	foreach ( $slugs as $s ) {
		$cat = get_category_by_slug( $s );
		if ( $cat ) {
			$out[] = $cat;
		}
	}
	return $out;
}
