<?php
/**
 * Instagram: imaginea verticală a postării (1080×1350) și textul ei, ca la Poveștile Științei.
 *
 * - Imaginea: sus sigla Eurogol pe banda în culoarea temei, la mijloc coperta articolului, dedesubt titlul scris mare,
 *   jos banda „CITEȘTE TOT ARTICOLUL · LINK ÎN BIO · EUROGOL.RO”. Se desenează cu GD și se păstrează în
 *   wp-content/uploads/eg-instagram/<ID>.jpg (se refolosește la „Postează din nou”).
 * - Textul: titlul, rezumatul, „Citește tot articolul pe eurogol.ro (link în bio)” și hashtag-urile.
 * Postarea propriu-zisă e în inc/facebook.php (eg_ig_posteaza), cu tokenul paginii de Facebook.
 */

defined( 'ABSPATH' ) || exit;

const EG_IG_L = 1080;
const EG_IG_H = 1350;

function eg_ig_font() {
	return EG_DIR . '/assets/fonts/SofiaSansExtraCondensed-ExtraBold.ttf';
}

/** Culoarea principală a temei (Personalizează → Eurogol), ca RGB. */
function eg_ig_culoare() {
	$hex = ltrim( (string) get_theme_mod( 'eg_culoare', '#1546A8' ), '#' );
	if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) {
		$hex = '1546A8';
	}
	return array_map( 'hexdec', str_split( $hex, 2 ) );
}

/** Împarte textul în rânduri care încap în lățime; micșorează fontul până încap cel mult $max rânduri. */
function eg_ig_randuri( $text, $latime, $marime, $max ) {
	$font = eg_ig_font();
	for ( $m = $marime; $m >= 34; $m -= 4 ) {
		$cuvinte = preg_split( '/\s+/u', trim( $text ) );
		$randuri = array();
		$rand    = '';
		foreach ( $cuvinte as $c ) {
			$incerc = '' === $rand ? $c : $rand . ' ' . $c;
			$b      = imagettfbbox( $m, 0, $font, $incerc );
			if ( $b[2] - $b[0] > $latime && '' !== $rand ) {
				$randuri[] = $rand;
				$rand      = $c;
			} else {
				$rand = $incerc;
			}
		}
		$randuri[] = $rand;
		if ( count( $randuri ) <= $max ) {
			return array( $m, $randuri );
		}
	}
	$randuri = array_slice( $randuri, 0, $max );
	$randuri[ $max - 1 ] = rtrim( $randuri[ $max - 1 ], ' ,.;:' ) . '…';
	return array( 34, $randuri );
}

function eg_ig_text_centrat( $im, $text, $marime, $y, $culoare ) {
	$b = imagettfbbox( $marime, 0, eg_ig_font(), $text );
	$x = (int) ( ( EG_IG_L - ( $b[2] - $b[0] ) ) / 2 );
	imagettftext( $im, $marime, 0, $x, $y, $culoare, eg_ig_font(), $text );
}

/** Desenează imaginea; întoarce [cale, url] sau WP_Error. */
function eg_ig_card( $post, $refa = false ) {
	$post = get_post( $post );
	if ( ! function_exists( 'imagettftext' ) ) {
		return new WP_Error( 'eg_ig', 'serverul nu are GD cu FreeType' );
	}
	$up   = wp_upload_dir();
	$dir  = trailingslashit( $up['basedir'] ) . 'eg-instagram';
	$cale = $dir . '/' . $post->ID . '.jpg';
	$url  = trailingslashit( $up['baseurl'] ) . 'eg-instagram/' . $post->ID . '.jpg';
	if ( ! $refa && file_exists( $cale ) ) {
		return array( $cale, $url );
	}
	wp_mkdir_p( $dir );

	$im = imagecreatetruecolor( EG_IG_L, EG_IG_H );
	list( $r, $g, $b ) = eg_ig_culoare();
	$baza   = imagecolorallocate( $im, $r, $g, $b );
	$inchis = imagecolorallocate( $im, (int) ( $r * 0.35 ), (int) ( $g * 0.35 ), (int) ( $b * 0.35 ) );
	$alb    = imagecolorallocate( $im, 255, 255, 255 );
	$galben = imagecolorallocate( $im, 255, 210, 63 );
	imagefill( $im, 0, 0, $inchis );

	// Banda de sus, cu sigla.
	imagefilledrectangle( $im, 0, 0, EG_IG_L, 150, $baza );
	$logo = EG_DIR . '/assets/img/logo.png';
	if ( file_exists( $logo ) && ( $l = imagecreatefrompng( $logo ) ) ) {
		$lw = imagesx( $l );
		$lh = imagesy( $l );
		$h  = 96;
		$w  = (int) ( $lw * $h / $lh );
		imagecopyresampled( $im, $l, (int) ( ( EG_IG_L - $w ) / 2 ), 27, 0, 0, $w, $h, $lw, $lh );
		imagedestroy( $l );
	}

	// Coperta articolului.
	$y0  = 150;
	$hc  = (int) ( EG_IG_L * 630 / 1200 );
	$src = get_attached_file( get_post_thumbnail_id( $post ) );
	if ( $src && file_exists( $src ) ) {
		$c = @imagecreatefromstring( (string) file_get_contents( $src ) );
		if ( $c ) {
			$cw = imagesx( $c );
			$ch = imagesy( $c );
			// „cover”: umple 1080×567, tăiat central.
			$scara = max( EG_IG_L / $cw, $hc / $ch );
			$sw    = (int) ( EG_IG_L / $scara );
			$sh    = (int) ( $hc / $scara );
			imagecopyresampled( $im, $c, 0, $y0, (int) ( ( $cw - $sw ) / 2 ), (int) ( ( $ch - $sh ) / 2 ), EG_IG_L, $hc, $sw, $sh );
			imagedestroy( $c );
		}
	}
	imagefilledrectangle( $im, 0, $y0 + $hc, EG_IG_L, $y0 + $hc + 8, $galben );

	// Titlul.
	$titlu = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
	list( $m, $randuri ) = eg_ig_randuri( $titlu, EG_IG_L - 120, 70, 5 );
	$spatiu = (int) ( $m * 1.18 );
	$zona_s = $y0 + $hc + 8;
	$zona_j = EG_IG_H - 150;
	$y      = (int) ( $zona_s + ( $zona_j - $zona_s - $spatiu * count( $randuri ) ) / 2 + $m );
	foreach ( $randuri as $rand ) {
		eg_ig_text_centrat( $im, $rand, $m, $y, $alb );
		$y += $spatiu;
	}

	// Banda de jos.
	imagefilledrectangle( $im, 0, EG_IG_H - 150, EG_IG_L, EG_IG_H, $baza );
	eg_ig_text_centrat( $im, 'CITEȘTE TOT ARTICOLUL', 52, EG_IG_H - 82, $alb );
	eg_ig_text_centrat( $im, 'LINK ÎN BIO · EUROGOL.RO', 32, EG_IG_H - 32, $galben );

	$ok = imagejpeg( $im, $cale, 90 );
	imagedestroy( $im );
	return $ok ? array( $cale, $url ) : new WP_Error( 'eg_ig', 'nu pot salva imaginea în uploads' );
}

/** Textul postării de Instagram. */
function eg_ig_text( $post ) {
	$post = get_post( $post );
	$t    = html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' );
	if ( has_excerpt( $post ) ) {
		$t .= "\n\n" . html_entity_decode( wp_strip_all_tags( $post->post_excerpt ), ENT_QUOTES, 'UTF-8' );
	}
	$t .= "\n\n📲 Citește tot articolul pe eurogol.ro (link în bio).";
	if ( function_exists( 'eg_fb_hashtaguri' ) ) {
		$t .= "\n\n" . implode( ' ', eg_fb_hashtaguri( $post, 20 ) );
	}
	return mb_substr( $t, 0, 2150 );
}
