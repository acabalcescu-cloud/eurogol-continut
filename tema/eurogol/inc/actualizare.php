<?php
/**
 * Actualizarea temei din GitHub (tema/eurogol.zip din depozitul de conținut), în două feluri:
 *
 * 1. POST /wp-json/eg/v1/tema – ca la povestilestiintei.ro (/ps/v1/tema): doar administratori (update_themes).
 *    Descarcă arhiva, o verifică cu amprenta SHA-256 din tema/sha256.txt, o instalează peste tema actuală,
 *    apoi golește OPcache și cache-ul LiteSpeed. Întoarce { inainte, acum }.
 * 3. Singură: la fiecare import (15 minute), dacă versiune.txt e mai nouă, se instalează automat.
 * 2. Panou → Actualizări: când tema/versiune.txt are o versiune mai nouă, WordPress arată actualizarea ca la orice temă.
 *    Pachetul trece prin aceeași verificare SHA-256 înainte de instalare.
 */

defined( 'ABSPATH' ) || exit;

function eg_tema_baza() {
	$depozit = function_exists( 'eg_import_depozit' ) ? eg_import_depozit() : '';
	return $depozit ? 'https://raw.githubusercontent.com/' . $depozit . '/main/tema/' : '';
}

/** Amprenta SHA-256 așteptată (prima „vorbă” din tema/sha256.txt) sau WP_Error. */
function eg_tema_sha_asteptat() {
	$r = wp_remote_get( eg_tema_baza() . 'sha256.txt?nocache=' . time(), array( 'timeout' => 30 ) );
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
		return new WP_Error( 'eg_tema', 'Nu pot citi tema/sha256.txt din depozit.', array( 'status' => 502 ) );
	}
	$sha = strtolower( trim( strtok( wp_remote_retrieve_body( $r ), " \n" ) ) );
	return preg_match( '/^[0-9a-f]{64}$/', $sha ) ? $sha : new WP_Error( 'eg_tema', 'tema/sha256.txt nu conține o amprentă validă.', array( 'status' => 502 ) );
}

/** Golește cache-urile după o schimbare (OPcache pentru PHP, LiteSpeed pentru pagini). */
function eg_goleste_cache() {
	if ( function_exists( 'opcache_reset' ) ) {
		@opcache_reset(); // phpcs:ignore
	}
	do_action( 'litespeed_purge_all' ); // dacă e instalat modulul LiteSpeed Cache
	// Fără modul: serverul LiteSpeed golește cache-ul când primește acest antet în orice răspuns PHP (și în wp-cron.php).
	if ( ! headers_sent() ) {
		header( 'X-LiteSpeed-Purge: *', false );
	}
	update_option( 'eg_cache_golit', time(), false );
}

/** Paginile care se schimbă des (prima pagină, liste, „Pe scurt”) stau în cache cel mult 5 minute. */
add_action( 'template_redirect', 'eg_cache_scurt' );
function eg_cache_scurt() {
	if ( is_user_logged_in() || headers_sent() ) {
		return;
	}
	$ttl = ( is_front_page() || is_home() || is_archive() || is_search() ) ? 300 : 3600;
	do_action( 'litespeed_control_set_ttl', $ttl ); // modulul LiteSpeed Cache (altfel ține paginile 7 zile)
	header( 'X-LiteSpeed-Cache-Control: public, max-age=' . $ttl );
}

/* ---------- 1. Ruta REST, ca la povestilestiintei ---------- */

add_action( 'rest_api_init', 'eg_tema_rest' );
function eg_tema_rest() {
	register_rest_route(
		'eg/v1',
		'/tema',
		array(
			'methods'             => 'POST',
			'permission_callback' => fn() => current_user_can( 'update_themes' ),
			'callback'            => 'eg_actualizeaza_tema',
		)
	);
	register_rest_route(
		'eg/v1',
		'/cache',
		array(
			'methods'             => 'POST',
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
			'callback'            => function () {
				eg_goleste_cache();
				return array( 'golit' => true );
			},
		)
	);
}

function eg_actualizeaza_tema() {
	if ( ! eg_tema_baza() ) {
		return new WP_Error( 'eg_tema', 'Depozitul nu e setat (Instrumente → Import Eurogol).', array( 'status' => 400 ) );
	}
	$asteptat = eg_tema_sha_asteptat();
	if ( is_wp_error( $asteptat ) ) {
		return $asteptat;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/theme.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	$fisier = download_url( eg_tema_baza() . 'eurogol.zip?nocache=' . time(), 120 );
	if ( is_wp_error( $fisier ) ) {
		return $fisier;
	}
	if ( ! hash_equals( $asteptat, hash_file( 'sha256', $fisier ) ) ) {
		@unlink( $fisier ); // phpcs:ignore
		return new WP_Error( 'eg_tema', 'Arhiva nu corespunde cu sha256.txt; n-am instalat nimic.', array( 'status' => 409 ) );
	}
	$inainte  = wp_get_theme( 'eurogol' )->get( 'Version' );
	$upgrader = new Theme_Upgrader( new WP_Ajax_Upgrader_Skin() );
	$rezultat = $upgrader->install( $fisier, array( 'overwrite_package' => true, 'clear_update_cache' => true ) );
	@unlink( $fisier ); // phpcs:ignore
	if ( is_wp_error( $rezultat ) || ! $rezultat ) {
		$err = is_wp_error( $rezultat ) ? $rezultat->get_error_message() : implode( '; ', (array) $upgrader->skin->get_error_messages() );
		return new WP_Error( 'eg_tema', 'Instalarea a eșuat: ' . $err, array( 'status' => 500 ) );
	}
	wp_clean_themes_cache();
	eg_goleste_cache();
	return array( 'inainte' => $inainte, 'acum' => wp_get_theme( 'eurogol' )->get( 'Version' ) );
}

/* ---------- 3. Instalarea automată, la fiecare import (15 minute) ---------- */

/**
 * Dacă tema/versiune.txt din depozit e mai nouă decât tema instalată, o instalează singură
 * (aceeași verificare SHA-256 ca mai sus). Se oprește din Instrumente → Import Eurogol.
 */
add_action( 'eg_import', 'eg_auto_actualizare', 20 );
function eg_auto_actualizare() {
	if ( ! get_option( 'eg_tema_auto', 1 ) || ! eg_tema_baza() ) {
		return;
	}
	$r = wp_remote_get( eg_tema_baza() . 'versiune.txt?nocache=' . time(), array( 'timeout' => 10 ) );
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
		return;
	}
	$noua    = trim( wp_remote_retrieve_body( $r ) );
	$inainte = wp_get_theme( 'eurogol' )->get( 'Version' );
	if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $noua ) || ! version_compare( $noua, $inainte, '>' ) ) {
		return;
	}
	// O singură încercare pe versiune la 30 de minute (GitHub poate servi câteva minute arhiva veche).
	if ( get_transient( 'eg_tema_incercare_' . md5( $noua ) ) ) {
		return;
	}
	set_transient( 'eg_tema_incercare_' . md5( $noua ), 1, 30 * MINUTE_IN_SECONDS );
	$rez = eg_actualizeaza_tema();
	if ( function_exists( 'eg_import_jurnal' ) ) {
		eg_import_jurnal( is_wp_error( $rez ) ? "Tema {$noua} nu s-a instalat: " . $rez->get_error_message() : "Tema actualizată: {$rez['inainte']} → {$rez['acum']}." );
	}
}

/* ---------- 2. Panou → Actualizări ---------- */

add_filter( 'pre_set_site_transient_update_themes', 'eg_verifica_actualizare' );
function eg_verifica_actualizare( $transient ) {
	if ( ! is_object( $transient ) || ! eg_tema_baza() ) {
		return $transient;
	}
	$r = wp_remote_get( eg_tema_baza() . 'versiune.txt?nocache=' . time(), array( 'timeout' => 10 ) );
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
		return $transient;
	}
	$noua = trim( wp_remote_retrieve_body( $r ) );
	if ( preg_match( '/^\d+\.\d+\.\d+$/', $noua ) && version_compare( $noua, wp_get_theme( 'eurogol' )->get( 'Version' ), '>' ) ) {
		$transient->response['eurogol'] = array(
			'theme'       => 'eurogol',
			'new_version' => $noua,
			'url'         => 'https://github.com/' . eg_import_depozit() . '/tree/main/tema',
			'package'     => eg_tema_baza() . 'eurogol.zip',
		);
	}
	return $transient;
}

/** Și actualizarea din Panou verifică amprenta SHA-256 înainte să instaleze. */
add_filter( 'upgrader_pre_download', 'eg_verifica_pachet', 10, 4 );
function eg_verifica_pachet( $raspuns, $pachet, $upgrader, $hook_extra ) {
	if ( false !== $raspuns || ( $hook_extra['theme'] ?? '' ) !== 'eurogol' || ! eg_tema_baza() || 0 !== strpos( $pachet, eg_tema_baza() ) ) {
		return $raspuns;
	}
	$asteptat = eg_tema_sha_asteptat();
	if ( is_wp_error( $asteptat ) ) {
		return $asteptat;
	}
	$fisier = download_url( $pachet . '?nocache=' . time(), 120 );
	if ( is_wp_error( $fisier ) ) {
		return $fisier;
	}
	if ( ! hash_equals( $asteptat, hash_file( 'sha256', $fisier ) ) ) {
		@unlink( $fisier ); // phpcs:ignore
		return new WP_Error( 'eg_tema', 'Arhiva nu corespunde cu sha256.txt; n-am instalat nimic.' );
	}
	return $fisier;
}

add_action( 'upgrader_process_complete', 'eg_dupa_actualizare', 10, 2 );
function eg_dupa_actualizare( $upgrader, $extra ) {
	if ( 'theme' === ( $extra['type'] ?? '' ) && in_array( 'eurogol', (array) ( $extra['themes'] ?? array() ), true ) ) {
		eg_goleste_cache();
	}
}
