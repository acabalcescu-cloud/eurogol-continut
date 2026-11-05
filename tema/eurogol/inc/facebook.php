<?php
/**
 * Distribuirea automată pe pagina de Facebook (Graph API), din Instrumente → Facebook Eurogol.
 *
 * - Conectarea: App ID + App Secret ale aplicației Meta + un token de utilizator (Graph API Explorer, cu
 *   pages_show_list, pages_read_engagement, pages_manage_posts). Tema îl schimbă într-un token de lungă durată și
 *   salvează tokenul PAGINII (nu expiră) doar în baza de date a site-ului, niciodată în depozit.
 * - Articolele publicate după activare intră într-o coadă. La finalul fiecărei rulări a importului (după ce
 *   articolul are coperta și etichetele), coada se golește: cu intervalul 0 pleacă toate articolele noi (cel mult
 *   EG_FB_MAX_RULARE pe rulare), ca la web2social; cu interval > 0, cel mult unul la „interval” minute.
 *   Fiecare articol se postează o singură dată.
 * - Instagram: dacă pagina are un cont profesional legat și e bifat, după fiecare postare pe Facebook pleacă și coperta
 *   articolului pe Instagram (titlu, rezumat, hashtag-uri), prin același token al paginii.
 */

defined( 'ABSPATH' ) || exit;

const EG_FB_API = 'https://graph.facebook.com/v21.0/';
const EG_FB_MAX_RULARE = 10;

function eg_fb_setari() {
	return wp_parse_args(
		(array) get_option( 'eg_fb', array() ),
		array( 'pagina_id' => '', 'pagina_nume' => '', 'token' => '', 'activ' => 0, 'interval' => 20, 'de_la' => 0, 'ultima' => 0, 'ig_id' => '', 'ig_nume' => '', 'ig_activ' => 0 )
	);
}

function eg_fb_salveaza( $s ) {
	update_option( 'eg_fb', $s, false );
}

function eg_fb_jurnal( $m ) {
	if ( function_exists( 'eg_import_jurnal' ) ) {
		eg_import_jurnal( 'Facebook: ' . $m );
	}
}

function eg_fb_cerere( $metoda, $cale, $param ) {
	$url = EG_FB_API . ltrim( $cale, '/' );
	$r   = 'GET' === $metoda
		? wp_remote_get( add_query_arg( $param, $url ), array( 'timeout' => 20 ) )
		: wp_remote_post( $url, array( 'timeout' => 20, 'body' => $param ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$d = json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! empty( $d['error'] ) ) {
		$e   = $d['error'];
		$det = trim( ( $e['error_user_msg'] ?? '' ) . ' (cod ' . ( $e['code'] ?? '?' ) . ( isset( $e['error_subcode'] ) ? '/' . $e['error_subcode'] : '' ) . ')' );
		return new WP_Error( 'eg_fb', ( $e['message'] ?? 'eroare Facebook' ) . ' ' . $det );
	}
	return is_array( $d ) ? $d : new WP_Error( 'eg_fb', 'răspuns necunoscut' );
}

/** Schimbă tokenul de utilizator în tokenul de lungă durată al paginii alese. */
function eg_fb_conecteaza( $app_id, $secret, $user_token, $pagina ) {
	$lung = eg_fb_cerere( 'GET', 'oauth/access_token', array( 'grant_type' => 'fb_exchange_token', 'client_id' => $app_id, 'client_secret' => $secret, 'fb_exchange_token' => $user_token ) );
	if ( is_wp_error( $lung ) ) {
		return $lung;
	}
	$conturi = eg_fb_cerere( 'GET', 'me/accounts', array( 'access_token' => $lung['access_token'], 'fields' => 'id,name,username,access_token', 'limit' => 100 ) );
	if ( is_wp_error( $conturi ) ) {
		return $conturi;
	}
	$brut = trim( preg_replace( '~^.*facebook\.com/|[/?#].*$~i', '', trim( (string) $pagina ) ) );
	if ( '' === $brut ) {
		$brut = (string) eg_fb_setari()['pagina_id']; // reconectare: pagina conectată înainte.
	}
	// „EuroGol.ro”, „eurogol”, „facebook.com/EuroGol.ro” → „eurogol”: comparăm fără punctuație și fără „.ro”.
	$norm   = fn( $t ) => preg_replace( '/[^a-z0-9]+/', '', preg_replace( '~^.*facebook\.com/|\.ro$~', '', strtolower( trim( (string) $t ) ) ) );
	$pagina = $norm( $pagina );
	$lista  = (array) ( $conturi['data'] ?? array() );
	// Paginile administrate printr-un portofoliu Business nu apar mereu în me/accounts: le cerem direct, după ID sau nume de utilizator.
	if ( '' !== $brut ) {
		$direct = eg_fb_cerere( 'GET', rawurlencode( $brut ), array( 'access_token' => $lung['access_token'], 'fields' => 'id,name,access_token' ) );
		if ( ! is_wp_error( $direct ) && ! empty( $direct['access_token'] ) ) {
			$gasit = false;
			foreach ( $lista as $p ) {
				$gasit = $gasit || $p['id'] === $direct['id'];
			}
			if ( ! $gasit ) {
				$lista = array( $direct );
			}
		}
	}
	if ( ! $lista ) {
		return new WP_Error( 'eg_fb', 'Tokenul nu vede nicio pagină. La „Generate Access Token”, în fereastra Facebook, alege „Editează setările” și bifează pagina EuroGol.ro.' );
	}
	foreach ( $lista as $p ) {
		if ( 1 === count( $lista ) || in_array( $pagina, array( $norm( $p['id'] ), $norm( $p['name'] ), $norm( $p['username'] ?? '' ) ), true ) ) {
			$s                = eg_fb_setari();
			$s['pagina_id']   = $p['id'];
			$s['pagina_nume'] = $p['name'];
			$s['token']       = $p['access_token'];
			$s['ultima']      = 0; // după reconectare, prima postare pleacă la următorul import.
			eg_fb_salveaza( $s );
			delete_option( 'eg_fb_token_cazut' );
			$ig = eg_ig_detecteaza();
			return $p['name'] . ( is_wp_error( $ig ) || ! $ig ? '' : ' (și contul de Instagram @' . $ig . ')' );
		}
	}
	return new WP_Error( 'eg_fb', 'Pagina „' . $pagina . '” nu e printre cele văzute de token: ' . implode( ', ', wp_list_pluck( $lista, 'name' ) ) . '. Scrie la „Pagina” unul dintre aceste nume sau ID-ul numeric al paginii EuroGol (Facebook → pagina → Despre → Transparența paginii).' );
}

/* ---------- Hashtag-uri ---------- */

/** „Cristi Chivu” → „CristiChivu”, „Brasileirão” → „Brasileirao”; gol dacă nu rămâne nimic folositor. */
function eg_fb_hashtag( $text ) {
	$t = remove_accents( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) );
	$t = preg_replace( '/[^A-Za-z0-9 ]+/', ' ', $t );
	$w = array_filter( explode( ' ', $t ), 'strlen' );
	if ( ! $w ) {
		return '';
	}
	$h = implode( '', array_map( fn( $x ) => ctype_upper( $x ) ? $x : ucfirst( $x ), $w ) );
	// Fără sezoane și ani singuri („2026/2027”, „1986”) și fără cuvinte de o literă.
	if ( preg_match( '/^\d+$/', $h ) || strlen( $h ) < 2 ) {
		return '';
	}
	return '#' . $h;
}

/** Hashtag-urile postării: fixe, competiția, categoriile, etichetele, oamenii din text, plus cele după subiect. */
function eg_fb_hashtaguri( $post, $max = 12 ) {
	$post  = get_post( $post );
	$lista = array( '#eurogol', '#fotbal' );
	$comp  = function_exists( 'eg_clasament_pentru' ) ? eg_clasament_pentru( $post ) : null;
	$slug  = $comp ? $comp[0] : '';
	if ( $slug ) {
		$lista[] = eg_fb_hashtag( eg_clasament_competitii()[ $slug ][1] ?? $slug );
	}
	$cats = get_the_category( $post->ID );
	foreach ( $cats as $c ) {
		$lista[] = eg_fb_hashtag( $c->name );
	}
	$slugs_cat = wp_list_pluck( $cats, 'slug' );
	$titlu     = remove_accents( strtolower( $post->post_title . ' ' . $post->post_excerpt ) );

	// După subiect.
	if ( in_array( $slug, array( 'superliga' ), true ) || array_intersect( $slugs_cat, array( 'superliga', 'cupa-romaniei', 'nationala' ) ) ) {
		$lista[] = '#fotbalromanesc';
	}
	if ( array_intersect( $slugs_cat, array( 'nationala' ) ) || preg_match( '/\b(nationala|tricolor|stranier|romanul|un roman)\b/', $titlu ) ) {
		$lista[] = '#tricolorii';
	}
	if ( preg_match( '/transfer|semneaz|a semnat|imprumut|mercato/', $titlu ) ) {
		$lista[] = '#transferuri';
	}
	if ( in_array( 'povesti', $slugs_cat, true ) ) {
		$lista[] = '#istoriafotbalului';
	}
	if ( in_array( $slug, array( 'premier-league', 'laliga', 'serie-a', 'bundesliga', 'ligue-1', 'champions-league', 'europa-league', 'conference-league' ), true ) ) {
		$lista[] = '#fotbaleuropean';
	}
	if ( in_array( $slug, array( 'brasileirao', 'liga-profesional' ), true ) || array_intersect( $slugs_cat, array( 'copa-libertadores', 'brasileirao', 'liga-profesional' ) ) ) {
		$lista[] = '#futbol';
	}

	// Oamenii din text (bulinele), apoi etichetele (cluburi, oameni, competiții).
	if ( function_exists( 'eg_persoane_pe_pagina' ) ) {
		foreach ( eg_persoane_pe_pagina( $post ) as $x ) {
			$lista[] = eg_fb_hashtag( $x['nume'] );
		}
	}
	foreach ( (array) get_the_tags( $post->ID ) as $t ) {
		if ( $t ) {
			$lista[] = eg_fb_hashtag( $t->name );
		}
	}

	$out  = array();
	$vazt = array();
	foreach ( $lista as $h ) {
		$k = strtolower( (string) $h );
		if ( $h && ! isset( $vazt[ $k ] ) ) {
			$vazt[ $k ] = true;
			$out[]      = $h;
		}
	}
	return array_slice( $out, 0, $max );
}

/* ---------- Coada ---------- */

add_action( 'transition_post_status', 'eg_fb_in_coada', 20, 3 );
function eg_fb_in_coada( $nou, $vechi, $post ) {
	$s = eg_fb_setari();
	if ( 'publish' !== $nou || 'publish' === $vechi || 'post' !== $post->post_type || ! $s['activ'] || ! $s['token'] ) {
		return;
	}
	// Contează momentul în care apare pe site (acum), nu data știrii: importul aduce articole cu ora evenimentului,
	// adesea cu zeci de minute în urmă. Sărim doar articolele vechi (reimporturi, arhivă).
	if ( time() < (int) $s['de_la'] || get_post_time( 'U', true, $post ) < time() - 12 * HOUR_IN_SECONDS || get_post_meta( $post->ID, '_eg_fb_post', true ) ) {
		return;
	}
	$c = (array) get_option( 'eg_fb_coada', array() );
	if ( ! in_array( $post->ID, $c, true ) ) {
		$c[] = $post->ID;
		update_option( 'eg_fb_coada', $c, false );
	}
}

/** O singură dată după actualizare: articolele din ultimele 6 ore, nepostate, intră în coadă (au fost sărite de regula veche). */
add_action( 'eg_import', 'eg_fb_recupereaza', 59 );
function eg_fb_recupereaza() {
	$s = eg_fb_setari();
	if ( 2 <= (int) get_option( 'eg_fb_recuperat' ) || ! $s['activ'] || ! $s['token'] ) {
		return;
	}
	$c = (array) get_option( 'eg_fb_coada', array() );
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 40, 'fields' => 'ids', 'date_query' => array( array( 'after' => '4 hours ago', 'column' => 'post_date_gmt' ) ) ) ) as $id ) {
		if ( ! get_post_meta( $id, '_eg_fb_post', true ) && ! in_array( $id, $c, true ) ) {
			$c[] = $id;
		}
	}
	update_option( 'eg_fb_coada', $c, false );
	update_option( 'eg_fb_recuperat', 2 );
	eg_fb_jurnal( count( $c ) . ' articole recente puse în coadă.' );
}

add_action( 'eg_import', 'eg_fb_trimite', 60 );
function eg_fb_trimite() {
	$s = eg_fb_setari();
	// O singură dată: trecerea la postarea fiecărui articol nou (intervalul 0).
	if ( ! get_option( 'eg_fb_mod_imediat' ) ) {
		$s['interval'] = 0;
		eg_fb_salveaza( $s );
		update_option( 'eg_fb_mod_imediat', 1, false );
	}
	$imediat = 0 === (int) $s['interval'];
	for ( $i = 0; $i < ( $imediat ? EG_FB_MAX_RULARE : 1 ); $i++ ) {
		if ( ! eg_fb_trimite_unul( $imediat ) ) {
			break;
		}
	}
}

/** Trimite cel mai vechi articol din coadă. Întoarce true dacă a postat și se poate continua. */
function eg_fb_trimite_unul( $imediat ) {
	$s = eg_fb_setari();
	$c = array_values( array_filter( (array) get_option( 'eg_fb_coada', array() ) ) );
	if ( ! $s['activ'] || ! $s['token'] || ! $c || ( ! $imediat && time() - (int) $s['ultima'] < 60 * max( 1, (int) $s['interval'] ) ) ) {
		return false;
	}
	// Cel mai vechi primul, ca pagina să urmeze ordinea de pe site.
	usort( $c, fn( $a, $b ) => get_post_time( 'U', true, $a ) <=> get_post_time( 'U', true, $b ) );
	$id = array_shift( $c );
	update_option( 'eg_fb_coada', $c, false );
	$p = get_post( $id );
	// Articolele rămase prea mult în coadă (de ex. cât conexiunea a fost căzută) nu mai sunt știri: le sărim.
	while ( $p && ( 'publish' !== $p->post_status || get_post_meta( $id, '_eg_fb_post', true ) || get_post_time( 'U', true, $p ) < time() - 8 * HOUR_IN_SECONDS ) ) {
		$id = array_shift( $c );
		$p  = $id ? get_post( $id ) : null;
	}
	update_option( 'eg_fb_coada', $c, false );
	if ( ! $p ) {
		return false;
	}
	$mesaj = html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' );
	if ( has_excerpt( $p ) ) {
		$mesaj .= "\n\n" . html_entity_decode( wp_strip_all_tags( $p->post_excerpt ), ENT_QUOTES, 'UTF-8' );
	}
	$img = get_the_post_thumbnail_url( $p, 'full' );
	if ( $img ) {
		// Postare cu imagine (coperta), cu linkul spre articol în text.
		$mesaj .= "\n\n👉 Citește articolul: " . get_permalink( $p ) . "\n\n" . implode( ' ', eg_fb_hashtaguri( $p ) );
		$r      = eg_fb_cerere( 'POST', $s['pagina_id'] . '/photos', array( 'url' => $img, 'caption' => $mesaj, 'access_token' => $s['token'] ) );
	} else {
		$mesaj .= "\n\n" . implode( ' ', eg_fb_hashtaguri( $p ) );
		$r      = eg_fb_cerere( 'POST', $s['pagina_id'] . '/feed', array( 'message' => $mesaj, 'link' => get_permalink( $p ), 'access_token' => $s['token'] ) );
	}
	if ( is_wp_error( $r ) ) {
		eg_fb_jurnal( 'eroare la „' . get_the_title( $p ) . '”: ' . $r->get_error_message() );
		if ( preg_match( '/access token|session/i', $r->get_error_message() ) ) {
			update_option( 'eg_fb_token_cazut', time(), false );
		}
		array_unshift( $c, $id ); // reîncearcă la următoarea rulare
		update_option( 'eg_fb_coada', $c, false );
		$s['ultima'] = time(); // fără rafale de reîncercări
		eg_fb_salveaza( $s );
		return false;
	}
	delete_option( 'eg_fb_token_cazut' );
	update_post_meta( $id, '_eg_fb_post', $r['id'] ?? 'trimis' );
	$s['ultima'] = time();
	eg_fb_salveaza( $s );
	eg_fb_jurnal( 'postat „' . get_the_title( $p ) . '”.' );
	eg_ig_posteaza( $p );
	return true;
}

/* ---------- Instagram (prin pagina de Facebook) ---------- */

/** Caută contul de Instagram profesional legat de pagină; întoarce numele de utilizator, '' sau WP_Error. */
function eg_ig_detecteaza() {
	$s = eg_fb_setari();
	if ( ! $s['token'] || ! $s['pagina_id'] ) {
		return new WP_Error( 'eg_ig', 'pagina de Facebook nu e conectată' );
	}
	$r = eg_fb_cerere( 'GET', $s['pagina_id'], array( 'fields' => 'instagram_business_account{id,username}', 'access_token' => $s['token'] ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$ig            = $r['instagram_business_account'] ?? array();
	$s['ig_id']    = (string) ( $ig['id'] ?? '' );
	$s['ig_nume']  = (string) ( $ig['username'] ?? '' );
	eg_fb_salveaza( $s );
	return $s['ig_nume'];
}

/** Postează coperta articolului pe Instagram, cu titlul, rezumatul și hashtag-urile. O singură dată pe articol. */
function eg_ig_posteaza( $p, $din_nou = false ) {
	$s = eg_fb_setari();
	if ( ! $s['ig_id'] || ! $s['token'] || ( ! $din_nou && ( ! $s['ig_activ'] || get_post_meta( $p->ID, '_eg_ig_post', true ) ) ) ) {
		return false;
	}
	if ( ! function_exists( 'eg_ig_card' ) || ! get_post_thumbnail_id( $p ) ) {
		eg_fb_jurnal( 'Instagram: „' . get_the_title( $p ) . '” nu are copertă, sărit.' );
		return false;
	}
	$card = eg_ig_card( $p, $din_nou );
	if ( is_wp_error( $card ) ) {
		eg_fb_jurnal( 'Instagram: imaginea pentru „' . get_the_title( $p ) . '”: ' . $card->get_error_message() );
		return false;
	}
	$c = eg_fb_cerere( 'POST', $s['ig_id'] . '/media', array( 'image_url' => $card[1] . '?v=' . filemtime( $card[0] ), 'caption' => eg_ig_text( $p ), 'access_token' => $s['token'] ) );
	if ( is_wp_error( $c ) || empty( $c['id'] ) ) {
		eg_fb_jurnal( 'Instagram: eroare la „' . get_the_title( $p ) . '”: ' . ( is_wp_error( $c ) ? $c->get_error_message() : 'fără id' ) );
		return false;
	}
	// Instagram procesează imaginea câteva secunde; publicarea înainte de FINISHED dă „Fatal”.
	$stare = '';
	for ( $i = 0; $i < 10; $i++ ) {
		$st    = eg_fb_cerere( 'GET', $c['id'], array( 'fields' => 'status_code,status', 'access_token' => $s['token'] ) );
		$stare = is_wp_error( $st ) ? '' : (string) ( $st['status_code'] ?? '' );
		if ( 'FINISHED' === $stare || 'ERROR' === $stare || 'EXPIRED' === $stare ) {
			break;
		}
		sleep( 3 );
	}
	if ( 'ERROR' === $stare || 'EXPIRED' === $stare ) {
		eg_fb_jurnal( 'Instagram: imaginea pentru „' . get_the_title( $p ) . '” a fost respinsă (' . $stare . ( empty( $st['status'] ) ? '' : ': ' . $st['status'] ) . ').' );
		return false;
	}
	$r = eg_fb_cerere( 'POST', $s['ig_id'] . '/media_publish', array( 'creation_id' => $c['id'], 'access_token' => $s['token'] ) );
	if ( is_wp_error( $r ) ) {
		sleep( 10 );
		$r = eg_fb_cerere( 'POST', $s['ig_id'] . '/media_publish', array( 'creation_id' => $c['id'], 'access_token' => $s['token'] ) );
	}
	if ( is_wp_error( $r ) ) {
		$lim = eg_fb_cerere( 'GET', $s['ig_id'] . '/content_publishing_limit', array( 'fields' => 'quota_usage,config', 'access_token' => $s['token'] ) );
		if ( ! is_wp_error( $lim ) && isset( $lim['data'][0]['quota_usage'] ) ) {
			$r = new WP_Error( 'eg_fb', $r->get_error_message() . ' — limită zilnică: ' . $lim['data'][0]['quota_usage'] . '/' . ( $lim['data'][0]['config']['quota_total'] ?? '?' ) . ', stare imagine: ' . ( $stare ?: 'necunoscută' ) );
		}
	}
	if ( is_wp_error( $r ) ) {
		eg_fb_jurnal( 'Instagram: eroare la publicarea „' . get_the_title( $p ) . '”: ' . $r->get_error_message() );
		return false;
	}
	update_post_meta( $p->ID, '_eg_ig_post', $r['id'] ?? 'trimis' );
	update_post_meta( $p->ID, '_eg_ig_cand', time() );
	eg_fb_jurnal( 'Instagram: postat „' . get_the_title( $p ) . '”.' );
	return true;
}

/* ---------- Pagina din administrare ---------- */

add_action( 'admin_menu', fn() => add_management_page( 'Facebook Eurogol', 'Facebook Eurogol', 'manage_options', 'eg-facebook', 'eg_fb_pagina' ) );

function eg_fb_pagina() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$mesaj = '';
	$s     = eg_fb_setari();
	if ( isset( $_POST['eg_fb_actiune'] ) && check_admin_referer( 'eg_fb' ) ) {
		$a = sanitize_key( wp_unslash( $_POST['eg_fb_actiune'] ) );
		if ( 'conecteaza' === $a ) {
			$r     = eg_fb_conecteaza(
				sanitize_text_field( wp_unslash( $_POST['app_id'] ?? '' ) ),
				sanitize_text_field( wp_unslash( $_POST['app_secret'] ?? '' ) ),
				sanitize_text_field( wp_unslash( $_POST['user_token'] ?? '' ) ),
				sanitize_text_field( wp_unslash( $_POST['pagina'] ?? '' ) )
			);
			$mesaj = is_wp_error( $r ) ? 'Eroare: ' . $r->get_error_message() : 'Conectat la pagina „' . $r . '”.';
		} elseif ( 'salveaza' === $a ) {
			$activ = empty( $_POST['activ'] ) ? 0 : 1;
			if ( $activ && ! $s['activ'] ) {
				$s['de_la'] = time(); // doar articolele publicate de acum înainte
			}
			$s['activ']    = $activ;
			$iv            = absint( $_POST['interval'] ?? 0 );
			$s['interval'] = 0 === $iv ? 0 : max( 5, $iv );
			$s['ig_activ'] = empty( $_POST['ig_activ'] ) ? 0 : 1;
			eg_fb_salveaza( $s );
			$mesaj = 'Setările au fost salvate.';
			if ( $s['ig_activ'] && ! $s['ig_id'] ) {
				$ig    = eg_ig_detecteaza();
				$mesaj .= is_wp_error( $ig ) ? ' Instagram: ' . $ig->get_error_message() : ( $ig ? ' Instagram: contul @' . $ig . ' a fost găsit.' : ' Instagram: pagina de Facebook nu are un cont de Instagram profesional legat.' );
			}
		} elseif ( 'test' === $a && $s['token'] ) {
			$r     = eg_fb_cerere( 'GET', $s['pagina_id'], array( 'fields' => 'name,followers_count', 'access_token' => $s['token'] ) );
			$mesaj = is_wp_error( $r ) ? 'Eroare: ' . $r->get_error_message() : 'Conexiunea merge: „' . $r['name'] . '”' . ( isset( $r['followers_count'] ) ? ', ' . (int) $r['followers_count'] . ' urmăritori' : '' ) . '.';
			$ig     = eg_ig_detecteaza();
			$mesaj .= is_wp_error( $ig ) ? ' Instagram: ' . $ig->get_error_message() : ( $ig ? ' Instagram: @' . $ig . '.' : ' Instagram: niciun cont profesional legat de pagină.' );
		} elseif ( 'ultimul' === $a && $s['token'] ) {
			$ult = get_posts( array( 'numberposts' => 1 ) );
			if ( $ult ) {
				delete_post_meta( $ult[0]->ID, '_eg_fb_post' );
				update_option( 'eg_fb_coada', array( $ult[0]->ID ), false );
				$s['ultima'] = 0;
				eg_fb_salveaza( $s );
				$sa          = $s['activ'];
				$s['activ']  = 1;
				eg_fb_salveaza( $s );
				eg_fb_trimite();
				$s           = eg_fb_setari();
				$s['activ']  = $sa;
				eg_fb_salveaza( $s );
				$mesaj = get_post_meta( $ult[0]->ID, '_eg_fb_post', true ) ? 'Am postat cel mai nou articol pe pagină.' : 'Nu s-a putut posta; vezi jurnalul din Instrumente → Import Eurogol.';
			}
		} elseif ( 'ig_din_nou' === $a && $s['token'] ) {
			$pid = absint( $_POST['post_id'] ?? 0 );
			$pp  = $pid ? get_post( $pid ) : null;
			$mesaj = ( $pp && eg_ig_posteaza( $pp, true ) ) ? 'Am postat din nou pe Instagram: „' . get_the_title( $pp ) . '”.' : 'Nu s-a putut posta pe Instagram; vezi jurnalul din Instrumente → Import Eurogol.';
		} elseif ( 'deconecteaza' === $a ) {
			delete_option( 'eg_fb' );
			delete_option( 'eg_fb_coada' );
			$mesaj = 'Pagina a fost deconectată.';
		}
		$s = eg_fb_setari();
	}
	$coada = count( (array) get_option( 'eg_fb_coada', array() ) );
	$urm   = ( $s['ultima'] && $s['interval'] ) ? max( 0, (int) ( ( $s['ultima'] + 60 * $s['interval'] - time() ) / 60 ) ) : 0;
	?>
	<div class="wrap">
		<h1>Facebook Eurogol</h1>
		<?php if ( $mesaj ) : ?><div class="notice notice-info"><p><?php echo esc_html( $mesaj ); ?></p></div><?php endif; ?>
		<p>Pagina conectată: <strong><?php echo $s['pagina_nume'] ? esc_html( $s['pagina_nume'] ) : 'niciuna'; ?></strong>
			· distribuirea automată: <strong><?php echo $s['activ'] ? 'pornită' : 'oprită'; ?></strong>
			· Instagram: <strong><?php echo $s['ig_id'] ? '@' . esc_html( $s['ig_nume'] ) . ( $s['ig_activ'] ? ' (pornit)' : ' (oprit)' ) : 'nelegat'; ?></strong>
			· articole în coadă: <strong><?php echo (int) $coada; ?></strong>
			<?php if ( $coada && $s['activ'] ) : ?> · următoarea postare în cel mult <strong><?php echo (int) $urm + 5; ?> minute</strong><?php endif; ?></p>
		<?php if ( ! $s['activ'] && $s['token'] ) : ?><div class="notice notice-warning inline"><p>Distribuirea automată e <strong>oprită</strong>: bifează „Postează pe pagină fiecare articol nou” și apasă Salvează.</p></div><?php endif; ?>

		<h2>1. Conectarea paginii</h2>
		<form method="post">
			<?php wp_nonce_field( 'eg_fb' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="app_id">App ID</label></th><td><input class="regular-text" id="app_id" name="app_id" autocomplete="off"></td></tr>
				<tr><th><label for="app_secret">App Secret</label></th><td><input class="regular-text" type="password" id="app_secret" name="app_secret" autocomplete="off"><p class="description">Folosit o singură dată, pentru schimbul de token; nu se salvează.</p></td></tr>
				<tr><th><label for="user_token">Token de utilizator</label></th><td><input class="large-text" type="password" id="user_token" name="user_token" autocomplete="off"><p class="description">Din Graph API Explorer, cu permisiunile pages_show_list, pages_read_engagement, pages_manage_posts; pentru Instagram, și business_management, instagram_basic, instagram_content_publish.</p></td></tr>
				<tr><th><label for="pagina">Pagina</label></th><td><input class="regular-text" id="pagina" name="pagina" value="EuroGol.ro"><p class="description">Numele de utilizator, numele sau ID-ul paginii.</p></td></tr>
			</table>
			<p><button class="button button-primary" name="eg_fb_actiune" value="conecteaza">Conectează pagina</button></p>
		</form>

		<h2>2. Distribuirea</h2>
		<form method="post">
			<?php wp_nonce_field( 'eg_fb' ); ?>
			<table class="form-table" role="presentation">
				<tr><th>Automat</th><td><label><input type="checkbox" name="activ" value="1" <?php checked( $s['activ'] ); ?>> Postează pe pagină fiecare articol nou (doar cele publicate după activare)</label></td></tr>
				<tr><th>Instagram</th><td><label><input type="checkbox" name="ig_activ" value="1" <?php checked( $s['ig_activ'] ); ?>> Postează fiecare articol și pe contul de Instagram legat de pagină (imagine verticală cu titlul + rezumat + hashtag-uri)</label><p class="description">Contul trebuie să fie profesional (Business sau Creator) și legat de pagina de Facebook. Cont găsit: <strong><?php echo $s['ig_id'] ? '@' . esc_html( $s['ig_nume'] ) : 'niciunul'; ?></strong>.</p></td></tr>
				<tr><th><label for="interval">Interval minim</label></th><td><input type="number" min="0" id="interval" name="interval" value="<?php echo (int) $s['interval']; ?>"> minute între două postări<p class="description">0 = fiecare articol nou pleacă imediat după ce apare pe site (la finalul importului, cel mult <?php echo (int) EG_FB_MAX_RULARE; ?> odată).</p></td></tr>
			</table>
			<p>
				<button class="button button-primary" name="eg_fb_actiune" value="salveaza">Salvează</button>
				<button class="button" name="eg_fb_actiune" value="test" <?php disabled( ! $s['token'] ); ?>>Testează conexiunea</button>
				<button class="button" name="eg_fb_actiune" value="ultimul" <?php disabled( ! $s['token'] ); ?>>Postează acum cel mai nou articol</button>
				<button class="button button-link-delete" name="eg_fb_actiune" value="deconecteaza" <?php disabled( ! $s['token'] ); ?>>Deconectează</button>
			</p>
		</form>

		<?php if ( $s['ig_id'] ) : ?>
		<h2>3. Instagram: ultimele articole</h2>
		<p class="description">Imaginea și textul care pleacă pe Instagram. Click pe imagine = descarcă.</p>
		<table class="widefat striped" style="max-width:1200px">
			<tbody>
			<?php foreach ( get_posts( array( 'numberposts' => 15 ) ) as $pp ) :
				$card = function_exists( 'eg_ig_card' ) && get_post_thumbnail_id( $pp ) ? eg_ig_card( $pp ) : null;
				$cand = (int) get_post_meta( $pp->ID, '_eg_ig_cand', true );
				$post_ig = get_post_meta( $pp->ID, '_eg_ig_post', true );
				?>
				<tr>
					<td style="width:170px">
						<?php if ( $card && ! is_wp_error( $card ) ) : ?>
							<a href="<?php echo esc_url( $card[1] ); ?>" download><img src="<?php echo esc_url( $card[1] . '?v=' . filemtime( $card[0] ) ); ?>" width="160" alt=""></a><br><small>click = descarcă</small>
						<?php else : ?>—<?php endif; ?>
					</td>
					<td><textarea readonly rows="7" style="width:100%"><?php echo esc_textarea( function_exists( 'eg_ig_text' ) ? eg_ig_text( $pp ) : '' ); ?></textarea></td>
					<td style="width:200px">
						<?php if ( $post_ig ) : ?><p>✅ postat <?php echo $cand ? esc_html( wp_date( 'Y-m-d H:i', $cand ) ) : ''; ?></p><?php else : ?><p>nepostat</p><?php endif; ?>
						<form method="post">
							<?php wp_nonce_field( 'eg_fb' ); ?>
							<input type="hidden" name="post_id" value="<?php echo (int) $pp->ID; ?>">
							<button class="button" name="eg_fb_actiune" value="ig_din_nou"><?php echo $post_ig ? 'Postează din nou' : 'Postează acum'; ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>
	<?php
}

/* ---------- Stare publică (fără token): GET /wp-json/eg/v1/fb-stare ---------- */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'eg/v1',
		'/fb-stare',
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'callback'            => function () {
				$s = eg_fb_setari();
				$j = array_values( array_filter( (array) get_option( 'eg_import_jurnal', array() ), fn( $l ) => false !== strpos( $l, 'Facebook:' ) ) );
				$c = (array) get_option( 'eg_fb_coada', array() );
				return array(
					'activ'      => (bool) $s['activ'],
					'conectat'   => '' !== (string) $s['token'],
					'interval'   => (int) $s['interval'],
					'ultima'     => $s['ultima'] ? wp_date( 'Y-m-d H:i', (int) $s['ultima'] ) : '',
					'de_la'      => $s['de_la'] ? wp_date( 'Y-m-d H:i', (int) $s['de_la'] ) : '',
					'coada'      => count( $c ),
					'instagram'  => $s['ig_id'] ? '@' . $s['ig_nume'] . ( $s['ig_activ'] ? ' (pornit)' : ' (oprit)' ) : 'nelegat',
					'import'     => wp_date( 'Y-m-d H:i', (int) get_option( 'eg_import_ultima', 0 ) ),
					'urmatorul'  => wp_next_scheduled( 'eg_import' ) ? wp_date( 'Y-m-d H:i', wp_next_scheduled( 'eg_import' ) ) : '',
					'acum'       => wp_date( 'Y-m-d H:i' ),
					'jurnal'     => array_slice( $j, -15 ),
				);
			},
		)
	);
} );

/* Avertisment în panou când Facebook a invalidat conexiunea. */
add_action( 'admin_notices', function () {
	if ( get_option( 'eg_fb_token_cazut' ) && current_user_can( 'manage_options' ) ) {
		echo '<div class="notice notice-error"><p><strong>Facebook Eurogol:</strong> conexiunea cu pagina a expirat (parolă schimbată sau App Secret resetat). Postările sunt oprite. <a href="' . esc_url( admin_url( 'tools.php?page=eg-facebook' ) ) . '">Reconectează pagina</a>.</p></div>';
	}
} );
