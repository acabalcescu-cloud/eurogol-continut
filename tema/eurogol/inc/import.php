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
	return $s;
}

add_action( 'init', 'eg_import_programeaza' );
function eg_import_programeaza() {
	if ( eg_import_depozit() && ! wp_next_scheduled( EG_IMPORT_HOOK ) ) {
		wp_schedule_event( time() + 60, 'eg_15m', EG_IMPORT_HOOK );
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

	$index = eg_import_json( 'index.json' );
	if ( is_wp_error( $index ) ) {
		eg_import_jurnal( 'Eroare: ' . $index->get_error_message() );
		delete_transient( 'eg_import_blocare' );
		$rez['erori']++;
		return $rez;
	}

	// Articolele noi: cele mai vechi primele, ca ordinea de pe site să urmeze ordinea din depozit.
	$vazute = (array) get_option( 'eg_import_vazute', array() );
	$cai    = array_reverse( array_values( array_filter( (array) ( $index['cronici'] ?? array() ), 'is_string' ) ) );
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
	foreach ( array_filter( (array) ( $index['actualizate'] ?? array() ), 'is_string' ) as $cale ) {
		$art = eg_import_json( $cale );
		if ( is_wp_error( $art ) || empty( $art['id'] ) || empty( $art['actualizat'] ) ) {
			continue;
		}
		$post_id = eg_import_gaseste( sanitize_title( $art['id'] ) );
		if ( ! $post_id || get_post_meta( $post_id, '_eg_actualizat', true ) === $art['actualizat'] ) {
			continue;
		}
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_title'   => wp_strip_all_tags( $art['titlu'] ?? get_the_title( $post_id ) ),
				'post_excerpt' => wp_strip_all_tags( $art['rezumat'] ?? '' ),
				'post_content' => eg_import_continut( $art ),
			)
		);
		update_post_meta( $post_id, '_eg_actualizat', $art['actualizat'] );
		eg_import_jurnal( 'Actualizat: ' . get_the_title( $post_id ) );
		$rez['actualizate']++;
	}

	update_option( 'eg_import_ultima', time(), false );
	if ( $rez['noi'] || $rez['actualizate'] || $rez['erori'] ) {
		eg_import_jurnal( sprintf( 'Rulare: %d noi, %d actualizate, %d erori.', $rez['noi'], $rez['actualizate'], $rez['erori'] ) );
	}
	delete_transient( 'eg_import_blocare' );
	return $rez;
}

/** HTML-ul articolului, cu legăturile interne pregătite pentru afișare. */
function eg_import_continut( $art ) {
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

/** Creează articolul. Întoarce ID-ul, 0 dacă exista deja, sau WP_Error. */
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
	if ( 'publish' === $status && $data && $data->getTimestamp() > time() + 60 ) {
		$status = 'future';
	}

	$post = array(
		'post_type'     => 'post',
		'post_status'   => $status,
		'post_title'    => wp_strip_all_tags( $art['titlu'] ),
		'post_excerpt'  => wp_strip_all_tags( $art['rezumat'] ?? '' ),
		'post_content'  => eg_import_continut( $art ),
		'post_author'   => eg_import_autor( $art['autor'] ?? null ),
		'post_category' => array_filter( array( eg_import_categorie( $art['categorie'] ?? 'Știri' ) ) ),
		'tags_input'    => array_map( 'sanitize_text_field', (array) ( $art['etichete'] ?? array() ) ),
		'meta_input'    => array(
			'_eg_id'         => $eg_id,
			'_eg_cale'       => $cale,
			'_eg_actualizat' => (string) ( $art['actualizat'] ?? '' ),
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

	// Coperta: fișierul din același folder cu articolul sau o adresă completă.
	$img = '';
	if ( ! empty( $art['imagine_url'] ) ) {
		$img = $art['imagine_url'];
	} elseif ( ! empty( $art['imagine'] ) ) {
		$dir = trim( dirname( $cale ), './' );
		$img = eg_import_url( ( $dir ? $dir . '/' : '' ) . $art['imagine'] );
	}
	if ( $img ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$media = media_sideload_image( esc_url_raw( $img ), $id, wp_strip_all_tags( $art['titlu'] ), 'id' );
		if ( is_wp_error( $media ) ) {
			eg_import_jurnal( "Coperta n-a putut fi descărcată pentru {$eg_id}: " . $media->get_error_message() );
		} else {
			set_post_thumbnail( $id, $media );
			update_post_meta( $media, '_wp_attachment_image_alt', wp_strip_all_tags( $art['titlu'] ) );
		}
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
