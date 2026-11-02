<?php
/**
 * Semnăturile pe departamente: importul alege autorul după subiectul articolului (dacă JSON-ul nu are câmpul „autor”).
 * Conturile se creează singure la prima folosire, fără email (ca la povestilestiintei), cu rolul Autor și descrierea departamentului.
 *
 * Ordinea regulilor contează: prima potrivire câștigă.
 */

defined( 'ABSPATH' ) || exit;

/** login => [ nume afișat, departament, descriere ] */
function eg_redactori() {
	return array(
		'andrei.toma'        => array( 'Andrei Toma', 'SuperLiga', 'SuperLiga, Cupa României și cluburile românești: cronicile meciurilor, cronica etapei, clasamentul și tot ce se întâmplă în campionatul nostru.' ),
		'razvan.petrescu'    => array( 'Răzvan Petrescu', 'Naționala și stranierii', 'Echipa națională, românii care joacă în străinătate și antrenorii români de peste hotare.' ),
		'mihai.georgescu'    => array( 'Mihai Georgescu', 'Cupele europene', 'Champions League, Europa League și Conference League, cu atenție specială pentru echipele românești și adversarii lor.' ),
		'vlad.ene'           => array( 'Vlad Ene', 'Marile campionate', 'Premier League, LaLiga, Serie A, Bundesliga și Ligue 1: etapele, derby-urile, antrenorii și vedetele.' ),
		'catalin.dumitru'    => array( 'Cătălin Dumitru', 'Transferuri', 'Mutările confirmate, contractele și zvonurile serioase, din SuperLiga și din marile campionate.' ),
		'raul.neagu'         => array( 'Raul Neagu', 'America de Sud', 'Copa Libertadores, Brasileirão, fotbalul argentinian și echipele naționale din CONMEBOL.' ),
		'sorin.avram'        => array( 'Sorin Avram', 'Povești', 'Istoria fotbalului: legende, meciuri memorabile, stadioane și rivalități, de la Ghencea la Maracanã.' ),
		'cristian.manolache' => array( 'Cristian Manolache', 'Declarații și opinii', 'Interviuri, conferințe de presă, declarații de după meci și editoriale.' ),
	);
}

function eg_fara_diacritice( $s ) {
	return strtolower( remove_accents( (string) $s ) );
}

/** Alege login-ul redactorului pentru un articol. */
function eg_alege_redactor( $id, $titlu, $etichete, $categorie, $e_cronica = false ) {
	$t   = ' ' . eg_fara_diacritice( $titlu . ' ' . implode( ' ', (array) $etichete ) ) . ' ';
	$cat = eg_fara_diacritice( $categorie );
	$id  = strtolower( (string) $id );
	$are = fn( $re ) => (bool) preg_match( $re, $t );

	$sud    = '/libertadores|sudamericana|conmebol|copa america|brasileirao|brazilia|argentin|boca juniors|river plate|flamengo|palmeiras|superclasico/';
	$europa = '/champions league|liga campionilor|europa league|conference league|\buefa\b/';
	$mari   = '/premier league|laliga|la liga|serie a|bundesliga|ligue 1/';

	if ( false !== strpos( $id, '-poveste-' ) || preg_match( '/povest|legend|istori/', $cat ) ) {
		return 'sorin.avram';
	}
	// Cronicile de meci și de etapă merg la departamentul competiției.
	if ( $e_cronica || preg_match( '/-etapa-\d+-/', $id ) ) {
		if ( preg_match( $sud, $cat . $t ) ) {
			return 'raul.neagu';
		}
		if ( preg_match( '/nationala/', $cat ) ) {
			return 'razvan.petrescu';
		}
		if ( preg_match( $europa, $cat ) ) {
			return 'mihai.georgescu';
		}
		if ( preg_match( $mari, $cat ) ) {
			return 'vlad.ene';
		}
		return 'andrei.toma';
	}
	if ( preg_match( $sud, $cat ) || $are( $sud ) ) {
		return 'raul.neagu';
	}
	if ( $are( '/transfer|contract|semneaz|a semnat|imprumut|oferta|clauza|impresar|vandut|cumpar|achizit|mercato/' ) ) {
		return 'catalin.dumitru';
	}
	if ( preg_match( '/nationala/', $cat ) || $are( '/nationala|tricolor|stranier|\bromanul\b|\bun roman\b|chivu|lucescu|dragusin|ianis hagi|razvan marin|stanciu|birligea|mihaila|dennis man/' ) ) {
		return 'razvan.petrescu';
	}
	if ( $are( '/„|declarat|conferint|interviu|editorial|a spus|a reactionat|reactia/' ) ) {
		return 'cristian.manolache';
	}
	if ( preg_match( $europa, $cat ) || $are( $europa ) ) {
		return 'mihai.georgescu';
	}
	if ( preg_match( $mari, $cat ) || $are( $mari ) ) {
		return 'vlad.ene';
	}
	return 'andrei.toma';
}

/** ID-ul contului (îl creează dacă lipsește). */
function eg_redactor_id( $login ) {
	$r = eg_redactori();
	if ( ! isset( $r[ $login ] ) ) {
		return 0;
	}
	$u = get_user_by( 'login', $login );
	if ( $u ) {
		// Conturile create de versiunea 1.3.0 aveau o adresă de rezervă; acum rămân fără email.
		if ( str_ends_with( (string) $u->user_email, '@redactori.eurogol.invalid' ) ) {
			wp_update_user( array( 'ID' => $u->ID, 'user_email' => '' ) );
		}
		return (int) $u->ID;
	}
	list( $nume, $departament, $descriere ) = $r[ $login ];
	$parti = explode( ' ', $nume, 2 );
	$id    = wp_insert_user(
		array(
			'user_login'    => $login,
			'user_pass'     => wp_generate_password( 40 ),
			'display_name'  => $nume,
			'nickname'      => $nume,
			'first_name'    => $parti[0],
			'last_name'     => $parti[1] ?? '',
			'user_nicename' => sanitize_title( $nume ),
			'description'   => $departament . '. ' . $descriere,
			'role'          => 'author',
		)
	);
	if ( is_wp_error( $id ) ) {
		return 0;
	}
	update_user_meta( $id, 'eg_departament', $departament );
	return (int) $id;
}

/**
 * O singură dată (și din nou când se schimbă EG_REDACTORI_VERSIUNE): articolele importate deja
 * primesc semnătura departamentului lor. Rulează la import.
 */
const EG_REDACTORI_VERSIUNE = 1;
function eg_realoca_redactori() {
	if ( (int) get_option( 'eg_redactori_versiune', 0 ) >= EG_REDACTORI_VERSIUNE ) {
		return 0;
	}
	$n   = 0;
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => '_eg_id',
		)
	);
	foreach ( $ids as $pid ) {
		if ( get_post_meta( $pid, '_eg_autor_explicit', true ) ) {
			continue;
		}
		$cat   = get_the_category( $pid );
		$login = eg_alege_redactor(
			get_post_meta( $pid, '_eg_id', true ),
			get_the_title( $pid ),
			wp_get_post_tags( $pid, array( 'fields' => 'names' ) ),
			$cat ? $cat[0]->name : '',
			(bool) get_post_meta( $pid, '_eg_cronica', true ) || ! preg_match( '/-(stire|poveste|video|opinie)-|-etapa-/', (string) get_post_meta( $pid, '_eg_id', true ) )
		);
		$uid = eg_redactor_id( $login );
		if ( $uid && (int) get_post_field( 'post_author', $pid ) !== $uid ) {
			wp_update_post( array( 'ID' => $pid, 'post_author' => $uid ) );
			$n++;
		}
	}
	update_option( 'eg_redactori_versiune', EG_REDACTORI_VERSIUNE );
	if ( $n ) {
		do_action( 'eg_redactori_realocati' );
	}
	if ( $n && function_exists( 'eg_import_jurnal' ) ) {
		eg_import_jurnal( "Semnături pe departamente: {$n} articole reatribuite." );
	}
	return $n;
}
