<?php
/**
 * SEO fără module: titluri, descrieri, canonical, Open Graph / Twitter, date structurate (JSON-LD),
 * firimituri, sitemap Google News, robots. Dacă e activ un modul SEO (Yoast, Rank Math, AIOSEO, SEOPress),
 * etichetele din <head> le lasă pe seama lui și păstrează doar sitemap-ul de știri și firimiturile vizibile.
 */

defined( 'ABSPATH' ) || exit;

function eg_seo_modul_activ() {
	return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
}

function eg_seo_nume() {
	return 'Eurogol';
}

function eg_seo_slogan() {
	return get_theme_mod( 'eg_slogan', 'Fotbal românesc, european și sud-american, spus ca o poveste' );
}

/* ---------- Titlul paginii ---------- */

add_filter( 'document_title_separator', fn() => '|' );
add_filter( 'document_title_parts', 'eg_seo_titlu' );
function eg_seo_titlu( $p ) {
	$p['site'] = eg_seo_nume();
	if ( is_front_page() ) {
		return array( 'title' => eg_seo_nume() . ' – știri, cronici și povești din fotbalul românesc, european și sud-american' );
	}
	if ( is_category() ) {
		$p['title'] = single_cat_title( '', false ) . ': știri, cronici și clasament';
	} elseif ( is_tag() ) {
		$p['title'] = single_tag_title( '', false ) . ': toate articolele';
	} elseif ( is_author() ) {
		$dep        = get_user_meta( get_queried_object_id(), 'eg_departament', true );
		$p['title'] = get_the_author_meta( 'display_name', get_queried_object_id() ) . ( $dep ? ', ' . $dep : '' );
	}
	unset( $p['tagline'] );
	return $p;
}

/* ---------- Descrierea ---------- */

function eg_seo_scurteaza( $t, $max = 158 ) {
	$t = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $t ) ) );
	if ( mb_strlen( $t ) <= $max ) {
		return $t;
	}
	$t = mb_substr( $t, 0, $max - 1 );
	return preg_replace( '/\s+\S*$/u', '', $t ) . '…';
}

function eg_seo_descriere() {
	if ( is_front_page() ) {
		return eg_seo_scurteaza( eg_seo_nume() . ': ' . eg_seo_slogan() . '. Știri din SuperLiga, Champions League, Premier League, LaLiga, Serie A și Copa Libertadores, cronici, clasamente și povești.' );
	}
	if ( is_singular() ) {
		$p = get_queried_object();
		return eg_seo_scurteaza( has_excerpt( $p ) ? $p->post_excerpt : $p->post_content );
	}
	if ( is_category() || is_tag() ) {
		$d = term_description();
		if ( $d ) {
			return eg_seo_scurteaza( $d );
		}
		$n = single_term_title( '', false );
		return is_tag()
			? eg_seo_scurteaza( "Toate articolele Eurogol despre {$n}: știri, declarații, cronici și povești." )
			: eg_seo_scurteaza( "{$n} pe Eurogol: ultimele știri, cronicile meciurilor, declarații, transferuri și clasamentul la zi." );
	}
	if ( is_author() ) {
		return eg_seo_scurteaza( get_the_author_meta( 'description', get_queried_object_id() ) );
	}
	return eg_seo_scurteaza( eg_seo_slogan() );
}

/* ---------- robots ---------- */

add_filter( 'wp_robots', 'eg_seo_robots' );
function eg_seo_robots( $r ) {
	if ( is_search() || is_404() ) {
		return array( 'noindex' => true, 'follow' => true );
	}
	$r['max-image-preview'] = 'large';
	$r['max-snippet']       = '-1';
	$r['max-video-preview'] = '-1';
	return $r;
}

/* ---------- <head>: descriere, Open Graph, Twitter, JSON-LD ---------- */

add_action( 'wp_head', 'eg_seo_head', 4 );
function eg_seo_head() {
	echo "\n<!-- Eurogol SEO -->\n";
	if ( ! eg_seo_modul_activ() ) {
		$desc  = eg_seo_descriere();
		$titlu = wp_get_document_title();
		$url   = eg_seo_url_curent();
		$img   = '';
		$imgw  = 1200;
		$imgh  = 630;
		if ( is_singular() && has_post_thumbnail( get_queried_object_id() ) ) {
			$src = wp_get_attachment_image_src( get_post_thumbnail_id( get_queried_object_id() ), 'full' );
			if ( $src ) {
				list( $img, $imgw, $imgh ) = $src;
			}
		}
		if ( ! $img ) {
			$img = EG_URI . '/assets/img/icon-512.png';
			$imgw = $imgh = 512;
		}
		$m = array(
			array( 'name', 'description', $desc ),
			array( 'property', 'og:locale', 'ro_RO' ),
			array( 'property', 'og:site_name', eg_seo_nume() ),
			array( 'property', 'og:type', is_singular( 'post' ) ? 'article' : 'website' ),
			array( 'property', 'og:title', is_singular() ? get_the_title( get_queried_object_id() ) : $titlu ),
			array( 'property', 'og:description', $desc ),
			array( 'property', 'og:url', $url ),
			array( 'property', 'og:image', $img ),
			array( 'property', 'og:image:width', (string) $imgw ),
			array( 'property', 'og:image:height', (string) $imgh ),
			array( 'name', 'twitter:card', 512 === (int) $imgw ? 'summary' : 'summary_large_image' ),
			array( 'name', 'twitter:title', is_singular() ? get_the_title( get_queried_object_id() ) : $titlu ),
			array( 'name', 'twitter:description', $desc ),
			array( 'name', 'twitter:image', $img ),
		);
		if ( is_singular( 'post' ) ) {
			$p   = get_queried_object();
			$m[] = array( 'property', 'article:published_time', get_the_date( 'c', $p ) );
			$m[] = array( 'property', 'article:modified_time', get_the_modified_date( 'c', $p ) );
			$c   = get_the_category( $p->ID );
			if ( $c ) {
				$m[] = array( 'property', 'article:section', $c[0]->name );
			}
			foreach ( array_slice( (array) get_the_tags( $p->ID ), 0, 8 ) as $t ) {
				if ( $t ) {
					$m[] = array( 'property', 'article:tag', $t->name );
				}
			}
		}
		foreach ( $m as $x ) {
			if ( '' !== (string) $x[2] ) {
				printf( '<meta %s="%s" content="%s">' . "\n", $x[0], esc_attr( $x[1] ), esc_attr( $x[2] ) );
			}
		}
		$v = get_theme_mod( 'eg_google_verificare' );
		if ( $v ) {
			printf( '<meta name="google-site-verification" content="%s">' . "\n", esc_attr( $v ) );
		}
		$b = get_theme_mod( 'eg_bing_verificare' );
		if ( $b ) {
			printf( '<meta name="msvalidate.01" content="%s">' . "\n", esc_attr( $b ) );
		}
	}
	$ld = eg_seo_jsonld();
	if ( $ld && ! eg_seo_modul_activ() ) {
		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n";
	}
	echo "<!-- /Eurogol SEO -->\n";
}

function eg_seo_url_curent() {
	if ( is_singular() ) {
		return get_permalink( get_queried_object_id() );
	}
	if ( is_category() || is_tag() ) {
		return get_term_link( get_queried_object() );
	}
	if ( is_author() ) {
		return get_author_posts_url( get_queried_object_id() );
	}
	return home_url( '/' );
}

/* ---------- Date structurate ---------- */

function eg_seo_organizatie() {
	$retele = array();
	foreach ( array( 'facebook', 'instagram', 'x', 'tiktok', 'youtube' ) as $k ) {
		if ( get_theme_mod( 'eg_' . $k ) ) {
			$retele[] = get_theme_mod( 'eg_' . $k );
		}
	}
	return array_filter(
		array(
			'@type'  => 'NewsMediaOrganization',
			'@id'    => home_url( '/#organizatie' ),
			'name'   => eg_seo_nume(),
			'url'    => home_url( '/' ),
			'logo'   => array( '@type' => 'ImageObject', 'url' => EG_URI . '/assets/img/logo.png', 'width' => 342, 'height' => 112 ),
			'sameAs' => $retele ?: null,
		)
	);
}

function eg_seo_firimituri() {
	$f = array( array( 'Acasă', home_url( '/' ) ) );
	if ( is_singular( 'post' ) ) {
		$c = eg_seo_categoria_principala( get_queried_object_id() );
		if ( $c ) {
			$f[] = array( $c->name, get_category_link( $c ) );
		}
		$f[] = array( get_the_title( get_queried_object_id() ), get_permalink( get_queried_object_id() ) );
	} elseif ( is_category() || is_tag() ) {
		$f[] = array( single_term_title( '', false ), get_term_link( get_queried_object() ) );
	} elseif ( is_author() ) {
		$f[] = array( get_the_author_meta( 'display_name', get_queried_object_id() ), get_author_posts_url( get_queried_object_id() ) );
	}
	return $f;
}

/** Categoria cea mai potrivită: competiția, nu „Știri”. */
function eg_seo_categoria_principala( $post_id ) {
	$cats = get_the_category( $post_id );
	foreach ( $cats as $c ) {
		if ( ! in_array( $c->slug, array( 'stiri', 'uncategorized' ), true ) ) {
			return $c;
		}
	}
	return $cats ? $cats[0] : null;
}

function eg_seo_jsonld() {
	$g   = array();
	$org = eg_seo_organizatie();
	if ( is_front_page() ) {
		$g[] = $org;
		$g[] = array(
			'@type'           => 'WebSite',
			'@id'             => home_url( '/#site' ),
			'url'             => home_url( '/' ),
			'name'            => eg_seo_nume(),
			'description'     => eg_seo_slogan(),
			'inLanguage'      => 'ro-RO',
			'publisher'       => array( '@id' => home_url( '/#organizatie' ) ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => home_url( '/?s={search_term_string}' ),
				'query-input' => 'required name=search_term_string',
			),
		);
	}
	if ( is_singular( 'post' ) ) {
		$p    = get_queried_object();
		$img  = array();
		$tid  = get_post_thumbnail_id( $p );
		if ( $tid ) {
			foreach ( array( 'full', 'eg-mediu' ) as $m ) {
				$s = wp_get_attachment_image_src( $tid, $m );
				if ( $s ) {
					$img[] = $s[0];
				}
			}
		}
		$c    = eg_seo_categoria_principala( $p->ID );
		$text = wp_strip_all_tags( $p->post_content );
		$art  = array(
			'@type'            => 'NewsArticle',
			'@id'              => get_permalink( $p ) . '#articol',
			'mainEntityOfPage' => get_permalink( $p ),
			'headline'         => mb_substr( get_the_title( $p ), 0, 110 ),
			'description'      => eg_seo_scurteaza( has_excerpt( $p ) ? $p->post_excerpt : $text, 300 ),
			'image'            => array_values( array_unique( $img ) ),
			'datePublished'    => get_the_date( 'c', $p ),
			'dateModified'     => get_the_modified_date( 'c', $p ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => get_the_author_meta( 'display_name', $p->post_author ),
				'url'   => get_author_posts_url( $p->post_author ),
			),
			'publisher'        => $org,
			'articleSection'   => $c ? $c->name : null,
			'keywords'         => implode( ', ', wp_list_pluck( (array) get_the_tags( $p->ID ) ?: array(), 'name' ) ),
			'wordCount'        => str_word_count( remove_accents( $text ) ),
			'inLanguage'       => 'ro-RO',
			'isAccessibleForFree' => true,
		);
		// Persoanele din articol (jucători, antrenori).
		if ( function_exists( 'eg_persoane_pe_pagina' ) ) {
			$about = array();
			foreach ( eg_persoane_pe_pagina( $p ) as $x ) {
				$about[] = array( '@type' => 'Person', 'name' => $x['nume'], 'jobTitle' => 'antrenor' === ( $x['rol'] ?? '' ) ? 'Antrenor de fotbal' : 'Fotbalist' );
			}
			if ( $about ) {
				$art['about'] = $about;
			}
		}
		$g[] = array_filter( $art, fn( $v ) => null !== $v && '' !== $v && array() !== $v );
	}
	if ( is_category() || is_tag() || is_author() ) {
		$g[] = array(
			'@type'       => 'CollectionPage',
			'name'        => wp_get_document_title(),
			'url'         => eg_seo_url_curent(),
			'description' => eg_seo_descriere(),
			'inLanguage'  => 'ro-RO',
			'isPartOf'    => array( '@type' => 'WebSite', 'name' => eg_seo_nume(), 'url' => home_url( '/' ) ),
		);
	}
	$f = eg_seo_firimituri();
	if ( count( $f ) > 1 ) {
		$items = array();
		foreach ( $f as $k => $x ) {
			$items[] = array( '@type' => 'ListItem', 'position' => $k + 1, 'name' => $x[0], 'item' => $x[1] );
		}
		$g[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $items );
	}
	return $g ? array( '@context' => 'https://schema.org', '@graph' => $g ) : null;
}

/** Firimiturile vizibile, deasupra titlului articolului. */
function eg_seo_firimituri_html() {
	$f = eg_seo_firimituri();
	if ( count( $f ) < 2 ) {
		return '';
	}
	array_pop( $f ); // fără titlul articolului (e chiar dedesubt)
	$o = '<nav class="eg-firimituri" aria-label="Unde te afli">';
	foreach ( $f as $k => $x ) {
		$o .= ( $k ? '<span aria-hidden="true">›</span>' : '' ) . '<a href="' . esc_url( $x[1] ) . '">' . esc_html( $x[0] ) . '</a>';
	}
	return $o . '</nav>';
}

/* ---------- Sitemap Google News (articolele din ultimele 48 de ore) ---------- */

add_action( 'init', 'eg_seo_reguli' );
function eg_seo_reguli() {
	add_rewrite_rule( '^news-sitemap\.xml$', 'index.php?eg_news_sitemap=1', 'top' );
	if ( get_option( 'eg_seo_reguli_v' ) !== '1' ) {
		flush_rewrite_rules( false );
		update_option( 'eg_seo_reguli_v', '1' );
	}
}
add_filter( 'query_vars', fn( $v ) => array_merge( $v, array( 'eg_news_sitemap' ) ) );

add_action( 'template_redirect', 'eg_seo_news_sitemap', 0 );
function eg_seo_news_sitemap() {
	if ( ! get_query_var( 'eg_news_sitemap' ) ) {
		return;
	}
	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1000,
			'date_query'     => array( array( 'after' => '48 hours ago', 'column' => 'post_date_gmt' ) ),
		)
	);
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:news="http://www.google.com/schemas/sitemap-news/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
	foreach ( $posts as $p ) {
		echo '<url><loc>' . esc_url( get_permalink( $p ) ) . '</loc><news:news><news:publication><news:name>' . esc_html( eg_seo_nume() ) . '</news:name><news:language>ro</news:language></news:publication>'
			. '<news:publication_date>' . esc_html( get_the_date( 'c', $p ) ) . '</news:publication_date><news:title>' . esc_html( get_the_title( $p ) ) . '</news:title></news:news>';
		$img = get_the_post_thumbnail_url( $p, 'full' );
		if ( $img ) {
			echo '<image:image><image:loc>' . esc_url( $img ) . '</image:loc></image:image>';
		}
		echo "</url>\n";
	}
	echo '</urlset>';
	exit;
}

add_filter( 'robots_txt', 'eg_seo_robots_txt', 20, 2 );
function eg_seo_robots_txt( $txt, $public ) {
	if ( ! $public ) {
		return $txt;
	}
	if ( false === strpos( $txt, 'news-sitemap.xml' ) ) {
		$txt .= "\nSitemap: " . home_url( '/news-sitemap.xml' ) . "\n";
	}
	if ( false === strpos( $txt, 'wp-sitemap.xml' ) && false === strpos( $txt, 'sitemap_index.xml' ) ) {
		$txt .= 'Sitemap: ' . home_url( '/wp-sitemap.xml' ) . "\n";
	}
	// Paginile de căutare nu se indexează: regulile intră în grupul „User-agent: *”, înaintea sitemap-urilor.
	if ( false === strpos( $txt, 'Disallow: /?s=' ) ) {
		$txt = preg_replace( '#(User-agent: \*\R)#', "$1Disallow: /?s=\nDisallow: /search/\n", $txt, 1 );
	}
	return $txt;
}

/* Sitemap-ul WordPress: fără utilizatorii fără articole și fără paginile goale. */
add_filter( 'wp_sitemaps_users_query_args', fn( $a ) => array_merge( $a, array( 'has_published_posts' => array( 'post' ) ) ) );
add_filter( 'wp_sitemaps_taxonomies_query_args', fn( $a ) => array_merge( $a, array( 'hide_empty' => true ) ) );

/* ---------- Descrierile competițiilor (o dată, doar unde lipsesc) ---------- */

add_action( 'eg_import', 'eg_seo_descrieri_categorii', 50 );
function eg_seo_descrieri_categorii() {
	$d = array(
		'superliga'         => 'Știri din SuperLiga României: cronicile meciurilor, declarații, transferuri, accidentări și clasamentul la zi, de la FCSB, Dinamo, Rapid, CFR Cluj, Universitatea Craiova, U Cluj și celelalte echipe.',
		'champions-league'  => 'Champions League pe Eurogol: avancronici, cronici, declarații și clasamentul fazei principale, cu atenție la românii din competiție.',
		'europa-league'     => 'Europa League pe Eurogol: meciurile, rezultatele, clasamentul și echipele românești din competiție.',
		'conference-league' => 'Conference League pe Eurogol: meciurile echipelor românești, adversarii lor, rezultatele și clasamentul fazei principale.',
		'premier-league'    => 'Premier League pe Eurogol: cronicile etapelor, derby-uri, transferuri, românii din Anglia și clasamentul la zi.',
		'laliga'            => 'LaLiga pe Eurogol: Real Madrid, Barcelona, Atlético și restul Spaniei, cronicile etapelor, declarații și clasamentul la zi.',
		'serie-a'           => 'Serie A pe Eurogol: Inter lui Cristi Chivu, Milan, Juventus, Napoli, românii din Italia, cronicile etapelor și clasamentul la zi.',
		'bundesliga'        => 'Bundesliga pe Eurogol: Bayern, Dortmund și restul Germaniei, cronicile etapelor, transferuri și clasamentul la zi.',
		'ligue-1'           => 'Ligue 1 pe Eurogol: PSG, Marseille și restul Franței, cronicile etapelor și clasamentul la zi.',
		'povesti'           => 'Povești din fotbal: legende, meciuri care n-au murit niciodată, derby-uri și stadioane, de la Sevilla 1986 la Maracanã 1950.',
		'stiri'             => 'Ultimele știri din fotbalul românesc, european și sud-american, pe Eurogol.',
	);
	foreach ( $d as $slug => $text ) {
		$t = get_category_by_slug( $slug );
		if ( $t && '' === trim( (string) $t->description ) ) {
			wp_update_term( $t->term_id, 'category', array( 'description' => $text ) );
		}
	}
}
