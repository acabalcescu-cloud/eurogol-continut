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
	$pagina = strtolower( trim( (string) $pagina ) );
	foreach ( (array) ( $conturi['data'] ?? array() ) as $p ) {
		if ( ! $pagina || in_array( $pagina, array( strtolower( $p['id'] ), strtolower( $p['name'] ), strtolower( $p['username'] ?? '' ) ), true ) ) {
			$s                = eg_fb_setari();
			$s['pagina_id']   = $p['id'];
			$s['pagina_nume'] = $p['name'];
			$s['token']       = $p['access_token'];
			eg_fb_salveaza( $s );
			return $p['name'];
		}
	}
	return new WP_Error( 'eg_fb', 'Contul nu administrează pagina „' . $pagina . '” (sau tokenul nu are permisiunea pages_show_list).' );
}

/* ---------- Coada ---------- */

add_action( 'transition_post_status', 'eg_fb_in_coada', 20, 3 );
function eg_fb_in_coada( $nou, $vechi, $post ) {
	$s = eg_fb_setari();
	if ( 'publish' !== $nou || 'publish' === $vechi || 'post' !== $post->post_type || ! $s['activ'] || ! $s['token'] ) {
		return;
	}
	if ( get_post_time( 'U', true, $post ) < (int) $s['de_la'] || get_post_meta( $post->ID, '_eg_fb_post', true ) ) {
		return;
	}
	$c = (array) get_option( 'eg_fb_coada', array() );
	if ( ! in_array( $post->ID, $c, true ) ) {
		$c[] = $post->ID;
		update_option( 'eg_fb_coada', $c, false );
	}
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
	if ( ! $p || 'publish' !== $p->post_status || get_post_meta( $id, '_eg_fb_post', true ) ) {
		return;
	}
	$mesaj = html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' );
	if ( has_excerpt( $p ) ) {
		$mesaj .= "\n\n" . html_entity_decode( wp_strip_all_tags( $p->post_excerpt ), ENT_QUOTES, 'UTF-8' );
	}
	$r = eg_fb_cerere( 'POST', $s['pagina_id'] . '/feed', array( 'message' => $mesaj, 'link' => get_permalink( $p ), 'access_token' => $s['token'] ) );
	if ( is_wp_error( $r ) ) {
		eg_fb_jurnal( 'eroare la „' . get_the_title( $p ) . '”: ' . $r->get_error_message() );
		array_unshift( $c, $id ); // reîncearcă la următoarea rulare
		update_option( 'eg_fb_coada', $c, false );
		$s['ultima'] = time(); // fără rafale de reîncercări
		eg_fb_salveaza( $s );
		return;
	}
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
	?>
	<div class="wrap">
		<h1>Facebook Eurogol</h1>
		<?php if ( $mesaj ) : ?><div class="notice notice-info"><p><?php echo esc_html( $mesaj ); ?></p></div><?php endif; ?>
		<p>Pagina conectată: <strong><?php echo $s['pagina_nume'] ? esc_html( $s['pagina_nume'] ) : 'niciuna'; ?></strong>
			· distribuirea automată: <strong><?php echo $s['activ'] ? 'pornită' : 'oprită'; ?></strong>
			· articole în coadă: <strong><?php echo (int) $coada; ?></strong></p>

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
