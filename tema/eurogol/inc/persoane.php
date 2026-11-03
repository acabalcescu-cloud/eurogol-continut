<?php
/**
 * Jucătorii și antrenorii din articole (ca la Poveștile Științei):
 *  - registrul vine din depozit: unelte/persoane/persoane.json + fotografiile unelte/persoane/<slug>.jpg (licențe libere);
 *  - la importul de la 15 minute, registrul se reîmprospătează, fotografiile noi intră în biblioteca media,
 *    iar fiecare articol primește ca etichete numele persoanelor pomenite (și articolele vechi, când registrul se schimbă);
 *  - pe pagina articolului: banda „În acest articol” după primul paragraf și bulinele pe imaginea de sus;
 *  - bulina duce la pagina etichetei („toate articolele despre …”); creditele fotografiilor apar la finalul articolului.
 * Rezumatul video: câmpul „video” din JSON (YouTube) → secțiunea „Rezumatul meciului”.
 */

defined( 'ABSPATH' ) || exit;

/** Registrul de persoane, din cache (opțiunea „eg_persoane”). */
function eg_persoane() {
	return (array) get_option( 'eg_persoane', array() );
}

add_action( 'eg_import', 'eg_persoane_sincronizeaza', 25 );
/** Citește registrul din depozit, încarcă fotografiile noi, apoi reetichetează articolele dacă s-a schimbat ceva. */
function eg_persoane_sincronizeaza() {
	if ( ! function_exists( 'eg_import_json' ) ) {
		return;
	}
	$j = eg_import_json( 'unelte/persoane/persoane.json' );
	if ( is_wp_error( $j ) || ! is_array( $j ) ) {
		return;
	}
	$hash = md5( wp_json_encode( $j ) );
	$vechi = eg_persoane();
	$foto  = (array) get_option( 'eg_persoane_foto', array() );
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$out        = array();
	$descarcari = 0;
	foreach ( $j as $x ) {
		if ( empty( $x['slug'] ) || empty( $x['nume'] ) || ( empty( $x['fisier'] ) && empty( $x['url'] ) ) ) {
			continue;
		}
		$cheie = $x['slug'] . '|' . ( $x['sursa'] ?? '' ) . '|' . ( $x['fisier'] ?? '' ) . ( $x['url'] ?? '' );
		if ( empty( $foto[ $cheie ] ) || ! wp_get_attachment_url( $foto[ $cheie ] ) ) {
			if ( ! empty( $x['fisier'] ) ) {
				$id = media_sideload_image( eg_import_url( 'unelte/persoane/' . $x['fisier'] ), 0, $x['nume'], 'id' );
			} elseif ( $descarcari++ < 8 ) { // câte 8 pe rulare, ca importul să nu dureze prea mult.
				$id = eg_persoana_descarca( $x );
			} else {
				$id = new WP_Error( 'eg_amanat', 'Data viitoare.' );
			}
			if ( ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_wp_attachment_image_alt', $x['nume'] );
				wp_update_post( array( 'ID' => $id, 'post_excerpt' => 'Foto: ' . ( $x['credit'] ?? '' ) ) );
				$foto[ $cheie ] = $id;
			}
		}
		if ( ! empty( $foto[ $cheie ] ) ) {
			$x['poza'] = wp_get_attachment_image_url( $foto[ $cheie ], 'medium' );
		} elseif ( ! empty( $x['fisier'] ) ) {
			$x['poza'] = eg_import_url( 'unelte/persoane/' . $x['fisier'] );
		} else {
			continue; // fotografia de pe Wikimedia n-a putut fi descărcată acum; se reîncearcă la următorul import.
		}
		$out[] = $x;
	}
	update_option( 'eg_persoane_foto', $foto, false );
	update_option( 'eg_persoane', $out, false );
	if ( get_option( 'eg_persoane_hash' ) !== $hash || count( $vechi ) !== count( $out ) ) {
		update_option( 'eg_persoane_hash', $hash, false );
		eg_persoane_eticheteaza_tot();
	}
}

/**
 * Fotografia unei persoane descărcată direct de pe Wikimedia Commons (câmpul „url” din registru),
 * decupată pătrat din partea de sus a imaginii, unde e fața. Întoarce ID-ul atașamentului sau WP_Error.
 */
function eg_persoana_descarca( $x ) {
	$url = (string) $x['url'];
	if ( ! preg_match( '~^https://upload\.wikimedia\.org/~', $url ) ) {
		return new WP_Error( 'eg_url', 'Doar Wikimedia Commons.' );
	}
	$tmp = download_url( $url, 60 );
	if ( is_wp_error( $tmp ) && preg_match( '~^(https://upload\.wikimedia\.org/wikipedia/commons)/thumb/(.+)/[^/]+$~', $url, $m ) ) {
		$tmp = download_url( $m[1] . '/' . $m[2], 90 ); // miniatura nu există: fișierul original.
	}
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}
	$ed = wp_get_image_editor( $tmp );
	if ( ! is_wp_error( $ed ) ) {
		$d = $ed->get_size();
		$l = min( $d['width'], $d['height'] );
		$y = $d['height'] > $d['width'] ? (int) round( min( ( $d['height'] - $l ) * 0.12, $d['height'] - $l ) ) : 0;
		$ed->crop( (int) round( ( $d['width'] - $l ) / 2 ), $y, $l, $l, min( 480, $l ), min( 480, $l ) );
		$ed->set_quality( 86 );
		$r = $ed->save( $tmp . '.jpg', 'image/jpeg' );
		if ( ! is_wp_error( $r ) && ! empty( $r['path'] ) ) {
			@unlink( $tmp );
			$tmp = $r['path'];
		}
	}
	$id = media_handle_sideload( array( 'name' => $x['slug'] . '.jpg', 'tmp_name' => $tmp ), 0, $x['nume'] );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
	}
	return $id;
}

/** Persoanele pomenite într-un text: în ordinea primei apariții. */
function eg_persoane_din_text( $html ) {
	static $memo = array();
	$k = md5( $html );
	if ( isset( $memo[ $k ] ) ) {
		return $memo[ $k ];
	}
	$text = preg_replace( '#<p[^>]*>\s*<em>Surse:</em>.*?</p>#su', '', $html );
	$text = remove_accents( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) );
	$gas  = array();
	foreach ( eg_persoane() as $x ) {
		$prima = PHP_INT_MAX;
		$total = 0;
		foreach ( array_unique( array_map( 'remove_accents', (array) ( $x['alias'] ?? array( $x['nume'] ) ) ) ) as $a ) {
			if ( mb_strlen( $a ) >= 4 && preg_match_all( '/(?<![\p{L}\p{M}])' . preg_quote( $a, '/' ) . '(?![\p{L}\p{M}])/u', $text, $m, PREG_OFFSET_CAPTURE ) ) {
				$total += count( $m[0] );
				$prima  = min( $prima, $m[0][0][1] );
			}
		}
		if ( $total ) {
			$x['prima']    = $prima;
			$x['aparitii'] = $total;
			$gas[]         = $x;
		}
	}
	usort( $gas, fn( $a, $b ) => $a['prima'] <=> $b['prima'] );
	return $memo[ $k ] = $gas;
}

/** Etichetele articolului: numele persoanelor pomenite. */
function eg_persoane_eticheteaza( $post_id ) {
	$p = get_post( $post_id );
	if ( ! $p ) {
		return;
	}
	$nume = wp_list_pluck( eg_persoane_din_text( $p->post_title . ' <p>' . $p->post_content . '</p>' ), 'nume' );
	if ( $nume ) {
		wp_add_post_tags( $post_id, $nume );
	}
}

function eg_persoane_eticheteaza_tot() {
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => array( 'publish', 'future' ), 'posts_per_page' => 400, 'fields' => 'ids' ) ) as $id ) {
		eg_persoane_eticheteaza( $id );
	}
}

/** Pagina „toate articolele despre …” (eticheta persoanei). */
function eg_persoana_link( $x ) {
	$t = get_term_by( 'name', $x['nume'], 'post_tag' );
	return $t ? get_tag_link( $t ) : home_url( '/?s=' . rawurlencode( $x['nume'] ) );
}

function eg_persoane_pe_pagina( $post ) {
	$post = get_post( $post );
	return array_slice( eg_persoane_din_text( $post->post_title . ' <p>' . $post->post_content . '</p>' ), 0, 8 );
}

/* Banda „În acest articol”, după primul paragraf. */
add_filter( 'the_content', 'eg_persoane_banda', 26 );
function eg_persoane_banda( $content ) {
	$p = get_post();
	if ( ! $p || ! is_singular( 'post' ) || (int) $p->ID !== (int) get_queried_object_id() || ! in_the_loop() ) {
		return $content;
	}
	$pers = eg_persoane_pe_pagina( $p );
	if ( ! $pers ) {
		return $content;
	}
	$b = '<aside class="eg-in-articol" aria-label="În acest articol"><span class="eg-in-articol-titlu">În acest articol</span><div class="eg-in-articol-lista">';
	foreach ( $pers as $x ) {
		$b .= '<a class="eg-in-articol-om" href="' . esc_url( eg_persoana_link( $x ) ) . '" title="Toate articolele despre ' . esc_attr( $x['nume'] ) . '">'
			. '<span class="eg-bulina"><img src="' . esc_url( $x['poza'] ) . '" alt="' . esc_attr( $x['nume'] ) . '" loading="lazy" width="84" height="84"></span>'
			. '<span class="eg-in-articol-nume">' . esc_html( $x['nume'] ) . '</span>'
			. '<span class="eg-in-articol-rol">' . esc_html( ( array( 'antrenor' => 'Antrenor', 'patron' => 'Patron', 'fost' => 'Fost jucător', 'arbitru' => 'Arbitru' )[ $x['rol'] ?? '' ] ?? 'Jucător' ) . ( ! empty( $x['club'] ) ? ' · ' . $x['club'] : '' ) ) . '</span></a>';
	}
	$b  .= '</div></aside>';
	$poz = strpos( $content, '</p>' );
	return false === $poz ? $b . $content : substr( $content, 0, $poz + 4 ) . $b . substr( $content, $poz + 4 );
}

/* Creditele fotografiilor, la finalul textului. */
add_filter( 'the_content', 'eg_persoane_credite', 27 );
function eg_persoane_credite( $content ) {
	$p = get_post();
	if ( ! $p || ! is_singular( 'post' ) || (int) $p->ID !== (int) get_queried_object_id() || ! in_the_loop() ) {
		return $content;
	}
	$cr = array();
	foreach ( eg_persoane_pe_pagina( $p ) as $x ) {
		if ( ! empty( $x['credit'] ) ) {
			$cr[] = esc_html( $x['nume'] ) . ': ' . ( ! empty( $x['sursa'] ) ? '<a href="' . esc_url( $x['sursa'] ) . '" rel="nofollow">' . esc_html( $x['credit'] ) . '</a>' : esc_html( $x['credit'] ) );
		}
	}
	return $cr ? $content . '<p class="eg-credit-foto">Fotografii în buline: ' . implode( '; ', $cr ) . '.</p>' : $content;
}

/** Bulinele de pe imaginea de sus a articolului (colțul din dreapta jos), cu numele de familie. */
function eg_persoane_bulinele_copertii( $post ) {
	$pers = array_slice( eg_persoane_pe_pagina( $post ), 0, 6 );
	if ( ! $pers ) {
		return '';
	}
	$o = '<div class="eg-coperta-oameni">';
	foreach ( $pers as $x ) {
		$parti = explode( ' ', $x['nume'] );
		$o    .= '<a class="eg-coperta-om" href="' . esc_url( eg_persoana_link( $x ) ) . '" title="' . esc_attr( $x['nume'] ) . '"><span class="eg-bulina"><img src="' . esc_url( $x['poza'] ) . '" alt="' . esc_attr( $x['nume'] ) . '" width="64" height="64"></span><span>' . esc_html( end( $parti ) ) . '</span></a>';
	}
	return $o . '</div>';
}

/* ---------- Rezumatul video ---------- */

function eg_video_id( $url ) {
	return preg_match( '~(?:youtu\.be/|v=|/embed/|/shorts/)([A-Za-z0-9_-]{11})~', (string) $url, $m ) ? $m[1] : '';
}

/** Secțiunea „Rezumatul meciului”: imaginea se vede direct, playerul (youtube-nocookie) se încarcă la apăsare. */
function eg_video_html( $post ) {
	$url = get_post_meta( get_post( $post )->ID, '_eg_video_rezumat', true );
	$id  = eg_video_id( $url );
	if ( ! $id ) {
		return '';
	}
	$sursa = get_post_meta( get_post( $post )->ID, '_eg_video_sursa', true );
	return '<section class="eg-video" aria-label="Rezumatul meciului"><h2 class="eg-titlu-sectiune">Rezumatul meciului</h2>'
		. '<button class="eg-video-play" data-id="' . esc_attr( $id ) . '" aria-label="Pornește rezumatul video"><img src="https://i.ytimg.com/vi/' . esc_attr( $id ) . '/hqdefault.jpg" alt="" loading="lazy"><span class="eg-video-buton" aria-hidden="true"></span></button>'
		. '<p class="eg-cl-sursa">Video: ' . ( $sursa ? esc_html( $sursa ) . ', ' : '' ) . '<a href="' . esc_url( 'https://www.youtube.com/watch?v=' . $id ) . '" rel="nofollow">YouTube</a></p></section>';
}
