<?php
/**
 * Clasamentul la finalul articolelor (date publice LiveScore, fără cheie – ca la povestilestiintei.ro).
 *
 * Competiția articolului, în ordine:
 *   1. câmpul „clasament” din JSON-ul articolului (slug, ex. "serie-a"; false = fără clasament), salvat la import;
 *   2. categoria articolului (ex. „SuperLiga”, „Champions League”);
 *   3. etichetele (ex. „Serie A”);
 *   4. echipele din titlu și etichete, căutate în clasamentele tuturor competițiilor.
 * Echipele pomenite în titlu și etichete sunt evidențiate în tabel.
 * Clasamentele se reîmprospătează în fundal, la importul de la 15 minute (cel mult o dată la 30 de minute).
 */

defined( 'ABSPATH' ) || exit;

/** slug => [ calea LiveScore, numele afișat ] – în ordinea priorității la căutarea după echipe. */
function eg_clasament_competitii() {
	return array(
		'superliga'         => array( 'romania/liga-1', 'SuperLiga' ),
		'premier-league'    => array( 'england/premier-league', 'Premier League' ),
		'laliga'            => array( 'spain/laliga', 'LaLiga' ),
		'serie-a'           => array( 'italy/serie-a', 'Serie A' ),
		'bundesliga'        => array( 'germany/bundesliga', 'Bundesliga' ),
		'ligue-1'           => array( 'france/ligue-1', 'Ligue 1' ),
		'champions-league'  => array( 'champions-league/league-stage', 'Champions League' ),
		'europa-league'     => array( 'europa-league/league-stage', 'Europa League' ),
		'conference-league' => array( 'conference-league/league-stage', 'Conference League' ),
		'brasileirao'       => array( 'brazil/serie-a', 'Brasileirão' ),
		'liga-profesional'  => array( 'argentina/primera-division-clausura', 'Liga Profesional (Argentina)' ),
	);
}

/** Zonele (coduri LiveScore „phr”), cu numele și culoarea lor. */
function eg_clasament_zone() {
	return array(
		20  => array( 'Liga Campionilor', '#1f6feb' ),
		21  => array( 'Liga Campionilor (calificări)', '#58a6ff' ),
		25  => array( 'Europa League', '#e8730c' ),
		191 => array( 'Conference League (calificări)', '#2ea043' ),
		5   => array( 'Play-off', '#1f6feb' ),
		16  => array( 'Baraj / play-out', '#d29922' ),
		15  => array( 'Retrogradare', '#cf222e' ),
		50  => array( 'Copa Libertadores', '#1f6feb' ),
		51  => array( 'Copa Libertadores (calificări)', '#58a6ff' ),
		52  => array( 'Copa Sudamericana', '#2ea043' ),
		901 => array( 'Optimi (direct)', '#1f6feb' ),
		902 => array( 'Play-off pentru optimi', '#58a6ff' ),
	);
}

/** Citește clasamentul din LiveScore: [ [ grupa, [rânduri] ], ... ] sau null. */
function eg_clasament_descarca( $cale ) {
	$r = wp_remote_get( 'https://prod-public-api.livescore.com/v1/api/app/stage/soccer/' . $cale . '/0?locale=en&MD=1', array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/json' ) ) );
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
		return null;
	}
	$d      = json_decode( wp_remote_retrieve_body( $r ), true );
	$grupe  = array();
	$faza_l = false !== strpos( $cale, 'league-stage' );
	foreach ( (array) ( $d['Stages'] ?? array() ) as $st ) {
		foreach ( (array) ( $st['LeagueTable']['L'] ?? array() ) as $l ) {
			foreach ( (array) ( $l['Tables'] ?? array() ) as $tb ) {
				if ( ! in_array( (int) ( $tb['LTT'] ?? 0 ), array( 1, 11 ), true ) ) {
					continue; // doar clasamentul total (nu acasă / deplasare)
				}
				$rows = array();
				foreach ( (array) ( $tb['team'] ?? array() ) as $x ) {
					$loc  = (int) $x['rnk'];
					$zone = array_map( 'intval', (array) ( $x['phr'] ?? array() ) );
					if ( $faza_l && ! array_intersect( $zone, array_keys( eg_clasament_zone() ) ) ) { // faza principală a cupelor europene: 1–8 direct în optimi, 9–24 play-off
						$zone = $loc <= 8 ? array( 901 ) : ( $loc <= 24 ? array( 902 ) : array() );
					}
					$rows[] = array(
						'loc'  => $loc,
						'nume' => (string) $x['Tnm'],
						'img'  => ! empty( $x['Img'] ) ? 'https://storage.livescore.com/images/team/medium/' . ltrim( $x['Img'], '/' ) : '',
						'm'    => (int) $x['pld'],
						'v'    => (int) $x['win'],
						'e'    => (int) $x['drw'],
						'i'    => (int) $x['lst'],
						'gm'   => (int) $x['gf'],
						'gp'   => (int) $x['ga'],
						'pct'  => (int) $x['pts'],
						'zona' => $zone,
					);
				}
				if ( $rows ) {
					usort( $rows, fn( $a, $b ) => $a['loc'] - $b['loc'] );
					$grupe[] = array( (string) ( $tb['name'] ?? '' ), $rows );
				}
			}
		}
		if ( $grupe ) {
			break;
		}
	}
	return $grupe ?: null;
}

/** Toate clasamentele, din cache (opțiunea „eg_clasamente”); $reimprospateaza = descarcă cele mai vechi de 30 de minute. */
function eg_clasamente( $reimprospateaza = false ) {
	$toate = (array) get_option( 'eg_clasamente', array() );
	if ( $reimprospateaza ) {
		$schimbat = false;
		foreach ( eg_clasament_competitii() as $slug => $c ) {
			if ( ! empty( $toate[ $slug ]['t'] ) && time() - $toate[ $slug ]['t'] < 30 * MINUTE_IN_SECONDS ) {
				continue;
			}
			$g = eg_clasament_descarca( $c[0] );
			if ( $g ) {
				$toate[ $slug ] = array( 't' => time(), 'grupe' => $g );
				$schimbat       = true;
			}
		}
		if ( $schimbat ) {
			update_option( 'eg_clasamente', $toate, false );
		}
	}
	return $toate;
}

add_action( 'eg_import', 'eg_clasament_cron', 30 );
function eg_clasament_cron() {
	eg_clasamente( true );
}

/** Normalizare pentru potrivirea numelor de echipe. */
function eg_clasament_norm( $s ) {
	$s = strtolower( remove_accents( (string) $s ) );
	$s = preg_replace( '/[^a-z0-9 ]+/', ' ', $s );
	return ' ' . trim( preg_replace( '/\s+/', ' ', $s ) ) . ' ';
}

/** Variantele după care recunoaștem o echipă din clasament într-un text. */
function eg_clasament_variante( $nume ) {
	static $alias = array(
		'CS Universitatea Craiova' => array( 'universitatea craiova', 'craiova' ),
		'Universitatea Cluj'       => array( 'u cluj', 'universitatea cluj' ),
		'FC Rapid 1923'            => array( 'rapid' ),
		'FCV Farul Constanta'      => array( 'farul' ),
		'Miercurea Ciuc'           => array( 'csikszereda', 'miercurea ciuc' ),
		'ACS Champions FC Arges'   => array( 'arges', 'fc arges' ),
		'Dinamo Bucuresti'         => array( 'dinamo bucuresti', 'dinamo' ),
		'Bayern Munich'            => array( 'bayern' ),
		'Paris Saint-Germain'      => array( 'psg', 'paris saint germain' ),
		'Atletico Madrid'          => array( 'atletico madrid', 'atletico' ),
		'Inter'                    => array( 'inter' ),
		'AC Milan'                 => array( 'milan' ),
	);
	if ( isset( $alias[ $nume ] ) ) {
		return $alias[ $nume ];
	}
	$generice = array( 'fc', 'cf', 'cs', 'sc', 'ac', 'afc', 'acs', 'ss', 'as', 'fk', 'sk', 'sv', 'vfb', 'vfl', 'club', 'de', 'calcio', 'osk' );
	$w        = array_values( array_diff( explode( ' ', trim( eg_clasament_norm( preg_replace( '/\d+/', '', $nume ) ) ) ), $generice ) );
	return $w ? array( implode( ' ', $w ) ) : array();
}

/** Echipele din clasament pomenite în text: [ nume LiveScore => true ]. */
function eg_clasament_echipe( $grupe, $text ) {
	$t   = eg_clasament_norm( $text );
	$gas = array();
	foreach ( $grupe as $g ) {
		foreach ( $g[1] as $r ) {
			foreach ( eg_clasament_variante( $r['nume'] ) as $v ) {
				if ( strlen( $v ) >= 3 && false !== strpos( $t, ' ' . $v . ' ' ) ) {
					$gas[ $r['nume'] ] = true;
					break;
				}
			}
		}
	}
	return $gas;
}

/** Competiția și echipele de evidențiat pentru un articol: [ slug, [echipe] ] sau null. */
function eg_clasament_pentru( $post ) {
	$post = get_post( $post );
	$meta = get_post_meta( $post->ID, '_eg_clasament', true );
	if ( 'nu' === $meta ) {
		return null;
	}
	$comp  = eg_clasament_competitii();
	$cats  = wp_list_pluck( get_the_category( $post->ID ), 'slug' );
	if ( array_intersect( $cats, array( 'povesti', 'rezumate-video' ) ) && ! $meta ) {
		return null; // poveștile sunt istorie: clasamentul de azi nu are ce căuta acolo
	}
	$tags  = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
	$text  = $post->post_title . ' ' . implode( ' ', $tags );
	$toate = eg_clasamente();

	$slug = isset( $comp[ $meta ] ) ? $meta : '';
	if ( ! $slug ) {
		$slug = (string) current( array_intersect( $cats, array_keys( $comp ) ) );
	}
	if ( ! $slug ) {
		foreach ( $tags as $tg ) {
			$s = sanitize_title( $tg );
			if ( isset( $comp[ $s ] ) ) {
				$slug = $s;
				break;
			}
		}
	}
	if ( ! $slug ) { // după echipe: competiția cu cele mai multe echipe pomenite (la egalitate, prima din listă)
		$best = 0;
		foreach ( array_keys( $comp ) as $s ) {
			$n = count( eg_clasament_echipe( $toate[ $s ]['grupe'] ?? array(), $text ) );
			if ( $n > $best ) {
				$best = $n;
				$slug = $s;
			}
		}
	}
	if ( ! $slug ) {
		return null;
	}
	if ( empty( $toate[ $slug ]['grupe'] ) ) { // încă nu e în cache: o descărcăm acum (o singură dată)
		$g = eg_clasament_descarca( $comp[ $slug ][0] );
		if ( ! $g ) {
			return null;
		}
		$toate[ $slug ] = array( 't' => time(), 'grupe' => $g );
		update_option( 'eg_clasamente', $toate, false );
	}
	return array( $slug, eg_clasament_echipe( $toate[ $slug ]['grupe'], $text ), $toate[ $slug ] );
}

/** Tabelul HTML. */
function eg_clasament_html( $post ) {
	$x = eg_clasament_pentru( $post );
	if ( ! $x ) {
		return '';
	}
	list( $slug, $ale_noastre, $date ) = $x;
	$zone  = eg_clasament_zone();
	$nume  = eg_clasament_competitii()[ $slug ][1];
	$grupe = $date['grupe'];
	if ( count( $grupe ) > 1 && $ale_noastre ) { // la ligile cu grupe: doar grupa echipei
		$grupe = array_values( array_filter( $grupe, fn( $g ) => array_intersect_key( array_flip( wp_list_pluck( $g[1], 'nume' ) ), $ale_noastre ) ) ) ?: $grupe;
	}
	$folosite = array();
	$out      = '<section class="eg-clasament" aria-label="Clasament ' . esc_attr( $nume ) . '"><h2 class="eg-titlu-sectiune">Clasament ' . esc_html( $nume ) . '</h2>';
	foreach ( $grupe as $g ) {
		$rows = $g[1];
		// Clasamente lungi (36 de echipe): primele 8 și echipele noastre cu vecinii lor; tot tabelul, la cerere.
		$arata = array();
		if ( count( $rows ) > 20 ) {
			foreach ( $rows as $k => $r ) {
				if ( $k < 8 ) {
					$arata[ $k ] = true;
				}
				if ( isset( $ale_noastre[ $r['nume'] ] ) ) {
					for ( $j = max( 0, $k - 2 ); $j <= min( count( $rows ) - 1, $k + 2 ); $j++ ) {
						$arata[ $j ] = true;
					}
				}
			}
		}
		$tabel = function ( $doar ) use ( $rows, $ale_noastre, $zone, &$folosite ) {
			$h    = '<table><thead><tr><th class="eg-cl-loc">#</th><th class="eg-cl-echipa">Echipa</th><th>M</th><th class="eg-cl-det">V</th><th class="eg-cl-det">E</th><th class="eg-cl-det">Î</th><th>GV</th><th>Pct</th></tr></thead><tbody>';
			$prev = -1;
			foreach ( $rows as $k => $r ) {
				if ( $doar && empty( $doar[ $k ] ) ) {
					continue;
				}
				if ( $doar && $prev >= 0 && $k > $prev + 1 ) {
					$h .= '<tr class="eg-cl-pauza"><td colspan="8">…</td></tr>';
				}
				$prev = $k;
				$z    = '';
				foreach ( $r['zona'] as $c ) {
					if ( isset( $zone[ $c ] ) ) {
						$z               = $zone[ $c ][1];
						$folosite[ $c ] = true;
						break;
					}
				}
				$gv = $r['gm'] - $r['gp'];
				$h .= '<tr' . ( isset( $ale_noastre[ $r['nume'] ] ) ? ' class="eg-cl-noi"' : '' ) . '>'
					. '<td class="eg-cl-loc"' . ( $z ? ' style="box-shadow: inset 4px 0 0 ' . esc_attr( $z ) . '"' : '' ) . '>' . (int) $r['loc'] . '</td>'
					. '<td class="eg-cl-echipa"><span class="eg-cl-n">' . ( $r['img'] ? '<img src="' . esc_url( $r['img'] ) . '" alt="" width="20" height="20" loading="lazy" onerror="this.style.visibility=\'hidden\'">' : '' ) . '<span>' . esc_html( $r['nume'] ) . '</span></span></td>'
					. '<td>' . (int) $r['m'] . '</td><td class="eg-cl-det">' . (int) $r['v'] . '</td><td class="eg-cl-det">' . (int) $r['e'] . '</td><td class="eg-cl-det">' . (int) $r['i'] . '</td>'
					. '<td>' . ( $gv > 0 ? '+' : '' ) . $gv . '</td><td class="eg-cl-pct">' . (int) $r['pct'] . '</td></tr>';
			}
			return $h . '</tbody></table>';
		};
		$out .= $g[0] && count( $grupe ) > 1 ? '<h3 class="eg-cl-grupa">' . esc_html( $g[0] ) . '</h3>' : '';
		if ( $arata ) {
			$out .= '<div class="eg-cl-tabel">' . $tabel( $arata ) . '</div><details class="eg-cl-tot"><summary>Tot clasamentul (' . count( $rows ) . ' de echipe)</summary><div class="eg-cl-tabel">' . $tabel( null ) . '</div></details>';
		} else {
			$out .= '<div class="eg-cl-tabel">' . $tabel( null ) . '</div>';
		}
	}
	if ( $folosite ) {
		$out .= '<ul class="eg-cl-legenda">';
		foreach ( array_keys( $folosite ) as $c ) {
			$out .= '<li><i style="background:' . esc_attr( $zone[ $c ][1] ) . '"></i>' . esc_html( $zone[ $c ][0] ) . '</li>';
		}
		$out .= '</ul>';
	}
	$out .= '<p class="eg-cl-sursa">Sursa: LiveScore · actualizat ' . esc_html( eg_data_ro( (int) $date['t'] ) ) . '</p></section>';
	return $out;
}
