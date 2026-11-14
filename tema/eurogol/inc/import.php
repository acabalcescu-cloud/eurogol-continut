<?php
/**
 * Importul articolelor din depozitul GitHub de conținut (implicit acabalcescu-cloud/eurogol-continut).
 *
 * La fiecare 15 minute citește index.json:
 *   { "cronici": ["cronici/<id>.json", ...], "actualizate": ["cronici/<id>.json", ...] }
 * și creează articolele noi (cu coperta ca imagine reprezentativă, categoria și etichetele),
 * apoi actualizează articolele din „actualizate” al căror câmp „actualizat” s-a schimbat.
 *
 * Articolul JSON: { id, titlu, rezumat, continut (HTML), categorie, etichete[], imagine | imagine_url,
 *                   data_publicare ("AAAA-LL-ZZ hh:mm", ora României), status ("draft" = ciornă),
 *                   autor: { login, nume }, actualizat, tip: "video" + video_url }
 * Legăturile interne <a href="ps:<id>"> (sau "eg:<id>") devin linkuri spre articolul nostru, după ce acesta e publicat.
 *
 * Manual: Instrumente → Import Eurogol, sau POST /wp-json/eg/v1/import (administratori).
 */

defined( 'ABSPATH' ) || exit;

const EG_IMPORT_HOOK = 'eg_import';

function eg_import_depozit() {
	return trim( (string) get_option( 'eg_import_depozit', 'acabalcescu-cloud/eurogol-continut' ) );
}

function eg_import_url( $cale ) {
	$ramura = trim( (string) get_option( 'eg_import_ramura', 'main' ) ) ?: 'main';
	return 'https://raw.githubusercontent.com/' . eg_import_depozit() . '/' . rawurlencode( $ramura ) . '/' . ltrim( $cale, '/' );
}

/* ---------- Programarea la 15 minute ---------- */

add_filter( 'cron_schedules', 'eg_import_interval' );
function eg_import_interval( $s ) {
	$s['eg_15m'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'La 15 minute' );
	$s['eg_5m']  = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'La 5 minute' );
	return $s;
}

add_action( 'init', 'eg_import_programeaza' );
function eg_import_programeaza() {
	if ( ! eg_import_depozit() ) {
		return;
	}
	// Programarea veche (15 minute) trece la 5 minute.
	if ( wp_next_scheduled( EG_IMPORT_HOOK ) && 'eg_5m' !== wp_get_schedule( EG_IMPORT_HOOK ) ) {
		wp_clear_scheduled_hook( EG_IMPORT_HOOK );
	}
	if ( ! wp_next_scheduled( EG_IMPORT_HOOK ) ) {
		wp_schedule_event( time() + 60, 'eg_5m', EG_IMPORT_HOOK );
	}
	// Plasă de siguranță: dacă cron-ul găzduirii n-a mai rulat de 20 de minute, orice vizită (necache-uită) îl pornește.
	if ( ! wp_doing_cron() && time() - (int) get_option( 'eg_import_ultima', 0 ) > 20 * MINUTE_IN_SECONDS && ! get_transient( 'eg_cron_pornit' ) ) {
		set_transient( 'eg_cron_pornit', 1, 5 * MINUTE_IN_SECONDS );
		wp_remote_post( site_url( 'wp-cron.php?doing_wp_cron=' . sprintf( '%.22F', microtime( true ) ) ), array( 'timeout' => 0.01, 'blocking' => false, 'sslverify' => false ) );
	}
}

add_action( 'switch_theme', 'eg_import_opreste' );
function eg_import_opreste() {
	wp_clear_scheduled_hook( EG_IMPORT_HOOK );
}

add_action( EG_IMPORT_HOOK, 'eg_import_ruleaza' );

/* ---------- Jurnalul ---------- */

function eg_import_jurnal( $mesaj ) {
	$j   = (array) get_option( 'eg_import_jurnal', array() );
	$j[] = wp_date( 'Y-m-d H:i' ) . '  ' . $mesaj;
	update_option( 'eg_import_jurnal', array_slice( $j, -80 ), false );
}

/* ---------- Citirea din GitHub ---------- */

/** Calea completă în depozit: acceptă și intrări scrise doar cu id-ul („2026-11-05-stire-x”). */
function eg_import_cale( $cale ) {
	$cale = trim( (string) $cale );
	if ( '' !== $cale && 0 !== strpos( $cale, 'cronici/' ) && false === strpos( $cale, '/' ) ) {
		$cale = 'cronici/' . preg_replace( '/\.json$/', '', $cale ) . '.json';
	}
	return $cale;
}

function eg_import_json( $cale ) {
	$r = wp_remote_get( add_query_arg( 't', time(), eg_import_url( $cale ) ), array( 'timeout' => 20 ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$cod = wp_remote_retrieve_response_code( $r );
	if ( 200 !== $cod ) {
		return new WP_Error( 'eg_http', "GitHub a răspuns {$cod} la {$cale}" );
	}
	$date = json_decode( wp_remote_retrieve_body( $r ), true );
	return is_array( $date ) ? $date : new WP_Error( 'eg_json', "JSON invalid în {$cale}" );
}

/** Articolul deja importat cu acest id (orice stare, în afară de coș). */
function eg_import_gaseste( $eg_id ) {
	$q = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
			'meta_key'       => '_eg_id',
			'meta_value'     => $eg_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	return $q ? (int) $q[0] : 0;
}

/**
 * Rulează importul. Întoarce un rezumat: [ 'noi' => n, 'actualizate' => n, 'erori' => n ].
 * $limita = câte fișiere noi se citesc la o rulare (restul la următoarea).
 */
function eg_import_ruleaza( $limita = 15 ) {
	$rez = array( 'noi' => 0, 'actualizate' => 0, 'erori' => 0 );
	if ( ! eg_import_depozit() ) {
		return $rez;
	}
	if ( get_transient( 'eg_import_blocare' ) ) {
		return $rez;
	}
	set_transient( 'eg_import_blocare', 1, 10 * MINUTE_IN_SECONDS );
	eg_realoca_redactori();

	$index = eg_import_json( 'index.json' );
	if ( is_wp_error( $index ) ) {
		eg_import_jurnal( 'Eroare: ' . $index->get_error_message() );
		delete_transient( 'eg_import_blocare' );
		$rez['erori']++;
		return $rez;
	}

	// Articolele noi: cele mai vechi primele, ca ordinea de pe site să urmeze ordinea din depozit.
	$vazute = (array) get_option( 'eg_import_vazute', array() );
	$cai    = array_reverse( array_values( array_map( 'eg_import_cale', array_filter( (array) ( $index['cronici'] ?? array() ), 'is_string' ) ) ) );
	$citite = 0;
	foreach ( $cai as $cale ) {
		if ( isset( $vazute[ $cale ] ) ) {
			continue;
		}
		if ( $citite >= $limita ) {
			break;
		}
		$citite++;
		$art = eg_import_json( $cale );
		if ( is_wp_error( $art ) ) {
			eg_import_jurnal( 'Eroare: ' . $art->get_error_message() );
			$rez['erori']++;
			continue;
		}
		$id = eg_import_articol( $art, $cale );
		if ( is_wp_error( $id ) ) {
			eg_import_jurnal( "Eroare la {$cale}: " . $id->get_error_message() );
			$rez['erori']++;
			continue;
		}
		$vazute[ $cale ] = $id;
		$rez['noi'] += $id ? 1 : 0;
		update_option( 'eg_import_vazute', $vazute, false );
	}

	// Articolele completate după publicare.
	foreach ( array_map( 'eg_import_cale', array_filter( (array) ( $index['actualizate'] ?? array() ), 'is_string' ) ) as $cale ) {
		$art = eg_import_json( $cale );
		if ( is_wp_error( $art ) || empty( $art['id'] ) || ( empty( $art['actualizat'] ) && empty( $art['coperta_v'] ) ) ) {
			continue;
		}
		$art['actualizat'] = $art['actualizat'] ?? '';
		$post_id = eg_import_gaseste( sanitize_title( $art['id'] ) );
		if ( ! $post_id ) {
			continue;
		}
		// Coperta refăcută: „coperta_v” nou → imaginea se înlocuiește.
		if ( ! empty( $art['coperta_v'] ) && (string) get_post_meta( $post_id, '_eg_coperta_v', true ) !== (string) $art['coperta_v'] ) {
			if ( eg_import_coperta( $post_id, $art, $cale ) ) {
				update_post_meta( $post_id, '_eg_coperta_v', (string) $art['coperta_v'] );
				eg_import_jurnal( 'Copertă nouă: ' . get_the_title( $post_id ) );
				$rez['actualizate']++;
			}
		}
		if ( get_post_meta( $post_id, '_eg_actualizat', true ) === $art['actualizat'] ) {
			continue;
		}
		wp_update_post(
			wp_slash(
			array(
				'ID'           => $post_id,
				'post_title'   => wp_strip_all_tags( $art['titlu'] ?? get_the_title( $post_id ) ),
				'post_excerpt' => wp_strip_all_tags( $art['rezumat'] ?? '' ),
				'post_content' => eg_import_persoane( eg_import_continut( $art ), $art, $post_id ),
			)
			)
		);
		update_post_meta( $post_id, '_eg_actualizat', $art['actualizat'] );
		if ( ! empty( $art['video'] ) ) {
			update_post_meta( $post_id, '_eg_video_rezumat', esc_url_raw( $art['video'] ) );
			update_post_meta( $post_id, '_eg_video_sursa', sanitize_text_field( (string) ( $art['video_sursa'] ?? '' ) ) );
			wp_set_post_tags( $post_id, 'Video', true );
		}
		if ( function_exists( 'eg_persoane_eticheteaza' ) ) {
			eg_persoane_eticheteaza( $post_id );
		}
		if ( array_key_exists( 'clasament', $art ) ) {
			update_post_meta( $post_id, '_eg_clasament', $art['clasament'] ? sanitize_title( $art['clasament'] ) : 'nu' );
		}
		eg_import_jurnal( 'Actualizat: ' . get_the_title( $post_id ) );
		$rez['actualizate']++;
	}

	if ( $rez['noi'] || $rez['actualizate'] || did_action( 'eg_redactori_realocati' ) ) {
		// Prima pagină și listele trebuie să arate imediat articolele noi, nu versiunea din cache.
		if ( function_exists( 'eg_goleste_cache' ) ) {
			eg_goleste_cache();
		}
	}
	update_option( 'eg_import_ultima', time(), false );
	if ( $rez['noi'] || $rez['actualizate'] || $rez['erori'] ) {
		eg_import_jurnal( sprintf( 'Rulare: %d noi, %d actualizate, %d erori.', $rez['noi'], $rez['actualizate'], $rez['erori'] ) );
	}
	delete_transient( 'eg_import_blocare' );
	return $rez;
}

/** Portretele din depozit (unelte/fete/fete.json), citite o dată pe rulare. */
function eg_import_fete() {
	static $fete = null;
	if ( null === $fete ) {
		$j    = eg_import_json( 'unelte/fete/fete.json' );
		$fete = array();
		foreach ( is_wp_error( $j ) ? array() : $j as $x ) {
			if ( ! empty( $x['slug'] ) ) {
				$fete[ $x['slug'] ] = $x;
			}
		}
	}
	return $fete;
}

/** Fotografia unei persoane, în biblioteca media (o singură dată pe fotografie); altfel adresa originală. */
function eg_import_foto( $x, $post_id ) {
	$url = $x['foto'] ?? '';
	if ( ! $url ) {
		return '';
	}
	$atasate = (array) get_option( 'eg_foto_atasamente', array() );
	if ( ! empty( $atasate[ $url ] ) && wp_get_attachment_url( $atasate[ $url ] ) ) {
		return wp_get_attachment_image_url( $atasate[ $url ], 'large' );
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_sideload_image( esc_url_raw( $url ), $post_id, $x['nume'], 'id' );
	if ( is_wp_error( $id ) ) {
		return $url; // Wikimedia permite afișarea directă a fișierelor
	}
	update_post_meta( $id, '_wp_attachment_image_alt', $x['nume'] );
	wp_update_post( array( 'ID' => $id, 'post_excerpt' => 'Foto: ' . $x['credit'] ) );
	$atasate[ $url ] = $id;
	update_option( 'eg_foto_atasamente', $atasate, false );
	return wp_get_attachment_image_url( $id, 'large' );
}

/**
 * Persoanele articolului: „persoane” (fotografii în text) și „fata” (portretul de pe copertă).
 * Fotografia intră după al doilea paragraf, cu autorul și licența; creditul portretului, la final.
 */
function eg_import_persoane( $html, $art, $post_id ) {
	$fete     = eg_import_fete();
	$persoane = array_values( array_unique( array_filter( array_merge( (array) ( $art['persoane'] ?? array() ), array( $art['fata'] ?? '' ) ) ) ) );
	$figuri   = '';
	foreach ( array_slice( $persoane, 0, 2 ) as $s ) {
		$x = $fete[ $s ] ?? null;
		if ( ! $x || empty( $x['foto'] ) ) {
			continue;
		}
		$src     = eg_import_foto( $x, $post_id );
		$licenta = ! empty( $x['sursa'] ) ? ' (<a href="' . esc_url( $x['sursa'] ) . '" rel="nofollow">sursa</a>)' : '';
		$figuri .= '<figure class="eg-foto"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $x['nume'] ) . '" loading="lazy"><figcaption>' . esc_html( $x['nume'] ) . '. Foto: ' . esc_html( $x['credit'] ) . $licenta . '</figcaption></figure>';
	}
	if ( $figuri ) {
		$poz = 0;
		for ( $k = 0; $k < 2; $k++ ) {
			$p = strpos( $html, '</p>', $poz );
			if ( false === $p ) {
				break;
			}
			$poz = $p + 4;
		}
		$html = $poz ? substr( $html, 0, $poz ) . "\n" . $figuri . "\n" . substr( $html, $poz ) : $figuri . $html;
	}
	if ( ! empty( $art['fundal'] ) ) {
		$fo = eg_import_json( 'unelte/fotografii/fotografii.json' );
		foreach ( is_wp_error( $fo ) ? array() : $fo as $y ) {
			if ( ( $y['slug'] ?? '' ) === $art['fundal'] && ! empty( $y['credit'] ) ) {
				$html .= "\n" . '<p class="eg-credit-foto">Fotografie pe copertă: ' . esc_html( $y['credit'] ) . ( ! empty( $y['sursa'] ) ? ' (<a href="' . esc_url( $y['sursa'] ) . '" rel="nofollow">sursa</a>)' : '' ) . '.</p>';
			}
		}
	}
	$f = $fete[ $art['fata'] ?? '' ] ?? null;
	if ( $f && ! empty( $f['credit'] ) ) {
		$html .= "\n" . '<p class="eg-credit-foto">Portret pe copertă: ' . esc_html( $f['nume'] ) . ', prelucrare Eurogol după o fotografie de ' . esc_html( $f['credit'] ) . '.</p>';
	}
	return $html;
}

/** HTML-ul articolului, cu legăturile interne pregătite pentru afișare. */
function eg_import_continut( $art, $post_id = 0 ) {
	$html = (string) ( $art['continut'] ?? '' );
	if ( 'video' === ( $art['tip'] ?? '' ) && ! empty( $art['video_url'] ) ) {
		// URL-ul pe rând separat devine player (oEmbed).
		$html = "\n" . esc_url_raw( $art['video_url'] ) . "\n\n" . ( $html ?: '<p>' . esc_html( $art['rezumat'] ?? '' ) . '</p>' );
	}
	// ps:<id> / eg:<id> → #eg:<id> (filtrul de conținut îl transformă în link real; ps:meci:… nu există pe Eurogol, rămâne text).
	$html = preg_replace( '/href="(?:ps|eg):meci:[^"]*"/i', 'href="#eg:-"', $html );
	$html = preg_replace( '/href="(?:ps|eg):([a-z0-9-]+)"/i', 'href="#eg:$1"', $html );
	return $html;
}

/**
 * Știrile primesc și categoria competiției (SuperLiga, Serie A…), ca să apară în secțiunile ei,
 * după câmpul „clasament” sau după echipele pomenite (aceeași regulă ca tabelul de la final).
 */
function eg_import_categoria_competitiei( $post_id ) {
	if ( ! function_exists( 'eg_clasament_pentru' ) ) {
		return;
	}
	$x = eg_clasament_pentru( $post_id );
	if ( ! $x ) {
		return;
	}
	$nume = eg_clasament_competitii()[ $x[0] ][1] ?? '';
	$cat  = $nume ? eg_import_categorie( $nume ) : 0;
	if ( $cat && ! has_category( $cat, $post_id ) ) {
		wp_set_post_categories( $post_id, array( $cat ), true );
	}
}

/** O singură dată: categoria competiției pentru articolele deja publicate. */
add_action( 'eg_import', 'eg_import_categorii_vechi', 40 );
function eg_import_categorii_vechi() {
	if ( (int) get_option( 'eg_categorii_v', 0 ) >= 1 ) {
		return;
	}
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => array( 'publish', 'future' ), 'posts_per_page' => 500, 'fields' => 'ids', 'meta_key' => '_eg_id' ) ) as $id ) {
		eg_import_categoria_competitiei( $id );
	}
	update_option( 'eg_categorii_v', 1 );
	if ( function_exists( 'eg_goleste_cache' ) ) {
		eg_goleste_cache();
	}
}

/** Categoria după nume (o creează dacă lipsește). */
function eg_import_categorie( $nume ) {
	$nume = trim( (string) $nume );
	if ( '' === $nume ) {
		return 0;
	}
	$slug = sanitize_title( $nume );
	$t    = get_term_by( 'slug', $slug, 'category' );
	if ( $t ) {
		return (int) $t->term_id;
	}
	$nou = wp_insert_term( $nume, 'category', array( 'slug' => $slug ) );
	return is_wp_error( $nou ) ? 0 : (int) $nou['term_id'];
}

/** Autorul: contul indicat în JSON (creat cu rolul Autor dacă nu există) sau autorul implicit. */
function eg_import_autor( $autor ) {
	if ( is_array( $autor ) && ! empty( $autor['login'] ) ) {
		$login = sanitize_user( $autor['login'], true );
		$u     = get_user_by( 'login', $login );
		if ( $u ) {
			return $u->ID;
		}
		$id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 32 ),
				'display_name' => sanitize_text_field( $autor['nume'] ?? $login ),
				'role'         => 'author',
			)
		);
		if ( ! is_wp_error( $id ) ) {
			return $id;
		}
	}
	$implicit = (int) get_option( 'eg_import_autor', 0 );
	if ( $implicit && get_userdata( $implicit ) ) {
		return $implicit;
	}
	$admini = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID', 'orderby' => 'ID' ) );
	return $admini ? (int) $admini[0] : 1;
}

/** Descarcă coperta (fișierul de lângă articol sau „imagine_url”) și o pune ca imagine reprezentativă; șterge coperta veche. */
function eg_import_coperta( $id, $art, $cale ) {
	$img = '';
	if ( ! empty( $art['imagine_url'] ) ) {
		$img = $art['imagine_url'];
	} elseif ( ! empty( $art['imagine'] ) ) {
		$dir = trim( dirname( $cale ), './' );
		$img = add_query_arg( 'v', rawurlencode( (string) ( $art['coperta_v'] ?? '1' ) ), eg_import_url( ( $dir ? $dir . '/' : '' ) . $art['imagine'] ) );
	}
	if ( ! $img ) {
		return false;
	}
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$veche = get_post_thumbnail_id( $id );
	$media = media_sideload_image( esc_url_raw( $img ), $id, wp_strip_all_tags( $art['titlu'] ?? '' ), 'id' );
	if ( is_wp_error( $media ) ) {
		eg_import_jurnal( 'Coperta n-a putut fi descărcată pentru ' . ( $art['id'] ?? $id ) . ': ' . $media->get_error_message() );
		return false;
	}
	set_post_thumbnail( $id, $media );
	update_post_meta( $media, '_wp_attachment_image_alt', wp_strip_all_tags( $art['titlu'] ?? '' ) );
	// Coperta veche rămâne în biblioteca media: paginile din cache, Google și rețelele sociale o pot încă cere.
	return true;
}

/** Creează articolul. Întoarce ID-ul, 0 dacă exista deja, sau WP_Error. */
/** Intervalul dintre două articole noi publicate, în minute: ales la întâmplare între MIN și MAX (media ~26 de minute, cam 55 de articole în 24 de ore). 0 = fără limită. */
if ( ! defined( 'EG_INTERVAL_MIN' ) ) {
	define( 'EG_INTERVAL_MIN', 18 );
}
if ( ! defined( 'EG_INTERVAL_MAX' ) ) {
	define( 'EG_INTERVAL_MAX', 34 );
}

/**
 * Următorul loc liber în programul de publicare: un interval variabil (EG_INTERVAL_MIN–EG_INTERVAL_MAX minute) după ultimul articol
 * publicat sau programat, și nu înainte de data dorită (sau de acum).
 */
function eg_import_slot( $data, $tz ) {
	$min = (int) apply_filters( 'eg_interval_min', EG_INTERVAL_MIN );
	$max = max( $min, (int) apply_filters( 'eg_interval_max', EG_INTERVAL_MAX ) );
	if ( $max <= 0 ) {
		return $data;
	}
	// Minute și secunde neregulate, ca publicările să nu cadă la ore fixe.
	$pas = wp_rand( $min * MINUTE_IN_SECONDS, $max * MINUTE_IN_SECONDS );
	$dorit  = $data ? max( $data->getTimestamp(), time() ) : time();
	$ultimul = (int) get_option( 'eg_ultima_publicare', 0 );
	$slot   = max( $dorit, $ultimul + $pas );
	update_option( 'eg_ultima_publicare', $slot, false );
	return ( new DateTimeImmutable( '@' . $slot ) )->setTimezone( $tz );
}

function eg_import_articol( $art, $cale ) {
	if ( empty( $art['id'] ) || empty( $art['titlu'] ) ) {
		return new WP_Error( 'eg_camp', 'lipsește id sau titlu' );
	}
	$eg_id = sanitize_title( $art['id'] );
	if ( eg_import_gaseste( $eg_id ) ) {
		return 0;
	}

	$tz   = wp_timezone();
	$data = null;
	if ( ! empty( $art['data_publicare'] ) ) {
		$data = date_create( $art['data_publicare'], $tz ) ?: null;
	} elseif ( ! empty( $art['meci']['data'] ) ) {
		// Cronicile fără oră de publicare: seara meciului.
		$data = date_create( $art['meci']['data'] . ' 23:00', $tz ) ?: null;
		if ( $data && $data->getTimestamp() > time() ) {
			$data = null;
		}
	}
	$status = ( 'draft' === ( $art['status'] ?? '' ) ) ? 'draft' : 'publish';
	// Cadența: articolele noi apar la 18–34 de minute unul de altul (interval variabil); cele în plus așteaptă la coadă.
	// Articolele video nu stau la coada știrilor: apar la ora din „data_publicare”.
	if ( 'publish' === $status && 'video' !== ( $art['tip'] ?? '' ) ) {
		$data = eg_import_slot( $data, $tz );
	}
	if ( 'publish' === $status && $data && $data->getTimestamp() > time() + 60 ) {
		$status = 'future';
	}

	$post = array(
		'post_type'     => 'post',
		'post_status'   => $status,
		'post_title'    => wp_strip_all_tags( $art['titlu'] ),
		'post_excerpt'  => wp_strip_all_tags( $art['rezumat'] ?? '' ),
		'post_content'  => eg_import_continut( $art ),
		'post_author'   => ! empty( $art['autor'] ) ? eg_import_autor( $art['autor'] ) : ( eg_redactor_id( eg_alege_redactor( $eg_id, $art['titlu'], (array) ( $art['etichete'] ?? array() ), $art['categorie'] ?? '', ! empty( $art['meci'] ) ) ) ?: eg_import_autor( null ) ),
		'post_category' => array_filter( array( eg_import_categorie( $art['categorie'] ?? 'Știri' ) ) ),
		'tags_input'    => array_map( 'sanitize_text_field', (array) ( $art['etichete'] ?? array() ) ),
		'meta_input'    => array(
			'_eg_id'         => $eg_id,
			'_eg_cale'       => $cale,
			'_eg_actualizat' => (string) ( $art['actualizat'] ?? '' ),
			'_eg_cronica'    => empty( $art['meci'] ) ? '' : '1',
			'_eg_autor_explicit' => empty( $art['autor'] ) ? '' : '1',
			'_eg_video_rezumat' => esc_url_raw( (string) ( $art['video'] ?? '' ) ),
			'_eg_video_sursa'   => sanitize_text_field( (string) ( $art['video_sursa'] ?? '' ) ),
			'_eg_clasament'  => array_key_exists( 'clasament', $art ) ? ( $art['clasament'] ? sanitize_title( $art['clasament'] ) : 'nu' ) : '',
		),
	);
	if ( $data ) {
		$post['post_date']     = $data->format( 'Y-m-d H:i:s' );
		$post['post_date_gmt'] = get_gmt_from_date( $post['post_date'] );
	}
	if ( 'video' === ( $art['tip'] ?? '' ) ) {
		$post['meta_input']['_eg_video'] = esc_url_raw( $art['video_url'] ?? '' );
	}

	$id = wp_insert_post( wp_slash( $post ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}

	if ( ! empty( $art['persoane'] ) || ! empty( $art['fata'] ) || ! empty( $art['fundal'] ) ) {
		wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => eg_import_persoane( get_post_field( 'post_content', $id ), $art, $id ) ) ) );
	}

	eg_import_coperta( $id, $art, $cale );
	eg_import_categoria_competitiei( $id );
	if ( ! empty( $art['video'] ) || ( 'video' === ( $art['tip'] ?? '' ) && ! empty( $art['video_url'] ) ) ) {
		wp_set_post_tags( $id, 'Video', true );
	}
	if ( function_exists( 'eg_persoane_eticheteaza' ) ) {
		eg_persoane_eticheteaza( $id );
	}
	if ( ! empty( $art['coperta_v'] ) ) {
		update_post_meta( $id, '_eg_coperta_v', (string) $art['coperta_v'] );
	}

	eg_import_jurnal( ( 'draft' === $status ? 'Ciornă: ' : ( 'future' === $status ? 'Programat: ' : 'Publicat: ' ) ) . $art['titlu'] );
	return $id;
}

/* ---------- Legăturile interne, la afișare ---------- */

add_filter( 'the_content', 'eg_legaturi_interne', 20 );
function eg_legaturi_interne( $html ) {
	if ( false === strpos( $html, '#eg:' ) ) {
		return $html;
	}
	return preg_replace_callback(
		'/<a\s[^>]*href="#eg:([a-z0-9-]*)"[^>]*>(.*?)<\/a>/is',
		function ( $m ) {
			$post_id = $m[1] ? eg_import_gaseste( $m[1] ) : 0;
			if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
				return '<a href="' . esc_url( get_permalink( $post_id ) ) . '">' . $m[2] . '</a>';
			}
			return $m[2]; // articolul nu e (încă) publicat: rămâne doar textul
		},
		$html
	);
}

/* ---------- Pagina din administrare și ruta REST ---------- */

add_action( 'admin_menu', 'eg_import_meniu' );
function eg_import_meniu() {
	add_management_page( 'Import Eurogol', 'Import Eurogol', 'manage_options', 'eg-import', 'eg_import_pagina' );
}

function eg_import_pagina() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$mesaj = '';
	if ( isset( $_POST['eg_actiune'] ) && check_admin_referer( 'eg_import' ) ) {
		if ( 'salveaza' === $_POST['eg_actiune'] ) {
			update_option( 'eg_import_depozit', sanitize_text_field( wp_unslash( $_POST['eg_depozit'] ?? '' ) ) );
			update_option( 'eg_import_ramura', sanitize_text_field( wp_unslash( $_POST['eg_ramura'] ?? 'main' ) ) );
			update_option( 'eg_import_autor', absint( $_POST['eg_autor'] ?? 0 ) );
			update_option( 'eg_tema_auto', empty( $_POST['eg_tema_auto'] ) ? 0 : 1 );
			wp_clear_scheduled_hook( EG_IMPORT_HOOK );
			eg_import_programeaza();
			$mesaj = 'Setările au fost salvate.';
		} elseif ( 'importa' === $_POST['eg_actiune'] ) {
			delete_transient( 'eg_import_blocare' );
			$r     = eg_import_ruleaza( 40 );
			$mesaj = sprintf( 'Import terminat: %d articole noi, %d actualizate, %d erori.', $r['noi'], $r['actualizate'], $r['erori'] );
		}
	}
	$ultima = (int) get_option( 'eg_import_ultima', 0 );
	$urm    = wp_next_scheduled( EG_IMPORT_HOOK );
	?>
	<div class="wrap">
		<h1>Import Eurogol</h1>
		<?php if ( $mesaj ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $mesaj ); ?></p></div>
		<?php endif; ?>
		<p>Articolele vin din depozitul GitHub de conținut, la fiecare 15 minute.
			Ultima rulare: <strong><?php echo $ultima ? esc_html( wp_date( 'j F Y, H:i', $ultima ) ) : 'niciodată'; ?></strong>.
			Următoarea: <strong><?php echo $urm ? esc_html( wp_date( 'H:i', $urm ) ) : 'neprogramată'; ?></strong>.</p>
		<form method="post">
			<?php wp_nonce_field( 'eg_import' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="eg_depozit">Depozitul GitHub</label></th>
					<td><input class="regular-text" id="eg_depozit" name="eg_depozit" value="<?php echo esc_attr( eg_import_depozit() ); ?>"><p class="description">cont/depozit, public. Gol = importul oprit.</p></td></tr>
				<tr><th><label for="eg_ramura">Ramura</label></th>
					<td><input class="regular-text" id="eg_ramura" name="eg_ramura" value="<?php echo esc_attr( get_option( 'eg_import_ramura', 'main' ) ); ?>"></td></tr>
				<tr><th><label for="eg_autor">Autorul implicit</label></th>
					<td><?php wp_dropdown_users( array( 'name' => 'eg_autor', 'id' => 'eg_autor', 'selected' => (int) get_option( 'eg_import_autor', 0 ), 'show_option_none' => 'Primul administrator', 'option_none_value' => 0 ) ); ?></td></tr>
				<tr><th>Tema</th>
					<td><label><input type="checkbox" name="eg_tema_auto" value="1" <?php checked( get_option( 'eg_tema_auto', 1 ) ); ?>> Instalează singură versiunile noi ale temei din GitHub (verificate cu SHA-256)</label>
					<p class="description">Versiunea instalată: <?php echo esc_html( wp_get_theme( 'eurogol' )->get( 'Version' ) ); ?>.</p></td></tr>
			</table>
			<p>
				<button class="button" name="eg_actiune" value="salveaza">Salvează setările</button>
				<button class="button button-primary" name="eg_actiune" value="importa">Importă acum</button>
			</p>
		</form>
		<h2>Jurnal</h2>
		<pre style="background:#fff;padding:12px;max-height:420px;overflow:auto;white-space:pre-wrap"><?php echo esc_html( implode( "\n", array_reverse( (array) get_option( 'eg_import_jurnal', array() ) ) ) ?: 'Gol.' ); ?></pre>
	</div>
	<?php
}

add_action( 'rest_api_init', 'eg_import_rest' );
function eg_import_rest() {
	register_rest_route(
		'eg/v1',
		'/import',
		array(
			'methods'             => 'POST',
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
			'callback'            => function () {
				delete_transient( 'eg_import_blocare' );
				return eg_import_ruleaza( 40 );
			},
		)
	);
}
