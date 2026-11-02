<?php
/**
 * Actualizarea temei din GitHub: când tema/versiune.txt din depozit are o versiune mai nouă,
 * WordPress arată actualizarea în Panou → Actualizări (și în Aspect → Teme), ca la orice temă.
 * Pachetul: tema/eurogol.zip din același depozit.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'pre_set_site_transient_update_themes', 'eg_verifica_actualizare' );
function eg_verifica_actualizare( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}
	$depozit = function_exists( 'eg_import_depozit' ) ? eg_import_depozit() : '';
	if ( ! $depozit ) {
		return $transient;
	}
	$baza = 'https://raw.githubusercontent.com/' . $depozit . '/main/tema/';
	$r    = wp_remote_get( $baza . 'versiune.txt?t=' . time(), array( 'timeout' => 10 ) );
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
		return $transient;
	}
	$noua = trim( wp_remote_retrieve_body( $r ) );
	$tema = wp_get_theme( 'eurogol' );
	if ( preg_match( '/^\d+\.\d+\.\d+$/', $noua ) && version_compare( $noua, $tema->get( 'Version' ), '>' ) ) {
		$transient->response['eurogol'] = array(
			'theme'       => 'eurogol',
			'new_version' => $noua,
			'url'         => 'https://github.com/' . $depozit . '/tree/main/tema',
			'package'     => $baza . 'eurogol.zip',
		);
	}
	return $transient;
}
