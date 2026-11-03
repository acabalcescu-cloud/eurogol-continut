<?php
/**
 * Distribuirea automată pe pagina de Facebook (Graph API), din Instrumente → Facebook Eurogol.
 *
 * - Conectarea: App ID + App Secret ale aplicației Meta + un token de utilizator (Graph API Explorer, cu
 *   pages_show_list, pages_read_engagement, pages_manage_posts). Tema îl schimbă într-un token de lungă durată și
 *   salvează tokenul PAGINII (nu expiră) doar în baza de date a site-ului, niciodată în depozit.
 * - Articolele publicate după activare intră într-o coadă; la fiecare rulare a importului (5 minute) pleacă cel mult
 *   unul, la cel puțin „interval” minute după precedentul. Fiecare articol se postează o singură dată.
 */

defined( 'ABSPATH' ) || exit;

const EG_FB_API = 'https://graph.facebook.com/v21.0/';

function eg_fb_setari() {
	return wp_parse_args(
		(array) get_option( 'eg_fb', array() ),
		array( 'pagina_id' => '', 'pagina_nume' => '', 'token' => '', 'activ' => 0, 'interval' => 20, 'de_la' => 0, 'ultima' => 0 )
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
		return new WP_Error( 'eg_fb', $d['error']['message'] ?? 'eroare Facebook' );
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
	// „EuroGol.ro”, „eurogol”, „facebook.com/EuroGol.ro” → „eurogol”: comparăm fără punctuație și fără „.ro”.
	$norm   = fn( $t ) => preg_replace( '/[^a-z0-9]+/', '', preg_replace( '~^.*facebook\.com/|\.ro$~', '', strtolower( trim( (string) $t ) ) ) );
	$pagina = $norm( $pagina );
	$lista  = (array) ( $conturi['data'] ?? array() );
	if ( ! $lista ) {
		return new WP_Error( 'eg_fb', 'Tokenul nu vede nicio pagină. La „Generate Access Token”, în fereastra Facebook, alege „Editează setările” și bifează pagina EuroGol.ro.' );
	}
	foreach ( $lista as $p ) {
		if ( ! $pagina || 1 === count( $lista ) || in_array( $pagina, array( $norm( $p['id'] ), $norm( $p['name'] ), $norm( $p['username'] ?? '' ) ), true ) ) {
			$s                = eg_fb_setari();
			$s['pagina_id']   = $p['id'];
			$s['pagina_nume'] = $p['name'];
			$s['token']       = $p['access_token'];
			$s['ultima']      = 0; // după reconectare, prima postare pleacă la următorul import.
			eg_fb_salveaza( $s );
			delete_option( 'eg_fb_token_cazut' );
			return $p['name'];
		}
	}
	return new WP_Error( 'eg_fb', 'Pagina „' . $pagina . '” nu e printre cele văzute de token: ' . implode( ', ', wp_list_pluck( $lista, 'name' ) ) . '. Scrie unul dintre aceste nume la „Pagina”.' );
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
	if ( get_option( 'eg_fb_recuperat' ) || ! $s['activ'] || ! $s['token'] ) {
		return;
	}
	$c = (array) get_option( 'eg_fb_coada', array() );
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 40, 'fields' => 'ids', 'date_query' => array( array( 'after' => '6 hours ago', 'column' => 'post_date_gmt' ) ) ) ) as $id ) {
		if ( ! get_post_meta( $id, '_eg_fb_post', true ) && ! in_array( $id, $c, true ) ) {
			$c[] = $id;
		}
	}
	update_option( 'eg_fb_coada', $c, false );
	update_option( 'eg_fb_recuperat', 1 );
	eg_fb_jurnal( count( $c ) . ' articole recente puse în coadă.' );
}

add_action( 'eg_import', 'eg_fb_trimite', 60 );
function eg_fb_trimite() {
	$s = eg_fb_setari();
	$c = array_values( array_filter( (array) get_option( 'eg_fb_coada', array() ) ) );
	if ( ! $s['activ'] || ! $s['token'] || ! $c || time() - (int) $s['ultima'] < 60 * max( 1, (int) $s['interval'] ) ) {
		return;
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
		return;
	}
	$mesaj = html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' );
	if ( has_excerpt( $p ) ) {
		$mesaj .= "\n\n" . html_entity_decode( wp_strip_all_tags( $p->post_excerpt ), ENT_QUOTES, 'UTF-8' );
	}
	$mesaj .= "\n\n" . implode( ' ', eg_fb_hashtaguri( $p ) );
	$r = eg_fb_cerere( 'POST', $s['pagina_id'] . '/feed', array( 'message' => $mesaj, 'link' => get_permalink( $p ), 'access_token' => $s['token'] ) );
	if ( is_wp_error( $r ) ) {
		eg_fb_jurnal( 'eroare la „' . get_the_title( $p ) . '”: ' . $r->get_error_message() );
		if ( preg_match( '/access token|session/i', $r->get_error_message() ) ) {
			update_option( 'eg_fb_token_cazut', time(), false );
		}
		array_unshift( $c, $id ); // reîncearcă la următoarea rulare
		update_option( 'eg_fb_coada', $c, false );
		$s['ultima'] = time(); // fără rafale de reîncercări
		eg_fb_salveaza( $s );
		return;
	}
	delete_option( 'eg_fb_token_cazut' );
	update_post_meta( $id, '_eg_fb_post', $r['id'] ?? 'trimis' );
	$s['ultima'] = time();
	eg_fb_salveaza( $s );
	eg_fb_jurnal( 'postat „' . get_the_title( $p ) . '”.' );
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
			$s['interval'] = max( 5, absint( $_POST['interval'] ?? 20 ) );
			eg_fb_salveaza( $s );
			$mesaj = 'Setările au fost salvate.';
		} elseif ( 'test' === $a && $s['token'] ) {
			$r     = eg_fb_cerere( 'GET', $s['pagina_id'], array( 'fields' => 'name,followers_count', 'access_token' => $s['token'] ) );
			$mesaj = is_wp_error( $r ) ? 'Eroare: ' . $r->get_error_message() : 'Conexiunea merge: „' . $r['name'] . '”' . ( isset( $r['followers_count'] ) ? ', ' . (int) $r['followers_count'] . ' urmăritori' : '' ) . '.';
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
		} elseif ( 'deconecteaza' === $a ) {
			delete_option( 'eg_fb' );
			delete_option( 'eg_fb_coada' );
			$mesaj = 'Pagina a fost deconectată.';
		}
		$s = eg_fb_setari();
	}
	$coada = count( (array) get_option( 'eg_fb_coada', array() ) );
	$urm   = $s['ultima'] ? max( 0, (int) ( ( $s['ultima'] + 60 * $s['interval'] - time() ) / 60 ) ) : 0;
	?>
	<div class="wrap">
		<h1>Facebook Eurogol</h1>
		<?php if ( $mesaj ) : ?><div class="notice notice-info"><p><?php echo esc_html( $mesaj ); ?></p></div><?php endif; ?>
		<p>Pagina conectată: <strong><?php echo $s['pagina_nume'] ? esc_html( $s['pagina_nume'] ) : 'niciuna'; ?></strong>
			· distribuirea automată: <strong><?php echo $s['activ'] ? 'pornită' : 'oprită'; ?></strong>
			· articole în coadă: <strong><?php echo (int) $coada; ?></strong>
			<?php if ( $coada && $s['activ'] ) : ?> · următoarea postare în cel mult <strong><?php echo (int) $urm + 5; ?> minute</strong><?php endif; ?></p>
		<?php if ( ! $s['activ'] && $s['token'] ) : ?><div class="notice notice-warning inline"><p>Distribuirea automată e <strong>oprită</strong>: bifează „Postează pe pagină fiecare articol nou” și apasă Salvează.</p></div><?php endif; ?>

		<h2>1. Conectarea paginii</h2>
		<form method="post">
			<?php wp_nonce_field( 'eg_fb' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="app_id">App ID</label></th><td><input class="regular-text" id="app_id" name="app_id" autocomplete="off"></td></tr>
				<tr><th><label for="app_secret">App Secret</label></th><td><input class="regular-text" type="password" id="app_secret" name="app_secret" autocomplete="off"><p class="description">Folosit o singură dată, pentru schimbul de token; nu se salvează.</p></td></tr>
				<tr><th><label for="user_token">Token de utilizator</label></th><td><input class="large-text" type="password" id="user_token" name="user_token" autocomplete="off"><p class="description">Din Graph API Explorer, cu permisiunile pages_show_list, pages_read_engagement, pages_manage_posts.</p></td></tr>
				<tr><th><label for="pagina">Pagina</label></th><td><input class="regular-text" id="pagina" name="pagina" value="EuroGol.ro"><p class="description">Numele de utilizator, numele sau ID-ul paginii.</p></td></tr>
			</table>
			<p><button class="button button-primary" name="eg_fb_actiune" value="conecteaza">Conectează pagina</button></p>
		</form>

		<h2>2. Distribuirea</h2>
		<form method="post">
			<?php wp_nonce_field( 'eg_fb' ); ?>
			<table class="form-table" role="presentation">
				<tr><th>Automat</th><td><label><input type="checkbox" name="activ" value="1" <?php checked( $s['activ'] ); ?>> Postează pe pagină fiecare articol nou (doar cele publicate după activare)</label></td></tr>
				<tr><th><label for="interval">Interval minim</label></th><td><input type="number" min="5" id="interval" name="interval" value="<?php echo (int) $s['interval']; ?>"> minute între două postări</td></tr>
			</table>
			<p>
				<button class="button button-primary" name="eg_fb_actiune" value="salveaza">Salvează</button>
				<button class="button" name="eg_fb_actiune" value="test" <?php disabled( ! $s['token'] ); ?>>Testează conexiunea</button>
				<button class="button" name="eg_fb_actiune" value="ultimul" <?php disabled( ! $s['token'] ); ?>>Postează acum cel mai nou articol</button>
				<button class="button button-link-delete" name="eg_fb_actiune" value="deconecteaza" <?php disabled( ! $s['token'] ); ?>>Deconectează</button>
			</p>
		</form>
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
